<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

$appSettings = admin_load_settings();
$data        = admin_load_data();

$socialLinks = isset($appSettings['footer_social_links']) && is_array($appSettings['footer_social_links'])
	? $appSettings['footer_social_links']
	: [['platform' => '', 'url' => '']];
?>

		<div class="tabs">
			<div class="tab <?php echo $activeTab === 'general'    ? 'active' : ''; ?>" data-tab="general">⚙️ &nbsp; <?php _e('settings_tab_general'); ?></div>
			<div class="tab <?php echo $activeTab === 'appearance' ? 'active' : ''; ?>" data-tab="appearance">🎨 &nbsp; <?php _e('appearance'); ?></div>
			<div class="tab <?php echo $activeTab === 'seo'        ? 'active' : ''; ?>" data-tab="seo">🔍 &nbsp; <?php _e('seo_settings'); ?></div>
			<div class="tab <?php echo $activeTab === 'images'     ? 'active' : ''; ?>" data-tab="images">🏞️ &nbsp; <?php _e('settings_tab_images'); ?></div>
			<div class="tab <?php echo $activeTab === 'contact'    ? 'active' : ''; ?>" data-tab="contact">✉️ &nbsp; <?php _e('settings_tab_contact'); ?></div>
		</div>

		<form method="post" action="index.php?action=settings">

			<!-- ══════════════════════ GENERAL TAB ══════════════════════ -->
			<div id="general-tab" class="tab-content" <?php echo $activeTab !== 'general' ? 'style="display: none;"' : ''; ?>>
				<div class="site-settings-section">
					<h3><?php _e('general'); ?></h3>
					<div class="form-group">
						<label for="site_title"><?php _e('site_title'); ?>:</label>
						<input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($appSettings['site_title']); ?>" required>
					</div>
					<div class="form-group">
						<label for="site_description"><?php _e('site_description'); ?>:</label>
						<textarea id="site_description" name="site_description" rows="2"><?php echo htmlspecialchars($appSettings['site_description']); ?></textarea>
					</div>
					
					<div class="settings-section">
						<div class="form-group">
							<label for="footer_text"><?php _e('footer_text'); ?>:</label>
							<input type="text" id="footer_text" name="settings[footer_text]" value="<?php echo htmlspecialchars($appSettings['footer_text'] ?? ''); ?>" class="form-control">
							<p class="help-text"><?php _e('footer_text_help'); ?></p>
						</div>
						<div class="form-group">
							<label for="date_format">📅 <?php _e('date_format_label'); ?>:</label>
							<select id="date_format" name="date_format">
								<?php
								$dateFormats = [
									'Y-m-d'  => 'YYYY-MM-DD',
									'd/m/Y'  => 'DD/MM/YYYY',
									'm/d/Y'  => 'MM/DD/YYYY',
									'd-m-Y'  => 'DD-MM-YYYY',
									'd M Y'  => 'DD Mon YYYY',
									'F j, Y' => 'Month DD, YYYY',
									'j F Y'  => 'DD Month YYYY',
								];
								$currentFormat = $appSettings['date_format'] ?? 'Y-m-d';
								foreach ($dateFormats as $format => $label):
									$example = date($format);
								?>
								<option value="<?php echo $format; ?>" <?php echo ($currentFormat === $format) ? 'selected' : ''; ?>>
									<?php echo $label; ?> (<?php echo $example; ?>)
								</option>
								<?php endforeach; ?>
							</select>
							<p class="help-text"><?php _e('date_format_help'); ?></p>
						</div>
						<div class="form-group">
							<label for="active_language"><?php _e('active_language'); ?></label>
							<select name="active_language" id="active_language" class="form-control">
								<?php
								$availableLangs = lang_available();
								$currentLang    = $appSettings['active_language'] ?? 'en';
								foreach ($availableLangs as $locale => $label):
								?>
								<option value="<?php echo htmlspecialchars($locale); ?>" <?php echo $locale === $currentLang ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($label); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="help-text"><?php _e('lang_help'); ?></p>
						</div>
						<div class="form-group">
							<label class="checkbox-label">
								<input type="checkbox" name="autosave_enabled" <?php echo !empty($appSettings['autosave_enabled']) ? 'checked' : ''; ?>>
								<?php _e('enable_autosave'); ?>
							</label>
							<p class="help-text"><?php _e('autosave_help'); ?></p>
						</div>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('display_settings'); ?></h3>
					<div class="form-group">
						<label for="homepage_type">🏠 <?php _e('homepage_display_choice'); ?>:</label>
						<select id="homepage_type" name="homepage_type">
							<option value="default" <?php echo $appSettings['homepage_type'] === 'default' ? 'selected' : ''; ?>><?php _e('homepage_default'); ?></option>
							<option value="page"    <?php echo $appSettings['homepage_type'] === 'page'    ? 'selected' : ''; ?>><?php _e('homepage_selected_page'); ?></option>
						</select>
					</div>
					<div id="homepage_page_selector" class="form-group" <?php echo $appSettings['homepage_type'] !== 'page' ? 'style="display:none;"' : ''; ?>>
						<label for="homepage_page_id"><?php _e('homepage_page_label'); ?>:</label>
						<select id="homepage_page_id" name="homepage_page_id">
							<option value=""><?php _e('select_page'); ?></option>
							<?php if (isset($data['page'])): ?>
							<?php foreach ($data['page'] as $idx => $page): ?>
							<?php $pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug']; ?>
							<option value="<?php echo htmlspecialchars($pageSlug); ?>" <?php echo $pageSlug === $appSettings['homepage_page_id'] ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($page['title']); ?>
							</option>
							<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>
					<h4><?php _e('pagination_settings'); ?></h4>
					<div class="form-group">
						<label for="articles_per_page"><?php _e('articles_per_page'); ?>:</label>
						<input type="number" id="articles_per_page" name="articles_per_page" value="<?php echo $appSettings['articles_per_page']; ?>" min="1" max="50">
						<label class="checkbox-label">
							<input type="checkbox" name="show_articles_on_homepage" <?php echo $appSettings['show_articles_on_homepage'] ? 'checked' : ''; ?>>
							<?php _e('show_articles_on_homepage'); ?>
						</label>
						<label for="projects_per_page"><?php _e('projects_per_page'); ?>:</label>
						<input type="number" id="projects_per_page" name="projects_per_page" value="<?php echo $appSettings['projects_per_page'] ?? 3; ?>" min="1" max="20">
						<label class="checkbox-label">
							<input type="checkbox" name="show_projects_on_homepage" <?php echo $appSettings['show_projects_on_homepage'] ? 'checked' : ''; ?>>
							<?php _e('show_projects_on_homepage'); ?>
						</label>
					</div>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="show_breadcrumbs" <?php echo !empty($appSettings['show_breadcrumbs']) ? 'checked' : ''; ?>>
							<?php _e('show_breadcrumbs'); ?>
						</label>
						<p class="help-text"><?php _e('show_breadcrumbs_help'); ?></p>
						<label class="checkbox-label">
							<input type="checkbox" name="show_site_title_in_header" <?php echo $appSettings['show_site_title_in_header'] ? 'checked' : ''; ?>>
							<?php _e('show_site_title_in_header'); ?>
						</label>
						<label class="checkbox-label" for="footer_show_login">
							<input type="checkbox" id="footer_show_login" name="settings[footer_show_login]" value="1" <?php echo !empty($appSettings['footer_show_login']) ? 'checked' : ''; ?>>
							<?php _e('footer_show_login'); ?>
						</label>
						<p class="help-text"><?php _e('footer_show_login_warning'); ?></p>
						<label class="checkbox-label">
							<input type="checkbox" name="show_search_icon" id="show_search_icon" <?php echo !empty($appSettings['show_search_icon']) ? 'checked' : ''; ?>>
							<?php _e('show_search_icon'); ?>
						</label>
						<p class="help-text"><?php _e('show_search_icon_help'); ?></p>
					</div>
					<div class="form-group">
						<label class="checkbox-label" for="footer_show_social">
							<input type="checkbox" id="footer_show_social" name="settings[footer_show_social]" value="1" <?php echo !empty($appSettings['footer_show_social']) ? 'checked' : ''; ?>>
							<?php _e('footer_show_social'); ?>
						</label>
						<div id="social-links-container" style="display: <?= !empty($appSettings['footer_show_social']) ? 'block' : 'none' ?>;">
							<h4><?php _e('social_links'); ?></h4>
							<div id="social-links">
								<?php foreach ($socialLinks as $index => $link): ?>
								<div class="social-link-row">
									<div class="form-group">
										<select name="settings[footer_social_links][<?php echo $index; ?>][platform]" class="form-control">
											<option value=""><?php _e('select_platform'); ?></option>
											<option value="instagram" <?php echo isset($link['platform']) && $link['platform'] === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
											<option value="twitter"   <?php echo isset($link['platform']) && $link['platform'] === 'twitter'   ? 'selected' : ''; ?>>Twitter</option>
											<option value="github"    <?php echo isset($link['platform']) && $link['platform'] === 'github'    ? 'selected' : ''; ?>>GitHub</option>
											<option value="facebook"  <?php echo isset($link['platform']) && $link['platform'] === 'facebook'  ? 'selected' : ''; ?>>Facebook</option>
											<option value="linkedin"  <?php echo isset($link['platform']) && $link['platform'] === 'linkedin'  ? 'selected' : ''; ?>>LinkedIn</option>
										</select>
										<button type="button" class="button danger remove-social-link"><?php _e('remove'); ?></button>
										<input type="text" name="settings[footer_social_links][<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($link['url'] ?? ''); ?>" placeholder="URL" class="form-control">
									</div>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" id="add-social-link" class="button btn-secondary"><?php _e('add_social_link'); ?></button>
						</div>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('change_password_title'); ?></h3>
					<div class="form-group">
						<label for="current_password"><?php _e('current_password_field'); ?>:</label>
						<div class="password-wrapper">
							<input type="password" id="current_password" name="current_password">
							<button type="button" class="toggle-password" aria-label="Toggle password visibility" data-target="current_password">
								<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
									<circle cx="12" cy="12" r="3"/>
								</svg>
							</button>
						</div>
					</div>
					<div class="form-group">
						<label for="new_password"><?php _e('new_password_field'); ?>:</label>
						<div class="password-wrapper">
							<input type="password" id="new_password" name="new_password">
							<button type="button" class="toggle-password" aria-label="Toggle password visibility" data-target="new_password">
								<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
									<circle cx="12" cy="12" r="3"/>
								</svg>
							</button>
						</div>
						<p class="help-text"><?php _e('new_password_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="confirm_password"><?php _e('confirm_password_field'); ?>:</label>
						<div class="password-wrapper">
							<input type="password" id="confirm_password" name="confirm_password">
							<button type="button" class="toggle-password" aria-label="Toggle password visibility" data-target="confirm_password">
								<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
									<circle cx="12" cy="12" r="3"/>
								</svg>
							</button>
						</div>
					</div>
					<button type="button" id="change_password_btn" class="button"><?php _e('change_password_btn_text'); ?></button>
					<div id="password_change_message"></div>
				</div>
			</div>

			<!-- ══════════════════════ APPEARANCE TAB ══════════════════════ -->
			<div id="appearance-tab" class="tab-content" <?php echo $activeTab !== 'appearance' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php _e('website_theme'); ?></h3>
					<div class="form-group">
						<label for="active_theme"><?php _e('website_theme_choose'); ?>:</label>
						<select id="active_theme" name="active_theme">
							<?php
							$availableThemes = admin_get_themes();
							$currentTheme    = $appSettings['active_theme'] ?? 'default';
							sort($availableThemes);
							foreach ($availableThemes as $themeName):
								$selected = ($themeName === $currentTheme) ? 'selected' : '';
							?>
							<option value="<?php echo htmlspecialchars($themeName); ?>" <?php echo $selected; ?>><?php echo ucfirst($themeName); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="help-text"><?php _e('themes_help'); ?></p>
						<div class="theme-info" style="margin-top: 10px; padding: 10px; background-color: #f1f1f1; border-radius: 4px;">
							<strong><?php _e('detected_themes'); ?>:</strong>
							<?php if (empty($availableThemes)): ?>
							<p><?php _e('no_themes_detected'); ?></p>
							<?php else: ?>
							<ul style="margin-top: 5px; margin-bottom: 0;">
								<?php foreach ($availableThemes as $theme): ?>
								<li><?php echo ucfirst(htmlspecialchars($theme)); ?> <?php echo ($theme === $currentTheme) ? '(' . __t('theme_active_label') . ')' : ''; ?></li>
								<?php endforeach; ?>
							</ul>
							<?php endif; ?>
						</div>

						<div id="theme-import-section" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border-color,#e0e0e0);">
							<label style="font-weight:600; display:block; margin-bottom:6px;">📦 <?php _e('theme_import_title'); ?></label>
							<p class="help-text" style="margin-bottom:8px;"><?php _e('theme_import_help'); ?></p>
							<?php if (!class_exists('ZipArchive')): ?>
								<p style="color:var(--color-danger,#c0392b);"><?php _e('theme_ziparchive_missing'); ?></p>
							<?php else: ?>
								<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
									<input type="file" name="theme_zip" form="theme-upload-form" accept=".zip" required style="flex:1; min-width:180px;">
									<button type="submit" form="theme-upload-form" class="button">⬆️ <?php _e('theme_import_btn'); ?></button>
								</div>
								<p class="help-text" style="margin-top:6px;"><?php _e('theme_import_limit'); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('appearance'); ?></h3>
					<div class="form-group">
						<a href="index.php?action=manage_themes" class="button btn-secondary">🎭 <?php _e('theme_manager_btn'); ?></a>
						<a href="index.php?action=menu_builder" class="button btn-secondary">⛩️ <?php _e('menu_builder_open'); ?></a>
						<a href="css-editor.php" class="button btn-secondary">🎨 <?php _e('css_theme_editor'); ?></a>
					</div>
				</div>
			</div>

			<!-- ══════════════════════ SEO TAB ══════════════════════ -->
			<div id="seo-tab" class="tab-content" <?php echo $activeTab !== 'seo' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php _e('seo_settings'); ?></h3>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="enable_seo" <?php echo $appSettings['enable_seo'] ? 'checked' : ''; ?>>
							<?php _e('enable_seo'); ?>
						</label>
						<p class="help-text"><?php _e('seo_help_text'); ?></p>
					</div>
					<div class="form-group">
						<label for="default_meta_title"><?php _e('default_meta_title_label'); ?>:</label>
						<input type="text" id="default_meta_title" name="default_meta_title" value="<?php echo htmlspecialchars($appSettings['default_meta_title']); ?>">
						<p class="help-text"><?php _e('meta_title_vars'); ?></p>
					</div>
					<div class="form-group">
						<label for="default_meta_description"><?php _e('default_meta_description_label'); ?>:</label>
						<textarea id="default_meta_description" name="default_meta_description" rows="2"><?php echo htmlspecialchars($appSettings['default_meta_description']); ?></textarea>
						<p class="help-text"><?php _e('meta_description_vars'); ?></p>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('seo_overview'); ?></h3>
					<p class="help-text"><?php _e('seo_overview_desc'); ?></p>
					<div class="form-group">
						<a href="seo-overview.php" class="button">📈 <?php _e('seo_overview_btn'); ?></a>
					</div>
				</div>
			</div>

			<!-- ══════════════════════ IMAGES TAB ══════════════════════ -->
			<div id="images-tab" class="tab-content" <?php echo $activeTab !== 'images' ? 'style="display: none;"' : ''; ?>>
				<div class="site-settings-section">
					<h3><?php _e('image_optimization_settings'); ?></h3>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="image_optimization_enabled" <?php echo !empty($appSettings['image_optimization_enabled']) ? 'checked' : ''; ?>>
							<?php _e('enable_image_optimization'); ?>
						</label>
						<p class="help-text"><?php _e('image_optimization_help'); ?></p>
					</div>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="convert_to_webp" <?php echo !empty($appSettings['convert_to_webp']) ? 'checked' : ''; ?>>
							<?php _e('convert_to_webp'); ?>
						</label>
						<p class="help-text"><?php _e('convert_to_webp_help'); ?></p>
					</div>
					<h4><?php _e('resize_compression'); ?></h4>
					<div class="form-group" style="margin-top:30px;">
						<label for="max_width"><?php _e('max_width'); ?>:</label>
						<input type="number" id="max_width" name="max_width" value="<?php echo $appSettings['max_width'] ?? 1920; ?>" min="100" max="4000">
					</div>
					<div class="form-group">
						<label for="max_height"><?php _e('max_height'); ?>:</label>
						<input type="number" id="max_height" name="max_height" value="<?php echo $appSettings['max_height'] ?? 1080; ?>" min="100" max="4000">
					</div>
					<div class="form-group">
						<label for="image_quality"><?php _e('image_quality'); ?>:</label>
						<input type="range" id="image_quality" name="image_quality" value="<?php echo $appSettings['image_quality'] ?? 85; ?>" min="1" max="100" oninput="document.getElementById('quality_value').textContent = this.value">
						<span id="quality_value"><?php echo $appSettings['image_quality'] ?? 85; ?></span>
						<p class="help-text"><?php _e('image_quality_help'); ?></p>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('auto_thumbnails'); ?></h3>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="create_thumbnails" <?php echo !empty($appSettings['create_thumbnails']) ? 'checked' : ''; ?>>
							<?php _e('create_thumbnails'); ?>
						</label>
						<p class="help-text"><?php _e('create_thumbnails_help'); ?></p>
					</div>
					<h4><?php _e('thumbnails_size'); ?></h4>
					<div class="form-group">
						<label for="thumb_width"><?php _e('thumb_width'); ?>:</label>
						<input type="number" id="thumb_width" name="thumb_width" value="<?php echo $appSettings['thumb_width'] ?? 300; ?>" min="50" max="1000">
					</div>
					<div class="form-group">
						<label for="thumb_height"><?php _e('thumb_height'); ?>:</label>
						<input type="number" id="thumb_height" name="thumb_height" value="<?php echo $appSettings['thumb_height'] ?? 300; ?>" min="50" max="1000">
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('image_optimizer'); ?></h3>
					<p class="help-text" style="margin-left: 20px;"><?php _e('batch_optimizer_desc'); ?></p>
					<div class="form-group">
						<a href="batch-optimize.php" class="button">🗜️ <?php _e('image_optimizer'); ?></a>
					</div>
				</div>
			</div>

			<!-- ══════════════════════ CONTACT TAB ══════════════════════ -->
			<div id="contact-tab" class="tab-content" <?php echo $activeTab !== 'contact' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3>&#x2709;&#xFE0F; <?php _e('contact_form_settings'); ?></h3>
					<p class="help-text" style="margin-bottom:16px;margin-left: 20px;"><?php _e('contact_form_help'); ?></p>
					<div class="form-group">
						<label for="contact_email"><?php _e('contact_email_to'); ?> *</label>
						<input type="email" id="contact_email" name="contact_email"
							value="<?php echo htmlspecialchars($appSettings['contact_email'] ?? ''); ?>"
							placeholder="you@example.com">
						<p class="help-text"><?php _e('contact_email_to_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="contact_subject"><?php _e('contact_subject'); ?></label>
						<input type="text" id="contact_subject" name="contact_subject"
							value="<?php echo htmlspecialchars($appSettings['contact_subject'] ?? 'New message from {name}'); ?>">
						<p class="help-text"><?php _e('contact_subject_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="contact_success_message"><?php _e('contact_success_msg'); ?></label>
						<input type="text" id="contact_success_message" name="contact_success_message"
							value="<?php echo htmlspecialchars($appSettings['contact_success_message'] ?? ''); ?>"
							placeholder="<?php _e('contact_success_default'); ?>">
					</div>
					<div class="form-group">
						<label for="contact_error_message"><?php _e('contact_error_msg'); ?></label>
						<input type="text" id="contact_error_message" name="contact_error_message"
							value="<?php echo htmlspecialchars($appSettings['contact_error_message'] ?? ''); ?>"
							placeholder="<?php _e('contact_error_default'); ?>">
					</div>
				</div>

				<div class="site-settings-section">
					<h3>&#x1F916; <?php _e('hcaptcha_section'); ?></h3>
					<p class="help-text" style="margin-bottom:12px;margin-left: 20px;"><?php _e('hcaptcha_help'); ?></p>
					<div class="form-group">
						<label for="hcaptcha_site_key"><?php _e('hcaptcha_site_key'); ?></label>
						<input type="text" id="hcaptcha_site_key" name="hcaptcha_site_key"
							value="<?php echo htmlspecialchars($appSettings['hcaptcha_site_key'] ?? ''); ?>"
							placeholder="10000000-ffff-ffff-ffff-000000000001"
							autocomplete="off">
						<p class="help-text"><?php _e('hcaptcha_site_key_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="hcaptcha_secret_key"><?php _e('hcaptcha_secret_key'); ?></label>
						<input type="password" id="hcaptcha_secret_key" name="hcaptcha_secret_key"
							value="<?php echo htmlspecialchars($appSettings['hcaptcha_secret_key'] ?? ''); ?>"
							autocomplete="off">
						<p class="help-text"><?php _e('hcaptcha_secret_key_help'); ?></p>
					</div>
					<?php if (empty($appSettings['hcaptcha_site_key'])): ?>
					<p style="color:var(--color-warning,#e67e22);font-size:0.85rem;">
						&#x26A0;&#xFE0F; <?php _e('hcaptcha_not_configured'); ?>
					</p>
					<?php else: ?>
					<p style="color:var(--color-success,#27ae60);font-size:0.85rem;">
						&#x2705; <?php _e('hcaptcha_configured'); ?>
					</p>
					<?php endif; ?>
				</div>

				<div class="site-settings-section">
					<h3>&#x1F4D0; <?php _e('page_templates_section'); ?></h3>
					<p class="help-text">
						<?php _e('page_templates_help'); ?>
					</p>
					<?php
					$availableTemplates = getPageTemplates();
					if (count($availableTemplates) <= 1): ?>
					<p style="color:var(--color-muted,#888);"><?php _e('no_page_templates'); ?></p>
					<?php else: ?>
					<ul style="margin:0;padding-left:18px;">
						<?php foreach ($availableTemplates as $tKey => $tName):
							if ($tKey === '') continue; ?>
						<li><code><?php echo htmlspecialchars($tKey); ?>.php</code> &mdash; <?php echo htmlspecialchars($tName); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>

			</div>

			<button type="submit" name="save_settings" class="button save-button"><?php _e('save_all_settings'); ?></button>
		</form>

		<!-- Form upload thème : déclaré ici, champs liés via form="theme-upload-form" -->
		<form id="theme-upload-form"
			  method="post"
			  action="theme-upload.php"
			  enctype="multipart/form-data"
			  style="display:none;">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
		</form>

	<script>
		document.addEventListener('DOMContentLoaded', function() {

			document.querySelectorAll('.message.success, .message.error').forEach(function(msg) {
				setTimeout(function() {
					msg.style.transition = 'opacity 0.5s';
					msg.style.opacity    = '0';
					setTimeout(function() { msg.style.display = 'none'; msg.style.opacity = '1'; }, 500);
				}, 5000);
			});

			document.querySelectorAll('.tab').forEach(tab => {
				tab.addEventListener('click', function() {
					document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
					document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
					const tabId = this.getAttribute('data-tab');
					document.getElementById(tabId + '-tab').style.display = 'grid';
					this.classList.add('active');
					const url = new URL(window.location.href);
					url.searchParams.set('tab', tabId);
					history.replaceState(null, '', url);
				});
			});

			const homepageTypeSelect = document.getElementById('homepage_type');
			if (homepageTypeSelect) {
				homepageTypeSelect.addEventListener('change', function() {
					document.getElementById('homepage_page_selector').style.display = (this.value === 'page') ? 'block' : 'none';
				});
			}

			const changePasswordBtn = document.getElementById('change_password_btn');
			if (changePasswordBtn) {
				changePasswordBtn.addEventListener('click', function() {
					const currentPassword = document.getElementById('current_password').value;
					const newPassword     = document.getElementById('new_password').value;
					const confirmPassword = document.getElementById('confirm_password').value;

					if (!currentPassword || !newPassword || !confirmPassword) {
						showPasswordMessage(t('password_fill_all'), 'error');
						return;
					}
					if (newPassword !== confirmPassword) {
						showPasswordMessage(t('passwords_dont_match'), 'error');
						return;
					}
					const complexityRe = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/;
					if (!complexityRe.test(newPassword)) {
						showPasswordMessage(t('password_too_weak'), 'error');
						return;
					}
					if (newPassword === currentPassword) {
						showPasswordMessage(t('password_same_as_current'), 'error');
						return;
					}
					const csrfToken = '<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? ""); ?>';
					const xhr = new XMLHttpRequest();
					xhr.open('POST', 'change-password.php', true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onload = function() {
						if (xhr.status === 200) {
							try {
								const response = JSON.parse(xhr.responseText);
								showPasswordMessage(t(response.message), response.status);
								if (response.status === 'success') {
									document.getElementById('current_password').value = '';
									document.getElementById('new_password').value     = '';
									document.getElementById('confirm_password').value = '';
								}
							} catch (e) {
								showPasswordMessage(t('invalid_server_response'), 'error');
							}
						} else {
							showPasswordMessage(t('error_try_again'), 'error');
						}
					};
					xhr.send(`csrf_token=${encodeURIComponent(csrfToken)}&current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`);
				});
			}

			function showPasswordMessage(message, type) {
				const messageElement = document.getElementById('password_change_message');
				if (messageElement) {
					messageElement.className     = type === 'error' ? 'message error' : 'message success';
					messageElement.textContent   = message;
					messageElement.style.display = 'block';
					setTimeout(function() {
						messageElement.style.opacity = '0';
						setTimeout(function() { messageElement.style.display = 'none'; messageElement.style.opacity = '1'; }, 500);
					}, 5000);
				}
			}

			window.initialSocialLinkIndex = <?php echo count($socialLinks); ?>;

			document.querySelectorAll('.toggle-password').forEach(function(btn) {
				btn.addEventListener('click', function() {
					const input = document.getElementById(this.dataset.target);
					if (!input) return;
					const isHidden = input.type === 'password';
					input.type = isHidden ? 'text' : 'password';
					const svg = this.querySelector('svg');
					if (isHidden) {
						svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>' +
							'<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>' +
							'<line x1="1" y1="1" x2="23" y2="23"/>';
					} else {
						svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
							'<circle cx="12" cy="12" r="3"/>';
					}
				});
			});
		});
	</script>
	</main>
