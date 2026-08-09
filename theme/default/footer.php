<?php
/**
 * Mono Theme — footer.php
 *
 * Closes .site-main and .site-layout opened in header.php,
 * then loads the theme JavaScript.
 *
 * Injected variables (from loadThemeTemplate):
 * @var array  $settings
 * @var string $currentYear
 * @var string $baseUrl
 */
$themePath = getBaseUrl() . 'theme/mono';
?>
    </main><!-- /.site-main -->
</div><!-- /.site-layout -->
<footer class="site-footer">
        <div class="site-footer-inner">
            <?php echo render_footer_content(); ?>
        </div>
    </footer>
</body>
</html>
