<?php

declare(strict_types=1);

use Core\Http\Response;
use Core\Router\Router;

return static function (Router $router, string $root): void {
    $router->add('GET', '/', static function () use ($root): Response {
        $index = $root . '/public/assets/index.html';
        if (!is_file($index)) {
            return Response::html('<!doctype html><meta charset="utf-8"><p>Выполните npm ci и npm run build.</p>', 503);
        }
        return Response::html(file_get_contents($index));
    });
};
