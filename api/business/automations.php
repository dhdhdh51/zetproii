<?php
/**
 * GET    /api/business/automations.php?business_id=X
 * POST   /api/business/automations.php  Body: { business_id, name, trigger_event, actions: [...] }
 * PUT    /api/business/automations.php  Body: { business_id, id, is_active }
 * DELETE /api/business/automations.php  Body: { business_id, id }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'automations.manage');

if ($request->method === 'GET') {
    $rules = Database::fetchAll(
        "SELECT ar.*, (SELECT COUNT(*) FROM automation_runs run WHERE run.automation_rule_id = ar.id) AS run_count
         FROM automation_rules ar WHERE ar.business_id = ? ORDER BY ar.created_at DESC",
        [$businessId]
    );
    Response::success($rules);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('name', 'Name')->required('trigger_event', 'Trigger event')->validateOrFail();

    Database::query(
        "INSERT INTO automation_rules (business_id, name, trigger_event, conditions, actions, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())",
        [
            $businessId, Security::cleanString($request->string('name')), $request->string('trigger_event'),
            json_encode($request->array('conditions')), json_encode($request->array('actions')),
        ]
    );
    AuditLogger::log((int) $user['id'], $businessId, 'automation_rule_created', []);
    Response::success(Database::fetchOne("SELECT * FROM automation_rules WHERE id = ?", [(int) Database::lastInsertId()]), 'Automation rule created.', 201);
}

if ($request->method === 'PUT' || $request->method === 'PATCH') {
    Security::requireCsrf();
    Validator::make($request->all())->required('id')->validateOrFail();
    Database::query(
        "UPDATE automation_rules SET is_active = ? WHERE id = ? AND business_id = ?",
        [$request->bool('is_active') ? 1 : 0, $request->int('id'), $businessId]
    );
    Response::success(null, 'Automation rule updated.');
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    Database::query("DELETE FROM automation_rules WHERE id = ? AND business_id = ?", [$request->int('id'), $businessId]);
    Response::success(null, 'Automation rule deleted.');
}

Response::error('Method not allowed', [], 405);
