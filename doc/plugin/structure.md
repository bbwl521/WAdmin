# 插件目录结构

## 标准结构

```
plugins/my-plugin/
├── plugin.json              # [必需] 插件清单
├── Plugin.php               # [可选] 生命周期钩子
├── routes.php               # [可选] 路由定义
├── migrations/              # [可选] 数据库迁移
│   ├── 001_CreateXxx.php
│   └── 002_AddColumns.php
└── src/                     # [推荐] 源码目录
    ├── Controller/
    ├── Model/
    ├── Service/
    └── ConfigProvider.php
```

## 各文件职责

### plugin.json

插件的唯一入口文件，安装引擎解析它来获取所有元数据。参见 [插件指南](/plugin/guide)。

### Plugin.php

实现 `App\Plugin\Contract\PluginInterface`，提供生命周期钩子。可选——不写也不影响安装。

### routes.php

返回二维数组 `[[method, path, handler], ...]`。安装时自动注入 Hyperf 路由表并持久化到 `runtime/plugin_routes.php`，重启后自动恢复。

### migrations/

按文件名字典序存放迁移类。每个类继承 `Hyperf\Database\Migrations\Migration`，类名与文件名一致。

安装时仅执行 `plugin_migrations` 表中未追踪的文件；卸载时逆序回滚已追踪的文件。表已存在的迁移会自动标记跳过。

### src/

PSR-4 自动加载目录。在 `plugin.json` 中通过 `autoload.psr-4` 声明映射，安装时动态注入 Composer ClassLoader 和 Hyperf 注解扫描路径。

推荐按 Controller / Model / Service / Middleware 分层组织。

## 命名规范

| 元素 | 规范 | 示例 |
|------|------|------|
| 插件 code | 全小写+下划线 | `order_export` |
| 权限 name | `code:action` | `order_export:download` |
| 菜单 name | 父级=code，子级=权限名 | `order_export` / `order_export:download` |
| 迁移文件 | 数字前缀+动作+对象 | `001_CreateOrders.php` |
