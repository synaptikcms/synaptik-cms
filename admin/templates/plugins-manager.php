<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

require_once dirname(__DIR__) . '/../core/plugin-api.php';
require_once dirname(__DIR__) . '/includes/extension-update-functions.php';

// ── ACTIVATE / DEACTIVATE / DELETE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugin_action'])) {
	admin_csrf_check();

	$slug = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');

	if ($_POST['plugin_action'] === 'activate' && $slug !== '') {
		if (pl_activate($slug)) {
			$_SESSION['message'] = __t('extensions_activated', 'Plugin activated successfully.');
		} else {
			$_SESSION['error'] = __t('extensions_activate_failed', 'Failed to activate plugin.');
		}
	} elseif ($_POST['plugin_action'] === 'deactivate' && $slug !== '') {
		pl_deactivate($slug);
		$_SESSION['message'] = __t('extensions_deactivated', 'Plugin deactivated. Its data was preserved.');
	} elseif ($_POST['plugin_action'] === 'delete' && $slug !== '') {
		if (pl_delete_plugin($slug)) {
			$_SESSION['message'] = __t('extensions_deleted', 'Plugin deleted.');
		} else {
			$_SESSION['error'] = __t('extensions_delete_failed', 'Could not delete plugin. Deactivate it first, then try again.');
		}
	} elseif ($_POST['plugin_action'] === 'update' && $slug !== '') {
		$result = admin_apply_extension_update('plugin', $slug);
		if ($result['success']) {
			$_SESSION['message'] = sprintf(__t('extensions_update_success', 'Plugin "%s" updated successfully.'), htmlspecialchars($slug));
		} else {
			$_SESSION['error'] = $result['error'] ?? __t('update_failed_apply');
		}
	}

	header('Location: index.php?action=plugins');
	exit;
}

// ── BUILD PLUGIN LIST ─────────────────────────────────────────────────────────
$plugins = pl_list_plugins();

// Active plugins first (left-most in the grid), matching the Theme Manager's
// own sort order — within each group, alphabetical by slug for a stable,
// predictable order across reloads (uasort() preserves the key => value
// association ksort() just established).
ksort($plugins);
uasort($plugins, fn($a, $b) => (int)$b['active'] <=> (int)$a['active']);

// Available updates, keyed by plugin slug — fetched once, 24h-cached.
$pluginUpdates = admin_check_plugin_updates();
?>

<p class="help-text"><?php _e('extensions_desc'); ?></p>

<!-- Install plugin -->
<div class="site-settings-section" style="margin-bottom: 24px;">
	<h3><?php echo admin_icon('upload'); ?> <?php _e('extensions_upload_title'); ?></h3>
	<div class="form-group">
		<p class="help-text"><?php _e('extensions_upload_help'); ?></p>
		<?php if (!class_exists('ZipArchive')): ?>
			<p style="color:var(--danger-text);"><?php _e('extensions_upload_no_ziparchive'); ?></p>
		<?php else: ?>
			<form method="POST" action="plugin-upload.php" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
				<input type="file" name="plugin_zip" accept=".zip" required style="flex:1; max-width:500px;">
				<button type="submit" class="btn btn-outline"><?php echo admin_icon('upload'); ?> <?php _e('extensions_upload_btn'); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>

