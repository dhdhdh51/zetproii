<?php
/**
 * GET  /api/admin/support-tickets.php?status=&page=1
 * POST /api/admin/support-tickets.php  Body: { id, status? , reply? }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();
$service = new SupportService();

if ($request->method === 'GET') {
    if ($request->has('id')) {
        $ticket = $service->findWithReplies($request->int('id'));
        if ($ticket === null) {
            Response::notFound('Ticket not found.');
        }
        Response::success($ticket);
    }
    Response::success($service->listAll(['status' => $request->string('status')], $request->int('page', 1), $request->int('per_page', 20)));
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('id')->validateOrFail();

    if ($request->has('reply') && $request->string('reply') !== '') {
        $service->reply($request->int('id'), (int) $admin['id'], $request->string('reply'), true);
    }
    if ($request->has('status')) {
        $service->setStatus($request->int('id'), $request->string('status'), (int) $admin['id']);
    }

    Response::success(null, 'Ticket updated.');
}

Response::error('Method not allowed', [], 405);
