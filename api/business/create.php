<?php
/**
 * POST /api/business/create.php
 * Body: { name }
 * Creates a new business owned by the authenticated user and kicks off
 * the onboarding wizard (step 1 already done via registration; this
 * creates the business shell for step 2 onward).
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();

Validator::make($request->all())->required('name', 'Business name')->maxLength('name', 190)->validateOrFail();

$business = (new BusinessService())->create((int) $user['id'], $request->string('name'));

Response::success($business, 'Business created successfully.', 201);
