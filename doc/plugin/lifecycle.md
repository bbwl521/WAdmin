# 插件生命周期

插件通过实现 `App\Plugin\Contract\PluginInterface` 接口来响应生命周期事件。

## 接口定义

```php
interface PluginInterface
{
    public function onInstall(): void;
    public function onUninstall(): void;
    public function onUpgrade(): void;
    public function onBeforeUpgrade(): void;
    public function onEnable(): void;
    public function onDisable(): void;
}
```

## 钩子触发时机

| 钩子 | 触发时机 | 典型用途 |
|------|------|------|
| `onInstall()` | 安装完成后（迁移、路由、菜单已注册） | 初始化数据、预热缓存 |
| `onUninstall()` | 卸载开始前（菜单、路由、文件尚未删除） | 清理自定义数据、通知 |
| `onUpgrade()` | 升级完成后（新版代码执行） | 数据迁移、配置更新 |
| `onBeforeUpgrade()` | 升级开始前（旧版代码执行） | 备份数据、暂停服务 |
| `onEnable()` | 启用时（菜单恢复、路由注册后） | 恢复定时任务、开启监听 |
| `onDisable()` | 禁用时（菜单移除、路由清除前） | 暂停定时任务、关闭连接 |

## 完整示例

```php
<?php

declare(strict_types=1);

namespace Plugin\Feedback;

use App\Plugin\Contract\PluginInterface;

final class Plugin implements PluginInterface
{
    public function onInstall(): void
    {
        // 安装时初始化
    }

    public function onUninstall(): void
    {
        // 卸载时清理
    }

    public function onUpgrade(): void
    {
        // 升级后新版代码执行
    }

    public function onBeforeUpgrade(): void
    {
        // 升级前旧版代码执行
    }

    public function onEnable(): void
    {
        // 启用时恢复
    }

    public function onDisable(): void
    {
        // 禁用时暂停
    }
}
```

## 注意事项

- **不需要构造函数**。系统从数据库 `plugin.meta` 和 `plugin.json` 读取元数据，不依赖 Plugin 实例的属性
- **不需要导入 PluginManifest**。`code()` 和 `manifest()` 已从接口中移除
- **方法体为空完全正常**。系统自动处理迁移、路由和菜单，钩子仅用于自定义逻辑
- **钩子异常不影响主流程**。钩子执行失败仅记录 warning 日志，不会阻断安装/卸载

## 安装流程全貌

```
guardNotInstalled ─→ checkDependencies ─→ checkConflicts
        │
        ▼
  register PSR-4 ─→ 失败则回滚
        │
        ▼
  runUp(迁移)    ─→ 失败则回滚迁移+PSR-4
        │
        ▼
  register(路由)  ─→ 失败则回滚路由+迁移+PSR-4
        │
        ▼
  register(菜单)  ─→ 失败则回滚菜单+路由+迁移+PSR-4
        │
        ▼
  DB insert ─→ onInstall()
```

## 卸载流程全貌

```
onUninstall()
    │
    ▼
unregister(菜单) ─→ runDown(迁移) ─→ removeScanPaths ─→ removeRouteCache ─→ deleteFiles ─→ delete DB
```

## 升级流程全貌

```
版本检查 ─→ 备份旧版 ─→ onBeforeUpgrade()
    │
    ▼
新版PSR-4 ─→ 新迁移 ─→ 新路由 ─→ 新菜单 ─→ 更新DB ─→ onUpgrade()
    │
    ▼ (失败)
恢复备份目录
```
