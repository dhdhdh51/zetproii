<?php
/**
 * GET  /api/business/chatbot-config.php?business_id=X
 * POST /api/business/chatbot-config.php  Body: { business_id, ...fields }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

$service = new ChatbotService();

if ($request->method === 'GET') {
    Response::success($service->getOrCreateConfig($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'chatbot.manage');
    $config = $service->updateConfig($businessId, $request->all());
    AuditLogger::log((int) $user['id'], $businessId, 'chatbot_config_updated', []);
    Response::success($config, 'Chatbot settings saved.');
}

Response::error('Method not allowed', [], 405);
