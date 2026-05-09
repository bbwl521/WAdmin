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
use Database\DatabaseSeeder;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class InstallService
{
    #[Inject]
    private ?LoggerInterface $logger = null;

    private InstallProgressTracker $progressTracker;
    private EnvironmentCheckService $envCheckService;

    public function __construct()
    {
        $this->progressTracker = new InstallProgressTracker();
        $this->envCheckService = new EnvironmentCheckService();
    }

    /**
     * 获取环境检测服务
     */
    public function getEnvironmentCheckService(): EnvironmentCheckService
    {
        return $this->envCheckService;
    }

    /**
     * 获取进度追踪器
     */
    public function getProgressTracker(): InstallProgressTracker
    {
        return $this->progressTracker;
    }

    /**
     * 检查系统是否已安装
     */
    public function isInstalled(): bool
    {
        return file_exists($this->getInstallLockFile());
    }

    /**
     * 获取安装锁文件路径
     */
    private function getInstallLockFile(): string
    {
        return BASE_PATH . '/install.lock';
    }

    /**
     * 创建安装锁文件
     */
    private function createInstallLock(): void
    {
        $lockFile = $this->getInstallLockFile();
        $content = json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (file_put_contents($lockFile, $content) === false) {
            throw new BusinessException(ResultCode::FAIL, '无法创建安装锁文件');
        }
    }

    /**
     * 获取安装状态信息
     */
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
            $config = $this->parseEnv(file_get_contents($envFile));

            if (! empty($config['DB_DATABASE']) && ! empty($config['DB_HOST'])) {
                $status['db_configured'] = true;

                if ($this->checkDatabaseConnection($config)) {
                    $status['db_connected'] = true;

                    try {
                        $tables = Db::select('SHOW TABLES');
                        $status['migrations_run'] = count($tables) > 0;
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

    /**
     * 执行环境检测
     */
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

    /**
     * 创建数据库
     */
    public function createDatabase(array $config): bool
    {
        try {
            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'info',
                "正在创建数据库: {$config['DB_DATABASE']}"
            );

            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $config['DB_HOST'],
                $config['DB_PORT'] ?? 3306,
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );

            $pdo = new \PDO(
                $dsn,
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $dbName = $this->escapeIdentifier($config['DB_DATABASE']);
            $charset = $config['DB_CHARSET'] ?? 'utf8mb4';
            $collation = $config['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';

            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET {$charset} COLLATE {$collation}");

            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_DB_CREATE,
                InstallProgressTracker::STATUS_SUCCESS,
                ['database' => $config['DB_DATABASE']]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'success',
                "数据库 '{$config['DB_DATABASE']}' 创建成功"
            );

            return true;
        } catch (\PDOException $e) {
            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_DB_CREATE,
                InstallProgressTracker::STATUS_FAILED,
                ['error' => $e->getMessage()]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'error',
                "创建数据库失败: " . $e->getMessage()
            );

            throw new \RuntimeException("Failed to create database: " . $e->getMessage());
        }
    }

    /**
     * 检查数据库是否存在
     */
    public function checkDatabaseExists(array $config): bool
    {
        try {
            $dsn = sprintf(
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

            $dbName = $this->escapeIdentifier($config['DB_DATABASE']);
            $result = $pdo->query("SHOW DATABASES LIKE '{$config['DB_DATABASE']}'");
            return $result->rowCount() > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * 测试数据库连接
     */
    public function testDatabaseConnection(array $config): array
    {
        $dbHost = $config['DB_HOST'];
        $dbPort = $config['DB_PORT'] ?? 3306;
        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'info',
            "正在测试数据库连接: {$dbHost}:{$dbPort}"
        );

        $dbExists = $this->checkDatabaseExists($config);
        if ($dbExists) {
            return [
                'success' => false,
                'message' => '数据库 "' . $config['DB_DATABASE'] . '" 已存在，请更换其他库名。',
                'database_exists' => true,
            ];
        }

        $connected = $this->checkDatabaseConnection($config);
        if (! $connected) {
            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CONFIG,
                'error',
                '无法连接到 MySQL 服务器'
            );

            return [
                'success' => false,
                'message' => '无法连接到 MySQL 服务器，请检查连接设置。',
                'database_exists' => false,
            ];
        }

        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'success',
            '数据库连接测试成功'
        );

        return [
            'success' => true,
            'message' => '连接成功！数据库将在安装时自动创建。',
            'database_exists' => false,
        ];
    }

    /**
     * 检查数据库连接
     */
    private function checkDatabaseConnection(array $config): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d',
                $config['DB_HOST'],
                $config['DB_PORT'] ?? 3306
            );

            $pdo = new \PDO(
                $dsn,
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * 执行数据库迁移
     */
    public function runMigrations(): bool
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_MIGRATION,
            'info',
            '开始执行数据库迁移'
        );

        $this->progressTracker->setProgress(
            InstallProgressTracker::STEP_MIGRATION,
            InstallProgressTracker::STATUS_RUNNING
        );

        try {
            $envFile = BASE_PATH . '/.env';
            if (! file_exists($envFile)) {
                throw new BusinessException(ResultCode::FAIL, '.env file not found');
            }

            $output = [];
            $returnCode = 0;
            exec('cd ' . BASE_PATH . ' && php bin/hyperf.php migrate --force 2>&1', $output, $returnCode);

            $outputStr = implode("\n", $output);

            if ($returnCode !== 0) {
                throw new BusinessException(ResultCode::FAIL, "Migration failed: " . $outputStr);
            }

            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_MIGRATION,
                InstallProgressTracker::STATUS_SUCCESS,
                ['tables_created' => count($output)]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_MIGRATION,
                'success',
                '数据库迁移完成'
            );

            return true;
        } catch (\Throwable $e) {
            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_MIGRATION,
                InstallProgressTracker::STATUS_FAILED,
                ['error' => $e->getMessage()]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_MIGRATION,
                'error',
                '数据库迁移失败: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * 种子数据填充
     */
    public function seedDatabase(?string $adminUsername = null, ?string $adminPassword = null): bool
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_SEED,
            'info',
            '开始填充初始数据',
            ['admin_username' => $adminUsername ?? 'admin']
        );

        $this->progressTracker->setProgress(
            InstallProgressTracker::STEP_SEED,
            InstallProgressTracker::STATUS_RUNNING
        );

        try {
            $envFile = BASE_PATH . '/.env';
            if (! file_exists($envFile)) {
                throw new BusinessException(ResultCode::FAIL, '.env file not found');
            }

            $envConfig = $this->parseEnvFile($envFile);

            $this->configureDatabaseConnection($envConfig);

            $seeder = new \Database\DatabaseSeeder();
            $seeder->run($adminUsername, $adminPassword);

            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_SEED,
                InstallProgressTracker::STATUS_SUCCESS,
                [
                    'admin_username' => $adminUsername ?? 'admin',
                    'admin_password_set' => ! empty($adminPassword),
                ]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_SEED,
                'success',
                "初始数据填充完成 (管理员: {$adminUsername})"
            );

            return true;
        } catch (\Throwable $e) {
            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_SEED,
                InstallProgressTracker::STATUS_FAILED,
                ['error' => $e->getMessage()]
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_SEED,
                'error',
                '数据填充失败: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * 配置 Hyperf 数据库连接
     */
    private function configureDatabaseConnection(array $envConfig): void
    {
        try {
            $container = \Hyperf\Context\ApplicationContext::getContainer();
            $resolver = $container->get(\Hyperf\Database\ConnectionResolverInterface::class);
            $connection = $resolver->connection();

            $reflection = new \ReflectionClass($connection);
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);

            $config = $configProperty->getValue($connection) ?: [];
            $config['host'] = $envConfig['DB_HOST'] ?? 'localhost';
            $config['port'] = (int) ($envConfig['DB_PORT'] ?? 3306);
            $config['database'] = $envConfig['DB_DATABASE'] ?? '';
            $config['username'] = $envConfig['DB_USERNAME'] ?? 'root';
            $config['password'] = $envConfig['DB_PASSWORD'] ?? '';
            $config['charset'] = $envConfig['DB_CHARSET'] ?? 'utf8mb4';
            $config['collation'] = $envConfig['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
            $config['prefix'] = $envConfig['DB_PREFIX'] ?? '';

            $configProperty->setValue($connection, $config);
            $connection->reconnect();

            $this->log('info', 'Database connection configured: ' . $config['database']);
        } catch (\Throwable $e) {
            $this->log('warning', 'Failed to configure database connection: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取数据库列表
     */
    public function getDatabaseList(array $config): array
    {
        try {
            $dsn = sprintf(
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

            $stmt = $pdo->query("SHOW DATABASES");
            $databases = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $dbName = array_values($row)[0];
                if (! in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                    $databases[] = $dbName;
                }
            }

            return $databases;
        } catch (\PDOException $e) {
            throw new BusinessException(ResultCode::FAIL, "Failed to get database list: " . $e->getMessage());
        }
    }

    /**
     * 创建 .env 文件
     */
    public function createEnvFile(array $config): bool
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'info',
            '正在创建 .env 配置文件'
        );

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
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $envContent
            );
        }

        $envFile = BASE_PATH . '/.env';
        $result = file_put_contents($envFile, $envContent);

        if ($result === false) {
            throw new BusinessException(ResultCode::FAIL, 'Failed to create .env file');
        }

        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'success',
            '.env 配置文件创建成功'
        );

        return true;
    }

    /**
     * 重新加载环境变量配置
     */
    public function reloadEnvConfig(): void
    {
        if (function_exists('opcache_get_status')) {
            opcache_get_status();
        }

        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    if (! empty($key)) {
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                        putenv("{$key}={$value}");
                    }
                }
            }
        }
    }

    /**
     * 完整安装流程
     */
    public function install(array $config, array $options = []): array
    {
        // 检查系统是否已安装，防止重复安装
        if ($this->isInstalled()) {
            throw new BusinessException(ResultCode::FAIL, '系统已安装，如需重新安装请先重置安装状态');
        }

        // 获取安装锁
        if (! $this->progressTracker->acquireLock()) {
            throw new BusinessException(ResultCode::FAIL, '安装进程已被锁定，请稍后重试');
        }

        $adminUsername = $options['admin_username'] ?? 'admin';
        $adminPassword = $options['admin_password'] ?? null;
        $createDb = $options['create_db'] ?? true;
        $runMigrations = $options['run_migrations'] ?? true;
        $seedData = $options['seed_data'] ?? true;

        $result = [
            'success' => false,
            'admin_username' => $adminUsername,
            'admin_password' => $adminPassword,
            'logs' => [],
            'progress' => [],
        ];

        try {
            // 1. 环境检测
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

            // 2. 创建 .env 文件
            $this->createEnvFile($config);
            $this->reloadEnvConfig();

            // 3. 创建数据库
            if ($createDb) {
                $this->createDatabase($config);
            }

            // 4. 执行迁移
            if ($runMigrations) {
                $this->runMigrations();
            }

            // 5. 填充数据
            if ($seedData) {
                $this->seedDatabase($adminUsername, $adminPassword);
            }

            // 6. 安装完成
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

            $result['success'] = true;
            $result['progress'] = $this->progressTracker->getProgress();
            $result['logs'] = $this->progressTracker->getLogs();

            // 释放锁
            $this->progressTracker->releaseLock();

            return $result;
        } catch (\Throwable $e) {
            $this->progressTracker->log(
                InstallProgressTracker::STEP_COMPLETE,
                'error',
                '安装失败: ' . $e->getMessage()
            );

            $result['logs'] = $this->progressTracker->getLogs();
            $result['error'] = $e->getMessage();

            // 不释放锁，保留安装进度以便调试
            throw $e;
        }
    }

    /**
     * 获取安装日志
     */
    public function getInstallLogs(): array
    {
        return $this->progressTracker->getLogs();
    }

    /**
     * 获取安装进度
     */
    public function getInstallProgress(): array
    {
        return $this->progressTracker->getProgress();
    }

    /**
     * 解析 .env 文件内容
     */
    private function parseEnv(string $content): array
    {
        $config = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * 解析 .env 文件
     */
    private function parseEnvFile(string $path): array
    {
        $config = [];
        if (! file_exists($path)) {
            return $config;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * 生成 JWT 密钥
     */
    private function generateJwtSecret(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }

    /**
     * 转义 SQL 标识符
     */
    private function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * 记录日志
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->$level('[Install] ' . $message);
        }
    }

    /**
     * 获取安装步骤
     */
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

    /**
     * 重置安装状态
     */
    public function resetInstall(): void
    {
        // 删除安装锁文件
        $lockFile = $this->getInstallLockFile();
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        $this->progressTracker->reset();
    }
}
