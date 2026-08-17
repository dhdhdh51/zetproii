<?php
/**
 * OpenAI (and any OpenAI-compatible API, since CustomProvider reuses
 * this same request/response shape) chat completions adapter.
 */
final class OpenAIProvider implements AIProviderInterface
{
    public function __construct(private string $apiKey, private string $baseUrl)
    {
    }

    public function complete(array $messages, string $model, float $temperature, int $maxTokens, int $timeoutSeconds): array
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $result = HttpClient::postJson($url, [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ], [
            'Authorization: Bearer ' . $this->apiKey,
        ], $timeoutSeconds);

        if ($result['error'] !== null) {
            throw new RuntimeException("OpenAI request failed: {$result['error']}");
        }

        $data = json_decode($result['body'], true);

        if ($result['status'] >= 400 || !is_array($data)) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $result['status']);
            throw new RuntimeException("OpenAI API error: {$msg}");
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => (string) $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }
}
