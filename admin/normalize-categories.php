<?php
session_start();
/**
 * SynaptikCMS — One-shot category normalization script
 *
 * Converts all item categories from display strings to slugs.
 * For each category found in items:
 *   - Computes its slug via sanitizeSlug()
 *   - Upserts the display name into categories.json (without overwriting existing entries,
 *     so parent relationships defined in the store are preserved)
 *   - Replaces the raw string in the item with the slug
 *
 * Run once after upgrading to the slug-based category architecture.
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
        if (empty($item['category'])) continue;
        $displayCat = $item['category'];
        $slug       = sanitizeSlug($displayCat);
        if ($slug === '') continue;
        // Upsert into categories store if slug is not yet registered
        // (do NOT overwrite existing entries — parent relationships must be preserved)
        if (!isset($data['categories'][$slug])) {
            $data['categories'][$slug] = ['name' => $displayCat];
        }
        // Convert item category to slug
        if ($item['category'] !== $slug) {
            $report[] = "[$type] \"{$item['title']}\": \"{$item['category']}\" → \"$slug\"";
            $changed++;
            $item['category'] = $slug;
        }
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
<title>Category Normalization — SynaptikCMS</title>
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
<h1>Category normalization</h1>
<?php if ($changed === 0): ?>
<p><span class="badge ok">Nothing to do</span> All categories are already stored as slugs.</p>
<?php else: ?>
<p><span class="badge warn"><?php echo $changed; ?> category value<?php echo $changed > 1 ? 's' : ''; ?> converted</span></p>
<pre><?php echo htmlspecialchars(implode("\n", $report)); ?></pre>
<?php endif; ?>
<p style="margin-top:24px;"><a href="index.php?action=manage_categories">← Back to Categories</a></p>
</body>
</html>
