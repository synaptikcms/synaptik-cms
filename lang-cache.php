<?php
/**
 * Returns the current loading context: 'admin' or 'front'.
 * The constant LANG_CONTEXT must be defined BEFORE the require_once
 * in admin-functions.php. If absent, we are on the front-end.
 */
function _lang_context(): string {
	return defined('LANG_CONTEXT') ? LANG_CONTEXT : 'front';
}

/**
 * Returns the absolute CMS root path (where settings.json lives).
 * Walks up from __DIR__ until settings.json is found (max 3 levels).
 */
function _lang_cms_root(): string {
	static $root = null;
	if ($root !== null) return $root;

	$dir = __DIR__;
	for ($i = 0; $i < 3; $i++) {
		if (file_exists($dir . '/settings.json')) {
			$root = rtrim($dir, '/\\');
			return $root;
		}
		$dir = dirname($dir);
	}

	// Fallback: assume this file is at the CMS root
	$root = rtrim(__DIR__, '/\\');
	return $root;
}

/**
 * Returns the source JSON directory for the current context.
 *   front → lang/
 *   admin → lang/admin/
 */
function _lang_json_dir(): string {
	$sub = _lang_context() === 'admin' ? '/lang/admin/' : '/lang/front/';
	return _lang_cms_root() . $sub;
}

/**
 * Returns the absolute path to the source JSON file for a given locale.
 * Falls back to en.json if the requested locale does not exist.
 */
function _lang_json_path(string $locale): string {
	$dir  = _lang_json_dir();
	$file = $dir . $locale . '.json';
	return file_exists($file) ? $file : $dir . 'en.json';
}

/**
 * Returns the absolute path to the PHP cache file for a given locale.
 *   front → cache/lang/front/{locale}.php
 *   admin → cache/lang/admin/{locale}.php
 *
 * /cache/ is at the CMS root, blocked by .htaccess (Deny from all).
 * Both front-end (functions.php) and admin (admin-functions.php) resolve
 * the CMS root the same way via _lang_cms_root(), so neither context
 * needs to cross into the other's directory.
 */
function _lang_cache_path(string $locale): string {
	$sub = _lang_context() === 'admin' ? 'admin' : 'front';
	return _lang_cms_root() . '/cache/lang/' . $sub . '/' . $locale . '.php';
}

// ─── Cache validation and generation ─────────────────────────────────────────

/**
 * Returns true if the cache file exists and is newer than both
 * the source JSON file and settings.json.
 */
function _lang_cache_is_valid(string $cachePath, string $jsonPath): bool {
	if (!file_exists($cachePath)) return false;

	$cacheMtime    = filemtime($cachePath);
	$jsonMtime     = filemtime($jsonPath);
	$settingsMtime = filemtime(_lang_cms_root() . '/settings.json');

	return $cacheMtime >= $jsonMtime && $cacheMtime >= $settingsMtime;
}

/**
 * Safely calls opcache_invalidate(), swallowing the warning some hosts throw
 * when opcache.restrict_api blocks the OPcache control API for this script's
 * path. The cache file itself is already written correctly either way — this
 * only affects how fast a stale in-memory bytecode copy gets refreshed.
 */
function _lang_opcache_invalidate(string $path): void {
	if (!function_exists('opcache_invalidate')) return;
	@opcache_invalidate($path, true);
}

/**
 * Builds (or rebuilds) the PHP cache file for a given locale.
 *
 * The generated file looks like:
 *   <?php return ['home' => 'Home', 'read_more' => 'Read more', ...];
 *
 * OPcache compiles this as bytecode in shared memory:
 * zero disk I/O and zero json_decode() on subsequent hits.
 *
 * Uses atomic write (tmp file + rename) to prevent concurrent requests
 * from reading a partially written cache file.
 *
 * @return array  The loaded strings (avoids a double read on first hit).
 */
