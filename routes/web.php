<?php

declare(strict_types=1);

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\Router;

return static function (Router $router, string $root): void {
    $router->fallback(static function (Request $request) use ($root): Response {
        if (
            !in_array($request->method, ['GET', 'HEAD'], true)
            || !in_array(rtrim($request->path, '/'), ['', '/notes'], true)
        ) {
            throw new HttpException(404, 'Not Found');
        }

        $index = $root . '/public/assets/index.html';
        if (!is_file($index)) {
            return Response::html('<!doctype html><meta charset="utf-8"><p>Выполните npm run build.</p>', 503);
        }
        return Response::html(file_get_contents($index));
    });
};
