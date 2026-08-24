<?php
// Security check
if (!defined('INCLUDED')) {
	http_response_code(403);
	exit;
}

// Standalone on purpose: both admin-functions.php (the normal admin bootstrap)
// and preview.php (which deliberately avoids admin-functions.php's heavier
// bootstrap — see its own top-of-file comment) need these two functions
// without pulling in everything else admin-functions.php defines.

if (!function_exists('_admin_scrub_tag_attributes')) {
/**
 * Strips event-handler attributes and dangerous URL schemes from every tag,
 * whatever quoting style the value uses.
 *
 * Replaces three earlier regexes (shared verbatim by both purifiers) that
 * each required the value to be wrapped in quotes — `["\']([^"\']*)["\']`.
 * The unquoted form matched none of them, so `<img src=x onerror=alert(1)>`
 * passed through sanitization completely untouched. Values are matched here
 * in all three forms: "double", 'single', and bare.
 *
 * The scheme probe decodes HTML entities before testing, so an encoded
 * `java&#115;cript:` is caught the same as the literal spelling — browsers
 * decode the attribute before resolving the URL, so the check has to too.
 */
function _admin_scrub_tag_attributes($html) {
	return preg_replace_callback(
		'/<([a-zA-Z][a-zA-Z0-9:_-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/',
		function ($tag) {
			$attrs = $tag[2];

			// Any on*= handler, value quoted or bare
			$attrs = preg_replace(
				'/[\s\r\n]+on[a-z]+[\s\r\n]*=[\s\r\n]*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
				'',
				$attrs
			);

			// URL-bearing attributes: drop unsafe schemes, keep everything else
			$attrs = preg_replace_callback(
				'/[\s\r\n]+(?:href|src|xlink:href|action|formaction|srcset|poster|data)[\s\r\n]*=[\s\r\n]*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
				function ($attr) {
					$value = $attr[0];
					$value = substr($value, strpos($value, '=') + 1);
					$value = trim($value, " \t\r\n\"'");
					$probe = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					$probe = strtolower(preg_replace('/[\s\x00-\x1F]/', '', $probe));

					$unsafe = preg_match('/^(?:javascript|vbscript|file):/', $probe)
						|| (strpos($probe, 'data:') === 0 && strpos($probe, 'data:image/') !== 0);

					return $unsafe ? '' : $attr[0];
				},
				$attrs
			);

			return '<' . $tag[1] . $attrs . '>';
		},
		$html
	);
}
}

if (!function_exists('admin_purify_html')) {
/**
 * Sanitize HTML content to prevent XSS attacks
 */
function admin_purify_html($html) {
	 // Protect code blocks first
	 $codeBlocks = [];
	 $placeholder = '___PROTECTED_CODE_';

	 // Extract <pre> blocks (which contain <code>) - fixed pattern
	 $html = preg_replace_callback(
		 '/<pre\b[^>]*>(?:(?!<\/pre>).)*<\/pre>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = $matches[0];
			 return $placeholder . $index . '___';
		 },
		 $html
	 );

	 // Protect standalone <code> blocks (not inside <pre>)
	 $html = preg_replace_callback(
		 '/<code\b[^>]*>(?:(?!<\/code>).)*<\/code>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 if (strpos($matches[0], '___PROTECTED_CODE_') !== false) {
				 return $matches[0];
			 }
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = $matches[0];
			 return $placeholder . $index . '___';
		 },
		 $html
	 );

	 // Purify the rest
	 $allowedTags = '<p><br><b><i><strong><em><u><s><strike><del><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody><button><svg><path><polygon><polyline><circle><rect><line><g>';

	 $html = strip_tags($html, $allowedTags);

	 // Remove event handlers and dangerous URL schemes (all quoting styles)
	 $html = _admin_scrub_tag_attributes($html);

	 // Restore protected code blocks
	 $html = preg_replace_callback(
		 '/___PROTECTED_CODE_(\d+)___/',
		 function($matches) use ($codeBlocks) {
			 return $codeBlocks[$matches[1]] ?? '';
		 },
		 $html
	 );

	 return $html;
 }
}

if (!function_exists('admin_purify_markdown')) {
/**
 * Sanitize Markdown-format content to prevent XSS from embedded raw HTML.
 * Markdown syntax itself never produces literal '<' characters (headings,
 * emphasis, links and images all use their own non-HTML syntax), so the tag
 * allowlist below only ever strips raw HTML an author typed directly —
 * legitimate Markdown passes through untouched. Fenced code blocks and
 * inline code spans are protected first, mirroring how admin_purify_html()
 * protects <pre>/<code>, so a tutorial showing a literal <script> tag as
 * example text isn't mangled by the pass below.
 *
 * Markdown's own [text](url) link syntax is sanitized separately, at render
 * time, by _md_sanitize_url() in core/render/tf-markdown.php — this function
 * only needs to guard against HTML typed directly into the source.
 */
function admin_purify_markdown($markdown) {
	$blocks = [];
	$placeholder = '___PROTECTED_MD_';

	// Protect fenced code blocks (```lang\n...\n```)
	$markdown = preg_replace_callback(
		'/```.*?```/s',
		function($matches) use (&$blocks, $placeholder) {
			$index = count($blocks);
			$blocks[$index] = $matches[0];
			return $placeholder . $index . '___';
		},
		$markdown
	);

	// Protect inline code spans (`...`)
	$markdown = preg_replace_callback(
		'/`[^`\n]+`/',
		function($matches) use (&$blocks, $placeholder) {
			$index = count($blocks);
			$blocks[$index] = $matches[0];
			return $placeholder . $index . '___';
		},
		$markdown
	);

	// Strip any embedded raw HTML down to the same allowlist as admin_purify_html()
	$allowedTags = '<p><br><b><i><strong><em><u><s><strike><del><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody><button><svg><path><polygon><polyline><circle><rect><line><g>';
	$markdown = strip_tags($markdown, $allowedTags);

	// Remove event handlers (onclick, onerror, ...) and dangerous URL schemes
	// (javascript:, vbscript:, non-image data:) from any raw HTML tag typed
	// directly into the source — all quoting styles, see the helper above.
	$markdown = _admin_scrub_tag_attributes($markdown);

	// Restore protected code blocks/spans
	$markdown = preg_replace_callback(
		'/___PROTECTED_MD_(\d+)___/',
		function($matches) use ($blocks) {
			return $blocks[$matches[1]] ?? '';
		},
		$markdown
	);

	return $markdown;
}
}
