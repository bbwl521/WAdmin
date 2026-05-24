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

use App\Event\InstallationCompletedEvent;
use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use Hyperf\DbConnection\Db;
use Psr\EventDispatcher\EventDispatcherInterface;

class InstallService
{
    public function __construct(
        private readonly EnvFileWriter $envWriter,
        private readonly DatabaseInstaller $dbInstaller,
        private readonly InstallProgressTracker $progressTracker,
        private readonly EnvironmentCheckService $envCheckService,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    // ============================================================
    //  环境检测相关（直接委托给 EnvironmentCheckService）
    // ============================================================

    public function getEnvironmentCheckService(): EnvironmentCheckService
    {
        return $this->envCheckService;
    }

    public function checkEnvironment(): array
    {
        $this->progressTracker->log(InstallProgressTracker::STEP_ENV_CHECK, 'info', '开始环境检测');

        $requirements = $this->envCheckService->checkAll();
        $summary = $this->envCheckService->getSummary();

        $status = $summary['passed']
            ? InstallProgressTracker::STATUS_SUCCESS
            : InstallProgressTracker::STATUS_FAILED;

        $this->progressTracker->setProgress(
            InstallProgressTracker::STEP_ENV_CHECK,
            $status,
            $summary
        );

        $this->progressTracker->log(
            InstallProgressTracker::STEP_ENV_CHECK,
            $summary['passed'] ? 'success' : 'error',
            $summary['passed'] ? '环境检测通过' : '环境检测未通过',
            ['errors' => $summary['errors']]
        );

        return [
            'requirements' => $requirements,
            'summary' => $summary,
            'passed' => $summary['passed'],
        ];
    }

    // ============================================================
    //  安装状态与锁管理
    // ============================================================

    public function isInstalled(): bool
    {
        return file_exists($this->getInstallLockFile());
    }

    public function getInstallStatus(): array
    {
        $status = [
            'installed' => $this->isInstalled(),
            'env_exists' => false,
            'db_configured' => false,
            'db_connected' => false,
            'migrations_run' => false,
            'install_locked' => $this->progressTracker->isLocked(),
            'lock_info' => $this->progressTracker->getLockInfo(),
            'progress' => $this->progressTracker->getProgress(),
            'message' => 'System not installed',
        ];

        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            $status['env_exists'] = true;
            $config = $this->envWriter->parseEnvFile(file_get_contents($envFile));

            if (! empty($config['DB_DATABASE']) && ! empty($config['DB_HOST'])) {
                $status['db_configured'] = true;

                if ($this->dbInstaller->testConnectionSilent($config)) {
                    $status['db_connected'] = true;

                    try {
                        $tables = Db::select('SHOW TABLES');
                        $status['migrations_run'] = \count($tables) > 0;
                    } catch (\Throwable) {
                        $status['migrations_run'] = false;
                    }
                } else {
                    $status['message'] = 'Database connection failed';
                }
            } else {
                $status['message'] = 'Database not configured';
            }
        } else {
            $status['message'] = 'Environment file not found';
        }

        return $status;
    }

    // ============================================================
    //  .env 文件操作（委托给 EnvFileWriter）
    // ============================================================

