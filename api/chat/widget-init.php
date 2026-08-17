<?php
/**
 * GET /api/chat/widget-init.php?key=WIDGET_KEY&url=SOURCE_URL
 * Public, unauthenticated endpoint - called by the embedded chat widget
 * on any external website to start a session. Rate-limited by IP.
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

RateLimitMiddleware::throttle('chatwidget_init_' . Security::clientIp(), 30, 60);

$widgetKey = $request->string('key');
if ($widgetKey === '') {
    Response::error('Missing widget key.', [], 400);
}

$session = (new ChatbotService())->startSession($widgetKey, $request->string('url') ?: null);

Response::success($session);
