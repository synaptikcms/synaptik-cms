<?php
/**
 * Secure backup download proxy.
 *
 * Streams a file from /bckps/ after verifying the admin session.
 * /bckps/ is blocked from direct browser access by .htaccess, so all
 * downloads must go through this endpoint.
 *
 * Usage: backup-dl.php?file=synaptik-full-backup-2026-01-15-120000.zip
 *
 * Security:
 *   - Session auth check
 *   - basename() strips directory traversal from the filename parameter
 *   - realpath() confinement ensures the file is inside /bckps/
 *   - Extension whitelist: only .zip and .json files are served
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once __DIR__ . '/includes/admin-functions.php';

if (!admin_is_logged_in()) {
	http_response_code(403);
	exit('Forbidden');
}

$requested = $_GET['file'] ?? '';
if (empty($requested)) {
	http_response_code(400);
	exit('Bad Request');
}

$filename = basename($requested);

$allowedExt = ['zip', 'json'];
if (!in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowedExt, true)) {
	http_response_code(403);
	exit('Forbidden');
}

$backupDir  = realpath(__DIR__ . '/../bckps');
$targetPath = realpath($backupDir . DIRECTORY_SEPARATOR . $filename);

if ($targetPath === false || strpos($targetPath, $backupDir) !== 0) {
	http_response_code(404);
	exit('Not Found');
}

if (!is_file($targetPath) || !is_readable($targetPath)) {
	http_response_code(404);
	exit('Not Found');
}

$ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentType = ($ext === 'zip') ? 'application/zip' : 'application/json';
$filesize    = filesize($targetPath);

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (ob_get_level()) ob_end_clean();
readfile($targetPath);
exit;
