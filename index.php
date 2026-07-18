<?php
ini_set('memory_limit', '256M');
session_start();
// Include the functions file
require_once 'functions.php';
// Load the new split-file data layer
require_once 'data-layer.php';

// Load settings
$settings = loadSettings();

// ── Public password reset route ─────────────────────────────────
// Intercepts ?reset_token= BEFORE any routing or output.
// Lets the admin reset their password without the admin folder name
// appearing in the emailed link.
if (isset($_GET['reset_token']) && $_GET['reset_token'] !== '') {
    // Forward token as the param reset-password.php expects
    $_GET['token'] = $_GET['reset_token'];

    // Resolve admin folder via shared helper (settings → filesystem scan → default)
    $adminDirName = resolve_admin_dir();
    $resetFile = __DIR__ . '/' . $adminDirName . '/reset-password.php';
    if ($resetFile && file_exists($resetFile)) {
        // chdir() to the admin folder so that relative paths inside
        // admin-functions.php (e.g. '../data-functions.php') resolve correctly.
        // Without this, those paths are relative to the CMS root (index.php's CWD)
        // and point one level too high, causing a fatal error.
        $prevCwd = getcwd();
        chdir(__DIR__ . '/' . $adminDirName);
        require $resetFile;
        chdir($prevCwd); // restore — though exit() follows immediately
    } else {
        http_response_code(404);
        echo 'Reset page not found.';
    }
    exit;
}

// Theme preview banner: shown when a valid _tp token is present in the URL.
// The token is validated inside loadSettings() — if active_theme was overridden,
// we know the token is valid and we display the banner.
$_themePreviewBanner = '';
if (isset($_GET['_tp']) && isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
	// Decode theme name from token for display (re-decode; no security logic here)
	$_tpDecoded = base64_decode(strtr($_GET['_tp'], '-_', '+/'), true);
	$_tpParts   = $_tpDecoded ? explode('|', $_tpDecoded, 3) : [];
	$_tpTheme   = isset($_tpParts[0]) ? htmlspecialchars(basename($_tpParts[0])) : $settings['active_theme'];
	$_tpToken   = htmlspecialchars($_GET['_tp']);
	$_themePreviewBanner = '
<style>
  #theme-preview-banner {
	position:fixed;bottom:0;left:0;right:0;z-index:99999;
	background:#0f172a;color:#e2e8f0;
	font-family:system-ui,sans-serif;font-size:13px;
	padding:10px 20px;display:flex;align-items:center;gap:16px;
	border-top:2px solid #818cf8;box-shadow:0 -4px 20px rgba(0,0,0,.5);
  }
  #theme-preview-banner .tpb-badge {
	background:#818cf8;color:#fff;font-weight:700;font-size:11px;
	padding:3px 8px;border-radius:3px;letter-spacing:.08em;
	text-transform:uppercase;white-space:nowrap;flex-shrink:0;
  }
  #theme-preview-banner .tpb-label { flex:1;font-weight:600; }
  #theme-preview-banner .tpb-sub { opacity:.45;font-weight:400;margin-left:6px;font-style:italic; }
  #theme-preview-banner .tpb-close {
	flex-shrink:0;font-weight:700;background:transparent;
	border:1px solid #475569;color:#e2e8f0;
	padding:5px 14px;border-radius:4px;cursor:pointer;font-size:12px;text-transform:uppercase;margin-bottom:20px;
  }
  #theme-preview-banner .tpb-close:hover { background:#1e293b; }
  body { padding-bottom:56px !important; }
</style>
<div id="theme-preview-banner">
  <span class="tpb-badge">&#127912; ' . __t('preview_badge') . '</span>
  <span class="tpb-label">' . $_tpTheme . '
	<span class="tpb-sub">&mdash; not active &middot; preview only</span>
  </span>
  <button class="tpb-close" onclick="window.close()">' . __t('preview_close') . '</button>
</div>';
}

// ── Load data — split-file architecture ───────────────────────────────────────
//
// Step 1: always load the lightweight index for all types.
//   - Fast: no content body, only metadata fields (title, slug, date, image, etc.)
//   - Sufficient for: routing (parseRequestUri), list pages, category/tag pages,
//     navigation rendering, homepage article/project cards, cleanUrl() helpers.
//
// Step 2 (below, after routing): for single-item views, upgrade $data[$type] to
//   a single full item containing the content body and all SEO/gallery fields.
//   Only ONE file is read — not all items for the type.
//
$data = sl_build_data_array(['article', 'page', 'project'], false);
// Expose globally so parseRequestUri() and cleanUrl() helpers can read it
$GLOBALS['data'] = $data;

