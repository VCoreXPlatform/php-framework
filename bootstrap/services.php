<?php

declare(strict_types=1);

use Core\Config\Config;
use Core\Container;
use Core\DB\Database;
use Core\Http\HttpClient;
use Core\Http\Session;
use Core\Http\SessionStore;
use Core\Router\Router;
use VCoreX\Auth\Auth;
use VCoreX\Auth\AuthRoutes;
use VCoreX\Auth\RateLimiter;

return static function (Container $container, string $root, Config $config): void {
    if (class_exists(HttpClient::class)) {
        $container->singleton(HttpClient::class, static fn (): HttpClient => new HttpClient());
    }

    if (class_exists(Session::class)) {
        $container->singleton(
            Session::class,
            static fn (): Session => new Session(
                $root . '/storage/sessions',
                $config->get('app.env') === 'production'
                    || in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true),
            ),
        );
    }

    if (interface_exists(SessionStore::class)) {
        $container->singleton(SessionStore::class, static fn (Container $c): SessionStore => $c->get(Session::class));
    }

    if (class_exists(Auth::class)) {
        $container->singleton(
            Auth::class,
            static fn (Container $c): Auth => new Auth($c->get(Database::class), $c->get(SessionStore::class)),
        );
        $container->singleton(RateLimiter::class, static fn (): RateLimiter => new RateLimiter($root . '/storage/cache'));
        AuthRoutes::register($container->get(Router::class));
    }
};
