<?php
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

$token = strtr(base64_encode($payload . '|' . $hmac), '+/', '-_');
$isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
$host     = $_SERVER['HTTP_HOST'];
$baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$siteRoot = ($isHttps ? 'https' : 'http') . '://' . $host . $baseDir . '/';

header('Location: ' . $siteRoot . '?_tp=' . urlencode($token));
exit;