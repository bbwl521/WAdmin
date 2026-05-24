-- MineAdmin 数据库初始化脚本
-- 生成时间: 2026-05-23
-- 字符集: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for user
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID,主键',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `password` varchar(100) NOT NULL COMMENT '密码',
  `user_type` varchar(3) NOT NULL DEFAULT '100' COMMENT '用户类型:100=系统用户',
  `nickname` varchar(30) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `phone` varchar(11) NOT NULL DEFAULT '' COMMENT '手机',
  `email` varchar(50) NOT NULL DEFAULT '' COMMENT '用户邮箱',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '用户头像',
  `signed` varchar(255) NOT NULL DEFAULT '' COMMENT '个人签名',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态:1=正常,2=停用',
  `login_ip` varchar(45) NOT NULL DEFAULT '127.0.0.1' COMMENT '最后登陆IP',
  `login_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '最后登陆时间',
  `backend_setting` json DEFAULT NULL COMMENT '后台设置数据',
  `created_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '创建者',
  `updated_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '更新者',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户信息表';

-- ----------------------------
-- Table structure for menu
-- ----------------------------
DROP TABLE IF EXISTS `menu`;
CREATE TABLE `menu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `parent_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '父ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '菜单名称',
  `meta` json DEFAULT NULL COMMENT '附加属性',
  `path` varchar(60) NOT NULL DEFAULT '' COMMENT '路径',
  `component` varchar(150) NOT NULL DEFAULT '' COMMENT '组件路径',
  `redirect` varchar(100) NOT NULL DEFAULT '' COMMENT '重定向地址',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态:1=正常,2=停用',
  `sort` smallint NOT NULL DEFAULT '0' COMMENT '排序',
  `created_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '创建者',
  `updated_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '更新者',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `remark` varchar(60) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单信息表';

-- ----------------------------
-- Table structure for role
-- ----------------------------
DROP TABLE IF EXISTS `role`;
CREATE TABLE `role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(30) NOT NULL COMMENT '角色名称',
  `code` varchar(100) NOT NULL COMMENT '角色代码',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态:1=正常,2=停用',
  `sort` smallint NOT NULL DEFAULT '0' COMMENT '排序',
  `created_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '创建者',
  `updated_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '更新者',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色信息表';

-- ----------------------------
-- Table structure for attachment
-- ----------------------------
DROP TABLE IF EXISTS `attachment`;
CREATE TABLE `attachment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `storage_mode` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储模式:local=本地,oss=阿里云,qiniu=七牛云,cos=腾讯云',
  `origin_name` varchar(255) DEFAULT NULL COMMENT '原文件名',
  `object_name` varchar(50) DEFAULT NULL COMMENT '新文件名',
  `hash` varchar(64) DEFAULT NULL COMMENT '文件hash',
  `mime_type` varchar(255) DEFAULT NULL COMMENT '资源类型',
  `storage_path` varchar(100) DEFAULT NULL COMMENT '存储目录',
  `suffix` varchar(20) DEFAULT NULL COMMENT '文件后缀',
  `size_byte` bigint DEFAULT NULL COMMENT '字节数',
  `size_info` varchar(50) DEFAULT NULL COMMENT '文件大小',
  `url` varchar(255) DEFAULT NULL COMMENT 'url地址',
  `created_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '创建者',
  `updated_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '更新者',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  UNIQUE KEY `attachment_hash_unique` (`hash`),
  KEY `attachment_storage_path_index` (`storage_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='上传文件信息表';

-- ----------------------------
-- Table structure for rules (Casbin)
-- ----------------------------
DROP TABLE IF EXISTS `rules`;
CREATE TABLE `rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ptype` varchar(255) DEFAULT NULL,
  `v0` varchar(255) DEFAULT NULL,
  `v1` varchar(255) DEFAULT NULL,
  `v2` varchar(255) DEFAULT NULL,
  `v3` varchar(255) DEFAULT NULL,
  `v4` varchar(255) DEFAULT NULL,
  `v5` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for user_login_log
