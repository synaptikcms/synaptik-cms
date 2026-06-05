<!-- <footer>
	<p>Developed with ♥ by Dorian • Powered by SynaptikCMS • © 2026</p>
</footer> -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
	<script src="js/common.js"></script>
	<script src="js/admin-sidebar.js"></script>
	<?php
	$currentScript = basename($_SERVER['PHP_SELF']);
	$action = $_GET['action'] ?? '';
	$type = $_GET['type'] ?? '';
	$isContentEditor = in_array($currentScript, ['content-add.php', 'content-edit.php']) || 
					in_array($action, ['add', 'edit']);
	$isContentList = ($currentScript === 'content-list.php') || 
					(isset($_GET['type']) && in_array($type, ['article', 'page', 'project']));
	$isDrafts = ($currentScript === 'drafts.php');
	$isSettings    = ($action === 'settings');
$isMenuBuilder = ($action === 'menu_builder');
	$isDashboard = ($currentScript === 'dashboard.php' || ($currentScript === 'index.php' && empty($action)));
	$isOrganization = in_array($action, ['manage_categories', 'manage_tags']);
	$isThemeManager = ($action === 'manage_themes');
	$needsAdminJS = $isContentList || $isDrafts || $isDashboard || $isSettings || $isContentEditor || $isOrganization || $isThemeManager || $isMenuBuilder;
	?>
<?php if ($needsAdminJS): ?>
	<script src="js/panel.js"></script>
<?php endif; ?>
<?php if ($isSettings || $isMenuBuilder): ?>
	<script src="js/menu-builder.js"></script>
<?php endif; ?>
<?php if ($isContentEditor): ?>
	<script src="js/gallery.js"></script>
	<script>
		<?php
		$_autosave_settings = admin_load_settings();
		?>
		window.AUTOSAVE_ENABLED_BY_SETTINGS = <?php echo !empty($_autosave_settings['autosave_enabled']) ? 'true' : 'false'; ?>;
		window.AUTOSAVE_INTERVAL_SECONDS    = <?php echo (int)($_autosave_settings['autosave_interval'] ?? 5) * 60; ?>;
	</script>
	<script src="js/editor-common.js"></script>
	<script src="js/editor.js"></script>
	<script src="js/editor-markdown.js"></script>
	<script src="js/seo-preview.js"></script>
<?php endif; ?>
</body>
</html>