// Toggle fields based on content type selection
function toggleFields() {
	var contentType = $('#type').val();

	// Hide all fields first
	$('#articleFields, #projectFields').hide();

	// Show fields based on content type
	if (contentType === 'article' || contentType === 'page') {
		$('#articleFields').show();
	} else if (contentType === 'project') {
		$('#projectFields').show();
	}
}

// Check URL parameters for tab preference
function getUrlParameter(name) {
	const results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
	return results ? decodeURIComponent(results[1]) : null;
}

$(document).ready(function() {
	initTableSorting();

	// Get references to filter elements
	const contentSearch = document.getElementById('content-search');
	const clearSearch = document.getElementById('clear-search');
	const categoryFilter = document.getElementById('category-filter');
	const sortFilter = document.getElementById('sort-filter');

	// Clear any existing event listeners
	if (contentSearch) {
		contentSearch.removeEventListener('input', filterContent);
		contentSearch.addEventListener('input', filterContent);
	}

	if (clearSearch) {
		clearSearch.removeEventListener('click', clearSearchHandler);
		clearSearch.addEventListener('click', clearSearchHandler);
	}

	if (categoryFilter) {
		categoryFilter.removeEventListener('change', filterContent);
		categoryFilter.addEventListener('change', filterContent);
	}

	if (sortFilter) {
		// Sort dropdown is handled in initContentListPagination() where
		// CLState is updated explicitly. This stub is kept for non-list pages
		// that might have a sort-filter element without the full CL system.
		if (!document.querySelector('.content-cards-container')) {
			sortFilter.removeEventListener('change', filterContent);
			sortFilter.addEventListener('change', filterContent);
		}
	}

	// Helper function for clear search
	function clearSearchHandler() {
		if (contentSearch) {
			contentSearch.value = '';
			clearSearch.style.display = 'none';
			filterContent();
		}
	}

	// Show/hide clear button based on search input
	if (contentSearch && clearSearch) {
		contentSearch.addEventListener('input', function() {
			clearSearch.style.display = this.value ? 'block' : 'none';
		});
	}

	// Load view preference from localStorage or default to list view
	const savedView = localStorage.getItem('content-view-preference') || 'list';
	const toggleBtn = document.getElementById('view-toggle-btn');
	if (toggleBtn) {
		toggleBtn.setAttribute('data-current-view', savedView);
	}

	// Show the appropriate view
	if (savedView === 'card') {
		$('.content-cards-container').show();
		$('.content-list-container').hide();
	} else {
		// Default to list view
		$('.content-cards-container').hide();
		$('.content-list-container').show();
	}

	// Tab init: URL ?tab= param takes priority over localStorage fallback.
	const _urlTab    = getUrlParameter('tab');
	const _storageKey = window.location.pathname.split('/').pop() + '-active-tab';
	const _savedTab  = localStorage.getItem(_storageKey);
	const _activeTab = (_urlTab && $(`.tab[data-tab="${_urlTab}"]`).length)
					 ? _urlTab
					 : (_savedTab && $(`.tab[data-tab="${_savedTab}"]`).length ? _savedTab : null);

	$('.tab-content').hide();
	$('.tab').removeClass('active');

	if (_activeTab) {
		$(`.tab[data-tab="${_activeTab}"]`).addClass('active');
		$(`#${_activeTab}-tab`).show();
	} else {
		$('#general-tab').show();
		$('.tab[data-tab="general"]').addClass('active');
	}

	$('.tab').on('click', function(e) {
		e.preventDefault();
		var tabId = $(this).data('tab');
		$('.tab').removeClass('active');
		$(this).addClass('active');
		$('.tab-content').hide();
		$('#' + tabId + '-tab').show();

		const _storageKey = window.location.pathname.split('/').pop() + '-active-tab';
		localStorage.setItem(_storageKey, tabId);
		const _url = new URL(window.location.href);
		_url.searchParams.set('tab', tabId);
		history.replaceState(null, '', _url.toString());
	});

	$('.tab').click(function() {
		var tabName = $(this).text().toLowerCase().split(' ')[0];
	});

	// When clicking the modal window background, close it
	$('.modal-overlay').on('click', function(e) {
		if (e.target === this) {
			$(this).hide();
		}
	});

	// View toggle button
	$('#view-toggle-btn').off('click').on('click', function() {
		const currentView = $(this).attr('data-current-view') || 'list';
		const newView = currentView === 'list' ? 'card' : 'list';
		$(this).attr('data-current-view', newView);

		if (newView === 'card') {
			$('.content-cards-container').show();
			$('.content-list-container').hide();
		} else {
			$('.content-cards-container').hide();
			$('.content-list-container').show();
		}
		localStorage.setItem('content-view-preference', newView);
	});
	initContentListPagination();
});

