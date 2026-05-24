# 插件系统概述

WAdmin 提供了一套完整的热插拔式插件架构，支持**零命令行安装**。只需按规范编写插件并打包为 zip，用户即可在管理后台一键安装、卸载、启用、禁用和升级。

## 核心特性

| 特性 | 说明 |
|------|------|
| **零命令行** | 无需 `composer require` 或 `php bin/hyperf.php` |
| **运行时自动加载** | PSR-4 动态注入 Composer ClassLoader |
| **数据库迁移追踪** | `plugin_migrations` 表记录已执行迁移，幂等安装 |
| **版本升级** | 增量升级，失败自动回滚 |
| **路由冲突检测** | 安装前检测路由冲突 |
| **菜单权限自动注册** | 菜单树 + Casbin 规则，管理员角色可配置 |
| **启用/禁用** | 禁用后菜单移除、路由清除、请求 403 |
| **生命周期钩子** | 6 个钩子覆盖完整生命周期 |
| **配置管理** | `plugin.json` 声明 + `PluginConfigService` 读写 |
| **安装回滚** | 任一步骤失败逆序回滚 |
| **依赖版本校验** | 语义化版本约束 |
| **菜单即时同步** | 安装/卸载后前端自动刷新侧边栏 |

## plugin.json 清单

插件的唯一入口，安装引擎通过它识别身份和元数据：

```json
{
    "code": "feedback",
    "name": "意见反馈",
    "version": "1.0.0",
    "description": "收集用户意见与反馈",
    "author": { "name": "MineAdmin", "email": "root@imoi.cn" },
    "hyperf": ">=3.1",
    "mineadmin": ">=3.0",
    "dependencies": {},
    "autoload": { "psr-4": { "Plugin\\Feedback\\": "src/" } },
    "permissions": [
        { "name": "feedback:index", "display_name": "反馈列表" },
        { "name": "feedback:save", "display_name": "提交反馈" }
    ],
    "menus": [
        {
            "name": "feedback",
            "meta": { "title": "意见反馈", "icon": "ri:feedback-line", "type": "M" },
            "path": "/feedback",
            "component": "base/views/feedback/index",
            "children": [
                { "name": "feedback:index", "meta": { "title": "反馈列表", "type": "B" } }
            ]
        }
    ],
    "config": [
        { "key": "max_length", "label": "最大字符数", "type": "number", "default": 500 }
    ]
}
```

| 字段 | 必需 | 说明 |
|------|------|------|
| `code` | 是 | 插件唯一标识，全小写+下划线 |
| `name` | 是 | 显示名称 |
| `version` | 是 | 语义化版本号 |
| `autoload.psr-4` | 否 | 命名空间→目录映射 |
| `dependencies` | 否 | 其他插件依赖，支持版本约束 |
| `permissions` | 否 | Casbin 权限规则 |
| `menus` | 否 | 菜单树，支持嵌套 children |
| `config` | 否 | 配置项声明 |

### 依赖版本约束

| 约束 | 示例 | 含义 |
|------|------|------|
| `>=1.0` | `>=1.0.0` | 版本 ≥ 1.0.0 |
| `^1.2` | `^1.2.3` | `>=1.2.3 <2.0.0` |
| `~1.2` | `~1.2.3` | `>=1.2.3 <1.3.0` |
| `1.*` | `1.*` | `>=1.0.0 <2.0.0` |

### 菜单类型

| type | 含义 |
|------|------|
| `M` | 菜单目录（显示在侧边栏） |
| `B` | 按钮权限（不显示，仅鉴权） |

## 路由定义

`routes.php` 返回二维数组：

```php
<?php
return [
    ['GET',    '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@index'],
    ['POST',   '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@store'],
    ['PUT',    '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@update'],
    ['DELETE', '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@destroy'],
];
```

## 配置读写

```php
use App\Plugin\PluginManager;

// 带默认值
$maxLen = $this->pluginManager->getConfig('feedback', 'max_length', 500);

// 更新
$this->pluginManager->setConfig('feedback', ['max_length' => 1000]);
```

## 迁移文件

放在 `migrations/` 目录下，字典序执行，通过 `plugin_migrations` 表追踪：

```php
<?php
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateFeedbackTable extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
}
```

## 数据表

### plugin

| 字段 | 说明 |
|------|------|
| `code` | 唯一标识 |
| `status` | 1=启用，2=禁用 |
| `config` | JSON 私有配置 |
| `meta` | plugin.json 快照 |

### plugin_migrations

| 字段 | 说明 |
|------|------|
| `plugin_code` | 插件标识 |
| `migration` | 迁移文件名 |

## 系统配置

`config/autoload/plugin.php`：

| 配置 | 默认 | 环境变量 |
|------|------|------|
| `super_admin_role_id` | `1` | `PLUGIN_SUPER_ADMIN_ROLE_ID` |
| `super_admin_role_name` | `SuperAdmin` | `PLUGIN_SUPER_ADMIN_ROLE_NAME` |
| `guard_cache_ttl` | `60` | `PLUGIN_GUARD_CACHE_TTL` |
| `allow_unsigned` | `true` | `PLUGIN_ALLOW_UNSIGNED` |
