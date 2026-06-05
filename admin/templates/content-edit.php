<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Check if we're restoring a draft
if (isset($_GET['restore']) && $_GET['restore'] == 1 && isset($_SESSION['draft_data'])) {
	$draftData = $_SESSION['draft_data'];

	if ($draftData['type'] === $contentType || $draftData['type'] === $selectedType) {
		$_SESSION['form_data'] = $draftData;
		if (isset($editItem)) {
			$editItem = array_merge($editItem, $draftData);
		}
	}
	unset($_SESSION['draft_data']);
}

// Make sure we have an item to edit
if (!$editItem) {
	echo '<div class="message error">' . __t('item_not_found') . '</div>';
	return;
}
?>
			
			<div class="editor-layout">
				<!-- Main Content Area -->
				<div class="editor-main">
					<form method="post" action="index.php?action=edit&type=<?php echo urlencode($contentType); ?>&index=<?php echo $index; ?>" enctype="multipart/form-data" id="content-form">
						<input type="hidden" name="type" value="<?php echo $contentType; ?>">
						<input type="hidden" name="remove_featured_image" id="remove-featured-image-flag" value="0">
			
						<!-- Title Field -->
						<div class="title-container">
							<input type="text" id="title" name="title" class="title-input" placeholder="<?php _e('add_title'); ?>" value="<?php echo htmlspecialchars($editItem['title']); ?>" required>
						</div>
			
						<!-- Format state synced to topbar switcher via JS (see _initFormatTabs below) -->
						<?php $currentFormat = $editItem['content_format'] ?? 'html'; ?>
						<input type="hidden" id="content-format" name="content_format" form="content-form" value="<?php echo htmlspecialchars($currentFormat); ?>">
			
						<!-- Content Editor -->
						<div class="content-container">
							<textarea id="content" name="content" rows="20" required><?php echo htmlspecialchars($editItem['content'] ?? ''); ?></textarea>
						</div>
			
						<?php if ($contentType === 'article'): ?>
						<!-- Article Summary -->
						<div class="editor-section">
							<div class="form-group">
								<label for="summary"><?php _e('article_summary_label', 'Short summary'); ?></label>
								<textarea id="summary" name="summary" rows="3" placeholder="<?php echo htmlspecialchars(__t('article_summary_placeholder', 'Write a short summary displayed in article listings…')); ?>"><?php echo htmlspecialchars($editItem['summary'] ?? ''); ?></textarea>
								<p class="help-text"><?php _e('article_summary_help', 'Replaces the auto-generated excerpt in article cards. Leave empty to use the content excerpt.'); ?></p>
							</div>
						</div>
						<?php endif; ?>
			
						<?php if ($contentType === 'project'): ?>
						<!-- Project Description -->
						<div class="editor-section">
							<h3><?php _e('project_description'); ?></h3>
							<div class="form-group">
								<textarea id="description" name="description" rows="3" placeholder="<?php _e('project_summary_placeholder'); ?>"><?php echo $editItem['description'] ?? ''; ?></textarea>
								<p class="help-text"><?php _e('project_description_help'); ?></p>
							</div>
						</div>
						<?php endif; ?>
			
						<!-- Named Galleries Section -->
						<div class="editor-section" id="galleries-section">
							<div class="galleries-section-header" style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
								<h3 style="margin:0;">🏞️ <?php _e('galleries'); ?></h3>
								<button type="button" id="add-gallery-block" class="button small">+ <?php _e('new_gallery'); ?></button>
								<span class="help-text" style="margin:0;font-size:11px;"><?php _e('gallery_shortcode_help'); ?></span>
							</div>
							<div id="named-galleries-container">
								<?php
											$editGalleries = $editItem['galleries'] ?? [];
											// Legacy migration
											if (empty($editGalleries) && !empty($editItem['gallery']) && is_array($editItem['gallery'])) {
												$editGalleries = [[
													'label'  => 'Gallery',
													'layout' => $editItem['gallery_layout'] ?? 'grid',
													'images' => $editItem['gallery'],
												]];
											}
											foreach ($editGalleries as $gIdx => $gallery):
												$gLabel  = htmlspecialchars($gallery['label'] ?? __t('gallery_default_label', 'Gallery ' . ($gIdx + 1)));
												$gLayout = $gallery['layout'] ?? 'grid';
												$gImages = $gallery['images'] ?? [];
											?>
								<div class="named-gallery-block" data-gallery-index="<?php echo $gIdx; ?>">
									<div class="named-gallery-header">
										<input type="text" name="galleries[<?php echo $gIdx; ?>][label]" class="gallery-label-input" value="<?php echo $gLabel; ?>" placeholder="<?php _e('gallery_name_placeholder'); ?>">
										<select name="galleries[<?php echo $gIdx; ?>][layout]" class="gallery-layout-select">
											<option value="grid" <?php echo $gLayout === 'grid'      ? 'selected' : ''; ?>><?php _e('layout_grid'); ?></option>
											<option value="masonry" <?php echo $gLayout === 'masonry'   ? 'selected' : ''; ?>><?php _e('layout_masonry'); ?></option>
											<option value="justified" <?php echo $gLayout === 'justified' ? 'selected' : ''; ?>><?php _e('layout_justified'); ?></option>
											<option value="carousel" <?php echo $gLayout === 'carousel'  ? 'selected' : ''; ?>><?php _e('layout_carousel'); ?></option>
										</select>
										<button type="button" class="remove-gallery-block button small danger">✕ <?php _e('delete'); ?></button>
									</div>
									<div class="gallery-shortcode-bar">
										<code class="shortcode-display">[gallery id="<?php echo $gIdx; ?>"]</code>
										<button type="button" class="copy-shortcode-btn button small" data-shortcode='[gallery id="<?php echo $gIdx; ?>"]'><?php _e('copy'); ?></button>
										<button type="button" class="insert-shortcode-btn button small" data-gallery-id="<?php echo $gIdx; ?>">↳ <?php _e('insert_into_editor'); ?></button>
									</div>
									<div class="named-gallery-items gallery-items" data-gallery-index="<?php echo $gIdx; ?>">
										<?php foreach ($gImages as $imgIdx => $image):
														$imgSrc = $image['src'];
														$imgUrl = (strpos($imgSrc, 'files/') === 0) ? '../' . $imgSrc : '../files/' . $imgSrc;
													?>
										<div class="gallery-item" data-index="<?php echo $imgIdx; ?>">
											<img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php _e('galleries'); ?>">
											<div class="gallery-item-controls">
												<input type="hidden" name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][src]" value="<?php echo htmlspecialchars($imgSrc); ?>">
												<input type="text" name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][caption]" value="<?php echo htmlspecialchars(admin_decode_html($image['caption'] ?? '')); ?>" placeholder="<?php _e('caption'); ?>">
												<input type="text" name="galleries[<?php echo $gIdx; ?>][images][<?php echo $imgIdx; ?>][alt_text]" value="<?php echo htmlspecialchars(admin_decode_html($image['alt_text'] ?? '')); ?>" placeholder="<?php _e('alt_text'); ?>">
												<button type="button" class="remove-named-gallery-item">✕</button>
											</div>
										</div>
										<?php endforeach; ?>
									</div>
									<button type="button" class="add-named-gallery-images button" data-gallery-index="<?php echo $gIdx; ?>">+ <?php _e('add_images'); ?></button>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</form>
				</div>
				<!-- Sidebar -->
				<aside class="editor-sidebar">
					<div class="sidebar-tabs" id="sidebar-tabs">
						<button type="button" class="sidebar-tab active" data-panel="panel-content"><?php _e('content'); ?></button>
						<?php if (!empty($appSettings['enable_seo'])): ?>
						<button type="button" class="sidebar-tab" data-panel="panel-seo">SEO</button>
						<?php endif; ?>
						<?php
							$_cf_schema_edit = $appSettings['custom_fields_schema'][$contentType] ?? [];
							if (!empty($_cf_schema_edit)):
						?>
						<button type="button" class="sidebar-tab" data-panel="panel-cf"><?php _e('cf_tab'); ?></button>
						<?php endif; ?>
					</div>
			
					<!-- TAB 1: Content -->
					<div class="sidebar-tab-panel active" id="panel-content">
						<div class="sidebar-panel">
							<div class="form-group" style="margin-bottom: 15px;">
								<label for="publish_datetime"><span class="label-icon">📅</span> <?php _e('publish_date'); ?></label>
								<?php
							$_edit_stored = $editItem['date'] ?? '';
							$_edit_date   = substr($_edit_stored, 0, 10) ?: date('Y-m-d');
							$_edit_time   = admin_extract_time($_edit_stored) ?: '';
							$_edit_dt_val = $_edit_date . ($_edit_time ? 'T' . $_edit_time : 'T00:00');
							?>
								<input type="datetime-local" id="publish_datetime" name="publish_datetime" form="content-form" value="<?php echo htmlspecialchars($_edit_dt_val); ?>">
								<input type="hidden" id="date" name="date" form="content-form" value="">
								<input type="hidden" id="time" name="time" form="content-form" value="">
							</div>
							<div class="form-group" style="margin-bottom: 15px;">
								<label for="publish_at"><span class="label-icon">🕐</span> <?php _e('schedule_publish'); ?></label>
								<?php if (($editItem['status'] ?? 'published') === 'scheduled'): ?>
								<div class="badge badge-scheduled" style="margin-bottom:8px;">🕐 <?php _e('scheduled_for'); ?> <?php echo htmlspecialchars($editItem['publish_at'] ?? ''); ?></div>
								<?php endif; ?>
								<input type="datetime-local" id="publish_at" name="publish_at" form="content-form" value="<?php echo !empty($editItem['publish_at']) ? htmlspecialchars(str_replace(' ', 'T', $editItem['publish_at'])) : ''; ?>">
								<p class="help-text"><?php _e('schedule_help'); ?></p>
							</div>
							<?php if (!empty($editItem['last_modified'])): ?>
							<div class="form-group" style="margin-bottom: 15px;">
								<label><span class="label-icon">🕒</span> <?php _e('last_modified'); ?></label>
								<div class="meta-value"><?php echo admin_format_date($editItem['last_modified']); ?><?php $lmTime = admin_format_time($editItem['last_modified']); if ($lmTime): ?><span class="meta-time"> - <?php echo htmlspecialchars($lmTime); ?></span><?php endif; ?></div>
							</div>
							<?php endif; ?>
							<div class="form-group">
								<label for="custom_slug">🔗 <?php _e('custom_url_slug'); ?></label>
								<input type="text" id="custom_slug" name="custom_slug" form="content-form" value="<?php echo htmlspecialchars($editItem['custom_slug'] ?? ''); ?>" placeholder="<?php _e('slug_autogenerate_placeholder'); ?>">
								<p class="help-text"><?php _e('slug_help'); ?></p>
							</div>
						</div>
			
						<!-- Categories & Tags -->
						<?php
							$existingCategories = [];
							foreach (['article', 'project', 'page'] as $type) {
							if (isset($data[$type])) foreach ($data[$type] as $item)
							if (!empty($item['category'])) $existingCategories[$item['category']] = true;
								}
							if (isset($data['categories'])) foreach ($data['categories'] as $slug => $cd)
							if (!empty($cd['name'])) $existingCategories[$cd['name']] = true;
							ksort($existingCategories);
							$existingTags = [];
							foreach (['article', 'project', 'page'] as $type) {
							if (isset($data[$type])) foreach ($data[$type] as $item)
							if (!empty($item['tags']) && is_array($item['tags'])) foreach ($item['tags'] as $tag) $existingTags[$tag] = true;
							}
							if (isset($data['tags'])) foreach ($data['tags'] as $slug => $td)
							if (!empty($td['name'])) $existingTags[$td['name']] = true;
							ksort($existingTags);
							?>
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><?php _e('category_and_tags'); ?></h3>
							<div class="panel-content">
								<?php if ($contentType !== 'page'): ?>
								<div class="form-group" style="margin-bottom: 15px;">
									<label><?php _e('category'); ?></label>
									<input type="text" id="category" name="category" form="content-form" placeholder="<?php _e('type_or_select'); ?>" value="<?php echo isset($editItem['category']) ? htmlspecialchars($editItem['category']) : ''; ?>" list="category-datalist">
									<datalist id="category-datalist">
										<option value=""><?php _e('no_category'); ?></option>
										<?php foreach (array_keys($existingCategories) as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"><?php endforeach; ?>
									</datalist>
									<button type="button" class="button browse-btn" onclick="toggleSuggestions('category-suggestions')"><?php _e('browse_all'); ?></button>
								</div>
								<div id="category-suggestions" class="suggestions-box" style="display:none;">
									<span class="item-badge category-badge" data-value=""><?php _e('no_category'); ?></span>
									<?php foreach (array_keys($existingCategories) as $cat): ?><span class="item-badge category-badge" data-value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></span><?php endforeach; ?>
								</div>
								<?php endif; ?>
								<div class="form-group">
									<label><?php _e('tags'); ?></label>
									<input type="text" id="tags" name="tags" form="content-form" placeholder="<?php _e('tags_placeholder'); ?>" value="<?php echo isset($editItem['tags']) && is_array($editItem['tags']) ? htmlspecialchars(implode(', ', $editItem['tags'])) : ''; ?>">
									<button type="button" class="button browse-btn" onclick="toggleSuggestions('tag-suggestions')"><?php _e('browse_all'); ?></button>
								</div>
								<div id="tag-suggestions" class="suggestions-box" style="display:none;">
									<?php foreach (array_keys($existingTags) as $tag): ?><span class="item-badge tag-badge" data-value="<?php echo htmlspecialchars($tag); ?>"><?php echo htmlspecialchars($tag); ?></span><?php endforeach; ?>
								</div>
								<label class="checkbox-label">
									<input type="checkbox" name="show_tags_at_bottom" form="content-form" <?php echo isset($editItem['show_tags_at_bottom']) && $editItem['show_tags_at_bottom'] ? 'checked' : ''; ?>>
									<?php _e('show_tags_at_bottom'); ?>
								</label>
							</div>
						</div>
			
						<?php if ($contentType === 'page'):
							$pageTemplates    = getPageTemplates();
							$selectedTemplate = $editItem['page_template'] ?? '';
							?>
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><span class="toggle-icon">▶</span> 📐 <?php _e('page_template'); ?></h3>
							<div class="panel-content panel-collapsible">
								<div class="form-group">
									<label><?php _e('page_template_label'); ?></label>
									<select id="page_template" name="page_template" form="content-form">
										<?php foreach ($pageTemplates as $tplKey => $tplName): ?>
										<option value="<?php echo htmlspecialchars($tplKey); ?>" <?php echo $selectedTemplate === $tplKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($tplName); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="help-text"><?php _e('page_template_help'); ?></p>
								</div>
							</div>
						</div>
						<?php endif; ?>
			
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><span class="toggle-icon">▼</span> <?php _e('display_options'); ?></h3>
							<div class="panel-content">
								<div class="checkbox-group">
									<label class="checkbox-label"><input type="checkbox" name="show_featured_image" form="content-form" <?php echo isset($editItem['show_featured_image']) && $editItem['show_featured_image'] ? 'checked' : ''; ?>><?php _e('show_featured_image'); ?></label>
									<label class="checkbox-label"><input type="checkbox" name="show_date" form="content-form" <?php echo isset($editItem['show_date']) && $editItem['show_date'] ? 'checked' : ''; ?>><?php _e('show_date'); ?></label>
									<label class="checkbox-label"><input type="checkbox" name="show_title" form="content-form" <?php echo isset($editItem['show_title']) && $editItem['show_title'] ? 'checked' : ''; ?>><?php _e('show_title_on_page'); ?></label>
									<?php if ($contentType === 'article' || $contentType === 'project'): ?>
									<label class="checkbox-label"><input type="checkbox" name="show_on_homepage" form="content-form" <?php echo isset($editItem['show_on_homepage']) && $editItem['show_on_homepage'] ? 'checked' : ''; ?>><?php _e('show_on_homepage'); ?></label>
									<?php endif; ?>
									<label class="checkbox-label">
										<input type="checkbox" name="show_in_menu" form="content-form" id="show_in_menu" <?php echo isset($editItem['show_in_menu']) && $editItem['show_in_menu'] ? 'checked' : ''; ?>>
										<?php _e('show_in_menu'); ?>
									</label>
									<div id="menu_order_field" style="<?php echo isset($editItem['show_in_menu']) && $editItem['show_in_menu'] ? 'display:block;' : 'display:none;'; ?> margin-left:20px;">
										<label><?php _e('menu_order'); ?></label>
										<input type="number" name="menu_order" id="menu_order" form="content-form" value="<?php echo (int)($editItem['menu_order'] ?? 0); ?>" min="0" max="999">
										<p class="help-text"><?php _e('menu_order_help'); ?></p>
									</div>
									<script>
										document.getElementById('show_in_menu').addEventListener('change', function() {
											document.getElementById('menu_order_field').style.display = this.checked ? 'block' : 'none';
										});
									</script>
								</div>
							</div>
						</div>
			
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><?php _e('featured_image'); ?></h3>
							<div class="panel-content">
								<div class="featured-image-container">
									<?php
											$featuredRawPath    = $editItem['image'] ?? '';
								$featuredCleanPath  = strpos($featuredRawPath, 'files/') === 0 ? substr($featuredRawPath, 6) : $featuredRawPath;
							$featuredDisplayUrl = $featuredCleanPath ? '../files/' . ltrim($featuredCleanPath, '/') : '';
							$selectedImagePathValue = $editItem['selected_image_path'] ?? $featuredCleanPath;
							?>
									<?php if ($featuredCleanPath): ?>
									<div id="featured-image-preview" style="margin-top:10px;">
										<img src="<?php echo htmlspecialchars($featuredDisplayUrl); ?>" alt="" class="featured-preview">
										<button type="button" class="remove-featured-image"><?php _e('remove_image'); ?></button>
									</div>
									<?php else: ?>
									<div id="featured-image-preview" style="display:none;margin-top:10px;">
										<img src="" alt="" class="featured-preview">
										<button type="button" class="remove-featured-image"><?php _e('remove_image'); ?></button>
									</div>
									<?php endif; ?>
									<input type="file" id="image" name="image" form="content-form" style="width:100%;">
									<button type="button" id="select-featured-image" class="button" style="margin-top:10px;"><?php _e('select_from_files'); ?></button>
									<p class="help-text"><?php _e('upload_or_select'); ?></p>
									<input type="hidden" id="selected-image-path" name="selected_image_path" form="content-form" value="<?php echo htmlspecialchars($selectedImagePathValue); ?>">
								</div>
							</div>
						</div>
			
						<?php
							// Prepare existing related items for the panel
							$_ri_edit = (is_array($editItem['related_items'] ?? null)) ? $editItem['related_items'] : [];
							$_ri_current_slug = !empty($editItem['custom_slug']) ? $editItem['custom_slug'] : ($editItem['slug'] ?? '');
							?>
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><span class="toggle-icon">▶</span> <?php _e('related_content'); ?></h3>
							<div class="panel-content panel-collapsible panel-content--ri">
								<label class="checkbox-label" style="margin-bottom:10px;">
									<input type="checkbox" name="show_related_items" form="content-form" <?php echo isset($editItem['show_related_items']) && $editItem['show_related_items'] ? 'checked' : ''; ?>>
									<?php _e('show_related_items'); ?>
								</label>
								<div id="ri-selected"></div>
								<div style="position:relative;margin-top:10px;">
									<input type="text" id="ri-search" placeholder="<?php echo htmlspecialchars(__t('related_content_search_ph')); ?>" autocomplete="off" style="width:100%;box-sizing:border-box;">
									<div id="ri-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;
										background:var(--bg,#fff);border:1px solid var(--border,#e2e8f0);border-radius:4px;
										max-height:350px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.12);"></div>
								</div>
								<p class="help-text" style="margin-top:8px;"><?php _e('related_content_help'); ?></p>
								<input type="hidden" id="ri-data" name="related_items" form="content-form" value="<?php echo htmlspecialchars(json_encode($_ri_edit, JSON_UNESCAPED_UNICODE)); ?>">
							</div>
						</div>
			
						<script>
							(function() {
								'use strict';
								var hidden = document.getElementById('ri-data');
								var selDiv = document.getElementById('ri-selected');
								var inp = document.getElementById('ri-search');
								var drop = document.getElementById('ri-dropdown');
								if (!hidden || !selDiv || !inp || !drop) return;
			
								var sel = [];
								var timer;
								var curType = <?php echo json_encode($contentType); ?>;
								var curSlug = <?php echo json_encode($_ri_current_slug); ?>;
								var i18n = {
									empty: <?php echo json_encode(__t('related_content_empty')); ?> ,
									none : <?php echo json_encode(__t('related_content_no_results')); ?> ,
									loading : <?php echo json_encode(__t('related_content_loading')); ?>
								};
			
								function esc(s) {
									return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
								}
			
								function save() {
									hidden.value = JSON.stringify(sel);
								}
			
								function hasSel(type, slug) {
									return sel.some(function(i) {
										return i.type === type && i.slug === slug;
									});
								}
			
								function renderSel() {
									if (!sel.length) {
										selDiv.innerHTML = '<p style="font-size:11px;color:var(--muted,#888);margin:0 0 8px;">' + esc(i18n.empty) + '</p>';
										return;
									}
									selDiv.innerHTML = sel.map(function(it, idx) {
										return '<div style="display:flex;align-items:center;gap:6px;padding:10px 0;border-bottom:1px solid var(--border,#e2e8f0);">' +
											'<span style="font-size:10px;padding:1px 5px;border-radius:10px;background:var(--primary,#3b82f6);color:#fff;text-transform:uppercase;flex-shrink:0;">' + esc(it.type) + '</span>' +
											'<span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + esc(it.title) + '">' + esc(it.title) + '</span>' +
											'<button type="button" data-idx="' + idx + '" style="flex-shrink:0;background:none;border:none;cursor:pointer;color:var(--muted,#888);font-size:18px;line-height:1;padding:2px 4px;" title="Remove">&times;</button>' +
											'</div>';
									}).join('');
									selDiv.querySelectorAll('button[data-idx]').forEach(function(btn) {
										btn.addEventListener('click', function() {
											sel.splice(parseInt(this.dataset.idx, 10), 1);
											save();
											renderSel();
										});
									});
								}
			
								inp.addEventListener('input', function() {
									clearTimeout(timer);
									var q = this.value.trim().toLowerCase();
									if (q.length < 2) {
										drop.style.display = 'none';
										return;
									}
									timer = setTimeout(function() {
										doSearch(q);
									}, 300);
								});
								inp.addEventListener('blur', function() {
									setTimeout(function() {
										drop.style.display = 'none';
									}, 200);
								});
			
								function doSearch(q) {
									var types = ['article', 'page', 'project'];
									var pending = types.length;
									var results = [];
									drop.innerHTML = '<div style="padding:8px;font-size:12px;color:var(--muted,#888);">' + esc(i18n.loading) + '</div>';
									drop.style.display = 'block';
									types.forEach(function(type) {
										fetch('index.php?action=get_content_items&type=' + type)
											.then(function(r) {
												return r.json();
											})
											.then(function(items) {
												items.forEach(function(it) {
													if (type === curType && it.slug === curSlug) return;
													if (hasSel(type, it.slug)) return;
													if (it.title.toLowerCase().indexOf(q) === -1 && it.slug.toLowerCase().indexOf(q) === -1) return;
													results.push({
														type: type,
														slug: it.slug,
														title: it.title
													});
												});
											})
											.catch(function() {})
											.finally(function() {
												pending--;
												if (pending === 0) renderDrop(results);
											});
									});
								}
			
								function renderDrop(results) {
									if (!results.length) {
										drop.innerHTML = '<div style="padding:8px;font-size:12px;color:var(--muted,#888);">' + esc(i18n.none) + '</div>';
										return;
									}
									drop.innerHTML = results.slice(0, 10).map(function(it) {
										return '<div class="ri-res" data-type="' + esc(it.type) + '" data-slug="' + esc(it.slug) + '" data-title="' + esc(it.title) + '"' +
											' style="display:flex;align-items:center;gap:6px;padding:7px 10px;cursor:pointer;border-bottom:1px solid var(--border,#e2e8f0);">' +
											'<span style="font-size:10px;padding:1px 5px;border-radius:10px;background:var(--primary,#3b82f6);color:#fff;text-transform:uppercase;flex-shrink:0;">' + esc(it.type) + '</span>' +
											'<span style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(it.title) + '</span>' +
											'</div>';
									}).join('');
									drop.style.display = 'block';
									drop.querySelectorAll('.ri-res').forEach(function(el) {
										el.addEventListener('mousedown', function(e) {
											e.preventDefault();
											sel.push({
												type: this.dataset.type,
												slug: this.dataset.slug,
												title: this.dataset.title
											});
											save();
											renderSel();
											inp.value = '';
											drop.style.display = 'none';
										});
									});
								}
			
								try {
									sel = JSON.parse(hidden.value) || [];
								} catch (e) {
									sel = [];
								}
								renderSel();
							})();
						</script>
			
					</div><!-- /#panel-content -->
			
					<!-- TAB 2: SEO — only rendered when SEO features are enabled in settings -->
					<?php if (!empty($appSettings['enable_seo'])): ?>
					<div class="sidebar-tab-panel" id="panel-seo">
			
						<div class="sidebar-panel panel-collapsible">
							<h3 class="panel-header"><span class="toggle-icon">▶</span> <?php _e('seo_settings'); ?></h3>
							<div class="panel-content panel-collapsible">
								<div class="form-group">
									<label for="meta_title"><?php _e('meta_title'); ?></label>
									<input type="text" id="meta_title" name="meta_title" form="content-form" maxlength="60" value="<?php echo htmlspecialchars($editItem['meta_title'] ?? ''); ?>">
									<p class="help-text"><?php _e('meta_title_help'); ?></p>
								</div>
								<div class="form-group">
									<label for="meta_description"><?php _e('meta_description'); ?></label>
									<textarea id="meta_description" name="meta_description" form="content-form" rows="3" maxlength="160"><?php echo htmlspecialchars($editItem['meta_description'] ?? ''); ?></textarea>
									<p class="help-text"><?php _e('meta_description_help'); ?></p>
								</div>
								<div class="form-group">
									<label for="meta_keywords"><?php _e('meta_keywords'); ?></label>
									<input type="text" id="meta_keywords" name="meta_keywords" form="content-form" value="<?php echo htmlspecialchars($editItem['meta_keywords'] ?? ''); ?>">
								</div>
								<div class="form-group">
									<label for="canonical_url"><?php _e('canonical_url'); ?></label>
									<input type="url" id="canonical_url" name="canonical_url" form="content-form" value="<?php echo htmlspecialchars($editItem['canonical_url'] ?? ''); ?>">
								</div>
								<div class="form-group">
									<label for="schema_type"><?php _e('schema_type'); ?></label>
									<select id="schema_type" name="schema_type" form="content-form">
										<option value=""><?php _e('none'); ?></option>
										<option value="Article" <?php echo ($editItem['schema_type'] ?? '') === 'Article' ? 'selected' : ''; ?>>Article</option>
										<option value="BlogPosting" <?php echo ($editItem['schema_type'] ?? '') === 'BlogPosting' ? 'selected' : ''; ?>>Blog Posting</option>
										<option value="NewsArticle" <?php echo ($editItem['schema_type'] ?? '') === 'NewsArticle' ? 'selected' : ''; ?>>News Article</option>
										<option value="WebPage" <?php echo ($editItem['schema_type'] ?? '') === 'WebPage' ? 'selected' : ''; ?>>Web Page</option>
									</select>
								</div>
								<div class="seo-subsection">
									<h4><?php _e('social_media'); ?></h4>
									<div class="form-group">
										<label for="og_title"><?php _e('og_title'); ?></label>
										<input type="text" id="og_title" name="og_title" form="content-form" value="<?php echo htmlspecialchars($editItem['og_title'] ?? ''); ?>">
									</div>
									<div class="form-group">
										<label for="og_description"><?php _e('og_description'); ?></label>
										<textarea id="og_description" name="og_description" form="content-form" rows="2"><?php echo htmlspecialchars($editItem['og_description'] ?? ''); ?></textarea>
									</div>
									<div class="form-group" style="text-align:center;">
										<label><?php _e('social_media_image'); ?></label>
										<input type="hidden" id="og_image" name="og_image" form="content-form" value="<?php echo htmlspecialchars($editItem['og_image'] ?? ''); ?>">
										<div id="og-image-preview" style="display:none;margin-top:10px;">
											<?php if (!empty($editItem['og_image'])):
								$ogUrl = strpos($editItem['og_image'], 'files/') === 0 ? '../' . $editItem['og_image'] : '../files/' . $editItem['og_image'];
								?>
											<img src="<?php echo htmlspecialchars($ogUrl); ?>" alt="" style="max-width:100%;border-radius:4px;margin-bottom:10px;">
											<button type="button" class="remove-og-image"><?php _e('remove_image'); ?></button>
											<?php endif; ?>
										</div>
										<button type="button" id="select-og-image" class="button"><?php _e('select_image'); ?></button>
										<p class="help-text"><?php _e('og_image_help'); ?></p>
									</div>
								</div>
								<div class="seo-score-container">
									<h4><?php _e('seo_score'); ?></h4>
									<div class="seo-score-meter">
										<div id="seo-score-fill" class="seo-score-fill"></div>
									</div>
									<div id="seo-score-value">0%</div>
									<ul id="seo-recommendations" class="seo-recommendations">
										<li><?php _e('seo_add_content'); ?></li>
									</ul>
								</div>
								<div class="preview-box">
									<h4><?php _e('google_preview'); ?></h4>
									<div class="preview-title" id="preview_title"><?php _e('preview_title_placeholder'); ?></div>
									<div class="preview-url" id="preview_url" data-site-url="<?php echo htmlspecialchars(admin_site_url()); ?>" data-content-type="<?php echo htmlspecialchars($contentType); ?>"></div>
									<div class="preview-description" id="preview_description"><?php _e('preview_description_placeholder'); ?></div>
								</div>
							</div>
						</div>
					</div><!-- /#panel-seo -->
					<?php endif; // enable_seo ?>
			
					<!-- TAB 3: Custom Fields -->
					<?php if (!empty($_cf_schema_edit)): ?>
					<div class="sidebar-tab-panel" id="panel-cf">
						<div class="sidebar-panel">
							<?php
									$_cf_saved = $editItem['custom_fields'] ?? [];
									foreach ($_cf_schema_edit as $cf):
									$cfKey   = htmlspecialchars($cf['key']   ?? '');
									$cfLabel = htmlspecialchars($cf['label'] ?? $cfKey);
									$cfType  = $cf['type'] ?? 'text';
									$cfReq   = !empty($cf['required']);
									$cfRaw   = $_cf_saved[$cf['key'] ?? ''] ?? '';
									$cfVal   = htmlspecialchars($cfRaw);
									$cfName  = 'custom_fields[' . ($cf['key'] ?? '') . ']';
									?>
							<div class="form-group" style="margin-bottom:14px;">
								<label for="cf_<?php echo $cfKey; ?>"><?php echo $cfLabel; ?><?php if ($cfReq): ?> <span style="color:var(--danger);">*</span><?php endif; ?></label>
								<?php if ($cfType === 'textarea'): ?>
								<textarea id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" rows="3" <?php echo $cfReq ? 'required' : ''; ?>><?php echo $cfVal; ?></textarea>
								<?php elseif ($cfType === 'checkbox'): ?>
								<label class="checkbox-label">
									<input type="checkbox" id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" value="1" <?php echo $cfRaw ? 'checked' : ''; ?>>
									<?php echo $cfLabel; ?>
								</label>
								<?php elseif ($cfType === 'select' && !empty($cf['options'])): ?>
								<select id="cf_<?php echo $cfKey; ?>" name="<?php echo $cfName; ?>" form="content-form" <?php echo $cfReq ? 'required' : ''; ?>>
									<option value=""></option>
									<?php foreach (array_map('trim', explode(',', $cf['options'])) as $opt): ?>
									<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $cfRaw === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
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
					</div><!-- /#panel-cf -->
					<?php endif; // custom fields ?>
			
				</aside>
			</div>

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
							<button type="button" id="open-file-manager" class="button small"><?php _e('gallery_open_fm'); ?></button>
							<button type="button" id="select-all-images" class="button small"><?php _e('select_all'); ?></button>
							<button type="button" id="deselect-all-images" class="button small"><?php _e('deselect_all'); ?></button>
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
							<button type="button" id="add-selected-gallery-items" class="button"><?php _e('add_selected'); ?></button>
							<button type="button" id="clear-gallery-selection" class="button danger"><?php _e('clear'); ?></button>
						</div>
					</div>

				</div>
			</div>
		</main>
	</div>
	<script>
	window.AUTOSAVE_ENABLED_BY_SETTINGS = <?php echo isset($appSettings['autosave_enabled']) && $appSettings['autosave_enabled'] ? 'true' : 'false'; ?>;
	window.CONTENT_FORMAT = <?php echo json_encode($editItem['content_format'] ?? 'html'); ?>;

	// Sync topbar format buttons to the stored content_format
	(function _initFormatTabs() {
		var tabs  = document.querySelectorAll('#topbar-format-switcher .editor-format-tab');
		var input = document.getElementById('content-format');
		if (!tabs.length || !input) return;
		var fmt = input.value || 'html';
		tabs.forEach(function(btn) { btn.classList.toggle('active', btn.dataset.format === fmt); });
		tabs.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var newFmt = this.dataset.format;
				if (newFmt === window.CONTENT_FORMAT) return;
				var msg = newFmt === 'markdown'
					? <?php echo json_encode(__t('editor_switch_to_md_confirm', 'Switch to Markdown editor? Your current content will be preserved as-is — it will NOT be converted.')); ?>
					: <?php echo json_encode(__t('editor_switch_to_html_confirm', 'Switch to WYSIWYG editor? Your current content will be preserved as-is — it will NOT be converted.')); ?>;
				if (!confirm(msg)) return;
				input.value = newFmt;
				window.CONTENT_FORMAT = newFmt;
				tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.format === newFmt); });
				if (window.EditorCommon && window.EditorCommon.switchFormat) {
					window.EditorCommon.switchFormat(newFmt);
				}
			});
		});
	})();

	function toggleSuggestions(id) {
		const element = document.getElementById(id);
		if (element.style.display === 'none') {
			element.style.display = 'flex';
			setTimeout(() => element.classList.add('show'), 10);
		} else {
			element.classList.remove('show');
			setTimeout(() => element.style.display = 'none', 300);
		}
	}

	// ── Schedule publish button label ───────────────────────────────────────────
	(function () {
		var input = document.getElementById('publish_at');
		var label = document.getElementById('publish-btn-label');
		var icon  = document.getElementById('publish-btn-icon');
		var updateLabel   = <?php echo json_encode(__t('update',   'Update')); ?>;
		var scheduleLabel = <?php echo json_encode(__t('schedule', 'Schedule')); ?>;

		function syncBtn() {
			var scheduled = input && input.value && new Date(input.value) > new Date();
			if (label) label.textContent = scheduled ? scheduleLabel : updateLabel;
			if (icon)  icon.textContent  = scheduled ? '🕐' : '✓';
		}

		if (input) input.addEventListener('input', syncBtn);
		syncBtn();
	})();

	document.addEventListener('DOMContentLoaded', function() {
		// Sidebar tabs — always start on Content tab (no localStorage persistence)
		document.querySelectorAll('.sidebar-tab').forEach(tab => {
			tab.addEventListener('click', function() {
				document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
				document.querySelectorAll('.sidebar-tab-panel').forEach(p => p.classList.remove('active'));
				this.classList.add('active');
				document.getElementById(this.dataset.panel)?.classList.add('active');
			});
		});

		document.querySelectorAll('.sidebar-panel').forEach(panel => {
			panel.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
		});

		document.querySelectorAll('.badge-category, .badge-tag').forEach(badge => {
			badge.addEventListener('click', function() {
				const value    = this.dataset.value;
				const targetId = this.classList.contains('badge-category') ? 'category' : 'tags';
				const input    = document.getElementById(targetId);

				this.style.transform = 'scale(0.95)';
				setTimeout(() => this.style.transform = 'scale(1)', 150);

				if (targetId === 'tags' && input.value) {
					input.value += ', ' + value;
				} else {
					input.value = value;
				}

				input.style.backgroundColor = 'var(--primary-light)';
				setTimeout(() => input.style.backgroundColor = '', 500);
			});
		});

		// ── Aperçu live ──────────────────────────────────────────────────────
		const previewBtn = document.getElementById('preview-btn');
		if (previewBtn) {
			previewBtn.addEventListener('click', function () {
				openContentPreview();
			});
		}
	});

	function openContentPreview() {
		const form = document.getElementById('content-form');
		const data = new FormData(form);

		// Inclut les champs liés au formulaire via form="content-form"
		document.querySelectorAll('[form="content-form"]').forEach(function (el) {
			if (el.name && el.type !== 'file' && !data.has(el.name)) {
				if (el.type === 'checkbox') {
					if (el.checked) data.append(el.name, el.value || '1');
				} else {
					data.append(el.name, el.value);
				}
			}
		});

		// Forcer la valeur du textarea rich-text (editor.js peut ne pas l'avoir sync)
		const activeFormat = window.CONTENT_FORMAT || 'html';
		if (activeFormat === 'markdown') {
			const contentHidden = document.getElementById('content-hidden');
			if (contentHidden) data.set('content', contentHidden.value);
		} else {
			const contentArea = document.getElementById('content');
			if (contentArea) data.set('content', contentArea.value);
		}
		data.set('content_format', activeFormat);

		// Images sélectionnées (hidden inputs)
		['selected_image_path', 'featured_image', 'og_image'].forEach(function (name) {
			const el = document.querySelector('input[name="' + name + '"]');
			if (el && !data.has(name)) data.set(name, el.value);
		});

		// Alias : preview.php attend "featured_image", on mappe depuis selected_image_path
		if (!data.get('featured_image') && data.get('selected_image_path')) {
			data.set('featured_image', data.get('selected_image_path'));
		}

		// Création d'un formulaire temporaire invisible → POST target="_blank"
		const tmpForm    = document.createElement('form');
		tmpForm.method   = 'POST';
		tmpForm.action   = 'preview.php';
		tmpForm.target   = '_blank';
		tmpForm.style.display = 'none';

		data.forEach(function (value, key) {
			if (typeof value === 'string') {
				const input = document.createElement('input');
				input.type  = 'hidden';
				input.name  = key;
				input.value = value;
				tmpForm.appendChild(input);
			}
		});

		document.body.appendChild(tmpForm);
		tmpForm.submit();
		document.body.removeChild(tmpForm);
	}
	</script>