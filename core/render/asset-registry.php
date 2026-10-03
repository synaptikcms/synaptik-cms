<?php
function enqueue_js(string $handle, string $path, bool $defer = true): void
{
    if (isset($GLOBALS['_enqueued_js'][$handle])) return;
    $GLOBALS['_enqueued_js'][$handle] = ['path' => $path, 'defer' => $defer];
}

function enqueue_css(string $handle, string $path): void
{
    if (isset($GLOBALS['_enqueued_css'][$handle])) return;
    $GLOBALS['_enqueued_css'][$handle] = ['path' => $path];
}

function render_enqueued_assets(): string
{
    $base = getBaseUrl();
    $root = CMS_ROOT;
    $out  = '';

    foreach ($GLOBALS['_enqueued_css'] ?? [] as $item) {
        $out .= '<link rel="stylesheet" href="' . $base . $item['path']
              . _asset_version($root . '/' . $item['path']) . '">' . "\n";
    }
    foreach ($GLOBALS['_enqueued_js'] ?? [] as $item) {
        $out .= '<script' . ($item['defer'] ? ' defer' : '') . ' src="' . $base . $item['path']
              . _asset_version($root . '/' . $item['path']) . '"></script>' . "\n";
    }

    return $out;
}

function sl_content_needs_shortcode_assets(string $renderedHtml): bool
{
    return $renderedHtml !== '' && stripos($renderedHtml, 'class="sc-') !== false;
}

function sl_content_needs_gallery_assets(string $renderedHtml): bool
{
    if ($renderedHtml === '') return false;
    foreach (['class="c-col', 'class="tab-group', 'class="inline-gallery', 'class="gallery-', 'class="carousel-', 'class="masonry-item', 'class="justified-gallery'] as $marker) {
        if (stripos($renderedHtml, $marker) !== false) return true;
    }
    return false;
}
