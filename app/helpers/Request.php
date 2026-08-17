<?php
/**
 * Wraps incoming request data (JSON body, form data, query string) into a
 * single, consistent accessor with basic sanitization.
 */
final class Request
{
    private array $data;
    private array $query;
    private array $files;
    public string $method;
    public string $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $this->query = $_GET ?? [];
        $this->files = $_FILES ?? [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = [];

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } else {
            $body = $_POST ?? [];
        }

        $this->data = array_merge($this->query, $body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $val = $this->input($key, $default);
        return is_string($val) ? trim($val) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $val = $this->input($key, $default);
        return is_numeric($val) ? (int) $val : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $val = $this->input($key, $default);
        return is_numeric($val) ? (float) $val : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->input($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        return in_array($val, [1, '1', 'true', 'on', 'yes'], true);
    }

    public function array(string $key, array $default = []): array
    {
        $val = $this->input($key, $default);
        return is_array($val) ? $val : $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
