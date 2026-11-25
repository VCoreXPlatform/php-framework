<?php

declare(strict_types=1);

use Core\Application;
use Core\DB\Database;
use Core\Http\ArraySession;
use Core\Http\SessionStore;
use Core\Testing\HttpClient;
use VCoreX\Auth\Auth;
use VCoreX\Auth\ResetDelivery;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$_SERVER['REMOTE_ADDR'] = 'test-' . bin2hex(random_bytes(8));
$app = Application::boot($root);
$session = new ArraySession();
$app->container->instance(SessionStore::class, $session);

$delivery = new class implements ResetDelivery {
    public string $token = '';

    public function enqueue(string $email, string $token): void
    {
        $this->token = $token;
    }
};
$db = $app->container->get(Database::class);
$app->container->instance(Auth::class, new Auth($db, $session, $delivery));
$client = new HttpClient($app);

$email = bin2hex(random_bytes(8)) . '@example.test';
$password = 'a test password 2026';
$credentials = ['email' => $email, 'password' => $password];
$next = 'a changed password 2026';
$headers = static function () use ($client): array {
    return ['X-CSRF-Token' => $client->getJson('/api/auth/csrf')->assertStatus(200)->json()['csrf_token']];
};

$client->postJson('/api/auth/register', $credentials)->assertStatus(403);
$registered = $client->postJson('/api/auth/register', $credentials, $headers())->assertStatus(201)->json();
$user = $registered['user'];
if (array_keys($user) !== ['id', 'email'] || strlen($registered['recovery_code']) !== 64) {
    throw new RuntimeException('Unexpected registration payload');
}

try {
    $client->postJson('/api/auth/register', $credentials, $headers())->assertStatus(409);
    $client->getJson('/api/auth/me')->assertStatus(401);
    $client->postJson('/api/auth/login', $credentials, $headers())->assertStatus(200);
    if ($session->rotations !== 1) {
        throw new RuntimeException('Login did not rotate the session');
    }
    $client->getJson('/api/auth/me')->assertStatus(200)->assertJson(['user' => $user]);
    $client->postJson('/api/auth/reset-password', [
        'email' => $email, 'token' => $registered['recovery_code'], 'password' => $next,
    ], $headers())->assertStatus(200);
    $client->getJson('/api/auth/me')->assertStatus(401);
    $client->postJson('/api/auth/reset-password', [
        'email' => $email, 'token' => $registered['recovery_code'], 'password' => $password,
    ], $headers())->assertStatus(422);
    $client->postJson('/api/auth/forgot-password', ['email' => $email], $headers())->assertStatus(202);
    $client->postJson('/api/auth/reset-password', [
        'email' => $email, 'token' => $delivery->token, 'password' => $password,
    ], $headers())->assertStatus(200);
    $client->postJson('/api/auth/login', $credentials, $headers())->assertStatus(200);
    $client->postJson('/api/auth/logout', [], $headers())->assertStatus(204);
    $client->getJson('/api/auth/me')->assertStatus(401);

    for ($i = 0; $i < 6; $i++) {
        $client->postJson('/api/auth/login', ['email' => $email, 'password' => 'incorrect'], $headers())
            ->assertStatus(401);
    }
    $client->postJson('/api/auth/login', $credentials, $headers())->assertStatus(429);
} finally {
    $db->table('frameworkuser', [])->delete($user['id']);
}

echo "Auth registration, CSRF, login, recovery, logout and rate limits passed.\n";
