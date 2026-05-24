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

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Result;
use App\Plugin\Exception\PluginConflictException;
use App\Plugin\Exception\PluginNotFoundException;
use App\Plugin\MarketplacePluginService;
use App\Plugin\MarketplaceService;
use App\Plugin\PluginPublisher;
use App\Plugin\PluginManager;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Mine\Swagger\Attributes\ResultResponse;

#[HyperfServer(name: 'http')]
final class PluginController extends AbstractController
{
    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly MarketplaceService $marketplaceService,
        private readonly MarketplacePluginService $mktPluginService,
        private readonly PluginPublisher $publisher,
        private readonly RequestInterface $request,
    ) {}

    // ============================================================
    //  市场上传管理 API（市场运营者专用）
    // ============================================================

    #[Post(
        path: '/admin/plugin/marketplace/upload',
        operationId: 'uploadMarketplacePlugin',
        summary: '上传插件包到市场',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplaceUpload(): Result
    {
        try {
            $file = $this->request->file('file');
            if ($file === null) {
                return $this->error('请选择插件包文件');
            }

            $plugin = $this->mktPluginService->upload($file);

            return $this->success([
                'uploaded' => true,
                'plugin' => $plugin->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->error('上传失败: ' . $e->getMessage());
        }
    }

    #[Put(
        path: '/admin/plugin/marketplace/{code}/unpublish',
        operationId: 'unpublishMarketplacePlugin',
        summary: '下架市场插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplaceUnpublish(string $code): Result
    {
        try {
            $this->mktPluginService->unpublish($code);
            return $this->success(['message' => '已下架']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Delete(
        path: '/admin/plugin/marketplace/{code}',
        operationId: 'deleteMarketplacePlugin',
        summary: '删除市场插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplaceDelete(string $code): Result
    {
        try {
            $this->mktPluginService->delete($code);
            return $this->success(['message' => '已删除']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    // ============================================================
    //  插件市场 API（必须在动态路由前声明，避免路由冲突）
    // ============================================================

    #[Get(
        path: '/admin/plugin/marketplace',
        operationId: 'getMarketplacePlugins',
        summary: '获取插件市场列表',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplace(): Result
    {
        return $this->success(
            $this->marketplaceService->search($this->request->all())
        );
    }

    #[Get(
        path: '/admin/plugin/marketplace/{code}',
        operationId: 'getMarketplacePluginDetail',
        summary: '获取市场插件详情',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplaceDetail(string $code): Result
    {
        $detail = $this->marketplaceService->detail($code);
        if ($detail === null) {
            return $this->error('插件不存在或市场不可用');
        }
        $detail['installed'] = $this->pluginManager->isInstalled($code);
        return $this->success($detail);
    }

    #[Post(
        path: '/admin/plugin/marketplace/install',
        operationId: 'installMarketplacePlugin',
        summary: '从市场安装插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function marketplaceInstall(): Result
    {
        try {
            $code = $this->request->input('code', '');
            if ($code === '') {
                return $this->error('请指定插件标识 code');
            }
            $version = $this->request->input('version', '');
            $downloadUrl = $this->marketplaceService->getDownloadUrl($code, $version);
            if ($downloadUrl !== null && $downloadUrl !== '') {
                $plugin = $this->pluginManager->installFromRemote($downloadUrl);
            } else {
                $localPath = BASE_PATH . '/plugins/' . $code;
                if (! is_dir($localPath)) {
                    return $this->error('无法下载插件，且本地也未找到');
                }
                $plugin = $this->pluginManager->install($localPath);
            }
            return $this->success(['installed' => true, 'plugin' => $plugin->toArray(), 'need_refresh_menu' => true]);
        } catch (\Throwable $e) {
            return $this->error('安装失败: ' . $e->getMessage());
        }
    }

    // ============================================================
    //  已安装插件管理 API
    // ============================================================

    #[Get(
        path: '/admin/plugin',
        operationId: 'getPluginList',
        summary: '获取已安装插件列表',
        security: [],
        tags: ['admin:plugin']
    )]
    #[ResultResponse(instance: Result::class)]
    public function index(): Result
    {
        return $this->success(
            $this->pluginManager->installed()->toArray()
        );
    }

    #[Post(
        path: '/admin/plugin/install',
        operationId: 'installPlugin',
        summary: '手动安装插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function install(): Result
    {
        try {
            $data = $this->request->all();
            $source = $data['source'] ?? 'local';
            if ($source === 'remote') {
                $url = $data['url'] ?? '';
                if ($url === '') {
                    return $this->error('远程安装需要提供 url');
                }
                $plugin = $this->pluginManager->installFromRemote($url, $data['hash'] ?? '');
            } elseif ($source === 'zip') {
                $zipPath = $data['path'] ?? '';
                if ($zipPath === '' || ! file_exists($zipPath)) {
                    return $this->error('zip 文件路径无效');
                }
                $plugin = $this->pluginManager->installFromZip($zipPath);
            } else {
                $localPath = $data['path'] ?? '';
                if ($localPath === '' || ! is_dir($localPath)) {
                    return $this->error('插件目录路径无效');
                }
                $plugin = $this->pluginManager->install($localPath);
            }
            return $this->success(['installed' => true, 'plugin' => $plugin->toArray(), 'need_refresh_menu' => true]);
        } catch (PluginConflictException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error('安装失败: ' . $e->getMessage());
        }
    }

    #[Get(
        path: '/admin/plugin/{code}',
        operationId: 'getPluginDetail',
        summary: '获取插件详情',
        security: [],
        tags: ['admin:plugin']
    )]
    public function detail(string $code): Result
    {
        try {
            return $this->success($this->pluginManager->detail($code)->toArray());
        } catch (PluginNotFoundException $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Delete(
        path: '/admin/plugin/{code}',
        operationId: 'uninstallPlugin',
        summary: '卸载插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function uninstall(string $code): Result
    {
        try {
            $keepData = (bool) ($this->request->input('keep_data', false));
            $this->pluginManager->uninstall($code, $keepData);
            return $this->success(['message' => '插件已卸载', 'need_refresh_menu' => true]);
        } catch (PluginNotFoundException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error('卸载失败: ' . $e->getMessage());
        }
    }

    #[Put(
        path: '/admin/plugin/{code}/enable',
        operationId: 'enablePlugin',
        summary: '启用插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function enable(string $code): Result
    {
        try {
            $this->pluginManager->enable($code);
            return $this->success(['message' => '插件已启用', 'need_refresh_menu' => true]);
        } catch (PluginNotFoundException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error('启用失败: ' . $e->getMessage());
        }
    }

    #[Put(
        path: '/admin/plugin/{code}/disable',
        operationId: 'disablePlugin',
        summary: '禁用插件',
        security: [],
        tags: ['admin:plugin']
    )]
    public function disable(string $code): Result
    {
        try {
            $this->pluginManager->disable($code);
            return $this->success(['message' => '插件已禁用', 'need_refresh_menu' => true]);
        } catch (PluginNotFoundException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error('禁用失败: ' . $e->getMessage());
        }
    }
    #[Post(
        path: '/admin/plugin/{code}/publish',
        operationId: 'publishPlugin',
        summary: '发布插件到市场',
        security: [],
        tags: ['admin:plugin']
    )]
    public function publish(string $code): Result
    {
        try {
            $apiToken = $this->request->input('api_token', '');
            if ($apiToken === '') {
                return $this->error('请提供开发者 API Token');
            }

            if (! $this->pluginManager->isInstalled($code)) {
                return $this->error("插件 '{$code}' 未安装");
            }

            // 1. 校验
            $validation = $this->publisher->validate($code);
            if (! $validation['valid']) {
                return $this->error('插件校验未通过: ' . implode('; ', $validation['errors']));
            }

            // 2. 打包
            $zipPath = $this->publisher->package($code);

            // 3. 读取元数据
            $manifest = json_decode(file_get_contents(BASE_PATH . '/plugins/' . $code . '/plugin.json'), true);

            // 4. 提交到远程市场
            $result = $this->marketplaceService->submit($zipPath, $manifest ?? [], $apiToken);

            // 5. 清理临时 zip
            @unlink($zipPath);

            if ($result['success'] ?? false) {
                return $this->success([
                    'published' => true,
                    'message' => '插件已成功发布到市场',
                    'url' => $result['url'] ?? null,
                ]);
            }

            return $this->error($result['message'] ?? '发布失败');
        } catch (\Throwable $e) {
            return $this->error('发布失败: ' . $e->getMessage());
        }
    }

    #[Get(
        path: '/admin/plugin/{code}/publish/validate',
        operationId: 'validatePluginForPublish',
        summary: '校验插件是否可发布',
        security: [],
        tags: ['admin:plugin']
    )]
    public function validateForPublish(string $code): Result
    {
        try {
            return $this->success($this->publisher->validate($code));
        } catch (\Throwable $e) {
            return $this->success(['valid' => false, 'errors' => [$e->getMessage()]]);
        }
    }

}