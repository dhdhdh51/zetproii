<?php
/**
 * GET  /api/business/support-tickets.php               -> current user's tickets
 * POST /api/business/support-tickets.php  Body: { business_id?, subject, description, priority? }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new SupportService();

if ($request->method === 'GET') {
    Response::success($service->listForUser((int) $user['id']));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('subject', 'Subject')->required('description', 'Description')->validateOrFail();

    // business_id is optional (a ticket can be general/account-level), but
    // if provided it MUST belong to the requesting user - never trust it blindly.
    $businessId = $request->int('business_id') ?: null;
    if ($businessId !== null) {
        AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
    }

    $ticket = $service->createTicket($businessId, (int) $user['id'], $request->string('subject'), $request->string('description'), $request->string('priority', 'medium'));
    Response::success($ticket, 'Support ticket created.', 201);
}

Response::error('Method not allowed', [], 405);