// Clean up content if needed (legacy compat — content no longer lives here at this stage)
if (isset($data['content'])) {
  $data['content'] = stripslashes($data['content']);
}

// Define content types
$contentTypes = ["article", "page", "project"];

// Parse the URI to get routing parameters (reads $GLOBALS['data'] for slug resolution)
$uriParams = parseRequestUri();

// Plugin hook: manual redirects + optional 404→home fallback (Redirects
// plugin, if active). Positioned here deliberately: after routing is
// resolved (so we know whether this request is a genuine 404) but before
// any HTTP header or HTML output. function_exists() guards this to a
// no-op when the plugin isn't active.
if (function_exists('rd_maybe_redirect')) {
	rd_maybe_redirect($uriParams['type'] === '404');
}

// Override GET parameters if clean URL was used and parsed
if (!empty($uriParams["type"])) {
	$_GET["type"] = $uriParams["type"];
}
if (!empty($uriParams["slug"])) {
	$_GET["slug"] = $uriParams["slug"];
}
if (!empty($uriParams["page"])) {
	$_GET["page"] = $uriParams["page"];
}
if (!empty($uriParams["category"])) {
	$_GET["category"] = $uriParams["category"];
}
if (!empty($uriParams["tag"])) {
	$_GET["tag"] = $uriParams["tag"];
}

// Initialize variables
$type = isset($_GET["type"]) ? $_GET["type"] : "";
$slug = isset($_GET["slug"]) ? $_GET["slug"] : "";
$category = isset($_GET["category"]) ? $_GET["category"] : "";
$tag = isset($_GET["tag"]) ? $_GET["tag"] : "";

// ── Step 2: contextual full-item loading ───────────────────────────────────────
//
// Single item view (article/page/project + slug): replace the index entries for
// that type with the single full item file. This loads exactly ONE .json file
// (~5–15 KB) instead of the entire type's data. All other types keep index data.
//
// Homepage set to a specific page: load that page's full content file only.
//
if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	$_fullItem = sl_load_item_by_slug($type, $slug);
	if ($_fullItem !== null) {
		$data[$type]         = [$_fullItem];
		$GLOBALS['data']     = $data;
	}
} elseif (
	empty($type) && empty($slug)
	&& ($settings['homepage_type'] ?? '') === 'page'
	&& !empty($settings['homepage_page_id'])
) {
	$_homepageItem = sl_load_item_by_slug('page', $settings['homepage_page_id']);
	if ($_homepageItem !== null) {
		$data['page']     = [$_homepageItem];
		$GLOBALS['data']  = $data;
	}
}

// Process content based on type, slug and category
// Determine routing and call processContent ONCE here, before header output.
// http_response_code() must be set before loadThemeTemplate('header') emits HTML.
if ($type === 'category' && !empty($category)) {
	$pageTitle = 'Category: ' . urldecode($category);
	$pageContent = renderCategoryPage($category, $data);
	$httpStatus = 200;
} elseif ($type === 'tag' && !empty($tag)) {
	$pageTitle = 'Tag: ' . urldecode($tag);
	$pageContent = renderTagPage($tag, $data);
	$httpStatus = 200;
} else {
	$pageData = processContent($type, $slug, $data, $settings, $category, $tag);
	$pageTitle = $pageData['title'];
	$pageContent = $pageData['content'];
	$httpStatus = $pageData['http_status'] ?? 200;
}

// Set HTTP status BEFORE any output (header template inclusion below)
http_response_code($httpStatus);

// Generate SEO metadata
// At this point $data[$type] contains the full item for single-item views,
// so generateSEO() can read meta_title, meta_description from it correctly.
$seoData = generateSEO($pageTitle, $type, $slug, $data, $settings);
$metaTitle = $seoData['title'];
$metaDescription = $seoData['description'];

$requiredGalleryScripts = [];
$galleryLayouts = [];

// Initialize SEO variables that are missing
$metaKeywords = '';
$ogImage = '';
$ogTitle = '';
$ogDescription = '';

// render_header_scripts() in the theme header automatically injects
// synaptikCSS.php, the active theme stylesheet, and main.js via its $system
// array. Do NOT add them here — this array is for page-specific extras only.

