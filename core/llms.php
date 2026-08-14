<?php
/**
 * llms.txt — structured site summary for LLM indexers.
 * Spec: https://llmstxt.org/
 * Accessible at /llms.txt via .htaccess rewrite.
 */

require_once __DIR__ . '/functions.php';

$settings = loadConfig();
$baseUrl  = getBaseUrl();

$articles = sl_load_index('article');
$pages    = sl_load_index('page');

$articles = array_filter($articles, fn($a) => ($a['status'] ?? '') === 'published');
$pages    = array_filter($pages,    fn($p) => ($p['status'] ?? '') === 'published');

usort($articles, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

$siteTitle = $settings['site_title']       ?? 'SynaptikCMS';
$siteDesc  = $settings['site_description'] ?? '';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo '# ' . $siteTitle . "\n";
if ($siteDesc) {
    echo '> ' . $siteDesc . "\n";
}
echo "\n";

if (!empty($pages)) {
    echo "## Pages\n\n";
    foreach ($pages as $page) {
        $slug = sl_effective_slug($page);
        $url  = cleanUrl('page', $slug);
        echo '- [' . ($page['title'] ?? $slug) . '](' . $url . ')' . "\n";
    }
    echo "\n";
}

if (!empty($articles)) {
    echo "## Articles\n\n";
    foreach ($articles as $article) {
        $slug    = sl_effective_slug($article);
        $cat     = $article['category'] ?? '';
        $url     = cleanUrl('article', $slug, null, $cat ?: null);
        $summary = $article['summary'] ?? '';
        $line    = '- [' . ($article['title'] ?? $slug) . '](' . $url . ')';
        if ($summary) {
            $line .= ': ' . $summary;
        }
        echo $line . "\n";
    }
    echo "\n";
}