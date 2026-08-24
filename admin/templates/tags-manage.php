<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

// Build tags index with count + linked items list
$tags = [];
$_tagStore = $data['tags'] ?? [];

foreach (['article', 'project', 'page'] as $_tagCt) {
	if (!isset($data[$_tagCt])) continue;
	foreach ($data[$_tagCt] as $idx => $item) {
		if (!isset($item['tags']) || !is_array($item['tags'])) continue;
		foreach ($item['tags'] as $tagRaw) {
			$slug = sanitizeSlug($tagRaw);
			if (!$slug) continue;
			if (!isset($tags[$slug])) {
				$tags[$slug] = ['name' => $_tagStore[$slug]['name'] ?? $tagRaw, 'count' => 0, 'items' => []];
			}
			$tags[$slug]['count']++;
			$tags[$slug]['items'][] = ['title' => $item['title'], 'type' => $_tagCt, 'index' => $idx];
		}
	}
}

// Add tags from store that have no content (orphans)
foreach ($_tagStore as $slug => $tagData) {
	if (!isset($tags[$slug])) {
		$tags[$slug] = ['name' => $tagData['name'], 'count' => 0, 'items' => []];
	}
}

$orphanCount = count(array_filter($tags, fn($t) => $t['count'] === 0));

// Alphabetically sorted copy for the merge dropdowns (table itself has its own sortable columns)
$tagsAlpha = $tags;
uasort($tagsAlpha, fn($a, $b) => strcasecmp($a['name'], $b['name']));
?>

