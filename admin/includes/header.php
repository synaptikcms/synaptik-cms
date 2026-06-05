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
	<link rel="icon" href="img/favicon.ico" type="image/x-icon">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap" media="print" onload="this.media='all'">
	<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap"></noscript>
	<link rel="stylesheet" href="css/admin-base.css">
	<link rel="stylesheet" href="css/admin-components.css">
	<link rel="stylesheet" href="css/admin-content.css">
	<link rel="stylesheet" href="css/editor-layout.css">
	<link rel="stylesheet" href="css/admin-sidebar.css">
	<style>
	  .sidebar { background: #1e2a3a; }
	</style>
	<script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
</head>
<body style="background-color: #1e2a3a;">
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
		<main class="content<?php echo in_array($currentAction, ['add', 'edit']) ? ' editor-page' : ''; ?>">
			<?php if (in_array($currentAction, ['add', 'edit'])): ?>
			<div class="editor-topbar">
				<h1 class="main-heading" style="margin:0;border:none;"><?php echo admin_get_page_title(); ?></h1>
				<!-- Format switcher — active state is corrected by JS in each template -->
				<div class="editor-topbar-format" id="topbar-format-switcher">
					<button type="button" class="editor-format-tab active" data-format="html"
						onclick="window.EditorCommon && window.EditorCommon.switchFormat('html')">WYSIWYG</button>
					<button type="button" class="editor-format-tab" data-format="markdown"
						onclick="window.EditorCommon && window.EditorCommon.switchFormat('markdown')">Markdown</button>
				</div>
				<div class="editor-topbar-actions">
					<div id="autosave-status" class="autosave-status"></div>
					<?php
					// Resolve the front-end URL for the item being edited so we can show a "View" link.
					// $data is already loaded above via admin_load_data(), no extra I/O needed.
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
					<?php if ($_topbar_view_url): ?>
					<a href="<?php echo htmlspecialchars($_topbar_view_url); ?>" target="_blank" class="button view-item-btn" >
						<span class="icon">🌐</span> <?php printf(__t('view_type'), __t('type_' . $currentType)); ?>
					</a>
					<?php endif; ?>
					<button type="button" id="save-draft-btn" class="button btn-draft"><?php _e('save_draft'); ?></button>
					<button type="button" id="preview-btn" class="button btn-draft" title="<?php _e('preview_open_tab'); ?>">👁️ <?php _e('preview_badge'); ?></button>
					<button type="submit" form="content-form" id="publish-btn" class="button btn-publish">
						<span id="publish-btn-icon">✓</span>
						<span id="publish-btn-label"><?php echo $currentAction === 'edit' ? __t('update') : __t('publish'); ?></span>
					</button>
				</div>
			</div>
			<?php else: ?>
			<h1 class="main-heading"><?php echo admin_get_page_title(); ?></h1>
			<a href="<?php echo admin_site_url(); ?>" target="_blank" class="view-website-btn"><span class="icon">🌐</span> <?php _e('view_website'); ?></a>
			<?php endif; ?>
			<?php if (isset($message)): ?>
				<div class="message success"><?php echo $message; ?></div>
			<?php endif; ?>
			<?php if (isset($notice)): ?>
				<div class="message warning"><?php echo htmlspecialchars($notice); ?></div>
			<?php endif; ?>
			<?php if (isset($error)): ?>
				<div class="message error"><?php echo $error; ?></div>
			<?php endif; ?>