<?php
session_start();
/**
 * SynaptikCMS — One-shot tag normalization script
 *
 * Converts all item tags from display strings to slugs.
 * For each tag found in items:
 *   - Computes its slug via sanitizeSlug()
 *   - Upserts the display name into tags.json (if the slug is not already there)
 *   - Replaces the raw string in the item with the slug
 *
 * Run once after upgrading to the slug-based tag architecture.
 * Safe to run multiple times — already-normalized slugs pass through unchanged.
 */

if (!defined('INCLUDED')) define('INCLUDED', true);
require_once 'includes/admin-functions.php';

if (!admin_is_logged_in()) {
    header('Location: auth.php');
    exit;
}

$data    = loadData();
$changed = 0;
$report  = [];

foreach (['article', 'project', 'page'] as $type) {
    if (empty($data[$type])) continue;
    foreach ($data[$type] as &$item) {
        if (empty($item['tags']) || !is_array($item['tags'])) continue;
        $newTags = [];
        foreach ($item['tags'] as $tagRaw) {
            $slug = sanitizeSlug($tagRaw);
            if ($slug === '') continue;
            // Upsert display name into the tags store if slug is not yet registered
            if (!isset($data['tags'][$slug])) {
                $data['tags'][$slug] = ['name' => $tagRaw];
            }
            if (!in_array($slug, $newTags, true)) {
                $newTags[] = $slug;
            }
            // Record conversion for the report
            if ($tagRaw !== $slug) {
                $report[] = "[$type] \"{$item['title']}\": \"$tagRaw\" → \"$slug\"";
                $changed++;
            }
        }
        $item['tags'] = $newTags;
    }
    unset($item);
}

if ($changed > 0) {
    saveData($data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tag Normalization — SynaptikCMS</title>
<style>
body { font-family: system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 20px; color: #eee; background: #1a1a2e; }
h1 { font-size: 1.4rem; margin-bottom: 8px; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
.ok { background: #1a4731; color: #4ade80; }
.warn { background: #3b2800; color: #fb923c; }
pre { background: #111; padding: 16px; border-radius: 6px; font-size: .82rem; overflow: auto; line-height: 1.6; }
a { color: #818cf8; }
</style>
</head>
<body>
<h1>Tag normalization</h1>
<?php if ($changed === 0): ?>
<p><span class="badge ok">Nothing to do</span> All tags are already stored as slugs.</p>
<?php else: ?>
<p><span class="badge warn"><?php echo $changed; ?> tag value<?php echo $changed > 1 ? 's' : ''; ?> converted</span></p>
<pre><?php echo htmlspecialchars(implode("\n", $report)); ?></pre>
<?php endif; ?>
<p style="margin-top:24px;"><a href="index.php?action=manage_tags">← Back to Tags</a></p>
</body>
</html>
