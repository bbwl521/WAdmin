<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\Plugin as PluginModel;
use App\Plugin\Contract\PluginInterface;
use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginConflictException;
use App\Plugin\Exception\PluginManifestException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class PluginInstallService
{
    private string $pluginsDir;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
        private readonly PluginAutoloader $autoloader,
        private readonly PluginMigrationRunner $migrationRunner,
        private readonly PluginRouteRegistrar $routeRegistrar,
        private readonly PluginMenuRegistrar $menuRegistrar,
    ) {
        $this->pluginsDir = BASE_PATH . '/plugins';
    }

    public function installFromLocal(string $pluginDir): PluginModel
    {
        $manifest = PluginManifest::fromJsonFile($pluginDir . '/plugin.json');
        $this->guardNotInstalled($manifest->code);
        $this->checkDependencies($manifest);
        $this->checkConflicts($manifest, $pluginDir);

        $autoloadRegistered = false;
        $scanPathsAdded = false;
        $migrationRun = false;
        $routeRegistered = false;
        $menuRegistered = false;

        try {
            $this->autoloader->register($manifest->code, $manifest->getPsr4Mappings(), $pluginDir);
            $autoloadRegistered = true;

            foreach ($manifest->getPsr4Mappings() as $namespace => $relativePath) {
                $absolutePath = rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/');
                $this->autoloader->addScanPath($manifest->code, $absolutePath);
                $this->autoloader->persistScanPath($absolutePath);
                $this->autoloader->persistAutoload($namespace, $absolutePath);
            }
            $scanPathsAdded = true;

            $this->migrationRunner->runUp($pluginDir, $manifest->code);
            $migrationRun = true;

            $this->routeRegistrar->register($pluginDir, $manifest->code);
            $routeRegistered = true;

            $this->menuRegistrar->register($manifest->menus, $manifest->permissions);
            $menuRegistered = true;

            $plugin = PluginModel::create([
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'source' => 'marketplace',
                'status' => 1,
                'config' => null,
                'meta' => $manifest->raw,
            ]);

            $this->invokeLifecycle($manifest, $pluginDir, 'onInstall');

            $this->logger->info("[Plugin] 安装完成: {$manifest->code} v{$manifest->version}");

            return $plugin;
        } catch (\Throwable $e) {
            $this->logger->error("[Plugin] 安装失败，执行回滚: {$manifest->code} - " . $e->getMessage());

            if ($menuRegistered) {
                try {
                    $this->menuRegistrar->unregister($this->collectAllNames($manifest));
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('[Plugin] 回滚菜单失败: ' . $rollbackError->getMessage());
                }
            }

            if ($routeRegistered) {
                try {
                    $this->routeRegistrar->removeFromCache($manifest->code);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('[Plugin] 回滚路由失败: ' . $rollbackError->getMessage());
                }
            }

            if ($migrationRun) {
                try {
                    $this->migrationRunner->runDown($pluginDir, $manifest->code);
                } catch (\Throwable $rollbackError) {
                    $this->logger->error('[Plugin] 回滚迁移失败: ' . $rollbackError->getMessage());
                }
            }

            if ($scanPathsAdded) {
                foreach ($manifest->getPsr4Mappings() as $relativePath) {
                    $this->autoloader->removePersistedScanPath(
                        rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/')
                    );
                }
            }

            throw $e;
        }
    }

    public function installFromZip(string $zipPath): PluginModel
    {
        if (! file_exists($zipPath)) {
            throw new \RuntimeException('插件包不存在: ' . $zipPath);
        }

        $tempDir = BASE_PATH . '/runtime/plugin_temp/' . uniqid('install_', true);
        $this->extractZip($zipPath, $tempDir);
        $pluginDir = $this->findPluginRoot($tempDir);

        try {
            $model = $this->installFromLocal($pluginDir);

            $targetDir = $this->pluginsDir . '/' . $model->code;
            if (is_dir($targetDir)) {
                $this->rmdirRecursive($targetDir);
            }
            rename($pluginDir, $targetDir);

            $this->fixAutoloadPaths($tempDir, $targetDir);
            $this->rmdirRecursive($tempDir);

            return $model;
        } catch (\Throwable $e) {
            $this->rmdirRecursive($tempDir);
            throw $e;
        }
    }

    public function installFromRemote(string $downloadUrl, string $expectedHash = ''): PluginModel
    {
        $tempZip = BASE_PATH . '/runtime/plugin_temp/' . uniqid('download_', true) . '.zip';
        $tempDir = dirname($tempZip);
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        try {
            $content = file_get_contents($downloadUrl);
            if ($content === false) {
                throw new \RuntimeException('下载插件失败: ' . $downloadUrl);
            }
            file_put_contents($tempZip, $content);

            if ($expectedHash !== '' && $expectedHash !== hash_file('sha256', $tempZip)) {
                @unlink($tempZip);
                throw new \RuntimeException('插件包哈希校验失败');
            }

            return $this->installFromZip($tempZip);
        } finally {
            @unlink($tempZip);
        }
    }

    private function fixAutoloadPaths(string $oldBase, string $newBase): void
    {
        $cacheFile = BASE_PATH . '/runtime/plugin_autoload.php';
        if (! file_exists($cacheFile)) {
            return;
        }

        $maps = require $cacheFile;
        if (! is_array($maps)) {
            return;
        }

        $updated = false;
        foreach ($maps as $namespace => &$paths) {
            foreach ($paths as &$path) {
                if (str_starts_with($path, $oldBase)) {
                    $path = str_replace($oldBase, $newBase, $path);
                    $updated = true;
                }
            }
        }
        unset($paths);

        if ($updated) {
            file_put_contents($cacheFile, '<?php return ' . var_export($maps, true) . ';' . PHP_EOL);
        }
    }

    private function guardNotInstalled(string $code): void
    {
        if (PluginModel::query()->where('code', $code)->exists()) {
            throw new PluginConflictException("插件 '{$code}' 已安装");
        }
    }

    private function checkDependencies(PluginManifest $manifest): void
    {
        $versionChecker = new VersionChecker();

        foreach ($manifest->dependencies as $depCode => $depVersion) {
            $dep = PluginModel::query()->where('code', $depCode)->first();
            if ($dep === null) {
                throw new PluginConflictException("缺少依赖插件: {$depCode}");
            }
            if ($dep->status !== 1) {
                throw new PluginConflictException("依赖插件 '{$depCode}' 未启用");
            }
            if (! $versionChecker->satisfies($dep->version, (string) $depVersion)) {
                throw new PluginConflictException(
                    "依赖插件 '{$depCode}' 版本不满足: 需要 {$depVersion}，当前为 {$dep->version}"
                );
            }
        }
    }

    private function checkConflicts(PluginManifest $manifest, string $pluginDir): void
    {
        $this->routeRegistrar->checkConflicts($pluginDir, $manifest->code);
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

    private function extractZip(string $zipPath, string $targetDir): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开 zip 文件');
        }
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $zip->extractTo($targetDir);
        $zip->close();
    }

    private function findPluginRoot(string $dir): string
    {
        if (file_exists($dir . '/plugin.json')) {
            return $dir;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $subDir = $dir . '/' . $item;
            if (is_dir($subDir) && file_exists($subDir . '/plugin.json')) {
                return $subDir;
            }
        }
        throw new PluginManifestException('插件包中未找到 plugin.json');
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
