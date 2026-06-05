<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'includes/admin-functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'logout') {
	session_unset();
	session_destroy();
	header('Location: auth.php');
	exit;
}

include_once 'admin-credentials.php';
$hashed_password    = isset($admin_password)     ? $admin_password     : '';
$stored_username    = isset($admin_username)     ? $admin_username     : 'admin';
$stored_displayname = isset($admin_display_name) ? $admin_display_name : $stored_username;

$max_attempts = 5;
$lockout_time = 15 * 60;

// IP-based rate limiting
// If behind Cloudflare, define TRUSTED_PROXY_IP and uncomment the relevant block below.
function get_client_ip(): string {
	// Uncomment for Cloudflare:
	// if (defined('TRUSTED_PROXY_IP') && $_SERVER['REMOTE_ADDR'] === TRUSTED_PROXY_IP) {
	// 	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	// 		return trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
	// 	}
	// }

	// Uncomment for standard reverse proxy (nginx, HAProxy…):
	// if (defined('TRUSTED_PROXY_IP') && $_SERVER['REMOTE_ADDR'] === TRUSTED_PROXY_IP) {
	// 	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// 		return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
	// 	}
	// }

	return $_SERVER['REMOTE_ADDR'];
}

$ip      = get_client_ip();
$now     = time();
$rateFile = dirname(__DIR__) . '/private/auth_rate.json';
$ipKey   = hash('sha256', $ip);

// Load the centralized rate file and purge stale entries to keep it small.
// Retained: currently locked IPs, recently unlocked (within 24 h — preserves
// the re-lock penalty for persistent attackers), and IPs accumulating attempts.
$rateData = [];
if (file_exists($rateFile)) {
	$_raw     = file_get_contents($rateFile);
	$_decoded = ($_raw !== false) ? json_decode($_raw, true) : null;
	if (is_array($_decoded)) {
		foreach ($_decoded as $_k => $_entry) {
			$_expiry = (int)($_entry['lockout_until'] ?? 0);
			if ($_expiry > $now) {
				$rateData[$_k] = $_entry; // actively locked
			} elseif ($_expiry > 0 && ($now - $_expiry) < 86400) {
				$rateData[$_k] = $_entry; // recently unlocked — keep re-lock penalty
			} elseif ($_expiry === 0 && ($_entry['attempts'] ?? 0) > 0) {
				$rateData[$_k] = $_entry; // accumulating attempts, not yet locked
			}
			// else: clean or fully stale — drop it
		}
	}
}

$lockData = $rateData[$ipKey] ?? ['attempts' => 0, 'lockout_until' => 0];

$is_locked        = false;
$remaining_time   = 0;
$remaining_minutes = 0;

if ($lockData['lockout_until'] > $now) {
	$is_locked        = true;
	$remaining_time   = $lockData['lockout_until'] - time();
	$remaining_minutes = ceil($remaining_time / 60);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$error = __t('auth_csrf_error');
	} else {
		$username = trim($_POST['username'] ?? '');
		$password = $_POST['password'] ?? '';

		// Both username and password must match
		$usernameOk = hash_equals($stored_username, $username);

		if ($usernameOk && $hashed_password && password_verify($password, $hashed_password)) {
			// Reset lockout on success — remove entry from centralized file
			unset($rateData[$ipKey]);
			@file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

			$_SESSION['admin']               = true;
			$_SESSION['admin_last_activity']  = time();
			$_SESSION['admin_username']       = $stored_username;
			$_SESSION['admin_display_name']   = $stored_displayname;

			session_regenerate_id(true);

			header('Location: index.php');
			exit;
		} else {
			$lockData['attempts']++;
			if ($lockData['attempts'] >= $max_attempts) {
				$lockData['lockout_until'] = $now + $lockout_time;
				$is_locked        = true;
				$remaining_minutes = $lockout_time / 60;
				$error = str_replace('%d', $remaining_minutes, __t('auth_locked_error'));
			} else {
				$remaining_attempts = $max_attempts - $lockData['attempts'];
				$error = str_replace('%d', $remaining_attempts, __t('auth_invalid_creds'));
			}
			$rateData[$ipKey] = $lockData;
			@file_put_contents($rateFile, json_encode($rateData), LOCK_EX);
		}
	}
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="<?php echo lang_current(); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php _e('auth_title'); ?></title>
	<link rel="stylesheet" href="css/admin-base.css">
</head>
<body>
	<div class="login-container">
		<div class="login-header">
			<h1><?php _e('auth_title'); ?></h1>
		</div>

		<?php if ($error): ?>
			<div class="message error"><?php echo $error; ?></div>
		<?php endif; ?>

		<?php if ($is_locked): ?>
			<div class="message error">
				<?php echo str_replace('%d', $remaining_minutes, __t('auth_lockout_msg')); ?>
			</div>
		<?php else: ?>
			<form class="login-form" method="post" action="">
				<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

				<label for="username"><?php _e('auth_username_label'); ?></label>
				<input type="text" id="username" name="username" required autofocus
				       autocomplete="username" spellcheck="false">

				<label for="password"><?php _e('auth_password_label'); ?></label>
				<input type="password" id="password" name="password" required
				       autocomplete="current-password">

				<button type="submit" class="login-button"><?php _e('auth_login_btn'); ?></button>
			</form>
		<?php endif; ?>

		<div style="text-align: center; margin-top: 20px;">
			<a href="<?php echo admin_site_url(); ?>"><?php _e('auth_return'); ?></a>
			&nbsp;&middot;&nbsp;
			<a href="forgot-password.php"><?php echo __t('auth_forgot_password', 'Forgot password?'); ?></a>
		</div>
	</div>

	<script>
		<?php if ($is_locked): ?>
		setTimeout(function() { window.location.reload(); }, <?php echo $remaining_time * 1000; ?>);
		<?php endif; ?>
	</script>
</body>
</html>
