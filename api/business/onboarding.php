<?php
/**
 * POST /api/business/onboarding.php
 * Body: { business_id, step, fields: {...} }
 * Saves progress for a given onboarding step. Server-side re-verifies
 * the requesting user actually owns/manages business_id.
 *
 * GET /api/business/onboarding.php?business_id=X
 * Returns current onboarding progress + business record.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();

if ($request->method === 'GET') {
    $businessId = $request->int('business_id');
    AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

    $business = Database::fetchOne("SELECT * FROM businesses WHERE id = ? AND deleted_at IS NULL", [$businessId]);
    if ($business === null) {
        Response::notFound('Business not found.');
    }
    $hours = Database::fetchAll("SELECT * FROM business_hours WHERE business_id = ? ORDER BY day_of_week", [$businessId]);
    $faqs = Database::fetchAll("SELECT * FROM business_faqs WHERE business_id = ? ORDER BY sort_order", [$businessId]);

    Response::success(['business' => $business, 'hours' => $hours, 'faqs' => $faqs]);
}

if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

Security::requireCsrf();

$businessId = $request->int('business_id');
AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

$step = $request->int('step');
$fields = $request->array('fields');

Validator::make($request->all())->required('business_id')->numeric('step')->validateOrFail();

(new BusinessService())->updateOnboardingStep($businessId, $step, $fields);

// Step-specific side effects (business hours, FAQs) handled here since
// they are separate normalized tables, not columns on `businesses`.
if ($step === 4 && isset($fields['hours']) && is_array($fields['hours'])) {
    foreach ($fields['hours'] as $h) {
        Database::query(
            "UPDATE business_hours SET is_open = ?, open_time = ?, close_time = ? WHERE business_id = ? AND day_of_week = ?",
            [
                !empty($h['is_open']) ? 1 : 0,
                $h['open_time'] ?? '09:00:00',
                $h['close_time'] ?? '18:00:00',
                $businessId,
                (int) $h['day_of_week'],
            ]
        );
    }
}

if ($step === 5 && isset($fields['faqs']) && is_array($fields['faqs'])) {
    Database::query("DELETE FROM business_faqs WHERE business_id = ?", [$businessId]);
    foreach ($fields['faqs'] as $i => $faq) {
        if (empty($faq['question']) || empty($faq['answer'])) {
            continue;
        }
        Database::query(
            "INSERT INTO business_faqs (business_id, question, answer, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$businessId, Security::cleanString($faq['question']), Security::cleanString($faq['answer']), $i]
        );
    }
}

if ($step >= 7) {
    (new BusinessService())->completeOnboarding($businessId);
}

Response::success(['step' => $step], 'Progress saved.');
