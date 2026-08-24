<?php
/**
 * Page Renderers — SynaptikCMS
 * SEO, header scripts, search UI, content metadata, footer,
 * theme utilities, site logo/favicon, and social icons.
 */

/**
 * Returns an <img> tag for the site logo, or empty string when none is configured.
 */
function render_site_logo(array $settings, string $class = '', string $alt = ''): string
{
    $path = trim($settings['site_logo'] ?? '');
    if ($path === '') return '';
    $url = getBaseUrl() . ltrim($path, '/');
    $cls = $class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '';
    $a   = htmlspecialchars($alt !== '' ? $alt : ($settings['site_title'] ?? ''));
    return '<img src="' . htmlspecialchars($url) . '" alt="' . $a . '"' . $cls . '>';
}

/**
 * Returns the <link rel="icon"> tag for the site favicon, or empty string when none is configured.
 */
function render_site_favicon(array $settings): string
{
    $path = trim($settings['site_favicon'] ?? '');
    if ($path === '') return '';
    $url     = getBaseUrl() . ltrim($path, '/');
    $ext     = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = [
        'ico'  => 'image/x-icon', 'svg'  => 'image/svg+xml',
        'png'  => 'image/png',    'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mime = $mimeMap[$ext] ?? 'image/x-icon';
    return '<link rel="icon" type="' . $mime . '" href="' . htmlspecialchars($url) . '">' . "\n";
}

/**
 * Returns the site title string for use in the header.
 */
function render_site_title($settings, $pageTitle)
{
    if ($settings['show_site_title_in_header']) {
        return htmlspecialchars($settings['site_title']);
    }
    return $pageTitle === 'Welcome to SynaptikCMS' ? $pageTitle : $settings['site_title'];
}

/**
 * Returns the <h1> title for a content item, or empty string if disabled.
 */
function render_content_title($item)
{
    if (!isset($item['show_title']) || $item['show_title']) {
        return '<h1 class="content-title">' . htmlspecialchars($item['title']) . '</h1>';
    }
    return '';
}

/**
 * Generates all SEO meta tags: description, OG, canonical.
 */
function render_meta_tags($settings, $metaTitle, $metaDescription, $pageData = null)
{
    global $metaKeywords, $ogImage, $ogTitle, $ogDescription;
    $f = ENT_COMPAT | ENT_HTML5;
    $c = 'UTF-8';
    ob_start(); ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($settings['site_title'], $f, $c); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, $f, $c); ?>">
<?php if (!empty($metaKeywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords, $f, $c); ?>">
<?php endif; ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle ? $ogTitle : $metaTitle, $f, $c); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription ? $ogDescription : $metaDescription, $f, $c); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars(getBaseUrl() . ltrim($_SERVER['REQUEST_URI'], '/'), $f, $c); ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?php echo htmlspecialchars($ogImage, $f, $c); ?>">
<?php endif; ?>
<?php if ($settings['enable_seo']): ?>
    <?php
        global $type, $slug, $category, $tag;
        $_type     = $type     ?? '';
        $_slug     = $slug     ?? '';
        $_category = $category ?? '';
        $_tag      = $tag      ?? '';

        // Archive pages (tag pages and content-type list pages like /articles/,
        // /projects/, /pages/) are noindexed to keep Google focused on real
        // content. They are thin listings that duplicate their child items,
        // so indexing them dilutes the search index. "follow" lets Google
        // still crawl the links to reach the real articles/pages/projects.
        // Category pages are intentionally left indexed — they often carry
        // unique intro text and group related content in a way that has SEO
        // value. Must be paired with NOT blocking these pages in robots.txt,
        // or Google can never crawl to see this tag and drop them from index.
        $_isArchive = false;
        if ($_type === 'tag' && !empty($_tag)) {
            $_isArchive = true;
        } elseif (!empty($_type) && empty($_slug) && empty($_category) && empty($_tag)
                  && !in_array($_type, ['home', '404'], true)) {
            $_isArchive = true;
        }
        if ($_isArchive) {
            echo '<meta name="robots" content="noindex, follow">';
        }

        // Canonical URL — built from the same routing logic used for internal
        // links (cleanUrl), instead of $_SERVER['REQUEST_URI']. REQUEST_URI
        // varies with/without trailing slash and query strings, which causes
        // each variant to self-canonicalize — a duplicate content signal that
        // confuses Google ("chose different canonical than user"). cleanUrl()
        // always returns a single normalized form (trailing slash, no query
        // string) matching the URLs used in nav.
        if ($pageData && !empty($pageData['canonical_url'])) {
            echo '<link rel="canonical" href="' . htmlspecialchars($pageData['canonical_url'], $f, $c) . '">';
        } else {
            if ($_type === 'category' && !empty($_category)) {
                $_canonical = cleanUrl('category', null, null, $_category);
            } elseif ($_type === 'tag' && !empty($_tag)) {
                $_canonical = cleanUrl('tag', null, null, $_tag);
            } elseif (!empty($_type) && !empty($_slug)) {
                $_canonical = cleanUrl($_type, $_slug, null, $_category);
            } elseif (!empty($_type)) {
                // Content type list (e.g. /articles/)
                $_canonical = cleanUrl($_type);
            } else {
                $_canonical = cleanUrl('home');
            }

            echo '<link rel="canonical" href="' . htmlspecialchars($_canonical, $f, $c) . '">';
        } ?>
