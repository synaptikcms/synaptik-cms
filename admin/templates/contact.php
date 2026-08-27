<?php
/* Template Name: Contact */
/* Template Description: Page with contact form appended below the content  */
$settings = loadConfig();
$hasInlineShortcode = (mb_strpos($item['content'] ?? '', '[contact_form]') !== false);
?>

<article class="page-content contact-page">
    <?php echo render_content_title($item); ?>
    <?php echo render_featured_image($item); ?>
    <?php if (!empty($item['content'])): ?>
    <div class="page-body">
        <?php echo render_content_html($item['content'], $item); ?>
    </div>
    <?php endif; ?>

    <?php if (!$hasInlineShortcode): ?>
    <div class="contact-form-section">
        <?php echo render_contact_form_html(); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($item['show_tags_at_bottom'])): ?>
        <?php echo render_content_tags($item); ?>
    <?php endif; ?>
</article>
