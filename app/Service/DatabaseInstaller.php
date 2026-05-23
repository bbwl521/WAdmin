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

class DatabaseInstaller
{
    private string $lastDbError = '';

    /**
     * 创建数据库（如果不存在）.
     *
     * 使用 sprintf 拼接 DDL，因为 PDO 预处理不支持绑定 CHARACTER SET / COLLATE 参数。
     * charset/collation 值来自内部常量白名单，安全可控.
     */
    public function createDatabase(array $config, InstallProgressTracker $tracker): bool
    {
        try {
            $tracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'info',
                "正在创建数据库: {$config['DB_DATABASE']}"
            );

            $dsn = \sprintf(
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

            $sql = \sprintf(
                'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s',
                $dbName,
                $this->escapeIdentifier($charset),
                $this->escapeIdentifier($collation)
            );
            $pdo->exec($sql);

            $tracker->setProgress(
                InstallProgressTracker::STEP_DB_CREATE,
                InstallProgressTracker::STATUS_SUCCESS,
                ['database' => $config['DB_DATABASE']]
            );

            $tracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'success',
                "数据库 '{$config['DB_DATABASE']}' 创建成功"
            );

            return true;
        } catch (\PDOException $e) {
            $tracker->setProgress(
                InstallProgressTracker::STEP_DB_CREATE,
                InstallProgressTracker::STATUS_FAILED,
                ['error' => $e->getMessage()]
            );

            $tracker->log(
                InstallProgressTracker::STEP_DB_CREATE,
                'error',
                '创建数据库失败: ' . $e->getMessage()
            );

            throw new \RuntimeException('Failed to create database: ' . $e->getMessage());
        }
    }

    /**
     * 删除指定数据库（回滚时使用）.
     */
    public function dropDatabase(array $config): void
    {
        $dsn = \sprintf(
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
        $pdo->exec("DROP DATABASE IF EXISTS {$dbName}");
    }

    /**
     * 测试数据库连接是否可用.
     */
    public function testConnection(array $config, InstallProgressTracker $tracker): array
    {
        $dbHost = $config['DB_HOST'];
        $dbPort = $config['DB_PORT'] ?? 3306;
        $this->lastDbError = '';

        $tracker->log(
            InstallProgressTracker::STEP_DB_CONFIG,
            'info',
            "正在测试数据库连接: {$dbHost}:{$dbPort}"
        );

        $connected = $this->checkDatabaseConnection($config);

        if (! $connected) {
            $errorMsg = $this->lastDbError ?: '无法连接到 MySQL 服务器，请检查连接设置。';

            $tracker->log(
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
            // 存在性检测失败不影响连接成功判定
        }

        $tracker->log(
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
     * 执行 SQL 文件导入（建表 + 初始数据）.
     *
     * 替代 Migration + Seeder 方案，类似 FastAdmin 的单文件导入模式.
     */
    public function importSqlFile(
        array $config,
        InstallProgressTracker $tracker,
        ?string $adminUsername = null,
        ?string $adminPassword = null
    ): bool {
        $tracker->log(
            InstallProgressTracker::STEP_MIGRATION,
            'info',
            '开始导入数据库 SQL 文件'
        );

        $tracker->setProgress(
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
            $dsn = \sprintf(
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

            $pdo->exec($sql);

            // 更新管理员账号密码
            if ($adminUsername || $adminPassword) {
                $this->updateAdminAccount($pdo, $adminUsername, $adminPassword, $prefix);
            }

            $tracker->setProgress(
                InstallProgressTracker::STEP_MIGRATION,
                InstallProgressTracker::STATUS_SUCCESS,
                ['sql_file' => 'mineadmin.sql']
            );

            $tracker->log(
                InstallProgressTracker::STEP_MIGRATION,
                'success',
                '数据库 SQL 导入完成'
            );

            return true;
        } catch (\Throwable $e) {
            $tracker->setProgress(
                InstallProgressTracker::STEP_MIGRATION,
                InstallProgressTracker::STATUS_FAILED,
                ['error' => $e->getMessage()]
            );

            $tracker->log(
                InstallProgressTracker::STEP_MIGRATION,
                'error',
                'SQL 导入失败: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * 获取 MySQL 服务器上的数据库列表.
     */
    public function getDatabaseList(array $config): array
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

            $stmt = $pdo->query('SHOW DATABASES');
            $databases = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $dbName = array_values($row)[0];
                if (! \in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'], true)) {
                    $databases[] = $dbName;
                }
            }

            return $databases;
        } catch (\PDOException $e) {
            throw new BusinessException(ResultCode::FAIL, 'Failed to get database list: ' . $e->getMessage());
        }
    }

    /**
     * 检查目标数据库是否已存在.
     */
    private function checkDatabaseExists(array $config): bool
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

    /**
     * 底层数据库连接测试（带错误抑制和超时控制）.
     *
     * @phpstan-impure
     */
    private function checkDatabaseConnection(array $config): bool
    {
        $host = $config['DB_HOST'];
        $port = (int) ($config['DB_PORT'] ?? 3306);
        $pdoError = null;

        // 彻底压制 PDO 连接时的底层错误（Broken pipe 等），防止 Swoole Worker 崩溃
        $prevReport = error_reporting(0);

        set_error_handler(static function (int $errno, string $errstr) use (&$pdoError): bool {
            if ($errno <= \E_WARNING) {
                $pdoError = new \RuntimeException($errstr, $errno);
                return true;
            }

            return false;
        });

        try {
            ini_set('mysql.connect_timeout', '3');

            /** @var \PDO $pdo */
            $pdo = @new \PDO(
                \sprintf('mysql:host=%s;port=%d', $host, $port),
                $config['DB_USERNAME'],
                $config['DB_PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3]
            );

            if ($pdoError !== null) {
                throw $pdoError;
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
     * 解析数据库错误信息为用户友好的中文提示.
     */
    private function parseDbError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Access denied') || str_contains($msg, 'authentication')
            || str_contains((string) $e->getCode(), '1045')
            || str_contains($msg, 'Broken pipe')) {
            return '用户名或密码错误';
        }

        if (str_contains($msg, 'Connection refused') || str_contains((string) $e->getCode(), '2002')) {
            return '无法连接到 MySQL，请检查地址和端口';
        }

        if (str_contains($msg, 'timed out') || str_contains((string) $e->getCode(), '2003')) {
            return '连接超时（3秒），请检查主机和网络';
        }

        return '连接失败: ' . $msg;
    }

    /**
     * 更新管理员账号的用户名和密码.
     */
    private function updateAdminAccount(\PDO $pdo, ?string $adminUsername, ?string $adminPassword, string $prefix = ''): void
    {
        $userTable = $prefix . 'user';

        if ($adminUsername) {
            $stmt = $pdo->prepare("UPDATE `{$userTable}` SET `username` = ? WHERE `id` = 1");
            $stmt->execute([$adminUsername]);
        }

        if ($adminPassword) {
            $hashedPassword = password_hash($adminPassword, \PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE `{$userTable}` SET `password` = ? WHERE `id` = 1");
            $stmt->execute([$hashedPassword]);
        }
    }

    /**
     * 转义 SQL 标识符（防注入）.
     */
    private function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
