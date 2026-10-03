<article class="content-single">
    <?php if (!empty($type)): ?>
    <a href="<?php echo hsc(cleanUrl($type)); ?>" class="back-link"><?php echo hsc(sl_type_label($type, true)); ?></a>
    <?php endif; ?>
    <?php if (!empty($item['category'])): ?>
    <div class="content-eyebrow">
        <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($item['category']) . '/'; ?>" class="content-category-link">
            <?php echo htmlspecialchars($item['category']); ?>
        </a>
    </div>
    <?php endif; ?>
    <?php if (!isset($item['show_title']) || $item['show_title']): ?>
    <h1 class="content-title"><?php echo htmlspecialchars($item['title']); ?></h1>
    <?php endif; ?>
    <?php if (!empty($item['date']) && !empty($item['show_date'])): ?>
    <time datetime="<?php echo htmlspecialchars($item['date']); ?>"><?php echo htmlspecialchars(format_date($item['date'])); ?></time>
    <?php endif; ?>
    <?php if (!empty($item['tags']) && is_array($item['tags'])): ?>
    <div class="content-tags">
        <?php foreach ($item['tags'] as $tag): ?>
        <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>"><?php echo htmlspecialchars($tag); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($item['image']) && !empty($item['show_featured_image'])): ?>
    <figure class="content-featured-image">
        <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
    </figure>
    <?php endif; ?>
    <?php $customFieldsHtml = render_item_custom_fields($item, $type ?? ''); ?>
    <?php if ($customFieldsHtml): ?>
    <div class="content-custom-fields"><?php echo $customFieldsHtml; ?></div>
    <?php endif; ?>
    <div class="prose-body">
        <?php echo render_content_html($item['content'] ?? '', $item); ?>
    </div>
</article>
