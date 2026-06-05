<?php
/**
 * Contact Form Handler — Hardened
 *
 * Security layers (in order of execution):
 *  1. Method check            — POST only
 *  2. Honeypot                — _hp must be empty
 *  3. Timing check            — minimum 5 s between page load and submit
 *  4. Referrer validation     — request must come from own domain
 *  5. CSRF token              — HMAC-SHA256, stateless, TTL 2 h
 *  6. Rate limiting           — max 5 submissions per IP per hour (flat file)
 *  7. Input validation        — required fields, email format, length limits
 *  8. Spam content detection  — URL density, known spam patterns
 *  9. hCaptcha verification   — server-side token check (when configured)
 * 10. Header injection guard  — strip CR/LF from every mail header value
 * 11. send mail
 */

// ── 1. Method ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// ── Load settings ─────────────────────────────────────────────────────────────
$settingsFile = __DIR__ . '/settings.json';
$settings     = [];
if (file_exists($settingsFile)) {
    $decoded = json_decode(file_get_contents($settingsFile), true);
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


// ════════════════════════════════════════════════════════════════════════════
// Helper functions
// ════════════════════════════════════════════════════════════════════════════

/**
 * Strip CR and LF from any value going into a mail header.
 * Prevents header injection attacks.
 */
function _contact_sanitize_header(string $value): string
{
    return str_replace(["\r", "\n", "\r\n"], '', $value);
}

/**
 * Get the client IP address.
 * Handles common proxy headers while preferring REMOTE_ADDR.
 */
function _contact_get_ip(): string
{
    // Only trust proxy headers if you're behind a known reverse proxy.
    // For safety, default to REMOTE_ADDR.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Returns the path to the /private/ directory at the CMS root.
 * Creates the directory (with .htaccess protection) on first use if absent.
 */
function _contact_private_dir(): string
{
    $dir = __DIR__ . '/private';

    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
        // Drop a deny-all .htaccess so the directory is protected even if
        // the root .htaccess is ever misconfigured or missing.
        $htaccess = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                  . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        @file_put_contents($dir . '/.htaccess', $htaccess);
    }

    return $dir;
}

/**
 * Load the HMAC secret from private/contact.secret.
 * Creates the file with a cryptographically random 32-byte key on first use.
 * The /private/ directory is blocked by .htaccess — never publicly accessible.
 */
function _contact_get_secret(): string
{
    $secretFile = _contact_private_dir() . '/contact.secret';

    if (file_exists($secretFile)) {
        $secret = trim(file_get_contents($secretFile));
        if (strlen($secret) === 64) { // 32 bytes hex = 64 chars
            return $secret;
        }
    }

    // Generate and persist a new secret
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secretFile, $secret, LOCK_EX);
    return $secret;
}

/**
 * Verify a CSRF token generated by render_contact_form_html().
 * Format: "{timestamp}.{HMAC-SHA256}"
 * TTL: 2 hours.
 */
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

/**
 * Check whether this IP has exceeded the rate limit.
 * Limit: 5 submissions per 3600-second window.
 * Data stored in private/contact_rate.json.
 *
 * @return bool  true = allowed, false = rate-limited
 */
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

    // Hash IP to avoid storing PII in plaintext
    $key = hash('sha256', $ip);

    // Purge expired entries to keep the file small
    foreach (array_keys($data) as $k) {
        if (($now - ($data[$k]['window_start'] ?? 0)) > $window) {
            unset($data[$k]);
        }
    }

    if (!isset($data[$key])) {
        return true; // First submission from this IP
    }

    $entry = $data[$key];

    // Within the current window?
    if (($now - $entry['window_start']) < $window) {
        return $entry['count'] < $limit;
    }

    return true; // Window has expired — allow
}

/**
 * Record a successful submission in the rate-limit file.
 */
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

/**
 * Basic spam content detection.
 * Checks for URL density and common spam keyword patterns.
 *
 * @return bool  true = likely spam
 */
function _contact_is_spam(string $name, string $message): bool
{
    $combined = $name . ' ' . $message;

    // Too many URLs (more than 3 links = almost certainly spam)
    $urlCount = preg_match_all('/https?:\/\//i', $combined);
    if ($urlCount > 3) {
        return true;
    }

    // Common spam keywords (case-insensitive)
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

    // Excessive repetition (same character 10+ times = bot junk)
    if (preg_match('/(.)\1{9,}/', $message)) {
        return true;
    }

    return false;
}

/**
 * Verify an hCaptcha token with the hCaptcha API.
 * Requires the server to make an outbound HTTPS request.
 *
 * @return bool  true = token valid
 */
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
        // hCaptcha API unreachable — fail closed (reject) to err on the side of security
        return false;
    }

    $data = json_decode($result, true);
    return isset($data['success']) && $data['success'] === true;
}

/**
 * Redirect back to the referring page with a status param.
 * Strips any existing contact_* params before appending the new one.
 */
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

/**
 * Redirect with a fake success to avoid leaking information to bots/spammers.
 * Used for honeypot, timing, and spam content failures.
 */
function _contact_silent_success(array $settings): void
{
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '/';
    $referrer  = preg_replace('/[?&]contact_[a-z_]+=?[^&]*/i', '', $referrer);
    $referrer  = rtrim($referrer, '?&');
    $separator = strpos($referrer, '?') !== false ? '&' : '?';
    header('Location: ' . $referrer . $separator . 'contact_sent=1');
    exit;
}

/**
 * Redirect with an explicit error code (shown to the user).
 * Used for CSRF, rate limit, config, validation, and captcha failures.
 */
function _contact_fail(string $reason): void
{
    $referrer  = $_SERVER['HTTP_REFERER'] ?? '/';
    $referrer  = preg_replace('/[?&]contact_[a-z_]+=?[^&]*/i', '', $referrer);
    $referrer  = rtrim($referrer, '?&');
    $separator = strpos($referrer, '?') !== false ? '&' : '?';
    header('Location: ' . $referrer . $separator . 'contact_error=' . urlencode($reason));
    exit;
}
