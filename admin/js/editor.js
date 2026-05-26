/**
 * SynaptikCMS Custom WYSIWYG Editor
 * A clean, modern editor with proper HTML formatting and special features
 */

const t = window.t || ((key, fb) => window.CMS_LANG?.[key] ?? fb ?? key);

// Get autosave preference from server-side settings (passed via window object)
const AUTOSAVE_ENABLED_BY_SETTINGS = window.AUTOSAVE_ENABLED_BY_SETTINGS !== undefined ? window.AUTOSAVE_ENABLED_BY_SETTINGS : true;
 
document.addEventListener('DOMContentLoaded', function() {

	// Both editors always initialize. The inactive one starts hidden.
	// EditorCommon.switchFormat() handles live switching between formats.

	// ── CodeMirror lazy-load (source view) ──────────────────────────────────
	(function injectCodeMirror() {
		const CM_VERSION = '5.65.16';
		const CDN = `https://cdnjs.cloudflare.com/ajax/libs/codemirror/${CM_VERSION}`;

		function addCSS(href) {
			if (document.querySelector(`link[href="${href}"]`)) return;
			const l = document.createElement('link');
			l.rel = 'stylesheet'; l.href = href;
			document.head.appendChild(l);
		}
		function addJS(src) {
			if (document.querySelector(`script[src="${src}"]`)) return;
			const s = document.createElement('script');
			s.src = src; s.async = false;
			document.head.appendChild(s);
		}

		addCSS(`${CDN}/codemirror.min.css`);
		addCSS(`${CDN}/addon/fold/foldgutter.min.css`);
		addCSS(`${CDN}/theme/ayu-dark.min.css`);

		addJS(`${CDN}/codemirror.min.js`);
		addJS(`${CDN}/mode/xml/xml.min.js`);
		addJS(`${CDN}/mode/css/css.min.js`);
		addJS(`${CDN}/mode/javascript/javascript.min.js`);
		addJS(`${CDN}/mode/htmlmixed/htmlmixed.min.js`);
		addJS(`${CDN}/addon/fold/foldcode.min.js`);
		addJS(`${CDN}/addon/fold/foldgutter.min.js`);
		addJS(`${CDN}/addon/fold/xml-fold.min.js`);
		addJS(`${CDN}/addon/edit/closetag.min.js`);
		addJS(`${CDN}/addon/edit/matchbrackets.min.js`);
	})();
	// ────────────────────────────────────────────────────────────────────────

	// Find the content textarea
	const contentTextarea = document.getElementById('content');
	if (!contentTextarea) return; // Exit if no content area exists

	// Create hidden input for formatted content storage
	const contentHidden = document.createElement('input');
	contentHidden.type = 'hidden';
	contentHidden.id = 'content-hidden';
	contentHidden.name = 'content';
	contentTextarea.parentNode.insertBefore(contentHidden, contentTextarea);

	// Initial content sync
	contentHidden.value = contentTextarea.value;

	// Create the editor container
	const editorContainer = document.createElement('div');
	editorContainer.className = 'custom-editor-container';

	// Create editor toolbar
	const toolbar = document.createElement('div');
	toolbar.className = 'editor-toolbar';

	// Define toolbar buttons with icons and commands
	const buttons = [
		{ icon: 'H1', command: 'formatBlock', value: 'h1', title: t('editor_heading_1') },
		{ icon: 'H2', command: 'formatBlock', value: 'h2', title: t('editor_heading_2') },
		{ icon: 'H3', command: 'formatBlock', value: 'h3', title: t('editor_heading_3') },
		{ icon: ' ', command: 'separator', title: '' },
		{ icon: '<strong>B</strong>', command: 'bold', title: t('editor_bold') },
		{ icon: '<em>I</em>', command: 'italic', title: t('editor_italic') },
		{ icon: '<u>U</u>', command: 'underline', title: t('editor_underline') },
		{ icon: '<span style="text-decoration: line-through">S</span>', command: 'strikeThrough', title: t('editor_strikethrough') },
		{ command: 'textColor', title: t('editor_text_color') },
		{ icon: ' ', command: 'separator', title: '' },
		{ icon: '•', command: 'insertUnorderedList', title: t('editor_bullet_list') },
		{ icon: '1.', command: 'insertOrderedList', title: t('editor_numbered_list') },
		{ icon: '⇥', command: 'indent', title: t('editor_indent') },
		{ icon: '⇤', command: 'outdent', title: t('editor_outdent') },
		{ icon: ' ', command: 'separator', title: '' },
		{ icon: '&#8592;', command: 'justifyLeft', title: t('editor_align_left') },
		{ icon: '&#8596;', command: 'justifyCenter', title: t('editor_align_center') },
		{ icon: '&#8594;', command: 'justifyRight', title: t('editor_align_right') },
		{ icon: '≡', command: 'justifyFull', title: t('editor_justify') },
		{ icon: ' ', command: 'separator', title: '' },
		{
			command: 'dropdown', icon: '⏐', title: t('editor_columns'),
			items: [
				{ icon: '2⏐', command: 'insertTwoColumns', title: t('editor_2_columns') },
				{ icon: '3⏐', command: 'insertThreeColumns', title: t('editor_3_columns') },
				{ icon: '4⏐', command: 'insertFourColumns', title: t('editor_4_columns') },
			]
		},
		{
			command: 'dropdown', icon: '▾', title: t('editor_collapsible_sections'),
			items: [
				{ icon: '▾₁', command: 'insertCollapsible', value: '1', title: t('editor_level_1') },
				{ icon: '▾₂', command: 'insertCollapsible', value: '2', title: t('editor_level_2') },
				{ icon: '▾₃', command: 'insertCollapsible', value: '3', title: t('editor_level_3') },
				{ icon: '▾₄', command: 'insertCollapsible', value: '4', title: t('editor_level_4') },
				{ icon: '📑', command: 'insertTabGroup', title: t('editor_insert_tabs') },
			]
		},
		{ icon: ' ', command: 'separator', title: '' },
		{ icon: '🔗', command: 'createLink', title: t('insert_link') },
		{ icon: '🖼️', command: 'insertImage', title: t('editor_insert_image') },
		{ icon: '🏞', command: 'insertGalleryShortcode', title: t('editor_insert_gallery') },
		{
			command: 'dropdown', icon: '[/]', title: t('editor_shortcodes', 'Shortcodes'),
			items: [
				{ icon: '&#x1F4CB;', command: 'scInsert', value: '[toc]',                                              title: t('sc_toc',              'Table of contents') },
				{ icon: '&#x2139;',  command: 'scCallout', value: 'info',                                             title: t('sc_callout',          'Callout block') },
				{ icon: '&#x275D;',  command: 'scQuote',   value: '',                                                 title: t('sc_quote',            'Quote') },
				{ icon: '&#x1F517;', command: 'scButton',  value: '',                                                 title: t('sc_button',           'Button / CTA') },
				{ icon: '&#x1F4F0;', command: 'scRecentArticles', value: '',                                          title: t('sc_recent_articles',  'Recent articles') },
				{ icon: '&#x1F5C2;', command: 'scRecentProjects', value: '',                                          title: t('sc_recent_projects',  'Recent projects') },
				{ icon: '&#x1F3F7;', command: 'scArticlesByTag',  value: '',                                         title: t('sc_articles_by_tag',  'Articles by tag') },
				{ icon: '&#x2709;',  command: 'scInsert', value: '[contact_form]',                                    title: t('sc_contact_form',     'Contact form') },
			]
		},
		{ icon: '⊞', command: 'insertTable', title: t('editor_insert_table') },
		{ icon: '<>', command: 'insertCode', title: t('insert_code_block') },
		{ icon: '↵', command: 'insertLineBreak', title: t('editor_insert_line_break') },
		{ icon: ' ', command: 'separator', title: '' },
		{ icon: '⟲', command: 'undo', title: t('editor_undo') },
		{ icon: '⟳', command: 'redo', title: t('editor_redo') },
		{ icon: '&lt;/&gt;', command: 'sourceView', title: t('editor_source_code') },
		{ icon: '⛶', command: 'toggleFullscreen', title: t('editor_toggle_fullscreen') }
	];

	// Create toolbar buttons
	buttons.forEach(button => {
		if (button.command === 'separator') {
			const sep = document.createElement('span');
			sep.className = 'editor-separator';
			sep.innerHTML = button.icon || '';
			toolbar.appendChild(sep);
			return;
		}
	
		// — Dropdown —
		if (button.command === 'dropdown') {
			const wrap = document.createElement('div');
			wrap.className = 'editor-dropdown';
	
			const dropBtn = document.createElement('button');
			dropBtn.type = 'button';
			dropBtn.className = 'editor-btn editor-dropdown-btn';
			dropBtn.title = button.title;
			dropBtn.innerHTML = button.icon + '<span class="editor-dropdown-arrow">▾</span>';
			wrap.appendChild(dropBtn);
	
			const menu = document.createElement('div');
			menu.className = 'editor-dropdown-menu';
	
			(button.items || []).forEach(item => {
				const itemBtn = document.createElement('button');
				itemBtn.type = 'button';
				itemBtn.className = 'editor-dropdown-item';
				itemBtn.title = item.title;
				itemBtn.innerHTML = item.icon || '';
				itemBtn.dataset.command = item.command;
				if (item.value) itemBtn.dataset.value = item.value;
				const label = document.createElement('span');
				label.className = 'dropdown-item-label';
				label.textContent = item.title;
				itemBtn.appendChild(label);
				menu.appendChild(itemBtn);
			});
	
			wrap.appendChild(menu);
			toolbar.appendChild(wrap);
			return;
		}
	
		// — Color Picker —
		if (button.command === 'textColor') {
			const wrap = document.createElement('div');
			wrap.className = 'editor-color-wrapper';
	
			const colorBtn = document.createElement('button');
			colorBtn.type = 'button';
			colorBtn.className = 'editor-btn editor-color-btn';
			colorBtn.title = button.title;
			colorBtn.innerHTML = '<span class="color-letter">A</span><span class="color-swatch" id="editor-color-swatch"></span>';
			wrap.appendChild(colorBtn);
	
			const colorInput = document.createElement('input');
			colorInput.type = 'color';
			colorInput.id = 'editor-color-input';
			colorInput.value = '#e74c3c';
			colorInput.className = 'editor-color-input';
			wrap.appendChild(colorInput);
	
			toolbar.appendChild(wrap);
	
			// Update swatch on change and apply color
			colorInput.addEventListener('change', function() {
				const color = this.value;
				const swatch = document.getElementById('editor-color-swatch');
				if (swatch) swatch.style.background = color;
			
				editorContent.focus();
				restoreSelection();
			
				const sel = window.getSelection();
				if (sel && sel.rangeCount > 0 && !sel.isCollapsed) {
					const range = sel.getRangeAt(0);
					try {
						// Wrap la sélection dans un <span style="color:"> au lieu de <font>
						const span = document.createElement('span');
						span.style.color = color;
						const fragment = range.extractContents();
						span.appendChild(fragment);
						range.insertNode(span);
						range.setStartAfter(span);
						range.collapse(true);
						sel.removeAllRanges();
						sel.addRange(range);
					} catch(err) {
						// Fallback si la sélection chevauche des éléments (ex: sélection partielle sur un <strong>)
						console.warn('Color wrap failed, using insertHTML fallback:', err);
						document.execCommand('insertHTML', false,
							`<span style="color:${color}">${sel.toString()}</span>`);
					}
				}
				updateContent();
			});
	
			return;
		}
	
		// — Regular button —
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.innerHTML = button.icon;
		btn.title = button.title;
		btn.dataset.command = button.command;
		if (button.value) btn.dataset.value = button.value;
		btn.className = 'editor-btn';
		toolbar.appendChild(btn);
	});

	// Create editor content area
	const editorContent = document.createElement('div');
	editorContent.className = 'editor-content';
	editorContent.contentEditable = true;
	editorContent.innerHTML = contentTextarea.value || '<p><br></p>';

	// Create source view wrapper + textarea (CodeMirror will replace the textarea visually)
	const sourceViewWrapper = document.createElement('div');
	sourceViewWrapper.className = 'editor-source-wrapper';
	sourceViewWrapper.style.display = 'none';

	const sourceView = document.createElement('textarea');
	sourceView.className = 'editor-source-view';
	sourceViewWrapper.appendChild(sourceView);

	// Assemble editor
	editorContainer.appendChild(toolbar);
	editorContainer.appendChild(editorContent);
	editorContainer.appendChild(sourceViewWrapper);

	// Insert editor after the original textarea
	contentTextarea.parentNode.insertBefore(editorContainer, contentTextarea.nextSibling);

	// Hide the original textarea
	contentTextarea.style.display = 'none';

	// If Markdown is the active format, start WYSIWYG hidden
	if (window.CONTENT_FORMAT === 'markdown') {
		editorContainer.style.display = 'none';
	}

	// Global flags and state
	let isInSourceView = false;
	let isInFullscreen = false;
	let isImageInserting = false;
	let cmEditor = null; // CodeMirror instance (initialised lazily on first source-view open)
	window.isEditorImageInsertion = false; // Global flag for gallery modal
	window.savedEditorRange = null; // For storing selection when modal opens

	/**
	 * Button click handler for toolbar
	 */
	toolbar.addEventListener('mousedown', function(e) {
		e.preventDefault(); // toujours prévenir la perte de focus
	
		// — Clic sur item d'un dropdown —
		const dropItem = e.target.closest('.editor-dropdown-item');
		if (dropItem) {
			// Fermer le menu
			dropItem.closest('.editor-dropdown-menu').classList.remove('open');
			if (!isInSourceView) {
				executeCommand(dropItem.dataset.command, dropItem.dataset.value || null);
			}
			return;
		}
	
		// — Clic sur le bouton d'un dropdown (toggle menu) —
		const dropBtn = e.target.closest('.editor-dropdown-btn');
		if (dropBtn) {
			const thisMenu = dropBtn.closest('.editor-dropdown').querySelector('.editor-dropdown-menu');
			const wasOpen = thisMenu.classList.contains('open');
			// Fermer tous les dropdowns ouverts
			toolbar.querySelectorAll('.editor-dropdown-menu.open').forEach(m => m.classList.remove('open'));
			if (!wasOpen) thisMenu.classList.add('open');
			return;
		}
	
		// — Clic sur le bouton color picker —
		if (e.target.closest('.editor-color-btn')) {
			saveSelection();
			document.getElementById('editor-color-input').click();
			return;
		}
	
		// — Bouton normal —
		if (e.target.classList.contains('editor-btn')) {
			const command = e.target.dataset.command;
			const value = e.target.dataset.value || null;
	
			if (command === 'sourceView') {
				toggleSourceView();
				return;
			}
	
			if (isInSourceView) return;
	
			executeCommand(command, value);
		}
	});
	
	// Fermer les dropdowns si clic en dehors de la toolbar
	document.addEventListener('mousedown', function(e) {
		if (!e.target.closest('.editor-dropdown')) {
			toolbar.querySelectorAll('.editor-dropdown-menu.open').forEach(m => m.classList.remove('open'));
		}
	});

	/**
	 * Execute editor command
	 */
	function executeCommand(command, value) {
	// Sauvegarder la sélection AVANT focus() — certains navigateurs la collapsent au focus
	saveSelection();
	editorContent.focus();
	
	// Pour les commandes non-modales, restaurer la sélection immédiatement après focus.
	// Les commandes modales (link, image, table, code, collapsible, tabs) appellent
	// saveSelection() elles-mêmes à l'ouverture de leur modal.
	const modalCommands = ['createLink','insertImage','insertTable','insertCode','insertCollapsible','insertTabGroup','insertGalleryShortcode','scCallout','scQuote','scButton','scRecentArticles','scRecentProjects','scArticlesByTag'];
	if (!modalCommands.includes(command)) {
		restoreSelection();
	}
		try {
			switch (command) {
				case 'formatBlock':
					document.execCommand('formatBlock', false, value);
					break;
				case 'createLink':
					createLinkModal();
					break;
				case 'insertImage':
					insertImageModal();
					break;
				case 'insertGalleryShortcode':
					insertGalleryShortcodeModal();
					break;
				case 'scInsert':
					window.insertShortcodeAtCursor(value);
					break;
				case 'scCallout':
					scCalloutModal(value);
					break;
				case 'scQuote':
					scQuoteModal();
					break;
				case 'scButton':
					scButtonModal();
					break;
				case 'scRecentArticles':
					scRecentArticlesModal();
					break;
				case 'scRecentProjects':
					scRecentProjectsModal();
					break;
				case 'scArticlesByTag':
					scArticlesByTagModal();
					break;
				case 'insertTable':
					insertTableModal();
					break;
				case 'insertCode':
					insertCodeBlockModal();
					break;
				case 'insertLineBreak':
					insertLineBreak();
					break;
				case 'insertTwoColumns':
					insertColumns(2);
					break;
				case 'insertThreeColumns':
					insertColumns(3);
					break;
				case 'insertFourColumns':
					insertColumns(4);
					break;
				case 'insertTabGroup':
					insertTabGroupModal();
					break;
				case 'insertCollapsible':
					insertCollapsibleModal(value);
					break;
				case 'toggleFullscreen':
					toggleFullscreen();
					break;
				case 'bold':
				case 'italic':
				case 'underline':
				case 'strikeThrough':
					// Always use execCommand for consistent HTML output
					document.execCommand(command, false, null);
					break;
				default:
					document.execCommand(command, false, value);
			}

			// Log the result for debugging
			if (['bold', 'italic', 'underline', 'strikeThrough'].includes(command)) {
				console.log(`Command result: ${document.queryCommandState(command)}`);
			}
		} catch (error) {
			console.error(`Error executing command ${command}:`, error);
		}

		// Cleanup and update content after command execution
		setTimeout(() => {
			cleanupContent();
			updateContent();
		}, 10);
	}

	/**
	 * Save current selection/range
	 */
	function saveSelection() {
		if (window.getSelection().rangeCount > 0) {
			window.savedEditorRange = window.getSelection().getRangeAt(0).cloneRange();
		}
	}

	/**
	 * Restore saved selection/range
	 */
	function restoreSelection() {
		if (window.savedEditorRange) {
			const selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(window.savedEditorRange);
			window.savedEditorRange = null;
		}
	}

	/**
	 * Toggle between visual and source view
	 */
	function toggleSourceView() {
		if (!isInSourceView) {
			// ── Switch TO source view ────────────────────────────────────────
			const formatted = formatHTML(editorContent.innerHTML);
			editorContent.style.display = 'none';
			sourceViewWrapper.style.display = 'block';
			document.querySelector('[data-command="sourceView"]').classList.add('active-view');
			isInSourceView = true;

			if (window.CodeMirror) {
				if (!cmEditor) {
					// First open: initialise CodeMirror on the backing textarea
					cmEditor = CodeMirror.fromTextArea(sourceView, {
						mode            : 'htmlmixed',
						theme           : 'ayu-dark',
						lineNumbers     : true,
						lineWrapping    : true,
						foldGutter      : true,
						gutters         : ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
						foldOptions     : { rangeFinder: CodeMirror.fold.xml },
						autoCloseTags   : true,
						matchBrackets   : true,
						indentUnit      : 2,
						tabSize         : 2,
						indentWithTabs  : false,
						extraKeys       : {
							'Ctrl-Q' : cm => cm.foldCode(cm.getCursor()),
							'Cmd-Q'  : cm => cm.foldCode(cm.getCursor())
						}
					});

					// Inject Nova-like overrides on top of ayu-dark
					const cmStyle = document.createElement('style');
					cmStyle.textContent = `
						.editor-source-wrapper .CodeMirror {
							height: 100%;
							min-height: 400px;
							font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', 'SF Mono', 'Menlo', monospace;
							font-size: 13px;
							line-height: 1.7;
							border-radius: 0 0 6px 6px;
						}
						.editor-source-wrapper .CodeMirror-scroll { min-height: 400px; }
						.editor-source-wrapper .CodeMirror-gutters {
							border-right: 1px solid rgba(255,255,255,.07);
							background: #0d1117;
						}
						.editor-source-wrapper .CodeMirror-linenumber {
							color: #4b5263;
							padding: 0 10px 0 8px;
							min-width: 36px;
						}
						.editor-source-wrapper .CodeMirror-foldgutter-open,
						.editor-source-wrapper .CodeMirror-foldgutter-folded {
							color: #5b8dd9;
							cursor: pointer;
						}
						.editor-source-wrapper .CodeMirror-foldmarker {
							background: #1e2d42;
							border: 1px solid #2d4a6e;
							border-radius: 3px;
							color: #5b8dd9;
							font-size: 10px;
							padding: 0 4px;
							margin: 0 4px;
							cursor: pointer;
						}
						.editor-source-wrapper .CodeMirror-selected { background: #1e3a5f; }
						.editor-source-wrapper .CodeMirror-cursor { border-left: 2px solid #e8c56d; }
					`;
					document.head.appendChild(cmStyle);

					cmEditor.on('change', () => updateContent());
				}
				cmEditor.setValue(formatted);
				setTimeout(() => { cmEditor.refresh(); cmEditor.focus(); }, 20);
			} else {
				// Fallback: CodeMirror not yet loaded, use raw textarea
				sourceView.value = formatted;
				sourceView.style.display = 'block';
			}

		} else {
			// ── Switch back to visual editor ────────────────────────────────
			const src = cmEditor ? cmEditor.getValue() : sourceView.value;
			editorContent.innerHTML = src;
			sourceViewWrapper.style.display = 'none';
			editorContent.style.display = 'block';
			document.querySelector('[data-command="sourceView"]').classList.remove('active-view');
			isInSourceView = false;
		}

		updateContent();
	}

	/**
	 * Toggle fullscreen mode
	 */
	function toggleFullscreen() {
		isInFullscreen = !isInFullscreen;
		editorContainer.classList.toggle('fullscreen');

		// Update button appearance
		const fullscreenBtn = document.querySelector('[data-command="toggleFullscreen"]');
		if (fullscreenBtn) {
			if (isInFullscreen) {
				fullscreenBtn.title = t('editor_exit_fullscreen');
				fullscreenBtn.innerHTML = '⨯';
				editorContainer.style.zIndex = "9000";
			} else {
				fullscreenBtn.title = t('editor_toggle_fullscreen');
				fullscreenBtn.innerHTML = '⛶';
				editorContainer.style.zIndex = "";
			}
		}

		// Ensure modals remain above editor
		const modals = document.querySelectorAll('.modal-overlay, .menu-modal, .modal');
		modals.forEach(modal => {
			modal.style.zIndex = "10000";
		});

		// Refocus editor
		if (!isInSourceView) {
			editorContent.focus();
		} else {
			if (cmEditor) cmEditor.focus();
			else sourceView.focus(); // fallback si CodeMirror pas encore chargé
		}
	}

	/**
	 * Format HTML à la Nova.app :
	 *  - éléments block contenant UNIQUEMENT du texte/inline → une seule ligne
	 *  - éléments block contenant des enfants block → indentation
	 *  - éléments inline toujours sur la même ligne que leur parent
	 */
	function formatHTML(html) {
		// 1. Protéger les blocs <pre> intacts
		const codeBlocks = [];
		let codeIndex = 0;
		html = html.replace(/<pre\b[^>]*>[\s\S]*?<\/pre>/gi, (match) => {
			const marker = `___CODE_BLOCK_${codeIndex}___`;
			codeBlocks.push({ marker, content: match });
			codeIndex++;
			return marker;
		});

		const BLOCK_TAGS = new Set([
			'p','div','h1','h2','h3','h4','h5','h6',
			'ul','ol','li','blockquote',
			'table','thead','tbody','tfoot','tr','th','td',
			'section','article','header','footer','main','nav',
			'figure','figcaption','details','summary'
		]);
		const SELF_CLOSING = new Set([
			'br','hr','img','input','meta','link',
			'area','base','col','embed','param','source','track','wbr'
		]);

		function isBlock(node) {
			return node.nodeType === Node.ELEMENT_NODE &&
				BLOCK_TAGS.has(node.tagName.toLowerCase());
		}

		/** Returns true if any direct or deep child is a block element */
		function hasBlockDescendant(node) {
			for (const child of node.childNodes) {
				if (isBlock(child)) return true;
				if (child.nodeType === Node.ELEMENT_NODE && hasBlockDescendant(child)) return true;
			}
			return false;
		}

		function serializeAttrs(el) {
			return Array.from(el.attributes).map(a => {
				if (a.value === '') return ` ${a.name}`;
				return ` ${a.name}="${a.value.replace(/"/g, '&quot;')}"`;
			}).join('');
		}

		/**
		 * Serialize a single node.
		 * `indent` is the current indentation string of the PARENT line.
		 */
		function serializeNode(node, indent) {
			// ── Text node ──
			if (node.nodeType === Node.TEXT_NODE) {
				return node.textContent.replace(/[ \t]*[\r\n]+[ \t]*/g, ' ');
			}
			if (node.nodeType !== Node.ELEMENT_NODE) return '';

			const tag   = node.tagName.toLowerCase();
			const attrs = serializeAttrs(node);

			// ── Self-closing ──
			if (SELF_CLOSING.has(tag)) return `<${tag}${attrs}>`;

			// ── Block with block children → multi-line + indent ──
			if (isBlock(node) && hasBlockDescendant(node)) {
				const ci = indent + '  ';
				const children = [];
				for (const child of node.childNodes) {
					if (child.nodeType === Node.TEXT_NODE) {
						const t = child.textContent.replace(/[ \t]*[\r\n]+[ \t]*/g, ' ').trim();
						if (t) children.push(ci + t);
					} else if (isBlock(child)) {
						children.push(ci + serializeNode(child, ci));
					} else {
						// inline child of a multi-line block: keep inline
						const s = serializeNode(child, ci).trim();
						if (s) children.push(ci + s);
					}
				}
				return `<${tag}${attrs}>\n${children.join('\n')}\n${indent}</${tag}>`;
			}

			// ── Block with only inline/text children → single line ──
			if (isBlock(node)) {
				const inner = Array.from(node.childNodes)
					.map(child => serializeNode(child, ''))
					.join('')
					.replace(/[ \t]*[\r\n]+[ \t]*/g, ' ')
					.trim();
				return `<${tag}${attrs}>${inner}</${tag}>`;
			}

			// ── Inline element → stays on the same line ──
			const inner = Array.from(node.childNodes)
				.map(child => serializeNode(child, ''))
				.join('');
			return `<${tag}${attrs}>${inner}</${tag}>`;
		}

		// Parse the HTML fragment
		const parser = new DOMParser();
		const doc  = parser.parseFromString(`<div id="__fmt__">${html}</div>`, 'text/html');
		const root = doc.getElementById('__fmt__');
		if (!root) return html;

		// Serialize top-level children
		const lines = [];
		for (const child of root.childNodes) {
			if (child.nodeType === Node.TEXT_NODE) {
				const t = child.textContent.replace(/[ \t]*[\r\n]+[ \t]*/g, ' ').trim();
				if (t) lines.push(t);
			} else {
				lines.push(serializeNode(child, ''));
			}
		}

		html = lines.join('\n');

		// 5. Restaurer les blocs de code intacts
		codeBlocks.forEach(({ marker, content }) => {
			html = html.replace(marker, content);
		});

		return html;
	}

	/**
	 * Helper function to clean empty text nodes
	 */
	function cleanEmptyTextNodes(node) {
		const childNodes = Array.from(node.childNodes);

		for (let i = 0; i < childNodes.length; i++) {
			const child = childNodes[i];

			// Remove text nodes with only whitespace
			if (child.nodeType === Node.TEXT_NODE && !child.textContent.trim()) {
				try {
					if (node.contains(child)) {
						node.removeChild(child);
					}
				} catch (e) {
					console.error('Could not remove text node:', e);
				}
			}

			// Clean child elements recursively
			if (child.nodeType === Node.ELEMENT_NODE) {
				cleanEmptyTextNodes(child);
			}
		}
	}

	/**
	 * Create link insertion modal
	 */
	function createLinkModal() {
		// Save selection before modal
		saveSelection();

		// Get selected text
		const selection = window.getSelection();
		const selectedText = selection.toString();

		// Instead of using showModal, we'll create a custom modal ourselves
		const modalId = 'editor-link-modal-' + Date.now();

		// Create modal container
		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';

		// Create modal content
		const modalHtml = `
	  <div class="modal-container">
		<div class="modal-header">
		  <span class="modal-title">${t('insert_link')}</span>
		  <span class="modal-close">&times;</span>
		</div>
		<div class="modal-content">
		  <div class="form-group">
			<label for="link-text-${modalId}">${t('link_text')}</label>
			<input type="text" id="link-text-${modalId}" value="${selectedText}" placeholder="${t('editor_text_to_display')}">
		  </div>
		  <div class="form-group">
			<label for="link-url-${modalId}">${t('link_url')}</label>
			<input type="text" id="link-url-${modalId}" placeholder="https://example.com">
		  </div>
		  <div class="form-group">
			<label class="checkbox-label">
			  <input type="checkbox" id="link-new-tab-${modalId}">
			  ${t('open_in_new_tab')}
			</label>
		  </div>
		</div>
		<div class="modal-footer">
		  <button id="cancel-link-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
		  <button id="insert-link-${modalId}" class="modal-button modal-confirm">${t('insert')}</button>
		</div>
	  </div>
	`;

		modalOverlay.innerHTML = modalHtml;
		document.body.appendChild(modalOverlay);

		// Add event listeners to close the modal
		const closeBtn = modalOverlay.querySelector('.modal-close');
		const cancelBtn = modalOverlay.querySelector(`#cancel-link-${modalId}`);
		const insertBtn = modalOverlay.querySelector(`#insert-link-${modalId}`);

		closeBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		cancelBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		// Handle link insertion
		insertBtn.addEventListener('click', () => {
			const linkText = modalOverlay.querySelector(`#link-text-${modalId}`).value || '';
			const linkUrl = modalOverlay.querySelector(`#link-url-${modalId}`).value || '';
			const newTab = modalOverlay.querySelector(`#link-new-tab-${modalId}`).checked || false;

			if (!linkUrl) {
				document.body.removeChild(modalOverlay);
				return;
			}

			// Restore selection and focus editor
			editorContent.focus();
			restoreSelection();

			// Insert link
			if (selection.rangeCount > 0 && !selection.isCollapsed) {
				// Selected text exists - create link with selection
				document.execCommand('createLink', false, linkUrl);

				// Get the newly created link
				const linkNode = findLinkInSelection();

				if (linkNode && newTab) {
					linkNode.target = '_blank';
					linkNode.rel = 'noopener';
				}
			} else {
				// No selection - create new link
				insertHtmlAtCursor(`<a href="${linkUrl}" ${newTab ? 'target="_blank" rel="noopener"' : ''}>${linkText || linkUrl}</a>`);
			}

			// Update content
			updateContent();

			// Close the modal
			document.body.removeChild(modalOverlay);
		});

		// Focus the text field
		setTimeout(() => {
			modalOverlay.querySelector(`#link-text-${modalId}`).focus();
		}, 10);
	}

	/**
	 * Helper to find link in current selection
	 */
	function findLinkInSelection() {
		const selection = window.getSelection();
		if (selection.rangeCount === 0) return null;

		const range = selection.getRangeAt(0);
		let node = range.commonAncestorContainer;

		// Check if node itself is a link
		if (node.nodeType === 1 && node.tagName === 'A') {
			return node;
		}

		// Check if parent is a link
		if (node.parentNode && node.parentNode.tagName === 'A') {
			return node.parentNode;
		}

		// Look for link in ancestor chain
		while (node) {
			if (node.tagName === 'A') return node;
			node = node.parentNode;
		}

		return null;
	}

	/**
	 * Create table insertion modal
	 */
	function insertTableModal() {
		// Save selection before modal
		saveSelection();

		// Create custom modal
		const modalId = 'editor-table-modal-' + Date.now();

		// Create modal container
		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';

		// Create modal content
		const modalHtml = `
	  <div class="modal-container">
		<div class="modal-header">
		  <span class="modal-title">${t('editor_insert_table')}</span>
		  <span class="modal-close">&times;</span>
		</div>
		<div class="modal-content">
		  <div class="form-group">
			<label for="table-rows-${modalId}">${t('editor_table_rows')}</label>
			<input type="number" id="table-rows-${modalId}" value="3" min="1" max="20">
		  </div>
		  <div class="form-group">
			<label for="table-cols-${modalId}">${t('editor_table_cols')}</label>
			<input type="number" id="table-cols-${modalId}" value="3" min="1" max="10">
		  </div>
		  <div class="form-group">
			<label class="checkbox-label">
			  <input type="checkbox" id="table-header-${modalId}" checked>
			  ${t('editor_table_header')}
			</label>
		  </div>
		  <div class="form-group">
			<label for="table-style-${modalId}">${t('editor_table_style')}</label>
			<select id="table-style-${modalId}">
			  <option value="default">${t('editor_table_style_default')}</option>
			  <option value="striped">${t('editor_table_style_striped')}</option>
			  <option value="bordered">${t('editor_table_style_bordered')}</option>
			  <option value="minimal">${t('editor_table_style_minimal')}</option>
			</select>
		  </div>
		</div>
		<div class="modal-footer">
		  <button id="cancel-table-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
		  <button id="insert-table-${modalId}" class="modal-button modal-confirm">${t('insert')}</button>
		</div>
	  </div>
	`;

		modalOverlay.innerHTML = modalHtml;
		document.body.appendChild(modalOverlay);

		// Add event listeners to close the modal
		const closeBtn = modalOverlay.querySelector('.modal-close');
		const cancelBtn = modalOverlay.querySelector(`#cancel-table-${modalId}`);
		const insertBtn = modalOverlay.querySelector(`#insert-table-${modalId}`);

		closeBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		cancelBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		// Handle table insertion
		insertBtn.addEventListener('click', () => {
			const rows = parseInt(modalOverlay.querySelector(`#table-rows-${modalId}`).value) || 3;
			const cols = parseInt(modalOverlay.querySelector(`#table-cols-${modalId}`).value) || 3;
			const includeHeader = modalOverlay.querySelector(`#table-header-${modalId}`).checked || false;
			const style = modalOverlay.querySelector(`#table-style-${modalId}`).value || 'default';

			// Generate table HTML
			let tableHTML = '<table';

			// Add CSS class based on style
			tableHTML += ` class="table-${style}"`;
			tableHTML += '>\n';

			// Add header row if requested
			if (includeHeader) {
				tableHTML += '  <thead>\n    <tr>\n';
				for (let i = 0; i < cols; i++) {
					tableHTML += `      <th>Header ${i+1}</th>\n`;
				}
				tableHTML += '    </tr>\n  </thead>\n';
			}

			// Add table body
			tableHTML += '  <tbody>\n';

			for (let i = 0; i < rows; i++) {
				tableHTML += '    <tr>\n';
				for (let j = 0; j < cols; j++) {
					tableHTML += `      <td>Cell ${i+1}-${j+1}</td>\n`;
				}
				tableHTML += '    </tr>\n';
			}

			tableHTML += '  </tbody>\n</table>\n<p><br></p>';

			// Insert table at cursor
			editorContent.focus();
			restoreSelection();
			insertHtmlAtCursor(tableHTML);

			// Update content
			updateContent();

			// Close the modal
			document.body.removeChild(modalOverlay);
		});
	}

	/**
	 * Create code block insertion modal
	 */
	function insertCodeBlockModal() {
		// Save selection before modal
		saveSelection();

		// Create custom modal
		const modalId = 'editor-code-modal-' + Date.now();

		// Create modal container
		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';

		// Create modal content
		const modalHtml = `
	  <div class="modal-container">
		<div class="modal-header">
		  <span class="modal-title">${t('insert_code_block')}</span>
		  <span class="modal-close">&times;</span>
		</div>
		<div class="modal-content">
		  <div class="form-group">
			<label for="code-language-${modalId}">${t('code_language')}</label>
			<select id="code-language-${modalId}">
			  <option value="">${t('select_code_language')}</option>
			  <option value="html">HTML</option>
			  <option value="css">CSS</option>
			  <option value="javascript">JavaScript</option>
			  <option value="php">PHP</option>
			  <option value="python">Python</option>
			  <option value="sql">SQL</option>
			  <option value="bash">Bash/Shell</option>
			  <option value="json">JSON</option>
			  <option value="xml">XML</option>
			</select>
		  </div>
		  <div class="form-group">
			<label for="code-content-${modalId}">${t('code_content')}</label>
			<textarea id="code-content-${modalId}" rows="8" placeholder="${t('editor_code_placeholder')}"></textarea>
		  </div>
		</div>
		<div class="modal-footer">
		  <button id="cancel-code-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
		  <button id="insert-code-${modalId}" class="modal-button modal-confirm">${t('insert')}</button>
		</div>
	  </div>
	`;

		modalOverlay.innerHTML = modalHtml;
		document.body.appendChild(modalOverlay);

		// Add event listeners to close the modal
		const closeBtn = modalOverlay.querySelector('.modal-close');
		const cancelBtn = modalOverlay.querySelector(`#cancel-code-${modalId}`);
		const insertBtn = modalOverlay.querySelector(`#insert-code-${modalId}`);

		closeBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		cancelBtn.addEventListener('click', () => {
			document.body.removeChild(modalOverlay);
		});

		// Handle code block insertion
		insertBtn.addEventListener('click', () => {
			const language = modalOverlay.querySelector(`#code-language-${modalId}`).value || '';
			const code = modalOverlay.querySelector(`#code-content-${modalId}`).value || '';

			if (!code) {
				document.body.removeChild(modalOverlay);
				return;
			}

			// Escape HTML entities
			const escapedCode = code
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');

			// Generate code block HTML with class on <code> tag
			let codeHTML = '<pre><code';
			if (language) {
				codeHTML += ` class="language-${language}"`;
			}
			codeHTML += '>';
			codeHTML += escapedCode;
			codeHTML += '</code></pre><p><br></p>';

			// NEW APPROACH: Create actual DOM elements instead of inserting HTML string
			editorContent.focus();
			restoreSelection();

			// Create the elements
			const pre = document.createElement('pre');
			const codeElem = document.createElement('code');

			if (language) {
				codeElem.className = `language-${language}`;
			}

			// Set text content (not innerHTML) to preserve escaping
			codeElem.textContent = code;

			pre.appendChild(codeElem);

			// Insert using DOM methods
			const selection = window.getSelection();
			if (selection.rangeCount > 0) {
				const range = selection.getRangeAt(0);
				range.deleteContents();
				range.insertNode(pre);

				// Add paragraph after
				const p = document.createElement('p');
				p.innerHTML = '<br>';
				pre.parentNode.insertBefore(p, pre.nextSibling);

				// Move cursor to paragraph
				range.setStartAfter(p);
				range.collapse(true);
				selection.removeAllRanges();
				selection.addRange(range);
			}

			// Update content
			updateContent();

			// Close the modal
			document.body.removeChild(modalOverlay);
		});

		// Focus the code textarea
		setTimeout(() => {
			modalOverlay.querySelector(`#code-content-${modalId}`).focus();
		}, 10);
	}
	/**
	 * Create collapsible section insertion modal
	 */
	function insertCollapsibleModal(defaultLevel) {
		saveSelection();
	
		const modalId = 'editor-col-modal-' + Date.now();
		defaultLevel = defaultLevel || '1';
	
		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';
	
		const modalHtml = `
	  <div class="modal-container">
		<div class="modal-header">
		  <span class="modal-title">${t('editor_insert_collapsible')}</span>
		  <span class="modal-close">&times;</span>
		</div>
		<div class="modal-content">
		  <div class="form-group">
			<label for="col-level-${modalId}">${t('editor_nesting_level')}</label>
			<select id="col-level-${modalId}">
			  <option value="1">${t('editor_col_level_1')}</option>
			  <option value="2">${t('editor_col_level_2')}</option>
			  <option value="3">${t('editor_col_level_3')}</option>
			  <option value="4">${t('editor_col_level_4')}</option>
			</select>
		  </div>
		  <div class="form-group">
			<label for="col-title-${modalId}">${t('title')} :</label>
			<input type="text" id="col-title-${modalId}" value="${t('editor_col_default_title')}" placeholder="${t('editor_col_title_placeholder')}">
		  </div>
		  <div class="form-group">
			<label for="col-icon-${modalId}">${t('editor_col_icon')}</label>
			<input type="text" id="col-icon-${modalId}" value="" maxlength="4" placeholder="Ex : 🧠 ⚙️ 🔬" style="width: 100px;">
		  </div>
		  <div class="form-group">
			<label for="col-color-${modalId}">${t('editor_col_color')}</label>
			<input type="color" id="col-color-${modalId}" value="#60a5fa" style="width: 60px; height: 32px; padding: 0; border: 1px solid #ccc;">
			<label class="checkbox-label" style="display:inline-block; margin-left:12px;">
			  <input type="checkbox" id="col-color-enabled-${modalId}" checked>
			  ${t('editor_col_apply_color')}
			</label>
		  </div>
		</div>
		<div class="modal-footer">
		  <button id="cancel-col-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
		  <button id="insert-col-${modalId}" class="modal-button modal-confirm">${t('insert')}</button>
		</div>
	  </div>
	`;
	
		modalOverlay.innerHTML = modalHtml;
		document.body.appendChild(modalOverlay);
	
		// Pré-sélectionner le niveau demandé par le bouton
		modalOverlay.querySelector(`#col-level-${modalId}`).value = defaultLevel;
	
		const closeBtn = modalOverlay.querySelector('.modal-close');
		const cancelBtn = modalOverlay.querySelector(`#cancel-col-${modalId}`);
		const insertBtn = modalOverlay.querySelector(`#insert-col-${modalId}`);
	
		closeBtn.addEventListener('click', () => document.body.removeChild(modalOverlay));
		cancelBtn.addEventListener('click', () => document.body.removeChild(modalOverlay));
	
		insertBtn.addEventListener('click', () => {
			const level = modalOverlay.querySelector(`#col-level-${modalId}`).value || '1';
			const title = (modalOverlay.querySelector(`#col-title-${modalId}`).value || 'Titre').trim();
			const icon = (modalOverlay.querySelector(`#col-icon-${modalId}`).value || '').trim();
			const color = modalOverlay.querySelector(`#col-color-${modalId}`).value;
			const useColor = modalOverlay.querySelector(`#col-color-enabled-${modalId}`).checked;
	
			// Échappement basique du titre (évite d'injecter du HTML via le champ)
			const safeTitle = title
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
			const safeIcon = icon
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
	
			const iconHtml = safeIcon ? `<span class="c-icon">${safeIcon}</span>` : '';
			const colorStyle = useColor ? ` style="--c-color:${color};--c-bg:${color}1A"` : '';
			const colorAttr = useColor ? ` data-color="${color}"` : '';
	
			const html =
				`<div class="c-col open" data-level="${level}"${colorAttr}${colorStyle}>` +
				`<div class="c-head" contenteditable="false">${iconHtml}<span class="c-title" contenteditable="true">${safeTitle}</span><span class="c-chevron">▼</span></div>` +
				`<div class="c-body"><p>Section content…</p></div>` +
				`</div><p><br></p>`;
	
			editorContent.focus();
			restoreSelection();
			insertHtmlAtCursor(html);
			updateContent();
	
			document.body.removeChild(modalOverlay);
		});
	
		setTimeout(() => {
			modalOverlay.querySelector(`#col-title-${modalId}`).focus();
			modalOverlay.querySelector(`#col-title-${modalId}`).select();
		}, 10);
	}
	
	/**
	 * Create tab group insertion modal
	 * Inserts a <div class="tab-group"> wrapping N level-1 collapsibles.
	 * Each collapsible's title becomes the tab label on the theme.
	 */
	function insertTabGroupModal() {
		saveSelection();
	
		const modalId = 'editor-tab-modal-' + Date.now();
	
		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';
	
		const modalHtml = `
		  <div class="modal-container">
			<div class="modal-header">
			  <span class="modal-title">${t('editor_insert_tab_group')}</span>
			  <span class="modal-close">&times;</span>
			</div>
			<div class="modal-content">
			  <div class="form-group">
				<label for="tab-count-${modalId}">${t('editor_tab_count')}</label>
				<select id="tab-count-${modalId}">
				  <option value="2">${t('editor_tab_n').replace('%d', 2)}</option>
				  <option value="3" selected>${t('editor_tab_n').replace('%d', 3)}</option>
				  <option value="4">${t('editor_tab_n').replace('%d', 4)}</option>
				  <option value="5">${t('editor_tab_n').replace('%d', 5)}</option>
				  <option value="6">${t('editor_tab_n').replace('%d', 6)}</option>
				  <option value="7">${t('editor_tab_n').replace('%d', 7)}</option>
				  <option value="8">${t('editor_tab_n').replace('%d', 8)}</option>
				</select>
			  </div>
			  <p class="help-text" style="font-size:12px;opacity:.75;">
				${t('editor_tab_help')}
			  </p>
			</div>
			<div class="modal-footer">
			  <button id="cancel-tab-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
			  <button id="insert-tab-${modalId}" class="modal-button modal-confirm">${t('insert')}</button>
			</div>
		  </div>
		`;
	
		modalOverlay.innerHTML = modalHtml;
		document.body.appendChild(modalOverlay);
	
		const closeBtn = modalOverlay.querySelector('.modal-close');
		const cancelBtn = modalOverlay.querySelector(`#cancel-tab-${modalId}`);
		const insertBtn = modalOverlay.querySelector(`#insert-tab-${modalId}`);
	
		closeBtn.addEventListener('click', () => document.body.removeChild(modalOverlay));
		cancelBtn.addEventListener('click', () => document.body.removeChild(modalOverlay));
	
		insertBtn.addEventListener('click', () => {
			const count = parseInt(modalOverlay.querySelector(`#tab-count-${modalId}`).value, 10) || 3;
			const palette = ['#60a5fa', '#00e888', '#ffa726', '#c084fc', '#ff6b6b', '#ffd93d', '#fb923c', '#00d4ff'];
	
			let inner = '';
			for (let i = 0; i < count; i++) {
				const color = palette[i % palette.length];
				inner +=
					`<div class="c-col open" data-level="1" data-color="${color}" style="--c-color:${color};--c-bg:${color}1A">` +
					`<div class="c-head" contenteditable="false">` +
					`<span class="c-title" contenteditable="true">Tab ${i + 1}</span>` +
					`<span class="c-chevron">▼</span>` +
					`</div>` +
					`<div class="c-body"><p>Tab content... ${i + 1}…</p></div>` +
					`</div>`;
			}
	
			const html = `<div class="tab-group">${inner}</div><p><br></p>`;
	
			editorContent.focus();
			restoreSelection();
			insertHtmlAtCursor(html);
			updateContent();
	
			document.body.removeChild(modalOverlay);
		});
	}
	
	/**
	 * Insert line break
	 */
	function insertLineBreak() {
		editorContent.focus();
		document.execCommand('insertHTML', false, '<br>');
	}

	/**
	 * Insert columns layout
	 */
	function insertColumns(numColumns) {
		// Save selection before insertion
		saveSelection();

		// Create columns HTML
		let columnsHtml = '<div class="content-columns columns-' + numColumns + '">\n';

		for (let i = 1; i <= numColumns; i++) {
			columnsHtml += '  <div class="column">\n';
			columnsHtml += '    <p>Column ' + i + ' content</p>\n';
			columnsHtml += '  </div>\n';
		}

		columnsHtml += '</div>\n<p><br></p>';

		// Insert at cursor
		editorContent.focus();
		restoreSelection();
		insertHtmlAtCursor(columnsHtml);

		// Update content
		updateContent();
	}

	/**
	 * Insert HTML at cursor position
	 */
	function insertHtmlAtCursor(html) {
		// Check if we're in source view
		if (isInSourceView) {
			if (cmEditor) {
				// CodeMirror : remplace la sélection courante par le HTML
				const doc    = cmEditor.getDoc();
				const cursor = doc.getCursor('from');
				const anchor = doc.getCursor('to');
				doc.replaceRange(html, cursor, anchor);
				// Repositionner le curseur après l'insertion
				const newPos = cmEditor.posFromIndex(
					cmEditor.indexFromPos(cursor) + html.length
				);
				doc.setCursor(newPos);
				cmEditor.focus();
			} else {
				// Fallback textarea brut
				const start   = sourceView.selectionStart;
				const end     = sourceView.selectionEnd;
				const content = sourceView.value;
				sourceView.value = content.slice(0, start) + html + content.slice(end);
				sourceView.selectionStart = sourceView.selectionEnd = start + html.length;
			}
			return;
		}

		// Check if we need to restore selection
		if (window.savedEditorRange) {
			restoreSelection();
		}

		// Get the current selection
		const selection = window.getSelection();
		if (selection.rangeCount === 0) return;

		const range = selection.getRangeAt(0);

		// Delete any selected content
		range.deleteContents();

		// Insert the HTML
		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = html;

		// Use a fragment to insert all nodes at once
		const fragment = document.createDocumentFragment();
		let node, lastNode;

		while ((node = tempDiv.firstChild)) {
			lastNode = fragment.appendChild(node);
		}

		range.insertNode(fragment);

		// Move cursor after insertion
		if (lastNode) {
			const newRange = document.createRange();
			newRange.setStartAfter(lastNode);
			newRange.collapse(true);
			selection.removeAllRanges();
			selection.addRange(newRange);
		}
	}

	/**
	 * Image insertion modal - using gallery modal
	 */
	function insertImageModal() {
		// Save selection before modal
		saveSelection();

		// Set global flag for gallery modal
		window.isEditorImageInsertion = true;
		// Ensure the selection mode is reset to multi-select gallery mode
		window.gallerySelectionMode = 'gallery';

		// Get gallery modal
		const galleryModal = document.getElementById('gallery-modal');
		if (!galleryModal) {
			console.error("Gallery modal not found");
			return;
		}

		// Show the modal
		galleryModal.style.display = 'block';

		// Update modal title and button text
		const modalTitle = galleryModal.querySelector('h2');
		if (modalTitle) {
			modalTitle.textContent = t('editor_insert_image_title');
		}

		// Update button text to reflect editor insertion context
		const addSelectedBtn = document.getElementById('add-selected-gallery-items');
		if (addSelectedBtn) {
			addSelectedBtn.textContent = t('insert_into_editor');
		}

		// Load files using get-files.php (via loadFilesForGallery)
		if (typeof window.loadFilesForGallery === 'function') {
			window.loadFilesForGallery('');
		} else {
			// Fallback if loadFilesForGallery not available
			const filesContainer = galleryModal.querySelector('#modal-files');
			if (filesContainer) {
				filesContainer.innerHTML = '<p>Loading files...</p>';

				fetch('get-files.php?path=')
					.then(response => response.json())
					.then(data => {
						displayFilesInModal(filesContainer, data, '');
					})
					.catch(error => {
						filesContainer.innerHTML = '<p>Error loading files: ' + error.message + '</p>';
					});
			}
		}
	}

	/**
	 * Display files in modal for image selection
	 */
	function displayFilesInModal(container, data, path) {
		let html = '';

		// Add folders
		if (data.folders && data.folders.length) {
			html += '<div class="folders-grid">';
			data.folders.forEach(folder => {
				html += `<div class="folder-item" data-path="${path}${folder}/">
		  <div class="folder-icon">📁</div>
		  <div class="folder-name">${folder}</div>
		</div>`;
			});
			html += '</div>';
		}

		// Add files (only images)
		if (data.files && data.files.length) {
			const imageFiles = data.files.filter(file => file.is_image);

			if (imageFiles.length) {
				html += '<div class="files-grid">';
				imageFiles.forEach(file => {
					const filePath = path + file.name;
					const imageUrl = '../files/' + filePath;

					html += `<div class="file-item" data-path="${filePath}">
			<div class="file-thumbnail"><img src="${imageUrl}" alt="${file.name}"></div>
			<div class="file-name">${file.name}</div>
		  </div>`;
				});
				html += '</div>';
			} else {
				html += '<p>' + t('editor_no_images') + '</p>';
			}
		}

		container.innerHTML = html;

		// Add click handlers for folders
		container.querySelectorAll('.folder-item').forEach(folder => {
			folder.addEventListener('click', function() {
				const folderPath = this.getAttribute('data-path');
				fetch('get-files.php?path=' + encodeURIComponent(folderPath))
					.then(response => response.json())
					.then(data => {
						displayFilesInModal(container, data, folderPath);
					});
			});
		});

		// Add click handlers for files in editor mode
		if (window.isEditorImageInsertion) {
			container.querySelectorAll('.file-item').forEach(file => {
				file.addEventListener('click', function() {
					file.classList.toggle('selected');
				});
			});
		}
	}

	/**
	 * Gallery shortcode insertion — shows picker if multiple galleries exist
	 */
	function insertGalleryShortcodeModal() {
		saveSelection();

		const blocks = document.querySelectorAll('.named-gallery-block');

		if (blocks.length === 0) {
			window.showModal(t('editor_no_gallery_alert'), t('editor_insert_gallery'));
			return;
		}

		if (blocks.length === 1) {
			const id = blocks[0].dataset.galleryIndex;
			window.insertShortcodeAtCursor('[gallery id="' + id + '"]');
			return;
		}

		// Multiple galleries — show a floating picker
		document.querySelectorAll('.gallery-shortcode-picker').forEach(p => p.remove());

		const picker = document.createElement('div');
		picker.className = 'gallery-shortcode-picker';
		picker.style.cssText = 'position:fixed;z-index:10001;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.2);padding:12px;min-width:220px;';

		const title = document.createElement('div');
		title.style.cssText = 'font-weight:600;font-size:13px;margin-bottom:8px;color:#333;';
		title.textContent = t('editor_insert_which_gallery');
		picker.appendChild(title);

		blocks.forEach(block => {
			const id    = block.dataset.galleryIndex;
			const label = block.querySelector('.gallery-label-input')?.value || t('editor_gallery_label').replace('%s', id);
			const btn   = document.createElement('button');
			btn.type    = 'button';
			btn.style.cssText = 'display: block;width: 100%;text-align: left;padding: 7px 10px;margin-bottom: 4px;border: 1px solid rgb(224, 224, 224);border-radius: 5px;background: rgb(62 72 62);cursor: pointer;font-size: 13px;color:white;';
			btn.innerHTML = '<strong>' + label + '</strong> <code style="font-size:11px;color:#888;">[gallery id="' + id + '"]</code>';
			btn.addEventListener('click', function() {
				picker.remove();
				window.insertShortcodeAtCursor('[gallery id="' + id + '"]');
			});
			picker.appendChild(btn);
		});

		const closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.style.cssText = 'margin-top:4px;width:100%;padding:5px;border:none;background:none;color:#999;cursor:pointer;font-size:12px;';
		closeBtn.textContent = '✕ Close';
		closeBtn.addEventListener('click', () => picker.remove());
		picker.appendChild(closeBtn);

		// Position near the toolbar
		const toolbarRect = toolbar.getBoundingClientRect();
		picker.style.top  = (toolbarRect.bottom + 6) + 'px';
		picker.style.left = toolbarRect.left + 'px';
		document.body.appendChild(picker);

		// Close on outside click
		let skipFirst = true;
		document.addEventListener('mousedown', function closePicker(e) {
			if (skipFirst) { skipFirst = false; return; }
			if (!picker.contains(e.target)) {
				picker.remove();
				document.removeEventListener('mousedown', closePicker);
			}
		});
	}

	// ════════════════════════════════════════════════════════════════════════
	// Shortcode picker modals
	// Each follows the same pattern: saveSelection → build modal → on confirm
	// call window.insertShortcodeAtCursor(shortcodeString).
	// ════════════════════════════════════════════════════════════════════════

	/** Shared modal builder — returns {overlay, form} */
	function _scModal(title, fields, onInsert) {
		saveSelection();
		const id = 'sc-modal-' + Date.now();
		const overlay = document.createElement('div');
		overlay.className = 'modal-overlay';
		overlay.id = id;
		overlay.style.display = 'block';

		let fieldsHtml = fields.map(f => {
			if (f.type === 'select') {
				const opts = f.options.map(o =>
					`<option value="${o.value}"${o.value === f.default ? ' selected' : ''}>${o.label}</option>`
				).join('');
				return `<div class="form-group"><label>${f.label}</label><select id="sc-${f.id}-${id}">${opts}</select>${f.help ? `<p class="help-text">${f.help}</p>` : ''}</div>`;
			}
			if (f.type === 'textarea') {
				return `<div class="form-group"><label>${f.label}</label><textarea id="sc-${f.id}-${id}" rows="4" placeholder="${f.placeholder||''}">${f.default||''}</textarea>${f.help ? `<p class="help-text">${f.help}</p>` : ''}</div>`;
			}
			return `<div class="form-group"><label>${f.label}</label><input type="text" id="sc-${f.id}-${id}" value="${f.default||''}" placeholder="${f.placeholder||''}">${f.help ? `<p class="help-text">${f.help}</p>` : ''}</div>`;
		}).join('');

		overlay.innerHTML = `
		<div class="modal-container">
			<div class="modal-header">
				<span class="modal-title">${title}</span>
				<span class="modal-close">&times;</span>
			</div>
			<div class="modal-content">${fieldsHtml}</div>
			<div class="modal-footer">
				<button class="modal-button modal-cancel">${t('cancel','Cancel')}</button>
				<button class="modal-button modal-confirm">${t('insert','Insert')}</button>
			</div>
		</div>`;

		document.body.appendChild(overlay);

		const get = (fieldId) => overlay.querySelector(`#sc-${fieldId}-${id}`)?.value.trim() ?? '';
		const close = () => document.body.removeChild(overlay);

		overlay.querySelector('.modal-close').addEventListener('click', close);
		overlay.querySelector('.modal-cancel').addEventListener('click', close);
		overlay.querySelector('.modal-confirm').addEventListener('click', () => {
			const sc = onInsert(get);
			if (sc) {
				editorContent.focus();
				restoreSelection();
				window.insertShortcodeAtCursor(sc);
			}
			close();
		});

		setTimeout(() => overlay.querySelector('input, textarea, select')?.focus(), 30);
	}

	/** [callout type="info|warning|tip|danger"]Text[/callout] */
	function scCalloutModal(defaultType) {
		_scModal(
			t('sc_callout', 'Callout block'),
			[
				{ id: 'type', label: t('sc_callout_type', 'Type'), type: 'select', default: defaultType || 'info',
				  options: [
					{ value: 'info',    label: 'ℹ️  Info' },
					{ value: 'warning', label: '⚠️  Warning' },
					{ value: 'tip',     label: '💡 Tip' },
					{ value: 'danger',  label: '🚫 Danger' },
				  ]
				},
				{ id: 'text', label: t('sc_content', 'Content'), type: 'textarea',
				  placeholder: t('sc_callout_ph', 'Callout text…'), default: '' },
			],
			(get) => `[callout type="${get('type')}"]${get('text')}[/callout]`
		);
	}

	/** [quote author="Name"]Text[/quote] */
	function scQuoteModal() {
		_scModal(
			t('sc_quote', 'Quote'),
			[
				{ id: 'author', label: t('sc_quote_author', 'Author'), type: 'text',
				  placeholder: 'Hippocrate', default: '' },
				{ id: 'text', label: t('sc_content', 'Content'), type: 'textarea',
				  placeholder: t('sc_quote_ph', 'Quote text…'), default: '' },
			],
			(get) => {
				const author = get('author');
				const attr   = author ? ` author="${author}"` : '';
				return `[quote${attr}]${get('text')}[/quote]`;
			}
		);
	}

	/** [button url="…" label="…" style="primary"] */
	function scButtonModal() {
		_scModal(
			t('sc_button', 'Button / CTA'),
			[
				{ id: 'label', label: t('sc_button_label', 'Label'), type: 'text',
				  placeholder: t('sc_button_label_ph', 'Click here'), default: '' },
				{ id: 'url', label: 'URL', type: 'text', placeholder: '/contact', default: '' },
				{ id: 'style', label: t('sc_button_style', 'Style'), type: 'select', default: 'primary',
				  options: [
					{ value: 'primary',   label: 'Primary' },
					{ value: 'secondary', label: 'Secondary' },
					{ value: 'outline',   label: 'Outline' },
				  ]
				},
			],
			(get) => `[button url="${get('url')}" label="${get('label')}" style="${get('style')}"]`
		);
	}

	/** [recent_articles limit="3" tag="…" category="…"] */
	function scRecentArticlesModal() {
		_scModal(
			t('sc_recent_articles', 'Recent articles'),
			[
				{ id: 'limit', label: t('sc_limit', 'Number of articles'), type: 'text',
				  placeholder: '3', default: '3' },
				{ id: 'tag', label: t('sc_tag_filter', 'Filter by tag (optional)'), type: 'text',
				  placeholder: 'naturopathie', default: '',
				  help: t('sc_tag_filter_help', 'Leave empty to show all recent articles.') },
				{ id: 'category', label: t('sc_cat_filter', 'Filter by category (optional)'), type: 'text',
				  placeholder: 'anatomie', default: '' },
			],
			(get) => {
				let sc = `[recent_articles limit="${get('limit') || '3'}"`;
				if (get('tag'))      sc += ` tag="${get('tag')}"`;
				if (get('category')) sc += ` category="${get('category')}"`;
				sc += ']';
				return sc;
			}
		);
	}

	/** [recent_projects limit="3"] */
	function scRecentProjectsModal() {
		_scModal(
			t('sc_recent_projects', 'Recent projects'),
			[
				{ id: 'limit', label: t('sc_limit', 'Number of projects'), type: 'text',
				  placeholder: '3', default: '3' },
			],
			(get) => `[recent_projects limit="${get('limit') || '3'}"]`
		);
	}

	/** [articles_by_tag tag="…" limit="5"] */
	function scArticlesByTagModal() {
		_scModal(
			t('sc_articles_by_tag', 'Articles by tag'),
			[
				{ id: 'tag', label: t('sc_tag', 'Tag'), type: 'text',
				  placeholder: 'nutrition', default: '' },
				{ id: 'limit', label: t('sc_limit', 'Number of articles'), type: 'text',
				  placeholder: '5', default: '5' },
			],
			(get) => `[articles_by_tag tag="${get('tag')}" limit="${get('limit') || '5'}"]`
		);
	}


	/**
	 * Insert a plain-text shortcode at the current editor cursor position.
	 * Registered with EditorCommon so the global dispatcher routes here when HTML format is active.
	 */
	const _insertShortcodeAtCursor = function(shortcode) {
		editorContent.focus();
		restoreSelection();

		const sel = window.getSelection();
		if (sel && sel.rangeCount > 0) {
			const range = sel.getRangeAt(0);
			range.deleteContents();
			const textNode = document.createTextNode(shortcode);
			range.insertNode(textNode);
			range.setStartAfter(textNode);
			range.collapse(true);
			sel.removeAllRanges();
			sel.addRange(range);
		} else {
			// Fallback: append a new paragraph with the shortcode
			editorContent.innerHTML += '<p>' + shortcode + '</p>';
		}
		setTimeout(updateContent, 10);
	};


	/**
	 * Insert image at cursor position
	 * Make this globally available for the gallery modal
	 */
	// autoClose = false is used by gallery.js when inserting multiple images in sequence.
	// In that case the function saves the cursor after each insert so the next call
	// lands right after the previously inserted image instead of re-using the stale
	// original saved range (which restoreSelection() clears on first call).
	const _insertImageAtCursor = function(src, alt = 'Image', autoClose = true) {
		// Focus editor and restore selection
		editorContent.focus();
		restoreSelection();

		// Create the image element
		const img = document.createElement('img');

		// Make sure src is a proper URL
		if (src.startsWith('http')) {
			img.src = src; // Already absolute
		} else {
			// Clean up path
			let cleanPath = src;
			if (cleanPath.startsWith('../')) {
				cleanPath = cleanPath.substring(3);
			}

			// Ensure path is complete
			if (!cleanPath.startsWith('files/')) {
				cleanPath = 'files/' + cleanPath.replace(/^\/+/, '');
			}

			// Build absolute URL the same way getFileUrl() does in gallery.js
			const baseUrl  = window.location.protocol + '//' + window.location.host;
			const adminPos = window.location.pathname.indexOf('/admin/');
			let basePath   = '';

			if (adminPos !== -1) {
				basePath = window.location.pathname.substring(0, adminPos);
			}

			img.src = baseUrl + basePath + '/' + cleanPath;
		}

		img.alt = alt;
		img.style.maxWidth = '100%';

		// Insert the image
		const selection = window.getSelection();
		const range = selection.getRangeAt(0);
		range.deleteContents();
		range.insertNode(img);

		// Move cursor after image
		range.setStartAfter(img);
		range.collapse(true);
		selection.removeAllRanges();
		selection.addRange(range);

		// Make the image resizable
		makeImageResizable(img);

		// Add paragraph after image if needed
		const parent = img.parentNode;
		if (!img.nextSibling ||
			(img.nextSibling.nodeType !== Node.ELEMENT_NODE ||
				img.nextSibling.tagName !== 'P')) {
			const p = document.createElement('p');
			p.innerHTML = '<br>';
			parent.insertBefore(p, img.nextSibling);

			// Move cursor to this paragraph
			const newRange = document.createRange();
			newRange.setStart(p, 0);
			newRange.collapse(true);
			selection.removeAllRanges();
			selection.addRange(newRange);
		}

		// Update content
		updateContent();

		if (autoClose) {
			// Single-image insert: clean up flags and close the modal
			window.isEditorImageInsertion = false;
			const galleryModal = document.getElementById('gallery-modal');
			if (galleryModal) {
				galleryModal.style.display = 'none';
			}
		} else {
			// Multi-image insert: persist the current cursor position so the next
			// call to insertImageAtCursor restores it instead of the stale original range.
			if (window.getSelection().rangeCount > 0) {
				window.savedEditorRange = window.getSelection().getRangeAt(0).cloneRange();
			}
		}
	};

	/**
	 * Make an image resizable with controls
	 */
	function makeImageResizable(img) {
		if (!img) return;

		// Remove any existing handlers to prevent duplicates
		img.removeEventListener('click', imageClickHandler);

		// Add click handler to show controls
		img.addEventListener('click', imageClickHandler);
	}

	/**
	 * Handle image click to show the alignment toolbar
	 */
	function imageClickHandler(e) {
		e.preventDefault();
		e.stopPropagation();

		// Deselect every other image
		document.querySelectorAll('.editor-content img').forEach(img => {
			img.classList.remove('selected');
		});

		this.classList.add('selected');

		// Show the inline alignment toolbar (Edit button inside leads to showImageControls)
		showImageAlignToolbar(this);
	}

	/**
	 * Show image properties dialog (width, height, alt text)
	 * Opened from the alignment toolbar's Edit button.
	 */
	function showImageControls(img) {
		const currentWidth  = img.width;
		const currentHeight = img.height;
		const naturalWidth  = img.naturalWidth  || currentWidth;
		const naturalHeight = img.naturalHeight || currentHeight;

		const modalId = 'editor-image-modal-' + Date.now();

		const modalOverlay = document.createElement('div');
		modalOverlay.className = 'modal-overlay';
		modalOverlay.id = modalId;
		modalOverlay.style.display = 'block';

		const originalLabel = t('editor_img_original_size').replace('%s', naturalWidth + '×' + naturalHeight);

		modalOverlay.innerHTML = `
  <div class="modal-container">
	<div class="modal-header">
	  <span class="modal-title">${t('editor_img_properties')}</span>
	  <span class="modal-close">&times;</span>
	</div>
	<div class="modal-content">
	  <div class="form-group">
		<label for="image-width-${modalId}">${t('editor_img_width')}</label>
		<input type="number" id="image-width-${modalId}" value="${currentWidth}" min="10" max="2000">
		<span class="help-text">${originalLabel}</span>
	  </div>
	  <div class="form-group">
		<label for="image-height-${modalId}">${t('editor_img_height')}</label>
		<input type="number" id="image-height-${modalId}" value="${currentHeight}" min="10" max="2000">
	  </div>
	  <div class="form-group">
		<label for="image-alt-${modalId}">${t('alt_text')}</label>
		<input type="text" id="image-alt-${modalId}" value="${img.alt || ''}">
	  </div>
	  <div class="form-group">
		<label class="checkbox-label">
		  <input type="checkbox" id="maintain-ratio-${modalId}" checked>
		  ${t('editor_img_maintain_ratio')}
		</label>
	  </div>
	  <div class="form-group">
		<button type="button" id="reset-size-${modalId}"  class="button">${t('editor_img_reset_size')}</button>
		<button type="button" id="remove-image-${modalId}" class="button cancel">${t('remove_image')}</button>
	  </div>
	</div>
	<div class="modal-footer">
	  <button id="cancel-image-${modalId}" class="modal-button modal-cancel">${t('cancel')}</button>
	  <button id="apply-image-${modalId}"  class="modal-button modal-confirm">${t('editor_img_apply')}</button>
	</div>
  </div>
`;

		document.body.appendChild(modalOverlay);

		const closeBtn     = modalOverlay.querySelector('.modal-close');
		const cancelBtn    = modalOverlay.querySelector(`#cancel-image-${modalId}`);
		const applyBtn     = modalOverlay.querySelector(`#apply-image-${modalId}`);
		const widthInput   = modalOverlay.querySelector(`#image-width-${modalId}`);
		const heightInput  = modalOverlay.querySelector(`#image-height-${modalId}`);
		const maintainRatio = modalOverlay.querySelector(`#maintain-ratio-${modalId}`);
		const aspectRatio  = naturalHeight / naturalWidth;

		function closeModal() {
			document.body.removeChild(modalOverlay);
			img.classList.remove('selected');
		}

		function applyChanges() {
			const width  = parseInt(widthInput.value);
			const height = parseInt(heightInput.value);
			const alt    = modalOverlay.querySelector(`#image-alt-${modalId}`).value;
			if (!isNaN(width)  && width  > 0) img.width  = width;
			if (!isNaN(height) && height > 0) img.height = height;
			img.alt = alt;
			img.classList.remove('selected');
			updateContent();
			document.body.removeChild(modalOverlay);
		}

		closeBtn.addEventListener('click',  closeModal);
		cancelBtn.addEventListener('click', closeModal);
		applyBtn.addEventListener('click',  applyChanges);

		// Enter → apply; Escape → cancel
		modalOverlay.addEventListener('keydown', function(e) {
			if (e.key === 'Enter')  { e.preventDefault(); applyChanges(); }
			if (e.key === 'Escape') { e.preventDefault(); closeModal(); }
		});

		// Aspect-ratio maintenance
		widthInput.addEventListener('input', function() {
			if (maintainRatio.checked) {
				heightInput.value = Math.round((parseInt(this.value) || currentWidth) * aspectRatio);
			}
		});
		heightInput.addEventListener('input', function() {
			if (maintainRatio.checked) {
				widthInput.value = Math.round((parseInt(this.value) || currentHeight) / aspectRatio);
			}
		});

		// Reset to natural size
		modalOverlay.querySelector(`#reset-size-${modalId}`).addEventListener('click', () => {
			widthInput.value  = naturalWidth;
			heightInput.value = naturalHeight;
		});

		// Remove image
		modalOverlay.querySelector(`#remove-image-${modalId}`).addEventListener('click', () => {
			img.parentNode.removeChild(img);
			document.body.removeChild(modalOverlay);
			updateContent();
		});

		// Auto-focus width field so the user can type + press Enter right away
		setTimeout(() => { widthInput.focus(); widthInput.select(); }, 50);
	}

	// ── Image alignment contextual toolbar ────────────────────────────────────

	/**
	 * Show a small floating toolbar below (or above) the clicked image.
	 * Buttons: float-left, float-right, center, full-width, reset, edit.
	 */
	function showImageAlignToolbar(img) {
		removeImageAlignToolbar();

		const toolbar = document.createElement('div');
		toolbar.className = 'img-align-toolbar';
		toolbar.id = 'img-align-toolbar';

		// Button definitions: [icon, command, i18n key]
		const btnDefs = [
			{ icon: '◧', cmd: 'left',   key: 'editor_img_float_left'   },
			{ icon: '◨', cmd: 'right',  key: 'editor_img_float_right'  },
			{ icon: '⊡', cmd: 'center', key: 'editor_img_align_center' },
			{ icon: '↔', cmd: 'full',   key: 'editor_img_full_width'   },
			{ icon: '⊘', cmd: 'none',   key: 'editor_img_no_align'     },
			{ icon: '✎', cmd: 'edit',   key: 'editor_img_edit'         },
		];

		btnDefs.forEach(function(def) {
			const btn = document.createElement('button');
			btn.type      = 'button';
			btn.className = 'img-align-btn';
			btn.title     = t(def.key);
			btn.textContent = def.icon;
			if (isActiveAlignment(img, def.cmd)) btn.classList.add('active');

			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				applyImageAlignment(img, def.cmd);
			});
			toolbar.appendChild(btn);
		});

		document.body.appendChild(toolbar);
		positionAlignToolbar(toolbar, img);

		// Reposition on window scroll/resize
		function onScroll() { positionAlignToolbar(toolbar, img); }
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		toolbar._cleanupScroll = function() {
			window.removeEventListener('scroll', onScroll);
			window.removeEventListener('resize', onScroll);
		};

		// Close when clicking outside image and toolbar
		setTimeout(function() {
			document.addEventListener('click', function outsideHandler(e) {
				if (!toolbar.contains(e.target) && e.target !== img) {
					removeImageAlignToolbar();
					img.classList.remove('selected');
					document.removeEventListener('click', outsideHandler);
				}
			});
		}, 0);

		// Close on Escape
		document.addEventListener('keydown', function escHandler(e) {
			if (e.key === 'Escape') {
				removeImageAlignToolbar();
				img.classList.remove('selected');
				document.removeEventListener('keydown', escHandler);
			}
		});
	}

	/** Position the toolbar just below the image (or above if near the viewport bottom). */
	function positionAlignToolbar(toolbar, img) {
		const rect          = img.getBoundingClientRect();
		const tbHeight      = toolbar.offsetHeight || 38;
		const tbWidth       = toolbar.offsetWidth  || 200;
		const margin        = 6;
		const viewportH     = window.innerHeight;
		const viewportW     = window.innerWidth;

		let top  = rect.bottom + margin;
		if (top + tbHeight > viewportH - 8) {
			top = rect.top - tbHeight - margin;
		}

		let left = rect.left;
		if (left + tbWidth > viewportW - 8) {
			left = viewportW - tbWidth - 8;
		}
		left = Math.max(8, left);

		toolbar.style.top  = top  + 'px';
		toolbar.style.left = left + 'px';
	}

	/** Remove the alignment toolbar and clean up its listeners. */
	function removeImageAlignToolbar() {
		const existing = document.getElementById('img-align-toolbar');
		if (existing) {
			if (existing._cleanupScroll) existing._cleanupScroll();
			existing.remove();
		}
	}

	/**
	 * Apply an alignment preset to the image via inline styles.
	 * Inline styles persist in saved HTML without requiring theme CSS classes.
	 */
	function applyImageAlignment(img, cmd) {
		// Strip previous alignment state
		img.style.cssText = img.style.cssText
			.replace(/float\s*:[^;]+;?/gi,       '')
			.replace(/margin\s*:[^;]+;?/gi,       '')
			.replace(/display\s*:[^;]+;?/gi,      '')
			.replace(/clear\s*:[^;]+;?/gi,        '')
			.replace(/max-width\s*:[^;]+;?/gi,    '')
			.replace(/width\s*:[^;]+;?/gi,        '')
			.replace(/height\s*:[^;]+;?/gi,       '');

		switch (cmd) {
			case 'left':
				img.style.float    = 'left';
				img.style.margin   = '4px 16px 10px 0';
				img.style.maxWidth = '50%';
				img.style.height   = 'auto';
				break;
			case 'right':
				img.style.float    = 'right';
				img.style.margin   = '4px 0 10px 16px';
				img.style.maxWidth = '50%';
				img.style.height   = 'auto';
				break;
			case 'center':
				img.style.display  = 'block';
				img.style.margin   = '10px auto';
				img.style.float    = 'none';
				img.style.clear    = 'both';
				break;
			case 'full':
				img.style.display  = 'block';
				img.style.width    = '100%';
				img.style.maxWidth = '100%';
				img.style.height   = 'auto';
				img.style.float    = 'none';
				img.style.clear    = 'both';
				img.style.margin   = '10px 0';
				break;
			case 'edit':
				removeImageAlignToolbar();
				img.classList.remove('selected');
				showImageControls(img);
				return; // skip cleanup below
			case 'none':
			default:
				// All styles already stripped above — nothing else needed
				break;
		}

		removeImageAlignToolbar();
		img.classList.remove('selected');
		updateContent();
	}

	/**
	 * Return whether the given alignment command is currently active on the image,
	 * based on the image's inline style (the canonical source of truth after save/reload).
	 */
	function isActiveAlignment(img, cmd) {
		const s = img.style;
		switch (cmd) {
			case 'left':   return s.float === 'left';
			case 'right':  return s.float === 'right';
			case 'center': return s.float !== 'left' && s.float !== 'right'
							   && s.display === 'block' && s.width !== '100%';
			case 'full':   return s.width === '100%';
			default:       return false;
		}
	}

	/**
	 * Clean up editor content for better formatting
	 */
	function cleanupContent() {
		// Skip if in source view
		if (isInSourceView) return;

		// Clean up empty paragraphs
		const emptyParagraphs = editorContent.querySelectorAll('p:empty:not(:only-child)');
		emptyParagraphs.forEach(p => {
			if (!p.firstChild && p !== editorContent.lastElementChild) {
				p.parentNode.removeChild(p);
			}
		});

		// Ensure paragraphs have content
		const paragraphs = editorContent.querySelectorAll('p');
		paragraphs.forEach(p => {
			if (p.innerHTML.trim() === '') {
				p.innerHTML = '<br>';
			}
		});

		// Ensure last element is a paragraph for proper editing
		const lastElement = editorContent.lastElementChild;
		if (!lastElement || lastElement.tagName !== 'P') {
			const newParagraph = document.createElement('p');
			newParagraph.innerHTML = '<br>';
			editorContent.appendChild(newParagraph);
		}
	}

	/**
	 * Update content in textarea and hidden field
	 */
	function updateContent() {
		if (isInSourceView) {
			const val = cmEditor ? cmEditor.getValue() : sourceView.value;
			contentTextarea.value = val;
			contentHidden.value   = val;
		} else {
			const rawHtml = editorContent.innerHTML;  // ← pas de formatHTML ici
			contentTextarea.value = rawHtml;
			contentHidden.value   = rawHtml;
			editorContent.querySelectorAll('img').forEach(img => makeImageResizable(img));
		}
	}

	/**
	 * Handle paste events for proper formatting
	 */
	editorContent.addEventListener('paste', function(e) {
		e.preventDefault();

		// Get pasted content
		let pastedText = '';
		let pastedHtml = '';

		if (e.clipboardData && e.clipboardData.getData) {
			pastedText = e.clipboardData.getData('text/plain');
			pastedHtml = e.clipboardData.getData('text/html');
		}

		if (pastedHtml) {
			// Clean the HTML before inserting
			const tempDiv = document.createElement('div');
			tempDiv.innerHTML = pastedHtml;

			// Remove unwanted styles and attributes
			const allElements = tempDiv.querySelectorAll('*');
			allElements.forEach(el => {
				// Keep only essential attributes
				const allowedAttrs = ['href', 'src', 'alt', 'title', 'id', 'class'];

				for (let i = el.attributes.length - 1; i >= 0; i--) {
					const attr = el.attributes[i];
					if (!allowedAttrs.includes(attr.name)) {
						el.removeAttribute(attr.name);
					}
				}
			});

			// Insert the cleaned HTML
			document.execCommand('insertHTML', false, tempDiv.innerHTML);
		} else {
			// Insert plain text
			document.execCommand('insertText', false, pastedText);
		}

		// Clean up content after paste
		setTimeout(cleanupContent, 0);
		updateContent();
	});

	/**
	 * Handle keyup events for content updates
	 */
	editorContent.addEventListener('keyup', function(e) {
		// Cleanup on certain keys
		if (e.key === 'Enter' || e.key === 'Delete' || e.key === 'Backspace') {
			setTimeout(cleanupContent, 0);
		}

		// Update content
		updateContent();
	});

	/**
	 * Handle source view changes — CodeMirror fires updateContent() via its
	 * 'change' event registered at init time. The raw textarea fallback is kept
	 * for the rare case where CodeMirror hasn't loaded yet.
	 */
	sourceView.addEventListener('keyup', function() {
		if (!cmEditor) updateContent(); // fallback only
	});

	/**
	 * Handle form submissions to ensure content is updated
	 */
	const formElement = contentTextarea.closest('form');
	if (formElement) {
		formElement.addEventListener('submit', function () {
			// Only sync when WYSIWYG is the active format
			if (!window.CONTENT_FORMAT || window.CONTENT_FORMAT === 'html') updateContent();
		});
	}

	/**
	 * Initialize existing images to be resizable
	 */
	const existingImages = editorContent.querySelectorAll('img');
	existingImages.forEach(img => {
		makeImageResizable(img);
	});

	// Register with EditorCommon for autosave dispatch and format switching
	window.EditorCommon.registerEditor('html', {
		syncContent:     updateContent,
		insertImage:     _insertImageAtCursor,
		insertShortcode: _insertShortcodeAtCursor,
		show: function () {
			// Restore WYSIWYG and sync content from the hidden input
			const latestVal = document.getElementById('content-hidden')?.value || '';
			editorContent.innerHTML = latestVal || '<p><br></p>';
			editorContainer.style.display = '';
			updateContent();
			if (!isInSourceView) editorContent.focus();
		},
		hide: function () {
			updateContent();
			editorContainer.style.display = 'none';
		},
	});

	// ── AUTOSAVE REMOVED ─────────────────────────────────────────────────────
	// Autosave is now handled entirely by editor-common.js (EditorCommon).
	// The manual save button and the toggle checkbox are wired up there.
	// ─────────────────────────────────────────────────────────────────────────

	let autoSaveEnabled = window.AUTOSAVE_ENABLED_BY_SETTINGS !== false;



	// Add CSS for the columns
	const style = document.createElement('style');
	style.textContent = `
	/* ===== Sections pliables dans l'éditeur WYSIWYG ===== */
	.editor-content .c-col{
	  --c-color:#60a5fa;
	  --c-bg:rgba(96,165,250,.08);
	  border:1px solid rgba(0,0,0,.1);
	  border-radius:10px;
	  margin:10px 0;
	  background:var(--c-bg);
	  overflow:hidden;
	}
	.editor-content .c-col > .c-head{
	  display:flex;
	  align-items:center;
	  gap:10px;
	  padding:10px 12px;
	  background:rgba(0,0,0,.03);
	  cursor:default;
	  user-select:none;
	  border-bottom:1px solid rgba(0,0,0,.06);
	}
	.editor-content .c-col > .c-head .c-title{
	  flex:1;
	  font-weight:600;
	  color:var(--c-color);
	  outline:none;
	}
	.editor-content .c-col > .c-head .c-title:focus{
	  background:rgba(255,255,255,.6);
	  border-radius:4px;
	  padding:2px 4px;
	}
	.editor-content .c-col > .c-head .c-chevron{
	  font-size:11px;
	  color:var(--c-color);
	  opacity:.6;
	  /* rétablit l'affichage du caractère ▼ dans l'éditeur */
	  display:inline-flex;
	  align-items:center;
	  justify-content:center;
	  width:20px;
	  height:20px;
	}
	.editor-content .c-col > .c-head .c-chevron::before{
	  display:none; /* masque le triangle CSS : on garde le caractère ▼ */
	}
	.editor-content .c-col > .c-body{
	  display:block !important;      /* toujours visible en édition */
	  max-height:none !important;    /* override l'animation max-height du thème */
	  overflow:visible !important;
	  opacity:1 !important;
	  transform:none !important;
	  padding:10px 14px !important;
	}
	.editor-content .c-col[data-level="1"]::before{ content:"Niveau 1"; }
	.editor-content .c-col[data-level="2"]::before{ content:"Niveau 2"; }
	.editor-content .c-col[data-level="3"]::before{ content:"Niveau 3"; }
	.editor-content .c-col[data-level="4"]::before{ content:"Niveau 4"; }
	.editor-content .c-col::before{
	  display:inline-block;
	  font-size:10px;
	  text-transform:uppercase;
	  letter-spacing:.5px;
	  color:var(--c-color);
	  opacity:.6;
	  padding:3px 8px;
	  background:rgba(0,0,0,.05);
	  border-bottom-right-radius:6px;
	  font-weight:700;
	}
	.editor-content .c-col[data-level="2"]{margin-left:8px;}
	.editor-content .c-col[data-level="3"]{margin-left:16px;}
	.editor-content .c-col[data-level="4"]{margin-left:24px;}
	
	.content-columns {
	  display: flex;
	  flex-wrap: wrap;
	  gap: 20px;
	  margin: 1em 0;
	}
	
	.columns-2 .column {
	  flex: 0 0 calc(50% - 10px);
	}
	
	.columns-3 .column {
	  flex: 0 0 calc(33.333% - 14px);
	}
	
	.columns-4 .column {
	  flex: 0 0 calc(25% - 15px);
	}
	
	@media (max-width: 768px) {
	  .content-columns {
		flex-direction: column;
	  }
	  
	  .columns-2 .column,
	  .columns-3 .column,
	  .columns-4 .column {
		flex: 0 0 100%;
	  }
	}
	/* ===== Dropdown toolbar ===== */
	.editor-dropdown {
	  position: relative;
	  display: inline-flex;
	}
	.editor-dropdown-btn {
	  display: inline-flex;
	  align-items: center;
	  gap: 2px;
	}
	.editor-dropdown-arrow {
	  font-size: 8px;
	  opacity: .6;
	  margin-left: 1px;
	}
	.editor-dropdown-menu {
	  display: none;
	  position: absolute;
	  top: calc(100% + 4px);
	  left: 0;
	  background: #fff;
	  border: 1px solid #d0d7de;
	  border-radius: 6px;
	  padding: 4px;
	  z-index: 9999;
	  min-width: 160px;
	  box-shadow: 0 6px 18px rgba(0,0,0,.15);
	  flex-direction: column;
	  gap: 2px;
	}
	.editor-dropdown-menu.open {
	  display: flex;
	}
	.editor-dropdown-item {
	  display: flex;
	  align-items: center;
	  gap: 8px;
	  width: 100%;
	  background: none;
	  border: none;
	  color: #1a1a1a;
	  cursor: pointer;
	  padding: 6px 10px;
	  border-radius: 4px;
	  font-size: 13px;
	  text-align: left;
	  white-space: nowrap;
	}
	.editor-dropdown-item:hover {
	  background: #f0f6ff;
	  color: #0969da;
	}
	.dropdown-item-label {
	  font-size: 12px;
	  opacity: .85;
	}
	/* ===== Color picker ===== */
	.editor-color-wrapper {
	  position: relative;
	  display: inline-flex;
	}
	.editor-color-btn {
	  display: inline-flex;
	  flex-direction: column;
	  align-items: center;
	  gap: 2px;
	  padding: 4px 6px !important;
	}
	.color-letter {
	  font-weight: 700;
	  font-size: 13px;
	  line-height: 1;
	}
	.color-swatch {
	  display: block;
	  width: 16px;
	  height: 3px;
	  border-radius: 2px;
	  background: #e74c3c;
	}
	.editor-color-input {
	  position: absolute;
	  opacity: 0;
	  width: 0;
	  height: 0;
	  pointer-events: none;
	}
  `;
	document.head.appendChild(style);

	// Final initialization
	updateContent();
});