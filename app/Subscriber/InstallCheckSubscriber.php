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

namespace App\Subscriber;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Server\Server;

final class InstallCheckSubscriber implements ListenerInterface
{
    private const LOCK_FILE = BASE_PATH . '/runtime/.install/install.lock';

    private const FALLBACK_URL = 'http://127.0.0.1:9501/install';

    private const ASCII_ART = <<<'ART'
           ___ _           _                   _
          / __(_)_ __   __| | ___  _ __   ___ (_)_ __ ___
         | |__| | '_ \ / _` |/ _ \| '_ \ / _ \| | '__/ _ \\
         |  __| | | | | (_| | (_) | |_) | (_) | | | |  __/
         |_|  |_|_| |_|\__,_|\___/| .__/ \___/|_|_|  \___|
                                   |_|
        ART;

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        if ($this->isInstalled()) {
            return;
        }

        $this->printBanner();
        $this->tryOpenBrowser();
    }

    private function isInstalled(): bool
    {
        return file_exists(self::LOCK_FILE);
    }

    private function printBanner(): void
    {
        $installUrl = $this->getInstallUrl();

        echo \PHP_EOL;
        echo "\033[34m" . self::ASCII_ART . "\033[0m";
        echo \PHP_EOL;
        echo "\033[36m  Welcome to MineAdmin!\033[0m" . \PHP_EOL;
        echo \PHP_EOL;
        echo "  \033[33m⚠️  System is not installed yet.\033[0m" . \PHP_EOL;
        echo \PHP_EOL;
        echo '  Please complete the installation by visiting:' . \PHP_EOL;
        echo "    \033[36m  " . $installUrl . "\033[0m" . \PHP_EOL;
        echo \PHP_EOL;
        echo '  Or use the command line:' . \PHP_EOL;
        echo "    \033[36m  php bin/hyperf.php mine:install\033[0m" . \PHP_EOL;
        echo \PHP_EOL;
        echo '  For more information, visit:' . \PHP_EOL;
        echo "    \033[36m  https://doc.mineadmin.com\033[0m" . \PHP_EOL;
        echo \PHP_EOL;
    }

    private function tryOpenBrowser(): void
    {
        $installUrl = $this->getInstallUrl();

        $opened = match (\PHP_OS_FAMILY) {
            'Darwin' => $this->execInBackground('open ' . escapeshellarg($installUrl)),
            'Windows' => $this->execInBackground('start "" ' . escapeshellarg($installUrl)),
            default => $this->execInBackground('xdg-open ' . escapeshellarg($installUrl))
                || $this->execInBackground('sensible-browser ' . escapeshellarg($installUrl)),
        };

        if ($opened) {
            echo "  \033[32m✅ Browser opened automatically: " . $installUrl . "\033[0m" . \PHP_EOL;
        } else {
            echo "  \033[33m⚠️  Could not open browser automatically. Please open the URL manually.\033[0m" . \PHP_EOL;
        }
        echo \PHP_EOL;
    }

    /**
     * 从 Server 配置中动态获取安装 URL，避免硬编码端口.
     */
    private function getInstallUrl(): string
    {
        try {
            $container = ApplicationContext::getContainer();
            $config = $container->get(ConfigInterface::class);

            /** @var null|array<string, mixed> $servers */
            $servers = $config->get('server.servers');
            if (\is_array($servers)) {
                foreach ($servers as $server) {
                    if (isset($server['type']) && $server['type'] === Server::SERVER_HTTP) {
                        $host = $server['host'] ?? '0.0.0.0';
                        $port = (int) ($server['port'] ?? 9501);
                        // 如果绑定了 0.0.0.0，使用 127.0.0.1 进行本地访问
                        $accessHost = $host === '0.0.0.0' ? '127.0.0.1' : $host;
                        return "http://{$accessHost}:{$port}/install";
                    }
                }
            }
        } catch (\Throwable) {
            // 容器未就绪时静默降级
        }

        return self::FALLBACK_URL;
    }

    private function execInBackground(string $command): bool
    {
        exec($command . ' > /dev/null 2>&1 &', result_code: $returnCode);
        return $returnCode === 0;
    }
}
