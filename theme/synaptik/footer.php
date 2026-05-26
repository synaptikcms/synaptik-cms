<?php do_theme_action('after_content'); ?>
	
	</main>
	<?php do_theme_action('before_footer'); ?>
	<footer>
		<?php echo render_footer_content(); ?>
	</footer>
	<?php do_theme_action('after_footer'); ?>
	<script>
			// Find all submenu items
			  const submenuItems = document.querySelectorAll('header nav li.has-submenu');
			  // For mobile: toggle submenu on click
			  submenuItems.forEach(item => {
				item.querySelector('a').addEventListener('click', function(e) {
				  if (window.innerWidth <= 768) {
					e.preventDefault();
					this.parentNode.classList.toggle('mobile-expanded');
				  }
				});
			  });

	</script>
</body>
</html>