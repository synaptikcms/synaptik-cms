<?php

function render_featured_image($item)
{
    if (!isset($item['image']) || (isset($item['show_featured_image']) && !$item['show_featured_image'])) {
        return '';
    }
    ob_start(); ?>
<div class="featured-image">
    <img src="<?php echo getBaseUrl() . hsc($item['image']); ?>" alt="<?php echo hsc(!empty($item['image_alt']) ? $item['image_alt'] : $item['title']); ?>"<?php echo _image_dimensions_attr($item['image']); ?>>
</div>
<?php
    return ob_get_clean();
}

function render_content_date($item)
{
    if (!isset($item['date']) || !isset($item['show_date']) || !$item['show_date']) {
        return '';
    }
    return '<div class="article-date">' . hsc(format_date($item['date'])) . '</div>';
}

function render_content_category($item)
{
    if (empty($item['category'])) {
        return '';
    }
    $catSlug  = sanitizeSlug($item['category']);
    if ($catSlug === '') return '';
    $catStore = sl_load_categories();
    $name     = $catStore[$catSlug]['name'] ?? $item['category'];
    ob_start(); ?>
<div class="article-category">
    <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . hsc($catSlug); ?>/" class="category-badge">
        <?php echo hsc($name); ?>
    </a>
</div>
<?php
    return ob_get_clean();
}

function render_content_tags($item)
{
    if (empty($item['tags']) || !is_array($item['tags'])) {
        return '';
    }
    $tagStore = sl_load_tags();
    ob_start(); ?>
<div class="article-tags">
    <?php foreach ($item['tags'] as $tagRaw):
        $tagSlug = sanitizeSlug($tagRaw);
        if ($tagSlug === '') continue;
        $name = $tagStore[$tagSlug]['name'] ?? $tagRaw;
    ?>
    <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . hsc($tagSlug); ?>/" class="tag-link">
        <?php echo hsc($name); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

function render_content_gallery($item)
{
    if (empty($item['gallery']) || !is_array($item['gallery'])) {
        return '';
    }
    ob_start(); ?>
<div class="content-gallery">
    <h3><?php echo __t('gallery'); ?></h3>
    <?php echo renderGallery($item['gallery'], $item['gallery_layout'] ?? 'grid'); ?>
</div>
<?php
    return ob_get_clean();
}

