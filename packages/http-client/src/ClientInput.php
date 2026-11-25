<?php

declare(strict_types=1);

namespace Core\Http;

use InvalidArgumentException;
use JsonException;

final class ClientInput
{
    public static function path(string $path): string
    {
        if (strlen($path) > 2048 || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Invalid HTTP client path');
        }

        $relative = ltrim($path, '/');
        if ($relative === '') {
            return '';
        }

        foreach (explode('/', rtrim($relative, '/')) as $segment) {
            if (!preg_match('/\A[A-Za-z0-9._~-]+\z/', $segment) || in_array($segment, ['.', '..'], true)) {
                throw new InvalidArgumentException('Invalid HTTP client path segment');
            }
        }
        return $relative;
    }

    public static function query(array $data): string
    {
        self::validateParameters($data);
        $query = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        if (strlen($query) > 8192) {
            throw new InvalidArgumentException('HTTP client query exceeds 8192 bytes');
        }
        return $query;
    }

    public static function json(array $data): string
    {
        self::validateParameters($data);
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 32);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid HTTP client JSON', 0, $error);
        }
        if (strlen($json) > 1_048_576) {
            throw new InvalidArgumentException('HTTP client JSON exceeds 1 MiB');
        }
        return $json;
    }

    private static function validateParameters(array $data, int $depth = 0): void
    {
        if ($depth >= 16) {
            throw new InvalidArgumentException('HTTP client parameter nesting exceeds 16 levels');
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && (!preg_match('//u', $key) || preg_match('/[\x00-\x1f\x7f]/', $key))) {
                throw new InvalidArgumentException('Invalid HTTP client parameter name');
            }
            if (is_array($value)) {
                self::validateParameters($value, $depth + 1);
                continue;
            }
            if (
                ($value !== null && !is_scalar($value))
                || (is_float($value) && !is_finite($value))
                || (is_string($value) && !preg_match('//u', $value))
            ) {
                throw new InvalidArgumentException('Invalid HTTP client parameter value');
            }
        }
    }
}
