<?php
if (!defined('INCLUDED')) define('INCLUDED', true);
require_once __DIR__ . '/admin-icons.php';
require_once __DIR__ . '/content-purify.php';

if (!function_exists('hsc')) {
    function hsc(?string $s, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $enc = 'UTF-8'): string
    {
        return htmlspecialchars((string) ($s ?? ''), $flags, $enc);
    }
}

if (!function_exists('themePreviewSecret')) {
    function themePreviewSecret(): string {
        $secretFile = dirname(__DIR__, 2) . '/private/theme_preview.secret';
        if (file_exists($secretFile)) {
            $secret = file_get_contents($secretFile);
            if ($secret !== false && strlen(trim($secret)) >= 32) return trim($secret);
        }
        $secret = bin2hex(random_bytes(32));
        @file_put_contents($secretFile, $secret, LOCK_EX);
        return $secret;
    }
}

if (!function_exists('sl_type_label')) {
    function sl_type_label(string $type, bool $plural = false): string {
        $settings = admin_load_config();
        $override = $settings['type_labels'][$type][$plural ? 'plural' : 'singular'] ?? '';
        if ($override !== '') return $override;
        return __t($plural ? $type . 's' : $type, ucfirst($type));
    }
}

if (!function_exists('sanitizeFileName')) {
	function sanitizeFileName($filename) {
		$filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
		$filename = substr($filename, 0, 255);
		if (empty($filename)) {
			$filename = 'unnamed_file_' . time();
		}
		return $filename;
	}
}

if (!function_exists('sanitizeSlug')) {
	function sanitizeSlug($string) {
		$string = trim($string);
		$accents = [
			'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
			'Ç'=>'C',
			'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
			'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
			'Ð'=>'D','Ñ'=>'N',
			'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
			'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
			'Ý'=>'Y','Þ'=>'TH','ß'=>'ss',
			'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
			'ç'=>'c',
			'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
			'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
			'ð'=>'d','ñ'=>'n',
			'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
			'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
			'ý'=>'y','þ'=>'th','ÿ'=>'y',
			'œ'=>'oe','Œ'=>'OE','Ÿ'=>'Y',
		];
		$string = strtr($string, $accents);
		$string = strtolower($string);
		$string = preg_replace('/\s+/', '-', $string);
		$string = preg_replace('/[^a-z0-9\-_]/', '', $string);
		$string = preg_replace('/-+/', '-', $string);
		$string = trim($string, '-_');
		return $string;
	}
}

function admin_load_config(): array {
	$settings = loadDefaultConfig();

	$configFile = dirname(dirname(__DIR__)) . '/config.json';
	if (file_exists($configFile)) {
		$loaded = json_decode(file_get_contents($configFile), true);
		if (is_array($loaded)) {
			$settings = array_merge($settings, $loaded);
			if (!empty($settings['timezone'])) {
				@date_default_timezone_set($settings['timezone']);
			}
		}
	}

	$settings['available_themes'] = function_exists('getAvailableThemes') ? getAvailableThemes() : ['default'];

	return $settings;
}

function admin_add_custom_field(string $type, string $label, string $fieldType): array|false {
	if (!in_array($type, ['article', 'page', 'project'], true)) return false;

	$label = trim($label);
	if ($label === '') return false;

	$allowedTypes = ['text', 'textarea', 'number', 'url', 'checkbox'];
	if (!in_array($fieldType, $allowedTypes, true)) $fieldType = 'text';

	$baseKey = sanitizeSlug($label);
	if ($baseKey === '') return false;

	$config = admin_load_config();
	$schema = $config['custom_fields_schema'][$type] ?? [];

	$existingKeys = array_column($schema, 'key');
	$key = $baseKey;
	$i = 2;
	while (in_array($key, $existingKeys, true)) {
		$key = $baseKey . '_' . $i;
		$i++;
	}

	$field = [
		'key'      => $key,
		'label'    => hsc($label),
		'type'     => $fieldType,
		'required' => false,
	];

	$schema[] = $field;
	$config['custom_fields_schema'][$type] = $schema;

	$configPath = dirname(__DIR__, 2) . '/config.json';
	if (!_sl_write_json($configPath, $config)) return false;

	return $field;
}

