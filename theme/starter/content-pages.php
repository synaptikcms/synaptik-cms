<article class="content-single">
    <?php echo render_content_title($item); ?>

    <?php if (!empty($item['image']) && !empty($item['show_featured_image'])): ?>
    <figure class="content-featured-image">
        <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
    </figure>
    <?php endif; ?>

    <?php echo render_item_custom_fields($item, 'page'); ?>

    <div class="prose-body">
        <?php echo render_content_html($item['content'] ?? '', $item); ?>
    </div>

    <?php echo render_content_gallery($item); ?>
</article>