<?php if (empty($plugins)): ?>
<div class="theme-empty"><?php _e('extensions_no_plugins'); ?></div>
<?php else: ?>
<div class="theme-grid">
	<?php foreach ($plugins as $slug => $plugin): ?>
	<div class="theme-card <?php echo $plugin['active'] ? 'is-active' : ''; ?>">

		<div class="theme-preview-placeholder">
			<?php echo admin_icon('puzzle'); ?>
		</div>

		<div class="theme-card-body">
			<div class="theme-card-name">
				<?php echo htmlspecialchars($plugin['name'] ?? $slug); ?>
				<?php if ($plugin['active']): ?>
				<span class="theme-badge-active"><?php _e('extensions_active', 'Active'); ?></span>
				<?php endif; ?>
				<?php if (isset($pluginUpdates[$slug])): ?>
				<span class="theme-badge-update" title="<?php echo htmlspecialchars(sprintf(__t('theme_update_available_title', 'Version %s available'), $pluginUpdates[$slug]['remote_version'])); ?>"><?php _e('theme_update_badge', 'Update available'); ?></span>
				<?php endif; ?>
			</div>

			<?php if (!empty($plugin['author']) || !empty($plugin['version'])): ?>
			<div class="theme-card-meta">
				<?php if (!empty($plugin['author'])): ?>
					<?php _e('theme_manager_by'); ?> <?php echo htmlspecialchars($plugin['author']); ?>
				<?php endif; ?>
				<?php if (!empty($plugin['version'])): ?>
					&nbsp;·&nbsp; v<?php echo htmlspecialchars($plugin['version']); ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($plugin['description'])): ?>
			<div class="theme-card-desc"><?php echo htmlspecialchars($plugin['description']); ?></div>
			<?php endif; ?>

			<div class="theme-card-meta" style="font-family: monospace; font-size: 0.78em;"><?php echo htmlspecialchars($slug); ?></div>

			<div class="theme-card-actions">
				<?php if ($plugin['active']): ?>
				<button type="button" class="btn btn-outline btn-sm" onclick="confirmPluginAction('deactivate', '<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>')"><?php _e('extensions_deactivate'); ?></button>
				<?php else: ?>
				<button type="button" class="btn btn-primary btn-sm" onclick="confirmPluginAction('activate', '<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>')"><?php _e('extensions_activate'); ?></button>
				<button type="button" class="btn btn-danger btn-sm" onclick="confirmPluginDelete('<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($plugin['name'] ?? $slug, ENT_QUOTES); ?>')"><?php _e('extensions_delete'); ?></button>
				<?php endif; ?>
				<?php if (isset($pluginUpdates[$slug])): ?>
				<button type="button" class="btn btn-primary btn-sm" onclick="confirmPluginUpdate('<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($plugin['name'] ?? $slug, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pluginUpdates[$slug]['remote_version'], ENT_QUOTES); ?>')"><?php echo admin_icon('update'); ?> <?php _e('theme_update_btn', 'Update'); ?></button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Hidden activate/deactivate form — submitted programmatically -->
<form id="plugin-toggle-form" method="POST" action="index.php?action=plugins" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="plugin_action" id="plugin-toggle-action" value="">
	<input type="hidden" name="slug" id="plugin-toggle-slug" value="">
</form>

<!-- Hidden delete form — submitted programmatically -->
<form id="plugin-delete-form" method="POST" action="index.php?action=plugins" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="plugin_action" value="delete">
	<input type="hidden" name="slug" id="plugin-delete-slug" value="">
</form>

<script>
function confirmPluginAction(action, slug) {
	var isActivate = action === 'activate';
	showModal(
		isActivate ? t('extensions_activate') + '?' : t('extensions_deactivate') + '?',
		isActivate ? t('extensions_activate') : t('extensions_deactivate'),
		{
			showCancel:  true,
			confirmText: isActivate ? t('extensions_activate') : t('extensions_deactivate'),
			cancelText:  t('cancel'),
			danger:      false,
			onConfirm: function () {
				document.getElementById('plugin-toggle-action').value = action;
				document.getElementById('plugin-toggle-slug').value   = slug;
				document.getElementById('plugin-toggle-form').submit();
			}
		}
	);
}

function confirmPluginDelete(slug, label) {
	showModal(
		t('extensions_delete_confirm').replace('%s', label),
		t('extensions_delete'),
		{
			showCancel:  true,
			confirmText: t('extensions_delete'),
			cancelText:  t('cancel'),
			danger:      true,
			onConfirm: function () {
				document.getElementById('plugin-delete-slug').value = slug;
				document.getElementById('plugin-delete-form').submit();
			}
		}
	);
}

function confirmPluginUpdate(slug, label, newVersion) {
	showModal(
		t('theme_update_confirm').replace('%s', label).replace('%v', newVersion),
		t('theme_update_confirm_title'),
		{
			showCancel:  true,
			confirmText: t('theme_update_btn'),
			cancelText:  t('cancel'),
			danger:      false,
			onConfirm: function () {
				document.getElementById('plugin-toggle-action').value = 'update';
				document.getElementById('plugin-toggle-slug').value   = slug;
				document.getElementById('plugin-toggle-form').submit();
			}
		}
	);
}
</script>
