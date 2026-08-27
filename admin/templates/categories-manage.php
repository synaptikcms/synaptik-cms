<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

$categories = [];

foreach (['article', 'project', 'page'] as $ct) {
	if (!isset($data[$ct])) continue;
	foreach ($data[$ct] as $idx => $item) {
		if (empty($item['category'])) continue;
		$slug = sanitizeSlug($item['category']);
		if (!$slug) continue;
		if (!isset($categories[$slug])) {
			$name = isset($data['categories'][$slug]) ? $data['categories'][$slug]['name'] : $item['category'];
			$categories[$slug] = ['name' => $name, 'count' => 0, 'items' => [], 'parent' => ''];
		}
		$categories[$slug]['count']++;
		$categories[$slug]['items'][] = ['title' => $item['title'], 'type' => $ct, 'index' => $idx];
	}
}

// Add categories from the dedicated collection (orphans will have count 0)
if (isset($data['categories'])) {
	foreach ($data['categories'] as $slug => $categoryData) {
		if (!isset($categories[$slug])) {
			$categories[$slug] = ['name' => $categoryData['name'], 'count' => 0, 'items' => [], 'parent' => ''];
		}
		// Always carry the parent value from the authoritative categories store
		$categories[$slug]['parent'] = $categoryData['parent'] ?? '';
	}
}

$orphanCount = count(array_filter($categories, fn($c) => $c['count'] === 0));

function buildCategoryTree(array $categories, string $parentSlug = '', int $depth = 0): array {
	if ($depth >= 3) return []; // Hard limit: 3 sub-levels maximum
	$result = [];
	// Sort siblings at this level alphabetically by name before walking them
	$siblings = array_filter($categories, fn($cat) => ($cat['parent'] ?? '') === $parentSlug);
	uasort($siblings, fn($a, $b) => strcasecmp($a['name'], $b['name']));
	foreach ($siblings as $slug => $cat) {
		$entry = array_merge($cat, ['slug' => $slug, 'depth' => $depth]);
		$result[] = $entry;
		// Recursively add children
		$children = buildCategoryTree($categories, $slug, $depth + 1);
		foreach ($children as $child) {
			$result[] = $child;
		}
	}
	return $result;
}

$categoryTree = buildCategoryTree($categories);
?>