function admin_get_pinned_plugins(): array {
	$config = admin_load_config();
	$pinned = $config['pinned_plugins'] ?? [];
	return is_array($pinned) ? array_values(array_unique($pinned)) : [];
}

function admin_set_plugin_pinned(string $slug, bool $pinned): bool {
	$config = admin_load_config();
	$current = is_array($config['pinned_plugins'] ?? null) ? $config['pinned_plugins'] : [];

	if ($pinned) {
		if (!in_array($slug, $current, true)) $current[] = $slug;
	} else {
		$current = array_values(array_diff($current, [$slug]));
	}

	$config['pinned_plugins'] = $current;

	$configPath = dirname(__DIR__, 2) . '/config.json';
	return _sl_write_json($configPath, $config);
}

function admin_format_date($date) {
	if (empty($date)) return '';

	$appSettings = admin_load_config();
	$format      = $appSettings['date_format'] ?? 'Y-m-d';

	$timestamp = strtotime($date);
	if ($timestamp === false) return $date;

	return date($format, $timestamp);
}

function admin_format_time($date) {
	if (empty($date)) return '';

	if (!preg_match('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $date)) return '';

	$timestamp = strtotime($date);
	if ($timestamp === false) return '';

	return date('H:i', $timestamp);
}

function admin_extract_time(string $date = '', bool $defaultNow = false): string {
	if (!empty($date) && preg_match('/\d{4}-\d{2}-\d{2}[T ](?P<t>\d{2}:\d{2})/', $date, $m)) {
		return $m['t'];
	}
	return $defaultNow ? date('H:i') : '';
}

function admin_diff_lines(string $old, string $new): ?array {
	if ($old === $new) return [];

	$oldLines = $old === '' ? [] : explode("\n", $old);
	$newLines = $new === '' ? [] : explode("\n", $new);
	$m = count($oldLines);
	$n = count($newLines);

	if ($m * $n > 4_000_000) return null;

	$lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
	for ($i = $m - 1; $i >= 0; $i--) {
		for ($j = $n - 1; $j >= 0; $j--) {
			$lcs[$i][$j] = $oldLines[$i] === $newLines[$j]
				? $lcs[$i + 1][$j + 1] + 1
				: max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
		}
	}

	$result = [];
	$i = 0; $j = 0;
	while ($i < $m && $j < $n) {
		if ($oldLines[$i] === $newLines[$j]) {
			$result[] = ['type' => 'same', 'text' => $oldLines[$i]];
			$i++; $j++;
		} elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
			$result[] = ['type' => 'removed', 'text' => $oldLines[$i]];
			$i++;
		} else {
			$result[] = ['type' => 'added', 'text' => $newLines[$j]];
			$j++;
		}
	}
	while ($i < $m) { $result[] = ['type' => 'removed', 'text' => $oldLines[$i]]; $i++; }
	while ($j < $n) { $result[] = ['type' => 'added', 'text' => $newLines[$j]]; $j++; }

	return $result;
}

function admin_require_core_functions() {
	include_once dirname(dirname(__DIR__)) . '/core/data-functions.php';
	include_once dirname(dirname(__DIR__)) . '/core/core-functions.php';
}
admin_require_core_functions();

function admin_get_username(): string {
	return $_SESSION['admin_username'] ?? 'admin';
}

function admin_get_display_name(): string {
	return $_SESSION['admin_display_name'] ?? admin_get_username();
}

function admin_users_path(): string {
	return dirname(__DIR__, 2) . '/private/users.json';
}

function admin_write_json_atomic(string $path, $data): bool {
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) return false;
	$tmp = $path . '.tmp';
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

function admin_build_content_item_from_post(
	string $type,
	array $post,
	?array $existingItem,
	array $existingIndexForDedup = [],
	array $files = []
): array {
	$title = $post['title'] ?? '';

	$contentFormatSubmitted = $post['content_format'] ?? '';
	$contentFormat = in_array($contentFormatSubmitted, ['html', 'markdown'], true)
		? $contentFormatSubmitted
		: ($existingItem['content_format'] ?? 'html');
	$content = isset($post['content']) && $post['content'] !== ''
		? ($contentFormat === 'markdown' ? admin_purify_markdown($post['content']) : admin_purify_html($post['content']))
		: '';

	$slug       = sanitizeSlug($title);
	$customSlug = !empty($post['custom_slug']) ? sanitizeSlug($post['custom_slug'], true) : '';

	$slugRenamedTo = null;
	if (!empty($existingIndexForDedup)) {
		$existingSlugs = array_map(
			fn($entry) => !empty($entry['custom_slug']) ? $entry['custom_slug'] : ($entry['slug'] ?? ''),
			$existingIndexForDedup
		);
		$effectiveSlug = $customSlug !== '' ? $customSlug : $slug;
		if (in_array($effectiveSlug, $existingSlugs, true)) {
			$base = $effectiveSlug;
			$n    = 2;
			while (in_array($base . '-' . $n, $existingSlugs, true)) $n++;
			$uniqueSlug    = $base . '-' . $n;
			$slugRenamedTo = $uniqueSlug;
			if ($customSlug !== '') { $customSlug = $uniqueSlug; } else { $slug = $uniqueSlug; }
		}
	}

	$tags       = [];
	$newTags    = [];
	if (!empty($post['tags'])) {
		$tagsStore = sl_load_tags();
		foreach (explode(',', $post['tags']) as $tagInput) {
			$displayName = trim($tagInput);
			if ($displayName === '') continue;
			$tagSlug = sanitizeSlug($displayName);
			if ($tagSlug === '') continue;
			$tags[] = $tagSlug;
			if (!isset($tagsStore[$tagSlug])) $newTags[$tagSlug] = $displayName;
		}
	}

	$category    = '';
	$newCategory = null;
	if (isset($post['category']) && trim($post['category']) !== '') {
		$displayCat = trim($post['category']);
		$catSlug    = sanitizeSlug($displayCat);
		if ($catSlug !== '') {
			$category = $catSlug;
			if (!isset(sl_load_categories()[$catSlug])) $newCategory = [$catSlug => $displayCat];
		}
	}

	$item = [
		'title'                => $title,
		'author_id'            => $existingItem['author_id'] ?? admin_current_user_id(),
		'slug'                 => $slug,
		'custom_slug'          => $customSlug,
		'content'              => $content,
		'meta_title'           => trim($post['meta_title'] ?? ''),
		'meta_description'     => trim($post['meta_description'] ?? ''),
		'meta_keywords'        => trim($post['meta_keywords'] ?? ''),
		'canonical_url'        => trim($post['canonical_url'] ?? ''),
		'schema_type'          => trim($post['schema_type'] ?? ''),
		'og_title'             => trim($post['og_title'] ?? ''),
		'og_description'       => trim($post['og_description'] ?? ''),
		'og_image'             => trim($post['og_image'] ?? ''),
		'show_featured_image'  => isset($post['show_featured_image']),
		'show_date'            => isset($post['show_date']),
		'show_title'           => isset($post['show_title']),
		'show_related_items'   => isset($post['show_related_items']),
		'gallery_layout'       => $post['gallery_layout'] ?? 'grid',
		'category'             => $category,
		'tags'                 => $tags,
		'show_tags_at_bottom'  => isset($post['show_tags_at_bottom']),
		'content_format'       => $contentFormat,
	];

	if (!empty($post['remove_featured_image'])) {
	} elseif (!empty($post['selected_image_path'])) {
		$selectedImagePath = $post['selected_image_path'];
		if (strpos($selectedImagePath, 'files/') !== 0) {
			$selectedImagePath = 'files/' . ltrim($selectedImagePath, '/');
		}
		$item['image'] = $selectedImagePath;
	} elseif (isset($files['image']) && ($files['image']['error'] ?? 1) === 0) {
		$uploadedImagePath = handleImageUpload($files['image'], $type);
		if ($uploadedImagePath) $item['image'] = $uploadedImagePath;
	} elseif (!empty($existingItem['image'])) {
		$item['image'] = $existingItem['image'];
	}

	$dtRaw = trim($post['publish_datetime'] ?? '');
	if ($dtRaw !== '') {
		$date = str_replace('T', ' ', $dtRaw);
	} else {
		$timeRaw = trim($post['time'] ?? '');
		if (!empty($post['date'])) {
			$date = ($timeRaw !== '') ? $post['date'] . ' ' . $timeRaw : $post['date'];
		} else {
			$date = date('Y-m-d H:i');
		}
	}
	if ($type === 'project') {
		$item['date']        = $date;
		$item['description'] = htmlspecialchars($post['description'] ?? '');
	}
	if ($type === 'article' || $type === 'page') {
		$item['date'] = $date;
	}
	if ($type === 'article') {
		$item['summary'] = trim($post['summary'] ?? '');
	}
	if ($type === 'page') {
		$item['page_template'] = trim($post['page_template'] ?? '');
	}
	if ($type === 'article' || $type === 'project') {
		$item['show_on_homepage'] = isset($post['show_on_homepage']);
	}

	$item['show_in_menu'] = isset($post['show_in_menu']);
	$item['menu_order']   = isset($post['menu_order']) ? max(0, min(999, (int)$post['menu_order'])) : 0;

	if (isset($post['custom_fields']) && is_array($post['custom_fields'])) {
		$cleanCf = [];
		foreach ($post['custom_fields'] as $cfKey => $cfVal) {
			$cleanCf[sanitizeSlug($cfKey, true)] = is_array($cfVal) ? '' : trim((string)$cfVal);
		}
		$item['custom_fields'] = $cleanCf;
	} elseif (!empty($existingItem['custom_fields'])) {
		$item['custom_fields'] = $existingItem['custom_fields'];
	}

	$riRaw = (string)($post['related_items'] ?? '');
	if ($riRaw !== '') {
		$riDecoded = json_decode(stripslashes($riRaw), true);
		if (is_array($riDecoded)) {
			$riClean = [];
			foreach ($riDecoded as $riRef) {
				$riType  = $riRef['type']  ?? '';
				$riSlug  = $riRef['slug']  ?? '';
				$riTitle = mb_substr(strip_tags((string)($riRef['title'] ?? '')), 0, 300);
				if (in_array($riType, ['article', 'page', 'project'], true) && $riSlug !== '') {
					$riClean[] = ['type' => $riType, 'slug' => sanitizeSlug($riSlug), 'title' => $riTitle];
				}
			}
			$item['related_items'] = $riClean;
		}
	} elseif (!empty($existingItem['related_items'])) {
		$item['related_items'] = $existingItem['related_items'];
	}

	// Legacy flat gallery
	if (isset($post['gallery']) && is_array($post['gallery'])) {
		$galleryItems = [];
		foreach ($post['gallery'] as $galleryItem) {
			if (!empty($galleryItem['src'])) {
				$galleryItems[] = [
					'src'      => htmlspecialchars($galleryItem['src']),
					'caption'  => $galleryItem['caption']  ?? '',
					'alt_text' => $galleryItem['alt_text'] ?? '',
				];
			}
		}
		if (!empty($galleryItems)) $item['gallery'] = $galleryItems;
	}

	if (isset($post['galleries']) && is_array($post['galleries'])) {
		$galleries = [];
		foreach ($post['galleries'] as $gIdx => $galleryData) {
			$images = [];
			if (!empty($galleryData['images']) && is_array($galleryData['images'])) {
				foreach ($galleryData['images'] as $img) {
					if (!empty($img['src'])) {
						$images[] = [
							'src'      => htmlspecialchars($img['src']),
							'caption'  => $img['caption']  ?? '',
							'alt_text' => $img['alt_text'] ?? '',
						];
					}
				}
			}
			$galleries[] = [
				'label'  => $galleryData['label'] ?? ('Galerie ' . $gIdx),
				'layout' => in_array($galleryData['layout'] ?? 'grid', ['grid', 'masonry', 'justified', 'carousel'], true)
							? $galleryData['layout'] : 'grid',
				'images' => $images,
			];
		}
		if (!empty($galleries)) $item['galleries'] = $galleries;
	}

	if ($existingItem !== null) {
		$item['last_modified'] = date('Y-m-d H:i');
	}

	return [
		'item'            => $item,
		'slug_renamed_to' => $slugRenamedTo,
		'new_tags'        => $newTags,
		'new_category'    => $newCategory,
	];
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
	foreach (['article', 'page', 'project'] as $_aflBaseType) {
		$_aflPlural = null;
		if ($type === $_aflBaseType) $_aflPlural = false;
		elseif ($type === $_aflBaseType . 's') $_aflPlural = true;
		if ($_aflPlural !== null) {
			$settings = admin_load_config();
			$override = $settings['type_labels'][$_aflBaseType][$_aflPlural ? 'plural' : 'singular'] ?? '';
			if ($override !== '') return sanitizeSlug($override);
			break;
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

	return $baseUrl . $finalSlug . '/';
}

function admin_get_themes() {
	$themesDir = dirname(dirname(__DIR__)) . '/theme/';
	$themes    = [];

	if (!is_dir($themesDir)) {
		return ['default'];
	}

	foreach (scandir($themesDir) as $item) {
		if ($item === '.' || $item === '..' || $item[0] === '.') {
			continue;
		}
		$themePath = $themesDir . $item;
		if (!is_dir($themePath)) continue;
		if (!file_exists($themePath . '/header.php'))    continue;
		if (!file_exists($themePath . '/footer.php'))    continue;
		if (!file_exists($themePath . '/home.php'))      continue;
		if (!file_exists($themePath . '/css/style.css')) continue;
		$themes[] = $item;
	}

	if (empty($themes)) {
		$themes[] = 'default';
	}

	return $themes;
}

function admin_get_page_title() {
	$currentFile = basename($_SERVER['PHP_SELF']);
	$action      = $_GET['action'] ?? '';
	$type        = $_GET['type']   ?? '';
	if ($currentFile === 'index.php') {
		if ($action === 'add') {
			$contentType = in_array($type, ['article', 'page', 'project']) ? $type : 'article';
			return sprintf(__t('add_new_type'), sl_type_label($contentType));
		}
		if ($action === 'edit') {
			$label = in_array($type, ['article', 'page', 'project'], true)
				? sl_type_label($type)
				: __t('content', 'Content');
			return sprintf(__t('edit_type'), $label);
		}
		if ($action === 'manage_categories') return __t('manage_categories');
		if ($action === 'manage_tags')       return __t('manage_tags');
		if ($action === 'settings')          return __t('settings');
		if ($action === 'manage_themes')     return __t('theme_manager_title');
		if ($action === 'translations')      return __t('translations_title');
		if ($action === 'system_info')       return __t('system_information');
		if ($action === 'backup')            return __t('backup_export');
		if ($action === 'menu_builder')      return __t('menu_configuration');
		if ($action === 'account')           return __t('account');
		if ($action === 'users')             return __t('users_title');
		if ($action === 'plugins')           return __t('extensions_title', 'Extensions');
		if (empty($action) && in_array($type, ['article', 'page', 'project'], true)) {
			return sl_type_label($type, true);
		}
		return __t('dashboard');
	}
	return __t('admin');
}

function admin_decode_html($html) {
	if (!$html) return '';
	return html_entity_decode($html);
}

function initializeProgress($jobName) {
	$_SESSION['batch_job'] = [
		'name' => $jobName,
		'total' => 0,
		'processed' => 0,
		'start_time' => time(),
		'status' => 'initializing'
	];
}

function updateProgress($processed, $total, $status = null) {
	if (isset($_SESSION['batch_job'])) {
		$_SESSION['batch_job']['processed'] = $processed;
		$_SESSION['batch_job']['total'] = $total;
		if ($status) {
			$_SESSION['batch_job']['status'] = $status;
		}
	}
}

function syncMenuUrlsForCategory($data, $oldCategorySlug, $newCategoryName) {
	$settingsFile = dirname(dirname(__DIR__)) . '/config.json';
	if (!file_exists($settingsFile)) return;

	$settings = json_decode(file_get_contents($settingsFile), true);
	if (!is_array($settings) || empty($settings['main_menu'])) return;

	$newCategorySlug = sanitizeSlug($newCategoryName);
	$changed = false;

	foreach ($settings['main_menu'] as &$menuItem) {
		if (empty($menuItem['content_type']) || empty($menuItem['content_slug'])) continue;

		$contentType = $menuItem['content_type'];
		$contentSlug = $menuItem['content_slug'];

		if (empty($data[$contentType])) continue;

		foreach ($data[$contentType] as $contentItem) {
			$itemSlug = !empty($contentItem['custom_slug'])
				? $contentItem['custom_slug']
				: ($contentItem['slug'] ?? '');

			if ($itemSlug !== $contentSlug) continue;

			$currentCategorySlug = !empty($contentItem['category'])
				? sanitizeSlug($contentItem['category'])
				: '';

			if ($currentCategorySlug !== $newCategorySlug) break;

			$catPath = getCategoryPath($newCategorySlug, $data);
			if ($contentType === 'article' && !empty($catPath)) {
				$newUrl = $catPath . '/' . $contentSlug . '/';
			} elseif ($contentType === 'project' && !empty($catPath)) {
				$newUrl = 'project/' . $catPath . '/' . $contentSlug . '/';
			} elseif ($contentType === 'page' && !empty($catPath)) {
				$newUrl = $catPath . '/' . $contentSlug . '/';
			} else {
				$newUrl = $contentType . '/' . $contentSlug . '/';
			}

			if ($menuItem['url'] !== $newUrl) {
				$menuItem['url'] = $newUrl;
				if (array_key_exists('content_category', $menuItem)) {
					$menuItem['content_category'] = $newCategoryName;
				}
				$changed = true;
			}
			break;
		}
	}
	unset($menuItem);

	if ($changed) {
		file_put_contents(
			$settingsFile,
			json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);
	}
}

function syncMenuUrlsForTag($oldTagSlug, $newTagName) {
	$settingsFile = dirname(dirname(__DIR__)) . '/config.json';
	if (!file_exists($settingsFile)) return;

	$settings = json_decode(file_get_contents($settingsFile), true);
	if (!is_array($settings) || empty($settings['main_menu'])) return;

	$changed = false;

	foreach ($settings['main_menu'] as &$menuItem) {
		if (empty($menuItem['tag_slug'])) continue;
		if ($menuItem['tag_slug'] !== $oldTagSlug) continue;

		if ($newTagName === null) {			$menuItem['url'] = '#tag-deleted';
			$menuItem['content_slug'] = '';
		} else {
			$newTagSlug = sanitizeSlug($newTagName);
			$menuItem['url'] = 'tag/' . $newTagSlug . '/';
			$menuItem['tag_slug'] = $newTagSlug;
		}
		$changed = true;
	}
	unset($menuItem);

	if ($changed) {
		file_put_contents(
			$settingsFile,
			json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);
	}
}

function getPageTemplates(): array {
	$templates = ['' => __t('page_template_default', 'Default')];

	$settings  = admin_load_config();
	$theme     = $settings['active_theme'] ?? 'default';
	$dir       = dirname(dirname(__DIR__)) . '/theme/' . basename($theme) . '/page-templates/';

	if (!is_dir($dir)) {
		return $templates;
	}
	foreach (glob($dir . '*.php') as $filePath) {
		$head = file_get_contents($filePath, false, null, 0, 512);
		if ($head !== false && preg_match('/Template Name:\s*(.+)/i', $head, $m)) {
			$key             = basename($filePath, '.php');
			$templates[$key] = trim(preg_replace('/\s*\*\/.*$/', '', $m[1]));
		}
	}
	return $templates;
}

function theme_editor_allowed_extensions(): array {
	return ['php', 'css', 'js', 'json'];
}

function theme_editor_scan_files(string $themeDir): array {
	$allowed = theme_editor_allowed_extensions();
	$groups  = [];

	if (!is_dir($themeDir)) {
		return $groups;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $fileInfo) {
		if ($fileInfo->isDir()) continue;

		$filename = $fileInfo->getFilename();
		if ($filename[0] === '.') continue;

		$ext = strtolower($fileInfo->getExtension());
		if (!in_array($ext, $allowed, true)) continue;

		$relativePath = substr($fileInfo->getPathname(), strlen($themeDir) + 1);
		$relativePath = str_replace('\\', '/', $relativePath);

		$folder = dirname($relativePath);
		$group  = ($folder === '.') ? '' : $folder . '/';

		$groups[$group][$relativePath] = $relativePath;
	}
	$rootGroup = $groups[''] ?? [];
	unset($groups['']);
	ksort($groups, SORT_NATURAL);
	foreach ($groups as &$files) {
		ksort($files, SORT_NATURAL);
	}
	unset($files);
	ksort($rootGroup, SORT_NATURAL);

	return ($rootGroup ? ['' => $rootGroup] : []) + $groups;
}

function theme_editor_resolve_path(string $themeDir, string $requestedFile): ?string {
	if ($requestedFile === '') return null;

	$ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
	if (!in_array($ext, theme_editor_allowed_extensions(), true)) return null;

	$themeReal = realpath($themeDir);
	if ($themeReal === false) return null;

	$candidate = $themeDir . '/' . $requestedFile;
	$candidateReal = realpath($candidate);
	if ($candidateReal === false) return null;
	if (strpos($candidateReal, $themeReal . DIRECTORY_SEPARATOR) !== 0) return null;
	return $candidateReal;
}

function admin_check_for_update(): ?array {
	 $_vFile = dirname(dirname(__DIR__)) . '/version.json';
	 $_vData = file_exists($_vFile) ? json_decode(file_get_contents($_vFile), true) : null;
	 $localVersion = (is_array($_vData) && !empty($_vData['version'])) ? $_vData['version'] : '1.0';
	 $remoteUrl    = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/version.json';
	 $cacheDir     = __DIR__ . '/../cache';
	 $cacheFile    = $cacheDir . '/update-check.json';
	 $cacheTtl     = 86400; 
 	 if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
		 $cached = json_decode(file_get_contents($cacheFile), true);
		 if (is_array($cached)) {
			 return version_compare($cached['version'], $localVersion, '>') ? $cached : null;
		 }
	 }
 
	 $json = false;
	 if (function_exists('curl_init')) {
		 $ch = curl_init($remoteUrl);
		 curl_setopt_array($ch, [
			 CURLOPT_RETURNTRANSFER => true,
			 CURLOPT_TIMEOUT        => 3,
			 CURLOPT_FOLLOWLOCATION => true,
			 CURLOPT_SSL_VERIFYPEER => true,
		 ]);
		 $json = curl_exec($ch);
		 if (curl_errno($ch)) $json = false;
	 }
	 if ($json === false && ini_get('allow_url_fopen')) {
		 $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
		 $json = @file_get_contents($remoteUrl, false, $ctx);
	 }
	 if ($json === false) return null;
	 
	 $remote = json_decode($json, true);
	 if (!is_array($remote) || empty($remote['version'])) return null;
 
	 if (!is_dir($cacheDir)) {
		 @mkdir($cacheDir, 0755, true);
	 }
	 if (is_writable($cacheDir)) {
		 file_put_contents($cacheFile, $json);
	 }
 
	 return version_compare($remote['version'], $localVersion, '>') ? $remote : null;
 }

function admin_fetch_news(): array {
	$remoteUrl = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/news.json';
	$cacheDir  = __DIR__ . '/../cache';
	$cacheFile = $cacheDir . '/news-cache.json';
	$cacheTtl  = 86400;

	$filterExpired = function(array $items): array {
		$today = strtotime('today');
		return array_values(array_filter($items, function($item) use ($today) {
			return empty($item['expires']) || strtotime($item['expires']) >= $today;
		}));
	};

	if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
		$cached = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cached['news'] ?? null)) return $filterExpired($cached['news']);
	}

	$json = false;
	if (function_exists('curl_init')) {
		$ch = curl_init($remoteUrl);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 3,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
		]);
		$json = curl_exec($ch);
		if (curl_errno($ch)) $json = false;
	}
	if ($json === false && ini_get('allow_url_fopen')) {
		$ctx  = stream_context_create(['http' => ['timeout' => 3]]);
		$json = @file_get_contents($remoteUrl, false, $ctx);
	}
	if ($json === false) return [];

	$data = json_decode($json, true);
	if (!is_array($data['news'] ?? null)) return [];

	if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
	if (is_writable($cacheDir)) file_put_contents($cacheFile, $json);

	return $filterExpired($data['news']);
}

