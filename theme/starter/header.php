<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($settings['active_language'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $metaTitle; ?></title>
    <?php echo render_meta_tags($settings, $metaTitle, $metaDescription); ?>
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>assets/css/lightbox.css">
    <?php echo render_header_scripts($headerScripts); ?>
    <?php echo render_site_favicon($settings); ?>
</head>
<body>
<?php render_adminbar(); ?>
<header class="site-header">
    <a href="<?php echo cleanUrl('home'); ?>" class="site-brand">
        <?php echo render_site_logo($settings); ?>
        <?php echo htmlspecialchars($settings['site_title'] ?? 'My Site'); ?>
    </a>
    <nav class="site-nav" aria-label="Main navigation">
        <?php echo renderHierarchicalMenu($settings, $data); ?>
        <button type="button" id="search-toggle" class="search-toggle" aria-label="Search">Search</button>
    </nav>
</header>
<main class="site-main" id="main-content">
