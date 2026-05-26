// Common UI functions and utilities for the Synaptik CMS admin
// Includes centralized modal system and other shared functionality
window.t = (key, fallback) => window.CMS_LANG?.[key] ?? fallback ?? key;

// Global showModal function
window.showModal = function(message, title = window.t('notification'), options = {}) {
	let modal = document.getElementById('global-modal');

	if (!modal) {
		const modalHTML = `
	  <div id="global-modal" class="modal-overlay" style="display: none;">
		<div class="modal-container">
		  <div class="modal-header">
			<span class="modal-title">Notification</span>
			<span class="modal-close">&times;</span>
		  </div>
		  <div class="modal-content">
			<p id="modal-message"></p>
		  </div>
		  <div class="modal-footer">
			<!-- Buttons will be inserted dynamically -->
		  </div>
		</div>
	  </div>
	`;
		document.body.insertAdjacentHTML('beforeend', modalHTML);
		modal = document.getElementById('global-modal');

		const closeBtn = modal.querySelector('.modal-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', function() {
				modal.style.display = 'none';
			});
		}

		modal.addEventListener('click', function(e) {
			if (e.target === modal) {
				modal.style.display = 'none';
			}
		});
	}

	const modalMessage = document.getElementById('modal-message');
	const modalTitle   = modal.querySelector('.modal-title');
	const modalFooter  = modal.querySelector('.modal-footer');
	const modalHeader  = modal.querySelector('.modal-header');

	if (!modalMessage || !modalTitle || !modalFooter) {
		alert(message);
		return;
	}

	modalMessage.innerHTML  = message;
	modalTitle.textContent  = title;
	modalHeader.className   = 'modal-header' + (options.danger ? ' danger' : '');
	modal.className         = 'modal-overlay' + (options.modalClass ? ' ' + options.modalClass : '');

	modalFooter.innerHTML = '';

	if (options.showCancel) {
		const cancelButton = document.createElement('button');
		cancelButton.className   = 'modal-button modal-cancel';
		cancelButton.textContent = options.cancelText || window.t('cancel');
		cancelButton.onclick = function() {
			modal.style.display = 'none';
			if (typeof options.onCancel === 'function') options.onCancel();
		};
		modalFooter.appendChild(cancelButton);

		const confirmButton = document.createElement('button');
		confirmButton.className   = 'modal-button modal-confirm' + (options.danger ? ' danger' : '');
		confirmButton.textContent = options.confirmText || window.t('confirm');
		confirmButton.onclick = function() {
			if (typeof options.onConfirm === 'function') {
				try { options.onConfirm(); } catch (error) {}
			}
			modal.style.display = 'none';
		};
		modalFooter.appendChild(confirmButton);
	} else {
		const okButton = document.createElement('button');
		okButton.className   = 'modal-button modal-confirm';
		okButton.textContent = options.confirmText || window.t('confirm_ok');
		okButton.onclick = function() {
			if (typeof options.onConfirm === 'function') {
				try { options.onConfirm(); } catch (error) {}
			}
			modal.style.display = 'none';
		};
		modalFooter.appendChild(okButton);
	}

	modal.style.display = 'block';
	return modal;
};

window.closeModal = function() {
	const modal = document.getElementById('global-modal');
	if (modal) modal.style.display = 'none';
};

window.formatFileSize = function(bytes) {
	const units = ['B', 'KB', 'MB', 'GB', 'TB'];
	let size = bytes, unitIndex = 0;
	while (size >= 1024 && unitIndex < units.length - 1) {
		size /= 1024;
		unitIndex++;
	}
	return `${size.toFixed(2)} ${units[unitIndex]}`;
};

window.generateId = function(prefix = 'id') {
	return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
};

window.getFileUrl = function(path) {
	var baseUrl     = window.location.protocol + '//' + window.location.host;
	var currentPath = window.location.pathname;
	var adminPos    = currentPath.indexOf('/admin/');
	var basePath    = adminPos !== -1 ? currentPath.substring(0, adminPos) : '';

	if (path.startsWith('files/')) {
		// already correct
	} else if (path.startsWith('../files/')) {
		path = path.substring(3);
	} else {
		path = 'files/' + path.replace(/^\/+/, '');
	}
	return baseUrl + basePath + '/' + path;
};

