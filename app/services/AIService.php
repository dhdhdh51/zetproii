<?php
/**
 * Unified AI service used by every AI-powered feature in the platform.
 * Selects a model based on admin configuration (default/fallback,
 * priority-ordered providers), enforces per-business usage limits before
 * calling out, and logs every request to ai_usage regardless of outcome.
 *
 * Fallback chain: tries providers in priority order (ai_providers.priority
 * ascending) until one succeeds, using each provider's default (or
 * fallback-flagged) enabled model. If all fail, throws a controlled
 * RuntimeException that callers turn into a clean user-facing error.
 */
final class AIService
{
    /**
     * @param array<int,array{role:string,content:string}> $messages
     */
    public function generateText(?int $businessId, ?int $userId, string $feature, array $messages, array $options = []): string
    {
        if ($businessId !== null) {
            $limit = UsageLimitService::check($businessId, 'ai_credits');
            if (!$limit['allowed']) {
                throw new RuntimeException('Your AI credit limit for this billing period has been reached. Please upgrade your plan to continue.');
            }
        }

        $providers = $this->enabledProvidersInOrder();
        if (empty($providers)) {
            throw new RuntimeException('No AI provider is currently configured. Please contact your administrator.');
        }

        $temperature = $options['temperature'] ?? null;
        $maxTokens = $options['max_tokens'] ?? null;

        $lastError = null;
        $usedFallback = false;

        foreach ($providers as $index => $providerRow) {
            $model = $this->modelForProvider($providerRow['id'], $index > 0);
            if ($model === null) {
                continue;
            }

            $startedAt = microtime(true);
            try {
                $adapter = AIProviderFactory::make($providerRow);
                $result = $adapter->complete(
                    $messages,
                    $model['name'],
                    (float) ($temperature ?? $model['temperature']),
                    (int) ($maxTokens ?? $model['max_tokens']),
                    (int) $providerRow['timeout_seconds']
                );

                $responseTimeMs = (int) ((microtime(true) - $startedAt) * 1000);
                $cost = $this->estimateCost($model, $result['prompt_tokens'], $result['completion_tokens']);

                $this->logUsage($businessId, $userId, $providerRow['id'], $model['id'], $feature, $result, $cost, $usedFallback ? 'fallback_used' : 'success', null, $responseTimeMs);

                if ($businessId !== null) {
                    UsageLimitService::increment($businessId, 'ai_credits', 1);
                }

                return $result['content'];
            } catch (\Throwable $e) {
                $responseTimeMs = (int) ((microtime(true) - $startedAt) * 1000);
                $lastError = $e->getMessage();
                Logger::ai("AI provider [{$providerRow['slug']}] failed: {$lastError}", ['feature' => $feature]);
                $this->logUsage($businessId, $userId, $providerRow['id'], $model['id'] ?? null, $feature, null, 0, 'failed', $lastError, $responseTimeMs);
                $usedFallback = true;
                continue;
            }
        }

        throw new RuntimeException('All configured AI providers failed to respond. Please try again shortly. Last error: ' . ($lastError ?? 'unknown'));
    }

    public function chat(?int $businessId, ?int $userId, int $conversationId, string $userMessage, array $systemContext = []): string
    {
        $history = Database::fetchAll(
            "SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 20",
            [$conversationId]
        );

        $messages = [];
        if (!empty($systemContext['system_prompt'])) {
            $messages[] = ['role' => 'system', 'content' => $systemContext['system_prompt']];
        }
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        Database::query(
            "INSERT INTO ai_messages (conversation_id, role, content, created_at) VALUES (?, 'user', ?, NOW())",
            [$conversationId, $userMessage]
        );

        $reply = $this->generateText($businessId, $userId, 'chat', $messages);

        Database::query(
            "INSERT INTO ai_messages (conversation_id, role, content, created_at) VALUES (?, 'assistant', ?, NOW())",
            [$conversationId, $reply]
        );
        Database::query("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?", [$conversationId]);

        return $reply;
    }

    public function summarize(?int $businessId, ?int $userId, string $text): string
    {
        return $this->generateText($businessId, $userId, 'summarize', [
            ['role' => 'system', 'content' => 'You are a concise business analyst. Summarize the given text in 3-5 bullet points.'],
            ['role' => 'user', 'content' => $text],
        ]);
    }

    public function classify(?int $businessId, ?int $userId, string $text, array $categories): string
    {
        $catList = implode(', ', $categories);
        return trim($this->generateText($businessId, $userId, 'classify', [
            ['role' => 'system', 'content' => "Classify the input into exactly one of these categories: {$catList}. Respond with only the category name."],
            ['role' => 'user', 'content' => $text],
        ]));
    }

