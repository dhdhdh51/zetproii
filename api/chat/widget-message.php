<?php
/**
 * POST /api/chat/widget-message.php
 * Body: { key, session_uuid, message }
 * Public endpoint - the embedded widget calls this for every visitor
 * message. Rate-limited by session to prevent abuse/cost blowout.
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

Validator::make($request->all())
    ->required('key', 'Widget key')
    ->required('session_uuid', 'Session')
    ->required('message', 'Message')
    ->maxLength('message', 2000)
    ->validateOrFail();

RateLimitMiddleware::throttle('chatwidget_msg_' . $request->string('session_uuid'), 20, 60);

$reply = (new ChatbotService())->sendMessage(
    $request->string('key'),
    $request->string('session_uuid'),
    $request->string('message')
);

Response::success(['reply' => $reply]);
