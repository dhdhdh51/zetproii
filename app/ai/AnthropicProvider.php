<?php
/**
 * Anthropic Claude adapter (Messages API).
 */
final class AnthropicProvider implements AIProviderInterface
{
    public function __construct(private string $apiKey, private string $baseUrl)
    {
    }

    public function complete(array $messages, string $model, float $temperature, int $maxTokens, int $timeoutSeconds): array
    {
        $url = rtrim($this->baseUrl, '/') . '/messages';

        $systemPrompt = '';
        $chatMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemPrompt .= $m['content'] . "\n";
                continue;
            }
            $chatMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $chatMessages,
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = trim($systemPrompt);
        }

        $result = HttpClient::postJson($url, $payload, [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ], $timeoutSeconds);

        if ($result['error'] !== null) {
            throw new RuntimeException("Anthropic request failed: {$result['error']}");
        }

        $data = json_decode($result['body'], true);

        if ($result['status'] >= 400 || !is_array($data)) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $result['status']);
            throw new RuntimeException("Anthropic API error: {$msg}");
        }

        $content = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];
        $promptTokens = (int) ($usage['input_tokens'] ?? 0);
        $completionTokens = (int) ($usage['output_tokens'] ?? 0);

        return [
            'content' => (string) $content,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
        ];
    }
}
