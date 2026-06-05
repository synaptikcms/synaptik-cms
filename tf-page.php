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
        if ($pageData && !empty($pageData['canonical_url'])) {
            echo '<link rel="canonical" href="' . htmlspecialchars($pageData['canonical_url'], $f, $c) . '">';
        } else {
            echo '<link rel="canonical" href="' . htmlspecialchars(getBaseUrl() . ltrim($_SERVER['REQUEST_URI'], '/'), $f, $c) . '">';
        } ?>
<?php endif; ?>
<?php
    return ob_get_clean();
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
    $settings = loadSettings();
    $theme    = $settings['active_theme'] ?? 'default';
    $root     = __DIR__;

    $system = [
        '<base href="' . htmlspecialchars($base) . '">',
        '    <link rel="stylesheet" href="' . $base . 'css/synaptikCSS.php">',
        '    <link rel="stylesheet" href="' . $base . 'theme/' . $theme . '/css/style.css'
            . _asset_version($root . '/theme/' . $theme . '/css/style.css') . '">',
        '    <script src="' . $base . 'js/main.js'
            . _asset_version($root . '/js/main.js') . '"></script>',
        '    <link rel="alternate" type="application/rss+xml" title="'
            . htmlspecialchars($settings['site_title'] ?? 'RSS Feed')
            . '" href="' . $base . 'feed.php">',
    ];

    $lang = '    <script>window.CMS_LANG = ' . lang_js_bridge() . ';</script>';

    $themeScript = [];
    $themeScriptPath = $root . '/theme/' . $theme . '/js/script.js';
    if (file_exists($themeScriptPath)) {
        $themeScript[] = '    <script defer src="' . $base . 'theme/' . $theme . '/js/script.js'
            . _asset_version($themeScriptPath) . '"></script>';
    }

    return implode("\n", array_merge($system, $headerScripts, $themeScript, [$lang])) . "\n";
}

/**
 * Renders the featured image block for a content item.
 */
function render_featured_image($item)
{
    if (!isset($item['image']) || (isset($item['show_featured_image']) && !$item['show_featured_image'])) {
        return '';
    }
    ob_start(); ?>
<div class="featured-image">
    <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
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
 */
function render_content_category($item)
{
    if (empty($item['category'])) {
        return '';
    }
    ob_start(); ?>
<div class="article-category">
    <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($item['category']); ?>/" class="category-badge">
        <?php echo htmlspecialchars($item['category']); ?>
    </a>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders the tag links for a content item.
 */
function render_content_tags($item)
{
    if (empty($item['tags']) || !is_array($item['tags'])) {
        return '';
    }
    ob_start(); ?>
<div class="article-tags">
    <?php foreach ($item['tags'] as $tag): ?>
    <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag); ?>/" class="tag-link">
        <?php echo htmlspecialchars($tag); ?>
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

    return ob_get_clean();
}

/**
 * Returns an inline SVG icon for a social platform.
 * Supported: instagram, twitter, github, facebook, linkedin. Falls back to a generic circle.
 */
function get_social_icon($platform)
{
    switch (strtolower($platform)) {
        case 'instagram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>';
        case 'twitter':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>';
        case 'github':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>';
        case 'facebook':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>';
        case 'linkedin':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
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
    $theme = loadSettings()['active_theme'] ?? 'default';
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
    $theme = loadSettings()['active_theme'] ?? 'default';
    $path  = __DIR__ . '/theme/' . $theme . '/partials/' . $name . '.php';
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
 */
function render_search_ui()
{
    ob_start(); ?>
<div id="search-overlay" class="search-overlay">
    <div class="search-container">
        <button id="close-search">&#215;</button>
        <div class="search-input-container">
            <input type="text" id="search-input" placeholder="<?php echo htmlspecialchars(__t('search_placeholder')); ?>">
            <button class="search-clear-btn">&#215;</button>
        </div>
        <div class="search-options">
            <label><input type="checkbox" id="search-in-content" checked> <?php echo __t('search_in_content'); ?></label>
            <label><input type="checkbox" id="search-articles"   checked> <?php echo __t('articles'); ?></label>
            <label><input type="checkbox" id="search-pages"      checked> <?php echo __t('pages'); ?></label>
            <label><input type="checkbox" id="search-projects"   checked> <?php echo __t('projects'); ?></label>
        </div>
        <div id="search-results"></div>
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
