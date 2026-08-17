<?php
/**
 * Website chatbot (spec #15): manages per-business chatbot config and
 * powers the public-facing widget conversation, including lead capture.
 */
final class ChatbotService
{
    public function getOrCreateConfig(int $businessId): array
    {
        $config = Database::fetchOne("SELECT * FROM chatbot_configs WHERE business_id = ?", [$businessId]);
        if ($config !== null) {
            return $config;
        }

        $widgetKey = 'cbw_' . bin2hex(random_bytes(20));
        Database::query(
            "INSERT INTO chatbot_configs (business_id, widget_key, bot_name, welcome_message, primary_color, tone, lead_collection_enabled, required_fields, is_active, created_at)
             VALUES (?, ?, 'AI Assistant', 'Hi! How can I help you today?', '#4f46e5', 'friendly', 1, ?, 1, NOW())",
            [$businessId, $widgetKey, json_encode(['name', 'email'])]
        );
        return Database::fetchOne("SELECT * FROM chatbot_configs WHERE business_id = ?", [$businessId]);
    }

    public function updateConfig(int $businessId, array $data): array
    {
        $this->getOrCreateConfig($businessId); // ensure exists

        $allowed = ['bot_name', 'welcome_message', 'primary_color', 'tone', 'human_handoff_enabled', 'handoff_email', 'lead_collection_enabled', 'is_active'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = is_bool($data[$col]) ? (int) $data[$col] : Security::cleanString((string) $data[$col]);
            }
        }
        if (isset($data['required_fields']) && is_array($data['required_fields'])) {
            $sets[] = "required_fields = ?";
            $params[] = json_encode($data['required_fields']);
        }
        if (!empty($sets)) {
            $params[] = $businessId;
            Database::query("UPDATE chatbot_configs SET " . implode(', ', $sets) . " WHERE business_id = ?", $params);
        }

        return Database::fetchOne("SELECT * FROM chatbot_configs WHERE business_id = ?", [$businessId]);
    }

    public function configByWidgetKey(string $widgetKey): ?array
    {
        return Database::fetchOne(
            "SELECT c.*, b.name AS business_name FROM chatbot_configs c JOIN businesses b ON b.id = c.business_id
             WHERE c.widget_key = ? AND c.is_active = 1 AND b.status = 'active' AND b.deleted_at IS NULL",
            [$widgetKey]
        );
    }

    public function startSession(string $widgetKey, ?string $sourceUrl): array
    {
        $config = $this->configByWidgetKey($widgetKey);
        if ($config === null) {
            Response::notFound('Chatbot not found or inactive.');
        }

        $uuid = $this->uuid4();
        Database::query(
            "INSERT INTO chat_sessions (uuid, business_id, visitor_ip, source_url, status, created_at)
             VALUES (?, ?, ?, ?, 'open', NOW())",
            [$uuid, $config['business_id'], Security::clientIp(), $sourceUrl]
        );
        $sessionId = (int) Database::lastInsertId();

        return [
            'session_uuid' => $uuid,
            'session_id' => $sessionId,
            'bot_name' => $config['bot_name'],
            'welcome_message' => $config['welcome_message'],
            'primary_color' => $config['primary_color'],
            'lead_collection_enabled' => (bool) $config['lead_collection_enabled'],
            'required_fields' => json_decode($config['required_fields'] ?? '[]', true) ?: [],
        ];
    }

    public function sendMessage(string $widgetKey, string $sessionUuid, string $message): string
    {
        $config = $this->configByWidgetKey($widgetKey);
        if ($config === null) {
            Response::notFound('Chatbot not found or inactive.');
        }

        $session = Database::fetchOne("SELECT * FROM chat_sessions WHERE uuid = ? AND business_id = ?", [$sessionUuid, $config['business_id']]);
        if ($session === null) {
            Response::notFound('Chat session not found.');
        }

        $conversationId = $session['conversation_id'];
        if ($conversationId === null) {
            $uuid = $this->uuid4();
            Database::query(
                "INSERT INTO ai_conversations (uuid, business_id, context_type, created_at) VALUES (?, ?, 'chatbot', NOW())",
                [$uuid, $config['business_id']]
            );
            $conversationId = (int) Database::lastInsertId();
            Database::query("UPDATE chat_sessions SET conversation_id = ? WHERE id = ?", [$conversationId, $session['id']]);
        }

        $systemPrompt = $this->buildChatbotSystemPrompt($config);

        $ai = new AIService();
        try {
            $reply = $ai->chat($config['business_id'], null, (int) $conversationId, $message, ['system_prompt' => $systemPrompt]);
        } catch (\Throwable $e) {
            return "I'm sorry, I'm having trouble responding right now. Please try again in a moment, or leave your contact details and we'll get back to you.";
        }

        return $reply;
    }

    public function captureLead(string $widgetKey, string $sessionUuid, array $fields): array
    {
        $config = $this->configByWidgetKey($widgetKey);
        if ($config === null) {
            Response::notFound('Chatbot not found or inactive.');
        }
        $session = Database::fetchOne("SELECT * FROM chat_sessions WHERE uuid = ? AND business_id = ?", [$sessionUuid, $config['business_id']]);
        if ($session === null) {
            Response::notFound('Chat session not found.');
        }

        $businessId = (int) $config['business_id'];

        $leadService = new LeadService();
        $lead = $leadService->create($businessId, null, [
            'name' => $fields['name'] ?? 'Website Visitor',
            'email' => $fields['email'] ?? null,
            'phone' => $fields['phone'] ?? null,
            'company' => $fields['company'] ?? null,
            'requirement' => $fields['requirement'] ?? null,
            'budget' => $fields['budget'] ?? null,
            'location' => $fields['location'] ?? null,
            'source_id' => $this->chatbotSourceId($businessId),
        ]);

        Database::query(
            "INSERT INTO chat_leads (chat_session_id, lead_id, name, email, phone, company, requirement, budget, location, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $session['id'], $lead['id'],
                $fields['name'] ?? null, $fields['email'] ?? null, $fields['phone'] ?? null,
                $fields['company'] ?? null, $fields['requirement'] ?? null, $fields['budget'] ?? null, $fields['location'] ?? null,
            ]
        );

        AutomationService::trigger($businessId, 'chat.lead_created', ['lead_id' => $lead['id']]);

        return $lead;
    }

    private function chatbotSourceId(int $businessId): ?int
    {
        $row = Database::fetchOne(
            "SELECT id FROM lead_sources WHERE (business_id = ? OR business_id IS NULL) AND name = 'AI Chatbot' ORDER BY business_id IS NULL ASC LIMIT 1",
            [$businessId]
        );
        return $row['id'] ?? null;
    }

    private function buildChatbotSystemPrompt(array $config): string
    {
        $business = Database::fetchOne("SELECT name, about FROM businesses WHERE id = ?", [$config['business_id']]);
        $faqs = Database::fetchAll("SELECT question, answer FROM business_faqs WHERE business_id = ? AND is_active = 1", [$config['business_id']]);
        $faqText = implode("\n", array_map(fn ($f) => "Q: {$f['question']} A: {$f['answer']}", $faqs));

        $tone = $config['tone'] ?: 'friendly';

        return "You are {$config['bot_name']}, the {$tone} AI chat assistant for \"{$business['name']}\" on their website.\n" .
            "About the business: " . ($business['about'] ?? 'N/A') . "\n" .
            "FAQs:\n{$faqText}\n\n" .
            "Answer visitor questions helpfully and briefly. If you don't know something specific, say so honestly rather than making it up. " .
            "If the visitor shows buying interest, politely ask for their name and contact details so the team can follow up.";
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
