# MineAdmin 插件开发指南

## 概述

MineAdmin 插件系统提供了一套完整的**零命令行安装**机制。开发者只需按规范编写插件并打包为 zip 文件，用户即可在管理后台一键安装、卸载、启用、禁用和升级插件。安装/卸载后侧边菜单即时同步，无需手动刷新。

### 核心特性

- **零命令行安装/升级**：无需 `composer require` 或 `php bin/hyperf.php`
- **运行时 PSR-4 自动加载**：动态注入 Composer ClassLoader，无需 `composer dump-autoload`
- **数据库迁移追踪**：`plugin_migrations` 表记录已执行迁移，支持幂等安装和增量升级
- **版本升级**：支持增量升级（仅新迁移），失败自动回滚到旧版本
- **路由注册与冲突检测**：按插件 code 精确分组持久化，支持重启恢复；安装前检测路由冲突
- **菜单权限自动注册**：菜单树递归写入 Casbin 规则，管理员角色可配置
- **启用/禁用**：禁用后菜单自动移除、路由从缓存清除、请求返回 403；重新启用恢复
- **生命周期钩子**：`PluginInterface` 提供 `onInstall`/`onUninstall`/`onUpgrade`/`onBeforeUpgrade`/`onEnable`/`onDisable`
- **配置管理**：`plugin.json` 声明配置项，通过 `PluginConfigService` 读写，支持默认值回退
- **安装失败回滚**：任一步骤失败自动逆序回滚已执行操作
- **依赖版本校验**：支持语义化版本约束（`>=1.0`、`^2.0`、`~1.2`、`1.*`、`>=1.0 <2.0`）
- **侧边菜单同步**：安装/卸载后前端自动刷新菜单树

---

## 插件目录结构

```
my-plugin/
├── plugin.json          # [必需] 插件清单文件
├── Plugin.php           # [可选] 实现 PluginInterface 生命周期钩子
├── routes.php           # [可选] 路由定义
├── migrations/          # [可选] 数据库迁移文件（按字典序执行）
│   └── 001_CreateTables.php
└── src/                 # [推荐] 插件源码（PSR-4 自动加载）
    ├── Controller/
    ├── Model/
    └── Service/
```

---

## plugin.json 规范

### 完整示例

```json
{
    "code": "feedback",
    "name": "意见反馈",
    "version": "1.0.0",
    "description": "收集用户意见与反馈，支持分类与状态追踪",
    "author": {
        "name": "MineAdmin",
        "email": "root@imoi.cn"
    },
    "hyperf": ">=3.1",
    "mineadmin": ">=3.0",
    "dependencies": {
        "sms": ">=1.0"
    },
    "autoload": {
        "psr-4": {
            "Plugin\\Feedback\\": "src/"
        }
    },
    "permissions": [
        {"name": "feedback:index", "display_name": "反馈列表"},
        {"name": "feedback:save", "display_name": "提交反馈"},
        {"name": "feedback:update", "display_name": "处理反馈"},
        {"name": "feedback:delete", "display_name": "删除反馈"}
    ],
    "menus": [
        {
            "name": "feedback",
            "meta": {
                "title": "意见反馈",
                "icon": "ri:feedback-line",
                "type": "M",
                "hidden": 0
            },
            "path": "/feedback",
            "component": "base/views/feedback/index",
            "sort": 99,
            "children": [
                {"name": "feedback:index", "meta": {"title": "反馈列表", "type": "B"}},
                {"name": "feedback:save", "meta": {"title": "提交反馈", "type": "B"}},
                {"name": "feedback:update", "meta": {"title": "处理反馈", "type": "B"}},
                {"name": "feedback:delete", "meta": {"title": "删除反馈", "type": "B"}}
            ]
        }
    ],
    "config": [
        {
            "key": "max_length",
            "label": "最大字符数",
            "type": "number",
            "default": 500,
            "description": "反馈内容最大长度限制"
        }
    ]
}
```

