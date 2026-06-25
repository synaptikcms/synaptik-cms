<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Determine active tab from URL or localStorage fallback
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

global $admin_message, $admin_message_type;

// Load messages from session if they exist (for backward compatibility)
if (isset($_SESSION['message'])) {
	$message = $_SESSION['message'];
	unset($_SESSION['message']);
} elseif (isset($admin_message)) {
	$message = $admin_message;
}

if (isset($_SESSION['error'])) {
	$error = $_SESSION['error'];
	unset($_SESSION['error']);
} elseif (isset($admin_message) && isset($admin_message_type) && $admin_message_type == 'error') {
	$error = $admin_message;
}

// Notice-level flash message (e.g. slug auto-rename warning)
$notice = null;
if (isset($_SESSION['notice'])) {
	$notice = $_SESSION['notice'];
	unset($_SESSION['notice']);
}

// Load the database
$data = admin_load_data();

// Get all drafts
$draftsDir = 'drafts';
$draftCount = 0;

if (file_exists($draftsDir)) {
	$draftFiles = glob($draftsDir . '/*.json');
	$draftCount = count($draftFiles);
}

// Content counts for sidebar badges
$contentCounts = [
	'article' => count($data['article'] ?? []),
	'page'    => count($data['page'] ?? []),
	'project' => count($data['project'] ?? []),
	'drafts'  => $draftCount
];

// Helpers pour les états actifs
$currentAction = $_GET['action'] ?? '';
$currentType   = $_GET['type'] ?? '';
$currentFile   = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(lang_current()); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo admin_get_page_title(); ?> | SynaptikCMS Admin</title>
	<link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
	<script>
	// Theme bootstrap — doit s'exécuter avant le premier paint pour éviter le flash
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
	<style>
	  /* Anti-flash avant init JS de la sidebar — couleur via token, pas hardcodée */
	  .sidebar { background: var(--sidebar-bg); }
	</style>
	<script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
</head>
<body>
	<script>
	  (function() {
		try {
		  // Sidebar expanded/collapsed
		  var saved = localStorage.getItem('synaptik_sidebar_state');
		  if (saved) {
			var state = JSON.parse(saved);
			if (state.isExpanded === false) {
			  document.body.classList.add('sidebar-collapsed');
			} else {
			  document.body.classList.add('sidebar-expanded');
			}
		  } else {
			document.body.classList.add('sidebar-expanded');
		  }
		} catch(e) {
		  document.body.classList.add('sidebar-expanded');
		}
		// Collapsible sections — appliqué ici pour éviter le flash
		try {
		  var sections = localStorage.getItem('synaptik_sidebar_sections');
		  if (sections) {
			var sectionsState = JSON.parse(sections);
			document.addEventListener('DOMContentLoaded', function() {
			  var details = document.querySelectorAll('.sidebar-collapsible[data-key]');
			  details.forEach(function(el) {
				var key = el.getAttribute('data-key');
				if (Object.prototype.hasOwnProperty.call(sectionsState, key) && sectionsState[key] === false) {
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
			<div class="admin-topbar-default">
			<?php if (in_array($currentAction, ['add', 'edit'])): ?>
				<h1 class="admin-topbar-title"><?php echo admin_get_page_title(); ?></h1>
				<div class="editor-topbar">
					<div class="editor-topbar-format" id="topbar-format-switcher">
						<button type="button" class="editor-format-tab active" data-format="html"
							onclick="window.EditorCommon && window.EditorCommon.switchFormat('html')">WYSIWYG</button>
						<button type="button" class="editor-format-tab" data-format="markdown"
							onclick="window.EditorCommon && window.EditorCommon.switchFormat('markdown')">Markdown</button>
					</div>
					<div class="editor-topbar-actions">
					<div id="autosave-status" class="autosave-status"></div>
					<div class="topbar-new-dropdown">
						<button type="button" class="btn btn-outline btn-sm topbar-new-toggle" id="topbar-new-btn" aria-haspopup="true" aria-expanded="false">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
							<span class="topbar-hide-mobile"><?php _e('add_new'); ?></span>
						</button>
						<div class="topbar-new-menu" id="topbar-new-menu" role="menu">
							<a href="index.php?action=add&type=article" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
								<?php _e('new_article'); ?>
							</a>
							<a href="index.php?action=add&type=page" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
								<?php _e('new_page'); ?>
							</a>
							<a href="index.php?action=add&type=project" class="topbar-new-item" role="menuitem">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
								<?php _e('new_project'); ?>
							</a>
						</div>
					</div>
						<?php
						$_topbar_view_url = null;
						if ($currentAction === 'edit' && in_array($currentType, ['article', 'page', 'project'], true)) {
							$_edit_idx = isset($_GET['index']) ? (int)$_GET['index'] : -1;
							if ($_edit_idx >= 0 && !empty($data[$currentType][$_edit_idx]['slug'])) {
								$_ti = $data[$currentType][$_edit_idx];
								$_topbar_view_url = admin_content_url(
									$currentType,
									$_ti['slug']        ?? '',
									$_ti['custom_slug'] ?? '',
									$_ti['category']    ?? ''
								);
								unset($_ti, $_edit_idx);
							}
						}
						?>
						<button type="button" id="save-draft-btn" class="btn btn-outline btn-sm"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg><?php _e('save_draft'); ?></button>
						<button type="button" id="preview-btn" class="btn btn-outline btn-sm" title="<?php _e('preview_open_tab'); ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><?php _e('preview_badge'); ?></button>
						<?php if ($_topbar_view_url): ?>
						<a target="_blank" href="<?php echo htmlspecialchars($_topbar_view_url); ?>" class="btn btn-outline btn-sm">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px;vertical-align:-1px"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><?php printf(__t('view_type'), __t('type_' . $currentType)); ?>
						</a>
						<?php endif; ?>
						<button type="submit" form="content-form" id="publish-btn" class="btn btn-primary btn-sm btn-publish">
							<span id="publish-btn-icon">✓</span>
							<span id="publish-btn-label"><?php echo $currentAction === 'edit' ? __t('update') : __t('publish'); ?></span>
						</button>
					</div>
				</div>
			<?php else: ?>
				<h1 class="admin-topbar-title"><?php echo admin_get_page_title(); ?></h1>
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
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
							<?php _e('new_project'); ?>
						</a>
					</div>
				</div>
				<a href="<?php echo admin_site_url(); ?>" class="btn btn-outline btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><span class="topbar-hide-mobile"><?php _e('view_website'); ?></span></a>
			<?php endif; ?>
			</div><!-- /.admin-topbar-default -->
		</div><!-- /.admin-topbar -->
		<main class="content<?php echo in_array($currentAction, ['add', 'edit']) ? ' editor-page' : ''; ?>">
			<?php if (isset($message)): ?>
				<div class="message success"><?php echo $message; ?></div>
			<?php endif; ?>
			<?php if (isset($notice)): ?>
				<div class="message warning"><?php echo htmlspecialchars($notice); ?></div>
			<?php endif; ?>
			<?php if (isset($error)): ?>
				<div class="message error"><?php echo $error; ?></div>
			<?php endif; ?>