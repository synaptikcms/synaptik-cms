<?php
// Define constants
if (!defined('INCLUDED')) define('INCLUDED', true);

require_once __DIR__ . '/admin-icons.php';
require_once __DIR__ . '/content-purify.php';

// hsc() may already be defined if core/functions.php was loaded first.
// In admin-only paths (auth.php, template-editor.php) it is not, so we define it here.
if (!function_exists('hsc')) {
    function hsc(?string $s, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $enc = 'UTF-8'): string
    {
        return htmlspecialchars((string) ($s ?? ''), $flags, $enc);
    }
}

// May already be defined if core/functions.php was loaded first (front-end
// theme-preview token validation lives there). Duplicated rather than
// require_once'd from here — core/functions.php's own sanitizeSlug() has no
// function_exists() guard, so pulling it in after this file (which already
// defines a guarded sanitizeSlug() below) would fatal on redeclaration.
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

// May already be defined if core/functions.php was loaded first. Duplicated
// for the same reason as hsc()/themePreviewSecret() above — most admin pages
// never load core/functions.php. Uses admin_load_config() instead of
// loadConfig() since that's the equivalent already available here.
if (!function_exists('sl_type_label')) {
    function sl_type_label(string $type, bool $plural = false): string {
        $settings = admin_load_config();
        $override = $settings['type_labels'][$type][$plural ? 'plural' : 'singular'] ?? '';
        if ($override !== '') return $override;
        return __t($plural ? $type . 's' : $type, ucfirst($type));
    }
}

/**
 * Admin wrapper for sanitizeSlug function
 * Ensures the function is available in admin context
 */
/**
 * Sanitize a filename to make it safe for storage.
 *
 * Single home for what used to be two byte-identical copies, in content.php
 * and file-upload.php. They never collided at runtime (file-upload.php is a
 * standalone endpoint, never included), but the duplication invited the two
 * from drifting apart — tightening the rule in one upload path and not the
 * other. Both callers already require this file.
 */
if (!function_exists('sanitizeFileName')) {
	function sanitizeFileName($filename) {
		// Remove any non-alphanumeric characters except dots, hyphens, and underscores
		$filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);

		// Limit filename length
		$filename = substr($filename, 0, 255);

		// Ensure filename is not empty
		if (empty($filename)) {
			$filename = 'unnamed_file_' . time();
		}

		return $filename;
	}
}

if (!function_exists('sanitizeSlug')) {
	function sanitizeSlug($string) {
		$string = trim($string);
		// Transliterate accented characters to ASCII equivalents via explicit map
		// iconv TRANSLIT is unreliable (inserts ', ? or other chars on some systems)
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
		// Replace spaces with hyphens FIRST
		$string = preg_replace('/\s+/', '-', $string);
		// Only allow alphanumeric, hyphens, underscores
		$string = preg_replace('/[^a-z0-9\-_]/', '', $string);
		// Remove multiple consecutive hyphens
		$string = preg_replace('/-+/', '-', $string);
		// Trim hyphens and underscores from start/end
		$string = trim($string, '-_');
		
		return $string;
	}
}

/**
 * Load configuration from config.json, merged with hardcoded defaults.
 * Unique source of truth for every application parameter.
 * Used across the admin panel via admin_load_config().
 */
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

	// Always refresh the theme list from the filesystem
	$settings['available_themes'] = function_exists('getAvailableThemes') ? getAvailableThemes() : ['default'];

	return $settings;
}

/**
 * Appends a new custom field to a content type's schema in config.json —
 * used by the editor's quick-add form so a field can be created without
 * leaving the Settings page. Key is derived from the label and de-duplicated
 * against the type's existing keys. 'select' is intentionally not offered
 * here since it needs an options list; those still go through Settings.
 */
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

/**
 * Slugs of plugins the admin has chosen to pin as a sidebar shortcut.
 * Site-wide preference (not per-user) — deliberately simple, matching how
 * every other admin preference in config.json already works.
 */
function admin_get_pinned_plugins(): array {
	$config = admin_load_config();
	$pinned = $config['pinned_plugins'] ?? [];
	return is_array($pinned) ? array_values(array_unique($pinned)) : [];
}

/**
 * Adds or removes a plugin slug from the pinned-sidebar list.
 */
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