// Homepage SEO overrides — only for homepage_type === 'default'.
// When homepage_type === 'page', the selected page carries its own SEO fields
// and is handled by the single-item block below.
if (empty($type) && empty($slug) && ($settings['homepage_type'] ?? 'default') === 'default') {
	$metaKeywords  = $settings['home_meta_keywords']  ?? '';
	$ogTitle       = !empty($settings['home_og_title'])       ? $settings['home_og_title']       : $metaTitle;
	$ogDescription = !empty($settings['home_og_description']) ? $settings['home_og_description'] : $metaDescription;
	$ogImage       = !empty($settings['home_og_image'])       ? getBaseUrl() . $settings['home_og_image'] : '';
}

// For single content items, extract SEO data
// $data[$type] now contains only the one matching item — the foreach finds it on the first iteration.
if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	foreach ($data[$type] as $item) {
		$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		if ($itemSlug === $slug) {
			// Set metadata from item
			$metaKeywords = $item['meta_keywords'] ?? '';
			$ogImage = !empty($item['og_image']) ? getBaseUrl() . $item['og_image'] :
			   (!empty($item['image']) ? getBaseUrl() . $item['image'] : '');
			$ogTitle = $item['og_title'] ?? $metaTitle;
			$ogDescription = $item['og_description'] ?? $metaDescription;
			break;
		}
	}
}

// For single content items, check if there's a gallery and what layout it uses
if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	foreach ($data[$type] as $item) {
		$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		if ($itemSlug === $slug) {
			// Legacy single gallery
			if (isset($item['gallery']) && is_array($item['gallery']) && !empty($item['gallery'])) {
				$galleryLayouts[] = isset($item['gallery_layout']) ? $item['gallery_layout'] : 'grid';
			}
			// New named galleries system
			if (isset($item['galleries']) && is_array($item['galleries'])) {
				foreach ($item['galleries'] as $namedGallery) {
					$galleryLayouts[] = $namedGallery['layout'] ?? 'grid';
				}
			}
			break;
		}
	}
}

// Check if homepage is a page with gallery
if (empty($type) && empty($slug) && $settings['homepage_type'] === 'page' && !empty($settings['homepage_page_id'])) {
	foreach ($data['page'] as $page) {
		$pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];
		if ($pageSlug === $settings['homepage_page_id']) {
			if (isset($page['gallery']) && is_array($page['gallery']) && !empty($page['gallery'])) {
				$galleryLayouts[] = isset($page['gallery_layout']) ? $page['gallery_layout'] : 'grid';
			}
			if (isset($page['galleries']) && is_array($page['galleries'])) {
				foreach ($page['galleries'] as $namedGallery) {
					$galleryLayouts[] = $namedGallery['layout'] ?? 'grid';
				}
			}
			break;
		}
	}
}

// Load all required scripts for detected gallery layouts
foreach ($galleryLayouts as $layout) {
	$layoutScripts = getGalleryScripts($layout);
	$requiredGalleryScripts = array_merge($requiredGalleryScripts, $layoutScripts);
}

// Remove duplicate scripts
$requiredGalleryScripts = array_unique($requiredGalleryScripts);

// Initialize $headerScripts with any gallery-specific scripts detected above.
// System assets (synaptikCSS, theme CSS, main.js) are always prepended by
// render_header_scripts() and must NOT be added here.
$headerScripts = array_values(array_unique($requiredGalleryScripts));

$headerScripts[] = '	<script>window.appSettings = window.appSettings || {}; window.appSettings.showSearchIcon = ' . (isset($settings["show_search_icon"]) && $settings["show_search_icon"] ? 'true' : 'false') . ';</script>';

