<?php
ob_start();
session_start();
require_once 'includes/admin-functions.php';

if (!admin_is_logged_in()) {
	ob_clean();
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
	exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
	ob_clean();
	http_response_code(403);
	header('Content-Type: application/json');
	echo json_encode(['status' => 'error', 'message' => __t('auth_csrf_error')]);
	exit;
}

ob_clean();
header('Content-Type: application/json');

$newUsername    = trim($_POST['admin_username']     ?? '');
$newDisplayName = trim($_POST['admin_display_name'] ?? '');
$newEmail       = trim($_POST['admin_email']        ?? '');

if ($newUsername === '') {
	echo json_encode(['status' => 'error', 'message' => __t('profile_username_required')]);
	exit;
}
if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $newUsername)) {
	echo json_encode(['status' => 'error', 'message' => __t('profile_username_invalid')]);
	exit;
}
if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
	echo json_encode(['status' => 'error', 'message' => __t('profile_email_invalid')]);
	exit;
}

$fields = [
	'username'     => $newUsername,
	'display_name' => $newDisplayName,
];
if ($newEmail !== '') {
	$fields['email'] = $newEmail;
}

$ok = admin_save_credentials($fields);
if (!$ok) {
	echo json_encode(['status' => 'error', 'message' => __t('settings_save_failed')]);
	exit;
}

$_SESSION['admin_username']     = $newUsername;
$_SESSION['admin_display_name'] = $newDisplayName ?: $newUsername;

echo json_encode([
	'status'       => 'success',
	'message'      => __t('profile_saved'),
	'display_name' => $_SESSION['admin_display_name'],
]);
exit;