<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';
AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();
Response::success((new AdminService())->systemLogs($request->string('channel') ?: null, $request->int('page', 1), $request->int('per_page', 20)));