function _lang_build_cache(string $locale, string $jsonPath, string $cachePath): array {
	$strings = [];

	if (file_exists($jsonPath)) {
		$decoded = json_decode(file_get_contents($jsonPath), true);
		if (is_array($decoded)) {
			$strings = $decoded;
		}
	}

	// Create cache directory if it does not exist (cache/lang/front/ or cache/lang/admin/)
	$cacheDir = dirname($cachePath);
	if (!is_dir($cacheDir)) {
		mkdir($cacheDir, 0755, true);
	}

	$context  = _lang_context();
	$exported = var_export($strings, true);
	$content  = "<?php\n"
			  . "/**\n"
			  . " * Auto-generated cache — DO NOT EDIT MANUALLY.\n"
			  . " * Context  : {$context}\n"
			  . " * Locale   : {$locale}\n"
			  . " * Source   : " . basename(dirname($jsonPath)) . '/' . basename($jsonPath) . "\n"
			  . " * Generated: " . date('Y-m-d H:i:s') . "\n"
			  . " * Invalidated automatically when settings.json or the source .json changes.\n"
			  . " */\n"
			  . "return {$exported};\n";

	// Atomic write: write to a tmp file then rename to avoid partial reads
	$tmpPath = $cachePath . '.tmp.' . getmypid();
	if (file_put_contents($tmpPath, $content, LOCK_EX) !== false) {
		rename($tmpPath, $cachePath);

		// Invalidate OPcache entry so stale bytecode is not served until next restart.
		// Some hosts restrict the OPcache control API (opcache.restrict_api) even
		// though the function exists — guard with _lang_opcache_invalidate() so a
		// blocked call degrades silently instead of throwing a warning.
		_lang_opcache_invalidate($cachePath);
	} else {
		// Write failed (permissions) — log and continue without breaking the site
		error_log("SynaptikCMS lang-cache: cannot write {$cachePath}");
	}

	return $strings;
}

// Keep the global variable for compatibility with existing code.
// It holds the strings in memory for the duration of the current request,
// avoiding even the include() call on repeated __t() calls.
if (!isset($GLOBALS['_LANG_STRINGS'])) {
	$GLOBALS['_LANG_STRINGS'] = null;
}

/**
 * Loads the active language strings with PHP file cache (OPcache-friendly).
 * @return array
 */
function lang_load(): array {
	global $_LANG_STRINGS;

	// 1. Already in memory for this request
	if ($_LANG_STRINGS !== null) {
		return $_LANG_STRINGS;
	}

	// Read the active locale for the current context (admin vs front).
	// settings.json may carry separate keys:
	//   - active_language → public site
	//   - admin_language  → admin panel (falls back to active_language if unset)
	$settingsFile = _lang_cms_root() . '/settings.json';
	$locale = 'en';
	if (file_exists($settingsFile)) {
		$s = json_decode(file_get_contents($settingsFile), true);
		if (is_array($s)) {
			if (_lang_context() === 'admin') {
				$locale = $s['admin_language'] ?? $s['active_language'] ?? 'en';
			} else {
				$locale = $s['active_language'] ?? 'en';
			}
		}
	}

	$jsonPath  = _lang_json_path($locale);
	$cachePath = _lang_cache_path($locale);

	// 2. Valid PHP cache — include() lets OPcache serve the array from RAM
	if (_lang_cache_is_valid($cachePath, $jsonPath)) {
		$strings = include $cachePath;
		$_LANG_STRINGS = is_array($strings) ? $strings : [];
		return $_LANG_STRINGS;
	}

	// 3. Cache missing or stale — regenerate from source JSON
	$_LANG_STRINGS = _lang_build_cache($locale, $jsonPath, $cachePath);
	return $_LANG_STRINGS;
}

if (!function_exists('lang_current')) {
	/**
	 * Returns the active locale string for the current context
	 * (admin → admin_language ?? active_language, front → active_language).
	 */
	function lang_current(): string {
		$settingsFile = _lang_cms_root() . '/settings.json';
		if (file_exists($settingsFile)) {
			$s = json_decode(file_get_contents($settingsFile), true);
			if (is_array($s)) {
				if (_lang_context() === 'admin') {
					return $s['admin_language'] ?? $s['active_language'] ?? 'en';
				}
				return $s['active_language'] ?? 'en';
			}
		}
		return 'en';
	}
}

