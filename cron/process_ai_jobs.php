<?php
/**
 * cron/process_ai_jobs.php
 *
 * Processes pending knowledge_sources (e.g. documents awaiting text
 * extraction by a future PDF/DOCX parser) and retries any AI-dependent
 * background work that couldn't complete synchronously. Currently
 * handles: re-attempting 'pending' knowledge sources of type 'document'
 * whose underlying file has since become readable (e.g. admin installed
 * a parser), and resetting a stale 'processing' lock older than 1 hour
 * (defensive - guards against a crashed request leaving it stuck).
 *
 * Suggested cPanel cron: every 15 minutes
 *   php /home/user/public_html/cron/process_ai_jobs.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

$ctx = cron_start('process_ai_jobs');

try {
    $processed = 0;

    // Reset stuck "processing" sources back to "pending" so they get retried.
    Database::query(
        "UPDATE knowledge_sources SET status = 'pending' WHERE status = 'processing' AND updated_at < (NOW() - INTERVAL 1 HOUR)"
    );

    $pendingDocs = Database::fetchAll(
        "SELECT ks.id, ks.business_id, ks.reference_id, bd.file_path, bd.file_type
         FROM knowledge_sources ks JOIN business_documents bd ON bd.id = ks.reference_id
         WHERE ks.source_type = 'document' AND ks.status = 'pending' AND ks.deleted_at IS NULL
         LIMIT 50"
    );

    foreach ($pendingDocs as $doc) {
        if (in_array($doc['file_type'], ['txt', 'csv'], true) && is_readable($doc['file_path'])) {
            $text = (string) file_get_contents($doc['file_path']);
            Database::query("UPDATE knowledge_sources SET raw_content = ?, status = 'indexed' WHERE id = ?", [$text, $doc['id']]);
            Database::query("UPDATE business_documents SET processed_status = 'completed' WHERE id = ?", [$doc['reference_id']]);

            // Chunk it using the same logic KnowledgeService uses.
            $chunks = str_split(trim($text), 1200);
            foreach ($chunks as $i => $chunk) {
                Database::query(
                    "INSERT INTO knowledge_chunks (business_id, source_id, chunk_index, content, token_count, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$doc['business_id'], $doc['id'], $i, $chunk, (int) (strlen($chunk) / 4)]
                );
            }
            $processed++;
        }
        // PDF/DOCX documents remain 'pending' until a parser is configured -
        // this is intentional (see KnowledgeService docblock): we do not
        // fake extraction results.
    }

    cron_finish($ctx, 'success', "Processed {$processed} pending knowledge document(s).");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
