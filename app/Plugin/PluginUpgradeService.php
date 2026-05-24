<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\Plugin as PluginModel;
use App\Plugin\Contract\PluginInterface;
use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginConflictException;
use App\Plugin\Exception\PluginNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class PluginUpgradeService
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

    public function upgradeFromLocal(string $pluginDir): PluginModel
    {
        $manifest = PluginManifest::fromJsonFile($pluginDir . '/plugin.json');
        $existing = PluginModel::query()->where('code', $manifest->code)->first();

        if ($existing === null) {
            throw new PluginNotFoundException("插件 '{$manifest->code}' 未安装，无法升级");
        }

        $this->guardCanUpgrade($existing->version, $manifest->version);
        $this->checkDependencies($manifest);

        $oldPluginDir = $this->pluginsDir . '/' . $manifest->code;

        $backupDir = BASE_PATH . '/runtime/plugin_backup/' . $manifest->code . '_v' . $existing->version . '_' . time();
        if (is_dir($oldPluginDir)) {
            if (! is_dir(dirname($backupDir))) {
                @mkdir(dirname($backupDir), 0755, true);
            }
            rename($oldPluginDir, $backupDir);
        }

        try {
            $this->autoloader->register($manifest->code, $manifest->getPsr4Mappings(), $pluginDir);

            foreach ($manifest->getPsr4Mappings() as $namespace => $relativePath) {
                $absolutePath = rtrim($pluginDir, '/') . '/' . ltrim($relativePath, '/');
                $this->autoloader->addScanPath($manifest->code, $absolutePath);
                $this->autoloader->persistScanPath($absolutePath);
                $this->autoloader->persistAutoload($namespace, $absolutePath);
            }

            $this->invokeLifecycleByModel($existing, $backupDir, 'onBeforeUpgrade');

            $this->migrationRunner->runUp($pluginDir, $manifest->code);

            $this->routeRegistrar->removeFromCache($manifest->code);
            $this->routeRegistrar->register($pluginDir, $manifest->code);

            $oldManifest = $this->loadManifestFromBackup($backupDir);
            if ($oldManifest !== null) {
                $this->menuRegistrar->unregister($this->collectAllNames($oldManifest));
            }
            $this->menuRegistrar->register($manifest->menus, $manifest->permissions);

            rename($pluginDir, $oldPluginDir);

            $existing->update([
                'version' => $manifest->version,
                'name' => $manifest->name,
                'meta' => $manifest->raw,
            ]);

            $this->invokeLifecycle($manifest, $oldPluginDir, 'onUpgrade');

            $this->logger->info("[Plugin] 升级完成: {$manifest->code} {$existing->version} → {$manifest->version}");

            $this->rmdirRecursive($backupDir);

            $existing->refresh();

            return $existing;
        } catch (\Throwable $e) {
            $this->logger->error("[Plugin] 升级失败，回滚: {$manifest->code} - " . $e->getMessage());

            if (is_dir($oldPluginDir)) {
                $this->rmdirRecursive($oldPluginDir);
            }
            if (is_dir($backupDir)) {
                rename($backupDir, $oldPluginDir);
            }

            throw $e;
        }
    }

    public function upgradeFromZip(string $zipPath): PluginModel
    {
        if (! file_exists($zipPath)) {
            throw new \RuntimeException('插件包不存在: ' . $zipPath);
        }

        $tempDir = BASE_PATH . '/runtime/plugin_temp/' . uniqid('upgrade_', true);
        $this->extractZip($zipPath, $tempDir);
        $pluginDir = $this->findPluginRoot($tempDir);

        try {
            return $this->upgradeFromLocal($pluginDir);
        } finally {
            $this->rmdirRecursive($tempDir);
        }
    }

    public function upgradeFromRemote(string $downloadUrl, string $expectedHash = ''): PluginModel
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

            return $this->upgradeFromZip($tempZip);
        } finally {
            @unlink($tempZip);
        }
    }

    private function guardCanUpgrade(string $oldVersion, string $newVersion): void
    {
        if (version_compare($newVersion, $oldVersion, '<=')) {
            throw new PluginConflictException(
                "新版本 {$newVersion} 不高于当前版本 {$oldVersion}，无法升级"
            );
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

    private function loadManifestFromBackup(string $backupDir): ?PluginManifest
    {
        $jsonPath = $backupDir . '/plugin.json';
        if (! file_exists($jsonPath)) {
            return null;
        }
        try {
            return PluginManifest::fromJsonFile($jsonPath);
        } catch (\Throwable) {
            return null;
        }
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

    private function invokeLifecycleByModel(PluginModel $plugin, string $pluginDir, string $method): void
    {
        $meta = $plugin->meta;
        if (! is_array($meta)) {
            return;
        }
        $manifest = PluginManifest::fromArray($meta);
        $this->invokeLifecycle($manifest, $pluginDir, $method);
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
        throw new \RuntimeException('插件包中未找到 plugin.json');
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