function admin_render_settings_tabs(string $activeTab, bool $onSettingsPage): void {
	$tabs = [
		'general'       => ['settings', __t('general')],
		'reading'       => ['reading', __t('settings_tab_reading')],
		'writing'       => ['writing', __t('settings_tab_writing')],
		'seo'           => ['seo', __t('seo')],
		'images'        => ['images', __t('images')],
		'contact'       => ['contact', __t('settings_tab_contact')],
		'custom_fields' => ['puzzle', __t('cf_tab')],
	];

	echo '<div class="tabs">';
	foreach ($tabs as $key => $tab) {
		[$icon, $label] = $tab;
		$activeClass = $activeTab === $key ? ' active' : '';
		if ($onSettingsPage) {
			echo '<div class="tab' . $activeClass . '" data-tab="' . hsc($key) . '">' . admin_icon($icon) . ' ' . hsc($label) . '</div>';
		} else {
			echo '<a href="index.php?action=settings&tab=' . urlencode($key) . '" class="tab' . $activeClass . '">' . admin_icon($icon) . ' ' . hsc($label) . '</a>';
		}
	}
	$usersActiveClass = $activeTab === 'users' ? ' active' : '';
	echo '<a href="index.php?action=users" class="tab' . $usersActiveClass . '">' . admin_icon('account') . ' ' . hsc(__t('users_title')) . '</a>';
	echo '</div>';
}

define('LANG_CONTEXT', 'admin');
require_once dirname(dirname(__DIR__)) . '/core/lang-cache.php';require_once dirname(dirname(__DIR__)) . '/core/data-layer.php';
require_once dirname(dirname(__DIR__)) . '/core/plugin-api.php';
pl_load_active_plugins();
require_once dirname(dirname(__DIR__)) . '/core/admin-data-layer.php';

if (!function_exists('loadData'))          { function loadData() { return admin_load_data(); } }
if (!function_exists('saveData'))          { function saveData($data) { return admin_save_data($data); } }
if (!function_exists('getBaseUrl'))        { function getBaseUrl() { return admin_site_url(); } }
if (!function_exists('adminCleanUrl'))     { function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') { return admin_content_url($contentType, $slug, $customSlug, $category); } }
if (!function_exists('formatFileSize'))    { function formatFileSize($bytes) { return admin_format_file_size($bytes); } }
if (!function_exists('getAvailableThemes')){ function getAvailableThemes() { return admin_get_themes(); } }
if (!function_exists('decodeHtmlEntities')){ function decodeHtmlEntities($html) { return admin_decode_html($html); } }