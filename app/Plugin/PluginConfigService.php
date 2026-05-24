<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Model\Plugin as PluginModel;
use App\Plugin\Exception\PluginNotFoundException;

/**
 * 插件配置管理服务.
 *
 * 支持读取和更新插件私有配置（plugin.config JSON 字段），
 * 提供默认值回退（从 plugin.json 中声明的 config 字段读取）。
 */
final class PluginConfigService
{
    /**
     * 获取插件配置，带默认值回退.
     *
     * @param string $code 插件标识
     * @param string|null $key 配置键（点号分隔嵌套键），null 返回全部
     * @param mixed $default 未找到时的默认值
     * @return mixed
     */
    public function get(string $code, ?string $key = null, mixed $default = null): mixed
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $config = $plugin->config ?? [];
        if (! is_array($config)) {
            $config = [];
        }

        // 合并默认值
        $defaults = $this->getDefaults($plugin);
        $merged = array_merge($defaults, $config);

        if ($key === null) {
            return $merged;
        }

        return $this->dotGet($merged, $key, $default);
    }

    /**
     * 更新插件配置（部分 merge）.
     *
     * @param string $code 插件标识
     * @param array<string, mixed> $values 要更新的配置键值对
     */
    public function set(string $code, array $values): void
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $current = $plugin->config ?? [];
        if (! is_array($current)) {
            $current = [];
        }

        $merged = array_merge($current, $values);
        $plugin->update(['config' => $merged]);
    }

    /**
     * 重置插件配置为默认值.
     */
    public function reset(string $code): void
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $plugin->update(['config' => null]);
    }

    /**
     * 获取配置 schema（来自 plugin.json 的 config 声明）.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSchema(string $code): array
    {
        $plugin = PluginModel::query()->where('code', $code)->first();
        if ($plugin === null) {
            throw new PluginNotFoundException("插件 '{$code}' 未安装");
        }

        $meta = $plugin->meta;
        if (! is_array($meta)) {
            return [];
        }

        return $meta['config'] ?? [];
    }

    /**
     * 从 plugin.json meta 中提取默认配置.
     *
     * @return array<string, mixed>
     */
    private function getDefaults(PluginModel $plugin): array
    {
        $defaults = [];
        $schema = $this->getSchema($plugin->code);

        foreach ($schema as $item) {
            if (isset($item['key']) && array_key_exists('default', $item)) {
                $defaults[$item['key']] = $item['default'];
            }
        }

        return $defaults;
    }

    /**
     * 点号分隔键获取嵌套数组值.
     */
    private function dotGet(array $array, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (! is_array($array) || ! array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}
