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
     * 静默测试数据库连接（不写进度日志，供状态查询使用）.
     */
    public function testConnectionSilent(array $config): bool
    {
        return $this->checkDatabaseConnection($config);
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

            // 替换表前缀（仅替换表名，不替换列名）
            // 原始的 str_replace('`', '`'.$prefix) 会将列名也加上前缀，导致 SQL 执行失败
            // 正确做法：只匹配 SQL 关键字后的表名标识符
            $prefix = $config['DB_PREFIX'] ?? '';
            if ($prefix) {
                $sql = $this->replaceTablePrefix($sql, $prefix);
            }

            // 防御性规范化：自动修复常见 SQL 格式缺陷
            // 某些 SQL 导出工具生成的 INSERT 语句可能缺少列名间的逗号
            $sql = $this->normalizeSql($sql);

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

            // 按分号分割逐条执行 SQL（兼容 PHP 8.x mysqlnd 禁用多语句）
            $this->executeSqlStatements($pdo, $sql, $tracker);

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
            $hashedPassword = password_hash($adminPassword, \PASSWORD_BCRYPT);
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

    /**
     * 智能替换表名前缀，仅匹配 DDL/DML 语句中的表名，不替换列名.
     *
     * 匹配规则：
     *   CREATE TABLE / DROP TABLE / ALTER TABLE / INSERT INTO / UPDATE / DELETE FROM
     *   后跟的反引号标识符才添加前缀.
     *
     * @param string $sql 原始 SQL 内容
     * @param string $prefix 表前缀（如 'ma_'）
     */
    public function replaceTablePrefix(string $sql, string $prefix): string
    {
        // 匹配 SQL 关键字后的表名模式: 关键字 + 空格 + (IF NOT EXISTS)? + 空格 + `表名`
        // 不匹配列定义中的 `column_name` 模式
        $pattern = '/\b(CREATE\s+TABLE|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|ALTER\s+TABLE|INSERT\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE|RENAME\s+TABLE)\s+`([^`]+)`/';

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use ($prefix): string {
                $keyword = $matches[1];
                $table = $matches[2];
                return "{$keyword} `{$prefix}{$table}`";
            },
            $sql
        );
    }

    /**
     * 规范化 SQL 内容，自动修复常见格式缺陷.
     *
     * 防御性措施：
     *   - INSERT 列名列表中 `col1` `col2` → `col1`, `col2`（缺少逗号）
     *   - INSERT VALUES 中值之间缺少逗号
     *
     * 此方法确保即使 SQL 文件格式不完美，也能正确导入.
     */
    private function normalizeSql(string $sql): string
    {
        // Fix: INSERT INTO `table` (`a` `b` `c`) VALUES → (`a`, `b`, `c`)
        // 匹配 INSERT 语句括号内的列名/值列表，在反引号标识符间补充逗号
        $sql = (string) preg_replace_callback(
            '/INSERT\s+INTO\s+`[^`]+`\s*\(([^)]+)\)\s*VALUES/i',
            static function (array $m): string {
                // 只修复列定义部分，保持原样结构
                $cols = $m[1];
                // `word` 后面跟空格+`word` → 补逗号
                $fixed = (string) preg_replace(
                    '/(`[^`]*`)  +(`)/',
                    '$1, $2',
                    $cols
                );

                return str_replace($cols, $fixed, $m[0]);
            },
            $sql
        );

        return $sql;
    }

    /**
     * 逐条执行 SQL 语句（按分号分割）.
     *
     * PHP 8.x 的 mysqlnd 默认禁用 PDO 多语句执行，
     * 因此需要将 SQL 文件分割为单条语句逐一执行。
     *
     * 自动跳过空语句和纯注释行。
     */
    private function executeSqlStatements(\PDO $pdo, string $sql, InstallProgressTracker $tracker): int
    {
        // 移除存储过程/函数/触发器体中的分号（DELIMITER 块内的内容）
        // 简化处理：先按 DELIMITER 分割，再对非 delimiter 块按 ; 分割
        $statements = $this->splitSqlStatements($sql);

        $executedCount = 0;
        $totalStatements = count($statements);
        $lastProgress = 0;

        foreach ($statements as $index => $statement) {
            $stmt = trim($statement);

            // 剥离前导注释行后判断是否为空（修复：注释行后的 SQL 语句被错误跳过的问题）
            $stmt = self::stripLeadingComments($stmt);
            if ($stmt === '') {
                continue;
            }

            try {
                $pdo->exec($stmt);
                ++$executedCount;

                // 每 10 条或最后一条时更新进度
                $progress = (int) (($index + 1) / $totalStatements * 100);
                if ($progress !== $lastProgress && $progress % 5 === 0) {
                    $tracker->log(
                        InstallProgressTracker::STEP_MIGRATION,
                        'info',
                        "SQL 导入进度: {$executedCount}/{$totalStatements} ({$progress}%)"
                    );
                    $lastProgress = $progress;
                }
            } catch (\PDOException $e) {
                // 记录失败的具体语句（截断过长内容）
                $failedStmtPreview = mb_strlen($stmt) > 100 ? mb_substr($stmt, 0, 100) . '...' : $stmt;

                throw new \RuntimeException(
                    "SQL 执行失败 (#{$executedCount}): {$e->getMessage()}
语句预览: {$failedStmtPreview}",
                    (int) $e->getCode()
                );
            }
        }

        return $executedCount;
    }

    /**
     * 将完整 SQL 文件内容拆分为单条可执行语句.
     *
     * 处理边界情况：
     *   - 分号出现在字符串字面量中（不分割）
     *   - DELIMITER 定界符块（保持原样）
     *   - 注释行（保留但不影响分割）
     */
        /**
     * 剥离 SQL 语句前导的注释行（-- 行注释和 / * ... * / 块注释），
     * 保留注释之后的有效 SQL 内容.
     */
    private static function stripLeadingComments(string $sql): string
    {
        $sql = ltrim($sql);
        while ($sql !== '') {
            if (str_starts_with($sql, '--')) {
                $pos = strpos($sql, "\n");
                if ($pos === false) {
                    return '';
                }
                $sql = ltrim(substr($sql, $pos + 1));
            } elseif (str_starts_with($sql, '/*')) {
                $pos = strpos($sql, '*/', 2);
                if ($pos === false) {
                    return '';
                }
                $sql = ltrim(substr($sql, $pos + 2));
            } else {
                break;
            }
        }
        return $sql;
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;       // 是否在字符串内
        $stringChar = '';         // 当前字符串的引号类型 (' 或 ")
        $escaped = false;         // 上一个字符是否转义

        foreach (str_split($sql) as $char) {
            if (! $inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            // 字符串状态跟踪
            if (! $inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $escaped = false;
            } elseif ($inString && ! $escaped && $char === $stringChar) {
                $inString = false;
            } elseif ($inString && $char === '\\' && ! $escaped) {
                $escaped = true;
            } elseif ($inString) {
                $escaped = false;
            }

            $current .= $char;
        }

        // 收集最后一条未以分号结尾的语句
        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}
