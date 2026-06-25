<?php
/**
 * Shared admin layout.
 *
 * Every admin page — routed (via index.php) and standalone — uses this single
 * file to emit the full HTML document. No other header/footer includes exist.
 *
 * Expected variables (all optional except $pageContent):
 *   string $pageContent          Buffered HTML output of the page body.
 *   string $pageTitle            Page title. Falls back to admin_get_page_title()
 *                                when called from the routed context.
 *   string $extraHead            Additional <link>/<style>/<script> tags for <head>.
 *   string $extraFooterScripts   Additional <script> tags injected before </body>,
 *                                after the shared scripts but before the inline block.
 *   bool   $isEditor             True on add/edit pages — activates editor topbar.
 *
 * Variables available to all pages via $data / $contentCounts (loaded here if
 * not already set by the calling file):
 *   array  $data
 *   array  $contentCounts
 */

// Allow templates that guard against direct access via INCLUDED constant.
if (!defined('INCLUDED')) {
	define('INCLUDED', true);
}

// ── Bootstrap (idempotent — standalone pages may have loaded this already) ──
if (!isset($data)) {
	$data = admin_load_data();
}

if (!isset($contentCounts)) {
	$_draftsDir  = 'drafts';
	$_draftCount = (is_dir($_draftsDir))
		? count(glob($_draftsDir . '/*.json') ?: [])
		: 0;
	$contentCounts = [
		'article' => count($data['article'] ?? []),
		'page'    => count($data['page']    ?? []),
		'project' => count($data['project'] ?? []),
		'drafts'  => $_draftCount,
	];
}

// ── Flash messages (session → local vars, consumed once) ──────────────────────
global $admin_message, $admin_message_type;

if (!isset($message)) {
	if (isset($_SESSION['message']))       { $message = $_SESSION['message']; unset($_SESSION['message']); }
	elseif (isset($admin_message))         { $message = $admin_message; }
}
if (!isset($error)) {
	if (isset($_SESSION['error']))         { $error = $_SESSION['error']; unset($_SESSION['error']); }
	elseif (isset($admin_message, $admin_message_type) && $admin_message_type === 'error') { $error = $admin_message; }
}
if (!isset($notice)) {
	if (isset($_SESSION['notice']))        { $notice = $_SESSION['notice']; unset($_SESSION['notice']); }
}

// ── Context ──────────────────────────────────────────────────────────────────
$_currentAction = $_GET['action'] ?? '';
$_currentType   = $_GET['type']   ?? '';
$_isEditor      = $isEditor ?? in_array($_currentAction, ['add', 'edit']);

// Resolve page title
$_layoutTitle = $pageTitle
	?? (function_exists('admin_get_page_title') ? admin_get_page_title() : 'Admin');

// ── Editor topbar: "View" link ────────────────────────────────────────────────
$_topbarViewUrl = null;
if ($_isEditor && $_currentAction === 'edit'
	&& in_array($_currentType, ['article', 'page', 'project'], true)) {
	$_editIdx = isset($_GET['index']) ? (int)$_GET['index'] : -1;
	if ($_editIdx >= 0 && !empty($data[$_currentType][$_editIdx]['slug'])) {
		$_ti = $data[$_currentType][$_editIdx];
		$_topbarViewUrl = admin_content_url(
			$_currentType,
			$_ti['slug']        ?? '',
			$_ti['custom_slug'] ?? '',
			$_ti['category']    ?? ''
		);
	}
}

// ── Footer JS: detect which scripts are needed ────────────────────────────────
$_currentScript  = basename($_SERVER['PHP_SELF']);
$_needsPanel     = $_isEditor
	|| in_array($_currentAction, ['settings', 'menu_builder', 'manage_categories',
		'manage_tags', 'manage_themes', 'drafts'], true)
	|| in_array($_currentScript, ['dashboard.php'], true)
	|| (isset($_GET['type']) && in_array($_currentType, ['article', 'page', 'project'], true))
	|| empty($_currentAction); // dashboard
$_needsMenuJS    = in_array($_currentAction, ['settings', 'menu_builder'], true);
$_needsEditorJS  = $_isEditor;

