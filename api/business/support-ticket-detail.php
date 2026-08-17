<?php
/**
 * GET  /api/business/support-ticket-detail.php?id=X
 * POST /api/business/support-ticket-detail.php  Body: { id, message }  -> reply
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$user = AuthMiddleware::user();
$service = new SupportService();
$id = $request->int('id');

$ticket = $service->findWithReplies($id);
if ($ticket === null || (int) $ticket['user_id'] !== (int) $user['id']) {
    Response::notFound('Support ticket not found.');
}

if ($request->method === 'GET') {
    Response::success($ticket);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    Validator::make($request->all())->required('message', 'Message')->validateOrFail();
    $reply = $service->reply($id, (int) $user['id'], $request->string('message'), false);
    Response::success($reply, 'Reply sent.', 201);
}

Response::error('Method not allowed', [], 405);
