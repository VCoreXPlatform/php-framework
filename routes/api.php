<?php

declare(strict_types=1);

use App\Controller\NoteController;
use App\Middleware\ApiHeaders;
use Core\Http\Response;
use Core\Router\Router;

return static function (Router $router): void {
    $router->group('/api', [ApiHeaders::class], static function (Router $router): void {
        $router->add('GET', '/health', static fn (): Response => Response::json(['status' => 'ok']));
        $router->add('GET', '/notes', [NoteController::class, 'index']);
        $router->add('POST', '/notes', [NoteController::class, 'create']);
        $router->add('GET', '/notes/{id}', [NoteController::class, 'show']);
        $router->add('PUT', '/notes/{id}', [NoteController::class, 'update']);
        $router->add('PATCH', '/notes/{id}', [NoteController::class, 'update']);
        $router->add('DELETE', '/notes/{id}', [NoteController::class, 'delete']);
    });
};