// ── Admin top bar ────────────────────────────────────────────────────────────
// Visible only when an admin session is active.
// CSS injected into <head> via $headerScripts.
// body.has-adminbar adds padding-top so content clears the bar.
// z-index:2147483647 (CSS max) + isolation:isolate ensures the bar renders
// above all theme stacking contexts, including those created by backdrop-filter.
$isAdminLoggedIn = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$_adminBarHtml   = '';
if ($isAdminLoggedIn) {
	$_adminDir  = resolve_admin_dir();
	$_adminBase = getBaseUrl() . $_adminDir;

	// Load admin locale strings for correct translations (edit, new_article, etc.).
	// Direct JSON read — cheap single file, no cache overhead, admin-only path.
	$_adminLang = [];
	$_adminLangFile = __DIR__ . '/lang/admin/' . ($settings['active_language'] ?? 'en') . '.json';
	if (!file_exists($_adminLangFile)) {
		$_adminLangFile = __DIR__ . '/lang/admin/en.json';
	}
	if (file_exists($_adminLangFile)) {
		$_adminLang = json_decode(file_get_contents($_adminLangFile), true) ?? [];
	}
	$_at = static function(string $key) use ($_adminLang): string {
		return htmlspecialchars($_adminLang[$key] ?? $key);
	};

	// Contextual actions: edit/manage button + new item button.
	// The home icon always goes to the dashboard.
	$_ctxLabel     = '';
	$_ctxHref      = '';
	$_newLabel     = '';
	$_newHref      = '';
	$_listLink     = ''; // optional secondary link to current content-type list
	$_showSettings = true;

	if (!empty($type) && !empty($slug)) {
		// Single item view
		$_singleType = in_array($type, $contentTypes) ? $type : rtrim($type, 's');
		if (in_array($_singleType, $contentTypes)) {
			$_indexFound = sl_find_in_index($_singleType, $slug);
			if ($_indexFound !== null) {
				[, $_contentIndex] = $_indexFound;
				$_ctxLabel  = $_adminLang['edit']                   ?? 'Edit';
				$_ctxHref   = $_adminBase . '/index.php?action=edit&type=' . $_singleType . '&index=' . $_contentIndex;
				$_newLabel  = $_adminLang['new_' . $_singleType]    ?? ('New ' . $_singleType);
				$_newHref   = $_adminBase . '/index.php?action=add&type=' . $_singleType;
				$_listLink  = $_adminBase . '/index.php?type=' . $_singleType;
			}
		}
	} elseif (!empty($type) && empty($slug) && in_array(rtrim($type, 's'), $contentTypes)) {
		// Content list view
		$_listType     = rtrim($type, 's');
		$_ctxLabel     = $_adminLang['manage']                  ?? 'Manage';
		$_ctxHref      = $_adminBase . '/index.php?type=' . $_listType;
		$_newLabel     = $_adminLang['new_' . $_listType]       ?? ('New ' . $_listType);
		$_newHref      = $_adminBase . '/index.php?action=add&type=' . $_listType;
		$_showSettings = false;
	}

	// CSS and JS injected into <head> via $headerScripts.
	$_s  = '<style>';
	$_s .= ':root{--snk-adminbar-height:36px;}';
	$_s .= '#snk-admin-bar{position:fixed;top:0;left:0;right:0;z-index:2147483647;isolation:isolate;display:flex;align-items:center;gap:4px;padding:0 12px;height:var(--snk-adminbar-height);background:#1e2a3a;color:#b2bac6;font-family:system-ui,sans-serif;font-size:12px;line-height:1;}';
	$_s .= 'body.has-adminbar{padding-top:var(--snk-adminbar-height);}';
	$_s .= '#snk-admin-bar a{display:inline-flex;align-items:center;gap:5px;padding:0 10px;height:26px;border-radius:4px;color:#b2bac6;text-decoration:none;white-space:nowrap;transition:background .15s,color .15s;}';
	$_s .= '#snk-admin-bar a:hover{background:rgba(255,255,255,.08);color:#fff;}';
	$_s .= '#snk-admin-bar .snk-ab-site{font-weight:700;color:#fff;}';
	$_s .= '#snk-admin-bar .snk-ab-divider{width:1px;height:18px;background:rgba(255,255,255,.12);margin:0 4px;flex-shrink:0;}';
	$_s .= '#snk-admin-bar .snk-ab-ctx{background:rgba(79,167,92,.2);color:#b2e8b8;font-weight:600;border:1px solid rgba(79,167,92,.3);}';
	$_s .= '#snk-admin-bar .snk-ab-ctx:hover{background:rgba(79,167,92,.35);color:#fff;}';
	$_s .= '#snk-admin-bar .snk-ab-spacer{flex:1;}';
	$_s .= '#snk-admin-bar svg{flex-shrink:0;}';
	$_s .= '</style>';
	$_s .= '<script>document.addEventListener("DOMContentLoaded",function(){document.body.classList.add("has-adminbar");});</script>';
	$headerScripts[] = $_s;

	$_ico_home = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
	$_ico_list = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
	$_ico_edit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
	$_ico_new  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
	$_ico_cog  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l-.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';

	$_adminBarHtml  = '<div id="snk-admin-bar">';

	// Home icon — always goes to the admin dashboard
	$_adminBarHtml .= '<a class="snk-ab-site" href="' . htmlspecialchars($_adminBase . '/index.php') . '">' . $_ico_home . htmlspecialchars($settings['site_title'] ?? 'SynaptikCMS') . '</a>';

	$_adminBarHtml .= '<div class="snk-ab-divider"></div>';

	// Link to the current content-type list (only on single item or list pages)
	if (!empty($_listLink)) {
		$_adminBarHtml .= '<a href="' . htmlspecialchars($_listLink) . '">' . $_ico_list . htmlspecialchars($_adminLang[rtrim($type, 's') . 's'] ?? ucfirst($type)) . '</a>';
	}

	// Contextual primary action (edit this item / manage this list)
	if (!empty($_ctxLabel) && !empty($_ctxHref)) {
		$_adminBarHtml .= '<a class="snk-ab-ctx" href="' . htmlspecialchars($_ctxHref) . '">' . $_ico_edit . htmlspecialchars($_ctxLabel) . '</a>';
	}

	// New item shortcut
	if (!empty($_newLabel) && !empty($_newHref)) {
		$_adminBarHtml .= '<a href="' . htmlspecialchars($_newHref) . '">' . $_ico_new . htmlspecialchars($_newLabel) . '</a>';
	}

	$_adminBarHtml .= '<div class="snk-ab-spacer"></div>';

	if ($_showSettings) {
		$_adminBarHtml .= '<a href="' . htmlspecialchars($_adminBase . '/index.php?action=settings') . '">' . $_ico_cog . htmlspecialchars($_adminLang['settings'] ?? 'Settings') . '</a>';
	}

	$_adminBarHtml .= '</div>';
	$GLOBALS['_adminBarHtml'] = $_adminBarHtml;
}

