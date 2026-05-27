/**
 * SynaptikCMS Editor Common
 * Shared utilities used by editor.js (WYSIWYG) and editor-markdown.js (Markdown).
 * Must be loaded before both editor files.
 *
 * Responsibilities:
 *  - Editor registry: each format module registers its API
 *  - switchFormat(): live switching between WYSIWYG and Markdown with show/hide
 *  - Global dispatch: window.insertImageAtCursor / window.insertShortcodeAtCursor
 *  - Autosave: single implementation, delegates sync to the active editor
 *  - Shared CSS: dropdown and toolbar styles available regardless of active editor
 */
window.EditorCommon = (function () {
	'use strict';

	const t = (key, fb) => window.CMS_LANG?.[key] ?? fb ?? key;

	// ── Editor registry ───────────────────────────────────────────────────────
	// api = { syncContent, insertImage, insertShortcode, show, hide }
	const _registry = {};

	function registerEditor(format, api) {
		_registry[format] = api;
	}

	function _activeEditor() {
		return _registry[window.CONTENT_FORMAT || 'html'] || null;
	}

	// ── Shared CSS ────────────────────────────────────────────────────────────
	// Injected by editor-common (always loaded) so both editors get dropdown styles.
	(function _injectSharedCSS() {
		const id = 'synaptik-editor-shared-css';
		if (document.getElementById(id)) return;
		const style = document.createElement('style');
		style.id = id;
		style.textContent = `
/* Shared editor toolbar styles — required by both WYSIWYG and Markdown editors */
.editor-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:2px; padding:6px 8px; background:var(--bg-toolbar,#f8f9fa); border-bottom:1px solid var(--border-light,#e0e0e0); }
.editor-btn { display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:28px; padding:0 5px; border:1px solid transparent; border-radius:4px; background:none; font-size:13px; cursor:pointer; color:var(--text,#333); transition:background .12s,border-color .12s; }
.editor-btn:hover { background:var(--bg-hover,#e9ecef); border-color:var(--border-light,#ddd); }
.editor-btn.active-view { background:var(--primary,#3b82f6); color:#fff; }
.editor-separator { display:inline-block; width:1px; height:20px; background:var(--border-light,#ddd); margin:0 3px; }
.editor-dropdown { position:relative; display:inline-flex; }
.editor-dropdown-btn { display:inline-flex; align-items:center; gap:2px; }
.editor-dropdown-arrow { font-size:8px; opacity:.6; margin-left:1px; }
.editor-dropdown-menu { display:none; position:absolute; top:calc(100% + 4px); left:0; background:#fff; border:1px solid #d0d7de; border-radius:6px; padding:4px; z-index:9999; min-width:160px; box-shadow:0 6px 18px rgba(0,0,0,.15); flex-direction:column; gap:2px; }
.editor-dropdown-menu.open { display:flex; }
.editor-dropdown-item { display:flex; align-items:center; gap:8px; width:100%; background:none; border:none; color:#1a1a1a; cursor:pointer; padding:6px 10px; border-radius:4px; font-size:13px; text-align:left; white-space:nowrap; }
.editor-dropdown-item:hover { background:#f0f6ff; color:#0969da; }
.dropdown-item-label { font-size:12px; opacity:.85; }
/* Format switcher */
.editor-format-switcher { display:flex; align-items:center; gap:4px; padding:6px 0 4px; margin: 0 20px; }
.editor-format-tab { padding:4px 12px; border:1px solid var(--border-light,#ddd); border-radius:4px; background:transparent; font-size:12px; font-weight:500; color:var(--text-muted,#888); cursor:pointer; transition:background .15s,color .15s,border-color .15s; }
.editor-format-tab:hover:not(.active) { border-color:var(--primary,#3b82f6); color:white; }
.editor-format-tab.active { background:var(--primary,#3b82f6); border-color:var(--primary,#3b82f6); color:#fff; cursor:default; }
`;
		(document.head || document.documentElement).appendChild(style);
	})();

	// ── Format switching ──────────────────────────────────────────────────────
	function switchFormat(newFormat) {
		const current = window.CONTENT_FORMAT || 'html';
		if (newFormat === current) return;

// 		const msg = newFormat === 'markdown'
// 			? t('editor_switch_to_md_confirm',   'Switch to Markdown? Your content will be preserved as-is — it will NOT be converted.')
// 			: t('editor_switch_to_html_confirm', 'Switch to WYSIWYG? Your content will be preserved as-is — it will NOT be converted.');
// 
// 		if (!confirm(msg)) return;

		// Sync then hide current editor
		const currentEd = _activeEditor();
		if (currentEd) {
			if (typeof currentEd.syncContent === 'function') currentEd.syncContent();
			if (typeof currentEd.hide === 'function') currentEd.hide();
		}

		window.CONTENT_FORMAT = newFormat;
		const input = document.getElementById('content-format');
		if (input) input.value = newFormat;

		// Update tab visual state
		document.querySelectorAll('.editor-format-tab').forEach(btn => {
			btn.classList.toggle('active', btn.dataset.format === newFormat);
		});

		// Show (and lazy-init if needed) the target editor
		const nextEd = _registry[newFormat];
		if (nextEd && typeof nextEd.show === 'function') {
			nextEd.show();
		}
	}

	// ── Global insertion dispatchers ──────────────────────────────────────────
	window.insertImageAtCursor = function (src, alt, autoClose) {
		const ed = _activeEditor();
		if (ed && typeof ed.insertImage === 'function') ed.insertImage(src, alt, autoClose);
	};

	window.insertShortcodeAtCursor = function (shortcode) {
		const ed = _activeEditor();
		if (ed && typeof ed.insertShortcode === 'function') ed.insertShortcode(shortcode);
	};

	// ── Autosave ──────────────────────────────────────────────────────────────
	let _timer        = null;
	let _draftId      = null;
	let _busy         = false;
	let _userEnabled  = true;
	const INTERVAL    = 300000;

	function formatTimestamp(ts) {
		const ago = Math.floor(Date.now() / 1000) - ts;
		if (ago < 60)   return t('editor_just_now',    'just now');
		if (ago < 3600) return t('editor_minutes_ago', '%d min. ago').replace('%d', Math.floor(ago / 60));
		return new Date(ts * 1000).toLocaleTimeString();
	}

	function _fade(el) {
		setTimeout(() => {
			el.style.opacity = '0';
			setTimeout(() => { el.textContent = ''; el.className = 'autosave-status'; el.style.opacity = '1'; }, 300);
		}, 5000);
	}

	function saveDraft() {
		if (_busy) return;
		const ed = _activeEditor();
		if (ed && typeof ed.syncContent === 'function') ed.syncContent();

		const form = document.getElementById('content-form');
		if (!form) return;

		const fd = new FormData(form);
		const params = new URLSearchParams(window.location.search);
		fd.set('index', params.get('index') || -1);

		const galleries = [];
		document.querySelectorAll('.named-gallery-block').forEach(block => {
			const idx    = parseInt(block.dataset.galleryIndex);
			const label  = block.querySelector('.gallery-label-input')?.value  || ('Gallery ' + (idx + 1));
			const layout = block.querySelector('.gallery-layout-select')?.value || 'grid';
			const images = [];
			block.querySelectorAll('.named-gallery-items .gallery-item').forEach(item => {
				const src     = item.querySelector('input[name*="[src]"]')?.value;
				const caption = item.querySelector('input[name*="[caption]"]')?.value  || '';
				const alt     = item.querySelector('input[name*="[alt_text]"]')?.value || '';
				if (src) images.push({ src, caption, alt_text: alt });
			});
			galleries.push({ label, layout, images });
		});
		fd.set('galleries', JSON.stringify(galleries));
		fd.delete('gallery');
		fd.delete('gallery_layout');
		if (_draftId) fd.set('draft_id', _draftId);

		const content  = fd.get('content')  || '';
		const title    = fd.get('title')    || '';
		const statusEl = document.getElementById('autosave-status');

		if (content.length < 50 && title.length < 3) {
			if (statusEl) { statusEl.textContent = t('editor_content_too_short', 'Content too short!'); statusEl.className = 'autosave-status autosave-error'; _fade(statusEl); }
			return;
		}
		if (statusEl) { statusEl.textContent = t('editor_saving', 'Saving...'); statusEl.className = 'autosave-status autosave-saving'; }

		_busy = true;
		fetch('autosave.php', { method: 'POST', body: fd })
			.then(r => r.json())
			.then(data => {
				_busy = false;
				if (data.error) {
					if (statusEl) { statusEl.textContent = t('editor_autosave_failed', 'Autosave failed!'); statusEl.className = 'autosave-status autosave-error'; _fade(statusEl); }
					return;
				}
				if (data.draft_id) _draftId = data.draft_id;
				const ts = data.timestamp || Math.floor(Date.now() / 1000);
				if (statusEl) { statusEl.textContent = t('editor_saved', 'Saved %s').replace('%s', formatTimestamp(ts)); statusEl.className = 'autosave-status autosave-saved'; _fade(statusEl); }
			})
			.catch(() => {
				_busy = false;
				if (statusEl) { statusEl.textContent = t('editor_autosave_failed', 'Autosave failed!'); statusEl.className = 'autosave-status autosave-error'; _fade(statusEl); }
			});
	}

	function startAutoSave() {
		stopAutoSave();
		if (!window.AUTOSAVE_ENABLED_BY_SETTINGS || !_userEnabled) return;
		_timer = setInterval(saveDraft, INTERVAL);
	}

	function stopAutoSave() {
		if (_timer) { clearInterval(_timer); _timer = null; }
	}

	return { registerEditor, switchFormat, saveDraft, startAutoSave, stopAutoSave, formatTimestamp };
})();

document.addEventListener('DOMContentLoaded', function () {
	const t = (key, fb) => window.CMS_LANG?.[key] ?? fb ?? key;

	// Wire autosave UI
	const saveBtn = document.getElementById('save-draft-btn');
	if (saveBtn) saveBtn.addEventListener('click', e => { e.preventDefault(); window.EditorCommon.saveDraft(); });

	const toggleEl = document.getElementById('toggle-autosave');
	if (toggleEl) {
		if (!window.AUTOSAVE_ENABLED_BY_SETTINGS) {
			toggleEl.disabled = true; toggleEl.checked = false;
			const lbl = toggleEl.closest('label');
			if (lbl) { lbl.style.opacity = '0.5'; lbl.title = t('editor_autosave_disabled_tooltip', 'Autosave is disabled in site settings'); }
		}
		toggleEl.addEventListener('change', function () {
			if (!window.AUTOSAVE_ENABLED_BY_SETTINGS) { this.checked = false; return; }
			this.checked ? window.EditorCommon.startAutoSave() : window.EditorCommon.stopAutoSave();
		});
	}

	if (window.AUTOSAVE_ENABLED_BY_SETTINGS !== false) window.EditorCommon.startAutoSave();
});