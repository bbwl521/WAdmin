<?php

declare(strict_types=1);

namespace App\Plugin;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                MarketplaceService::class => MarketplaceService::class,
                MarketplacePluginService::class => MarketplacePluginService::class,
                PluginPublisher::class => PluginPublisher::class,
                PluginAutoloader::class => PluginAutoloader::class,
                PluginMigrationRunner::class => PluginMigrationRunner::class,
                PluginRouteRegistrar::class => PluginRouteRegistrar::class,
                PluginMenuRegistrar::class => PluginMenuRegistrar::class,
                PluginInstallService::class => PluginInstallService::class,
                PluginUninstallService::class => PluginUninstallService::class,
                PluginUpgradeService::class => PluginUpgradeService::class,
                PluginConfigService::class => PluginConfigService::class,
                PluginManager::class => PluginManager::class,
            ],
        ];
    }
}
