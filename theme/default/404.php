<?php
/**
 * Mono Theme — 404.php
 *
 * Minimal 404 error page.
 */
?>
<div class="not-found">
    <p class="not-found-code">404</p>
    <h1><?php echo __t('page_not_found') ?: 'Page not found'; ?></h1>
    <p><?php echo __t('page_not_found_desc') ?: 'The page you are looking for does not exist or has been moved.'; ?></p>
    <a href="<?php echo cleanUrl('home'); ?>">← <?php echo __t('back_to_home') ?: 'Back to home'; ?></a>
</div>
