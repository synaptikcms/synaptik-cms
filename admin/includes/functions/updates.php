<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_check_for_update(): ?array {
	 $_vFile = dirname(__DIR__, 3) . '/version.json';
	 $_vData = file_exists($_vFile) ? json_decode(file_get_contents($_vFile), true) : null;
	 $localVersion = (is_array($_vData) && !empty($_vData['version'])) ? $_vData['version'] : '1.0';
	 $remoteUrl    = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/version.json';
	 $cacheDir     = dirname(__DIR__) . '/../cache';
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
	$cacheDir  = dirname(__DIR__) . '/../cache';
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
		'advanced'      => ['tools', __t('settings_tab_advanced')],
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
require_once dirname(__DIR__, 3) . '/core/lang-cache.php';require_once dirname(__DIR__, 3) . '/core/data-layer.php';
require_once dirname(__DIR__, 3) . '/core/plugin-api.php';
pl_load_active_plugins();
require_once dirname(__DIR__, 3) . '/core/admin-data-layer.php';

if (!function_exists('loadData'))          { function loadData() { return admin_load_data(); } }
if (!function_exists('saveData'))          { function saveData($data) { return admin_save_data($data); } }
if (!function_exists('getBaseUrl'))        { function getBaseUrl() { return admin_site_url(); } }
if (!function_exists('adminCleanUrl'))     { function adminCleanUrl($contentType, $slug, $customSlug = '', $category = '') { return admin_content_url($contentType, $slug, $customSlug, $category); } }
if (!function_exists('formatFileSize'))    { function formatFileSize($bytes) { return admin_format_file_size($bytes); } }
if (!function_exists('getAvailableThemes')){ function getAvailableThemes() { return admin_get_themes(); } }
if (!function_exists('decodeHtmlEntities')){ function decodeHtmlEntities($html) { return admin_decode_html($html); } }