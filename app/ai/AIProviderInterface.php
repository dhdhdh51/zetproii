<?php
/**
 * Contract every AI provider adapter must implement. This is the
 * abstraction that lets AIService swap between OpenAI, Gemini,
 * Anthropic, or any custom OpenAI-compatible endpoint without the rest
 * of the application knowing which provider actually served a request.
 */
interface AIProviderInterface
{
    /**
     * Sends a chat-style completion request.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{content:string, prompt_tokens:int, completion_tokens:int, total_tokens:int}
     * @throws RuntimeException on any provider-level failure (network, auth, rate limit, etc.)
     */
    public function complete(array $messages, string $model, float $temperature, int $maxTokens, int $timeoutSeconds): array;
}
