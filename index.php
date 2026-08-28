<?php
ini_set('memory_limit', '256M');

if (isset($_GET['_llms'])) {
    require_once __DIR__ . '/core/' . ($_GET['_llms'] === 'full' ? 'llms-full' : 'llms') . '.php';
    exit;
}

$__adminDirForSession = 'admin';
$__configPathForSession = __DIR__ . '/config.json';
if (file_exists($__configPathForSession)) {
    $__decodedForSession = json_decode(file_get_contents($__configPathForSession), true);
    if (is_array($__decodedForSession) && !empty($__decodedForSession['admin_dir'])) {
        $__adminDirForSession = $__decodedForSession['admin_dir'];
    }
}
$__sessionConfigPath = __DIR__ . '/' . $__adminDirForSession . '/includes/session-config.php';

if (!file_exists($__sessionConfigPath)) {
    foreach (glob(__DIR__ . '/*/auth.php') ?: [] as $__adminAuthFile) {
        $__candidate = __DIR__ . '/' . basename(dirname($__adminAuthFile)) . '/includes/session-config.php';
        if (file_exists($__candidate)) {
            $__sessionConfigPath = $__candidate;
            break;
        }
    }
    unset($__adminAuthFile, $__candidate);
}
if (file_exists($__sessionConfigPath)) {
    require_once $__sessionConfigPath;
}
unset($__adminDirForSession, $__configPathForSession, $__decodedForSession, $__sessionConfigPath);

session_start();
require_once __DIR__ . '/core/functions.php';

pl_do_hook('early_request');

$settings = loadConfig();

if (isset($_GET['reset_token']) && $_GET['reset_token'] !== '') {
    $_GET['token'] = $_GET['reset_token'];

    $adminDirName = resolve_admin_dir();
    $resetFile = __DIR__ . '/' . $adminDirName . '/reset-password.php';
    if ($resetFile && file_exists($resetFile)) {
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

$_themePreviewBanner = '';
if (isset($_GET['_tp']) && isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
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
  <button class="tpb-close">' . __t('preview_close') . '</button>
</div>';
}

$__pageCacheEligible = ($_SERVER['REQUEST_METHOD'] === 'GET')
    && empty($_SERVER['QUERY_STRING'])
    && !(isset($_SESSION['admin']) && $_SESSION['admin'] === true)
    && _sl_page_cache_host_allowed();
$__pageCacheKey = null;

if ($__pageCacheEligible) {
    foreach (['article', 'page', 'project'] as $__scheduledType) {
        sl_promote_scheduled($__scheduledType);
    }
    unset($__scheduledType);

    $__pageCacheKey = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $__cachedStatus = 200;
    $__cachedHtml = sl_page_cache_get($__pageCacheKey, $settings['active_language'] ?? 'en', $__cachedStatus);
    if ($__cachedHtml !== null) {
        http_response_code($__cachedStatus);
        echo $__cachedHtml;
        exit;
    }
    ob_start();
}

$data = sl_build_data_array(['article', 'page', 'project'], false);
$GLOBALS['data'] = $data;

if (isset($data['content'])) {
  $data['content'] = stripslashes($data['content']);
}

$contentTypes = ["article", "page", "project"];
$uriParams = parseRequestUri();

pl_do_hook('after_routing', $uriParams['type'] === '404');

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

$_allowedTypes = array_merge($contentTypes, ['tag', 'category', '404']);
$type     = isset($_GET['type']) ? (in_array($_GET['type'], $_allowedTypes, true) ? $_GET['type'] : '') : '';
$slug     = isset($_GET['slug'])     ? basename(preg_replace('/[^\p{L}\p{N}\-_\/]/u', '', $_GET['slug'])) : '';
$category = isset($_GET['category']) ? preg_replace('/[^\p{L}\p{N}\-_]/u', '', $_GET['category']) : '';
$tag      = isset($_GET['tag'])      ? preg_replace('/[^\p{L}\p{N}\-_]/u', '', $_GET['tag'])      : '';

$_isDraftPreview = false;

if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	$_fullItem = sl_load_item_by_slug($type, $slug);
	if ($_fullItem === null && sl_admin_preview_session_active()) {
		$_fullItem = sl_load_item_by_slug_unfiltered($type, $slug);
		if ($_fullItem !== null) {
			$_isDraftPreview = true;
		}
	}
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

$_draftPreviewBanner = '';
if ($_isDraftPreview) {
	$_dpStatusKey = $_fullItem['status'] ?? 'draft';
	$_dpStatusLabel = __t('status_' . $_dpStatusKey, ucfirst($_dpStatusKey));
	$_draftPreviewBanner = '
<style>
  #draft-preview-banner {
	position:fixed;bottom:0;left:0;right:0;z-index:2147483646;
	background:#3d2800;color:#fde68a;
	font-family:system-ui,sans-serif;font-size:13px;
	padding:10px 20px;display:flex;align-items:center;gap:16px;
	border-top:2px solid #f59e0b;box-shadow:0 -4px 20px rgba(0,0,0,.5);
  }
  #draft-preview-banner .dpb-badge {
	background:#f59e0b;color:#1a1300;font-weight:700;font-size:11px;
	padding:3px 8px;border-radius:3px;letter-spacing:.08em;
	text-transform:uppercase;white-space:nowrap;flex-shrink:0;
  }
  #draft-preview-banner .dpb-label { flex:1; }
  body { padding-bottom:56px !important; }
</style>
<div id="draft-preview-banner">
  <span class="dpb-badge">' . htmlspecialchars($_dpStatusLabel) . '</span>
  <span class="dpb-label">' . __t('draft_preview_notice', 'You are the only one who can see this — visitors get a 404 until it is published.') . '</span>
</div>';
}

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

