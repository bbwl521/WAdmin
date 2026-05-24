<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Plugin\Exception\PluginConflictException;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class PluginRouteRegistrar
{
    private string $cacheFile;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $this->cacheFile = BASE_PATH . '/runtime/plugin_routes.php';
    }

    public function register(string $pluginDir, string $pluginCode): void
    {
        $routeFile = rtrim($pluginDir, '/') . '/routes.php';
        if (! file_exists($routeFile)) {
            return;
        }

        $routes = require $routeFile;
        if (! is_array($routes) || $routes === []) {
            return;
        }

        $this->removeFromCache($pluginCode);
        $this->addRoutes($routes);
        $this->persist($pluginCode, $routes);
    }

    public function bootFromCache(): void
    {
        if (! file_exists($this->cacheFile)) {
            return;
        }

        $allRoutes = require $this->cacheFile;
        if (! is_array($allRoutes)) {
            return;
        }

        foreach ($allRoutes as $code => $routes) {
            if (is_array($routes)) {
                $this->addRoutes($routes);
            }
        }
    }

    public function checkConflicts(string $pluginDir, string $pluginCode): void
    {
        $routeFile = rtrim($pluginDir, '/') . '/routes.php';
        if (! file_exists($routeFile)) {
            return;
        }

        $newRoutes = require $routeFile;
        if (! is_array($newRoutes)) {
            return;
        }

        $existingRoutes = $this->loadAllRegisteredRoutes();

        foreach ($newRoutes as $route) {
            if (count($route) < 3) {
                continue;
            }

            $method = strtoupper((string) $route[0]);
            $path = (string) $route[1];

            $key = "{$method}:{$path}";
            if (isset($existingRoutes[$key]) && $existingRoutes[$key] !== $pluginCode) {
                throw new PluginConflictException(
                    "路由冲突: {$method} {$path} 已被插件 '{$existingRoutes[$key]}' 注册"
                );
            }
        }
    }

    public function removeFromCache(string $pluginCode): void
    {
        if (! file_exists($this->cacheFile)) {
            return;
        }

        $allRoutes = require $this->cacheFile;
        if (! is_array($allRoutes)) {
            return;
        }

        unset($allRoutes[$pluginCode]);

        file_put_contents($this->cacheFile, '<?php return ' . var_export($allRoutes, true) . ';' . PHP_EOL);
    }

    /**
     * 添加路由到 Hyperf 路由器，重复路由自动跳过.
     *
     * FastRoute 抛出 BadRouteException (extends LogicException)，
     * Hyperf 将其包装为 RuntimeException，两者都捕获。
     */
    private function addRoutes(array $routes): void
    {
        $factory = $this->container->get(DispatcherFactory::class);
        $router = $factory->getRouter('http');
        $logger = $this->container->get(LoggerInterface::class);

        foreach ($routes as $route) {
            if (count($route) < 3) {
                continue;
            }

            [$method, $path, $handler] = $route;
            $options = $route['options'] ?? [];
            $methods = [strtoupper((string) $method)];
            $pathStr = (string) $path;

            try {
                $router->addRoute($methods, $pathStr, (string) $handler, (array) $options);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Cannot register two routes matching')
                    || str_contains($msg, 'duplicate route')
                    || str_contains($msg, 'already registered')
                ) {
                    $logger->warning("[PluginRoute] 路由已存在，跳过: {$method} {$pathStr}");
                    continue;
                }
                throw $e;
            }
        }
    }

    private function persist(string $pluginCode, array $routes): void
    {
        $allRoutes = [];
        if (file_exists($this->cacheFile)) {
            $allRoutes = require $this->cacheFile;
            if (! is_array($allRoutes)) {
                $allRoutes = [];
            }
        }

        $allRoutes[$pluginCode] = $routes;

        $dir = dirname($this->cacheFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($this->cacheFile, '<?php return ' . var_export($allRoutes, true) . ';' . PHP_EOL);
    }

    private function loadAllRegisteredRoutes(): array
    {
        $result = [];

        if (file_exists($this->cacheFile)) {
            $allRoutes = require $this->cacheFile;
            if (is_array($allRoutes)) {
                foreach ($allRoutes as $code => $routes) {
                    if (! is_array($routes)) {
                        continue;
                    }
                    foreach ($routes as $route) {
                        if (count($route) >= 2) {
                            $key = strtoupper((string) $route[0]) . ':' . (string) $route[1];
                            $result[$key] = (string) $code;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
