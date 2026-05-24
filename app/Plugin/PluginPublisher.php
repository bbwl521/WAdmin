<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace App\Plugin;

use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginManifestException;
use Psr\Log\LoggerInterface;

/**
 * 插件发布服务.
 *
 * 将本地插件打包为 zip，通过 Marketplace API 上传到云端市场。
 */
final class PluginPublisher
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * 将插件目录打包为 zip 文件.
     *
     * @param string $pluginCode 插件标识
     * @return string zip 文件路径
     */
    public function package(string $pluginCode): string
    {
        $pluginDir = BASE_PATH . '/plugins/' . $pluginCode;

        if (! is_dir($pluginDir)) {
            throw new PluginManifestException("插件目录不存在: {$pluginCode}");
        }

        $manifest = PluginManifest::fromJsonFile($pluginDir . '/plugin.json');

        // 临时 zip 路径
        $tempDir = BASE_PATH . '/runtime/plugin_temp';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/' . $pluginCode . '-v' . $manifest->version . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建 zip 文件');
        }

        $this->addDirToZip($zip, $pluginDir, $pluginCode . '/');
        $zip->close();

        $this->logger->info("[PluginPublisher] 打包完成: {$zipPath} (" . $this->formatSize(filesize($zipPath)) . ')');

        return $zipPath;
    }

    /**
     * 校验插件是否可以发布.
     *
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(string $pluginCode): array
    {
        $errors = [];
        $pluginDir = BASE_PATH . '/plugins/' . $pluginCode;

        if (! is_dir($pluginDir)) {
            return ['valid' => false, 'errors' => ["插件目录 '{$pluginCode}' 不存在"]];
        }

        $jsonPath = $pluginDir . '/plugin.json';
        if (! file_exists($jsonPath)) {
            return ['valid' => false, 'errors' => ['缺少 plugin.json']];
        }

        try {
            $manifest = PluginManifest::fromJsonFile($jsonPath);

            if (empty($manifest->code)) {
                $errors[] = '插件标识不能为空';
            }
            if (empty($manifest->name)) {
                $errors[] = '插件名称不能为空';
            }
            if (empty($manifest->version)) {
                $errors[] = '版本号不能为空';
            }
            if (empty($manifest->description)) {
                $errors[] = '建议填写插件描述';
            }
        } catch (PluginManifestException $e) {
            return ['valid' => false, 'errors' => ['plugin.json 格式无效: ' . $e->getMessage()]];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $relativePath): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $item;
            $zipPath = $relativePath . $item;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $fullPath, $zipPath . '/');
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    private function formatSize(int|false $bytes): string
    {
        if ($bytes === false) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            ++$i;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
