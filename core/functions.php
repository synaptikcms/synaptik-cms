<?php
if (!defined('CMS_ROOT')) define('CMS_ROOT', dirname(__DIR__));
require_once __DIR__ . '/core-functions.php';
require_once __DIR__ . '/data-functions.php';
require_once __DIR__ . '/render/theme-api.php';
require_once __DIR__ . '/data-layer.php';
require_once __DIR__ . '/plugin-api.php';

function hsc(?string $s, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $enc = 'UTF-8'): string
{
    return htmlspecialchars((string) ($s ?? ''), $flags, $enc);
}

function sanitizeSlug($text, $allowSpecialChars = false) {
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	if (empty($text)) {
		return 'item-' . time();
	}

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
	$text = strtolower($text);
	if ($allowSpecialChars) {
		$text = preg_replace('/[^\w\-\.]/', '-', $text);
	} else {
		$text = preg_replace('/[^a-z0-9]/', '-', $text);
	}
	$text = preg_replace('/-+/', '-', $text);
	$text = trim($text, '-');
	return $text;
}

function decodeHtmlEntities($text) {
	return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function _image_dimensions_attr(string $relativePath): string {
	if ($relativePath === '' || strpos($relativePath, '://') !== false) return '';
	$size = @getimagesize(CMS_ROOT . '/' . ltrim($relativePath, '/'));
	if (!$size) return '';
	return ' width="' . (int)$size[0] . '" height="' . (int)$size[1] . '"';
}

function getBaseUrl() {
	$origin = (_sl_request_is_https() ? 'https' : 'http') . '://' . _sl_request_host();

	$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
	$cmsRoot = defined('CMS_ROOT') ? realpath(CMS_ROOT) ?: '' : '';

	if ($docRoot !== '' && $cmsRoot !== '' && strpos($cmsRoot, $docRoot) === 0) {
		$subDir = substr($cmsRoot, strlen($docRoot));
	} else {
		$subDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
	}

	return $origin . $subDir . '/';
}

function sl_type_label(string $type, bool $plural = false): string {
	$settings = loadConfig();
	$labels   = $settings['type_labels'][$type] ?? [];
	$override = $labels[$plural ? 'plural' : 'singular'] ?? '';
	if ($override !== '') return $override;
	$fallback = $labels[$plural ? 'singular' : 'plural'] ?? '';
	if ($fallback !== '') return $fallback;
	return __t($plural ? $type . 's' : $type, ucfirst($type));
}

function sl_type_labels_json(): string {
	$labels = [];
	foreach (['article', 'page', 'project'] as $type) {
		$labels[$type]         = sl_type_label($type, false);
		$labels[$type . 's']   = sl_type_label($type, true);
	}
	return json_encode($labels, JSON_UNESCAPED_UNICODE);
}

function url_slug(string $type): string {
	foreach (['article', 'page', 'project'] as $_baseType) {
		$_plural = null;
		if ($type === $_baseType) $_plural = false;
		elseif ($type === $_baseType . 's') $_plural = true;
		if ($_plural !== null) {
			$settings = loadConfig();
			$override = $settings['type_labels'][$_baseType][$_plural ? 'plural' : 'singular'] ?? '';
			if ($override !== '') return sanitizeSlug($override);
			break;
		}
	}

	$raw = __t('url_slug_' . $type, $type);
	return sanitizeSlug($raw);
}

function cleanUrl($type, $slug = null, $page = null, $category = null) {
	$baseUrl = getBaseUrl();

	if ($type === "home") {
		return $baseUrl;
	}

	$pluralMap = ['articles' => 'article', 'projects' => 'project', 'pages' => 'page'];
	if (isset($pluralMap[$type])) {
		$type = $pluralMap[$type];
		return $baseUrl . url_slug($type . 's') . "/";
	}

	if ($type === "category") {
		return $baseUrl . url_slug('category') . "/" . $category . "/";
	}

	if ($type === "tag") {
		return $baseUrl . url_slug('tag') . "/" . $category . "/";
	}

	if (in_array($type, ["article", "project", "page"])) {
		if ($slug === null && $page === null) {
			return $baseUrl . url_slug($type . 's') . "/";
		} elseif ($slug !== null && $page === null) {
			if ($type === "page") {
				if ($category !== null && !empty($category)) {
					$data = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
					$catPath = getCategoryPath(sanitizeSlug($category), $data);
					return $baseUrl . $catPath . "/" . $slug . "/";
				}
				return $baseUrl . $slug . "/";
			} elseif ($type === "article") {
				if ($category !== null && !empty($category)) {
					$data = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
					$catPath = getCategoryPath(sanitizeSlug($category), $data);
					return $baseUrl . $catPath . "/" . $slug . "/";
				}
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
			return $baseUrl . url_slug($type . 's') . "/page/" . $page . "/";
		}
	}

	return $baseUrl;
}

function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') {
	$baseUrl = (_sl_request_is_https() ? 'https' : 'http') . '://' . _sl_request_host();
	$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

	$finalSlug    = !empty($customSlug) ? $customSlug : $slug;
	$categorySlug = !empty($category) ? sanitizeSlug($category) : '';

	$catPath = '';
	if (!empty($categorySlug)) {
		$data    = loadData();
		$catPath = getCategoryPath($categorySlug, $data);
	}

	if ($contentType === 'page') {
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

function themePreviewSecret(): string {
    if (!defined('CMS_ROOT')) return '';
    $secretFile = CMS_ROOT . '/private/theme_preview.secret';
    if (file_exists($secretFile)) {
        $secret = file_get_contents($secretFile);
        if ($secret !== false && strlen(trim($secret)) >= 32) return trim($secret);
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secretFile, $secret, LOCK_EX);
    return $secret;
}

function loadConfig() {
	if (isset($GLOBALS['_config_cache'])) {
		return $GLOBALS['_config_cache'];
	}

	$settings = loadDefaultConfig();

	if (file_exists(CMS_ROOT . '/config.json')) {
		$loadedSettings = json_decode(file_get_contents(CMS_ROOT . '/config.json'), true);
		if (is_array($loadedSettings)) {
			$settings = array_merge($settings, $loadedSettings);
		}
	}
	$settings['available_themes'] = getAvailableThemes();
	if (!isset($settings['active_theme']) || !in_array($settings['active_theme'], $settings['available_themes'])) {
		$settings['active_theme'] = 'default';
	}

	if (isset($_GET['_tp']) && session_status() === PHP_SESSION_ACTIVE &&
		isset($_SESSION['admin']) && $_SESSION['admin'] === true
	) {
		$decoded = base64_decode(strtr($_GET['_tp'], '-_', '+/'), true);
		if ($decoded !== false) {
			$parts = explode('|', $decoded, 3);
			if (count($parts) === 3) {
				[$tpTheme, $tpTs, $tpMac] = $parts;
				$tpTheme = basename($tpTheme);
				if (is_numeric($tpTs) && (time() - (int)$tpTs) < 7200) {
					$secret   = themePreviewSecret();
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

	if (!empty($settings['timezone'])) {
		@date_default_timezone_set($settings['timezone']);
	}

	$GLOBALS['_config_cache'] = $settings;
	return $settings;
}

function loadConfig_invalidate(): void {
	unset($GLOBALS['_config_cache']);
}

function getAvailableThemes() {
	$themesDir = CMS_ROOT . '/theme/';
	$themes = [];
	if (!file_exists($themesDir) || !is_dir($themesDir)) {
		return ['default'];
	}
	$items = scandir($themesDir);
	
	foreach ($items as $item) {
		if ($item === '.' || $item === '..' || $item[0] === '.') {
			continue;
		}
		$themePath = $themesDir . $item;
		if (is_dir($themePath)) {
			if (file_exists($themePath . '/css/style.css')) {
				$themes[] = $item;
			}
		}
	}
	if (empty($themes)) {
		$themes[] = 'default';
	}
	
	return $themes;
}

function loadThemeTemplate($template, $params = []) {
   $settings = loadConfig();
   $theme = $settings['active_theme'] ?? 'default';
   extract($params, EXTR_SKIP);
   $basePath = CMS_ROOT;
   $templatePaths = [
   $basePath . "/theme/child_theme/{$theme}/{$template}.php",
   $basePath . "/theme/{$theme}/{$template}.php", // Theme-specific template
   $basePath . "/theme/default/{$template}.php", // Default theme fallback
   $basePath . "/{$template}.php" // Root fallback
   ];

   foreach ($templatePaths as $path) {
	   if (file_exists($path)) {
		   include $path;
		   return;
	   }
   }
   echo "<!-- Template not found: {$template} (Looked in theme/child_theme/{$theme}/, theme/{$theme}/, theme/default/, and root) -->";
}

require_once __DIR__ . '/lang-cache.php';

(function () {
	$__s     = loadConfig();
	$__theme = $__s['active_theme'] ?? 'default';
	$__path  = CMS_ROOT . '/theme/' . $__theme . '/functions.php';
	if (file_exists($__path)) {
		require_once $__path;
	}
	$__customPath = CMS_ROOT . '/theme/child_theme/' . $__theme . '/functions.php';
	if (file_exists($__customPath)) {
		require_once $__customPath;
	}
})();
pl_load_active_plugins();