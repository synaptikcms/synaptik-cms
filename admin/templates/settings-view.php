<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Render a self-contained image picker for the settings form.
 *
 * @param string $field       Base input name (e.g. 'site_logo').
 * @param string $currentPath Currently stored relative path (e.g. 'files/logo.png').
 */
function _sv_image_picker(string $field, string $currentPath): void {
	$clean = ltrim($currentPath, '/');
	$src   = $clean ? '../' . $clean : '';
	?>
	<div class="sip-wrapper" data-field="<?php echo htmlspecialchars($field); ?>">
		<div class="sip-preview"<?php echo $clean ? '' : ' style="display:none"'; ?>>
			<img class="sip-preview-img" src="<?php echo htmlspecialchars($src); ?>" alt="">
			<button type="button" class="btn btn-danger btn-sm sip-remove-btn" data-field="<?php echo htmlspecialchars($field); ?>" name="<?php _e('remove_image'); ?>">X</button>
		</div>
		<div class="sip-controls">
			<input type="file" name="<?php echo htmlspecialchars($field); ?>_file" accept="image/*"
			       class="sip-upload-input" data-field="<?php echo htmlspecialchars($field); ?>"
			       style="font-size:12px;width:auto;">
			<button type="button" class="btn btn-outline btn-sm sip-browse-btn" data-field="<?php echo htmlspecialchars($field); ?>">
				<?php _e('select_from_files'); ?>
			</button>
		</div>
		<input type="hidden" name="<?php echo htmlspecialchars($field); ?>_path"
		       id="sip-path-<?php echo htmlspecialchars($field); ?>" value="<?php echo htmlspecialchars($clean); ?>">
		<input type="hidden" name="<?php echo htmlspecialchars($field); ?>_remove"
		       id="sip-remove-<?php echo htmlspecialchars($field); ?>" value="">
	</div>
	<?php
}

$appSettings = admin_load_settings();
$data        = admin_load_data();
$activeTab   = $_GET['tab'] ?? 'general';

$socialLinks = isset($appSettings['footer_social_links']) && is_array($appSettings['footer_social_links'])
	? $appSettings['footer_social_links']
	: [['platform' => '', 'url' => '']];
