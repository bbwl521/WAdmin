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

namespace App\Command;

use App\Service\InstallService;
use Hyperf\Command\Annotation\AsCommand;
use Hyperf\Command\Concerns\InteractsWithIO;

#[AsCommand(
    signature: 'mine:install',
    description: 'Check and perform system installation'
)]
class InstallCommand
{
    use InteractsWithIO;

    public function __construct(
        private readonly InstallService $installService,
    ) {}

    public function handle(): void
    {
        $status = $this->installService->getInstallStatus();

        $this->output->title('MineAdmin Installation Check');
        $this->output->section('Installation Status');

        $this->output->writeln([
            \sprintf('  <info>Environment File:</info> %s', $status['env_exists'] ? '<fg=green>EXISTS</>' : '<fg=red>NOT FOUND</>'),
            \sprintf('  <info>Database Configured:</info> %s', $status['db_configured'] ? '<fg=green>YES</>' : '<fg=red>NO</>'),
            \sprintf('  <info>Database Connected:</info> %s', $status['db_connected'] ? '<fg=green>YES</>' : '<fg=red>NO</>'),
            \sprintf('  <info>Migrations Run:</info> %s', $status['migrations_run'] ? '<fg=green>YES</>' : '<fg=red>NO</>'),
            \sprintf('  <info>System Installed:</info> %s', $status['installed'] ? '<fg=green>YES</>' : '<fg=red>NO</>'),
            '',
            \sprintf('  Status: %s', $status['message']),
        ]);

        if ($status['installed']) {
            $this->output->success('System is already installed. You can start the server with: php bin/hyperf.php start');
        } else {
            $this->output->warning('System is not installed yet.');
            $this->output->writeln([
                '',
                'Please visit the installation page at:',
                '  <fg=blue>http://127.0.0.1:9501/install</>',
                '',
                'Or use the API endpoint:',
                '  <fg=blue>POST /admin/install/install</>',
                '',
                'For detailed installation instructions, please visit:',
                '  <fg=blue>https://doc.mineadmin.com</>',
            ]);
        }
    }
}
