<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';
if (!admin_is_logged_in()) {
    header('Location: auth.php');
    exit;
}
if (!admin_can_manage_all_content()) {
    http_response_code(403);
    exit('Access denied.');
}

// Get all drafts
$draftsDir = sl_admin_drafts_dir();
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
$baseUrl = sm_base_url();

// Default path for sitemap
$sitemapPath = sm_sitemap_path();

// Add / remove sitemap exclusions
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_exclusion']) || isset($_POST['remove_exclusion']))) {
    $__tok = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $__tok)) {
        $error = 'Security token invalid or expired. Please try again.';
        goto sitemap_render;
    }

    $exclusions = sm_load_exclusions();

    if (isset($_POST['add_exclusion'])) {
        $entry = sm_normalize_exclusion((string)($_POST['exclusion_path'] ?? ''), $baseUrl);
        if ($entry !== '' && !in_array($entry, $exclusions, true)) {
            $exclusions[] = $entry;
        }
    } elseif (isset($_POST['remove_exclusion'])) {
        $target     = (string)($_POST['remove_exclusion'] ?? '');
        $exclusions = array_values(array_filter($exclusions, fn($e) => $e !== $target));
    }

    _sl_write_json(sm_exclusions_path(), $exclusions);
}

$sitemapExclusions = sm_load_exclusions();

// Generate sitemap when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sitemap'])) {
    $__tok = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $__tok)) {
        $error = 'Security token invalid or expired. Please try again.';
        goto sitemap_render;
    }

    if (sm_regenerate()) {
        $message = __t('sitemap_generated') . ' <a href="' . $baseUrl . '/sitemap.xml" target="_blank">' . __t('view') . '</a>';

        if (!empty($_POST['indexnow'])) {
            $indexNow = sm_indexnow_submit($baseUrl);
            if (!$indexNow['ok']) {
                $message .= '<br>' . sprintf(__t('sitemap_indexnow_failed', 'IndexNow notification failed (HTTP %s).'), $indexNow['status'] ?: 'n/a');
            } elseif ($indexNow['sent'] > 0) {
                $message .= '<br>' . sprintf(__t('sitemap_indexnow_sent', 'IndexNow: %d URL(s) sent to search engines.'), $indexNow['sent']);
            } else {
                $message .= '<br>' . __t('sitemap_indexnow_none', 'IndexNow: no URL changed since the last notification.');
            }
        }
    } else {
        $error = 'Error generating sitemap.';
    }
}

sitemap_render:
function sm_format_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
$pageTitle = __t('sitemap_generator');

ob_start();
?>
<?php
$smCsrf   = hsc($_SESSION['csrf_token'] ?? '');
$smUrl    = $baseUrl . '/sitemap.xml';
$smExists = file_exists($sitemapPath);
?>
            <div class="sitemap-intro">
                <p><?php _e('sitemap_desc'); ?></p>
                <h3><?php _e('sitemap_how_to_use'); ?></h3>
                <ol>
                    <li><?php _e('sitemap_step_1'); ?></li>
                    <li><?php _e('sitemap_step_2'); ?>
                        <pre>Sitemap: <?php echo hsc($smUrl); ?></pre>
                    </li>
                    <li><?php _e('sitemap_step_3'); ?></li>
                    <li><?php _e('sitemap_step_4'); ?></li>
                </ol>
            </div>
            <div class="sitemap-content">
                <div class="site-settings-section">
                    <h3><?php _e('sitemap_current_status'); ?></h3>
                    <?php if ($smExists): ?>
                    <dl class="sitemap-status">
                        <dt><?php _e('sitemap_location'); ?></dt>
                        <dd><a href="<?php echo hsc($smUrl); ?>" target="_blank"><?php echo hsc($smUrl); ?></a></dd>
                        <dt><?php _e('sitemap_last_updated'); ?></dt>
                        <dd><?php echo hsc(date('d-m-Y H:i', filemtime($sitemapPath))); ?></dd>
                        <dt><?php _e('sitemap_file_size'); ?></dt>
                        <dd><?php echo hsc(sm_format_filesize(filesize($sitemapPath))); ?></dd>
                    </dl>
                    <?php else: ?>
                    <p class="help-text"><?php _e('sitemap_not_generated'); ?></p>
                    <?php endif; ?>
                    <form method="post" action="" class="sitemap-generate-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $smCsrf; ?>">
                        <label class="checkbox-label">
                            <input type="checkbox" name="indexnow" value="1" checked>
                            <?php _e('sitemap_indexnow_label'); ?>
                        </label>
                        <p class="help-text"><?php _e('sitemap_indexnow_help'); ?></p>
                        <button type="submit" name="generate_sitemap" class="btn btn-primary"><?php _e($smExists ? 'sitemap_update_btn' : 'generate_sitemap'); ?></button>
                    </form>
                </div>
                <div class="site-settings-section">
                    <h3><?php _e('sitemap_exclusions_title'); ?></h3>
                    <p class="help-text"><?php _e('sitemap_exclusions_desc'); ?></p>
                    <?php if (empty($sitemapExclusions)): ?>
                    <p class="sitemap-exclusion-empty"><?php _e('sitemap_exclusion_empty'); ?></p>
                    <?php else: ?>
                    <ul class="sitemap-exclusion-list">
                        <?php foreach ($sitemapExclusions as $sitemapExclusion): ?>
                        <li>
                            <code><?php echo hsc($sitemapExclusion); ?></code>
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $smCsrf; ?>">
                                <input type="hidden" name="remove_exclusion" value="<?php echo hsc($sitemapExclusion); ?>">
                                <button type="submit" class="btn btn-outline btn-sm"><?php _e('sitemap_exclusion_remove'); ?></button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <form method="post" action="" class="sitemap-exclusion-add">
                        <input type="hidden" name="csrf_token" value="<?php echo $smCsrf; ?>">
                        <input type="text" name="exclusion_path" placeholder="<?php echo hsc(__t('sitemap_exclusion_placeholder')); ?>">
                        <button type="submit" name="add_exclusion" class="btn btn-outline"><?php _e('sitemap_exclusion_add'); ?></button>
                    </form>
                </div>
            </div>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';