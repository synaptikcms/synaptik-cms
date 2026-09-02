<?php

if (!defined('INCLUDED')) {
	define('INCLUDED', true);
}

if (!isset($data)) {
	$data = admin_load_data();
}

if (!isset($contentCounts)) {
	$contentCounts = [
		'article' => count($data['article'] ?? []),
		'page'    => count($data['page']    ?? []),
		'project' => count($data['project'] ?? []),
	];
}

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

$_currentAction = $_GET['action'] ?? '';
$_currentType   = $_GET['type']   ?? '';
$_isEditor      = $isEditor ?? in_array($_currentAction, ['add', 'edit']);
$_layoutTitle = $pageTitle
	?? (function_exists('admin_get_page_title') ? admin_get_page_title() : 'Admin');

$_editItemStatus  = null;
if ($_isEditor && $_currentAction === 'edit'
	&& in_array($_currentType, ['article', 'page', 'project'], true)) {
	$_editIdx = isset($_GET['index']) ? (int)$_GET['index'] : -1;
	if ($_editIdx >= 0 && !empty($data[$_currentType][$_editIdx]['slug'])) {
		$_editItemStatus = $data[$_currentType][$_editIdx]['status'] ?? 'published';
	}
}

$_currentScript  = basename($_SERVER['PHP_SELF']);
$_needsPanel     = $_isEditor
	|| in_array($_currentAction, ['settings', 'menu_builder', 'manage_categories',
		'manage_tags', 'manage_themes'], true)
	|| in_array($_currentScript, ['dashboard.php'], true)
	|| (isset($_GET['type']) && in_array($_currentType, ['article', 'page', 'project'], true))
	|| empty($_currentAction); // dashboard
$_needsMenuJS    = in_array($_currentAction, ['settings', 'menu_builder'], true);
$_needsEditorJS  = $_isEditor;

$_sb_versionFile = dirname(dirname(__DIR__)) . '/version.json';
$_sb_versionData = file_exists($_sb_versionFile) ? json_decode(file_get_contents($_sb_versionFile), true) : null;
$_sb_version     = (is_array($_sb_versionData) && !empty($_sb_versionData['version'])) ? $_sb_versionData['version'] : '';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(lang_current()); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($_layoutTitle); ?> | Synaptik CMS Admin</title>
	<link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
	<script src="assets/js/theme-boot.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/theme-boot.js'); ?>"></script>
	<link rel="preconnect" href="https://fonts.bunny.net">
	<link id="gfonts-link" rel="stylesheet" href="https://fonts.bunny.net/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" media="print">
	<script src="assets/js/gfonts-async.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/gfonts-async.js'); ?>"></script>
	<noscript><link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"></noscript>
	<link rel="stylesheet" href="assets/css/admin-base.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-base.css'); ?>">
	<link rel="stylesheet" href="assets/css/admin-components.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-components.css'); ?>">
	<link rel="stylesheet" href="assets/css/admin-content.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-content.css'); ?>">
	<?php if ($_isEditor): ?>
	<link rel="stylesheet" href="assets/css/editor-layout.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/editor-layout.css'); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="assets/css/admin-sidebar.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin-sidebar.css'); ?>">
	<style>.sidebar { background: var(--sidebar-bg); }</style>
	<script type="application/json" id="cms-lang-json"><?php echo lang_js_bridge(); ?></script>
	<script src="assets/js/admin-boot.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin-boot.js'); ?>"></script>
	<?php echo $extraHead ?? ''; ?>