// Pass all relevant data to the header template
loadThemeTemplate('header', [
	'settings' => $settings,
	'data' => $data,
	'pageTitle' => $pageTitle,
	'metaTitle' => $metaTitle,
	'metaDescription' => $metaDescription,
	'metaKeywords' => $metaKeywords,
	'ogImage' => $ogImage,
	'ogTitle' => $ogTitle,
	'ogDescription' => $ogDescription,
	'type' => $type,
	'slug' => $slug,
	'contentTypes' => $contentTypes,
	'headerScripts' => $headerScripts
]);

// Emit admin top bar after the theme header for themes that do not call render_adminbar() directly.
// Themes with backdrop-filter navs (nova, prism, etc.) should call render_adminbar() as the
// first child of <body> in their header.php to avoid stacking context conflicts.
if (!empty($_adminBarHtml) && empty($GLOBALS['_adminBarHtml_emitted'])) {
	echo $_adminBarHtml;
}

// Display breadcrumbs if enabled in settings
if (isset($settings['show_breadcrumbs']) && $settings['show_breadcrumbs']) {
	$breadcrumbTitle = '';
	if (!empty($type) && !empty($slug)) {
		foreach ($data[$type] as $item) {
			$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
			if ($itemSlug === $slug) {
				$breadcrumbTitle = $item['title'];
				break;
			}
		}
	}
	echo getBreadcrumbs($type, $slug, $breadcrumbTitle, $category);
}

// Display the title if needed
$displayTitle = true; // Default to true for non-content pages
// Special case for pages set as homepage
if (empty($type) && empty($slug) && isset($settings['homepage_type']) && $settings['homepage_type'] === 'page' && !empty($settings['homepage_page_id'])) {
	// Find the homepage page
	foreach ($data['page'] as $item) {
		$pageSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		if ($pageSlug === $settings['homepage_page_id']) {
			// Use the show_title preference if it exists
			$displayTitle = isset($item['show_title']) ? $item['show_title'] : false;
			break;
		}
	}
}
// For regular content pages
else if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	foreach ($data[$type] as $item) {
		$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		
		if ($itemSlug === $slug) {
			// Use the show_title preference if it exists, otherwise default to false
			$displayTitle = isset($item['show_title']) ? $item['show_title'] : false;
			break;
		}
	}
}

// Display the content
echo $pageContent;

// Load footer template with all necessary parameters
loadThemeTemplate('footer', [
	'settings' => $settings,
	'data' => $data,
	'currentYear' => date("Y"),
	'baseUrl' => getBaseUrl()
]);

// Theme preview banner injected after footer so it overlays all content
if (!empty($_themePreviewBanner)) {
	echo $_themePreviewBanner;
}
