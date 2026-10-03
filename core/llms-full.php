<?php

require_once __DIR__ . '/functions.php';

$settings = loadConfig();
$baseUrl  = getBaseUrl();

$articles = sl_load_index('article');
$pages    = sl_load_index('page');

$articles = array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published');
$pages    = array_filter($pages,    fn($p) => ($p['status'] ?? '') === 'published');

usort($articles, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

$siteTitle = $settings['site_title']       ?? 'Synaptik CMS';
$siteDesc  = $settings['site_description'] ?? '';

function _llms_plain_text(string $content, array $item): string {
    $html = render_content_html($content, $item);
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\n{3,}/', "\n\n", trim($text));
    return $text;
}

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo '# ' . $siteTitle . "\n";
if ($siteDesc) {
    echo '> ' . $siteDesc . "\n";
}
echo "\n";

// Pages — full content
if (!empty($pages)) {
    foreach ($pages as $page) {
        $slug = sl_effective_slug($page);
        $item = sl_load_item('page', sl_file_slug($page));
        if ($item === null) continue;
        $url     = cleanUrl('page', $slug);
        $title   = $item['title']   ?? $slug;
        $content = _llms_plain_text($item['content'] ?? '', $item);

        echo '## ' . $title . "\n";
        echo 'URL: ' . $url . "\n\n";
        if ($content) echo trim($content) . "\n";
        echo "\n---\n\n";
    }
}

// Articles — full content
if (!empty($articles)) {
    foreach ($articles as $article) {
        $slug = sl_effective_slug($article);
        $item = sl_load_item('article', sl_file_slug($article));
        if ($item === null) continue;
        $cat     = $article['category'] ?? '';
        $url     = cleanUrl('article', $slug, null, $cat ?: null);
        $title   = $item['title']   ?? $slug;
        $date    = $item['date']    ?? '';
        $summary = $item['summary'] ?? '';
        $content = _llms_plain_text($item['content'] ?? '', $item);

        echo '## ' . $title . "\n";
        echo 'URL: ' . $url . "\n";
        if ($date)    echo 'Date: ' . $date . "\n";
        if ($summary) echo 'Summary: ' . $summary . "\n";
        echo "\n";
        if ($content) echo trim($content) . "\n";
        echo "\n---\n\n";
    }
}