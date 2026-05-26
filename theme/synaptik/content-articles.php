
	<section class="content-details">
		<?php echo render_content_title($item); ?> 
		<?php echo render_content_date($item); ?>
		<?php echo render_featured_image($item); ?>
		
		<div class="content-main">
			<?php echo render_content_html($item['content'], $item); ?>
		</div>
		<?php if (isset($item['show_tags_at_bottom']) && $item['show_tags_at_bottom']): ?>
		<div class="article-footer-tags">
			<h4>Tags:</h4>
			<?php echo render_content_tags($item); ?>
		</div>
		<?php endif; ?>
	</section>