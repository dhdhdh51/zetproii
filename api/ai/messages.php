<?php
/**
 * GET  /api/ai/messages.php?business_id=X&conversation_id=Y  -> get message history
 * POST /api/ai/messages.php  Body: { business_id, conversation_id, message } -> send + get reply
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'ai.use');

$service = new AssistantService();

if ($request->method === 'GET') {
    $conversationId = $request->int('conversation_id');
    // Verify this conversation belongs to the caller within this business.
    $owned = Database::fetchOne(
        "SELECT id FROM ai_conversations WHERE id = ? AND business_id = ? AND user_id = ?",
        [$conversationId, $businessId, $user['id']]
    );
    if ($owned === null) {
        Response::notFound('Conversation not found.');
    }
    Response::success($service->getMessages($conversationId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    RateLimitMiddleware::throttle('ai_chat_' . $user['id'], 30, 60);

    Validator::make($request->all())->required('conversation_id')->required('message', 'Message')->validateOrFail();

    $reply = $service->sendMessage($businessId, (int) $user['id'], $request->int('conversation_id'), $request->string('message'));
    Response::success(['reply' => $reply]);
}

Response::error('Method not allowed', [], 405);
