<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Core\Http\Middleware;
use Core\Http\Request;
use Core\Http\Response;

final class ApiHeaders implements Middleware
{
    public function process(Request $request, Closure $next): Response
    {
        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}
