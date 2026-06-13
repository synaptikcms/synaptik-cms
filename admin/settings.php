<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

require_once 'includes/admin-functions.php';

// Load settings
$appSettings = admin_load_settings();

// Handle cache clear request
if (isset($_POST['clear_cache'])) {
	sl_clear_all_cache();
	// Also remove the media stats cache
	$_mediaCacheFile = dirname(__DIR__) . '/cache/media-stats.json';
	if (file_exists($_mediaCacheFile)) {
		@unlink($_mediaCacheFile);
	}
	$_SESSION['message'] = __t('cache_cleared');
	header('Location: index.php?action=settings&tab=general');
	exit;
}

// Handle menu builder submissions
if (isset($_POST['save_menu'])) {
	// Set the use_custom_menu flag
	$appSettings['use_custom_menu'] = isset($_POST['use_custom_menu']);
	// Save default menu settings
	$appSettings['default_menu_style'] = isset($_POST['default_menu_style']) ? $_POST['default_menu_style'] : 'flat';
	$appSettings['default_menu_order'] = isset($_POST['default_menu_order']) ? $_POST['default_menu_order'] : 'alphabetical';
	$menuItems = [];
	
	// Process menu items if they exist
	if (isset($_POST['menu']) && is_array($_POST['menu'])) {
		foreach ($_POST['menu'] as $item) {
			// Make sure all required fields are set
			if (isset($item['type']) && isset($item['label']) && isset($item['url'])) {
				// Create a basic menu item with explicit parent_id
				$menuItem = [
					'type' => $item['type'],
					'label' => $item['label'],
					'url' => $item['url'],
					'id' => isset($item['id']) ? $item['id'] : 'menu_item_' . uniqid()
				];
				
				// Explicitly include parent_id, even if it's empty string
				if (isset($item['parent_id'])) {
					$menuItem['parent_id'] = $item['parent_id'] !== '' ? $item['parent_id'] : null;
				} else {
					$menuItem['parent_id'] = null;
				}
				
				// Add content-specific fields if they exist
				if ($item['type'] === 'content' && isset($item['content_type']) && isset($item['content_slug'])) {
					$menuItem['content_type'] = $item['content_type'];
					$menuItem['content_slug'] = $item['content_slug'];
				}
				// Add tag-specific fields if they exist
				if (isset($item['tag_slug'])) {
					$menuItem['tag_slug'] = $item['tag_slug'];
				}

				// Add target (open in new tab)
				$menuItem['target'] = (isset($item['target']) && $item['target'] === '_blank') ? '_blank' : '';
				
				// Add the item to our menu array
				$menuItems[] = $menuItem;
			}
		}
	}
	
	// Update settings with the new menu
	$appSettings['main_menu'] = $menuItems;
	
	// Save settings to file
	$jsonData = json_encode($appSettings, JSON_PRETTY_PRINT);
	$result = file_put_contents(dirname(__DIR__) . '/settings.json', $jsonData);
	
	if ($result !== false) {
		$_SESSION['message'] = __t('menu_saved');
	} else {
		$_SESSION['error'] = __t('menu_save_failed');
	}
	header('Location: index.php?action=menu_builder');
	exit;
}