if (!function_exists('lang_js_bridge')) {
	/**
	 * Returns all strings serialized as JSON for window.CMS_LANG injection.
	 * Strips the _meta key (file metadata, not needed in JS).
	 */
	function lang_js_bridge(): string {
		$strings = lang_load();
		unset($strings['_meta']);
		return json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	}
}

if (!function_exists('lang_available')) {
	/**
	 * Returns available languages by scanning the active context's lang directory.
	 * Used to populate the language dropdown in admin settings.
	 *
	 * @return array  e.g. ['en' => 'English', 'fr' => 'Français']
	 */
	function lang_available(): array {
		$langDir   = _lang_json_dir();
		$languages = [];
		if (!is_dir($langDir)) return ['en' => 'English'];
		foreach (glob($langDir . '*.json') as $file) {
			$locale = basename($file, '.json');
			$data   = json_decode(file_get_contents($file), true);
			$label  = $data['_meta']['language'] ?? strtoupper($locale);
			$languages[$locale] = $label;
		}
		ksort($languages);
		return $languages;
	}
}

if (!function_exists('lang_available_for_scope')) {
	/**
	 * Same as lang_available() but for an explicit scope, independent of
	 * the current LANG_CONTEXT. Used by the admin settings page to list
	 * both the front-end and the admin available locales side by side.
	 *
	 * @param string $scope  'front' or 'admin'
	 * @return array         e.g. ['en' => 'English', 'fr' => 'Français']
	 */
	function lang_available_for_scope(string $scope): array {
		$sub = $scope === 'admin' ? '/lang/admin/' : '/lang/front/';
		$langDir = _lang_cms_root() . $sub;
		$languages = [];
		if (!is_dir($langDir)) return ['en' => 'English'];
		foreach (glob($langDir . '*.json') as $file) {
			$locale = basename($file, '.json');
			$data   = json_decode(file_get_contents($file), true);
			$label  = $data['_meta']['language'] ?? strtoupper($locale);
			$languages[$locale] = $label;
		}
		ksort($languages);
		return $languages;
	}
}

if (!function_exists('__t')) {
	/**
	 * Translates a key. Returns the fallback (or the key itself) if not found.
	 */
	function __t(string $key, ?string $fallback = null): string {
		$strings = lang_load();
		return $strings[$key] ?? ($fallback ?? $key);
	}
}

if (!function_exists('_e')) {
	/**
	 * Translates and immediately echoes a key (shorthand for echo __t()).
	 */
	function _e(string $key, ?string $fallback = null): void {
		echo __t($key, $fallback);
	}
}

if (!function_exists('__n')) {
	/**
	 * Pluralization: picks the singular or plural key based on $count.
	 * The plural string must contain %d as a placeholder for the count.
	 */
	function __n(string $singular, string $plural, int $count): string {
		$key = $count === 1 ? $singular : $plural;
		return sprintf(__t($key), $count);
	}
}

/**
 * Purges the entire cache (both front and admin, all locales).
 * Useful when a .json file is edited directly on disk.
 */
function lang_cache_purge_all(): void {
	$cacheDir = _lang_cms_root() . '/cache/lang/';
	if (!is_dir($cacheDir)) return;
	foreach (glob($cacheDir . '*/*.php') as $file) {
		unlink($file);
		_lang_opcache_invalidate($file);
	}
}

/**
 * Purges the cache for a specific locale in the current context.
 * Example: lang_cache_purge('fr');
 */
function lang_cache_purge(string $locale): void {
	$cachePath = _lang_cache_path($locale);
	if (file_exists($cachePath)) {
		unlink($cachePath);
		_lang_opcache_invalidate($cachePath);
	}
}
