<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

// ── Build categories index with count + linked items ──────────────────────────
$categories = [];

foreach (['article', 'project'] as $ct) {
	if (!isset($data[$ct])) continue;
	foreach ($data[$ct] as $idx => $item) {
		if (empty($item['category'])) continue;
		$slug = sanitizeSlug($item['category']);
		if (!isset($categories[$slug])) {
			$categories[$slug] = ['name' => $item['category'], 'count' => 0, 'items' => [], 'parent' => ''];
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

/**
 * Build a hierarchy-aware sorted list of categories (max 3 levels).
 * Returns a flat array with each entry carrying a 'depth' and 'label_prefix' for display.
 *
 * @param array  $categories  Flat map: slug => ['name', 'count', 'items', 'parent']
 * @param string $parentSlug  Current parent being processed
 * @param int    $depth       Current nesting depth (0 = root)
 * @return array
 */
function buildCategoryTree(array $categories, string $parentSlug = '', int $depth = 0): array {
	if ($depth >= 3) return []; // Hard limit: 3 sub-levels maximum
	$result = [];
	foreach ($categories as $slug => $cat) {
		if (($cat['parent'] ?? '') !== $parentSlug) continue;
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
				<button class="button" type="submit"><?php _e('add_category_btn'); ?></button>
			</div>
		</form>
	</div>
	<div class="site-settings-section">
		<!-- Merge Categories Form -->
		<?php if (count($categories) >= 2): ?>
			<button type="button" class="button" id="toggle-merge-cats"><?php _e('merge_categories'); ?></button>
			<div id="merge-cats-form" style="display:none; padding:15px; background:var(--bg-secondary); border-radius:5px;">
				<h3 style="margin-top:0;"><?php _e('merge_categories'); ?></h3>
				<p style="font-size:.85rem; opacity:.75; margin-top:0;"><?php _e('merge_cats_help'); ?></p>
				<form id="merge-cats-submit-form" method="post" action="index.php?action=manage_categories">
					<input type="hidden" name="category_action" value="merge">
					<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
						<div class="form-group" style="margin:0; padding:0; flex:1; min-width:160px;">
							<label><?php _e('merge_source'); ?></label>
							<select name="source_slug" required>
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
							<select name="target_slug" required>
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
						<button class="button danger merge-btn" type="button"
							data-form="merge-cats-submit-form"
							data-confirm="<?php _e('confirm_merge_cats'); ?>"
							style="margin-bottom:1px;"><?php _e('merge'); ?></button>
					</div>
				</form>
			</div>
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
				<button type="button" class="button small danger purge-btn"
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
				data-slug="<?php echo htmlspecialchars($slug); ?>"
				data-parent-slug="<?php echo htmlspecialchars($cat['parent'] ?? ''); ?>"
				data-count="<?php echo $cat['count']; ?>">
				<td>
					<?php if ($depth > 0): ?>
						<span style="opacity:.4; font-size:.8em;"><?php echo $indent . $depthIcon; ?></span>
					<?php endif; ?>
					<?php echo htmlspecialchars($cat['name']); ?>
					<?php if ($cat['count'] === 0): ?>
						<span class="badge-orphan" title="<?php _e('orphan_cat_title'); ?>"><?php _e('orphan'); ?></span>
					<?php endif; ?>
				</td>
				<td><code class="slug-display"><?php echo htmlspecialchars($slug); ?></code></td>
				<td>
					<?php if (!empty($parentName)): ?>
						<span style="font-size:.85rem; opacity:.75;"><?php echo htmlspecialchars($parentName); ?></span>
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
							data-items="<?php echo htmlspecialchars(json_encode($cat['items'])); ?>">
							<?php echo $cat['count']; ?>
						</span>
					<?php else: ?>
						<span style="opacity:.4">0</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="#" style="margin:0;" class="table-btn edit-btn small edit-category"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo htmlspecialchars($cat['name']); ?>"
						data-parent="<?php echo htmlspecialchars($cat['parent'] ?? ''); ?>"
						data-depth="<?php echo $depth; ?>"><?php _e('edit'); ?></a>
					<a href="#" style="margin:0;" class="table-btn delete-btn small danger delete-category"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo htmlspecialchars($cat['name']); ?>"><?php _e('delete'); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Edit Category — bare form, populated and submitted via JS modal -->
	<form id="edit-category-form" method="post" action="index.php?action=manage_categories" style="display:none">
		<input type="hidden" name="category_action" value="edit">
		<input type="hidden" id="edit_category_slug" name="category_slug">
		<input type="hidden" id="edit_category_name" name="category_name">
		<input type="hidden" id="edit_category_parent" name="category_parent">
	</form>

	<!-- Parent options for JS modal — serialized from PHP -->
	<script>
	// Available parent options for the edit modal (slug => label with depth prefix)
	window.CMS_PARENT_OPTIONS = <?php
		$opts = ['' => __t('cat_no_parent')];
		foreach ($categoryTree as $cat) {
			if ($cat['depth'] >= 2) continue; // Cannot be parent at depth 2+ (would exceed 3 levels)
			$prefix = str_repeat('— ', $cat['depth']);
			$opts[$cat['slug']] = $prefix . $cat['name'];
		}
		echo json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	?>;
	</script>
<?php endif; ?></main>
</div>

<!-- Popover for linked items -->
<div id="items-popover" style="
	display:none; position:fixed; z-index:9999;	background:rgba(35, 47, 61, 0.777); color:white; border:1px solid var(--border-color);border-radius:6px; padding:10px 14px; min-width:200px; max-width:320px;
	box-shadow:0 4px 16px rgba(0,0,0,.35); font-size:.85rem; pointer-events:none;">
	<div id="items-popover-content"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

	// ── Edit category ─────────────────────────────────────────────
	const editForm      = document.getElementById('edit-category-form');
	const slugInput     = document.getElementById('edit_category_slug');
	const nameInput     = document.getElementById('edit_category_name');
	const parentInput   = document.getElementById('edit_category_parent');

	document.querySelectorAll('.edit-category').forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			const slug        = this.getAttribute('data-slug');
			const name        = this.getAttribute('data-name');
			const currentParent = this.getAttribute('data-parent') || '';
			const currentDepth  = parseInt(this.getAttribute('data-depth') || '0', 10);

			// Build parent <select> options, excluding self and own descendants
			let parentSelectHtml = '<select id="modal-cat-parent" style="width:100%;box-sizing:border-box;margin-top:4px;">';
			const opts = window.CMS_PARENT_OPTIONS || {};
			for (const [optSlug, optLabel] of Object.entries(opts)) {
				// Cannot set self as its own parent
				if (optSlug === slug) continue;
				const selected = optSlug === currentParent ? ' selected' : '';
				parentSelectHtml += `<option value="${optSlug}"${selected}>${optLabel}</option>`;
			}
			parentSelectHtml += '</select>';

			const modal = showModal(
				`<div class="form-group" style="margin:0 0 14px">
					<label for="modal-cat-name" style="display:block;margin-bottom:6px">${window.t('category_name')}</label>
					<input type="text" id="modal-cat-name" style="width:100%;box-sizing:border-box;">
				</div>
				<div class="form-group" style="margin:0">
					<label style="display:block;margin-bottom:6px">${window.t('cat_parent')}</label>
					${parentSelectHtml}
					<p style="font-size:.8rem;opacity:.65;margin-top:4px;">${window.t('cat_parent_help')}</p>
				</div>`,
				window.t('edit_category'),
				{
					showCancel: true,
					confirmText: window.t('update_category'),
					cancelText: window.t('cancel'),
					onConfirm: function() {
						const newName   = document.getElementById('modal-cat-name').value.trim();
						const newParent = document.getElementById('modal-cat-parent').value;
						if (!newName) return;
						slugInput.value   = slug;
						nameInput.value   = newName;
						parentInput.value = newParent;
						editForm.submit();
					}
				}
			);
			const input = document.getElementById('modal-cat-name');
			if (input) {
				input.value = name;
				input.select();
				input.addEventListener('keydown', function(e) {
					if (e.key === 'Enter') modal.querySelector('.modal-confirm').click();
				});
			}
		});
	});

	// ── Orphan filter ─────────────────────────────────────────────
	const orphanCheckbox = document.getElementById('show-orphans-only');
	const tableRows      = document.querySelectorAll('#cats-table tbody tr');
	
	if (orphanCheckbox) {
		orphanCheckbox.addEventListener('change', function() {
			tableRows.forEach(row => {
				row.style.display = (this.checked && row.dataset.orphan !== '1') ? 'none' : '';
			});
		});
	}

	// ── Merge form toggle ─────────────────────────────────────────
	const toggleMergeBtn = document.getElementById('toggle-merge-cats');
	const mergeFormBox   = document.getElementById('merge-cats-form');
	if (toggleMergeBtn) {
		toggleMergeBtn.addEventListener('click', () => {
			mergeFormBox.style.display = mergeFormBox.style.display === 'none' ? 'block' : 'none';
		});
	}

	// ── Count popover ─────────────────────────────────────────────
	const popover        = document.getElementById('items-popover');
	const popoverContent = document.getElementById('items-popover-content');

	function escHtml(str) {
		return String(str)
			.replace(/&/g,'&amp;').replace(/</g,'&lt;')
			.replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	function positionPopover(e) {
		const pad = 14;
		let x = e.clientX + pad;
		let y = e.clientY + pad;
		if (x + 320 > window.innerWidth)  x = e.clientX - 320 - pad;
		if (y + 200 > window.innerHeight) y = e.clientY - 200 - pad;
		popover.style.left = x + 'px';
		popover.style.top  = y + 'px';
	}

	document.querySelectorAll('.count-link').forEach(el => {
		el.style.cursor = 'pointer';
		el.style.borderBottom = '1px dotted currentColor';

		el.addEventListener('mouseenter', function(e) {
			const items = JSON.parse(this.dataset.items || '[]');
			if (!items.length) return;
			const t = (k) => window.CMS_LANG?.[k] ?? k;
		let html = `<strong style="display:block;margin-bottom:6px;">${t('linked_items')}</strong><ul style="margin:0;padding:0 0 0 16px;">`;
			items.forEach(item => {
				const url = `index.php?action=edit&type=${item.type}&index=${item.index}`;
				html += `<li><a href="${url}" style="color:var(--accent-color);">${escHtml(item.title)}</a> <em style="opacity:.5">(${item.type})</em></li>`;
			});
			html += '</ul>';
			popoverContent.innerHTML = html;
			popover.style.display = 'block';
			positionPopover(e);
		});

		el.addEventListener('mousemove', positionPopover);
		el.addEventListener('mouseleave', () => popover.style.display = 'none');

		el.addEventListener('click', function() {
			window.location = `index.php?type=${this.dataset.typeFilter}`;
		});
	});

	// ── Hierarchy-aware table sort — overrides the global sortTableByKey for #cats-table ──
	// The global version in panel.js sorts all rows independently, which breaks the
	// parent/child grouping. This version sorts only root rows, then re-inserts
	// each root's descendants immediately below it, preserving nesting at every depth.
	const catsTable = document.getElementById('cats-table');
	if (catsTable) {

		/**
		 * Return a plain text value from a row for a given sort key.
		 * Reads data-slug for 'slug', data-count for 'count',
		 * and the first cell's text for 'name'.
		 */
		function getCatRowValue(row, sortKey) {
			if (sortKey === 'slug')  return row.dataset.slug  || '';
			if (sortKey === 'count') return parseInt(row.dataset.count ?? row.querySelector('td:nth-child(4)')?.textContent.trim(), 10) || 0;
			// 'name': strip leading whitespace / tree characters, keep only the name text
			const nameCell = row.querySelector('td:first-child');
			const clone = nameCell ? nameCell.cloneNode(true) : null;
			// Remove the orphan badge span before reading text so it doesn't pollute the sort value
			clone?.querySelectorAll('span').forEach(s => s.remove());
			return (clone?.textContent ?? '').trim().toLowerCase();
		}

		/**
		 * Sort tbody rows of #cats-table while preserving parent→child grouping.
		 * Only top-level rows (depth === '0') are sorted; their descendants
		 * are kept in original relative order immediately below them.
		 *
		 * @param {string}  sortKey    'name' | 'slug' | 'count'
		 * @param {boolean} isAscending
		 */
		function sortCatsTable(sortKey, isAscending) {
			const tbody = catsTable.querySelector('tbody');
			if (!tbody) return;

			const allRows = Array.from(tbody.querySelectorAll('tr'));

			// Build a map: slug → row element (for quick parent lookup)
			const rowBySlug = {};
			allRows.forEach(row => { rowBySlug[row.dataset.slug] = row; });

			// Identify root rows (depth 0) and group each with its descendants
			// A descendant is any row whose ancestor chain leads back to the root.
			// We collect groups in original DOM order first, then sort the groups.
			const groups = []; // [ { root: rowEl, children: [rowEl, ...] }, ... ]

			allRows.forEach(row => {
				if (row.dataset.depth !== '0') return; // skip non-roots
				// Collect all descendants in current DOM order
				const children = allRows.filter(r => {
					if (r.dataset.depth === '0') return false;
					// Walk up parent chain via data-slug on parent rows
					let current = r;
					for (let i = 0; i < 3; i++) {
						const parentSlug = current.dataset.parentSlug || '';
						if (!parentSlug) break;
						const parentRow = rowBySlug[parentSlug];
						if (!parentRow) break;
						if (parentRow === row) return true; // root is an ancestor
						current = parentRow;
					}
					return false;
				});
				groups.push({ root: row, children });
			});

			// Sort groups by the root row's value
			groups.sort((a, b) => {
				const va = getCatRowValue(a.root, sortKey);
				const vb = getCatRowValue(b.root, sortKey);
				if (typeof va === 'number') return isAscending ? va - vb : vb - va;
				return isAscending ? va.localeCompare(vb) : vb.localeCompare(va);
			});

			// Re-insert: root first, then its children in their original relative order
			groups.forEach(({ root, children }) => {
				tbody.appendChild(root);
				children.forEach(child => tbody.appendChild(child));
			});
		}

		// Hook into the header clicks for #cats-table specifically, running
		// sortCatsTable AFTER the global handler has already fired (so sort icons update).
		catsTable.querySelectorAll('th.sortable').forEach(th => {
			th.addEventListener('click', function() {
				const sortKey    = this.getAttribute('data-sort');
				const isAscending = this.classList.contains('sorted-asc'); // global handler already toggled
				sortCatsTable(sortKey, isAscending);
			});
		});
	}
});
</script>