<?php
/* Template Name: Contact 
*/

// $item is injected by loadThemeTemplate()
// Detects whether [contact_form] is already embedded in the content.
// If it is, render_content_html() expands it inline — no second form is appended.
$hasInlineShortcode = (strpos($item['content'] ?? '', '[contact_form]') !== false);
?>

	<section class="page-details">
		<?php echo render_content_title($item); ?>
		<?php echo render_content_date($item); ?>
		<?php echo render_featured_image($item); ?>

		<div class="page-content">
			<?php echo render_content_html($item['content'], $item); ?>

			<?php if (!$hasInlineShortcode): ?>
			<?php echo render_contact_form_html(); ?>
			<?php endif; ?>
		</div>

		<?php if (isset($item['show_tags_at_bottom']) && $item['show_tags_at_bottom']): ?>
		<div class="article-footer-tags">
			<h4>Tags:</h4>
			<?php echo render_content_tags($item); ?>
		</div>
		<?php endif; ?>
	</section>