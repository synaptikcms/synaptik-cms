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
<?php
// Render search overlay if the search feature is active
if (!empty($settings['show_search_icon'])) {
    echo render_search_ui();
}
?>
</body>
</html>
