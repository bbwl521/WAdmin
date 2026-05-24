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
use Symfony\Component\Finder\Finder;

return [
    'enable' => true,
    'port' => 9503,
    'json_dir' => BASE_PATH . '/storage/swagger',
    'html' => file_get_contents(BASE_PATH . '/storage/swagger/index.html'),
    'url' => '/swagger',
    'auto_generate' => true,
    'scan' => [
        'paths' => [
            Finder::create()
                ->in([BASE_PATH . '/app/Http', BASE_PATH . '/app/Schema'])
                ->name('*.php')
                ->getIterator(),
        ],
    ],
    'processors' => [],
];
