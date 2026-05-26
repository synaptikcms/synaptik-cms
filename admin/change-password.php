<?php
ob_start();
session_start();
require_once 'includes/admin-functions.php';

// 1. Authentification obligatoire — le CSRF seul ne suffit pas
if (!admin_is_logged_in()) {
	ob_clean();
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
	exit;
}

// 2. CSRF token — hash_equals() résistant aux timing attacks
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
	echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
	exit;
}

if ($newPassword !== $confirmPassword) {
	echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
	exit;
}

// Enforce password complexity: min 8 chars, 1 uppercase, 1 digit, 1 special character
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

// Reject if the new password is identical to the current one
if (password_verify($newPassword, $admin_password)) {
	echo json_encode(['status' => 'error', 'message' => 'password_same_as_current']);
	exit;
}

$newHashedPassword  = password_hash($newPassword, PASSWORD_BCRYPT);
$credentialsContent = "<?php\n// Admin password (hashed)\n\$admin_password = '" . $newHashedPassword . "';\n?>";
$writeResult        = file_put_contents('admin-credentials.php', $credentialsContent);

if ($writeResult === false) {
	echo json_encode(['status' => 'error', 'message' => 'password_update_failed']);
	exit;
}

// 3. Régénération de session après changement de credentials
session_regenerate_id(true);

echo json_encode(['status' => 'success', 'message' => 'password_changed_success']);
exit;