<?php
/**
 * Google Gemini adapter. Translates OpenAI-style {role, content} messages
 * into Gemini's {role, parts:[{text}]} format and back.
 */
final class GeminiProvider implements AIProviderInterface
{
    public function __construct(private string $apiKey, private string $baseUrl)
    {
    }

    public function complete(array $messages, string $model, float $temperature, int $maxTokens, int $timeoutSeconds): array
    {
        $url = rtrim($this->baseUrl, '/') . "/models/{$model}:generateContent?key=" . urlencode($this->apiKey);

        $systemInstruction = null;
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $m['content']]]];
                continue;
            }
            $contents[] = [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];
        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $result = HttpClient::postJson($url, $payload, [], $timeoutSeconds);

        if ($result['error'] !== null) {
            throw new RuntimeException("Gemini request failed: {$result['error']}");
        }

        $data = json_decode($result['body'], true);

        if ($result['status'] >= 400 || !is_array($data)) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $result['status']);
            throw new RuntimeException("Gemini API error: {$msg}");
        }

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? [];

        return [
            'content' => (string) $content,
            'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
        ];
    }
}