### 字段说明

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `code` | string | 是 | 插件唯一标识，全小写字母和下划线 |
| `name` | string | 是 | 插件显示名称 |
| `version` | string | 是 | 语义化版本号，如 `1.0.0` |
| `description` | string | 否 | 插件功能描述 |
| `author` | object | 否 | 开发者信息 `{name, email}` |
| `hyperf` | string | 否 | 依赖的 Hyperf 版本约束 |
| `mineadmin` | string | 否 | 依赖的 MineAdmin 版本约束 |
| `dependencies` | object | 否 | 其他插件依赖，支持语义化版本约束 |
| `autoload.psr-4` | object | 否 | 命名空间 → 目录映射 |
| `permissions` | array | 否 | Casbin 权限规则声明 |
| `menus` | array | 否 | 菜单树定义，支持嵌套 children |
| `config` | array | 否 | 配置项声明（key/label/type/default/description） |

### 菜单字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `name` | string | 父级用 code，子级用 `code:action` 格式 |
| `meta.title` | string | 菜单标题 |
| `meta.icon` | string | Remix Icon 图标名 |
| `meta.type` | string | `M`=菜单目录，`B`=按钮权限 |
| `meta.hidden` | int | `0`=显示，`1`=隐藏 |
| `path` | string | 前端路由路径 |
| `component` | string | 前端组件路径，相对于 `web/src/modules/` |
| `sort` | int | 排序值，越小越靠前 |

### 依赖版本约束语法

| 约束 | 示例 | 含义 |
|------|------|------|
| 大于等于 | `>=1.0.0` | 版本 ≥ 1.0.0 |
| 小于 | `<2.0.0` | 版本 < 2.0.0 |
| 插入符 | `^1.2.3` | `>=1.2.3 <2.0.0` |
| 波浪符 | `~1.2.3` | `>=1.2.3 <1.3.0` |
| 通配符 | `1.*` | `>=1.0.0 <2.0.0` |
| 多约束 | `>=1.0 <2.0` | AND 关系 |
| 并集 | `1.0 \|\| 1.2` | OR 关系 |

---

## routes.php 规范

```php
<?php

return [
    ['GET',  '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@index'],
    ['POST', '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@store'],
    ['GET',  '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@show'],
    ['PUT',  '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@update'],
    ['DELETE', '/admin/feedback/{id}', 'Plugin\\Feedback\\Controller\\FeedbackController@destroy'],
];
```

> 安装时自动检测路由冲突，冲突则安装失败。

---

## 插件生命周期钩子

在 `src/Plugin.php` 中实现 `App\Plugin\Contract\PluginInterface`：

```php
<?php

declare(strict_types=1);

namespace Plugin\Feedback;

use App\Plugin\Contract\PluginInterface;

final class Plugin implements PluginInterface
{
    public function onInstall(): void
    {
        // 安装时：初始化数据、预热缓存
    }

    public function onUninstall(): void
    {
        // 卸载时：清理自定义数据
    }

    public function onUpgrade(): void
    {
        // 升级后：新版代码执行
    }

    public function onBeforeUpgrade(): void
    {
        // 升级前：旧版代码执行，可备份数据
    }

    public function onEnable(): void
    {
        // 启用时：恢复定时任务等
    }

    public function onDisable(): void
    {
        // 禁用时：暂停定时任务等
    }
}
```

> 不需要构造函数，不需要导入 `PluginManifest`。系统从数据库和 `plugin.json` 读取元数据，不依赖 `Plugin` 实例。

---

## 控制器规范

```php
<?php

declare(strict_types=1);

namespace Plugin\Feedback\Controller;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Result;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;

final class FeedbackController extends AbstractController
{
    public function __construct(
        private readonly RequestInterface $request
    ) {}

    public function index(): Result
    {
        $list = Db::table('feedback')->orderBy('id', 'desc')->get();
        return $this->success(['list' => $list]);
    }

    public function store(): Result
    {
        $data = $this->request->all();
        Db::table('feedback')->insert([
            'title'   => $data['title'] ?? '',
            'content' => $data['content'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['message' => '提交成功']);
    }
}
```

---

## 插件配置读写

```php
use App\Plugin\PluginManager;

// 获取配置（key 支持点号分隔嵌套）
$maxLength = $this->pluginManager->getConfig('feedback', 'max_length', 500);

// 更新配置（部分 merge）
$this->pluginManager->setConfig('feedback', ['max_length' => 1000]);

// 获取配置声明
$schema = $this->pluginManager->getConfigSchema('feedback');
```

---

## 打包与发布

```bash
# 在插件根目录执行
cd plugins/feedback
zip -r feedback-v1.0.0.zip . -x "*.DS_Store"
```

---

## 安装流程

