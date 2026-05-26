<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: auth.php');
    exit;
}

require_once 'includes/admin-functions.php';

// Get all drafts
$draftsDir = 'drafts';
$draftCount = 0;

if (file_exists($draftsDir)) {
    $files = glob($draftsDir . '/*.json');
    $draftCount = count($files);
}

// Load content counts for sidebar
$data = admin_load_data() ?? [];
$contentCounts = [
    'article' => count($data['article'] ?? []),
    'page'    => count($data['page']    ?? []),
    'project' => count($data['project'] ?? []),
    'drafts'  => $draftCount,
];

// Get the protocol and domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain   = $_SERVER['HTTP_HOST'];
$baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$baseUrl  = $protocol . '://' . $domain . $baseDir;

// Default path for sitemap
$sitemapPath = '../sitemap.xml';

// Generate sitemap when form is submitted
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sitemap'])) {

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->appendChild($urlset);

    /**
     * Append a <url> node to the sitemap document.
     */
    $addUrl = function (string $loc, string $lastmod, string $priority) use ($xml, $urlset): void {
        $url = $xml->createElement('url');
        $url->appendChild($xml->createElement('loc',      htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $url->appendChild($xml->createElement('lastmod',  $lastmod));
        $url->appendChild($xml->createElement('priority', $priority));
        $urlset->appendChild($url);
    };

    // ── Homepage ──────────────────────────────────────────────────────────────
    $addUrl($baseUrl . '/', date('Y-m-d'), '1.0');

    // ── Individual content items (published only) ─────────────────────────────
    // Delegates entirely to admin_content_url() — the same function used in
    // content-list, SEO overview, etc. It calls admin_front_url_slug() for
    // localized type prefixes ('projet' not 'project') and getCategoryPath()
    // for full hierarchical category paths ('parent/sous-categorie').
    $priorities = ['page' => '0.9', 'article' => '0.8', 'project' => '0.7'];

    foreach (['article', 'page', 'project'] as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;

            $slug       = $item['slug']        ?? '';
            $customSlug = $item['custom_slug'] ?? '';
            $category   = $item['category']    ?? '';

            if (empty($slug) && empty($customSlug)) continue;

            $itemUrl = admin_content_url($ct, $slug, $customSlug, $category);
            $lastmod = $item['last_modified'] ?? ($item['date'] ?? date('Y-m-d'));
            $addUrl($itemUrl, $lastmod, $priorities[$ct] ?? '0.8');
        }
    }

    // ── Category listing pages ────────────────────────────────────────────────
    // Uses admin_front_url_slug('category') for the localized prefix ('categorie')
    // and getCategoryPath() so parent segments are also emitted.
    $seenCatUrls = [];
    $catPrefix   = admin_front_url_slug('category');

    foreach (['article', 'project', 'page'] as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;
            if (empty($item['category'])) continue;

            $leafSlug = sanitizeSlug($item['category']);
            $catPath  = getCategoryPath($leafSlug, $data);

            $segments    = explode('/', $catPath);
            $accumulated = '';
            foreach ($segments as $seg) {
                $accumulated = $accumulated !== '' ? $accumulated . '/' . $seg : $seg;
                $catUrl = $baseUrl . '/' . $catPrefix . '/' . $accumulated . '/';
                if (!isset($seenCatUrls[$catUrl])) {
                    $seenCatUrls[$catUrl] = true;
                    $addUrl($catUrl, date('Y-m-d'), '0.6');
                }
            }
        }
    }

    // ── Content-type archive pages (localized plural slugs) ───────────────────
    // admin_front_url_slug() reads the front-end lang JSON directly so
    // 'articles', 'projets', 'pages' are locale-aware.
    foreach (['article', 'project', 'page'] as $ct) {
        if (!empty($data[$ct])) {
            $pluralSlug = admin_front_url_slug($ct . 's');
            $addUrl($baseUrl . '/' . $pluralSlug . '/', date('Y-m-d'), '0.5');
        }
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    try {
        $xml->save($sitemapPath);
        $message = __t('sitemap_generated') . ' <a href="' . $baseUrl . '/sitemap.xml" target="_blank">' . __t('view') . '</a>';

        if (isset($_POST['ping_search_engines']) && $_POST['ping_search_engines'] == 1) {
            $sitemapUrl = urlencode($baseUrl . '/sitemap.xml');
            @file_get_contents('https://www.google.com/ping?sitemap=' . $sitemapUrl);
            @file_get_contents('https://www.bing.com/ping?sitemap='   . $sitemapUrl);
            $message .= '<br>' . __t('sitemap_pinged');
        }
    } catch (Exception $e) {
        $error = 'Error generating sitemap: ' . $e->getMessage();
    }
}

