<?php
/**
 * GET /api/leads/meta.php?business_id=X
 * Returns lead statuses, sources, tags and assignable team members -
 * everything the Leads UI needs to populate filters/dropdowns.
 *
 * POST /api/leads/meta.php  Body: { business_id, type: 'status'|'source'|'tag', name, color? }
 * Allows admin/owner to create custom statuses/sources/tags (spec #16:
 * "Allow admin to customize statuses").
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);

if ($request->method === 'GET') {
    $statuses = Database::fetchAll(
        "SELECT * FROM lead_statuses WHERE business_id = ? OR business_id IS NULL ORDER BY sort_order ASC",
        [$businessId]
    );
    $sources = Database::fetchAll(
        "SELECT * FROM lead_sources WHERE business_id = ? OR business_id IS NULL ORDER BY name ASC",
        [$businessId]
    );
    $tags = Database::fetchAll("SELECT * FROM tags WHERE business_id = ? ORDER BY name ASC", [$businessId]);
    $teamMembers = Database::fetchAll(
        "SELECT u.id, u.name FROM business_members bm JOIN users u ON u.id = bm.user_id
         WHERE bm.business_id = ? AND bm.status = 'active'
         UNION
         SELECT u.id, u.name FROM businesses b JOIN users u ON u.id = b.owner_id WHERE b.id = ?",
        [$businessId, $businessId]
    );

    Response::success([
        'statuses' => $statuses,
        'sources' => $sources,
        'tags' => $tags,
        'team_members' => $teamMembers,
    ]);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    PermissionMiddleware::require($role, 'settings.manage');

    $type = $request->string('type');
    $name = Security::cleanString($request->string('name'));
    if ($name === '') {
        Response::validationError(['name' => ['Name is required.']]);
    }

    if ($type === 'status') {
        Database::query(
            "INSERT INTO lead_statuses (business_id, name, slug, color, sort_order, created_at)
             VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM lead_statuses WHERE business_id = ?), NOW())",
            [$businessId, $name, slugify($name), $request->string('color', '#6b7280'), $businessId]
        );
    } elseif ($type === 'source') {
        Database::query("INSERT INTO lead_sources (business_id, name, created_at) VALUES (?, ?, NOW())", [$businessId, $name]);
    } elseif ($type === 'tag') {
        Database::query(
            "INSERT INTO tags (business_id, name, color, created_at) VALUES (?, ?, ?, NOW())",
            [$businessId, $name, $request->string('color', '#6366f1')]
        );
    } else {
        Response::validationError(['type' => ['Invalid type. Must be status, source, or tag.']]);
    }

    Response::success(['id' => (int) Database::lastInsertId()], ucfirst($type) . ' created.', 201);
}

Response::error('Method not allowed', [], 405);

function slugify(string $name): string
{
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
}
