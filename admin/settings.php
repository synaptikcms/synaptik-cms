<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Add this at the top of each major file
error_log('Executing file: ' . __FILE__);

// And right before the error is set
if (isset($_SESSION['error'])) {
  error_log('Error set in file: ' . __FILE__ . ' at line: ' . __LINE__);
}

require_once 'includes/admin-functions.php';

// Load settings
$appSettings = admin_load_settings();

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
	$result = file_put_contents('../settings.json', $jsonData);
	
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
	$appSettings['site_title'] = htmlspecialchars(trim($_POST['site_title']));
	$appSettings['site_description'] = htmlspecialchars(trim($_POST['site_description']));
	$appSettings['default_meta_title'] = htmlspecialchars(trim($_POST['default_meta_title']));
	$appSettings['default_meta_description'] = htmlspecialchars(trim($_POST['default_meta_description']));
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
	// $appSettings['footer_text'] = htmlspecialchars(trim($_POST['settings']['footer_text'] ?? 'Developed with ♥ by Dorian • &copy; {year}'));
	$appSettings['footer_text'] = trim($_POST['settings']['footer_text'] ?? 'Developed with ♥ by Dorian • &copy; {year}');
	$appSettings['footer_show_login'] = isset($_POST['settings']['footer_show_login']);
	$appSettings['footer_show_social'] = isset($_POST['settings']['footer_show_social']);
	$appSettings['autosave_enabled'] = isset($_POST['autosave_enabled']);
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
	
	// Get the selected theme and validate it's in our auto-detected list
	$selectedTheme = $_POST['active_theme'];
	if (in_array($selectedTheme, $autoDetectedThemes)) {
		$appSettings['active_theme'] = $selectedTheme;
	} else {
		$appSettings['active_theme'] = 'default';
	}
	
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

	// WebP conversion settings
	$appSettings['convert_to_webp'] = isset($_POST['convert_to_webp']);
	
	// Save settings
	$saveResult = file_put_contents('../settings.json', json_encode($appSettings, JSON_PRETTY_PRINT));
	if ($saveResult === false) {
		error_log('Failed to save settings to file: ../settings.json');
		$_SESSION['error'] = __t('settings_save_failed');
	} else {
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
 * Load settings from file specifically for the admin panel
 * 
 * @return array Settings array
 */
function loadedSettings() {
	$defaultSettings = [
		'articles_per_page' => 3,
		'projects_per_page' => 3,
		'show_articles_on_homepage' => true,
		'show_projects_on_homepage' => true,
		'main_menu' => [],
		'use_custom_menu' => false,
		'default_menu_style' => 'grouped',
		'default_menu_order' => 'alphabetical',
		'site_title' => 'Synaptik CMS',
		'site_description' => 'A super lightweight, blazing fast, crazy simple, and very flexible file-based content management system for artists, creatives and personal websites.',
		// Basic SEO settings
		'default_meta_title' => '{page_title} | {site_title}',
		'default_meta_description' => '{site_description}',
		'default_meta_keywords' => '',
		'enable_seo' => true,
		// Advanced SEO settings
		'generate_schema_markup' => true,
		'default_schema_type' => 'WebSite',
		'auto_canonical_urls' => true,
		'social_image_width' => 1200,
		'social_image_height' => 630,
		// Standard settings
		'show_site_title_in_header' => true,
		'show_search_icon' => true,
		'homepage_type' => 'default',
		'homepage_page_id' => '',
		// Image settings
		'image_optimization_enabled' => true,
		'max_width' => 1920,
		'max_height' => 1080,
		'image_quality' => 85,
		'create_thumbnails' => true,
		'thumb_width' => 300,
		'thumb_height' => 300,
		'convert_to_webp' => false,
		// Theme & footer settings
		'active_theme' => 'default',
		'active_language' => 'en',
		'footer_text' => 'Developed with ♥ by Dorian • &copy; {year}',
		'footer_show_login' => true,
		'footer_show_social' => false,
		'footer_social_links' => [
			['platform' => 'instagram', 'url' => 'https://instagram.com/'],
			['platform' => 'twitter', 'url' => 'https://twitter.com/']
		],
		'autosave_enabled' => false,
		// Social media sharing settings
		'og_site_name' => '',
		'twitter_card_type' => 'summary_large_image',
		'twitter_site' => '',
		'show_breadcrumbs' => true,
	];

	$settingsFile = '../settings.json';
	if (file_exists($settingsFile)) {
		$loadedSettings = json_decode(file_get_contents($settingsFile), true);
		if (is_array($loadedSettings)) {
			return array_merge($defaultSettings, $loadedSettings);
		}
	}
	
	return $defaultSettings;
}