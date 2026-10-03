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

function syncMenuUrlsForCategory($data, $oldCategorySlug, $newCategoryName) {
	$settingsFile = dirname(__DIR__, 3) . '/config.json';
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

			$currentCategorySlug = !empty($contentItem['category'])
				? sanitizeSlug($contentItem['category'])
				: '';

			if ($currentCategorySlug !== $newCategorySlug) break;

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

function syncMenuUrlsForTag($oldTagSlug, $newTagName) {
	$settingsFile = dirname(__DIR__, 3) . '/config.json';
	if (!file_exists($settingsFile)) return;

	$settings = json_decode(file_get_contents($settingsFile), true);
	if (!is_array($settings) || empty($settings['main_menu'])) return;

	$changed = false;

	foreach ($settings['main_menu'] as &$menuItem) {
		if (empty($menuItem['tag_slug'])) continue;
		if ($menuItem['tag_slug'] !== $oldTagSlug) continue;

		if ($newTagName === null) {			$menuItem['url'] = '#tag-deleted';
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

