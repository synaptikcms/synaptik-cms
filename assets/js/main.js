/**
 * Dorian FICHOT
 * v1.3
 * Main script for animations and interactions for any theme
 */
const t = (key) => window.CMS_LANG?.[key] ?? key;

// ======== Define code syntax highlighters object =========
const highlighters = {
	html: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		// 1. Comments (now escaped as &lt;!-- and --&gt;)
		result = result.replace(/(&lt;!--[\s\S]*?--&gt;)/g,
			'<span class="comment">$1</span>'
		);

		// 2. Tags with attributes
		result = result.replace(/(&lt;\/?)([\w][\w-]*)([^&]*?)(\/?&gt;)/g, (match, open, tagName, attrs, close) => {
			// Don't process if already highlighted
			if (attrs.includes('<span')) return match;

			// Highlight attributes (now looking for escaped quotes)
			let highlightedAttrs = attrs.replace(/\s([\w][\w-]*)=(["'])([^"']*?)\2/g,
				' <span class="attr">$1</span>=<span class="string">$2$3$2</span>'
			);

			return `${open}<span class="tag">${tagName}</span>${highlightedAttrs}${close}`;
		});

		return result;
	},

	javascript: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		// Unified marker system — comments AND strings use markers so that
		// subsequent keyword/function regexes never operate on inserted HTML.
		const jsMarkers = [];
		let idx = 0;

		const addJSMark = (html) => {
			const m = `___JSM_${idx++}___`;
			jsMarkers.push({ m, html });
			return m;
		};

		// 1. Comments first — protected by markers before string regex runs,
		//    which prevents the string regex from matching "comment" inside
		//    the class="comment" attribute of an already-inserted <span>.
		result = result.replace(/\/\*[\s\S]*?\*\//g, (match) =>
			addJSMark(`<span class="comment">${match}</span>`)
		);
		result = result.replace(/\/\/[^\n]*/g, (match) =>
			addJSMark(`<span class="comment">${match}</span>`)
		);

		// 2. Strings
		result = result.replace(/"(?:\\.|[^"\\])*"/g, (match) =>
			addJSMark(`<span class="string">${match}</span>`)
		);
		result = result.replace(/'(?:\\.|[^'\\])*'/g, (match) =>
			addJSMark(`<span class="string">${match}</span>`)
		);
		result = result.replace(/`(?:\\.|[^`\\])*`/g, (match) =>
			addJSMark(`<span class="string">${match}</span>`)
		);

		// 3. Keywords — safe to run: only real code remains, all comments and
		//    strings have been replaced by ___JSM_N___ markers.
		result = result.replace(/\b(const|let|var|function|return|if|else|for|while|do|switch|case|break|continue|class|extends|new|this|super|async|await|try|catch|finally|throw|typeof|instanceof|delete|in|of|void)\b/g,
			'<span class="keyword">$1</span>'
		);

		// 4. Booleans
		result = result.replace(/\b(true|false)\b/g,
			'<span class="boolean">$1</span>'
		);

		// 5. Null/undefined
		result = result.replace(/\b(null|undefined)\b/g,
			'<span class="null">$1</span>'
		);

		// 6. Numbers
		result = result.replace(/\b(\d+\.?\d*)\b/g,
			'<span class="number">$1</span>'
		);

		// 7. Function calls
		result = result.replace(/\b([a-zA-Z_$][\w$]*)\s*(?=\()/g,
			'<span class="function">$1</span>'
		);

		// 8. Restore all markers (comments and strings)
		jsMarkers.forEach(({ m, html }) => {
			result = result.replace(m, html);
		});

		return result;
	},

	css: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		// Comments — protected by markers so that the selector regex can never
		// match across a comment's closing </span> boundary.  Previously the
		// selector char class [^{}\/] stopped at "/" but not "<", so the "<"
		// from "</span>" was consumed as the start of a selector, and "span>"
		// bled into the next CSS selector, producing visible "*/span>" noise.
		const commentMarkers = [];
		let cidx = 0;
		result = result.replace(/(\/\*[\s\S]*?\*\/)/g, (match) => {
			const m = `___CM_${cidx++}___`;
			commentMarkers.push({ m, html: `<span class="comment">${match}</span>` });
			return m;
		});

		// Selectors — \n excluded so a comment marker on its own line cannot
		// bleed into the next line's selector.  Guard also checks for markers.
		result = result.replace(/([^{}\/\n]+?)(\s*\{)/g, (match, selector, brace) => {
			if (selector.includes('</span>') || selector.includes('___')) return match;
			return `<span class="selector">${selector}</span>${brace}`;
		});

		// Properties with markers for values
		const valueMarkers = [];
		let idx = 0;

		result = result.replace(/([a-zA-Z-]+)(\s*)(:)(\s*)([^;}\n]+)(;?)/g, (match, prop, sp1, colon, sp2, val, semi) => {
			if (prop.includes('</span>') || prop.includes('___')) return match;
			const marker = `___VAL_${idx}___`;
			valueMarkers.push({ marker, html: `<span class="value">${val}</span>` });
			idx++;
			return `<span class="property">${prop}</span>${sp1}${colon}${sp2}${marker}${semi}`;
		});

		// Restore value markers, then comment markers
		valueMarkers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});
		commentMarkers.forEach(({ m, html }) => {
			result = result.replace(m, html);
		});

		return result;
	},

	php: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		// Use markers for all highlighted elements to prevent reprocessing
		const markers = [];
		let markerIndex = 0;

		const addMarker = (html) => {
			const marker = `___MARKER_${markerIndex}___`;
			markers.push({ marker, html });
			markerIndex++;
			return marker;
		};

		// 1. Multi-line comments
		result = result.replace(/\/\*[\s\S]*?\*\//g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);

		// 2. Single-line comments (// and #)
		result = result.replace(/\/\/[^\n]*/g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);
		result = result.replace(/#[^\n]*/g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);

		// 3. Strings (double quotes)
		result = result.replace(/"(?:\\.|[^"\\])*"/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// 4. Strings (single quotes)
		result = result.replace(/'(?:\\.|[^'\\])*'/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// 5. Keywords
		result = result.replace(/\b(function|return|if|else|elseif|foreach|for|while|do|echo|print|class|public|private|protected|static|final|abstract|interface|trait|extends|implements|new|clone|namespace|use|require|require_once|include|include_once|isset|empty|array|as|switch|case|break|continue|default|die|exit|try|catch|finally|throw)\b/g,
			(match) => addMarker(`<span class="keyword">${match}</span>`)
		);

		// 6. Booleans
		result = result.replace(/\b(true|false|TRUE|FALSE)\b/g,
			(match) => addMarker(`<span class="boolean">${match}</span>`)
		);

		// 7. Null
		result = result.replace(/\b(null|NULL)\b/g,
			(match) => addMarker(`<span class="null">${match}</span>`)
		);

		// 8. Variables
		result = result.replace(/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/g, (match) =>
			addMarker(`<span class="variable">${match}</span>`)
		);

		// 9. Numbers
		result = result.replace(/\b\d+\.?\d*\b/g, (match) =>
			addMarker(`<span class="number">${match}</span>`)
		);

		// 10. Function calls — guard skips ___MARKER_N___ identifiers: they start
		//     with "_" (which is in [a-zA-Z_]) and can be followed by \s*( when
		//     the original code was e.g. `if (...` → marker + " (" triggers the
		//     lookahead, swallowing the marker into a spurious function span and
		//     preventing its restoration later.
		result = result.replace(/\b([a-zA-Z_][\w]*)\s*(?=\()/g, (match, name) => {
			if (/^___MARKER_\d+___$/.test(name)) return match;
			return addMarker(`<span class="function">${match}</span>`);
		});

		// Restore all markers
		markers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});

		return result;
	},

	python: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		const markers = [];
		let markerIndex = 0;

		const addMarker = (html) => {
			const marker = `___MARKER_${markerIndex}___`;
			markers.push({ marker, html });
			markerIndex++;
			return marker;
		};

		// Comments
		result = result.replace(/#[^\n]*/g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);

		// Triple-quoted strings
		result = result.replace(/"""[\s\S]*?"""/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);
		result = result.replace(/'''[\s\S]*?'''/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Regular strings
		result = result.replace(/"(?:\\.|[^"\\])*"/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);
		result = result.replace(/'(?:\\.|[^'\\])*'/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// F-strings
		result = result.replace(/f"(?:\\.|[^"\\])*"/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);
		result = result.replace(/f'(?:\\.|[^'\\])*'/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Keywords
		result = result.replace(/\b(def|class|import|from|as|if|elif|else|for|while|in|return|yield|break|continue|pass|raise|try|except|finally|with|lambda|and|or|not|is|None|True|False|async|await|global|nonlocal|assert|del)\b/g,
			(match) => addMarker(`<span class="keyword">${match}</span>`)
		);

		// Built-in functions
		result = result.replace(/\b(print|len|range|int|str|float|list|dict|set|tuple|type|isinstance|open|input|enumerate|zip|map|filter|sorted|sum|min|max|abs|round|all|any)\b(?=\s*\()/g,
			(match) => addMarker(`<span class="function">${match}</span>`)
		);

		// Decorators
		result = result.replace(/@[\w.]+/g, (match) =>
			addMarker(`<span class="function">${match}</span>`)
		);

		// Numbers
		result = result.replace(/\b\d+\.?\d*\b/g, (match) =>
			addMarker(`<span class="number">${match}</span>`)
		);

		// Restore markers
		markers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});

		return result;
	},

	sql: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		const markers = [];
		let markerIndex = 0;

		const addMarker = (html) => {
			const marker = `___MARKER_${markerIndex}___`;
			markers.push({ marker, html });
			markerIndex++;
			return marker;
		};

		// Comments
		result = result.replace(/--[^\n]*/g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);
		result = result.replace(/\/\*[\s\S]*?\*\//g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);

		// Strings
		result = result.replace(/'(?:''|[^'])*'/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Keywords (case insensitive)
		result = result.replace(/\b(SELECT|FROM|WHERE|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TABLE|DATABASE|INDEX|VIEW|JOIN|INNER|LEFT|RIGHT|FULL|OUTER|ON|AS|AND|OR|NOT|NULL|IS|IN|BETWEEN|LIKE|ORDER|BY|GROUP|HAVING|LIMIT|OFFSET|DISTINCT|COUNT|SUM|AVG|MAX|MIN|UNION|ALL|EXISTS|CASE|WHEN|THEN|ELSE|END|PRIMARY|KEY|FOREIGN|REFERENCES|CONSTRAINT|UNIQUE|DEFAULT|AUTO_INCREMENT|CASCADE|SET|VALUES|INTO)\b/gi,
			(match) => addMarker(`<span class="keyword">${match}</span>`)
		);

		// Data types
		result = result.replace(/\b(INT|INTEGER|VARCHAR|CHAR|TEXT|DATE|DATETIME|TIMESTAMP|BOOLEAN|DECIMAL|FLOAT|DOUBLE|BLOB|ENUM)\b/gi,
			(match) => addMarker(`<span class="type">${match}</span>`)
		);

		// Numbers
		result = result.replace(/\b\d+\.?\d*\b/g, (match) =>
			addMarker(`<span class="number">${match}</span>`)
		);

		// Functions
		result = result.replace(/\b([A-Z_]+)\s*(?=\()/g, (match) =>
			addMarker(`<span class="function">${match}</span>`)
		);

		// Restore markers
		markers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});

		return result;
	},

	bash: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		const markers = [];
		let markerIndex = 0;

		const addMarker = (html) => {
			const marker = `___MARKER_${markerIndex}___`;
			markers.push({ marker, html });
			markerIndex++;
			return marker;
		};

		// Comments
		result = result.replace(/#[^\n]*/g, (match) =>
			addMarker(`<span class="comment">${match}</span>`)
		);

		// Strings (double quotes)
		result = result.replace(/"(?:\\.|[^"\\])*"/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Strings (single quotes)
		result = result.replace(/'(?:\\.|[^'\\])*'/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Keywords and built-ins
		result = result.replace(/\b(if|then|else|elif|fi|for|while|do|done|case|esac|function|return|exit|break|continue|in|select|until)\b/g,
			(match) => addMarker(`<span class="keyword">${match}</span>`)
		);

		// Common commands
		result = result.replace(/\b(echo|cd|ls|pwd|mkdir|rm|cp|mv|cat|grep|sed|awk|find|chmod|chown|sudo|export|source|alias)\b/g,
			(match) => addMarker(`<span class="function">${match}</span>`)
		);

		// Variables
		result = result.replace(/\$\{?[a-zA-Z_][a-zA-Z0-9_]*\}?/g, (match) =>
			addMarker(`<span class="variable">${match}</span>`)
		);

		// Special variables
		result = result.replace(/\$[@*#?$!0-9]/g, (match) =>
			addMarker(`<span class="variable">${match}</span>`)
		);

		// Numbers
		result = result.replace(/\b\d+\b/g, (match) =>
			addMarker(`<span class="number">${match}</span>`)
		);

		// Operators and redirects
		result = result.replace(/[|&;><]+/g, (match) =>
			addMarker(`<span class="operator">${match}</span>`)
		);

		// Restore markers
		markers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});

		return result;
	},

	json: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		const markers = [];
		let markerIndex = 0;

		const addMarker = (html) => {
			const marker = `___MARKER_${markerIndex}___`;
			markers.push({ marker, html });
			markerIndex++;
			return marker;
		};

		// Strings (including property names)
		result = result.replace(/"(?:\\.|[^"\\])*"/g, (match) =>
			addMarker(`<span class="string">${match}</span>`)
		);

		// Numbers
		result = result.replace(/\b-?\d+\.?\d*(?:[eE][+-]?\d+)?\b/g, (match) =>
			addMarker(`<span class="number">${match}</span>`)
		);

		// Booleans
		result = result.replace(/\b(true|false)\b/g,
			(match) => addMarker(`<span class="boolean">${match}</span>`)
		);

		// Null
		result = result.replace(/\bnull\b/g,
			(match) => addMarker(`<span class="null">${match}</span>`)
		);

		// Restore markers
		markers.forEach(({ marker, html }) => {
			result = result.replace(marker, html);
		});

		return result;
	},

	xml: (code) => {
		let result = code
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');

		// Comments
		result = result.replace(/(&lt;!--[\s\S]*?--&gt;)/g,
			'<span class="comment">$1</span>'
		);

		// CDATA sections
		result = result.replace(/(&lt;!\[CDATA\[[\s\S]*?\]\]&gt;)/g,
			'<span class="string">$1</span>'
		);

		// Processing instructions
		result = result.replace(/(&lt;\?[\s\S]*?\?&gt;)/g,
			'<span class="comment">$1</span>'
		);

		// DOCTYPE declarations
		result = result.replace(/(&lt;!DOCTYPE[\s\S]*?&gt;)/g,
			'<span class="comment">$1</span>'
		);

		// Tags with attributes
		result = result.replace(/(&lt;\/?)([\w][\w:-]*)([^&]*?)(\/?&gt;)/g, (match, open, tagName, attrs, close) => {
			if (attrs.includes('<span')) return match;

			// Highlight attributes
			let highlightedAttrs = attrs.replace(/\s([\w][\w:-]*)=(["'])([^"']*?)\2/g,
				' <span class="attr">$1</span>=<span class="string">$2$3$2</span>'
			);

			return `${open}<span class="tag">${tagName}</span>${highlightedAttrs}${close}`;
		});

		return result;
	}
};

// ===== SEARCH FUNCTIONALITY =====
(function() {
	function sanitizeSlug(text) {
		if (!text) return '';

		// Transliterate accented characters to ASCII equivalents
		var accentMap = {
			'à':'a','á':'a','â':'a','ã':'a','ä':'a','å':'a',
			'ç':'c',
			'è':'e','é':'e','ê':'e','ë':'e',
			'ì':'i','í':'i','î':'i','ï':'i',
			'ð':'d','ñ':'n',
			'ò':'o','ó':'o','ô':'o','õ':'o','ö':'o','ø':'o',
			'ù':'u','ú':'u','û':'u','ü':'u',
			'ý':'y','ÿ':'y','þ':'th','ß':'ss','œ':'oe','æ':'ae'
		};

		// Convert to lowercase and replace non-alphanumeric characters with hyphens
		return text.toLowerCase()
			.replace(/[àáâãäåçèéêëìíîïðñòóôõöøùúûüýþßÿœæ]/g, function(c) {
				return accentMap[c] || c;
			})
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
	}

	document.addEventListener('DOMContentLoaded', function() {
		// Add the search icon only if not already present AND not disabled in settings.
		// Note: we intentionally do NOT return early here — the overlay, CSS,
		// and keyboard shortcut (Ctrl+K) must always be initialised regardless
		// of whether the visible icon is shown.
		if (!document.querySelector('.search-icon') &&
			(typeof window.appSettings === 'undefined' || window.appSettings.showSearchIcon !== false)) {
			const navMenu = document.querySelector('nav ul');
			if (navMenu) {
				const searchListItem = document.createElement('li');
				searchListItem.classList.add('search-icon');
				searchListItem.innerHTML = '<a href="#" id="search-toggle"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></a>';
				navMenu.appendChild(searchListItem);
			}
		}

		// if (!document.getElementById('search-css')) {
		// 	const cssLink = document.createElement('link');
		// 	cssLink.id = 'search-css';
		// 	cssLink.rel = 'stylesheet';
		// 	cssLink.href = document.location.origin +
		// 		document.location.pathname.substring(0, document.location.pathname.indexOf('/', 1)) +
		// 		'/css/search.css';
		// 	document.head.appendChild(cssLink);
		// }

		// ── Build search overlay HTML ──────────────────────────────────────────
		// Layout: flex column overlay
		//   .search-top  → fixed-height bar (input + filters), vertically centred
		//                  by default, slides to top via .has-results on the overlay
		//   .search-results-panel → flex:1 scrollable area, invisible until results arrive
		if (!document.getElementById('search-overlay')) {
			const searchOverlay = document.createElement('div');
			searchOverlay.id = 'search-overlay';
			searchOverlay.classList.add('search-overlay');

			// Start hidden; JS transitions handle reveal
			searchOverlay.style.display = 'none';
			searchOverlay.style.opacity = '0';
			searchOverlay.style.visibility = 'hidden';

			searchOverlay.innerHTML = `
		<button id="close-search" class="close-btn">×</button>
		<div class="search-bar">
		  <div class="search-input-container">
			<input type="text" id="search-input" placeholder="${t('search_placeholder')}">
			<button class="search-clear-btn">×</button>
		  </div>
		  <div class="search-options">
			<label>
			  <input type="checkbox" id="search-in-content">
			  ${t('search_in_content')}
			</label>
			<label>
			  <input type="checkbox" id="search-articles" checked>
			  ${t('search_filter_articles')}
			</label>
			<label>
			  <input type="checkbox" id="search-pages" checked>
			  ${t('search_filter_pages')}
			</label>
			<label>
			  <input type="checkbox" id="search-projects" checked>
			  ${t('search_filter_projects')}
			</label>
		  </div>
		</div>
		<div class="search-results" id="search-results">
		  <div class="search-loading" style="display: none;">${t('search_loading')}</div>
		  <div class="search-results-content"></div>
		</div>
	  `;
			document.body.appendChild(searchOverlay);
		}

		// ── References ────────────────────────────────────────────────────────
		const searchToggle  = document.getElementById('search-toggle');
		const closeSearch   = document.getElementById('close-search');
		const searchInput   = document.getElementById('search-input');
		const clearButton   = document.querySelector('.search-clear-btn');
		const searchOverlay = document.getElementById('search-overlay');
		const searchLoading = document.querySelector('.search-loading');
		const searchInContent = document.getElementById('search-in-content');
		const searchArticles  = document.getElementById('search-articles');
		const searchPages     = document.getElementById('search-pages');
		const searchProjects  = document.getElementById('search-projects');

		// Debounce helper to limit API call frequency
		function debounce(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}

		// ── Helper: reset results state (input cleared or overlay closed) ─────
		function clearResults() {
			const content = document.querySelector('.search-results-content');
			if (content) content.innerHTML = '';
			if (searchOverlay) searchOverlay.classList.remove('has-results');
		}

		// ── Close button ──────────────────────────────────────────────────────
		if (closeSearch) {
			closeSearch.addEventListener('click', closeSearchOverlay);
		}

		// Close when clicking on the backdrop (outside .search-top and results panel)
		if (searchOverlay) {
			searchOverlay.addEventListener('click', function(e) {
				if (e.target === searchOverlay) closeSearchOverlay();
			});
		}

		// ── Clear button ──────────────────────────────────────────────────────
		if (clearButton && searchInput) {
			clearButton.addEventListener('click', function() {
				searchInput.value = '';
				clearButton.style.display = 'none';
				clearResults();
			});
		}

		// ── Input handler ─────────────────────────────────────────────────────
		if (searchInput && clearButton) {
			const debouncedSearch = debounce(function(query) {
				if (query.trim() !== '') {
					performSearch(query.trim());
				} else {
					clearResults();
				}
			}, 300);

			searchInput.addEventListener('input', function() {
				if (this.value.trim() !== '') {
					clearButton.style.display = 'block';
					debouncedSearch(this.value);
				} else {
					clearButton.style.display = 'none';
					clearResults();
				}
			});
		}

		// ── Filter checkboxes re-trigger search ───────────────────────────────
		[searchInContent, searchArticles, searchPages, searchProjects].forEach(filter => {
			if (filter) {
				filter.addEventListener('change', function() {
					if (searchInput && searchInput.value.trim() !== '') {
						performSearch(searchInput.value.trim());
					}
				});
			}
		});

		// ── Resolve CMS base URL ──────────────────────────────────────────────
		function getBaseUrl() {
			const path = window.location.pathname;
			const baseUrl = window.location.protocol + '//' + window.location.host;
			let rootPath = '';

			// Special handling for category URLs
			if (path.includes('/category/')) {
				rootPath = path.substring(0, path.indexOf('/category/'));
			} else if (path.match(/\/[^\/]+\/[^\/]+\/?$/)) {
				// Handle URLs like /category-name/article-name/
				const firstSlash = path.indexOf('/', 1);
				if (firstSlash !== -1) {
					rootPath = path.substring(0, firstSlash);
				}
			} else {
				const contentPatterns = ['/article/', '/page/', '/project/', '/articles/', '/pages/', '/projects/'];
				let foundPattern = false;

				for (const pattern of contentPatterns) {
					if (path.includes(pattern)) {
						rootPath = path.substring(0, path.indexOf(pattern));
						foundPattern = true;
						break;
					}
				}

				if (!foundPattern) {
					rootPath = path.includes('.php') ? path.substring(0, path.lastIndexOf('/')) : path;
				}
			}

			if (rootPath && !rootPath.startsWith('/')) rootPath = '/' + rootPath;
			if (rootPath && !rootPath.endsWith('/'))   rootPath += '/';

			return baseUrl + rootPath;
		}

		// ── Resolve image URL ─────────────────────────────────────────────────
		function getCorrectImageUrl(imagePath) {
			if (imagePath.startsWith('http') || imagePath.startsWith('/')) return imagePath;
			if (!imagePath.startsWith('files/')) imagePath = 'files/' + imagePath;
			return (window._searchBaseUrl || getBaseUrl()) + imagePath;
		}

		// ── Fetch and display search results ──────────────────────────────────
		function performSearch(query) {
			if (!query || query.trim() === '') return;

			const inContent  = searchInContent?.checked  || false;
			const inArticles = searchArticles?.checked   || false;
			const inPages    = searchPages?.checked      || false;
			const inProjects = searchProjects?.checked   || false;

			if (searchLoading) searchLoading.style.display = 'block';

			let apiUrl = `search.php?q=${encodeURIComponent(query)}`
			           + `&content=${inContent}`
			           + `&articles=${inArticles}`
			           + `&pages=${inPages}`
			           + `&projects=${inProjects}`;

			fetch(apiUrl)
				.then(response => {
					if (!response.ok) throw new Error('Search request failed');
					return response.json();
				})
				.then(data => {
					if (searchLoading) searchLoading.style.display = 'none';
					window._searchBaseUrl = data.base_url;
					displaySearchResults(data.results, query);
				})
				.catch(error => {
					console.error('Error fetching search results:', error);
					if (searchLoading) searchLoading.style.display = 'none';
					const searchResults = document.querySelector('.search-results-content');
					if (searchResults) {
						searchResults.innerHTML = `<div class="error">${t('search_error_generic')}</div>`;
					}
					// Even on error, show the panel so the error message is visible
					if (searchOverlay) searchOverlay.classList.add('has-results');
				});
		}

		// ── Render results into .search-results-content ───────────────────────
		function displaySearchResults(results, query) {
			const searchResults = document.querySelector('.search-results-content');
			if (!searchResults) return;

			searchResults.innerHTML = '';

			// No results: show message but keep top bar centred
			if (!results || results.length === 0) {
				searchResults.innerHTML = `<div class="no-results">${t('search_no_results')}</div>`;
				// Still slide up so the no-results message is visible
				if (searchOverlay) searchOverlay.classList.add('has-results');
				return;
			}

			// Results found: slide top bar to top, reveal scrollable panel
			if (searchOverlay) searchOverlay.classList.add('has-results');

			// Helper: wrap matched term in .highlight span
			function highlightText(text, query) {
				if (!query || query.trim() === '' || !text) return text;
				const normalizedText  = text.toLowerCase();
				const normalizedQuery = query.toLowerCase();
				if (normalizedText.includes(normalizedQuery)) {
					const regex = new RegExp(`(${escapeRegExp(normalizedQuery)})`, 'gi');
					return text.replace(regex, '<span class="highlight">$1</span>');
				}
				return text;
			}

			function escapeRegExp(string) {
				return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			}

			// Results counter
			const resultsCounter = document.createElement('div');
			resultsCounter.className = 'results-count';
			resultsCounter.textContent = results.length === 1
				? t('search_found_one')
				: t('search_found_many').replace('%d', results.length);
			searchResults.appendChild(resultsCounter);

			// Group by content type
			const groupedResults = {};
			results.forEach(result => {
				if (!groupedResults[result.type]) groupedResults[result.type] = [];
				groupedResults[result.type].push(result);
			});

			// Render each type group
			Object.keys(groupedResults).forEach(type => {
				// Localized plural label (e.g. "Articles", "Projets")
				const typeLabelPlural = t('url_slug_' + type + 's') !== 'url_slug_' + type + 's'
					? t('url_slug_' + type + 's').charAt(0).toUpperCase() + t('url_slug_' + type + 's').slice(1)
					: type.charAt(0).toUpperCase() + type.slice(1) + 's';

				// Localized singular label for the type badge
				const typeLabelSingular = t('url_slug_' + type) !== 'url_slug_' + type
					? t('url_slug_' + type)
					: type;

				const typeHeading = document.createElement('h1');
				typeHeading.className = 'result-type-heading';
				typeHeading.textContent = typeLabelPlural;
				searchResults.appendChild(typeHeading);

				const resultsContainer = document.createElement('div');
				resultsContainer.className = 'results-group';

				groupedResults[type].forEach(result => {
					const resultItem = document.createElement('div');
					resultItem.className = 'search-result-item';

					let resultHTML = '<div class="result-content">';

					// Type badge
					resultHTML += `<div class="result-type ${result.type}">${typeLabelSingular}</div>`;

					// Use pre-built URL from search.php (cleanUrl() server-side).
					// Fallback to manual reconstruction for backward compatibility.
					let contentUrl = result.url || (() => {
						const typeSlug = result.type;
						const slug     = result.slug    ? result.slug.toLowerCase()     : '';
						const catPath  = result.cat_path ? result.cat_path.toLowerCase() : '';
						if (typeSlug === 'page') {
							return `${getBaseUrl()}${slug}/`;
						} else if (typeSlug === 'article' && catPath) {
							return `${getBaseUrl()}${catPath}/${slug}/`;
						} else if (typeSlug === 'project' && catPath) {
							return `${getBaseUrl()}${typeSlug}/${catPath}/${slug}/`;
						} else {
							return `${getBaseUrl()}${typeSlug}/${slug}/`;
						}
					})();

					// Title with search term highlighted
					resultHTML += `<h2 class="result-title-heading"><a href="${contentUrl}">${highlightText(result.title, query)}</a></h2>`;

					// Excerpt (optional)
					if (result.excerpt) {
						resultHTML += `<p class="result-excerpt">${highlightText(result.excerpt, query)}</p>`;
					}

					resultHTML += '</div>';

					// Thumbnail (optional)
					if (result.image) {
						const imageUrl = getCorrectImageUrl(result.image);
						resultHTML = `<div class="result-image"><img src="${imageUrl}" alt="${result.title}"></div>` + resultHTML;
						resultItem.classList.add('has-image');
					}

					resultItem.innerHTML = resultHTML;
					resultsContainer.appendChild(resultItem);
				});

				searchResults.appendChild(resultsContainer);
			});
		}

		// ── Open / close helpers ──────────────────────────────────────────────
		// Work whether or not the visible search icon is present in the nav.
		function openSearch() {
			if (!searchOverlay) return;
			document.body.style.overflow = 'hidden';
			searchOverlay.style.display = 'flex';
			setTimeout(() => {
				searchOverlay.classList.add('active');
				searchOverlay.style.opacity = '1';
				searchOverlay.style.visibility = 'visible';
			}, 10);
			// Focus input after CSS transition completes (~400ms)
			setTimeout(() => {
				if (searchInput) searchInput.focus();
			}, 420);
		}

		function closeSearchOverlay() {
			if (!searchOverlay) return;
			document.body.style.overflow = '';
			searchOverlay.classList.remove('active');
			searchOverlay.classList.remove('has-results'); // reset to centred state
			searchOverlay.style.opacity = '0';
			searchOverlay.style.visibility = 'hidden';
			setTimeout(() => { searchOverlay.style.display = 'none'; }, 300);
		}

		// Wire visible toggle button (when present)
		if (searchToggle && searchOverlay) {
			searchToggle.addEventListener('click', function(e) {
				e.preventDefault();
				searchOverlay.classList.contains('active') ? closeSearchOverlay() : openSearch();
			});
		}

		// Keyboard shortcuts: Ctrl/Cmd+K to open, Escape to close
		document.addEventListener('keydown', function(e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
				e.preventDefault();
				searchOverlay.classList.contains('active') ? closeSearchOverlay() : openSearch();
			}
			if (e.key === 'Escape' && searchOverlay && searchOverlay.classList.contains('active')) {
				closeSearchOverlay();
			}
		});
	});
})();

// ===== LIGHTBOX FUNCTIONALITY =====
// lightbox.css is served by synaptikCSS.php, which render_header_scripts()
// injects automatically in <head>. No runtime CSS injection needed here.
(function() {
	document.addEventListener('DOMContentLoaded', function() {

		// Enhanced lightbox implementation
		const lightbox = {
			init: function() {
				// Get all images with data-lightbox attribute
				const galleryImages = document.querySelectorAll('a[data-lightbox], .gallery-image a, .gallery-grid a, .gallery-masonry a, .justified-gallery a, .masonry-item a');

				// Exit if no gallery images found
				if (!galleryImages.length) return;

				// Create lightbox container if it doesn't exist
				this.createLightboxContainer();

				// Add click event listeners to each gallery image
				galleryImages.forEach(image => {
					image.addEventListener('click', (e) => {
						e.preventDefault();
						this.openLightbox(image.href, image.getAttribute('data-title') || '');
					});
				});

				// Add keyboard navigation
				document.addEventListener('keydown', (e) => {
					if (!document.getElementById('lightbox-container').classList.contains('active')) return;

					if (e.key === 'Escape') {
						this.closeLightbox();
					} else if (e.key === 'ArrowRight') {
						this.nextImage();
					} else if (e.key === 'ArrowLeft') {
						this.prevImage();
					}
				});
			},

			createLightboxContainer: function() {
				// If the lightbox container already exists, don't create it again
				if (document.getElementById('lightbox-container')) return;

				const lightboxContainer = document.createElement('div');
				lightboxContainer.id = 'lightbox-container';
				lightboxContainer.innerHTML = `
		  <div id="lightbox-overlay"></div>
		  <div id="lightbox-content">
			<button id="lightbox-close">&times;</button>
			<div id="lightbox-image-container">
			  <img id="lightbox-image" src="" alt="">
			</div>
			<div id="lightbox-caption"></div>
			<button id="lightbox-prev">&#10094;</button>
			<button id="lightbox-next">&#10095;</button>
		  </div>
		`;

				document.body.appendChild(lightboxContainer);

				// Add event listeners
				document.getElementById('lightbox-close').addEventListener('click', () => this.closeLightbox());
				document.getElementById('lightbox-overlay').addEventListener('click', () => this.closeLightbox());
				document.getElementById('lightbox-prev').addEventListener('click', () => this.prevImage());
				document.getElementById('lightbox-next').addEventListener('click', () => this.nextImage());

			},

			openLightbox: function(src, caption) {
				const lightbox = document.getElementById('lightbox-container');
				const lightboxImage = document.getElementById('lightbox-image');
				const lightboxCaption = document.getElementById('lightbox-caption');
				const imageContainer = document.getElementById('lightbox-image-container');

				// Store current gallery and image index
				this.currentGallery = this.getCurrentGallery(src);
				this.currentIndex = this.getCurrentIndex(src);

				// Show the lightbox without image (container becomes visible)
				lightbox.classList.add('active');

				// Preload image
				const img = new Image();
				img.onload = () => {
					// Set image and caption once loaded
					lightboxImage.src = src;
					lightboxCaption.textContent = caption;

					// Fade in the image
					setTimeout(() => {
						imageContainer.style.opacity = '1';
					}, 50);
				};
				img.src = src;

				// Disable scrolling on the body
				document.body.style.overflow = 'hidden';
			},

			closeLightbox: function() {
				const lightbox = document.getElementById('lightbox-container');
				const imageContainer = document.getElementById('lightbox-image-container');

				// Fade out image first
				imageContainer.style.opacity = '0';

				// Then hide lightbox after transition
				setTimeout(() => {
					lightbox.classList.remove('active');

					// Re-enable scrolling on the body
					document.body.style.overflow = '';
				}, 300);
			},

			getCurrentGallery: function(src) {
				// Find images in the current gallery - first check data-lightbox attribute
				let gallery = [];
				const currentImage = document.querySelector(`a[href="${src}"][data-lightbox]`);

				if (currentImage) {
					const galleryName = currentImage.getAttribute('data-lightbox');
					gallery = Array.from(document.querySelectorAll(`a[data-lightbox="${galleryName}"]`));
				} else {
					// If not found with data-lightbox, check for other gallery types
					const galleryTypes = [
						'.gallery-image a',
						'.gallery-grid a',
						'.gallery-masonry a',
						'.justified-gallery a',
						'.masonry-item a'
					];

					for (const selector of galleryTypes) {
						const items = Array.from(document.querySelectorAll(selector));
						if (items.some(item => item.href === src)) {
							gallery = items;
							break;
						}
					}
				}

				return gallery;
			},

			getCurrentIndex: function(src) {
				if (!this.currentGallery) {
					this.currentGallery = this.getCurrentGallery(src);
				}

				return this.currentGallery.findIndex(img => img.href === src);
			},

			transitionToImage: function(targetSrc, targetCaption) {
				const lightboxImage = document.getElementById('lightbox-image');
				const lightboxCaption = document.getElementById('lightbox-caption');

				// Fade out current image
				lightboxImage.classList.add('transitioning');

				// Wait for fade out to complete
				setTimeout(() => {
					// Update src and caption
					lightboxImage.src = targetSrc;
					lightboxCaption.textContent = targetCaption;

					// Force browser to recognize the new image before fading in
					setTimeout(() => {
						lightboxImage.classList.remove('transitioning');
					}, 50);
				}, 300);
			},

			nextImage: function() {
				if (!this.currentGallery || !this.currentGallery.length) return;

				this.currentIndex = (this.currentIndex + 1) % this.currentGallery.length;
				const nextImage = this.currentGallery[this.currentIndex];

				this.transitionToImage(
					nextImage.href,
					nextImage.getAttribute('data-title') || ''
				);
			},

			prevImage: function() {
				if (!this.currentGallery || !this.currentGallery.length) return;

				this.currentIndex = (this.currentIndex - 1 + this.currentGallery.length) % this.currentGallery.length;
				const prevImage = this.currentGallery[this.currentIndex];

				this.transitionToImage(
					prevImage.href,
					prevImage.getAttribute('data-title') || ''
				);
			}
		};

		// Initialize lightbox
		lightbox.init();

		// Expose lightbox API globally if needed
		window.lightbox = lightbox;
	});
})();

// ===== COLLAPSIBLES, TABS & CUSTOM COLORS =====
(function () {
	document.addEventListener('DOMContentLoaded', function () {

		/* Toggle collapsibles */
		document.addEventListener('click', function (e) {
			var head = e.target.closest('.c-col > .c-head');
			if (!head) return;
			head.parentElement.classList.toggle('open');
		});

		/* Tout ouvrir / tout fermer via data-c-toggle="open|close" */
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-c-toggle]');
			if (!btn) return;
			var root = btn.closest('[data-c-scope]');
			if (!root) return;
			var open = btn.dataset.cToggle === 'open';
			root.querySelectorAll('.c-col').forEach(function (el) {
				el.classList.toggle('open', open);
			});
		});

		/* Couleur personnalisée via data-color */
		document.querySelectorAll('.c-col[data-color]').forEach(function (el) {
			var c = el.getAttribute('data-color');
			el.style.setProperty('--c-color', c);
			el.style.setProperty('--c-bg', c + '1A');
		});

		/* Tab groups */
		document.querySelectorAll('.tab-group').forEach(function (group) {
			if (group.dataset.tabsBuilt) return;

			var panels = Array.prototype.slice.call(group.children).filter(function (el) {
				return el.classList && el.classList.contains('c-col');
			});
			if (panels.length === 0) return;

			var bar = document.createElement('div');
			bar.className = 'tabs';

			panels.forEach(function (panel, idx) {
				var titleEl = panel.querySelector(':scope > .c-head > .c-title');
				var iconEl  = panel.querySelector(':scope > .c-head > .c-icon');
				var color   = panel.getAttribute('data-color') || '';

				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'tab-btn' + (idx === 0 ? ' active' : '');
				btn.dataset.tabIndex = String(idx);
				if (color) btn.style.setProperty('--tab-color', color);

				var inner = '';
				if (iconEl) inner += '<span class="t-icon">' + iconEl.innerHTML + '</span>';
				inner += '<span class="t-label">' + (titleEl ? titleEl.textContent : (t('tab') + ' ' + (idx + 1))) + '</span>';
				btn.innerHTML = inner;

				btn.addEventListener('click', function () {
					bar.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
					btn.classList.add('active');
					panels.forEach(function (p, i) { p.classList.toggle('tab-active', i === idx); });
				});

				bar.appendChild(btn);
			});

			panels.forEach(function (p, i) { p.classList.toggle('tab-active', i === 0); });
			group.classList.add('tab-initialized');
			group.insertBefore(bar, group.firstChild);
			group.dataset.tabsBuilt = '1';
		});

		/* Boutons tout ouvrir / tout fermer */
		document.querySelectorAll('.tab-group').forEach(function (group) {
			if (!group.querySelector('.c-col')) return;
		
			group.dataset.cScope = '1';
		
			var toggleBar = document.createElement('div');
			toggleBar.className = 'c-toggle-bar';
			toggleBar.innerHTML =
				'<button type="button" data-c-toggle="open">⊕ ' + t('expand_all') + '</button>' +
				'<button type="button" data-c-toggle="close">⊖ ' + t('collapse_all') + '</button>';
		
			var tabsBar = group.querySelector(':scope > .tabs');
			if (tabsBar) {
				tabsBar.insertAdjacentElement('afterend', toggleBar);
			} else {
				group.insertBefore(toggleBar, group.firstChild);
			}
		});

	});
})();

// ===== PAGE TRANSITIONS AND CODE SYNTAX =====
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		// Add fade-in class to main content
		const mainContent = document.querySelector('main');
		if (mainContent) {
			mainContent.classList.add('fade-in');
		}

		// Add smooth transitions to pagination links
		const paginationLinks = document.querySelectorAll('.pagination a');
		paginationLinks.forEach(link => {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				const href = this.getAttribute('href');

				// Fade out main content before navigation
				if (mainContent) {
					mainContent.style.opacity = 0;

					// Wait for transition to complete before navigating
					setTimeout(() => {
						window.location.href = href;
					}, 300);
				} else {
					window.location.href = href;
				}
			});
		});

		// ======= Apply CODE syntax highlighting ======
		document.querySelectorAll('pre[class*="language-"] code, pre code[class*="language-"]').forEach(block => {
			if (block.querySelector('span')) {
				return;
			}

			const pre = block.closest('pre');
			const lang = (block.className.match(/language-(\w+)/) ||
				pre.className.match(/language-(\w+)/))?.[1];

			if (lang && highlighters[lang]) {
				let code = block.textContent;

				// Remove leading/trailing empty lines and normalize indentation
				const lines = code.split('\n');
				while (lines.length && !lines[0].trim()) lines.shift();
				while (lines.length && !lines[lines.length - 1].trim()) lines.pop();

				// Find minimum indentation
				const minIndent = lines
					.filter(line => line.trim())
					.reduce((min, line) => {
						const indent = line.match(/^\s*/)[0].length;
						return Math.min(min, indent);
					}, Infinity);

				// Remove minimum indentation from all lines
				if (minIndent > 0 && minIndent !== Infinity) {
					code = lines.map(line => line.slice(minIndent)).join('\n');
				} else {
					code = lines.join('\n');
				}

				block.innerHTML = highlighters[lang](code);
			}
		});
	});
})();