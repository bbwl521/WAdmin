<?php

declare(strict_types=1);

namespace App\Http\Common\Middleware;

use App\Model\Plugin;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 插件守卫中间件.
 *
 * 拦截已禁用插件的路由，返回 403。
 * 插件状态缓存时间可通过 config/autoload/plugin.php 中的 guard_cache_ttl 配置。
 */
final class PluginGuardMiddleware implements MiddlewareInterface
{
    /** @var array<string, bool>|null */
    private ?array $disabledMap = null;

    private int $lastFetchTime = 0;

    private int $cacheTtl;

    public function __construct(ConfigInterface $config)
    {
        $this->cacheTtl = (int) $config->get('plugin.guard_cache_ttl', 60);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        $pluginCode = $this->extractPluginCode($path);
        if ($pluginCode === null) {
            return $handler->handle($request);
        }

        $disabledMap = $this->getDisabledMap();

        if (! empty($disabledMap[$pluginCode])) {
            /** @var HttpResponse $response */
            $response = di()->get(HttpResponse::class);

            return $response->json([
                'code' => 403,
                'message' => "插件「{$pluginCode}」已停用，请联系管理员启用",
                'data' => [],
            ])->withStatus(403);
        }

        return $handler->handle($request);
    }

    /**
     * 从请求路径中提取插件标识.
     * 例如 /admin/feedback/xxx → feedback
     */
    private function extractPluginCode(string $path): ?string
    {
        $disabledMap = $this->getDisabledMap();
        if ($disabledMap === []) {
            return null;
        }

        foreach ($disabledMap as $code => $disabled) {
            if (str_contains($path, '/' . $code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * 获取已禁用插件映射（缓存 N 秒）.
     *
     * @return array<string, bool> [code => true if disabled]
     */
    private function getDisabledMap(): array
    {
        $now = time();

        if ($this->disabledMap !== null && ($now - $this->lastFetchTime) < $this->cacheTtl) {
            return $this->disabledMap;
        }

        $this->disabledMap = Plugin::query()
            ->where('status', 2)
            ->pluck('code')
            ->mapWithKeys(static fn (string $code) => [$code => true])
            ->toArray();

        $this->lastFetchTime = $now;

        return $this->disabledMap;
    }
}
