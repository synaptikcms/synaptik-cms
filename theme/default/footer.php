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
            <?php
            $footerText = $settings['footer_text'] ?? '';
            $footerText = str_replace('{year}', date('Y'), $footerText);
            echo strip_tags($footerText, '<a><em><strong>');
            ?>
            <?php if (!empty($settings['footer_show_social']) && !empty($settings['footer_social_links'])): ?>
            <div class="footer-social">
                <?php foreach ($settings['footer_social_links'] as $social): ?>
                    <?php if (!empty($social['platform']) && !empty($social['url'])): ?>
                    <a href="<?php echo htmlspecialchars($social['url']); ?>" class="footer-social-link" target="_blank" rel="noopener">
                        <?php echo get_social_icon($social['platform']); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>
