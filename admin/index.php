<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'includes/admin-functions.php';

// Check authentication
if (!admin_is_logged_in()) {
	header('Location: auth.php');
	exit;
}

// ── CSRF validation helper ────────────────────────────────────────────────────
// Validates the CSRF token for all state-changing POST requests and for
// destructive GET actions (delete, purge). Called before any data mutation.
function admin_verify_csrf(): bool {
	$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
	return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Abort with 403 on CSRF failure for state-changing requests.
// Always rejects when called — the token must be present and valid.
function admin_csrf_check(): void {
	if (!admin_verify_csrf()) {
		http_response_code(403);
		$_SESSION['error'] = 'Security token invalid or expired. Please try again.';
		header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
		exit;
	}
}

// AJAX request handler for menu builder content items
if (isset($_GET['action']) && $_GET['action'] === 'get_content_items' &&
	isset($_GET['type']) && !empty($_GET['type'])) {

	if (!admin_is_logged_in()) {
		header('Content-Type: application/json');
		echo json_encode(['error' => 'Not authorized']);
		exit;
	}

	$data = loadData();
	$contentType = $_GET['type'];

	header('Content-Type: application/json');

	if ($contentType === 'tag') {
		$tags = [];
		if (!empty($data['tags'])) {
			foreach ($data['tags'] as $slug => $tag) {
				$tags[$slug] = $tag['name'];
			}
		}
		foreach (['article', 'project'] as $type) {
			if (!empty($data[$type])) {
				foreach ($data[$type] as $item) {
					if (!empty($item['tags']) && is_array($item['tags'])) {
						foreach ($item['tags'] as $tagName) {
							$slug = sanitizeSlug($tagName);
							if (!isset($tags[$slug])) $tags[$slug] = $tagName;
						}
					}
				}
			}
		}
		$items = [];
		foreach ($tags as $slug => $name) {
			$items[] = ['title' => $name, 'slug' => $slug];
		}
		echo json_encode($items);
	} elseif (isset($data[$contentType])) {
		$items = [];
		foreach ($data[$contentType] as $item) {
			$slug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
			$items[] = [
				'title' => $item['title'],
				'slug' => $slug,
				'has_custom_slug' => !empty($item['custom_slug'])
			];
		}
		echo json_encode($items);
	} else {
		echo json_encode([]);
	}
	exit;
}

// Handle batch deletion — CSRF check required
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && $_POST['batch_action'] === 'delete') {
	admin_csrf_check();
	include_once 'content.php';
	exit;
}

// Handle batch trash actions (restore / permanent delete) — CSRF check required
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trash_action']) && in_array($_POST['trash_action'], ['batch_restore', 'batch_purge'], true)) {
	admin_csrf_check();
	include_once 'content.php';
	exit;
}

// Destructive GET actions that carry a CSRF token (delete, purge, category/tag delete)
$_currentAction = $_GET['action'] ?? '';
$_currentDraftAction = $_GET['draft_action'] ?? '';
$_currentTrashAction = $_GET['trash_action'] ?? '';
$_currentCategoryAction = $_GET['category_action'] ?? '';
$_currentTagAction = $_GET['tag_action'] ?? '';

$_isDestructiveGet = (
	in_array($_currentDraftAction, ['delete', 'batch_delete', 'restore'], true) ||
	in_array($_currentTrashAction, ['restore', 'purge', 'purge_all'], true) ||
	($_currentCategoryAction === 'delete') ||
	($_currentTagAction === 'delete') ||
	($_currentAction === 'delete') ||
	($_currentAction === 'revision_restore') ||
	($_currentAction === 'delete_revision') ||
	($_currentAction === 'duplicate') ||
	($_currentAction === 'unpublish')
);

if ($_isDestructiveGet) {
	admin_csrf_check();
}

