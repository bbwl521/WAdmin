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
use Hyperf\Contract\ContainerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class InstallService
{
    #[Inject]
    private ?LoggerInterface $logger = null;

    public function __construct()
    {
        // Logger will be injected automatically via DI
    }

    /**
     * 检查系统是否已安装
     */
    public function isInstalled(): bool
    {
        $envFile = BASE_PATH . '/.env';
        if (! file_exists($envFile)) {
            return false;
        }

        $envContent = file_get_contents($envFile);
        if (empty($envContent)) {
            return false;
        }

        $config = $this->parseEnv($envContent);

        return ! empty($config['DB_DATABASE'])
            && ! empty($config['DB_HOST'])
            && ! empty($config['DB_USERNAME'])
            && $this->checkDatabaseConnection($config);
    }

    /**
     * 获取安装状态信息
     */
    public function getInstallStatus(): array
    {
        $status = [
            'installed' => false,
            'env_exists' => false,
            'db_configured' => false,
            'db_connected' => false,
            'migrations_run' => false,
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
                    $status['installed'] = true;
                    $status['message'] = 'System is installed';

                    // 检查迁移是否已执行
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
     * 创建数据库
     */
    public function createDatabase(array $config): bool
    {
        try {
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

            $this->log('info', "Database '{$config['DB_DATABASE']}' created successfully");
            return true;
        } catch (\PDOException $e) {
            $this->log('error', "Failed to create database: " . $e->getMessage());
            throw new \RuntimeException("Failed to create database: " . $e->getMessage());
        }
    }

    /**
     * 检查数据库是否存在
     */
    public function databaseExists(array $config): bool
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
            $exists = $result->rowCount() > 0;

            $this->log('info', "Database '{$config['DB_DATABASE']}' exists: " . ($exists ? 'yes' : 'no'));
            return $exists;
        } catch (\PDOException $e) {
            $this->log('error', "Failed to check database: " . $e->getMessage());
            throw new BusinessException(ResultCode::FAIL, "Failed to check database existence: " . $e->getMessage());
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
     * @return array{success: bool, message: string, database_exists: bool}
     */
    public function testDatabaseConnection(array $config): array
    {
        // 先检查数据库是否已存在
        $dbExists = $this->checkDatabaseExists($config);
        if ($dbExists) {
            return [
                'success' => false,
                'message' => '数据库 "' . $config['DB_DATABASE'] . '" 已存在，请更换其他库名。',
                'database_exists' => true,
            ];
        }

        // 再测试服务器连接
        $connected = $this->checkDatabaseConnection($config);
        if (! $connected) {
            return [
                'success' => false,
                'message' => '无法连接到 MySQL 服务器，请检查连接设置。',
                'database_exists' => false,
            ];
        }

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
            // 只测试 MySQL 服务器连接，不指定数据库
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
        try {
            $envFile = BASE_PATH . '/.env';
            if (! file_exists($envFile)) {
                throw new BusinessException(ResultCode::FAIL, '.env file not found');
            }

            $this->log('info', 'Starting migrations via command');

            // 使用 exec 执行 Hyperf 的迁移命令（子进程会读取更新后的 .env）
            $output = [];
            $returnCode = 0;
            exec('cd ' . BASE_PATH . ' && php bin/hyperf.php migrate --force 2>&1', $output, $returnCode);

            $outputStr = implode("\n", $output);
            $this->log('info', 'Migration command output: ' . $outputStr);

            if ($returnCode !== 0) {
                throw new BusinessException(ResultCode::FAIL, "Migration failed with code {$returnCode}: " . $outputStr);
            }

            $this->log('info', 'All migrations completed successfully');
            return true;
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->log('error', 'Migration failed: ' . $e->getMessage());
            throw new BusinessException(ResultCode::FAIL, "Migration failed: " . $e->getMessage());
        }
    }

    /**
     * 使用 PDO 获取已迁移的表列表
     */
    private function getMigratedTablesWithPdo(\PDO $pdo, string $dbName): array
    {
        try {
            $result = $pdo->query("SHOW TABLES FROM `{$dbName}`");
            $tables = [];
            while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            return $tables;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 配置 Hyperf 数据库连接
     */
    private function configureHyperfConnection(array $envConfig): void
    {
        try {
            // 首先更新当前进程的环境变量
            foreach ($envConfig as $key => $value) {
                if (! is_array($value)) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }

            // 获取容器
            $container = \Hyperf\Context\ApplicationContext::getContainer();

            // 获取连接解析器
            $resolver = $container->get(\Hyperf\Database\ConnectionResolverInterface::class);

            // 获取连接
            $connection = $resolver->connection();

            // 使用反射获取所有属性
            $reflection = new \ReflectionClass($connection);
            $properties = $reflection->getProperties();

            foreach ($properties as $property) {
                $property->setAccessible(true);
                $propertyName = $property->getName();

                // 更新 config
                if ($propertyName === 'config') {
                    $config = $property->getValue($connection) ?: [];
                    $config['host'] = $envConfig['DB_HOST'] ?? 'localhost';
                    $config['port'] = $envConfig['DB_PORT'] ?? 3306;
                    $config['database'] = $envConfig['DB_DATABASE'] ?? '';
                    $config['username'] = $envConfig['DB_USERNAME'] ?? 'root';
                    $config['password'] = $envConfig['DB_PASSWORD'] ?? '';
                    $config['charset'] = $envConfig['DB_CHARSET'] ?? 'utf8mb4';
                    $config['collation'] = $envConfig['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
                    $config['prefix'] = $envConfig['DB_PREFIX'] ?? '';
                    $property->setValue($connection, $config);
                    $this->log('info', 'Updated connection config');
                }

                // 更新 PDO 连接
                if ($propertyName === 'pdo' || stripos($propertyName, 'pdo') !== false) {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                        $envConfig['DB_HOST'] ?? 'localhost',
                        $envConfig['DB_PORT'] ?? 3306,
                        $envConfig['DB_DATABASE'] ?? '',
                        $envConfig['DB_CHARSET'] ?? 'utf8mb4'
                    );
                    $newPdo = new \PDO(
                        $dsn,
                        $envConfig['DB_USERNAME'] ?? 'root',
                        $envConfig['DB_PASSWORD'] ?? '',
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    $property->setValue($connection, $newPdo);
                    $this->log('info', 'Replaced PDO connection in property: ' . $propertyName);
                }
            }

            // 尝试直接替换容器中的连接实例
            $this->replaceConnectionInResolver($resolver, $connection);

            $this->log('info', 'Hyperf connection fully configured');
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to configure Hyperf connection: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 替换 Resolver 中的连接实例
     */
    private function replaceConnectionInResolver($resolver, $connection): void
    {
        try {
            $reflection = new \ReflectionClass($resolver);
            $properties = $reflection->getProperties();

            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($resolver);

                // 如果是数组，尝试找到连接池并替换
                if (is_array($value)) {
                    foreach ($value as $key => $conn) {
                        if (is_object($conn) && method_exists($conn, 'getName')) {
                            // 这是连接实例
                            if ($conn->getName() === $connection->getName()) {
                                $value[$key] = $connection;
                                $property->setValue($resolver, $value);
                                $this->log('info', 'Replaced connection in resolver property: ' . $property->getName());
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->log('warning', 'Could not replace connection in resolver: ' . $e->getMessage());
        }
    }

    /**
     * 使用 PDO 执行单个迁移文件
     */
    private function executeMigrationWithPdo(\PDO $pdo, string $migrationFile): void
    {
        $migrationPath = BASE_PATH . '/databases/migrations/' . $migrationFile;
        $className = $this->getClassNameFromMigration($migrationFile);

        require_once $migrationPath;

        /** @var \Hyperf\Database\Migrations\Migration $migration */
        $migration = new $className();

        // 使用反射来调用 up 方法
        if (method_exists($migration, 'up')) {
            $migration->up();
        }
    }

    /**
     * 获取迁移文件列表
     */
    private function getMigrationFiles(): array
    {
        $migrationsPath = BASE_PATH . '/databases/migrations/';
        $files = glob($migrationsPath . '*.php');

        $migrationFiles = [];
        foreach ($files as $file) {
            $migrationFiles[] = basename($file);
        }

        sort($migrationFiles);
        return $migrationFiles;
    }

    /**
     * 从迁移文件名获取表名
     */
    private function getTableNameFromMigration(string $migrationFile): string
    {
        // 从文件名提取表名，例如 create_user_table.php -> user
        if (preg_match('/create_(\w+)_table\.php$/', $migrationFile, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * 从迁移文件名获取类名
     */
    private function getClassNameFromMigration(string $migrationFile): string
    {
        // 例如: 2021_04_12_160526_create_user_table.php -> CreateUserTable
        $content = file_get_contents(BASE_PATH . '/databases/migrations/' . $migrationFile);

        if (preg_match('/class\s+(\w+)\s+extends/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * 获取已保存的 .env 配置
     */
    public function getEnvConfig(): array
    {
        $envFile = BASE_PATH . '/.env';
        return $this->parseEnvFile($envFile);
    }

    /**
     * 通过命令行执行迁移
     */
    public function runMigrationsViaCommand(): bool
    {
        try {
            // 执行迁移命令
            $cmd = sprintf(
                'cd %s && php ./bin/hyperf.php migrate --force 2>&1',
                BASE_PATH
            );

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            $outputStr = implode("\n", $output);

            if ($returnCode !== 0) {
                $this->log('error', 'Migration command failed: ' . $outputStr);
                throw new BusinessException(ResultCode::FAIL, "Migration failed: " . $outputStr);
            }

            $this->log('info', 'Migrations completed via command: ' . $outputStr);
            return true;
        } catch (\Throwable $e) {
            $this->log('error', 'Migration failed: ' . $e->getMessage());
            throw new BusinessException(ResultCode::FAIL, "Migration failed: " . $e->getMessage());
        }
    }

    /**
     * 清除代理缓存
     */
    public function clearProxyCache(): void
    {
        $cacheFile = BASE_PATH . '/runtime/container/proxy/';
        if (is_dir($cacheFile)) {
            array_map('unlink', glob("{$cacheFile}*.php"));
        }
    }

    /**
     * 种子数据填充
     */
    public function seedDatabase(?string $adminUsername = null, ?string $adminPassword = null): bool
    {
        try {
            // 读取刚创建的 .env 文件获取配置
            $envFile = BASE_PATH . '/.env';
            if (! file_exists($envFile)) {
                throw new BusinessException(ResultCode::FAIL, '.env file not found');
            }

            $envConfig = $this->parseEnvFile($envFile);

            // 配置 Hyperf 数据库连接
            $this->configureDatabaseConnection($envConfig);

            // 执行数据库迁移命令
            $this->runMigrationsCommand();

            // 执行数据填充命令
            $this->runSeederCommand($adminUsername, $adminPassword);

            $this->log('info', 'Database seeded successfully');
            return true;
        } catch (\Throwable $e) {
            $this->log('error', 'Seeding failed: ' . $e->getMessage());
            throw new BusinessException(ResultCode::FAIL, "Seeding failed: " . $e->getMessage());
        }
    }

    /**
     * 执行数据库迁移命令
     */
    private function runMigrationsCommand(): void
    {
        $this->log('info', 'Running migrations...');

        $command = 'php bin/hyperf.php migrate --force 2>&1';
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        $this->log('info', 'Migration output: ' . $outputStr);

        $this->log('info', 'Migrations completed');
    }

    /**
     * 执行数据填充命令
     */
    private function runSeederCommand(?string $adminUsername, ?string $adminPassword): void
    {
        $this->log('info', 'Running seeders...');

        try {
            $seeder = new \Database\DatabaseSeeder();
            $seeder->run($adminUsername, $adminPassword);
            $this->log('info', 'Seeders completed successfully');
        } catch (\Throwable $e) {
            $this->log('error', 'Seeder error: ' . $e->getMessage());
            throw new \RuntimeException('Seeder failed: ' . $e->getMessage());
        }
    }

    /**
     * 配置 Hyperf 数据库连接
     */
    private function configureDatabaseConnection(array $envConfig): void
    {
        try {
            $container = \Hyperf\Context\ApplicationContext::getContainer();

            // 获取连接解析器
            $resolver = $container->get(\Hyperf\Database\ConnectionResolverInterface::class);

            // 获取连接
            $connection = $resolver->connection();

            // 使用反射更新连接配置
            $reflection = new \ReflectionClass($connection);

            // 找到并更新 config 属性
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

            // 重新连接数据库
            $connection->reconnect();

            $this->log('info', 'Database connection configured: ' . $config['database']);
        } catch (\Throwable $e) {
            $this->log('warning', 'Failed to configure database connection: ' . $e->getMessage());
            throw $e;
        }
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
     * 创建 .env 文件
     */
    public function createEnvFile(array $config): bool
    {
        $envExample = file_get_contents(BASE_PATH . '/.env.example');
        if ($envExample === false) {
            throw new BusinessException(ResultCode::FAIL, '.env.example file not found');
        }

        // 生成 JWT secret
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

        $this->log('info', '.env file created successfully');
        return true;
    }

    /**
     * 获取数据库列表（用于检测连接）
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
                // 排除系统数据库
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
     * 重新加载环境变量配置
     */
    public function reloadEnvConfig(): void
    {
        // 清除配置缓存
        if (function_exists('opcache_get_status')) {
            opcache_get_status();
        }

        // 重新加载 .env 配置到 Hyperf
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
            1 => ['title' => 'Environment Check', 'description' => 'Check system requirements'],
            2 => ['title' => 'Database Configuration', 'description' => 'Configure database connection'],
            3 => ['title' => 'Create Database', 'description' => 'Create database if not exists'],
            4 => ['title' => 'Run Migrations', 'description' => 'Create database tables'],
            5 => ['title' => 'Seed Data', 'description' => 'Insert initial data'],
        ];
    }
}