    public function createEnvFile(array $config): bool
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'info',
            '正在创建 .env 配置文件'
        );

        $result = $this->envWriter->createEnvFile($config);

        if ($result) {
            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CONFIG,
                'success',
                '.env 配置文件创建成功'
            );
        }

        return $result;
    }

    public function reloadEnvConfig(): void
    {
        $this->envWriter->reloadEnvConfig();
    }

    // ============================================================
    //  数据库操作（委托给 DatabaseInstaller）
    // ============================================================

    public function createDatabase(array $config): bool
    {
        return $this->dbInstaller->createDatabase($config, $this->progressTracker);
    }

    public function checkDatabaseExists(array $config): bool
    {
        try {
            $dsn = \sprintf(
                'mysql:host=%s;port=%d',
                $config['DB_HOST'],
                $config['DB_PORT'] ?? 3306
            );

            $pdo = new \PDO(
                $dsn,
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare('SHOW DATABASES LIKE :name');
            $stmt->execute(['name' => (string) $config['DB_DATABASE']]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    public function testDatabaseConnection(array $config): array
    {
        return $this->dbInstaller->testConnection($config, $this->progressTracker);
    }

    public function execSqlFile(array $config, ?string $adminUsername = null, ?string $adminPassword = null): bool
    {
        return $this->dbInstaller->importSqlFile($config, $this->progressTracker, $adminUsername, $adminPassword);
    }

    public function getDatabaseList(array $config): array
    {
        return $this->dbInstaller->getDatabaseList($config);
    }

    // ============================================================
    //  核心编排：完整安装流程 + 事务性回滚
    // ============================================================

    public function installation(
        string $mysqlHostname,
        int $mysqlHostport,
        string $mysqlDatabase,
        string $mysqlUsername,
        string $mysqlPassword,
        string $mysqlPrefix,
        string $adminUsername,
        string $adminPassword,
        ?string $adminEmail = null,
        ?string $siteName = null,
        bool $force = false,
        array $options = []
    ): array {
        // 解析安装选项
        $shouldCreateDb = (bool) ($options['create_db'] ?? true);
        $shouldRunMigrations = (bool) ($options['run_migrations'] ?? true);
        $shouldSeedData = (bool) ($options['seed_data'] ?? true);

        // 检查系统是否已安装，防止重复安装
        if ($this->isInstalled() && ! $force) {
            throw new BusinessException(ResultCode::FAIL, '系统已安装，如需重新安装请先重置安装状态');
        }

        // 获取安装锁
        if (! $this->progressTracker->acquireLock()) {
            throw new BusinessException(ResultCode::FAIL, '安装进程已被锁定，请稍后重试');
        }

        // 已提交步骤追踪器（局部变量，协程安全）
        $committed = [
            'env_created' => false,
            'database_created' => false,
            'sql_imported' => false,
        ];

        try {
            // Step 1: 环境检测
            $this->progressTracker->log(
                InstallProgressTracker::STEP_ENV_CHECK,
                'info',
                '检查系统环境'
            );

            $envResult = $this->checkEnvironment();
            if (! $envResult['passed']) {
                throw new BusinessException(
                    ResultCode::FAIL,
                    '环境检测未通过: ' . implode(', ', $envResult['summary']['errors'])
                );
            }

            // Step 2: 创建 .env 文件
            $config = [
                'DB_HOST' => $mysqlHostname,
                'DB_PORT' => $mysqlHostport,
                'DB_DATABASE' => $mysqlDatabase,
                'DB_USERNAME' => $mysqlUsername,
                'DB_PASSWORD' => $mysqlPassword,
                'DB_PREFIX' => $mysqlPrefix,
                'APP_NAME' => $siteName ?? 'MineAdmin',
            ];

            $this->createEnvFile($config);
            $committed['env_created'] = true;
            $this->reloadEnvConfig();

            // Step 3: 创建数据库（可跳过）
            if ($shouldCreateDb) {
                $this->createDatabase($config);
                $committed['database_created'] = true;
            }

            // Step 4: 执行 SQL 导入（建表 + 初始数据，可分别控制）
            if ($shouldRunMigrations || $shouldSeedData) {
                $this->execSqlFile($config, $adminUsername, $adminPassword);
                $committed['sql_imported'] = true;
            }

            // Step 5: 安装完成
            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_COMPLETE,
                InstallProgressTracker::STATUS_SUCCESS
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_COMPLETE,
                'success',
                '系统安装完成！'
            );

            // 创建安装锁文件
            $this->createInstallLock();

            // 发射安装完成事件
            $this->eventDispatcher?->dispatch(
                new InstallationCompletedEvent([
                    'app_name' => $siteName ?? 'MineAdmin',
                    'db_host' => $mysqlHostname,
                    'db_port' => $mysqlHostport,
                    'db_database' => $mysqlDatabase,
                    'db_prefix' => $mysqlPrefix,
                    'admin_username' => $adminUsername,
                ])
            );

            // 清理临时文件并释放锁
            $this->progressTracker->cleanup();
            $this->progressTracker->releaseLock();

            return [
                'success' => true,
                'admin_username' => $adminUsername,
                'admin_password' => $adminPassword,
                'progress' => $this->progressTracker->getProgress(),
                'logs' => $this->progressTracker->getLogs(),
            ];
        } catch (\Throwable $e) {
            $this->progressTracker->log(
                InstallProgressTracker::STEP_COMPLETE,
                'error',
                '安装失败: ' . $e->getMessage()
            );

            // 事务性回滚：按逆序清理已成功的步骤
            $this->rollbackInstallation($config ?? [], $committed);

            // 不释放锁，保留安装进度以便调试
            throw $e;
        }
    }

    // ============================================================
    //  兼容层与工具方法
    // ============================================================

    public function deleteInstallScript(): void
    {
        $installFile = BASE_PATH . '/public/install.html';
        if (file_exists($installFile)) {
            @unlink($installFile);
        }
    }

    public function getInstallLogs(): array
    {
        return $this->progressTracker->getLogs();
    }

    public function getInstallProgress(): array
    {
        return $this->progressTracker->getProgress();
    }

    public function getInstallSteps(): array
    {
        return [
            1 => ['title' => '环境检测', 'key' => 'env_check', 'description' => '检查系统环境和依赖'],
            2 => ['title' => '数据库配置', 'key' => 'db_config', 'description' => '配置数据库连接信息'],
            3 => ['title' => '创建数据库', 'key' => 'db_create', 'description' => '创建数据库和表结构'],
            4 => ['title' => '填充数据', 'key' => 'seed', 'description' => '导入初始数据'],
            5 => ['title' => '安装完成', 'key' => 'complete', 'description' => '系统已准备就绪'],
        ];
    }

    public function getProgressTracker(): InstallProgressTracker
    {
        return $this->progressTracker;
    }

    public function resetInstall(): void
    {
        $installDir = BASE_PATH . '/runtime/.install';

        // 清理 .install 目录内所有文件
        if (is_dir($installDir)) {
            foreach (glob($installDir . '/*') as $file) {
                @unlink($file);
            }
        }

        // 清理旧路径残留文件（兼容历史版本遗留）
        $legacyFiles = [
            BASE_PATH . '/runtime/.install.lock',
            BASE_PATH . '/runtime/.install.log',
            BASE_PATH . '/runtime/.install.lock.progress',
        ];

        foreach ($legacyFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        $this->progressTracker->reset();
    }

    // ============================================================
    //  内部方法：锁文件与回滚
    // ============================================================

    private function getInstallLockFile(): string
    {
        $lockDir = BASE_PATH . '/runtime/.install';
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0o755, true);
        }

        return $lockDir . '/install.lock';
    }

    private function createInstallLock(): void
    {
        $lockFile = $this->getInstallLockFile();
        $content = json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
        ], \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);

        if (file_put_contents($lockFile, $content) === false) {
            throw new BusinessException(ResultCode::FAIL, '无法创建安装锁文件');
        }
    }

    /**
     * 安装失败时按逆序回滚已成功执行的步骤.
     *
     * 回滚顺序：
     *   1. 删除数据库（如果已创建或已导入 SQL）
     *   2. 删除 .env 文件（如果已创建）
     */
    private function rollbackInstallation(array $config, array $committed): void
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_COMPLETE,
            'warning',
            '正在回滚已执行的安装步骤...'
        );

        // 逆序回滚：SQL 导入覆盖了数据库 → 先删库再删 env
        if ($committed['sql_imported'] || $committed['database_created']) {
            try {
                $this->dbInstaller->dropDatabase($config);
            } catch (\Throwable $e) {
                $this->progressTracker->log(
                    InstallProgressTracker::STEP_COMPLETE,
                    'error',
                    '回滚数据库失败: ' . $e->getMessage()
                );
            }
        }

        if ($committed['env_created']) {
            $envFile = BASE_PATH . '/.env';
            if (file_exists($envFile)) {
                @unlink($envFile);
                $this->progressTracker->log(
                    InstallProgressTracker::STEP_COMPLETE,
                    'info',
                    '已删除 .env 配置文件'
                );
            }
        }

        $this->progressTracker->log(
            InstallProgressTracker::STEP_COMPLETE,
            'info',
            '安装回滚完成'
        );
    }
}
