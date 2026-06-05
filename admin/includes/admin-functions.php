<?php
// Define constants
if (!defined('INCLUDED')) define('INCLUDED', true);

/**
 * Admin wrapper for sanitizeSlug function
 * Ensures the function is available in admin context
 */
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
 * Load settings from settings.json, merged with hardcoded defaults.
 * Unique source of truth pour tous les paramètres de l'application.
 * Utilisé dans tout l'admin via admin_load_settings().
 */
function admin_load_settings(): array {
	$settings = loadDefaultSettings();

	$settingsFile = dirname(dirname(__DIR__)) . '/settings.json';
	if (file_exists($settingsFile)) {
		$loaded = json_decode(file_get_contents($settingsFile), true);
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
 * Format a date according to admin settings.
 * Handles both legacy 'YYYY-MM-DD' and datetime 'YYYY-MM-DD HH:MM' strings.
 *
 * @param string $date  Raw date string stored in JSON
 * @return string       Formatted date (date part only)
 */
function admin_format_date($date) {
	if (empty($date)) return '';

	$appSettings = admin_load_settings();
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

function setAdminMessage($message, $type = 'success') {
	// Don't store in session, use global variables instead
	global $admin_message, $admin_message_type;
	$admin_message = $message;
	$admin_message_type = $type;
}

// Define our own versions of functions to avoid conflicts
// Load main site functions in a way that doesn't cause conflicts
function admin_require_core_functions() {
	// Include core functions without function conflicts
	include_once '../data-functions.php';
	include_once '../core-functions.php';
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
 * Persist updated credentials to admin-credentials.php.
 * Preserves all existing fields; only overwrites the ones provided.
 *
 * @param array $fields  Associative array of fields to update.
 *                       Accepted keys: password_hash, username, display_name, email.
 * @return bool  True on success.
 */
function admin_save_credentials(array $fields): bool {
	$credFile = __DIR__ . '/../admin-credentials.php';

	// Load current values as baseline
	$admin_username     = 'admin';
	$admin_display_name = '';
	$admin_password     = '';
	$admin_email        = '';
	if (file_exists($credFile)) {
		include $credFile;
	}

	if (isset($fields['username']))     $admin_username     = $fields['username'];
	if (isset($fields['display_name'])) $admin_display_name = $fields['display_name'];
	if (isset($fields['password_hash'])) $admin_password    = $fields['password_hash'];
	if (isset($fields['email']))        $admin_email        = $fields['email'];

	$esc = fn(string $v): string => str_replace("'", "\\'", $v);

	$content  = "<?php\n";
	$content .= "// Admin credentials — do not edit this file manually\n";
	$content .= "\$admin_username     = '" . $esc($admin_username)     . "';\n";
	$content .= "\$admin_display_name = '" . $esc($admin_display_name) . "';\n";
	$content .= "\$admin_password     = '" . $esc($admin_password)     . "';\n";
	if ($admin_email !== '') {
		$content .= "\$admin_email        = '" . $esc($admin_email) . "';\n";
	}
	$content .= "?>\n";

	return file_put_contents($credFile, $content) !== false;
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

	// Mise à jour du timestamp à chaque requête authentifiée
	$_SESSION['admin_last_activity'] = time();
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
function admin_url() {
	$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'];
	$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
	
	return $protocol . '://' . $host . $basePath . '/';
}

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
 * Sanitize HTML content to prevent XSS attacks
 */
function admin_purify_html($html) {
	 // Protect code blocks first
	 $codeBlocks = [];
	 $placeholder = '___PROTECTED_CODE_';
	 
	 // Extract <pre> blocks (which contain <code>) - fixed pattern
	 $html = preg_replace_callback(
		 '/<pre\b[^>]*>(?:(?!<\/pre>).)*<\/pre>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = $matches[0];
			 return $placeholder . $index . '___';
		 },
		 $html
	 );
	 
	 // Protect standalone <code> blocks (not inside <pre>)
	 $html = preg_replace_callback(
		 '/<code\b[^>]*>(?:(?!<\/code>).)*<\/code>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 if (strpos($matches[0], '___PROTECTED_CODE_') !== false) {
				 return $matches[0];
			 }
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = $matches[0];
			 return $placeholder . $index . '___';
		 },
		 $html
	 );
	 
	 // Purify the rest
	 //$allowedTags = '<p><br><b><i><strong><em><u><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody>';
	 $allowedTags = '<p><br><b><i><strong><em><u><s><strike><del><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody><button>';
	 
	 $html = strip_tags($html, $allowedTags);
	 
	 // Remove dangerous attributes
	 $html = preg_replace(
		 '/<(.*?)[\s\r\n]+(on[a-z]+)[\s\r\n]*=[\s\r\n]*["\']([^"\']*)["\'][^>]*>/i',
		 '<$1>',
		 $html
	 );
	 
	 // Remove javascript: URLs
	 $html = preg_replace(
		 '/<a[^>]*href[\s\r\n]*=[\s\r\n]*["\']javascript:[^"\']*["\'][^>]*>/i',
		 '<a>',
		 $html
	 );
	 
	 // Remove malicious URL schemes
	 $html = preg_replace(
		 '/<a[^>]*href[\s\r\n]*=[\s\r\n]*["\'](?!http|https|ftp|mailto|tel|#)([^"\']*)["\'][^>]*>/i',
		 '<a href="#$1">',
		 $html
	 );
	 
	 // Restore protected code blocks
	 $html = preg_replace_callback(
		 '/___PROTECTED_CODE_(\d+)___/',
		 function($matches) use ($codeBlocks) {
			 return $codeBlocks[$matches[1]] ?? '';
		 },
		 $html
	 );
	 
	 return $html;
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
	static $strings = null;
	if ($strings === null) {
		// _lang_cms_root() is defined in lang-cache.php, already loaded above
		$locale   = lang_current();
		$langFile = _lang_cms_root() . '/lang/' . $locale . '.json';
		if (!file_exists($langFile)) {
			$langFile = _lang_cms_root() . '/lang/en.json';
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
		$data    = admin_load_data();
		$catPath = getCategoryPath($categorySlug, $data);
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
			return sprintf(__t('add_new_type'), __t('type_' . $contentType));
		}

		if ($action === 'edit') {
			$contentType = in_array($type, ['article', 'page', 'project']) ? $type : 'content';
			return sprintf(__t('edit_type'), __t('type_' . $contentType));
		}

		if ($action === 'manage_categories') return __t('manage_categories');
		if ($action === 'manage_tags')       return __t('manage_tags');
		if ($action === 'settings')          return __t('settings');
		if ($action === 'drafts')            return __t('drafts');
		if ($action === 'manage_themes')     return __t('theme_manager_title');
		if ($action === 'backup')            return __t('backup_export');
		if ($action === 'menu_builder')      return __t('menu_configuration');
		if ($action === 'account')           return __t('account');

		if (empty($action) && in_array($type, ['article', 'page', 'project'])) {
			return __t('type_' . $type . 's');
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
 * Generate a smart hierarchical menu for themes
 * This function should be called from your theme files
 * 
 * @return array Hierarchical menu structure
 */
function get_smart_default_menu() {
	$settings = admin_load_settings();
	$data = admin_load_data();
	
	// If using custom menu, return it as-is
	if (!empty($settings['use_custom_menu']) && !empty($settings['main_menu'])) {
		return $settings['main_menu'];
	}
	
	// Build default menu
	$menuItems = [];
	$contentTypes = ['article', 'page', 'project'];
	
	// Determine grouping and labeling settings
	$groupByType = !empty($settings['default_menu_group_by_type']);
	$showTypeLabels = !empty($settings['default_menu_show_type_labels']);
	$orderBy = $settings['default_menu_order'] ?? 'alphabetical';
	
	foreach ($contentTypes as $type) {
		if (empty($data[$type])) continue;
		
		// Filter items that should show in menu
		$items = array_filter($data[$type], function($item) {
			return !empty($item['show_in_menu']);
		});
		
		if (empty($items)) continue;
		
		// Sort items based on settings
		usort($items, function($a, $b) use ($orderBy) {
			switch ($orderBy) {
				case 'menu_order':
					$orderA = isset($a['menu_order']) ? (int)$a['menu_order'] : 0;
					$orderB = isset($b['menu_order']) ? (int)$b['menu_order'] : 0;
					if ($orderA === $orderB) {
						return strcasecmp($a['title'], $b['title']);
					}
					return $orderA - $orderB;
					
				case 'date_desc':
					return strcmp($b['date'] ?? '', $a['date'] ?? '');
					
				case 'date_asc':
					return strcmp($a['date'] ?? '', $b['date'] ?? '');
					
				case 'alphabetical':
				default:
					return strcasecmp($a['title'], $b['title']);
			}
		});
		
		// Create menu items for this content type
		$typeItems = [];
		foreach ($items as $item) {
			$slug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
			$typeItems[] = [
				'type' => 'content',
				'label' => $item['title'],
				'url' => '/' . $type . '/' . $slug,
				'content_type' => $type,
				'content_slug' => $slug
			];
		}
		
		// Add to menu with or without grouping
		if ($groupByType && $showTypeLabels) {
			// Add parent type label
			$typeLabel = ucfirst($type) . 's'; // "Articles", "Pages", "Projects"
			$menuItems[] = [
				'type' => 'parent',
				'label' => $typeLabel,
				'url' => '/' . $type,
				'children' => $typeItems
			];
		} elseif ($groupByType && !$showTypeLabels) {
			// Group but don't show labels - just add items directly
			$menuItems = array_merge($menuItems, $typeItems);
		} else {
			// No grouping - flat menu
			$menuItems = array_merge($menuItems, $typeItems);
		}
	}
	
	return $menuItems;
}

/**
 * Sync menu URLs in settings.json when a category is renamed.
 * Rebuilds the URL for every menu item whose content belongs to the renamed category.
 *
 * @param array  $data            The already-updated data array (post-save)
 * @param string $oldCategorySlug The slug of the old category name
 * @param string $newCategoryName The new category name (not yet slugified)
 */
function syncMenuUrlsForCategory($data, $oldCategorySlug, $newCategoryName) {
	$settingsFile = '../settings.json';
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
 * Sync menu URLs in settings.json when a tag is renamed or deleted.
 * Updates menu items of type 'tag' whose content_slug matches the old tag slug.
 *
 * @param string      $oldTagSlug  Slug of the old tag name
 * @param string|null $newTagName  New tag name, or null if deleted
 */
function syncMenuUrlsForTag($oldTagSlug, $newTagName) {
	$settingsFile = '../settings.json';
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

	$settings  = admin_load_settings();
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
		 curl_close($ch);
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
		curl_close($ch);
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

define('LANG_CONTEXT', 'admin');
require_once dirname(dirname(__DIR__)) . '/lang-cache.php';
// Load split-file data layer (read) and admin data layer (write)
require_once dirname(dirname(__DIR__)) . '/data-layer.php';
require_once dirname(dirname(__DIR__)) . '/admin-data-layer.php';

/**
 * Wrapper functions for backwards compatibility
 * This makes it so you don't have to update all your template files
 */
if (!function_exists('isLoggedIn'))       { function isLoggedIn() { return admin_is_logged_in(); } }
if (!function_exists('loadData'))          { function loadData() { return admin_load_data(); } }
if (!function_exists('saveData'))          { function saveData($data) { return admin_save_data($data); } }
if (!function_exists('getBaseUrl'))        { function getBaseUrl() { return admin_site_url(); } }
if (!function_exists('adminCleanUrl'))     { function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') { return admin_content_url($contentType, $slug, $customSlug, $category); } }
if (!function_exists('formatFileSize'))    { function formatFileSize($bytes) { return admin_format_file_size($bytes); } }
if (!function_exists('getAvailableThemes')){ function getAvailableThemes() { return admin_get_themes(); } }
if (!function_exists('decodeHtmlEntities')){ function decodeHtmlEntities($html) { return admin_decode_html($html); } }