// AJAX: alt-text save — must run before header.php outputs HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_alt_save']) && ($_GET['action'] ?? '') === 'tools') {
	header('Content-Type: application/json');
	$_alt_data      = loadData();
	$_allowed_types  = ['article', 'page', 'project'];
	$_allowed_fields = ['alt_text', 'caption'];
	$_post_type   = $_POST['post_type']     ?? '';
	$_post_index  = (int)($_POST['post_index']    ?? -1);
	$_gallery_idx = (int)($_POST['gallery_index'] ?? -1);
	$_image_idx   = (int)($_POST['image_index']   ?? -1);
	$_field       = $_POST['field']         ?? '';
	$_value       = trim($_POST['value']    ?? '');
	if (!in_array($_post_type, $_allowed_types, true) || !in_array($_field, $_allowed_fields, true)
		|| $_post_index < 0 || $_gallery_idx < 0 || $_image_idx < 0) {
		echo json_encode(['ok' => false, 'error' => 'invalid_params']); exit;
	}
	if (!isset($_alt_data[$_post_type][$_post_index]['galleries'][$_gallery_idx]['images'][$_image_idx])) {
		echo json_encode(['ok' => false, 'error' => 'not_found']); exit;
	}
	if (!admin_can_edit_item($_alt_data[$_post_type][$_post_index])) {
		echo json_encode(['ok' => false, 'error' => 'not_authorized']); exit;
	}
	$_value = mb_substr(strip_tags($_value), 0, 500);
	$_alt_data[$_post_type][$_post_index]['galleries'][$_gallery_idx]['images'][$_image_idx][$_field] = $_value;
	$_result = saveData($_alt_data);
	echo json_encode(['ok' => $_result !== false, 'value' => $_value]);
	exit;
}

// AJAX: quick-add a custom field from the content editor sidebar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf_quick_add'])) {
	header('Content-Type: application/json');
	$_cfToken = $_POST['csrf_token'] ?? '';
	if (empty($_cfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_cfToken)) {
		echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']); exit;
	}
	if (!admin_is_admin()) {
		echo json_encode(['ok' => false, 'error' => 'not_authorized']); exit;
	}
	$_cfField = admin_add_custom_field(
		$_POST['cf_type']  ?? '',
		$_POST['cf_label'] ?? '',
		$_POST['cf_ftype'] ?? 'text'
	);
	echo json_encode($_cfField ? ['ok' => true, 'field' => $_cfField] : ['ok' => false, 'error' => 'invalid_input']);
	exit;
}

// Load data — index-only for list views, full items only when needed.
// On a site with hundreds of articles, loading full items for the list
// view costs 500+ file reads for nothing: the list only needs index fields.
$action = $_GET['action'] ?? '';
$type   = $_GET['type']   ?? '';

if ($type !== '' && in_array($type, ['article', 'page', 'project'], true) && $action === '') {
	// Content list: load index only for the requested type
	$contentType = $type;
	$data = [
		'article'    => [],
		'page'       => [],
		'project'    => [],
		'categories' => sl_load_categories(),
		'tags'       => sl_load_tags(),
	];
	$data[$contentType] = sl_load_index($contentType);
} else {
	$data = admin_load_data();
}

// Buffer page content

