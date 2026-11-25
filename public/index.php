<?php

declare(strict_types=1);

use Core\Application;
use Core\Http\Request;
use Core\Http\Response;

ini_set('display_errors', '0');

$root = dirname(__DIR__);
try {
    require $root . '/vendor/autoload.php';
    $app = Application::boot($root);
    $app->handle(Request::fromGlobals())->send($_SERVER['REQUEST_METHOD'] ?? 'GET');
} catch (Throwable $error) {
    error_log((string) $error);
    if (class_exists(Response::class)) {
        Response::json(['error' => 'Internal Server Error'], 500)->send($_SERVER['REQUEST_METHOD'] ?? 'GET');
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo '{"error":"Internal Server Error"}';
        }
    }
}
