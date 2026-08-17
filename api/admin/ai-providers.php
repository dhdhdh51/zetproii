<?php
/**
 * GET  /api/admin/ai-providers.php               -> list all providers + models
 * POST /api/admin/ai-providers.php  Body: { id, api_key?, base_url?, is_enabled?, priority?, timeout_seconds? }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$service = new AdminAIService();

if ($request->method === 'GET') {
    Response::success($service->listProviders());
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('id')->validateOrFail();
    $provider = $service->updateProvider($request->int('id'), $request->all(), (int) $admin['id']);
    Response::success($provider, 'AI provider updated.');
}

Response::error('Method not allowed', [], 405);
