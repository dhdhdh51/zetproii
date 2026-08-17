<?php
/**
 * Consistent JSON API response envelope:
 * { "success": bool, "data": mixed, "message": string, "errors": array }
 */
final class Response
{
    public static function json(bool $success, mixed $data = null, string $message = '', array $errors = [], int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode([
            'success' => $success,
            'data'    => $data,
            'message' => $message,
            'errors'  => $errors,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): never
    {
        self::json(true, $data, $message, [], $statusCode);
    }

    public static function error(string $message = 'Error', array $errors = [], int $statusCode = 400): never
    {
        self::json(false, null, $message, $errors, $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::json(false, null, $message, [], 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::json(false, null, $message, [], 403);
    }

    public static function notFound(string $message = 'Not found'): never
    {
        self::json(false, null, $message, [], 404);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): never
    {
        self::json(false, null, $message, $errors, 422);
    }

    public static function rateLimited(string $message = 'Too many requests'): never
    {
        self::json(false, null, $message, [], 429);
    }

    public static function serverError(string $message = 'Internal server error'): never
    {
        self::json(false, null, $message, [], 500);
    }
}
