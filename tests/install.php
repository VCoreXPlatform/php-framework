<?php

declare(strict_types=1);

use Core\Application;
use Core\Http\HttpClient;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;
use Core\Testing\HttpClient as TestClient;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = Application::boot($root);
$client = new TestClient($app);
$client->getJson('/api/health')->assertStatus(200)->assertJson(['status' => 'ok']);
$client->postJson('/api/health', [])->assertStatus(405);

$app->router->add('POST', '/test-validation', static function (Request $request): Response {
    return Response::json($request->validate([
        'name' => 'required|string|min:2|max:30',
        'active' => 'boolean',
        'age' => 'integer|min:18',
        'comment' => 'nullable|string',
    ]));
});
$client->postJson('/test-validation', [
    'name' => 'Яна', 'active' => 'false', 'age' => '21', 'comment' => null, 'protected' => true,
])->assertStatus(200)->assertJson(['name' => 'Яна', 'active' => false, 'age' => 21, 'comment' => null]);
$client->postJson('/test-validation', ['active' => [], 'age' => 17])
    ->assertStatus(422)
    ->assertJson(['error' => 'Validation failed', 'errors' => [
        'name' => ['required'], 'active' => ['boolean'], 'age' => ['min:18'],
    ]]);

$app->router->add('GET', '/test-query', static function (Request $request): Response {
    return Response::json($request->validateQuery(['page' => 'required|integer|min:1']));
});
$client->getJson('/test-query', ['page' => '2'])->assertStatus(200)->assertJson(['page' => 2]);

foreach ([HttpClient::class, Session::class] as $service) {
    if (class_exists($service) && !$app->container->get($service) instanceof $service) {
        throw new RuntimeException("Invalid service binding for {$service}");
    }
}

echo "Framework installation, HTTP test client, and validation passed.\n";