?>

		<div class="tabs">
			<div class="tab <?php echo $activeTab === 'general'       ? 'active' : ''; ?>" data-tab="general"><?php echo admin_icon('settings'); ?> <?php _e('general'); ?></div>
			<div class="tab <?php echo $activeTab === 'reading'       ? 'active' : ''; ?>" data-tab="reading"><?php echo admin_icon('reading'); ?> <?php _e('settings_tab_reading'); ?></div>
			<div class="tab <?php echo $activeTab === 'writing'       ? 'active' : ''; ?>" data-tab="writing"><?php echo admin_icon('writing'); ?> <?php _e('settings_tab_writing'); ?></div>
			<div class="tab <?php echo $activeTab === 'seo'           ? 'active' : ''; ?>" data-tab="seo"><?php echo admin_icon('seo'); ?> <?php _e('seo'); ?></div>
			<div class="tab <?php echo $activeTab === 'images'        ? 'active' : ''; ?>" data-tab="images"><?php echo admin_icon('images'); ?> <?php _e('images'); ?></div>
			<div class="tab <?php echo $activeTab === 'contact'       ? 'active' : ''; ?>" data-tab="contact"><?php echo admin_icon('contact'); ?> <?php _e('settings_tab_contact'); ?></div>
			<div class="tab <?php echo $activeTab === 'custom_fields' ? 'active' : ''; ?>" data-tab="custom_fields"><?php echo admin_icon('puzzle'); ?> <?php _e('cf_tab'); ?></div>
		</div>

		<form method="post" action="index.php?action=settings" enctype="multipart/form-data">

			<!-- ══════════════════════ GENERAL TAB ══════════════════════ -->
			<div id="general-tab" class="tab-content" <?php echo $activeTab !== 'general' ? 'style="display: none;"' : ''; ?>>
				<div class="site-settings-section">
					<h3><?php _e('settings_tab_general'); ?></h3>
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
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('site_identity'); ?></h3>
					<div class="form-group">
						<label><?php _e('site_logo_label'); ?></label>
						<?php _sv_image_picker('site_logo', $appSettings['site_logo'] ?? ''); ?>
						<p class="help-text"><?php _e('site_logo_help'); ?></p>
					</div>
					<div class="form-group">
						<label><?php _e('site_favicon_label'); ?></label>
						<?php _sv_image_picker('site_favicon', $appSettings['site_favicon'] ?? ''); ?>
						<p class="help-text"><?php _e('site_favicon_help'); ?></p>
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php _e('social_media'); ?></h3>
					<div class="form-group">
						<label class="checkbox-label" for="footer_show_social">
							<input type="checkbox" id="footer_show_social" name="settings[footer_show_social]" value="1" <?php echo !empty($appSettings['footer_show_social']) ? 'checked' : ''; ?>>
							<?php _e('footer_show_social'); ?>
						</label>
						<div id="social-links-container" style="display: <?= !empty($appSettings['footer_show_social']) ? 'block' : 'none' ?>;">
							<!-- <h4><?php _e('social_links'); ?></h4> -->
							<div id="social-links">
								<?php foreach ($socialLinks as $index => $link): ?>
								<div class="social-link-row">
									<div class="form-group">
										<button type="button" class="btn btn-danger btn-sm remove-social-link">X</button>
										<select name="settings[footer_social_links][<?php echo $index; ?>][platform]" class="form-control">
											<option value=""><?php _e('select_platform'); ?></option>
											<?php
											$_pl = $link['platform'] ?? '';
											$_platforms = [
											'bluesky'   => 'Bluesky',   'discord'  => 'Discord',
											'facebook'  => 'Facebook',  'github'   => 'GitHub',
											'instagram' => 'Instagram', 'linkedin' => 'LinkedIn',
											'mastodon'  => 'Mastodon',  'pinterest'=> 'Pinterest',
											'reddit'    => 'Reddit',    'snapchat' => 'Snapchat',
											'telegram'  => 'Telegram',  'threads'  => 'Threads',
											'tiktok'    => 'TikTok',    'twitch'   => 'Twitch',
											'twitter'   => 'Twitter',   'whatsapp' => 'WhatsApp',
											'x'         => 'X',         'youtube'  => 'YouTube',
									];
									foreach ($_platforms as $_pv => $_pn): ?>
									<option value="<?php echo $_pv; ?>"<?php echo $_pl === $_pv ? ' selected' : ''; ?>><?php echo $_pn; ?></option>
									<?php endforeach; ?>
										</select>
										<input type="text" name="settings[footer_social_links][<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($link['url'] ?? ''); ?>" placeholder="URL" class="form-control">
									</div> <!-- form-group -->
								</div> <!-- social-link-row -->
								<?php endforeach; ?>
							</div>
							<button type="button" id="add-social-link" class="btn btn-outline"><?php _e('add_social_link'); ?></button>
						</div>
					</div>
				</div>
				
				<div class="site-settings-section">
					<h3><?php _e('cache_section_title'); ?></h3>
					<div class="form-group">
						<p class="help-text"><?php _e('cache_section_help'); ?></p>
						<div style="margin-top:12px;">
							<button type="submit" name="clear_cache" form="clear-cache-form" class="btn btn-danger" onclick="return confirm('<?php echo htmlspecialchars(__t('cache_clear_confirm')); ?>')">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:5px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
								<?php _e('cache_clear_btn'); ?>
							</button>
						</div>
					</div>
				</div>

			</div>

			<!-- ══════════════════════ READING TAB ══════════════════════ -->
			<div id="reading-tab" class="tab-content" <?php echo $activeTab !== 'reading' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php _e('display_settings'); ?></h3>
					<div class="form-group">
						<label for="homepage_type"><?php echo admin_icon('home'); ?> <?php _e('homepage_display_choice'); ?>:</label>
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
						<p class="help-text"><?php _e('show_site_title_help'); ?></p>
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
				</div>

			</div>

			<!-- ══════════════════════ WRITING TAB ══════════════════════ -->
			<div id="writing-tab" class="tab-content" <?php echo $activeTab !== 'writing' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php _e('settings_tab_writing'); ?></h3>
					<div class="form-group">
						<label for="date_format"><?php echo admin_icon('calendar'); ?> <?php _e('date_format_label'); ?>:</label>
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
						<label for="timezone"><?php echo admin_icon('clock'); ?> <?php _e('timezone_label'); ?>:</label>
						<select id="timezone" name="timezone">
							<?php
							$currentTz     = $appSettings['timezone'] ?? 'UTC';
							$tzIdentifiers = DateTimeZone::listIdentifiers();
							$tzGroups      = [];
							foreach ($tzIdentifiers as $tz) {
								$parts  = explode('/', $tz, 2);
								$region = $parts[0];
								$tzGroups[$region][] = $tz;
							}
							foreach ($tzGroups as $region => $zones):
							?>
							<optgroup label="<?php echo htmlspecialchars($region); ?>">
								<?php foreach ($zones as $tz):
									$offset  = (new DateTimeZone($tz))->getOffset(new DateTime('now', new DateTimeZone('UTC')));
									$sign    = $offset >= 0 ? '+' : '-';
									$abs     = abs($offset);
									$label   = sprintf('(UTC%s%02d:%02d) %s', $sign, floor($abs / 3600), ($abs % 3600) / 60, str_replace('_', ' ', $tz));
								?>
								<option value="<?php echo htmlspecialchars($tz); ?>" <?php echo $tz === $currentTz ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($label); ?>
								</option>
								<?php endforeach; ?>
							</optgroup>
							<?php endforeach; ?>
						</select>
						<p class="help-text"><?php _e('timezone_help'); ?></p>
					</div>
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" id="autosave_enabled" name="autosave_enabled" <?php echo !empty($appSettings['autosave_enabled']) ? 'checked' : ''; ?>>
							<?php _e('enable_autosave'); ?>
						</label>
						<div class="form-group" style="margin-top:8px;margin-left:24px;">
							<label for="autosave_interval"><?php _e('autosave_interval_label'); ?></label>
							<select id="autosave_interval" class="autosave_interval" name="autosave_interval" <?php echo empty($appSettings['autosave_enabled']) ? 'disabled' : ''; ?>>
								<?php
								$intervals = [1 => '1', 3 => '3', 5 => '5', 10 => '10'];
								$currentInterval = (int)($appSettings['autosave_interval'] ?? 5);
								foreach ($intervals as $val => $label):
								?>
								<option value="<?php echo $val; ?>" <?php echo $currentInterval === $val ? 'selected' : ''; ?>>
									<?php printf(__t('autosave_interval_option'), $label); ?>
								</option>
								<?php endforeach; ?>
							</select>
						</div>
						<p class="help-text"><?php _e('autosave_help'); ?></p>
					</div>
				</div>

			</div>

			<!-- ══════════════════════ SEO TAB ══════════════════════ -->
			<div id="seo-tab" class="tab-content" <?php echo $activeTab !== 'seo' ? 'style="display: none;"' : ''; ?>>
				<div class="site-settings-section">
					<h3><?php _e('seo_homepage_section'); ?></h3>
					<div class="form-group">
						<p class="help-text"><?php _e('seo_homepage_help'); ?></p>
						<label for="home_meta_title"><?php _e('default_meta_title_label'); ?>:</label>
						<input type="text" id="home_meta_title" name="home_meta_title"
							   value="<?php echo htmlspecialchars($appSettings['home_meta_title'] ?? ''); ?>"
							   placeholder="<?php echo htmlspecialchars($appSettings['site_title'] ?? ''); ?>">
						<p class="help-text"><?php _e('seo_homepage_meta_title_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="home_meta_description"><?php _e('default_meta_description_label'); ?>:</label>
						<textarea id="home_meta_description" name="home_meta_description" rows="2"
								  placeholder="<?php echo htmlspecialchars($appSettings['site_description'] ?? ''); ?>"><?php echo htmlspecialchars($appSettings['home_meta_description'] ?? ''); ?></textarea>
						<p class="help-text"><?php _e('seo_homepage_meta_desc_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="home_meta_keywords"><?php _e('meta_keywords_label'); ?>:</label>
						<input type="text" id="home_meta_keywords" name="home_meta_keywords"
							   value="<?php echo htmlspecialchars($appSettings['home_meta_keywords'] ?? ''); ?>"
							   placeholder="keyword1, keyword2, keyword3">
						<p class="help-text"><?php _e('meta_keywords_help'); ?></p>
					</div>
					<h4><?php _e('seo_og_section'); ?></h4>
					<div class="form-group">
						<label for="home_og_title"><?php _e('og_title_label'); ?>:</label>
						<input type="text" id="home_og_title" name="home_og_title"
							   value="<?php echo htmlspecialchars($appSettings['home_og_title'] ?? ''); ?>"
							   placeholder="<?php echo htmlspecialchars($appSettings['site_title'] ?? ''); ?>">
					</div>
					<div class="form-group">
						<label for="home_og_description"><?php _e('og_description_label'); ?>:</label>
						<textarea id="home_og_description" name="home_og_description" rows="2"
								  placeholder="<?php echo htmlspecialchars($appSettings['site_description'] ?? ''); ?>"><?php echo htmlspecialchars($appSettings['home_og_description'] ?? ''); ?></textarea>
					</div>
					<div class="form-group">
						<label><?php _e('og_image_label'); ?>:</label>
						<?php _sv_image_picker('home_og_image', $appSettings['home_og_image'] ?? ''); ?>
						<p class="help-text"><?php _e('seo_og_image_help'); ?></p>
					</div>
				</div>
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
					<h3><?php _e('seo_overview'); ?></h3>
					<div class="form-group">
						<p class="help-text"><?php _e('seo_overview_desc'); ?></p>
						<a href="seo-overview.php" class="btn btn-outline"><?php echo admin_icon('chart'); ?> <?php _e('seo_overview_btn'); ?></a>
					</div>
				</div>
				<div class="site-settings-section">
					<h3><?php echo admin_icon('robot'); ?> <?php _e('robots_txt_title'); ?></h3>
					<div class="form-group">
						<p class="help-text"><?php _e('robots_txt_help'); ?></p>
						<textarea id="robots_txt" name="robots_txt" rows="10"
							style="font-family: monospace; font-size: 0.85rem;"
						><?php
						$_robotsFile = dirname(dirname(__DIR__)) . '/robots.txt';
						echo htmlspecialchars(file_exists($_robotsFile) ? file_get_contents($_robotsFile) : '');
						?></textarea>
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
						<a href="batch-optimize.php" class="btn btn-outline"><?php echo admin_icon('compress'); ?> <?php _e('image_optimizer'); ?></a>
					</div>
				</div>
			</div>

			<!-- ══════════════════════ CONTACT TAB ══════════════════════ -->
			<div id="contact-tab" class="tab-content" <?php echo $activeTab !== 'contact' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php echo admin_icon('contact'); ?> <?php _e('contact_form_settings'); ?></h3>
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
					<h3><?php echo admin_icon('robot'); ?> <?php _e('hcaptcha_section'); ?></h3>
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
					<p style="color:var(--warning-text);font-size:0.85rem;">
					<?php echo admin_icon('warning'); ?> <?php _e('hcaptcha_not_configured'); ?>
					</p>
					<?php else: ?>
					<p style="color:var(--primary-text);font-size:0.85rem;">
					<?php echo admin_icon('check-circle'); ?> <?php _e('hcaptcha_configured'); ?>
					</p>
					<?php endif; ?>
				</div>

				<div class="site-settings-section">
					<h3><?php echo admin_icon('ruler'); ?> <?php _e('page_templates_section'); ?></h3>
					<p class="help-text">
						<?php _e('page_templates_help'); ?>
					</p>
					<?php
					$availableTemplates = getPageTemplates();
					if (count($availableTemplates) <= 1): ?>
					<p style="color:var(--text-muted);"><?php _e('no_page_templates'); ?></p>
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

			<!-- ══════════════════════ CUSTOM FIELDS TAB ══════════════════════ -->
			<div id="custom_fields-tab" class="tab-content" <?php echo $activeTab !== 'custom_fields' ? 'style="display: none;"' : ''; ?>>
				<!-- <div class="site-settings-section">
					<h3><?php _e('cf_title'); ?></h3>
					<p class="help-text"><?php _e('cf_desc'); ?></p>
				</div> -->

				<?php
				$cfSchema  = $appSettings['custom_fields_schema'] ?? [];
				$cfTypes   = ['article', 'page', 'project'];
				$cfLabels  = [
					'article' => __t('cf_type_article'),
					'page'    => __t('cf_type_page'),
					'project' => __t('cf_type_project'),
				];
				$fieldTypes = [
					'text'     => __t('cf_type_text'),
					'textarea' => __t('cf_type_textarea'),
					'number'   => __t('cf_type_number'),
					'url'      => __t('cf_type_url'),
					'checkbox' => __t('cf_type_checkbox'),
					'select'   => __t('cf_type_select'),
				];
				foreach ($cfTypes as $cfType):
					$fields = $cfSchema[$cfType] ?? [];
				?>
				<div class="site-settings-section">
					<h3><?php echo htmlspecialchars($cfLabels[$cfType]); ?></h3>
					<div class="form-group">
					<div class="cf-fields-list" id="cf-list-<?php echo $cfType; ?>" data-type="<?php echo $cfType; ?>">
						<?php if (empty($fields)): ?>
						<p class="cf-empty help-text"><?php _e('cf_no_fields'); ?></p>
						<?php endif; ?>
						<?php foreach ($fields as $fi => $field):
							$fKey      = htmlspecialchars($field['key']      ?? '');
							$fLabel    = htmlspecialchars($field['label']    ?? '');
							$fType     = $field['type']     ?? 'text';
							$fRequired = !empty($field['required']);
							$fOptions  = htmlspecialchars($field['options']  ?? '');
							$fInput    = "custom_fields_schema[{$cfType}][{$fi}]";
						?>
						<div class="cf-field-row" data-index="<?php echo $fi; ?>">
						 <div class="cf-field-inputs">
						  <div class="cf-col">
						   <label><?php _e('cf_field_label'); ?></label>
						   <input type="text" name="<?php echo $fInput; ?>[label]" value="<?php echo $fLabel; ?>" placeholder="<?php echo htmlspecialchars(__t('cf_field_label_ph')); ?>">
						  </div>
						  <div class="cf-col">
						   <label><?php _e('cf_field_key'); ?></label>
						   <input type="text" name="<?php echo $fInput; ?>[key]" value="<?php echo $fKey; ?>" placeholder="<?php echo htmlspecialchars(__t('cf_field_key_ph')); ?>" pattern="[a-z0-9\-_]+">
						  </div>
						<div class="cf-col">
						<label><?php _e('cf_field_type'); ?></label>
						<select name="<?php echo $fInput; ?>[type]" class="cf-type-select">
						<?php foreach ($fieldTypes as $ftVal => $ftLabel): ?>
						<option value="<?php echo $ftVal; ?>" <?php echo $fType === $ftVal ? 'selected' : ''; ?>><?php echo htmlspecialchars($ftLabel); ?></option>
						<?php endforeach; ?>
						</select>
						</div>
						<div class="cf-col cf-col-options" style="<?php echo $fType !== 'select' ? 'display:none' : ''; ?>">
						<label><?php _e('cf_field_options'); ?></label>
						<input type="text" name="<?php echo $fInput; ?>[options]" value="<?php echo $fOptions; ?>" placeholder="<?php echo htmlspecialchars(__t('cf_field_options_ph')); ?>">
						</div>
						<div class="cf-col cf-col-required">
						<label class="checkbox-label">
						<input type="checkbox" name="<?php echo $fInput; ?>[required]" value="1" <?php echo $fRequired ? 'checked' : ''; ?>>
						<?php _e('cf_field_required'); ?>
						</label>
						</div>
						</div>
						<button type="button" class="btn btn-danger btn-sm cf-delete-btn" title="<?php echo htmlspecialchars(__t('cf_delete_field')); ?>">&#x2715;</button>
						</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="btn btn-outline cf-add-btn" data-type="<?php echo $cfType; ?>">
						+ <?php _e('cf_add_field'); ?>
					</button>
					</div>
				</div>
				<?php endforeach; ?>

			</div>

			<button type="submit" name="save_settings" class="btn btn-primary btn-lg" style="margin-top:20px"><?php _e('save_all_settings'); ?></button>
		</form>

		<!-- Standalone form for cache clear — declared outside main form to avoid nesting -->
		<form id="clear-cache-form" method="post" action="index.php?action=settings"></form>

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

			// ── Custom Fields: add/remove/options toggle ────────────────────────
			const cfI18n = {
				label:      <?php echo json_encode(__t('cf_field_label')); ?>,
				labelPh:    <?php echo json_encode(__t('cf_field_label_ph')); ?>,
				key:        <?php echo json_encode(__t('cf_field_key')); ?>,
				keyPh:      <?php echo json_encode(__t('cf_field_key_ph')); ?>,
				type:       <?php echo json_encode(__t('cf_field_type')); ?>,
				options:    <?php echo json_encode(__t('cf_field_options')); ?>,
				optionsPh:  <?php echo json_encode(__t('cf_field_options_ph')); ?>,
				required:   <?php echo json_encode(__t('cf_field_required')); ?>,
				delete:     <?php echo json_encode(__t('cf_delete_field')); ?>,
				empty:      <?php echo json_encode(__t('cf_no_fields')); ?>,
				types:      <?php echo json_encode([
					'text'     => __t('cf_type_text'),
					'textarea' => __t('cf_type_textarea'),
					'number'   => __t('cf_type_number'),
					'url'      => __t('cf_type_url'),
					'checkbox' => __t('cf_type_checkbox'),
					'select'   => __t('cf_type_select'),
				]); ?>,
			};

			function cfReindex(list) {
				const type = list.dataset.type;
				list.querySelectorAll('.cf-field-row').forEach((row, i) => {
					row.dataset.index = i;
					row.querySelectorAll('[name]').forEach(el => {
						// Replace the [type][N] part in the field name, handling literal brackets
						el.name = el.name.replace(
							/custom_fields_schema\[[^\]]+\]\[\d+\]/,
							`custom_fields_schema[${type}][${i}]`
						);
					});
				});
				const empty = list.querySelector('.cf-empty');
				if (empty) empty.style.display = list.querySelectorAll('.cf-field-row').length === 0 ? '' : 'none';
			}

			function cfBuildRow(type, index) {
				const pre = `custom_fields_schema[${type}][${index}]`;
				const typeOptions = Object.entries(cfI18n.types)
					.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
				return `<div class="cf-field-row" data-index="${index}">
					<div class="cf-field-inputs">
						<div class="cf-col">
							<label>${cfI18n.label}</label>
							<input type="text" name="${pre}[label]" placeholder="${cfI18n.labelPh}" required>
						</div>
						<div class="cf-col">
							<label>${cfI18n.key}</label>
							<input type="text" name="${pre}[key]" placeholder="${cfI18n.keyPh}" pattern="[a-z0-9\\-_]+" required>
						</div>
						<div class="cf-col">
							<label>${cfI18n.type}</label>
							<select name="${pre}[type]" class="cf-type-select">${typeOptions}</select>
						</div>
						<div class="cf-col cf-col-options" style="display:none">
							<label>${cfI18n.options}</label>
							<input type="text" name="${pre}[options]" placeholder="${cfI18n.optionsPh}">
						</div>
						<div class="cf-col cf-col-required">
							<label class="checkbox-label">
								<input type="checkbox" name="${pre}[required]" value="1">
								${cfI18n.required}
							</label>
						</div>
					</div>
					<button type="button" class="btn btn-danger btn-sm cf-delete-btn" title="${cfI18n.delete}">&#x2715;</button>
				</div>`;
			}

			// Add field
			document.querySelectorAll('.cf-add-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const type = this.dataset.type;
					const list = document.getElementById('cf-list-' + type);
					const index = list.querySelectorAll('.cf-field-row').length;
					const empty = list.querySelector('.cf-empty');
					if (empty) empty.style.display = 'none';
					list.insertAdjacentHTML('beforeend', cfBuildRow(type, index));
					const newRow = list.lastElementChild;
					newRow.querySelector('.cf-type-select').addEventListener('change', cfTypeChange);
					newRow.querySelector('.cf-delete-btn').addEventListener('click', cfDeleteRow);
				});
			});

			function cfTypeChange() {
				const optionsCol = this.closest('.cf-field-row').querySelector('.cf-col-options');
				if (optionsCol) optionsCol.style.display = this.value === 'select' ? '' : 'none';
			}

			function cfDeleteRow() {
				const row  = this.closest('.cf-field-row');
				const list = row.closest('.cf-fields-list');
				row.remove();
				cfReindex(list);
			}

			// Bind existing rows
			document.querySelectorAll('.cf-type-select').forEach(sel => sel.addEventListener('change', cfTypeChange));
			document.querySelectorAll('.cf-delete-btn').forEach(btn => btn.addEventListener('click', cfDeleteRow));

			window.initialSocialLinkIndex = <?php echo count($socialLinks); ?>;

			// Autosave checkbox ↔ interval dropdown
			const autosaveCheckbox = document.getElementById('autosave_enabled');
			const autosaveInterval = document.getElementById('autosave_interval');
			if (autosaveCheckbox && autosaveInterval) {
			autosaveCheckbox.addEventListener('change', function () {
			autosaveInterval.disabled = !this.checked;
			});
			}

			// ── Settings Image Picker (SIP) ─────────────────────────────────────────────
			(function () {
			var _sipActiveField = null;
			var _sipModal = document.getElementById('sip-modal');

			document.querySelectorAll('.sip-upload-input').forEach(function (input) {
			input.addEventListener('change', function () {
			var field = this.dataset.field;
			 var file  = this.files[0];
			 if (!file) return;
			 var reader = new FileReader();
			 reader.onload = function (e) { _sipShowPreview(field, e.target.result); };
			 reader.readAsDataURL(file);
			 var p = document.getElementById('sip-path-' + field);
			 var r = document.getElementById('sip-remove-' + field);
			 if (p) p.value = '';
					if (r) r.value = '';
			 });
			});

			document.querySelectorAll('.sip-browse-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
			_sipActiveField = this.dataset.field;
			_sipModal.style.display = 'flex';
			_sipLoad('');
			});
			});

			document.querySelectorAll('.sip-remove-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
			 var field = this.dataset.field;
					_sipHidePreview(field);
			 var p = document.getElementById('sip-path-' + field);
			 var r = document.getElementById('sip-remove-' + field);
			 var f = document.querySelector('.sip-upload-input[data-field="' + field + '"]');
			 if (p) p.value = '';
			 if (r) r.value = '1';
			 if (f) f.value = '';
			});
			});

			document.getElementById('sip-modal-close').addEventListener('click', function () {
			_sipModal.style.display = 'none';
			});
			_sipModal.addEventListener('click', function (e) {
			if (e.target === this) this.style.display = 'none';
			});

			function _sipLoad(path) {
			var body = document.getElementById('sip-modal-body');
			body.innerHTML = '<p style="padding:12px;color:var(--text-muted)"><?php echo __t('loading_files'); ?></p>';
			fetch('get-files.php?path=' + encodeURIComponent(path))
			 .then(function (r) { return r.json(); })
			 .then(function (data) {
			  body.innerHTML = '';
			  if (path !== '') {
			   var up = document.createElement('div');
			 up.className = 'sip-folder-item';
			   up.textContent = '← ..';
			   up.addEventListener('click', function () {
			    _sipLoad(path.includes('/') ? path.substring(0, path.lastIndexOf('/')) : '');
			   });
			   body.appendChild(up);
			  }
			  (data.folders || []).forEach(function (folder) {
			   var div = document.createElement('div');
							div.className = 'sip-folder-item';
			   div.textContent = '📁 ' + folder;
			   div.addEventListener('click', function () { _sipLoad(path ? path + '/' + folder : folder); });
							body.appendChild(div);
			  });
			  var images = (data.files || []).filter(function (f) { return f.is_image; });
			  if (images.length === 0 && (data.folders || []).length === 0) {
			   var empty = document.createElement('p');
			   empty.style.cssText = 'grid-column:1/-1;padding:12px;color:var(--text-muted)';
			   empty.textContent = '<?php echo __t('sip_no_images'); ?>';
			   body.appendChild(empty);
			   return;
			 }
			images.forEach(function (f) {
			var filePath = 'files/' + (path ? path + '/' : '') + f.name;
			   var div = document.createElement('div');
			div.className = 'sip-file-item';
			div.innerHTML = '<img src="../' + filePath + '" loading="lazy" alt="' + f.name + '"><span>' + f.name + '</span>';
			 div.addEventListener('click', function () { _sipSelect(filePath); });
			 body.appendChild(div);
			});
			})
			.catch(function () { body.innerHTML = '<p style="padding:12px;color:var(--danger)">Error loading files.</p>'; });
			}

			function _sipSelect(filePath) {
			if (!_sipActiveField) return;
			var p = document.getElementById('sip-path-' + _sipActiveField);
			var r = document.getElementById('sip-remove-' + _sipActiveField);
			var f = document.querySelector('.sip-upload-input[data-field="' + _sipActiveField + '"]');
			 if (p) p.value = filePath;
			  if (r) r.value = '';
			   if (f) f.value = '';
			   _sipShowPreview(_sipActiveField, '../' + filePath);
				_sipModal.style.display = 'none';
			}

			function _sipShowPreview(field, src) {
				var wrapper = document.querySelector('.sip-wrapper[data-field="' + field + '"]');
				if (!wrapper) return;
				var img     = wrapper.querySelector('.sip-preview-img');
				var preview = wrapper.querySelector('.sip-preview');
				if (img)     img.src = src;
				if (preview) preview.style.display = 'flex';
			}

			function _sipHidePreview(field) {
				var wrapper = document.querySelector('.sip-wrapper[data-field="' + field + '"]');
				if (!wrapper) return;
				var preview = wrapper.querySelector('.sip-preview');
				var img     = wrapper.querySelector('.sip-preview-img');
				if (img)     img.src = '';
				if (preview) preview.style.display = 'none';
			}
		})();

	});
	</script>

	<!-- Settings Image Picker modal -->
	<div id="sip-modal" style="display:none;position:fixed;inset:0;background:var(--overlay);z-index:9999;align-items:center;justify-content:center;">
		<div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);width:760px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--shadow-lg);">
			<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border);flex-shrink:0;">
				<h3 style="margin:0;"><?php _e('sip_select_modal_title'); ?></h3>
				<button type="button" id="sip-modal-close" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted);line-height:1;">&#x2715;</button>
			</div>
			<div id="sip-modal-body" style="padding:16px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;align-content:start;min-height:120px;">
			</div>
		</div>
	</div>

	<style>
	.sip-wrapper { display:flex; flex-direction:column; gap:10px; }
	.sip-preview { display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--surface-2); border-radius:var(--radius-sm); border:1px solid var(--border); }
	.sip-preview-img { max-width:160px; max-height:70px; object-fit:contain; border-radius:var(--radius-sm); }
	.sip-controls { display:flex; gap:8px; flex-wrap:wrap; }
	.sip-upload-label { cursor:pointer; overflow: hidden; line-height: normal; }
	.sip-upload-input { padding: 0; margin: 0; }
	.sip-file-item { cursor:pointer; border:2px solid transparent; border-radius:var(--radius-sm); overflow:hidden; background:var(--surface-2); transition:border-color .15s; }
	.sip-file-item:hover { border-color:var(--primary); }
	.sip-file-item img { width:100%; height:80px; object-fit:cover; display:block; }
	.sip-file-item span { display:block; font-size:.7em; padding:4px 6px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
	.sip-folder-item { cursor:pointer; padding:10px 14px; background:var(--surface-2); border-radius:var(--radius-sm); border:1px solid var(--border); grid-column:1/-1; transition:background .15s; }
	.sip-folder-item:hover { background:var(--primary-soft); }
	</style>
