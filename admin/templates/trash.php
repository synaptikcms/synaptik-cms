<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}
?>
<p class="help-text"><?php _e('trash_auto_purge_note'); ?></p>

<?php if (empty($trashItems)): ?>
	<div class="empty-content">
		<div class="empty-icon">
			<?php echo admin_icon('trash', '', 32); ?>
		</div>
		<p><?php _e('no_trash_items'); ?></p>
	</div>
<?php else: ?>
<form id="batch-restore-form" method="post" action="index.php?action=trash&trash_action=batch_restore" style="display: none;"></form>
<form id="batch-purge-form" method="post" action="index.php?action=trash&trash_action=batch_purge" style="display: none;"></form>

<div class="content-list-header">
	<div class="list-actions">
		<button id="enable-batch" class="btn-cl btn-cl--muted"><?php _e('batch_select'); ?></button>
		<div class="batch-actions" id="batch-actions" style="display: none;">
			<button id="batch-restore-btn" class="btn-cl"><?php _e('restore_selected'); ?> (<span id="selected-count">0</span>)</button>
			<button id="batch-purge-btn" class="btn-cl btn-cl--danger"><?php _e('purge_selected'); ?></button>
			<button id="cancel-batch" class="btn-cl btn-cl--muted"><?php _e('cancel'); ?></button>
		</div>
		<button id="purge-all-btn" class="btn-cl btn-cl--danger"><?php _e('empty_trash'); ?></button>
	</div>
</div>

<div class="drafts-list">
	<div class="table-wrap">
		<table>
		<thead>
			<tr>
				<th class="batch-checkbox-cell" style="display: none;"><?php _e('select'); ?></th>
				<th><?php _e('title'); ?></th>
				<th><?php _e('content_type'); ?></th>
				<th><?php _e('trashed_on'); ?></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($trashItems as $trashItem): ?>
			<tr>
				<td class="batch-checkbox-cell" style="display: none;">
					<input type="checkbox" class="batch-item" data-type="<?php echo hsc($trashItem['type']); ?>" data-file="<?php echo hsc($trashItem['_file'] ?? ''); ?>">
				</td>
				<td class="title-cell">
					<div class="title-with-preview">
						<?php if (!empty($trashItem['image'])): ?>
						<div class="mini-preview">
							<img src="<?php echo '../' . hsc($trashItem['image']); ?>" alt="" loading="lazy">
						</div>
						<?php else: ?>
						<div class="activity-icon unknown-icon"></div>
						<?php endif; ?>
						<span><?php echo hsc($trashItem['title'] ?: __t('untitled_draft')); ?></span>
					</div>
				</td>
				<td><?php echo ucfirst(hsc($trashItem['type'])); ?></td>
				<td><?php echo date('Y-m-d H:i:s', $trashItem['trashed_at'] ?? 0); ?></td>
				<td>
					<a href="index.php?action=trash&trash_action=restore&type=<?php echo urlencode($trashItem['type']); ?>&file=<?php echo urlencode($trashItem['_file'] ?? ''); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" class="table-btn edit-btn">
						<?php _e('restore'); ?>
					</a>
					<button type="button" class="table-btn delete-btn purge-item-btn"
					   data-type="<?php echo hsc($trashItem['type']); ?>"
					   data-file="<?php echo hsc($trashItem['_file'] ?? ''); ?>"
					   data-title="<?php echo hsc($trashItem['title'] ?: __t('untitled_draft')); ?>">
						<?php _e('purge_selected'); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
</div>
<?php endif; ?>

<script type="application/json" id="trash-data"><?php echo json_encode([
	'i18n' => [
		'batchNoSelection'      => __t('batch_no_selection_trash'),
		'batchNoSelectionTitle' => __t('batch_no_selection_title'),
		'purgeItemConfirm'      => __t('purge_item_confirm'),
		'purgeSelectedConfirm'  => __t('purge_selected_confirm'),
		'confirmPurge'          => __t('confirm_purge'),
		'emptyTrashConfirm'     => __t('empty_trash_confirm'),
		'emptyTrash'            => __t('empty_trash'),
		'purgeSelected'         => __t('purge_selected'),
		'cancel'                => __t('cancel'),
	],
	'count' => count($trashItems),
], JSON_HEX_TAG); ?></script>
<script src="assets/js/trash.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/trash.js'); ?>"></script>
