<?php
require_once __DIR__ . '/tf-markdown.php';
require_once __DIR__ . '/tf-shortcodes.php';
require_once __DIR__ . '/tf-cards.php';
require_once __DIR__ . '/tf-navigation.php';
require_once __DIR__ . '/tf-page.php';
require_once __DIR__ . '/asset-registry.php';

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

function add_theme_action($hook_name, $function, $priority = 10) {
    global $theme_hooks;
    if (!isset($theme_hooks[$hook_name])) {
        $theme_hooks[$hook_name] = [];
    }
    $theme_hooks[$hook_name][$priority][] = $function;
    ksort($theme_hooks[$hook_name]);
    return true;
}

function do_theme_action($hook_name, $args = null) {
    global $theme_hooks;
    if (!isset($theme_hooks[$hook_name])) return;
    foreach ($theme_hooks[$hook_name] as $functions) {
        foreach ($functions as $function) {
            call_user_func($function, $args);
        }
    }
}

$theme_filters = [];

function add_theme_filter($hook_name, $function, $priority = 10) {
    global $theme_filters;
    if (!isset($theme_filters[$hook_name])) {
        $theme_filters[$hook_name] = [];
    }
    $theme_filters[$hook_name][$priority][] = $function;
    ksort($theme_filters[$hook_name]);
    return true;
}

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

$theme_options = [];

function set_theme_option($name, $value) {
    global $theme_options;
    $theme_options[$name] = $value;
}

function get_theme_option($name, $default = null) {
    global $theme_options;
    return $theme_options[$name] ?? $default;
}


// ─── Asset helpers ────────────────────────────────────────────────────────────
function add_theme_stylesheet($stylesheet) {
    add_theme_action('header_scripts', function() use ($stylesheet) {
        $settings = loadConfig();
        $theme    = $settings['active_theme'] ?? 'default';
        echo '<link rel="stylesheet" href="' . getBaseUrl() . 'theme/' . $theme . '/' . $stylesheet . '">';
    });
}

function add_theme_script($script, $in_footer = true) {
    $hook = $in_footer ? 'footer_scripts' : 'header_scripts';
    add_theme_action($hook, function() use ($script) {
        $settings = loadConfig();
        $theme    = $settings['active_theme'] ?? 'default';
        echo '<script src="' . getBaseUrl() . 'theme/' . $theme . '/' . $script . '"></script>';
    });
}

// ─── Page detection helpers ───────────────────────────────────────────────────
function is_home() {
    return empty($_GET['type']) && empty($_GET['slug']);
}

function is_current_page($type, $slug = '') {
    $current_type = $_GET['type'] ?? '';
    $current_slug = $_GET['slug'] ?? '';
    if (empty($slug)) return $current_type === $type;
    return $current_type === $type && $current_slug === $slug;
}