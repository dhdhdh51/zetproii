<?php
/**
 * GET  /api/ai/conversations.php?business_id=X          -> list conversations
 * POST /api/ai/conversations.php  Body: { business_id, title? }  -> create new
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'ai.use');

$service = new AssistantService();

if ($request->method === 'GET') {
    Response::success($service->listConversations($businessId, (int) $user['id']));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    $conversation = $service->startConversation($businessId, (int) $user['id'], $request->string('title') ?: null);
    Response::success($conversation, 'Conversation started.', 201);
}

Response::error('Method not allowed', [], 405);
