<?php

declare(strict_types=1);

use App\Middleware\ApiHeaders;
use Core\Http\Response;
use Core\Router\Router;

return static function (Router $router): void {
    $router->group('/api', [ApiHeaders::class], static function (Router $router): void {
        $router->add('GET', '/health', static fn (): Response => Response::json(['status' => 'ok']));
    });
};
