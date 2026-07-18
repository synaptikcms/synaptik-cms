<?php
/**
 * Cats/Tags appear in the inline accordion only (Content section, active state).
 * Icons are inline SVG (Lucide, MIT) — monochrome, currentColor, no external deps.
 */

if (!isset($data)) {
	require_once dirname(dirname(__DIR__)) . '/data-layer.php';
	$data = sl_build_data_array(['article', 'page', 'project'], false);
}

$_sb_draftsDir  = __DIR__ . '/../drafts';
$_sb_draftCount = 0;
if (is_dir($_sb_draftsDir)) {
	$_sb_draftFiles = glob($_sb_draftsDir . '/*.json');
	$_sb_draftCount = $_sb_draftFiles ? count($_sb_draftFiles) : 0;
}

if (isset($contentCounts) && is_array($contentCounts)) {
	$_sb_articles = $contentCounts['article'] ?? 0;
	$_sb_pages    = $contentCounts['page']    ?? 0;
	$_sb_projects = $contentCounts['project'] ?? 0;
	$_sb_drafts   = $contentCounts['drafts']  ?? $_sb_draftCount;
} else {
	$_sb_articles = count($data['article'] ?? []);
	$_sb_pages    = count($data['page']    ?? []);
	$_sb_projects = count($data['project'] ?? []);
	$_sb_drafts   = $_sb_draftCount;
}

$_sb_action = $_GET['action'] ?? '';
$_sb_type   = $_GET['type']   ?? '';
$_sb_file   = basename($_SERVER['PHP_SELF']);

$_sb_versionFile = dirname(dirname(__DIR__)) . '/version.json';
$_sb_version     = null;
if (file_exists($_sb_versionFile)) {
	$_sb_vd = json_decode(file_get_contents($_sb_versionFile), true);
	if (!empty($_sb_vd['version'])) {
		$_sb_version = $_sb_vd['version'];
	}
}

$_sb_content_active    = !empty($_sb_type) || in_array($_sb_action, ['add', 'drafts', 'manage_categories', 'manage_tags']);
$_sb_appearance_active = in_array($_sb_action, ['appearance', 'manage_themes', 'menu_builder'])
	|| $_sb_file === 'template-editor.php';
$_sb_settings_active   = in_array($_sb_action, ['settings']);
$_sb_tools_active      = $_sb_action === 'backup'
	|| $_sb_action === 'translations'
	|| $_sb_action === 'plugins'
	|| $_sb_action === 'plugin_page'
	|| in_array($_sb_file, ['batch-optimize.php', 'alt-text-assistant.php', 'sitemap-generator.php', 'seo-overview.php']);
$_sb_account_active    = $_sb_action === 'account';
$_sb_settings_tab      = $_GET['tab'] ?? '';

/**
 * Return an inline Lucide SVG icon.
 * All paths from Lucide 0.378 (MIT). Size 16×16, stroke currentColor.
 *
 * @param string $name  Icon key
 * @param string $class Extra CSS classes
 */