// Handle general settings
if (isset($_POST['save_settings'])) {
	// Get auto-detected themes
	$autoDetectedThemes = getAvailableThemes();
	
	$availableLangs = lang_available();
	$selectedLang = $_POST['active_language'] ?? 'en';
	if (array_key_exists($selectedLang, $availableLangs)) {
		$appSettings['active_language'] = $selectedLang;
	} else {
		$appSettings['active_language'] = 'en';
	}
	
	$appSettings['articles_per_page'] = (int)$_POST['articles_per_page'];
	$appSettings['show_articles_on_homepage'] = isset($_POST['show_articles_on_homepage']);
	$appSettings['show_projects_on_homepage'] = isset($_POST['show_projects_on_homepage']);
	$appSettings['site_title'] = trim($_POST['site_title']);
	$appSettings['site_description'] = trim($_POST['site_description']);
	$appSettings['default_meta_title'] = trim($_POST['default_meta_title']);
	$appSettings['default_meta_description'] = trim($_POST['default_meta_description']);
	$appSettings['enable_seo'] = isset($_POST['enable_seo']);
	$appSettings['show_site_title_in_header'] = isset($_POST['show_site_title_in_header']);
	$appSettings['homepage_type'] = $_POST['homepage_type'];
	$appSettings['homepage_page_id'] = $_POST['homepage_page_id'];
	$appSettings['show_search_icon'] = isset($_POST['show_search_icon']) ? true : false;
	$appSettings['image_optimization_enabled'] = isset($_POST['image_optimization_enabled']);
	$appSettings['max_width'] = max(100, min(4000, (int)($_POST['max_width'] ?? 1920)));
	$appSettings['max_height'] = max(100, min(4000, (int)($_POST['max_height'] ?? 1080)));
	$appSettings['image_quality'] = max(1, min(100, (int)($_POST['image_quality'] ?? 85)));
	$appSettings['create_thumbnails'] = isset($_POST['create_thumbnails']);
	$appSettings['thumb_width'] = max(50, min(1000, (int)($_POST['thumb_width'] ?? 300)));
	$appSettings['thumb_height'] = max(50, min(1000, (int)($_POST['thumb_height'] ?? 300)));
	$appSettings['projects_per_page'] = max(1, min(20, (int)($_POST['projects_per_page'] ?? 3)));
	$appSettings['show_breadcrumbs'] = isset($_POST['show_breadcrumbs']);
	$appSettings['date_format'] = $_POST['date_format'] ?? 'Y-m-d';
	// Validate timezone against PHP's list to prevent arbitrary string injection
	$submittedTz = $_POST['timezone'] ?? 'UTC';
	$appSettings['timezone'] = in_array($submittedTz, DateTimeZone::listIdentifiers(), true)
		? $submittedTz
		: 'UTC';
	// $appSettings['footer_text'] = htmlspecialchars(trim($_POST['settings']['footer_text'] ?? 'Developed with ♥ by Dorian • &copy; {year}'));
	$appSettings['footer_text'] = trim($_POST['settings']['footer_text'] ?? 'Developed with ♥ by Dorian • &copy; {year}');
	$appSettings['footer_show_login'] = isset($_POST['settings']['footer_show_login']);
	$appSettings['footer_show_social'] = isset($_POST['settings']['footer_show_social']);
	$appSettings['autosave_enabled'] = isset($_POST['autosave_enabled']);
	$appSettings['autosave_interval'] = in_array((int)($_POST['autosave_interval'] ?? 5), [1, 3, 5, 10], true)
		? (int)$_POST['autosave_interval']
		: 5;
	$socialLinks = [];
	if (isset($_POST['settings']['footer_social_links']) && is_array($_POST['settings']['footer_social_links'])) {
		foreach ($_POST['settings']['footer_social_links'] as $link) {
			if (!empty($link['platform']) && !empty($link['url'])) {
				$socialLinks[] = [
					'platform' => htmlspecialchars(trim($link['platform'])),
					'url' => htmlspecialchars(trim($link['url']))
				];
			}
		}
	}
	$appSettings['footer_social_links'] = $socialLinks;
	
	// Preserve active_theme — the theme selector now lives in the Appearance sidebar section,
	// not in the settings form, so active_theme is not posted here. Only update it when
	// a valid value is explicitly submitted (e.g. from the theme manager).
	if (!empty($_POST['active_theme']) && in_array($_POST['active_theme'], $autoDetectedThemes)) {
		$appSettings['active_theme'] = $_POST['active_theme'];
	}
	// If absent or invalid, keep the current value already loaded in $appSettings.
	
	// Always update the available_themes with the auto-detected ones
	$appSettings['available_themes'] = $autoDetectedThemes;
	
	// Contact form settings
	$appSettings['contact_email']           = trim($_POST['contact_email'] ?? '');
	$appSettings['contact_subject']         = trim($_POST['contact_subject'] ?? 'New message from {name}');
	$appSettings['contact_success_message'] = trim($_POST['contact_success_message'] ?? '');
	$appSettings['contact_error_message']   = trim($_POST['contact_error_message'] ?? '');
	// hCaptcha keys — secret key kept as-is if field left blank (avoids accidental clearing)
	$appSettings['hcaptcha_site_key']       = trim($_POST['hcaptcha_site_key'] ?? '');
	$newSecret = trim($_POST['hcaptcha_secret_key'] ?? '');
	if (!empty($newSecret)) {
		$appSettings['hcaptcha_secret_key'] = $newSecret;
	}

	// Homepage SEO overrides
	$appSettings['home_meta_title']       = trim($_POST['home_meta_title']       ?? '');
	$appSettings['home_meta_description'] = trim($_POST['home_meta_description'] ?? '');
	$appSettings['home_meta_keywords']    = trim($_POST['home_meta_keywords']    ?? '');
	$appSettings['home_og_title']         = trim($_POST['home_og_title']         ?? '');
	$appSettings['home_og_description']   = trim($_POST['home_og_description']   ?? '');
	// home_og_image is handled by the image picker below

	// WebP conversion settings
	$appSettings['convert_to_webp'] = isset($_POST['convert_to_webp']);

	// robots.txt — write directly to the file at the CMS root
	if (isset($_POST['robots_txt'])) {
		$_robotsContent = str_replace("\r\n", "\n", $_POST['robots_txt']);
		$_robotsPath    = dirname(__DIR__) . '/robots.txt';
		file_put_contents($_robotsPath, $_robotsContent);
	}

	// Custom fields schema — always fully replaced on save.
	// We iterate all three types unconditionally so that deleting all fields
	// from a type (which sends no POST data for that type) correctly saves an
	// empty array instead of preserving the old values.
	$_cfSchema     = [];
	$_cfRaw        = (isset($_POST['custom_fields_schema']) && is_array($_POST['custom_fields_schema']))
		? $_POST['custom_fields_schema']
		: [];
	$_cfAllowedTypes = ['text', 'textarea', 'number', 'url', 'checkbox', 'select'];
	foreach (['article', 'page', 'project'] as $_cfType) {
		$_cfFields = [];
		if (!empty($_cfRaw[$_cfType]) && is_array($_cfRaw[$_cfType])) {
			foreach ($_cfRaw[$_cfType] as $_field) {
				$_key = preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($_field['key'] ?? '')));
				if ($_key === '') continue;
				$_fType   = in_array($_field['type'] ?? '', $_cfAllowedTypes, true) ? $_field['type'] : 'text';
				$_cfEntry = [
					'key'      => $_key,
					'label'    => htmlspecialchars(trim($_field['label'] ?? $_key)),
					'type'     => $_fType,
					'required' => !empty($_field['required']),
				];
				if ($_fType === 'select' && !empty($_field['options'])) {
					$_cfEntry['options'] = trim($_field['options']);
				}
				$_cfFields[] = $_cfEntry;
			}
		}
		$_cfSchema[$_cfType] = $_cfFields; // empty array when no rows remain
	}
	$appSettings['custom_fields_schema'] = $_cfSchema;
	
	// ── Site logo
	if (!empty($_FILES['site_logo_file']['name']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
		$_logoPath = _settings_upload_image('site_logo_file');
		if ($_logoPath !== '') $appSettings['site_logo'] = $_logoPath;
	} elseif (!empty($_POST['site_logo_path'])) {
		$appSettings['site_logo'] = _settings_sanitize_image_path($_POST['site_logo_path']);
	} elseif (!empty($_POST['site_logo_remove'])) {
		$appSettings['site_logo'] = '';
	}

	// ── Site favicon
	if (!empty($_FILES['site_favicon_file']['name']) && $_FILES['site_favicon_file']['error'] === UPLOAD_ERR_OK) {
		$_faviconPath = _settings_upload_image('site_favicon_file');
		if ($_faviconPath !== '') $appSettings['site_favicon'] = $_faviconPath;
	} elseif (!empty($_POST['site_favicon_path'])) {
		$appSettings['site_favicon'] = _settings_sanitize_image_path($_POST['site_favicon_path']);
	} elseif (!empty($_POST['site_favicon_remove'])) {
		$appSettings['site_favicon'] = '';
	}

	// ── Homepage OG image
	if (!empty($_FILES['home_og_image_file']['name']) && $_FILES['home_og_image_file']['error'] === UPLOAD_ERR_OK) {
		$_homeOgPath = _settings_upload_image('home_og_image_file');
		if ($_homeOgPath !== '') $appSettings['home_og_image'] = $_homeOgPath;
	} elseif (!empty($_POST['home_og_image_path'])) {
		$appSettings['home_og_image'] = _settings_sanitize_image_path($_POST['home_og_image_path']);
	} elseif (!empty($_POST['home_og_image_remove'])) {
		$appSettings['home_og_image'] = '';
	}

	// Save settings
	$saveResult = file_put_contents(dirname(__DIR__) . '/settings.json', json_encode($appSettings, JSON_PRETTY_PRINT));
	if ($saveResult === false) {
		error_log('Failed to save settings to file: ../settings.json');
		$_SESSION['error'] = __t('settings_save_failed');
	} else {
		if (function_exists('loadSettings_invalidate')) loadSettings_invalidate();
		$_SESSION['message'] = __t('settings_saved');
	}
	header('Location: index.php?action=settings&tab=' . $activeTab);
	exit;
}