function getTableStorageKey(table) {
	const page = window.location.pathname + window.location.search;
	const index = Array.from(document.querySelectorAll('table')).indexOf(table);
	return `table-sort:${page}:${index}`;
}

function sortTableByKey(table, sortKey, isAscending) {
	const tbody = table.querySelector('tbody');
	if (!tbody) return;
	const rows = Array.from(tbody.querySelectorAll('tr'));

	rows.sort((rowA, rowB) => {
		let valueA, valueB;
		if (sortKey === 'title') {
			valueA = rowA.getAttribute('data-title') || '';
			valueB = rowB.getAttribute('data-title') || '';
		} else if (sortKey === 'date') {
			valueA = rowA.getAttribute('data-date') || '';
			valueB = rowB.getAttribute('data-date') || '';
		} else if (sortKey === 'category') {
			valueA = rowA.getAttribute('data-category') || '';
			valueB = rowB.getAttribute('data-category') || '';
		} else if (sortKey === 'name') {
			valueA = rowA.querySelector('td:first-child')?.textContent.trim().toLowerCase() || '';
			valueB = rowB.querySelector('td:first-child')?.textContent.trim().toLowerCase() || '';
		} else if (sortKey === 'slug') {
			valueA = rowA.getAttribute('data-slug') || '';
			valueB = rowB.getAttribute('data-slug') || '';
		} else if (sortKey === 'types') {
			valueA = rowA.querySelector('td:nth-child(2)')?.textContent.trim().toLowerCase() || '';
			valueB = rowB.querySelector('td:nth-child(2)')?.textContent.trim().toLowerCase() || '';
		} else if (sortKey === 'count') {
			valueA = parseInt(rowA.querySelector('td:nth-child(3)')?.textContent.trim()) || 0;
			valueB = parseInt(rowB.querySelector('td:nth-child(3)')?.textContent.trim()) || 0;
		}
		if (typeof valueA === 'number' && typeof valueB === 'number') {
			return isAscending ? valueA - valueB : valueB - valueA;
		}
		return isAscending ? valueA.localeCompare(valueB) : valueB.localeCompare(valueA);
	});

	rows.forEach(row => tbody.appendChild(row));
}

