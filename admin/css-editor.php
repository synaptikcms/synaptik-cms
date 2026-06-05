<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: auth.php');
    exit;
}

require_once 'includes/admin-functions.php';

// Resolve paths from admin/ up to site root
$siteRoot = dirname(__DIR__);

// Load lightweight indices for sidebar (counts only, no content body needed)
require_once $siteRoot . '/data-layer.php';
$data = sl_build_data_array(['article', 'page', 'project'], false);

// Build CSS path from active theme in settings.json
$settings    = json_decode(file_get_contents($siteRoot . '/settings.json'), true);
$activeTheme = $settings['active_theme'] ?? 'default';


$cssPath   = $siteRoot . '/theme/' . $activeTheme . '/css/style.css';
$backupDir = $siteRoot . '/bckps/css/';
$message    = '';
$error      = '';

// Build base URL (same pattern as sitemap-generator.php)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain   = $_SERVER['HTTP_HOST'];
$baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$baseUrl  = $protocol . '://' . $domain . $baseDir;

// --- Ensure backup directory exists ---
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// --- Handle save ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['css_content'])
    && !isset($_POST['restore_backup']) && !isset($_POST['delete_backup'])) {
    $newCss = $_POST['css_content'];

    // Backup first
    $backupFile = $backupDir . 'style-' . date('Ymd-His') . '.css';
    if (!copy($cssPath, $backupFile)) {
        $error = __t('css_save_backup_failed') . ' <code>' . htmlspecialchars($backupDir) . '</code>.';
    } else {
        if (file_put_contents($cssPath, $newCss) !== false) {
            $message = __t('css_save_success') . ' <code>' . htmlspecialchars(basename($backupFile)) . '</code>';
        } else {
            $error = __t('css_save_write_failed') . ' <code>' . htmlspecialchars($cssPath) . '</code>.';
        }
    }
}

// --- Handle backup restore ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $backupToRestore = basename($_POST['restore_backup']); // basename prevents path traversal
    $fullBackupPath  = $backupDir . $backupToRestore;

    if (file_exists($fullBackupPath)) {
        // Make a backup of current before restoring
        $safetyBackup = $backupDir . 'style-pre-restore-' . date('Ymd-His') . '.css';
        copy($cssPath, $safetyBackup);

        if (copy($fullBackupPath, $cssPath)) {
            $message = __t('css_restore_success_prefix') . ' <code>' . htmlspecialchars($backupToRestore) . '</code>. ' . __t('css_restore_success_suffix') . ' <code>' . htmlspecialchars(basename($safetyBackup)) . '</code>.';
        } else {
            $error = __t('css_restore_failed');
        }
    } else {
        $error = __t('css_backup_not_found');
    }
}

// --- Handle backup delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $backupToDelete = basename($_POST['delete_backup']); // basename prevents path traversal
    $fullDeletePath = $backupDir . $backupToDelete;

    if (file_exists($fullDeletePath)) {
        if (unlink($fullDeletePath)) {
            $message = __t('css_backup_deleted_prefix') . ' <code>' . htmlspecialchars($backupToDelete) . '</code> ' . __t('css_backup_deleted_suffix') . '.';
        } else {
            $error = __t('css_backup_delete_failed');
        }
    } else {
        $error = __t('css_backup_not_found');
    }
}

// --- Load current CSS ---
$cssContent = file_exists($cssPath) ? file_get_contents($cssPath) : '/* style.css not found */';

