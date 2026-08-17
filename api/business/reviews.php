<?php
/**
 * GET  /api/business/reviews.php?business_id=X
 * POST /api/business/reviews.php  Body: { business_id, customer_name?, source?, rating?, review_text }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new ContentToolsService();

if ($request->method === 'GET') {
    Response::success($service->listReviews($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'ai.use');
    Validator::make($request->all())->required('review_text', 'Review text')->validateOrFail();
    $review = $service->addReview($businessId, $request->all());
    Response::success($review, 'Review added.', 201);
}

Response::error('Method not allowed', [], 405);
