<?php
/**
 * Mono Theme — content-articles.php
 *
 * Renders a single article page.
 *
 * Injected variables (from loadThemeTemplate):
 * @var array $item  The article data array
 */
$settings = loadSettings(); ?>
        <article class="content-single">
            <a href="<?php echo cleanUrl('article'); ?>" class="back-link"><?php echo __t('articles') ?: 'Articles'; ?></a>
            <?php if (!empty($item['category'])): ?>
            <div class="content-eyebrow">
                <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($item['category']) . '/'; ?>"
                class="content-category-link">
                    <?php echo htmlspecialchars($item['category']); ?>
                </a>
            </div>
            <?php endif; ?>
            <?php if (!isset($item['show_title']) || $item['show_title']): ?>
            <h1 class="content-title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <?php endif; ?>
            <?php $hasMeta = (!empty($item['date']) && !empty($item['show_date'])) || (!empty($item['tags']) && is_array($item['tags'])); ?>
            <?php if ($hasMeta): ?>
<div class="content-meta">
                <?php if (!empty($item['date']) && !empty($item['show_date'])): ?>
                        <time datetime="<?php echo htmlspecialchars($item['date']); ?>"><?php echo htmlspecialchars(format_date($item['date'])); ?></time>
                <?php endif; ?>
                <?php if (isset($item['show_tags_at_bottom']) && $item['show_tags_at_bottom']): ?>
                <span class="content-tags">
                    <?php foreach ($item['tags'] as $tag): ?>
                    <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>"><?php echo htmlspecialchars($tag); ?></a>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $customFieldsHtml = render_item_custom_fields($item, 'article'); ?>
    <?php if ($customFieldsHtml): ?>
    <div class="mono-custom-fields"><?php echo $customFieldsHtml; ?></div>
    <?php endif; ?>
    <?php if (!empty($item['image']) && !empty($item['show_featured_image'])): ?>
    <figure class="content-featured-image">
        <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>"
             alt="<?php echo htmlspecialchars($item['title']); ?>">
    </figure>
    <?php endif; ?>
    <div class="prose-body">
        <?php echo render_content_html($item['content'] ?? '', $item); ?>
    </div>
    
    <?php echo render_related_items($item); ?>
</article>
