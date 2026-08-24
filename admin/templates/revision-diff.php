<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}
?>

<div class="site-settings-section">
	<div class="content-list-header">
		<h2><?php _e('revision_history'); ?></h2>
		<a href="index.php?action=edit&type=<?php echo urlencode($contentType); ?>&index=<?php echo $index; ?>" class="btn-cl btn-cl--muted"><?php _e('back_to_editor'); ?></a>
	</div>

	<p class="help-text">
		<?php echo hsc(sprintf(__t('revision_from_date'), date('Y-m-d H:i:s', $revisionTimestamp))); ?>
	</p>

	<?php
	$_revFieldLabels = [
		'title'    => __t('title'),
		'summary'  => __t('article_summary_label', 'Summary'),
		'category' => __t('category'),
		'status'   => __t('status', 'Status'),
	];
	$_revChangedRows = [];
	foreach ($_revFieldLabels as $_revKey => $_revLabel) {
		$_revOld = (string)($revisionItem[$_revKey] ?? '');
		$_revNew = (string)($currentItem[$_revKey] ?? '');
		if ($_revOld === $_revNew) continue;
		$_revChangedRows[] = ['label' => $_revLabel, 'old' => $_revOld, 'new' => $_revNew];
	}
	?>

	<?php if (!empty($_revChangedRows)): ?>
	<div class="table-wrap">
		<table class="revision-fields-table">
			<thead>
				<tr>
					<th><?php _e('title'); ?></th>
					<th><?php _e('revision_before', 'This Revision'); ?></th>
					<th><?php _e('revision_after', 'Current'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($_revChangedRows as $_row): ?>
				<tr>
					<th><?php echo hsc($_row['label']); ?></th>
					<td class="revision-old"><?php echo hsc($_row['old']); ?></td>
					<td class="revision-new"><?php echo hsc($_row['new']); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php else: ?>
	<p class="help-text"><?php _e('revision_fields_unchanged', 'Title, summary, category and status are unchanged.'); ?></p>
	<?php endif; ?>

	<h3><?php _e('content_diff', 'Content Diff'); ?></h3>
	<?php $_revContentDiff = admin_diff_lines((string)($revisionItem['content'] ?? ''), (string)($currentItem['content'] ?? '')); ?>
	<?php if ($_revContentDiff === null): ?>
		<p class="help-text"><?php _e('diff_too_large', 'This content is too large to display a detailed diff.'); ?></p>
	<?php elseif (empty($_revContentDiff)): ?>
		<p class="help-text"><?php _e('content_unchanged', 'Content is unchanged.'); ?></p>
	<?php else: ?>
		<div class="revision-diff"><?php foreach ($_revContentDiff as $_line):
			$_cls    = 'diff-' . $_line['type'];
			$_prefix = $_line['type'] === 'added' ? '+ ' : ($_line['type'] === 'removed' ? '- ' : '  ');
		?><span class="<?php echo $_cls; ?>"><?php echo hsc($_prefix . $_line['text']); ?></span>
<?php endforeach; ?></div>
	<?php endif; ?>

	<button type="button" id="restore-revision-btn" class="btn btn-primary" style="margin-top:20px;"
		data-url="index.php?action=revision_restore&type=<?php echo urlencode($contentType); ?>&index=<?php echo $index; ?>&timestamp=<?php echo $revisionTimestamp; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>">
		<?php _e('restore_this_version', 'Restore This Version'); ?>
	</button>
	<button type="button" id="delete-revision-btn" class="btn btn-danger" style="margin-top:20px;"
		data-url="index.php?action=delete_revision&type=<?php echo urlencode($contentType); ?>&index=<?php echo $index; ?>&timestamp=<?php echo $revisionTimestamp; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>">
		<?php _e('delete_this_revision', 'Delete This Revision'); ?>
	</button>
</div>

<script type="application/json" id="revision-diff-data"><?php echo json_encode([
	'restoreRevisionConfirm' => __t('restore_revision_confirm', 'Restore this version? The current content will be saved as a new revision first.'),
	'confirmRestore'         => __t('confirm_restore', 'Confirm Restore'),
	'restore'                => __t('restore'),
	'cancel'                 => __t('cancel'),
	'deleteRevisionConfirm'  => __t('delete_revision_confirm', 'Delete this revision? This cannot be undone.'),
	'confirmDeleteRevision'  => __t('confirm_delete_revision', 'Confirm Delete'),
	'delete'                 => __t('delete'),
], JSON_HEX_TAG); ?></script>
<script src="assets/js/revision-diff.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/revision-diff.js'); ?>"></script>
