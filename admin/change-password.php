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
	echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed']);
	exit;
}

ob_clean();
header('Content-Type: application/json');

include_once 'admin-credentials.php';

$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password']     ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
	echo json_encode(['status' => 'error', 'message' => 'password_fill_all']);
	exit;
}

if ($newPassword !== $confirmPassword) {
	echo json_encode(['status' => 'error', 'message' => 'passwords_dont_match']);
	exit;
}

if (
	mb_strlen($newPassword) < 8 ||
	!preg_match('/[A-Z]/',  $newPassword) ||
	!preg_match('/[0-9]/',  $newPassword) ||
	!preg_match('/[\W_]/',  $newPassword)
) {
	echo json_encode(['status' => 'error', 'message' => 'password_too_weak']);
	exit;
}

if (!password_verify($currentPassword, $admin_password)) {
	echo json_encode(['status' => 'error', 'message' => 'password_current_incorrect']);
	exit;
}

if (password_verify($newPassword, $admin_password)) {
	echo json_encode(['status' => 'error', 'message' => 'password_same_as_current']);
	exit;
}

$ok = admin_save_credentials([
	'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
]);

if (!$ok) {
	echo json_encode(['status' => 'error', 'message' => 'password_update_failed']);
	exit;
}

session_regenerate_id(true);
echo json_encode(['status' => 'success', 'message' => 'password_changed_success']);
exit;
