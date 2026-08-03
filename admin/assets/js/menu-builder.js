/**
 * Capitalize the first letter of a string
 */
function ucfirst(str) {
	return str.charAt(0).toUpperCase() + str.slice(1);
}

$(document).ready(function() {
	/* =========================
	 Menu Builder Functionality 
	 ========================= */
	// Count existing menu items on load and setup menu tree
	menuItemCounter = $('.menu-item').length;
	setupMenuDropdown();

	// Apply visual hierarchy on page load
	if ($('.menu-item').length > 0) {
		console.log('[Menu Builder] Initializing menu structure on page load');
		updateMenuStructure();
	}

	// Initialize sortable functionality for menu items
	initSortable();

	// Show/hide add forms
	$('#add-custom-link').on('click', function(e) {
		e.preventDefault();
		$('#custom-link-form').show();
		$('#content-link-form').hide();
	});

	$('#add-content-link').on('click', function(e) {
		e.preventDefault();
		$('#content-link-form').show();
		$('#custom-link-form').hide();
	});

	$('#cancel-custom-link, #cancel-content-link').on('click', function(e) {
		e.preventDefault();
		$('#custom-link-form, #content-link-form').hide();
	});

	// Add custom link to menu
	$('#add-custom-link-btn').on('click', function(e) {
		e.preventDefault();
		let label   = $('#custom-label').val();
		let url     = $('#custom-url').val();
		let parentId = $('#custom-parent-menu').val() || null;
		let target  = $('#custom-link-new-tab').is(':checked') ? '_blank' : '';

		if (!label || !url) {
			showModal(window.t('menu_error_fill_fields'), window.t('warning'));
			return;
		}

		addMenuItem({
			type: 'custom',
			label: label,
			url: url,
			parent_id: parentId,
			target: target
		});

		// Reset form
		$('#custom-label, #custom-url').val('');
		$('#custom-parent-menu').val('');
		$('#custom-link-new-tab').prop('checked', false);
		$('#custom-link-form').hide();

		// Update parent menu dropdown with new item
		updateParentDropdowns();
	});

	// Load content items when content type is selected
	$('#content-type').on('change', function() {
		let contentType = $(this).val();

		if (contentType === 'contentlist') {
			$('#contentlist-options').show();
			$('#content-items').hide();
			return;
		}
		if (contentType === 'tag') {
			$('#contentlist-options').hide();
			$.ajax({
				url: window.location.pathname,
				type: 'GET',
				data: { action: 'get_content_items', type: 'tag' },
				dataType: 'json',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				success: function(data) {
					let html = '<label for="content-item">' + window.t('select_tag_label') + '</label>';
					html += '<select id="content-item"><option value="">Select Tag</option>';
					for (let i = 0; i < data.length; i++) {
						html += '<option value="' + data[i].slug + '">' + data[i].title + '</option>';
					}
					html += '</select>';
					$('#content-items').html(html).show();
				},
				error: function() {
					$('#content-items').html('<div class="error">Error loading tags.</div>').show();
				}
			});
			return;
		}
		$('#contentlist-options').hide();

		if (!contentType) {
			$('#content-items').hide();
			return;
		}

		// Load content items via AJAX
		$.ajax({
			url: window.location.pathname, // Use current path to ensure proper routing
			type: 'GET',
			data: {
				action: 'get_content_items',
				type: contentType
			},
			dataType: 'json',
			headers: {
				'X-Requested-With': 'XMLHttpRequest' // Add this header to indicate AJAX request
			},
			success: function(data) {
				let html = '<label for="content-item">' + window.t('select_item') + '</label>';
				html += '<select id="content-item">';
				html += '<option value="">Select Item</option>';

				for (let i = 0; i < data.length; i++) {
					// The slug here is now the correct one (custom or default)
					html += '<option value="' + data[i].slug + '">' + data[i].title + '</option>';
				}

				html += '</select>';

				$('#content-items').html(html).show();
			},
			error: function(xhr, status, error) {
				console.error('Error loading content items:', error);
				console.error('Response:', xhr.responseText.substring(0, 200) + '...'); // Only log the first part
				$('#content-items').html('<div class="error">Error loading content items. Please try again.</div>').show();
			}
		});
	});

	// Add content link to menu
	$('#add-content-link-btn').on('click', function(e) {
		e.preventDefault();
		let contentType = $('#content-type').val();
		let parentId = $('#content-parent-menu').val() || null;
		let target   = $('#content-link-new-tab').is(':checked') ? '_blank' : '';
		
		if (contentType === 'contentlist') {
			let listType = $('#contentlist-type').val();
			let label = $('#content-label').val() || ucfirst(listType) + 's';

			addMenuItem({
				type: 'content',
				label: label,
				url: listType + 's/',
				content_type: 'list',
				content_slug: listType,
				parent_id: parentId,
				target: target
			});

			// Reset form
			$('#content-type').val('');
			$('#content-label').val('');
			$('#content-parent-menu').val('');
			$('#content-link-new-tab').prop('checked', false);
			$('#content-link-form').hide();
			$('#content-items, #contentlist-options').hide();

			// Update parent menu dropdown with new item
			updateParentDropdowns();
			return;
		}

		if (contentType === 'tag') {
			let tagSlug = $('#content-item').val();
			let tagLabel = $('#content-label').val() || $('#content-item option:selected').text();
		
			if (!tagSlug) {
				showModal(window.t('menu_error_select_tag'), window.t('warning'));
				return;
			}
		
			addMenuItem({
				type: 'custom',
				label: tagLabel,
				url: 'tag/' + tagSlug + '/',
				tag_slug: tagSlug,
				parent_id: parentId,
				target: target
			});
		
			$('#content-type, #content-item, #content-label').val('');
			$('#content-parent-menu').val('');
			$('#content-link-new-tab').prop('checked', false);
			$('#content-link-form').hide();
			$('#content-items').hide();
			updateParentDropdowns();
			return;
		}
		
		let contentItem = $('#content-item').val();

		if (!contentType || !contentItem) {
			showModal(window.t('menu_error_select_content'), window.t('warning'));
			return;
		}

		let label = $('#content-label').val() || $('#content-item option:selected').text();

		// AJAX call to get the correct category info for this content
		$.ajax({
			url: 'index.php',
			type: 'GET',
			headers: {
				'User-Agent': 'Mozilla/5.0 (compatible; AdminSiteRequest)'
			},
			data: {
				action: 'get_content_info',
				type: contentType,
				slug: contentItem
			},
			dataType: 'json',
			success: function(response) {
				let url = contentType + '/' + contentItem + '/';

				// If category is available, use it in the URL
				if (response && response.category) {
					if (contentType === 'article') {
						url = response.category + '/' + contentItem + '/';
					} else if (contentType === 'project') {
						url = contentType + '/' + response.category + '/' + contentItem + '/';
					}
				}

				addMenuItem({
					type: 'content',
					label: label,
					url: url,
					content_type: contentType,
					content_slug: contentItem,
					content_category: response ? response.category : '',
					parent_id: parentId,
					target: target
				});

				// Reset form
				$('#content-type, #content-item, #content-label').val('');
				$('#content-parent-menu').val('');
				$('#content-link-new-tab').prop('checked', false);
				$('#content-link-form').hide();
				$('#content-items').hide();

				// Update parent menu dropdown with new item
				updateParentDropdowns();
			},
			error: function() {
				// Fallback if AJAX fails
				let url = contentType + '/' + contentItem + '/';

				addMenuItem({
					type: 'content',
					label: label,
					url: url,
					content_type: contentType,
					content_slug: contentItem,
					parent_id: parentId,
					target: target
				});

				// Reset form
				$('#content-type, #content-item, #content-label').val('');
				$('#content-parent-menu').val('');
				$('#content-link-new-tab').prop('checked', false);
				$('#content-link-form').hide();
				$('#content-items').hide();

				// Update parent menu dropdown with new item
				updateParentDropdowns();
			}
		});
	});

	// Remove menu item
	$(document).on('click', '.remove-menu-item', function(e) {
		e.preventDefault();
		const $menuItem = $(this).closest('.menu-item');
		const itemId = $menuItem.data('id');
		const itemLabel = $menuItem.find('input[name$="[label]"]').val();

		// Use showModal function to confirm deletion
		showModal(
			window.t('menu_delete_confirm').replace('%s', itemLabel),
			window.t('menu_delete_confirm_title'), {
				showCancel: true,
				confirmText: window.t('delete'),
				cancelText: window.t('cancel'),
				danger: true,
				onConfirm: function() {
					// First remove any children of this menu item
					$('.menu-item').each(function() {
						if ($(this).find('input[name$="[parent_id]"]').val() === itemId) {
							$(this).remove();
						}
					});

					// Then remove the menu item itself
					$menuItem.remove();

					// Update parent dropdowns after removal
					updateParentDropdowns();

					// Reindex all menu items
					reindexMenuItems();
				}
			}
		);
	});

	// Edit menu item
	$(document).on('click', '.edit-menu-item', function(e) {
		e.preventDefault();

		// Modal Initialization - make sure it exists before using it
		if ($('#edit-menu-modal').length === 0) {
			$('body').append(`
		 <div id="edit-menu-modal" class="menu-modal">
		   <div class="menu-modal-content">
			 <h3 class="menu-modal-title">${window.t('edit_menu_item_title')}</h3>
			 <div class="menu-modal-form">
			   <input type="hidden" id="edit-menu-id" value="">
			   <div class="form-group">
				 <label for="edit-menu-label">${window.t('menu_label')} :</label>
				 <input type="text" id="edit-menu-label" name="edit-menu-label">
			   </div>
			   <div class="form-group" id="edit-menu-url-container">
				 <label for="edit-menu-url">${window.t('link_url')} :</label>
				 <input type="text" id="edit-menu-url" name="edit-menu-url">
			   </div>
			   <div class="form-group">
				 <label class="checkbox-label">
				   <input type="checkbox" id="edit-menu-new-tab">
				   ${window.t('open_in_new_tab')}
				 </label>
			   </div>
			   <div class="form-group">
				 <label for="edit-parent-menu">${window.t('parent_menu_item')} :</label>
				 <select id="edit-parent-menu" name="edit-parent-menu">
				   <option value="">' + window.t('menu_parent_none') + '</option>
				 </select>
			   </div>
			   <div class="menu-modal-footer">
				 <button type="button" id="cancel-edit-menu" class="btn btn-neutral">${window.t('cancel')}</button>
				 <button type="button" id="save-menu-item-btn" class="btn btn-primary">${window.t('save_changes')}</button>
			   </div>
			 </div>
		   </div>
		 </div>
	   `);
		}

		let menuItem = $(this).closest('.menu-item');
		let itemId = menuItem.data('id');
		let itemType = menuItem.find('input[name$="[type]"]').val();
		let itemLabel = menuItem.find('input[name$="[label]"]').val();
		let itemUrl = menuItem.find('input[name$="[url]"]').val();
		let parentId = menuItem.find('input[name$="[parent_id]"]').val();

		let itemTarget = menuItem.find('input[name$="[target]"]').val() || '';

		// Update the form fields
		$('#edit-menu-id').val(itemId);
		$('#edit-menu-label').val(itemLabel);
		$('#edit-menu-url').val(itemUrl);
		$('#edit-menu-new-tab').prop('checked', itemTarget === '_blank');

		// Populate and update the parent dropdown
		updateParentDropdowns();
		$('#edit-parent-menu').val(parentId);

		// Disable self-selection in parent dropdown
		$('#edit-parent-menu option').each(function() {
			if ($(this).val() === itemId) {
				$(this).attr('disabled', true);
			} else {
				$(this).attr('disabled', false);
			}
		});

		// Only show URL field for custom links
		if (itemType === 'custom') {
			$('#edit-menu-url-container').show();
		} else {
			$('#edit-menu-url-container').hide();
		}

		// Show the modal
		$('#edit-menu-modal').show();

		// Attach event handlers using event delegation
		$(document).off('click', '#cancel-edit-menu, #save-menu-item-btn');

		$(document).on('click', '#cancel-edit-menu', function() {
			$('#edit-menu-modal').hide();
		});

		$(document).on('click', '#save-menu-item-btn', function() {
			// Get the values from the form
			const itemId     = $('#edit-menu-id').val();
			const newLabel   = $('#edit-menu-label').val();
			const newUrl     = $('#edit-menu-url').val();
			const newParentId = $('#edit-parent-menu').val() || '';
			const newTarget  = $('#edit-menu-new-tab').is(':checked') ? '_blank' : '';

			if (!newLabel) {
				showModal(window.t('menu_error_enter_label'), window.t('warning'));
				return;
			}

			// Find the menu item
			const $menuItem = $(`.menu-item[data-id="${itemId}"]`);

			// Update the label
			$menuItem.find('input[name$="[label]"]').val(newLabel);
			$menuItem.find('.menu-item-title').text(newLabel);

			// Update URL for custom links
			if ($menuItem.find('input[name$="[type]"]').val() === 'custom' && newUrl) {
				$menuItem.find('input[name$="[url]"]').val(newUrl);
			}

			// Update target (open in new tab)
			$menuItem.find('input[name$="[target]"]').val(newTarget);

			// Update parent ID
			const oldParentId = $menuItem.find('input[name$="[parent_id]"]').val();
			$menuItem.find('input[name$="[parent_id]"]').val(newParentId);

			// If parent changed, reposition the item under its new parent
			if (oldParentId !== newParentId) {
				if (newParentId) {
					// Move after the new parent item
					const $newParent = $(`.menu-item[data-id="${newParentId}"]`);
					if ($newParent.length) {
						// Find all existing children of the new parent and place after the last one
						let $lastChild = $newParent;
						$('.menu-item').each(function() {
							if ($(this).find('input[name$="[parent_id]"]').val() === newParentId && $(this).data('id') !== itemId) {
								$lastChild = $(this);
							}
						});
						$menuItem.insertAfter($lastChild);
					}
				} else {
					// Moving to top level - append to end of menu
					$('#menu-items').append($menuItem);
				}
				// Reindex after repositioning
				reindexMenuItems();
			}

			// Update visual hierarchy
			updateMenuStructure();

			// Close the modal
			$('#edit-menu-modal').hide();

			// Update parent dropdowns
			updateParentDropdowns();
		});
	});

	// Ensure the menu form submits correctly
	$('form[action*="action=settings"]').on('submit', function() {

		// Check if this is the menu form
		if ($(this).find('button[name="save_menu"]').length) {

			// Make sure the use_custom_menu checkbox state is preserved
			if (!$('#use_custom_menu').is(':checked')) {
				// We don't need to add a hidden field as PHP will correctly handle this
			}
		}
	});


/**
 * Setup the parent menu dropdown for submenu creation
 */
function setupMenuDropdown() {
	// Create the parent menu dropdown options
	let customParentDropdown = $('#custom-parent-menu');
	let contentParentDropdown = $('#content-parent-menu');
	let editParentDropdown = $('#edit-parent-menu');

	if (customParentDropdown.length && contentParentDropdown.length) {
		updateParentDropdowns();
	}
}

/**
 * Update all parent menu dropdowns with current menu items
 */
function updateParentDropdowns() {
	let parentDropdowns = $('#custom-parent-menu, #content-parent-menu, #edit-parent-menu');

	// Save selected values before updating
	let selectedValues = {};
	parentDropdowns.each(function() {
		selectedValues[$(this).attr('id')] = $(this).val();
	});

	// Clear and rebuild each dropdown
	parentDropdowns.each(function() {
		const dropdown = $(this);
		dropdown.empty();

		// Add the empty option
		dropdown.append('<option value="">' + window.t('menu_parent_none') + '</option>');

		// Add all menu items as potential parents
		$('.menu-item').each(function() {
			const $item = $(this);
			const itemId = $item.data('id');
			const itemLabel = $item.find('input[name$="[label]"]').val();

			dropdown.append(`<option value="${itemId}">${itemLabel}</option>`);
		});

		// Restore the previously selected value if it exists
		const previousValue = selectedValues[dropdown.attr('id')];
		if (previousValue) {
			dropdown.val(previousValue);
		}
	});
}

/**
 * Update the visual representation of the menu hierarchy
 */
function updateMenuStructure() {
	// Remove all level classes and inline margin
	$('.menu-item').removeClass('submenu-level-1 submenu-level-2 submenu-level-3 submenu-level-4').css('margin-left', '');

	// Build a map of item IDs to their parent IDs for quick lookup
	var parentMap = {};
	$('.menu-item').each(function() {
		var id = $(this).attr('data-id');
		var parentId = $(this).find('input[name$="[parent_id]"]').val() || '';
		parentMap[id] = parentId;
	});

	// Calculate depth for a given item ID
	function getDepth(id) {
		var depth = 0;
		var currentId = id;
		var visited = {};

		while (parentMap[currentId] && parentMap[currentId] !== '' && depth < 5) {
			if (visited[currentId]) break; // Prevent infinite loops
			visited[currentId] = true;
			currentId = parentMap[currentId];
			depth++;
		}
		return depth;
	}

	// Apply depth-based classes
	$('.menu-item').each(function() {
		var id = $(this).attr('data-id');
		var depth = getDepth(id);
		if (depth > 0) {
			var level = Math.min(depth, 4);
			$(this).addClass('submenu-level-' + level);
		}
	});
}

/**
 * Initialize the Sortable.js functionality for menu reordering
 */
function initSortable() {
	try {
		if (typeof Sortable !== 'undefined') {
			var menuItemsContainer = document.getElementById('menu-items');
			if (menuItemsContainer) {
				new Sortable(menuItemsContainer, {
					handle: '.menu-item-handle',
					animation: 150,
					onEnd: function(evt) {
						var $movedItem = $(evt.item);
						var movedId    = String($movedItem.data('id'));

						// Recursively collect the full subtree of a given parent ID,
						// preserving current DOM order so relative child positions are kept
						function collectSubtree(parentId) {
							var nodes = [];
							$('.menu-item').each(function() {
								var pid = $(this).find('input[name$="[parent_id]"]').val() || '';
								if (pid === parentId) {
									nodes.push(this);
									var childId = String($(this).data('id'));
									collectSubtree(childId).forEach(function(n) { nodes.push(n); });
								}
							});
							return nodes;
						}

						// Re-insert the whole subtree immediately after the moved parent
						var subtree = collectSubtree(movedId);
						if (subtree.length > 0) {
							var $anchor = $movedItem;
							subtree.forEach(function(child) {
								$(child).insertAfter($anchor);
								$anchor = $(child);
							});
						}

						reindexMenuItems();
						updateMenuStructure();
					}
				});
			}
		} else {
			if (document.getElementById('menu-items')) {
				$('#menu-items').before('<p class="error-message">Sortable library not loaded. Menu items cannot be reordered.</p>');
			}
		}
	} catch (e) {
		console.error('Error initializing sortable:', e);
	}
}

/**
 * Add a new item to the menu
 * @param {Object} item - The menu item to add
 */
function addMenuItem(item) {
	console.log('Adding menu item:', item);

	// Hide the "no items" message if it exists
	$('#no-menu-items').hide();

	// Generate a unique ID for this menu item
	const itemId = 'menu_item_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

	// Get current item count for proper indexing
	let itemIndex = $('.menu-item').length;
	console.log('Current item index:', itemIndex);

	let html = '<li class="menu-item" data-id="' + itemId + '">';
	html += '<div class="menu-item-handle"><span class="handle">≡</span></div>';
	html += '<div class="menu-item-title">' + item.label + '</div>';
	html += '<div class="menu-item-controls">';
	html += '<button type="button" class="btn btn-sm edit-menu-item">' + window.t('edit') + '</button>';
	html += '<button type="button" class="btn btn-sm remove-menu-item">' + window.t('remove') + '</button>';
	html += '</div>';

	// Critical: ensure name attributes are correctly formatted for PHP array
	html += '<input type="hidden" name="menu[' + itemIndex + '][type]" value="' + item.type + '">';
	html += '<input type="hidden" name="menu[' + itemIndex + '][label]" value="' + item.label + '">';
	html += '<input type="hidden" name="menu[' + itemIndex + '][url]" value="' + item.url + '">';
	html += '<input type="hidden" name="menu[' + itemIndex + '][id]" value="' + itemId + '">';
	html += '<input type="hidden" name="menu[' + itemIndex + '][parent_id]" value="' + (item.parent_id || '') + '">';
	html += '<input type="hidden" name="menu[' + itemIndex + '][target]" value="' + (item.target || '') + '">';

	if (item.type === 'content' && item.content_type && item.content_slug) {
		html += '<input type="hidden" name="menu[' + itemIndex + '][content_type]" value="' + item.content_type + '">';
		html += '<input type="hidden" name="menu[' + itemIndex + '][content_slug]" value="' + item.content_slug + '">';
	}
	if (item.tag_slug) {
		html += '<input type="hidden" name="menu[' + itemIndex + '][tag_slug]" value="' + item.tag_slug + '">';
	}

	html += '</li>';

	// If it has a parent, find the parent and insert after it
	if (item.parent_id) {
		// Find all siblings of the parent that already have this parent
		const $siblings = $('.menu-item').filter(function() {
			return $(this).find('input[name$="[parent_id]"]').val() === item.parent_id;
		});

		if ($siblings.length > 0) {
			// If there are already siblings, insert after the last one
			$siblings.last().after(html);
		} else {
			// Otherwise, insert after the parent
			const $parent = $('.menu-item[data-id="' + item.parent_id + '"]');
			$parent.after(html);
		}
	} else {
		// No parent, just append to the end
		$('#menu-items').append(html);
	}

	// Update visual hierarchy
	updateMenuStructure();

	// Reindex all menu items to ensure proper ordering in the form
	reindexMenuItems();

	console.log('Menu item added with index:', itemIndex);
}

/**
 * Reindex menu items after reordering
 */
function reindexMenuItems() {
	console.log('Reindexing menu items');
	$('.menu-item').each(function(index) {
		let item = $(this);

		// Find all inputs and update their names with the new index
		item.find('input').each(function() {
			let name = $(this).attr('name');
			let newName = name.replace(/menu\[\d+\]/, 'menu[' + index + ']');
			$(this).attr('name', newName);
		});
	});
}

});