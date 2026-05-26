<?php
$bodyClass = function_exists('theme_body_class') ? theme_body_class() : 'theme-synaptik';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo $metaTitle; ?></title>
	<?php echo render_meta_tags($settings, $metaTitle, $metaDescription); ?>
	<link rel="stylesheet" href="<?php echo getBaseUrl(); ?>css/lightbox.css">
	<link rel="icon" type="image/x-icon" href="https://dorianfichot.com/data/files/favicon.ico">
	<link rel="icon" type="image/png" sizes="32x32" href="https://dorianfichot.com/data/files/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="32x32" href="https://dorianfichot.com/data/files/apple-touch-icon.png">
	<?php echo render_header_scripts($headerScripts); ?>
</head>

<body class="<?php echo $bodyClass; ?>">
<header>
<?php if ($settings["show_site_title_in_header"]): ?>
	<h1 class="sitetitle">
		<a href="<?php echo cleanUrl("home"); ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($settings["site_title"]); ?></a>
	</h1>
<?php endif; ?>
	<nav>
		<?php echo renderHierarchicalMenu($settings, $data); ?>
	
	</nav>
</header>
<main>