<?php
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

    // Resolve admin folder: settings first, then filesystem scan as fallback
    $adminDirName = $settings['admin_dir'] ?? null;
    if (!$adminDirName || !is_dir(__DIR__ . '/' . $adminDirName)) {
        // Scan for a folder that contains admin-credentials.php
        foreach (glob(__DIR__ . '/*/admin-credentials.php') ?: [] as $_f) {
            $adminDirName = basename(dirname($_f));
            break;
        }
    }

    $resetFile = $adminDirName ? (__DIR__ . '/' . $adminDirName . '/reset-password.php') : null;
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

// Check if preference is set to display featured image as header
if (empty($type) && empty($slug) && isset($settings['homepage_type']) && $settings['homepage_type'] === 'page') {
	// Find page used as homepage and display its image
	foreach ($data['page'] as $page) {
		// Show featured image code that needs to be fixed
	}
}

// Also check if homepage is a page with gallery
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

// Content already processed above before header template — no duplicate call needed.

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

// Check if the admin is logged in
$isAdminLoggedIn = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// Display edit link if admin is logged in and we're viewing content
$isAdminLoggedIn = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
if ($isAdminLoggedIn) {
	$_adminDir = rtrim($settings['admin_dir'] ?? 'admin', '/');
	// For content lists (articles, pages, projects)
	if (!empty($type) && empty($slug) && in_array(rtrim($type, 's'), $contentTypes)) {
		echo '<div class="admin-edit-link">';
		echo '<a class="button" href="' . getBaseUrl() . $_adminDir . '/index.php?type=' . rtrim($type, 's') . '">';
		echo 'Manage ' . ucfirst($type);
		echo '</a>';
		echo '</div>';
	}
	// For individual content items
	else if (!empty($type) && !empty($slug)) {
		$singleType = in_array($type, $contentTypes) ? $type : rtrim($type, 's');

		if (in_array($singleType, $contentTypes)) {
			// Use sl_find_in_index to locate the item's position in the ordered index.
			// This replaces the old foreach-with-$idx loop that relied on $data[$type]
			// being the full sorted array — $data[$type] now contains only the single item.
			$_indexFound = sl_find_in_index($singleType, $slug);
			if ($_indexFound !== null) {
				[, $contentIndex] = $_indexFound;
				echo '<div class="admin-edit-link">';
				echo '<a class="button" href="' . getBaseUrl() . $_adminDir . '/index.php?action=edit&type=' . $singleType . '&index=' . $contentIndex . '">';
				echo 'Edit this ' . ucfirst($singleType);
				echo '</a>';
				echo '</div>';
			}
		}
	}
	// For homepage
	else if (empty($type) && empty($slug)) {
		echo '<div class="admin-edit-link">';
		echo '<a class="button" href="' . getBaseUrl() . $_adminDir . '/index.php?action=settings">';
		echo 'Manage Site Settings';
		echo '</a>';
		echo '</div>';
	}
}

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
?>