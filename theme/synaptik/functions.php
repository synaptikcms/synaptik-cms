<?php
/**
 * Synaptik Theme — functions.php
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
function theme_body_class(): string
{
	$type = $_GET['type'] ?? '';
	$slug = $_GET['slug'] ?? '';

	$classes = ['theme-synaptik'];

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
?>