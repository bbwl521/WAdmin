<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\Plugin as PluginModel;
use App\Plugin\Contract\PluginInterface;
use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginNotFoundException;
use Psr\Log\LoggerInterface;

final class PluginUninstallService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PluginAutoloader $autoloader,
        private readonly PluginMigrationRunner $migrationRunner,
        private readonly PluginRouteRegistrar $routeRegistrar,
        private readonly PluginMenuRegistrar $menuRegistrar,
    ) {}

    public function uninstall(string $code, bool $keepData = false): void
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $pluginDir = BASE_PATH . '/plugins/' . $code;
        $manifest = $this->loadManifest($plugin);

        $this->invokeLifecycle($manifest, $pluginDir, 'onUninstall');

        $allNames = $this->collectAllNames($manifest);
        if ($allNames !== []) {
            $this->menuRegistrar->unregister($allNames);
        }

        if (is_dir($pluginDir . '/migrations')) {
            $this->migrationRunner->runDown($pluginDir, $code);
        }

        foreach ($manifest->getPsr4Mappings() as $relativePath) {
            $absolutePath = rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/');
            $this->autoloader->removePersistedScanPath($absolutePath);
        }

        $this->routeRegistrar->removeFromCache($code);

        if (! $keepData && is_dir($pluginDir)) {
            $this->rmdirRecursive($pluginDir);
        }

        $plugin->delete();

        $this->logger->info("[Plugin] 卸载完成: {$code}");
    }

    public function enable(string $code): void
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $pluginDir = BASE_PATH . '/plugins/' . $code;
        $manifest = $this->loadManifest($plugin);

        $this->routeRegistrar->register($pluginDir, $code);
        $this->menuRegistrar->register($manifest->menus, $manifest->permissions);

        $plugin->update(['status' => 1]);

        $this->invokeLifecycle($manifest, $pluginDir, 'onEnable');

        $this->logger->info("[Plugin] 已启用: {$code}");
    }

    public function disable(string $code): void
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $manifest = $this->loadManifest($plugin);
        $pluginDir = BASE_PATH . '/plugins/' . $code;

        $this->invokeLifecycle($manifest, $pluginDir, 'onDisable');

        $allNames = $this->collectAllNames($manifest);
        if ($allNames !== []) {
            $this->menuRegistrar->unregister($allNames);
        }

        $this->routeRegistrar->removeFromCache($code);

        $plugin->update(['status' => 2]);

        $this->logger->info("[Plugin] 已禁用: {$code}");
    }

    private function collectAllNames(PluginManifest $manifest): array
    {
        $allNames = array_column($manifest->permissions, 'name');
        foreach ($manifest->menus as $menu) {
            $allNames[] = $menu['name'] ?? '';
            foreach ($menu['children'] ?? [] as $child) {
                $allNames[] = $child['name'] ?? '';
            }
        }
        return array_values(array_filter($allNames));
    }

    private function loadManifest(PluginModel $plugin): PluginManifest
    {
        $meta = $plugin->meta;
        if (is_array($meta) && $meta !== []) {
            return PluginManifest::fromArray($meta);
        }

        $pluginDir = BASE_PATH . '/plugins/' . $plugin->code;
        return PluginManifest::fromJsonFile($pluginDir . '/plugin.json');
    }

    private function invokeLifecycle(PluginManifest $manifest, string $pluginDir, string $method): void
    {
        foreach ($manifest->getPsr4Mappings() as $namespace => $relativePath) {
            $srcPath = rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/');
            $pluginFile = $srcPath . '/Plugin.php';
            if (! file_exists($pluginFile)) {
                continue;
            }

            $expectedClass = rtrim($namespace, '\\') . '\\Plugin';

            if (! class_exists($expectedClass, false)) {
                require_once $pluginFile;
            }

            if (class_exists($expectedClass, false) && method_exists($expectedClass, $method)) {
                $instance = new $expectedClass();
                if ($instance instanceof PluginInterface) {
                    $instance->{$method}();
                }
            }
            break;
        }
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmdirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