// --- Load backup list (newest first) ---
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.css');
    if ($files) {
        rsort($files);
        foreach ($files as $f) {
            $backups[] = [
                'name'     => basename($f),
                'size'     => round(filesize($f) / 1024, 1),
                'modified' => date('M j, Y — H:i', filemtime($f)),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SynaptikCMS Admin | <?php _e('css_theme_editor'); ?></title>
    <link rel="stylesheet" href="css/admin-base.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/admin-sidebar.css">
    <link rel="stylesheet" href="css/editor-layout.css">
    <link rel="icon" type="image/x-icon" href="../files/favicon.ico">

    <!-- CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">

    <script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>

    <style>
        /* ── CSS Editor layout ── */
        .css-editor-wrap {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 24px;
            align-items: start;
        }

        .editor-main { min-width: 0; }

        .editor-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            border-radius: 5px 5px 0 0;
            padding-left: 20px;
            padding-right: 20px;
        }

        .editor-toolbar .file-info {
            margin-left: auto;
            font-size: 0.78em;
            color: var(--text-muted);
            font-family: monospace;
        }

        /* CodeMirror overrides */
        .CodeMirror {
            height: 72vh;
            font-size: 13px;
            line-height: 1.6;
            border-radius: 0 0 6px 6px;
            border: 1px solid var(--border-dark);
            font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
        }

        .CodeMirror-scroll { padding-bottom: 20px; }

        /* Unsaved changes indicator */
        .dirty-indicator {
            display: none;
            background: #f0a500;
            color: #1a2432;
            font-size: 0.72em;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .is-dirty .dirty-indicator { display: inline-block; }

        /* ── Sidebar panel ── */
        .editor-panel {
            background: var(--bg-white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 20px;
        }

        .editor-panel h3 {
            margin: 0 0 14px 0;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 10px;
        }

        /* Stats */
        .css-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }

        .css-stat {
            background: var(--bg-light);
            border-radius: var(--radius-sm);
            padding: 10px;
            text-align: center;
        }

        .css-stat-value {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .css-stat-label {
            font-size: 0.72em;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Backup list */
        .backup-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 360px;
            overflow-y: auto;
        }

        .backup-list li {
            border-bottom: 1px solid var(--border-light);
            padding: 10px 0;
        }

        .backup-list li:last-child { border-bottom: none; }

        .backup-name {
            font-family: monospace;
            font-size: 0.78em;
            color: var(--text-dark);
            word-break: break-all;
            display: block;
            margin-bottom: 3px;
        }

        .backup-meta {
            font-size: 0.72em;
            color: var(--text-muted);
            display: block;
            margin-bottom: 6px;
        }

        .btn-restore {
            font-size: 0.75em;
            padding: 3px 10px;
            background: var(--info);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition-standard);
        }

        .btn-restore:hover { background: var(--info-dark); }

        .btn-delete-backup {
            font-size: 0.75em;
            padding: 3px 10px;
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition-standard);
            margin-left: 4px;
        }

        .btn-delete-backup:hover {
            background: var(--danger);
            color: #fff;
        }

        .no-backups {
            font-size: 0.82em;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .css-editor-wrap {
                grid-template-columns: 1fr;
            }
            .CodeMirror { height: 55vh; }
        }
    </style>
</head>
<body>
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('synaptik_sidebar_state');
                if (saved) {
                    var state = JSON.parse(saved);
                    document.body.classList.add(state.isExpanded === false ? 'sidebar-collapsed' : 'sidebar-expanded');
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
            <h1 class="main-heading"><?php _e('css_theme_editor'); ?></h1>
            <a href="<?php echo $baseUrl; ?>" target="_blank" class="view-website-btn">
                <span class="icon">🌐</span> <?php _e('view_website'); ?>
            </a>

            <?php if (!empty($message)): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!file_exists($cssPath)): ?>
                <div class="message error">
                    <strong><?php _e('css_file_not_found'); ?></strong><br>
                    <?php _e('css_active_theme'); ?> <code><?php echo htmlspecialchars($activeTheme); ?></code><br>
                    <?php _e('css_looking_here'); ?> <code><?php echo htmlspecialchars($cssPath); ?></code><br>
                    <?php _e('css_adjust_path'); ?>
                </div>
            <?php endif; ?>
            <div class="alt-text-container">
                <p><?php _e('css_editor_desc'); ?><br>
                <?php _e('css_editor_backup_desc'); ?></p>
            </div>
            <form method="post" action="" id="css-editor-form">
                <div class="css-editor-wrap">

                    <!-- ── Main editor ── -->
                    <div class="editor-main" id="editor-wrap">
                        <div class="editor-toolbar">
                            <button type="submit" class="button" id="save-btn"><?php _e('css_save_btn'); ?></button>
                            <span class="dirty-indicator" id="dirty-indicator"><?php _e('css_unsaved_changes'); ?></span>
                            <span class="file-info">style.css
                                &nbsp;·&nbsp;
                                <?php echo round(strlen($cssContent) / 1024, 1); ?> KB
                                &nbsp;·&nbsp;
                                <?php _e('last_modified'); ?> <?php echo file_exists($cssPath) ? date('M j, Y H:i', filemtime($cssPath)) : '—'; ?>
                            </span>
                        </div>

                        <textarea id="css-textarea" name="css_content"><?php echo htmlspecialchars($cssContent); ?></textarea>

                    </div><!-- /editor-main -->

                    <!-- ── Side panel ── -->
                    <div class="editor-panel">

                        <h3><?php _e('css_file_stats'); ?></h3>
                        <?php
                            $lines    = substr_count($cssContent, "\n") + 1;
                            $rules    = substr_count($cssContent, '{');
                            $vars     = substr_count($cssContent, '--');
                            $comments = substr_count($cssContent, '/*');
                        ?>
                        <div class="css-stats">
                            <div class="css-stat">
                                <span class="css-stat-value"><?php echo number_format($lines); ?></span>
                                <span class="css-stat-label"><?php _e('css_lines'); ?></span>
                            </div>
                            <div class="css-stat">
                                <span class="css-stat-value"><?php echo $rules; ?></span>
                                <span class="css-stat-label"><?php _e('css_rules'); ?></span>
                            </div>
                            <div class="css-stat">
                                <span class="css-stat-value"><?php echo $vars; ?></span>
                                <span class="css-stat-label"><?php _e('css_variables'); ?></span>
                            </div>
                            <div class="css-stat">
                                <span class="css-stat-value"><?php echo $comments; ?></span>
                                <span class="css-stat-label"><?php _e('css_comments'); ?></span>
                            </div>
                        </div>

                        <h3><?php _e('backups'); ?></h3>
                        <?php if (empty($backups)): ?>
                            <p class="no-backups"><?php _e('css_no_backups'); ?></p>
                        <?php else: ?>
                            <ul class="backup-list">
                                <?php foreach ($backups as $b): ?>
                                    <li>
                                        <span class="backup-name"><?php echo htmlspecialchars($b['name']); ?></span>
                                        <span class="backup-meta"><?php echo $b['modified']; ?> &nbsp;·&nbsp; <?php echo $b['size']; ?> KB</span>
                                        <button
                                            type="button"
                                            class="btn-restore"
                                            data-backup="<?php echo htmlspecialchars($b['name']); ?>">
                                            <?php _e('restore'); ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-delete-backup"
                                            data-backup="<?php echo htmlspecialchars($b['name']); ?>">
                                            <?php _e('delete'); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div><!-- /editor-panel -->
                </div><!-- /css-editor-wrap -->
            </form>
        </main>
    </div>

    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
    <link  rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/panel.js"></script>
    <script src="js/common.js"></script>
    <script src="js/admin-sidebar.js"></script>

    <script>
    (function () {
        const t = (k, fb) => window.CMS_LANG?.[k] ?? fb ?? k;

        // Init CodeMirror
        var editor = CodeMirror.fromTextArea(document.getElementById('css-textarea'), {
            mode:             'css',
            theme:            'dracula',
            lineNumbers:      true,
            matchBrackets:    true,
            autoCloseBrackets: true,
            lineWrapping:     false,
            tabSize:          2,
            indentWithTabs:   false,
            extraKeys: {
                // Ctrl/Cmd + S → save
                'Ctrl-S': function(cm) { syncAndSubmit(cm); },
                'Cmd-S':  function(cm) { syncAndSubmit(cm); },
                // Ctrl/Cmd + / → toggle comment
                'Ctrl-/': function(cm) { cm.toggleComment(); },
                'Cmd-/':  function(cm) { cm.toggleComment(); },
            }
        });

        var isDirty    = false;
        var dirtyEl    = document.getElementById('dirty-indicator');
        var editorWrap = document.getElementById('editor-wrap');
        var form       = document.getElementById('css-editor-form');

        // Track changes
        editor.on('change', function () {
            if (!isDirty) {
                isDirty = true;
                editorWrap.classList.add('is-dirty');
            }
        });

        // Sync CodeMirror → textarea before any form submit
        form.addEventListener('submit', function () {
            editor.save();
        });

        function syncAndSubmit(cm) {
            cm.save();
            form.submit();
        }

        // Warn on navigation if unsaved
        window.addEventListener('beforeunload', function (e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Restore backup — use global modal instead of confirm()
        document.querySelectorAll('.btn-restore').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var backupName = btn.getAttribute('data-backup');
                showModal(
                    '<strong>' + backupName + '</strong><br><br>' +
                    t('css_restore_confirm_body'),
                    t('restore_backup_btn'),
                    {
                        danger:      true,
                        showCancel:  true,
                        cancelText:  t('cancel'),
                        confirmText: t('restore'),
                        onConfirm: function () {
                            // Inject a hidden input and submit the form
                            var input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = 'restore_backup';
                            input.value = backupName;
                            form.appendChild(input);
                            // Skip the unsaved-changes warning for restore
                            isDirty = false;
                            form.submit();
                        }
                    }
                );
            });
        });

        // Delete backup — modal confirmation
        document.querySelectorAll('.btn-delete-backup').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var backupName = btn.getAttribute('data-backup');
                showModal(
                    '<strong>' + backupName + '</strong><br><br>' +
                    t('css_delete_confirm_body'),
                    t('backup_delete_confirm_title'),
                    {
                        danger:      true,
                        showCancel:  true,
                        cancelText:  t('cancel'),
                        confirmText: t('delete'),
                        onConfirm: function () {
                            var input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = 'delete_backup';
                            input.value = backupName;
                            form.appendChild(input);
                            isDirty = false;
                            form.submit();
                        }
                    }
                );
            });
        });
        // (PHP handles the reload, JS state resets automatically)
    })();
    </script>
</body>
</html>