function initTableSorting() {
	document.querySelectorAll('th.sortable').forEach(headerCell => {
		headerCell.addEventListener('click', function() {
			const table   = this.closest('table');
			const sortKey = this.getAttribute('data-sort');
			const isAsc   = !this.classList.contains('sorted-asc');

			table.querySelectorAll('th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
			this.classList.add(isAsc ? 'sorted-asc' : 'sorted-desc');

			const sortIcon = this.querySelector('.sort-icon');
			if (sortIcon) sortIcon.textContent = isAsc ? '↑' : '↓';

			table.dataset.sortKey       = sortKey;
			table.dataset.sortDirection = isAsc ? 'asc' : 'desc';

			// Content list: route through the unified virtual rendering system
			if (document.querySelector('.content-cards-container')) {
				CLState.sortKey = sortKey;
				CLState.sortDir = isAsc ? 'asc' : 'desc';
				CLState.page    = 1;
				renderList();
			} else {
				// Other tables (categories, tags) use direct DOM sort
				sortTableByKey(table, sortKey, isAsc);
			}

			try {
				localStorage.setItem(getTableStorageKey(table), JSON.stringify({
					sortKey:   sortKey,
					direction: isAsc ? 'asc' : 'desc',
				}));
			} catch(e) {}
		});
	});

	// Restore saved sort state
	document.querySelectorAll('table').forEach(table => {
		try {
			const saved = localStorage.getItem(getTableStorageKey(table));
			if (!saved) return;
			const { sortKey, direction } = JSON.parse(saved);
			const headerCell = table.querySelector(`th[data-sort="${sortKey}"]`);
			if (!headerCell) return;

			table.querySelectorAll('th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
			headerCell.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

			const sortIcon = headerCell.querySelector('.sort-icon');
			if (sortIcon) sortIcon.textContent = direction === 'asc' ? '↑' : '↓';

			table.dataset.sortKey       = sortKey;
			table.dataset.sortDirection = direction;

			if (document.querySelector('.content-cards-container')) {
				CLState.sortKey = sortKey;
				CLState.sortDir = direction;
			} else {
				sortTableByKey(table, sortKey, direction === 'asc');
			}
		} catch(e) {}
	});
}

function getFileUrl(path) {
	var baseUrl = window.location.protocol + '//' + window.location.host;
	var currentPath = window.location.pathname;
	var adminPos = currentPath.indexOf('/admin/');
	var basePath = '';
	if (adminPos !== -1) {
		basePath = currentPath.substring(0, adminPos);
	}
	if (path.startsWith('files/')) {
		path = path;
	} else if (path.startsWith('../files/')) {
		path = path.substring(3);
	} else {
		path = 'files/' + path.replace(/^\/+/, '');
	}
	return baseUrl + basePath + '/' + path;
}

function ucfirst(str) {
	return str.charAt(0).toUpperCase() + str.slice(1);
}

document.addEventListener('DOMContentLoaded', function() {

	// Category badges click handler
	const categoryBadges = document.querySelectorAll('.category-badge');
	const categoryInput = document.getElementById('category');

	if (categoryBadges.length && categoryInput) {
		categoryBadges.forEach(badge => {
			badge.addEventListener('click', function() {
				const value = this.getAttribute('data-value');
				categoryInput.value = value;
				categoryBadges.forEach(b => b.classList.remove('selected'));
				this.classList.add('selected');
			});
		});
	}

	// Tag badges click handler
	const tagBadges = document.querySelectorAll('.tag-badge');
	const tagsInput = document.getElementById('tags');

	if (tagBadges.length && tagsInput) {
		tagBadges.forEach(badge => {
			badge.addEventListener('click', function() {
				const value = this.getAttribute('data-value');
				if (!value) return;

				let currentTags = tagsInput.value ? tagsInput.value.split(',').map(tag => tag.trim()) : [];
				const tagIndex  = currentTags.indexOf(value);

				if (tagIndex === -1) {
					currentTags.push(value);
					this.classList.add('selected');
				} else {
					currentTags.splice(tagIndex, 1);
					this.classList.remove('selected');
				}
				tagsInput.value = currentTags.join(', ');
			});
		});

		// Pre-select tags from input value (edit form)
		if (tagsInput.value) {
			const currentTags = tagsInput.value.split(',').map(tag => tag.trim());
			tagBadges.forEach(badge => {
				if (currentTags.includes(badge.getAttribute('data-value'))) {
					badge.classList.add('selected');
				}
			});
		}

		// Pre-select category from input value (edit form)
		if (categoryInput && categoryInput.value) {
			const currentCategory = categoryInput.value;
			categoryBadges.forEach(badge => {
				if (badge.getAttribute('data-value') === currentCategory) {
					badge.classList.add('selected');
				}
			});
		}
	}
});

// Handle sidebar panel collapsibles (content-add/edit pages)
document.addEventListener('DOMContentLoaded', function() {
	const collapsiblePanels = document.querySelectorAll('.panel-collapsible');
	const STORAGE_KEY = 'collapsible-panel-states';

	function getSavedStates() {
		try {
			return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
		} catch (e) {
			return {};
		}
	}

	function saveState(panelId, isCollapsed) {
		const states = getSavedStates();
		states[panelId] = isCollapsed;
		localStorage.setItem(STORAGE_KEY, JSON.stringify(states));
	}

	function getPanelId(panel) {
		const header = panel.querySelector('.panel-header') || panel.querySelector('h3');
		if (header) {
			return 'panel-' + header.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
		}
		return null;
	}

	const savedStates = getSavedStates();

	collapsiblePanels.forEach(panel => {
		const header  = panel.querySelector('.panel-header') || panel.querySelector('h3');
		const content = panel.querySelector('.panel-content');
		const panelId = getPanelId(panel);

		if (header && content) {
			header.style.cursor = 'pointer';

			let icon = header.querySelector('.toggle-icon');
			if (!icon) {
				icon = document.createElement('span');
				icon.className   = 'toggle-icon';
				icon.textContent = '▶';
				header.insertBefore(icon, header.firstChild);
			}

			let isCollapsed;
			if (panelId && savedStates.hasOwnProperty(panelId)) {
				isCollapsed = savedStates[panelId];
				panel.classList.toggle('collapsed', isCollapsed);
			} else {
				isCollapsed = panel.classList.contains('collapsed');
			}

			if (isCollapsed) {
				content.style.cssText = 'display:none;opacity:0;max-height:0';
				icon.textContent = '▶';
			} else {
				content.style.cssText = 'display:block;opacity:1;max-height:2000px';
				icon.textContent = '▼';
			}

			header.addEventListener('click', function() {
				panel.classList.toggle('collapsed');
				const nowCollapsed = panel.classList.contains('collapsed');
				icon.textContent   = nowCollapsed ? '▶' : '▼';

				if (panelId) saveState(panelId, nowCollapsed);

				if (nowCollapsed) {
					content.style.opacity   = '0';
					content.style.maxHeight = '0';
					content.style.paddingTop = '0';
					content.style.marginTop  = '0';
					setTimeout(() => content.style.display = 'none', 300);
				} else {
					content.style.display   = 'block';
					void content.offsetWidth;
					content.style.opacity   = '1';
					content.style.maxHeight = '2000px';
					content.style.paddingTop = '';
					content.style.marginTop  = '';
				}
			});
		}
	});
});

document.addEventListener('DOMContentLoaded', function() {
	const footerShowSocial      = document.getElementById('footer_show_social');
	const socialLinksContainer  = document.getElementById('social-links-container');
	let socialLinkIndex = window.initialSocialLinkIndex || 0;

	if (footerShowSocial && socialLinksContainer) {
		socialLinksContainer.removeAttribute('hidden');

		function setSocialLinksVisibility(visible) {
			socialLinksContainer.style.setProperty('display', visible ? 'block' : 'none', 'important');
		}

		setSocialLinksVisibility(footerShowSocial.checked);
		footerShowSocial.addEventListener('change', function() {
			setSocialLinksVisibility(this.checked);
		});
	}

	const addSocialLinkBtn = document.getElementById('add-social-link');
	const socialLinks      = document.getElementById('social-links');

	if (addSocialLinkBtn) {
		addSocialLinkBtn.addEventListener('click', function() {
			const newRow = document.createElement('div');
			newRow.className = 'social-link-row';
			newRow.innerHTML = `
		<div class="form-group">
		  <select name="settings[footer_social_links][${socialLinkIndex}][platform]" class="form-control">
			<option value="">${window.t('select_platform')}</option>
			<option value="instagram">Instagram</option>
			<option value="twitter">Twitter</option>
			<option value="github">GitHub</option>
			<option value="facebook">Facebook</option>
			<option value="linkedin">LinkedIn</option>
		  </select>
		</div>
		<div class="form-group">
		  <input type="text" name="settings[footer_social_links][${socialLinkIndex}][url]" placeholder="URL" class="form-control">
		</div>
		<button type="button" class="button danger remove-social-link">${window.t('remove')}</button>
	  `;
			socialLinks.appendChild(newRow);
			socialLinkIndex++;

			newRow.querySelector('.remove-social-link').addEventListener('click', function() {
				newRow.remove();
			});
		});
	}

	document.querySelectorAll('.remove-social-link').forEach(button => {
		button.addEventListener('click', function() {
			this.closest('.social-link-row').remove();
		});
	});
});

// ── Unified content-list virtual rendering system ─────────────
//
// Items are stored as a JSON payload injected by PHP (#cl-data).
// renderList() generates HTML only for the current page slice,
// keeping the DOM minimal regardless of total item count.

/**
 * Persistent state: filter + sort + pagination.
 */
const CLState = {
	page:    1,
	perPage: parseInt(localStorage.getItem('cl-per-page') || '25', 10),
	sortKey: 'date',
	sortDir: 'desc',
};

/** Full item array loaded once from the JSON payload. */
let _clAllItems = null;

/** Whether batch mode is currently active (controls checkbox visibility). */
let _clBatchMode = false;

/**
 * Returns the raw item array from the JSON payload.
 * Parsed once and cached in _clAllItems.
 *
 * @returns {Array<Object>}
 */
function getAllItems() {
	if (_clAllItems !== null) return _clAllItems;
	const el = document.getElementById('cl-data');
	if (!el) return [];
	try {
		_clAllItems = JSON.parse(el.textContent || el.innerText);
	} catch (e) {
		_clAllItems = [];
	}
	return _clAllItems;
}

/**
 * Returns dataset attributes from #cl-data as a convenience object.
 *
 * @returns {Object}
 */
function getCLMeta() {
	const el = document.getElementById('cl-data');
	if (!el) return {};
	return el.dataset;
}

/**
 * Builds the HTML string for one card view item.
 *
 * @param {Object} item
 * @param {Object} meta  - dataset from #cl-data
 * @param {string} contentType
 * @returns {string}
 */
function buildCard(item, meta, contentType) {
	const editUrl = meta.editBase + item.idx;
	const title   = _esc(item.title) || '—';

	// Thumbnail or initial letter placeholder
	let imageHtml;
	if (item.image) {
		imageHtml = `<a href="${editUrl}" class="edit-list-link">
			<img src="../${_esc(item.image)}" alt="${title}" class="content-thumbnail" loading="lazy">
		</a>`;
	} else {
		const initial = (contentType.charAt(0) || '?').toUpperCase();
		imageHtml = `<a href="${editUrl}" class="edit-list-link">
			<div class="no-image"><span>${initial}</span></div>
		</a>`;
	}

	// Scheduled badge
	const scheduledBadge = item.status === 'scheduled'
		? `<span class="badge badge-scheduled" title="${_esc(item.publish_at)}">🕐 ${_esc(meta.i18nScheduled)}</span>`
		: '';

	// Tags (max 2 + overflow)
	let tagsHtml = '';
	if (item.tags && item.tags.length > 0) {
		const shown = item.tags.slice(0, 2).map(t => `<span class="tag-pill">${_esc(t)}</span>`).join('');
		const more  = item.tags.length > 2 ? `<span class="more-tags">+${item.tags.length - 2}</span>` : '';
		tagsHtml = `<div class="meta-tags">${shown}${more}</div>`;
	}

	const dateLabel = item.date || meta.i18nNoDate;
	const catLabel  = item.category ? `<span class="meta-category">${_esc(item.category)}</span>` : '';

	// Batch checkbox
	const checkbox = `<div class="batch-checkbox-cell" style="display:${_clBatchMode ? 'block' : 'none'};">
		<input type="checkbox" class="batch-item" data-index="${item.idx}" data-type="${_esc(contentType)}">
	</div>`;

	return `<div class="content-card${_clBatchMode ? ' batch-mode' : ''}"
		data-title="${_esc(item.title.toLowerCase())}"
		data-category="${_esc(item.category)}"
		data-date="${_esc(item.date)}">
		${checkbox}
		<div class="card-image">${imageHtml}</div>
		<div class="card-content">
			<h3 class="card-title">
				<a href="${editUrl}" class="edit-list-link">${title}</a>
			</h3>
			${scheduledBadge}
			<div class="card-meta">
				<span class="meta-date">${_esc(dateLabel)}</span>
				${catLabel}
				${tagsHtml}
			</div>
			<div class="card-actions">
				<a href="${editUrl}" class="card-btn edit-btn">
					<span class="btn-icon">✎</span> ${_esc(meta.i18nEdit)}
				</a>
				<a href="${_esc(item.view_url)}" target="_blank" class="card-btn view-btn">
					<span class="btn-icon">👁️</span> ${_esc(meta.i18nView)}
				</a>
				<button type="button" class="card-btn delete-btn"
					data-type="${_esc(contentType)}"
					data-index="${item.idx}"
					data-title="${_esc(item.title)}">
					<span class="btn-icon">🗑️</span> ${_esc(meta.i18nDelete)}
				</button>
			</div>
		</div>
	</div>`;
}

/**
 * Builds the HTML string for one table row (list view).
 *
 * @param {Object} item
 * @param {Object} meta
 * @param {string} contentType
 * @param {boolean} showCategory
 * @returns {string}
 */
function buildRow(item, meta, contentType, showCategory) {
	const editUrl = meta.editBase + item.idx;
	const title   = _esc(item.title) || '—';

	// Thumbnail or icon
	let thumbHtml;
	if (item.image) {
		thumbHtml = `<div class="mini-preview">
			<img src="../${_esc(item.image)}" alt="" loading="lazy">
		</div>`;
	} else {
		thumbHtml = `<div class="activity-icon unknown-icon"></div>`;
	}

	const scheduledBadge = item.status === 'scheduled'
		? `<span class="badge badge-scheduled" title="${_esc(item.publish_at)}">🕐 ${_esc(meta.i18nScheduled)}</span>`
		: '';

	// Tags (max 3 + overflow)
	let tagsHtml;
	if (item.tags && item.tags.length > 0) {
		const shown = item.tags.slice(0, 3).map(t => `<span class="tag-pill">${_esc(t)}</span>`).join('');
		const more  = item.tags.length > 3 ? `<span class="more-tags">+${item.tags.length - 3}</span>` : '';
		tagsHtml = `<div class="tags-wrapper">${shown}${more}</div>`;
	} else {
		tagsHtml = `<span class="text-muted">${_esc(meta.i18nNoTags)}</span>`;
	}

	const categoryCell = showCategory
		? `<td>${_esc(item.category || meta.i18nUncategorized)}</td>`
		: '';

	const checkbox = `<td class="batch-checkbox-cell" style="display:${_clBatchMode ? 'table-cell' : 'none'};">
		<input type="checkbox" class="batch-item" data-index="${item.idx}" data-type="${_esc(contentType)}">
	</td>`;

	return `<tr class="content-row${_clBatchMode ? ' batch-mode' : ''}"
		data-title="${_esc(item.title.toLowerCase())}"
		data-category="${_esc(item.category)}"
		data-date="${_esc(item.date)}">
		${checkbox}
		<td class="title-cell">
			<div class="title-with-preview">
				${thumbHtml}
				<b><a href="${editUrl}" class="edit-list-link">${title}</a></b>
				${scheduledBadge}
			</div>
		</td>
		<td>${_esc(item.date || meta.i18nNoDate)}</td>
		${categoryCell}
		<td>${tagsHtml}</td>
		<td class="actions-cell">
			<a href="${editUrl}" class="table-btn edit-btn">
				<span class="btn-icon">✎</span> ${_esc(meta.i18nEdit)}
			</a>
			<a href="${_esc(item.view_url)}" target="_blank" class="table-btn view-btn">
				<span class="btn-icon">👁️</span> ${_esc(meta.i18nView)}
			</a>
			<button type="button" class="table-btn delete-btn"
				data-type="${_esc(contentType)}"
				data-index="${item.idx}"
				data-title="${_esc(item.title)}">
				<span class="btn-icon">🗑️</span> ${_esc(meta.i18nDelete)}
			</button>
		</td>
	</tr>`;
}

/**
 * Minimal HTML-escape helper.
 *
 * @param {*} str
 * @returns {string}
 */
function _esc(str) {
	if (str === null || str === undefined) return '';
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

/**
 * Main render function.
 * 1. Reads filter inputs + CLState
 * 2. Filters the full item array
 * 3. Sorts
 * 4. Paginates
 * 5. Generates HTML only for the visible slice and injects it into the DOM
 * 6. Updates the pagination bar
 */
function renderList() {
	const cardsContainer = document.querySelector('.content-cards-container');
	if (!cardsContainer) return; // Not a content-list page

	const meta        = getCLMeta();
	const contentType = meta.type || '';
	const showCat     = (contentType === 'article' || contentType === 'project');

	// ── 1. Read filter inputs ─────────────────────────────────
	const searchTerm  = (document.getElementById('content-search')?.value || '').toLowerCase().trim();
	const categoryVal = (document.getElementById('category-filter')?.value || '').toLowerCase();

	// ── 2. Filter ─────────────────────────────────────────────
	const all = getAllItems();
	const filtered = all.filter(item => {
		const matchSearch   = !searchTerm  || item.title.toLowerCase().includes(searchTerm);
		const matchCategory = !categoryVal || item.category.toLowerCase() === categoryVal;
		return matchSearch && matchCategory;
	});

	// ── 3. Sort ───────────────────────────────────────────────
	filtered.sort((a, b) => {
		let va, vb;
		switch (CLState.sortKey) {
			case 'title':
			case 'name':
				va = a.title.toLowerCase();    vb = b.title.toLowerCase();    break;
			case 'category':
				va = a.category.toLowerCase(); vb = b.category.toLowerCase(); break;
			default: // 'date'
				va = a.date;  vb = b.date;
		}
		const cmp = va < vb ? -1 : va > vb ? 1 : 0;
		return CLState.sortDir === 'asc' ? cmp : -cmp;
	});

	// ── 4. Paginate ───────────────────────────────────────────
	const total    = filtered.length;
	const perPage  = CLState.perPage === 0 ? total : CLState.perPage;
	const totalPgs = perPage > 0 ? Math.ceil(total / perPage) : 1;
	if (CLState.page > totalPgs) CLState.page = Math.max(1, totalPgs);
	if (CLState.page < 1)        CLState.page = 1;

	const start     = (CLState.page - 1) * (perPage || 1);
	const end       = CLState.perPage === 0 ? total : start + perPage;
	const pageSlice = filtered.slice(start, end);

	// ── 5. Render page slice into DOM ─────────────────────────
	const cardHtml = pageSlice.map(item => buildCard(item, meta, contentType)).join('');
	cardsContainer.innerHTML = cardHtml;

	const tbody = document.getElementById('cl-tbody');
	if (tbody) {
		tbody.innerHTML = pageSlice.map(item => buildRow(item, meta, contentType, showCat)).join('');
	}

	// ── 6. No-results message ─────────────────────────────────
	const noResults = document.getElementById('no-results');
	if (noResults) noResults.style.display = total === 0 ? 'block' : 'none';

	// ── 7. Pagination bar ─────────────────────────────────────
	renderPagination(total, perPage, totalPgs, start, end);
}

/**
 * Renders the pagination bar.
 *
 * @param {number} total
 * @param {number} perPage
 * @param {number} totalPgs
 * @param {number} start
 * @param {number} end
 */
function renderPagination(total, perPage, totalPgs, start, end) {
	const bar     = document.getElementById('cl-pagination');
	const info    = document.getElementById('cl-pagination-info');
	const pages   = document.getElementById('cl-pg-pages');
	const btnPrev = document.getElementById('cl-pg-prev');
	const btnNext = document.getElementById('cl-pg-next');

	if (!bar) return;

	if (total === 0 || (totalPgs <= 1 && CLState.perPage === 0)) {
		bar.style.display = 'none';
		return;
	}
	bar.style.display = 'flex';

	const displayEnd = Math.min(end, total);
	if (info) {
		info.textContent = total > 0 ? `${start + 1}–${displayEnd} of ${total}` : '';
	}

	if (btnPrev) btnPrev.disabled = CLState.page <= 1;
	if (btnNext) btnNext.disabled = CLState.page >= totalPgs;

	if (pages) {
		pages.innerHTML = '';
		if (totalPgs <= 1) return;

		const toShow = new Set([1, totalPgs]);
		for (let p = Math.max(1, CLState.page - 2); p <= Math.min(totalPgs, CLState.page + 2); p++) {
			toShow.add(p);
		}

		const sorted = [...toShow].sort((a, b) => a - b);
		let prev = 0;
		sorted.forEach(p => {
			if (prev && p - prev > 1) {
				const dots = document.createElement('span');
				dots.className   = 'cl-pg-ellipsis';
				dots.textContent = '…';
				pages.appendChild(dots);
			}
			const btn = document.createElement('button');
			btn.className   = 'cl-pg-page' + (p === CLState.page ? ' active' : '');
			btn.textContent = p;
			btn.addEventListener('click', () => { CLState.page = p; renderList(); });
			pages.appendChild(btn);
			prev = p;
		});
	}
}

/**
 * Content filtering — resets to page 1 and re-renders.
 */
function filterContent() {
	CLState.page = 1;
	renderList();
}

/**
 * Initializes the content-list system.
 * Called from $(document).ready.
 */
function initContentListPagination() {
	if (!document.querySelector('.content-cards-container')) return;

	// ── Restore per-page from localStorage ───────────────────
	const savedPerPage = parseInt(localStorage.getItem('cl-per-page') || '25', 10);
	CLState.perPage    = savedPerPage;
	const perPageSelect = document.getElementById('per-page-select');
	if (perPageSelect) {
		const opts = Array.from(perPageSelect.options).map(o => parseInt(o.value, 10));
		perPageSelect.value = opts.includes(savedPerPage) ? savedPerPage : 25;
		perPageSelect.addEventListener('change', function () {
			CLState.perPage = parseInt(this.value, 10);
			CLState.page    = 1;
			localStorage.setItem('cl-per-page', CLState.perPage);
			renderList();
		});
	}

	// ── Sort dropdown ─────────────────────────────────────────
	const sortDropdown = document.getElementById('sort-filter');
	if (sortDropdown) {
		const initParts = sortDropdown.value.split('-');
		CLState.sortKey = initParts[0] || 'date';
		CLState.sortDir = initParts[1] || 'desc';
		sortDropdown.addEventListener('change', function () {
			const parts     = this.value.split('-');
			CLState.sortKey = parts[0] || 'date';
			CLState.sortDir = parts[1] || 'desc';
			CLState.page    = 1;
			renderList();
		});
	}

	// ── Prev / Next buttons ───────────────────────────────────
	document.getElementById('cl-pg-prev')?.addEventListener('click', () => {
		if (CLState.page > 1) { CLState.page--; renderList(); }
	});
	document.getElementById('cl-pg-next')?.addEventListener('click', () => {
		CLState.page++;
		renderList();
	});

	// ── Initial render ────────────────────────────────────────
	renderList();
}

// Re-export applySorting for column-header click handler in initTableSorting()
function applySorting(sortValue) {
	const parts = (sortValue || 'date-desc').split('-');
	CLState.sortKey = parts[0] || 'date';
	CLState.sortDir = parts[1] || 'desc';
	renderList();
}