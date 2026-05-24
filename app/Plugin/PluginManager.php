<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\Plugin as PluginModel;
use App\Plugin\DTO\PluginManifest;
use App\Plugin\Exception\PluginNotFoundException;
use Hyperf\Collection\Collection;

/**
 * 插件管理器 —— 插件系统的统一入口.
 *
 * 对外提供安装、卸载、升级、启用、禁用、配置管理、列表查询等能力。
 */
final class PluginManager
{
    public function __construct(
        private readonly PluginInstallService $installer,
        private readonly PluginUninstallService $uninstaller,
        private readonly PluginUpgradeService $upgrader,
        private readonly PluginConfigService $config,
    ) {}

    /** 从本地目录安装 */
    public function install(string $pluginDir): PluginModel
    {
        return $this->installer->installFromLocal($pluginDir);
    }

    /** 从 zip 包安装 */
    public function installFromZip(string $zipPath): PluginModel
    {
        return $this->installer->installFromZip($zipPath);
    }

    /** 从远程 URL 安装 */
    public function installFromRemote(string $url, string $hash = ''): PluginModel
    {
        return $this->installer->installFromRemote($url, $hash);
    }

    /** 从本地目录升级 */
    public function upgrade(string $pluginDir): PluginModel
    {
        return $this->upgrader->upgradeFromLocal($pluginDir);
    }

    /** 从 zip 包升级 */
    public function upgradeFromZip(string $zipPath): PluginModel
    {
        return $this->upgrader->upgradeFromZip($zipPath);
    }

    /** 从远程 URL 升级 */
    public function upgradeFromRemote(string $url, string $hash = ''): PluginModel
    {
        return $this->upgrader->upgradeFromRemote($url, $hash);
    }

    /** 卸载 */
    public function uninstall(string $code, bool $keepData = false): void
    {
        $this->uninstaller->uninstall($code, $keepData);
    }

    /** 启用 */
    public function enable(string $code): void
    {
        $this->uninstaller->enable($code);
    }

    /** 禁用 */
    public function disable(string $code): void
    {
        $this->uninstaller->disable($code);
    }

    /** 获取插件配置 */
    public function getConfig(string $code, ?string $key = null, mixed $default = null): mixed
    {
        return $this->config->get($code, $key, $default);
    }

    /** 更新插件配置 */
    public function setConfig(string $code, array $values): void
    {
        $this->config->set($code, $values);
    }

    /** 获取配置 schema */
    public function getConfigSchema(string $code): array
    {
        return $this->config->getSchema($code);
    }

    /** 获取已安装插件列表（含状态） */
    public function installed(): Collection
    {
        return PluginModel::query()->orderBy('id')->get();
    }

    /** 获取单个插件详情 */
    public function detail(string $code): PluginModel
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        return $plugin;
    }

    /** 检查是否已安装 */
    public function isInstalled(string $code): bool
    {
        return PluginModel::query()->where('code', $code)->exists();
    }

    /** 检查是否已启用 */
    public function isEnabled(string $code): bool
    {
        $plugin = PluginModel::query()->where('code', $code)->first();

        return $plugin !== null && $plugin->status === 1;
    }
}
