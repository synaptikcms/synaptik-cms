<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

function _sv_image_picker(string $field, string $currentPath): void {
	$clean = ltrim($currentPath, '/');
	$src   = $clean ? '../' . $clean : '';
	?>
	<div class="sip-wrapper" data-field="<?php echo hsc($field); ?>">
		<div class="sip-preview"<?php echo $clean ? '' : ' style="display:none"'; ?>>
			<img class="sip-preview-img" src="<?php echo hsc($src); ?>" alt="">
			<button type="button" class="btn btn-danger btn-sm sip-remove-btn" data-field="<?php echo hsc($field); ?>" name="<?php _e('remove_image'); ?>">X</button>
		</div>
		<div class="sip-controls">
			<input type="file" name="<?php echo hsc($field); ?>_file" accept="image/*"
			       class="sip-upload-input" data-field="<?php echo hsc($field); ?>"
			       style="font-size:12px;width:auto;">
			<button type="button" class="btn btn-outline btn-sm sip-browse-btn" data-field="<?php echo hsc($field); ?>">
				<?php _e('select_from_files'); ?>
			</button>
		</div>
		<input type="hidden" name="<?php echo hsc($field); ?>_path"
		       id="sip-path-<?php echo hsc($field); ?>" value="<?php echo hsc($clean); ?>">
		<input type="hidden" name="<?php echo hsc($field); ?>_remove"
		       id="sip-remove-<?php echo hsc($field); ?>" value="">
	</div>
	<?php
}

$appSettings = admin_load_config();
$data        = admin_load_data();
$activeTab   = $_GET['tab'] ?? 'general';

$socialLinks = isset($appSettings['footer_social_links']) && is_array($appSettings['footer_social_links'])
	? $appSettings['footer_social_links']
	: [['platform' => '', 'url' => '']];
