<?php
if (!defined('INCLUDED')) {
	http_response_code(403);
	exit;
}
if (!function_exists('_admin_scrub_tag_attributes')) {
function _admin_scrub_tag_attributes($html) {
	return preg_replace_callback(
		'/<([a-zA-Z][a-zA-Z0-9:_-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/',
		function ($tag) {
			$attrs = $tag[2];
			$attrs = preg_replace(
				'/[\s\r\n]+on[a-z]+[\s\r\n]*=[\s\r\n]*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
				'',
				$attrs
			);

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

			$attrs = preg_replace_callback(
				'/[\s\r\n]+style[\s\r\n]*=[\s\r\n]*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
				function ($attr) {
					$value = $attr[0];
					$value = substr($value, strpos($value, '=') + 1);
					$value = trim($value, " \t\r\n\"'");
					$probe = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					$probe = strtolower(preg_replace('/[\s\x00-\x1F]/', '', $probe));

					$unsafe = strpos($probe, 'url(') !== false
						|| strpos($probe, 'expression(') !== false
						|| strpos($probe, 'position:fixed') !== false
						|| strpos($probe, 'position:absolute') !== false
						|| strpos($probe, 'javascript:') !== false
						|| strpos($probe, '@import') !== false
						|| strpos($probe, 'behavior:') !== false
						|| strpos($probe, '-moz-binding') !== false;

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

if (!function_exists('_admin_sanitize_code_block')) {
function _admin_sanitize_code_block($block) {
	if (!preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>(.*)<\/\1>$/is', $block, $m)) {
		return htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
	}

	$open  = _admin_scrub_tag_attributes('<' . $m[1] . $m[2] . '>');
	$inner = preg_replace_callback(
		'/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/',
		function ($tag) {
			$allowed = ['code', 'span', 'br', 'b', 'i', 'em', 'strong'];
			return in_array(strtolower($tag[1]), $allowed, true)
				? _admin_scrub_tag_attributes($tag[0])
				: htmlspecialchars($tag[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
		},
		$m[3]
	);

	return $open . $inner . '</' . $m[1] . '>';
}
}

if (!function_exists('admin_purify_html')) {
function admin_purify_html($html) {
	 $codeBlocks = [];
	 $placeholder = '___PROTECTED_CODE_';
	 $html = preg_replace_callback(
		 '/<pre\b[^>]*>(?:(?!<\/pre>).)*<\/pre>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = _admin_sanitize_code_block($matches[0]);
			 return $placeholder . $index . '___';
		 },
		 $html
	 );

	 $html = preg_replace_callback(
		 '/<code\b[^>]*>(?:(?!<\/code>).)*<\/code>/s',
		 function($matches) use (&$codeBlocks, $placeholder) {
			 if (strpos($matches[0], '___PROTECTED_CODE_') !== false) {
				 return $matches[0];
			 }
			 $index = count($codeBlocks);
			 $codeBlocks[$index] = _admin_sanitize_code_block($matches[0]);
			 return $placeholder . $index . '___';
		 },
		 $html
	 );

	 $allowedTags = '<p><br><b><i><strong><em><u><s><strike><del><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody><button><svg><path><polygon><polyline><circle><rect><line><g>';
	 $html = strip_tags($html, $allowedTags);
	 $html = _admin_scrub_tag_attributes($html);
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
function admin_purify_markdown($markdown) {
	$blocks = [];
	$placeholder = '___PROTECTED_MD_';

	$markdown = preg_replace_callback(
		'/```.*?```/s',
		function($matches) use (&$blocks, $placeholder) {
			$index = count($blocks);
			$blocks[$index] = $matches[0];
			return $placeholder . $index . '___';
		},
		$markdown
	);

	$markdown = preg_replace_callback(
		'/`[^`\n]+`/',
		function($matches) use (&$blocks, $placeholder) {
			$index = count($blocks);
			$blocks[$index] = $matches[0];
			return $placeholder . $index . '___';
		},
		$markdown
	);

	$allowedTags = '<p><br><b><i><strong><em><u><s><strike><del><a><ul><ol><li><span><img><h1><h2><h3><h4><h5><h6><blockquote><pre><code><div><table><tr><td><th><thead><tbody><button><svg><path><polygon><polyline><circle><rect><line><g>';
	$markdown = strip_tags($markdown, $allowedTags);

	$markdown = _admin_scrub_tag_attributes($markdown);

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