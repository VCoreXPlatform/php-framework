<?php

declare(strict_types=1);

use Core\Config\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'framework'),
    'username' => Env::get('DB_USERNAME', 'framework'),
    'password' => Env::get('DB_PASSWORD', ''),
    'freeze' => Env::bool('DB_FREEZE', true),
];
