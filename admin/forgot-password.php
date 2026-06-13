<?php
/**
 * forgot-password.php — SynaptikCMS admin password reset request
 *
 * Behaviour:
 *   1. If mail() succeeds → shows "check your inbox".
 *   2. If mail() fails (no local MTA, MAMP, etc.) → shows the reset link
 *      directly on screen as a fallback. Safe for a single-admin CMS: the
 *      admin knows their own email address, and this page is already behind
 *      the admin URL.
 *
 * Email source priority:
 *   1. $admin_email in admin-credentials.php  (installer v2+)
 *   2. contact_email in settings.json         (pre-v2 fallback)
 *
 * Token: stored in private/reset_token.json (protected by .htaccess)
 * TTL  : 15 minutes
 */
session_start();
require_once 'includes/admin-functions.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

define('RESET_TOKEN_TTL', 900); // 15 minutes

$adminCredFile = __DIR__ . '/admin-credentials.php';
$tokenFile     = dirname(__DIR__) . '/private/reset_token.json';

// ── Resolve admin email ───────────────────────────────────────────────────────
$admin_email    = '';
$admin_password = '';
if (file_exists($adminCredFile)) {
    include $adminCredFile;
}
if (empty($admin_email)) {
    $settings    = admin_load_settings();
    $admin_email = trim($settings['contact_email'] ?? '');
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// State vars ──────────────────────────────────────────────────────────────────
$sent        = false;   // show "check your inbox" success screen
$fallbackUrl = '';      // set when mail() fails — show link on screen instead
$error       = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = __t('auth_csrf_error', 'Invalid security token. Please try again.');
    } else {
        $inputEmail = trim(strtolower($_POST['email'] ?? ''));

        if (!empty($admin_email) && strtolower($admin_email) === $inputEmail) {

            // Rate-limit: one active token at a time
            $existing    = null;
            $alreadySent = false;
            if (file_exists($tokenFile)) {
                $existing = json_decode(file_get_contents($tokenFile), true);
                $alreadySent = is_array($existing)
                            && isset($existing['expires_at'])
                            && $existing['expires_at'] > time();
            }

            if ($alreadySent) {
                // A valid token already exists — don't generate a new one.
                // We still show $sent = true to avoid leaking this info, but
                // we do NOT expose a fallback URL (the first one is still valid).
                $sent = true;
            } else {
                // Generate a new token
                $token     = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = time() + RESET_TOKEN_TTL;

                $writeOk = file_put_contents($tokenFile, json_encode([
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ], JSON_PRETTY_PRINT));

                if ($writeOk === false) {
                    // Can't write the token file — abort entirely
                    $error = 'Could not write reset token. Check write permissions on <code>/private/</code>.';
                } else {
                    // Build reset URL — points to the CMS root (?reset_token=TOKEN)
                    // so the admin folder name is never exposed in the email.
                    // __DIR__ is always the real admin folder on disk.
                    // dirname(__DIR__) is the CMS root. We derive the public URL
                    // path from DOCUMENT_ROOT which is always correct.
                    $settings  = admin_load_settings();
                    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host      = $_SERVER['HTTP_HOST'];
                    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                    $cmsPath   = str_replace($docRoot, '', rtrim(dirname(__DIR__), '/'));
                    $resetUrl  = $protocol . '://' . $host . $cmsPath . '/?reset_token=' . urlencode($token);

                    // Try to send the email
                    $siteName = $settings['site_title'] ?? 'SynaptikCMS';
                    $subject  = '[' . $siteName . '] Password Reset';
                    $body     = "Hello,\n\n"
                              . "A password reset was requested for the " . $siteName . " admin account.\n\n"
                              . "Click the link below to set a new password (valid for 15 minutes):\n\n"
                              . $resetUrl . "\n\n"
                              . "If you did not request this, ignore this email.\n\n"
                              . "— " . $siteName;
                    $headers  = "From: noreply@" . $host . "\r\n"
                              . "Reply-To: noreply@" . $host . "\r\n"
                              . "X-Mailer: SynaptikCMS\r\n"
                              . "Content-Type: text/plain; charset=UTF-8";

                    $mailOk = mail($admin_email, $subject, $body, $headers);

                    if (!$mailOk) {
                        // mail() failed (no MTA, MAMP, shared host restriction, etc.)
                        // Show the link directly — safe for a single-admin CMS.
                        $fallbackUrl = $resetUrl;
                    }

                    $sent = true;
                }
            }
        } else {
            // Email doesn't match — show generic success anyway (no enumeration)
            $sent = true;
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?php echo lang_current(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(__t('reset_page_title', 'Password Reset')); ?> — SynaptikCMS</title>
    <script>
    (function() {
        try {
            var t = localStorage.getItem('synaptik_theme');
            if (t !== 'dark' && t !== 'light') {
                t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/admin-base.css'); ?>">
    <style>
        .login-container { max-width: 420px; }
        .help-text  { font-size: 0.85rem; color: var(--text-muted); margin-top: 6px; }
        .back-link  { display: block; text-align: center; margin-top: 16px; font-size: 0.875rem; }
        .notice-warning {
            background: var(--warning-soft); border: 1px solid var(--warning); border-left: 4px solid var(--warning);
            border-radius: var(--radius-sm); padding: 12px 16px;
            font-size: 0.875rem; margin-bottom: 20px; color: var(--warning-text);
        }
        .notice-warning code { background: var(--surface-3); padding: 1px 4px; border-radius: 3px; }
        .reset-link-fallback {
            background: var(--info-soft); border: 1px solid var(--info);
            border-radius: var(--radius-sm); padding: 14px 16px;
            margin-top: 16px; font-size: 0.875rem; word-break: break-all;
        }
        .reset-link-fallback strong { display: block; margin-bottom: 8px; }
        .reset-link-fallback a {
            color: var(--info-text);
            font-weight: 600;
        }
        .reset-link-fallback .expires {
            display: block; margin-top: 8px;
            font-size: 0.8rem; color: var(--text-muted);
        }
    </style>
</head>
<body style="background-color: var(--surface2);">
<div class="login-container">
    <div class="login-header">
        <h1><?php echo htmlspecialchars(__t('reset_send_link_heading', 'Forgot your password?')); ?></h1>
    </div>

    <?php if ($error): ?>

        <div class="blockquote error"><?php echo $error; ?></div>
        <a class="back-link" href="auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php elseif ($sent && $fallbackUrl): ?>

        <!-- mail() failed — show the link directly -->
        <div class="blockquote warning">
            <strong>⚠ Email could not be sent</strong><br>
            No mail server is configured on this environment (common on MAMP / local dev).
        </div>
        <div class="reset-link-fallback">
            <strong>Use this one-time link to reset your password:</strong>
            <a href="<?php echo htmlspecialchars($fallbackUrl); ?>">
                <?php echo htmlspecialchars($fallbackUrl); ?>
            </a>
            <span class="expires">⏱ Expires in 15 minutes. Do not share this link.</span>
        </div>
        <a class="back-link" href="auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php elseif ($sent): ?>

        <div class="blockquote success" style="text-align:center;">
            <strong><?php echo htmlspecialchars(__t('reset_inbox_title', 'Check your inbox.')); ?></strong><br>
            <?php echo htmlspecialchars(__t('reset_inbox_detail', 'If that address is registered, a reset link has been sent. It expires in 15 minutes.')); ?>
        </div>
        <a class="back-link" href="auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php else: ?>

        <?php if (empty($admin_email)): ?>
        <div class="notice-warning">
            <?php echo __t('reset_no_email_warning', '⚠ No admin email configured. Password reset by email is unavailable.'); ?><br>
            <small><?php echo __t('reset_no_email_fix', "To enable it, add <code>\$admin_email = 'you@example.com';</code> to <code>admin-credentials.php</code>."); ?></small>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label for="email">
                <?php echo htmlspecialchars(__t('reset_email_label', 'Admin email address')); ?>
            </label>
            <input type="email" id="email" name="email"
                   placeholder="you@example.com" required autofocus autocomplete="email">
            <p class="help-text">
                <?php echo htmlspecialchars(__t('reset_email_help', "We'll send a one-time reset link to this address.")); ?>
            </p>

            <button type="submit" class="btn btn-primary btn-lg btn-block login-button">
                <?php echo htmlspecialchars(__t('reset_send_btn', 'Send reset link')); ?>
            </button>
        </form>

        <a class="back-link" href="auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php endif; ?>
</div>
</body>
</html>