<?php endif; ?>
<?php
    // Plugin filter: lets a plugin add or adjust <head> meta output (e.g.
    // an analytics <meta> tag, an extra OG property) without patching core.
    return pl_apply_filter('head_meta_tags', ob_get_clean(), $pageData);
}

/**
 * Generates a Schema.org JSON-LD <script> block for a single content item.
 * Returns an empty string when schema_type is not set, SEO is disabled, or
 * the page is not a single-item view (list pages, homepage, category/tag pages).
 *
 * @param  array  $settings  Site config (from loadConfig()).
 * @param  string $type      Routing type (article, page, project).
 * @param  string $slug      Item slug (empty for non-single-item pages).
 * @param  array  $data      Full data array.
 * @return string            <script type="application/ld+json">…</script> or ''.
 */
function render_schema_jsonld(array $settings, string $type, string $slug, array $data): string
{
    if (empty($settings['enable_seo']) || empty($type) || empty($slug)) return '';

    $contentTypes = ['article', 'page', 'project'];
    if (!in_array($type, $contentTypes, true)) return '';

    $item = null;
    foreach ($data[$type] ?? [] as $candidate) {
        $effectiveSlug = !empty($candidate['custom_slug']) ? $candidate['custom_slug'] : ($candidate['slug'] ?? '');
        if ($effectiveSlug === $slug) { $item = $candidate; break; }
    }
    if ($item === null) return '';

    $schemaType = trim($item['schema_type'] ?? '');
    if ($schemaType === '') return '';

    $allowedTypes = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'CreativeWork'];
    if (!in_array($schemaType, $allowedTypes, true)) return '';

    $base        = getBaseUrl();
    $siteTitle   = $settings['site_title']       ?? '';
    $authorName  = trim($settings['schema_author_name'] ?? '');
    $pubType     = in_array($settings['schema_publisher_type'] ?? '', ['Person', 'Organization'], true)
                     ? $settings['schema_publisher_type'] : 'Person';

    // Item-level author name (display name stored at save time) overrides the global default
    if (!empty($item['author_name'])) {
        $authorName = $item['author_name'];
    } elseif ($authorName === '') {
        $authorName = $siteTitle;
    }

    $effectiveSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
    if ($type === 'page') {
        $itemUrl = $base . $effectiveSlug . '/';
    } else {
        $itemUrl = cleanUrl($type, $effectiveSlug, null, $item['category'] ?? null);
    }

    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => $schemaType,
        'headline' => $item['title'] ?? '',
        'url'      => $itemUrl,
    ];

    if (!empty($item['date'])) {
        $ts = strtotime($item['date']);
        if ($ts !== false) $ld['datePublished'] = date('Y-m-d', $ts);
    }
    if (!empty($item['last_modified'])) {
        $ts = strtotime($item['last_modified']);
        if ($ts !== false) $ld['dateModified'] = date('Y-m-d', $ts);
    }
    if (!empty($item['image'])) {
        $ld['image'] = $base . ltrim($item['image'], '/');
    }
    if (!empty($item['meta_description'])) {
        $ld['description'] = $item['meta_description'];
    } elseif (!empty($item['summary'])) {
        $ld['description'] = $item['summary'];
    } elseif (!empty($item['description'])) {
        $ld['description'] = $item['description'];
    }
    if ($authorName !== '') {
        $ld['author'] = ['@type' => 'Person', 'name' => $authorName];
    }
    // Publisher is always the site entity, typed per the global setting
    $publisher = ['@type' => $pubType, 'name' => $siteTitle];
    if (!empty($settings['site_logo'])) {
        $publisher['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $base . ltrim($settings['site_logo'], '/'),
        ];
    }
    $ld['publisher'] = $publisher;

    $json = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return '    <script type="application/ld+json">' . $json . '</script>';
}

