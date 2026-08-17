<?php
/**
 * GET  /api/ai/seo-content.php?business_id=X                      -> list projects
 * POST /api/ai/seo-content.php  Body: { business_id, action: 'create_project'|'generate', ... }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
$service = new ContentToolsService();

if ($request->method === 'GET') {
    if ($request->has('project_id')) {
        Response::success($service->listSeoContent($request->int('project_id')));
    }
    Response::success($service->listSeoProjects($businessId));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'ai.use');

    $action = $request->string('action');
    if ($action === 'create_project') {
        Validator::make($request->all())->required('name', 'Project name')->validateOrFail();
        $project = $service->createSeoProject($businessId, $request->string('name'), $request->string('country', 'IN'), $request->string('language', 'en'));
        Response::success($project, 'SEO project created.', 201);
    }

    if ($action === 'generate') {
        Validator::make($request->all())->required('seo_project_id')->required('target_keyword', 'Target keyword')->validateOrFail();
        $content = $service->generateSeoContent($businessId, (int) $user['id'], $request->int('seo_project_id'), $request->all());
        Response::success($content, 'SEO content generated.', 201);
    }

    Response::validationError(['action' => ['Invalid action.']]);
}

Response::error('Method not allowed', [], 405);
