<?php
/**
 * Cats/Tags appear in the inline accordion only (Content section, active state).
 * Icons are inline SVG (Lucide, MIT) — monochrome, currentColor, no external deps.
 */

if (!isset($data)) {
	require_once dirname(dirname(__DIR__)) . '/core/data-layer.php';
	$data = sl_build_data_array(['article', 'page', 'project'], false);
}

$_sb_trashCount = 0;
if (function_exists('sl_admin_load_trash_index')) {
	foreach (['article', 'page', 'project'] as $_sb_trashType) {
		$_sb_trashCount += count(sl_admin_load_trash_index($_sb_trashType));
	}
}

if (isset($contentCounts) && is_array($contentCounts)) {
	$_sb_articles = $contentCounts['article'] ?? 0;
	$_sb_pages    = $contentCounts['page']    ?? 0;
	$_sb_projects = $contentCounts['project'] ?? 0;
	$_sb_trash    = $contentCounts['trash']   ?? $_sb_trashCount;
} else {
	$_sb_articles = count($data['article'] ?? []);
	$_sb_pages    = count($data['page']    ?? []);
	$_sb_projects = count($data['project'] ?? []);
	$_sb_trash    = $_sb_trashCount;
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

$_sb_content_active    = !empty($_sb_type) || in_array($_sb_action, ['add', 'trash', 'manage_categories', 'manage_tags']);
$_sb_appearance_active = in_array($_sb_action, ['appearance', 'manage_themes', 'menu_builder'])
	|| $_sb_file === 'template-editor.php';
$_sb_settings_active   = in_array($_sb_action, ['settings', 'users']);
$_sb_tools_active      = $_sb_action === 'backup'
	|| $_sb_action === 'translations'
	|| $_sb_action === 'system_info'
	|| $_sb_action === 'activity_log'
	|| in_array($_sb_file, ['batch-optimize.php', 'alt-text-assistant.php', 'sitemap-generator.php', 'seo-overview.php']);

// An active plugin's own admin page (action=plugin_page&slug=...) highlights
// that plugin's own sub-item within "Plugins" — see $_sb_plugins_active below.
$_sb_plugin_slug        = ($_sb_action === 'plugin_page') ? ($_GET['slug'] ?? '') : '';
$_sb_account_active    = $_sb_action === 'account';
$_sb_settings_tab      = $_GET['tab'] ?? '';

/**
 * Thin wrapper around the shared admin_icon() so existing sidebar call
 * sites don't need to change — keeps the 'sb-icon' class for CSS targeting.
 */
function sb_icon(string $name): string {
	return admin_icon($name, '', 18, 'sb-icon');
}
?>
<div class="sidebar">
	<div class="admin-logo">
		<img src="assets/img/logo.webp" alt="SynaptikCMS">
		<h2>Synaptik</h2>
	</div>
	
	<?php $_sb_display_name = $_SESSION['admin_display_name'] ?? ($_SESSION['admin_username'] ?? ''); ?>

	<?php /* ── Dashboard ─────────────────────────────────────── */ ?>
	<div class="sidebar-section">
		<ul>
			<li>
				<a href="index.php" class="sidebar-simple-link <?php echo ($_sb_file === 'index.php' && empty($_sb_action) && empty($_sb_type)) ? 'active' : ''; ?>" data-label="<?php echo hsc(__t('dashboard')); ?>">
					<?php echo sb_icon('dashboard'); ?><?php _e('dashboard'); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="sidebar-divider" style="margin-bottom: 15px;"></div>

	<?php /* ── Content (flyout parent) ───────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_content_active ? 'is-open' : ''; ?>" data-flyout="articles">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_content_active ? 'active' : ''; ?>">
				<a href="index.php?type=article" class="sidebar-parent-link <?php echo $_sb_content_active ? 'active' : ''; ?>">
					<?php echo sb_icon('article'); ?>
					<span class="sb-parent-label"><?php _e('content'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=add&type=article" class="sidebar-subitem sidebar-subitem--add <?php echo $_sb_action === 'add' ? 'active' : ''; ?>"><?php _e('add_new'); ?></a></li>
			<li><a href="index.php?type=article" class="sidebar-subitem <?php echo ($_sb_type === 'article' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_articles; ?>"><?php echo hsc(sl_type_label('article', true)); ?></a></li>
			<li><a href="index.php?type=page" class="sidebar-subitem <?php echo ($_sb_type === 'page' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_pages; ?>"><?php echo hsc(sl_type_label('page', true)); ?></a></li>
			<li><a href="index.php?type=project" class="sidebar-subitem <?php echo ($_sb_type === 'project' && $_sb_action !== 'add') ? 'active' : ''; ?>" data-badge="<?php echo $_sb_projects; ?>"><?php echo hsc(sl_type_label('project', true)); ?></a></li>
			<?php if ($_sb_trash > 0): ?>
			<li><a href="index.php?action=trash" class="sidebar-subitem <?php echo $_sb_action === 'trash' ? 'active' : ''; ?>" data-badge="<?php echo $_sb_trash; ?>"><?php _e('trash'); ?></a></li>
			<?php endif; ?>
			<?php if (admin_can_manage_all_content()): ?>
			<li class="sidebar-subitem-sep"></li>
			<li><a href="index.php?action=manage_categories" class="sidebar-subitem <?php echo $_sb_action === 'manage_categories' ? 'active' : ''; ?>"><?php _e('categories'); ?></a></li>
			<li><a href="index.php?action=manage_tags" class="sidebar-subitem <?php echo $_sb_action === 'manage_tags' ? 'active' : ''; ?>"><?php _e('tags'); ?></a></li>
			<?php endif; ?>
		</ul>
	</div>

	<div class="sidebar-divider"></div>

	<?php /* ── Media ─────────────────────────────────────────── */ ?>
	<div class="sidebar-section">
		<ul>
			<li>
				<a href="file-manager.php" class="sidebar-simple-link <?php echo $_sb_file === 'file-manager.php' ? 'active' : ''; ?>" data-label="<?php echo hsc(__t('media')); ?>">
					<?php echo sb_icon('images'); ?><?php _e('media'); ?>
				</a>
			</li>
		</ul>
	</div>

	<?php /* ── Appearance (flyout) — hidden for authors, who have no items in it ── */ ?>
	<?php if (admin_can_manage_all_content()): ?>
	<div class="sidebar-divider"></div>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_appearance_active ? 'is-open' : ''; ?>" data-flyout="appearance" data-flyout-label="1">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_appearance_active ? 'active' : ''; ?>">
				<a href="index.php?action=<?php echo admin_is_admin() ? 'manage_themes' : 'menu_builder'; ?>" class="sidebar-parent-link <?php echo $_sb_appearance_active ? 'active' : ''; ?>">
					<?php echo sb_icon('appearance'); ?>
					<span class="sb-parent-label"><?php _e('appearance'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<?php if (admin_is_admin()): ?>
			<li><a href="index.php?action=manage_themes" class="sidebar-subitem <?php echo $_sb_action === 'manage_themes' ? 'active' : ''; ?>"><?php _e('theme_manager_title'); ?></a></li>
			<?php endif; ?>
			<?php if (admin_can_manage_all_content()): ?>
			<li><a href="index.php?action=menu_builder" class="sidebar-subitem <?php echo $_sb_action === 'menu_builder' ? 'active' : ''; ?>"><?php _e('menu_builder'); ?></a></li>
			<?php endif; ?>
			<?php if (admin_is_admin()): ?>
			<li><a href="template-editor.php" class="sidebar-subitem <?php echo $_sb_file === 'template-editor.php' ? 'active' : ''; ?>"><?php _e('template_editor_title'); ?></a></li>
			<?php endif; ?>
		</ul>
	</div>
	<?php endif; ?>

	<div class="sidebar-divider"></div>

	<?php /* ── Tools (flyout parent) ────────────────────────── */ ?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_tools_active ? 'is-open' : ''; ?>" data-flyout="tools" data-flyout-label="1">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_tools_active ? 'active' : ''; ?>">
				<a href="<?php echo admin_is_admin() ? 'index.php?action=backup' : 'alt-text-assistant.php'; ?>" class="sidebar-parent-link <?php echo $_sb_tools_active ? 'active' : ''; ?>">
					<?php echo sb_icon('tools'); ?>
					<span class="sb-parent-label"><?php _e('tools'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="alt-text-assistant.php" class="sidebar-subitem <?php echo $_sb_file === 'alt-text-assistant.php' ? 'active' : ''; ?>"><?php _e('alt_assistant_title'); ?></a></li>
			<?php if (admin_can_manage_all_content()): ?>
			<li><a href="batch-optimize.php" class="sidebar-subitem <?php echo $_sb_file === 'batch-optimize.php' ? 'active' : ''; ?>"><?php _e('image_compression'); ?></a></li>
			<li><a href="seo-overview.php" class="sidebar-subitem <?php echo $_sb_file === 'seo-overview.php' ? 'active' : ''; ?>"><?php _e('seo_overview'); ?></a></li>
			<li><a href="sitemap-generator.php" class="sidebar-subitem <?php echo $_sb_file === 'sitemap-generator.php' ? 'active' : ''; ?>"><?php _e('sitemap_generator'); ?></a></li>
			<?php endif; ?>
			<?php if (admin_is_admin()): ?>
			<li class="sidebar-subitem-sep"></li>
			<li><a href="index.php?action=backup" class="sidebar-subitem <?php echo $_sb_action === 'backup' ? 'active' : ''; ?>"><?php _e('backup_export'); ?></a></li>
			<li><a href="index.php?action=translations" class="sidebar-subitem <?php echo $_sb_action === 'translations' ? 'active' : ''; ?>"><?php _e('translations_title'); ?></a></li>
			<li><a href="index.php?action=system_info" class="sidebar-subitem <?php echo $_sb_action === 'system_info' ? 'active' : ''; ?>"><?php _e('system_information'); ?></a></li>
			<li><a href="index.php?action=activity_log" class="sidebar-subitem <?php echo $_sb_action === 'activity_log' ? 'active' : ''; ?>"><?php _e('activity_log'); ?></a></li>
			<?php endif; ?>
		</ul>
	</div>

	<div class="sidebar-divider"></div>

	<?php
	if (!function_exists('pl_get_admin_menu_items')) {
		require_once dirname(dirname(__DIR__)) . '/core/plugin-api.php';
	}
	$_sb_pinned_slugs   = admin_get_pinned_plugins();
	$_sb_plugin_items   = array_values(array_filter(
		pl_get_admin_menu_items(),
		fn($item) => in_array($item['slug'], $_sb_pinned_slugs, true)
	));
	$_sb_plugins_active = $_sb_action === 'plugins' || $_sb_plugin_slug !== '';
	?>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_plugins_active ? 'is-open' : ''; ?>" data-flyout="plugins" data-flyout-label="1">
		<ul>
			<li class="sidebar-parent-item <?php echo $_sb_plugins_active ? 'active' : ''; ?>">
				<a href="index.php?action=plugins" class="sidebar-parent-link <?php echo $_sb_plugins_active ? 'active' : ''; ?>">
					<?php echo sb_icon('plug'); ?>
					<span class="sb-parent-label"><?php _e('plugins_nav'); ?></span>
					<span class="sidebar-flyout-arrow" aria-hidden="true"></span>
				</a>
			</li>
		</ul>
		<ul class="sidebar-subitems">
			<li><a href="index.php?action=plugins" class="sidebar-subitem sidebar-subitem--plugin <?php echo $_sb_action === 'plugins' ? 'active' : ''; ?>"><?php echo sb_icon('settings'); ?><?php _e('plugins_manage_link'); ?></a></li>
			<?php if (!empty($_sb_plugin_items)): ?>
			<li class="sidebar-subitem-sep"></li>
			<?php foreach ($_sb_plugin_items as $_sb_pi): ?>
			<?php $_sb_pi_active = ($_sb_plugin_slug !== '' && $_sb_plugin_slug === $_sb_pi['slug']); ?>
			<li>
				<a href="<?php echo hsc($_sb_pi['url']); ?>" class="sidebar-subitem sidebar-subitem--plugin <?php echo $_sb_pi_active ? 'active' : ''; ?>">
					<?php if (!empty($_sb_pi['icon'])): ?>
					<svg class="sb-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?php echo $_sb_pi['icon']; ?></svg>
					<?php endif; ?>
					<?php echo hsc($_sb_pi['label']); ?>
				</a>
			</li>
			<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	</div>
	<?php /* ── Settings (flyout parent) — admin-only ──────────── */ ?>
	<?php if (admin_is_admin()): ?>
	<div class="sidebar-divider"></div>
	<div class="sidebar-section sidebar-has-flyout <?php echo $_sb_settings_active ? 'is-open' : ''; ?>" data-flyout="settings" data-flyout-label="1">
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
			<li><a href="index.php?action=settings&tab=general" data-tab="general" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'general') ? 'active' : ''; ?>"><?php _e('settings_general'); ?></a></li>
			<li><a href="index.php?action=settings&tab=reading" data-tab="reading" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'reading') ? 'active' : ''; ?>"><?php _e('settings_tab_reading'); ?></a></li>
			<li><a href="index.php?action=settings&tab=writing" data-tab="writing" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'writing') ? 'active' : ''; ?>"><?php _e('settings_tab_writing'); ?></a></li>
			<li><a href="index.php?action=settings&tab=seo" data-tab="seo" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'seo') ? 'active' : ''; ?>"><?php _e('seo'); ?></a></li>
			<li><a href="index.php?action=settings&tab=images" data-tab="images" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'images') ? 'active' : ''; ?>"><?php _e('images'); ?></a></li>
			<li><a href="index.php?action=settings&tab=contact" data-tab="contact" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'contact') ? 'active' : ''; ?>"><?php _e('settings_tab_contact'); ?></a></li>
			<li><a href="index.php?action=settings&tab=custom_fields" data-tab="custom_fields" class="sidebar-subitem <?php echo ($_sb_settings_active && $_sb_settings_tab === 'custom_fields') ? 'active' : ''; ?>"><?php _e('cf_tab'); ?></a></li>
			<li><a href="index.php?action=users" class="sidebar-subitem <?php echo $_sb_action === 'users' ? 'active' : ''; ?>"><?php _e('users_title'); ?></a></li>
		</ul>
	</div>
	<?php endif; ?>
	<div class="sidebar-divider"></div>

	<?php /* ── Theme toggle ─────────────────────────────────── */ ?>
	<div class="sidebar-section sidebar-theme-section">
		<button type="button" id="theme-toggle" class="sidebar-theme-toggle" aria-label="Basculer le thème clair/sombre">
			<?php echo admin_icon('moon', '', 18, 'sb-icon theme-icon-moon'); ?>
			<?php echo admin_icon('sun', '', 18, 'sb-icon theme-icon-sun'); ?>
			<span class="sb-parent-label theme-label-dark"><?php _e('dark_mode'); ?></span>
			<span class="sb-parent-label theme-label-light"><?php _e('light_mode'); ?></span>
		</button>
	</div>

	<?php /* ── User footer: display name (→ account) + icon-only logout ── */ ?>
	<div class="sidebar-footer">
		<a href="index.php?action=account" class="sidebar-footer-user <?php echo $_sb_account_active ? 'active' : ''; ?>">
			<?php echo sb_icon('account'); ?>
			<span id="sidebar-display-name" class="sidebar-footer-name"><?php echo hsc($_sb_display_name); ?></span>
		</a>
		<a href="auth.php?action=logout" class="sidebar-footer-logout" title="<?php echo hsc(__t('logout')); ?>" aria-label="<?php echo hsc(__t('logout')); ?>">
			<?php echo sb_icon('logout'); ?>
		</a>
	</div>
</div>