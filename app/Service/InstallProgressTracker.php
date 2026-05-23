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

    private string $lockFile;

    private string $logFile;

    /** @var null|resource flock 文件句柄 */
    private mixed $lockHandle = null;

    private array $progress = [];

    private array $logs = [];

    private bool $isLocked = false;

    public function __construct()
    {
        // 统一使用 runtime/.install/ 目录下的锁文件，与 InstallService 保持一致
        $installDir = BASE_PATH . '/runtime/.install';
        if (! is_dir($installDir)) {
            @mkdir($installDir, 0o755, true);
        }
        $this->lockFile = $installDir . '/process.lock';
        $this->logFile = $installDir . '/process.log';
    }

    public function acquireLock(): bool
    {
        if ($this->isLocked) {
            return true;
        }

        // 先检查并清理僵尸锁（进程已退出但锁文件残留的情况）
        $this->cleanupStaleLock();

        $lockDir = \dirname($this->lockFile);
        if (! is_dir($lockDir)) {
            mkdir($lockDir, 0o755, true);
        }

        $lockData = [
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
            'host' => gethostname(),
        ];

        // 使用原生 flock 进行跨进程/协程安全的互斥锁
        $fp = @fopen($this->lockFile . '.flock', 'c+');
        if ($fp === false) {
            // 回退到非阻塞方式
            return $this->acquireLockFallback($lockData);
        }

        // 非阻塞获取排他锁（LOCK_EX | LOCK_NB）
        if (! flock($fp, \LOCK_EX | \LOCK_NB)) {
            fclose($fp);
            return false;
        }

        // 写入锁数据
        ftruncate($fp, 0);
        fwrite($fp, json_encode($lockData, \JSON_UNESCAPED_UNICODE));
        fflush($fp);

        // 保存文件句柄以便后续释放
        $this->lockHandle = $fp;
        $this->isLocked = true;
        $this->log(self::STEP_INIT, 'info', '安装进程已锁定');
        return true;
    }

    public function releaseLock(): bool
    {
        if (! $this->isLocked && ! file_exists($this->lockFile)) {
            return true;
        }

        // 释放 flock 句柄
        if ($this->lockHandle !== null && \is_resource($this->lockHandle)) {
            @flock($this->lockHandle, \LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
        }

        // 只删除当前进程创建的锁文件（防止误删其他进程的锁）
        $canDelete = false;
        if (file_exists($this->lockFile)) {
            try {
                $content = file_get_contents($this->lockFile);
                $data = json_decode($content, true);
                if (\is_array($data) && isset($data['pid']) && (int) $data['pid'] === getmypid()) {
                    @unlink($this->lockFile);
                    $canDelete = true;
                }
            } catch (\Throwable) {
                // 无法解析时也允许清理
                @unlink($this->lockFile);
                $canDelete = true;
            }
        }

        // 仅在锁归属当前进程时清理日志和进度
        if ($canDelete || $this->isLocked) {
            if (file_exists($this->logFile)) {
                @unlink($this->logFile);
            }

            $progressFile = $this->lockFile . '.progress';
            if (file_exists($progressFile)) {
                @unlink($progressFile);
            }
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

        if (! \is_array($data)) {
            return false;
        }

        // 检查进程是否还在运行（复用 cleanupStaleLock 的逻辑）
        if (isset($data['pid'])) {
            $pid = (int) $data['pid'];
            $exists = true;

            if (mb_strtoupper(mb_substr(\PHP_OS, 0, 3)) === 'WIN') {
                $exists = true;
            } elseif (\function_exists('posix_kill')) {
                $exists = @posix_kill($pid, 0);
            } else {
                $exists = \PHP_OS_FAMILY === 'Linux' ? is_readable("/proc/{$pid}") : true;
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
        if (\PHP_SAPI === 'cli') {
            $prefix = match ($level) {
                'error' => "\033[31m✗\033[0m",
                'success' => "\033[32m✓\033[0m",
                'warning' => "\033[33m⚠\033[0m",
                'info' => "\033[36m●\033[0m",
                default => '  ',
            };
            echo \sprintf(
                "[%s] %s [%s] %s%s\n",
                $logEntry['timestamp'],
                $prefix,
                mb_strtoupper($step),
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

    public function reset(): void
    {
        $this->progress = [];
        $this->logs = [];
        $this->releaseLock();
    }

    /**
     * 安装完成后清理进度和日志临时文件.
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

    /**
     * 清理僵尸锁文件（进程已退出但锁文件残留）.
     */
    private function cleanupStaleLock(): void
    {
        if (! file_exists($this->lockFile)) {
            return;
        }

        try {
            $content = file_get_contents($this->lockFile);
            $data = json_decode($content, true);

            if (! \is_array($data) || ! isset($data['pid'])) {
                // 损坏的锁文件，直接删除
                @unlink($this->lockFile);
                return;
            }

            $pid = (int) $data['pid'];
            $exists = true;

            // 检查进程是否仍在运行
            if (mb_strtoupper(mb_substr(\PHP_OS, 0, 3)) === 'WIN') {
                // Windows 下始终认为存在（无法可靠检测）
                $exists = true;
            } elseif (\function_exists('posix_kill')) {
                $exists = @posix_kill($pid, 0);
            } else {
                // 无 posix 扩展时，检查 /proc/{pid} 是否可读（仅 Linux）
                $exists = \PHP_OS_FAMILY === 'Linux' ? is_readable("/proc/{$pid}") : true;
            }

            // 进程不存在 → 僵尸锁，清理掉
            if (! $exists) {
                @unlink($this->lockFile);
                if (file_exists($this->logFile)) {
                    @unlink($this->logFile);
                }
                // 同时清理 progress 文件
                $progressFile = $this->lockFile . '.progress';
                if (file_exists($progressFile)) {
                    @unlink($progressFile);
                }
            }
        } catch (\Throwable) {
            // 静默处理，不影响主流程
        }
    }

    /**
     * 回退方案：当 flock 不可用时使用文件存在性 + pid 检测.
     */
    private function acquireLockFallback(array $lockData): bool
    {
        $result = @file_put_contents(
            $this->lockFile,
            json_encode($lockData, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT),
            \LOCK_EX
        );

        if ($result === false) {
            return false;
        }

        // 验证是否真的获得了锁（检查 pid 是否匹配）
        if (file_exists($this->lockFile)) {
            $existing = @json_decode(@file_get_contents($this->lockFile), true);
            if (\is_array($existing) && isset($existing['pid']) && (int) $existing['pid'] !== getmypid()) {
                return false;  // 被其他进程占用
            }
        }

        $this->isLocked = true;
        $this->log(self::STEP_INIT, 'info', '安装进程已锁定（兼容模式）');
        return true;
    }

    private function saveProgress(): void
    {
        $dir = \dirname($this->lockFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents(
            $this->lockFile . '.progress',
            json_encode($this->progress, \JSON_UNESCAPED_UNICODE),
            \LOCK_EX
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
        $dir = \dirname($this->logFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents(
            $this->logFile,
            json_encode($this->logs, \JSON_UNESCAPED_UNICODE),
            \LOCK_EX
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
            'is_complete' => isset($this->progress[self::STEP_COMPLETE])
                            && $this->progress[self::STEP_COMPLETE]['status'] === self::STATUS_SUCCESS,
        ];
    }
}
