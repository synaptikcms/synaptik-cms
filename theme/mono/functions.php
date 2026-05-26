<?php
/**
 * Mono Theme — functions.php
 *
 * Theme-specific helpers loaded automatically by SynaptikCMS after all core
 * libraries are ready. Define helper functions for use in theme templates here.
 *
 * NOTE: You cannot redefine core CMS functions here (they are already loaded).
 * To override card HTML, create files in theme/mono/partials/ instead.
 */

/**
 * Return space-separated CSS body classes for the current request.
 * Used by header.php to provide layout and page-type context.
 *
 * @return string CSS class string
 */
function mono_body_class(): string
{
    $type = $_GET['type'] ?? '';
    $slug = $_GET['slug'] ?? '';

    $classes = ['theme-mono'];

    if (empty($type) && empty($slug)) {
        $classes[] = 'is-home';
    } elseif (!empty($type) && empty($slug)) {
        $classes[] = 'is-list';
        $classes[] = 'is-list--' . htmlspecialchars($type);
    } elseif (!empty($slug)) {
        $classes[] = 'is-single';
        $classes[] = 'is-single--' . htmlspecialchars($type);
    }

    return implode(' ', $classes);
}

/**
 * Render a back-navigation link for single content pages.
 * Returns HTML string pointing to the relevant list page.
 *
 * @param string $type  Content type: article | project | page
 * @return string HTML anchor tag
 */
function mono_back_link(string $type): string
{
    $labels = [
        'article' => __t('articles'),
        'project' => __t('projects'),
        'page'    => __t('pages'),
    ];
    $label = $labels[$type] ?? ucfirst($type) . 's';
    $url   = cleanUrl($type . 's'); // e.g. cleanUrl('articles')

    // cleanUrl expects the type key not the plural — use the list form
    $url = cleanUrl($type, null, null, null);

    return '<a href="' . $url . '" class="back-link">' . htmlspecialchars($label) . '</a>';
}
