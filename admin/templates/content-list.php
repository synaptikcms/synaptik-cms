<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}
require_once 'includes/admin-functions.php';

// ── Total count and category list (read from full index) ─────────────────────
// These are always built from the complete index so filters and counts
// are accurate regardless of how many items we send in the initial payload.
$_cl_all_items  = [];
$_cl_categories = [];

if (isset($data[$contentType]) && is_array($data[$contentType])) {
	foreach ($data[$contentType] as $_cl_idx => $_cl_item) {
		$_cl_effective_slug = !empty($_cl_item['custom_slug'])
			? $_cl_item['custom_slug']
			: ($_cl_item['slug'] ?? '');
		$_cl_category_slug  = isset($_cl_item['category'])
			? sanitizeSlug($_cl_item['category'])
			: '';

		$_cl_tags = [];
		if (!empty($_cl_item['tags']) && is_array($_cl_item['tags'])) {
			$_cl_tags = array_values($_cl_item['tags']);
		}

		$_cl_all_items[] = [
			'idx'           => $_cl_idx,
			'title'         => $_cl_item['title']    ?? '',
			'date'          => $_cl_item['date']      ?? '',
			'date_formatted'=> admin_format_date($_cl_item['date'] ?? ''),
			'time_formatted'=> admin_format_time($_cl_item['date'] ?? ''),
			'last_modified' => $_cl_item['last_modified'] ?? $_cl_item['date'] ?? '',
			'last_modified_formatted' => admin_format_date($_cl_item['last_modified'] ?? $_cl_item['date'] ?? ''),
			'last_modified_time'      => admin_format_time($_cl_item['last_modified'] ?? $_cl_item['date'] ?? ''),
			'category'      => $_cl_item['category'] ?? '',
			'category_slug' => $_cl_category_slug,
			'tags'          => $_cl_tags,
			'image'         => $_cl_item['image']    ?? '',
			'status'        => $_cl_item['status']   ?? 'published',
			'publish_at'    => $_cl_item['publish_at'] ?? '',
			'slug'          => $_cl_effective_slug,
			'custom_slug'   => $_cl_item['custom_slug'] ?? '',
			'view_url'      => adminCleanUrl(
				$contentType,
				$_cl_item['slug']        ?? '',
				$_cl_item['custom_slug'] ?? '',
				$_cl_item['category']    ?? ''
			),
		];

		if (!empty($_cl_item['category']) && !in_array($_cl_item['category'], $_cl_categories, true)) {
			$_cl_categories[] = $_cl_item['category'];
		}
	}
}
sort($_cl_categories);

// ── Initial payload cap ───────────────────────────────────────────────────────
// Send only the first CL_INITIAL_LIMIT items inline.
// Beyond that threshold the JS switches to server-side AJAX automatically.
// This keeps the HTML payload small regardless of total item count.
define('CL_INITIAL_LIMIT', 200);
$_cl_total        = count($_cl_all_items);
$_cl_use_ajax     = $_cl_total > CL_INITIAL_LIMIT;
// Sort newest-first before slicing so the initial payload shows the most
// recent items (matches the default sort order in the UI).
usort($_cl_all_items, fn($a, $b) => strcmp($b['date'], $a['date']));
$_cl_items        = $_cl_use_ajax
	? array_slice($_cl_all_items, 0, CL_INITIAL_LIMIT)
	: $_cl_all_items;
?>

<form id="batch-delete-form" method="post" action="index.php" style="display: none;">
	<input type="hidden" name="batch_action" value="delete">
	<input type="hidden" name="content_type" value="<?php echo $contentType; ?>">
	<input type="hidden" name="selected_items" id="selected-items-input" value="">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
</form>

<div class="content-list-header">
	<div class="list-actions">
		<a href="index.php?action=add&type=<?php echo urlencode($contentType); ?>" class="btn btn-primary btn-sm">
			<span class=""><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg></span><?php printf(__t('add_new_type'), __t('type_' . $contentType)); ?>
		</a>
		<button id="enable-batch" class="btn btn-outline btn-sm"><?php _e('batch_select'); ?></button>
		<div class="batch-actions" id="batch-actions" style="display: none;">
			<button id="batch-delete-btn" class="btn btn-danger btn-sm">
				<?php _e('delete_selected'); ?> (<span id="selected-count">0</span>)
			</button>
			<button id="cancel-batch" class="btn btn-neutral btn-sm"><?php _e('cancel'); ?></button>
		</div>
	</div>
	<div class="view-toggle">
		<button id="view-toggle-btn" class="btn btn-outline btn-sm" title="<?php _e('switch_view'); ?>">
			<span class="icon-list"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><?php _e('view_card'); ?></span>
			<span class="icon-card"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg><?php _e('view_list'); ?></span>
		</button>
	</div>
</div>

<?php if (!empty($_cl_items)): ?>

