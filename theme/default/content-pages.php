
        <article class="content-single">
            <?php if (!isset($item['show_title']) || $item['show_title']): ?>
            <h1 class="content-title" style="margin-bottom: 2.5rem;">
                <?php echo htmlspecialchars($item['title']); ?>
            </h1>
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
        </article>