?>

		<?php admin_render_settings_tabs($activeTab, true); ?>

		<form method="post" action="index.php?action=settings" enctype="multipart/form-data">
			<input type="hidden" name="tab" id="settings-active-tab" value="<?php echo hsc($activeTab); ?>">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">

			<!-- ══════════════════════ GENERAL TAB ══════════════════════ -->
			<div id="general-tab" class="tab-content" <?php echo $activeTab !== 'general' ? 'style="display: none;"' : ''; ?>>
				<div class="site-settings-section">
					<h3><?php _e('settings_tab_general'); ?></h3>
					<div class="form-group">
						<label for="site_title"><?php _e('site_title'); ?>:</label>
						<input type="text" id="site_title" name="site_title" value="<?php echo hsc($appSettings['site_title']); ?>" required>
					</div>
					<div class="form-group">
						<label for="site_description"><?php _e('site_description'); ?>:</label>
						<textarea id="site_description" name="site_description" rows="3"><?php echo hsc($appSettings['site_description']); ?></textarea>
					</div>
					
					<div class="settings-section">
						<div class="form-group">
							<label for="footer_text"><?php _e('footer_text'); ?>:</label>
							<input type="text" id="footer_text" name="settings[footer_text]" value="<?php echo hsc($appSettings['footer_text'] ?? ''); ?>" class="form-control">
							<p class="help-text"><?php _e('footer_text_help'); ?></p>
						</div>
						<div class="form-group">
							<label for="active_language"><?php _e('active_language'); ?></label>
							<div class="lang-row" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
								<select name="active_language" id="active_language" class="form-control" style="flex:1;min-width:220px;">
									<?php
									$frontLangs   = lang_available_for_scope('front');
									$currentLang  = $appSettings['active_language'] ?? 'en';
									foreach ($frontLangs as $locale => $label):
									?>
									<option value="<?php echo hsc($locale); ?>" <?php echo $locale === $currentLang ? 'selected' : ''; ?>>
										<?php echo hsc($label); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<a href="index.php?action=translations&scope=front" class="btn btn-outline">
									<?php echo admin_icon('globe'); ?> <?php _e('translations_new_btn'); ?>
								</a>
							</div>
							<p class="help-text"><?php _e('lang_help'); ?></p>
						</div>
						<div class="form-group">
							<label for="admin_language"><?php _e('admin_language'); ?></label>
							<div class="lang-row" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
								<select name="admin_language" id="admin_language" class="form-control" style="flex:1;min-width:220px;">
									<?php
									$adminLangs       = lang_available_for_scope('admin');
									$currentAdminLang = $appSettings['admin_language'] ?? $currentLang;
									foreach ($adminLangs as $locale => $label):
									?>
									<option value="<?php echo hsc($locale); ?>" <?php echo $locale === $currentAdminLang ? 'selected' : ''; ?>>
										<?php echo hsc($label); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<a href="index.php?action=translations&scope=admin" class="btn btn-outline">
									<?php echo admin_icon('globe'); ?> <?php _e('translations_new_btn'); ?>
								</a>
							</div>
							<p class="help-text"><?php _e('admin_language_help'); ?></p>
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
										<input type="text" name="settings[footer_social_links][<?php echo $index; ?>][url]" value="<?php echo hsc($link['url'] ?? ''); ?>" placeholder="URL" class="form-control">
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
						<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
							<button type="button" class="btn btn-danger" id="clear-cache-confirm-btn">
								<?php echo admin_icon('update', 'style="vertical-align:-2px;margin-right:5px;"', 14); ?>
								<?php _e('cache_clear_btn'); ?>
							</button>
							<button type="button" class="btn btn-outline" id="clear-admin-cache-confirm-btn">
								<?php echo admin_icon('update', 'style="vertical-align:-2px;margin-right:5px;"', 14); ?>
								<?php _e('admin_cache_clear_btn', 'Clear admin cache'); ?>
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
							<option value="<?php echo hsc($pageSlug); ?>" <?php echo $pageSlug === $appSettings['homepage_page_id'] ? 'selected' : ''; ?>>
								<?php echo hsc($page['title']); ?>
							</option>
							<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>
					
					<h4><?php _e('pagination_settings'); ?></h4>
					<div class="form-group" style="margin-top:14px;">
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
							<input type="checkbox" name="show_site_title_in_header" <?php echo $appSettings['show_site_title_in_header'] ? 'checked' : ''; ?>>
							<?php _e('show_site_title_in_header'); ?>
						</label><br>
						<label class="checkbox-label">
							<input type="checkbox" name="show_breadcrumbs" <?php echo !empty($appSettings['show_breadcrumbs']) ? 'checked' : ''; ?>>
							<?php _e('show_breadcrumbs'); ?>
						</label><br>
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
				<div class="site-settings-section">
					<h3><?php _e('type_labels_title'); ?></h3>
					<p class="help-text"><?php _e('type_labels_help'); ?></p>
					<div class="form-group">
						<?php foreach (['article', 'page', 'project'] as $_svType): ?>
						<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px;">
							<div>
								<label for="type_label_<?php echo $_svType; ?>_singular"><?php echo hsc(sl_type_label($_svType, false)); ?> — <?php _e('type_labels_singular'); ?></label>
								<input type="text" id="type_label_<?php echo $_svType; ?>_singular" name="type_label_<?php echo $_svType; ?>_singular"
									   value="<?php echo hsc($appSettings['type_labels'][$_svType]['singular'] ?? ''); ?>"
									   placeholder="<?php echo hsc(__t($_svType, ucfirst($_svType))); ?>">
							</div>
							<div>
								<label for="type_label_<?php echo $_svType; ?>_plural"><?php _e('type_labels_plural'); ?></label>
								<input type="text" id="type_label_<?php echo $_svType; ?>_plural" name="type_label_<?php echo $_svType; ?>_plural"
									   value="<?php echo hsc($appSettings['type_labels'][$_svType]['plural'] ?? ''); ?>"
									   placeholder="<?php echo hsc(__t($_svType . 's', ucfirst($_svType) . 's')); ?>">
							</div>
						</div>
						<?php endforeach; ?>
						<p class="help-text"><?php _e('type_labels_url_warning'); ?></p>
					</div>
				</div>

			</div>

			<!-- ══════════════════════ WRITING TAB ══════════════════════ -->
			<div id="writing-tab" class="tab-content" <?php echo $activeTab !== 'writing' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php _e('settings_tab_writing'); ?></h3>
					<div class="form-group">
						<label for="default_editor"><?php echo admin_icon('writing'); ?> <?php _e('default_editor_label'); ?>:</label>
						<?php $currentEditor = ($appSettings['default_editor'] ?? 'html') === 'markdown' ? 'markdown' : 'html'; ?>
						<select id="default_editor" name="default_editor">
							<option value="html" <?php echo $currentEditor === 'html' ? 'selected' : ''; ?>>WYSIWYG</option>
							<option value="markdown" <?php echo $currentEditor === 'markdown' ? 'selected' : ''; ?>>Markdown</option>
						</select>
						<p class="help-text"><?php _e('default_editor_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="date_format"><?php echo admin_icon('calendar'); ?> <?php _e('date_format_label'); ?>:</label>
						<select id="date_format" name="date_format">
							<?php
							$dateFormats = [
								'Y-m-d'  => 'YYYY-MM-DD',
								'd/m/Y'  => 'DD/MM/YYYY',
								'm/d/Y'  => 'MM/DD/YYYY',
								'd-m-Y'  => 'DD-MM-YYYY',
								'd.m.Y'  => 'DD.MM.YYYY',
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
							<optgroup label="<?php echo hsc($region); ?>">
								<?php foreach ($zones as $tz):
									$offset  = (new DateTimeZone($tz))->getOffset(new DateTime('now', new DateTimeZone('UTC')));
									$sign    = $offset >= 0 ? '+' : '-';
									$abs     = abs($offset);
									$label   = sprintf('(UTC%s%02d:%02d) %s', $sign, floor($abs / 3600), ($abs % 3600) / 60, str_replace('_', ' ', $tz));
								?>
								<option value="<?php echo hsc($tz); ?>" <?php echo $tz === $currentTz ? 'selected' : ''; ?>>
									<?php echo hsc($label); ?>
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
							   value="<?php echo hsc($appSettings['home_meta_title'] ?? ''); ?>"
							   placeholder="<?php echo hsc($appSettings['site_title'] ?? ''); ?>">
						<p class="help-text"><?php _e('seo_homepage_meta_title_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="home_meta_description"><?php _e('default_meta_description_label'); ?>:</label>
						<textarea id="home_meta_description" name="home_meta_description" rows="3"
								  placeholder="<?php echo hsc($appSettings['site_description'] ?? ''); ?>"><?php echo hsc($appSettings['home_meta_description'] ?? ''); ?></textarea>
						<p class="help-text"><?php _e('seo_homepage_meta_desc_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="home_meta_keywords"><?php _e('meta_keywords_label'); ?>:</label>
						<input type="text" id="home_meta_keywords" name="home_meta_keywords"
							   value="<?php echo hsc($appSettings['home_meta_keywords'] ?? ''); ?>"
							   placeholder="keyword1, keyword2, keyword3">
						<p class="help-text"><?php _e('meta_keywords_help'); ?></p>
					</div>
					<h4><?php _e('seo_og_section'); ?></h4>
					<div class="form-group">
						<label for="home_og_title"><?php _e('og_title_label'); ?>:</label>
						<input type="text" id="home_og_title" name="home_og_title"
							   value="<?php echo hsc($appSettings['home_og_title'] ?? ''); ?>"
							   placeholder="<?php echo hsc($appSettings['site_title'] ?? ''); ?>">
					</div>
					<div class="form-group">
						<label for="home_og_description"><?php _e('og_description_label'); ?>:</label>
						<textarea id="home_og_description" name="home_og_description" rows="3"
								  placeholder="<?php echo hsc($appSettings['site_description'] ?? ''); ?>"><?php echo hsc($appSettings['home_og_description'] ?? ''); ?></textarea>
					</div>
					<div class="form-group">
						<label><?php _e('og_image_label'); ?>:</label>
						<?php _sv_image_picker('home_og_image', $appSettings['home_og_image'] ?? ''); ?>
						<p class="help-text"><?php _e('seo_og_image_help'); ?></p>
					</div>
				</div>
				<div class="site-settings-section">
					<h3><?php _e('seo_settings'); ?></h3>
						<label class="checkbox-label">
							<input type="checkbox" name="enable_seo" <?php echo $appSettings['enable_seo'] ? 'checked' : ''; ?>>
							<?php _e('enable_seo'); ?>
						</label>
					<p class="help-text"><?php _e('seo_help_text'); ?></p>
					<div class="form-group">
						<label for="default_meta_title"><?php _e('default_meta_title_label'); ?>:</label>
						<input type="text" id="default_meta_title" name="default_meta_title" value="<?php echo hsc($appSettings['default_meta_title']); ?>">
						<p class="help-text"><?php _e('meta_title_vars'); ?></p>
					</div>
					<div class="form-group">
						<label for="default_meta_description"><?php _e('default_meta_description_label'); ?>:</label>
						<textarea id="default_meta_description" name="default_meta_description" rows="2"><?php echo hsc($appSettings['default_meta_description']); ?></textarea>
						<p class="help-text"><?php _e('meta_description_vars'); ?></p>
					</div>
					<div class="form-group">
						<label for="canonical_host"><?php _e('canonical_host_label'); ?></label>
						<input type="text" id="canonical_host" name="canonical_host"
							value="<?php echo hsc($appSettings['canonical_host'] ?? ''); ?>"
							placeholder="<?php echo hsc(__t('canonical_host_placeholder')); ?>">
						<p class="help-text"><?php _e('canonical_host_help'); ?></p>
					</div>
					<h3>Schema.org JSON-LD</h3>
					<div class="form-group">
						<label for="schema_author_name"><?php _e('schema_author_name_label'); ?></label>
						<input type="text" id="schema_author_name" name="schema_author_name"
							value="<?php echo hsc($appSettings['schema_author_name'] ?? ''); ?>"
							placeholder="<?php echo hsc(__t('schema_author_name_placeholder')); ?>">
						<p class="help-text"><?php _e('schema_author_name_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="schema_publisher_type"><?php _e('schema_publisher_type_label'); ?></label>
						<select id="schema_publisher_type" name="schema_publisher_type">
							<option value="Person" <?php echo ($appSettings['schema_publisher_type'] ?? 'Person') === 'Person' ? 'selected' : ''; ?>><?php _e('schema_publisher_person'); ?></option>
							<option value="Organization" <?php echo ($appSettings['schema_publisher_type'] ?? '') === 'Organization' ? 'selected' : ''; ?>><?php _e('schema_publisher_organization'); ?></option>
						</select>
						<p class="help-text"><?php _e('schema_publisher_type_help'); ?></p>
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
						echo hsc(file_exists($_robotsFile) ? file_get_contents($_robotsFile) : '');
						?></textarea>
					</div>
				</div>
				<div class="site-settings-section">
					<h3><?php echo admin_icon('warning'); ?> <?php _e('htaccess_rules_title'); ?></h3>
					<div class="update-notice"><?php _e('htaccess_rules_warning'); ?></div>
					<div class="form-group">
						<p class="help-text"><?php _e('htaccess_rules_help'); ?></p>
						<textarea id="htaccess_rules" name="htaccess_rules" form="htaccess-rules-form" rows="8"
							style="font-family: monospace; font-size: 0.85rem;"
						><?php echo hsc(sl_htaccess_load_custom()); ?></textarea>
					</div>
					<div class="form-group" style="display:flex; gap:10px; align-items:center;">
						<button type="submit" form="htaccess-rules-form" name="htaccess_rules_save" value="1" class="btn btn-primary">
							<?php _e('htaccess_rules_save_btn'); ?>
						</button>
						<?php if (sl_htaccess_latest_backup() !== null): ?>
						<button type="submit" form="htaccess-restore-form" name="htaccess_restore" value="1" class="btn btn-outline"
							onclick="return confirm('<?php echo hsc(__t('htaccess_restore_confirm')); ?>');">
							<?php _e('htaccess_restore_btn'); ?>
						</button>
						<?php endif; ?>
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
						<input style="width:90%; padding:0;" type="range" id="image_quality" name="image_quality" value="<?php echo $appSettings['image_quality'] ?? 85; ?>" min="1" max="100">
						<span style="color: var(--primary); font-size: 1.2em; font-weight: 500; padding:3px; border:1px solid var(--border); border-radius: var(--radius-sm);" id="quality_value"><?php echo $appSettings['image_quality'] ?? 85; ?></span>
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
					<p class="help-text" style="margin-bottom: 16px;"><?php _e('batch_optimizer_desc'); ?></p>
					<div class="form-group">
						<a href="batch-optimize.php" class="btn btn-outline"><?php echo admin_icon('compress'); ?> <?php _e('image_optimizer'); ?></a>
					</div>
				</div>
			</div>

			<!-- ══════════════════════ CONTACT TAB ══════════════════════ -->
			<div id="contact-tab" class="tab-content" <?php echo $activeTab !== 'contact' ? 'style="display: none;"' : ''; ?>>

				<div class="site-settings-section">
					<h3><?php echo admin_icon('contact'); ?> <?php _e('contact_form_settings'); ?></h3>
					<p class="help-text" style="margin-bottom:16px;"><?php _e('contact_form_help'); ?></p>
					<div class="form-group">
						<label for="contact_email"><?php _e('contact_email_to'); ?> *</label>
						<input type="email" id="contact_email" name="contact_email"
							value="<?php echo hsc($appSettings['contact_email'] ?? ''); ?>"
							placeholder="you@example.com">
					</div>
					<div class="form-group">
						<label for="contact_subject"><?php _e('contact_subject'); ?></label>
						<input type="text" id="contact_subject" name="contact_subject"
							value="<?php echo hsc($appSettings['contact_subject'] ?? 'New message from {name}'); ?>">
						<p class="help-text"><?php _e('contact_subject_help'); ?></p>
					</div>
					<div class="form-group">
						<label for="contact_success_message"><?php _e('contact_success_msg'); ?></label>
						<input type="text" id="contact_success_message" name="contact_success_message"
							value="<?php echo hsc($appSettings['contact_success_message'] ?? ''); ?>"
							placeholder="<?php _e('contact_success_default'); ?>">
					</div>
					<div class="form-group">
						<label for="contact_error_message"><?php _e('contact_error_msg'); ?></label>
						<input type="text" id="contact_error_message" name="contact_error_message"
							value="<?php echo hsc($appSettings['contact_error_message'] ?? ''); ?>"
							placeholder="<?php _e('contact_error_default'); ?>">
					</div>
				</div>

				<div class="site-settings-section">
					<h3><?php echo admin_icon('robot'); ?> <?php _e('hcaptcha_section'); ?></h3>
					<p class="help-text" style="margin-bottom:16px;"><?php _e('hcaptcha_help'); ?></p>
					<div class="form-group">
						<label for="hcaptcha_site_key"><?php _e('hcaptcha_site_key'); ?></label>
						<input type="text" id="hcaptcha_site_key" name="hcaptcha_site_key"
							value="<?php echo hsc($appSettings['hcaptcha_site_key'] ?? ''); ?>"
							placeholder="10000000-ffff-ffff-ffff-000000000001"
							autocomplete="off">
					</div>
					<div class="form-group">
						<label for="hcaptcha_secret_key"><?php _e('hcaptcha_secret_key'); ?></label>
						<input type="password" id="hcaptcha_secret_key" name="hcaptcha_secret_key"
							value="<?php echo hsc($appSettings['hcaptcha_secret_key'] ?? ''); ?>"
							autocomplete="off">
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
						<li><code><?php echo hsc($tKey); ?>.php</code> &mdash; <?php echo hsc($tName); ?></li>
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
				<div class="site-settings-section" id="cf-section-<?php echo $cfType; ?>">
					<h3><?php echo hsc($cfLabels[$cfType]); ?></h3>
					<div class="form-group">
					<div class="cf-fields-list" id="cf-list-<?php echo $cfType; ?>" data-type="<?php echo $cfType; ?>">
						<?php if (empty($fields)): ?>
						<p class="cf-empty help-text"><?php _e('cf_no_fields'); ?></p>
						<?php endif; ?>
						<?php foreach ($fields as $fi => $field):
							$fKey      = hsc($field['key']      ?? '');
							$fLabel    = hsc($field['label']    ?? '');
							$fType     = $field['type']     ?? 'text';
							$fRequired = !empty($field['required']);
							$fOptions  = hsc($field['options']  ?? '');
							$fInput    = "custom_fields_schema[{$cfType}][{$fi}]";
						?>
						<div class="cf-field-row" data-index="<?php echo $fi; ?>">
						 <div class="cf-field-inputs">
						  <div class="cf-col">
						   <label><?php _e('cf_field_label'); ?></label>
						   <input type="text" name="<?php echo $fInput; ?>[label]" value="<?php echo $fLabel; ?>" placeholder="<?php echo hsc(__t('cf_field_label_ph')); ?>">
						  </div>
						  <div class="cf-col">
						   <label><?php _e('cf_field_key'); ?></label>
						   <input type="text" name="<?php echo $fInput; ?>[key]" value="<?php echo $fKey; ?>" placeholder="<?php echo hsc(__t('cf_field_key_ph')); ?>" pattern="[a-z0-9\-_]+">
						  </div>
						<div class="cf-col">
						<label><?php _e('cf_field_type'); ?></label>
						<select name="<?php echo $fInput; ?>[type]" class="cf-type-select">
						<?php foreach ($fieldTypes as $ftVal => $ftLabel): ?>
						<option value="<?php echo $ftVal; ?>" <?php echo $fType === $ftVal ? 'selected' : ''; ?>><?php echo hsc($ftLabel); ?></option>
						<?php endforeach; ?>
						</select>
						</div>
						<div class="cf-col cf-col-options" style="<?php echo $fType !== 'select' ? 'display:none' : ''; ?>">
						<label><?php _e('cf_field_options'); ?></label>
						<input type="text" name="<?php echo $fInput; ?>[options]" value="<?php echo $fOptions; ?>" placeholder="<?php echo hsc(__t('cf_field_options_ph')); ?>">
						</div>
						<div class="cf-col cf-col-required">
						<label class="checkbox-label">
						<input type="checkbox" name="<?php echo $fInput; ?>[required]" value="1" <?php echo $fRequired ? 'checked' : ''; ?>>
						<?php _e('cf_field_required'); ?>
						</label>
						</div>
						</div>
						<button type="button" class="btn btn-danger btn-sm cf-delete-btn" title="<?php echo hsc(__t('cf_delete_field')); ?>">&#x2715;</button>
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
		<form id="clear-cache-form" method="post" action="index.php?action=settings">
			<input type="hidden" name="clear_cache" value="1">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
		</form>
		<form id="clear-admin-cache-form" method="post" action="index.php?action=settings">
			<input type="hidden" name="clear_admin_cache" value="1">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
		</form>

		<form id="htaccess-rules-form" method="post" action="index.php?action=settings">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
		</form>
		<form id="htaccess-restore-form" method="post" action="index.php?action=settings">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
		</form>

		<!-- Form upload thème : déclaré ici, champs liés via form="theme-upload-form" -->
		<form id="theme-upload-form"
			  method="post"
			  action="theme-upload.php"
			  enctype="multipart/form-data"
			  style="display:none;">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
		</form>

<script type="application/json" id="settings-view-data"><?php echo json_encode([
	'cfI18n' => [
		'label'     => __t('cf_field_label'),
		'labelPh'   => __t('cf_field_label_ph'),
		'key'       => __t('cf_field_key'),
		'keyPh'     => __t('cf_field_key_ph'),
		'type'      => __t('cf_field_type'),
		'options'   => __t('cf_field_options'),
		'optionsPh' => __t('cf_field_options_ph'),
		'required'  => __t('cf_field_required'),
		'delete'    => __t('cf_delete_field'),
		'empty'     => __t('cf_no_fields'),
		'types'     => [
			'text'     => __t('cf_type_text'),
			'textarea' => __t('cf_type_textarea'),
			'number'   => __t('cf_type_number'),
			'url'      => __t('cf_type_url'),
			'checkbox' => __t('cf_type_checkbox'),
			'select'   => __t('cf_type_select'),
		],
	],
	'initialSocialLinkIndex' => count($socialLinks),
	'loadingFiles'           => __t('loading_files'),
	'sipNoImages'            => __t('sip_no_images'),
	'sipModalTitle'          => __t('sip_select_modal_title'),
	'home'                   => __t('home'),
	'cacheClearConfirm'      => __t('cache_clear_confirm'),
	'cacheClearBtn'          => __t('cache_clear_btn'),
	'adminCacheClearConfirm' => __t('admin_cache_clear_confirm', 'Clear the admin cache? This will force a fresh check for CMS and extension updates.'),
	'adminCacheClearBtn'     => __t('admin_cache_clear_btn', 'Clear admin cache'),
], JSON_HEX_TAG); ?></script>

<script src="assets/js/settings-view.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/settings-view.js'); ?>" defer></script>

	<style>
	.sip-wrapper { display:flex; flex-direction:column; gap:10px; }
	.sip-preview { display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--surface-2); border-radius:var(--radius-sm); border:1px solid var(--border); }
	.sip-preview-img { max-width:160px; max-height:70px; object-fit:contain; border-radius:var(--radius-sm); }
	.sip-controls { display:flex; gap:8px; flex-wrap:wrap; }
	.sip-upload-label { cursor:pointer; overflow: hidden; line-height: normal; }
	.sip-upload-input { padding: 0; margin: 0; }
	</style>
