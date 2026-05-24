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

namespace App\Http\Admin\Controller;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Result;
use App\Service\InstallService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Swagger\Attributes\ResultResponse;

#[HyperfServer(name: 'http')]
class InstallController extends AbstractController
{
    public function __construct(
        private readonly InstallService $installService,
        private readonly RequestInterface $request
    ) {}

    #[Get(
        path: '/admin/install/status',
        operationId: 'getInstallStatus',
        summary: '获取安装状态',
        security: [],
        tags: ['admin:install']
    )]
    #[ResultResponse(instance: Result::class)]
    public function status(): Result
    {
        return $this->success($this->installService->getInstallStatus());
    }

    #[Get(
        path: '/admin/install/check',
        operationId: 'checkInstalled',
        summary: '检查系统是否已安装',
        security: [],
        tags: ['admin:install']
    )]
    public function check(): Result
    {
        return $this->success([
            'installed' => $this->installService->isInstalled(),
        ]);
    }

    #[Get(
        path: '/admin/install/steps',
        operationId: 'getInstallSteps',
        summary: '获取安装步骤',
        security: [],
        tags: ['admin:install']
    )]
    public function steps(): Result
    {
        return $this->success($this->installService->getInstallSteps());
    }

    #[Get(
        path: '/admin/install/env-check',
        operationId: 'checkEnvironment',
        summary: '执行环境检测',
        security: [],
        tags: ['admin:install']
    )]
    public function envCheck(): Result
    {
        $result = $this->installService->checkEnvironment();
        return $this->success($result);
    }

    #[Get(
        path: '/admin/install/env',
        operationId: 'getEnvironmentInfo',
        summary: '获取环境检测信息',
        security: [],
        tags: ['admin:install']
    )]
    public function envInfo(): Result
    {
        $envService = $this->installService->getEnvironmentCheckService();
        return $this->success([
            'requirements' => $envService->checkAll(),
            'summary' => $envService->getSummary(),
        ]);
    }

    #[Post(
        path: '/admin/install/test-connection',
        operationId: 'testDatabaseConnection',
        summary: '测试数据库连接',
        security: [],
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
        }
        return $this->error($result['message'], [
            'database_exists' => $result['database_exists'] ?? false,
        ]);
    }

    #[Get(
        path: '/admin/install/databases',
        operationId: 'getDatabases',
        summary: '获取数据库列表',
        security: [],
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

    #[Get(
        path: '/admin/install/progress',
        operationId: 'getInstallProgress',
        summary: '获取安装进度',
        security: [],
        tags: ['admin:install']
    )]
    public function progress(): Result
    {
        return $this->success($this->installService->getInstallProgress());
    }

    #[Get(
        path: '/admin/install/logs',
        operationId: 'getInstallLogs',
        summary: '获取安装日志',
        security: [],
        tags: ['admin:install']
    )]
    public function logs(): Result
    {
        return $this->success([
            'logs' => $this->installService->getInstallLogs(),
        ]);
    }

    #[Post(
        path: '/admin/install',
        operationId: 'doInstall',
        summary: '执行系统安装',
        security: [],
        tags: ['admin:install']
    )]
    public function install(): Result
    {
        try {
            $data = $this->request->all();

            // 必填字段校验
            $host = $data['host'] ?? '';
            $database = $data['database'] ?? '';
            $username = $data['username'] ?? '';

            if ($host === '') {
                return $this->error('数据库主机地址 (host) 不能为空');
            }
            if ($database === '') {
                return $this->error('数据库名称 (database) 不能为空');
            }
            if ($username === '') {
                return $this->error('数据库用户名 (username) 不能为空');
            }

            // 管理员账号校验
            $adminUsername = trim($data['admin_username'] ?? 'admin');
            if ($adminUsername === '') {
                return $this->error('管理员用户名不能为空');
            }

            $adminPassword = $data['admin_password'] ?? null;
            if ($adminPassword === null || $adminPassword === '') {
                return $this->error('管理员密码不能为空');
            }
            if (mb_strlen($adminPassword) < 6) {
                return $this->error('管理员密码长度不能少于 6 位');
            }

            // 数据库配置（单一数据源，Service 不再重复构造）
            $dbConfig = [
                'host' => $host,
                'port' => (int) ($data['port'] ?? 3306),
                'database' => $database,
                'username' => $username,
                'password' => $data['password'] ?? '',
                'prefix' => $data['prefix'] ?? '',
            ];

            // 管理员账号
            $adminAccount = [
                'username' => $adminUsername,
                'password' => $adminPassword,
            ];

            // 安装选项
            $options = [
                'dry_run' => (bool) ($data['dry_run'] ?? false),
                'create_db' => (bool) ($data['create_db'] ?? true),
                'run_migrations' => (bool) ($data['run_migrations'] ?? true),
                'seed_data' => (bool) ($data['seed_data'] ?? true),
                'site_name' => $data['app_name'] ?? 'WAdmin',
            ];

            $result = $this->installService->installation($dbConfig, $adminAccount, $options);

            return $this->success([
                'installed' => $result['success'],
                'admin_username' => $result['admin_username'],
                'message' => $result['success'] ? '系统安装成功！' : '安装失败',
                'progress' => $result['progress'],
            ]);
        } catch (\Throwable $e) {
            return $this->error('Installation failed: ' . $e->getMessage());
        }
    }

    #[Post(
        path: '/admin/install/reset',
        operationId: 'resetInstall',
        summary: '重置安装状态（仅未安装时可用）',
        security: [],
        tags: ['admin:install']
    )]
    public function reset(): Result
    {
        try {
            // 守卫：已安装状态下禁止通过 API 重置
            if ($this->installService->isInstalled()) {
                return $this->error('系统已安装，不允许通过 API 重置安装状态。如需重新安装请手动删除 runtime/.install/install.lock');
            }

            $this->installService->resetInstall();
            return $this->success(['message' => '安装状态已重置']);
        } catch (\Throwable $e) {
            return $this->error('重置失败: ' . $e->getMessage());
        }
    }
}
