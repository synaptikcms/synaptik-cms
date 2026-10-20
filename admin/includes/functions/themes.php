<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_get_themes() {
	$themesDir = dirname(__DIR__, 3) . '/theme/';
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
			$contentType = sl_content_type_exists($type) ? $type : 'article';
			return sprintf(__t('add_new_type'), sl_type_label($contentType));
		}
		if ($action === 'edit') {
			$label = sl_content_type_exists($type)
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
		if ($action === 'backup')            return __t('backup_restore');
		if ($action === 'menu_builder')      return __t('menu_configuration');
		if ($action === 'account')           return __t('account');
		if ($action === 'users')             return __t('users_title');
		if ($action === 'plugins')           return __t('extensions_title', 'Extensions');
		if (empty($action) && sl_content_type_exists($type)) {
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

function menu_sync_load_config(): ?array {
	$file = CMS_ROOT . '/config.json';
	if (!is_file($file)) return null;

	$settings = json_decode((string) file_get_contents($file), true);
	if (!is_array($settings) || empty($settings['main_menu']) || !is_array($settings['main_menu'])) return null;

	return $settings;
}

function menu_sync_content_url(string $contentType, string $contentSlug, string $categoryPath): string {
	if ($categoryPath === '') return $contentType . '/' . $contentSlug . '/';
	if ($contentType === 'article' || $contentType === 'page') return $categoryPath . '/' . $contentSlug . '/';
	return $contentType . '/' . $categoryPath . '/' . $contentSlug . '/';
}

function menu_sync_taxonomy_items(array &$menu, string $kind, string $oldSlug, ?string $newSlug): bool {
	$key     = $kind . '_slug';
	$changed = false;

	foreach ($menu as &$menuItem) {
		if (($menuItem[$key] ?? '') !== $oldSlug) continue;

		if ($newSlug === null) {
			$menuItem['url']          = '#' . $kind . '-deleted';
			$menuItem['content_slug'] = '';
			$changed = true;
			continue;
		}

		$newUrl = admin_front_url_slug($kind) . '/' . $newSlug . '/';
		if (($menuItem['url'] ?? '') !== $newUrl || $oldSlug !== $newSlug) {
			$menuItem['url'] = $newUrl;
			$menuItem[$key]  = $newSlug;
			$changed = true;
		}
	}
	unset($menuItem);

	return $changed;
}

function menu_sync_content_items(array &$menu, array $data): bool {
	$changed = false;

	foreach ($menu as &$menuItem) {
		$contentType = $menuItem['content_type'] ?? '';
		$contentSlug = $menuItem['content_slug'] ?? '';
		if ($contentType === '' || $contentType === 'list' || $contentSlug === '' || empty($data[$contentType])) continue;

		foreach ($data[$contentType] as $contentItem) {
			$itemSlug = !empty($contentItem['custom_slug']) ? $contentItem['custom_slug'] : ($contentItem['slug'] ?? '');
			if ($itemSlug !== $contentSlug) continue;

			$categorySlug = !empty($contentItem['category']) ? sanitizeSlug($contentItem['category']) : '';
			$categoryPath = $categorySlug !== '' ? getCategoryPath($categorySlug, $data) : '';

			if ($categoryPath === '' && empty($menuItem['content_category'])) break;

			$newUrl = menu_sync_content_url($contentType, $contentSlug, $categoryPath);
			if (($menuItem['url'] ?? '') !== $newUrl) {
				$menuItem['url'] = $newUrl;
				if (array_key_exists('content_category', $menuItem)) {
					$menuItem['content_category'] = $contentItem['category'] ?? '';
				}
				$changed = true;
			}
			break;
		}
	}
	unset($menuItem);

	return $changed;
}

function syncMenuUrlsForCategory($data, $oldCategorySlug, $newCategorySlug) {
	$settings = menu_sync_load_config();
	if ($settings === null) return;

	$changed = menu_sync_taxonomy_items($settings['main_menu'], 'category', (string) $oldCategorySlug, $newCategorySlug === null ? null : (string) $newCategorySlug);
	if (menu_sync_content_items($settings['main_menu'], $data)) $changed = true;

	if ($changed) sl_admin_save_config($settings);
}

function syncMenuUrlsForTag($oldTagSlug, $newTagSlug) {
	$settings = menu_sync_load_config();
	if ($settings === null) return;

	if (menu_sync_taxonomy_items($settings['main_menu'], 'tag', (string) $oldTagSlug, $newTagSlug === null ? null : (string) $newTagSlug)) {
		sl_admin_save_config($settings);
	}
}

function getPageTemplates(): array {
	$templates = ['' => __t('page_template_default', 'Default')];

	$settings  = admin_load_config();
	$theme     = $settings['active_theme'] ?? 'default';
	$dir       = dirname(__DIR__, 3) . '/theme/' . basename($theme) . '/page-templates/';

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

