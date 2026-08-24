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
			sl_admin_log_activity('extension_activate', $slug);
		} else {
			$_SESSION['error'] = __t('extensions_activate_failed', 'Failed to activate plugin.');
		}
	} elseif ($_POST['plugin_action'] === 'deactivate' && $slug !== '') {
		pl_deactivate($slug);
		$_SESSION['message'] = __t('extensions_deactivated', 'Plugin deactivated. Its data was preserved.');
		sl_admin_log_activity('extension_deactivate', $slug);
	} elseif ($_POST['plugin_action'] === 'pin' && $slug !== '') {
		admin_set_plugin_pinned($slug, true);
	} elseif ($_POST['plugin_action'] === 'unpin' && $slug !== '') {
		admin_set_plugin_pinned($slug, false);
	} elseif ($_POST['plugin_action'] === 'delete' && $slug !== '') {
		if (pl_delete_plugin($slug)) {
			// Drop any leftover sidebar-pin preference — the plugin no longer exists.
			admin_set_plugin_pinned($slug, false);
			$_SESSION['message'] = __t('extensions_deleted', 'Plugin deleted.');
			sl_admin_log_activity('extension_uninstall', $slug);
		} else {
			$_SESSION['error'] = __t('extensions_delete_failed', 'Could not delete plugin. Deactivate it first, then try again.');
		}
	} elseif ($_POST['plugin_action'] === 'update' && $slug !== '') {
		$result = admin_apply_extension_update('plugin', $slug);
		if ($result['success']) {
			$_SESSION['message'] = sprintf(__t('extensions_update_success', 'Plugin "%s" updated successfully.'), hsc($slug));
			sl_admin_log_activity('extension_update', $slug);
		} else {
			$_SESSION['error'] = $result['error'] ?? __t('update_failed_apply');
		}
	}

	header('Location: index.php?action=plugins');
	exit;
}

// ── BUILD PLUGIN LIST ─────────────────────────────────────────────────────────
$plugins = pl_list_plugins();

// Active plugins first, matching the Theme Manager's own sort order —
// within each group, alphabetical by slug for a stable, predictable order
// across reloads (uasort() preserves the key => value association ksort()
// just established).
ksort($plugins);
uasort($plugins, fn($a, $b) => (int)$b['active'] <=> (int)$a['active']);

// Available updates, keyed by plugin slug — fetched once, 24h-cached.
$pluginUpdates = admin_check_plugin_updates();

// Each active plugin's own settings/dashboard URL and icon, if it registered
// one via the 'admin_menu' hook — same source the sidebar uses, safe to call
// here too now that pl_get_admin_menu_items() memoizes the hook firing.
$pluginMenuItems = pl_get_admin_menu_items();
$pluginMenuUrls  = array_column($pluginMenuItems, 'url', 'slug');
$pluginMenuIcons = array_column($pluginMenuItems, 'icon', 'slug');

$pinnedPlugins = admin_get_pinned_plugins();
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
			<form method="POST" action="extension-upload.php" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
				<input type="hidden" name="_type" value="plugin">
				<input type="file" name="plugin_zip" accept=".zip" required style="flex:1; max-width:500px;">
				<button type="submit" class="btn btn-outline"><?php echo admin_icon('upload'); ?> <?php _e('extensions_upload_btn'); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>

<?php if (empty($plugins)): ?>
<div class="theme-empty"><?php _e('extensions_no_plugins'); ?></div>
<?php else: ?>
<div class="table-wrap">
<table>
<thead>
	<tr>
		<th><?php _e('extensions_title'); ?></th>
		<th><?php _e('extensions_version'); ?></th>
		<th><?php _e('extensions_active'); ?></th>
		<th><?php _e('extensions_pin_sidebar'); ?></th>
		<th><?php _e('actions'); ?></th>
	</tr>