<div class="sitemap-content">
	<div class="site-settings-section">
		<!-- Add New Category Form -->
		<h3 style="margin-top:0;"><?php _e('add_category'); ?></h3>
		<form method="post" action="index.php?action=manage_categories">
			<input type="hidden" name="category_action" value="add">
			<div class="form-group">
				<label for="category_name"><?php _e('cat_new_name'); ?></label>
				<input type="text" id="category_name" name="category_name" required>
			</div>
			<div class="form-group">
				<label for="category_parent"><?php _e('cat_parent'); ?></label>
				<select id="category_parent" name="category_parent">
					<option value=""><?php _e('cat_no_parent'); ?></option>
					<?php foreach ($categoryTree as $cat):
						// Only show categories that can accept children (depth < 2, i.e. not already at level 2)
						if ($cat['depth'] >= 2) continue;
						$prefix = str_repeat('— ', $cat['depth']);
					?>
					<option value="<?php echo htmlspecialchars($cat['slug']); ?>">
						<?php echo $prefix . htmlspecialchars($cat['name']); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<p class="help-text"><?php _e('cat_parent_help'); ?></p>
				<button class="btn btn-primary" type="submit"><?php _e('add_category_btn'); ?></button>
			</div>
		</form>
	</div>
	<div class="site-settings-section">
		<!-- Merge Categories Form -->
		<?php if (count($categories) >= 2): ?>
			<h3 style="margin-top:0;"><?php _e('merge_categories'); ?></h3>
			<p style="font-size:.85rem; opacity:.75; margin-top:0;"><?php _e('merge_cats_help'); ?></p>
			<form id="merge-cats-submit-form" method="post" action="index.php?action=manage_categories">
				<input type="hidden" name="category_action" value="merge">
				<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
					<div class="form-group" style="margin:0; padding:0; flex:1; min-width:160px;">
						<label><?php _e('merge_source'); ?></label>
						<select name="source_slug" style="width:100%;" required>
							<option value=""><?php _e('select_category'); ?></option>
							<?php foreach ($categoryTree as $cat):
								$prefix = str_repeat('— ', $cat['depth']);
							?>
							<option value="<?php echo $cat['slug']; ?>">
								<?php echo $prefix . htmlspecialchars($cat['name']); ?> (<?php echo $cat['count']; ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div style="padding-bottom:8px; font-size:1.6rem; opacity:.8;">→</div>
					<div class="form-group" style="margin:0; padding:0; flex:1; min-width:160px;">
						<label><?php _e('merge_target'); ?></label>
						<select name="target_slug" style="width:100%;" required>
							<option value=""><?php _e('select_category'); ?></option>
							<?php foreach ($categoryTree as $cat):
								$prefix = str_repeat('— ', $cat['depth']);
							?>
							<option value="<?php echo $cat['slug']; ?>">
								<?php echo $prefix . htmlspecialchars($cat['name']); ?> (<?php echo $cat['count']; ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<button class="btn btn-danger merge-btn" type="button"
						data-form="merge-cats-submit-form"
						data-confirm="<?php _e('confirm_merge_cats'); ?>"
						style="margin-bottom:1px;"><?php _e('merge'); ?></button>
				</div>
			</form>
		<?php endif; ?>
		<!-- Toolbar: orphan filter + purge -->
		<div class="tag-toolbar" style="display:flex; align-items:center; gap:12px; margin-bottom:18px; flex-wrap:wrap;">
			<?php if ($orphanCount > 0): ?>
			<label class="checkbox-label" style="display:flex; align-items:center; font-size:.9rem;">
				<input type="checkbox" id="show-orphans-only">
				<?php _e('show_orphans_only'); ?>
				<span class="badge-orphan"><?php echo $orphanCount; ?></span>
			</label>

			<form method="post" action="index.php?action=manage_categories" id="purge-cats-form" style="margin:0;">
				<input type="hidden" name="category_action" value="purge_orphans">
				<button type="button" class="btn btn-danger btn-sm purge-btn"
					data-form="purge-cats-form"
					data-confirm="<?php _e('confirm_purge_orphan_cats'); ?>">
					<?php _e('purge_orphan_cats'); ?> (<?php echo $orphanCount; ?>)
				</button>
			</form>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Existing Categories List -->
<h3 style="margin-top:30px;"><?php _e('existing_categories'); ?></h3>
<?php if (empty($categories)): ?>
	<p><?php _e('no_categories_found'); ?></p>
<?php else: ?>
	<div class="table-wrap">	
		<table id="cats-table">
		<thead>
			<tr>
				<th class="sortable" data-sort="name"><?php _e('category_name'); ?> <span class="sort-icon">↕</span></th>
				<th class="sortable" data-sort="slug"><?php _e('slug'); ?> <span class="sort-icon">↕</span></th>
				<th><?php _e('cat_parent'); ?></th>
				<th class="sortable" data-sort="count"><?php _e('item_count'); ?> <span class="sort-icon">↕</span></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($categoryTree as $cat):
				$slug   = $cat['slug'];
				$depth  = $cat['depth'];
				$indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
				$depthIcon = $depth > 0 ? str_repeat('└ ', 1) : '';
				$parentName = '';
				if (!empty($cat['parent']) && isset($categories[$cat['parent']])) {
					$parentName = $categories[$cat['parent']]['name'];
				}
			?>
			<tr data-orphan="<?php echo $cat['count'] === 0 ? '1' : '0'; ?>"
				data-depth="<?php echo $depth; ?>"
				data-slug="<?php echo hsc($slug); ?>"
				data-parent-slug="<?php echo hsc($cat['parent'] ?? ''); ?>"
				data-count="<?php echo $cat['count']; ?>">
				<td>
					<?php if ($depth > 0): ?>
						<span style="opacity:.4; font-size:.8em;"><?php echo $indent . $depthIcon; ?></span>
					<?php endif; ?>
					<?php echo hsc($cat['name']); ?>
					<?php if ($cat['count'] === 0): ?>
						<span class="badge-orphan" title="<?php _e('orphan_cat_title'); ?>"><?php _e('orphan'); ?></span>
					<?php endif; ?>
				</td>
				<td><code class="slug-display"><?php echo hsc($slug); ?></code></td>
				<td>
					<?php if (!empty($parentName)): ?>
						<span style="font-size:.85rem; opacity:.75;"><?php echo hsc($parentName); ?></span>
					<?php else: ?>
						<span style="opacity:.3">—</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ($cat['count'] > 0): ?>
						<span class="count-link"
							data-type-filter="<?php
								$types = array_unique(array_column($cat['items'], 'type'));
								echo count($types) === 1 ? $types[0] : 'article';
							?>"
							data-items="<?php echo hsc(json_encode($cat['items'])); ?>">
							<?php echo $cat['count']; ?>
						</span>
					<?php else: ?>
						<span style="opacity:.4">0</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="#" style="margin:0;" class="table-btn edit-btn small edit-category"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo hsc($cat['name']); ?>"
						data-parent="<?php echo hsc($cat['parent'] ?? ''); ?>"
						data-depth="<?php echo $depth; ?>"><?php echo admin_icon('writing', '', 13); ?><?php _e('edit'); ?></a>
					<a href="#" style="margin:0;" class="table-btn delete-btn small danger delete-category"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo hsc($cat['name']); ?>"><?php echo admin_icon('trash', '', 14); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<!-- Edit Category — bare form, populated and submitted via JS modal -->
	<form id="edit-category-form" method="post" action="index.php?action=manage_categories" style="display:none">
		<input type="hidden" name="category_action" value="edit">
		<input type="hidden" id="edit_category_slug" name="category_slug">
		<input type="hidden" id="edit_category_name" name="category_name">
		<input type="hidden" id="edit_category_parent" name="category_parent">
	</form>

	<!-- Parent options for JS modal — serialized from PHP -->
	<script type="application/json" id="cms-parent-options-json"><?php
		$opts = ['' => __t('cat_no_parent')];
		foreach ($categoryTree as $cat) {
			if ($cat['depth'] >= 2) continue; // Cannot be parent at depth 2+ (would exceed 3 levels)
			$prefix = str_repeat('— ', $cat['depth']);
			$opts[$cat['slug']] = $prefix . $cat['name'];
		}
		echo json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	?></script>
<?php endif; ?>

<!-- Popover for linked items -->
<div id="items-popover" style="
	display:none; position:fixed; z-index:9999;	background:var(--sidebar-bg); color:#fff; border:1px solid var(--border);border-radius:var(--radius-sm); padding:10px 14px; min-width:200px; max-width:320px;
	box-shadow:var(--shadow-lg); font-size:.85rem; pointer-events:none;">
	<div id="items-popover-content"></div>
</div>

<script src="assets/js/categories-manage.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/categories-manage.js'); ?>"></script>