<?php

declare(strict_types=1);

use Core\Application;
use Core\Http\HttpClient;
use Core\Http\Request;
use Core\Http\Session;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = Application::boot($root);
$response = $app->handle(new Request('GET', '/api/health'));
if ($response->status !== 200 || json_decode($response->body, true, 512, JSON_THROW_ON_ERROR) !== ['status' => 'ok']) {
    throw new RuntimeException('The installed core did not serve the health route');
}

foreach ([HttpClient::class, Session::class] as $service) {
    if (!class_exists($service)) {
        continue;
    }
    if (!$app->container->get($service) instanceof $service) {
        throw new RuntimeException("Invalid service binding for {$service}");
    }
}

echo "Framework installation smoke test passed.\n";
