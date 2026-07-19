<?php
/**
 * preview.php — SynaptikCMS Live preview of unpublished content 
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
	http_response_code(403);
	exit(__t('http_access_denied'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit(__t('http_post_only'));
}

$rootDir = dirname(__DIR__);

chdir($rootDir);

$_SERVER['SCRIPT_NAME'] = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/index.php';

require_once $rootDir . '/functions.php';

$settings = loadSettings();
// Load lightweight indices — preview builds its own $item from POST data,
// $data is only needed so cleanUrl() / getCategoryPath() can resolve paths.
require_once $rootDir . '/data-layer.php';
$data = sl_build_data_array(['article', 'page', 'project'], false);

$activeTheme = $settings['active_theme'] ?? 'default';

$GLOBALS['settings'] = $settings;
$GLOBALS['data']     = $data;
$headerScripts       = []; 

$contentType = in_array($_POST['type'] ?? '', ['article', 'page', 'project'])
	? $_POST['type']
	: 'article';

$str  = fn(string $k, string $d = '') => trim($_POST[$k] ?? $d);
$bool = fn(string $k) => isset($_POST[$k]) && $_POST[$k] === '1';
$chk  = fn(string $k) => isset($_POST[$k]);

// Image à la une
$rawImage  = $str('featured_image') ?: $str('selected_image_path');
$itemImage = '';
if ($rawImage !== '') {
	$itemImage = (strpos($rawImage, 'files/') === 0)
		? $rawImage
		: 'files/' . ltrim($rawImage, '/');
}

// Galleries
$galleries = [];
if (isset($_POST['galleries']) && is_array($_POST['galleries'])) {
	foreach ($_POST['galleries'] as $gallery) {
		$images = [];
		foreach ($gallery['images'] ?? [] as $img) {
			$src = trim($img['src'] ?? '');
			if ($src === '') continue;
			$images[] = [
				'src'      => (strpos($src, 'files/') === 0) ? $src : 'files/' . ltrim($src, '/'),
				'caption'  => $img['caption']  ?? '',
				'alt_text' => $img['alt_text']  ?? '',
			];
		}
		$galleries[] = [
			'label'  => $gallery['label']  ?? 'Gallery',
			'layout' => $gallery['layout'] ?? 'grid',
			'images' => $images,
		];
	}
}

// ── Gallery scripts ───────────────────────────────────────────────────────────
// Collect library scripts required by masonry/justified layouts.
// Deduplication prevents jQuery from appearing twice when multiple layouts coexist.
$_seenGalleryLayouts = [];
foreach ($galleries as $_g) {
	$_gLayout = $_g['layout'] ?? 'grid';
	if (
		!in_array($_gLayout, $_seenGalleryLayouts, true) &&
		in_array($_gLayout, ['masonry', 'justified', 'carousel'], true)
	) {
		$_seenGalleryLayouts[] = $_gLayout;
		foreach (getGalleryScripts($_gLayout) as $_script) {
			if (!in_array($_script, $headerScripts, true)) {
				$headerScripts[] = $_script;
			}
		}
	}
}
unset($_seenGalleryLayouts, $_g, $_gLayout, $_script);

// Tags
$rawTags = $_POST['tags'] ?? '';
$tags = is_array($rawTags)
	? array_filter(array_map('trim', $rawTags))
	: array_filter(array_map('trim', explode(',', $rawTags)));

$item = [
	'type'                => $contentType,
	'title'               => $str('title', '(Sans titre)'),
	'content'             => $_POST['content'] ?? '',
	'content_format'      => in_array($_POST['content_format'] ?? 'html', ['html', 'markdown'])
	? $_POST['content_format'] : 'html',
	'description'         => $str('description'),
	'date'                => $str('date', date('Y-m-d')),
	'category'            => $str('category'),
	'tags'                => array_values($tags),
	'image'               => $itemImage,
	'show_featured_image' => $chk('show_featured_image') && $itemImage !== '',
	'show_title'          => $chk('show_title'),
	'show_date'           => $chk('show_date'),
	'show_tags_at_bottom' => $chk('show_tags_at_bottom'),
	'galleries'           => $galleries,
	'meta_title'          => $str('meta_title'),
	'meta_description'    => $str('meta_description'),
	'meta_keywords'       => $str('meta_keywords'),
	'og_title'            => $str('og_title'),
	'og_description'      => $str('og_description'),
	'slug'                => 'preview-' . time(),
	'custom_slug'         => '',
	'page_template'       => $str('page_template'),
	'show_related_items'  => $chk('show_related_items'),
	'related_items'       => (function() {
		$raw     = $_POST['related_items'] ?? '[]';
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : [];
	})(),
	'custom_fields'       => (isset($_POST['custom_fields']) && is_array($_POST['custom_fields']))
		? array_map('trim', $_POST['custom_fields'])
		: [],
];

$metaTitle       = $item['meta_title']       !== '' ? $item['meta_title']       : $item['title'];
$metaDescription = $item['meta_description'] !== '' ? $item['meta_description'] : ($settings['site_description'] ?? '');
$metaKeywords    = $item['meta_keywords'];
$ogTitle         = $item['og_title']         !== '' ? $item['og_title']         : $item['title'];
$ogDescription   = $item['og_description']   !== '' ? $item['og_description']   : $metaDescription;
$ogImage         = $itemImage !== '' ? getBaseUrl() . $itemImage : '';

// ── functions.php du thème si présent (hooks, options) ───────────────────────
$themeFunctionsFile = $rootDir . '/theme/' . $activeTheme . '/functions.php';
if (file_exists($themeFunctionsFile)) {
	require_once $themeFunctionsFile;
}

// ── Bannière aperçu ───────────────────────────────────────────────────────────
$previewBannerHtml = '
<style>
  #preview-banner {
	position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;
	background: #0f172a; color: #e2e8f0;
	font-family: system-ui, sans-serif; font-size: 13px;
	padding: 10px 20px; display: flex; align-items: center; gap: 16px;
	border-top: 2px solid #f59e0b; box-shadow: 0 -4px 20px rgba(0,0,0,.5);
  }
  #preview-banner .pb-badge {
	background: #f59e0b; color: #000; font-weight: 700; font-size: 14px;
	padding: 3px 8px; border-radius: 3px; letter-spacing:.08em;
	text-transform: uppercase; white-space: nowrap; flex-shrink: 0;
  }
  #preview-banner .pb-title { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; }
  #preview-banner .pb-type  { opacity:.45; font-weight:400; margin-left:6px; }
  #preview-banner .pb-close {
	flex-shrink:0; font-weight: 700; background:transparent; border:1px solid #475569;
	color:#e2e8f0; padding:5px 14px; border-radius:4px; cursor:pointer; font-size:14px; text-transform: uppercase; margin-bottom:20px;
  }
  #preview-banner .pb-close:hover { background:#1e293b; }
  body { padding-bottom: 52px !important; }
</style>
<div id="preview-banner">
  <span class="pb-badge">⚡ ' . __t('preview_badge') . '</span>
  <span class="pb-title">' . htmlspecialchars($item['title'])
	. '<span class="pb-type">— ' . htmlspecialchars(ucfirst($contentType)) . ' · ' . __t('preview_unpublished') . '</span></span>
  <button class="pb-close" onclick="window.close()">' . __t('preview_close') . '</button>
</div>';


$themeVars = [
	'settings'        => $settings,
	'data'            => $data,
	'metaTitle'       => $metaTitle,
	'metaDescription' => $metaDescription,
	'metaKeywords'    => $metaKeywords,
	'ogTitle'         => $ogTitle,
	'ogDescription'   => $ogDescription,
	'ogImage'         => $ogImage,
	'headerScripts'   => $headerScripts,
];

// For pages with a custom template, load page-templates/{name}.php instead of content-pages.php.
// basename() prevents path traversal in the page_template value.
$contentTemplate = ($contentType === 'page' && !empty($item['page_template']))
	? 'page-templates/' . basename($item['page_template'])
	: 'content-' . $contentType . 's';

loadThemeTemplate('header', $themeVars);
loadThemeTemplate($contentTemplate, $themeVars + ['item' => $item]);
echo $previewBannerHtml;
loadThemeTemplate('footer', $themeVars);