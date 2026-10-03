<?php

function render_site_logo(array $settings, string $class = '', string $alt = ''): string
{
    $path = trim($settings['site_logo'] ?? '');
    if ($path === '') return '';
    $a = hsc($alt !== '' ? $alt : ($settings['site_title'] ?? ''));
    $relDir = ltrim(dirname($path), '.');
    $relDir = $relDir === '' || $relDir === '/' ? '' : trim($relDir, '/') . '/';
    $ext    = pathinfo($path, PATHINFO_EXTENSION);
    $root   = preg_replace('/-(light|dark)$/i', '', pathinfo($path, PATHINFO_FILENAME));

    $lightRel = $relDir . $root . '-light.' . $ext;
    $darkRel  = $relDir . $root . '-dark.' . $ext;

    if (is_file(CMS_ROOT . '/' . $lightRel) && is_file(CMS_ROOT . '/' . $darkRel)) {
        $base     = getBaseUrl();
        $lightCls = hsc(trim('sl-logo-light ' . $class));
        $darkCls  = hsc(trim('sl-logo-dark ' . $class));
        return '<img src="' . hsc($base . $lightRel) . '" alt="' . $a . '" class="' . $lightCls . '">'
             . '<img src="' . hsc($base . $darkRel) . '" alt="' . $a . '" class="' . $darkCls . '">';
    }

    $url = getBaseUrl() . ltrim($path, '/');
    $cls = $class !== '' ? ' class="' . hsc($class) . '"' : '';
    return '<img src="' . hsc($url) . '" alt="' . $a . '"' . $cls . '>';
}

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
    return '<link rel="icon" type="' . $mime . '" href="' . hsc($url) . '">' . "\n";
}

function render_site_title($settings, $pageTitle)
{
    if ($settings['show_site_title_in_header']) {
        return hsc($settings['site_title']);
    }
    return $pageTitle === 'Welcome to Synaptik CMS' ? $pageTitle : $settings['site_title'];
}

function render_content_title($item)
{
    if (!isset($item['show_title']) || $item['show_title']) {
        return '<h1 class="content-title">' . hsc($item['title']) . '</h1>';
    }
    return '';
}

