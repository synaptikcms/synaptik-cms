<?php
/**
 * theme-preview.php — SynaptikCMS Theme Live Preview
 *
 * Generates a signed HMAC token containing the theme name + timestamp,
 * then redirects to the site homepage with ?_tp=TOKEN in the URL.
 *
 * index.php validates the token inside loadConfig() and overrides
 * active_theme for that single request only. Zero session state.
 * Closing the preview tab (with or without the Close button) leaves
 * no trace — the token only works when present in the URL.
 *
 * Token format (base64url): "themeName|unixTimestamp|hmac-sha256"
 * TTL: 2 hours. Secret: a dedicated per-install value in
 * private/theme_preview.secret (see themePreviewSecret()).
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/admin-functions.php';
if (!admin_is_logged_in()) {
    http_response_code(403);
    exit('Access denied.');
}
if (!admin_is_admin()) {
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
$secret    = themePreviewSecret();
$timestamp = time();
$payload   = $requestedTheme . '|' . $timestamp;
$hmac      = hash_hmac('sha256', $payload, $secret);

// Encode as URL-safe base64 (no padding issues in GET params)
$token = strtr(base64_encode($payload . '|' . $hmac), '+/', '-_');

// ── Redirect to site homepage with token in URL ───────────────────────────────
// Use getBaseUrl() when available (handles OVH reverse proxy via HTTP_X_FORWARDED_PROTO).
// Cannot use getBaseUrl() directly — SCRIPT_NAME points to admin/theme-preview.php,
// so we must go two levels up to get the CMS root.
$isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
$host     = $_SERVER['HTTP_HOST'];
$baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$siteRoot = ($isHttps ? 'https' : 'http') . '://' . $host . $baseDir . '/';

header('Location: ' . $siteRoot . '?_tp=' . urlencode($token));
exit;