1. **冲突检测** — 检查路由冲突、依赖版本
2. **注册 PSR-4** — 注入 Composer ClassLoader + 注解扫描路径
3. **执行迁移** — 仅运行 `plugin_migrations` 表中未追踪的迁移
4. **注册路由** — 注入 HTTP 路由表，按 plugin code 持久化
5. **注册菜单权限** — 写入 `menu`/`rules`/`role_belongs_menu` 表
6. **入库** — 写入 `plugin` 表（status=1）
7. **调用钩子** — `onInstall()`
8. **失败回滚** — 任一步骤失败自动逆序回滚

## 升级流程

1. **版本检查** — 新版本 > 当前版本
2. **备份旧版** → `runtime/plugin_backup/`
3. **旧版 onBeforeUpgrade()**
4. **新迁移 + 新路由 + 新菜单 + 新版 onUpgrade()**
5. **失败回滚** — 恢复备份目录

## 卸载流程

1. `onUninstall()`
2. 移除菜单/权限/路由缓存/扫描路径
3. 回滚已追踪迁移（逆序）
4. 删除插件文件
5. 删除 `plugin` 表记录

---

## 启用/禁用机制

| 操作 | 效果 |
|------|------|
| 启用 | `status=1`，菜单恢复，路由注册，`onEnable()` |
| 禁用 | `status=2`，菜单移除，路由清除，`onDisable()` |

- 禁用不删数据、不删文件
- `PluginGuardMiddleware`（全局中间件）拦截禁用插件路由返回 403
- `PluginGuardListener` 提供额外保护层

---

## 数据表

### plugin（已安装插件）

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | string | 唯一标识 |
| `name` | string | 插件名称 |
| `version` | string | 当前版本 |
| `status` | int | 1=已启用，2=已禁用 |
| `config` | json | 私有配置 |
| `meta` | json | plugin.json 快照 |

### plugin_migrations（迁移追踪）

| 字段 | 类型 | 说明 |
|------|------|------|
| `plugin_code` | string | 插件标识 |
| `migration` | string | 迁移文件名（不含 .php） |

---

## 配置参考

`config/autoload/plugin.php`：

| 配置项 | 默认值 | 环境变量 | 说明 |
|------|------|------|------|
| `marketplace_url` | `https://marketplace.mineadmin.com` | `PLUGIN_MARKETPLACE_URL` | 远程市场地址 |
| `marketplace_timeout` | `30` | `PLUGIN_MARKETPLACE_TIMEOUT` | 市场请求超时（秒） |
| `allow_unsigned` | `true` | `PLUGIN_ALLOW_UNSIGNED` | 是否允许未签名插件 |
| `super_admin_role_id` | `1` | `PLUGIN_SUPER_ADMIN_ROLE_ID` | 管理员角色 ID |
| `super_admin_role_name` | `SuperAdmin` | `PLUGIN_SUPER_ADMIN_ROLE_NAME` | 管理员角色标识 |
| `guard_cache_ttl` | `60` | `PLUGIN_GUARD_CACHE_TTL` | 禁用插件缓存时间（秒） |

---

## 最佳实践

### 命名规范

- **插件 code**：全小写+下划线，如 `order_export`
- **权限 name**：`code:action` 格式，如 `order_export:download`
- **菜单 name**：父级用 code，子级用权限 name

### 迁移规范

- 文件名按字典序，如 `001_CreateTables.php`、`002_AddIndexes.php`
- 类名必须与文件名一致，继承 `Hyperf\Database\Migrations\Migration`
- 已执行迁移自动跳过，**表已存在会标记为已追踪**
- 卸载时仅回滚**已追踪且文件仍存在**的迁移

### 目录规范

- 代码放 `src/`，迁移放 `migrations/`，路由放 `routes.php`
- 生命周期钩子放 `src/Plugin.php`
- 不放置 `vendor/`、`runtime/` 等外部依赖

### 版本管理

- 语义化版本 `MAJOR.MINOR.PATCH`
- 每次发布前更新 `plugin.json` 中的 `version`
- 升级时仅执行新增迁移，不重复执行

### 配置最佳实践

- `plugin.json` 中声明 `config` 数组（key/label/type/default）
- 通过 `PluginConfigService` 读写，自动合并默认值
- 敏感配置（密钥）使用 `.env` 而非插件配置
