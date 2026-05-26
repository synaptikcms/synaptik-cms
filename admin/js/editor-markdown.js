/**
 * SynaptikCMS Markdown Editor
 * CodeMirror 5-based editor. Always initializes on DOMContentLoaded but stays
 * hidden when WYSIWYG is the active format. Activated via EditorCommon.switchFormat()
 * or on load when window.CONTENT_FORMAT === 'markdown'.
 */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	const t = (key, fb) => window.CMS_LANG?.[key] ?? fb ?? key;

	const contentTextarea = document.getElementById('content');
	if (!contentTextarea) return;

	// ── State ─────────────────────────────────────────────────────────────────
	let cmEditor     = null;
	let cmLoaded     = false;
	let isFullscreen = false;
	const initialFormat = window.CONTENT_FORMAT || 'html';

	// ── Shared hidden input ───────────────────────────────────────────────────
	// editor.js creates this first; we find or create it.
	let contentHidden = document.getElementById('content-hidden');
	if (!contentHidden) {
		contentHidden = document.createElement('input');
		contentHidden.type = 'hidden';
		contentHidden.id   = 'content-hidden';
		contentHidden.name = 'content';
		contentTextarea.parentNode.insertBefore(contentHidden, contentTextarea);
		contentTextarea.removeAttribute('name');
	}

	// ── DOM scaffolding ───────────────────────────────────────────────────────
	const container = document.createElement('div');
	container.className = 'md-editor-container';

	// Reuse the same editor-toolbar class so shared CSS applies
	const toolbar = document.createElement('div');
	toolbar.className = 'md-editor-toolbar editor-toolbar';

	const body = document.createElement('div');
	body.className = 'md-editor-body';

	const cmTextarea = document.createElement('textarea');
	cmTextarea.className = 'md-editor-source';
	cmTextarea.value = contentTextarea.value;

	body.appendChild(cmTextarea);
	container.appendChild(toolbar);
	container.appendChild(body);
	contentTextarea.parentNode.insertBefore(container, contentTextarea.nextSibling);

	// Hide textarea — it's only used as a data bridge
	contentTextarea.style.display = 'none';

	// Start hidden if WYSIWYG is the initial format
	if (initialFormat !== 'markdown') {
		container.style.display = 'none';
	}

	// ── Toolbar definition ────────────────────────────────────────────────────
	const TOOLBAR = [
		{ label: 'H1', title: t('editor_heading_1', 'Heading 1'),     action: 'h1' },
		{ label: 'H2', title: t('editor_heading_2', 'Heading 2'),     action: 'h2' },
		{ label: 'H3', title: t('editor_heading_3', 'Heading 3'),     action: 'h3' },
		{ type: 'sep' },
		{ label: '<strong>B</strong>', title: t('editor_bold',          'Bold'),         action: 'bold' },
		{ label: '<em>I</em>',         title: t('editor_italic',        'Italic'),       action: 'italic' },
		{ label: '<s>S</s>',           title: t('editor_strikethrough', 'Strikethrough'),action: 'strike' },
		{ type: 'sep' },
		{ label: '•',  title: t('editor_bullet_list',   'Bullet list'),   action: 'ul' },
		{ label: '1.', title: t('editor_numbered_list', 'Numbered list'), action: 'ol' },
		{ label: '❝',  title: t('sc_quote',             'Blockquote'),    action: 'blockquote' },
		{ type: 'sep' },
		{ label: '🔗',  title: t('insert_link',         'Insert link'),   action: 'link' },
		{ label: '🖼️', title: t('editor_insert_image', 'Insert image'),  action: 'image' },
		{ label: '🏞', title: t('editor_insert_gallery','Insert gallery'),action: 'gallery' },
		{ label: '💬', title: t('sc_callout',            'Callout block'), action: 'callout' },
		{ type: 'sep' },
		{ label: '<>',  title: t('insert_code_block',   'Code block'),    action: 'code' },
		{ label: '`',   title: t('sc_inline_code',      'Inline code'),   action: 'inlinecode' },
		{ label: '—',  title: t('sc_hr',                'Horizontal rule'),action: 'hr' },
		{ type: 'sep' },
		{
			type: 'dropdown', label: '[/]', title: t('editor_shortcodes', 'Shortcodes'),
			items: [
				{ label: '📋', title: t('sc_toc',             'Table of contents'),  action: 'sc',       value: '[toc]' },
				{ label: '📰', title: t('sc_recent_articles', 'Recent articles'),    action: 'sc-modal', value: 'recent_articles' },
				{ label: '🗂', title: t('sc_recent_projects', 'Recent projects'),    action: 'sc-modal', value: 'recent_projects' },
				{ label: '✉', title: t('sc_contact_form',    'Contact form'),        action: 'sc',       value: '[contact_form]' },
			]
		},
		{ type: 'sep' },
		{ label: '⟲', title: t('editor_undo',            'Undo'),             action: 'undo' },
		{ label: '⟳', title: t('editor_redo',            'Redo'),             action: 'redo' },
		{ label: '⛶', title: t('editor_toggle_fullscreen','Toggle fullscreen'),action: 'fullscreen' },
	];

	// Build toolbar
	TOOLBAR.forEach(def => {
		if (def.type === 'sep') {
			const sep = document.createElement('span');
			sep.className = 'editor-separator';
			toolbar.appendChild(sep);
			return;
		}
		if (def.type === 'dropdown') {
			const wrap = document.createElement('div');
			wrap.className = 'editor-dropdown';
			const btn = document.createElement('button');
			btn.type = 'button'; btn.className = 'editor-btn editor-dropdown-btn';
			btn.title = def.title;
			btn.innerHTML = def.label + '<span class="editor-dropdown-arrow">▾</span>';
			const menu = document.createElement('div');
			menu.className = 'editor-dropdown-menu';
			(def.items || []).forEach(item => {
				const ib = document.createElement('button');
				ib.type = 'button'; ib.className = 'editor-dropdown-item';
				ib.title = item.title;
				ib.dataset.action = item.action;
				ib.dataset.value  = item.value || '';
				ib.innerHTML = item.label || '';
				const lbl = document.createElement('span');
				lbl.className = 'dropdown-item-label';
				lbl.textContent = item.title;
				ib.appendChild(lbl);
				menu.appendChild(ib);
			});
			wrap.appendChild(btn);
			wrap.appendChild(menu);
			toolbar.appendChild(wrap);
			return;
		}
		const btn = document.createElement('button');
		btn.type = 'button'; btn.className = 'editor-btn';
		btn.title = def.title; btn.innerHTML = def.label;
		btn.dataset.action = def.action;
		toolbar.appendChild(btn);
	});

	// ── Toolbar events ────────────────────────────────────────────────────────
	toolbar.addEventListener('mousedown', function (e) {
		e.preventDefault();
		const dropItem = e.target.closest('.editor-dropdown-item');
		if (dropItem) {
			dropItem.closest('.editor-dropdown-menu').classList.remove('open');
			_handleAction(dropItem.dataset.action, dropItem.dataset.value);
			return;
		}
		const dropBtn = e.target.closest('.editor-dropdown-btn');
		if (dropBtn) {
			const menu = dropBtn.closest('.editor-dropdown').querySelector('.editor-dropdown-menu');
			const wasOpen = menu.classList.contains('open');
			toolbar.querySelectorAll('.editor-dropdown-menu.open').forEach(m => m.classList.remove('open'));
			if (!wasOpen) menu.classList.add('open');
			return;
		}
		const btn = e.target.closest('.editor-btn[data-action]');
		if (btn) _handleAction(btn.dataset.action, '');
	});

	document.addEventListener('mousedown', function (e) {
		if (!e.target.closest('.editor-dropdown'))
			toolbar.querySelectorAll('.editor-dropdown-menu.open').forEach(m => m.classList.remove('open'));
	});

	// ── CodeMirror loading ────────────────────────────────────────────────────
	const CM_VER = '5.65.16';
	const CDN    = `https://cdnjs.cloudflare.com/ajax/libs/codemirror/${CM_VER}`;

	function _addCSS(href) {
		if (document.querySelector(`link[href="${href}"]`)) return;
		const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href;
		document.head.appendChild(l);
	}
	function _addJS(src, cb) {
		if (document.querySelector(`script[src="${src}"]`)) { if (cb) setTimeout(cb, 0); return; }
		const s = document.createElement('script'); s.src = src; s.async = false;
		if (cb) s.onload = cb;
		document.head.appendChild(s);
	}

	function _loadCM(callback) {
		if (cmLoaded) { callback(); return; }
		_addCSS(`${CDN}/codemirror.min.css`);
		_addCSS(`${CDN}/theme/ayu-dark.min.css`);
		_addJS(`${CDN}/codemirror.min.js`, () => {
			_addJS(`${CDN}/mode/xml/xml.min.js`, () => {
				_addJS(`${CDN}/mode/markdown/markdown.min.js`, () => {
					_addJS(`${CDN}/addon/edit/continuelist.min.js`, () => {
						cmLoaded = true;
						callback();
					});
				});
			});
		});
	}

	function _buildEditor() {
		if (cmEditor) return; // already built
		cmEditor = window.CodeMirror.fromTextArea(cmTextarea, {
			mode:        { name: 'markdown', fencedCodeBlockDefaultMode: 'javascript' },
			theme:       'ayu-dark',
			lineNumbers: true,
			lineWrapping: true,
			indentUnit:  2,
			tabSize:     2,
			extraKeys: {
				'Enter':  'newlineAndIndentContinueMarkdownList',
				'Ctrl-B': () => _handleAction('bold'),
				'Cmd-B':  () => _handleAction('bold'),
				'Ctrl-I': () => _handleAction('italic'),
				'Cmd-I':  () => _handleAction('italic'),
				'Ctrl-Z': () => cmEditor.undo(),
				'Cmd-Z':  () => cmEditor.undo(),
			},
		});

		// Inject scoped MD editor styles
		const style = document.createElement('style');
		style.textContent = `
			.md-editor-container{display:flex;flex-direction:column;border:1px solid var(--border-light,#e0e0e0);border-radius:6px;overflow:clip;margin-top:4px}
			.md-editor-container.fullscreen{position:fixed;inset:0;z-index:9000;border-radius:0}
			.md-editor-body{flex:1;min-height:420px}
			.md-editor-body .CodeMirror{height:100%;min-height:420px;font-family:'JetBrains Mono','Fira Code',monospace;font-size:13.5px;line-height:1.75;border-radius:0}
			.md-editor-body .CodeMirror-scroll{min-height:420px}
			.md-editor-container.fullscreen .md-editor-body{height:calc(100vh - 48px)}
			.md-editor-container.fullscreen .md-editor-body .CodeMirror{height:100%;min-height:unset}
		`;
		document.head.appendChild(style);

		cmEditor.on('change', syncContent);
		syncContent();
		setTimeout(() => { cmEditor.refresh(); if (container.style.display !== 'none') cmEditor.focus(); }, 50);
	}

	// Eagerly load CM if starting in Markdown mode
	if (initialFormat === 'markdown') {
		_loadCM(_buildEditor);
	}

	// ── Content sync ──────────────────────────────────────────────────────────
	function syncContent() {
		if (window.CONTENT_FORMAT !== 'markdown') return; // only sync when active
		const val = cmEditor ? cmEditor.getValue() : cmTextarea.value;
		contentTextarea.value = val;
		contentHidden.value   = val;
	}

	// ── Action handlers ───────────────────────────────────────────────────────
	function _handleAction(action, value) {
		if (action === 'fullscreen') { _toggleFullscreen(); return; }
		if (action === 'undo')       { if (cmEditor) cmEditor.undo(); return; }
		if (action === 'redo')       { if (cmEditor) cmEditor.redo(); return; }
		if (action === 'image')      { _openGalleryForImage(); return; }
		if (action === 'gallery')    { _insertGalleryShortcode(); return; }
		if (action === 'callout')    { _calloutModal(); return; }
		if (action === 'link')       { _linkModal(); return; }
		if (action === 'sc-modal')   { _scModal(value); return; }
		if (!cmEditor) return;
		cmEditor.focus();
		switch (action) {
			case 'h1':         _wrapLine('# ');           break;
			case 'h2':         _wrapLine('## ');          break;
			case 'h3':         _wrapLine('### ');         break;
			case 'bold':       _wrapSel('**', '**');      break;
			case 'italic':     _wrapSel('*', '*');        break;
			case 'strike':     _wrapSel('~~', '~~');      break;
			case 'ul':         _wrapLine('- ');           break;
			case 'ol':         _wrapLine('1. ');          break;
			case 'blockquote': _wrapLine('> ');           break;
			case 'hr':         _insert('\n\n---\n\n');    break;
			case 'inlinecode': _wrapSel('`', '`');        break;
			case 'code':       _insertCodeBlock();        break;
			case 'sc':         _insert(value || '');      break;
		}
		syncContent();
	}

	function _wrapLine(prefix) {
		const doc = cmEditor.getDoc(), cur = doc.getCursor(), line = doc.getLine(cur.line);
		if (line.startsWith(prefix)) doc.replaceRange(line.slice(prefix.length), {line:cur.line,ch:0},{line:cur.line,ch:line.length});
		else doc.replaceRange(prefix + line, {line:cur.line,ch:0},{line:cur.line,ch:line.length});
	}
	function _wrapSel(before, after) {
		const doc = cmEditor.getDoc(), sel = doc.getSelection();
		if (sel) { doc.replaceSelection(before + sel + after); }
		else { const cur = doc.getCursor(); doc.replaceRange(before + after, cur); doc.setCursor({line:cur.line,ch:cur.ch+before.length}); }
	}
	function _insert(text) { cmEditor.getDoc().replaceRange(text, cmEditor.getDoc().getCursor()); }
	function _insertCodeBlock() { const doc = cmEditor.getDoc(), sel = doc.getSelection() || 'code here'; doc.replaceSelection('\n```\n' + sel + '\n```\n'); }

	// ── Link modal ────────────────────────────────────────────────────────────
	function _linkModal() {
		if (!cmEditor) return;
		const doc = cmEditor.getDoc(), sel = doc.getSelection();
		const uid = 'md-lnk-' + Date.now();
		const overlay = _overlay(`
			<div class="modal-container">
				<div class="modal-header"><span class="modal-title">${t('insert_link','Insert Link')}</span><span class="modal-close">&times;</span></div>
				<div class="modal-content">
					<div class="form-group"><label>${t('link_text','Link Text')}</label><input type="text" id="lt-${uid}" value="${_esc(sel)}"></div>
					<div class="form-group"><label>URL</label><input type="text" id="lu-${uid}" placeholder="https://"></div>
					<div class="form-group"><label class="checkbox-label"><input type="checkbox" id="ln-${uid}"> ${t('open_in_new_tab','Open in new tab')}</label></div>
				</div>
				<div class="modal-footer">
					<button class="modal-button modal-cancel">${t('cancel','Cancel')}</button>
					<button class="modal-button modal-confirm">${t('insert','Insert')}</button>
				</div>
			</div>`);
		overlay.querySelector('.modal-close').addEventListener('click',  () => _close(overlay));
		overlay.querySelector('.modal-cancel').addEventListener('click', () => _close(overlay));
		overlay.querySelector('.modal-confirm').addEventListener('click', () => {
			const text = overlay.querySelector('#lt-'+uid).value || 'link';
			const url  = overlay.querySelector('#lu-'+uid).value  || '#';
			const newTab = overlay.querySelector('#ln-'+uid).checked;
			const md = newTab ? `[${text}](${url}){:target="_blank"}` : `[${text}](${url})`;
			if (cmEditor) { cmEditor.getDoc().replaceSelection(md); syncContent(); }
			_close(overlay);
		});
		setTimeout(() => overlay.querySelector('#lt-'+uid).focus(), 30);
	}

	// ── Image / gallery ───────────────────────────────────────────────────────
	function _openGalleryForImage() {
		window.isEditorImageInsertion = true; window.gallerySelectionMode = 'gallery';
		const modal = document.getElementById('gallery-modal');
		if (!modal) return;
		modal.style.display = 'block';
		const h = modal.querySelector('h2'); if (h) h.textContent = t('editor_insert_image_title','Insert Image');
		const addBtn = document.getElementById('add-selected-gallery-items');
		if (addBtn) addBtn.textContent = t('insert_into_editor','Insert into editor');
		if (typeof window.loadFilesForGallery === 'function') window.loadFilesForGallery('');
	}

	function insertImage(src, alt, autoClose) {
		if (!cmEditor) return;
		let cleanPath = src;
		if (cleanPath.startsWith('../')) cleanPath = cleanPath.substring(3);
		if (!cleanPath.startsWith('files/') && !cleanPath.startsWith('http')) cleanPath = 'files/' + cleanPath.replace(/^\/+/,'');
		let finalUrl = src.startsWith('http') ? src : (() => {
			const base = window.location.protocol + '//' + window.location.host;
			const adminPos = window.location.pathname.indexOf('/admin/');
			return base + (adminPos !== -1 ? window.location.pathname.substring(0, adminPos) : '') + '/' + cleanPath;
		})();
		cmEditor.getDoc().replaceRange(`![${alt}](${finalUrl})`, cmEditor.getDoc().getCursor());
		syncContent();
		if (autoClose) { window.isEditorImageInsertion = false; const gm = document.getElementById('gallery-modal'); if (gm) gm.style.display = 'none'; }
	}

	function _insertGalleryShortcode() {
		const blocks = document.querySelectorAll('.named-gallery-block');
		if (blocks.length === 0) { alert(t('editor_no_gallery_alert','No gallery available.')); return; }
		if (blocks.length === 1) { if (cmEditor) { _insert('[gallery id="'+blocks[0].dataset.galleryIndex+'"]'); syncContent(); } return; }
		// Multi-gallery picker
		document.querySelectorAll('.gallery-shortcode-picker').forEach(p => p.remove());
		const picker = document.createElement('div');
		picker.className = 'gallery-shortcode-picker';
		picker.style.cssText = 'position:fixed;z-index:10001;background:#fff;border:1px solid #ccc;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.2);padding:12px;min-width:220px;';
		const titleEl = document.createElement('div'); titleEl.style.cssText = 'font-weight:600;font-size:13px;margin-bottom:8px;'; titleEl.textContent = t('editor_insert_which_gallery','Insert which gallery?'); picker.appendChild(titleEl);
		blocks.forEach(block => {
			const gid = block.dataset.galleryIndex, label = block.querySelector('.gallery-label-input')?.value || ('Gallery '+gid);
			const btn = document.createElement('button'); btn.type = 'button';
			btn.style.cssText = 'display:block;width:100%;text-align:left;padding:7px 10px;margin-bottom:4px;border:1px solid #e0e0e0;border-radius:4px;background:#3e483e;cursor:pointer;font-size:13px;color:white;';
			btn.innerHTML = `<strong>${label}</strong> <code style="font-size:11px;color:#aaa;">[gallery id="${gid}"]</code>`;
			btn.addEventListener('click', () => { picker.remove(); if (cmEditor) { _insert('[gallery id="'+gid+'"]'); syncContent(); } });
			picker.appendChild(btn);
		});
		const rect = toolbar.getBoundingClientRect();
		picker.style.top = (rect.bottom+6)+'px'; picker.style.left = rect.left+'px';
		document.body.appendChild(picker);
		let skip = true;
		document.addEventListener('mousedown', function _cp(e) { if (skip){skip=false;return;} if (!picker.contains(e.target)){picker.remove();document.removeEventListener('mousedown',_cp);} });
	}

	// ── Callout modal ─────────────────────────────────────────────────────────
	function _calloutModal() {
		const uid = 'md-co-' + Date.now();
		const typeOptions = [
			{ value: 'note',    label: 'ℹ️  Note (info)',    css: 'info'    },
			{ value: 'warning', label: '⚠️  Warning',        css: 'warning' },
			{ value: 'tip',     label: '💡 Tip',             css: 'tip'     },
			{ value: 'danger',  label: '🚫 Danger',          css: 'danger'  },
		];
		const opts = typeOptions.map(o =>
			`<option value="${o.value}">${o.label}</option>`
		).join('');

		const overlay = _overlay(`
			<div class="modal-container">
				<div class="modal-header">
					<span class="modal-title">${t('sc_callout','Callout block')}</span>
					<span class="modal-close">&times;</span>
				</div>
				<div class="modal-content">
					<div class="form-group">
						<label>${t('sc_callout_type','Type')}</label>
						<select id="co-type-${uid}">${opts}</select>
					</div>
					<div class="form-group">
						<label>${t('sc_callout_title_field','Title (optional)')}</label>
						<input type="text" id="co-title-${uid}" placeholder="${t('sc_callout_title_ph','My title...')}">
					</div>
					<div class="form-group">
						<label>${t('sc_content','Content')}</label>
						<textarea id="co-body-${uid}" rows="4" placeholder="${t('sc_callout_ph','Callout text...')}"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button class="modal-button modal-cancel">${t('cancel','Cancel')}</button>
					<button class="modal-button modal-confirm">${t('insert','Insert')}</button>
				</div>
			</div>`);

		overlay.querySelector('.modal-close').addEventListener('click',  () => _close(overlay));
		overlay.querySelector('.modal-cancel').addEventListener('click', () => _close(overlay));
		overlay.querySelector('.modal-confirm').addEventListener('click', () => {
			const type  = overlay.querySelector('#co-type-'  + uid).value.trim();
			const title = overlay.querySelector('#co-title-' + uid).value.trim();
			const body  = overlay.querySelector('#co-body-'  + uid).value;
			const header = title ? type + ' ' + title : type;
			const md = '\n:::' + header + '\n' + (body || t('sc_callout_ph','Callout text...')) + '\n:::' + '\n';
			if (cmEditor) { _insert(md); syncContent(); }
			_close(overlay);
		});
		setTimeout(() => overlay.querySelector('#co-title-' + uid).focus(), 30);
	}

	// ── Shortcode modals ──────────────────────────────────────────────────────
	function _scModal(type) {
		let fields = '', buildSc;
		if (type === 'recent_articles') {
			const uid = 'rca-'+Date.now();
			fields = `<div class="form-group"><label>${t('sc_limit','Number of articles')}</label><input type="number" id="scl-${uid}" value="3" min="1" max="20"></div>
					  <div class="form-group"><label>${t('sc_tag_filter','Tag filter (optional)')}</label><input type="text" id="sct-${uid}" placeholder="tag-slug"></div>
					  <div class="form-group"><label>${t('sc_cat_filter','Category filter (optional)')}</label><input type="text" id="scc-${uid}" placeholder="category-slug"></div>`;
			buildSc = () => { let sc=`[recent_articles limit="${overlay.querySelector('#scl-'+uid).value||3}"`; const tag=overlay.querySelector('#sct-'+uid).value.trim(),cat=overlay.querySelector('#scc-'+uid).value.trim(); if(tag)sc+=` tag="${tag}"`; if(cat)sc+=` category="${cat}"`; return sc+']'; };
		} else if (type === 'recent_projects') {
			const uid = 'rcp-'+Date.now();
			fields = `<div class="form-group"><label>${t('sc_limit','Number of projects')}</label><input type="number" id="scl-${uid}" value="3" min="1" max="20"></div>`;
			buildSc = () => `[recent_projects limit="${overlay.querySelector('#scl-'+uid).value||3}"]`;
		}
		if (!buildSc) return;
		const overlay = _overlay(`
			<div class="modal-container">
				<div class="modal-header"><span class="modal-title">${t('editor_shortcodes','Shortcodes')}</span><span class="modal-close">&times;</span></div>
				<div class="modal-content">${fields}</div>
				<div class="modal-footer"><button class="modal-button modal-cancel">${t('cancel','Cancel')}</button><button class="modal-button modal-confirm">${t('insert','Insert')}</button></div>
			</div>`);
		overlay.querySelector('.modal-close').addEventListener('click',  () => _close(overlay));
		overlay.querySelector('.modal-cancel').addEventListener('click', () => _close(overlay));
		overlay.querySelector('.modal-confirm').addEventListener('click', () => { if (cmEditor) { _insert(buildSc()); syncContent(); } _close(overlay); });
		setTimeout(() => overlay.querySelector('input')?.focus(), 30);
	}

	// ── Fullscreen ────────────────────────────────────────────────────────────
	function _toggleFullscreen() {
		isFullscreen = !isFullscreen; container.classList.toggle('fullscreen', isFullscreen);
		const btn = toolbar.querySelector('[data-action="fullscreen"]');
		if (btn) { btn.title = isFullscreen ? t('editor_exit_fullscreen','Exit Fullscreen') : t('editor_toggle_fullscreen','Toggle Fullscreen'); btn.innerHTML = isFullscreen ? '⨯' : '⛶'; }
		if (cmEditor) setTimeout(() => cmEditor.refresh(), 100);
	}

	// ── Modal helpers ─────────────────────────────────────────────────────────
	function _overlay(html) { const el = document.createElement('div'); el.className = 'modal-overlay'; el.style.display = 'block'; el.innerHTML = html; document.body.appendChild(el); return el; }
	function _close(el)     { if (el && el.parentNode) el.parentNode.removeChild(el); }
	function _esc(str)      { return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

	// ── Show / Hide (called by EditorCommon.switchFormat) ─────────────────────
	function show() {
		container.style.display = '';
		contentTextarea.style.display = 'none';
		// Sync value from textarea (which WYSIWYG may have updated) to CM
		const latestVal = contentHidden.value || contentTextarea.value;
		if (!cmEditor) {
			cmTextarea.value = latestVal;
			_loadCM(() => {
				_buildEditor();
				if (cmEditor) cmEditor.setValue(latestVal);
			});
		} else {
			cmEditor.setValue(latestVal);
			setTimeout(() => { cmEditor.refresh(); cmEditor.focus(); }, 50);
		}
	}

	function hide() {
		syncContent();
		container.style.display = 'none';
	}

	// ── Register with EditorCommon ────────────────────────────────────────────
	window.EditorCommon.registerEditor('markdown', {
		syncContent,
		insertImage,
		insertShortcode: function (sc) { if (!cmEditor) return; _insert(sc); syncContent(); },
		show,
		hide,
	});

	// Form submit: sync only when active
	const form = document.getElementById('content-form');
	if (form) form.addEventListener('submit', function() { if (window.CONTENT_FORMAT === 'markdown') syncContent(); });
});