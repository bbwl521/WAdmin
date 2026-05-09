#!/usr/bin/env php
<?php

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '1G');

error_reporting(E_ALL);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);
! defined('START_TIME') && define('START_TIME', time());    // 启动时间
! defined('HF_VERSION') && define('HF_VERSION', '3.1');     // 定义hyperf版本号

require BASE_PATH . '/vendor/autoload.php';

// Load .env file if it exists
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    try {
        Dotenv\Dotenv::createImmutable(BASE_PATH, '.env')->load();
        // Sync $_ENV to getenv for Hyperf framework compatibility
        foreach ($_ENV as $key => $value) {
            if (!is_array($value)) {
                putenv("{$key}={$value}");
            }
        }
    } catch (\Exception $e) {
        // Ignore if .env loading fails
    }
}

/**
 * Check if the system is installed
 */
function checkInstallation(): bool
{
    $envFile = BASE_PATH . '/.env';

    // If .env doesn't exist, system is not installed
    if (! file_exists($envFile)) {
        return false;
    }

    // Parse .env file to check if database is configured
    $envContent = file_get_contents($envFile);
    if (empty($envContent)) {
        return false;
    }

    $config = [];
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }

    // Check if required database configuration exists
    if (empty($config['DB_DATABASE']) || empty($config['DB_HOST'])) {
        return false;
    }

    return true;
}

/**
 * Print installation instructions
 */
function printInstallInstructions(): void
{
    $ascii = <<<'ART'
   ___ _           _                   _
  / __(_)_ __   __| | ___  _ __   ___ (_)_ __ ___
 | |__| | '_ \ / _` |/ _ \| '_ \ / _ \| | '__/ _ \
 |  __| | | | | (_| | (_) | |_) | (_) | | | |  __/
 |_|  |_|_| |_|\__,_|\___/| .__/ \___/|_|_|  \___|
                           |_|
ART;

    echo "\n";
    echo "\033[34m" . $ascii . "\033[0m";
    echo "\n";
    echo "\033[36m  Welcome to MineAdmin!\033[0m\n";
    echo "\n";
    echo "  \033[33m⚠️  System is not installed yet.\033[0m\n";
    echo "\n";
    echo "  Please complete the installation by visiting:\n";
    echo "    \033[36m  http://127.0.0.1:9501/install\033[0m\n";
    echo "\n";
    echo "  Or use the command line:\n";
    echo "    \033[36m  php bin/hyperf.php mine:install\033[0m\n";
    echo "\n";
    echo "  For more information, visit:\n";
    echo "    \033[36m  https://doc.mineadmin.com\033[0m\n";
    echo "\n";
}

// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    // Check installation status
    if (! checkInstallation()) {
        printInstallInstructions();
        // Start server anyway so user can access install page
    }

    Hyperf\Di\ClassLoader::init();
    /** @var Psr\Container\ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';

    $application = $container->get(Hyperf\Contract\ApplicationInterface::class);
    $application->run();
})();
