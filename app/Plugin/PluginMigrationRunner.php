<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\PluginMigration;
use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Throwable;

final class PluginMigrationRunner
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * 执行插件 up 迁移（仅执行未追踪的迁移文件）.
     */
    public function runUp(string $pluginDir, string $pluginCode): void
    {
        $pending = $this->pendingMigrations($pluginDir, $pluginCode);
        foreach ($pending as $file => $className) {
            try {
                $this->logger->info("[PluginMigration] up: {$className}");
                $this->loadMigration($file)->up();
            } catch (Throwable $e) {
                if ($this->isTableExistsError($e)) {
                    $this->logger->warning("[PluginMigration] 表已存在，自动标记: {$className}");
                    PluginMigration::create([
                        'plugin_code' => $pluginCode,
                        'migration' => $className,
                    ]);
                    continue;
                }
                throw $e;
            }

            PluginMigration::create([
                'plugin_code' => $pluginCode,
                'migration' => $className,
            ]);
        }
    }

    /**
     * 回滚插件 down 迁移（仅回滚已追踪的迁移文件，逆序）.
     */
    public function runDown(string $pluginDir, string $pluginCode): void
    {
        $tracked = PluginMigration::query()
            ->where('plugin_code', $pluginCode)
            ->orderBy('id', 'desc')
            ->pluck('migration')
            ->toArray();

        $files = $this->collectMigrations($pluginDir);
        $toRollback = [];

        foreach ($tracked as $className) {
            foreach ($files as $file => $name) {
                if ($name === $className) {
                    $toRollback[$file] = $name;
                    break;
                }
            }
        }

        foreach ($toRollback as $file => $className) {
            $this->logger->info("[PluginMigration] down: {$className}");
            $this->loadMigration($file)->down();
            PluginMigration::query()
                ->where('plugin_code', $pluginCode)
                ->where('migration', $className)
                ->delete();
        }
    }

    private function pendingMigrations(string $pluginDir, string $pluginCode): array
    {
        $files = $this->collectMigrations($pluginDir);
        if ($files === []) {
            return [];
        }

        $tracked = PluginMigration::query()
            ->where('plugin_code', $pluginCode)
            ->pluck('migration')
            ->toArray();

        return array_filter($files, static fn (string $className) => ! in_array($className, $tracked, true));
    }

    /**
     * 安全加载迁移类.
     *
     * 先用 Token 解析器获取文件中的真实类名，
     * 如果类已存在（从另一个路径加载过）直接返回，避免重复声明。
     */
    private function loadMigration(string $file): Migration
    {
        $realClass = $this->parseClassName($file);

        if ($realClass !== null && class_exists($realClass, false)) {
            $instance = new $realClass();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }

        $beforeClasses = get_declared_classes();
        require_once $file;

        // 优先用解析出的真实类名匹配
        if ($realClass !== null && class_exists($realClass, false)) {
            $instance = new $realClass();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }

        // 回退：差集查找
        foreach (array_diff(get_declared_classes(), $beforeClasses) as $class) {
            if (is_subclass_of($class, Migration::class)) {
                return new $class();
            }
        }

        throw new \RuntimeException("迁移文件 {$file} 中未找到继承 Migration 的类");
    }

    /**
     * 用 Token 解析器提取 PHP 文件中第一个 class 声明.
     */
    private function parseClassName(string $file): ?string
    {
        if (! file_exists($file)) {
            return null;
        }

        $code = file_get_contents($file);
        if ($code === false) {
            return null;
        }

        $tokens = token_get_all($code);
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
                continue;
            }

            // 跳过匿名类
            for ($j = $i + 1; $j < $count; ++$j) {
                if (! is_array($tokens[$j])) {
                    continue;
                }
                if ($tokens[$j][0] === T_STRING) {
                    return $tokens[$j][1];
                }
                if ($tokens[$j][0] !== T_WHITESPACE && $tokens[$j][0] !== T_COMMENT && $tokens[$j][0] !== T_DOC_COMMENT) {
                    break;
                }
            }
        }

        return null;
    }

    private function collectMigrations(string $pluginDir): array
    {
        $dir = rtrim($pluginDir, '/') . '/migrations';
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.php');
        if ($files === false || $files === []) {
            return [];
        }
        sort($files, SORT_STRING);

        $result = [];
        foreach ($files as $file) {
            $result[$file] = basename($file, '.php');
        }

        return $result;
    }

    private function isTableExistsError(Throwable $e): bool
    {
        $message = $e->getMessage();
        $code = (string) $e->getCode();

        return str_contains($message, 'Base table or view already exists')
            || str_contains($message, 'already exists')
            || $code === '42S01'
            || $code === 'HY000' && str_contains($message, 'already exists');
    }
}
