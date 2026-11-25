<?php

declare(strict_types=1);

use Core\Config\Config;
use Core\Container;
use Core\Http\HttpClient;
use Core\Http\Session;

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
};
