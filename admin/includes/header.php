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
	<link rel="icon" type="image/x-icon" href="../files/favicon.ico">
	<link rel="icon" type="image/png" sizes="32x32" href="../files/favicon-32x32.png">
	<link rel="apple-touch-icon" href="../files/apple-touch-icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap" media="print" onload="this.media='all'">
	<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap"></noscript>
	<link rel="stylesheet" href="css/admin-base.css">
	<link rel="stylesheet" href="css/admin-components.css">
	<link rel="stylesheet" href="css/admin-content.css">
	<!-- <link rel="stylesheet" href="css/admin-filemanager.css"> -->
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
		<main class="content">
			<h1 class="main-heading"><?php echo admin_get_page_title(); ?></h1>
			<a href="<?php echo admin_site_url(); ?>" target="_blank" class="view-website-btn"><span class="icon">🌐</span> <?php _e('view_website'); ?></a>
			<?php if (isset($message)): ?>
				<div class="message success"><?php echo $message; ?></div>
			<?php endif; ?>
			<?php if (isset($notice)): ?>
				<div class="message warning"><?php echo htmlspecialchars($notice); ?></div>
			<?php endif; ?>
			<?php if (isset($error)): ?>
				<div class="message error"><?php echo $error; ?></div>
			<?php endif; ?>