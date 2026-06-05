<?php
$bodyClass = function_exists('mono_body_class') ? mono_body_class() : 'theme-mono';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($settings['site_language'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <?php echo render_meta_tags($settings, $metaTitle, $metaDescription); ?>
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>css/lightbox.css">
    <?php echo render_header_scripts($headerScripts); ?>
    <?php echo render_site_favicon($settings); ?>
</head>
<body class="<?php echo $bodyClass; ?>">
<div class="site-overlay" id="mono-overlay" aria-hidden="true"></div>
<button class="burger" id="mono-burger" aria-label="<?php echo __t('toggle_navigation') ?: 'Toggle navigation'; ?>" aria-expanded="false" aria-controls="mono-sidebar">
    <span></span>
    <span></span>
    <span></span>
</button>
<div class="site-layout">
    <aside class="site-sidebar" id="mono-sidebar" aria-label="Site navigation">
        <div class="site-title"><?php echo render_site_logo($settings) ?><a href="<?php echo cleanUrl('home'); ?>"><?php echo htmlspecialchars($settings['site_title'] ?? 'My Site'); ?></a></div>
        <div class="sidebar-inner">
            <nav class="site-nav" aria-label="Main navigation">
                <?php echo renderHierarchicalMenu($settings, $data); ?>
            </nav>
        </div>
    </aside>
    
    <main class="site-main" id="main-content">
