<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreatePluginMigrationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_migrations', static function (Blueprint $table) {
            $table->comment('插件迁移追踪表');
            $table->bigIncrements('id')->comment('主键');
            $table->string('plugin_code', 100)->comment('插件标识');
            $table->string('migration', 200)->comment('迁移文件名（不含 .php）');
            $table->timestamp('created_at')->useCurrent()->comment('执行时间');
            $table->unique(['plugin_code', 'migration'], 'plugin_migration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_migrations');
    }
}
