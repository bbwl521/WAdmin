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

class InstallProgressTracker
{
    private string $lockFile;
    private string $logFile;
    private array $progress = [];
    private array $logs = [];
    private bool $isLocked = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const STEP_INIT = 'init';
    public const STEP_ENV_CHECK = 'env_check';
    public const STEP_DB_CONFIG = 'db_config';
    public const STEP_DB_CREATE = 'db_create';
    public const STEP_MIGRATION = 'migration';
    public const STEP_SEED = 'seed';
    public const STEP_COMPLETE = 'complete';

    public function __construct()
    {
        // 统一使用 runtime/.install/ 目录下的锁文件，与 InstallService 保持一致
        $installDir = BASE_PATH . '/runtime/.install';
        if (! is_dir($installDir)) {
            @mkdir($installDir, 0755, true);
        }
        $this->lockFile = $installDir . '/process.lock';
        $this->logFile = $installDir . '/process.log';
    }

    public function acquireLock(): bool
    {
        if ($this->isLocked) {
            return true;
        }

        $lockDir = dirname($this->lockFile);
        if (! is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $lockData = [
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
            'host' => gethostname(),
        ];

        $result = file_put_contents(
            $this->lockFile,
            json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if ($result !== false) {
            $this->isLocked = true;
            $this->log(self::STEP_INIT, 'info', '安装进程已锁定');
            return true;
        }

        return false;
    }

    public function releaseLock(): bool
    {
        if (! $this->isLocked && ! file_exists($this->lockFile)) {
            return true;
        }

        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        $this->isLocked = false;
        return true;
    }

    public function isLocked(): bool
    {
        if (! file_exists($this->lockFile)) {
            return false;
        }

        $content = file_get_contents($this->lockFile);
        $data = json_decode($content, true);

        if (! $data) {
            return false;
        }

        // 检查进程是否还在运行
        if (isset($data['pid'])) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $exists = true;
            } else {
                $exists = posix_kill($data['pid'], 0);
            }

            if (! $exists) {
                $this->releaseLock();
                return false;
            }
        }

        return true;
    }

    public function getLockInfo(): ?array
    {
        if (! file_exists($this->lockFile)) {
            return null;
        }

        $content = file_get_contents($this->lockFile);
        return json_decode($content, true);
    }

    public function setProgress(string $step, string $status, ?array $data = null): void
    {
        $this->progress[$step] = [
            'status' => $status,
            'data' => $data,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->saveProgress();
    }

    public function getProgress(): array
    {
        $savedProgress = $this->loadProgress();
        if ($savedProgress) {
            $this->progress = $savedProgress;
        }

        return [
            'steps' => $this->progress,
            'overall' => $this->calculateOverallProgress(),
        ];
    }

    public function log(string $step, string $level, string $message, ?array $data = null): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'step' => $step,
            'level' => $level,
            'message' => $message,
            'data' => $data,
        ];

        $this->logs[] = $logEntry;
        $this->saveLogs();

        // 同时输出到标准输出（如果可用）
        if (php_sapi_name() === 'cli') {
            $prefix = match ($level) {
                'error' => "\033[31m✗\033[0m",
                'success' => "\033[32m✓\033[0m",
                'warning' => "\033[33m⚠\033[0m",
                'info' => "\033[36m●\033[0m",
                default => "  ",
            };
            echo sprintf(
                "[%s] %s [%s] %s%s\n",
                $logEntry['timestamp'],
                $prefix,
                strtoupper($step),
                $message,
                $data ? ' ' . json_encode($data) : ''
            );
        }
    }

    public function getLogs(): array
    {
        $savedLogs = $this->loadLogs();
        if ($savedLogs) {
            $this->logs = array_merge($this->logs, $savedLogs);
        }

        return $this->logs;
    }

    public function clearLogs(): void
    {
        $this->logs = [];
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function saveProgress(): void
    {
        $dir = dirname($this->lockFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->lockFile . '.progress',
            json_encode($this->progress, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function loadProgress(): array
    {
        $file = $this->lockFile . '.progress';
        if (! file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }

    private function saveLogs(): void
    {
        $dir = dirname($this->logFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->logFile,
            json_encode($this->logs, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function loadLogs(): array
    {
        if (! file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        return json_decode($content, true) ?: [];
    }

    private function calculateOverallProgress(): array
    {
        $steps = [
            self::STEP_ENV_CHECK => ['weight' => 10, 'name' => '环境检测'],
            self::STEP_DB_CONFIG => ['weight' => 10, 'name' => '数据库配置'],
            self::STEP_DB_CREATE => ['weight' => 15, 'name' => '创建数据库'],
            self::STEP_MIGRATION => ['weight' => 30, 'name' => '执行迁移'],
            self::STEP_SEED => ['weight' => 30, 'name' => '填充数据'],
            self::STEP_COMPLETE => ['weight' => 5, 'name' => '完成安装'],
        ];

        $totalWeight = 0;
        $completedWeight = 0;
        $currentStep = null;

        foreach ($steps as $step => $info) {
            $totalWeight += $info['weight'];

            if (isset($this->progress[$step])) {
                $status = $this->progress[$step]['status'];
                if ($status === self::STATUS_SUCCESS) {
                    $completedWeight += $info['weight'];
                } elseif ($status === self::STATUS_RUNNING) {
                    $currentStep = $step;
                }
            }
        }

        $percentage = $totalWeight > 0 ? round($completedWeight / $totalWeight * 100) : 0;

        return [
            'percentage' => $percentage,
            'completed_weight' => $completedWeight,
            'total_weight' => $totalWeight,
            'current_step' => $currentStep,
            'is_complete' => isset($this->progress[self::STEP_COMPLETE]) &&
                            $this->progress[self::STEP_COMPLETE]['status'] === self::STATUS_SUCCESS,
        ];
    }

    public function reset(): void
    {
        $this->progress = [];
        $this->logs = [];
        $this->releaseLock();
    }

    /**
     * 安装完成后清理进度和日志临时文件
     */
    public function cleanup(): void
    {
        $progressFile = $this->lockFile . '.progress';
        if (file_exists($progressFile)) {
            @unlink($progressFile);
        }
        if (file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    public function hasFailedStep(): bool
    {
        foreach ($this->progress as $step => $data) {
            if ($data['status'] === self::STATUS_FAILED) {
                return true;
            }
        }
        return false;
    }

    public function getFailedStep(): ?array
    {
        foreach ($this->progress as $step => $data) {
            if ($data['status'] === self::STATUS_FAILED) {
                return [
                    'step' => $step,
                    'data' => $data['data'] ?? null,
                ];
            }
        }
        return null;
    }
}
