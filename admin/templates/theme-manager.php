<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

$appSettings = admin_load_settings();
$activeTheme = $appSettings['active_theme'] ?? 'default';
$themesDir   = '../theme/';

// ── HELPERS ───────────────────────────────────────────────────────────────────
if (!function_exists('tm_delete_dir')) {
	function tm_delete_dir(string $dir): bool {
		if (!is_dir($dir)) return false;
		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') continue;
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir($path) ? tm_delete_dir($path) : unlink($path);
		}
		return rmdir($dir);
	}
}

// ── ACTIVATE THEME ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_theme'])) {
	$themeName = basename($_POST['theme_name'] ?? '');
	$availableThemes = admin_get_themes();

	if (empty($themeName) || !in_array($themeName, $availableThemes)) {
		$_SESSION['error'] = __t('theme_manager_not_found');
	} else {
		$appSettings['active_theme'] = $themeName;
		$result = file_put_contents('../settings.json', json_encode($appSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		if ($result !== false) {
			$_SESSION['message'] = sprintf(__t('theme_manager_activated'), htmlspecialchars($themeName));
		} else {
			$_SESSION['error'] = __t('theme_manager_activate_failed');
		}
	}

	header('Location: index.php?action=manage_themes');
	exit;
}

// ── DELETE THEME ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_theme'])) {
	$themeName = basename($_POST['theme_name'] ?? '');

	if (empty($themeName)) {
		$_SESSION['error'] = __t('theme_manager_invalid_name');
	} elseif ($themeName === 'default') {
		$_SESSION['error'] = __t('theme_manager_cannot_delete_default');
	} elseif ($themeName === $activeTheme) {
		$_SESSION['error'] = __t('theme_manager_cannot_delete_active');
	} else {
		$themePath = realpath($themesDir . $themeName);
		$basePath  = realpath($themesDir);

		if (!$themePath || strpos($themePath, $basePath) !== 0 || !is_dir($themePath)) {
			$_SESSION['error'] = __t('theme_manager_not_found');
		} else {
			if (tm_delete_dir($themePath)) {
				$_SESSION['message'] = sprintf(__t('theme_manager_deleted'), htmlspecialchars($themeName));
			} else {
				$_SESSION['error'] = __t('theme_manager_delete_failed');
			}
		}
	}

	header('Location: index.php?action=manage_themes');
	exit;
}

// ── BUILD THEMES LIST ─────────────────────────────────────────────────────────
// URL base for preview images
$protocol   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain     = $_SERVER['HTTP_HOST'];
$baseDir    = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$siteBase   = $protocol . '://' . $domain . $baseDir;

$themes = [];
if (is_dir($themesDir)) {
	foreach (scandir($themesDir) as $dir) {
		if ($dir === '.' || $dir === '..') continue;
		if (!is_dir($themesDir . $dir))    continue;
		if (!file_exists($themesDir . $dir . '/header.php')    ||
			!file_exists($themesDir . $dir . '/footer.php')     ||
			!file_exists($themesDir . $dir . '/home.php')       ||
			!file_exists($themesDir . $dir . '/css/style.css')) continue;

		$previewFile = null;
		foreach (['preview.jpg', 'preview.jpeg', 'preview.png', 'preview.webp'] as $_pf) {
			if (file_exists($themesDir . $dir . '/' . $_pf)) { $previewFile = $_pf; break; }
		}
		$themeMeta = [];
		$themeJson = $themesDir . $dir . '/theme.json';
		if (file_exists($themeJson)) {
			$decoded = json_decode(file_get_contents($themeJson), true);
			if (is_array($decoded)) $themeMeta = $decoded;
		}

		$themes[] = [
			'name'        => $dir,
			'active'      => ($dir === $activeTheme),
			'has_preview' => $previewFile !== null,
			'preview_url' => $previewFile !== null ? ($siteBase . '/theme/' . rawurlencode($dir) . '/' . $previewFile) : '',
			'label'       => $themeMeta['name']        ?? ucfirst($dir),
			'author'      => $themeMeta['author']      ?? '',
			'version'     => $themeMeta['version']     ?? '',
			'description' => $themeMeta['description'] ?? '',
		];
	}
}
usort($themes, fn($a, $b) => $b['active'] <=> $a['active']);
?>

<p class="help-text"><?php _e('theme_manager_desc'); ?></p>

<!-- Import thème -->
<div class="site-settings-section" style="margin-bottom: 24px;">
	<h3>📦 <?php _e('theme_import_title'); ?></h3>
	<div class="form-group">
		<p class="help-text"><?php _e('theme_import_help'); ?></p>
		<?php if (!class_exists('ZipArchive')): ?>
			<p style="color:var(--color-danger,#c0392b);"><?php _e('theme_ziparchive_missing'); ?></p>
		<?php else: ?>
			<form method="POST" action="theme-upload.php" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
				<input type="hidden" name="_redirect" value="index.php?action=manage_themes">
				<input type="file" name="theme_zip" accept=".zip" required style="flex:1; max-width:500px;">
				<button type="submit" class="button">⬆️ <?php _e('theme_import_btn'); ?></button>
			</form>
			<p class="help-text" style="margin-top:6px;"><?php _e('theme_import_limit'); ?></p>
		<?php endif; ?>
	</div>
