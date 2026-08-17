<?php
/**
 * GET /api/admin/ai-usage.php?days=30
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);

$request = new Request();
$days = max(1, min(365, $request->int('days', 30)));

Response::success((new AdminAIService())->usageSummary($days));