$GLOBALS['pageContent'] = $pageContent;

http_response_code($httpStatus);
$seoData = generateSEO($pageTitle, $type, $slug, $data, $settings);
$metaTitle = $seoData['title'];
$metaDescription = $seoData['description'];

$requiredGalleryScripts = [];
$galleryLayouts = [];

$metaKeywords = '';
$ogImage = '';
$ogTitle = '';
$ogDescription = '';

if (empty($type) && empty($slug) && ($settings['homepage_type'] ?? 'default') === 'default') {
	$metaKeywords  = $settings['home_meta_keywords']  ?? '';
	$ogTitle       = !empty($settings['home_og_title'])       ? $settings['home_og_title']       : $metaTitle;
	$ogDescription = !empty($settings['home_og_description']) ? $settings['home_og_description'] : $metaDescription;
	$ogImage       = !empty($settings['home_og_image'])       ? getBaseUrl() . $settings['home_og_image'] : '';
}

if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	foreach ($data[$type] as $item) {
		$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		if ($itemSlug === $slug) {
			$metaKeywords = $item['meta_keywords'] ?? '';
			$ogImage = !empty($item['og_image']) ? getBaseUrl() . $item['og_image'] :
			   (!empty($item['image']) ? getBaseUrl() . $item['image'] : '');
			$ogTitle = $item['og_title'] ?? $metaTitle;
			$ogDescription = $item['og_description'] ?? $metaDescription;
			break;
		}
	}
}

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

foreach ($galleryLayouts as $layout) {
	$layoutScripts = getGalleryScripts($layout);
	$requiredGalleryScripts = array_merge($requiredGalleryScripts, $layoutScripts);
}

if (!empty($galleryLayouts)) {
	enqueue_js('lightbox', 'assets/js/features/lightbox.js');
}

$requiredGalleryScripts = array_unique($requiredGalleryScripts);
$headerScripts = array_values(array_unique($requiredGalleryScripts));
$_schemaJsonld = render_schema_jsonld($settings, $type, $slug, $data);
if ($_schemaJsonld !== '') {
	$headerScripts[] = $_schemaJsonld;
}
unset($_schemaJsonld);
$headerScripts[] = '	<script type="application/json" id="cms-appsettings-json">'
	. json_encode(['showSearchIcon' => isset($settings["show_search_icon"]) && $settings["show_search_icon"]])
	. '</script>';