    public function analyzeText(?int $businessId, ?int $userId, string $text, string $instruction): string
    {
        return $this->generateText($businessId, $userId, 'analyze', [
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user', 'content' => $text],
        ]);
    }

    /**
     * Generates structured JSON data. Attempts to decode the model's
     * response as JSON; throws if the model does not return valid JSON
     * even after being explicitly instructed to.
     */
    public function generateStructuredData(?int $businessId, ?int $userId, string $feature, string $prompt, array $schemaHint): array
    {
        $schemaDesc = json_encode($schemaHint);
        $raw = $this->generateText($businessId, $userId, $feature, [
            ['role' => 'system', 'content' => "You must respond with ONLY valid JSON matching this shape (no markdown, no explanation): {$schemaDesc}"],
            ['role' => 'user', 'content' => $prompt],
        ]);

        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(json)?/', '', $cleaned);
        $cleaned = preg_replace('/```$/', '', $cleaned);
        $decoded = json_decode(trim($cleaned), true);

        if (!is_array($decoded)) {
            throw new RuntimeException('AI did not return valid structured data. Please try again.');
        }

        return $decoded;
    }

    // ---------------- Feature-specific helpers (spec section 12) ----------------

    public function generateEmail(?int $businessId, ?int $userId, string $purpose, array $context): string
    {
        $ctx = json_encode($context);
        return $this->generateText($businessId, $userId, 'generate_email', [
            ['role' => 'system', 'content' => 'You write professional, warm business emails. Return only the email body.'],
            ['role' => 'user', 'content' => "Write an email for: {$purpose}. Context: {$ctx}"],
        ]);
    }

    public function generateReviewReply(?int $businessId, ?int $userId, string $reviewText, ?int $rating, string $tone = 'professional'): string
    {
        return $this->generateText($businessId, $userId, 'review_reply', [
            ['role' => 'system', 'content' => "You write {$tone} replies to customer reviews on behalf of a business. Be genuine, specific and never defensive. Never fabricate facts about the business."],
            ['role' => 'user', 'content' => "Customer review (rating: " . ($rating ?? 'n/a') . "/5): {$reviewText}"],
        ]);
    }

    public function generateSocialPost(?int $businessId, ?int $userId, array $params): string
    {
        return $this->generateText($businessId, $userId, 'social_post', [
            ['role' => 'system', 'content' => "You are a social media copywriter for platform: {$params['platform']}. Tone: {$params['tone']}. Audience: {$params['audience']}. Language: {$params['language']}. Include a call-to-action: {$params['cta']}."],
            ['role' => 'user', 'content' => "Write a post about: {$params['topic']}. Keywords to include if natural: {$params['keywords']}"],
        ]);
    }

    // ---------------- Internals ----------------

    /** @return array<int,array<string,mixed>> providers ordered by priority ascending, enabled only */
    private function enabledProvidersInOrder(): array
    {
        return Database::fetchAll(
            "SELECT * FROM ai_providers WHERE is_enabled = 1 ORDER BY priority ASC"
        );
    }

    private function modelForProvider(int $providerId, bool $preferFallbackFlag): ?array
    {
        $order = $preferFallbackFlag ? "is_fallback DESC, is_default DESC" : "is_default DESC, is_fallback DESC";
        return Database::fetchOne(
            "SELECT * FROM ai_models WHERE provider_id = ? AND is_enabled = 1 ORDER BY {$order} LIMIT 1",
            [$providerId]
        );
    }

    private function estimateCost(array $model, int $promptTokens, int $completionTokens): float
    {
        $inputCost = ($promptTokens / 1000) * (float) $model['input_cost_per_1k'];
        $outputCost = ($completionTokens / 1000) * (float) $model['output_cost_per_1k'];
        return round($inputCost + $outputCost, 6);
    }

    private function logUsage(?int $businessId, ?int $userId, ?int $providerId, ?int $modelId, string $feature, ?array $result, float $cost, string $status, ?string $error, int $responseTimeMs): void
    {
        try {
            Database::query(
                "INSERT INTO ai_usage (business_id, user_id, provider_id, model_id, feature, prompt_tokens, completion_tokens,
                                       total_tokens, estimated_cost, status, error_message, response_time_ms, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $businessId, $userId, $providerId, $modelId, $feature,
                    $result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, $result['total_tokens'] ?? 0,
                    $cost, $status, $error, $responseTimeMs,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('Failed to log AI usage: ' . $e->getMessage());
        }
    }
}
