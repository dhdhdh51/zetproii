<?php
/**
 * Admin-facing AI provider/model configuration (spec #11/#33). API keys
 * are encrypted before storage and are NEVER returned to the frontend -
 * only a masked indicator of whether a key is set.
 */
final class AdminAIService
{
    public function listProviders(): array
    {
        $providers = Database::fetchAll("SELECT * FROM ai_providers ORDER BY priority ASC");
        foreach ($providers as &$p) {
            $p['has_api_key'] = !empty($p['api_key_encrypted']);
            unset($p['api_key_encrypted']);
            $p['models'] = Database::fetchAll("SELECT * FROM ai_models WHERE provider_id = ? ORDER BY is_default DESC, name ASC", [$p['id']]);
        }
        return $providers;
    }

    public function updateProvider(int $providerId, array $data, int $adminUserId): array
    {
        $provider = Database::fetchOne("SELECT * FROM ai_providers WHERE id = ?", [$providerId]);
        if ($provider === null) {
            Response::notFound('AI provider not found.');
        }

        $sets = [];
        $params = [];

        foreach (['base_url', 'priority', 'timeout_seconds'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (array_key_exists('is_enabled', $data)) {
            $sets[] = "is_enabled = ?";
            $params[] = $data['is_enabled'] ? 1 : 0;
        }
        if (!empty($data['api_key'])) {
            $sets[] = "api_key_encrypted = ?";
            $params[] = Security::encrypt($data['api_key']);
        }

        if (!empty($sets)) {
            $params[] = $providerId;
            Database::query("UPDATE ai_providers SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }

        AuditLogger::admin($adminUserId, 'ai_provider_updated', "Updated provider {$provider['slug']}", [
            'provider_id' => $providerId,
            'fields' => array_keys($data),
        ]);

        return Database::fetchOne("SELECT id, name, slug, base_url, is_enabled, priority, timeout_seconds FROM ai_providers WHERE id = ?", [$providerId]);
    }

    public function upsertModel(array $data, int $adminUserId): array
    {
        $providerId = (int) $data['provider_id'];
        $provider = Database::fetchOne("SELECT id FROM ai_providers WHERE id = ?", [$providerId]);
        if ($provider === null) {
            Response::validationError(['provider_id' => ['Invalid provider.']]);
        }

        if (!empty($data['id'])) {
            Database::query(
                "UPDATE ai_models SET name = ?, display_name = ?, max_tokens = ?, temperature = ?,
                        input_cost_per_1k = ?, output_cost_per_1k = ?, supports_vision = ?, is_enabled = ?,
                        is_default = ?, is_fallback = ? WHERE id = ? AND provider_id = ?",
                [
                    $data['name'], $data['display_name'], $data['max_tokens'], $data['temperature'],
                    $data['input_cost_per_1k'] ?? 0, $data['output_cost_per_1k'] ?? 0,
                    !empty($data['supports_vision']) ? 1 : 0, !empty($data['is_enabled']) ? 1 : 0,
                    !empty($data['is_default']) ? 1 : 0, !empty($data['is_fallback']) ? 1 : 0,
                    $data['id'], $providerId,
                ]
            );
            $modelId = (int) $data['id'];
        } else {
            Database::query(
                "INSERT INTO ai_models (provider_id, name, display_name, max_tokens, temperature, input_cost_per_1k,
                        output_cost_per_1k, supports_vision, is_enabled, is_default, is_fallback, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $providerId, $data['name'], $data['display_name'], $data['max_tokens'] ?? 4096, $data['temperature'] ?? 0.7,
                    $data['input_cost_per_1k'] ?? 0, $data['output_cost_per_1k'] ?? 0,
                    !empty($data['supports_vision']) ? 1 : 0, !empty($data['is_enabled']) ? 1 : 1,
                    !empty($data['is_default']) ? 1 : 0, !empty($data['is_fallback']) ? 1 : 0,
                ]
            );
            $modelId = (int) Database::lastInsertId();
        }

        // Only one default/fallback model per provider makes sense.
        if (!empty($data['is_default'])) {
            Database::query("UPDATE ai_models SET is_default = 0 WHERE provider_id = ? AND id != ?", [$providerId, $modelId]);
        }
        if (!empty($data['is_fallback'])) {
            Database::query("UPDATE ai_models SET is_fallback = 0 WHERE provider_id = ? AND id != ?", [$providerId, $modelId]);
        }

        AuditLogger::admin($adminUserId, 'ai_model_saved', '', ['model_id' => $modelId]);

        return Database::fetchOne("SELECT * FROM ai_models WHERE id = ?", [$modelId]);
    }

    public function deleteModel(int $modelId, int $adminUserId): void
    {
        Database::query("DELETE FROM ai_models WHERE id = ?", [$modelId]);
        AuditLogger::admin($adminUserId, 'ai_model_deleted', '', ['model_id' => $modelId]);
    }

    public function usageSummary(int $days = 30): array
    {
        $byFeature = Database::fetchAll(
            "SELECT feature, COUNT(*) requests, SUM(total_tokens) tokens, SUM(estimated_cost) cost
             FROM ai_usage WHERE created_at >= (NOW() - INTERVAL ? DAY) GROUP BY feature ORDER BY requests DESC",
            [$days]
        );
        $byProvider = Database::fetchAll(
            "SELECT p.name, COUNT(*) requests, SUM(u.total_tokens) tokens, SUM(u.estimated_cost) cost,
                    SUM(CASE WHEN u.status = 'failed' THEN 1 ELSE 0 END) failures
             FROM ai_usage u LEFT JOIN ai_providers p ON p.id = u.provider_id
             WHERE u.created_at >= (NOW() - INTERVAL ? DAY) GROUP BY p.id ORDER BY requests DESC",
            [$days]
        );
        $totals = Database::fetchOne(
            "SELECT COUNT(*) requests, SUM(total_tokens) tokens, SUM(estimated_cost) cost
             FROM ai_usage WHERE created_at >= (NOW() - INTERVAL ? DAY)",
            [$days]
        );
        return ['by_feature' => $byFeature, 'by_provider' => $byProvider, 'totals' => $totals];
    }
}
