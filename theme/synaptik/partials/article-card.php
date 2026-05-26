<?php
/**
 * Article Card Partial — theme/default/partials/article-card.php
 *
 * Renders a single article card used in list pages, category pages,
 * tag pages, and the homepage article grid.
 *
 * Copy this file into your own theme's partials/ folder and edit freely.
 * The CMS will automatically use your version instead of the built-in default.
 *
 * Available variables
 * -------------------
 * @var array  $article      Full article data array (title, content, image, date, tags, category…)
 * @var string $article_link Fully-qualified URL to the article
 *
 * Core helpers available
 * ----------------------
 * getBaseUrl()          — base URL with trailing slash
 * cleanUrl()            — generate any CMS URL
 * url_slug($type)       — localised URL prefix for a content type
 * sanitizeSlug($string) — convert a string to a URL-safe slug
 * format_date($date)    — format a date string using the site's date_format setting
 * __t($key)             — translate a string key using the active locale
 * loadSettings()        — return the site settings array (use sparingly)
 * _clean_excerpt($html, $length) — strip tags and trim content to $length characters
 */
?>

<article class="article-card">
    <?php if (!empty($article['image'])): ?>
    <div class="article-thumbnail">
        <a href="<?php echo $article_link; ?>">
            <img src="<?php echo getBaseUrl() . htmlspecialchars($article['image']); ?>"
                 alt="<?php echo htmlspecialchars($article['title']); ?>">
        </a>
    </div>
    <?php endif; ?>

    <h3>
        <a href="<?php echo $article_link; ?>"><?php echo htmlspecialchars($article['title']); ?></a>
    </h3>

    <?php if (!empty($article['date']) && !empty($article['show_date'])): ?>
    <div class="article-date">
        <?php echo htmlspecialchars(format_date($article['date'])); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($article['category'])): ?>
    <div class="article-category">
        <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($article['category']) . '/'; ?>"
           class="category-badge">
            <?php echo htmlspecialchars($article['category']); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($article['tags']) && is_array($article['tags'])): ?>
    <div class="article-tags">
        <?php foreach ($article['tags'] as $tag): ?>
        <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>"
           class="tag-link"><?php echo htmlspecialchars($tag); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="article-summary">
        <?php echo get_article_summary($article); ?>
    </div>

    <a href="<?php echo $article_link; ?>" class="read-more"><?php echo __t('read_more'); ?></a>
</article>

