const t = window.t || ((key, fb) => window.CMS_LANG?.[key] ?? fb ?? key);

document.addEventListener('DOMContentLoaded', function() {
	const selectAllBtn = document.getElementById('select-all-btn');
	if (!selectAllBtn) {
		return;
	}
	const fileGrid = document.querySelector('.file-grid');
	const uploadForm = document.getElementById('upload-form');
	const fileInput = document.getElementById('file-input');
	const dropzone = document.getElementById('dropzone');
	const contextMenu = document.getElementById('context-menu');
	const renameDialog = document.getElementById('rename-dialog');
	const selectionIndicator = document.getElementById('selection-indicator');

	// State management
	let selectedItems = [];
	let isDragging = false;
	let draggedItems = [];
	let isSelecting = false;
	let selectionBox = null;
	let selectionBoxStart = { x: 0, y: 0 };
	let currentPath = getCurrentPath();

	function getCurrentPath() {
		const url = new URL(window.location.href);
		return url.searchParams.get('path') || '';
	}

	// Make breadcrumb links droppable for moving files to parent folders
	function initBreadcrumbDropTargets() {
		document.querySelectorAll('.breadcrumbs a').forEach(crumb => {
			const targetPath = new URL(crumb.href).searchParams.get('path') || '';

			crumb.addEventListener('dragover', function(e) {
				if (!window.isDragging || !window.draggedItems || !window.draggedItems.length) return;
				e.preventDefault();
				e.stopPropagation();
				this.classList.add('breadcrumb-drag-over');
				e.dataTransfer.dropEffect = 'move';
			});

			crumb.addEventListener('dragleave', function(e) {
				this.classList.remove('breadcrumb-drag-over');
			});

			crumb.addEventListener('drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				this.classList.remove('breadcrumb-drag-over');

				if (!window.isDragging || !window.draggedItems || !window.draggedItems.length) return;

				const itemsToMove = window.draggedItems.map(item => ({
					type: item.classList.contains('file-item') ? 'file' : 'folder',
					name: item.querySelector(item.classList.contains('file-item') ? '.file-name' : '.folder-name').textContent
				}));

				moveItems(itemsToMove, targetPath, true);
			});
		});
	}

	// Ensure window-level variables are accessible
	window.isDragging = false;
	window.draggedItems = [];

	function moveItems(items, targetFolder, isAbsolutePath = false) {
		if (!items || items.length === 0) return;

		showModal(t('fm_moving'), t('fm_please_wait'), {
			showCancel: false,
			confirmText: null
		});

		const currentPath = new URL(window.location.href).searchParams.get('path') || '';

		const formData = new FormData();
		formData.append('action', 'move');
		formData.append('items', JSON.stringify(items));
		formData.append('current_path', currentPath);

		if (isAbsolutePath) {
			// For breadcrumb drops, targetFolder is an absolute path
			if (targetFolder === '') {
				formData.append('target_folder', 'root');
			} else {
				formData.append('target_path', targetFolder);
			}
		} else {
			// For in-grid folder drops, targetFolder is a folder name relative to current path
			formData.append('target_folder', targetFolder);
		}

		fetch('file-manager.php?path=' + encodeURIComponent(currentPath), {
				method: 'POST',
				body: formData
			})
			.then(response => {
				if (!response.ok) throw new Error('Server returned status ' + response.status);
				return response.json();
			})
			.then(data => {
				if (data.success) {
					window.location.reload();
				} else {
					showModal(data.message || t('fm_move_failed'), t('error'));
				}
			})
			.catch(error => {
				console.error('Error moving items:', error);
				showModal(t('error') + ': ' + error.message, t('fm_move_failed_title'));
			});
	}

	// Initialize breadcrumb drop targets on page load
	initBreadcrumbDropTargets();

	// Collapsible upload/folder controls panel
	(function() {
		const toggleBtn = document.getElementById('fm-controls-toggle');
		const panel     = document.getElementById('fm-controls-panel');
		const icon      = document.getElementById('fm-controls-toggle-icon');
		if (!toggleBtn || !panel) return;

		const LS_KEY = 'fm-controls-open';

		function applyState(open) {
			if (open) {
				panel.style.maxHeight = panel.scrollHeight + 'px';
				panel.style.opacity   = '1';
				icon.textContent      = '▼';
				// After the opening transition ends, remove the max-height constraint
				// so dynamic content (progress bars, file lists) can grow freely
				panel.addEventListener('transitionend', function onEnd(ev) {
					if (ev.propertyName !== 'max-height') return;
					panel.style.maxHeight = 'none';
					panel.style.overflow  = 'visible';
					panel.removeEventListener('transitionend', onEnd);
				});
			} else {
				// Re-constrain before animating shut
				panel.style.overflow  = 'hidden';
				panel.style.maxHeight = panel.scrollHeight + 'px';
				// Force a reflow so the browser registers the explicit value before we animate to 0
				void panel.offsetHeight;
				panel.style.maxHeight = '0';
				panel.style.opacity   = '0';
				icon.textContent      = '▶';
			}
		}

		// Prepare panel for CSS transition
		panel.style.overflow   = 'hidden';
		panel.style.transition = 'max-height 0.3s ease, opacity 0.25s ease';

		// Restore saved state (default: collapsed)
		const saved  = localStorage.getItem(LS_KEY);
		const isOpen = saved === 'true';

		// Apply immediately without transition on load
		panel.style.transition = 'none';
		if (isOpen) {
			panel.style.maxHeight = 'none';
			panel.style.overflow  = 'visible';
			panel.style.opacity   = '1';
			icon.textContent      = '▼';
		} else {
			panel.style.maxHeight = '0';
			panel.style.opacity   = '0';
			icon.textContent      = '▶';
		}
		requestAnimationFrame(() => {
			panel.style.transition = 'max-height 0.3s ease, opacity 0.25s ease';
		});

		toggleBtn.addEventListener('click', function() {
			const nowOpen = panel.style.maxHeight === '0px' || panel.style.maxHeight === '';
			applyState(nowOpen);
			localStorage.setItem(LS_KEY, nowOpen ? 'true' : 'false');
		});
	})();

	// Send a CSRF-protected POST delete request (file or folder)
	function postDelete(name, type, force = false) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = 'file-manager.php?path=' + encodeURIComponent(currentPath);
		const fields = {
			[type === 'folder' ? 'delete_folder' : 'delete_file']: name,
			csrf_token: window.CMS_CSRF_TOKEN || '',
		};
		if (force) fields['force'] = '1';
		Object.entries(fields).forEach(([k, v]) => {
			const inp = document.createElement('input');
			inp.type = 'hidden'; inp.name = k; inp.value = v;
			form.appendChild(inp);
		});
		document.body.appendChild(form);
		form.submit();
	}


	function confirmDeleteFolder(url, foldername) {
		const folderItem = document.querySelector(`.folder-item[data-name="${foldername}"]`);
		const hasContents = folderItem && folderItem.getAttribute('data-has-contents') === 'true';

		let message = t('fm_delete_folder_confirm').replace('%s', foldername);
		let deleteUrl = url;

		if (hasContents) {
			message += '<br><br><span style="color: var(--danger-text); font-weight: bold;">' + t('fm_delete_folder_warning') + '</span>';
			if (deleteUrl.indexOf('?') !== -1) {
				deleteUrl += '&force=1';
			} else {
				deleteUrl += '?force=1';
			}
		}

		showModal(
			message,
			t('fm_confirm_folder_deletion'), {
				showCancel: true,
				confirmText: t('fm_delete_folder_btn'),
				cancelText: t('cancel'),
				danger: true,
				onConfirm: function() {
					postDelete(foldername, 'folder', hasContents);
				}
			}
		);

		return false;
	}

	// Delete file modal confirmation
	function confirmDelete(url, filename) {
		showModal(
			t('delete_item_confirm').replace('%s', filename),
			t('confirm_deletion'), {
				showCancel: true,
				confirmText: t('delete'),
				cancelText: t('cancel'),
				danger: true,
				onConfirm: function() {
					postDelete(filename, 'file');
				}
			}
		);
		return false;
	}

	// Add function to initialize action buttons
	function initializeActionButtons() {
		// Initialize rename buttons
		document.querySelectorAll('.rename-btn').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const name = this.getAttribute('data-name');
				const type = this.getAttribute('data-type');

				const items = document.querySelectorAll(type === 'file' ? '.file-item' : '.folder-item');
				let targetItem = null;

				items.forEach(item => {
					const itemName = item.querySelector(type === 'file' ? '.file-name' : '.folder-name').textContent;
					if (itemName === name) {
						targetItem = item;
					}
				});

				if (targetItem) {
					showRenameDialog(targetItem);
				}
			});
		});
		
		// Initialize delete folder buttons
		document.querySelectorAll('.folder-item a[href*="delete_folder="]').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const url = this.getAttribute('href');
				const foldername = this.closest('.folder-item').querySelector('.folder-name').textContent;

				confirmDeleteFolder(url, foldername);
			});
		});

		// Initialize copy URL buttons
		document.querySelectorAll('.copy-url-btn').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const url = this.getAttribute('data-url');
				copyToClipboard(url);
			});
		});

		// Initialize delete buttons
		document.querySelectorAll('.delete-file-link, .delete-folder-link').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const url = this.getAttribute('href');
				const name = this.closest('.file-item, .folder-item').querySelector('.file-name, .folder-name').textContent;
				const isFolder = this.classList.contains('delete-folder-link');

				if (isFolder) {
					confirmDeleteFolder(url, name);
				} else {
					confirmDelete(url, name);
				}
			});
		});
	}

	// Call this function on initial page load
	initializeActionButtons();

	// View mode toggle
	const toggleViewBtn = document.getElementById('toggle-view-mode');
	if (toggleViewBtn) {
		toggleViewBtn.addEventListener('click', function() {
			const fileGrid = document.querySelector('.file-grid');
			const currentMode = this.getAttribute('data-mode');

			if (currentMode === 'grid') {
				fileGrid.classList.add('list-view');
				this.setAttribute('data-mode', 'list');
				this.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>' + t('fm_grid_view');
			} else {
				fileGrid.classList.remove('list-view');
				this.setAttribute('data-mode', 'grid');
				this.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>' + t('fm_list_view');
			}

			localStorage.setItem('file-manager-view', currentMode === 'grid' ? 'list' : 'grid');

			// — Animation zoom/scale sur chaque item au changement de vue —
			const items = fileGrid.querySelectorAll('.file-item, .folder-item');
			items.forEach((item, i) => {
				item.style.setProperty('--fm-stagger-index', i);
			});
			fileGrid.classList.remove('view-switching');
			void fileGrid.offsetWidth; // force reflow pour relancer l'animation
			fileGrid.classList.add('view-switching');
			const maxDelay = items.length * 18 + 280;
			clearTimeout(fileGrid._viewSwitchTimeout);
			fileGrid._viewSwitchTimeout = setTimeout(() => {
				fileGrid.classList.remove('view-switching');
			}, maxDelay);
		});

		// Apply saved view preference
		const savedView = localStorage.getItem('file-manager-view');
		if (savedView === 'list') {
			toggleViewBtn.click();
		}
	}

	// Initialize dragover/drop events for folders
	initializeFolderDropTargets();

	// Context menu functionality
	if (fileGrid) {
		fileGrid.addEventListener('contextmenu', function(e) {
			e.preventDefault();

			const fileItem = e.target.closest('.file-item');
			const folderItem = e.target.closest('.folder-item');
			const targetItem = fileItem || folderItem;

			if (targetItem) {
				if (!targetItem.classList.contains('selected')) {
					if (!e.ctrlKey && !e.metaKey) {
						clearSelection();
					}
					targetItem.classList.add('selected');
					selectedItems.push(targetItem);
					updateSelectionIndicator();
				}

				contextMenu.style.left = e.pageX + 'px';
				contextMenu.style.top = e.pageY + 'px';
				contextMenu.style.display = 'block';

				const isFile = targetItem.classList.contains('file-item');
				document.querySelectorAll('.context-menu .file-only').forEach(item => {
					item.style.display = isFile ? 'flex' : 'none';
				});
			}
		});

		document.addEventListener('click', function() {
			contextMenu.style.display = 'none';
		});

		contextMenu.addEventListener('click', function(e) {
			const actionElement = e.target.closest('[data-action]');
			if (!actionElement) return;

			const action = actionElement.getAttribute('data-action');

			switch (action) {
				case 'rename':
					if (selectedItems.length === 1) {
						showRenameDialog(selectedItems[0]);
					}
					break;
				case 'delete':
					deleteSelectedItems();
					break;
				case 'copy-url':
					if (selectedItems.length === 1 && selectedItems[0].classList.contains('file-item')) {
						let fileUrl;
						const copyUrlBtn = selectedItems[0].querySelector('.copy-url-btn');
						if (copyUrlBtn) {
							fileUrl = copyUrlBtn.getAttribute('data-url');
						} else {
							const viewLink = selectedItems[0].querySelector('a[target="_blank"]');
							if (viewLink) {
								fileUrl = viewLink.getAttribute('href');
							}
						}
						if (fileUrl) {
							copyToClipboard(fileUrl);
						}
					}
					break;
				case 'view':
					if (selectedItems.length === 1 && selectedItems[0].classList.contains('file-item')) {
						const viewLink = selectedItems[0].querySelector('a[target="_blank"]');
						if (viewLink) {
							window.open(viewLink.getAttribute('href'), '_blank');
						}
					}
					break;
			}

			contextMenu.style.display = 'none';
		});

		initializeRenameDialog();
		initializeMultipleSelection();
		initializeDragAndDrop();

		document.getElementById('delete-selected').addEventListener('click', function() {
			deleteSelectedItems();
		});

		document.getElementById('cancel-selection').addEventListener('click', function() {
			clearSelection();
		});
	}

	// Rename dialog initialization
	function initializeRenameDialog() {
		const closeBtn = renameDialog.querySelector('.modal-close');
		const cancelBtn = document.getElementById('rename-cancel');
		const confirmBtn = document.getElementById('rename-confirm');
		const renameForm = document.getElementById('rename-form');
		const newNameInput = document.getElementById('rename-new-name');

		closeBtn.addEventListener('click', function() {
			renameDialog.style.display = 'none';
		});

		cancelBtn.addEventListener('click', function() {
			renameDialog.style.display = 'none';
		});

		confirmBtn.addEventListener('click', function() {
			const newName = newNameInput.value.trim();
			const itemType = document.getElementById('rename-item-type').value;
			const originalName = document.getElementById('rename-original-name').value;

			if (newName && newName !== originalName) {
				renameItem(itemType, originalName, newName);
			}

			renameDialog.style.display = 'none';
		});

		renameForm.addEventListener('submit', function(e) {
			e.preventDefault();
			confirmBtn.click();
		});

		renameDialog.addEventListener('click', function(e) {
			if (e.target === renameDialog) {
				renameDialog.style.display = 'none';
			}
		});
	}

	function showRenameDialog(item) {
		const isFile = item.classList.contains('file-item');
		const itemType = isFile ? 'file' : 'folder';
		const itemName = isFile ?
			item.querySelector('.file-name').textContent :
			item.querySelector('.folder-name').textContent;

		item.classList.add('renaming');
		setTimeout(() => item.classList.remove('renaming'), 3000);

		document.getElementById('rename-item-type').value = itemType;
		document.getElementById('rename-original-name').value = itemName;

		let nameWithoutExt = itemName;
		let extension = '';

		if (isFile && itemName.includes('.')) {
			const parts = itemName.split('.');
			extension = '.' + parts.pop();
			nameWithoutExt = parts.join('.');
		}

		document.getElementById('rename-new-name').value = itemName;
		renameDialog.style.display = 'block';

		const inputField = document.getElementById('rename-new-name');
		inputField.focus();

		if (isFile && extension) {
			inputField.setSelectionRange(0, nameWithoutExt.length);

			const helperText = document.getElementById('rename-helper-text') || document.createElement('div');
			helperText.id = 'rename-helper-text';
			helperText.style.fontSize = '12px';
			helperText.style.marginTop = '4px';
			helperText.style.color = 'var(--text-muted)';
			helperText.textContent = t('file_extension_note').replace('%s', extension);

			if (!document.getElementById('rename-helper-text')) {
				document.getElementById('rename-new-name').parentNode.appendChild(helperText);
			}
		} else {
			inputField.select();

			const helperText = document.getElementById('rename-helper-text');
			if (helperText) {
				helperText.remove();
			}
		}
	}

	function renameItem(itemType, originalName, newName) {
		if (itemType === 'file' && originalName.includes('.')) {
			const origExt = originalName.split('.').pop();
			if (!newName.includes('.')) {
				newName = newName + '.' + origExt;
			}
		}

		const formData = new FormData();
		formData.append('action', 'rename');
		formData.append('item_type', itemType);
		formData.append('original_name', originalName);
		formData.append('new_name', newName);
		formData.append('current_path', currentPath);

		fetch('file-manager.php?path=' + encodeURIComponent(currentPath), {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					window.location.reload();
				} else {
					showModal(data.message || t('rename_error'), t('error'));
				}
			})
			.catch(error => {
				console.error('Error renaming item:', error);
				showModal(t('rename_error'), t('error'));
			});
	}

	// Multiple selection functionality
	function initializeMultipleSelection() {
		if (fileGrid) {
			fileGrid.addEventListener('mousedown', function(e) {
				if (e.button !== 0 || e.target.closest('.context-menu') ||
					e.target.closest('a') || e.target.closest('button')) {
					return;
				}

				if (!e.target.closest('.file-item, .folder-item')) {
					isSelecting = true;
					selectionBoxStart = { x: e.pageX, y: e.pageY };

					selectionBox = document.createElement('div');
					selectionBox.className = 'selection-box';
					selectionBox.style.zIndex = '1000';
					selectionBox.style.border = '2px dashed var(--primary)';
					selectionBox.style.backgroundColor = 'var(--primary-soft)';
					selectionBox.style.position = 'absolute';
					selectionBox.style.pointerEvents = 'none';
					selectionBox.style.left = e.pageX + 'px';
					selectionBox.style.top = e.pageY + 'px';
					document.body.appendChild(selectionBox);

					if (!e.ctrlKey && !e.metaKey) {
						clearSelection();
					}

					e.preventDefault();
				}
			});

			document.addEventListener('mousemove', function(e) {
				if (!isSelecting || !selectionBox) return;

				const left = Math.min(e.pageX, selectionBoxStart.x);
				const top = Math.min(e.pageY, selectionBoxStart.y);
				const width = Math.abs(e.pageX - selectionBoxStart.x);
				const height = Math.abs(e.pageY - selectionBoxStart.y);

				selectionBox.style.left = left + 'px';
				selectionBox.style.top = top + 'px';
				selectionBox.style.width = width + 'px';
				selectionBox.style.height = height + 'px';

				const selectionRect = selectionBox.getBoundingClientRect();
				document.querySelectorAll('.file-item, .folder-item').forEach(item => {
					const itemRect = item.getBoundingClientRect();

					if (
						!(itemRect.right < selectionRect.left ||
							itemRect.left > selectionRect.right ||
							itemRect.bottom < selectionRect.top ||
							itemRect.top > selectionRect.bottom)
					) {
						if (!selectedItems.includes(item)) {
							item.classList.add('selected');
							selectedItems.push(item);
						}
					} else if (!e.ctrlKey && !e.metaKey && selectedItems.includes(item)) {
						item.classList.remove('selected');
						selectedItems = selectedItems.filter(i => i !== item);
					}
				});

				updateSelectionIndicator();
			});

			document.addEventListener('mouseup', function() {
				if (isSelecting && selectionBox) {
					selectionBox.remove();
					selectionBox = null;
					isSelecting = false;
				}
			});

			document.querySelectorAll('.file-item, .folder-item').forEach(item => {
				item.addEventListener('click', function(e) {
					if (e.target.closest('a') || e.target.closest('button') ||
						e.target.tagName === 'INPUT' || e.target.closest('.file-actions')) {
						return;
					}

					if (this.classList.contains('folder-item')) {
						const folderLink = this.querySelector('a[href*="path="]');
						if (folderLink && e.target === folderLink || e.target.closest('a[href*="path="]') === folderLink) {
							if (e.ctrlKey || e.metaKey || e.shiftKey) {
								e.preventDefault();
							} else {
								return;
							}
						}
					}

					if (e.ctrlKey || e.metaKey) {
						if (selectedItems.includes(this)) {
							this.classList.remove('selected');
							selectedItems = selectedItems.filter(i => i !== this);
						} else {
							this.classList.add('selected');
							selectedItems.push(this);
						}
						e.preventDefault();
						e.stopPropagation();
					} else if (e.shiftKey && selectedItems.length > 0) {
						const allItems = Array.from(document.querySelectorAll('.file-item, .folder-item'));
						const lastSelectedIndex = allItems.indexOf(selectedItems[selectedItems.length - 1]);
						const currentIndex = allItems.indexOf(this);
						const start = Math.min(lastSelectedIndex, currentIndex);
						const end = Math.max(lastSelectedIndex, currentIndex);

						if (!e.ctrlKey && !e.metaKey) {
							clearSelection();
						}

						for (let i = start; i <= end; i++) {
							if (!selectedItems.includes(allItems[i])) {
								allItems[i].classList.add('selected');
								selectedItems.push(allItems[i]);
							}
						}
						e.preventDefault();
						e.stopPropagation();
					} else {
						if (selectedItems.includes(this)) {
							this.classList.remove('selected');
							selectedItems = selectedItems.filter(i => i !== this);
						} else {
							clearSelection();
							this.classList.add('selected');
							selectedItems.push(this);
						}
						e.preventDefault();
						e.stopPropagation();
					}

					updateSelectionIndicator();
				});
			});
		}
	}

	document.getElementById('select-all-btn').addEventListener('click', function() {
		const allItems = document.querySelectorAll('.file-item, .folder-item');
		allItems.forEach(item => {
			if (!selectedItems.includes(item)) {
				item.classList.add('selected');
				selectedItems.push(item);
			}
		});
		updateSelectionIndicator();
	});

	// Drag and drop functionality
	function initializeDragAndDrop() {
		document.querySelectorAll('.file-item, .folder-item').forEach(item => {
			item.setAttribute('draggable', 'true');

			// Note: no stopPropagation on mousedown children — Safari needs the event
			// to bubble up to the draggable parent to initiate drag correctly.

			// Prevent the browser from hijacking drag on <img> elements inside thumbnails.
			// Use stopPropagation (not preventDefault) so Safari doesn't cancel the parent dragstart.
			item.querySelectorAll('img').forEach(img => {
				img.setAttribute('draggable', 'false');
				img.addEventListener('dragstart', e => e.stopPropagation());
			});

			item.addEventListener('dragstart', function(e) {
				e.stopPropagation();
				window.isDragging = true;

				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', 'moving');

				// Clear any existing drag states
				document.querySelectorAll('.folder-item').forEach(folder => {
					folder.classList.remove('drag-over');
					const overlay = folder.querySelector('.folder-drag-overlay');
					if (overlay) overlay.style.display = 'none';
				});
				document.querySelectorAll('.breadcrumbs a').forEach(a => {
					a.classList.remove('breadcrumb-drag-over');
				});

				if (!this.classList.contains('selected')) {
					document.querySelectorAll('.file-item.selected, .folder-item.selected').forEach(selected => {
						selected.classList.remove('selected');
					});
					selectedItems = [];
					this.classList.add('selected');
					selectedItems.push(this);
				}

				draggedItems = selectedItems.slice();
				window.draggedItems = draggedItems;

				draggedItems.forEach(item => {
					item.classList.add('dragging');
				});

				// Replace the native drag ghost (which can obscure breadcrumb targets)
				// with a compact canvas pill showing icon + item count/name.
				// Safari requires the canvas to be in the DOM at the time setDragImage is called.
				try {
					const count = draggedItems.length;
					const isFolder = this.classList.contains('folder-item');
					const icon = isFolder ? '\u{1F4C1}' : '\u{1F4C4}';
					const nameEl = this.querySelector('.file-name, .folder-name');
					const name = nameEl ? nameEl.textContent : '';
					const text = count > 1 ? icon + '  ' + count + ' ' + t('items') : icon + '  ' + name;

					const canvas = document.createElement('canvas');
					const padding = 12;
					const fontSize = 13;
					canvas.height = 32;

					// Position off-screen so it's in the DOM but invisible (required by Safari)
					canvas.style.position = 'fixed';
					canvas.style.top = '-200px';
					canvas.style.left = '-200px';
					canvas.style.pointerEvents = 'none';
					document.body.appendChild(canvas);
					window._dragGhostCanvas = canvas;

					const ctx = canvas.getContext('2d');
					ctx.font = fontSize + 'px -apple-system, BlinkMacSystemFont, sans-serif';
					const textWidth = ctx.measureText(text).width;
					canvas.width = Math.min(Math.ceil(textWidth) + padding * 2, 220);

					// Re-set font after canvas resize (resize clears the context state)
					ctx.font = fontSize + 'px -apple-system, BlinkMacSystemFont, sans-serif';

					// Rounded pill background
					const w = canvas.width, h = canvas.height, r = 8;
					ctx.fillStyle = 'rgba(25, 25, 25, 0.85)';
					ctx.beginPath();
					ctx.moveTo(r, 0);
					ctx.lineTo(w - r, 0);
					ctx.quadraticCurveTo(w, 0, w, r);
					ctx.lineTo(w, h - r);
					ctx.quadraticCurveTo(w, h, w - r, h);
					ctx.lineTo(r, h);
					ctx.quadraticCurveTo(0, h, 0, h - r);
					ctx.lineTo(0, r);
					ctx.quadraticCurveTo(0, 0, r, 0);
					ctx.closePath();
					ctx.fill();

					// Label
					ctx.fillStyle = '#ffffff';
					ctx.textBaseline = 'middle';
					ctx.fillText(text, padding, h / 2);

					e.dataTransfer.setDragImage(canvas, canvas.width / 2, canvas.height / 2);
				} catch (err) {
					// Silently fall back to native ghost if canvas fails
				}
			});
		});

		document.addEventListener('dragend', function() {
			window.isDragging = false;

			// Remove the ghost canvas we attached to the DOM for Safari compatibility
			if (window._dragGhostCanvas) {
				window._dragGhostCanvas.remove();
				window._dragGhostCanvas = null;
			}

			if (window.draggedItems) {
				window.draggedItems.forEach(item => {
					item.classList.remove('dragging');
				});
			}

			document.querySelectorAll('.folder-item').forEach(folder => {
				folder.classList.remove('drag-over');
				const overlay = folder.querySelector('.folder-drag-overlay');
				if (overlay) overlay.style.display = 'none';
			});
			document.querySelectorAll('.breadcrumbs a').forEach(a => {
				a.classList.remove('breadcrumb-drag-over');
			});

			window.draggedItems = [];
			draggedItems = [];
		});
	}

	function initializeFolderDropTargets() {
		document.querySelectorAll('.folder-item').forEach(folder => {
			if (!folder.querySelector('.folder-drag-overlay')) {
				const overlay = document.createElement('div');
				overlay.className = 'folder-drag-overlay';
				overlay.textContent = t('fm_drop_here');
				folder.style.position = 'relative';
				folder.appendChild(overlay);
			}

			if (folder._dragoverHandler) folder.removeEventListener('dragover', folder._dragoverHandler);
			if (folder._dragleaveHandler) folder.removeEventListener('dragleave', folder._dragleaveHandler);
			if (folder._dropHandler) folder.removeEventListener('drop', folder._dropHandler);

			folder._dragoverHandler = function(e) {
				e.preventDefault();
				e.stopPropagation();

				if (!window.isDragging || !window.draggedItems || !window.draggedItems.length) return;

				let canDrop = true;
				window.draggedItems.forEach(item => {
					if (item === this) canDrop = false;
				});

				if (canDrop) {
					this.classList.add('drag-over');
					const overlay = this.querySelector('.folder-drag-overlay');
					if (overlay) overlay.style.display = 'flex';
					e.dataTransfer.dropEffect = 'move';
				}
			};

			folder._dragleaveHandler = function(e) {
				e.preventDefault();
				e.stopPropagation();
				const relatedTarget = e.relatedTarget;
				if (!this.contains(relatedTarget)) {
					this.classList.remove('drag-over');
					const overlay = this.querySelector('.folder-drag-overlay');
					if (overlay) overlay.style.display = 'none';
				}
			};

			folder._dropHandler = function(e) {
				e.preventDefault();
				e.stopPropagation();

				this.classList.remove('drag-over');
				const overlay = this.querySelector('.folder-drag-overlay');
				if (overlay) overlay.style.display = 'none';

				if (!window.draggedItems || !window.draggedItems.length) return;

				const targetFolder = this.querySelector('.folder-name').textContent;

				const itemsToMove = window.draggedItems.map(item => ({
					type: item.classList.contains('file-item') ? 'file' : 'folder',
					name: item.querySelector(item.classList.contains('file-item') ? '.file-name' : '.folder-name').textContent
				}));

				moveItems(itemsToMove, targetFolder);

				window.isDragging = false;
				window.draggedItems.forEach(item => item.classList.remove('dragging'));
				window.draggedItems = [];
			};

			folder.addEventListener('dragover', folder._dragoverHandler);
			folder.addEventListener('dragleave', folder._dragleaveHandler);
			folder.addEventListener('drop', folder._dropHandler);
		});

		// Make file grid drop target for files dragged from outside the browser
		if (fileGrid) {
			fileGrid.addEventListener('dragover', function(e) {
				if (!isDragging) {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'copy';
					this.classList.add('grid-drag-over');
				} else {
					this.classList.remove('grid-drag-over');
				}
			});

			fileGrid.addEventListener('dragleave', function(e) {
				if (!isDragging && !e.target.closest('.file-grid')) {
					this.classList.remove('grid-drag-over');
				}
			});

			fileGrid.addEventListener('drop', function(e) {
				if (!isDragging) {
					e.preventDefault();
					this.classList.remove('grid-drag-over');

					if (e.dataTransfer.files.length > 0 && fileInput && uploadForm) {
						fileInput.files = e.dataTransfer.files;
						if (dropzone) {
							dropzone.textContent = t('fm_files_selected').replace('%d', e.dataTransfer.files.length);
						}
						uploadForm.dispatchEvent(new Event('submit'));
					}
				}
			});
		}
	}

	// Helper function to refresh selection events
	function refreshSelectionEvents() {
		if (isSelecting && selectionBox) {
			selectionBox.remove();
			selectionBox = null;
			isSelecting = false;
		}
		initializeMultipleSelection();
		initializeDragAndDrop();
		initializeFolderDropTargets();
		initializeActionButtons();
	}

	// Keyboard shortcut support
	document.addEventListener('keydown', function(e) {
		if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
			return;
		}

		if ((e.key === 'Delete' || e.key === 'Backspace') && selectedItems.length > 0) {
			e.preventDefault();
			deleteSelectedItems();
		}

		if (e.key === 'a' && (e.ctrlKey || e.metaKey)) {
			e.preventDefault();
			document.getElementById('select-all-btn').click();
		}

		if (e.key === 'Escape') {
			e.preventDefault();
			clearSelection();
		}
	});

	function clearSelection() {
		selectedItems.forEach(item => {
			item.classList.remove('selected');
		});
		selectedItems = [];
		updateSelectionIndicator();
	}

	function updateSelectionIndicator() {
		if (selectedItems.length > 0) {
			document.getElementById('selection-count').textContent = selectedItems.length;
			selectionIndicator.style.display = 'flex';
		} else {
			selectionIndicator.style.display = 'none';
		}
	}

	function deleteSelectedItems() {
		if (selectedItems.length === 0) return;

		let hasFoldersWithContent = false;
		let itemsToDelete = selectedItems.map(item => {
			const isFile = item.classList.contains('file-item');
			const itemName = isFile ?
				item.querySelector('.file-name').textContent :
				item.querySelector('.folder-name').textContent;

			if (!isFile) {
				const hasContents = item.getAttribute('data-has-contents') === 'true';
				if (hasContents) {
					hasFoldersWithContent = true;
				}
			}

			return {
				type: isFile ? 'file' : 'folder',
				name: itemName,
				hasContents: !isFile ? (item.getAttribute('data-has-contents') === 'true') : false
			};
		});

		const itemCount = selectedItems.length;
		let message = t('fm_delete_items_confirm').replace('%d', itemCount);

		if (hasFoldersWithContent) {
			message += '<br><br><span style="color: var(--danger-text); font-weight: bold;">' + t('fm_delete_folder_content_warning') + '</span>';
		}

		showModal(
			message,
			t('confirm_deletion'), {
				showCancel: true,
				confirmText: t('delete'),
				cancelText: t('cancel'),
				danger: true,
				onConfirm: function() {
					const form = document.createElement('form');
					form.method = 'POST';
					form.action = 'file-manager.php?path=' + encodeURIComponent(currentPath);

					const batchDeleteInput = document.createElement('input');
					batchDeleteInput.type = 'hidden';
					batchDeleteInput.name = 'batch_delete';
					batchDeleteInput.value = '1';
					form.appendChild(batchDeleteInput);

					const itemsInput = document.createElement('input');
					itemsInput.type = 'hidden';
					itemsInput.name = 'items_to_delete';
					itemsInput.value = JSON.stringify(itemsToDelete);
					form.appendChild(itemsInput);

					if (hasFoldersWithContent) {
						const forceInput = document.createElement('input');
						forceInput.type = 'hidden';
						forceInput.name = 'force';
						forceInput.value = '1';
						form.appendChild(forceInput);
					}

					const csrfInput = document.createElement('input');
					csrfInput.type = 'hidden';
					csrfInput.name = 'csrf_token';
					csrfInput.value = window.CMS_CSRF_TOKEN || '';
					form.appendChild(csrfInput);

					document.body.appendChild(form);
					form.submit();
				}
			}
		);
	}

	// Dropzone functionality
	if (dropzone && fileInput) {
		dropzone.setAttribute('tabindex', '0');

		dropzone.addEventListener('click', function(e) {
			e.preventDefault();
			fileInput.click();
		});

		dropzone.addEventListener('keydown', function(e) {
			if (e.key === ' ' || e.key === 'Enter') {
				e.preventDefault();
				fileInput.click();
			}
		});

		if (fileInput) {
			fileInput.addEventListener('change', function() {
				if (this.files.length > 0) {
					dropzone.textContent = t('fm_files_selected').replace('%d', this.files.length);
				} else {
					dropzone.innerHTML = '<p>' + t('fm_dropzone_text') + '</p>';
				}
			});
		}

		['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
			dropzone.addEventListener(eventName, preventDefaults, false);
		});

		function preventDefaults(e) {
			e.preventDefault();
			e.stopPropagation();
		}

		['dragenter', 'dragover'].forEach(eventName => {
			dropzone.addEventListener(eventName, highlight, false);
		});

		['dragleave', 'drop'].forEach(eventName => {
			dropzone.addEventListener(eventName, unhighlight, false);
		});

		function highlight() {
			dropzone.classList.add('dragover');
		}

		function unhighlight() {
			dropzone.classList.remove('dragover');
		}

		dropzone.addEventListener('drop', function(e) {
			const dt = e.dataTransfer;
			const files = dt.files;

			if (files.length > 0) {
				const newFileList = new DataTransfer();
				for (let i = 0; i < files.length; i++) {
					newFileList.items.add(files[i]);
				}
				fileInput.files = newFileList.files;
				dropzone.textContent = t('fm_files_selected').replace('%d', files.length);
			}
		});

		if (uploadForm) {
			uploadForm.addEventListener('submit', function(e) {
				if (!fileInput.files || fileInput.files.length === 0) {
					showModal(t('fm_no_file_to_upload'), t('fm_attention'));
					e.preventDefault();
					return false;
				}

				e.preventDefault();

				const uploadProgress = document.getElementById('upload-progress');
				const progressFill = document.getElementById('progress-fill');
				const progressStage = document.getElementById('progress-stage');
				const progressMessage = document.getElementById('progress-message');
				const fileCountElement = document.getElementById('file-count');

				if (uploadProgress) {
					uploadProgress.style.display = 'block';
					const existingFileLists = uploadProgress.querySelectorAll('.file-list');
					existingFileLists.forEach(list => list.remove());
				}

				if (fileCountElement) {
					fileCountElement.textContent = fileInput.files.length;
				}

				if (progressFill) progressFill.style.width = '0%';
				if (progressStage) progressStage.textContent = t('fm_starting');
				if (progressMessage) progressMessage.textContent = t('fm_preparing_upload');

				const formData = new FormData(uploadForm);
				const xhr = new XMLHttpRequest();

				xhr.open('POST', window.location.href, true);
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

				xhr.upload.onprogress = function(e) {
					if (e.lengthComputable && progressFill) {
						const percent = Math.min(95, Math.round((e.loaded / e.total) * 100));
						progressFill.style.width = percent + '%';
						if (progressStage) progressStage.textContent = t('fm_uploading');
						if (progressMessage) progressMessage.textContent = t('fm_upload_progress').replace('%1', formatFileSize(e.loaded)).replace('%2', formatFileSize(e.total)).replace('%3', percent);
					}
				};

				xhr.onload = function() {
					if (xhr.status === 200) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response.status === 'uploaded' && response.files && response.files.length > 0) {
								if (progressStage) progressStage.textContent = t('fm_processing');
								if (progressMessage) progressMessage.textContent = t('fm_files_processing');

								if (document.getElementById('processing-results')) {
									document.getElementById('processing-results').style.display = 'block';
								}

								let completedFiles = 0;
								const totalFiles = response.files.length;

								if (uploadProgress) {
									let fileList = document.createElement('div');
									fileList.className = 'file-list';
									fileList.innerHTML = '<h4>' + t('fm_processing_files') + '</h4>';

									response.files.forEach(file => {
										const fileRow = document.createElement('div');
										fileRow.className = 'file-status-row';
										fileRow.innerHTML = `
											<span class="file-name">${file.name}</span>
											<span class="file-stage" id="stage-${file.id}">${t('fm_waiting')}</span>
											<div class="file-progress">
												<div class="progress-bar">
													<div class="progress-fill" id="progress-${file.id}" style="width: 0%"></div>
												</div>
											</div>
										`;
										fileList.appendChild(fileRow);

										const eventSource = new EventSource(`file-manager.php?stream_status=1&file_id=${file.id}`);

										eventSource.onmessage = function(event) {
											try {
												const data = JSON.parse(event.data);

												if (data.percentage !== null) {
													const progressElement = document.getElementById(`progress-${file.id}`);
													if (progressElement) {
														progressElement.style.width = data.percentage + '%';
													}
												}

												if (data.stage) {
													const stageElement = document.getElementById(`stage-${file.id}`);
													if (stageElement) {
														const stageName = data.stage.charAt(0).toUpperCase() + data.stage.slice(1);
														stageElement.textContent = stageName;
													}
												}

												updateTotalProgress();

												if (data.percentage === 100 || data.stage === 'complete') {
													eventSource.close();
													completedFiles++;

													if (completedFiles >= totalFiles) {
														if (progressFill) progressFill.style.width = '100%';
														if (progressStage) progressStage.textContent = t('fm_complete');
														if (progressMessage) progressMessage.textContent = t('fm_all_processed');
														setTimeout(function() {
															window.location.reload();
														}, 1500);
													}
												}
											} catch (error) {
												console.error('Error parsing event data:', error);
											}
										};

										eventSource.onerror = function() {
											console.log('SSE error for file:', file.name);
											if (eventSource.readyState === 2) {
												eventSource.close();
												completedFiles++;
											}
										};
									});

									uploadProgress.appendChild(fileList);
								}

								function updateTotalProgress() {
									if (!progressFill) return;
									let totalProgress = 0;
									response.files.forEach(file => {
										const progressElement = document.getElementById(`progress-${file.id}`);
										if (progressElement) {
											const width = progressElement.style.width;
											const percent = parseInt(width, 10) || 0;
											totalProgress += percent;
										}
									});
									progressFill.style.width = (totalProgress / totalFiles) + '%';
								}
							} else {
								if (progressStage) progressStage.textContent = t('error');
								if (progressMessage) progressMessage.textContent = t('fm_unexpected_response');
								console.error('Unexpected response:', response);
							}
						} catch (e) {
							if (progressStage) progressStage.textContent = t('error');
							if (progressMessage) progressMessage.textContent = t('fm_parse_error');
							console.error('Parse error:', e);
						}
					} else {
						if (progressStage) progressStage.textContent = t('error');
						if (progressMessage) progressMessage.textContent = t('fm_server_error').replace('%s', xhr.status);
					}
				};

				xhr.onerror = function() {
					if (progressStage) progressStage.textContent = t('fm_network_error');
					if (progressMessage) progressMessage.textContent = t('fm_connection_failed');
				};

				xhr.send(formData);
			});
		}
	}

	// Helper function to format file sizes
	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	}

	// Copy URL functionality
	window.copyToClipboard = function(text) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text)
				.then(() => {
					showModal(t('fm_url_copied'), t('success'));
				})
				.catch(err => {
					fallbackCopyToClipboard(text);
				});
		} else {
			fallbackCopyToClipboard(text);
		}

		function fallbackCopyToClipboard(text) {
			const input = document.createElement('textarea');
			input.value = text;
			input.style.position = 'fixed';
			document.body.appendChild(input);
			input.select();
			input.setSelectionRange(0, 99999);

			try {
				document.execCommand('copy');
				showModal(t('fm_url_copied'), t('success'));
			} catch (err) {
				console.error('Failed to copy text: ', err);
				showModal(t('fm_copy_failed'), t('error'));
			} finally {
				document.body.removeChild(input);
			}
		}
	};

	// Modal functionality
	window.showModal = function(message, title = t('notification'), options = {}) {
		const modal = document.getElementById('custom-modal');
		const modalMessage = document.getElementById('modal-message');
		const modalTitle = document.querySelector('.modal-title');
		const modalFooter = document.querySelector('.modal-footer');

		modalMessage.innerHTML = message;
		modalTitle.textContent = title;
		modalFooter.innerHTML = '';

		if (options.showCancel) {
			const cancelButton = document.createElement('button');
			cancelButton.className = 'modal-button modal-cancel';
			cancelButton.textContent = options.cancelText || t('cancel');
			cancelButton.onclick = function() {
				modal.style.display = 'none';
				if (typeof options.onCancel === 'function') options.onCancel();
			};
			modalFooter.appendChild(cancelButton);

			const confirmButton = document.createElement('button');
			confirmButton.className = 'modal-button modal-confirm' + (options.danger ? ' danger' : '');
			confirmButton.textContent = options.confirmText || t('confirm');
			confirmButton.onclick = function() {
				modal.style.display = 'none';
				if (typeof options.onConfirm === 'function') options.onConfirm();
			};
			modalFooter.appendChild(confirmButton);
		} else if (options.confirmText !== null) {
			const okButton = document.createElement('button');
			okButton.className = 'modal-button modal-confirm danger';
			okButton.textContent = options.confirmText || t('confirm_ok');
			okButton.onclick = function() {
				modal.style.display = 'none';
				if (typeof options.onConfirm === 'function') options.onConfirm();
			};
			modalFooter.appendChild(okButton);
		}

		modal.style.display = 'block';

		document.querySelector('.modal-close').onclick = function() {
			modal.style.display = 'none';
			if (typeof options.onCancel === 'function') options.onCancel();
		};
		// Close when clicking outside modal container
		modal.addEventListener('click', function(e) {
			if (e.target === modal) {
				modal.style.display = 'none';
			}
		});
	};

	// Auto-fade success messages
	const successMessages = document.querySelectorAll('.message.success');
	if (successMessages.length > 0) {
		setTimeout(function() {
			successMessages.forEach(function(message) {
				message.style.transition = 'opacity 0.5s ease';
				message.style.opacity = '0';
				setTimeout(function() {
					message.remove();
				}, 500);
			});
		}, 5000);
	}

	// ── Search / Filter / Sort toolbar ─────────────────────────────────────
	(function() {
		const searchInput  = document.getElementById('fm-search');
		const typeSelect   = document.getElementById('fm-type-filter');
		const sortSelect   = document.getElementById('fm-sort');
		const resultsCount = document.getElementById('fm-results-count');
		if (!searchInput) return;

		const IMAGE_EXTS = ['jpg','jpeg','png','gif','webp','svg','heic','heif','bmp','tiff','tif'];

		function getItems() {
			return Array.from(document.querySelectorAll('.file-grid .file-item, .file-grid .folder-item'));
		}

		function applyToolbar() {
			const query    = searchInput.value.trim().toLowerCase();
			const typeFilter = typeSelect.value;
			const [sortKey, sortDir] = sortSelect.value.split('-');
			const grid     = document.querySelector('.file-grid');
			if (!grid) return;

			const items = getItems();
			let visible = 0;

			items.forEach(item => {
				const name     = (item.querySelector('.file-name, .folder-name')?.textContent || '').toLowerCase();
				const ftype    = item.dataset.filetype || (item.classList.contains('folder-item') ? 'folder' : 'other');

				const matchSearch = !query || name.includes(query);

				let matchType = typeFilter === 'all';
				if (!matchType) {
					if (typeFilter === 'folder')   matchType = ftype === 'folder';
					else                           matchType = ftype === typeFilter;
				}

				const show = matchSearch && matchType;
				item.style.display = show ? '' : 'none';
				if (show) visible++;
			});

			if (resultsCount) {
				resultsCount.textContent = (query || typeFilter !== 'all')
					? visible + ' ' + t('fm_results')
					: '';
			}

			const visibleItems = getItems().filter(i => i.style.display !== 'none');
			visibleItems.sort((a, b) => {
				let va, vb;
				if (sortKey === 'name') {
					va = (a.querySelector('.file-name, .folder-name')?.textContent || '').toLowerCase();
					vb = (b.querySelector('.file-name, .folder-name')?.textContent || '').toLowerCase();
					return sortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
				} else if (sortKey === 'date') {
					va = parseInt(a.dataset.modified || '0');
					vb = parseInt(b.dataset.modified || '0');
				} else {
					va = parseInt(a.dataset.bytes || '0');
					vb = parseInt(b.dataset.bytes || '0');
				}
				return sortDir === 'asc' ? va - vb : vb - va;
			});
			visibleItems.forEach(item => grid.appendChild(item));

			const emptyMsg = grid.querySelector('.empty-message');
			if (emptyMsg) grid.appendChild(emptyMsg);
		}

		searchInput.addEventListener('input', applyToolbar);
		typeSelect.addEventListener('change', applyToolbar);
		sortSelect.addEventListener('change', applyToolbar);

		const savedSort = localStorage.getItem('fm-sort');
		if (savedSort) sortSelect.value = savedSort;
		sortSelect.addEventListener('change', () => localStorage.setItem('fm-sort', sortSelect.value));

		applyToolbar();
	})();
});