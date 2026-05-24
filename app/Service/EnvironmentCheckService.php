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

namespace App\Service;

class EnvironmentCheckService
{
    private array $requirements = [];

    private array $phpExtensions = [
        'pdo_mysql' => ['name' => 'PDO MySQL', 'required' => true],
        'mbstring' => ['name' => 'MBString', 'required' => true],
        'json' => ['name' => 'JSON', 'required' => true],
        'openssl' => ['name' => 'OpenSSL', 'required' => true],
        'curl' => ['name' => 'cURL', 'required' => true],
        'zip' => ['name' => 'ZIP', 'required' => true],
        'fileinfo' => ['name' => 'FileInfo', 'required' => true],
        'tokenizer' => ['name' => 'Tokenizer', 'required' => true, 'check' => 'function'],
        'xml' => ['name' => 'XML', 'required' => true],
        'pcntl' => ['name' => 'PCNTL (异步支持)', 'required' => false],
        'swoole' => ['name' => 'Swoole', 'required' => false],
        'redis' => ['name' => 'Redis', 'required' => false],
        'pcre' => ['name' => 'PCRE', 'required' => true],
        'gd' => ['name' => 'GD', 'required' => false],
    ];

    private array $directories = [
        'runtime' => ['name' => 'runtime (运行缓存)', 'required' => true, 'recursive' => true],
        'storage' => ['name' => 'storage (存储)', 'required' => true, 'recursive' => true],
        'public' => ['name' => 'public (公共目录)', 'required' => true, 'recursive' => false],
        'config' => ['name' => 'config (配置目录)', 'required' => true, 'recursive' => false],
    ];

    private array $phpConfigurations = [
        'memory_limit' => ['name' => 'memory_limit', 'required' => true, 'min' => '128M', 'suggest' => '256M'],
        'max_execution_time' => ['name' => 'max_execution_time', 'required' => true, 'min' => 30, 'suggest' => 300],
        'upload_max_filesize' => ['name' => 'upload_max_filesize', 'required' => false, 'min' => '2M', 'suggest' => '20M'],
        'post_max_size' => ['name' => 'post_max_size', 'required' => false, 'min' => '2M', 'suggest' => '20M'],
    ];

    public function __construct()
    {
        $this->checkAll();
    }

