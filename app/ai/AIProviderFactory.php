<?php
/**
 * Builds a concrete AIProviderInterface adapter for a given ai_providers
 * DB row, decrypting its stored API key. Custom OpenAI-compatible
 * providers reuse OpenAIProvider since they share the same request shape.
 */
final class AIProviderFactory
{
    public static function make(array $providerRow): AIProviderInterface
    {
        $apiKey = $providerRow['api_key_encrypted'] !== null
            ? (Security::decrypt($providerRow['api_key_encrypted']) ?? '')
            : '';
        $baseUrl = (string) $providerRow['base_url'];

        return match ($providerRow['slug']) {
            'openai', 'custom' => new OpenAIProvider($apiKey, $baseUrl),
            'gemini' => new GeminiProvider($apiKey, $baseUrl),
            'anthropic' => new AnthropicProvider($apiKey, $baseUrl),
            default => throw new RuntimeException("Unknown AI provider slug: {$providerRow['slug']}"),
        };
    }
}
