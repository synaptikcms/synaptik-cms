<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}
require_once 'includes/admin-functions.php';

$_cl_all_items  = [];
$_cl_categories = [];

$_cl_authors = [];
foreach (admin_load_users() as $_cl_u) {
	$_cl_authors[$_cl_u['id']] = $_cl_u['display_name'] ?: $_cl_u['username'];
}
$_cl_show_authors = count($_cl_authors) > 1;

if (isset($data[$contentType]) && is_array($data[$contentType])) {
	foreach ($data[$contentType] as $_cl_idx => $_cl_item) {
		if (!admin_can_edit_item($_cl_item)) continue;

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
			'author_name'   => $_cl_show_authors ? ($_cl_authors[$_cl_item['author_id'] ?? ''] ?? '') : '',
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

$_cl_draftsDir = sl_admin_drafts_dir();
if (is_dir($_cl_draftsDir)) {
	foreach (glob($_cl_draftsDir . '/*.json') ?: [] as $_cl_draftFile) {
		$_cl_draftData = json_decode(file_get_contents($_cl_draftFile), true);
		if (!is_array($_cl_draftData) || ($_cl_draftData['type'] ?? '') !== $contentType) continue;
		if (!admin_can_edit_draft($_cl_draftData)) continue;
		if (($_cl_draftData['index'] ?? -1) < 0) continue;

		$_cl_pending_autosave_idx[$_cl_draftData['index']] = $_cl_draftData['id'];
	}
}

foreach ($_cl_all_items as &$_cl_row) {
	if (isset($_cl_pending_autosave_idx[$_cl_row['idx']])) {
		$_cl_row['has_pending_autosave'] = true;
		$_cl_row['autosave_url'] = 'index.php?action=drafts&draft_action=restore&id=' . urlencode($_cl_pending_autosave_idx[$_cl_row['idx']]) . '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '');
	} else {
		$_cl_row['has_pending_autosave'] = false;
	}
}
unset($_cl_row);

define('CL_INITIAL_LIMIT', 200);
$_cl_total        = count($_cl_all_items);
$_cl_use_ajax     = $_cl_total > CL_INITIAL_LIMIT;

usort($_cl_all_items, fn($a, $b) => strcmp($b['date'], $a['date']));
$_cl_items        = $_cl_use_ajax
	? array_slice($_cl_all_items, 0, CL_INITIAL_LIMIT)
	: $_cl_all_items;
?>

<form id="batch-delete-form" method="post" action="index.php" style="display: none;">
	<input type="hidden" name="batch_action" value="delete">
	<input type="hidden" name="content_type" value="<?php echo $contentType; ?>">
	<input type="hidden" name="selected_items" id="selected-items-input" value="">
	<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
</form>

<div class="content-list-header">
	<div class="list-actions">
		<a href="index.php?action=add&type=<?php echo urlencode($contentType); ?>" class="btn btn-primary btn-sm">
			<span class=""><?php echo admin_icon('circle-plus', '', 14); ?></span><?php printf(hsc(__t('add_new_type')), hsc(sl_type_label($contentType))); ?>
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
			<span class="icon-list"><?php echo admin_icon('grid', 'style="vertical-align:-2px;margin-right:4px"', 13); ?><?php _e('view_card'); ?></span>
			<span class="icon-card"><?php echo admin_icon('list', 'style="vertical-align:-2px;margin-right:4px"', 13); ?><?php _e('view_list'); ?></span>
		</button>
	</div>
</div>

<?php if (!empty($_cl_items)): ?>

<?php
$_cl_json_items      = json_encode($_cl_items,      JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$_cl_json_categories = json_encode($_cl_categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<script id="cl-data" type="application/json"
	data-type="<?php echo hsc($contentType); ?>"
	data-edit-base="index.php?action=edit&amp;type=<?php echo urlencode($contentType); ?>&amp;index="
	data-type-label="<?php echo hsc(sl_type_label($contentType)); ?>"
	data-i18n-edit="<?php echo hsc(__t('edit')); ?>"
	data-i18n-view="<?php echo hsc(__t('view')); ?>"
	data-i18n-duplicate="<?php echo hsc(__t('duplicate', 'Duplicate')); ?>"
	data-i18n-delete="<?php echo hsc(__t('delete')); ?>"
	data-duplicate-base="index.php?action=duplicate&amp;type=<?php echo urlencode($contentType); ?>&amp;csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>&amp;index="
	data-i18n-no-date="<?php echo hsc(__t('no_date')); ?>"
	data-i18n-no-tags="<?php echo hsc(__t('no_tags')); ?>"
	data-i18n-uncategorized="<?php echo hsc(__t('uncategorized')); ?>"
	data-i18n-published="<?php echo hsc(__t('status_published')); ?>"
	data-i18n-scheduled="<?php echo hsc(__t('scheduled')); ?>"
	data-i18n-draft="<?php echo hsc(__t('status_draft')); ?>"
	data-i18n-unpublished="<?php echo hsc(__t('status_unpublished')); ?>"
	data-i18n-unsaved-changes="<?php echo hsc(__t('unsaved_changes')); ?>"
	data-i18n-searching="<?php echo hsc(__t('searching', 'Searching…')); ?>"
	data-total="<?php echo $_cl_total; ?>"
	data-use-ajax="<?php echo $_cl_use_ajax ? '1' : '0'; ?>"
	data-ajax-url="list-content.php"
	data-show-authors="<?php echo $_cl_show_authors ? '1' : '0'; ?>"
	data-i18n-author-col="<?php echo hsc(__t('author_col', 'Author')); ?>"
><?php echo $_cl_json_items; ?></script>

<div class="content-filters">
	<div class="search-filter">
		<input type="text" id="content-search"
			placeholder="<?php printf(hsc(__t('search_type')), hsc(sl_type_label($contentType, true))); ?>">
		<button id="clear-search" class="clear-filter-list-btn">×</button>
	</div>

	<?php if ($contentType === 'article' || $contentType === 'project'): ?>
	<div class="category-filter">
		<select id="category-filter">
			<option value=""><?php _e('all_categories'); ?></option>
			<?php foreach ($_cl_categories as $_cat): ?>
			<option value="<?php echo hsc($_cat); ?>"><?php echo hsc($_cat); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<div class="status-filter">
		<select id="status-filter">
			<option value=""><?php _e('all_statuses'); ?></option>
			<option value="published"><?php _e('status_published'); ?></option>
			<option value="scheduled"><?php _e('scheduled'); ?></option>
			<option value="draft"><?php _e('status_draft'); ?></option>
			<option value="unpublished"><?php _e('status_unpublished'); ?></option>
		</select>
	</div>

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
				<th class="batch-checkbox-cell" style="display: none;">
					<input type="checkbox" id="batch-select-all" title="<?php echo hsc(__t('select_all_visible', 'Select all visible')); ?>">
				</th>
				<th class="sortable" data-sort="title"><?php _e('title'); ?> <span class="sort-icon">↕</span></th>
				<th class="sortable" data-sort="date"><?php _e('date'); ?> <span class="sort-icon">↕</span></th>
				<th class="status-col"><?php _e('status_col', 'Status'); ?></th>
				<?php if ($contentType === 'article' || $contentType === 'project'): ?>
				<th class="sortable" data-sort="category"><?php _e('category'); ?> <span class="sort-icon">↕</span></th>
				<?php endif; ?>
				<?php if ($_cl_show_authors): ?>
				<th class="author-col"><?php _e('author_col', 'Author'); ?></th>
				<?php endif; ?>
				<th class="tags-col"><?php _e('tags'); ?></th>
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
	<p><?php printf(hsc(__t('no_type_found')), hsc(sl_type_label($contentType, true))); ?></p>
	<a href="index.php?action=add&type=<?php echo urlencode($contentType); ?>" class="btn btn-primary">
		<?php printf(hsc(__t('create_first')), hsc(sl_type_label($contentType))); ?>
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
			<div id="modal-message"></div>
		</div>
		<div class="modal-footer"></div>
	</div>
</div>