<?php
/**
 * GET /dashboard/print-document.php?business_id=X&type=proposal|quotation|invoice&id=Y
 * Renders a clean, print-friendly HTML view of a document with a
 * "Print / Save as PDF" button (browser-native, no server PDF library
 * dependency required). Ownership is verified server-side before render.
 */
require_once __DIR__ . '/_init.php';

$request = new Request();
$businessId = $request->int('business_id');
$type = $request->string('type');
$id = $request->int('id');

if ((int) $businessId !== (int) $activeBusiness['id']) {
    AuthMiddleware::requireBusinessAccess((int) $currentUser['id'], $businessId);
}

$business = Database::fetchOne("SELECT * FROM businesses WHERE id = ?", [$businessId]);
$renderer = new DocumentRenderService();

if ($type === 'proposal') {
    $doc = (new ProposalService())->find($businessId, $id);
    if ($doc === null) { http_response_code(404); echo 'Not found'; exit; }
    echo $renderer->renderProposal($doc, $business);
} elseif ($type === 'quotation') {
    $doc = (new QuotationService())->find($businessId, $id);
    if ($doc === null) { http_response_code(404); echo 'Not found'; exit; }
    echo $renderer->renderQuotation($doc, $business);
} elseif ($type === 'invoice') {
    $doc = (new InvoiceService())->find($businessId, $id);
    if ($doc === null) { http_response_code(404); echo 'Not found'; exit; }
    echo $renderer->renderInvoice($doc, $business);
} else {
    http_response_code(400);
    echo 'Invalid document type';
}
