<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once __DIR__ . '/includes/admin-functions.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$tokenFile = dirname(__DIR__) . '/private/reset_token.json';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function _reset_token_find_user_id(string $rawToken, string $tokenFile): ?string
{
    if ($rawToken === '' || !file_exists($tokenFile)) return null;
    $stored = json_decode(file_get_contents($tokenFile), true);
    if (!is_array($stored)) return null;
    foreach ($stored as $entry) {
        if (
            is_array($entry)
            && !empty($entry['token_hash'])
            && !empty($entry['user_id'])
            && !empty($entry['expires_at'])
            && $entry['expires_at'] > time()
            && hash_equals($entry['token_hash'], hash('sha256', $rawToken))
        ) {
            return $entry['user_id'];
        }
    }
    return null;
}

function _reset_token_consume(string $rawToken, string $tokenFile): void
{
    if (!file_exists($tokenFile)) return;
    $_lockFp = @fopen($tokenFile, 'c+');
    if (!$_lockFp) return;
    flock($_lockFp, LOCK_EX);
    $_raw     = stream_get_contents($_lockFp);
    $_decoded = ($_raw !== false && $_raw !== '') ? json_decode($_raw, true) : null;
    $tokens   = [];
    if (is_array($_decoded)) {
        $targetHash = hash('sha256', $rawToken);
        foreach ($_decoded as $entry) {
            if (is_array($entry) && ($entry['token_hash'] ?? '') === $targetHash) continue;
            $tokens[] = $entry;
        }
    }
    ftruncate($_lockFp, 0);
    rewind($_lockFp);
    fwrite($_lockFp, json_encode($tokens, JSON_PRETTY_PRINT));
    flock($_lockFp, LOCK_UN);
    fclose($_lockFp);
}

$rawToken       = trim($_GET['reset_token'] ?? '');
$resetUserId    = _reset_token_find_user_id($rawToken, $tokenFile);
$tokenValid     = $resetUserId !== null;
$error          = '';
$success        = false;

if (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = __t('reset_link_invalid', 'This reset link is invalid or has expired.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error      = __t('auth_csrf_error', 'Invalid security token. Please start over.');
        $tokenValid = false;
    }

    if (empty($error) && $resetUserId === null) {
        $error      = __t('reset_link_expired', 'Your reset link has expired.')
                    . ' <a href="forgot-password.php">'
                    . hsc(__t('reset_request_new', 'request a new one'))
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
            $resetUser = admin_find_user_by_id($resetUserId);

            if ($resetUser === null) {
                $error = __t('reset_link_expired', 'Your reset link has expired.');
            } elseif (password_verify($newPassword, $resetUser['password_hash'] ?? '')) {
                $error = __t('reset_same_password', 'Your new password must be different from your current password.');
            } else {
                $ok = admin_update_user($resetUser['id'], ['password' => $newPassword]);

                if ($ok) {
                    _reset_token_consume($rawToken, $tokenFile);
                    $success = true;
                } else {
                    $error = __t('password_update_failed',
                        'Could not save the new password. Check write permissions on private/users.json.');
                }
            }
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    <title><?php echo hsc(__t('reset_new_pwd_heading', 'Set New Password')); ?> — Synaptik CMS</title>
    <?php
    $_scheme      = _sl_request_is_https() ? 'https' : 'http';
    $_adminCssUrl = $_scheme . '://' . _sl_request_host()
                  . str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', rtrim(__DIR__, '/'));
    ?>
    <script src="<?php echo hsc($_adminCssUrl); ?>/assets/js/theme-boot.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/theme-boot.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo hsc($_adminCssUrl); ?>/assets/css/admin-base.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/admin-base.css'); ?>">
    <style>
        .login-container { max-width: 420px; }
        .pw-rules { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .pw-rule {
            font-size: .78rem; padding: 2px 9px; border-radius: var(--radius-sm);
            background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);
            transition: all .2s;
        }
        .pw-rule.valid { background: var(--primary-soft); color: var(--primary-text); border-color: var(--primary); }
        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 0.875rem; }
        .message a { color: inherit; font-weight: 600; }
    </style>
</head>
<body class="auth-page" style="background-color: var(--surface2);">
<div class="login-container">
    <div class="login-header">
        <h1><?php echo hsc(__t('reset_new_pwd_heading', 'Set New Password')); ?></h1>
    </div>

    <?php if ($success): ?>

        <div class="blockquote success" style="text-align:center;">
            <strong><?php echo hsc(__t('reset_success_title', 'Password updated.')); ?></strong><br>
            <?php echo hsc(__t('reset_success_detail', 'You can now log in with your new password.')); ?>
        </div>
        <a class="back-link" href="<?php echo hsc($_adminCssUrl); ?>/auth.php">
            <?php echo hsc(__t('reset_go_to_login', '→ Go to login')); ?>
        </a>

    <?php elseif (!$tokenValid): ?>

        <div class="blockquote error"><?php echo $error; ?></div>
        <a class="back-link" href="<?php echo hsc($_adminCssUrl); ?>/auth.php">
            <?php echo hsc(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="blockquote error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form class="login-form" method="POST"
              action="?reset_token=<?php echo urlencode($rawToken); ?>">
            <input type="hidden" name="csrf_token"
                   value="<?php echo hsc($_SESSION['csrf_token']); ?>">

            <label for="new_password">
                <?php echo hsc(__t('reset_new_password_label', 'New password')); ?>
            </label>
            <input type="password" id="new_password" name="new_password"
                   required autofocus autocomplete="new-password">
            <div class="pw-rules" id="pw-rules-new"></div>

            <label for="confirm_password" style="margin-top:14px;">
                <?php echo hsc(__t('reset_confirm_password_label', 'Confirm password')); ?>
            </label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password">
            <div class="pw-rules" id="pw-rules-match"></div>

            <button type="submit" class="btn btn-primary btn-lg btn-block login-button" style="margin-top:20px;">
                <?php echo hsc(__t('reset_submit_btn', 'Set new password')); ?>
            </button>
        </form>

        <a class="back-link" href="<?php echo hsc($_adminCssUrl); ?>/auth.php">
            <?php echo hsc(__t('reset_back_to_login', '← Back to login')); ?>
        </a>

    <?php endif; ?>
</div>

<script type="application/json" id="reset-password-strings"><?php echo json_encode($jsStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?></script>
<script src="<?php echo hsc($_adminCssUrl); ?>/assets/js/reset-password.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/reset-password.js'); ?>"></script>
</body>
</html>