/**
 * Batch operations — works in two modes:
 *
 * Virtual-render mode (content-list pages with #cl-data):
 *   Toggling batch mode sets the global _clBatchMode flag and calls
 *   renderList(), which re-generates cards/rows with checkboxes visible.
 *   querySelectorAll on .batch-checkbox-cell is therefore always safe
 *   because the DOM is freshly built by renderList() before any
 *   post-toggle query runs.
 *
 * Legacy mode (other admin pages without #cl-data):
 *   Original querySelectorAll approach is used unchanged.
 */
function initBatchOperations() {
	const enableBatchBtn    = document.getElementById('enable-batch');
	const cancelBatchBtn    = document.getElementById('cancel-batch');
	const batchActionsDiv   = document.getElementById('batch-actions');
	const batchForm         = document.getElementById('batch-delete-form');
	const selectedCountEl   = document.getElementById('selected-count');
	const batchDeleteBtn    = document.getElementById('batch-delete-btn');

	if (!enableBatchBtn) return;

	// Detect whether we are on a virtual-render content-list page
	const isVirtualList = !!document.getElementById('cl-data');

	function showCheckboxes() {
		if (isVirtualList) {
			// _clBatchMode is declared in panel.js; renderList() re-builds DOM with checkboxes shown
			if (typeof _clBatchMode !== 'undefined') {
				_clBatchMode = true; // eslint-disable-line no-undef
			}
			if (typeof renderList === 'function') renderList();
		} else {
			document.querySelectorAll('.batch-checkbox-cell').forEach(cell => {
				cell.style.display = 'table-cell';
			});
			document.querySelectorAll('.content-card, .content-row').forEach(item => {
				item.classList.add('batch-mode');
			});
			const table = document.querySelector('.content-list-container table');
			if (table) table.classList.add('batch-mode');
		}
	}

	function hideCheckboxes() {
		if (isVirtualList) {
			if (typeof _clBatchMode !== 'undefined') {
				_clBatchMode = false; // eslint-disable-line no-undef
			}
			if (typeof renderList === 'function') renderList();
		} else {
			document.querySelectorAll('.batch-checkbox-cell').forEach(cell => {
				cell.style.display = 'none';
			});
			document.querySelectorAll('.batch-item').forEach(cb => { cb.checked = false; });
			document.querySelectorAll('.content-card, .content-row').forEach(item => {
				item.classList.remove('batch-mode');
			});
			const table = document.querySelector('.content-list-container table');
			if (table) table.classList.remove('batch-mode');
		}
	}

	enableBatchBtn.addEventListener('click', function() {
		if (batchActionsDiv) batchActionsDiv.style.display = 'flex';
		this.style.display = 'none';
		showCheckboxes();
	});

	if (cancelBatchBtn) {
		cancelBatchBtn.addEventListener('click', function() {
			if (batchActionsDiv) batchActionsDiv.style.display = 'none';
			enableBatchBtn.style.display = 'inline-block';
			if (selectedCountEl) selectedCountEl.textContent = '0';
			hideCheckboxes();
		});
	}

	// Delegated change listener — works on dynamically generated checkboxes
	document.addEventListener('change', function(e) {
		if (e.target && e.target.classList.contains('batch-item')) {
			const selectedCount = document.querySelectorAll('.batch-item:checked').length;
			if (selectedCountEl) selectedCountEl.textContent = selectedCount;
		}
	});

	if (batchDeleteBtn && batchForm) {
		batchDeleteBtn.addEventListener('click', function() {
			const selectedItems = Array.from(document.querySelectorAll('.batch-item:checked'))
				.map(cb => cb.getAttribute('data-index'));

			if (selectedItems.length === 0) {
				window.showModal(window.t('batch_no_items'), window.t('batch_no_items_title'));
				return;
			}

			window.showModal(
				window.t('batch_delete_items_confirm').replace('%d', selectedItems.length),
				window.t('confirm_batch_deletion'), {
					showCancel:  true,
					confirmText: window.t('delete_selected'),
					cancelText:  window.t('cancel'),
					danger:      true,
					onConfirm: function() {
						const inputField = document.getElementById('selected-items-input');
						if (inputField) inputField.value = JSON.stringify(selectedItems);
						batchForm.submit();
					},
				}
			);
		});
	}
}

window.confirmCancel = function(event) {
	event.preventDefault();

	const form       = document.querySelector('form');
	const hasTitle   = form && form.querySelector('[name="title"]') && form.querySelector('[name="title"]').value;
	const hasContent = document.getElementById('content') && document.getElementById('content').value;

	if (hasTitle || hasContent) {
		window.showModal(
			window.t('confirm_cancel'),
			window.t('confirm_cancellation'), {
				showCancel:  true,
				confirmText: window.t('discard_changes'),
				cancelText:  window.t('continue_editing'),
				danger:      true,
				onConfirm:   function() { window.location.href = 'index.php'; },
			}
		);
	} else {
		window.location.href = 'index.php';
	}
	return false;
};