</div>

<?php if (empty($themes)): ?>
<div class="theme-empty"><?php _e('theme_manager_no_themes'); ?></div>
<?php else: ?>
<div class="theme-grid">
	<?php foreach ($themes as $theme): ?>
	<div class="theme-card <?php echo $theme['active'] ? 'is-active' : ''; ?>">

		<?php if ($theme['has_preview']): ?>
			<img
				class="theme-preview"
				src="<?php echo htmlspecialchars($theme['preview_url']); ?>"
				alt="<?php echo htmlspecialchars($theme['label']); ?>"
				loading="lazy"
			>
		<?php else: ?>
			<div class="theme-preview-placeholder">
				🖼️ <?php _e('theme_manager_no_preview'); ?>
			</div>
		<?php endif; ?>

		<div class="theme-card-body">
			<div class="theme-card-name">
				<?php echo htmlspecialchars($theme['label']); ?>
				<?php if ($theme['active']): ?>
				<span class="theme-badge-active"><?php _e('theme_active_label'); ?></span>
				<?php endif; ?>
			</div>

			<?php if ($theme['author'] || $theme['version']): ?>
			<div class="theme-card-meta">
				<?php if ($theme['author']): ?>
					<?php _e('theme_manager_by'); ?> <?php echo htmlspecialchars($theme['author']); ?>
				<?php endif; ?>
				<?php if ($theme['version']): ?>
					&nbsp;·&nbsp; v<?php echo htmlspecialchars($theme['version']); ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ($theme['description']): ?>
			<div class="theme-card-desc"><?php echo htmlspecialchars($theme['description']); ?></div>
			<?php endif; ?>

			<div class="theme-card-meta" style="font-family: monospace; font-size: 0.78em;"><?php echo htmlspecialchars($theme['name']); ?></div>

			<div class="theme-card-actions">
				<?php if (!$theme['active']): ?>
				<button
					type="button"
					class="button"
					onclick="confirmActivateTheme('<?php echo htmlspecialchars($theme['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($theme['label'], ENT_QUOTES); ?>')"><?php _e('theme_manager_activate_btn'); ?></button>
				<button
					type="button"
					class="button danger"
					onclick="confirmDeleteTheme('<?php echo htmlspecialchars($theme['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($theme['label'], ENT_QUOTES); ?>')"><?php _e('delete'); ?></button>
				<?php else: ?>
				<span class="help-text"><?php _e('theme_manager_active_cannot_delete'); ?></span>
				<?php endif; ?>
				<button
				type="button"
				class="button info"
				title="<?php _e('theme_preview_hover'); ?>"
				onclick="openThemePreview('<?php echo htmlspecialchars($theme['name'], ENT_QUOTES); ?>')"><?php _e('theme_preview_btn'); ?></button>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Hidden delete form — submitted programmatically -->
<form id="delete-theme-form" method="POST" action="index.php?action=manage_themes" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="delete_theme" value="1">
	<input type="hidden" name="theme_name" id="delete-theme-name" value="">
</form>

<!-- Hidden activate form — submitted programmatically -->
<form id="activate-theme-form" method="POST" action="index.php?action=manage_themes" style="display:none;">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
	<input type="hidden" name="activate_theme" value="1">
	<input type="hidden" name="theme_name" id="activate-theme-name" value="">
</form>

<script>
function confirmActivateTheme(name, label) {
	showModal(
		t('theme_manager_activate_confirm').replace('%s', label),
		t('theme_manager_activate_confirm_title'),
		{
			showCancel:  true,
			confirmText: t('theme_manager_activate_btn'),
			cancelText:  t('cancel'),
			danger:      false,
			onConfirm: function () {
				document.getElementById('activate-theme-name').value = name;
				document.getElementById('activate-theme-form').submit();
			}
		}
	);
}

function confirmDeleteTheme(name, label) {
	showModal(
		t('theme_manager_delete_confirm').replace('%s', label),
		t('theme_manager_delete_confirm_title'),
		{
			showCancel:  true,
			confirmText: t('delete'),
			cancelText:  t('cancel'),
			danger:      true,
			onConfirm: function () {
				document.getElementById('delete-theme-name').value = name;
				document.getElementById('delete-theme-form').submit();
			}
		}
	);
}
function openThemePreview(name) {
	window.open(
		'theme-preview.php?theme=' + encodeURIComponent(name),
		'theme-preview-' + name,
		'width=1280,height=800,scrollbars=yes,resizable=yes'
	);
}
</script>