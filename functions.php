<?php
/**
 * Core Functions
 * 
 * Contains essential system functions for the CMS.
 */

// Include specialized function libraries
require_once dirname(__FILE__) . '/core-functions.php';
require_once dirname(__FILE__) . '/data-functions.php';
require_once dirname(__FILE__) . '/theme-api.php';
require_once dirname(__FILE__) . '/data-layer.php';

/**
 * Sanitize a string to create a valid slug
 * @param string $text The text to sanitize
 * @param bool $allowSpecialChars Whether to allow special characters
 * @return string Sanitized slug
 */
function sanitizeSlug($text, $allowSpecialChars = false) {
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	if (empty($text)) {
		return 'item-' . time();
	}

	// Transliterate accented characters to ASCII equivalents (explicit map — iconv TRANSLIT unreliable)
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
	$text = strtr($text, $accents);

	// Convert to lowercase
	$text = strtolower($text);

	if ($allowSpecialChars) {
		// For custom slugs, allow hyphens and certain special characters
		$text = preg_replace('/[^\w\-\.]/', '-', $text);
	} else {
		// For auto-generated slugs, be more restrictive
		$text = preg_replace('/[^a-z0-9]/', '-', $text);
	}

	// Replace multiple hyphens with a single hyphen
	$text = preg_replace('/-+/', '-', $text);

	// Remove hyphens from start and end
	$text = trim($text, '-');

	return $text;
}

/**
 * Decode HTML entities
 * @param string $text The text to decode
 * @return string Decoded text
 */
