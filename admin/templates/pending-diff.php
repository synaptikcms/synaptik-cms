<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

$_pdRestoreUrl = 'index.php?action=drafts&draft_action=restore&id=' . urlencode($pendingDraftId) . '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '');
$_pdDiscardUrl = 'index.php?action=drafts&draft_action=delete&id=' . urlencode($pendingDraftId) . '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '');
?>

<div class="site-settings-section">
	<div class="content-list-header">
		<h2><?php _e('unsaved_changes'); ?></h2>
		<a href="index.php?action=edit&type=<?php echo urlencode($contentType); ?>&index=<?php echo $index; ?>" class="btn-cl btn-cl--muted"><?php _e('back_to_editor'); ?></a>
	</div>

	<p class="help-text">
		<?php echo hsc(sprintf(__t('pending_from_date', 'Autosaved on %s — never applied. Compared here against the published version.'), date('Y-m-d H:i:s', $pendingTimestamp))); ?>
	</p>

	<?php
	$_pdFieldLabels = [
		'title'    => __t('title'),
		'summary'  => __t('article_summary_label', 'Summary'),
		'category' => __t('category'),
	];
	$_pdChangedRows = [];
	foreach ($_pdFieldLabels as $_pdKey => $_pdLabel) {
		$_pdOld = (string)($currentItem[$_pdKey] ?? '');
		$_pdNew = (string)($pendingItem[$_pdKey] ?? '');
		if ($_pdOld === $_pdNew) continue;
		$_pdChangedRows[] = ['label' => $_pdLabel, 'old' => $_pdOld, 'new' => $_pdNew];
	}
	?>

	<?php if (!empty($_pdChangedRows)): ?>
	<div class="table-wrap">
		<table class="revision-fields-table">
			<thead>
				<tr>
					<th><?php _e('title'); ?></th>
					<th><?php _e('status_published'); ?></th>
					<th><?php _e('unsaved_changes'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($_pdChangedRows as $_row): ?>
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
	<p class="help-text"><?php _e('pending_fields_unchanged', 'Title, summary and category are unchanged.'); ?></p>
	<?php endif; ?>

	<h3><?php _e('content_diff', 'Content Diff'); ?></h3>
	<?php $_pdContentDiff = admin_diff_lines((string)($currentItem['content'] ?? ''), (string)($pendingItem['content'] ?? '')); ?>
	<?php if ($_pdContentDiff === null): ?>
		<p class="help-text"><?php _e('diff_too_large', 'This content is too large to display a detailed diff.'); ?></p>
	<?php elseif (empty($_pdContentDiff)): ?>
		<p class="help-text"><?php _e('content_unchanged', 'Content is unchanged.'); ?></p>
	<?php else: ?>
		<div class="revision-diff"><?php foreach ($_pdContentDiff as $_line):
			$_cls    = 'diff-' . $_line['type'];
			$_prefix = $_line['type'] === 'added' ? '+ ' : ($_line['type'] === 'removed' ? '- ' : '  ');
		?><span class="<?php echo $_cls; ?>"><?php echo hsc($_prefix . $_line['text']); ?></span>
<?php endforeach; ?></div>
	<?php endif; ?>

	<div style="margin-top:20px; display:flex; gap:10px;">
		<button type="button" id="load-pending-btn" class="btn btn-primary" data-url="<?php echo hsc($_pdRestoreUrl); ?>">
			<?php _e('load_pending_version', 'Load This Version Into the Editor'); ?>
		</button>
		<button type="button" id="discard-pending-btn" class="btn btn-danger" data-url="<?php echo hsc($_pdDiscardUrl); ?>">
			<?php _e('discard_pending', 'Discard This Version'); ?>
		</button>
	</div>
</div>

<script type="application/json" id="pending-diff-data"><?php echo json_encode([
	'loadPendingConfirm'    => __t('load_pending_confirm', 'Load this unsaved version into the editor? Your published content stays untouched until you save.'),
	'confirmLoad'           => __t('confirm_load', 'Confirm'),
	'discardPendingConfirm' => __t('discard_pending_confirm', 'Discard this unsaved version? The published content is not affected — only this autosaved snapshot is deleted.'),
	'confirmDiscard'        => __t('confirm_discard', 'Confirm Discard'),
	'load'                  => __t('load', 'Load'),
	'discard'               => __t('discard', 'Discard'),
	'cancel'                => __t('cancel'),
], JSON_HEX_TAG); ?></script>
<script src="assets/js/pending-diff.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/pending-diff.js'); ?>"></script>
