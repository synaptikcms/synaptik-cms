<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Set default content type or use the one from URL
$selectedType = isset($_GET['type']) && in_array($_GET['type'], $contentTypes) ? $_GET['type'] : 'article';
?>

			<div class="editor-layout" id="editor-layout">
				<!-- Main Content Area -->
				<div class="editor-main">
					<form method="post" action="index.php?action=add" enctype="multipart/form-data" id="content-form">
						<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
						<input type="hidden" name="type" value="<?php echo $selectedType; ?>">

						<!-- Title Field -->
						<div class="title-container">
							<input type="text" id="title" name="title" class="title-input"
								placeholder="<?php _e('add_title'); ?>"
								value="<?php echo isset($_SESSION['form_data']['title']) ? hsc($_SESSION['form_data']['title']) : ''; ?>"
								required>
						</div>

						<!-- Format state synced to topbar switcher via JS (see _initFormatTabs below) -->
						<input type="hidden" id="content-format" name="content_format" form="content-form" value="html">

				<!-- Content Editor -->
				<div class="content-container">
							<textarea id="content" name="content" rows="20" required><?php echo isset($_SESSION['form_data']['content']) ? hsc($_SESSION['form_data']['content']) : ''; ?></textarea>
						</div>

						<?php if ($selectedType === 'article'): ?>
						<!-- Article Summary -->
						<div class="editor-section">
								<label for="summary" style="margin: 0 0 7px;"><?php _e('article_summary_label', 'Short summary'); ?></label>
								<textarea id="summary" name="summary" rows="3" placeholder="<?php echo hsc(__t('article_summary_placeholder', 'Summary shown in article cards — leave empty to use a content excerpt…')); ?>"><?php echo isset($_SESSION['form_data']['summary']) ? hsc($_SESSION['form_data']['summary']) : ''; ?></textarea>
						</div>
						<?php endif; ?>

						<?php if ($selectedType === 'project'): ?>
						<!-- Project Description -->
						<div class="editor-section">
							<div class="form-group">
								<label for="description"><?php _e('short_description'); ?></label>
								<textarea id="description" name="description" rows="3" placeholder="<?php _e('project_summary_placeholder'); ?>"><?php echo isset($_SESSION['form_data']['description']) ? hsc($_SESSION['form_data']['description']) : ''; ?></textarea>
							</div>
						</div>
						<?php endif; ?>

						<!-- Named Galleries Section -->
						<div class="editor-section" id="galleries-section">
							<div class="galleries-section-header" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
								<h3 style="margin:0;"><?php echo admin_icon('images', 'style="vertical-align:-4px"', 20); ?> <?php _e('galleries'); ?></h3>
								<button type="button" id="add-gallery-block" class="btn btn-primary btn-sm">+ <?php _e('new_gallery'); ?></button>
								<span class="help-text" style="margin:0;font-size:11px;"><?php _e('gallery_shortcode_help'); ?></span>
							</div>
							<div id="named-galleries-container">
								<?php
								$sessionGalleries = $_SESSION['form_data']['galleries'] ?? [];
								foreach ($sessionGalleries as $gIdx => $gallery):
									$gLabel  = hsc($gallery['label'] ?? __t('gallery_default_label', 'Gallery ' . ($gIdx + 1)));
									$gLayout = $gallery['layout'] ?? 'grid';
									$gImages = $gallery['images'] ?? [];
								?>
								<div class="named-gallery-block" data-gallery-index="<?php echo $gIdx; ?>">
									<div class="named-gallery-header">
										<button type="button" class="remove-gallery-block btn btn-danger btn-sm">X</button>
										<select name="galleries[<?php echo $gIdx; ?>][layout]" class="gallery-layout-select">
											<option value="grid"      <?php echo $gLayout === 'grid'      ? 'selected' : ''; ?>><?php _e('layout_grid'); ?></option>
											<option value="masonry"   <?php echo $gLayout === 'masonry'   ? 'selected' : ''; ?>><?php _e('layout_masonry'); ?></option>
											<option value="justified" <?php echo $gLayout === 'justified' ? 'selected' : ''; ?>><?php _e('layout_justified'); ?></option>
											<option value="carousel"  <?php echo $gLayout === 'carousel'  ? 'selected' : ''; ?>><?php _e('layout_carousel'); ?></option>
										</select>
										<input type="text" name="galleries[<?php echo $gIdx; ?>][label]" class="gallery-label-input" value="<?php echo $gLabel; ?>" placeholder="<?php _e('gallery_name_placeholder'); ?>">
									</div>
									<div class="gallery-shortcode-bar">
										<code class="shortcode-display">[gallery id="<?php echo $gIdx; ?>"]</code>
										<button type="button" class="copy-shortcode-btn btn btn-outline btn-sm" data-shortcode='[gallery id="<?php echo $gIdx; ?>"]'><?php _e('copy'); ?></button>
										<button type="button" class="insert-shortcode-btn btn btn-outline btn-sm" data-gallery-id="<?php echo $gIdx; ?>">↳ <?php _e('insert_into_editor'); ?></button>
									</div>
									<div class="named-gallery-items gallery-items" data-gallery-index="<?php echo $gIdx; ?>">
										<?php foreach ($gImages as $imgIdx => $image):
											$imgSrc = $image['src'];
											$imgUrl = (strpos($imgSrc, 'files/') === 0) ? '../' . $imgSrc : '../files/' . $imgSrc;
										?>
										<div class="gallery-item" data-index="<?php echo $imgIdx; ?>">
											<img src="<?php echo hsc($imgUrl); ?>" alt="">
											<div class="gallery-item-controls">
												<input type="hidden" name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][src]"     value="<?php echo hsc($imgSrc); ?>">
												<input type="text"   name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][caption]" value="<?php echo hsc(admin_decode_html($image['caption'] ?? '')); ?>" placeholder="<?php _e('caption'); ?>">
												<input type="text"   name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][alt_text]" value="<?php echo hsc(admin_decode_html($image['alt_text'] ?? '')); ?>" placeholder="<?php _e('alt_text'); ?>">
												<button type="button" class="remove-named-gallery-item">✕</button>
											</div>
										</div>
										<?php endforeach; ?>
									</div>
									<button type="button" class="add-named-gallery-images btn btn-outline btn-sm" data-gallery-index="<?php echo $gIdx; ?>">+ <?php _e('add_images'); ?></button>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</form>
				</div>

				<!-- Sidebar - Metadata -->
				<div class="editor-sidebar-wrap">
					<button type="button" id="sidebar-toggle-handle" class="sidebar-toggle-handle" title="<?php echo hsc(__t('toggle_sidebar', 'Toggle sidebar')); ?>" aria-label="<?php echo hsc(__t('toggle_sidebar', 'Toggle sidebar')); ?>">
						<?php echo admin_icon('panel', '', 25); ?>
					</button>
				<aside class="editor-sidebar">

				<!-- Autosave row -->
				<!-- <div class="editor-autosave-row">
					<?php if (isset($appSettings['autosave_enabled']) && $appSettings['autosave_enabled']): ?>
					<label class="checkbox-label">
						<input type="checkbox" id="toggle-autosave" checked>
						<?php _e('autosave'); ?>
					</label>
					<?php endif; ?>
					<div id="autosave-status" class="autosave-status"></div>
				</div> -->

				<!-- Sidebar tabs -->
				<div class="sidebar-tabs" id="sidebar-tabs">
				<button type="button" class="sidebar-tab active" data-panel="panel-content"><?php _e('content'); ?></button>
				<?php if (!empty($appSettings['enable_seo'])): ?>
				<button type="button" class="sidebar-tab" data-panel="panel-seo">SEO</button>
				<?php endif; ?>
				 <?php $_cf_schema_add = $appSettings['custom_fields_schema'][$selectedType] ?? []; ?>
					<button type="button" class="sidebar-tab" data-panel="panel-cf"><?php _e('cf_tab'); ?></button>
				</div>

				<!-- TAB 1: Content -->
				<div class="sidebar-tab-panel active" id="panel-content">

				<div class="sidebar-panel">
				<div class="form-group" style="margin-bottom: 14px;">
				 <label for="publish_datetime">
				  <span class="label-icon"><?php echo admin_icon('calendar', '', 14); ?></span>
				 <?php _e('publish_date'); ?>
				 </label>
				 <?php
				  $_add_stored = $_SESSION['form_data']['date'] ?? '';
				  $_add_date   = substr($_add_stored, 0, 10) ?: date('Y-m-d');
				 $_add_time   = admin_extract_time($_add_stored) ?: date('H:i');
				$_add_dt_val = $_add_date . 'T' . $_add_time;
				?>
				 <input type="datetime-local" id="publish_datetime" name="publish_datetime" form="content-form"
				  value="<?php echo hsc($_add_dt_val); ?>">
				<input type="hidden" id="date" name="date" form="content-form" value="">
				 <input type="hidden" id="time" name="time" form="content-form" value="">
				 </div>
				 <?php $_add_status = $_SESSION['form_data']['status'] ?? 'published'; ?>
				 <div class="form-group" style="margin-bottom: 14px;">
				 <label for="status-select"><?php _e('status_col'); ?></label>
				 <select id="status-select" name="status" form="content-form">
					<option value="published" <?php echo $_add_status === 'published' ? 'selected' : ''; ?>><?php _e('status_published'); ?></option>
					<option value="scheduled" <?php echo $_add_status === 'scheduled' ? 'selected' : ''; ?>><?php _e('scheduled'); ?></option>
					<option value="draft" <?php echo $_add_status === 'draft' ? 'selected' : ''; ?>><?php _e('status_draft'); ?></option>
					<option value="unpublished" <?php echo $_add_status === 'unpublished' ? 'selected' : ''; ?>><?php _e('status_unpublished'); ?></option>
				 </select>
				 </div>
				 <div class="form-group" id="schedule-field" style="margin-bottom: 14px; display: <?php echo $_add_status === 'scheduled' ? 'block' : 'none'; ?>;">
				 <label for="publish_at">
				  <span class="label-icon"><?php echo admin_icon('clock', '', 14); ?></span>
				 <?php _e('schedule_publish'); ?>
				</label>
				 <input type="datetime-local" id="publish_at" name="publish_at" form="content-form"
				   value="<?php echo isset($_SESSION['form_data']['publish_at']) ? hsc(str_replace(' ', 'T', $_SESSION['form_data']['publish_at'])) : ''; ?>">
							</div>
				  <div class="form-group" style="margin-bottom: 10px;">
				   <label for="custom_slug"><?php _e('custom_url_slug'); ?></label>
				   <input type="text" id="custom_slug" name="custom_slug" form="content-form"
				    value="<?php echo isset($_SESSION['form_data']['custom_slug']) ? hsc($_SESSION['form_data']['custom_slug']) : ''; ?>"
				    placeholder="<?php _e('slug_autogenerate_placeholder'); ?>">
				</div>
				<?php
				$existingCategories = []; // slug => display name
				if (isset($data['categories'])) {
				foreach ($data['categories'] as $slug => $cd) {
				 if (!empty($cd['name'])) $existingCategories[$slug] = $cd['name'];
				  }
				 }
				 // Collect inline categories from items not yet in the store (legacy resilience)
				 foreach (['article', 'project', 'page'] as $type) {
				 	if (!isset($data[$type])) continue;
				 	foreach ($data[$type] as $item) {
				 		if (empty($item['category'])) continue;
				 		$slug = sanitizeSlug($item['category']);
				 		if ($slug && !isset($existingCategories[$slug])) $existingCategories[$slug] = $item['category'];
				 	}
				 }
				 asort($existingCategories); // sort by display name
				 $existingTags = []; // slug => display name
				 if (isset($data['tags'])) {
				 foreach ($data['tags'] as $slug => $td) {
				 if (!empty($td['name'])) $existingTags[$slug] = $td['name'];
				  }
				 }
				 // Collect inline tags from items not yet in the store (legacy resilience)
				  foreach (['article', 'project', 'page'] as $type) {
				 	if (!isset($data[$type])) continue;
				 	foreach ($data[$type] as $item) {
				 		if (empty($item['tags']) || !is_array($item['tags'])) continue;
				 		foreach ($item['tags'] as $tagRaw) {
				 			$slug = sanitizeSlug($tagRaw);
				 			if ($slug && !isset($existingTags[$slug])) $existingTags[$slug] = $tagRaw;
				 		}
				 	}
				 }
				 asort($existingTags); // sort by display name
				?>
				<?php if ($selectedType !== 'page'): ?>
				 <div class="form-group" style="margin-bottom: 14px;">
				   <label><?php _e('category'); ?></label>
				   <?php $_catDisplayVal = isset($_SESSION['form_data']['category']) ? $_SESSION['form_data']['category'] : ''; ?>
				   <div class="ip-picker">
				     <div class="ip-field" id="category-field">
				       <input type="text" id="category-search" class="ip-input" placeholder="<?php _e('type_or_select'); ?>" autocomplete="off">
				     </div>
				     <div id="category-dropdown" class="ip-dropdown"></div>
				   </div>
				   <input type="hidden" id="category-data" name="category" form="content-form" value="<?php echo hsc($_catDisplayVal); ?>">
				   <script type="application/json" id="category-source"><?php echo json_encode(array_values($existingCategories), JSON_UNESCAPED_UNICODE); ?></script>
				 </div>
				 <?php endif; ?>
				<div class="form-group" style="margin-bottom: 8px;">
				  <label><?php _e('tags'); ?></label>
				  <?php
				  $_tagSessionVal = $_SESSION['form_data']['tags'] ?? '';
				  $_tagDisplayVal = is_array($_tagSessionVal) ? implode(', ', $_tagSessionVal) : $_tagSessionVal;
				  ?>
				  <div class="ip-picker">
				    <div class="ip-field" id="tags-field">
				      <input type="text" id="tags-search" class="ip-input" placeholder="<?php _e('type_or_select'); ?>" autocomplete="off">
				    </div>
				    <div id="tags-dropdown" class="ip-dropdown"></div>
				  </div>
				  <input type="hidden" id="tags-data" name="tags" form="content-form" value="<?php echo hsc($_tagDisplayVal); ?>">
				  <script type="application/json" id="tags-source"><?php echo json_encode(array_values($existingTags), JSON_UNESCAPED_UNICODE); ?></script>
				 </div>
				 <label class="checkbox-label">
				  <input type="checkbox" name="show_tags_at_bottom" form="content-form"
				   <?php echo isset($_SESSION['form_data']['show_tags_at_bottom']) && $_SESSION['form_data']['show_tags_at_bottom'] ? 'checked' : ''; ?>>
				 <?php _e('show_tags_at_bottom'); ?>
				</label>
				</div>

				<?php if ($selectedType === 'page'):
				 $pageTemplates = getPageTemplates();
				$selectedTemplate = $_SESSION['form_data']['page_template'] ?? '';
						?>
				<div class="sidebar-panel panel-collapsible collapsed">
				<h3 class="panel-header"><span class="toggle-icon">▶</span> <?php echo admin_icon('ruler', 'style="vertical-align:-2px"', 14); ?> <?php _e('page_template'); ?></h3>
				<div class="panel-content panel-collapsible" style="display:none;opacity:0;max-height:0">
				<div class="form-group">
				<label><?php _e('page_template_label'); ?></label>
				<select name="page_template" form="content-form">
				<?php foreach ($pageTemplates as $tplKey => $tplName): ?>
				 <option value="<?php echo hsc($tplKey); ?>" <?php echo $selectedTemplate === $tplKey ? 'selected' : ''; ?>><?php echo hsc($tplName); ?></option>
				<?php endforeach; ?>
				</select>
				  <p class="help-text"><?php _e('page_template_help'); ?></p>
				</div>
				</div>
				</div>
				<?php endif; ?>

				<!-- Display Options -->
				<div class="sidebar-panel panel-collapsible">
				<h3 class="panel-header"><span class="toggle-icon">▼</span> <?php _e('display_options'); ?></h3>
				<div class="panel-content">
								<div class="checkbox-group">
				  <label class="checkbox-label"><input type="checkbox" name="show_featured_image" form="content-form" <?php echo isset($_SESSION['form_data']['show_featured_image']) && $_SESSION['form_data']['show_featured_image'] ? 'checked' : ''; ?>><?php _e('show_featured_image'); ?></label>
				 <label class="checkbox-label"><input type="checkbox" name="show_date" form="content-form" <?php echo isset($_SESSION['form_data']['show_date']) && $_SESSION['form_data']['show_date'] ? 'checked' : ''; ?>><?php _e('show_date'); ?></label>
				<label class="checkbox-label"><input type="checkbox" name="show_title" form="content-form" <?php echo isset($_SESSION['form_data']['show_title']) && $_SESSION['form_data']['show_title'] ? 'checked' : ''; ?>><?php _e('show_title_on_page'); ?></label>
				 <?php if ($selectedType === 'article' || $selectedType === 'project'): ?>
				  <label class="checkbox-label"><input type="checkbox" name="show_on_homepage" form="content-form" checked><?php _e('show_on_homepage'); ?></label>
				   <?php endif; ?>
				    <label class="checkbox-label">
										<input type="checkbox" name="show_in_menu" form="content-form" id="show_in_menu">
				     <?php _e('show_in_menu'); ?>
				   </label>
				   <div id="menu_order_field" style="display:none; margin-left:20px;">
				    <label><?php _e('menu_order'); ?></label>
				     <input type="number" name="menu_order" id="menu_order" form="content-form" value="0" min="0" max="999">
				     <p class="help-text"><?php _e('menu_order_help'); ?></p>
				   </div>
				  <script src="assets/js/menu-order-toggle.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/menu-order-toggle.js'); ?>"></script>
				 </div>
				 <div class="featured-image-container" style="margin-top:16px;border-top:1px solid var(--border);">
				 <label><?php _e('featured_image'); ?></label>
				<?php
				$restoredFeaturedPath = $_SESSION['form_data']['selected_image_path'] ?? $_SESSION['form_data']['image'] ?? '';
				if (strpos($restoredFeaturedPath, 'files/') === 0) $restoredFeaturedPath = substr($restoredFeaturedPath, 6);
				 $restoredFeaturedUrl = $restoredFeaturedPath ? '../files/' . ltrim($restoredFeaturedPath, '/') : '';
				 ?>
				  <input type="hidden" id="selected-image-path" name="selected_image_path" form="content-form" value="<?php echo hsc($restoredFeaturedPath); ?>">
				   <div id="featured-image-preview" style="display:<?php echo $restoredFeaturedPath ? 'block' : 'none'; ?>;margin-top:10px;">
				     <?php if ($restoredFeaturedPath): ?>
				     <p style="font-size:.7em;color:var(--text-muted);margin-top:0;"><?php _e('selected'); ?>: <?php echo hsc(basename($restoredFeaturedPath)); ?></p>
										<img src="<?php echo hsc($restoredFeaturedUrl); ?>" alt="<?php _e('featured_image'); ?>" class="featured-preview">
				     <?php else: ?>
				     <img src="" alt="<?php _e('featured_image'); ?>" class="featured-preview">
				    <?php endif; ?>
				   <button type="button" class="remove-featured-image"><?php _e('remove_image'); ?></button>
				  </div>
				   <input type="file" id="image" name="image" accept="image/*" style="font-size:12px;width:100%;">
				   <button type="button" id="select-featured-image" class="btn btn-outline btn-sm" style="margin-top:10px;"><?php _e('select_from_files'); ?></button>
				   </div>
				   </div>
				   </div>

			<div class="sidebar-panel panel-collapsible collapsed">
				<h3 class="panel-header"><span class="toggle-icon">▶</span> <?php _e('related_content'); ?></h3>
				   <div class="panel-content panel-collapsible" style="display:none;opacity:0;max-height:0">
				    <label class="checkbox-label" style="margin-bottom:10px;">
						<input type="checkbox" name="show_related_items" form="content-form">
						<?php _e('show_related_items'); ?>
					</label>
				    <div class="ip-picker" style="margin-top:10px;">
						<div class="ip-field" id="ri-field">
							<input type="text" id="ri-search" class="ip-input"
								placeholder="<?php echo hsc(__t('related_content_search_ph')); ?>"
								autocomplete="off">
						</div>
						<div id="ri-dropdown" class="ip-dropdown"></div>
					</div>
					<p class="help-text" style="margin-top:8px;"><?php _e('related_content_help'); ?></p>
					<input type="hidden" id="ri-data" name="related_items" form="content-form" value="[]"
						data-cur-type="<?php echo hsc($selectedType); ?>" data-cur-slug="">
				</div>
				</div>

				<script src="assets/js/item-picker.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/item-picker.js'); ?>"></script>

				</div><!-- /#panel-content -->

				<!-- TAB 2: SEO — only rendered when SEO features are enabled in settings -->
				<?php if (!empty($appSettings['enable_seo'])): ?>
				<div class="sidebar-tab-panel" id="panel-seo">

					<div class="sidebar-panel panel-collapsible">
				<h3 class="panel-header"><span class="toggle-icon">▶</span> <?php _e('seo_settings'); ?></h3>
				<div class="panel-content panel-collapsible">
				<div class="form-group">
				<label for="meta_title"><?php _e('meta_title'); ?></label>
				 <input type="text" id="meta_title" name="meta_title" form="content-form" maxlength="80" placeholder="<?php _e('meta_title_help'); ?>"
				  value="<?php echo isset($_SESSION['form_data']['meta_title']) ? hsc($_SESSION['form_data']['meta_title']) : ''; ?>">
				</div>
				<div class="form-group">
				 <label for="meta_description"><?php _e('meta_description'); ?></label>
				 <textarea style="margin-bottom: 0;" id="meta_description" name="meta_description" form="content-form" rows="3" maxlength="200" placeholder="<?php _e('meta_description_help'); ?>"><?php echo isset($_SESSION['form_data']['meta_description']) ? hsc($_SESSION['form_data']['meta_description']) : ''; ?></textarea>
				</div>
				<div class="form-group">
				 <label for="meta_keywords"><?php _e('meta_keywords'); ?></label>
				 <input type="text" id="meta_keywords" name="meta_keywords" form="content-form"
				 value="<?php echo isset($_SESSION['form_data']['meta_keywords']) ? hsc($_SESSION['form_data']['meta_keywords']) : ''; ?>">
				</div>
				<div class="form-group">
				 <label for="canonical_url"><?php _e('canonical_url'); ?></label>
				 <input type="url" id="canonical_url" name="canonical_url" form="content-form"
				  value="<?php echo isset($_SESSION['form_data']['canonical_url']) ? hsc($_SESSION['form_data']['canonical_url']) : ''; ?>">
				</div>
				<div class="form-group">
				 <label for="schema_type"><?php _e('schema_type'); ?></label>
				  <select id="schema_type" name="schema_type" form="content-form">
				    <option value=""><?php _e('none'); ?></option>
				     <option value="Article" <?php echo (isset($_SESSION['form_data']['schema_type']) && $_SESSION['form_data']['schema_type'] === 'Article') ? 'selected' : ''; ?>>Article</option>
										<option value="BlogPosting" <?php echo (isset($_SESSION['form_data']['schema_type']) && $_SESSION['form_data']['schema_type'] === 'BlogPosting') ? 'selected' : ''; ?>>Blog Posting</option>
				     <option value="NewsArticle" <?php echo (isset($_SESSION['form_data']['schema_type']) && $_SESSION['form_data']['schema_type'] === 'NewsArticle') ? 'selected' : ''; ?>>News Article</option>
				     <option value="WebPage" <?php echo (isset($_SESSION['form_data']['schema_type']) && $_SESSION['form_data']['schema_type'] === 'WebPage') ? 'selected' : ''; ?>>Web Page</option>
				   </select>
				  </div>
				 <div class="seo-subsection">
				  <h4><?php _e('social_media'); ?></h4>
				 <div class="form-group">
				  <label for="og_title"><?php _e('og_title'); ?></label>
				  <input type="text" id="og_title" name="og_title" form="content-form"
				  value="<?php echo isset($_SESSION['form_data']['og_title']) ? hsc($_SESSION['form_data']['og_title']) : ''; ?>">
				 </div>
				 <div class="form-group">
				  <label for="og_description"><?php _e('og_description'); ?></label>
				  <textarea id="og_description" name="og_description" form="content-form" rows="2"><?php echo isset($_SESSION['form_data']['og_description']) ? hsc($_SESSION['form_data']['og_description']) : ''; ?></textarea>
				</div>
				 <div class="form-group" style="text-align:center;">
				 <label for="og_image"><?php _e('social_media_image'); ?></label>
				 <input type="hidden" id="og_image" name="og_image" form="content-form" value="<?php echo hsc($_SESSION['form_data']['og_image'] ?? ''); ?>">
				 <div id="og-image-preview" style="display:none;margin-top:10px;"></div>
				 <button type="button" id="select-og-image" class="btn btn-outline btn-sm"><?php _e('select_image'); ?></button>
				 <p class="help-text"><?php _e('og_image_help'); ?></p>
				</div>
				</div>
				<div class="seo-score-container">
				 <h4><?php _e('seo_score'); ?></h4>
				 <div class="seo-score-meter"><div id="seo-score-fill" class="seo-score-fill"></div></div>
				 <div id="seo-score-value">0%</div>
				  <ul id="seo-recommendations" class="seo-recommendations"><li><?php _e('seo_add_content'); ?></li></ul>
				  </div>
				   <div class="preview-box">
									<h4><?php _e('google_preview'); ?></h4>
				    <div class="preview-title" id="preview_title"><?php _e('preview_title_placeholder'); ?></div>
				    <div class="preview-url" id="preview_url"
				     data-site-url="<?php echo hsc(admin_site_url()); ?>"
				    data-content-type="<?php echo hsc($selectedType); ?>"></div>
				  <div class="preview-description" id="preview_description"><?php _e('preview_description_placeholder'); ?></div>
				 </div>
				 </div>
				</div>
				</div><!-- /#panel-seo -->
				<?php endif; // enable_seo ?>

				<!-- TAB 3: Custom Fields -->
				<div class="sidebar-tab-panel" id="panel-cf">
					<div class="sidebar-panel">
						<div id="cf-quick-add-list">
						<?php if (empty($_cf_schema_add)): ?>
						<p class="help-text"><?php _e('cf_none_for_type'); ?></p>
						<?php endif; ?>
						<?php foreach ($_cf_schema_add as $cf): ?>
						<?php
						$cfKey   = hsc($cf['key']   ?? '');
						$cfLabel = hsc($cf['label'] ?? $cfKey);
						$cfType  = $cf['type'] ?? 'text';
						$cfReq   = !empty($cf['required']);
						$cfVal   = hsc($_SESSION['form_data']['custom_fields'][$cf['key'] ?? ''] ?? '');
						$cfName  = 'custom_fields[' . ($cf['key'] ?? '') . ']';
						?>
						<div class="form-group" style="margin-bottom:8px;">
							<label for="cf_<?php echo $cfKey; ?>"><?php echo $cfLabel; ?><?php if ($cfReq): ?> <span style="color:var(--danger);">*</span><?php endif; ?></label>
							<?php if ($cfType === 'textarea'): ?>
							<textarea id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" rows="3" <?php echo $cfReq ? 'required' : ''; ?>><?php echo $cfVal; ?></textarea>
							<?php elseif ($cfType === 'checkbox'): ?>
							<label class="checkbox-label">
								<input type="checkbox" id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" value="1" <?php echo $cfVal ? 'checked' : ''; ?>>
								<?php echo $cfLabel; ?>
							</label>
							<?php elseif ($cfType === 'select' && !empty($cf['options'])): ?>
							<select id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" <?php echo $cfReq ? 'required' : ''; ?>>
								<option value=""></option>
								<?php foreach (array_map('trim', explode(',', $cf['options'])) as $opt): ?>
								<option value="<?php echo hsc($opt); ?>" <?php echo $cfVal === hsc($opt) ? 'selected' : ''; ?>><?php echo hsc($opt); ?></option>
								<?php endforeach; ?>
							</select>
							<?php elseif ($cfType === 'number'): ?>
							<input type="number" id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" value="<?php echo $cfVal; ?>" <?php echo $cfReq ? 'required' : ''; ?>>
							<?php elseif ($cfType === 'url'): ?>
							<input type="url" id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" value="<?php echo $cfVal; ?>" <?php echo $cfReq ? 'required' : ''; ?>>
							<?php else: // text ?>
							<input type="text" id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" value="<?php echo $cfVal; ?>" <?php echo $cfReq ? 'required' : ''; ?>>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
						</div>
						<button type="button" id="cf-quick-add-btn" class="btn btn-outline btn-sm" data-cf-type="<?php echo hsc($selectedType); ?>" style="margin-top:6px;">+ <?php _e('cf_add_field'); ?></button>
						<div id="cf-quick-add-form" style="display:none;margin-top:10px;">
							<div class="form-group" style="margin-bottom:8px;">
								<label for="cf-quick-add-label"><?php _e('cf_field_label'); ?></label>
								<input type="text" id="cf-quick-add-label" placeholder="<?php echo hsc(__t('cf_field_label_ph')); ?>">
							</div>
							<div class="form-group" style="margin-bottom:10px;">
								<label for="cf-quick-add-type"><?php _e('cf_field_type'); ?></label>
								<select id="cf-quick-add-type">
									<option value="text"><?php _e('cf_type_text'); ?></option>
									<option value="textarea"><?php _e('cf_type_textarea'); ?></option>
									<option value="number"><?php _e('cf_type_number'); ?></option>
									<option value="url"><?php _e('cf_type_url'); ?></option>
									<option value="checkbox"><?php _e('cf_type_checkbox'); ?></option>
								</select>
							</div>
							<button type="button" id="cf-quick-add-submit" class="btn btn-primary btn-sm"><?php _e('cf_add_field'); ?></button>
							<button type="button" id="cf-quick-add-cancel" class="btn btn-outline btn-sm"><?php _e('cancel'); ?></button>
						</div>
						<a href="index.php?action=settings&amp;tab=custom_fields#cf-section-<?php echo hsc($selectedType); ?>" class="btn btn-outline btn-sm" style="margin-top:16px;">+ <?php _e('cf_manage_link'); ?></a>
					</div>
				</div><!-- /#panel-cf -->
				<script src="assets/js/cf-quick-add.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/cf-quick-add.js'); ?>"></script>

				</aside>
				</div><!-- /.editor-sidebar-wrap -->

				<!-- Gallery Modal -->
				<div id="gallery-modal" class="modal gm-modal">
					<div class="modal-content gm-shell">

						<!-- Modal header -->
						<div class="gm-header">
							<h2><?php _e('select_images'); ?></h2>
							<span class="close-gallery-modal gm-close">&times;</span>
						</div>

						<!-- Toolbar: breadcrumbs + action buttons -->
						<div class="gm-toolbar">
							<nav class="breadcrumbs" id="modal-breadcrumbs">
								<a href="#" data-path=""><?php _e('home'); ?></a>
							</nav>
							<div class="gm-toolbar-btns">
								<button type="button" id="open-file-manager" class="btn btn-outline btn-sm"><?php _e('gallery_open_fm'); ?></button>
								<button type="button" id="select-all-images" class="btn btn-outline btn-sm"><?php _e('select_all'); ?></button>
								<button type="button" id="deselect-all-images" class="btn btn-outline btn-sm"><?php _e('deselect_all'); ?></button>
							</div>
						</div>

						<!-- Scrollable file body -->
						<div class="gm-body" id="modal-files">
							<p><?php _e('loading_files'); ?></p>
						</div>

						<!-- Selection footer -->
						<div class="gm-footer" id="gallery-selection-indicator" style="display: none;">
							<span class="gm-count">
								<strong id="gallery-selected-count">0</strong> <?php _e('gallery_n_selected'); ?>
							</span>
							<div class="gm-footer-actions">
								<button type="button" id="add-selected-gallery-items" class="btn btn-primary btn-sm"><?php _e('add_selected'); ?></button>
								<button type="button" id="clear-gallery-selection" class="btn btn-danger btn-sm"><?php _e('clear'); ?></button>
							</div>
						</div>

					</div>
				</div>
			<!-- </main>
		</div> -->
<script type="application/json" id="content-editor-i18n"><?php echo json_encode([
	'publish'      => __t('publish', 'Publish'),
	'schedule'     => __t('schedule', 'Schedule'),
	'save'         => __t('save', 'Save'),
], JSON_HEX_TAG); ?></script>
<script src="assets/js/content-add-editor.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/content-add-editor.js'); ?>"></script>
<?php
if (isset($_SESSION['form_data'])) {
	unset($_SESSION['form_data']);
}
?>