/**
 * Emits the admin top bar HTML if an admin session is active.
 * Call once as the first child of <body> in theme header.php files.
 * No-op when no admin is logged in.
 */
function render_adminbar(): void
{
    if (!empty($GLOBALS['_adminBarHtml'])) {
        echo $GLOBALS['_adminBarHtml'];
        $GLOBALS['_adminBarHtml_emitted'] = true;
    }
}

/**
 * Returns a cache-busting query string for a file based on its mtime.
 * Produces ?v=<mtime> so the URL changes whenever the file is modified,
 * invalidating the browser cache automatically without manual versioning.
 *
 * @param  string $absPath  Absolute server path to the file.
 * @return string           Query string, e.g. '?v=1715000000', or '' if file missing.
 */
function _asset_version(string $absPath): string
{
    return file_exists($absPath) ? '?v=' . filemtime($absPath) : '';
}

/**
 * Injects all system and theme assets into the <head>.
 * Includes synaptikCSS.php, theme style.css, main.js, theme script.js, and the i18n bridge.
 * Every local asset URL carries a ?v=<mtime> cache-busting parameter so that
 * the 1-year browser cache is invalidated automatically when the file changes.
 * Call exactly once in header.php.
 */
function render_header_scripts($headerScripts)
{
    if (!is_array($headerScripts)) {
        $headerScripts = [];
    }
    $base     = getBaseUrl();
    $settings = loadConfig();
    $theme    = $settings['active_theme'] ?? 'default';
    $root     = CMS_ROOT;

    // synaptikCSS.php is served `immutable` for a year, so without a version in
    // the URL an edit to any bundled stylesheet never reaches a returning
    // visitor — `immutable` tells the browser not to revalidate, which also
    // makes the bundle's own ETag unreachable. _asset_version() can't be used
    // here: the bundle's content comes from the files it concatenates, and
    // editing one of those leaves synaptikCSS.php's own mtime untouched. Take
    // the newest of the bundled sources instead — the same value the bundle
    // derives its ETag from, so URL and payload can never disagree.
    $sysCssV = 0;
    foreach (['search.css', 'shortcodes.css', 'gallery-layout.css'] as $_f) {
        $_p = $root . '/assets/css/' . $_f;
        if (file_exists($_p)) $sysCssV = max($sysCssV, filemtime($_p));
    }
    $sysCssV = $sysCssV ? '?v=' . $sysCssV : '';

    $system = [
        '<base href="' . htmlspecialchars($base) . '">',
        '    <meta name="generator" content="SynaptikCMS — https://synaptikcms.com">',
        // window.CMS_BASE_URL / window.CMS_LANG are read by main.js and theme
        // script.js. Emitted as inert JSON islands (not an executable inline
        // <script>) + front-boot.js, which assigns them — keeps script-src free
        // of 'unsafe-inline'. front-boot.js is loaded WITHOUT `defer` so it runs
        // before main.js / theme script.js, which are both deferred.
        '    <script type="application/json" id="cms-base-json">' . json_encode($base) . '</script>',
        '    <script type="application/json" id="cms-lang-json">' . lang_js_bridge() . '</script>',
        '    <script src="' . $base . 'assets/js/front-boot.js'
            . _asset_version($root . '/assets/js/front-boot.js') . '"></script>',
        // synaptikCSS.php bundles search/shortcodes/gallery CSS — none of these
        // are critical above-the-fold on most pages, so load it non-blocking.
        // The `media="print"` trick lets the browser fetch it without blocking
        // render; css-async.js swaps the media back to `all` once downloaded.
        // The <noscript> fallback keeps the styles available for no-JS visitors.
        '    <link rel="stylesheet" class="cms-async-css" href="' . $base . 'assets/css/synaptikCSS.php' . $sysCssV . '" media="print">',
        '    <script src="' . $base . 'assets/js/css-async.js'
            . _asset_version($root . '/assets/js/css-async.js') . '"></script>',
        '    <noscript><link rel="stylesheet" href="' . $base . 'assets/css/synaptikCSS.php' . $sysCssV . '"></noscript>',
        '    <link rel="stylesheet" href="' . $base . 'theme/' . $theme . '/css/style.css'
            . _asset_version($root . '/theme/' . $theme . '/css/style.css') . '">',
        // main.js is deferred so it never blocks HTML parsing — it runs after
        // the DOM is parsed but before DOMContentLoaded, preserving execution order.
        '    <script defer src="' . $base . 'assets/js/main.js'
            . _asset_version($root . '/assets/js/main.js') . '"></script>',
        '    <link rel="alternate" type="application/rss+xml" title="'
            . htmlspecialchars($settings['site_title'] ?? 'RSS Feed')
            . '" href="' . $base . 'core/feed.php">',
    ];

    $themeScript = [];
    $themeScriptPath = $root . '/theme/' . $theme . '/js/script.js';
    if (file_exists($themeScriptPath)) {
        $themeScript[] = '    <script defer src="' . $base . 'theme/' . $theme . '/js/script.js'
            . _asset_version($themeScriptPath) . '"></script>';
    }

    $hljs = render_hljs_scripts($base, $root, $theme);

    return implode("\n", array_merge($system, $hljs, $headerScripts, $themeScript)) . "\n";
}

