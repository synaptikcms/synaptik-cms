<?php
/**
 * theme-preview.php — SynaptikCMS Theme Live Preview
 *
 * Generates a signed HMAC token containing the theme name + timestamp,
 * then redirects to the site homepage with ?_tp=TOKEN in the URL.
 *
 * index.php validates the token inside loadSettings() and overrides
 * active_theme for that single request only. Zero session state.
 * Closing the preview tab (with or without the Close button) leaves
 * no trace — the token only works when present in the URL.
 *
 * Token format (base64url): "themeName|unixTimestamp|hmac-sha256"
 * TTL: 2 hours. Secret: sha256(adminPasswordHash + salt).
 */

session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Access denied.');
}

$rootDir = dirname(__DIR__);

// ── Validate requested theme ──────────────────────────────────────────────────
$requestedTheme = basename($_GET['theme'] ?? '');
$themePath      = $rootDir . '/theme/' . $requestedTheme;

if (
    $requestedTheme === '' ||
    !is_dir($themePath) ||
    !file_exists($themePath . '/header.php') ||
    !file_exists($themePath . '/footer.php') ||
    !file_exists($themePath . '/home.php')
) {
    http_response_code(400);
    exit('Invalid or incomplete theme: ' . htmlspecialchars($requestedTheme));
}

// ── Build HMAC token ──────────────────────────────────────────────────────────
$credFile = $rootDir . '/admin/admin-credentials.php';
if (!file_exists($credFile)) {
    http_response_code(500);
    exit('Admin credentials file not found.');
}

$admin_password = '';
require $credFile; // Sets $admin_password (the bcrypt hash)

$secret    = hash('sha256', $admin_password . 'theme_preview_salt');
$timestamp = time();
$payload   = $requestedTheme . '|' . $timestamp;
$hmac      = hash_hmac('sha256', $payload, $secret);

// Encode as URL-safe base64 (no padding issues in GET params)
$token = strtr(base64_encode($payload . '|' . $hmac), '+/', '-_');

// ── Redirect to site homepage with token in URL ───────────────────────────────
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$siteRoot = $protocol . '://' . $host . $baseDir . '/';

header('Location: ' . $siteRoot . '?_tp=' . urlencode($token));
exit;