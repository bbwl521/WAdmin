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
use Hyperf\DbConnection\Db;

class InstallService
{
    public function __construct(
        private readonly InstallProgressTracker $progressTracker,
        private readonly EnvironmentCheckService $envCheckService,
    ) {
    }

    private string $lastDbError = '';

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
     * 放在 runtime 目录下，避免根目录被误删除
     */
    private function getInstallLockFile(): string
    {
        $lockDir = BASE_PATH . '/runtime/.install';
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        return $lockDir . '/install.lock';
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

            // 使用预处理语句执行建库（标识符已通过 escapeIdentifier 转义）
            $sql = "CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET :charset COLLATE :collation";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['charset' => $charset, 'collation' => $collation]);

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

            // 使用预处理语句，通过 PDO 绑定参数防止 SQL 注入
            $stmt = $pdo->prepare('SHOW DATABASES LIKE :name');
            $stmt->execute(['name' => (string) $config['DB_DATABASE']]);
            return $stmt->rowCount() > 0;
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
        $this->lastDbError = '';

        $this->progressTracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'info',
            "正在测试数据库连接: {$dbHost}:{$dbPort}"
        );

        $connected = $this->checkDatabaseConnection($config);
        if (! $connected) {
            $errorMsg = $this->lastDbError ?: '无法连接到 MySQL 服务器，请检查连接设置。';

            $this->progressTracker->log(
                InstallProgressTracker::STEP_DB_CONFIG,
                'error',
                '无法连接: ' . $errorMsg
            );

            return [
                'success' => false,
                'message' => $errorMsg,
                'database_exists' => false,
            ];
        }

        // 检查数据库是否存在
        try {
            if ($this->checkDatabaseExists($config)) {
                return [
                    'success' => true,
                    'message' => "数据库 \"{$config['DB_DATABASE']}\" 已存在",
                    'database_exists' => true,
                ];
            }
        } catch (\Throwable) {
            // 检查存在性失败不影响连接成功
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
        $host = $config['DB_HOST'];
        $port = (int) ($config['DB_PORT'] ?? 3306);
        $pdoError = null;

        // 彻底压制 PDO 连接时的底层错误（Broken pipe 等），防止 Swoole Worker 崩溃
        $prevReport = error_reporting(0);

        set_error_handler(function (int $errno, string $errstr) use (&$pdoError): bool {
            if ($errno <= E_WARNING) {
                $pdoError = new \RuntimeException($errstr, $errno);
                return true;
            }
            return false;
        });

        try {
            ini_set('mysql.connect_timeout', '3');

            /** @var \PDO|false|null $pdo */
            $pdo = @new \PDO(
                sprintf('mysql:host=%s;port=%d', $host, $port),
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3]
            );

            if ($pdoError !== null || !($pdo instanceof \PDO)) {
                throw $pdoError ?: new \RuntimeException('连接失败');
            }
            return true;
        } catch (\Throwable $e) {
            $this->lastDbError = $this->parseDbError($e);
            return false;
        } finally {
            restore_error_handler();
            error_reporting($prevReport);
        }
    }

    /**
     * 解析数据库错误信息
     */
    private function parseDbError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Access denied') || str_contains($msg, 'authentication') || str_contains((string)$e->getCode(), '1045') || str_contains($msg, 'Broken pipe')) {
            return '用户名或密码错误';
        }
        if (str_contains($msg, 'Connection refused') || str_contains((string)$e->getCode(), '2002')) {
            return '无法连接到 MySQL，请检查地址和端口';
        }
        if (str_contains($msg, 'timed out') || str_contains((string)$e->getCode(), '2003')) {
            return '连接超时（3秒），请检查主机和网络';
        }
        return '连接失败: ' . $msg;
    }

    /**
     * 执行 SQL 文件（替代 Migration + Seeder，类似 FastAdmin）
     */
    public function execSqlFile(array $config, ?string $adminUsername = null, ?string $adminPassword = null): bool
    {
        $this->progressTracker->log(
            InstallProgressTracker::STEP_MIGRATION,
            'info',
            '开始导入数据库 SQL 文件'
        );

        $this->progressTracker->setProgress(
            InstallProgressTracker::STEP_MIGRATION,
            InstallProgressTracker::STATUS_RUNNING
        );

        try {
            $sqlFile = BASE_PATH . '/databases/mineadmin.sql';
            if (! file_exists($sqlFile)) {
                throw new BusinessException(ResultCode::FAIL, 'SQL 文件不存在: ' . $sqlFile);
            }

            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                throw new BusinessException(ResultCode::FAIL, '无法读取 SQL 文件');
            }

            // 替换表前缀
            $prefix = $config['DB_PREFIX'] ?? '';
            if ($prefix) {
                $sql = str_replace('`', '`' . $prefix, $sql);
            }

            // 连接目标数据库并执行 SQL
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['DB_HOST'],
                $config['DB_PORT'] ?? 3306,
                $config['DB_DATABASE'],
                $config['DB_CHARSET'] ?? 'utf8mb4'
            );

            $pdo = new \PDO(
                $dsn,
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // 执行 SQL（按分号分割语句）
            $pdo->exec($sql);

            // 更新管理员账号密码
            if ($adminUsername || $adminPassword) {
                $this->updateAdminAccount($pdo, $adminUsername, $adminPassword, $prefix);
            }

            $this->progressTracker->setProgress(
                InstallProgressTracker::STEP_MIGRATION,
                InstallProgressTracker::STATUS_SUCCESS,
                ['sql_file' => 'mineadmin.sql']
            );

            $this->progressTracker->log(
                InstallProgressTracker::STEP_MIGRATION,
                'success',
                '数据库 SQL 导入完成'
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
                'SQL 导入失败: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * 更新管理员账号密码
     */
    private function updateAdminAccount(\PDO $pdo, ?string $adminUsername, ?string $adminPassword, string $prefix = ''): void
    {
        $userTable = $prefix . 'user';

        // 更新用户名
        if ($adminUsername) {
            $stmt = $pdo->prepare("UPDATE `{$userTable}` SET `username` = ? WHERE `id` = 1");
            $stmt->execute([$adminUsername]);
        }

        // 更新密码
        if ($adminPassword) {
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE `{$userTable}` SET `password` = ? WHERE `id` = 1");
            $stmt->execute([$hashedPassword]);
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
     * 完整安装流程（统一入口，类似 FastAdmin）
     */
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
        bool $force = false
    ): array {
        // 检查系统是否已安装，防止重复安装
        if ($this->isInstalled() && ! $force) {
            throw new BusinessException(ResultCode::FAIL, '系统已安装，如需重新安装请先重置安装状态');
        }

        // 获取安装锁
        if (! $this->progressTracker->acquireLock()) {
            throw new BusinessException(ResultCode::FAIL, '安装进程已被锁定，请稍后重试');
        }

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
            $this->reloadEnvConfig();

            // 3. 创建数据库
            $this->createDatabase($config);

            // 4. 执行 SQL 文件（建表 + 初始数据）
            $this->execSqlFile($config, $adminUsername, $adminPassword);

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

            // 清理安装过程中的进度/日志临时文件
            $this->progressTracker->cleanup();

            // 释放锁
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

            // 不释放锁，保留安装进度以便调试
            throw $e;
        }
    }

    /**
     * 兼容旧版本的 install 方法
     */
    public function install(array $config, array $options = []): array
    {
        return $this->installation(
            $config['DB_HOST'] ?? 'localhost',
            (int) ($config['DB_PORT'] ?? 3306),
            $config['DB_DATABASE'] ?? '',
            $config['DB_USERNAME'] ?? 'root',
            $config['DB_PASSWORD'] ?? '',
            $config['DB_PREFIX'] ?? '',
            $options['admin_username'] ?? 'admin',
            $options['admin_password'] ?? '',
            $options['admin_email'] ?? null,
            $config['APP_NAME'] ?? null,
            $options['force'] ?? false
        );
    }

    /**
     * 删除安装脚本
     */
    public function deleteInstallScript(): void
    {
        $installFile = BASE_PATH . '/public/install.html';
        if (file_exists($installFile)) {
            @unlink($installFile);
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
        $installDir = BASE_PATH . '/runtime/.install';

        // 1. 清理 .install 目录内所有文件（统一目录）
        if (is_dir($installDir)) {
            foreach (glob($installDir . '/*') as $file) {
                @unlink($file);
            }
        }

        // 2. 清理旧路径残留文件（兼容历史版本遗留）
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
}
