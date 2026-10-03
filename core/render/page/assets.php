<?php

function render_adminbar(): void
{
    if (!empty($GLOBALS['_adminBarHtml'])) {
        echo $GLOBALS['_adminBarHtml'];
        $GLOBALS['_adminBarHtml_emitted'] = true;
    }
}

function _asset_version(string $absPath): string
{
    return file_exists($absPath) ? '?v=' . filemtime($absPath) : '';
}

function render_header_scripts($headerScripts)
{
    if (!is_array($headerScripts)) {
        $headerScripts = [];
    }
    $base     = getBaseUrl();
    $settings = loadConfig();
    $theme    = $settings['active_theme'] ?? 'default';
    $root     = CMS_ROOT;

    $sysCssV = 0;
    foreach (['synaptikCSS.php', 'search.css'] as $_f) {
        $_p = $root . '/assets/css/' . $_f;
        if (file_exists($_p)) $sysCssV = max($sysCssV, filemtime($_p));
    }
    $sysCssV = $sysCssV ? '?v=' . $sysCssV : '';

    global $pageContent;
    $_conditionalCss = '';
    if (sl_content_needs_shortcode_assets($pageContent ?? '')) {
        $_conditionalCss .= '    <link rel="stylesheet" href="' . $base . 'assets/css/shortcodes.css'
            . _asset_version($root . '/assets/css/shortcodes.css') . '">' . "\n";
    }
    if (sl_content_needs_gallery_assets($pageContent ?? '')) {
        $_conditionalCss .= '    <link rel="stylesheet" href="' . $base . 'assets/css/gallery-layout.css'
            . _asset_version($root . '/assets/css/gallery-layout.css') . '">' . "\n";
    }

    $system = [
        '<base href="' . hsc($base) . '">',
        '    <meta name="generator" content="Synaptik CMS — https://synaptikcms.com">',
        '    <script type="application/json" id="cms-lang-json">' . lang_js_bridge() . '</script>',
        '    <script type="application/json" data-window-var="CMS_TYPE_LABELS">' . sl_type_labels_json() . '</script>',
        '    <script src="' . $base . 'assets/js/front-boot.js'
            . _asset_version($root . '/assets/js/front-boot.js') . '"></script>',
        '    <link rel="stylesheet" href="' . $base . 'assets/css/synaptikCSS.php' . $sysCssV . '">',
        rtrim($_conditionalCss),
        '    <link rel="stylesheet" href="' . $base . 'theme/' . $theme . '/css/style.css'
            . _asset_version($root . '/theme/' . $theme . '/css/style.css') . '">',
        '    <script defer src="' . $base . 'assets/js/features/search.js'
            . _asset_version($root . '/assets/js/features/search.js') . '"></script>',
        '    <link rel="alternate" type="application/rss+xml" title="'
            . hsc($settings['site_title'] ?? 'RSS Feed')
            . '" href="' . $base . 'core/feed.php">',
    ];

    $themeScript = [];
    $themeScriptPath = $root . '/theme/' . $theme . '/js/script.js';
    if (file_exists($themeScriptPath)) {
        $themeScript[] = '    <script defer src="' . $base . 'theme/' . $theme . '/js/script.js'
            . _asset_version($themeScriptPath) . '"></script>';
    }

    $customCssPath = $root . '/theme/child_theme/' . $theme . '/css/style.css';
    if (file_exists($customCssPath)) {
        $system[] = '    <link rel="stylesheet" href="' . $base . 'theme/child_theme/' . $theme . '/css/style.css'
            . _asset_version($customCssPath) . '">';
    }

    $customScriptPath = $root . '/theme/child_theme/' . $theme . '/js/script.js';
    if (file_exists($customScriptPath)) {
        $themeScript[] = '    <script defer src="' . $base . 'theme/child_theme/' . $theme . '/js/script.js'
            . _asset_version($customScriptPath) . '"></script>';
    }

    $hljs = render_hljs_scripts($base, $root, $theme);
    render_collapsibles_script();

    $rendered = implode("\n", array_merge($system, $hljs, $headerScripts, $themeScript)) . "\n";

    return $rendered . render_enqueued_assets();
}

function render_collapsibles_script(): void
{
    global $pageContent;
    if (empty($pageContent)) return;
    if (stripos($pageContent, 'c-col') === false && stripos($pageContent, 'tab-group') === false) return;
    enqueue_js('collapsibles', 'assets/js/features/collapsibles.js');
}

function render_hljs_scripts(string $base, string $root, string $theme): array
{
    global $pageContent;
    if (empty($pageContent) || stripos($pageContent, 'language-') === false) {
        return [];
    }

    $vendorRoot = $root . '/assets/vendor/hljs/';
    $vendorBase = $base . 'assets/vendor/hljs/';
    $files = [
        'highlight.min.js',
        'languages/xml.min.js',
        'languages/javascript.min.js',
        'languages/css.min.js',
        'languages/php.min.js',
        'languages/python.min.js',
        'languages/sql.min.js',
        'languages/bash.min.js',
        'languages/json.min.js',
    ];

    $tags = [];
    $themeStylePath = $root . '/theme/' . $theme . '/css/style.css';
    $themeHasOwnHljsTheme = file_exists($themeStylePath)
        && str_contains(file_get_contents($themeStylePath), '.hljs');
    if (!$themeHasOwnHljsTheme) {
        $tags[] = '    <link rel="stylesheet" href="' . $base . 'assets/css/hljs-theme.css'
            . _asset_version($root . '/assets/css/hljs-theme.css') . '">';
    }

    foreach ($files as $path) {
        $tags[] = '    <script defer src="' . $vendorBase . $path . _asset_version($vendorRoot . $path) . '"></script>';
    }
    $tags[] = '    <script defer src="' . $base . 'assets/js/hljs-init.js'
        . _asset_version($root . '/assets/js/hljs-init.js') . '"></script>';

    return $tags;
}

