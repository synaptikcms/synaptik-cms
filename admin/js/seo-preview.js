// SEO Preview and Score — modern 2025 algorithm
// Scoring breakdown (100 pts total):
//   Meta title        15 pts
//   Meta description  20 pts
//   Content length    20 pts
//   Content structure 10 pts  (headings + images with alt text)
//   Keywords          25 pts
//   OG / Social       10 pts

document.addEventListener('DOMContentLoaded', function() {
	const t = window.t || ((k, fb) => window.CMS_LANG?.[k] ?? fb ?? k);

	const previewUrlElement = document.getElementById('preview_url');
	const siteUrl = previewUrlElement ? previewUrlElement.getAttribute('data-site-url') : '';

	// ─── Helpers ────────────────────────────────────────────────────────────────

	function getContentType() {
		if (previewUrlElement && previewUrlElement.getAttribute('data-content-type')) {
			return previewUrlElement.getAttribute('data-content-type');
		}
		const typeInput = document.querySelector('input[name="type"]');
		return typeInput ? typeInput.value : 'article';
	}

	// Transliterate accented chars and build a URL-safe slug (mirrors PHP sanitizeSlug)
	function sanitizeSlug(text) {
		if (!text) return '';
		const map = {
			'à':'a','á':'a','â':'a','ã':'a','ä':'a','å':'a','ç':'c',
			'è':'e','é':'e','ê':'e','ë':'e','ì':'i','í':'i','î':'i','ï':'i',
			'ð':'d','ñ':'n','ò':'o','ó':'o','ô':'o','õ':'o','ö':'o','ø':'o',
			'ù':'u','ú':'u','û':'u','ü':'u','ý':'y','ÿ':'y',
			'þ':'th','ß':'ss','œ':'oe','æ':'ae'
		};
		return text.toString().toLowerCase()
			.replace(/[àáâãäåçèéêëìíîïðñòóôõöøùúûüýþßÿœæ]/g, c => map[c] || c)
			.replace(/\s+/g, '-')
			.replace(/[^\w\-]+/g, '')
			.replace(/\-\-+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	function decodeHtmlEntities(html) {
		if (!html) return '';
		const txt = document.createElement('textarea');
		txt.innerHTML = html;
		return txt.value;
	}

	function stripHtml(html) {
		return html.replace(/<\/?[^>]+(>|$)/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function getWords(text) {
		return text.split(/\s+/).filter(w => w.length > 0);
	}

	// ─── Character counter ───────────────────────────────────────────────────────

	function updateCharCounter(field) {
		const maxLength = $(field).attr('maxlength');
		if (!maxLength) return;
		const len = $(field).val().length;
		const counterId = $(field).attr('id') + '_counter';
		$('#' + counterId).text(len + '/' + maxLength + ' ' + t('characters', 'chars'));
		if (len > maxLength * 0.9) {
			$('#' + counterId).removeClass('warning').addClass('error');
		} else if (len > maxLength * 0.7) {
			$('#' + counterId).removeClass('error').addClass('warning');
		} else {
			$('#' + counterId).removeClass('warning error');
		}
	}

	// ─── Google preview URL builder ──────────────────────────────────────────────

	function buildPreviewUrl(contentType, category, slug) {
		let url = siteUrl.replace(/\/$/, '');
		const catSlug = category ? sanitizeSlug(category) : '';
		if (contentType === 'article' && catSlug) {
			url += '/' + catSlug + '/' + slug + '/';
		} else if (contentType === 'project' && catSlug) {
			url += '/project/' + catSlug + '/' + slug + '/';
		} else if (contentType === 'page') {
			url += '/page/' + slug + '/';
		} else {
			url += '/' + contentType + '/' + slug + '/';
		}
		return url;
	}

	// ─── Google preview updater ──────────────────────────────────────────────────

	function updatePreview() {
		const title       = decodeHtmlEntities($('#title').val() || 'Page Title');
		const metaTitle   = decodeHtmlEntities($('#meta_title').val() || '');
		const metaDesc    = decodeHtmlEntities($('#meta_description').val() || t('preview_description_placeholder', 'Your page description will appear here...'));
		const customSlug  = $('#custom_slug').val() || '';
		const category    = $('#category').val() || '';
		const contentType = getContentType();

		$('#preview_title').text(metaTitle || title);
		$('#preview_description').text(metaDesc);

		const slug = customSlug ? sanitizeSlug(customSlug) : sanitizeSlug(title);
		$('#preview_url').text(buildPreviewUrl(contentType, category, slug || 'page'));
	}

	// ─── Content reader ──────────────────────────────────────────────────────────

	function getMainContent() {
		const editorEl = document.querySelector('.editor-content');
		if (editorEl && editorEl.isContentEditable) return editorEl.innerHTML || '';
		const textarea = document.getElementById('content');
		return textarea ? textarea.value || '' : '';
	}

	// ─── SEO score engine ────────────────────────────────────────────────────────

	function updateSeoScore() {
		const recs = [];  // { text, level } — level: 'error' | 'warning' | 'ok'
		let points = 0;

		const contentHtml = getMainContent();
		const plainText   = stripHtml(contentHtml);
		const words       = getWords(plainText);
		const wordCount   = words.length;
		const plainLower  = plainText.toLowerCase();

		// ── 1. META TITLE (15 pts) ───────────────────────────────────────────────
		// An empty meta_title is valid: the CMS auto-generates "{title} | {site_name}".
		// A custom meta_title should be 50–60 chars for optimal SERP display.
		const metaTitle      = $('#meta_title').val() || '';
		const articleTitle   = $('#title').val() || '';
		const effectiveTitle = metaTitle || articleTitle;
		const effectiveLower = effectiveTitle.toLowerCase();

		if (!effectiveTitle) {
			recs.push({ text: t('seo_rec_add_title'), level: 'error' });
		} else if (metaTitle) {
			if (metaTitle.length < 30) {
				recs.push({ text: t('seo_rec_title_short'), level: 'warning' });
				points += 7;
			} else if (metaTitle.length > 60) {
				recs.push({ text: t('seo_rec_title_long'), level: 'warning' });
				points += 10;
			} else {
				points += 15;
			}
		} else {
			// Auto-generated title — valid but not custom-optimised
			points += 10;
		}

		// ── 2. META DESCRIPTION (20 pts) ────────────────────────────────────────
		// Yoast / Google 2025 target: 120–160 chars.
		const metaDesc = $('#meta_description').val() || '';

		if (!metaDesc) {
			recs.push({ text: t('seo_rec_add_desc'), level: 'error' });
		} else if (metaDesc.length < 120) {
			recs.push({ text: t('seo_rec_desc_short'), level: 'warning' });
			points += 10;
		} else if (metaDesc.length > 160) {
			recs.push({ text: t('seo_rec_desc_long'), level: 'warning' });
			points += 12;
		} else {
			points += 20;
		}

		// ── 3. CONTENT LENGTH (20 pts) ───────────────────────────────────────────
		// 300+ words is the modern baseline. 600+ is strong for competitive topics.
		if (wordCount < 100) {
			recs.push({ text: t('seo_content_very_short').replace('%d', wordCount), level: 'error' });
			if (wordCount > 20) points += 5;
		} else if (wordCount < 300) {
			recs.push({ text: t('seo_content_short').replace('%d', wordCount), level: 'warning' });
			points += 10;
		} else if (wordCount < 600) {
			recs.push({ text: t('seo_content_good').replace('%d', wordCount), level: 'ok' });
			points += 15;
		} else {
			points += 20;
		}

		// ── 4. CONTENT STRUCTURE (10 pts) ───────────────────────────────────────
		// Headings help crawlers understand hierarchy.
		// Images with alt text improve engagement signals and accessibility.
		const hasHeadings = /<h[2-4][\s>]/i.test(contentHtml);
		const hasImages   = /<img\s/i.test(contentHtml);
		const hasAltText  = /alt\s*=\s*["'][^"']+["']/i.test(contentHtml);

		if (!hasHeadings) {
			recs.push({ text: t('seo_rec_add_headings'), level: 'warning' });
		} else {
			points += 5;
		}

		if (!hasImages) {
			recs.push({ text: t('seo_rec_add_images'), level: 'warning' });
		} else if (!hasAltText) {
			recs.push({ text: t('seo_rec_alt_text'), level: 'warning' });
			points += 2;
		} else {
			points += 5;
		}

		// ── 5. KEYWORDS (25 pts) ────────────────────────────────────────────────
		// Checks: present in title, description, content body, and early in content.
		// Flags keyword stuffing if density exceeds 3% (Google spam signal).
		const keywordsField = $('#meta_keywords').val() || '';
		const keywords = keywordsField.split(',').map(k => k.trim().toLowerCase()).filter(k => k.length > 2);

		if (keywords.length === 0) {
			recs.push({ text: t('seo_rec_add_keywords'), level: 'warning' });
		} else {
			const descLower     = metaDesc.toLowerCase();
			const first100Words = getWords(plainLower).slice(0, 100).join(' ');

			let kwInTitle   = false;
			let kwInDesc    = false;
			let kwInContent = false;
			let kwEarly     = false;
			let stuffing    = false;

			keywords.forEach(kw => {
				if (effectiveLower.includes(kw)) kwInTitle   = true;
				if (descLower.includes(kw))      kwInDesc    = true;
				if (plainLower.includes(kw))     kwInContent = true;
				if (first100Words.includes(kw))  kwEarly     = true;

				// Keyword density: flag any keyword exceeding 3% of total words
				if (wordCount > 0) {
					const escaped = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
					const hits = (plainText.match(new RegExp('\\b' + escaped + '\\b', 'gi')) || []).length;
					if ((hits / wordCount) * 100 > 3) stuffing = true;
				}
			});

			if (!kwInTitle) {
				recs.push({ text: t('seo_rec_keyword_title'), level: 'warning' });
			} else {
				points += 8;
			}

			if (!kwInDesc) {
				recs.push({ text: t('seo_rec_keyword_desc'), level: 'warning' });
			} else {
				points += 8;
			}

			if (!kwInContent) {
				recs.push({ text: t('seo_rec_keyword_content'), level: 'error' });
			} else {
				points += 5;
				if (kwEarly) {
					points += 4;
				} else {
					recs.push({ text: t('seo_rec_keyword_early'), level: 'warning' });
				}
			}

			if (stuffing) {
				recs.push({ text: t('seo_rec_keyword_stuffing'), level: 'error' });
				points = Math.max(0, points - 5);
			}
		}

		// ── 6. OG / SOCIAL (10 pts) ─────────────────────────────────────────────
		// Open Graph tags control how the page appears when shared on social networks.
		const ogTitle = $('#og_title').val() || '';
		const ogDesc  = $('#og_description').val() || '';

		if (!ogTitle) {
			recs.push({ text: t('seo_rec_og_title'), level: 'warning' });
		} else {
			points += 5;
		}
		if (!ogDesc) {
			recs.push({ text: t('seo_rec_og_desc'), level: 'warning' });
		} else {
			points += 5;
		}

		// ── Score display ────────────────────────────────────────────────────────

		const score = Math.min(Math.round(points), 100);

		$('#seo-score-fill').css('width', score + '%');
		$('#seo-score-value').text(score + '%');

		let color = '#ff4d4d';
		if (score >= 80)      color = '#4caf50';
		else if (score >= 50) color = '#ffa726';
		$('#seo-score-fill').css('background-color', color);

		// ── Recommendations list — errors first, then warnings, then info ────────

		const $recList = $('#seo-recommendations');
		$recList.empty();

		const sorted = [
			...recs.filter(r => r.level === 'error'),
			...recs.filter(r => r.level === 'warning'),
			...recs.filter(r => r.level === 'ok'),
		];

		if (sorted.length === 0) {
			$recList.append('<li class="seo-success">&#10003; ' + t('seo_success') + '</li>');
		} else {
			sorted.forEach(rec => {
				const icon = rec.level === 'error' ? '&#10007;' : rec.level === 'ok' ? '&#10003;' : '&#9888;';
				$recList.append('<li class="seo-rec-' + rec.level + '">' + icon + ' ' + rec.text + '</li>');
			});
		}
	}

	// ─── Init ────────────────────────────────────────────────────────────────────

	if (typeof $ === 'undefined') {
		console.warn('SEO Preview: jQuery is required but not loaded');
		return;
	}

	$('#meta_title, #meta_description').each(function() {
		updateCharCounter(this);
	});

	updatePreview();
	updateSeoScore();

	// Field listeners — includes og_title and og_description
	$('#title, #meta_title, #meta_description, #custom_slug, #meta_keywords, #category, #og_title, #og_description').on('input change', function() {
		updatePreview();
		updateSeoScore();
		if (this.id === 'meta_title' || this.id === 'meta_description') {
			updateCharCounter(this);
		}
	});

	$('#content').on('input', function() {
		updateSeoScore();
	});

	// WYSIWYG editor listeners
	const editorContent = document.querySelector('.editor-content');
	if (editorContent) {
		editorContent.addEventListener('input', updateSeoScore);
		editorContent.addEventListener('keyup', updateSeoScore);
		new MutationObserver(updateSeoScore).observe(editorContent, {
			childList: true, subtree: true, characterData: true
		});
	}

	if (typeof editor !== 'undefined' && editor && typeof editor.on === 'function') {
		editor.on('change', updateSeoScore);
	}

	$('input[name="type"]').on('change', updatePreview);
	$('.content-type-selector a').on('click', function() {
		setTimeout(() => { updatePreview(); updateSeoScore(); }, 100);
	});
});