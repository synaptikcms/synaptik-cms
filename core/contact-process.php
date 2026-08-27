<?php
// ── 1. Method ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}
if (!defined('CMS_ROOT')) define('CMS_ROOT', dirname(__DIR__));

$configFile = CMS_ROOT . '/config.json';
$settings   = [];
if (file_exists($configFile)) {
    $decoded = json_decode(file_get_contents($configFile), true);
    if (is_array($decoded)) {
        $settings = $decoded;
    }
}
// ── 2. Honeypot ───────────────────────────────────────────────────────────────
if (!empty($_POST['_hp'])) {
    _contact_silent_success($settings);
}
// ── 3. Timing check (min 5 s) ────────────────────────────────────────────────
$formTime = (int)($_POST['_ft'] ?? 0);
if ($formTime === 0 || (time() - $formTime) < 5) {
    _contact_silent_success($settings); // silent — don't tip off bots
}
// ── 4. Referrer validation ────────────────────────────────────────────────────
$siteHost = parse_url(
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'],
    PHP_URL_HOST
);
$refererHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
if (empty($refererHost) || strtolower($refererHost) !== strtolower($siteHost)) {
    _contact_fail('referrer');
}
// ── 5. CSRF token ─────────────────────────────────────────────────────────────
$csrfToken = trim($_POST['_csrf'] ?? '');
if (!_contact_verify_csrf($csrfToken)) {
    _contact_fail('csrf');
}
// ── 6. Rate limiting (5 req / IP / hour) ─────────────────────────────────────
$clientIp = _contact_get_ip();
if (!_contact_check_rate($clientIp)) {
    _contact_fail('rate');
}
// ── 7. Input validation ───────────────────────────────────────────────────────
$name    = trim(strip_tags($_POST['contact_name']    ?? ''));
$email   = trim(strip_tags($_POST['contact_email']   ?? ''));
$message = trim(strip_tags($_POST['contact_message'] ?? ''));

$toEmail = trim($settings['contact_email'] ?? '');

if (empty($name) || mb_strlen($name) > 100) {
    _contact_fail('validation');
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    _contact_fail('validation');
}
if (empty($message) || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    _contact_fail('validation');
}
if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    _contact_fail('config');
}

// ── 8. Spam content detection ─────────────────────────────────────────────────
if (_contact_is_spam($name, $message)) {
    _contact_silent_success($settings); // silent — don't tell spammers they were caught
}

// ── 9. hCaptcha server-side verification ─────────────────────────────────────
$hcaptchaSecret  = trim($settings['hcaptcha_secret_key'] ?? '');
$hcaptchaEnabled = !empty($hcaptchaSecret);

if ($hcaptchaEnabled) {
    $hcaptchaToken = trim($_POST['h-captcha-response'] ?? '');
    if (empty($hcaptchaToken) || !_contact_verify_hcaptcha($hcaptchaToken, $hcaptchaSecret)) {
        _contact_fail('captcha');
    }
}

// ── 10. Build and send mail ───────────────────────────────────────────────────
$subjectTemplate = $settings['contact_subject'] ?? 'New message from {name}';
$subjectLine     = str_replace('{name}', _contact_sanitize_header($name), $subjectTemplate);

// Plain-text body — no HTML, no risk of injection
$body  = "Name:    " . $name    . "\n";
$body .= "Email:   " . $email   . "\n";
$body .= "IP:      " . $clientIp . "\n";
$body .= "Date:    " . date('Y-m-d H:i:s') . "\n";
$body .= "\nMessage:\n" . $message . "\n";

// Build headers — sanitize EVERY value against CR/LF injection
$fromDomain = $siteHost ?: 'localhost';
$headers  = "From: "       . _contact_sanitize_header("noreply@{$fromDomain}")           . "\r\n";
$headers .= "Reply-To: "   . _contact_sanitize_header("{$name} <{$email}>")              . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8"                                    . "\r\n";
$headers .= "X-Mailer: SynaptikCMS/ContactForm"                                          . "\r\n";
$headers .= "X-Originating-IP: " . _contact_sanitize_header($clientIp)                  . "\r\n";

$sent = mail(
    $toEmail,
    '=?UTF-8?B?' . base64_encode($subjectLine) . '?=',
    $body,
    $headers
);

// Record successful submission in rate-limit file (increments counter)
_contact_record_rate($clientIp);

// Redirect
_contact_redirect($sent ? 'sent' : 'error_send');

function _contact_sanitize_header(string $value): string
{
    return str_replace(["\r", "\n", "\r\n"], '', $value);
}

function _contact_get_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function _contact_private_dir(): string
{
    $dir = CMS_ROOT . '/private';

    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
        $htaccess = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                  . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        @file_put_contents($dir . '/.htaccess', $htaccess);
    }

    return $dir;
}

