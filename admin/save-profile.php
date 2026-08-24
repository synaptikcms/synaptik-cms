<?php
ob_start();
require_once __DIR__ . '/includes/session-config.php';
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

$currentUser = admin_find_user_by_id((string)admin_current_user_id());
if ($currentUser === null) {
	echo json_encode(['status' => 'error', 'message' => __t('settings_save_failed')]);
	exit;
}

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
if ($newDisplayName !== '' && (mb_strlen($newDisplayName) > 60
	|| strpos($newDisplayName, '<') !== false || strpos($newDisplayName, '>') !== false
	|| strpos($newDisplayName, chr(34)) !== false
	|| strpos($newDisplayName, chr(92)) !== false)) {
	echo json_encode(['status' => 'error', 'message' => __t('profile_display_name_invalid')]);
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

$ok = admin_update_user($currentUser['id'], $fields);
if (!$ok) {
	echo json_encode(['status' => 'error', 'message' => __t('profile_username_taken')]);
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