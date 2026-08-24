<?php
/**
 * forgot-password.php — SynaptikCMS admin password reset request
 *
 * Looked up by email across every user in private/users.json — no
 * config.json contact_email fallback (that's the site's public contact
 * address, not any one person's, and would be wrong to accept here now
 * that there can be more than one personal account).
 *
 * Tokens: stored as an array in private/reset_token.json
 * (.htaccess-protected) so two different users can have an outstanding
 * reset at once. TTL: 15 minutes, one active token per user.
 */
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

define('RESET_TOKEN_TTL', 900); // 15 minutes

$tokenFile = dirname(__DIR__) . '/private/reset_token.json';

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// State vars ──────────────────────────────────────────────────────────────────
$sent  = false;
$error = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = __t('auth_csrf_error', 'Invalid security token. Please try again.');
    } else {
        $inputEmail  = trim($_POST['email'] ?? '');
        $matchedUser = admin_find_user_by_email($inputEmail);

        // Always run the same lock/read/purge/write cycle on the shared
        // token file whether or not the email matches an account — a real
        // match must not be distinguishable from "no such account" by how
        // much work this request does (same rationale as auth.php's
        // timing-safe login: the file I/O below is the expensive step, so
        // it always runs, and only the mail() call stays conditional).
        $_lockFp = @fopen($tokenFile, 'c+');
        $tokens      = [];
        $alreadySent = false;
        if ($_lockFp) {
            flock($_lockFp, LOCK_EX);
            $_raw     = stream_get_contents($_lockFp);
            $_decoded = ($_raw !== false && $_raw !== '') ? json_decode($_raw, true) : null;
            if (is_array($_decoded)) {
                // Keep every still-valid entry (expired ones are dropped
                // regardless of owner). One active token per user: if
                // this user already has a valid one, rate-limit —
                // don't generate/send another.
                foreach ($_decoded as $_entry) {
                    if (!is_array($_entry) || ($_entry['expires_at'] ?? 0) <= time()) continue;
                    $tokens[] = $_entry;
                    if ($matchedUser !== null && ($_entry['user_id'] ?? null) === $matchedUser['id']) $alreadySent = true;
                }
            }
        }

        if ($matchedUser === null || $alreadySent) {
            // No such account, or a valid token already exists for this
            // user — nothing new to persist, but still write the purged
            // list back so this request's file cost matches the
            // token-generating branch below.
            if ($_lockFp) {
                ftruncate($_lockFp, 0);
                rewind($_lockFp);
                fwrite($_lockFp, json_encode($tokens, JSON_PRETTY_PRINT));
                flock($_lockFp, LOCK_UN);
                fclose($_lockFp);
            }
            // Note: the token-generating branch below calls mail(), an SMTP
            // round-trip whose cost varies by relay and can't be reliably
            // padded to match with a fixed delay (tried 200ms here — it
            // overshot local sendmail's ~15-40ms and just flipped which
            // side is slower). What's fixed above is the deterministic
            // signal — file I/O now happens identically either way — the
            // residual mail()-timing gap is a known, accepted limitation of
            // sending reset emails synchronously with no queue.
            $sent = true;
        } else {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = time() + RESET_TOKEN_TTL;
            $tokens[]  = [
                'token_hash' => $tokenHash,
                'user_id'    => $matchedUser['id'],
                'expires_at' => $expiresAt,
            ];

            $writeOk = false;
            if ($_lockFp) {
                $writeOk = ftruncate($_lockFp, 0)
                    && rewind($_lockFp)
                    && (fwrite($_lockFp, json_encode($tokens, JSON_PRETTY_PRINT)) !== false);
                flock($_lockFp, LOCK_UN);
                fclose($_lockFp);
            }

            if (!$writeOk) {
                // Can't write the token file — abort entirely
                $error = 'Could not write reset token. Check write permissions on <code>/private/</code>.';
            } else {
                // Build reset URL from configured site_url to prevent Host header poisoning.
                $settings = admin_load_config();
                $siteUrl  = rtrim($settings['site_url'] ?? '', '/');
                if (empty($siteUrl)) {
                    // Fallback for unconfigured installs only.
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                    $cmsPath  = str_replace($docRoot, '', rtrim(dirname(__DIR__), '/'));
                    $siteUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . $cmsPath;
                }
                $resetUrl = $siteUrl . '/?reset_token=' . urlencode($token);

                // Try to send the email
                $siteName   = $settings['site_title'] ?? 'SynaptikCMS';
                $mailDomain = parse_url($siteUrl, PHP_URL_HOST) ?? 'localhost';
                $subject    = '[' . $siteName . '] Password Reset';
                $body       = "Hello,\n\n"
                            . "A password reset was requested for your " . $siteName . " admin account.\n\n"
                            . "Click the link below to set a new password (valid for 15 minutes):\n\n"
                            . $resetUrl . "\n\n"
                            . "If you did not request this, ignore this email.";
                $headers    = "From: noreply@" . $mailDomain . "\r\n"
                            . "Reply-To: noreply@" . $mailDomain . "\r\n"
                            . "X-Mailer: SynaptikCMS\r\n"
                            . "Content-Type: text/plain; charset=UTF-8";

                mail(str_replace(["\r", "\n"], '', $matchedUser['email']), $subject, $body, $headers);
                // Never expose the result of mail() or the token in the response.
                $sent = true;
            }
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
    <title><?php echo hsc(__t('reset_page_title', 'Password Reset')); ?> — SynaptikCMS</title>
    <script src="assets/js/theme-boot.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/theme-boot.js'); ?>"></script>
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

    </style>
</head>
<body class="auth-page" style="background-color: var(--surface2);">
<div class="login-container">
    <div class="login-header">
        <h1><?php echo hsc(__t('reset_send_link_heading', 'Forgot your password?')); ?></h1>
    </div>

    <?php if ($error): ?>

        <div class="blockquote error"><?php echo $error; ?></div>
        <a class="back-link" href="auth.php">
            <?php echo hsc(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php elseif ($sent): ?>

        <div class="blockquote success" style="text-align:center;">
            <strong><?php echo hsc(__t('reset_inbox_title', 'Check your inbox.')); ?></strong><br>
            <?php echo hsc(__t('reset_inbox_detail', 'If that address is registered, a reset link has been sent. It expires in 15 minutes.')); ?>
        </div>
        <a class="back-link" href="auth.php">
            <?php echo hsc(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php else: ?>

        <form class="login-form" method="POST" action="">
            <input type="hidden" name="csrf_token"
                   value="<?php echo hsc($_SESSION['csrf_token']); ?>">

            <label for="email">
                <?php echo hsc(__t('reset_email_label', 'Admin email address')); ?>
            </label>
            <input type="email" id="email" name="email"
                   placeholder="you@example.com" required autofocus autocomplete="email">
            <p class="help-text">
                <?php echo hsc(__t('reset_email_help', "We'll send a one-time reset link to this address.")); ?>
            </p>

            <button type="submit" class="btn btn-primary btn-lg btn-block login-button">
                <?php echo hsc(__t('reset_send_btn', 'Send reset link')); ?>
            </button>
        </form>

        <a class="back-link" href="auth.php">
            <?php echo hsc(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php endif; ?>
</div>
</body>
</html>
