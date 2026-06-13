<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

// Build tags index with count + linked items list
$tags = [];

if (isset($data['article'])) {
	foreach ($data['article'] as $idx => $article) {
		if (!isset($article['tags']) || !is_array($article['tags'])) continue;
		foreach ($article['tags'] as $tag) {
			$slug = sanitizeSlug($tag);
			if (!isset($tags[$slug])) {
				$tags[$slug] = ['name' => $tag, 'count' => 0, 'items' => []];
			}
			$tags[$slug]['count']++;
			$tags[$slug]['items'][] = ['title' => $article['title'], 'type' => 'article', 'index' => $idx];
		}
	}
}

if (isset($data['project'])) {
	foreach ($data['project'] as $idx => $project) {
		if (!isset($project['tags']) || !is_array($project['tags'])) continue;
		foreach ($project['tags'] as $tag) {
			$slug = sanitizeSlug($tag);
			if (!isset($tags[$slug])) {
				$tags[$slug] = ['name' => $tag, 'count' => 0, 'items' => []];
			}
			$tags[$slug]['count']++;
			$tags[$slug]['items'][] = ['title' => $project['title'], 'type' => 'project', 'index' => $idx];
		}
	}
}

// Add tags from dedicated collection (orphans have count 0)
if (isset($data['tags'])) {
	foreach ($data['tags'] as $slug => $tagData) {
		if (!isset($tags[$slug])) {
			$tags[$slug] = ['name' => $tagData['name'], 'count' => 0, 'items' => []];
		}
	}
}

$orphanCount = count(array_filter($tags, fn($t) => $t['count'] === 0));
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
			<button class="btn btn-primary" type="submit"><?php _e('add_tag_btn'); ?></button>
		</div>
	</form>
	</div>
	<div class="site-settings-section">
	<!-- Merge Tags Form -->
	<?php if (count($tags) >= 2): ?>
		<button type="button" class="btn btn-outline" id="toggle-merge-tags"><?php _e('merge_tags'); ?></button>
		<div id="merge-tags-form" style="display:none; margin-top:12px; padding:15px; background:var(--surface-2); border-radius:var(--radius-sm);">
			<h3 style="margin-top:0;"><?php _e('merge_tags'); ?></h3>
			<p style="font-size:.85rem; opacity:.75; margin-top:0;"><?php _e('merge_tags_help'); ?></p>
			<form id="merge-tags-submit-form" method="post" action="index.php?action=manage_tags">
				<input type="hidden" name="tag_action" value="merge">
				<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
					<div class="form-group" style="margin:0; padding: 0; flex:1; min-width:160px;">
						<label><?php _e('merge_source'); ?></label>
						<select name="source_slug" style="width:100%;" required>
							<option value=""><?php _e('select_tag'); ?></option>
							<?php foreach ($tags as $slug => $tag): ?>
							<option value="<?php echo $slug; ?>"><?php echo htmlspecialchars($tag['name']); ?> (<?php echo $tag['count']; ?>)</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div style="padding-bottom:8px; font-size:1.1rem; opacity:.6;">→</div>
					<div class="form-group" style="margin:0; padding:0; flex:1; min-width:160px;">
						<label><?php _e('merge_target'); ?></label>
						<select name="target_slug" style="width:100%;" required>
							<option value=""><?php _e('select_tag'); ?></option>
							<?php foreach ($tags as $slug => $tag): ?>
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
		</div>
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
			<tr data-orphan="<?php echo $tag['count'] === 0 ? '1' : '0'; ?>" data-slug="<?php echo htmlspecialchars($slug); ?>">
				<td>
					<?php echo htmlspecialchars($tag['name']); ?>
					<?php if ($tag['count'] === 0): ?>
						<span class="badge-orphan" title="<?php _e('orphan_tag_title'); ?>"><?php _e('orphan'); ?></span>
					<?php endif; ?>
				</td>
				<td><code class="slug-display"><?php echo htmlspecialchars($slug); ?></code></td>
				<td>
					<?php if ($tag['count'] > 0): ?>
						<span class="count-link"
							data-type-filter="<?php
								$types = array_unique(array_column($tag['items'], 'type'));
								echo count($types) === 1 ? $types[0] : 'article';
							?>"
							data-items="<?php echo htmlspecialchars(json_encode($tag['items'])); ?>">
							<?php echo $tag['count']; ?>
						</span>
					<?php else: ?>
						<span style="opacity:.4">0</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="#" style="margin:0;" class="table-btn edit-btn small edit-tag"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo htmlspecialchars($tag['name']); ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><?php _e('edit'); ?></a>
					<a href="#" style="margin:0;" class="table-btn delete-btn small danger delete-tag"
						data-slug="<?php echo $slug; ?>"
						data-name="<?php echo htmlspecialchars($tag['name']); ?>"><svg width="14" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {

	// ── Edit tag ──────────────────────────────────────────────────
	const editForm  = document.getElementById('edit-tag-form');
	const slugInput = document.getElementById('edit_tag_slug');
	const nameInput = document.getElementById('edit_tag_name');

	document.querySelectorAll('.edit-tag').forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			const slug = this.getAttribute('data-slug');
			const name = this.getAttribute('data-name');

			const modal = showModal(
				`<div class="form-group" style="margin:0">
					<label for="modal-tag-name" style="display:block;margin-bottom:6px">${window.t('tag_name')}</label>
					<input type="text" id="modal-tag-name" style="width:100%;box-sizing:border-box;">
				</div>`,
				window.t('edit_tag'),
				{
					showCancel: true,
					confirmText: window.t('update_tag'),
					cancelText: window.t('cancel'),
					onConfirm: function() {
						const newName = document.getElementById('modal-tag-name').value.trim();
						if (!newName) return;
						slugInput.value = slug;
						nameInput.value = newName;
						editForm.submit();
					}
				}
			);
			const input = document.getElementById('modal-tag-name');
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
	const tableRows      = document.querySelectorAll('#tags-table tbody tr');
	
	if (orphanCheckbox) {
		orphanCheckbox.addEventListener('change', function() {
			tableRows.forEach(row => {
				row.style.display = (this.checked && row.dataset.orphan !== '1') ? 'none' : '';
			});
		});
	}

	// ── Merge form toggle ─────────────────────────────────────────
	const toggleMergeBtn = document.getElementById('toggle-merge-tags');
	const mergeFormBox   = document.getElementById('merge-tags-form');
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
		// Keep within viewport
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
				html += `<li><a href="${url}" style="color:var(--primary);">${escHtml(item.title)}</a> <em style="opacity:.5">(${item.type})</em></li>`;
			});
			html += '</ul>';
			popoverContent.innerHTML = html;
			popover.style.display = 'block';
			positionPopover(e);
		});

		el.addEventListener('mousemove', positionPopover);
		el.addEventListener('mouseleave', () => popover.style.display = 'none');

		// Click navigates to content list
		el.addEventListener('click', function() {
			window.location = `index.php?type=${this.dataset.typeFilter}`;
		});
	});
});
</script>