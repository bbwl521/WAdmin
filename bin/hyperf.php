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
 * 通过 runtime/.install/install.lock 锁文件判断，类似 FastAdmin
 */
function checkInstallation(): bool
{
    return file_exists(BASE_PATH . '/runtime/.install/install.lock');
}

/**
 * Print installation instructions and try to open browser
 */
function printInstallInstructions(): void
{
    $ascii = <<<'ART'
   ___ _           _                   _
  / __(_)_ __   __| | ___  _ __   ___ (_)_ __ ___
 | |__| | '_ \ / _` |/ _ \| '_ \ / _ \| | '__/ _ \\
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

    // Try to open browser automatically
    $installUrl = 'http://127.0.0.1:9501/install';
    $opened = false;

    if (PHP_OS_FAMILY === 'Darwin') {
        // macOS
        exec('open ' . escapeshellarg($installUrl) . ' > /dev/null 2>&1 &', $output, $returnCode);
        $opened = ($returnCode === 0);
    } elseif (PHP_OS_FAMILY === 'Windows') {
        // Windows
        exec('start "" ' . escapeshellarg($installUrl) . ' > nul 2>&1', $output, $returnCode);
        $opened = ($returnCode === 0);
    } else {
        // Linux - try xdg-open, then sensible-browser
        exec('xdg-open ' . escapeshellarg($installUrl) . ' > /dev/null 2>&1 &', $output, $returnCode);
        if ($returnCode === 0) {
            $opened = true;
        } else {
            exec('sensible-browser ' . escapeshellarg($installUrl) . ' > /dev/null 2>&1 &', $output, $returnCode);
            $opened = ($returnCode === 0);
        }
    }

    if ($opened) {
        echo "  \033[32m✅ Browser opened automatically: {$installUrl}\033[0m\n";
    } else {
        echo "  \033[33m⚠️  Could not open browser automatically. Please open the URL manually.\033[0m\n";
    }
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
