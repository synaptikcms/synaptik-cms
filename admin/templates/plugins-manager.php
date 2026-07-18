<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

require_once dirname(__DIR__) . '/../plugin-api.php';

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
	}

	header('Location: index.php?action=plugins');
	exit;
}

// ── BUILD PLUGIN LIST ─────────────────────────────────────────────────────────
$plugins = pl_list_plugins();
ksort($plugins);
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
<div class="plugins-grid">
	<?php foreach ($plugins as $slug => $plugin): ?>
		<div class="plugin-card <?php echo $plugin['active'] ? 'plugin-card--active' : ''; ?>">
			<div class="plugin-card-icon"><?php echo admin_icon('puzzle'); ?></div>
			<div class="plugin-card-body">
				<h3 class="plugin-card-title"><?php echo htmlspecialchars($plugin['name'] ?? $slug); ?></h3>
				<p class="plugin-card-desc"><?php echo htmlspecialchars($plugin['description'] ?? ''); ?></p>
				<div class="plugin-card-meta">
					<?php if (!empty($plugin['version'])): ?>
						<span><?php _e('extensions_version'); ?> <?php echo htmlspecialchars($plugin['version']); ?></span>
					<?php endif; ?>
					<?php if (!empty($plugin['author'])): ?>
						<span><?php _e('extensions_author'); ?> <?php echo htmlspecialchars($plugin['author']); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="plugin-card-actions">
				<span class="plugin-status-badge <?php echo $plugin['active'] ? 'is-active' : 'is-inactive'; ?>">
					<?php echo $plugin['active'] ? __t('extensions_active', 'Active') : __t('extensions_inactive', 'Inactive'); ?>
				</span>
				<div class="plugin-card-buttons">
					<?php if ($plugin['active']): ?>
						<button type="button" class="btn btn-outline btn-sm" onclick="confirmPluginAction('deactivate', '<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>')"><?php _e('extensions_deactivate'); ?></button>
					<?php else: ?>
						<button type="button" class="btn btn-primary btn-sm" onclick="confirmPluginAction('activate', '<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>')"><?php _e('extensions_activate'); ?></button>
						<button type="button" class="btn btn-danger btn-sm" onclick="confirmPluginDelete('<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($plugin['name'] ?? $slug, ENT_QUOTES); ?>')"><?php _e('extensions_delete'); ?></button>
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

<style>
.plugins-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 16px;
}
.plugin-card {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 18px;
	border: 1px solid var(--border-color, #e2e8f0);
	border-radius: 10px;
	background: var(--card-bg, #fff);
}
.plugin-card--active {
	border-color: var(--accent-color, #2563eb);
}
.plugin-card-icon {
	width: 36px;
	height: 36px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 8px;
	background: var(--bg-muted, #f1f5f9);
}
.plugin-card-title {
	margin: 0 0 4px;
	font-size: 1rem;
}
.plugin-card-desc {
	margin: 0;
	font-size: 0.85rem;
	color: var(--text-muted, #64748b);
}
.plugin-card-meta {
	display: flex;
	gap: 12px;
	font-size: 0.75rem;
	color: var(--text-muted, #64748b);
	margin-top: 6px;
}
.plugin-card-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: auto;
	padding-top: 10px;
	border-top: 1px solid var(--border-color, #e2e8f0);
}
.plugin-status-badge {
	font-size: 0.75rem;
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 999px;
}
.plugin-status-badge.is-active {
	background: #f0fdf4;
	color: #16a34a;
}
.plugin-status-badge.is-inactive {
	background: #f1f5f9;
	color: #64748b;
}
.plugin-card-buttons {
	display: flex;
	gap: 6px;
}
</style>

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
</script>
