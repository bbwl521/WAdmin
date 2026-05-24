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

namespace App\Http\Common\Middleware;

use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 安装接口限流中间件
 * 防止未授权用户滥用安装端点（如暴力枚举数据库、频繁触发安装）.
 *
 * 策略：基于客户端 IP 的滑动窗口限流
 * - 每分钟最多 30 次请求
 * - 超出后返回 429 Too Many Requests
 */
final class InstallRateLimitMiddleware implements MiddlewareInterface
{
    /** 限流窗口大小（秒） */
    private const WINDOW_SECONDS = 60;

    /** 窗口内最大请求数 */
    private const MAX_REQUESTS = 30;

    private string $cacheFile;

    public function __construct()
    {
        $this->cacheFile = BASE_PATH . '/runtime/.install/rate_limit.json';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 仅对 /admin/install/* 路径限流
        if (! str_starts_with($request->getUri()->getPath(), '/admin/install')) {
            return $handler->handle($request);
        }

        $clientIp = $this->getClientIp($request);

        if ($this->isRateLimited($clientIp)) {
            return (new Response())
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) self::WINDOW_SECONDS)
                ->withBody(new SwooleStream(
                    json_encode([
                        'error' => 'Too Many Requests',
                        'message' => '请求频率过高，请稍后再试',
                        'retry_after' => self::WINDOW_SECONDS,
                    ], \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT)
                ));
        }

        // 记录本次请求
        $this->recordRequest($clientIp);

        return $handler->handle($request);
    }

    /**
     * 获取客户端真实 IP.
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();

        // 优先从代理头获取
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (! empty($server[$header])) {
                $ips = explode(',', $server[$header]);
                $ip = trim(reset($ips));
                if (filter_var($ip, \FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 判断是否超出限流阈值
     */
    private function isRateLimited(string $clientIp): bool
    {
        $data = $this->loadData();
        $now = time();

        if (! isset($data[$clientIp])) {
            return false;
        }

        $windowStart = $now - self::WINDOW_SECONDS;

        // 清理过期记录
        $data[$clientIp] = array_values(array_filter(
            $data[$clientIp],
            static fn (int $ts): bool => $ts > $windowStart
        ));

        // 保存清理后的数据
        $this->saveData($data);

        return \count($data[$clientIp]) >= self::MAX_REQUESTS;
    }

    /**
     * 记录一次请求
     */
    private function recordRequest(string $clientIp): void
    {
        $data = $this->loadData();

        if (! isset($data[$clientIp])) {
            $data[$clientIp] = [];
        }

        $data[$clientIp][] = time();

        // 只保留窗口内的记录
        $windowStart = time() - self::WINDOW_SECONDS;
        $data[$clientIp] = array_values(array_filter(
            $data[$clientIp],
            static fn (int $ts): bool => $ts > $windowStart
        ));

        $this->saveData($data);
    }

    /**
     * 加载限流数据.
     * @return array<string, list<int>>
     */
    private function loadData(): array
    {
        if (! file_exists($this->cacheFile)) {
            return [];
        }

        try {
            $content = file_get_contents($this->cacheFile);
            if ($content === false) {
                return [];
            }
            $data = json_decode($content, true);
            if (\is_array($data)) {
                return $data;
            }
        } catch (\Throwable) {
            // 忽略解析错误
        }

        return [];
    }

    /**
     * 保存限流数据.
     * @param array<string, list<int>> $data
     */
    private function saveData(array $data): void
    {
        try {
            $dir = \dirname($this->cacheFile);
            if (! is_dir($dir)) {
                @mkdir($dir, 0o755, true);
            }

            @file_put_contents(
                $this->cacheFile,
                json_encode($data),
                \LOCK_EX
            );
        } catch (\Throwable) {
            // 写入失败不影响主流程
        }
    }
}
