<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

$_act_entries = array_reverse(sl_admin_load_activity_log());

// Filters
$_act_filterUser   = trim($_GET['activity_user'] ?? '');
$_act_filterAction = trim($_GET['activity_action_filter'] ?? '');

if ($_act_filterUser !== '') {
	$_act_entries = array_values(array_filter($_act_entries, fn($e) => ($e['username'] ?? '') === $_act_filterUser));
}
if ($_act_filterAction !== '') {
	$_act_entries = array_values(array_filter($_act_entries, fn($e) => ($e['action'] ?? '') === $_act_filterAction));
}

// Pagination
$_act_perPage = 50;
$_act_total   = count($_act_entries);
$_act_page    = max(1, (int)($_GET['activity_page'] ?? 1));
$_act_pages   = max(1, (int)ceil($_act_total / $_act_perPage));
$_act_page    = min($_act_page, $_act_pages);
$_act_pageItems = array_slice($_act_entries, ($_act_page - 1) * $_act_perPage, $_act_perPage);

// Distinct users/actions for the filter dropdowns — drawn from the full log,
// not just the current page, so a filter never hides its own options.
$_act_allEntries  = sl_admin_load_activity_log();
$_act_knownUsers  = array_values(array_unique(array_filter(array_column($_act_allEntries, 'username'))));
sort($_act_knownUsers);
$_act_knownActions = array_values(array_unique(array_column($_act_allEntries, 'action')));
sort($_act_knownActions);

$_act_qs = function (array $overrides) {
	$params = array_merge($_GET, $overrides);
	unset($params['action']);
	return 'index.php?action=activity_log&' . http_build_query($params);
};
?>

<div class="content-header">
	<h1><?php echo admin_icon('info'); ?> <?php _e('activity_log_title'); ?></h1>
</div>

<p class="help-text"><?php _e('activity_log_intro'); ?></p>

<form method="get" action="index.php" class="content-list-header">
	<input type="hidden" name="action" value="activity_log">
	<div class="list-actions">
		<select name="activity_user" onchange="this.form.submit()">
			<option value=""><?php _e('activity_log_filter_user'); ?></option>
			<?php foreach ($_act_knownUsers as $_act_u): ?>
			<option value="<?php echo hsc($_act_u); ?>" <?php echo $_act_u === $_act_filterUser ? 'selected' : ''; ?>><?php echo hsc($_act_u); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="activity_action_filter" onchange="this.form.submit()">
			<option value=""><?php _e('activity_log_filter_action'); ?></option>
			<?php foreach ($_act_knownActions as $_act_a): ?>
			<option value="<?php echo hsc($_act_a); ?>" <?php echo $_act_a === $_act_filterAction ? 'selected' : ''; ?>><?php echo hsc(admin_activity_action_label($_act_a)); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ($_act_filterUser !== '' || $_act_filterAction !== ''): ?>
		<a href="index.php?action=activity_log" class="btn-cl btn-cl--muted"><?php _e('cancel'); ?></a>
		<?php endif; ?>
	</div>
</form>

<?php if (empty($_act_pageItems)): ?>
	<div class="empty-content">
		<div class="empty-icon">
			<?php echo admin_icon('clock', '', 32); ?>
		</div>
		<p><?php _e('activity_log_empty'); ?></p>
	</div>
<?php else: ?>
<div class="drafts-list">
	<div class="table-wrap">
		<table>
		<thead>
			<tr>
				<th><?php _e('activity_log_col_date'); ?></th>
				<th><?php _e('activity_log_col_user'); ?></th>
				<th><?php _e('activity_log_col_action'); ?></th>
				<th><?php _e('activity_log_col_details'); ?></th>
				<th><?php _e('activity_log_col_ip'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($_act_pageItems as $_act_entry): ?>
			<tr>
				<td><?php echo hsc(date('Y-m-d H:i:s', $_act_entry['ts'] ?? 0)); ?></td>
				<td><?php echo hsc($_act_entry['username'] ?? ''); ?></td>
				<td><?php echo hsc(admin_activity_action_label($_act_entry['action'] ?? '')); ?></td>
				<td><?php echo hsc($_act_entry['details'] ?? ''); ?></td>
				<td><?php echo hsc($_act_entry['ip'] ?? ''); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
</div>

<?php if ($_act_pages > 1): ?>
<div class="list-actions" style="margin-top:12px;">
	<?php for ($_act_p = 1; $_act_p <= $_act_pages; $_act_p++): ?>
	<a href="<?php echo hsc($_act_qs(['activity_page' => $_act_p])); ?>" class="btn-cl <?php echo $_act_p === $_act_page ? '' : 'btn-cl--muted'; ?>"><?php echo $_act_p; ?></a>
	<?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>
