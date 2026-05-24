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

namespace App\Http\Common\Swagger;

use Hyperf\Swagger\Annotation as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        version: '3.0.0',
        description: 'WAdmin 是一款基于 Hyperf 开发的开源管理系统，提供了用户管理、权限管理、系统设置、系统监控等功能。',
        title: 'WAdmin',
        termsOfService: 'https://github.com/bbwl521/WAdmin',
        contact: new OA\Contact(name: 'WAdmin', url: 'https://github.com/bbwl521/WAdmin/about'),
        license: new OA\License(name: 'Apache2.0', url: 'https://github.com/bbwl521/WAdmin/blob/master/LICENSE')
    ),
    servers: [
        new OA\Server(
            url: 'http://127.0.0.1:9501',
            description: '本地服务'
        ),
        new OA\Server(
            url: 'https://demo.mineadmin.com',
            description: '演示服务',
        ),
    ],
    externalDocs: new OA\ExternalDocumentation(description: '开发文档', url: 'https://github.com/bbwl521/WAdmin')
)]
#[OA\SecurityScheme(
    securityScheme: 'Bearer',
    type: 'http',
    name: 'Authorization',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
#[OA\SecurityScheme(
    securityScheme: 'ApiKey',
    type: 'apiKey',
    name: 'token',
    in: 'header'
)]
final class Server {}