// ── Footer: current CMS version (read from version.json at the CMS root) ──────
$_sb_versionFile = dirname(dirname(__DIR__)) . '/version.json';
$_sb_versionData = file_exists($_sb_versionFile) ? json_decode(file_get_contents($_sb_versionFile), true) : null;
$_sb_version     = (is_array($_sb_versionData) && !empty($_sb_versionData['version'])) ? $_sb_versionData['version'] : '';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(lang_current()); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($_layoutTitle); ?> | SynaptikCMS Admin</title>
	<link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
	<script>
	(function() {
		try {
			var t = localStorage.getItem('synaptik_theme');
			if (t !== 'light' && t !== 'dark') {
				t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
			}
			document.documentElement.setAttribute('data-theme', t);
		} catch (e) {
			document.documentElement.setAttribute('data-theme', 'light');
		}
	})();
	</script>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap" media="print" onload="this.media='all'">
	<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap"></noscript>
	<link rel="stylesheet" href="assets/css/admin-base.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-base.css'); ?>">
	<link rel="stylesheet" href="assets/css/admin-components.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-components.css'); ?>">
	<link rel="stylesheet" href="assets/css/admin-content.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-content.css'); ?>">
	<link rel="stylesheet" href="assets/css/editor-layout.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/editor-layout.css'); ?>">
	<link rel="stylesheet" href="assets/css/admin-sidebar.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-sidebar.css'); ?>">
	<style>.sidebar { background: var(--sidebar-bg); }</style>
	<script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
	<?php echo $extraHead ?? ''; ?>
