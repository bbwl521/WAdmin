# MineAdmin 架构全景与发展路线图

> 生成日期：2026-05-23 | Hyperf 3.1 + PHP 8.2 + Swoole 5.x

---

## 一、系统全景架构

```
┌─────────────────────────────── WAdmin (MineAdmin) ───────────────────────────┐
│                                                                              │
│  ┌── Frontend (web/) ────────────────────────────────────────────────────┐  │
│  │   Vue 3 + TS  │  UnoCSS  │  Pinia  │  Vue Router  │  Vite            │  │
│  │                                                                       │  │
│  │   组件体系: ma-dialog  ma-drawer  ma-auth  ma-resource-picker        │  │
│  │            ma-icon-picker  ma-dict-picker  ma-remote-select           │  │
│  │            ma-select-table  ma-key-value  ma-city-select              │  │
│  │                                                                       │  │
│  │   Hooks: useCache  useDialog  useDrawer  useForm  useTable            │  │
│  │          useWatermark  useThemeColor  useEcharts  useResourcePicker   │  │
│  │                                                                       │  │
│  │   布局: layouts/default(后台)  layouts/uc(用户中心)  layouts/[...all] │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │  HTTP/HTTPS                                    │
│  ┌── Middleware Pipeline ────────────────────────────────────────────────┐  │
│  │  InstallCheck → RequestId → Translation → CORS → Validation           │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │                                                │
│  ┌── Controller ─────────────────────────────────────────────────────────┐  │
│  │  Admin/Controller  │  Api/Controller  │  Common(Result/AbstractCtrl)  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │                                                │
│  ┌── Service ────────────────────────────────────────────────────────────┐  │
│  │  InstallService(安装引擎)  PassportService(认证)  Attachment(上传)     │  │
│  │  EnvironmentCheckService  InstallProgressTracker  PermissionService   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │                                                │
│  ┌── Repository ─────────────────────────────────────────────────────────┐  │
│  │  IRepository(契约)  AttachmentRepo  Logstash  Permission Repos        │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │                                                │
│  ┌── Model (ORM) ────────────────────────────────────────────────────────┐  │
│  │  Permission域: User  Role  Department  Position  Menu  Meta  Policy   │  │
│  │  通用域: Attachment  UserLoginLog  UserOperationLog                   │  │
│  │  Enums: User.Status  User.Type  DataPermission.PolicyType             │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                            │                                                │
│  ┌── Infrastructure ─────────────────────────────────────────────────────┐  │
│  │  MySQL  │  Redis  │  JWT  │  Casbin(RBAC)  │  CronTab  │  Swoole      │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌── mineadmin/* Ecosystem ───────────────────────────────────────────────┐  │
│  │  core → access → auth-jwt → jwt → support → swagger → upload          │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌── Events & Subscribers ───────────────────────────────────────────────┐  │
│  │  BootApplicationSubscriber  InstallCheckSubscriber                     │  │
│  │  DbQueryExecutedSubscriber  FailToHandleSubscriber                     │  │
│  │  QueueHandleSubscriber  UploadSubscriber  RegisterBlueprintListener    │  │
│  │  InstallationCompletedEvent (安装完成，插件可监听)                     │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 二、功能模块全景

```
WAdmin 功能矩阵
│
├── 🚀 安装向导
│   ├── InstallCheckMiddleware      未安装请求全局拦截
│   ├── InstallCheckSubscriber      启动时终端安装提示
│   ├── install_routes.php          / 和 /install 路由
│   ├── InstallController           API: 环境检测/数据库创建/SQL执行
│   ├── InstallService              完整安装流程引擎
│   ├── InstallProgressTracker      进度追踪 + 并发锁
│   ├── EnvironmentCheckService     PHP版本/扩展/目录权限检测
│   └── InstallationCompletedEvent  安装完成事件（插件扩展点）
│
├── 🔐 认证与授权
│   ├── JWT 双场景 (default + application)
│   ├── PassportService             登录 / Token签发 / 刷新
│   ├── Casbin + RBAC               角色-菜单-权限 动态策略
│   ├── DataPermission/Policy       行级数据权限策略
│   └── ma-auth                     前端权限组件
│
├── 👤 用户体系
│   ├── User / Role / Department / Position / Menu / Leader
│   ├── 用户状态枚举 (启用/禁用)
│   ├── 用户类型枚举 (系统/自定义)
│   └── 数据权限策略类型枚举
│
├── 📁 文件管理
│   ├── AttachmentService           上传/存储/URL管理
│   ├── UploadInterface → LocalUpload 契约驱动，可扩展OSS
│   ├── UploadSubscriber            事件驱动文件写入
│   ├── 相对路径 URL (/uploads/xxx) 去域名化
│   └── ma-resource-picker          前端资源选择器
│
├── 📊 操作审计
│   ├── UserLoginLog                登录日志
│   ├── UserOperationLog            操作日志
│   └── Logstash Repository         日志聚合查询
│
├── 🌐 API 文档
│   ├── Schema 层                   响应模型定义，与 Controller 解耦
│   ├── swagger-php Attribute       注解驱动文档生成
│   └── /swagger 文档页面
│
├── 🔧 前端能力
│   ├── 组件: ma-dialog/drawer/auth/icon-picker/city-select...
│   ├── Hooks: useCache/useDialog/useWatermark/useThemeColor...
│   ├── 国际化: zh_CN / zh_TW / en (YAML)
│   ├── 主题色切换 + 水印 + 图片预览
│   └── 自动导入 (unplugin-auto-import)
│
├── ⚡ Hyperf 基础设施
│   ├── Swoole 协程 HTTP Server
│   ├── DI 容器 (面向契约编程)
│   ├── Redis (缓存/异步队列/分布式锁)
│   ├── Crontab (定时任务)
│   └── 异常处理 Handler 链
│
└── 📦 Composer 包生态
    └── mineadmin/* (7个自研包，独立版本控制)
```

---

## 三、项目发展路线图

### 🟢 Phase 1 — 夯实基础（近期）

| # | 模块 | 目标 | 技术要点 |
|---|---|---|---|
| 1 | **ConfigProvider 化** | `app/ConfigProvider.php` 统一服务注册 | 替代分散的 `config/autoload/dependencies.php` |
| 2 | **Repository 重构** | 移除 `HasContainer` trait | 全部构造函数注入，严格 DI |
| 3 | **抽象数据权限接口** | `DataPermissionInterface` 契约 | 支持自定义数据权限策略插件 |
| 4 | **域事件体系** | `UserLoginEvent` `DataChangedEvent` | 登录/数据变更发射事件，解耦日志等副作用 |
| 5 | **API 版本化** | `app/Http/Api/V1/` | `Accept-Version: v1` Header 路由分发 |

### 🟡 Phase 2 — 能力扩展（中期）

| # | 模块 | 目标 | 技术要点 |
|---|---|---|---|
| 6 | **插件系统** | `PluginInterface`: install/uninstall/activate | 借鉴 mineadmin 扩展机制 |
| 7 | **API 速率限制** | Redis 令牌桶限流中间件 | `RateLimitMiddleware` + 注解配置 |
| 8 | **消息通知中心** | `NotificationService` + `NotifiableInterface` | 邮件/站内信/Webhook |
| 9 | **数据字典** | 统一 `DictService` | 前端 `ma-dict-picker` 已有雏形 |
| 10 | **OpenTelemetry 追踪** | 全链路追踪 | 配合 `RequestIdMiddleware` |
| 11 | **多租户** | `TenantMiddleware` + `TenantScope` | 数据库级数据隔离 |

### 🔵 Phase 3 — 生态建设（远期）

| # | 模块 | 目标 | 技术要点 |
|---|---|---|---|
| 12 | **BFF 聚合层** | `app/Http/Bff/` | 前端定制聚合接口，减少请求次数 |
| 13 | **WebSocket** | 实时通知/在线统计 | Hyperf WebSocket Server |
| 14 | **GraphQL** | 复杂查询场景 | 补充 REST API |
| 15 | **gRPC 微服务** | 内部服务间低延迟通信 | Service 层 gRPC 暴露 |
| 16 | **Composer 包拆分** | 通用能力独立包 | 安装器/审计/事件独立维护 |
| 17 | **SaaS 化** | 应用市场 + 租户商城 | 参考 mineadmin "app store" 定位 |

---

## 四、核心架构原则

```
┌── SOLID + PSR 规范 ─────────────────────────────────────────┐
│                                                              │
│  S - 单一职责： Controller→Service→Repository 各司其职      │
│  O - 开闭原则： 插件扩展不修改核心代码                        │
│  L - 里氏替换： UploadInterface 可替换为 OSS/COS/本地        │
│  I - 接口隔离： ICheckTokenInterface 最小化接口              │
│  D - 依赖倒置： 始终针对接口编程，而非具体实现               │
└──────────────────────────────────────────────────────────────┘

┌── 事件驱动架构 (EDA) ───────────────────────────────────────┐
│                                                              │
│  Event → Listener → 解耦副作用                               │
│  安装完成   → 初始化默认配置 → 通知插件                       │
│  文件上传   → 生成缩略图 → 更新统计                          │
│  数据变更   → 清理缓存   → 记录审计日志                       │
│  用户登录   → 更新登录信息 → 检查安全策略                     │
└──────────────────────────────────────────────────────────────┘

┌── 契约驱动 (Contract-First) ────────────────────────────────┐
│                                                              │
│  UploadInterface → LocalUpload / COSUpload / OSSUpload       │
│  CheckTokenInterface → JWT / OAuth / API Key                 │
│  DataPermissionInterface → Policy / Custom                   │
│  新功能先定义契约接口，再提供默认实现，开放扩展                │
└──────────────────────────────────────────────────────────────┘

┌── 不可变性与幂等 ───────────────────────────────────────────┐
│                                                              │
│  readonly 属性：Event DTO、Config Value Object               │
│  幂等操作：所有队列任务可重复执行                             │
│  缓存 Key：统一配置文件管理，禁止硬编码                       │
│  PDO 预处理：绝对禁止 SQL 字符串拼接                         │
└──────────────────────────────────────────────────────────────┘
```

---

## 五、目标目录结构演进

```
app/
├── Command/                   # CLI 命令
│   ├── InstallCommand.php
│   └── UpdateCommand.php
│
├── ConfigProvider.php         # [Phase1] 统一服务注册
│
├── Contract/                  # [Phase1] 所有契约接口
│   ├── DataPermissionInterface.php
│   ├── NotificationInterface.php
│   └── PluginInterface.php
│
├── Event/                     # 领域事件
│   ├── InstallationCompletedEvent.php   ✅ 已实现
│   ├── UserLoginEvent.php              # [Phase1]
│   └── DataChangedEvent.php            # [Phase1]
│
├── Exception/
│   ├── BusinessException.php
│   ├── JwtInBlackException.php
│   └── Handler/
│
├── Http/
│   ├── Admin/
│   │   ├── Controller/
│   │   ├── Middleware/
│   │   ├── Request/
│   │   └── Vo/
│   ├── Api/
│   │   └── V1/                # [Phase1] API 版本化
│   ├── Bff/                   # [Phase3] 前端聚合层
│   └── Common/
│       ├── Controller/AbstractController.php
│       ├── Middleware/
│       ├── Result.php
│       └── ResultCode.php
│
├── Library/
│   └── DataPermission/
│
├── Model/
│   ├── Attachment.php
│   ├── UserLoginLog.php
│   ├── UserOperationLog.php
│   ├── Casts/
│   ├── DataPermission/
│   ├── Enums/
│   └── Permission/
│
├── Plugin/                    # [Phase2] 插件管理
│   ├── PluginManager.php
│   └── Builtin/
│
├── Repository/
│   ├── IRepository.php
│   ├── AttachmentRepository.php
│   ├── Logstash/
│   ├── Permission/
│   └── Traits/
│
├── Schema/                    # API 响应模型
│   ├── UserSchema.php
│   ├── RoleSchema.php
│   ├── MenuSchema.php
│   ├── ...
│
├── Service/
│   ├── InstallService.php
│   ├── PassportService.php
│   ├── AttachmentService.php
│   ├── ...
│
├── Subscriber/                # 事件订阅者
│   └── InstallCheckSubscriber.php  ✅ 已实现
│
└── Support/                   # [Phase1] 工具类
    ├── RateLimiter.php
    └── ...
```

---

## 六、开发工作流

```bash
# 日常开发
composer dev                    # 热重载开发模式
composer cs-fix                 # 代码格式化 (PSR-12)
composer analyse                # 静态检测 (PHPStan Lv.5)
composer test                   # 单元测试

# 生产部署
composer install --no-dev       # 生产依赖
php bin/hyperf.php start        # 启动服务

# Composer 脚本
composer run-script post-autoload-dump  # 清理 DI 缓存
```

### 代码规范检查项

| 工具 | 命令 | 目标 |
|---|---|---|
| PHP-CS-Fixer | `composer cs-fix` | PSR-12 格式 |
| PHPStan | `composer analyse` | 静态分析 Level 5 |
| PHPUnit | `composer test` | 单元 + 功能测试 |

---

## 七、技术栈总览

| 层级 | 技术 | 版本 |
|---|---|---|
| 运行时 | PHP + Swoole | 8.2+ / 5.0-7.0 |
| 框架 | Hyperf | 3.1 |
| 数据库 | MySQL (PDO) | 5.7+ |
| 缓存/队列 | Redis | 6.0+ |
| 认证 | JWT (双场景) | — |
| 权限 | Casbin RBAC | — |
| API 文档 | Swagger-PHP | 4.10 |
| 前端 | Vue 3 + TypeScript + Vite | 3.x |
| CSS | UnoCSS | — |
| 状态管理 | Pinia | — |
| 测试 | PHPUnit + Mockery | — |
| 静态分析 | PHPStan | 2.x |
| 包管理 | Composer | 2.x |
