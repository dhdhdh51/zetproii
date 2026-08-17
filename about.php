<?php
/**
 * Real-file shim for the "about" marketing page.
 *
 * The pretty URL /about is produced by the mod_rewrite router in .htaccess.
 * This file makes the same page reachable at /about.php even when .htaccess
 * is missing (dotfiles are often skipped on upload) or ignored by the host
 * (AllowOverride None). Url::url('about') links here automatically whenever
 * rewriting is detected to be unavailable - see app/helpers/Url.php.
 */

declare(strict_types=1);

$_GET['route'] = 'about';
require __DIR__ . '/public/index.php';
