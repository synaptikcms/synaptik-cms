<?php
/**
 * manage-users.php — SynaptikCMS user account management (admin-only)
 *
 * POST handler for create/update/delete/role-change on private/users.json
 * entries. Renders no output of its own — always redirects back to
 * index.php?action=users with a flash message.
 */
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';

if (!admin_is_logged_in()) {
	header('Location: auth.php');
	exit;
}
if (!admin_is_admin()) {
	http_response_code(403);
	exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: index.php?action=users');
	exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
	$_SESSION['error'] = __t('auth_csrf_error');
	header('Location: index.php?action=users');
	exit;
}

$userAction = $_POST['user_action'] ?? '';

function _users_password_ok(string $password): bool {
	return mb_strlen($password) >= 8
		&& preg_match('/[A-Z]/', $password)
		&& preg_match('/[0-9]/', $password)
		&& preg_match('/[\W_]/', $password);
}

if ($userAction === 'add') {

	$username    = trim($_POST['username']     ?? '');
	$displayName = trim($_POST['display_name'] ?? '');
	$email       = trim($_POST['email']        ?? '');
	$password    = $_POST['password']          ?? '';
	$role        = $_POST['role']              ?? '';

	if ($username === '' || !preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $username)) {
		$_SESSION['error'] = __t('profile_username_invalid');
	} elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['error'] = __t('profile_email_invalid');
	} elseif (!_users_password_ok($password)) {
		$_SESSION['error'] = __t('password_too_weak');
	} elseif (!in_array($role, ['admin', 'editor', 'author'], true)) {
		$_SESSION['error'] = __t('users_invalid_role');
	} else {
		$newId = admin_create_user($username, $displayName, $email, $password, $role);
		$_SESSION[$newId !== null ? 'message' : 'error'] =
			$newId !== null ? __t('users_created') : __t('profile_username_taken');
		if ($newId !== null) sl_admin_log_activity('user_created', $username . ' (' . $role . ')');
	}

} elseif ($userAction === 'edit') {

	$userId      = $_POST['user_id']           ?? '';
	$username    = trim($_POST['username']     ?? '');
	$displayName = trim($_POST['display_name'] ?? '');
	$email       = trim($_POST['email']        ?? '');
	$password    = $_POST['password']          ?? '';
	$role        = $_POST['role']              ?? '';

	$target = $userId !== '' ? admin_find_user_by_id($userId) : null;

	if ($target === null) {
		$_SESSION['error'] = __t('users_not_found');
	} elseif ($username === '' || !preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $username)) {
		$_SESSION['error'] = __t('profile_username_invalid');
	} elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['error'] = __t('profile_email_invalid');
	} elseif ($password !== '' && !_users_password_ok($password)) {
		$_SESSION['error'] = __t('password_too_weak');
	} elseif (!in_array($role, ['admin', 'editor', 'author'], true)) {
		$_SESSION['error'] = __t('users_invalid_role');
	} else {
		$fields = [
			'username'     => $username,
			'display_name' => $displayName,
			'email'        => $email,
			'role'         => $role,
		];
		if ($password !== '') $fields['password'] = $password;

		$ok = admin_update_user($target['id'], $fields);
		if (!$ok) {
			$_SESSION['error'] = __t('users_update_failed');
		} else {
			$_SESSION['message'] = __t('users_updated');
			sl_admin_log_activity('user_updated', $username . ' (' . $role . ')');
			// Keep the live session in sync when the admin edits their own account.
			if ($target['id'] === admin_current_user_id()) {
				$_SESSION['admin_username']     = $username;
				$_SESSION['admin_display_name'] = $displayName ?: $username;
				$_SESSION['admin_role']         = $role;
			}
		}
	}

} elseif ($userAction === 'delete') {

	$userId = $_POST['user_id'] ?? '';
	$target = $userId !== '' ? admin_find_user_by_id($userId) : null;

	if ($target === null) {
		$_SESSION['error'] = __t('users_not_found');
	} elseif ($userId === admin_current_user_id()) {
		$_SESSION['error'] = __t('users_cannot_delete_self');
	} else {
		$ok = admin_delete_user($userId);
		$_SESSION[$ok ? 'message' : 'error'] = $ok ? __t('users_deleted') : __t('users_delete_last_admin');
		if ($ok) sl_admin_log_activity('user_deleted', $target['username'] ?? $userId);
	}
}

header('Location: index.php?action=users');
exit;
