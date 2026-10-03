<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_require_core_functions() {
	include_once dirname(__DIR__, 3) . '/core/data-functions.php';
	include_once dirname(__DIR__, 3) . '/core/core-functions.php';
}
admin_require_core_functions();

function admin_get_username(): string {
	return $_SESSION['admin_username'] ?? 'admin';
}

function admin_get_display_name(): string {
	return $_SESSION['admin_display_name'] ?? admin_get_username();
}

function admin_users_path(): string {
	return dirname(__DIR__, 3) . '/private/users.json';
}

function admin_write_json_atomic(string $path, $data): bool {
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) return false;
	$tmp = $path . '.' . getmypid() . '.tmp';
	if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
	return rename($tmp, $path);
}

function admin_load_users(): array {
	$json  = @file_get_contents(admin_users_path());
	$users = $json !== false ? json_decode($json, true) : null;
	return is_array($users) ? $users : [];
}

function admin_save_users(array $users): bool {
	return admin_write_json_atomic(admin_users_path(), array_values($users));
}

function admin_find_user_by_id(string $id): ?array {
	foreach (admin_load_users() as $user) {
		if (($user['id'] ?? null) === $id) return $user;
	}
	return null;
}

function admin_find_user_by_username(string $username): ?array {
	foreach (admin_load_users() as $user) {
		if (hash_equals(strtolower((string)($user['username'] ?? '')), strtolower($username))) return $user;
	}
	return null;
}

function admin_find_user_by_email(string $email): ?array {
	if ($email === '') return null;
	foreach (admin_load_users() as $user) {
		if (($user['email'] ?? '') !== '' && strtolower($user['email']) === strtolower($email)) return $user;
	}
	return null;
}

function admin_count_admins(array $users, ?string $excludeId = null): int {
	$count = 0;
	foreach ($users as $user) {
		if ($excludeId !== null && ($user['id'] ?? null) === $excludeId) continue;
		if (($user['role'] ?? '') === 'admin') $count++;
	}
	return $count;
}

function admin_create_user(string $username, string $displayName, string $email, string $password, string $role): ?string {
	if (!in_array($role, ['admin', 'editor', 'author'], true)) return null;

	$users = admin_load_users();
	foreach ($users as $user) {
		if (strtolower((string)($user['username'] ?? '')) === strtolower($username)) return null;
		if ($email !== '' && ($user['email'] ?? '') !== '' && strtolower($user['email']) === strtolower($email)) return null;
	}

	$id = bin2hex(random_bytes(8));
	$users[] = [
		'id'            => $id,
		'username'      => $username,
		'display_name'  => $displayName !== '' ? $displayName : $username,
		'email'         => $email,
		'password_hash' => password_hash($password, PASSWORD_DEFAULT),
		'role'          => $role,
		'created_at'    => time(),
	];

	return admin_save_users($users) ? $id : null;
}

function admin_update_user(string $id, array $fields): bool {
	$users = admin_load_users();
	$found = false;

	foreach ($users as &$user) {
		if (($user['id'] ?? null) !== $id) continue;
		$found = true;

		if (isset($fields['username']) && $fields['username'] !== $user['username']) {
			foreach ($users as $other) {
				if (($other['id'] ?? null) !== $id && strtolower((string)($other['username'] ?? '')) === strtolower($fields['username'])) {
					return false;
				}
			}
			$user['username'] = $fields['username'];
		}
		if (isset($fields['email'])) {
			if ($fields['email'] !== '' && $fields['email'] !== $user['email']) {
				foreach ($users as $other) {
					if (($other['id'] ?? null) !== $id && ($other['email'] ?? '') !== '' && strtolower($other['email']) === strtolower($fields['email'])) {
						return false;
					}
				}
			}
			$user['email'] = $fields['email'];
		}
		if (isset($fields['display_name'])) $user['display_name'] = $fields['display_name'];
		if (isset($fields['password']) && $fields['password'] !== '') {
			$user['password_hash'] = password_hash($fields['password'], PASSWORD_DEFAULT);
		}
		if (isset($fields['role']) && $fields['role'] !== $user['role']) {
			if (!in_array($fields['role'], ['admin', 'editor', 'author'], true)) return false;
			if ($user['role'] === 'admin' && admin_count_admins($users, $id) === 0) {
				return false; // this is the last admin — refuse to demote
			}
			$user['role'] = $fields['role'];
		}
		break;
	}
	unset($user);

	if (!$found) return false;
	return admin_save_users($users);
}

function admin_delete_user(string $id): bool {
	$users  = admin_load_users();
	$target = null;
	foreach ($users as $user) {
		if (($user['id'] ?? null) === $id) { $target = $user; break; }
	}
	if ($target === null) return false;
	if (($target['role'] ?? '') === 'admin' && admin_count_admins($users, $id) === 0) {
		return false;
	}

	$users = array_values(array_filter($users, fn($u) => ($u['id'] ?? null) !== $id));
	return admin_save_users($users);
}

function admin_current_user_id(): ?string {
	return $_SESSION['admin_user_id'] ?? null;
}

function admin_current_user_role(): string {
	return $_SESSION['admin_role'] ?? 'admin';
}

function admin_is_admin(): bool {
	return admin_current_user_role() === 'admin';
}

function admin_can_manage_all_content(): bool {
	return in_array(admin_current_user_role(), ['admin', 'editor'], true);
}

function admin_can_edit_item(array $item): bool {
	if (admin_can_manage_all_content()) return true;
	$ownerId = $item['author_id'] ?? null;
	return $ownerId !== null && $ownerId === admin_current_user_id();
}