/**
 * Conditionally emits highlight.js (core + the languages SynaptikCMS content
 * can produce a fenced code block in) plus its own init script — only when
 * the current page's actual rendered HTML contains a code block, so pages
 * without one carry zero extra weight. $pageContent is mirrored into
 * $GLOBALS by index.php once the page body is fully rendered, since this
 * function runs from a separate scope (reached via loadThemeTemplate()).
 *
 * @return string[] `<script>` tag lines, or [] when nothing is needed.
 */
function render_hljs_scripts(string $base, string $root, string $theme): array
{
    global $pageContent;
    if (empty($pageContent) || stripos($pageContent, 'language-') === false) {
        return [];
    }

    // Pinned to the same highlight.js version + CDN the synaptik-docs theme
    // already used, with real SRI hashes (computed from the exact files
    // this loads, not guessed). One file per language render_content_html()
    // can actually tag a fenced code block with — see core/render/tf-markdown.php.
    $cdnBase = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/';
    $files = [
        'highlight.min.js'           => 'sha384-F/bZzf7p3Joyp5psL90p/p89AZJsndkSoGwRpXcZhleCWhd8SnRuoYo4d0yirjJp',
        'languages/xml.min.js'        => 'sha384-jgkY4GMNWfQcLLIoP1vg3FWXflDrRhcSXGBW6ONIWC2SOIv5H1Pa57sXs+aomCuZ',
        'languages/javascript.min.js' => 'sha384-44q2s9jxk8W5N9gAB0yn7UYLi9E2oVw8eHyaTZLkDS3WuZM/AttkAiVj6JoZuGS4',
        'languages/css.min.js'        => 'sha384-EO/jSdjpPTVnKfaKudbmOTZBYv471ACLOeLdy0zhCEJrQAtjgUSCbn/Cc+9QWjIx',
        'languages/php.min.js'        => 'sha384-c1RNlWYUPEU/QhgCUumvQSdSFaq+yFhv3VfGTG/OTh8oirAi/Jnp6XbnqOLePgjg',
        'languages/python.min.js'     => 'sha384-FWJTgPmIGm1+zpNhubuHRC/ulS1UK7hAZ7qiUUmKD8yGfPcn5ZXYBd2qRgi6L8Tu',
        'languages/sql.min.js'        => 'sha384-8q00eP+tyV9451aJYD5ML3ftuHKsGnDcezp7EXMEclDg1fZVSoj8O+3VyJTkXmWp',
        'languages/bash.min.js'       => 'sha384-OqfnIfpOmAlErKRs2a525ZcwLuZ8Z1x8hPdIwBcMtHkJBbHnpJ6wX5/LVzASHkD5',
        'languages/json.min.js'       => 'sha384-RbRhXcXx5VHUdUaC5R0oV+XBXA5GhkaVCUzK8xN19K3FmtWSHyGVgulK92XnhBsI',
    ];

    $tags = [];

    // highlight.js only adds .hljs-* class names — it ships no colors of its
    // own. Skip the shared fallback theme for any theme that already styles
    // .hljs itself (synaptik-docs does, with its own light/dark palette);
    // loading it there would just add rules its own CSS already overrides
    // less predictably, since the fallback loads after the theme stylesheet.
    $themeStylePath = $root . '/theme/' . $theme . '/css/style.css';
    $themeHasOwnHljsTheme = file_exists($themeStylePath)
        && str_contains(file_get_contents($themeStylePath), '.hljs');
    if (!$themeHasOwnHljsTheme) {
        $tags[] = '    <link rel="stylesheet" href="' . $base . 'assets/css/hljs-theme.css'
            . _asset_version($root . '/assets/css/hljs-theme.css') . '">';
    }

    foreach ($files as $path => $integrity) {
        $tags[] = '    <script defer src="' . $cdnBase . $path . '" integrity="' . $integrity . '" crossorigin="anonymous"></script>';
    }
    $tags[] = '    <script defer src="' . $base . 'assets/js/hljs-init.js'
        . _asset_version($root . '/assets/js/hljs-init.js') . '"></script>';

    return $tags;
}