// Dispatch vers le bon template selon l'action
$action = $_GET['action'] ?? 'settings';
if ($action === 'menu_builder') {
	include 'templates/menu-builder.php';
} else {
	include 'templates/settings-view.php';
}

/**
 * Upload an image from $_FILES to /files/ and return its relative path (files/name.ext).
 * Returns empty string on failure or invalid file type.
 */
function _settings_upload_image(string $inputName): string
{
	$file = $_FILES[$inputName] ?? [];
	if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';

	$allowedMime = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/x-icon','image/vnd.microsoft.icon'];
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$mime  = $finfo->file($file['tmp_name']);
	if (!in_array($mime, $allowedMime, true)) return '';

	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico'], true)) return '';

	$base     = preg_replace('/[^a-z0-9_\-]/', '-', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
	$base     = trim($base, '-') ?: 'upload';
	$destDir  = dirname(__DIR__) . '/files/';
	$destName = $base . '.' . $ext;
	$destPath = $destDir . $destName;
	$i = 1;
	while (file_exists($destPath)) {
		$destName = $base . '-' . $i . '.' . $ext;
		$destPath = $destDir . $destName;
		$i++;
	}

	if (!move_uploaded_file($file['tmp_name'], $destPath)) return '';
	return 'files/' . $destName;
}

/**
 * Validate and sanitize a files/-relative image path coming from POST.
 * Returns empty string if the path fails validation.
 */
function _settings_sanitize_image_path(string $path): string
{
	$path = ltrim(str_replace(['..', "\0"], '', $path), '/');
	if (!preg_match('/^files\/[a-zA-Z0-9_\-\/\.]+\.(jpe?g|png|gif|webp|svg|ico)$/i', $path)) return '';
	return $path;
}