document.addEventListener('DOMContentLoaded', function() {

	// const mainContent = document.querySelector('main');
	// if (mainContent) mainContent.classList.add('fade-in');

	initBatchOperations();

	// Auto-hide success messages after 5 seconds
	const messages = document.querySelectorAll('.message:not(.error)');
	if (messages.length > 0) {
		setTimeout(function() {
			messages.forEach(function(msg) {
				msg.style.opacity = '0';
				setTimeout(function() { msg.style.display = 'none'; }, 500);
			});
		}, 5000);
	}

	// Global event delegation
	document.addEventListener('click', function(e) {

		// Delete buttons
		if (e.target.classList.contains('delete-btn') || e.target.closest('.delete-btn')) {
			e.preventDefault();
			const button = e.target.classList.contains('delete-btn') ? e.target : e.target.closest('.delete-btn');
			const type   = button.getAttribute('data-type');
			const index  = button.getAttribute('data-index');
			const title  = button.getAttribute('data-title');

			window.showModal(
				window.t('delete_item_confirm').replace('%s', title),
				window.t('confirm_deletion'), {
					showCancel:  true,
					confirmText: window.t('delete'),
					cancelText:  window.t('cancel'),
					danger:      true,
					onConfirm:   function() {
						window.location.href = `index.php?action=delete&type=${type}&index=${index}`;
					},
				}
			);
		}

		// Category deletion
		if (e.target.classList.contains('delete-category') || e.target.closest('.delete-category')) {
			e.preventDefault();
			const link = e.target.classList.contains('delete-category') ? e.target : e.target.closest('.delete-category');
			const slug = link.getAttribute('data-slug');
			const name = link.getAttribute('data-name');

			window.showModal(
				window.t('delete_category_confirm').replace('%s', name),
				window.t('confirm_deletion'), {
					showCancel:  true,
					confirmText: window.t('delete'),
					cancelText:  window.t('cancel'),
					danger:      true,
					onConfirm:   function() {
						window.location.href = `index.php?action=manage_categories&category_action=delete&slug=${slug}`;
					},
				}
			);
		}

		// Tag deletion
		if (e.target.classList.contains('delete-tag') || e.target.closest('.delete-tag')) {
			e.preventDefault();
			const link = e.target.classList.contains('delete-tag') ? e.target : e.target.closest('.delete-tag');
			const slug = link.getAttribute('data-slug');
			const name = link.getAttribute('data-name');

			window.showModal(
				window.t('delete_tag_confirm').replace('%s', name),
				window.t('confirm_deletion'), {
					showCancel:  true,
					confirmText: window.t('delete'),
					cancelText:  window.t('cancel'),
					danger:      true,
					onConfirm:   function() {
						window.location.href = `index.php?action=manage_tags&tag_action=delete&slug=${slug}`;
					},
				}
			);
		}

		// Purge orphans
		if (e.target.classList.contains('purge-btn') || e.target.closest('.purge-btn')) {
			e.preventDefault();
			const btn    = e.target.classList.contains('purge-btn') ? e.target : e.target.closest('.purge-btn');
			const formId = btn.getAttribute('data-form');
			const msg    = btn.getAttribute('data-confirm');
			const form   = document.getElementById(formId);

			window.showModal(msg, window.t('confirm_deletion'), {
				showCancel:  true,
				confirmText: window.t('delete'),
				cancelText:  window.t('cancel'),
				danger:      true,
				onConfirm:   function() { form.submit(); },
			});
		}

		// Merge tags / categories
		if (e.target.classList.contains('merge-btn') || e.target.closest('.merge-btn')) {
			e.preventDefault();
			const btn    = e.target.classList.contains('merge-btn') ? e.target : e.target.closest('.merge-btn');
			const formId = btn.getAttribute('data-form');
			const msg    = btn.getAttribute('data-confirm');
			const form   = document.getElementById(formId);

			window.showModal(msg, window.t('merge'), {
				showCancel:  true,
				confirmText: window.t('merge'),
				cancelText:  window.t('cancel'),
				danger:      true,
				onConfirm:   function() { form.submit(); },
			});
		}
	});
});