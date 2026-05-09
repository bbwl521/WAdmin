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

namespace App\Http\Admin\Request;

use Hyperf\Validation\Request\FormRequest;

class InstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:50',
            'charset' => 'nullable|string|max:50',
            'collation' => 'nullable|string|max:100',
            'app_name' => 'nullable|string|max:100',
            'app_url' => 'nullable|url|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'host.required' => 'Database host is required',
            'port.required' => 'Database port is required',
            'port.integer' => 'Database port must be an integer',
            'port.min' => 'Database port must be between 1 and 65535',
            'port.max' => 'Database port must be between 1 and 65535',
            'database.required' => 'Database name is required',
            'database.regex' => 'Database name must start with a letter and contain only letters, numbers, and underscores',
            'database.max' => 'Database name cannot exceed 64 characters',
            'username.required' => 'Database username is required',
            'app_url.url' => 'Application URL must be a valid URL',
        ];
    }

    /**
     * 获取数据库配置
     */
    public function getDatabaseConfig(): array
    {
        return [
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => $this->input('host', 'localhost'),
            'DB_PORT' => (int) $this->input('port', 3306),
            'DB_DATABASE' => $this->input('database'),
            'DB_USERNAME' => $this->input('username'),
            'DB_PASSWORD' => $this->input('password', ''),
            'DB_CHARSET' => $this->input('charset', 'utf8mb4'),
            'DB_COLLATION' => $this->input('collation', 'utf8mb4_unicode_ci'),
            'DB_PREFIX' => $this->input('prefix', ''),
            'APP_NAME' => $this->input('app_name', 'MineAdmin'),
            'APP_URL' => $this->input('app_url', 'http://127.0.0.1:9501'),
            'REDIS_HOST' => $this->input('redis_host', '127.0.0.1'),
            'REDIS_PORT' => (int) $this->input('redis_port', 6379),
            'REDIS_AUTH' => $this->input('redis_auth', ''),
            'REDIS_DB' => (int) $this->input('redis_db', 0),
        ];
    }
}