<div class="sitemap-content">
	<div class="site-settings-section">
	<!-- Add New Tag Form -->
	<h3 style="margin-top:0;"><?php _e('add_tag'); ?></h3>
	<form method="post" action="index.php?action=manage_tags">
		<input type="hidden" name="tag_action" value="add">
		<div class="form-group">
			<label for="tag_name"><?php _e('tag_new_name'); ?></label>
			<input type="text" id="tag_name" name="tag_name" required>
			<button class="btn btn-primary" style="margin-top:20px;" type="submit"><?php _e('add_tag_btn'); ?></button>
		</div>
	</form>
	</div>
	<div class="site-settings-section">
	<!-- Merge Tags Form -->
	<?php if (count($tags) >= 2): ?>
		<h3 style="margin-top:0;"><?php _e('merge_tags'); ?></h3>
		<p style="font-size:.85rem; opacity:.75; margin-top:0;"><?php _e('merge_tags_help'); ?></p>
		<form id="merge-tags-submit-form" method="post" action="index.php?action=manage_tags">
			<input type="hidden" name="tag_action" value="merge">
			<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
				<div class="form-group" style="margin:0; padding: 0; flex:1; min-width:160px;">
					<label><?php _e('merge_source'); ?></label>
					<select name="source_slug" style="width:100%;" required>
						<option value=""><?php _e('select_tag'); ?></option>
						<?php foreach ($tagsAlpha as $slug => $tag): ?>
						<option value="<?php echo $slug; ?>"><?php echo htmlspecialchars($tag['name']); ?> (<?php echo $tag['count']; ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="padding-bottom:8px; font-size:1.1rem; opacity:.6;">→</div>
				<div class="form-group" style="margin:0; padding:0; flex:1; min-width:160px;">
					<label><?php _e('merge_target'); ?></label>
					<select name="target_slug" style="width:100%;" required>
						<option value=""><?php _e('select_tag'); ?></option>
						<?php foreach ($tagsAlpha as $slug => $tag): ?>
						<option value="<?php echo $slug; ?>"><?php echo htmlspecialchars($tag['name']); ?> (<?php echo $tag['count']; ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button class="btn btn-danger merge-btn" type="button"
					data-form="merge-tags-submit-form"
					data-confirm="<?php _e('confirm_merge_tags'); ?>"
					style="margin-bottom:1px;"><?php _e('merge'); ?></button>
			</div>
		</form>
	<?php endif; ?>
		<!-- Toolbar: orphan filter + purge -->
		<div class="tag-toolbar" style="display:flex; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap;">
			<?php if ($orphanCount > 0): ?>
			<label  class="checkbox-label" style="display:flex; align-items:center;font-size:.9rem;">
				<input type="checkbox" id="show-orphans-only">
				<?php _e('show_orphans_only'); ?>
				<span class="badge-orphan"><?php echo $orphanCount; ?></span>
			</label>
		
			<form method="post" action="index.php?action=manage_tags" id="purge-tags-form" style="margin:0;">
				<input type="hidden" name="tag_action" value="purge_orphans">
				<button type="button" class="btn btn-danger btn-sm purge-btn"
					data-form="purge-tags-form"
					data-confirm="<?php _e('confirm_purge_orphan_tags'); ?>">
					<?php _e('purge_orphan_tags'); ?> (<?php echo $orphanCount; ?>)
				</button>
			</form>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Existing Tags List -->
<h3 style="margin-top:30px;"><?php _e('existing_tags'); ?></h3>
<?php if (empty($tags)): ?>
	<p><?php _e('no_tags_found_add'); ?></p>
<?php else: ?>
	<div class="table-wrap">	
		<table id="tags-table">
		<thead>
			<tr>
				<th class="sortable" data-sort="name"><?php _e('tag_name'); ?> <span class="sort-icon">↕</span></th>
				<th class="sortable" data-sort="slug"><?php _e('slug'); ?> <span class="sort-icon">↕</span></th>
				<th class="sortable" data-sort="count"><?php _e('item_count'); ?> <span class="sort-icon">↕</span></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($tags as $slug => $tag): ?>
			<tr data-orphan="<?php echo $tag['count'] === 0 ? '1' : '0'; ?>" data-slug="<?php echo hsc($slug); ?>">
				<td>
					<?php echo hsc($tag['name']); ?>
					<?php if ($tag['count'] === 0): ?>
						<span class="badge-orphan" title="<?php _e('orphan_tag_title'); ?>"><?php _e('orphan'); ?></span>
					<?php endif; ?>
				</td>
				<td><code class="slug-display"><?php echo hsc($slug); ?></code></td>
				<td>
					<?php if ($tag['count'] > 0): ?>
						<span class="count-link"
							data-type-filter="<?php
								$types = array_unique(array_column($tag['items'], 'type'));
								echo count($types) === 1 ? $types[0] : 'article';
							?>"
							data-items="<?php echo hsc(json_encode($tag['items'])); ?>">
							<?php echo $tag['count']; ?>
						</span>
					<?php else: ?>
						<span style="opacity:.4">0</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="#" style="margin:0;" class="table-btn edit-btn small edit-tag"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo hsc($tag['name']); ?>"><?php echo admin_icon('writing', '', 13); ?><?php _e('edit'); ?></a>
					<a href="#" style="margin:0;" class="table-btn delete-btn small danger delete-tag"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo hsc($tag['name']); ?>"><?php echo admin_icon('trash', '', 14); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<!-- Edit Tag — bare form, populated and submitted via JS modal -->
	<form id="edit-tag-form" method="post" action="index.php?action=manage_tags" style="display:none">
		<input type="hidden" name="tag_action" value="edit">
		<input type="hidden" id="edit_tag_slug" name="tag_slug">
		<input type="hidden" id="edit_tag_name" name="tag_name">
	</form>
<?php endif; ?>

<!-- Popover for linked items -->
<div id="items-popover" style="display:none; position:fixed; z-index:9999;background:var(--sidebar-bg); color:#fff;border:1px solid var(--border);
	border-radius:var(--radius-sm); padding:10px 14px; min-width:200px; max-width:320px;box-shadow:var(--shadow-lg); font-size:.85rem; pointer-events:none;">
	<div id="items-popover-content"></div>
</div>

<script src="assets/js/tags-manage.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/tags-manage.js'); ?>"></script>