</head>
<body>
	<script src="assets/js/sidebar-state-boot.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/sidebar-state-boot.js'); ?>"></script>
	<div class="admin-container">
		<?php include __DIR__ . '/sidebar.php'; ?>
		<div class="admin-main">
			<div class="admin-topbar">
			<?php if ($_isEditor): ?>
				<div class="admin-topbar-default admin-topbar-editor">
					<h1 class="admin-topbar-title"><?php echo htmlspecialchars($_layoutTitle); ?></h1>
					<div class="editor-topbar-format" id="topbar-format-switcher">
						<button type="button" class="editor-format-tab active" data-format="html">WYSIWYG</button>
						<button type="button" class="editor-format-tab" data-format="markdown">Markdown</button>
					</div>
					<div class="editor-topbar-actions">
						<div id="autosave-status" class="autosave-status"></div>
						<button type="button" id="save-draft-btn" class="btn btn-outline btn-sm" title="<?php _e('save_draft'); ?>"><?php echo admin_icon('save', 'style="margin-right:4px;vertical-align:-2px"', 13); ?><span class="topbar-hide-mobile"><?php _e('save_draft'); ?></span></button>
						<button type="button" id="preview-btn" class="btn btn-outline btn-sm" title="<?php _e('preview_open_tab'); ?>"><?php echo admin_icon('eye', 'style="margin-right:4px;vertical-align:-2px"', 13); ?><span class="topbar-hide-mobile"><?php _e('preview_badge'); ?></span></button>
						<button type="submit" form="content-form" id="publish-btn" class="btn btn-primary btn-sm btn-publish">
							<span id="publish-btn-icon"><?php echo admin_icon('check', 'style="vertical-align:-2px"', 13); ?></span>
							<span id="publish-btn-label"><?php
								if ($_currentAction === 'edit' && $_editItemStatus === 'published') echo __t('update');
								elseif ($_currentAction === 'edit' && $_editItemStatus === 'scheduled') echo __t('schedule');
								elseif ($_currentAction === 'edit' && in_array($_editItemStatus, ['draft', 'unpublished'], true)) echo __t('save');
								else echo __t('publish');
							?></span>
						</button>
					</div>
				</div>
			<?php else: ?>
				<div class="admin-topbar-default">
					<h1 class="admin-topbar-title"><?php echo htmlspecialchars($_layoutTitle); ?></h1>
					<div class="topbar-new-dropdown">
						<button type="button" class="btn btn-outline btn-sm topbar-new-toggle" id="topbar-new-btn-global" aria-haspopup="true" aria-expanded="false">
							<?php echo admin_icon('circle-plus', '', 14); ?>
							<span class="topbar-hide-mobile"><?php _e('add_new'); ?></span>
						</button>
						<div class="topbar-new-menu" id="topbar-new-menu-global" role="menu">
							<a href="index.php?action=add&type=article" class="topbar-new-item" role="menuitem">
								<?php echo admin_icon('article', '', 14); ?>
								<?php printf(hsc(__t('add_new_type')), hsc(sl_type_label('article'))); ?>
							</a>
							<a href="index.php?action=add&type=page" class="topbar-new-item" role="menuitem">
								<?php echo admin_icon('page', '', 14); ?>
								<?php printf(hsc(__t('add_new_type')), hsc(sl_type_label('page'))); ?>
							</a>
							<a href="index.php?action=add&type=project" class="topbar-new-item" role="menuitem">
								<?php echo admin_icon('project', '', 14); ?>
								<?php printf(hsc(__t('add_new_type')), hsc(sl_type_label('project'))); ?>
							</a>
						</div>
					</div>
					<a target="_blank" href="<?php echo admin_site_url(); ?>" class="btn btn-outline btn-sm">
						<?php echo admin_icon('globe', 'style="margin-right:5px;vertical-align:-2px"', 14); ?><span class="topbar-hide-mobile"><?php _e('view_website'); ?></span>
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
				<span>Powered by <a target="_blank" href="https://synaptikcms.com/">Synaptik CMS</a> — v<?php echo htmlspecialchars($_sb_version ?? ''); ?></span>
			</footer>
		</div><!-- /.admin-main -->
	</div><!-- /.admin-container -->

	<script type="application/json" id="cms-csrf-json"><?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?></script>
	<script src="assets/js/admin-boot.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin-boot.js'); ?>"></script>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js" integrity="sha384-vxc713BCZYoMxC6DlBK6K4M+gLAS8+63q7TtgB2+KZVn8GNafLKZCJ7Wk2S6ZEl1" crossorigin="anonymous"></script>
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
	<?php $_autosave_settings = admin_load_config(); ?>
	<script type="application/json" id="cms-autosave-json"><?php echo json_encode([
		'enabled'          => !empty($_autosave_settings['autosave_enabled']),
		'interval_seconds' => (int)($_autosave_settings['autosave_interval'] ?? 5) * 60,
	]); ?></script>
	<script src="assets/js/admin-boot.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/admin-boot.js'); ?>"></script>
	<script src="assets/js/editor-icons.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor-icons.js'); ?>"></script>
	<script src="assets/js/editor-common.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor-common.js'); ?>"></script>
	<script src="assets/js/editor.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor.js'); ?>"></script>
	<script src="assets/js/editor-markdown.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/editor-markdown.js'); ?>"></script>
	<script src="assets/js/seo-preview.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/seo-preview.js'); ?>"></script>
	<?php endif; ?>
	<?php echo $extraFooterScripts ?? ''; ?>
</body>
</html>