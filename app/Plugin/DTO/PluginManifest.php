<?php

declare(strict_types=1);

namespace App\Plugin\DTO;

use App\Plugin\Exception\PluginManifestException;

final class PluginManifest
{
    /** @param array<string, mixed> $raw plugin.json 解析后的原始数据 */
    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly array $author,
        public readonly string $hyperf,
        public readonly string $mineadmin,
        public readonly array $dependencies,
        public readonly array $autoload,
        public readonly array $permissions,
        public readonly array $menus,
        public readonly array $config,
        public readonly array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        $required = ['code', 'name', 'version'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                throw new PluginManifestException("plugin.json 缺少必需字段: {$key}");
            }
        }

        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            description: (string) ($data['description'] ?? ''),
            author: (array) ($data['author'] ?? []),
            hyperf: (string) ($data['hyperf'] ?? '>=3.0'),
            mineadmin: (string) ($data['mineadmin'] ?? '>=3.0'),
            dependencies: (array) ($data['dependencies'] ?? []),
            autoload: (array) ($data['autoload'] ?? []),
            permissions: (array) ($data['permissions'] ?? []),
            menus: (array) ($data['menus'] ?? []),
            config: (array) ($data['config'] ?? []),
            raw: $data,
        );
    }

    public static function fromJsonFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new PluginManifestException('plugin.json 不存在: ' . $path);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new PluginManifestException('无法读取 plugin.json: ' . $path);
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new PluginManifestException('plugin.json 格式无效: ' . $path);
        }

        return self::fromArray($data);
    }

    /** 获取 PSR-4 自动加载映射 */
    public function getPsr4Mappings(): array
    {
        return (array) ($this->autoload['psr-4'] ?? []);
    }

    /** 检查依赖是否已满足（仅检查声明非空） */
    public function hasDependencies(): bool
    {
        return $this->dependencies !== [];
    }

    /** 检查是否有配置项 */
    public function hasConfig(): bool
    {
        return $this->config !== [];
    }
}
