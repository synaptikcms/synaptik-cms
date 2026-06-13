<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

$appSettings  = admin_load_settings();
$contentTypes = ['article', 'page', 'project'];
?>

		<form method="post" action="index.php?action=menu_builder">
		<div class="sitemap-content">
			<div class="site-settings-section">
				<h3><?php _e('menu_configuration'); ?></h3>
				<div class="form-group">
					<label class="checkbox-label">
						<input type="checkbox" name="use_custom_menu" id="use_custom_menu" <?php echo $appSettings['use_custom_menu'] ? 'checked' : ''; ?>>
						<?php _e('use_custom_menu'); ?>
					</label>
					<p class="help-text"><?php _e('use_custom_menu_help'); ?></p>
				</div>
			</div>
				<div class="site-settings-section" id="default-menu-settings" style="<?php echo !empty($appSettings['use_custom_menu']) ? 'display: none;' : ''; ?>">
				<h3><?php _e('default_menu_settings'); ?></h3>
				<p class="help-text" style="margin-bottom: 20px;"><?php _e('default_menu_help'); ?></p>
				<div class="form-group">
					<label for="default_menu_style"><?php _e('menu_style'); ?>:</label>
					<select id="default_menu_style" name="default_menu_style">
						<option value="flat"    <?php echo (!isset($appSettings['default_menu_style']) || $appSettings['default_menu_style'] === 'flat')    ? 'selected' : ''; ?>><?php _e('menu_style_flat'); ?></option>
						<option value="grouped" <?php echo (isset($appSettings['default_menu_style']) && $appSettings['default_menu_style'] === 'grouped') ? 'selected' : ''; ?>><?php _e('menu_style_grouped'); ?></option>
					</select>
					<p class="help-text"><?php _e('menu_style_help'); ?></p>
				</div>
				<div class="form-group">
					<label for="default_menu_order"><?php _e('menu_sort'); ?>:</label>
					<select id="default_menu_order" name="default_menu_order">
						<option value="alphabetical" <?php echo (!isset($appSettings['default_menu_order']) || $appSettings['default_menu_order'] === 'alphabetical') ? 'selected' : ''; ?>><?php _e('menu_sort_alpha'); ?></option>
						<option value="date_desc"    <?php echo (isset($appSettings['default_menu_order']) && $appSettings['default_menu_order'] === 'date_desc')    ? 'selected' : ''; ?>><?php _e('menu_sort_date_desc'); ?></option>
						<option value="date_asc"     <?php echo (isset($appSettings['default_menu_order']) && $appSettings['default_menu_order'] === 'date_asc')     ? 'selected' : ''; ?>><?php _e('menu_sort_date_asc'); ?></option>
						<option value="menu_order"   <?php echo (isset($appSettings['default_menu_order']) && $appSettings['default_menu_order'] === 'menu_order')   ? 'selected' : ''; ?>><?php _e('menu_sort_manual'); ?></option>
					</select>
					<p class="help-text"><?php _e('menu_sort_help'); ?></p>
				</div>
			</div>
				<div class="site-settings-section" id="custom-menu-builder" style="<?php echo !empty($appSettings['use_custom_menu']) ? '' : 'display: none;'; ?>">
				<h3><?php _e('custom_menu_builder'); ?></h3>
				<div class="menu-builder">
					<ul id="menu-items" class="menu-items" style="padding-left: 0;">
						<?php
						if (!empty($appSettings['main_menu'])) {
							foreach ($appSettings['main_menu'] as $index => $menuItem) {
								$itemType     = $menuItem['type'];
								$itemLabel    = htmlspecialchars($menuItem['label']);
								$itemUrl      = htmlspecialchars($menuItem['url']);
								$itemId       = $menuItem['id'] ?? 'menu_item_' . uniqid();
								$parentId     = $menuItem['parent_id'] ?? '';
								$submenuClass = !empty($parentId) ? ' submenu-item' : '';
								$marginLeft   = !empty($parentId) ? ' style="margin-left: 40px;"' : '';

								echo '<li class="menu-item' . $submenuClass . '" data-id="' . $itemId . '"' . $marginLeft . '>';
								echo '<div class="menu-item-handle"><span class="handle">≡</span></div>';
								echo '<div class="menu-item-title">' . $itemLabel . '</div>';
								echo '<div class="menu-item-controls">';
								echo '<button type="button" class="btn btn-sm edit-menu-item">'  . __t('edit')   . '</button>';
								echo '<button type="button" class="btn btn-sm remove-menu-item">' . __t('remove') . '</button>';
								echo '</div>';
								echo '<input type="hidden" name="menu[' . $index . '][type]"      value="' . $itemType  . '">';
								echo '<input type="hidden" name="menu[' . $index . '][label]"     value="' . $itemLabel . '">';
								echo '<input type="hidden" name="menu[' . $index . '][url]"       value="' . $itemUrl   . '">';
								echo '<input type="hidden" name="menu[' . $index . '][id]"        value="' . $itemId    . '">';
								echo '<input type="hidden" name="menu[' . $index . '][parent_id]" value="' . $parentId  . '">';
								echo '<input type="hidden" name="menu[' . $index . '][target]"    value="' . htmlspecialchars($menuItem['target'] ?? '') . '">';
								if ($itemType == 'content' && isset($menuItem['content_type']) && isset($menuItem['content_slug'])) {
									echo '<input type="hidden" name="menu[' . $index . '][content_type]"  value="' . htmlspecialchars($menuItem['content_type'])  . '">';
									echo '<input type="hidden" name="menu[' . $index . '][content_slug]"  value="' . htmlspecialchars($menuItem['content_slug'])  . '">';
								}
								if (isset($menuItem['tag_slug'])) {
									echo '<input type="hidden" name="menu[' . $index . '][tag_slug]" value="' . htmlspecialchars($menuItem['tag_slug']) . '">';
								}
								echo '</li>';
							}
						}
						?>
					</ul>
					<div id="no-menu-items" style="<?php echo (!empty($appSettings['main_menu'])) ? 'display: none;' : ''; ?>" class="message info">
						<?php _e('no_menu_items'); ?>
					</div>
					<div class="menu-builder-controls">
						<button type="button" class="btn btn-outline" id="add-custom-link"><?php _e('add_custom_link'); ?></button>
						<button type="button" class="btn btn-outline" id="add-content-link"><?php _e('add_content_link'); ?></button>
					</div>

					<div id="custom-link-form" class="menu-item-form" style="display: none;">
						<h4><?php _e('add_custom_link'); ?></h4>
						<div class="form-group">
							<label for="custom-label"><?php _e('menu_label'); ?>:</label>
							<input type="text" id="custom-label" name="custom-label" placeholder="<?php _e('link_text'); ?>">
						</div>
						<div class="form-group">
							<label for="custom-url"><?php _e('link_url'); ?>:</label>
							<input type="text" id="custom-url" name="custom-url" placeholder="https://example.com">
						</div>
						<div class="form-group">
							<label for="custom-parent-menu"><?php _e('parent_menu_item'); ?>:</label>
							<select id="custom-parent-menu" name="custom-parent-menu">
								<option value=""><?php _e('menu_parent_none'); ?></option>
							</select>
						</div>
						<div class="form-group">
							<label class="checkbox-label">
								<input type="checkbox" id="custom-link-new-tab">
								<?php _e('open_in_new_tab'); ?>
							</label>
						</div>
					<div class="form-actions">
							<button type="button" class="btn btn-primary" id="add-custom-link-btn"><?php _e('add_to_menu'); ?></button>
							<button type="button" class="btn btn-neutral" id="cancel-custom-link"><?php _e('cancel'); ?></button>
						</div>
					</div>

					<div id="content-link-form" class="menu-item-form" style="display: none;">
						<h4><?php _e('add_content_link'); ?></h4>
						<div class="form-group">
							<label for="content-type"><?php _e('content_type'); ?>:</label>
							<select id="content-type" name="content-type">
								<option value=""><?php _e('select_type'); ?></option>
								<?php foreach ($contentTypes as $type): ?>
								<option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
								<?php endforeach; ?>
								<option value="contentlist"><?php _e('content_list'); ?></option>
								<option value="tag"><?php _e('tag'); ?></option>
							</select>
						</div>
						<div id="contentlist-options" class="form-group" style="display: none;">
							<label for="contentlist-type"><?php _e('list_type'); ?>:</label>
							<select id="contentlist-type" name="contentlist-type">
								<?php foreach ($contentTypes as $type): ?>
								<option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?>s</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div id="content-items" class="form-group" style="display: none;"></div>
						<div class="form-group">
							<label for="content-label"><?php _e('menu_label_optional'); ?>:</label>
							<input type="text" id="content-label" name="content-label" placeholder="<?php _e('menu_label_placeholder'); ?>">
						</div>
						<div class="form-group">
							<label for="content-parent-menu"><?php _e('parent_menu_item'); ?>:</label>
							<select id="content-parent-menu" name="content-parent-menu">
								<option value=""><?php _e('menu_parent_none'); ?></option>
							</select>
						</div>
						<div class="form-group">
							<label class="checkbox-label">
								<input type="checkbox" id="content-link-new-tab">
								<?php _e('open_in_new_tab'); ?>
							</label>
						</div>
					<div class="form-actions">
							<button type="button" class="btn btn-primary" id="add-content-link-btn"><?php _e('add_to_menu'); ?></button>
							<button type="button" class="btn btn-neutral" id="cancel-content-link"><?php _e('cancel'); ?></button>
						</div>
					</div>
				</div>
			</div>
			</div>
			<input type="hidden" name="save_menu" value="1">
			<button type="submit" class="btn btn-primary btn-lg"><?php _e('save_menu_btn'); ?></button>
		</form>

	<script>
	(function() {
		const useCustomCheckbox = document.getElementById('use_custom_menu');
		const defaultSettings   = document.getElementById('default-menu-settings');
		const customBuilder     = document.getElementById('custom-menu-builder');
		if (useCustomCheckbox && defaultSettings && customBuilder) {
			useCustomCheckbox.addEventListener('change', function() {
				defaultSettings.style.display = this.checked ? 'none'  : 'block';
				customBuilder.style.display   = this.checked ? 'block' : 'none';
			});
		}
	})();
	</script>