<?php
/**
 * reset-password.php — SynaptikCMS admin password reset form
 *
 * Validates the one-time token from the URL, then lets the admin set a new
 * password. On success, consumes the token (unlinks it) and redirects to login.
 */
session_start();
require_once __DIR__ . '/includes/admin-functions.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$adminCredFile = __DIR__ . '/admin-credentials.php';
$tokenFile     = dirname(__DIR__) . '/bckps/reset_token.json';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Token validation helper ───────────────────────────────────────────────────
function _reset_token_valid(string $rawToken, string $tokenFile): bool
{
    if ($rawToken === '' || !file_exists($tokenFile)) return false;
    $stored = json_decode(file_get_contents($tokenFile), true);
    return is_array($stored)
        && !empty($stored['token_hash'])
        && !empty($stored['expires_at'])
        && $stored['expires_at'] > time()
        && hash_equals($stored['token_hash'], hash('sha256', $rawToken));
}

// ── State ─────────────────────────────────────────────────────────────────────
$rawToken   = trim($_GET['token'] ?? '');
$tokenValid = _reset_token_valid($rawToken, $tokenFile);
$error      = '';
$success    = false;

if (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = __t('reset_link_invalid', 'This reset link is invalid or has expired.');
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error      = __t('auth_csrf_error', 'Invalid security token. Please start over.');
        $tokenValid = false;
    }

    if (empty($error) && !_reset_token_valid($rawToken, $tokenFile)) {
        $error      = __t('reset_link_expired', 'Your reset link has expired.')
                    . ' <a href="forgot-password.php">'
                    . htmlspecialchars(__t('reset_request_new', 'request a new one'))
                    . '</a>.';
        $tokenValid = false;
    }

    if (empty($error)) {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            $error = __t('passwords_dont_match', 'Passwords do not match.');
        } elseif (
            mb_strlen($newPassword) < 8 ||
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[\W_]/', $newPassword)
        ) {
            $error = __t('password_too_weak',
                'Password must be at least 8 characters and include an uppercase letter, a number, and a special character.');
        } else {
            // Load current credentials to preserve $admin_email
            $admin_email    = '';
            $admin_password = '';
            if (file_exists($adminCredFile)) {
                include $adminCredFile;
            }
            // Also fall back to settings.json for pre-v2 installs
            if (empty($admin_email)) {
                $settings    = admin_load_settings();
                $admin_email = trim($settings['contact_email'] ?? '');
            }

            $newHash     = password_hash($newPassword, PASSWORD_BCRYPT);
            $emailLine   = ($admin_email !== '')
                         ? "\$admin_email = '" . str_replace("'", "\\'", $admin_email) . "';\n"
                         : '';
            $credContent = "<?php\n"
                         . "// Admin credentials — updated by SynaptikCMS password reset\n"
                         . "\$admin_password = '" . str_replace("'", "\\'", $newHash) . "';\n"
                         . $emailLine
                         . "?>\n";

            if (file_put_contents($adminCredFile, $credContent) !== false) {
                @unlink($tokenFile); // consume token — one-time use
                $success = true;
            } else {
                $error = __t('password_update_failed',
                    'Could not save the new password. Check file permissions on admin-credentials.php.');
            }
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Translated JS strings (passed from PHP so the page works without CMS_LANG) ─
$jsStrings = [
    'r_len'   => __t('reset_rule_length',  '8+ characters'),
    'r_up'    => __t('reset_rule_upper',   '1 uppercase'),
    'r_dig'   => __t('reset_rule_digit',   '1 digit'),
    'r_spc'   => __t('reset_rule_special', '1 special character'),
    'r_match' => __t('reset_rule_match',   'Passwords match'),
];
?>
<!DOCTYPE html>
<html lang="<?php echo lang_current(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(__t('reset_new_pwd_heading', 'Set New Password')); ?> — SynaptikCMS</title>
    <?php
    // Build an absolute URL to the admin CSS so this page works correctly
    // whether it is included from index.php (/?reset_token=) or accessed directly.
    $_scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_adminCssUrl = $_scheme . '://' . $_SERVER['HTTP_HOST']
                  . str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', rtrim(__DIR__, '/'));
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($_adminCssUrl); ?>/css/admin-base.css">
    <style>
        .login-container { max-width: 420px; }
        .pw-rules { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .pw-rule {
            font-size: .78rem; padding: 2px 9px; border-radius: 20px;
            background: #e8eef2; color: #718096; border: 1px solid #dde2e8;
            transition: all .2s;
        }
        .pw-rule.valid { background: #e8f5e9; color: #2e7d32; border-color: #a5d6a7; }
        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 0.875rem; }
        .message a { color: inherit; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1><?php echo htmlspecialchars(__t('reset_new_pwd_heading', 'Set New Password')); ?></h1>
    </div>

    <?php if ($success): ?>

        <div class="message success" style="text-align:center;">
            <strong><?php echo htmlspecialchars(__t('reset_success_title', 'Password updated.')); ?></strong><br>
            <?php echo htmlspecialchars(__t('reset_success_detail', 'You can now log in with your new password.')); ?>
        </div>
        <a class="back-link" href="<?php echo htmlspecialchars($_adminCssUrl); ?>/auth.php">
            <?php echo htmlspecialchars(__t('reset_go_to_login', '→ Go to login')); ?>
        </a>

    <?php elseif (!$tokenValid): ?>

        <div class="message error"><?php echo $error; ?></div>
        <a class="back-link" href="<?php echo htmlspecialchars($_adminCssUrl); ?>/auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form class="login-form" method="POST"
              action="?reset_token=<?php echo urlencode($rawToken); ?>">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label for="new_password">
                <?php echo htmlspecialchars(__t('reset_new_password_label', 'New password')); ?>
            </label>
            <input type="password" id="new_password" name="new_password"
                   required autofocus autocomplete="new-password">
            <div class="pw-rules" id="pw-rules-new"></div>

            <label for="confirm_password" style="margin-top:14px;">
                <?php echo htmlspecialchars(__t('reset_confirm_password_label', 'Confirm password')); ?>
            </label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password">
            <div class="pw-rules" id="pw-rules-match"></div>

            <button type="submit" class="login-button" style="margin-top:20px;">
                <?php echo htmlspecialchars(__t('reset_submit_btn', 'Set new password')); ?>
            </button>
        </form>

        <a class="back-link" href="<?php echo htmlspecialchars($_adminCssUrl); ?>/auth.php">
            <?php echo htmlspecialchars(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php endif; ?>
</div>

<script>
(function () {
    var S = <?php echo json_encode($jsStrings, JSON_UNESCAPED_UNICODE); ?>;

    var pw   = document.getElementById('new_password');
    var pwc  = document.getElementById('confirm_password');
    var rNew = document.getElementById('pw-rules-new');
    var rMat = document.getElementById('pw-rules-match');
    if (!pw || !rNew) return;

    // Build rule badges
    function badge(id, label) {
        var s = document.createElement('span');
        s.className = 'pw-rule';
        s.id = id;
        s.textContent = label;
        return s;
    }
    rNew.appendChild(badge('r-len',   S.r_len));
    rNew.appendChild(badge('r-up',    S.r_up));
    rNew.appendChild(badge('r-dig',   S.r_dig));
    rNew.appendChild(badge('r-spc',   S.r_spc));
    if (rMat) rMat.appendChild(badge('r-match', S.r_match));

    function rule(id, ok) {
        var el = document.getElementById(id);
        if (el) el.classList.toggle('valid', ok);
    }
    function check() {
        var v = pw.value;
        var c = pwc ? pwc.value : '';
        rule('r-len',   v.length >= 8);
        rule('r-up',    /[A-Z]/.test(v));
        rule('r-dig',   /[0-9]/.test(v));
        rule('r-spc',   /[\W_]/.test(v));
        rule('r-match', v.length > 0 && v === c);
    }
    pw.addEventListener('input', check);
    if (pwc) pwc.addEventListener('input', check);
    check();
})();
</script>
</body>
</html>
