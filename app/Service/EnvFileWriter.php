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

namespace App\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;

class EnvFileWriter
{
    /**
     * 创建 .env 配置文件.
     *
     * 基于 .env.example 模板，将用户配置写入新 .env 文件，
     * 并对特殊字符进行安全转义.
     */
    public function createEnvFile(array $config): bool
    {
        $envExample = file_get_contents(BASE_PATH . '/.env.example');
        if ($envExample === false) {
            throw new BusinessException(ResultCode::FAIL, '.env.example file not found');
        }

        $jwtSecret = $this->generateJwtSecret();

        $replacements = [
            'APP_NAME' => $config['APP_NAME'] ?? 'MineAdmin',
            'APP_ENV' => $config['APP_ENV'] ?? 'dev',
            'APP_DEBUG' => $config['APP_DEBUG'] ?? 'false',
            'DB_DRIVER' => $config['DB_DRIVER'] ?? 'mysql',
            'DB_HOST' => $config['DB_HOST'] ?? 'localhost',
            'DB_PORT' => (string) ($config['DB_PORT'] ?? 3306),
            'DB_DATABASE' => $config['DB_DATABASE'] ?? '',
            'DB_USERNAME' => $config['DB_USERNAME'] ?? 'root',
            'DB_PASSWORD' => $config['DB_PASSWORD'] ?? '',
            'DB_CHARSET' => $config['DB_CHARSET'] ?? 'utf8mb4',
            'DB_COLLATION' => $config['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
            'DB_PREFIX' => $config['DB_PREFIX'] ?? '',
            'REDIS_HOST' => $config['REDIS_HOST'] ?? '127.0.0.1',
            'REDIS_AUTH' => $config['REDIS_AUTH'] ?? '',
            'REDIS_PORT' => (string) ($config['REDIS_PORT'] ?? 6379),
            'REDIS_DB' => (string) ($config['REDIS_DB'] ?? 0),
            'APP_URL' => $config['APP_URL'] ?? 'http://127.0.0.1:9501',
            'JWT_SECRET' => $jwtSecret,
            'MINE_ACCESS_TOKEN' => '(null)',
        ];

        $envContent = $envExample;
        foreach ($replacements as $key => $value) {
            $escapedValue = $this->escapeEnvValue((string) $value);
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$escapedValue}",
                $envContent
            );
        }

        $envFile = BASE_PATH . '/.env';
        $result = file_put_contents($envFile, $envContent);

        if ($result === false) {
            throw new BusinessException(ResultCode::FAIL, 'Failed to create .env file');
        }

        return true;
    }

    /**
     * 解析 .env 文件内容为关联数组.
     *
     * 统一的解析逻辑，支持双引号转义和单引号原始值.
     */
    public function parseEnvFile(string $content): array
    {
        $config = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = $this->unquoteEnvValue(trim($value));

                if ($key !== '') {
                    $config[$key] = $value;
                }
            }
        }

        return $config;
    }

    /**
     * 将已解析的 env 配置重新加载到运行时环境变量.
     */
    public function reloadEnvConfig(): void
    {
        if (\function_exists('opcache_get_status')) {
            opcache_get_status();
        }

        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            $config = $this->parseEnvFile((string) file_get_contents($envFile));
            foreach ($config as $key => $value) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * 转义 .env 值中的特殊字符，防止破坏 .env 文件解析.
     *
     * 对包含空格、$、#、!、&、= 等字符的值进行双引号包裹和反斜杠转义.
     */
    private function escapeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s$#!\&=]/', $value)) {
            $escaped = str_replace(
                [chr(92), chr(34), chr(36), chr(10), chr(13)],
                [chr(92) . chr(92), chr(92) . chr(34), chr(92) . chr(36), chr(92) . chr(110), chr(92) . chr(114)],
                $value
            );

            return chr(34) . $escaped . chr(34);
        }

        return $value;
    }

    /**
     * 去除 .env 值的引号包裹并还原内部转义.
     */
    private function unquoteEnvValue(string $value): string
    {
        // 双引号包裹的值需要还原内部转义
        if (mb_strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            $inner = mb_substr($value, 1, -1);

            return str_replace(
                ['\"', '\$', '\n', '\r', '\\\\'],
                ['"', '$', "\n", "\r", '\\'],
                $inner
            );
        }

        // 单引号包裹的值为原始字符串
        if (mb_strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") {
            return mb_substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * 生成 JWT 密钥（32 字节随机数据 base64 编码）.
     */
    private function generateJwtSecret(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }
}