function decodeHtmlEntities($text) {
	return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Get the base URL with proper trailing slash
 * @return string Base URL
 */
function getBaseUrl() {
	$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
	          || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
	          || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
	$baseUrl = ($isHttps ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
	$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
	return $baseUrl . $baseDir . '/';
}

function url_slug(string $type): string {
	// No static cache here: lang_load() already keeps strings in $GLOBALS['_LANG_STRINGS'],
	// so this is a plain array lookup — fast enough without a second cache layer.
	// A static cache here caused stale English values when the locale was not yet
	// loaded on the first call (e.g. 'category' instead of 'categorie').
	$raw = __t('url_slug_' . $type, $type);
	return sanitizeSlug($raw);
}

/**
 * Generate clean URL for content
 * @param string $type Content type (article, page, project, etc.)
 * @param string $slug Content slug
 * @param string $page Page number (for pagination)
 * @param string $category Category slug
 * @return string Clean URL
 */
function cleanUrl($type, $slug = null, $page = null, $category = null) {
	$baseUrl = getBaseUrl();

	if ($type === "home") {
		return $baseUrl;
	}

	// Accept plural type aliases — map them to the singular and force list mode
	$pluralMap = ['articles' => 'article', 'projects' => 'project', 'pages' => 'page'];
	if (isset($pluralMap[$type])) {
		$type = $pluralMap[$type];
		return $baseUrl . url_slug($type . 's') . "/";
	}

	// Category listing: use localized 'category' prefix
	if ($type === "category") {
		return $baseUrl . url_slug('category') . "/" . $category . "/";
	}

	// Tag listing: use localized 'tag' prefix
	if ($type === "tag") {
		return $baseUrl . url_slug('tag') . "/" . $category . "/";
	}

	if (in_array($type, ["article", "project", "page"])) {
		if ($slug === null && $page === null) {
			// Content type list: use localized plural prefix (e.g. 'articles', 'projets')
			return $baseUrl . url_slug($type . 's') . "/";
		} elseif ($slug !== null && $page === null) {
			if ($type === "page") {
				if ($category !== null && !empty($category)) {
					$data = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
					$catPath = getCategoryPath(sanitizeSlug($category), $data);
					return $baseUrl . $catPath . "/" . $slug . "/";
				}
				// Pages without category appear at root level (no type prefix)
				return $baseUrl . $slug . "/";
			} elseif ($type === "article") {
				if ($category !== null && !empty($category)) {
					$data = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
					$catPath = getCategoryPath(sanitizeSlug($category), $data);
					return $baseUrl . $catPath . "/" . $slug . "/";
				}
				// Articles without category: use localized 'article' prefix
				return $baseUrl . url_slug('article') . "/" . $slug . "/";
			} elseif ($type === "project") {
				if ($category !== null && !empty($category)) {
					$data = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
					$catPath = getCategoryPath(sanitizeSlug($category), $data);
					return $baseUrl . url_slug('project') . "/" . $catPath . "/" . $slug . "/";
				}
				return $baseUrl . url_slug('project') . "/" . $slug . "/";
			}
		} elseif ($page !== null) {
			// Pagination: use localized plural prefix
			return $baseUrl . url_slug($type . 's') . "/page/" . $page . "/";
		}
	}

	return $baseUrl;
}

/**
 * Generate clean URL for viewing content from admin
 * This function ensures compatibility with the cleanUrl function from functions.php
 */
function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') {
	$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
	          || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
	          || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
	$baseUrl = ($isHttps ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
	$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

	$finalSlug    = !empty($customSlug) ? $customSlug : $slug;
	$categorySlug = !empty($category) ? sanitizeSlug($category) : '';

	// Resolve full hierarchical category path when a category is set
	$catPath = '';
	if (!empty($categorySlug)) {
		$data    = loadData();
		$catPath = getCategoryPath($categorySlug, $data);
	}

	if ($contentType === 'page') {
		// Pages without category appear at root; with category they nest under it
		if (!empty($catPath)) {
			return $baseUrl . $baseDir . '/' . $catPath . '/' . $finalSlug . '/';
		}
		return $baseUrl . $baseDir . '/' . $finalSlug . '/';
	}

	if ($contentType === 'article' && !empty($catPath)) {
		return $baseUrl . $baseDir . '/' . $catPath . '/' . $finalSlug . '/';
	} elseif ($contentType === 'project' && !empty($catPath)) {
		return $baseUrl . $baseDir . '/' . url_slug('project') . '/' . $catPath . '/' . $finalSlug . '/';
	}

	// No category: use localized type prefix (article/project); pages stay at root
	if ($contentType === 'article') {
		return $baseUrl . $baseDir . '/' . url_slug('article') . '/' . $finalSlug . '/';
	}
	if ($contentType === 'project') {
		return $baseUrl . $baseDir . '/' . url_slug('project') . '/' . $finalSlug . '/';
	}

	return $baseUrl . $baseDir . '/' . $finalSlug . '/';
}

/**
 * Load settings from settings.json, with per-request GLOBALS cache.
 *
 * The file is read from disk only once per PHP request. Every subsequent
 * call within the same request returns the cached array instantly.
 * Call loadSettings_invalidate() after writing settings.json to force
 * a fresh read on the next call within the same request.
 *
 * @return array Merged settings array (defaults + settings.json overrides)
 */
function loadSettings() {
	// Per-request cache — avoids repeated file_get_contents / json_decode
	// on the same settings.json within a single PHP request.
	if (isset($GLOBALS['_settings_cache'])) {
		return $GLOBALS['_settings_cache'];
	}

	$settings = loadDefaultSettings();

	if (file_exists(__DIR__ . '/settings.json')) {
		$loadedSettings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
		if (is_array($loadedSettings)) {
			$settings = array_merge($settings, $loadedSettings);
		}
	}

	// Always refresh the theme list from filesystem — runs even when settings.json is absent
	$settings['available_themes'] = getAvailableThemes();

	// Ensure theme setting always exists and is valid
	if (!isset($settings['active_theme']) || !in_array($settings['active_theme'], $settings['available_themes'])) {
		$settings['active_theme'] = 'default';
	}

	// Theme preview override via signed GET token (_tp).
	// Token is HMAC-SHA256(themeName, adminPasswordHash) with a TTL of 2 hours.
	// No session state is used — the token is self-contained and per-request only.
	if (isset($_GET['_tp']) && session_status() === PHP_SESSION_ACTIVE &&
		isset($_SESSION['admin']) && $_SESSION['admin'] === true
	) {
		$decoded = base64_decode(strtr($_GET['_tp'], '-_', '+/'), true);
		if ($decoded !== false) {
			// Format stored in token: "themeName|timestamp|hmac"
			$parts = explode('|', $decoded, 3);
			if (count($parts) === 3) {
				[$tpTheme, $tpTs, $tpMac] = $parts;
				$tpTheme = basename($tpTheme);
				// Validate TTL (2 hours)
				if (is_numeric($tpTs) && (time() - (int)$tpTs) < 7200) {
					// Derive secret from admin password hash stored on disk.
					// resolve_admin_dir() reads admin_dir from settings.json so this
					// works regardless of what the admin folder was renamed to.
					$credFile = dirname(__FILE__) . '/' . resolve_admin_dir() . '/admin-credentials.php';
					if (file_exists($credFile)) {
						$admin_password = '';
						require $credFile; // Sets $admin_password
						$secret   = hash('sha256', $admin_password . 'theme_preview_salt');
						$expected = hash_hmac('sha256', $tpTheme . '|' . $tpTs, $secret);
						if (hash_equals($expected, $tpMac) &&
							in_array($tpTheme, $settings['available_themes'])
						) {
							$settings['active_theme'] = $tpTheme;
						}
					}
				}
			}
		}
	}

	// Apply timezone from settings so all PHP date() calls use the correct zone.
	// Falls back to server default if the setting is missing or invalid.
	if (!empty($settings['timezone'])) {
		@date_default_timezone_set($settings['timezone']);
	}

	$GLOBALS['_settings_cache'] = $settings;
	return $settings;
}

/**
 * Invalidate the per-request settings cache.
 * Call this immediately after writing settings.json so that subsequent
 * loadSettings() calls within the same request see the updated values.
 */
function loadSettings_invalidate(): void {
	unset($GLOBALS['_settings_cache']);
}

/**
 * Get available themes by scanning the theme directory
 * @return array List of available themes
 */
function getAvailableThemes() {
	$themesDir = dirname(__FILE__) . '/theme/';
	$themes = [];
	
	// Make sure the theme directory exists
	if (!file_exists($themesDir) || !is_dir($themesDir)) {
		// If no theme directory, return at least the default theme
		return ['default'];
	}
	
	// Scan the theme directory for subdirectories (themes)
	$items = scandir($themesDir);
	
	foreach ($items as $item) {
		// Skip . and .. directories, and hidden directories starting with .
		if ($item === '.' || $item === '..' || $item[0] === '.') {
			continue;
		}
		
		$themePath = $themesDir . $item;
		
		// Check if it's a directory
		if (is_dir($themePath)) {
			// Make sure it's a valid theme by checking for essential files
			// At minimum, a theme should have a CSS file
			if (file_exists($themePath . '/css/style.css')) {
				$themes[] = $item;
			}
		}
	}
	
	// If no valid themes found, at least return default
	if (empty($themes)) {
		$themes[] = 'default';
	}
	
	return $themes;
}

/**
* Load a theme template file
* @param string $template The template name without extension
* @param array $params Parameters to pass to the template
* @return void
*/
function loadThemeTemplate($template, $params = []) {
   $settings = loadSettings();
   $theme = $settings['active_theme'] ?? 'default';

   // Extract parameters into variables
   extract($params);

   // Define possible template paths with FULL server paths
   $basePath = dirname(__FILE__);
   $templatePaths = [
   $basePath . "/theme/{$theme}/{$template}.php", // Theme-specific template
   $basePath . "/theme/default/{$template}.php", // Default theme fallback
   $basePath . "/{$template}.php" // Root fallback
   ];

   // Try to include the first template that exists
   foreach ($templatePaths as $path) {
	   if (file_exists($path)) {
		   include $path;
		   return;
	   }
   }
   // If no template was found, output an error message
   echo "<!-- Template not found: {$template} (Looked in theme/{$theme}/, theme/default/, and root) -->";
}

// ─── Internationalisation ─────────────────────────────────────────────────────
require_once __DIR__ . '/lang-cache.php';

// ─── Theme functions ──────────────────────────────────────────────────────────
// Auto-load the active theme's functions.php after all core libraries are ready.
// Theme functions.php can register hooks, define custom shortcodes, or add any
// theme-specific PHP behaviour. It cannot redeclare core functions — use theme
// partial files (partials/article-card.php etc.) to override rendered HTML instead.
(function () {
	$__s    = loadSettings();
	$__path = __DIR__ . '/theme/' . ($__s['active_theme'] ?? 'default') . '/functions.php';
	if (file_exists($__path)) {
		require_once $__path;
	}
})();