</head>
<body>
	<script>
	(function() {
		try {
			var saved = localStorage.getItem('synaptik_sidebar_state');
			if (saved) {
				var s = JSON.parse(saved);
				document.body.classList.add(s.isExpanded === false ? 'sidebar-collapsed' : 'sidebar-expanded');
			} else {
				document.body.classList.add('sidebar-expanded');
			}
		} catch(e) { document.body.classList.add('sidebar-expanded'); }
		try {
			var sections = localStorage.getItem('synaptik_sidebar_sections');
			if (sections) {
				var ss = JSON.parse(sections);
				document.addEventListener('DOMContentLoaded', function() {
					document.querySelectorAll('.sidebar-collapsible[data-key]').forEach(function(el) {
						var key = el.getAttribute('data-key');
						if (Object.prototype.hasOwnProperty.call(ss, key) && ss[key] === false) {
							el.removeAttribute('open');
						}
					});
				});
			}
		} catch(e) {}
	})();
	</script>
	<div class="admin-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="admin-main">
			<div class="admin-topbar">
			<?php if ($_isEditor): ?>
				<div class="admin-topbar-default">
					<h1 class="admin-topbar-title"><?php echo htmlspecialchars($_layoutTitle); ?></h1>
					<div class="editor-topbar">
						<div class="topbar-new-dropdown">
							<button type="button" class="btn btn-outline btn-sm topbar-new-toggle" id="topbar-new-btn" aria-haspopup="true" aria-expanded="false">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
								<span class="topbar-hide-mobile"><?php _e('add_new'); ?></span>
							</button>
							<div class="topbar-new-menu" id="topbar-new-menu" role="menu">
								<a href="index.php?action=add&type=article" class="topbar-new-item" role="menuitem"><?php _e('new_article'); ?></a>
								<a href="index.php?action=add&type=page" class="topbar-new-item" role="menuitem"><?php _e('new_page'); ?></a>
								<a href="index.php?action=add&type=project" class="topbar-new-item" role="menuitem"><?php _e('new_project'); ?></a>
							</div>
						</div>
						<div class="editor-topbar-format" id="topbar-format-switcher">
							<button type="button" class="editor-format-tab active" data-format="html"
								onclick="window.EditorCommon && window.EditorCommon.switchFormat('html')">WYSIWYG</button>
							<button type="button" class="editor-format-tab" data-format="markdown"
								onclick="window.EditorCommon && window.EditorCommon.switchFormat('markdown')">Markdown</button>
						</div>
						<div class="editor-topbar-actions">
							<div id="autosave-status" class="autosave-status"></div>
							<button type="button" id="save-draft-btn" class="btn btn-outline btn-sm"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg><?php _e('save_draft'); ?></button>
							<button type="button" id="preview-btn" class="btn btn-outline btn-sm" title="<?php _e('preview_open_tab'); ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php _e('preview_badge'); ?></button>
							<?php if ($_topbarViewUrl): ?>
							<a target="_blank" href="<?php echo htmlspecialchars($_topbarViewUrl); ?>" class="btn btn-outline btn-sm">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><?php printf(__t('view_type'), __t('type_' . $_currentType)); ?>
							</a>
							<?php endif; ?>
							<button type="submit" form="content-form" id="publish-btn" class="btn btn-primary btn-sm btn-publish">
								<span id="publish-btn-icon">✓</span>
								<span id="publish-btn-label"><?php echo $_currentAction === 'edit' ? __t('update') : __t('publish'); ?></span>
							</button>
						</div>
					</div>
				</div>
			<?php else: ?>
				<div class="admin-topbar-default">
					<h1 class="admin-topbar-title"><?php echo htmlspecialchars($_layoutTitle); ?></h1>
					<div class="topbar-new-dropdown">
						<button type="button" class="btn btn-outline btn-sm topbar-new-toggle" id="topbar-new-btn-global" aria-haspopup="true" aria-expanded="false">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
							<span class="topbar-hide-mobile"><?php _e('add_new'); ?></span>
						</button>
						<div class="topbar-new-menu" id="topbar-new-menu-global" role="menu">
							<a href="index.php?action=add&type=article" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
								<?php _e('new_article'); ?>
							</a>
							<a href="index.php?action=add&type=page" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
								<?php _e('new_page'); ?>
							</a>
							<a href="index.php?action=add&type=project" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 7.5V5c0-1.1.9-2 2-2h4l2 2h8a2 2 0 0 1 2 2v1"/><path d="M2 7.5h20l-1.5 9a2 2 0 0 1-2 1.5H5.5a2 2 0 0 1-2-1.5z"/></svg>
								<?php _e('new_project'); ?>
							</a>
						</div>
					</div>
					<a href="<?php echo admin_site_url(); ?>" class="btn btn-outline btn-sm">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:5px;vertical-align:-2px"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><span class="topbar-hide-mobile"><?php _e('view_website'); ?></span>
					</a>
				</div>
			<?php endif; ?>
			</div><!-- /.admin-topbar -->
			<main class="content<?php echo $_isEditor ? ' editor-page' : ''; ?>">
				<?php if (!empty($message)): ?>
					<div class="message success"><?php echo $message; ?></div>
				<?php endif; ?>
				<?php if (!empty($notice)): ?>
					<div class="message warning"><?php echo htmlspecialchars($notice); ?></div>
				<?php endif; ?>
				<?php if (!empty($error)): ?>
					<div class="message error"><?php echo $error; ?></div>
				<?php endif; ?>
				<?php echo $pageContent ?? ''; ?>
			</main>
			<footer class="admin-footer">
				<span>Powered by <a target="_blank" href="https://synaptikcms.com/">SynaptikCMS</a> — v<?php echo htmlspecialchars($_sb_version ?? ''); ?></span>
			</footer>
		</div><!-- /.admin-main -->
	</div><!-- /.admin-container -->

	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
	<script src="assets/js/common.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/common.js'); ?>"></script>
	<script src="assets/js/admin-sidebar.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin-sidebar.js'); ?>"></script>
	<?php if ($_needsPanel): ?>
	<script src="assets/js/panel.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/panel.js'); ?>"></script>
	<?php endif; ?>
	<?php if ($_needsMenuJS): ?>
	<script src="assets/js/menu-builder.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/menu-builder.js'); ?>"></script>
	<?php endif; ?>
	<?php if ($_needsEditorJS): ?>
	<script src="assets/js/gallery.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/gallery.js'); ?>"></script>
	<script>
		<?php $_autosave_settings = admin_load_settings(); ?>
		window.AUTOSAVE_ENABLED_BY_SETTINGS = <?php echo !empty($_autosave_settings['autosave_enabled']) ? 'true' : 'false'; ?>;
		window.AUTOSAVE_INTERVAL_SECONDS    = <?php echo (int)($_autosave_settings['autosave_interval'] ?? 5) * 60; ?>;
	</script>
	<script src="assets/js/editor-common.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor-common.js'); ?>"></script>
	<script src="assets/js/editor.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor.js'); ?>"></script>
	<script src="assets/js/editor-markdown.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor-markdown.js'); ?>"></script>
	<script src="assets/js/seo-preview.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/seo-preview.js'); ?>"></script>
	<?php endif; ?>
	<?php echo $extraFooterScripts ?? ''; ?>
</body>
</html>
