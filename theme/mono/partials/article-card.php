<?php
/**
 * Mono Theme — partials/article-card.php
 *
 * Renders a single article as a title-only list item with hover animation.
 * Used by render_article_card() in list pages and the homepage.
 *
 * Available variables:
 * @var array  $article      Full article data array
 * @var string $article_link Fully-qualified URL to the article
 */
?>
<li class="title-item">
    <a href="<?php echo $article_link; ?>" class="title-link">
        <span class="title-arrow" aria-hidden="true">→</span>
        <span class="title-name"><?php echo htmlspecialchars($article['title']); ?></span>
        <?php if (!empty($article['date']) && !empty($article['show_date'])): ?>
        <span class="title-date"><?php echo htmlspecialchars(format_date($article['date'])); ?></span>
        <?php endif; ?>
    </a>
</li>
