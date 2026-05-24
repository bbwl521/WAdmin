<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 */

namespace App\Plugin;

use App\Model\MarketplacePlugin;
use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginConflictException;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Psr\Log\LoggerInterface;

final class MarketplacePluginService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /** 市场插件列表（从 DB 读取） */
    public function search(array $filters = []): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = (int) ($filters['page_size'] ?? 20);

        $query = MarketplacePlugin::query()
            ->where('status', 1)
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->orderBy('id', 'desc');

        $total = (int) $query->count();
        $items = $query->forPage($page, $pageSize)->get()->toArray();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    /** 获取单个市场插件 */
    public function detail(string $code): ?array
    {
        $plugin = MarketplacePlugin::query()->where('code', $code)->first();
        return $plugin?->toArray();
    }

    /** 获取下载地址 */
    public function getDownloadUrl(string $code): ?string
    {
        $plugin = MarketplacePlugin::query()->where('code', $code)->where('status', 1)->first();
        return $plugin?->download_url ?: null;
    }

    /** 上传插件包 */
    public function upload(UploadedFile $file): MarketplacePlugin
    {
        $tempDir = BASE_PATH . '/runtime/plugin_temp';
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . uniqid('upload_', true) . '.zip';
        $file->moveTo($tempPath);

        // 提取 plugin.json
        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new \RuntimeException('无法打开 zip 文件');
        }

        $manifestData = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (basename($name) === 'plugin.json') {
                $manifestData = json_decode($zip->getFromIndex($i), true);
                break;
            }
        }
        $zip->close();

        if (! is_array($manifestData)) {
            @unlink($tempPath);
            throw new \RuntimeException('插件包中未找到 plugin.json');
        }

        $manifest = PluginManifest::fromArray($manifestData);

        // 保存到仓库
        $repoDir = BASE_PATH . '/storage/plugins/repo';
        if (! is_dir($repoDir)) {
            @mkdir($repoDir, 0755, true);
        }
        $repoPath = $repoDir . '/' . $manifest->code . '-v' . $manifest->version . '.zip';
        rename($tempPath, $repoPath);

        $downloadUrl = rtrim(env('APP_URL', 'http://127.0.0.1:9501'), '/') . '/plugin-repo/' . basename($repoPath);

        // 写入数据库
        $existing = MarketplacePlugin::query()->where('code', $manifest->code)->first();
        if ($existing) {
            throw new PluginConflictException("插件 '{$manifest->code}' 已存在于市场中，请先下架再更新");
        }

        $plugin = MarketplacePlugin::create([
            'code' => $manifest->code,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'author' => $manifest->author['name'] ?? '',
            'category' => '工具',
            'icon' => 'ri:plug-line',
            'download_url' => $downloadUrl,
            'downloads' => 0,
            'status' => 1,
            'meta' => $manifest->raw,
        ]);

        $this->logger->info("[Marketplace] 上传插件: {$manifest->code} v{$manifest->version}");

        return $plugin;
    }

    /** 下架插件 */
    public function unpublish(string $code): void
    {
        MarketplacePlugin::query()->where('code', $code)->update(['status' => 2]);
    }

    /** 重新上架 */
    public function republish(string $code): void
    {
        MarketplacePlugin::query()->where('code', $code)->update(['status' => 1]);
    }

    /** 删除市场插件 */
    public function delete(string $code): void
    {
        MarketplacePlugin::query()->where('code', $code)->delete();
    }
}
