<?php
/**
 * POST /api/business/contact-message.php
 * Public endpoint backing the marketing site's /contact form.
 * Body: { name, email, phone?, subject?, message }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('contact_' . Security::clientIp(), 5, 600);

Validator::make($request->all())
    ->required('name', 'Name')->maxLength('name', 190)
    ->required('email', 'Email')->email('email')
    ->required('message', 'Message')->maxLength('message', 5000)
    ->validateOrFail();

Database::query(
    "INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at)
     VALUES (?, ?, ?, ?, ?, 'new', NOW())",
    [
        Security::cleanString($request->string('name')),
        Security::cleanEmail($request->string('email')),
        Security::cleanString($request->string('phone')) ?: null,
        Security::cleanString($request->string('subject')) ?: null,
        Security::cleanString($request->string('message')),
    ]
);

Response::success(null, 'Thanks for reaching out! We will get back to you soon.', 201);