    public function checkAll(): array
    {
        $this->requirements = [
            'php' => $this->checkPhp(),
            'php_extensions' => $this->checkPhpExtensions(),
            'directories' => $this->checkDirectories(),
            'php_config' => $this->checkPhpConfiguration(),
        ];

        return $this->requirements;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function isReady(): bool
    {
        $this->checkAll();

        // 检查 PHP 版本
        if (! $this->requirements['php']['passed']) {
            return false;
        }

        // 检查必需的扩展
        foreach ($this->requirements['php_extensions'] as $ext) {
            if ($ext['required'] && ! $ext['passed']) {
                return false;
            }
        }

        // 检查必需的目录
        foreach ($this->requirements['directories'] as $dir) {
            if ($dir['required'] && ! $dir['passed']) {
                return false;
            }
        }

        return true;
    }

    public function getSummary(): array
    {
        $this->checkAll();

        $passedCount = 0;
        /** @var int $totalCount */
        $totalCount = 0;
        $errors = [];

        // PHP 版本
        ++$totalCount;
        if ($this->requirements['php']['passed']) {
            ++$passedCount;
        } else {
            $errors[] = $this->requirements['php']['message'];
        }

        // PHP 扩展
        foreach ($this->requirements['php_extensions'] as $ext) {
            ++$totalCount;
            if ($ext['passed']) {
                ++$passedCount;
            } elseif ($ext['required']) {
                $errors[] = "缺少必需扩展: {$ext['name']}";
            }
        }

        // 目录权限
        foreach ($this->requirements['directories'] as $dir) {
            ++$totalCount;
            if ($dir['passed']) {
                ++$passedCount;
            } elseif ($dir['required']) {
                $errors[] = "目录不可写: {$dir['name']}";
            }
        }

        return [
            'passed' => $passedCount === $totalCount,
            'passed_count' => $passedCount,
            'total_count' => $totalCount,
            'percentage' => $totalCount > 0 ? round($passedCount / $totalCount * 100) : 0,
            'errors' => $errors,
        ];
    }

    private function checkPhp(): array
    {
        $version = \PHP_VERSION;
        $minVersion = '8.2.0';
        $passed = version_compare($version, $minVersion, '>=');

        return [
            'name' => 'PHP 版本',
            'version' => $version,
            'required' => $minVersion,
            'passed' => $passed,
            'message' => $passed
                ? "PHP 版本: {$version}"
                : "PHP 版本过低，需要 {$minVersion} 或更高版本 (当前: {$version})",
        ];
    }

    private function checkPhpExtensions(): array
    {
        $extensions = [];
        $loadedExtensions = get_loaded_extensions();

        foreach ($this->phpExtensions as $key => $ext) {
            $checkType = $ext['check'] ?? 'extension';
            $isLoaded = false;

            if ($checkType === 'function') {
                // 通过检查函数是否存在来判断
                $isLoaded = \function_exists('token_get_all');
            } else {
                $isLoaded = \in_array($key, $loadedExtensions, true) || \extension_loaded($key);
            }

            $extensions[$key] = [
                'name' => $ext['name'],
                'extension' => $key,
                'required' => $ext['required'],
                'loaded' => $isLoaded,
                'passed' => $isLoaded || ! $ext['required'],
                'message' => $isLoaded
                    ? "{$ext['name']} 扩展已加载"
                    : ($ext['required']
                        ? "缺少必需扩展: {$ext['name']}"
                        : "可选扩展: {$ext['name']} (未安装)"),
            ];
        }

        return $extensions;
    }

    private function checkDirectories(): array
    {
        $directories = [];

        foreach ($this->directories as $path => $dir) {
            $fullPath = BASE_PATH . '/' . $path;
            $exists = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);

            // 如果要求递归检查子目录
            if ($exists && $dir['recursive'] && $writable) {
                $writable = $this->checkDirectoryRecursive($fullPath);
            }

            $directories[$path] = [
                'name' => $dir['name'],
                'path' => $path,
                'full_path' => $fullPath,
                'exists' => $exists,
                'writable' => $writable,
                'required' => $dir['required'],
                'recursive' => $dir['recursive'],
                'passed' => $exists && $writable,
                'message' => ! $exists
                    ? "目录不存在: {$dir['name']}"
                    : (! $writable
                        ? "目录不可写: {$dir['name']}"
                        : "目录正常: {$dir['name']}"),
            ];
        }

        return $directories;
    }

    private function checkDirectoryRecursive(string $dir): bool
    {
        if (! is_writable($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && ! $this->checkDirectoryRecursive($path)) {
                return false;
            }
        }

        return true;
    }

    private function checkPhpConfiguration(): array
    {
        $configs = [];

        foreach ($this->phpConfigurations as $key => $config) {
            $value = \ini_get($key);
            $minValue = $config['min'];
            $passed = $this->compareConfigValue($value, $minValue);

            $configs[$key] = [
                'name' => $config['name'],
                'current' => $value,
                'required' => $minValue,
                'suggest' => $config['suggest'] ?? null,
                'required_check' => $passed,
                'passed' => $passed || ! $config['required'],
                'message' => $passed
                    ? "{$config['name']}: {$value}"
                    : "{$config['name']} 过低，需要 {$minValue} 或更高 (当前: {$value})",
            ];
        }

        return $configs;
    }

    private function compareConfigValue(string $value, $minValue): bool
    {
        // 0 表示无限制，在 Swoole 环境下是正常的
        if ($value === '0' || mb_strtolower($value) === 'unlimited') {
            return true;
        }

        // 处理内存限制 (如 128M, 256M)
        if (\is_string($minValue) && preg_match('/^(\d+)(M|K|G)?$/i', $minValue, $minMatches)) {
            $minNum = (int) $minMatches[1];
            $minUnit = mb_strtoupper($minMatches[2] ?? 'M');

            if (preg_match('/^(\d+)(M|K|G)?$/i', $value, $valMatches)) {
                $valNum = (int) $valMatches[1];
                $valUnit = mb_strtoupper($valMatches[2] ?? 'M');

                $minBytes = $this->convertToBytes($minNum, $minUnit);
                $valBytes = $this->convertToBytes($valNum, $valUnit);

                return $valBytes >= $minBytes;
            }
        }

        // 处理数字 (如 max_execution_time)
        if (is_numeric($minValue)) {
            return (int) $value >= (int) $minValue;
        }

        return true;
    }

    private function convertToBytes(int $num, string $unit): int
    {
        $units = ['K' => 1024, 'M' => 1024 * 1024, 'G' => 1024 * 1024 * 1024];
        return $num * ($units[$unit] ?? 1);
    }
}
