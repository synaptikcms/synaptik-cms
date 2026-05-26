<?php
/**
 * Secure backup download proxy.
 *
 * Streams a file from /bckps/ to the browser after verifying the admin session.
 * The /bckps/ directory is blocked from direct browser access by .htaccess, so
 * all downloads must go through this endpoint.
 *
 * Usage: backup-dl.php?file=synaptik-backup-2024-01-15-120000.json
 *
 * Security measures:
 *   - Session auth check before anything else
 *   - basename() strips any path components from the filename parameter
 *   - realpath() confinement ensures the resolved file is inside /bckps/
 *   - Extension whitelist: only .json files are served
 *   - No directory listing — only explicit filenames are accepted
 */

session_start();

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Input validation ──────────────────────────────────────────────────────────

$requested = $_GET['file'] ?? '';

if (empty($requested)) {
    http_response_code(400);
    exit('Bad Request: missing file parameter');
}

// basename() removes any directory traversal attempts ("../../etc/passwd" → "passwd")
$filename = basename($requested);

// Whitelist: only .json backup files may be downloaded
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'json') {
    http_response_code(403);
    exit('Forbidden: only .json backup files can be downloaded');
}

// ── Path confinement ──────────────────────────────────────────────────────────

// This file lives in /deevious/, so /bckps/ is one level up.
$backupDir  = realpath(__DIR__ . '/../bckps');
$targetPath = realpath($backupDir . DIRECTORY_SEPARATOR . $filename);

// realpath() returns false if the file does not exist — catches missing files
// and any residual traversal that survived basename().
if ($targetPath === false || strpos($targetPath, $backupDir) !== 0) {
    http_response_code(404);
    exit('Not Found');
}

if (!is_file($targetPath) || !is_readable($targetPath)) {
    http_response_code(404);
    exit('Not Found');
}

// ── Stream the file ───────────────────────────────────────────────────────────

$filesize = filesize($targetPath);

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Flush any output buffering so headers are sent cleanly
if (ob_get_level()) {
    ob_end_clean();
}

readfile($targetPath);
exit;