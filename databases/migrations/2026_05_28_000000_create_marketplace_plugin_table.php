<?php

declare(strict_types=1);
/**
 * This file is part of WAdmin.
 *
 * @link     https://github.com/bbwl521/WAdmin
 * @document https://github.com/bbwl521/WAdmin
 * @contact  admin@wadmin.local
 * @license  https://github.com/bbwl521/WAdmin/blob/master/LICENSE
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateMarketplacePluginTable extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_plugin', static function (Blueprint $table) {
            $table->comment('插件市场表');
            $table->bigIncrements('id')->comment('主键');
            $table->string('code', 100)->comment('插件唯一标识');
            $table->string('name', 100)->comment('插件名称');
            $table->string('version', 20)->comment('版本号');
            $table->text('description')->nullable()->comment('描述');
            $table->string('author', 100)->nullable()->comment('作者');
            $table->string('category', 50)->nullable()->comment('分类');
            $table->string('icon', 100)->nullable()->comment('图标');
            $table->string('download_url')->nullable()->comment('下载地址');
            $table->integer('downloads')->default(0)->comment('下载次数');
            $table->tinyInteger('status')->default(1)->comment('状态: 1=上架 2=下架');
            $table->json('meta')->nullable()->comment('元数据');
            $table->datetimes();
            $table->unique('code', 'mkt_plugin_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_plugin');
    }
}
