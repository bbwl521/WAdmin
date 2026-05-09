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

namespace App\Http\Admin\Controller;

use App\Http\Admin\Request\InstallRequest;
use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Result;
use App\Service\InstallService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\Post;

#[Controller(prefix: '/admin/install', server: 'http')]
#[OA\HyperfServer(name: 'http')]
class InstallController extends AbstractController
{
    public function __construct(
        private readonly InstallService $installService,
        private readonly RequestInterface $request
    ) {}

    #[Get(
        path: '/status',
        operationId: 'getInstallStatus',
        summary: 'Get installation status',
        tags: ['admin:install']
    )]
    public function status(): Result
    {
        return $this->success($this->installService->getInstallStatus());
    }

    #[Get(
        path: '/check',
        operationId: 'checkInstalled',
        summary: 'Check if system is installed',
        tags: ['admin:install']
    )]
    public function check(): Result
    {
        return $this->success([
            'installed' => $this->installService->isInstalled(),
        ]);
    }

    #[Get(
        path: '/steps',
        operationId: 'getInstallSteps',
        summary: 'Get installation steps',
        tags: ['admin:install']
    )]
    public function steps(): Result
    {
        return $this->success($this->installService->getInstallSteps());
    }

    #[Post(
        path: '/test-connection',
        operationId: 'testDatabaseConnection',
        summary: 'Test database connection',
        tags: ['admin:install']
    )]
    public function testConnection(): Result
    {
        $data = $this->request->all();
        $host = $data['host'] ?? 'localhost';
        $port = (int) ($data['port'] ?? 3306);
        $database = $data['database'] ?? '';
        $username = $data['username'] ?? 'root';
        $password = $data['password'] ?? '';

        if (empty($database)) {
            return $this->error('Database name is required');
        }

        $config = [
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'DB_CHARSET' => $data['charset'] ?? 'utf8mb4',
        ];

        $result = $this->installService->testDatabaseConnection($config);

        if ($result['success']) {
            return $this->success($result);
        } else {
            return $this->error($result['message'], [
                'database_exists' => $result['database_exists'] ?? false,
            ]);
        }
    }

    #[Get(
        path: '/databases',
        operationId: 'getDatabases',
        summary: 'Get database list',
        tags: ['admin:install']
    )]
    public function getDatabases(): Result
    {
        $host = $this->request->input('host', 'localhost');
        $port = (int) $this->request->input('port', 3306);
        $username = $this->request->input('username', 'root');
        $password = $this->request->input('password', '');

        $config = [
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ];

        try {
            $databases = $this->installService->getDatabaseList($config);
            return $this->success([
                'success' => true,
                'databases' => $databases,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Post(
        path: '/install',
        operationId: 'doInstall',
        summary: 'Execute system installation',
        tags: ['admin:install']
    )]
    public function install(?InstallRequest $request = null): Result
    {
        try {
            $data = $this->request->all();
            $config = $request !== null ? $request->getDatabaseConfig() : $this->getDatabaseConfigFromRequest();
            $createDb = $data['create_db'] ?? true;
            $runMigrations = $data['run_migrations'] ?? true;
            $seedData = $data['seed_data'] ?? true;
            $adminUsername = $data['admin_username'] ?? 'admin';
            $adminPassword = $data['admin_password'] ?? '123456';

            $this->installService->createEnvFile($config);

            // 重新加载 .env 文件到当前进程
            $this->installService->reloadEnvConfig();

            if ($createDb) {
                $this->installService->createDatabase($config);
            }

            if ($runMigrations) {
                $this->installService->runMigrations();
            }

            if ($seedData) {
                $this->installService->seedDatabase($adminUsername, $adminPassword);
            }

            return $this->success([
                'installed' => true,
                'admin_username' => $adminUsername,
                'message' => 'System installed successfully',
            ]);
        } catch (\Throwable $e) {
            $errorMessage = 'Installation failed: ' . $e->getMessage();
            // 获取更详细的错误信息
            $trace = $e->getTraceAsString();
            // 只取前几行堆栈信息
            $traceLines = explode("\n", $trace);
            $shortTrace = implode("\n", array_slice($traceLines, 0, 10));
            $errorMessage .= "\n\nTrace:\n" . $shortTrace;
            return $this->error($errorMessage);
        }
    }

    private function getDatabaseConfigFromRequest(): array
    {
        return [
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => $this->request->input('host', 'localhost'),
            'DB_PORT' => (int) $this->request->input('port', 3306),
            'DB_DATABASE' => $this->request->input('database'),
            'DB_USERNAME' => $this->request->input('username'),
            'DB_PASSWORD' => $this->request->input('password', ''),
            'DB_CHARSET' => $this->request->input('charset', 'utf8mb4'),
            'DB_COLLATION' => $this->request->input('collation', 'utf8mb4_unicode_ci'),
            'DB_PREFIX' => $this->request->input('prefix', ''),
            'APP_NAME' => $this->request->input('app_name', 'MineAdmin'),
            'APP_URL' => $this->request->input('app_url', 'http://127.0.0.1:9501'),
            'REDIS_HOST' => $this->request->input('redis_host', '127.0.0.1'),
            'REDIS_PORT' => (int) $this->request->input('redis_port', 6379),
            'REDIS_AUTH' => $this->request->input('redis_auth', ''),
            'REDIS_DB' => (int) $this->request->input('redis_db', 0),
        ];
    }
}
