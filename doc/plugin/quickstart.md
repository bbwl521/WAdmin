# 快速上手：编写一个意见反馈插件

本教程从零开始，带你完成一个完整的意见反馈插件。

## 第一步：创建插件目录

```bash
mkdir -p plugins/feedback/src/Controller
mkdir -p plugins/feedback/migrations
```

最终结构：

```
plugins/feedback/
├── plugin.json
├── Plugin.php
├── routes.php
├── migrations/
│   └── CreateFeedbackTable.php
└── src/
    └── Controller/
        └── FeedbackController.php
```

## 第二步：编写 plugin.json

```json
{
    "code": "feedback",
    "name": "意见反馈",
    "version": "1.0.0",
    "description": "收集用户意见与反馈，支持分类与状态追踪",
    "author": { "name": "MineAdmin", "email": "root@imoi.cn" },
    "hyperf": ">=3.1",
    "mineadmin": ">=3.0",
    "autoload": { "psr-4": { "Plugin\\Feedback\\": "src/" } },
    "permissions": [
        { "name": "feedback:index",  "display_name": "反馈列表" },
        { "name": "feedback:save",   "display_name": "提交反馈" },
        { "name": "feedback:update", "display_name": "处理反馈" },
        { "name": "feedback:delete", "display_name": "删除反馈" }
    ],
    "menus": [
        {
            "name": "feedback",
            "meta": { "title": "意见反馈", "icon": "ri:feedback-line", "type": "M" },
            "path": "/feedback",
            "component": "base/views/feedback/index",
            "sort": 99,
            "children": [
                { "name": "feedback:index",  "meta": { "title": "反馈列表", "type": "B" } },
                { "name": "feedback:save",   "meta": { "title": "提交反馈", "type": "B" } },
                { "name": "feedback:update", "meta": { "title": "处理反馈", "type": "B" } },
                { "name": "feedback:delete", "meta": { "title": "删除反馈", "type": "B" } }
            ]
        }
    ]
}
```

### 字段要点

- **code**：插件唯一标识，全小写+下划线
- **autoload.psr-4**：`"Plugin\\Feedback\\": "src/"` 让系统知道源码在哪
- **permissions**：`code:action` 格式，自动写入 Casbin 规则
- **menus**：父级 name 用 code，子级用权限 name；type 为 `M` 显示菜单、`B` 为按钮权限
- **component**：前端组件路径，相对于 `web/src/modules/`

## 第三步：编写迁移文件

`migrations/CreateFeedbackTable.php`：

```php
<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateFeedbackTable extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键');
            $table->string('title', 100)->comment('反馈标题');
            $table->text('content')->comment('反馈内容');
            $table->string('type', 20)->default('suggestion')->comment('类型');
            $table->string('contact', 100)->default('')->comment('联系方式');
            $table->string('status', 20)->default('pending')->comment('状态');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
}
```

> 迁移文件按字典序执行，已执行的不会重复运行。类名必须与文件名一致。

## 第四步：编写路由

`routes.php`：

```php
<?php

return [
    ['GET',    '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@index'],
    ['POST',   '/admin/feedback',       'Plugin\\Feedback\\Controller\\FeedbackController@store'],
    ['PUT',    '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@update'],
    ['DELETE', '/admin/feedback/{id}',  'Plugin\\Feedback\\Controller\\FeedbackController@destroy'],
];
```

格式：`[HTTP方法, 路径, 控制器@方法]`

## 第五步：编写控制器

`src/Controller/FeedbackController.php`：

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
        return $this->success(compact('list'));
    }

    public function store(): Result
    {
        $data = $this->request->all();
        Db::table('feedback')->insert([
            'title'      => $data['title'] ?? '',
            'content'    => $data['content'] ?? '',
            'type'       => $data['type'] ?? 'suggestion',
            'contact'    => $data['contact'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['message' => '提交成功']);
    }

    public function update(int $id): Result
    {
        $data = $this->request->all();
        Db::table('feedback')->where('id', $id)->update([
            'status'     => $data['status'] ?? 'pending',
            'reply'      => $data['reply'] ?? '',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['message' => '更新成功']);
    }

    public function destroy(int $id): Result
    {
        Db::table('feedback')->where('id', $id)->delete();
        return $this->success(['message' => '删除成功']);
    }
}
```

## 第六步：（可选）生命周期钩子

`Plugin.php`：

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

    public function onUpgrade(): void {}

    public function onBeforeUpgrade(): void {}

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

> 不需要构造函数，不需要导入 `PluginManifest`。方法体为空也完全正常——系统会自动处理迁移、路由和菜单。

## 第七步：打包与安装

```bash
cd plugins/feedback
zip -r feedback-v1.0.0.zip . -x "*.DS_Store"
```

然后进入管理后台 → 插件市场 → 上传插件包 → 点击安装。

安装流程：

1. 路由冲突检测
2. 注册 PSR-4 自动加载
3. 执行数据库迁移
4. 注册路由
5. 注册菜单和权限
6. 写入 plugin 表
7. 调用 `onInstall()`

安装完成后侧边栏即时出现"意见反馈"菜单。

## 第八步：前端页面

在 `web/src/modules/base/views/feedback/` 下创建 `index.vue`：

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'

defineOptions({ name: 'plugin:feedback' })

const list = ref([])

async function fetchList() {
  const res = await useHttp().get('/admin/feedback')
  list.value = res.data?.list ?? []
}

onMounted(() => fetchList())
</script>

<template>
  <div class="p-4">
    <el-table :data="list" border stripe>
      <el-table-column prop="title" label="标题" />
      <el-table-column prop="type" label="类型" width="100" />
      <el-table-column prop="status" label="状态" width="100" />
      <el-table-column prop="created_at" label="提交时间" width="180" />
    </el-table>
  </div>
</template>
```

路径 `base/views/feedback/index` 对应 `web/src/modules/base/views/feedback/index.vue`。

## 升级插件

1. 修改代码，更新 `plugin.json` 中的 `version`
2. 如需新增表/字段，添加新迁移文件（如 `AddStatusColumn.php`）
3. 打包新版本 zip
4. 在后台已安装列表点击"升级"

升级时会：
- 备份旧版本 → 执行新迁移 → 更新路由和菜单 → 失败自动回滚

## 小结

一个完整的插件 = `plugin.json` + `routes.php` + 控制器 + 可选迁移 + 可选钩子。10 分钟即可完成开发。
