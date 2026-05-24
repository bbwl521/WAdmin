<?php

declare(strict_types=1);

namespace App\Plugin\Contract;

interface PluginInterface
{
    /** 安装时调用 */
    public function onInstall(): void;

    /** 卸载时调用 */
    public function onUninstall(): void;

    /** 升级时调用（新版本代码执行） */
    public function onUpgrade(): void;

    /** 升级前调用（旧版本代码执行） */
    public function onBeforeUpgrade(): void;

    /** 启用时调用 */
    public function onEnable(): void;

    /** 禁用时调用 */
    public function onDisable(): void;
}