function sb_icon(string $name, string $class = ''): string {
	$base = 'width="16" height="16" viewBox="0 0 24 24" fill="none" '
		  . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';
	$cls  = 'sb-icon' . ($class ? ' ' . $class : '');

	$paths = [
		'dashboard'  => '<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
		'content'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
		'media'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
		'appearance' => '<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-venetian-mask-icon lucide-venetian-mask"><path d="M18 11c-1.5 0-2.5.5-3 2"/><path d="M4 6a2 2 0 0 0-2 2v4a5 5 0 0 0 5 5 8 8 0 0 1 5 2 8 8 0 0 1 5-2 5 5 0 0 0 5-5V8a2 2 0 0 0-2-2h-3a8 8 0 0 0-5 2 8 8 0 0 0-5-2z"/><path d="M6 11c1.5 0 2.5.5 3 2"/></svg>',
		'settings'   => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="2" fill="currentColor" stroke="none"/>',
		'tools'      => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
		'account'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
	];

	$inner = $paths[$name] ?? '';
	if ($inner === '') return '';
	return '<svg class="' . $cls . '" aria-hidden="true" ' . $base . '>' . $inner . '</svg>';
}
?>
<div class="sidebar">
	<div class="admin-logo">
		<img src="assets/img/logo.webp" alt="SynaptikCMS">
		<h2>Synaptik</h2>
	</div>
	
	<?php
	$_sb_display_name = $_SESSION['admin_display_name'] ?? ($_SESSION['admin_username'] ?? '');
	if ($_sb_display_name !== ''):
	?>
	<div class="sidebar-user">
		<span id="sidebar-display-name"><?php echo htmlspecialchars($_sb_display_name); ?></span>
	</div>
	<?php endif; ?>

	<?php /* ── Dashboard ─────────────────────────────────────── */ ?>
	<div class="sidebar-section">
		<ul>
			<li>
				<a href="index.php" class="sidebar-simple-link <?php echo ($_sb_file === 'index.php' && empty($_sb_action) && empty($_sb_type)) ? 'active' : ''; ?>" data-label="<?php echo htmlspecialchars(__t('dashboard')); ?>">
					<?php echo sb_icon('dashboard'); ?><?php _e('dashboard'); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Content (flyout parent) ───────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_content_active ? 'is-open' : ''; ?>" data-flyout="articles">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_content_active ? 'active' : ''; ?>">
				<a href="index.php?type=article" class="sidebar-parent-link <?php echo $_sb_content_active ? 'active' : ''; ?>">
					<?php echo sb_icon('content'); ?>
					<span class="sb-parent-label"><?php _e('content'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=add&type=article" class="sidebar-subitem sidebar-subitem--add <?php echo $_sb_action === 'add' ? 'active' : ''; ?>"><?php _e('add_new'); ?></a></li>
			<li><a href="index.php?type=article" class="sidebar-subitem <?php echo ($_sb_type === 'article' && !in_array($_sb_action, ['add','drafts'])) ? 'active' : ''; ?>" data-badge="<?php echo $_sb_articles; ?>"><?php _e('articles'); ?></a></li>
			<li><a href="index.php?type=page" class="sidebar-subitem <?php echo ($_sb_type === 'page' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_pages; ?>"><?php _e('pages'); ?></a></li>
			<li><a href="index.php?type=project" class="sidebar-subitem <?php echo ($_sb_type === 'project' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_projects; ?>"><?php _e('projects'); ?></a></li>
			<?php if ($_sb_drafts > 0): ?>
			<li><a href="index.php?action=drafts" class="sidebar-subitem <?php echo $_sb_action === 'drafts' ? 'active' : ''; ?>" data-badge="<?php echo $_sb_drafts; ?>"><?php _e('drafts'); ?></a></li>
			<?php endif; ?>
			<li class="sidebar-subitem-sep"></li>
			<li><a href="index.php?action=manage_categories" class="sidebar-subitem <?php echo $_sb_action === 'manage_categories' ? 'active' : ''; ?>"><?php _e('categories'); ?></a></li>
			<li><a href="index.php?action=manage_tags" class="sidebar-subitem <?php echo $_sb_action === 'manage_tags' ? 'active' : ''; ?>"><?php _e('tags'); ?></a></li>
		</ul>
		<div class="sidebar-flyout-panel" data-flyout-panel="articles" role="menu" aria-hidden="true">
			<a href="index.php?action=add&type=article" class="sidebar-flyout-link sidebar-flyout-link--add <?php echo $_sb_action === 'add' ? 'active' : ''; ?>" role="menuitem"><?php _e('add_new'); ?></a>
			<div class="sidebar-flyout-sep"></div>
			<a href="index.php?type=article" class="sidebar-flyout-link <?php echo ($_sb_type === 'article' && !in_array($_sb_action, ['add','drafts'])) ? 'active' : ''; ?>" data-badge="<?php echo $_sb_articles; ?>" role="menuitem"><?php _e('articles'); ?></a>
			<a href="index.php?type=page" class="sidebar-flyout-link <?php echo ($_sb_type === 'page' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_pages; ?>" role="menuitem"><?php _e('pages'); ?></a>
			<a href="index.php?type=project" class="sidebar-flyout-link <?php echo ($_sb_type === 'project' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_projects; ?>" role="menuitem"><?php _e('projects'); ?></a>
			<?php if ($_sb_drafts > 0): ?>
			<a href="index.php?action=drafts" class="sidebar-flyout-link <?php echo $_sb_action === 'drafts' ? 'active' : ''; ?>" data-badge="<?php echo $_sb_drafts; ?>" role="menuitem"><?php _e('drafts'); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Media ─────────────────────────────────────────── */ ?>
	<div class="sidebar-section">
		<ul>
			<li>
				<a href="file-manager.php" class="sidebar-simple-link <?php echo $_sb_file === 'file-manager.php' ? 'active' : ''; ?>" data-label="<?php echo htmlspecialchars(__t('media')); ?>">
					<?php echo sb_icon('media'); ?><?php _e('media'); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Appearance (flyout) ───────────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_appearance_active ? 'is-open' : ''; ?>" data-flyout="appearance">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_appearance_active ? 'active' : ''; ?>">
				<a href="index.php?action=manage_themes" class="sidebar-parent-link <?php echo $_sb_appearance_active ? 'active' : ''; ?>">
					<?php echo sb_icon('appearance'); ?>
					<span class="sb-parent-label"><?php _e('appearance'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=manage_themes" class="sidebar-subitem <?php echo $_sb_action === 'manage_themes' ? 'active' : ''; ?>"><?php _e('theme_manager_title'); ?></a></li>
			<li><a href="index.php?action=menu_builder" class="sidebar-subitem <?php echo $_sb_action === 'menu_builder' ? 'active' : ''; ?>"><?php _e('menu_builder'); ?></a></li>
			<li><a href="template-editor.php" class="sidebar-subitem <?php echo $_sb_file === 'template-editor.php' ? 'active' : ''; ?>"><?php _e('template_editor_title'); ?></a></li>
		</ul>
		<div class="sidebar-flyout-panel" data-flyout-panel="appearance" role="menu" aria-hidden="true">
			<div class="sidebar-flyout-label"><?php _e('appearance'); ?></div>
			<a href="index.php?action=manage_themes" class="sidebar-flyout-link <?php echo $_sb_action === 'manage_themes' ? 'active' : ''; ?>" role="menuitem"><?php _e('theme_manager_title'); ?></a>
			<a href="index.php?action=menu_builder" class="sidebar-flyout-link <?php echo $_sb_action === 'menu_builder' ? 'active' : ''; ?>" role="menuitem"><?php _e('menu_builder'); ?></a>
			<a href="template-editor.php" class="sidebar-flyout-link <?php echo $_sb_file === 'template-editor.php' ? 'active' : ''; ?>" role="menuitem"><?php _e('template_editor_title'); ?></a>
		</div>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Settings (flyout parent) ──────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_settings_active ? 'is-open' : ''; ?>" data-flyout="settings">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_settings_active ? 'active' : ''; ?>">
				<a href="index.php?action=settings&tab=general" class="sidebar-parent-link <?php echo $_sb_settings_active ? 'active' : ''; ?>">
					<?php echo sb_icon('settings'); ?>
					<span class="sb-parent-label"><?php _e('settings'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=settings&tab=general" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'general') ? 'active' : ''; ?>"><?php _e('settings_general'); ?></a></li>
			<li><a href="index.php?action=settings&tab=reading" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'reading') ? 'active' : ''; ?>"><?php _e('settings_tab_reading'); ?></a></li>
			<li><a href="index.php?action=settings&tab=writing" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'writing') ? 'active' : ''; ?>"><?php _e('settings_tab_writing'); ?></a></li>
			<li><a href="index.php?action=settings&tab=seo" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'seo') ? 'active' : ''; ?>"><?php _e('seo'); ?></a></li>
			<li><a href="index.php?action=settings&tab=images" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'images') ? 'active' : ''; ?>"><?php _e('images'); ?></a></li>
			<li><a href="index.php?action=settings&tab=contact" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'contact') ? 'active' : ''; ?>"><?php _e('settings_tab_contact'); ?></a></li>
			<li><a href="index.php?action=settings&tab=custom_fields" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'custom_fields') ? 'active' : ''; ?>"><?php _e('cf_tab'); ?></a></li>
		</ul>
		<div class="sidebar-flyout-panel" data-flyout-panel="settings" role="menu" aria-hidden="true">
			<div class="sidebar-flyout-label"><?php _e('settings'); ?></div>
			<a href="index.php?action=settings&tab=general" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'general') ? 'active' : ''; ?>" role="menuitem"><?php _e('settings_general'); ?></a>
			<a href="index.php?action=settings&tab=reading" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'reading') ? 'active' : ''; ?>" role="menuitem"><?php _e('settings_tab_reading'); ?></a>
			<a href="index.php?action=settings&tab=writing" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'writing') ? 'active' : ''; ?>" role="menuitem"><?php _e('settings_tab_writing'); ?></a>
			<a href="index.php?action=settings&tab=seo" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'seo') ? 'active' : ''; ?>" role="menuitem"><?php _e('seo_settings'); ?></a>
			<a href="index.php?action=settings&tab=images" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'images') ? 'active' : ''; ?>" role="menuitem"><?php _e('images'); ?></a>
			<a href="index.php?action=settings&tab=contact" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'contact') ? 'active' : ''; ?>" role="menuitem"><?php _e('settings_tab_contact'); ?></a>
			<a href="index.php?action=settings&tab=custom_fields" class="sidebar-flyout-link <?php echo ($_sb_settings_active && $_sb_settings_tab === 'custom_fields') ? 'active' : ''; ?>" role="menuitem"><?php _e('cf_tab'); ?></a>
		</div>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Tools (flyout parent) ────────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_tools_active ? 'is-open' : ''; ?>" data-flyout="tools">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_tools_active ? 'active' : ''; ?>">
				<a href="index.php?action=backup" class="sidebar-parent-link <?php echo $_sb_tools_active ? 'active' : ''; ?>">
					<?php echo sb_icon('tools'); ?>
					<span class="sb-parent-label"><?php _e('tools'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=backup" class="sidebar-subitem <?php echo $_sb_action === 'backup' ? 'active' : ''; ?>"><?php _e('backup_export'); ?></a></li>
			<li><a href="alt-text-assistant.php" class="sidebar-subitem <?php echo $_sb_file === 'alt-text-assistant.php' ? 'active' : ''; ?>"><?php _e('alt_assistant_title'); ?></a></li>
			<li><a href="batch-optimize.php" class="sidebar-subitem <?php echo $_sb_file === 'batch-optimize.php' ? 'active' : ''; ?>"><?php _e('image_compression'); ?></a></li>
			<li><a href="seo-overview.php" class="sidebar-subitem <?php echo $_sb_file === 'seo-overview.php' ? 'active' : ''; ?>"><?php _e('seo_overview'); ?></a></li>
			<li><a href="sitemap-generator.php" class="sidebar-subitem <?php echo $_sb_file === 'sitemap-generator.php' ? 'active' : ''; ?>"><?php _e('sitemap_generator'); ?></a></li>
			<li><a href="index.php?action=translations" class="sidebar-subitem <?php echo $_sb_action === 'translations' ? 'active' : ''; ?>"><?php _e('translations_title'); ?></a></li>
			<li class="sidebar-subitem-sep"></li>
			<li><a href="index.php?action=plugins" class="sidebar-subitem <?php echo $_sb_action === 'plugins' ? 'active' : ''; ?>"><?php _e('extensions_title'); ?></a></li>
		</ul>
		<div class="sidebar-flyout-panel" data-flyout-panel="tools" role="menu" aria-hidden="true">
			<div class="sidebar-flyout-label"><?php _e('tools'); ?></div>
			<a href="index.php?action=backup" class="sidebar-flyout-link <?php echo $_sb_action === 'backup' ? 'active' : ''; ?>" role="menuitem"><?php _e('backup_export'); ?></a>
			<a href="alt-text-assistant.php" class="sidebar-flyout-link <?php echo $_sb_file === 'alt-text-assistant.php' ? 'active' : ''; ?>" role="menuitem"><?php _e('alt_assistant_title'); ?></a>
			<a href="batch-optimize.php" class="sidebar-flyout-link <?php echo $_sb_file === 'batch-optimize.php' ? 'active' : ''; ?>" role="menuitem"><?php _e('image_compression'); ?></a>
			<a href="seo-overview.php" class="sidebar-flyout-link <?php echo $_sb_file === 'seo-overview.php' ? 'active' : ''; ?>" role="menuitem"><?php _e('seo_overview'); ?></a>
			<a href="sitemap-generator.php" class="sidebar-flyout-link <?php echo $_sb_file === 'sitemap-generator.php' ? 'active' : ''; ?>" role="menuitem"><?php _e('sitemap_generator'); ?></a>
			<a href="index.php?action=translations" class="sidebar-flyout-link <?php echo $_sb_action === 'translations' ? 'active' : ''; ?>" role="menuitem"><?php _e('translations_title'); ?></a>
			<div class="sidebar-flyout-sep"></div>
			<a href="index.php?action=plugins" class="sidebar-flyout-link <?php echo $_sb_action === 'plugins' ? 'active' : ''; ?>" role="menuitem"><?php _e('extensions_title'); ?></a>
		</div>
	</div>

	<div class="sidebar-divider"></div>

	<?php
	/* ── Active plugins (registered via the 'admin_menu' hook, see plugin-api.php) ──
	 * Each active plugin can call pl_register_admin_menu() to add itself here.
	 * Rendered as simple top-level links, same visual pattern as Dashboard/Media.
	 * plugin-api.php is guaranteed loaded here rather than assumed — most admin
	 * pages only load includes/admin-functions.php, which does not pull it in. */
	if (!function_exists('pl_get_admin_menu_items')) {
		require_once dirname(dirname(__DIR__)) . '/plugin-api.php';
	}
	$_sb_plugin_items = pl_get_admin_menu_items();
	if (!empty($_sb_plugin_items)):
	?>
	<div class="sidebar-section">
		<ul>
			<?php foreach ($_sb_plugin_items as $_sb_pi): ?>
			<li>
				<a href="<?php echo htmlspecialchars($_sb_pi['url']); ?>" class="sidebar-simple-link" data-label="<?php echo htmlspecialchars($_sb_pi['label']); ?>">
					<?php if (!empty($_sb_pi['icon'])): ?>
					<svg class="sb-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?php echo $_sb_pi['icon']; ?></svg>
					<?php endif; ?>
					<?php echo htmlspecialchars($_sb_pi['label']); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="sidebar-divider"></div>
	<?php endif; ?>

	<?php /* ── Account ──────────────────────────────────────── */ ?>
	<div class="sidebar-section sidebar-theme-section">
		<button type="button" id="theme-toggle" class="sidebar-theme-toggle" aria-label="Basculer le thème clair/sombre">
			<svg class="sb-icon theme-icon-moon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
			<svg class="sb-icon theme-icon-sun" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
			<span class="sb-parent-label theme-label-dark"><?php _e('dark_mode'); ?></span>
			<span class="sb-parent-label theme-label-light"><?php _e('light_mode'); ?></span>
		</button>
	</div>

	<div class="sidebar-divider"></div>

	<div class="sidebar-section">
		<ul>
			<li>
				<a href="index.php?action=account" class="<?php echo $_sb_account_active ? 'active' : ''; ?>">
					<?php echo sb_icon('account'); ?><?php _e('account'); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Logout ───────────────────────────────────────── */ ?>
	<div class="sidebar-section">
		<ul>
			<li>
				<a href="auth.php?action=logout">
					<?php echo sb_icon('logout'); ?><?php _e('logout'); ?>
				</a>
			</li>
		</ul>
	</div>
</div>