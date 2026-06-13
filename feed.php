<?php
/**
 * RSS 2.0 feed — articles sorted by date descending.
 * Accessible at /feed.php or via the /feed/ rewrite rule.
 */

require_once __DIR__ . '/functions.php';

$settings = loadSettings();
$articles = sl_load_index('article');

// Published only, sorted newest first, cap at 20
$articles = array_filter($articles, fn($a) => ($a['status'] ?? 'published') === 'published');
usort($articles, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
$articles = array_slice(array_values($articles), 0, 20);

$baseUrl   = getBaseUrl();
$siteTitle = htmlspecialchars($settings['site_title']      ?? 'SynaptikCMS', ENT_XML1);
$siteDesc  = htmlspecialchars($settings['site_description'] ?? '',            ENT_XML1);
$feedUrl   = $baseUrl . 'feed.php';
$buildDate = date(DATE_RSS);

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo $siteTitle; ?></title>
    <link><?php echo htmlspecialchars($baseUrl, ENT_XML1); ?></link>
    <description><?php echo $siteDesc; ?></description>
    <language><?php echo htmlspecialchars($settings['active_language'] ?? 'en', ENT_XML1); ?></language>
    <lastBuildDate><?php echo $buildDate; ?></lastBuildDate>
    <atom:link href="<?php echo htmlspecialchars($feedUrl, ENT_XML1); ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($articles as $article):
    $slug     = !empty($article['custom_slug']) ? $article['custom_slug'] : ($article['slug'] ?? '');
    $category = $article['category'] ?? '';
    $itemUrl  = cleanUrl('article', $slug, null, $category ?: null);
    $title    = htmlspecialchars($article['title'] ?? '', ENT_XML1);
    $pubDate  = !empty($article['date'])
        ? date(DATE_RSS, strtotime($article['date']))
        : $buildDate;

    // Use summary from index if available; otherwise load full item for excerpt
    if (!empty($article['summary'])) {
        $description = htmlspecialchars($article['summary'], ENT_XML1);
    } else {
        $fullItem    = sl_load_item('article', sl_file_slug($article));
        $description = $fullItem
            ? htmlspecialchars(_clean_excerpt($fullItem['content'] ?? '', 300), ENT_XML1)
            : '';
    }
?>
    <item>
        <title><?php echo $title; ?></title>
        <link><?php echo htmlspecialchars($itemUrl, ENT_XML1); ?></link>
        <guid isPermaLink="true"><?php echo htmlspecialchars($itemUrl, ENT_XML1); ?></guid>
        <pubDate><?php echo $pubDate; ?></pubDate>
        <?php if ($description): ?><description><?php echo $description; ?></description><?php endif; ?>
        <?php if ($category): ?><category><?php echo htmlspecialchars($category, ENT_XML1); ?></category><?php endif; ?>
        <?php foreach ((array)($article['tags'] ?? []) as $tag): ?>
        <category><?php echo htmlspecialchars($tag, ENT_XML1); ?></category>
        <?php endforeach; ?>
    </item>
<?php endforeach; ?>
</channel>
</rss>