-- ----------------------------
DROP TABLE IF EXISTS `user_login_log`;
CREATE TABLE `user_login_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `ip` varchar(45) DEFAULT NULL COMMENT '登录IP地址',
  `os` varchar(255) DEFAULT NULL COMMENT '操作系统',
  `browser` varchar(255) DEFAULT NULL COMMENT '浏览器',
  `status` smallint NOT NULL DEFAULT '1' COMMENT '登录状态 (1成功 2失败)',
  `message` varchar(50) DEFAULT NULL COMMENT '提示消息',
  `login_time` datetime NOT NULL COMMENT '登录时间',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `user_login_log_username_index` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录日志表';

-- ----------------------------
-- Table structure for user_operation_log
-- ----------------------------
DROP TABLE IF EXISTS `user_operation_log`;
CREATE TABLE `user_operation_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `method` varchar(20) NOT NULL COMMENT '请求方式',
  `router` varchar(500) NOT NULL COMMENT '请求路由',
  `service_name` varchar(30) NOT NULL COMMENT '业务名称',
  `ip` varchar(45) DEFAULT NULL COMMENT '请求IP地址',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `user_operation_log_username_index` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ----------------------------
-- Table structure for user_belongs_role
-- ----------------------------
DROP TABLE IF EXISTS `user_belongs_role`;
CREATE TABLE `user_belongs_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT '用户id',
  `role_id` bigint NOT NULL COMMENT '角色id',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for role_belongs_menu
-- ----------------------------
DROP TABLE IF EXISTS `role_belongs_menu`;
CREATE TABLE `role_belongs_menu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint NOT NULL COMMENT '角色id',
  `menu_id` bigint NOT NULL COMMENT '菜单id',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for department
-- ----------------------------
DROP TABLE IF EXISTS `department`;
CREATE TABLE `department` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(50) NOT NULL COMMENT '部门名称',
  `parent_id` bigint NOT NULL DEFAULT '0' COMMENT '父级部门ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

-- ----------------------------
-- Table structure for position
-- ----------------------------
DROP TABLE IF EXISTS `position`;
CREATE TABLE `position` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(50) NOT NULL COMMENT '岗位名称',
  `dept_id` bigint NOT NULL COMMENT '部门ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='岗位表';

