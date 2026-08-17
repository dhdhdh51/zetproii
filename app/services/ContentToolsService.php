<?php
/**
 * Review Assistant (spec #23), Social Content Generator (spec #24), and
 * SEO Content Tool (spec #25). Grouped in one service since they share
 * the same "generate + persist + list" shape.
 */
final class ContentToolsService
{
    // ---------------- Review Assistant ----------------

    public function addReview(int $businessId, array $data): array
    {
        Database::query(
            "INSERT INTO reviews (business_id, customer_name, source, rating, review_text, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$businessId, $data['customer_name'] ?? null, $data['source'] ?? 'manual', $data['rating'] ?? null, Security::cleanString($data['review_text'])]
        );
        return Database::fetchOne("SELECT * FROM reviews WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function listReviews(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT r.*, (SELECT COUNT(*) FROM review_replies rr WHERE rr.review_id = r.id) AS reply_count
             FROM reviews r WHERE r.business_id = ? ORDER BY r.created_at DESC LIMIT 100",
            [$businessId]
        );
    }

    public function generateReviewReply(int $businessId, ?int $userId, int $reviewId, string $tone = 'professional'): array
    {
        $review = Database::fetchOne("SELECT * FROM reviews WHERE id = ? AND business_id = ?", [$reviewId, $businessId]);
        if ($review === null) {
            Response::notFound('Review not found.');
        }

        $ai = new AIService();
        try {
            $replyText = $ai->generateReviewReply($businessId, $userId, $review['review_text'], $review['rating'], $tone);
        } catch (\Throwable $e) {
            Response::error('AI reply generation failed: ' . $e->getMessage(), [], 502);
        }

        $sentiment = $review['rating'] !== null
            ? ($review['rating'] >= 4 ? 'positive' : ($review['rating'] <= 2 ? 'negative' : 'neutral'))
            : null;
        if ($sentiment !== null) {
            Database::query("UPDATE reviews SET sentiment = ? WHERE id = ?", [$sentiment, $reviewId]);
        }

        Database::query(
            "INSERT INTO review_replies (review_id, reply_text, generated_by_ai, created_by, created_at) VALUES (?, ?, 1, ?, NOW())",
            [$reviewId, $replyText, $userId]
        );
        $replyId = (int) Database::lastInsertId();

        return Database::fetchOne("SELECT * FROM review_replies WHERE id = ?", [$replyId]);
    }

    // ---------------- Social Content Generator ----------------

    public function generateSocialPost(int $businessId, ?int $userId, array $params): array
    {
        $ai = new AIService();
        $defaults = ['platform' => 'instagram', 'tone' => 'friendly', 'audience' => 'general audience', 'language' => 'English', 'cta' => 'Contact us today', 'keywords' => ''];
        $params = array_merge($defaults, $params);

        try {
            $content = $ai->generateSocialPost($businessId, $userId, $params);
        } catch (\Throwable $e) {
            Response::error('AI social post generation failed: ' . $e->getMessage(), [], 502);
        }

        Database::query(
            "INSERT INTO social_posts (business_id, platform, topic, tone, audience, language, cta, keywords, content, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())",
            [$businessId, $params['platform'], $params['topic'] ?? null, $params['tone'], $params['audience'], $params['language'], $params['cta'], $params['keywords'], $content, $userId]
        );

        return Database::fetchOne("SELECT * FROM social_posts WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function listSocialPosts(int $businessId): array
    {
        return Database::fetchAll("SELECT * FROM social_posts WHERE business_id = ? ORDER BY created_at DESC LIMIT 100", [$businessId]);
    }

    // ---------------- SEO Content Tool ----------------

    public function createSeoProject(int $businessId, string $name, string $country, string $language): array
    {
        Database::query(
            "INSERT INTO seo_projects (business_id, name, country, language, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$businessId, Security::cleanString($name), $country, $language]
        );
        return Database::fetchOne("SELECT * FROM seo_projects WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function listSeoProjects(int $businessId): array
    {
        $projects = Database::fetchAll("SELECT * FROM seo_projects WHERE business_id = ? ORDER BY created_at DESC", [$businessId]);
        foreach ($projects as &$p) {
            $p['content_count'] = (int) (Database::fetchOne("SELECT COUNT(*) c FROM seo_content WHERE seo_project_id = ?", [$p['id']])['c'] ?? 0);
        }
        return $projects;
    }

    public function generateSeoContent(int $businessId, ?int $userId, int $seoProjectId, array $params): array
    {
        $project = Database::fetchOne("SELECT * FROM seo_projects WHERE id = ? AND business_id = ?", [$seoProjectId, $businessId]);
        if ($project === null) {
            Response::notFound('SEO project not found.');
        }

        $prompt = "Target keyword: {$params['target_keyword']}. Secondary keywords: " . ($params['secondary_keywords'] ?? '') .
            ". Search intent: " . ($params['search_intent'] ?? 'informational') . ". Country: {$project['country']}. Language: {$project['language']}. " .
            "Desired article length: " . ($params['article_length'] ?? 'medium (800-1200 words)') . ". Tone: " . ($params['tone'] ?? 'professional') . ".";

        $schema = [
            'seo_title' => 'string', 'meta_description' => 'string', 'slug' => 'string',
            'outline' => 'string (markdown headings)', 'article' => 'string (full article body)',
            'faqs' => [['question' => 'string', 'answer' => 'string']],
            'internal_linking_suggestions' => ['string'],
        ];

        $ai = new AIService();
        try {
            $result = $ai->generateStructuredData($businessId, $userId, 'seo_content', $prompt, $schema);
        } catch (\Throwable $e) {
            Response::error('AI SEO content generation failed: ' . $e->getMessage(), [], 502);
        }

        Database::query(
            "INSERT INTO seo_content (seo_project_id, title, slug, meta_description, outline, article_body, faqs, internal_link_suggestions, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())",
            [
                $seoProjectId, $result['seo_title'] ?? $params['target_keyword'], $result['slug'] ?? '',
                $result['meta_description'] ?? '', $result['outline'] ?? '', $result['article'] ?? '',
                json_encode($result['faqs'] ?? []), json_encode($result['internal_linking_suggestions'] ?? []),
            ]
        );

        Database::query(
            "INSERT INTO seo_keywords (seo_project_id, keyword, is_primary, search_intent, created_at) VALUES (?, ?, 1, ?, NOW())",
            [$seoProjectId, $params['target_keyword'], $params['search_intent'] ?? null]
        );

        return Database::fetchOne("SELECT * FROM seo_content WHERE id = ?", [(int) Database::lastInsertId()]);
    }

    public function listSeoContent(int $seoProjectId): array
    {
        return Database::fetchAll("SELECT * FROM seo_content WHERE seo_project_id = ? ORDER BY created_at DESC", [$seoProjectId]);
    }
}
