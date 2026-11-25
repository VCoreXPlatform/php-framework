<?php

declare(strict_types=1);

$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
$public = dirname(__DIR__) . '/public';
if (preg_match('~\A/assets/build/[a-zA-Z0-9_-]+\.(js|css)\z~', $path) && is_file($public . $path)) {
    return false;
}
require $public . '/index.php';
