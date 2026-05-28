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
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;

/**
 * 远程插件市场服务.
 * 远程 API 不可用时自动降级到本地数据库 (marketplace_plugin 表)。
 */
final class MarketplaceService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(
        private readonly MarketplacePluginService $mktService,
        private readonly ClientFactory $clientFactory,
        private readonly LoggerInterface $logger,
        ConfigInterface $config,
    ) {
        $this->baseUrl = (string) $config->get('plugin.marketplace_url', 'https://marketplace.mineadmin.com');
        $this->timeout = (int) $config->get('plugin.marketplace_timeout', 5);
    }

    /** 获取市场插件列表 */
    public function search(array $filters = []): array
    {
        try {
            $client = $this->clientFactory->create([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
            ]);
            $response = $client->get('/api/v1/plugins', [
                'query' => array_filter([
                    'search' => $filters['search'] ?? null,
                    'category' => $filters['category'] ?? null,
                    'page' => $filters['page'] ?? 1,
                    'page_size' => $filters['page_size'] ?? 20,
                ]),
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body)) {
                return $body;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Marketplace] 远程搜索不可用: ' . $e->getMessage());
        }

        return $this->mktService->search($filters);
    }

    /** 获取插件详情 */
    public function detail(string $code): ?array
    {
        try {
            $client = $this->clientFactory->create([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
            ]);
            $response = $client->get("/api/v1/plugins/{$code}");
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body)) {
                return $body;
            }
        } catch (\Throwable $e) {
            $this->logger->warning("[Marketplace] 获取详情失败: {$code}");
        }

        return $this->mktService->detail($code);
    }

    /** 获取下载地址 */
    public function getDownloadUrl(string $code, string $version = ''): ?string
    {
        try {
            $client = $this->clientFactory->create([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
            ]);
            $query = $version !== '' ? ['version' => $version] : [];
            $response = $client->get("/api/v1/plugins/{$code}/download", ['query' => $query]);
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body) && isset($body['url'])) {
                return (string) $body['url'];
            }
        } catch (\Throwable $e) {
            $this->logger->warning("[Marketplace] 获取下载地址失败: {$code}");
        }

        return $this->mktService->getDownloadUrl($code);
    }

    /** 提交插件到远程市场 */
    public function submit(string $zipPath, array $meta, string $apiToken): array
    {
        try {
            $client = $this->clientFactory->create([
                'base_uri' => $this->baseUrl,
                'timeout' => $this->timeout,
                'headers' => ['Authorization' => 'Bearer ' . $apiToken],
            ]);
            $response = $client->post('/api/v1/plugins/submit', [
                'multipart' => [
                    ['name' => 'file', 'contents' => fopen($zipPath, 'r'), 'filename' => basename($zipPath)],
                    ['name' => 'meta', 'contents' => json_encode($meta, JSON_UNESCAPED_UNICODE)],
                ],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return is_array($body) ? $body : ['success' => false, 'message' => '远程市场返回格式无效'];
        } catch (\Throwable $e) {
            $this->logger->error('[Marketplace] 提交失败: ' . $e->getMessage());
            return ['success' => false, 'message' => '提交失败: ' . $e->getMessage()];
        }
    }
}
