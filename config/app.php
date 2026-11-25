<?php

declare(strict_types=1);

use Core\Config\Env;

return [
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
];
