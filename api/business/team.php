<?php
/**
 * GET    /api/business/team.php?business_id=X          -> list members
 * POST   /api/business/team.php  Body: { business_id, email, role }  -> invite
 * DELETE /api/business/team.php  Body: { business_id, member_id }    -> remove
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

if ($request->method === 'GET') {
    $owner = Database::fetchOne(
        "SELECT b.owner_id, u.name, u.email FROM businesses b JOIN users u ON u.id = b.owner_id WHERE b.id = ?",
        [$businessId]
    );
    $members = Database::fetchAll(
        "SELECT bm.id, bm.role, bm.status, bm.joined_at, u.name, u.email FROM business_members bm
         JOIN users u ON u.id = bm.user_id WHERE bm.business_id = ?",
        [$businessId]
    );
    Response::success([
        'owner' => ['name' => $owner['name'], 'email' => $owner['email'], 'role' => 'BUSINESS_OWNER'],
        'members' => $members,
    ]);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'team.manage');
    Validator::make($request->all())->required('email', 'Email')->email('email')->required('role', 'Role')->validateOrFail();

    $member = (new BusinessService())->inviteMember($businessId, (int) $user['id'], $request->string('email'), $request->string('role'));
    Response::success($member, 'Team member added.', 201);
}

if ($request->method === 'DELETE') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'team.manage');
    Database::query("DELETE FROM business_members WHERE id = ? AND business_id = ?", [$request->int('member_id'), $businessId]);
    AuditLogger::log((int) $user['id'], $businessId, 'member_removed', ['member_id' => $request->int('member_id')]);
    Response::success(null, 'Team member removed.');
}

Response::error('Method not allowed', [], 405);