/**
 * Renders the featured image block for a content item.
 */
function render_featured_image($item)
{
    if (!isset($item['image']) || (isset($item['show_featured_image']) && !$item['show_featured_image'])) {
        return '';
    }
    // No loading="lazy" here on purpose — this is a single content item's
    // one prominent image, almost always the page's LCP candidate, and
    // lazy-loading the LCP image delays it rather than speeding anything up.
    ob_start(); ?>
<div class="featured-image">
    <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars(!empty($item['image_alt']) ? $item['image_alt'] : $item['title']); ?>"<?php echo _image_dimensions_attr($item['image']); ?>>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the publication date for a content item.
 */
function render_content_date($item)
{
    if (!isset($item['date']) || !isset($item['show_date']) || !$item['show_date']) {
        return '';
    }
    return '<div class="article-date">' . htmlspecialchars(format_date($item['date'])) . '</div>';
}

/**
 * Renders the category badge link for a content item.
 * Category is stored as a slug; the display name is resolved from the categories store.
 * Handles legacy items that still store display strings via sanitizeSlug().
 */
function render_content_category($item)
{
    if (empty($item['category'])) {
        return '';
    }
    $catSlug  = sanitizeSlug($item['category']);
    if ($catSlug === '') return '';
    $catStore = sl_load_categories();
    $name     = $catStore[$catSlug]['name'] ?? $item['category'];
    ob_start(); ?>
<div class="article-category">
    <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . htmlspecialchars($catSlug); ?>/" class="category-badge">
        <?php echo htmlspecialchars($name); ?>
    </a>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the tag links for a content item.
 * Tags are stored as slugs; display names are resolved from the tags store.
 * Handles legacy items that still store display strings via sanitizeSlug().
 */
function render_content_tags($item)
{
    if (empty($item['tags']) || !is_array($item['tags'])) {
        return '';
    }
    $tagStore = sl_load_tags();
    ob_start(); ?>
<div class="article-tags">
    <?php foreach ($item['tags'] as $tagRaw):
        $tagSlug = sanitizeSlug($tagRaw);
        if ($tagSlug === '') continue;
        $name = $tagStore[$tagSlug]['name'] ?? $tagRaw;
    ?>
    <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . htmlspecialchars($tagSlug); ?>/" class="tag-link">
        <?php echo htmlspecialchars($name); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the legacy gallery attached to a content item.
 */
function render_content_gallery($item)
{
    if (empty($item['gallery']) || !is_array($item['gallery'])) {
        return '';
    }
    ob_start(); ?>
<div class="content-gallery">
    <h3><?php echo __t('gallery'); ?></h3>
    <?php echo renderGallery($item['gallery'], $item['gallery_layout'] ?? 'grid'); ?>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the footer text and optional social links.
 * Appends a "Powered by SynaptikCMS" attribution link — visible, crawlable,
 * and honest. Never hidden: hidden backlinks are a Google spam violation.
 * Uses the global $settings variable populated by index.php.
 */
function render_footer_content()
{
    global $settings;
    ob_start();

    echo '<p class="footer-text">';
    $footer_text = $settings['footer_text'] ?? 'Developed with ♥ • &copy; {year}';
    if (strpos($footer_text, '{year}') !== false) {
        $footer_text = str_replace('{year}', date('Y'), $footer_text);
    }
    echo $footer_text;

    if (!empty($settings['footer_show_login'])) {
        echo ' | <span><a target="_blank" href="' . getBaseUrl() . 'admin/auth.php">Login</a></span>';
    }
    echo '</p>';

    if (!empty($settings['footer_show_social']) && !empty($settings['footer_social_links'])) {
        echo '<div class="social-links">';
        foreach ($settings['footer_social_links'] as $social) {
            if (!empty($social['platform']) && !empty($social['url'])) {
                echo '<a href="' . htmlspecialchars($social['url']) . '" class="social-icon" target="_blank">'
                   . get_social_icon($social['platform']) . '</a>';
            }
        }
        echo '</div>';
    }

    // Attribution — visible, honest, crawlable. Do not hide with CSS.
    echo '<p class="snk-credit">Powered by <a href="https://synaptikcms.com" target="_blank" rel="noopener">SynaptikCMS</a></p>';

    return ob_get_clean();
}

/**
 * Returns an inline SVG icon for a social platform.
 * Supported: bluesky, discord, facebook, github, instagram, linkedin, mastodon,
 * pinterest, reddit, snapchat, telegram, threads, tiktok, twitch, twitter,
 * whatsapp, x, youtube. Falls back to a generic circle.
 */
function get_social_icon($platform)
{
    switch (strtolower($platform)) {
        case 'instagram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>';
        case 'twitter':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>';
        case 'x':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16M20 4L4 20"/></svg>';
        case 'github':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>';
        case 'facebook':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>';
        case 'linkedin':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
        case 'youtube':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-1.96C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.4 19.54C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98" fill="currentColor" stroke="none"/></svg>';
        case 'tiktok':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>';
        case 'discord':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/><path d="M18 5c1.5 3 2 6.5 1.5 8.5-.5 2-2 3.5-3.5 4L15 16M6 5C4.5 8 4 11.5 4.5 13.5c.5 2 2 3.5 3.5 4L9 16"/><path d="M9.5 16.5c.8.5 1.6.8 2.5.8s1.7-.3 2.5-.8"/></svg>';
        case 'whatsapp':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/><path d="M9.1 9a.5.5 0 0 1 .9 0l.5 1a5 5 0 0 0 3.5 3.5l1 .5a.5.5 0 0 1 0 .9l-.5.3a2 2 0 0 1-2 0 9 9 0 0 1-3.9-3.9 2 2 0 0 1 0-2z"/></svg>';
        case 'snapchat':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a6 6 0 0 0-6 6v4l-2 3h4a4 4 0 0 0 8 0h4l-2-3V8a6 6 0 0 0-6-6z"/></svg>';
        case 'pinterest':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 20l4-9"/><path d="M10.7 14c.437 1.263 1.43 2 2.55 2 2.071 0 3.75-1.554 3.75-4a5 5 0 1 0-9.999 0c0 1.993.583 3.092 2 4"/></svg>';
        case 'threads':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M5 12c0-7 14-7 14 0s-7 11-12 6"/></svg>';
        case 'twitch':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2H3v16l4-4h14V2z"/><line x1="9.5" y1="9" x2="9.5" y2="14"/><line x1="14.5" y1="9" x2="14.5" y2="14"/></svg>';
        case 'telegram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2" fill="currentColor" stroke="none"/></svg>';
        case 'reddit':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="7"/><circle cx="9.5" cy="12.5" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="12.5" r="1" fill="currentColor" stroke="none"/><path d="M9.5 16a5 5 0 0 0 5 0"/><path d="M12 6V4"/><circle cx="14" cy="4" r="1" fill="currentColor" stroke="none"/></svg>';
        case 'mastodon':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.94 11c.17-1.16.25-2.34.25-3.53 0-3.59-2.36-4.65-2.36-4.65-2.36-.97-6.46-.97-8.83 0 0 0-2.36 1.06-2.36 4.65 0 5.8.34 8.84 3.74 9.8 1.5.44 2.8.53 3.82.47 1.88-.11 2.94-.68 2.94-.68L18 15.5s-1.35.44-2.86.38c-1.5-.05-3.08-.16-3.31-2a4 4 0 0 1-.04-.53s1.47.37 3.33.46c1.14.05 2.21-.06 3.3-.2 2.08-.25 3.89-1.54 4.12-2.72.36-1.84.33-4.5.33-4.5"/><path d="M16.5 8v4M13.5 8v4"/></svg>';
        case 'bluesky':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9C9.5 6 7 4 5 4a4 4 0 0 0 0 8c2 0 4.5-2 7-3z"/><path d="M12 9c2.5-3 5-5 7-5a4 4 0 0 1 0 8c-2 0-4.5-2-7-3z"/><path d="M12 9v11"/></svg>';
        default:
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';
    }
}

/**
 * Returns the server path to a resource in the active theme folder.
 *
 * @param string $resource Resource subdirectory (e.g. 'css', 'js').
 * @param string $file     Filename within that subdirectory.
 * @return string Relative path from CMS root.
 */
function getThemeResourcePath($resource, $file = '')
{
    $theme = loadConfig()['active_theme'] ?? 'default';
    return "theme/{$theme}/{$resource}/{$file}";
}

/**
 * Loads a theme partial and returns its rendered HTML, or null if not found.
 *
 * @param string $name  Partial name without .php (e.g. 'article-card').
 * @param array  $vars  Variables to extract into the partial's scope.
 * @return string|null  Rendered HTML, or null — caller uses its own fallback.
 */
function loadThemePartial(string $name, array $vars = []): ?string
{
    $theme = loadConfig()['active_theme'] ?? 'default';
    $path  = CMS_ROOT . '/theme/' . $theme . '/partials/' . $name . '.php';
    if (!file_exists($path)) {
        return null;
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $path;
    return ob_get_clean();
}

/**
 * Renders the full search overlay HTML.
 * Always generated regardless of show_search_icon — ensures Ctrl+K works
 * even when the visible icon is disabled.
 *
 * Structure must stay in exact sync with the overlay assets/js/main.js builds
 * at runtime when a theme does NOT call this function — main.js's own result
 * rendering, loading indicator and CSS (assets/css/search.css) all target
 * .search-bar / .search-results / .search-loading / .search-results-content
 * by class name, regardless of which side created the surrounding markup. A
 * structural mismatch here means the overlay opens but silently never shows
 * results — see changelog.
 */
function render_search_ui()
{
    ob_start(); ?>
<div id="search-overlay" class="search-overlay">
    <button id="close-search" class="close-btn">&#215;</button>
    <div class="search-bar">
        <div class="search-input-container">
            <input type="text" id="search-input" placeholder="<?php echo htmlspecialchars(__t('search_placeholder')); ?>">
            <button class="search-clear-btn">&#215;</button>
        </div>
        <div class="search-options">
            <label><input type="checkbox" id="search-in-content"> <?php echo __t('search_in_content'); ?></label>
            <label><input type="checkbox" id="search-articles" checked> <?php echo __t('search_filter_articles'); ?></label>
            <label><input type="checkbox" id="search-pages"    checked> <?php echo __t('search_filter_pages'); ?></label>
            <label><input type="checkbox" id="search-projects" checked> <?php echo __t('search_filter_projects'); ?></label>
        </div>
    </div>
    <div class="search-results" id="search-results">
        <div class="search-loading" style="display: none;"><?php echo __t('search_loading'); ?></div>
        <div class="search-results-content"></div>
    </div>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the search icon <li> for the navigation.
 * Returns empty string when show_search_icon is false.
 */
function render_search_icon()
{
    global $appSettings;
    if (isset($appSettings['show_search_icon']) && !$appSettings['show_search_icon']) {
        return '';
    }
    ob_start(); ?>
<li class="search-icon">
    <a href="#" id="search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </a>
</li>
<?php
    return ob_get_clean();
}
