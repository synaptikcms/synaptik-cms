(function() {
	const t = window.t || ((k, fb) => window.CMS_LANG?.[k] ?? fb ?? k);

	function normalizeImagePath(path, forDisplay = true) {
		if (!path) return '';
		
		let cleanPath = path;
		
		// Strip any existing prefixes to create a clean path
		if (cleanPath.startsWith('../files/')) {
			cleanPath = cleanPath.substring(9);
		} else if (cleanPath.startsWith('../')) {
			cleanPath = cleanPath.substring(3);
		} else if (cleanPath.startsWith('files/')) {
			cleanPath = cleanPath.substring(6);
		}
		
		// Add proper prefix for display if needed
		if (forDisplay) {
			return '../files/' + cleanPath;
		}
		
		return cleanPath;
	}

	// Initialize SEO preview when document is ready
	$(document).ready(function() {
		// Social media image selection functionality
		const selectOgImageBtn = document.getElementById('select-og-image');
		const ogImageInput = document.getElementById('og_image');
		const ogImagePreview = document.getElementById('og-image-preview');
		
		if (ogImageInput && ogImageInput.value && ogImagePreview) {
			// Make sure the preview is visible
			ogImagePreview.style.display = 'block';
		
			// ALWAYS create a complete path regardless of current format
			const displayPath = normalizeImagePath(ogImageInput.value);
		
			// Always recreate the preview
			ogImagePreview.innerHTML = `
		  <img src="${displayPath}" alt="Social media preview image">
		  <button type="button" class="remove-og-image">${t('remove_image')}</button>
		`;
		}
		
		// Show OG preview if it has content
		if (ogImagePreview && ogImagePreview.querySelector('img')) {
			ogImagePreview.style.display = 'inline-block';
		}

		// Initialize gallery manager
		initGalleryManager();

		// Register event handlers for gallery functionality
		setupGalleryEventHandlers();
	});

	// Initialize gallery manager
	function initGalleryManager() {
		// Init Sortable on every .named-gallery-items already in the DOM
		initSortableOnAllBlocks();
	}

	// Initialize Sortable on a single .named-gallery-items container
	function initSortableOnBlock(container) {
		if (typeof Sortable === 'undefined' || !container) return;
		// Avoid double-init
		if (container._sortable) return;
		try {
			const blockIdx = parseInt(container.dataset.galleryIndex);
			container._sortable = new Sortable(container, {
				animation: 150,
				handle: '.gallery-item',
				onEnd: function() {
					reindexNamedGalleryItems(blockIdx);
				}
			});
		} catch (e) {
			console.error('Sortable init error:', e);
		}
	}

	// Initialize Sortable on all .named-gallery-items in the DOM
	function initSortableOnAllBlocks() {
		document.querySelectorAll('.named-gallery-items').forEach(function(container) {
			initSortableOnBlock(container);
		});
	}

	// Set up all event handlers for gallery functionality
	function setupGalleryEventHandlers() {
		// Open gallery modal for adding gallery images
		$(document).on('click', '#add-gallery-image', function(e) {
			e.preventDefault();
			openGalleryModal('gallery');
		});

		// Open gallery modal for selecting featured image
		$(document).on('click', '#select-featured-image', function(e) {
			e.preventDefault();
			openGalleryModal('featured-image');
		});

		// Open gallery modal for selecting OG image
		$(document).on('click', '#select-og-image', function(e) {
			e.preventDefault();
			openGalleryModal('og-image');
		});
		
		// Remove OG image
		$(document).on('click', '.remove-og-image', function() {
			$('#og_image').val('');
			$('#og-image-preview').html('').hide();
		});

		// Close gallery modal
		$(document).on('click', '.close-gallery-modal', function() {
			closeGalleryModal();
		});

		// Close gallery modal when clicking outside
		$(document).on('click', '#gallery-modal', function(e) {
			if ($(e.target).is('#gallery-modal')) {
				closeGalleryModal();
			}
		});

		// Remove gallery item
		$(document).on('click', '.remove-gallery-item', function() {
			$(this).closest('.gallery-item').remove();
			reindexGalleryItems();
		});

		// Remove selected featured image (legacy handler)
		$(document).on('click', '.remove-selected-image', function() {
			$('#selected-image-path').val('');
			$('#image').prop('disabled', false);
			$('.selected-image-preview').remove();
			$('#featured-image-preview').html('').hide();
		});
		
		// Remove featured image
		$(document).on('click', '.remove-featured-image', function() {
			$('#featured-image-preview').html('').hide();
			$('#remove-featured-image-flag').val('1');
			$('#selected-image-path').val('');
			$('#image').prop('disabled', false);
			$(this).hide();
		});

		// Navigate folders in gallery modal (compact chip buttons)
		$(document).on('click', '#gallery-modal .gm-folder-chip', function(e) {
			e.preventDefault();
			const path = $(this).data('path');
			loadFilesForGallery(path);
		});

		// Navigate using breadcrumbs
		$(document).on('click', '#modal-breadcrumbs a', function(e) {
			e.preventDefault();
			const path = $(this).data('path');
			loadFilesForGallery(path);
		});

		// Select files in gallery modal
		$(document).on('click', '#gallery-modal .file-item', function(e) {
			e.preventDefault();
			e.stopPropagation();

			const mode = window.gallerySelectionMode || 'gallery';
			const isSingleSelectMode = (mode === 'featured-image' || mode === 'og-image');

			if (isSingleSelectMode) {
				// Single-select only: clicking any image replaces the current selection
				$('#gallery-modal .file-item').removeClass('selected');
				$(this).addClass('selected');
			} else if (e.ctrlKey || e.metaKey || e.shiftKey) {
				// Multi-select with Ctrl/Cmd/Shift
				$(this).toggleClass('selected');
			} else {
				// Plain click: deselect all then select this one (toggle off if already sole selection)
				if ($(this).hasClass('selected') && $('#gallery-modal .file-item.selected').length === 1) {
					$(this).removeClass('selected');
				} else {
					$('#gallery-modal .file-item').removeClass('selected');
					$(this).addClass('selected');
				}
			}

			updateGallerySelectionIndicator();
		});

		// Select all files
		$(document).on('click', '#select-all-images', function(e) {
			e.preventDefault();
			$('#gallery-modal .file-item').addClass('selected');
			updateGallerySelectionIndicator();
		});

		// Deselect all files
		$(document).on('click', '#deselect-all-images', function(e) {
			e.preventDefault();
			$('#gallery-modal .file-item').removeClass('selected');
			updateGallerySelectionIndicator();
		});

		// Open file manager in new tab
		$(document).on('click', '#open-file-manager', function(e) {
			e.preventDefault();
			window.open('file-manager.php', '_blank');
		});

		// Add selected gallery items (from selection indicator)
		$(document).on('click', '#add-selected-gallery-items', function(e) {
			e.preventDefault();
			processSelectedImages();
		});

		// Clear gallery selection (from selection indicator)
		$(document).on('click', '#clear-gallery-selection', function(e) {
			e.preventDefault();
			clearGallerySelection();
		});

		// Legacy support for old button ID
		$(document).on('click', '#add-selected-images', function(e) {
			e.preventDefault();
			processSelectedImages();
		});

		// ── Named gallery blocks ──────────────────────────────────────────────

		// Open modal for a named gallery block
		$(document).on('click', '.add-named-gallery-images', function(e) {
			e.preventDefault();
			window.currentNamedGalleryIndex = parseInt($(this).data('gallery-index'));
			openGalleryModal('named-gallery');
		});

		// Remove a named gallery item
		$(document).on('click', '.remove-named-gallery-item', function() {
			const block = $(this).closest('.named-gallery-block');
			const blockIdx = parseInt(block.data('gallery-index'));
			$(this).closest('.gallery-item').remove();
			reindexNamedGalleryItems(blockIdx);
		});

		// Remove a named gallery block
		$(document).on('click', '.remove-gallery-block', function(e) {
			e.preventDefault();
			const $block = $(this).closest('.named-gallery-block');
			window.showModal(
				t('gallery_delete_confirm'),
				t('delete'),
				{
					danger:      true,
					showCancel:  true,
					cancelText:  t('cancel'),
					confirmText: t('delete'),
					onConfirm: function() {
						$block.remove();
						reindexGalleryBlocks();
					}
				}
			);
		});

		// Add a new gallery block
		$(document).on('click', '#add-gallery-block', function(e) {
			e.preventDefault();
			addGalleryBlock();
		});

		// Copy shortcode to clipboard
		$(document).on('click', '.copy-shortcode-btn', function(e) {
			e.preventDefault();
			const shortcode = $(this).data('shortcode');
			navigator.clipboard.writeText(shortcode).then(() => {
				const btn = $(this);
				const orig = btn.text();
				btn.text(t('gallery_copied'));
				setTimeout(() => btn.text(orig), 1500);
			}).catch(() => {
				prompt(t('gallery_copy_prompt'), shortcode);
			});
		});

		// Insert shortcode into editor at cursor
		$(document).on('click', '.insert-shortcode-btn', function(e) {
			e.preventDefault();
			const galleryId = $(this).data('gallery-id');
			const shortcode = '[gallery id="' + galleryId + '"]';
			if (typeof window.insertShortcodeAtCursor === 'function') {
				window.insertShortcodeAtCursor(shortcode);
			} else {
				navigator.clipboard.writeText(shortcode).then(() => {
					const btn = $(this);
					const orig = btn.text();
					btn.text(t('gallery_copied'));
					setTimeout(() => btn.text(orig), 1500);
				}).catch(() => {
					prompt(t('gallery_shortcode_prompt'), shortcode);
				});
			}
		});
	}

	// Open the gallery modal
	function openGalleryModal(mode) {
		// Store current mode and reset any stale flags from previous uses
		window.gallerySelectionMode  = mode || 'gallery';
		window.isEditorImageInsertion = false;

		// Show the modal
		$('#gallery-modal').show();

		// Clear existing selections
		clearGallerySelection();

		// Update modal title based on mode
		let title = t('select_images');
		if (mode === 'featured-image') {
			title = t('gallery_modal_featured');
		} else if (mode === 'og-image') {
			title = t('gallery_modal_og');
		}
		$('#gallery-modal h2').text(title);

		// Load files
		loadFilesForGallery('');
	}

	// Close the gallery modal
	function closeGalleryModal() {
		$('#gallery-modal').hide();
		clearGallerySelection();
		// Ensure no selection box lingers outside the modal after close
		document.querySelectorAll('.gm-selection-box').forEach(function(el) { el.remove(); });
	}

	// Update the gallery selection indicator
	function updateGallerySelectionIndicator() {
		const selectedCount = $('#gallery-modal .file-item.selected').length;

		if (selectedCount > 0) {
			$('#gallery-selected-count').text(selectedCount);
			$('#gallery-selection-indicator').show();
		} else {
			$('#gallery-selection-indicator').hide();
		}
	}

	// Clear gallery selection
	function clearGallerySelection() {
		$('#gallery-modal .file-item').removeClass('selected');
		updateGallerySelectionIndicator();
	}
	
	// Set featured image
	function setFeaturedImage(imagePath, imageUrl, imageName) {
		// Set the hidden input value
		$('#selected-image-path').val(imagePath);
	
		// Remove any existing standalone preview
		$('.selected-image-preview').remove();
	
		// Add preview inside the featured-image-preview container
		const previewHtml = `
			<p style="font-size:0.7em;color:var(--text-muted);margin-top:0;">${t('gallery_selected_image')} ${imageName}</p>
			<img src="${imageUrl}" alt="Selected image" class="featured-preview">
			<button type="button" class="remove-featured-image">${t('remove_image')}</button>
		`;
	
		$('#featured-image-preview').html(previewHtml).show();
	
		// Disable file input
		$('#image').prop('disabled', true);
	}
	
	// Set OG/social media image
	function setOgImage(imagePath, imageUrl) {
		// Set the og_image input value
		$('#og_image').val(imagePath);
	
		// Update preview and show it
		$('#og-image-preview').html(`
			<img src="${imageUrl}" alt="Preview image">
			<button type="button" class="remove-og-image">${t('remove_image')}</button>
		`).css('display', 'inline-block');
	}
	
	// Add images to gallery
	function addImagesToGallery(selectedImages) {
		selectedImages.each(function() {
			const path = $(this).data('path');
			const imageUrl = getFileUrl(path);
			addImageToGallery(path, imageUrl);
		});
	}
	
	// Process the selected images based on the current mode
	function processSelectedImages() {
		const selectedImages = $('#gallery-modal .file-item.selected');
	
		if (selectedImages.length === 0) {
			window.showModal(t('gallery_select_one'), t('warning'));
			return;
		}
	
		const mode = window.gallerySelectionMode || 'gallery';
		const firstImage = selectedImages[0];
		const path = $(firstImage).data('path');
		const imageUrl = getFileUrl(path);
	
		if (mode === 'featured-image') {
			// Single image only — always use the first selected
			const imageName = $(firstImage).find('.file-name').text();
			setFeaturedImage(path, imageUrl, imageName);
		} else if (mode === 'og-image') {
			// Single image only — always use the first selected
			setOgImage(path, imageUrl);
		} else if (window.isEditorImageInsertion === true) {
			// Insert every selected image sequentially at the cursor position.
			// autoClose = false on all but the last so the cursor is preserved
			// between calls (restoreSelection() nulls out savedEditorRange after
			// the first call; the non-close path re-saves it instead).
			const total = selectedImages.length;
			selectedImages.each(function(index) {
				const imgPath = $(this).data('path');
				const imgName = $(this).find('.file-name').text() || 'Image';
				const editorImageUrl = '../files/' + imgPath;
				const isLast = (index === total - 1);
				if (typeof window.insertImageAtCursor === 'function') {
					window.insertImageAtCursor(editorImageUrl, imgName, isLast);
				}
			});
			window.isEditorImageInsertion = false;
		} else if (mode === 'named-gallery') {
			addImagesToNamedGallery(window.currentNamedGalleryIndex, selectedImages);
		} else {
			// Default gallery mode
			addImagesToGallery(selectedImages);
		}
	
		// Close modal and clear selection
		closeGalleryModal();
	}

	// Load files for gallery selection
	function loadFilesForGallery(path) {
		// Update breadcrumbs
		updateBreadcrumbs(path);

		// Show loading message
		$('#modal-files').html('<p>' + t('loading_files') + '</p>');

		// Construct the full URL to get-files.php
		const fullUrl = window.location.protocol + '//' +
			window.location.host +
			window.location.pathname.split('/').slice(0, -1).join('/') +
			'/get-files.php';

		// Fetch files via AJAX
		$.ajax({
			url: fullUrl,
			type: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			},
			data: { path: path },
			dataType: 'json',
			success: function(response) {
				displayFilesInModal(response, path);
			},
			error: function(xhr, status, error) {
				console.error('Error loading files:', error);
				console.error('Response:', xhr.responseText);
				$('#modal-files').html('<p class="error">' + t('gallery_error_loading') + ' ' + error + '</p>');
			}
		});
	}

	// Update breadcrumbs in the modal
	function updateBreadcrumbs(path) {
		let html = '<a href="#" data-path="">' + t('home') + '</a>';

		if (path) {
			const parts = path.split('/');
			let currentPath = '';

			for (let i = 0; i < parts.length; i++) {
				if (parts[i]) {
					currentPath += parts[i] + '/';
					html += ' / <a href="#" data-path="' + currentPath + '">' + parts[i] + '</a>';
				}
			}
		}

		$('#modal-breadcrumbs').html(html);
	}

	// Display files in the modal (redesigned: compact folder chips + dense image grid)
	function displayFilesInModal(files, currentPath) {
		let html = '';

		// Folder bar — compact pill/chip buttons instead of large cards
		if (files.folders && files.folders.length > 0) {
			html += '<div class="gm-folder-bar">';
			for (let i = 0; i < files.folders.length; i++) {
				const folder = files.folders[i];
				const folderPath = currentPath + folder + '/';
				html += '<button type="button" class="gm-folder-chip" data-path="' + folderPath + '">';
				html += '<span class="gm-folder-icon">📁</span>' + folder;
				html += '</button>';
			}
			html += '</div>';
		}

		// Image grid — square thumbnails with hover overlay
		if (files.files && files.files.length > 0) {
			let imageCount = 0;
			let gridHtml = '';

			for (let i = 0; i < files.files.length; i++) {
				const file = files.files[i];

				if (isImageFile(file.name)) {
					imageCount++;
					const filePath = currentPath + file.name;
					const fileUrl  = getFileUrl(filePath);

					gridHtml += '<div class="file-item" title="' + file.name + '" data-path="' + filePath + '">';
					gridHtml += '<div class="gm-thumb">';
					gridHtml += '<img src="' + fileUrl + '" alt="' + file.name + '" loading="lazy">';
					gridHtml += '<div class="gm-thumb-overlay"><span class="file-name">' + file.name + '</span></div>';
					gridHtml += '</div>';
					gridHtml += '</div>';
				}
			}

			if (imageCount > 0) {
				html += '<div class="gm-files-grid">' + gridHtml + '</div>';
			}
		}

		// Empty state
		if (html === '') {
			html = '<p class="gm-empty">' + t('gallery_no_images') + '</p>';
		}

		// Inject into scrollable body
		$('#modal-files').html(html);

		// Sync the footer selection counter
		updateGallerySelectionIndicator();

		// Wire up drag-selection for the newly rendered items
		initGalleryDragSelect();
	}

	// Rubber-band (lasso) drag selection inside the gallery modal body
	function initGalleryDragSelect() {
		const body = document.getElementById('modal-files');
		if (!body) return;

		// Tear down any previous listeners to avoid stacking on re-renders
		if (body._gmMoveHandler) {
			document.removeEventListener('mousemove', body._gmMoveHandler);
			document.removeEventListener('mouseup',   body._gmUpHandler);
		}

		// Purge any orphaned selection boxes left by an interrupted drag
		// (e.g. mouse released outside the browser window — mouseup never fires)
		document.querySelectorAll('.gm-selection-box').forEach(function(el) { el.remove(); });

		let isSelecting  = false;
		let selBox       = null;
		let startX       = 0;
		let startY       = 0;

		body.addEventListener('mousedown', function(e) {
			// Left button only; ignore clicks on interactive elements
			if (e.button !== 0) return;
			if (e.target.closest('.file-item, .gm-folder-chip, button, a')) return;

			// Drag-select is multi-select by nature — skip it in single-select modes
			const mode = window.gallerySelectionMode || 'gallery';
			if (mode === 'featured-image' || mode === 'og-image') return;

			isSelecting = true;
			startX = e.clientX;
			startY = e.clientY;

			// Create a fixed-position selection box overlay
			selBox = document.createElement('div');
			selBox.className = 'gm-selection-box';
			selBox.style.left   = startX + 'px';
			selBox.style.top    = startY + 'px';
			selBox.style.width  = '0px';
			selBox.style.height = '0px';
			document.body.appendChild(selBox);

			// Clear existing selection unless modifier key is held
			if (!e.ctrlKey && !e.metaKey) {
				$('#gallery-modal .file-item').removeClass('selected');
				updateGallerySelectionIndicator();
			}

			e.preventDefault();
		});

		const onMove = function(e) {
			if (!isSelecting || !selBox) return;

			// Resize and reposition the selection box
			const left   = Math.min(e.clientX, startX);
			const top    = Math.min(e.clientY, startY);
			const width  = Math.abs(e.clientX - startX);
			const height = Math.abs(e.clientY - startY);

			selBox.style.left   = left   + 'px';
			selBox.style.top    = top    + 'px';
			selBox.style.width  = width  + 'px';
			selBox.style.height = height + 'px';

			// Hit-test each file item against the selection box (both in viewport coords)
			const selRect = selBox.getBoundingClientRect();
			document.querySelectorAll('#gallery-modal .file-item').forEach(function(item) {
				const r = item.getBoundingClientRect();
				const overlaps = !(
					r.right  < selRect.left  ||
					r.left   > selRect.right ||
					r.bottom < selRect.top   ||
					r.top    > selRect.bottom
				);
				if (overlaps) {
					item.classList.add('selected');
				} else if (!e.ctrlKey && !e.metaKey) {
					item.classList.remove('selected');
				}
			});

			updateGallerySelectionIndicator();
		};

		const onUp = function() {
			// Remove every selection box in the DOM — handles the current one
			// and any orphans left by previous interrupted drags
			document.querySelectorAll('.gm-selection-box').forEach(function(el) { el.remove(); });
			selBox      = null;
			isSelecting = false;
		};

		// Store references so they can be removed on next render
		body._gmMoveHandler = onMove;
		body._gmUpHandler   = onUp;
		document.addEventListener('mousemove', onMove);
		document.addEventListener('mouseup',   onUp);
	}

	// Check if a file is an image
	function isImageFile(filename) {
		const ext = filename.split('.').pop().toLowerCase();
		return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].indexOf(ext) !== -1;
	}

	// Get the full URL for a file
	function getFileUrl(path) {
		// Base URL from server
		const baseUrl = window.location.protocol + '//' + window.location.host;

		// Get the current path
		const currentPath = window.location.pathname;
		// Find position of /admin/ in the path
		const adminPos = currentPath.indexOf('/admin/');

		let basePath = '';
		if (adminPos !== -1) {
			// Get everything before /admin/ which is our base directory
			basePath = currentPath.substring(0, adminPos);
		}

		// Handle path formatting
		if (path.startsWith('files/')) {
			// If it already has 'files/' prefix, don't add it again
			return baseUrl + basePath + '/' + path;
		} else {
			// Otherwise, add the 'files/' prefix
			return baseUrl + basePath + '/files/' + path;
		}
	}

	// Add image to gallery
	function addImageToGallery(path, url) {
		// Validate URL and path
		if (!url || !path) {
			console.error('Invalid image URL or path');
			return;
		}

		// Optional: Limit gallery items
		const maxGalleryItems = 20;
		const currentItems = $('.gallery-items .gallery-item').length;

		if (currentItems >= maxGalleryItems) {
			window.showModal(t('gallery_max_items').replace('%d', maxGalleryItems), t('warning'));
			return;
		}

		// Get gallery container and next index
		const galleryItems = $('.gallery-items');
		const itemIndex = galleryItems.children().length;

		// Create gallery item HTML
		const html = `
			<div class="gallery-item" data-index="${itemIndex}">
				<img src="${url}" alt="Gallery image">
				<div class="gallery-item-controls">
					<input type="hidden" name="gallery[${itemIndex}][src]" value="${path}">
					<input type="text" name="gallery[${itemIndex}][caption]" placeholder="${t('caption')}">
					<input type="text" name="gallery[${itemIndex}][alt_text]" placeholder="${t('alt_text')}">
					<button type="button" class="remove-gallery-item">${t('remove_image')}</button>
				</div>
			</div>
		`;

		// Add to gallery
		galleryItems.append(html);

		// Scroll to the new item
		galleryItems[0].scrollIntoView({ behavior: 'smooth', block: 'end' });
	}

	// Reindex gallery items (after sorting or removing)
	function reindexGalleryItems() {
		$('.gallery-item').each(function(index) {
			// Update data-index attribute
			$(this).attr('data-index', index);

			// Update input names
			$(this).find('input[name^="gallery["]').each(function() {
				const name = $(this).attr('name');
				const newName = name.replace(/gallery\[\d+\]/, 'gallery[' + index + ']');
				$(this).attr('name', newName);
			});
		});
	}

	// Add a single gallery image (for programmatic use)
	function addGalleryImage(src, caption = '', alt_text = '') {
		// Get next index
		const nextIndex = document.querySelectorAll('.gallery-item').length;

		// Create HTML
		const html = `
			<div class="gallery-item" data-index="${nextIndex}">
				<img src="${src}" alt="Gallery image">
				<div class="gallery-item-controls">
					<input type="hidden" name="gallery[${nextIndex}][src]" value="${src}">
					<input type="text" name="gallery[${nextIndex}][caption]" value="${caption}" placeholder="${t('caption')}">
					<input type="text" name="gallery[${nextIndex}][alt_text]" value="${alt_text}" placeholder="${t('alt_text')}">
					<button type="button" class="remove-gallery-item">${t('remove_image')}</button>
				</div>
			</div>
		`;

		// Add to gallery
		document.querySelector('.gallery-items').insertAdjacentHTML('beforeend', html);
	}


	// ── Named-gallery helpers ─────────────────────────────────────────────────

	function addImagesToNamedGallery(blockIndex, selectedImages) {
		const container = $('.named-gallery-items[data-gallery-index="' + blockIndex + '"]');
		selectedImages.each(function() {
			const path = $(this).data('path');
			const url  = getFileUrl(path);
			const itemIndex = container.children('.gallery-item').length;
			const html = `
				<div class="gallery-item" data-index="${itemIndex}">
					<img src="${url}" alt="Gallery image">
					<div class="gallery-item-controls">
						<input type="hidden" name="galleries[${blockIndex}][images][${itemIndex}][src]" value="${path}">
						<input type="text" name="galleries[${blockIndex}][images][${itemIndex}][caption]" placeholder="Caption">
						<input type="text" name="galleries[${blockIndex}][images][${itemIndex}][alt_text]" placeholder="Alt text">
						<button type="button" class="remove-named-gallery-item">✕</button>
					</div>
				</div>`;
			container.append(html);
		});
		// Ensure Sortable is active on this container
		initSortableOnBlock(
			document.querySelector('.named-gallery-items[data-gallery-index="' + blockIndex + '"]')
		);
	}

	function reindexNamedGalleryItems(blockIdx) {
		const container = $('.named-gallery-items[data-gallery-index="' + blockIdx + '"]');
		container.find('.gallery-item').each(function(imgIdx) {
			$(this).attr('data-index', imgIdx);
			$(this).find('input').each(function() {
				const name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(
						/galleries\[\d+\]\[images\]\[\d+\]/,
						'galleries[' + blockIdx + '][images][' + imgIdx + ']'
					));
				}
			});
		});
	}

	function reindexGalleryBlocks() {
		$('.named-gallery-block').each(function(blockIdx) {
			$(this).attr('data-gallery-index', blockIdx);
			$(this).find('.gallery-label-input').attr('name', 'galleries[' + blockIdx + '][label]');
			$(this).find('.gallery-layout-select').attr('name', 'galleries[' + blockIdx + '][layout]');
			$(this).find('.add-named-gallery-images').attr('data-gallery-index', blockIdx);
			$(this).find('.named-gallery-items').attr('data-gallery-index', blockIdx);
			$(this).find('.shortcode-display').text('[gallery id="' + blockIdx + '"]');
			$(this).find('.copy-shortcode-btn').attr('data-shortcode', '[gallery id="' + blockIdx + '"]');
			$(this).find('.insert-shortcode-btn').attr('data-gallery-id', blockIdx);
			reindexNamedGalleryItems(blockIdx);
		});
	}

	function addGalleryBlock() {
		const blockIdx = $('.named-gallery-block').length;
		const label    = t('gallery_default_label') + ' ' + (blockIdx + 1);
		const html = `
			<div class="named-gallery-block" data-gallery-index="${blockIdx}">
				<div class="named-gallery-header">
					<div class="form-group">
						<button type="button" class="remove-gallery-block btn btn-danger btn-sm">X</button>
						<select name="galleries[${blockIdx}][layout]" class="gallery-layout-select">
							<option value="grid">${t('layout_grid')}</option>
							<option value="masonry">${t('layout_masonry')}</option>
							<option value="justified">${t('layout_justified')}</option>
							<option value="carousel">${t('layout_carousel')}</option>
						</select>
						<input type="text" name="galleries[${blockIdx}][label]" class="gallery-label-input" value="${label}" placeholder="${t('gallery_name_placeholder')}">
					</div>
				</div>
				<div class="gallery-shortcode-bar">
					<code class="shortcode-display">[gallery id="${blockIdx}"]</code>
					<button type="button" class="copy-shortcode-btn btn btn-outline btn-sm" data-shortcode='[gallery id="${blockIdx}"]'>${t('copy')}</button>
					<button type="button" class="insert-shortcode-btn btn btn-outline btn-sm" data-gallery-id="${blockIdx}">${t('gallery_insert_editor')}</button>
				</div>
				<div class="named-gallery-items gallery-items" data-gallery-index="${blockIdx}"></div>
				<button type="button" class="add-named-gallery-images btn btn-outline btn-sm" data-gallery-index="${blockIdx}">+ ${t('add_images')}</button>
			</div>`;
		$('#named-galleries-container').append(html);
		// Init Sortable on the newly added block
		const newContainer = document.querySelector(
			'.named-gallery-items[data-gallery-index="' + blockIdx + '"]'
		);
		initSortableOnBlock(newContainer);
	}

	// Make functions globally accessible
	window.addGalleryImage = addGalleryImage;
	window.openGalleryModal = openGalleryModal;
	window.reindexGalleryItems = reindexGalleryItems;
	window.loadFilesForGallery   = loadFilesForGallery;
	window.addGalleryBlock          = addGalleryBlock;
	window.reindexGalleryBlocks     = reindexGalleryBlocks;
	window.initSortableOnAllBlocks  = initSortableOnAllBlocks;
	window.initSortableOnBlock      = initSortableOnBlock;
})();