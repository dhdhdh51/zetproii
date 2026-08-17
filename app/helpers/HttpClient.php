<?php
/**
 * Minimal dependency-free HTTP client (cURL wrapper) used by AI provider
 * adapters and outbound webhooks. No Guzzle/Composer package required.
 */
final class HttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string, error:?string}
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 30): array
    {
        return self::request('POST', $url, json_encode($payload), array_merge([
            'Content-Type: application/json',
        ], $headers), $timeoutSeconds);
    }

    public static function get(string $url, array $headers = [], int $timeoutSeconds = 30): array
    {
        return self::request('GET', $url, null, $headers, $timeoutSeconds);
    }

    private static function request(string $method, string $url, ?string $body, array $headers, int $timeoutSeconds): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch) ?: null;
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $response !== false ? (string) $response : '',
            'error' => $response === false ? $error : null,
        ];
    }
}