</thead>
<tbody>
	<?php foreach ($plugins as $slug => $plugin): ?>
	<?php $_hasMenuUrl = isset($pluginMenuUrls[$slug]); ?>
	<tr>
		<td>
			<div style="display:flex; align-items:flex-start; gap:10px;">
				<?php if (!empty($pluginMenuIcons[$slug])): ?>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="admin-icon" style="flex-shrink:0; margin-top:2px; opacity:.6"><?php echo $pluginMenuIcons[$slug]; ?></svg>
				<?php else: ?>
				<?php echo admin_icon('puzzle', 'style="flex-shrink:0; margin-top:2px; opacity:.6"', 18); ?>
				<?php endif; ?>
				<div>
					<div style="font-weight:600; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
						<?php echo hsc($plugin['name'] ?? $slug); ?>
						<?php if (isset($pluginUpdates[$slug])): ?>
						<span class="theme-badge-update" title="<?php echo hsc(sprintf(__t('theme_update_available_title', 'Version %s available'), $pluginUpdates[$slug]['remote_version'])); ?>"><?php _e('theme_update_badge', 'Update available'); ?></span>
						<?php endif; ?>
					</div>
					<?php if (!empty($plugin['description'])): ?>
					<div style="font-size:.82em; color:var(--text-muted); margin-top:2px;"><?php echo hsc($plugin['description']); ?></div>
					<?php endif; ?>
					<div style="font-size:.76em; color:var(--text-faint); font-family:monospace; margin-top:2px;"><?php echo hsc($slug); ?></div>
				</div>
			</div>
		</td>
		<td style="white-space:nowrap;">
			<?php if (!empty($plugin['version'])): ?>v<?php echo hsc($plugin['version']); ?><?php endif; ?>
			<?php if (!empty($plugin['author'])): ?>
			<div style="font-size:.8em; color:var(--text-muted);"><?php _e('theme_manager_by'); ?> <?php echo hsc($plugin['author']); ?></div>
			<?php endif; ?>
		</td>
		<td>
			<label class="admin-toggle" title="<?php echo hsc($plugin['active'] ? __t('extensions_deactivate') : __t('extensions_activate')); ?>">
				<input type="checkbox" class="plugin-toggle-checkbox" data-slug="<?php echo hsc($slug); ?>" data-name="<?php echo hsc($plugin['name'] ?? $slug); ?>" <?php echo $plugin['active'] ? 'checked' : ''; ?>>
				<span class="admin-toggle-track"><span class="admin-toggle-thumb"></span></span>
			</label>
		</td>
		<td>
			<?php if ($plugin['active'] && $_hasMenuUrl): ?>
			<label class="admin-toggle" title="<?php _e('extensions_pin_sidebar'); ?>">
				<input type="checkbox" class="plugin-pin-checkbox" data-slug="<?php echo hsc($slug); ?>" <?php echo in_array($slug, $pinnedPlugins, true) ? 'checked' : ''; ?>>
				<span class="admin-toggle-track"><span class="admin-toggle-thumb"></span></span>
			</label>
			<?php endif; ?>
		</td>
		<td>
			<div style="display:flex; gap:6px; flex-wrap:wrap;">
				<?php if ($plugin['active'] && $_hasMenuUrl): ?>
				<a href="<?php echo hsc($pluginMenuUrls[$slug]); ?>" class="btn btn-outline btn-sm"><?php _e('extensions_settings_link'); ?></a>
				<?php endif; ?>
				<?php if (!$plugin['active']): ?>
				<button type="button" class="btn btn-danger btn-sm plugin-delete-trigger-btn" data-slug="<?php echo hsc($slug); ?>" data-name="<?php echo hsc($plugin['name'] ?? $slug); ?>"><?php _e('extensions_delete'); ?></button>
				<?php endif; ?>
				<?php if (isset($pluginUpdates[$slug])): ?>
				<button type="button" class="btn btn-primary btn-sm plugin-update-btn" data-slug="<?php echo hsc($slug); ?>" data-name="<?php echo hsc($plugin['name'] ?? $slug); ?>" data-remote-version="<?php echo hsc($pluginUpdates[$slug]['remote_version']); ?>"><?php echo admin_icon('update'); ?> <?php _e('theme_update_btn', 'Update'); ?></button>
				<?php endif; ?>
			</div>
		</td>
	</tr>
	<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Hidden activate/deactivate form — submitted programmatically -->
<form id="plugin-toggle-form" method="POST" action="index.php?action=plugins" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="plugin_action" id="plugin-toggle-action" value="">
	<input type="hidden" name="slug" id="plugin-toggle-slug" value="">
</form>

<!-- Hidden delete form — submitted programmatically -->
<form id="plugin-delete-form" method="POST" action="index.php?action=plugins" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="plugin_action" value="delete">
	<input type="hidden" name="slug" id="plugin-delete-slug" value="">
</form>

<script src="assets/js/plugins-manager.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/plugins-manager.js'); ?>"></script>