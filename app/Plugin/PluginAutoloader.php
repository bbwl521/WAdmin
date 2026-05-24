<?php

declare(strict_types=1);
/**
 * This file is part of WAdmin.
 *
 * @link     https://github.com/bbwl521/WAdmin
 * @document https://github.com/bbwl521/WAdmin
 * @contact  admin@wadmin.local
 * @license  https://github.com/bbwl521/WAdmin/blob/master/LICENSE
 */

namespace App\Plugin;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * 运行时 PSR-4 自动加载器.
 *
 * 将插件目录动态注册到 Composer autoload 和 Hyperf 注解扫描路径，
 * 无需执行 composer dump-autoload 或重启服务。
 */
final class PluginAutoloader
{
    /** @var array<string, string> 已注册的命名空间映射 */
    private array $registered = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * 为插件注册 PSR-4 自动加载.
     *
     * @param string $pluginCode 插件标识
     * @param array<string, string> $psr4Mappings 命名空间 → 相对路径映射
     * @param string $pluginDir 插件根目录绝对路径
     */
    public function register(string $pluginCode, array $psr4Mappings, string $pluginDir): void
    {
        if (empty($psr4Mappings)) {
            return;
        }

        $loader = $this->getComposerLoader();
        $registeredNamespaces = [];

        foreach ($psr4Mappings as $namespace => $relativePath) {
            $absolutePath = rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/');
            $loader->addPsr4($namespace, $absolutePath, prepend: true);
            $registeredNamespaces[$namespace] = $absolutePath;
        }

        $this->registered[$pluginCode] = $registeredNamespaces;
    }

    /**
     * 将插件目录注入 Hyperf 注解扫描路径.
     */
    public function addScanPath(string $pluginCode, string $srcPath): void
    {
        $config = $this->container->get(ConfigInterface::class);
        $scanPaths = (array) $config->get('annotations.scan.paths', []);

        if (! in_array($srcPath, $scanPaths, true)) {
            $scanPaths[] = $srcPath;
            $config->set('annotations.scan.paths', $scanPaths);
        }
    }

    /**
     * 追加到 runtime 插件注册缓存（服务重启时自动加载）.
     */
    public function persistScanPath(string $srcPath): void
    {
        $cacheFile = BASE_PATH . '/runtime/plugins_scan_paths.php';
        $paths = [];

        if (file_exists($cacheFile)) {
            $paths = require $cacheFile;
            if (! is_array($paths)) {
                $paths = [];
            }
        }

        if (! in_array($srcPath, $paths, true)) {
            $paths[] = $srcPath;

            $content = '<?php return ' . var_export($paths, true) . ';' . PHP_EOL;
            $dir = dirname($cacheFile);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($cacheFile, $content);
        }
    }

    /** 持久化 PSR-4 映射到 autoload 缓存 */
    public function persistAutoload(string $namespace, string $path): void
    {
        $cacheFile = BASE_PATH . '/runtime/plugin_autoload.php';
        $maps = [];
        if (file_exists($cacheFile)) {
            $maps = require $cacheFile;
            if (! is_array($maps)) {
                $maps = [];
            }
        }

        if (! isset($maps[$namespace])) {
            $maps[$namespace] = [];
        }
        if (! in_array($path, $maps[$namespace], true)) {
            $maps[$namespace][] = $path;
        }

        $dir = dirname($cacheFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($cacheFile, '<?php return ' . var_export($maps, true) . ';' . PHP_EOL);
    }

    /** 移除持久化扫描路径缓存 */
    public function removePersistedScanPath(string $srcPath): void
    {
        $cacheFile = BASE_PATH . '/runtime/plugins_scan_paths.php';
        if (! file_exists($cacheFile)) {
            return;
        }

        $paths = require $cacheFile;
        if (! is_array($paths)) {
            return;
        }

        $paths = array_values(array_filter($paths, static fn (string $p) => $p !== $srcPath));

        $content = '<?php return ' . var_export($paths, true) . ';' . PHP_EOL;
        file_put_contents($cacheFile, $content);
    }

    private function getComposerLoader(): \Composer\Autoload\ClassLoader
    {
        $loader = require BASE_PATH . '/vendor/autoload.php';
        if (! $loader instanceof \Composer\Autoload\ClassLoader) {
            throw new \RuntimeException('无法获取 Composer ClassLoader');
        }

        return $loader;
    }
}