$isAdminLoggedIn = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$_adminBarHtml   = '';
if ($isAdminLoggedIn) {
	$_adminDir  = resolve_admin_dir();
	$_adminBase = getBaseUrl() . $_adminDir;

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

	$_ctxLabel     = '';
	$_ctxHref      = '';
	$_newLabel     = '';
	$_newHref      = '';
	$_listLink     = '';
	$_showSettings = true;

	if (!empty($type) && !empty($slug)) {
		$_singleType = in_array($type, $contentTypes) ? $type : rtrim($type, 's');
		if (in_array($_singleType, $contentTypes)) {
			$_rawIndex   = json_decode(file_get_contents(CMS_ROOT . '/data/' . $_singleType . 's/_index.json'), true) ?? [];
			$_adminIndex = null;
			foreach ($_rawIndex as $_rawPos => $_rawEntry) {
				if (sl_effective_slug($_rawEntry) === $slug) { $_adminIndex = $_rawPos; break; }
			}
			if ($_adminIndex !== null) {
				$_ctxLabel = $_adminLang['edit']                ?? 'Edit';
				$_ctxHref  = $_adminBase . '/index.php?action=edit&type=' . $_singleType . '&index=' . $_adminIndex;
				$_newLabel = $_adminLang['new_' . $_singleType] ?? ('New ' . $_singleType);
				$_newHref  = $_adminBase . '/index.php?action=add&type=' . $_singleType;
				$_listLink = $_adminBase . '/index.php?type=' . $_singleType;
			}
		}
	} elseif (!empty($type) && empty($slug) && in_array(rtrim($type, 's'), $contentTypes)) {
		$_listType     = rtrim($type, 's');
		$_ctxLabel     = $_adminLang['manage']                  ?? 'Manage';
		$_ctxHref      = $_adminBase . '/index.php?type=' . $_listType;
		$_newLabel     = $_adminLang['new_' . $_listType]       ?? ('New ' . $_listType);
		$_newHref      = $_adminBase . '/index.php?action=add&type=' . $_listType;
		$_showSettings = false;
	}

	$_s  = '	<style>';
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
	$headerScripts[] = $_s;

	$_ico_home = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
	$_ico_list = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
	$_ico_edit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
	$_ico_new  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
	$_ico_cog  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l-.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';

	$_adminBarHtml  = '<div id="snk-admin-bar">';
	$_adminBarHtml .= '<a class="snk-ab-site" href="' . htmlspecialchars($_adminBase . '/index.php') . '">' . $_ico_home . htmlspecialchars($settings['site_title'] ?? 'SynaptikCMS') . '</a>';
	$_adminBarHtml .= '<div class="snk-ab-divider"></div>';

	if (!empty($_listLink)) {
		$_adminBarHtml .= '<a href="' . htmlspecialchars($_listLink) . '">' . $_ico_list . htmlspecialchars($_adminLang[rtrim($type, 's') . 's'] ?? ucfirst($type)) . '</a>';
	}

	if (!empty($_ctxLabel) && !empty($_ctxHref)) {
		$_adminBarHtml .= '<a class="snk-ab-ctx" href="' . htmlspecialchars($_ctxHref) . '">' . $_ico_edit . htmlspecialchars($_ctxLabel) . '</a>';
	}

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

if (!empty($_adminBarHtml) && empty($GLOBALS['_adminBarHtml_emitted'])) {
	echo $_adminBarHtml;
}

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

$displayTitle = true; // Default to true for non-content pages
if (empty($type) && empty($slug) && isset($settings['homepage_type']) && $settings['homepage_type'] === 'page' && !empty($settings['homepage_page_id'])) {
	foreach ($data['page'] as $item) {
		$pageSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		if ($pageSlug === $settings['homepage_page_id']) {
			$displayTitle = isset($item['show_title']) ? $item['show_title'] : false;
			break;
		}
	}
}
else if (!empty($type) && !empty($slug) && in_array($type, $contentTypes)) {
	foreach ($data[$type] as $item) {
		$itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
		
		if ($itemSlug === $slug) {
			$displayTitle = isset($item['show_title']) ? $item['show_title'] : false;
			break;
		}
	}
}

echo $pageContent;

loadThemeTemplate('footer', [
	'settings' => $settings,
	'data' => $data,
	'currentYear' => date("Y"),
	'baseUrl' => getBaseUrl()
]);

if (!empty($_themePreviewBanner)) {
	echo $_themePreviewBanner;
}
if (!empty($_draftPreviewBanner)) {
	echo $_draftPreviewBanner;
}

if ($__pageCacheEligible) {
	$__pageHtml = ob_get_clean();
	echo $__pageHtml;
	sl_page_cache_set($__pageCacheKey, $settings['active_language'] ?? 'en', $httpStatus, $__pageHtml);
}