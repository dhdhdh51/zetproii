<?php
/**
 * GET    /api/knowledge/index.php?business_id=X                -> list sources
 * POST   /api/knowledge/index.php  Body: { business_id, type: 'text'|'url', title?, content?, url? }
 * DELETE /api/knowledge/index.php  Body: { business_id, id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

$service = new KnowledgeService();

if ($request->method === 'GET') {
    Response::success($service->listSources($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'settings.manage');

    $type = $request->string('type');
    if ($type === 'text') {
        Validator::make($request->all())->required('title', 'Title')->required('content', 'Content')->validateOrFail();
        $source = $service->addManualText($businessId, $request->string('title'), $request->string('content'));
    } elseif ($type === 'url') {
        Validator::make($request->all())->required('url', 'URL')->validateOrFail();
        $url = $request->string('url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Response::validationError(['url' => ['Please enter a valid URL.']]);
        }
        $source = $service->addUrlSource($businessId, $url);
    } else {
        Response::validationError(['type' => ['Type must be "text" or "url".']]);
    }

    AuditLogger::log((int) $user['id'], $businessId, 'knowledge_source_added', ['type' => $type]);
    Response::success($source, 'Knowledge source added.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'settings.manage');
    $service->deleteSource($businessId, $request->int('id'));
    Response::success(null, 'Knowledge source removed.');
}

Response::error('Method not allowed', [], 405);
