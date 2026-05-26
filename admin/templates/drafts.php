<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}
?>
<p><?php _e('drafts_auto_delete_note'); ?></p>

<?php if (empty($drafts)): ?>
	<div class="empty-content">
		<div class="empty-icon">📄</div>
		<p><?php _e('no_drafts_found'); ?></p>
	</div>
<?php else: ?>
<form id="batch-delete-form" method="post" action="index.php?action=drafts&draft_action=batch_delete" style="display: none;"></form>

<div class="content-list-header">
	<div class="list-actions">
		<button id="enable-batch" class="button"><?php _e('batch_select'); ?></button>
		<div class="batch-actions" id="batch-actions" style="display: none;">
			<button id="batch-delete-btn" class="button danger"><?php _e('delete_selected'); ?> (<span id="selected-count">0</span>)</button>
			<button id="cancel-batch" class="button"><?php _e('cancel'); ?></button>
		</div>
		<button id="purge-all-btn" class="button danger"><?php _e('purge_all_drafts'); ?></button>
	</div>
</div>

<div class="drafts-list">
	<table>
		<thead>
			<tr>
				<th class="batch-checkbox-cell" style="display: none;"><?php _e('select'); ?></th>
				<th><?php _e('title'); ?></th>
				<th><?php _e('content_type'); ?></th>
				<th><?php _e('last_saved'); ?></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($drafts as $draft): ?>
			<tr>
				<td class="batch-checkbox-cell" style="display: none;">
					<input type="checkbox" class="batch-item" data-id="<?php echo htmlspecialchars($draft['id']); ?>">
				</td>
				<td class="title-cell">
					<div class="title-with-preview">
						<?php if (!empty($draft['image'])): ?>
						<div class="mini-preview">
							<img src="<?php echo '../files/' . htmlspecialchars($draft['image']); ?>" alt="" loading="lazy">
						</div>
						<?php else: ?>
						<div class="activity-icon unknown-icon"></div>
						<?php endif; ?>
						<span><?php echo htmlspecialchars($draft['title'] ?: __t('untitled_draft')); ?></span>
					</div>
				</td>
				<td><?php echo ucfirst(htmlspecialchars($draft['type'] ?: __t('type_unknown'))); ?></td>
				<td><?php echo date('Y-m-d H:i:s', $draft['timestamp']); ?></td>
				<td>
					<a href="index.php?action=drafts&draft_action=restore&id=<?php echo urlencode($draft['id']); ?>" class="small table-btn edit-btn">
						<?php _e('restore'); ?>
					</a>
					<button type="button" class="button small delete-draft-btn"
					   data-id="<?php echo htmlspecialchars($draft['id']); ?>"
					   data-title="<?php echo htmlspecialchars($draft['title'] ?: __t('untitled_draft')); ?>">
						<?php _e('delete'); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<script>
const _drafts_i18n = {
	batchNoSelection:      <?php echo json_encode(__t('batch_no_selection')); ?>,
	batchNoSelectionTitle: <?php echo json_encode(__t('batch_no_selection_title')); ?>,
	deleteDraftsConfirm:   <?php echo json_encode(__t('delete_drafts_confirm')); ?>,
	confirmBatchDeletion:  <?php echo json_encode(__t('confirm_batch_deletion')); ?>,
	deleteDraftConfirm:    <?php echo json_encode(__t('delete_draft_confirm')); ?>,
	confirmDeletion:       <?php echo json_encode(__t('confirm_deletion')); ?>,
	purgeAllConfirm:       <?php echo json_encode(__t('purge_all_confirm')); ?>,
	purgeAllDrafts:        <?php echo json_encode(__t('purge_all_drafts')); ?>,
	delete:                <?php echo json_encode(__t('delete')); ?>,
	deleteAll:             <?php echo json_encode(__t('delete_all')); ?>,
	cancel:                <?php echo json_encode(__t('cancel')); ?>
};

let batchMode = false;
const selectedDrafts = new Set();

document.getElementById('enable-batch')?.addEventListener('click', function() {
	batchMode = true;
	document.querySelectorAll('.batch-checkbox-cell').forEach(el => el.style.display = '');
	document.getElementById('batch-actions').style.display = 'flex';
	this.style.display = 'none';
	document.getElementById('purge-all-btn').style.display = 'none';
});

document.getElementById('cancel-batch')?.addEventListener('click', function() {
	batchMode = false;
	selectedDrafts.clear();
	document.querySelectorAll('.batch-checkbox-cell').forEach(el => el.style.display = 'none');
	document.querySelectorAll('.batch-item').forEach(cb => cb.checked = false);
	document.getElementById('batch-actions').style.display = 'none';
	document.getElementById('enable-batch').style.display = '';
	document.getElementById('purge-all-btn').style.display = '';
	document.getElementById('selected-count').textContent = '0';
});

document.querySelectorAll('.batch-item').forEach(checkbox => {
	checkbox.addEventListener('change', function() {
		const draftId = this.getAttribute('data-id');
		if (this.checked) { selectedDrafts.add(draftId); } else { selectedDrafts.delete(draftId); }
		document.getElementById('selected-count').textContent = selectedDrafts.size;
	});
});

document.getElementById('batch-delete-btn')?.addEventListener('click', function() {
	if (selectedDrafts.size === 0) {
		window.showModal(_drafts_i18n.batchNoSelection, _drafts_i18n.batchNoSelectionTitle, { confirmText: 'OK', danger: false });
		return;
	}
	const form = document.getElementById('batch-delete-form');
	form.innerHTML = '';
	Array.from(selectedDrafts).forEach(draftId => {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'selected_drafts[]';
		input.value = draftId;
		form.appendChild(input);
	});
	window.showModal(
		_drafts_i18n.deleteDraftsConfirm.replace('%d', selectedDrafts.size),
		_drafts_i18n.confirmBatchDeletion,
		{ showCancel: true, confirmText: _drafts_i18n.delete, cancelText: _drafts_i18n.cancel, danger: true, onConfirm: () => form.submit() }
	);
});

document.querySelectorAll('.delete-draft-btn').forEach(button => {
	button.addEventListener('click', function() {
		const draftId = this.getAttribute('data-id');
		const title = this.getAttribute('data-title');
		showModal(
			_drafts_i18n.deleteDraftConfirm.replace('%s', title),
			_drafts_i18n.confirmDeletion,
			{ showCancel: true, confirmText: _drafts_i18n.delete, cancelText: _drafts_i18n.cancel, danger: true,
			  onConfirm: () => window.location.href = 'index.php?action=drafts&draft_action=delete&id=' + encodeURIComponent(draftId) }
		);
	});
});

document.getElementById('purge-all-btn')?.addEventListener('click', function() {
	showModal(
		_drafts_i18n.purgeAllConfirm.replace('%d', <?php echo count($drafts); ?>),
		_drafts_i18n.purgeAllDrafts,
		{ showCancel: true, confirmText: _drafts_i18n.deleteAll, cancelText: _drafts_i18n.cancel, danger: true,
		  onConfirm: () => window.location.href = 'index.php?action=drafts&draft_action=purge_all' }
	);
});
</script>