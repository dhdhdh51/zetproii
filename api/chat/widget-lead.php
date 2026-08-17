<?php
/**
 * POST /api/chat/widget-lead.php
 * Body: { key, session_uuid, name, email, phone, company, requirement, budget, location }
 * Public endpoint - captures a lead from the chat widget's lead form.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$request = new Request();
if ($request->method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('chatwidget_lead_' . Security::clientIp(), 10, 300);

Validator::make($request->all())
    ->required('key', 'Widget key')
    ->required('session_uuid', 'Session')
    ->required('name', 'Name')
    ->validateOrFail();

if ($request->string('email') !== '') {
    Validator::make($request->all())->email('email')->validateOrFail();
}

(new ChatbotService())->captureLead($request->string('key'), $request->string('session_uuid'), $request->all());

Response::success(null, 'Thank you! We will get back to you shortly.', 201);
