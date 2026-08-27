<article class="content-single">
    <?php echo render_content_category($item); ?>
    <?php echo render_content_title($item); ?>
    <?php echo render_content_date($item); ?>

    <?php if (!empty($item['image']) && !empty($item['show_featured_image'])): ?>
    <figure class="content-featured-image">
        <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
    </figure>
    <?php endif; ?>

    <?php echo render_item_custom_fields($item, 'article'); ?>

    <div class="prose-body">
        <?php echo render_content_html($item['content'] ?? '', $item); ?>
    </div>

    <?php echo render_content_gallery($item); ?>
    <?php echo render_content_tags($item); ?>
    <?php echo render_related_items($item); ?>
</article>