-- ----------------------------
-- Table structure for user_dept
-- ----------------------------
DROP TABLE IF EXISTS `user_dept`;
CREATE TABLE `user_dept` (
  `user_id` bigint NOT NULL COMMENT '用户ID',
  `dept_id` bigint NOT NULL COMMENT '部门ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户-部门关联表';

-- ----------------------------
-- Table structure for user_position
-- ----------------------------
DROP TABLE IF EXISTS `user_position`;
CREATE TABLE `user_position` (
  `user_id` bigint NOT NULL COMMENT '用户ID',
  `position_id` bigint NOT NULL COMMENT '岗位ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户-岗位关联表';

-- ----------------------------
-- Table structure for dept_leader
-- ----------------------------
DROP TABLE IF EXISTS `dept_leader`;
CREATE TABLE `dept_leader` (
  `dept_id` bigint NOT NULL COMMENT '部门ID',
  `user_id` bigint NOT NULL COMMENT '用户ID',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门领导表';

-- ----------------------------
-- Table structure for data_permission_policy
-- ----------------------------
DROP TABLE IF EXISTS `data_permission_policy`;
CREATE TABLE `data_permission_policy` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` bigint NOT NULL DEFAULT '0' COMMENT '用户ID（与角色二选一）',
  `position_id` bigint NOT NULL DEFAULT '0' COMMENT '岗位ID（与用户二选一）',
  `policy_type` varchar(20) NOT NULL COMMENT '策略类型',
  `is_default` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否默认策略',
  `value` json DEFAULT NULL COMMENT '策略值',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据权限策略';

-- ----------------------------
-- 初始数据
-- ----------------------------

-- 超级管理员用户 (密码: 123456，安装时会替换)
INSERT INTO `user` (`id`, `username`, `password`, `user_type`, `nickname`, `email`, `phone`, `signed`, `status`, `login_ip`, `login_time`, `backend_setting`, `created_by`, `updated_by`, `created_at`, `updated_at`, `remark`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '100', '创始人', 'admin@adminmine.com', '16858888988', '广阔天地，大有所为', 1, '127.0.0.1', NOW(), NULL, 0, 0, NOW(), NOW(), '');

-- 超级管理员角色
INSERT INTO `role` (`id`, `name`, `code`, `status`, `sort`, `created_by`, `updated_by`, `created_at`, `updated_at`, `remark`) VALUES
(1, '超级管理员', 'SuperAdmin', 1, 1, 0, 0, NOW(), NOW(), '系统超级管理员，拥有所有权限');

-- 用户-角色关联
INSERT INTO `user_belongs_role` (`user_id`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 1, NOW(), NOW());

-- Casbin 规则
INSERT INTO `rules` (`ptype`, `v0`, `v1`, `v2`, `v3`, `v4`, `v5`, `created_at`, `updated_at`) VALUES
('g', 'admin', 'SuperAdmin', NULL, NULL, NULL, NULL, NOW(), NOW());

-- 菜单数据
INSERT INTO `menu` (`id`, `parent_id`, `name`, `meta`, `path`, `component`, `redirect`, `status`, `sort`, `created_by`, `updated_by`, `created_at`, `updated_at`, `remark`) VALUES
(1, 0, 'permission', '{"title": "权限管理", "i18n": "baseMenu.permission.index", "icon": "ri:git-repository-private-line", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/permission', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(2, 1, 'permission:user', '{"title": "用户管理", "i18n": "baseMenu.permission.user", "icon": "material-symbols:manage-accounts-outline", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/permission/user', 'base/views/permission/user/index', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(3, 2, 'permission:user:index', '{"title": "用户列表", "type": "B", "i18n": "baseMenu.permission.userList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(4, 2, 'permission:user:save', '{"title": "用户保存", "type": "B", "i18n": "baseMenu.permission.userSave"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(5, 2, 'permission:user:update', '{"title": "用户更新", "type": "B", "i18n": "baseMenu.permission.userUpdate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(6, 2, 'permission:user:delete', '{"title": "用户删除", "type": "B", "i18n": "baseMenu.permission.userDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(7, 2, 'permission:user:password', '{"title": "用户初始化密码", "type": "B", "i18n": "baseMenu.permission.userPassword"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(8, 2, 'user:get:roles', '{"title": "获取用户角色", "type": "B", "i18n": "baseMenu.permission.getUserRole"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(9, 2, 'user:set:roles', '{"title": "用户角色赋予", "type": "B", "i18n": "baseMenu.permission.setUserRole"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(10, 1, 'permission:menu', '{"title": "菜单管理", "i18n": "baseMenu.permission.menu", "icon": "ph:list-bold", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/permission/menu', 'base/views/permission/menu/index', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(11, 10, 'permission:menu:index', '{"title": "菜单列表", "type": "B", "i18n": "baseMenu.permission.menuList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(12, 10, 'permission:menu:create', '{"title": "菜单保存", "type": "B", "i18n": "baseMenu.permission.menuSave"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(13, 10, 'permission:menu:save', '{"title": "菜单更新", "type": "B", "i18n": "baseMenu.permission.menuUpdate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(14, 10, 'permission:menu:delete', '{"title": "菜单删除", "type": "B", "i18n": "baseMenu.permission.menuDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(15, 1, 'permission:role', '{"title": "角色管理", "i18n": "baseMenu.permission.role", "icon": "material-symbols:supervisor-account-outline-rounded", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/permission/role', 'base/views/permission/role/index', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(16, 15, 'permission:role:index', '{"title": "角色列表", "type": "B", "i18n": "baseMenu.permission.roleList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(17, 15, 'permission:role:save', '{"title": "角色保存", "type": "B", "i18n": "baseMenu.permission.roleSave"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(18, 15, 'permission:role:update', '{"title": "角色更新", "type": "B", "i18n": "baseMenu.permission.roleUpdate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(19, 15, 'permission:role:delete', '{"title": "角色删除", "type": "B", "i18n": "baseMenu.permission.roleDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(20, 15, 'permission:get:role', '{"title": "获取角色权限", "type": "B", "i18n": "baseMenu.permission.getRolePermission"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(21, 15, 'permission:set:role', '{"title": "赋予角色权限", "type": "B", "i18n": "baseMenu.permission.setRolePermission"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(22, 0, 'log', '{"title": "日志管理", "i18n": "baseMenu.log.index", "icon": "ph:instagram-logo", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/log', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(23, 22, 'log:userLogin', '{"title": "用户登录日志管理", "type": "M", "hidden": 0, "icon": "ph:user-list", "i18n": "baseMenu.log.userLoginLog", "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/log/userLoginLog', 'base/views/log/userLogin', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(24, 23, 'log:userLogin:list', '{"title": "用户登录日志列表", "i18n": "baseMenu.log.userLoginLogList", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(25, 23, 'log:userLogin:delete', '{"title": "删除用户登录日志", "i18n": "baseMenu.log.userLoginLogDelete", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(26, 22, 'log:userOperation', '{"title": "操作日志管理", "type": "M", "hidden": 0, "icon": "ph:list-magnifying-glass", "i18n": "baseMenu.log.operationLog", "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/log/operationLog', 'base/views/log/userOperation', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(27, 26, 'log:userOperation:list', '{"title": "用户操作日志列表", "i18n": "baseMenu.log.userOperationLog", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(28, 26, 'log:userOperation:delete', '{"title": "删除用户操作日志", "i18n": "baseMenu.log.userOperationLogDelete", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(29, 1, 'permission:department', '{"title": "部门管理", "icon": "mingcute:department-line", "i18n": "baseMenu.permission.department", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue", "breadcrumbEnable": 1, "copyright": 1, "cache": 1, "affix": 0}', '/permission/department', 'base/views/permission/department/index', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(30, 29, 'permission:department:index', '{"title": "部门列表", "type": "B", "i18n": "baseMenu.permission.departmentList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(31, 29, 'permission:department:save', '{"title": "部门新增", "type": "B", "i18n": "baseMenu.permission.departmentCreate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(32, 29, 'permission:department:update', '{"title": "部门编辑", "type": "B", "i18n": "baseMenu.permission.departmentSave"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(33, 29, 'permission:department:delete', '{"title": "部门删除", "type": "B", "i18n": "baseMenu.permission.departmentDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(34, 29, 'permission:position:index', '{"title": "岗位列表", "type": "B", "i18n": "baseMenu.permission.positionList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(35, 29, 'permission:position:save', '{"title": "岗位新增", "type": "B", "i18n": "baseMenu.permission.positionCreate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(36, 29, 'permission:position:update', '{"title": "岗位编辑", "type": "B", "i18n": "baseMenu.permission.positionSave"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(37, 29, 'permission:position:delete', '{"title": "岗位删除", "type": "B", "i18n": "baseMenu.permission.positionDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(38, 29, 'permission:position:data_permission', '{"title": "设置岗位数据权限", "type": "B", "i18n": "baseMenu.permission.positionDataScope"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(39, 29, 'permission:leader:index', '{"title": "部门领导列表", "type": "B", "i18n": "baseMenu.permission.leaderList"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(40, 29, 'permission:leader:save', '{"title": "新增部门领导", "type": "B", "i18n": "baseMenu.permission.leaderCreate"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(41, 29, 'permission:leader:delete', '{"title": "部门领导移除", "type": "B", "i18n": "baseMenu.permission.leaderDelete"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), '');

-- 角色-菜单关联（SuperAdmin 拥有所有菜单权限）
INSERT INTO `role_belongs_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`) VALUES
(1, 1, NOW(), NOW()),
(1, 2, NOW(), NOW()),
(1, 3, NOW(), NOW()),
(1, 4, NOW(), NOW()),
(1, 5, NOW(), NOW()),
(1, 6, NOW(), NOW()),
(1, 7, NOW(), NOW()),
(1, 8, NOW(), NOW()),
(1, 9, NOW(), NOW()),
(1, 10, NOW(), NOW()),
(1, 11, NOW(), NOW()),
(1, 12, NOW(), NOW()),
(1, 13, NOW(), NOW()),
(1, 14, NOW(), NOW()),
(1, 15, NOW(), NOW()),
(1, 16, NOW(), NOW()),
(1, 17, NOW(), NOW()),
(1, 18, NOW(), NOW()),
(1, 19, NOW(), NOW()),
(1, 20, NOW(), NOW()),
(1, 21, NOW(), NOW()),
(1, 22, NOW(), NOW()),
(1, 23, NOW(), NOW()),
(1, 24, NOW(), NOW()),
(1, 25, NOW(), NOW()),
(1, 26, NOW(), NOW()),
(1, 27, NOW(), NOW()),
(1, 28, NOW(), NOW()),
(1, 29, NOW(), NOW()),
(1, 30, NOW(), NOW()),
(1, 31, NOW(), NOW()),
(1, 32, NOW(), NOW()),
(1, 33, NOW(), NOW()),
(1, 34, NOW(), NOW()),
(1, 35, NOW(), NOW()),
(1, 36, NOW(), NOW()),
(1, 37, NOW(), NOW()),
(1, 38, NOW(), NOW()),
(1, 39, NOW(), NOW()),
(1, 40, NOW(), NOW()),
(1, 41, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
-- 插件管理菜单
INSERT INTO `menu` (`id`, `parent_id`, `name`, `meta`, `path`, `component`, `redirect`, `status`, `sort`, `created_by`, `updated_by`, `created_at`, `updated_at`, `remark`) VALUES
(50, 0, 'plugin', '{"title": "插件管理", "icon": "ri:plug-line", "type": "M", "hidden": 0}', '/plugin', '', '', 1, 99, 0, 0, NOW(), NOW(), '插件市场与已安装插件管理'),

(51, 50, 'plugin:marketplace', '{"title": "插件市场", "icon": "ri:store-2-line", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue"}', '/plugin/marketplace', 'base/views/plugin/marketplace/index', '', 1, 0, 0, 0, NOW(), NOW(), '浏览和安装插件'),
(52, 51, 'plugin:marketplace:index', '{"title": "市场列表", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(53, 51, 'plugin:marketplace:install', '{"title": "安装插件", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),

(54, 50, 'plugin:installed', '{"title": "已安装插件", "icon": "ri:list-check-3", "type": "M", "hidden": 0, "componentPath": "modules/", "componentSuffix": ".vue"}', '/plugin/installed', 'base/views/plugin/installed/index', '', 1, 1, 0, 0, NOW(), NOW(), '管理已安装的插件'),
(55, 54, 'plugin:installed:index', '{"title": "已安装列表", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(56, 54, 'plugin:installed:enable', '{"title": "启用插件", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(57, 54, 'plugin:installed:disable', '{"title": "禁用插件", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), ''),
(58, 54, 'plugin:installed:delete', '{"title": "卸载插件", "type": "B"}', '', '', '', 1, 0, 0, 0, NOW(), NOW(), '');

-- 关联到超级管理员角色
INSERT INTO `role_belongs_menu` (`role_id`, `menu_id`, `created_at`, `updated_at`) VALUES
(1, 50, NOW(), NOW()),
(1, 51, NOW(), NOW()),
(1, 52, NOW(), NOW()),
(1, 53, NOW(), NOW()),
(1, 54, NOW(), NOW()),
(1, 55, NOW(), NOW()),
(1, 56, NOW(), NOW()),
(1, 57, NOW(), NOW()),
(1, 58, NOW(), NOW());

-- Casbin 权限规则
INSERT INTO `rules` (`ptype`, `v0`, `v1`, `v2`, `v3`, `v4`, `v5`, `created_at`, `updated_at`) VALUES
('p', 'SuperAdmin', 'plugin:marketplace:index', 'allow', NULL, NULL, NULL, NOW(), NOW()),
('p', 'SuperAdmin', 'plugin:marketplace:install', 'allow', NULL, NULL, NULL, NOW(), NOW()),
('p', 'SuperAdmin', 'plugin:installed:index', 'allow', NULL, NULL, NULL, NOW(), NOW()),
('p', 'SuperAdmin', 'plugin:installed:enable', 'allow', NULL, NULL, NULL, NOW(), NOW()),
('p', 'SuperAdmin', 'plugin:installed:disable', 'allow', NULL, NULL, NULL, NOW(), NOW()),
('p', 'SuperAdmin', 'plugin:installed:delete', 'allow', NULL, NULL, NULL, NOW(), NOW());