// Custom format file size function to avoid conflicts
function sm_format_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SynaptikCMS Admin | <?php _e('sitemap_generator'); ?></title>
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <!-- <link rel="stylesheet" href="css/editor-layout.css"> -->
    <link rel="icon" type="image/x-icon" href="../files/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../files/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../files/apple-touch-icon.png">
    <script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
</head>
<body>
    <script>
      // Apply sidebar state immediately to prevent flash
      (function() {
        try {
          var saved = localStorage.getItem('synaptik_sidebar_state');
          if (saved) {
            var state = JSON.parse(saved);
            if (state.isExpanded === false) {
              document.body.classList.add('sidebar-collapsed');
            } else {
              document.body.classList.add('sidebar-expanded');
            }
          } else {
            document.body.classList.add('sidebar-expanded');
          }
        } catch(e) {
          document.body.classList.add('sidebar-expanded');
        }
      })();
    </script>
    <div class="admin-container">
        <?php include_once 'includes/sidebar.php'; ?>
        <main class="content">
            <h1 class="main-heading"><?php _e('sitemap_generator'); ?></h1>
            <?php if (!empty($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            <a href="<?php echo $baseUrl; ?>" target="_blank" class="view-website-btn">
                <span class="icon">🌐</span> <?php _e('view_website'); ?>
            </a>
            <div class="sitemap-content">
                <div class="site-settings-section">
                    <div class="form-group">
                        <h3><?php _e('sitemap_how_to_use'); ?></h3>
                        <ol>
                            <li><?php _e('sitemap_step_1'); ?></li>
                            <li><?php _e('sitemap_step_2'); ?>
                                <pre>Sitemap: <?php echo $baseUrl; ?>/sitemap.xml</pre>
                            </li>
                            <li><?php _e('sitemap_step_3'); ?></li>
                        </ol>
                    </div>
                    <div class="form-group" style="margin-top:30px;">
                        <h3><?php _e('sitemap_current_status'); ?></h3>
                        <?php if (file_exists($sitemapPath)): ?>
                            <p><strong><?php _e('sitemap_location'); ?></strong> <a href="<?php echo $baseUrl . '/sitemap.xml'; ?>" target="_blank"><?php echo $baseUrl . '/sitemap.xml'; ?></a></p>
                            <p><strong><?php _e('sitemap_last_updated'); ?></strong> <?php echo date('F j, Y, g:i a', filemtime($sitemapPath)); ?></p>
                            <p><strong><?php _e('sitemap_file_size'); ?></strong> <?php echo sm_format_filesize(filesize($sitemapPath)); ?></p>
                        <?php else: ?>
                            <p><?php _e('sitemap_not_generated'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="site-settings-section">
                    <?php if (file_exists($sitemapPath)): ?>
                        <h3><?php _e('sitemap_update_btn'); ?></h3>
                    <?php else: ?>
                        <h3><?php _e('generate_sitemap'); ?></h3>
                    <?php endif; ?>
                    <p><?php _e('sitemap_desc'); ?></p>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="ping_search_engines" value="1" checked>
                                <?php _e('sitemap_ping_label'); ?>
                            </label>
                        </div>
                        <?php if (file_exists($sitemapPath)): ?>
                            <button type="submit" name="generate_sitemap" class="button"><?php _e('sitemap_update_btn'); ?></button>
                        <?php else: ?>
                            <button type="submit" name="generate_sitemap" class="button"><?php _e('generate_sitemap'); ?></button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/panel.js"></script>
    <script src="js/common.js"></script>
    <script src="js/admin-sidebar.js"></script>
</body>
</html>