/**
 * Format a date according to admin settings.
 * Handles both legacy 'YYYY-MM-DD' and datetime 'YYYY-MM-DD HH:MM' strings.
 *
 * @param string $date  Raw date string stored in JSON
 * @return string       Formatted date (date part only)
 */
function admin_format_date($date) {
	if (empty($date)) return '';

	$appSettings = admin_load_config();
	$format      = $appSettings['date_format'] ?? 'Y-m-d';

	$timestamp = strtotime($date);
	if ($timestamp === false) return $date;

	return date($format, $timestamp);
}

/**
 * Format the time portion of a stored date string.
 * Returns an empty string for legacy items that have no time component.
 *
 * @param string $date  Raw date string (e.g. '2024-05-15 14:30' or '2024-05-15')
 * @return string       Formatted time string, e.g. '14:30', or ''
 */
function admin_format_time($date) {
	if (empty($date)) return '';

	// Only return a time when the stored value explicitly contains HH:MM
	if (!preg_match('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $date)) return '';

	$timestamp = strtotime($date);
	if ($timestamp === false) return '';

	return date('H:i', $timestamp);
}

/**
 * Extract the time part (HH:MM) from a stored date string for use in <input type="time">.
 * Returns today's current time for new content, empty for legacy items without time.
 *
 * @param string|null $date  Stored date value
 * @param bool        $defaultNow  Return current time when no time component is found
 * @return string
 */
function admin_extract_time(string $date = '', bool $defaultNow = false): string {
	if (!empty($date) && preg_match('/\d{4}-\d{2}-\d{2}[T ](?P<t>\d{2}:\d{2})/', $date, $m)) {
		return $m['t'];
	}
	return $defaultNow ? date('H:i') : '';
}

/**
 * Line-based diff between two text blocks (LCS algorithm), used by the
 * revision history view to show what changed in the content field.
 * Returns null instead of computing when both texts together exceed a
 * size guard, to avoid the O(m*n) memory cost on very large documents.
 *
 * @param  string $old
 * @param  string $new
 * @return array{type: string, text: string}[]|null
 */
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

// Define our own versions of functions to avoid conflicts
// Load main site functions in a way that doesn't cause conflicts
function admin_require_core_functions() {
	// Include core functions without function conflicts.
	// The files live in /core/ (moved out of the CMS root during the /core reorg).
	include_once dirname(dirname(__DIR__)) . '/core/data-functions.php';
	include_once dirname(dirname(__DIR__)) . '/core/core-functions.php';
}
admin_require_core_functions();

/**
 * Return the logged-in admin's username from session.
 */
function admin_get_username(): string {
	return $_SESSION['admin_username'] ?? 'admin';
}

/**
 * Return the logged-in admin's display name from session.
 */
function admin_get_display_name(): string {
	return $_SESSION['admin_display_name'] ?? admin_get_username();
}

/**
 * ─── Multi-user store ───────────────────────────────────────────────────────
 * Users live in private/users.json (not data/ — private/ is denied by
 * .htaccess and already holds other per-install secrets). Lazily migrated
 * from the legacy single-admin admin-credentials.php on first access, so
 * there is no separate migration step to run — the pre-existing admin
 * account becomes the one and only 'admin' automatically.
 */

function admin_users_path(): string {
	return dirname(__DIR__, 2) . '/private/users.json';
}

/**
 * Atomic write (tmp file + rename) — mirrors _sl_write_json() in
 * core/admin-data-layer.php, duplicated here to avoid a new cross-file
 * dependency for a couple of small JSON stores.
 */
function admin_write_json_atomic(string $path, $data): bool {
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) return false;
	$tmp = $path . '.tmp';
	if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
	return rename($tmp, $path);
}

/**
 * Loads every user record, migrating the legacy single-admin credentials
 * file into the store on first access.
 */
function admin_load_users(): array {
	$path = admin_users_path();

	if (!file_exists($path)) {
		$credFile = __DIR__ . '/../admin-credentials.php';
		$admin_username     = 'admin';
		$admin_display_name = '';
		$admin_password     = '';
		$admin_email        = '';
		if (file_exists($credFile)) {
			include $credFile;
		}

		$users = [[
			'id'            => bin2hex(random_bytes(8)),
			'username'      => $admin_username,
			'display_name'  => $admin_display_name !== '' ? $admin_display_name : $admin_username,
			'email'         => $admin_email,
			'password_hash' => $admin_password,
			'role'          => 'admin',
			'created_at'    => time(),
		]];

		if (!admin_write_json_atomic($path, $users)) {
			// Migration failed (permissions?) — surface the account for this
			// request but don't pretend the store is durable.
			return $users;
		}

		// Migration done — the file held a real password hash and has no
		// further purpose (admin-folder detection uses auth.php, not this
		// file's presence). Remove it instead of leaving a stub behind.
		@unlink($credFile);

		return $users;
	}

	$json  = @file_get_contents($path);
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

/**
 * Timing-safe on purpose — this is the lookup the login flow uses.
 */
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

/**
 * Counts users with the admin role, optionally excluding one id — used to
 * block ever leaving the install with zero admins (delete or role change).
 */
function admin_count_admins(array $users, ?string $excludeId = null): int {
	$count = 0;
	foreach ($users as $user) {
		if ($excludeId !== null && ($user['id'] ?? null) === $excludeId) continue;
		if (($user['role'] ?? '') === 'admin') $count++;
	}
	return $count;
}

/**
 * Creates a new user. Returns the new user's id, or null on validation
 * failure (duplicate username/email, unknown role).
 */
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

/**
 * Updates an existing user. $fields may contain any of: username,
 * display_name, email, password (plain text — will be hashed), role.
 * Refuses a duplicate username/email, and refuses a role change away from
 * 'admin' that would leave the install with zero admins.
 */
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

/**
 * Deletes a user. Refuses to delete the last remaining admin.
 */
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

// ─── Current-user / role helpers ────────────────────────────────────────────

function admin_current_user_id(): ?string {
	return $_SESSION['admin_user_id'] ?? null;
}

/**
 * Defaults to 'admin' (not the usual least-privilege default) — a session
 * with no admin_role key predates this feature, meaning whoever holds it
 * was, by definition, the one-and-only admin under the old single-user
 * model. See admin_is_logged_in()'s self-heal for the durable fix.
 */
function admin_current_user_role(): string {
	return $_SESSION['admin_role'] ?? 'admin';
}

function admin_is_admin(): bool {
	return admin_current_user_role() === 'admin';
}

function admin_can_manage_all_content(): bool {
	return in_array(admin_current_user_role(), ['admin', 'editor'], true);
}

/**
 * Whether the current user may edit/delete/duplicate $item — full access
 * for admin/editor, own-content-only for author.
 */
function admin_can_edit_item(array $item): bool {
	if (admin_can_manage_all_content()) return true;
	$ownerId = $item['author_id'] ?? null;
	return $ownerId !== null && $ownerId === admin_current_user_id();
}

/**
 * Builds a content item's field set from POST data. Shared by the explicit
 * Add/Edit save actions (content.php) and by autosave's direct-write path
 * for draft-status items (autosave.php) — the three call sites differ only
 * in what they do with the result (status/publish_at, persistence, redirect).
 *
 * Does not validate that title/content are present — Add/Edit require both
 * (matching their forms' `required` attributes) while autosave's materialize
 * path tolerates a title-only or content-only item, so each caller applies
 * its own gate before calling this. Does NOT set 'status' or 'publish_at' —
 * callers decide those too, since Add/Edit evaluate publish_at into
 * published/scheduled while autosave never auto-publishes. Does not persist
 * anything.
 *
 * @param  string     $type              Internal type name.
 * @param  array      $post              $_POST-shaped data.
 * @param  array|null $existingItem      The item being edited, or null when creating.
 *                                        Drives author_id/image/custom_fields/
 *                                        related_items/content_format fallbacks
 *                                        and whether 'last_modified' is stamped.
 * @param  array      $existingIndexForDedup  Index entries to dedupe the slug
 *                                        against (Add-shaped calls only — Edit
 *                                        has never deduped on rename, preserved
 *                                        as-is here by passing []).
 * @param  array      $files             $_FILES-shaped data for a direct upload.
 * @return array{item: array, slug_renamed_to: ?string, new_tags: array, new_category: ?array}
 *                                        new_tags/new_category list display names not yet in
 *                                        the tags/categories store — the caller persists them.
 */
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

	// Deduplicate the slug against sibling items of the same type — Add-shaped
	// calls only (see docblock); an empty $existingIndexForDedup skips this
	// entirely, matching handleContentEdit()'s long-standing no-dedup behavior.
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

	// Tags/category — resolve to slugs and report back any display name not
	// already in the tags/categories store. Deliberately NOT persisted here:
	// the Add/Edit callers fold new entries into the in-memory $data array
	// they're about to save as a whole via saveData($data), and autosave's
	// leaner callers persist them immediately via sl_admin_save_tags()/
	// sl_admin_save_categories() — writing to the store directly from here
	// would race with (and be silently undone by) the Add/Edit callers'
	// later saveData($data) call, which still holds the pre-upsert snapshot.
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
		// SEO fields: store raw — htmlspecialchars() is applied at output time only
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

	// Featured image: explicit removal > newly selected/uploaded > fall back
	// to whatever the existing item already had (Add has no existing item,
	// so it simply ends up unset, matching its previous behavior).
	if (!empty($post['remove_featured_image'])) {
		// Omitted on purpose — clears the field.
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

	// Publish date/time — combined datetime-local field, falling back to
	// separate hidden date/time fields (old form submissions or autosave).
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

	// Custom fields — sanitize POST, or fall back to the existing item's
	// values when the form sent none (e.g. no custom fields defined for this type).
	if (isset($post['custom_fields']) && is_array($post['custom_fields'])) {
		$cleanCf = [];
		foreach ($post['custom_fields'] as $cfKey => $cfVal) {
			$cleanCf[sanitizeSlug($cfKey, true)] = is_array($cfVal) ? '' : trim((string)$cfVal);
		}
		$item['custom_fields'] = $cleanCf;
	} elseif (!empty($existingItem['custom_fields'])) {
		$item['custom_fields'] = $existingItem['custom_fields'];
	}

	// Related items — decode JSON from the hidden input, sanitize each
	// reference; falls back to the existing value only if the field was
	// absent entirely (an explicitly empty submission clears all links).
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

	// Named inline galleries
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

/**
 * Same access rule as admin_can_edit_item(), for autosave drafts — which
 * are not content items and track ownership via admin_user_id rather than
 * author_id (stamped by autosave.php).
 */
function admin_can_edit_draft(array $draftData): bool {
	if (admin_can_manage_all_content()) return true;
	$ownerId = $draftData['admin_user_id'] ?? null;
	return $ownerId !== null && $ownerId === admin_current_user_id();
}

/**
 * Check if user is logged in as admin
 * Enforces a 2-hour inactivity timeout.
 */
function admin_is_logged_in() {
	if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
		return false;
	}

	$timeout = 2 * 60 * 60; // 2 heures en secondes
	if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
		// Session expirée — on purge proprement
		session_unset();
		session_destroy();
		return false;
	}

	// Timestamp update with each authenticated request
	$_SESSION['admin_last_activity'] = time();

	// Self-heal a session that started before multi-user roles existed: it
	// carries $_SESSION['admin']=true (still valid) but no admin_user_id/
	// admin_role yet. Backfill both from the user store now rather than
	// leaving admin_current_user_role()'s 'admin' default to carry the
	// request indefinitely — this only runs once per session, right here,
	// since every gated file already calls this function.
	if (!isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_username'])) {
		$_matchedUser = admin_find_user_by_username($_SESSION['admin_username']);
		if ($_matchedUser !== null) {
			$_SESSION['admin_user_id'] = $_matchedUser['id'];
			$_SESSION['admin_role']    = $_matchedUser['role'];
		}
	}

	return true;
}

/**
 * Load data from the main data storage
 */
function admin_load_data() {
	 // Load full items for all types from the split-file architecture.
	 // Returns the same legacy array structure as before: ['article'=>[...], ...]
	 return sl_admin_load_all();
 }
  
 /**
  * Save data to the split-file architecture.
  * Drop-in replacement for the old file_put_contents('../data.json', ...) call.
  * Distributes items to individual files, rebuilds indices, handles renames/deletes.
  */
 function admin_save_data($data) {
	 return sl_admin_save_all($data);
 }

/**
 * Get admin URL
 */
/**
 * Get site URL for the main site
 */
function admin_site_url() {
	$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'];
	$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
	
	return $protocol . '://' . $host . $basePath . '/';
}

/**
 * Translates an activity log machine action key (e.g. "user_deleted") into
 * its localized label. Single source of truth shared by the Activity Log
 * page and the dashboard's recent-activity widget — falls back to the raw
 * key if it's untranslated or unknown.
 */
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

/**
 * Format file size for display
 */
function admin_format_file_size($bytes) {
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	
	return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Returns the localized URL prefix slug for a content type by reading
 * directly from the FRONT-END lang files (lang/{locale}.json).
 *
 * url_slug_* keys are routing data owned by the front end. Reading them
 * from the admin lang files would require keeping two copies in sync,
 * which is a maintenance hazard. This function always goes to the source.
 *
 * @param string $type  e.g. 'article', 'project', 'page', 'category', 'tag'
 * @return string       Slug-safe localized prefix (e.g. 'projet', 'categorie')
 */
function admin_front_url_slug(string $type): string {
	// Content-type slugs (article/page/project, singular or plural) can be
	// overridden per site via Settings > Reading — see sl_type_label() above.
	// Same override core/functions.php's url_slug() applies on the front end;
	// duplicated here because this function deliberately can't go through
	// __t()/lang_current() (see comment below on why it reads lang/front/
	// directly), so it can't just delegate to url_slug() either.
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
		// Always use the FRONT-end locale here, even when called from admin.
		// lang_current() now returns admin_language when LANG_CONTEXT === 'admin',
		// which would break URL generation — so we read active_language directly.
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
	$raw = $strings[$key] ?? $type; // fallback: raw type name (English)
	return sanitizeSlug($raw);
}

/**
 * Generate URLs for viewing published content from the admin panel.
 * Uses admin_front_url_slug() so links always match actual front-end routes.
 */
function admin_content_url($contentType, $slug, $customSlug = '', $category = '') {
	$baseUrl      = admin_site_url();
	$finalSlug    = !empty($customSlug) ? $customSlug : $slug;
	$categorySlug = !empty($category) ? sanitizeSlug($category) : '';

	// Resolve full hierarchical category path (e.g. "parent/child")
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
		// Pages without category live at root — no type prefix
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

/**
 * Get the dynamic page title for the admin header
 */
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

/**
 * Admin helper function to decode HTML entities
 */
function admin_decode_html($html) {
	if (!$html) return '';
	return html_entity_decode($html);
}

// Progress tracking for batch operations
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

/**
 * Sync menu URLs in config.json when a category is renamed.
 * Rebuilds the URL for every menu item whose content belongs to the renamed category.
 *
 * @param array  $data            The already-updated data array (post-save)
 * @param string $oldCategorySlug The slug of the old category name
 * @param string $newCategoryName The new category name (not yet slugified)
 */
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

			// Only process items that now belong to the renamed category
			$currentCategorySlug = !empty($contentItem['category'])
				? sanitizeSlug($contentItem['category'])
				: '';

			if ($currentCategorySlug !== $newCategorySlug) break;

			// Rebuild URL using full hierarchical category path
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

/**
 * Sync menu URLs in config.json when a tag is renamed or deleted.
 * Updates menu items of type 'tag' whose content_slug matches the old tag slug.
 *
 * @param string      $oldTagSlug  Slug of the old tag name
 * @param string|null $newTagName  New tag name, or null if deleted
 */
function syncMenuUrlsForTag($oldTagSlug, $newTagName) {
	$settingsFile = dirname(dirname(__DIR__)) . '/config.json';
	if (!file_exists($settingsFile)) return;

	$settings = json_decode(file_get_contents($settingsFile), true);
	if (!is_array($settings) || empty($settings['main_menu'])) return;

	$changed = false;

	foreach ($settings['main_menu'] as &$menuItem) {
		if (empty($menuItem['tag_slug'])) continue;
		if ($menuItem['tag_slug'] !== $oldTagSlug) continue;

		if ($newTagName === null) {
			// Tag deleted: clear the URL so it's obviously broken and visible
			$menuItem['url'] = '#tag-deleted';
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

/**
 * Scan the active theme's page-templates/ folder and return available page templates.
 * Each .php file must declare a Template Name header comment: /* Template Name: Foo * /
 *
 * @return array  Associative array [ 'filename-without-ext' => 'Template Name' ]
 *                Always includes the empty-string key '' => 'Default'.
 */
function getPageTemplates(): array {
	$templates = ['' => __t('page_template_default', 'Default')];

	$settings  = admin_load_config();
	$theme     = $settings['active_theme'] ?? 'default';
	$dir       = dirname(dirname(__DIR__)) . '/theme/' . basename($theme) . '/page-templates/';

	if (!is_dir($dir)) {
		return $templates;
	}

	foreach (glob($dir . '*.php') as $filePath) {
		// Read only the first 512 bytes — enough to find the header comment
		$head = file_get_contents($filePath, false, null, 0, 512);
		if ($head !== false && preg_match('/Template Name:\s*(.+)/i', $head, $m)) {
			$key             = basename($filePath, '.php');
			// Strip trailing block-comment closer (* /) so single-line comments
			// like /* Template Name: Foo */ don't include " */" in the label.
			$templates[$key] = trim(preg_replace('/\s*\*\/.*$/', '', $m[1]));
		}
	}

	return $templates;
}

/**
 * Whitelist of file extensions editable via the Template Editor.
 * Kept in one place so the scanner and the save/restore handlers always agree.
 */
function theme_editor_allowed_extensions(): array {
	return ['php', 'css', 'js', 'json'];
}

/**
 * Recursively scan a theme directory and return all editable files,
 * grouped by their folder for display in a grouped <select>.
 *
 * Only whitelisted extensions are returned. Hidden files/folders (leading dot)
 * and the screenshot are skipped.
 *
 * @param string $themeDir  Absolute path to the active theme directory.
 * @return array  [ groupLabel => [ relativePath => relativePath, ... ], ... ]
 *                Root-level files are grouped under the empty-string key ''.
 */
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
		if ($filename[0] === '.') continue; // skip hidden files (.DS_Store, etc.)

		$ext = strtolower($fileInfo->getExtension());
		if (!in_array($ext, $allowed, true)) continue;

		$relativePath = substr($fileInfo->getPathname(), strlen($themeDir) + 1);
		$relativePath = str_replace('\\', '/', $relativePath); // normalize on Windows dev setups

		$folder = dirname($relativePath);
		$group  = ($folder === '.') ? '' : $folder . '/';

		$groups[$group][$relativePath] = $relativePath;
	}

	// Root files first, then subfolders alphabetically. Files sorted within each group.
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

/**
 * Resolve a user-supplied relative file path against a theme directory and
 * guarantee the result stays inside that directory (prevents path traversal
 * via "../" segments or absolute paths smuggled into the request).
 *
 * @param string $themeDir       Absolute, trusted path to the active theme directory.
 * @param string $requestedFile  Untrusted relative path supplied by the client.
 * @return string|null  Absolute real path on success, or null if invalid/outside the theme.
 */
function theme_editor_resolve_path(string $themeDir, string $requestedFile): ?string {
	if ($requestedFile === '') return null;

	$ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
	if (!in_array($ext, theme_editor_allowed_extensions(), true)) return null;

	$themeReal = realpath($themeDir);
	if ($themeReal === false) return null;

	$candidate = $themeDir . '/' . $requestedFile;

	// The target may not exist yet only in the "file not found" case we want to
	// reject anyway — realpath() returning false there is the correct outcome.
	$candidateReal = realpath($candidate);
	if ($candidateReal === false) return null;

	// Enforce that the resolved path is strictly inside the theme directory.
	if (strpos($candidateReal, $themeReal . DIRECTORY_SEPARATOR) !== 0) return null;

	return $candidateReal;
}

/**
  * Check for a newer CMS version against the public version endpoint.
  * Result is cached for 24 hours in admin/cache/ to avoid repeated remote calls.
  *
  * @return array|null  Remote version data, or null if up-to-date / unreachable.
  */
function admin_check_for_update(): ?array {
	// Read local version from version.json at the CMS root
	 $_vFile = dirname(dirname(__DIR__)) . '/version.json';
	 $_vData = file_exists($_vFile) ? json_decode(file_get_contents($_vFile), true) : null;
	 $localVersion = (is_array($_vData) && !empty($_vData['version'])) ? $_vData['version'] : '1.0';
	 $remoteUrl    = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/version.json';
	 $cacheDir     = __DIR__ . '/../cache';
	 $cacheFile    = $cacheDir . '/update-check.json';
	 $cacheTtl     = 86400; // 24 hours
 
	 // Serve from cache if still fresh
	 if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
		 $cached = json_decode(file_get_contents($cacheFile), true);
		 if (is_array($cached)) {
			 return version_compare($cached['version'], $localVersion, '>') ? $cached : null;
		 }
	 }
 
	 // Fetch remote — try cURL first, fall back to file_get_contents
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
		 // No curl_close() call: deprecated since PHP 8.5 and a no-op since PHP 8.0 —
		 // handles are freed automatically by garbage collection.
	 }
	 if ($json === false && ini_get('allow_url_fopen')) {
		 $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
		 $json = @file_get_contents($remoteUrl, false, $ctx);
	 }
	 if ($json === false) return null;
	 
	 $remote = json_decode($json, true);
	 if (!is_array($remote) || empty($remote['version'])) return null;
 
	 // Persist cache
	 if (!is_dir($cacheDir)) {
		 @mkdir($cacheDir, 0755, true);
	 }
	 if (is_writable($cacheDir)) {
		 file_put_contents($cacheFile, $json);
	 }
 
	 return version_compare($remote['version'], $localVersion, '>') ? $remote : null;
 }

/**
 * Fetch CMS news feed from the public updates repo.
 * Cached for 24 hours in admin/cache/.
 *
 * @return array  Array of news items, each with 'date', 'type', 'message'.
 */
function admin_fetch_news(): array {
	$remoteUrl = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/news.json';
	$cacheDir  = __DIR__ . '/../cache';
	$cacheFile = $cacheDir . '/news-cache.json';
	$cacheTtl  = 86400;

	// Expiry filter applied on every code path — cache stores raw data from GitHub,
	// filtering happens at read time so expired items disappear without a cache bust.
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
		// No curl_close() call: deprecated since PHP 8.5 and a no-op since PHP 8.0 —
		// handles are freed automatically by garbage collection.
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

/**
 * Renders the Settings/Users tab bar shared by settings-view.php and
 * users.php. On the Settings page itself the 7 settings tabs stay
 * JS-toggled panels within that one page (see settings-view.js) — fast,
 * no reload — and only the Users tab is a real link, since Users lives on
 * its own page. Anywhere else (currently just users.php), every settings
 * tab is a real link back to its tab on the Settings page.
 *
 * @param string $activeTab      Current tab key (general/reading/.../users).
 * @param bool   $onSettingsPage Whether this is being rendered from settings-view.php.
 */
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
require_once dirname(dirname(__DIR__)) . '/core/lang-cache.php';
// Load split-file data layer (read) and admin data layer (write) — both in /core/
require_once dirname(dirname(__DIR__)) . '/core/data-layer.php';
require_once dirname(dirname(__DIR__)) . '/core/plugin-api.php';
// Load active plugins here (not only from the plugin_page/admin_menu code
// paths) so pl_apply_filter() calls inside admin-data-layer.php — e.g. the
// item-before-save filter — actually have a plugin's callbacks registered
// by the time content.php runs, not just when a plugin's own page is open.
pl_load_active_plugins();
require_once dirname(dirname(__DIR__)) . '/core/admin-data-layer.php';

/**
 * Wrapper functions for backwards compatibility
 * This makes it so you don't have to update all your template files
 */
if (!function_exists('loadData'))          { function loadData() { return admin_load_data(); } }
if (!function_exists('saveData'))          { function saveData($data) { return admin_save_data($data); } }
if (!function_exists('getBaseUrl'))        { function getBaseUrl() { return admin_site_url(); } }
if (!function_exists('adminCleanUrl'))     { function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') { return admin_content_url($contentType, $slug, $customSlug, $category); } }
if (!function_exists('formatFileSize'))    { function formatFileSize($bytes) { return admin_format_file_size($bytes); } }
if (!function_exists('getAvailableThemes')){ function getAvailableThemes() { return admin_get_themes(); } }
if (!function_exists('decodeHtmlEntities')){ function decodeHtmlEntities($html) { return admin_decode_html($html); } }
