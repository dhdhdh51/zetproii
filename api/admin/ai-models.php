<?php
/**
 * POST   /api/admin/ai-models.php  Body: { provider_id, id?, name, display_name, ... }
 * DELETE /api/admin/ai-models.php  Body: { id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
Security::requireCsrf();
$service = new AdminAIService();

if ($request->method === 'POST') {
    Validator::make($request->all())
        ->required('provider_id')->required('name', 'Model name')->required('display_name', 'Display name')
        ->validateOrFail();
    $model = $service->upsertModel($request->all(), (int) $admin['id']);
    Response::success($model, 'AI model saved.');
}

if ($request->method === 'DELETE') {
    Validator::make($request->all())->required('id')->validateOrFail();
    $service->deleteModel($request->int('id'), (int) $admin['id']);
    Response::success(null, 'AI model deleted.');
}

Response::error('Method not allowed', [], 405);
