<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_can_edit_draft(array $draftData): bool {
	if (admin_can_manage_all_content()) return true;
	$ownerId = $draftData['admin_user_id'] ?? null;
	return $ownerId !== null && $ownerId === admin_current_user_id();
}

function admin_is_logged_in() {
	if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
		return false;
	}

	$timeout = 2 * 60 * 60; 
	if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
		session_unset();
		session_destroy();
		return false;
	}

	$_SESSION['admin_last_activity'] = time();

	if (!isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_username'])) {
		$_matchedUser = admin_find_user_by_username($_SESSION['admin_username']);
		if ($_matchedUser !== null) {
			$_SESSION['admin_user_id'] = $_matchedUser['id'];
			$_SESSION['admin_role']    = $_matchedUser['role'];
		}
	}

	if (isset($_SESSION['admin_user_id'])) {
		$_currentUser = admin_find_user_by_id($_SESSION['admin_user_id']);
		if ($_currentUser === null) {
			session_unset();
			session_destroy();
			return false;
		}
		$_SESSION['admin_role'] = $_currentUser['role'];
	}

	return true;
}

function admin_load_data() {
	 return sl_admin_load_all();
 }

 function admin_save_data($data) {
	 return sl_admin_save_all($data);
 }

function admin_site_url() {
	$protocol = _sl_request_is_https() ? 'https' : 'http';
	$host = _sl_request_host();
	$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

	return $protocol . '://' . $host . $basePath . '/';
}

function admin_activity_action_label(string $action): string {
	static $labels = [
		'login_success'            => 'activity_action_login_success',
		'login_failed'             => 'activity_action_login_failed',
		'template_save'            => 'activity_action_template_save',
		'template_restore'         => 'activity_action_template_restore',
		'theme_install'            => 'activity_action_theme_install',
		'extension_install'        => 'activity_action_extension_install',
		'extension_uninstall'      => 'activity_action_extension_uninstall',
		'extension_activate'       => 'activity_action_extension_activate',
		'extension_deactivate'     => 'activity_action_extension_deactivate',
		'extension_update'         => 'activity_action_extension_update',
		'user_created'             => 'activity_action_user_created',
		'user_updated'             => 'activity_action_user_updated',
		'user_deleted'             => 'activity_action_user_deleted',
		'item_restored_from_trash' => 'activity_action_item_restored_from_trash',
		'revision_restored'        => 'activity_action_revision_restored',
		'revision_deleted'         => 'activity_action_revision_deleted',
		'backup_restored'          => 'activity_action_backup_restored',
	];
	return __t($labels[$action] ?? '', $action);
}

function admin_format_file_size($bytes) {
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	
	return round($bytes, 2) . ' ' . $units[$pow];
}

function admin_front_url_slug(string $type): string {
	if (sl_content_type_exists($type)) {
		return sl_type_url_slug($type, false);
	}
	foreach (sl_all_type_slugs() as $_aflBaseType) {
		if ($type === $_aflBaseType . 's') {
			return sl_type_url_slug($_aflBaseType, true);
		}
	}

	static $strings = null;
	if ($strings === null) {
		$settingsFile = _lang_cms_root() . '/config.json';
		$locale = 'en';
		if (file_exists($settingsFile)) {
			$s = json_decode(file_get_contents($settingsFile), true);
			if (is_array($s) && !empty($s['active_language'])) {
				$locale = $s['active_language'];
			}
		}
		$langFile = _lang_cms_root() . '/lang/front/' . $locale . '.json';
		if (!file_exists($langFile)) {
			$langFile = _lang_cms_root() . '/lang/front/en.json';
		}
		$decoded  = json_decode(file_get_contents($langFile), true);
		$strings  = is_array($decoded) ? $decoded : [];
	}

	$key = 'url_slug_' . $type;
	$raw = $strings[$key] ?? $type;
	return sanitizeSlug($raw);
}

function admin_content_url($contentType, $slug, $customSlug = '', $category = '') {
	$baseUrl      = admin_site_url();
	$finalSlug    = !empty($customSlug) ? $customSlug : $slug;
	$categorySlug = !empty($category) ? sanitizeSlug($category) : '';

	$catPath = '';
	if (!empty($categorySlug)) {
		static $_acu_data = null;
		if ($_acu_data === null) $_acu_data = admin_load_data();
		$catPath = getCategoryPath($categorySlug, $_acu_data);
	}

	if ($contentType === 'page') {
		if (!empty($catPath)) {
			return $baseUrl . $catPath . '/' . $finalSlug . '/';
		}
		return $baseUrl . $finalSlug . '/';
	}

	if ($contentType === 'article') {
		if (!empty($catPath)) {
			return $baseUrl . $catPath . '/' . $finalSlug . '/';
		}
		return $baseUrl . admin_front_url_slug('article') . '/' . $finalSlug . '/';
	}

	if ($contentType === 'project') {
		if (!empty($catPath)) {
			return $baseUrl . admin_front_url_slug('project') . '/' . $catPath . '/' . $finalSlug . '/';
		}
		return $baseUrl . admin_front_url_slug('project') . '/' . $finalSlug . '/';
	}

	if (!empty($catPath)) {
		return $baseUrl . admin_front_url_slug($contentType) . '/' . $catPath . '/' . $finalSlug . '/';
	}
	return $baseUrl . admin_front_url_slug($contentType) . '/' . $finalSlug . '/';
}

