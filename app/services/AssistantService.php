<?php
/**
 * AI Business Assistant (spec #13): a chat interface that has access to
 * the business's knowledge base and live data, so owners can ask things
 * like "Summarize today's leads" or "Which leads should I call?".
 */
final class AssistantService
{
    public function startConversation(int $businessId, int $userId, ?string $title = null): array
    {
        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO ai_conversations (uuid, business_id, user_id, title, context_type, created_at)
             VALUES (?, ?, ?, ?, 'assistant', NOW())",
            [$uuid, $businessId, $userId, $title]
        );
        $id = (int) Database::lastInsertId();
        return Database::fetchOne("SELECT * FROM ai_conversations WHERE id = ?", [$id]);
    }

    public function listConversations(int $businessId, int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM ai_conversations WHERE business_id = ? AND user_id = ? AND context_type = 'assistant' AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 30",
            [$businessId, $userId]
        );
    }

    public function getMessages(int $conversationId): array
    {
        return Database::fetchAll(
            "SELECT role, content, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC",
            [$conversationId]
        );
    }

    public function sendMessage(int $businessId, int $userId, int $conversationId, string $message): string
    {
        $conversation = Database::fetchOne(
            "SELECT * FROM ai_conversations WHERE id = ? AND business_id = ? AND user_id = ?",
            [$conversationId, $businessId, $userId]
        );
        if ($conversation === null) {
            Response::notFound('Conversation not found.');
        }

        $systemPrompt = $this->buildSystemPrompt($businessId);

        $ai = new AIService();
        try {
            $reply = $ai->chat($businessId, $userId, $conversationId, $message, ['system_prompt' => $systemPrompt]);
        } catch (\Throwable $e) {
            Response::error('AI assistant failed to respond: ' . $e->getMessage(), [], 502);
        }

        // Auto-title new conversations from the first message
        if (empty($conversation['title'])) {
            $title = mb_substr(trim($message), 0, 60);
            Database::query("UPDATE ai_conversations SET title = ? WHERE id = ?", [$title, $conversationId]);
        }

        return $reply;
    }

    private function buildSystemPrompt(int $businessId): string
    {
        $business = Database::fetchOne(
            "SELECT name, industry, about, target_customers, unique_selling_points, currency FROM businesses WHERE id = ?",
            [$businessId]
        );

        $todayLeadsCount = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM leads WHERE business_id = ? AND deleted_at IS NULL AND DATE(created_at) = CURDATE()",
            [$businessId]
        )['c'] ?? 0);

        $pendingFollowups = (int) (Database::fetchOne(
            "SELECT COUNT(*) c FROM followups WHERE business_id = ? AND status = 'pending' AND scheduled_at <= NOW()",
            [$businessId]
        )['c'] ?? 0);

        $faqs = Database::fetchAll("SELECT question, answer FROM business_faqs WHERE business_id = ? AND is_active = 1 LIMIT 10", [$businessId]);
        $faqText = implode("\n", array_map(fn ($f) => "Q: {$f['question']} A: {$f['answer']}", $faqs));

        return "You are the AI business assistant for \"{$business['name']}\", a business in the {$business['industry']} industry.\n" .
            "About: " . ($business['about'] ?? 'N/A') . "\n" .
            "Target customers: " . ($business['target_customers'] ?? 'N/A') . "\n" .
            "Unique selling points: " . ($business['unique_selling_points'] ?? 'N/A') . "\n" .
            "Currency: {$business['currency']}\n" .
            "Live context: {$todayLeadsCount} new leads today, {$pendingFollowups} follow-ups currently due.\n" .
            "Business FAQs:\n{$faqText}\n\n" .
            "Help the business owner with tasks like qualifying leads, drafting replies, summarizing activity, writing proposals/quotes/emails, and analyzing sales. Be concise and actionable.";
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
