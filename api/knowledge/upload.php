<?php
/**
 * POST /api/knowledge/upload.php  (multipart/form-data)
 * Fields: business_id, title, file
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'settings.manage');

$file = $request->file('file');
if ($file === null) {
    Response::validationError(['file' => ['Please choose a file to upload.']]);
}

try {
    $uploaded = FileUploadService::handleUpload(
        $file,
        config('uploads.allowed_document_extensions', ['pdf', 'txt', 'docx', 'csv']),
        (int) config('uploads.max_size_mb', 25),
        "knowledge/business_{$businessId}"
    );
} catch (\InvalidArgumentException $e) {
    Response::validationError(['file' => [$e->getMessage()]]);
} catch (\Throwable $e) {
    Logger::error('Knowledge upload failed: ' . $e->getMessage());
    Response::serverError('Failed to process the uploaded file.');
}

$title = $request->string('title') ?: $uploaded['original_name'];

$source = (new KnowledgeService())->addDocument(
    $businessId,
    (int) $user['id'],
    $title,
    $uploaded['stored_path'],
    $uploaded['extension'],
    $uploaded['size']
);

AuditLogger::log((int) $user['id'], $businessId, 'knowledge_document_uploaded', ['title' => $title]);

Response::success($source, 'Document uploaded successfully.', 201);
