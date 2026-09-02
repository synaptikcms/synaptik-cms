<?php

function _lang_context(): string {
	return defined('LANG_CONTEXT') ? LANG_CONTEXT : 'front';
}

function _lang_cms_root(): string {
	static $root = null;
	if ($root !== null) return $root;

	if (defined('CMS_ROOT')) {
		$root = CMS_ROOT;
		return $root;
	}

	$dir = __DIR__;
	for ($i = 0; $i < 3; $i++) {
		if (file_exists($dir . '/config.json')) {
			$root = rtrim($dir, '/\\');
			return $root;
		}
		$dir = dirname($dir);
	}

	$root = rtrim(__DIR__, '/\\');
	return $root;
}

function _lang_json_dir(?string $scope = null): string {
	$scope = $scope ?? _lang_context();
	$sub = $scope === 'admin' ? '/lang/admin/' : '/lang/front/';
	return _lang_cms_root() . $sub;
}

function _lang_json_path(string $locale, ?string $scope = null): string {
	$dir  = _lang_json_dir($scope);
	$file = $dir . $locale . '.json';
	return file_exists($file) ? $file : $dir . 'en.json';
}

function _lang_cache_path(string $locale, ?string $scope = null): string {
	$sub = ($scope ?? _lang_context()) === 'admin' ? 'admin' : 'front';
	return _lang_cms_root() . '/cache/lang/' . $sub . '/' . $locale . '.php';
}

function _lang_cache_is_valid(string $cachePath, string $jsonPath): bool {
	if (!file_exists($cachePath)) return false;

	$cacheMtime    = filemtime($cachePath);
	$jsonMtime     = filemtime($jsonPath);
	$configPath    = _lang_cms_root() . '/config.json';
	$configMtime   = file_exists($configPath) ? filemtime($configPath) : 0;

	return $cacheMtime >= $jsonMtime && $cacheMtime >= $configMtime;
}

function _lang_opcache_invalidate(string $path): void {
	if (!function_exists('opcache_invalidate')) return;
	@opcache_invalidate($path, true);
}

function _lang_build_cache(string $locale, string $jsonPath, string $cachePath, ?string $scope = null): array {
	$strings = [];

	if (file_exists($jsonPath)) {
		$decoded = json_decode(file_get_contents($jsonPath), true);
		if (is_array($decoded)) {
			$strings = $decoded;
		}
	}

	$cacheDir = dirname($cachePath);
	if (!is_dir($cacheDir)) {
		mkdir($cacheDir, 0755, true);
	}

	$context  = $scope ?? _lang_context();
	$exported = var_export($strings, true);
	$content  = "<?php\n"
			  . "/**\n"
			  . " * Auto-generated cache — DO NOT EDIT MANUALLY.\n"
			  . " * Context  : {$context}\n"
			  . " * Locale   : {$locale}\n"
			  . " * Source   : " . basename(dirname($jsonPath)) . '/' . basename($jsonPath) . "\n"
			  . " * Generated: " . date('Y-m-d H:i:s') . "\n"
			  . " * Invalidated automatically when config.json or the source .json changes.\n"
			  . " */\n"
			  . "return {$exported};\n";

	$tmpPath = $cachePath . '.tmp.' . getmypid();
	if (file_put_contents($tmpPath, $content, LOCK_EX) !== false) {
		rename($tmpPath, $cachePath);

		_lang_opcache_invalidate($cachePath);
	} else {
		error_log("Synaptik CMS lang-cache: cannot write {$cachePath}");
	}

	return $strings;
}

if (!isset($GLOBALS['_LANG_STRINGS'])) {
	$GLOBALS['_LANG_STRINGS'] = null;
}

function lang_load(): array {
	global $_LANG_STRINGS;

	if ($_LANG_STRINGS !== null) {
		return $_LANG_STRINGS;
	}

	$settingsFile = _lang_cms_root() . '/config.json';
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

function lang_load_for_scope(string $scope, ?string $locale = null): array {
	static $cache = [];

	$scope = $scope === 'admin' ? 'admin' : 'front';

	if ($locale === null) {
		$settingsFile = _lang_cms_root() . '/config.json';
		$locale = 'en';
		if (file_exists($settingsFile)) {
			$s = json_decode(file_get_contents($settingsFile), true);
			if (is_array($s)) {
				$locale = $scope === 'admin'
					? ($s['admin_language'] ?? $s['active_language'] ?? 'en')
					: ($s['active_language'] ?? 'en');
			}
		}
	}

	$cacheKey = $scope . ':' . $locale;
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$jsonPath  = _lang_json_path($locale, $scope);
	$cachePath = _lang_cache_path($locale, $scope);

	if (_lang_cache_is_valid($cachePath, $jsonPath)) {
		$strings = include $cachePath;
		$cache[$cacheKey] = is_array($strings) ? $strings : [];
		return $cache[$cacheKey];
	}

	$cache[$cacheKey] = _lang_build_cache($locale, $jsonPath, $cachePath, $scope);
	return $cache[$cacheKey];
}

if (!function_exists('__t_for_scope')) {

	function __t_for_scope(string $scope, string $key, ?string $fallback = null): string {
		$strings = lang_load_for_scope($scope);
		return $strings[$key] ?? ($fallback ?? $key);
	}
}

if (!function_exists('lang_current')) {
	function lang_current(): string {
		$settingsFile = _lang_cms_root() . '/config.json';
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
	function lang_js_bridge(): string {
		$strings = lang_load();
		unset($strings['_meta']);
		return json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	}
}

if (!function_exists('lang_available')) {
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
	function __t(string $key, ?string $fallback = null): string {
		$strings = lang_load();
		return $strings[$key] ?? ($fallback ?? $key);
	}
}

if (!function_exists('_e')) {
	function _e(string $key, ?string $fallback = null): void {
		echo __t($key, $fallback);
	}
}

if (!function_exists('__n')) {
	function __n(string $singular, string $plural, int $count): string {
		$key = $count === 1 ? $singular : $plural;
		return sprintf(__t($key), $count);
	}
}

function lang_cache_purge_all(): void {
	$cacheDir = _lang_cms_root() . '/cache/lang/';
	if (!is_dir($cacheDir)) return;
	foreach (glob($cacheDir . '*/*.php') as $file) {
		unlink($file);
		_lang_opcache_invalidate($file);
	}
}

function lang_cache_purge(string $locale): void {
	$cachePath = _lang_cache_path($locale);
	if (file_exists($cachePath)) {
		unlink($cachePath);
		_lang_opcache_invalidate($cachePath);
	}
}