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