function _contact_get_secret(): string
{
    $secretFile = _contact_private_dir() . '/contact.secret';

    if (file_exists($secretFile)) {
        $secret = trim(file_get_contents($secretFile));
        if (strlen($secret) === 64) { // 32 bytes hex = 64 chars
            return $secret;
        }
    }

    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secretFile, $secret, LOCK_EX);
    return $secret;
}

function _contact_verify_csrf(string $token): bool
{
    if (empty($token) || substr_count($token, '.') !== 1) {
        return false;
    }

    [$ts, $mac] = explode('.', $token, 2);

    if (!is_numeric($ts)) {
        return false;
    }

    // TTL check
    if ((time() - (int)$ts) > 7200) {
        return false;
    }

    $secret   = _contact_get_secret();
    $expected = hash_hmac('sha256', $ts, $secret);

    return hash_equals($expected, $mac);
}

function _contact_check_rate(string $ip): bool
{
    $limit    = 5;
    $window   = 3600; // 1 hour
    $rateFile = _contact_private_dir() . '/contact_rate.json';
    $now      = time();

    $data = [];
    if (file_exists($rateFile)) {
        $raw = file_get_contents($rateFile);
        $data = json_decode($raw, true) ?? [];
    }
    $key = hash('sha256', $ip);
    foreach (array_keys($data) as $k) {
        if (($now - ($data[$k]['window_start'] ?? 0)) > $window) {
            unset($data[$k]);
        }
    }

    if (!isset($data[$key])) {
        return true; // First submission from this IP
    }

    $entry = $data[$key];
    if (($now - $entry['window_start']) < $window) {
        return $entry['count'] < $limit;
    }

    return true; // Window has expired — allow
}

function _contact_record_rate(string $ip): void
{
    $window   = 3600;
    $rateFile = _contact_private_dir() . '/contact_rate.json';
    $now      = time();

    $data = [];
    if (file_exists($rateFile)) {
        $raw  = file_get_contents($rateFile);
        $data = json_decode($raw, true) ?? [];
    }

    $key = hash('sha256', $ip);

    if (!isset($data[$key]) || ($now - ($data[$key]['window_start'] ?? 0)) >= $window) {
        $data[$key] = ['count' => 1, 'window_start' => $now];
    } else {
        $data[$key]['count']++;
    }

    @file_put_contents($rateFile, json_encode($data), LOCK_EX);
}

function _contact_is_spam(string $name, string $message): bool
{
    $combined = $name . ' ' . $message;
    $urlCount = preg_match_all('/https?:\/\//i', $combined);
    if ($urlCount > 3) {
        return true;
    }
    $spamPatterns = [
        '/\bcasino\b/i', '/\bviagra\b/i', '/\bcialis\b/i',
        '/\bbitcoin\b/i', '/\bcrypto\b/i', '/\bnft\b/i',
        '/\bseo\s+service/i', '/\bbacklink/i',
        '/\bdiscount\b.{0,20}\bprice\b/i',
        '/click\s+here/i', '/buy\s+now/i',
        '/free\s+money/i', '/make\s+money\s+fast/i',
        '/\bporn\b/i', '/\bxxx\b/i',
    ];

    foreach ($spamPatterns as $pattern) {
        if (preg_match($pattern, $combined)) {
            return true;
        }
    }
    if (preg_match('/(.)\1{9,}/', $message)) {
        return true;
    }

    return false;
}

function _contact_verify_hcaptcha(string $token, string $secretKey): bool
{
    $url     = 'https://hcaptcha.com/siteverify';
    $payload = http_build_query([
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => _contact_get_ip(),
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "Content-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);

    $result = @file_get_contents($url, false, $ctx);

    if ($result === false) {
        return false;
    }

    $data = json_decode($result, true);
    return isset($data['success']) && $data['success'] === true;
}

function _contact_redirect(string $status): void
{
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '/';
    $referrer  = preg_replace('/[?&]contact_[a-z_]+=?[^&]*/i', '', $referrer);
    $referrer  = rtrim($referrer, '?&');
    $separator = strpos($referrer, '?') !== false ? '&' : '?';

    if ($status === 'sent') {
        header('Location: ' . $referrer . $separator . 'contact_sent=1');
    } else {
        header('Location: ' . $referrer . $separator . 'contact_error=' . urlencode($status));
    }
    exit;
}

function _contact_silent_success(array $settings): void
{
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '/';
    $referrer  = preg_replace('/[?&]contact_[a-z_]+=?[^&]*/i', '', $referrer);
    $referrer  = rtrim($referrer, '?&');
    $separator = strpos($referrer, '?') !== false ? '&' : '?';
    header('Location: ' . $referrer . $separator . 'contact_sent=1');
    exit;
}

function _contact_fail(string $reason): void
{
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '/';
    $referrer  = preg_replace('/[?&]contact_[a-z_]+=?[^&]*/i', '', $referrer);
    $referrer  = rtrim($referrer, '?&');
    $separator = strpos($referrer, '?') !== false ? '&' : '?';
    header('Location: ' . $referrer . $separator . 'contact_error=' . urlencode($reason));
    exit;
}
