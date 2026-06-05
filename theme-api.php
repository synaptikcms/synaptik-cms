<?php
/**
 * Theme API — SynaptikCMS
 * Loaded automatically via functions.php → theme-api.php.
 * Includes the five tf-*.php template modules, then defines hooks, filters,
 * theme options, asset helpers, page detection, and pagination.
 */

require_once __DIR__ . '/tf-markdown.php';
require_once __DIR__ . '/tf-shortcodes.php';
require_once __DIR__ . '/tf-cards.php';
require_once __DIR__ . '/tf-navigation.php';
require_once __DIR__ . '/tf-page.php';

// ─── Hooks storage ────────────────────────────────────────────────────────────

$theme_hooks = [
    'before_header'  => [],
    'after_header'   => [],
    'before_content' => [],
    'after_content'  => [],
    'before_footer'  => [],
    'after_footer'   => [],
    'header_scripts' => [],
    'footer_scripts' => [],
];

/**
 * Register a callback on a named hook.
 *
 * @param string   $hook_name Hook identifier (see list above)
 * @param callable $function  Callback to register
 * @param int      $priority  Execution order — lower runs first (default 10)
 * @return bool
 */
function add_theme_action($hook_name, $function, $priority = 10) {
    global $theme_hooks;
    if (!isset($theme_hooks[$hook_name])) {
        $theme_hooks[$hook_name] = [];
    }
    $theme_hooks[$hook_name][$priority][] = $function;
    ksort($theme_hooks[$hook_name]);
    return true;
}

/**
 * Execute all callbacks registered on a hook.
 *
 * @param string $hook_name Hook identifier
 * @param mixed  $args      Optional argument passed to each callback
 */
function do_theme_action($hook_name, $args = null) {
    global $theme_hooks;
    if (!isset($theme_hooks[$hook_name])) return;
    foreach ($theme_hooks[$hook_name] as $functions) {
        foreach ($functions as $function) {
            call_user_func($function, $args);
        }
    }
}


// ─── Filters storage ──────────────────────────────────────────────────────────

$theme_filters = [];

/**
 * Register a filter callback.
 *
 * @param string   $hook_name Filter identifier
 * @param callable $function  Callback — receives current value, must return value
 * @param int      $priority  Execution order (default 10)
 * @return bool
 */
function add_theme_filter($hook_name, $function, $priority = 10) {
    global $theme_filters;
    if (!isset($theme_filters[$hook_name])) {
        $theme_filters[$hook_name] = [];
    }
    $theme_filters[$hook_name][$priority][] = $function;
    ksort($theme_filters[$hook_name]);
    return true;
}

/**
 * Pass $content through all filters registered on $hook_name.
 *
 * @param string $hook_name Filter identifier
 * @param mixed  $content   Value to filter
 * @return mixed Filtered value
 */
function apply_theme_filters($hook_name, $content) {
    global $theme_filters;
    if (!isset($theme_filters[$hook_name])) return $content;
    foreach ($theme_filters[$hook_name] as $functions) {
        foreach ($functions as $function) {
            $content = call_user_func($function, $content);
        }
    }
    return $content;
}


// ─── Theme options ────────────────────────────────────────────────────────────

$theme_options = [];

/**
 * Store a theme option for the current request.
 */
function set_theme_option($name, $value) {
    global $theme_options;
    $theme_options[$name] = $value;
}

/**
 * Retrieve a theme option (returns $default if not set).
 */
function get_theme_option($name, $default = null) {
    global $theme_options;
    return $theme_options[$name] ?? $default;
}


// ─── Asset helpers ────────────────────────────────────────────────────────────

/**
 * Enqueue a CSS file from the active theme folder.
 * Called from theme/active/functions.php or any template.
 *
 * @param string $stylesheet Path relative to theme root (e.g. 'css/custom.css')
 */
function add_theme_stylesheet($stylesheet) {
    add_theme_action('header_scripts', function() use ($stylesheet) {
        $settings = loadSettings();
        $theme    = $settings['active_theme'] ?? 'default';
        echo '<link rel="stylesheet" href="' . getBaseUrl() . 'theme/' . $theme . '/' . $stylesheet . '">';
    });
}

/**
 * Enqueue a JS file from the active theme folder.
 *
 * @param string $script    Path relative to theme root (e.g. 'js/animations.js')
 * @param bool   $in_footer true → footer_scripts hook; false → header_scripts hook
 */
function add_theme_script($script, $in_footer = true) {
    $hook = $in_footer ? 'footer_scripts' : 'header_scripts';
    add_theme_action($hook, function() use ($script) {
        $settings = loadSettings();
        $theme    = $settings['active_theme'] ?? 'default';
        echo '<script src="' . getBaseUrl() . 'theme/' . $theme . '/' . $script . '"></script>';
    });
}


// ─── Page detection helpers ───────────────────────────────────────────────────

/**
 * Returns true if the current page is the homepage.
 */
function is_home() {
    return empty($_GET['type']) && empty($_GET['slug']);
}

/**
 * Returns true if the current page matches the given type and optional slug.
 *
 * @param string $type Content type (article, page, project, category, tag)
 * @param string $slug Optional slug to match
 */
function is_current_page($type, $slug = '') {
    $current_type = $_GET['type'] ?? '';
    $current_slug = $_GET['slug'] ?? '';
    if (empty($slug)) return $current_type === $type;
    return $current_type === $type && $current_slug === $slug;
}


// ─── Pagination ───────────────────────────────────────────────────────────────

/**
 * Generates HTML pagination links for a content list.
 *
 * @param int    $total_items    Total number of items
 * @param int    $items_per_page Items per page
 * @param int    $current_page   Current active page
 * @param string $type           Content type used for URL generation
 * @return string HTML pagination block, or empty string if only one page
 */
function get_pagination($total_items, $items_per_page, $current_page, $type) {
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages <= 1) return '';

    $output = '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $output .= '<span class="current-page">' . $i . '</span>';
        } else {
            $output .= '<a href="' . cleanUrl($type, '', $i) . '" class="page-link">' . $i . '</a>';
        }
    }
    $output .= '</div>';
    return $output;
}