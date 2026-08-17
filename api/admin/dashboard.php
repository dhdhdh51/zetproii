<?php
require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';
AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
Response::success((new AdminService())->dashboardMetrics());
