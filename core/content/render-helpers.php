<?php

function resolve_admin_dir(): string
{
    if (isset($GLOBALS['_resolved_admin_dir'])) {
        return $GLOBALS['_resolved_admin_dir'];
    }

    // 1. config.json
    if (function_exists('loadConfig')) {
        $s = loadConfig();
        $fromSettings = rtrim($s['admin_dir'] ?? '', '/');
        if ($fromSettings !== '' && is_dir(CMS_ROOT . '/' . $fromSettings)) {
            $GLOBALS['_resolved_admin_dir'] = $fromSettings;
            return $fromSettings;
        }
    }

    // 2. Filesystem scan
    foreach (glob(CMS_ROOT . '/*/auth.php') ?: [] as $f) {
        $found = basename(dirname($f));
        $GLOBALS['_resolved_admin_dir'] = $found;
        return $found;
    }

    // 3. Default
    $GLOBALS['_resolved_admin_dir'] = 'admin';
    return 'admin';
}

function getBreadcrumbs($type, $slug = '', $title = '', $category = '')
{
    $output = '
        <div class="breadcrumbs">';

    // Home link — always first
    $output .= '
            <a href="' . getBaseUrl() . '">' . __t('home') . '</a>';

    // ── List page: Home › Articles ────────────────────────────────────────────
    if (!empty($type) && empty($slug) && $type !== 'category' && $type !== 'tag') {
        $listLabel = sl_type_label($type, true);
        $output .= ' &raquo; <span>' . hsc($listLabel) . '</span>';
    }

    // ── Category listing page: Home › Category: name ──────────────────────────
    elseif ($type === 'category' && !empty($category)) {
        $output .= ' &raquo; 
            <span>' . __t('breadcrumb_category') . ': ' . hsc(urldecode($category)) . '</span>';
    }

    // ── Tag listing page: Home › Tag: name ───────────────────────────────────
    elseif ($type === 'tag' && !empty($slug)) {
        $output .= ' &raquo; 
            <span>' . __t('breadcrumb_tag') . ': ' . hsc(urldecode($slug)) . '</span>';
    }

    // ── Single content item ───────────────────────────────────────────────────
    elseif (!empty($type) && !empty($slug)) {
        // 1. Content-type list link (localized plural label)
        $listLabel = sl_type_label($type, true);
        $output .= ' &raquo;
            <a href="' . cleanUrl($type) . '">' . hsc($listLabel) . '</a>';

        // 2. Category crumbs — resolve full hierarchical path so each segment links correctly
        if (!empty($category)) {
            $data    = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
            $catPath = getCategoryPath($category, $data); // e.g. "parent/child"

            if (!empty($catPath)) {
                $segments    = explode('/', $catPath);
                $accumulated = '';

                foreach ($segments as $seg) {
                    $accumulated = $accumulated !== '' ? $accumulated . '/' . $seg : $seg;

                    // Resolve display name for this segment from the categories store
                    $catName = $seg; // fallback: slug itself
                    if (isset($data['categories'][$seg]['name'])) {
                        $catName = $data['categories'][$seg]['name'];
                    }

                    // Link target: localized category prefix + accumulated path
                    $catUrl  = getBaseUrl() . url_slug('category') . '/' . $accumulated . '/';
                    $output .= ' &raquo; 
            <a href="' . hsc($catUrl) . '">' . hsc($catName) . '</a>';
                }
            }
        }

        // 3. Current page title (terminal, non-linked)
        $output .= ' &raquo; 
            <span>' . hsc($title) . '</span>';
    }

    $output .= '
        </div>';
    return $output;
}

function get404PageContent()
{
    $base_url = getBaseUrl();
    $home_url = cleanUrl('home');

    $settings    = loadConfig();
    $activeTheme = isset($settings['active_theme']) ? $settings['active_theme'] : 'default';

    $templatePath = CMS_ROOT . '/theme/child_theme/' . $activeTheme . '/404.php';
    if (!file_exists($templatePath)) {
        $templatePath = CMS_ROOT . '/theme/' . $activeTheme . '/404.php';
    }

    if (file_exists($templatePath)) {
        ob_start();
        include $templatePath; // $base_url and $home_url are available in the template
        return ob_get_clean();
    }

    return '
    <div style="text-align:center;padding:4rem 2rem;font-family:Georgia,serif;">
        <h1 style="font-size:5rem;">404</h1>
        <p>' . hsc(__t('page_not_found_desc')) . '</p>
        <a href="' . hsc($home_url) . '">' . hsc(__t('back_to_home')) . '</a>
    </div>';
}

function sl_resolve_template_name(string $preferred, string $fallback): string
{
    $settings = loadConfig();
    $theme    = $settings['active_theme'] ?? 'default';
    $paths    = [
        CMS_ROOT . "/theme/child_theme/{$theme}/{$preferred}.php",
        CMS_ROOT . "/theme/{$theme}/{$preferred}.php",
        CMS_ROOT . "/theme/default/{$preferred}.php",
        CMS_ROOT . "/{$preferred}.php",
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) return $preferred;
    }
    return $fallback;
}