<?php
// Inline JSON payload — consumed by initContentListPagination() in panel.js.
// json_encode with JSON_HEX_TAG prevents XSS if a title contains HTML.
$_cl_json_items      = json_encode($_cl_items,      JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$_cl_json_categories = json_encode($_cl_categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<script id="cl-data" type="application/json"
	data-type="<?php echo htmlspecialchars($contentType); ?>"
	data-edit-base="index.php?action=edit&amp;type=<?php echo urlencode($contentType); ?>&amp;index="
	data-type-label="<?php echo htmlspecialchars(__t('type_' . $contentType)); ?>"
	data-i18n-edit="<?php echo htmlspecialchars(__t('edit')); ?>"
	data-i18n-view="<?php echo htmlspecialchars(__t('view')); ?>"
	data-i18n-duplicate="<?php echo htmlspecialchars(__t('duplicate', 'Duplicate')); ?>"
	data-i18n-delete="<?php echo htmlspecialchars(__t('delete')); ?>"
	data-duplicate-base="index.php?action=duplicate&amp;type=<?php echo urlencode($contentType); ?>&amp;index="
	data-i18n-no-date="<?php echo htmlspecialchars(__t('no_date')); ?>"
	data-i18n-no-tags="<?php echo htmlspecialchars(__t('no_tags')); ?>"
	data-i18n-uncategorized="<?php echo htmlspecialchars(__t('uncategorized')); ?>"
	data-i18n-scheduled="<?php echo htmlspecialchars(__t('scheduled')); ?>"
	data-i18n-searching="<?php echo htmlspecialchars(__t('searching', 'Searching…')); ?>"
	data-total="<?php echo $_cl_total; ?>"
	data-use-ajax="<?php echo $_cl_use_ajax ? '1' : '0'; ?>"
	data-ajax-url="list-content.php"
><?php echo $_cl_json_items; ?></script>

<div class="content-filters">
	<div class="search-filter">
		<input type="text" id="content-search"
			placeholder="<?php printf(__t('search_type'), __t('type_' . $contentType . 's')); ?>">
		<button id="clear-search" class="clear-filter-list-btn">×</button>
	</div>

	<?php if ($contentType === 'article' || $contentType === 'project'): ?>
	<div class="category-filter">
		<select id="category-filter">
			<option value=""><?php _e('all_categories'); ?></option>
			<?php foreach ($_cl_categories as $_cat): ?>
			<option value="<?php echo htmlspecialchars($_cat); ?>"><?php echo htmlspecialchars($_cat); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<div class="sort-filter">
		<select id="sort-filter">
			<option value="date-desc"><?php _e('sort_newest'); ?></option>
			<option value="date-asc"><?php _e('sort_oldest'); ?></option>
			<option value="title-asc"><?php _e('sort_title_az'); ?></option>
			<option value="title-desc"><?php _e('sort_title_za'); ?></option>
		</select>
	</div>

	<div class="per-page-filter">
		<select id="per-page-select" title="<?php _e('items_per_page', 'Items per page'); ?>">
			<option value="10">10</option>
			<option value="25" selected>25</option>
			<option value="50">50</option>
			<option value="0"><?php _e('show_all', 'All'); ?></option>
		</select>
	</div>
</div>

<div id="no-results" style="display:none; padding: 24px; text-align:center; color: var(--text-muted);">
	<?php _e('no_results_found', 'No items match your search.'); ?>
</div>

<div class="content-cards-container"></div>

<div class="content-list-container" style="display: none;">
	<div class="table-wrap">
		<table>
		<thead>
			<tr>
				<th class="batch-checkbox-cell" style="display: none;"><?php _e('select_chk'); ?></th>
				<th class="sortable" data-sort="title"><?php _e('title'); ?> <span class="sort-icon">↕</span></th>
				<th class="sortable" data-sort="date"><?php _e('date'); ?> <span class="sort-icon">↕</span></th>
				<?php if ($contentType === 'article' || $contentType === 'project'): ?>
				<th class="sortable" data-sort="category"><?php _e('category'); ?> <span class="sort-icon">↕</span></th>
				<?php endif; ?>
				<th><?php _e('tags'); ?></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody id="cl-tbody"></tbody>
	</table>
	</div>
</div>

<div class="cl-pagination" id="cl-pagination" style="display:none;">
	<div class="cl-pagination-info" id="cl-pagination-info"></div>
	<div class="cl-pagination-controls">
		<button class="cl-pg-btn" id="cl-pg-prev" aria-label="Previous page">‹</button>
		<div class="cl-pg-pages" id="cl-pg-pages"></div>
		<button class="cl-pg-btn" id="cl-pg-next" aria-label="Next page">›</button>
	</div>
</div>

<?php else: ?>

<div class="empty-content">
	<div class="empty-icon"><?php echo strtoupper(substr($contentType, 0, 1)); ?></div>
	<p><?php printf(__t('no_type_found'), __t('type_' . $contentType . 's')); ?></p>
	<a href="index.php?action=add&type=<?php echo urlencode($contentType); ?>" class="btn btn-primary">
		<?php printf(__t('create_first'), __t('type_' . $contentType)); ?>
	</a>
</div>

<?php endif; ?>

<!-- Confirmation modal -->
<div id="global-modal" class="modal-overlay" style="display: none;">
	<div class="modal-container">
		<div class="modal-header">
			<span class="modal-title"><?php _e('notification'); ?></span>
			<span class="modal-close">&times;</span>
		</div>
		<div class="modal-content">
			<p id="modal-message"></p>
		</div>
		<div class="modal-footer"></div>
	</div>
</div>