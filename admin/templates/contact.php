<?php
/* Template Name: Contact */
/* Template Description: Page with contact form appended below the content */

/**
 * Contact Page Template — theme/synaptik/page-templates/contact.php
 *
 * Renders page content then appends a contact form.
 * If the content already contains a [contact_form] shortcode,
 * render_content_html() expands it inline and no second form is added.
 *
 * Variables injected by loadThemeTemplate():
 *   $item     — the page data array
 *   $settings — site settings array  (also available via loadSettings())
 */

$settings = loadSettings();
$hasInlineShortcode = (mb_strpos($item['content'] ?? '', '[contact_form]') !== false);
?>

<article class="page-content contact-page">

    <?php /* ── Title ─────────────────────────────────────────────────────── */ ?>
    <?php echo render_content_title($item); ?>

    <?php /* ── Featured image ────────────────────────────────────────────── */ ?>
    <?php echo render_featured_image($item); ?>

    <?php /* ── Rich-text content (expands [contact_form] if present) ──────── */ ?>
    <?php if (!empty($item['content'])): ?>
    <div class="page-body">
        <?php echo render_content_html($item['content'], $item); ?>
    </div>
    <?php endif; ?>

    <?php /* ── Standalone form — only when no shortcode in content ─────────── */ ?>
    <?php if (!$hasInlineShortcode): ?>
    <div class="contact-form-section">
        <?php echo render_contact_form_html(); ?>
    </div>
    <?php endif; ?>

    <?php /* ── Tags at bottom (optional) ───────────────────────────────────── */ ?>
    <?php if (!empty($item['show_tags_at_bottom'])): ?>
        <?php echo render_content_tags($item); ?>
    <?php endif; ?>

</article>