// Plugin page router — renders a plugin's admin page inside the standard
// admin layout (sidebar, topbar, footer). A plugin exposes
// "{slug}_render_admin_page(string $view): array" (see plugin-api.php and
// the Booking plugin's admin/admin-page.php for the reference contract).
// Handled separately from the ob_start() block below because $pageTitle
// and $extraHead come from the plugin's own return value, not from
// admin_get_page_title() / a static <head> block.
if ($action === 'plugin_page') {
	require_once dirname(__DIR__) . '/core/plugin-api.php';

	$_pluginSlug = preg_replace('/[^a-z0-9_-]/', '', $_GET['slug'] ?? '');
	$_pluginView = preg_replace('/[^a-z0-9_-]/', '', $_GET['view'] ?? '');

	if ($_pluginSlug === '' || !pl_is_active($_pluginSlug)) {
		http_response_code(404);
		$pageTitle   = __t('extensions_title', 'Extensions');
		$pageContent = '<div class="site-settings-section"><p>' . htmlspecialchars(__t('extensions_no_plugins', 'Plugin not found or not active.')) . '</p></div>';
		require_once 'includes/layout.php';
		exit;
	}

	// Load every active plugin, in registry order — not just the one being
	// viewed. Loading only $_pluginSlug here would make its admin_menu hook
	// register before pl_get_admin_menu_items() (called later by the
	// sidebar) loads the others, so whichever plugin page was open would
	// always jump to the top of the sidebar's plugin list. Loading them all
	// up front, in the same order the sidebar itself uses, keeps that list
	// stable regardless of which plugin page is currently active.
	pl_load_active_plugins();

	$_pluginRenderFn = preg_replace('/[^a-z0-9_]/', '_', $_pluginSlug) . '_render_admin_page';

	if (!function_exists($_pluginRenderFn)) {
		http_response_code(500);
		$pageTitle   = __t('extensions_title', 'Extensions');
		$pageContent = '<div class="site-settings-section"><p>Plugin "' . htmlspecialchars($_pluginSlug) . '" does not expose an admin page renderer.</p></div>';
		require_once 'includes/layout.php';
		exit;
	}

	$_pluginResult = $_pluginRenderFn($_pluginView);

	$pageTitle   = $_pluginResult['title']      ?? $_pluginSlug;
	$pageContent = $_pluginResult['html']       ?? '';
	$extraHead   = $_pluginResult['extra_head'] ?? '';

	require_once 'includes/layout.php';
	exit;
}

// Role-gated actions: role required, or null to deny outright (reserved/removed).
// Actions not listed here (add/edit/delete/drafts/trash/revisions/account/users/
// tools/type=article|page|project) are open to all logged-in roles — either
// ownership-scoped in content.php (Phase 7) or self-gated in their own template.
$_adminOnlyActions        = ['settings', 'translations', 'system_info', 'activity_log', 'backup', 'update', 'plugins', 'manage_themes'];
$_editorAndAboveActions   = ['menu_builder', 'manage_categories', 'manage_tags'];

if (in_array($action, $_adminOnlyActions, true) && !admin_is_admin()) {
	http_response_code(403);
	ob_start();
	echo '<div class="site-settings-section"><p>' . hsc(__t('access_denied', 'Access denied.')) . '</p></div>';
	$pageContent = ob_get_clean();
	$pageTitle   = __t('access_denied', 'Access denied.');
	require_once 'includes/layout.php';
	exit;
}
if (in_array($action, $_editorAndAboveActions, true) && !admin_can_manage_all_content()) {
	http_response_code(403);
	ob_start();
	echo '<div class="site-settings-section"><p>' . hsc(__t('access_denied', 'Access denied.')) . '</p></div>';
	$pageContent = ob_get_clean();
	$pageTitle   = __t('access_denied', 'Access denied.');
	require_once 'includes/layout.php';
	exit;
}

ob_start();
if ($action === 'add' || $action === 'edit' || $action === 'delete' || $action === 'unpublish' || $action === 'drafts' || $action === 'trash' || $action === 'revision_diff' || $action === 'revision_restore' || $action === 'delete_revision' || $action === 'pending_diff' || $action === 'manage_categories' || $action === 'manage_tags' || $action === 'manage_themes') {
	include_once 'content.php';
} elseif ($action === 'settings' || $action === 'menu_builder') {
	include_once 'settings.php';
} elseif ($action === 'account') {
	include 'templates/account.php';
} elseif ($action === 'users') {
	include 'templates/users.php';
} elseif ($action === 'translations') {
	include 'templates/translations.php';
} elseif ($action === 'system_info') {
	include 'templates/system-info.php';
} elseif ($action === 'activity_log') {
	include 'templates/activity-log.php';
} elseif ($action === 'tools') {
	include 'templates/tools-view.php';
} elseif ($action === 'backup') {
	include 'templates/backup.php';
} elseif ($action === 'update') {
	include 'templates/update.php';
} elseif ($action === 'plugins') {
	include 'templates/plugins-manager.php';
} elseif ($type === 'article' || $type === 'page' || $type === 'project') {
	include_once 'content.php';
} else {
	include_once 'dashboard.php';
}
$pageContent = ob_get_clean();

require_once 'includes/layout.php';
