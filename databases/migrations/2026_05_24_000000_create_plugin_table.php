<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreatePluginTable extends Migration
{
    public function up(): void
    {
        Schema::create('plugin', static function (Blueprint $table) {
            $table->comment('插件信息表');
            $table->bigIncrements('id')->comment('主键');
            $table->string('code', 100)->comment('插件唯一标识');
            $table->string('name', 100)->comment('插件名称');
            $table->string('version', 20)->comment('当前版本号');
            $table->string('source', 20)->default('marketplace')->comment('来源: marketplace/local');
            $table->tinyInteger('status')->default(1)->comment('状态: 1=已启用, 2=已禁用');
            $table->json('config')->nullable()->comment('插件私有配置');
            $table->json('meta')->nullable()->comment('plugin.json 元数据快照');
            $table->datetimes();
            $table->unique('code', 'plugin_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin');
    }
}
