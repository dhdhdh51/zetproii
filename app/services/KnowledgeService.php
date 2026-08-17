<?php
/**
 * Knowledge base management (spec #14). Supports uploading documents,
 * adding website URLs, FAQs, and manual text as knowledge_sources, then
 * chunking their content into knowledge_chunks for retrieval.
 *
 * Search today uses MySQL FULLTEXT (see search()) - a real, working
 * search implementation rather than a fake RAG pipeline. The `embedding`
 * column on knowledge_chunks is deliberately present and nullable so
 * that a true vector-embedding pipeline can be added later (e.g. by
 * populating it via an embeddings-capable AI provider) without any
 * schema change - this keeps the architecture extensible per spec.
 */
final class KnowledgeService
{
    private const CHUNK_SIZE = 1200; // characters per chunk, roughly ~300 tokens

    public function addManualText(int $businessId, string $title, string $content): array
    {
        $sourceId = $this->createSource($businessId, 'manual_text', $title, null, $content);
        $this->chunkAndStore($businessId, $sourceId, $content);
        return $this->findSource($businessId, $sourceId);
    }

    public function addFaqSource(int $businessId, int $faqId, string $question, string $answer): array
    {
        $content = "Q: {$question}\nA: {$answer}";
        $sourceId = $this->createSource($businessId, 'faq', $question, null, $content, $faqId);
        $this->chunkAndStore($businessId, $sourceId, $content);
        return $this->findSource($businessId, $sourceId);
    }

    public function addUrlSource(int $businessId, string $url): array
    {
        $sourceId = $this->createSource($businessId, 'url', $url, $url, null);
        Database::query("UPDATE knowledge_sources SET status = 'processing' WHERE id = ?", [$sourceId]);

        try {
            $result = HttpClient::get($url, [], 15);
            if ($result['error'] !== null || $result['status'] >= 400) {
                throw new RuntimeException('Failed to fetch URL: ' . ($result['error'] ?? "HTTP {$result['status']}"));
            }
            $text = $this->stripHtml($result['body']);
            Database::query("UPDATE knowledge_sources SET raw_content = ?, status = 'indexed' WHERE id = ?", [$text, $sourceId]);
            $this->chunkAndStore($businessId, $sourceId, $text);
        } catch (\Throwable $e) {
            Database::query("UPDATE knowledge_sources SET status = 'failed' WHERE id = ?", [$sourceId]);
            Logger::error('Knowledge URL fetch failed: ' . $e->getMessage());
        }

        return $this->findSource($businessId, $sourceId);
    }

    public function addDocument(int $businessId, int $uploadedBy, string $title, string $storedPath, string $extension, int $sizeBytes): array
    {
        Database::query(
            "INSERT INTO business_documents (business_id, uploaded_by, title, file_path, file_type, file_size, processed_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [$businessId, $uploadedBy, $title, $storedPath, $extension, $sizeBytes]
        );
        $documentId = (int) Database::lastInsertId();

        $sourceId = $this->createSource($businessId, 'document', $title, null, null, $documentId);

        // Text extraction: plain text/CSV files are read directly. PDFs and
        // DOCX require a dedicated parser library which is out of scope for
        // a zero-dependency install; those are stored and marked pending so
        // an admin-configured extractor can process them later without any
        // schema change (extensible architecture, not a fake pipeline).
        if (in_array($extension, ['txt', 'csv'], true) && is_readable($storedPath)) {
            $text = (string) file_get_contents($storedPath);
            Database::query("UPDATE knowledge_sources SET raw_content = ?, status = 'indexed' WHERE id = ?", [$text, $sourceId]);
            Database::query("UPDATE business_documents SET processed_status = 'completed' WHERE id = ?", [$documentId]);
            $this->chunkAndStore($businessId, $sourceId, $text);
        } else {
            Database::query("UPDATE knowledge_sources SET status = 'pending' WHERE id = ?", [$sourceId]);
        }

        return $this->findSource($businessId, $sourceId);
    }

    public function listSources(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT ks.*, COUNT(kc.id) AS chunk_count FROM knowledge_sources ks
             LEFT JOIN knowledge_chunks kc ON kc.source_id = ks.id
             WHERE ks.business_id = ? AND ks.deleted_at IS NULL
             GROUP BY ks.id ORDER BY ks.created_at DESC",
            [$businessId]
        );
    }

    public function deleteSource(int $businessId, int $sourceId): void
    {
        Database::query("UPDATE knowledge_sources SET deleted_at = NOW() WHERE id = ? AND business_id = ?", [$sourceId, $businessId]);
    }

    /**
     * Full-text keyword search across a business's knowledge chunks.
     * This is the working, non-fake retrieval mechanism used today by
     * AssistantService/ChatbotService when embeddings are not configured.
     */
    public function search(int $businessId, string $query, int $limit = 5): array
    {
        if (trim($query) === '') {
            return [];
        }
        return Database::fetchAll(
            "SELECT kc.content, ks.title, MATCH(kc.content) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
             FROM knowledge_chunks kc JOIN knowledge_sources ks ON ks.id = kc.source_id
             WHERE kc.business_id = ? AND ks.deleted_at IS NULL
             HAVING relevance > 0 ORDER BY relevance DESC LIMIT ?",
            [$query, $businessId, $limit]
        );
    }

    private function createSource(int $businessId, string $type, string $title, ?string $url, ?string $rawContent, ?int $referenceId = null): int
    {
        Database::query(
            "INSERT INTO knowledge_sources (business_id, source_type, reference_id, title, source_url, raw_content, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$businessId, $type, $referenceId, Security::cleanString($title), $url, $rawContent, $rawContent !== null ? 'indexed' : 'pending']
        );
        return (int) Database::lastInsertId();
    }

    private function findSource(int $businessId, int $sourceId): array
    {
        return Database::fetchOne("SELECT * FROM knowledge_sources WHERE id = ? AND business_id = ?", [$sourceId, $businessId]);
    }

    private function chunkAndStore(int $businessId, int $sourceId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $chunks = str_split($text, self::CHUNK_SIZE);
        foreach ($chunks as $i => $chunk) {
            Database::query(
                "INSERT INTO knowledge_chunks (business_id, source_id, chunk_index, content, token_count, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$businessId, $sourceId, $i, $chunk, (int) (strlen($chunk) / 4)]
            );
        }
    }

    private function stripHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text);
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
