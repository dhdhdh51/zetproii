<?php
/**
 * Real-file shim for the "pricing" marketing page.
 *
 * The pretty URL /pricing is produced by the mod_rewrite router in .htaccess.
 * This file makes the same page reachable at /pricing.php even when .htaccess
 * is missing (dotfiles are often skipped on upload) or ignored by the host
 * (AllowOverride None). Url::url('pricing') links here automatically whenever
 * rewriting is detected to be unavailable - see app/helpers/Url.php.
 */

declare(strict_types=1);

$_GET['route'] = 'pricing';
require __DIR__ . '/public/index.php';
