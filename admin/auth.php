<?php
session_start();
// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'includes/admin-functions.php';

// Determine the action (login or logout)
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle logout
if ($action === 'logout') {
	// Clear session and redirect to login
	session_unset();
	session_destroy();
	header('Location: auth.php');
	exit;
}

// Include admin credentials
include_once 'admin-credentials.php';
$hashed_password = isset($admin_password) ? $admin_password : '';

// Track login attempts
$max_attempts = 5;
$lockout_time = 15 * 60;

// IP-based rate limiting
// ⚠️  If you get behing Cloudflare, define TRUSTED_PROXY_IP with proxy server IP (or Cloudflare CIDR) and uncomment this block.
// define('TRUSTED_PROXY_IP', '103.21.244.0'); // exemple IP Cloudflare

function get_client_ip(): string {
	// Uncomment if you use Cloudflare (most reliable header on CF side)
	// if (defined('TRUSTED_PROXY_IP') && $_SERVER['REMOTE_ADDR'] === TRUSTED_PROXY_IP) {
	// 	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	// 		return trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
	// 	}
	// }

	// Uncomment if you use a standard reverse proxy (nginx, HAProxy…)
	// if (defined('TRUSTED_PROXY_IP') && $_SERVER['REMOTE_ADDR'] === TRUSTED_PROXY_IP) {
	// 	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// 		return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
	// 	}
	// }

	// Default : direct access (local, MAMP, hosting without proxy)
	return $_SERVER['REMOTE_ADDR'];
}

$ip = get_client_ip();
$lockFile = sys_get_temp_dir() . '/cms_lock_' . md5($ip) . '.json';

// Load existing attempt data for this IP, or start fresh
if (file_exists($lockFile)) {
	$lockData = json_decode(file_get_contents($lockFile), true);
} else {
	$lockData = ['attempts' => 0, 'lockout_until' => 0];
}

$is_locked = false;
$remaining_time = 0;
$remaining_minutes = 0;

if ($lockData['lockout_until'] > time()) {
	$is_locked = true;
	$remaining_time = $lockData['lockout_until'] - time();
	$remaining_minutes = ceil($remaining_time / 60);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
	// Validate CSRF token
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$error = __t('auth_csrf_error');
	} else {
		$password = $_POST['password'];
		
		if (isset($hashed_password) && $hashed_password && password_verify($password, $hashed_password)) {
			// Reset IP lockout on success
			$lockData = ['attempts' => 0, 'lockout_until' => 0];
			file_put_contents($lockFile, json_encode($lockData));
			
			// Set admin session
			$_SESSION['admin'] = true;
			$_SESSION['admin_last_activity'] = time();
			
			// Regenerate session ID for security
			session_regenerate_id(true);
			
			header('Location: index.php');
			exit;
		} else {
			// Increment login attempts
			$lockData['attempts']++;
			if ($lockData['attempts'] >= $max_attempts) {
				$lockData['lockout_until'] = time() + $lockout_time;
				$is_locked = true;
				$remaining_minutes = $lockout_time / 60;
				$error = str_replace('%d', $remaining_minutes, __t('auth_locked_error'));
			} else {
				$remaining_attempts = $max_attempts - $lockData['attempts'];
				$error = str_replace('%d', $remaining_attempts, __t('auth_invalid_creds'));
			}
			file_put_contents($lockFile, json_encode($lockData));
		}
	}
}

// Generate CSRF token
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
				
				<label for="password"><?php _e('auth_password_label'); ?></label>
				<input type="password" id="password" name="password" required autofocus>
				
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
		// Auto-refresh the page when lockout period is over
		<?php if ($is_locked): ?>
		setTimeout(function() {
			window.location.reload();
		}, <?php echo $remaining_time * 1000; ?>);
		<?php endif; ?>
	</script>
</body>
</html>