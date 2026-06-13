<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: auth.php');
    exit;
}

require_once 'includes/admin-functions.php';

$siteRoot    = dirname(__DIR__);
$settings    = json_decode(file_get_contents($siteRoot . '/settings.json'), true);
$activeTheme = $settings['active_theme'] ?? 'default';
$cssPath     = $siteRoot . '/theme/' . $activeTheme . '/css/style.css';
$backupDir   = $siteRoot . '/bckps/css/';

$message = '';
$error   = '';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['css_content'])
    && !isset($_POST['restore_backup']) && !isset($_POST['delete_backup'])) {
    $newCss     = $_POST['css_content'];
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

// Restore backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $backupToRestore = basename($_POST['restore_backup']);
    $fullBackupPath  = $backupDir . $backupToRestore;
    if (file_exists($fullBackupPath)) {
        $safetyBackup = $backupDir . 'style-pre-restore-' . date('Ymd-His') . '.css';
        copy($cssPath, $safetyBackup);
        if (copy($fullBackupPath, $cssPath)) {
            $message = __t('css_restore_success_prefix') . ' <code>' . htmlspecialchars($backupToRestore) . '</code>. '
                     . __t('css_restore_success_suffix') . ' <code>' . htmlspecialchars(basename($safetyBackup)) . '</code>.';
        } else {
            $error = __t('css_restore_failed');
        }
    } else {
        $error = __t('css_backup_not_found');
    }
}

// Delete backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $backupToDelete = basename($_POST['delete_backup']);
    $fullDeletePath = $backupDir . $backupToDelete;
    if (file_exists($fullDeletePath)) {
        if (unlink($fullDeletePath)) {
            $message = __t('css_backup_deleted_prefix') . ' <code>' . htmlspecialchars($backupToDelete) . '</code> '
                     . __t('css_backup_deleted_suffix') . '.';
        } else {
            $error = __t('css_backup_delete_failed');
        }
    } else {
        $error = __t('css_backup_not_found');
    }
}

$cssContent = file_exists($cssPath) ? file_get_contents($cssPath) : '/* style.css not found */';

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

$pageTitle = __t('css_theme_editor');

$extraHead = <<<HTML
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
<link rel="stylesheet" href="assets/css/admin-content.css">
<style>
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
    padding: 10px 20px;
}
.editor-toolbar .file-info {
    margin-left: auto;
    font-size: 0.78em;
    color: var(--text-muted);
    font-family: monospace;
}
.CodeMirror {
    height: 72vh;
    font-size: 13px;
    line-height: 1.6;
    border-radius: 0 0 6px 6px;
    border: 1px solid var(--border-strong);
    font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
}
.CodeMirror-scroll { padding-bottom: 20px; }
.dirty-indicator {
    display: none;
    background: var(--warning);
    color: #3d2800;
    font-size: 0.72em;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.is-dirty .dirty-indicator { display: inline-block; }
.editor-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 20px;
}
.editor-panel h3 {
    margin: 0 0 14px 0;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    padding-bottom: 10px;
}
.css-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 20px;
}
.css-stat {
    background: var(--surface-2);
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
.backup-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 360px;
    overflow-y: auto;
}
.backup-list li {
    border-bottom: 1px solid var(--border);
    padding: 10px 0;
}
.backup-list li:last-child { border-bottom: none; }
.backup-name {
    font-family: monospace;
    font-size: 0.78em;
    color: var(--text);
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
    transition: background var(--transition);
}
.btn-restore:hover { background: var(--info-hover); }
.btn-delete-backup {
    font-size: 0.75em;
    padding: 3px 10px;
    background: transparent;
    color: var(--danger-text);
    border: 1px solid var(--danger);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--transition), color var(--transition);
    margin-left: 4px;
}
.btn-delete-backup:hover { background: var(--danger); color: #fff; }
.no-backups { font-size: 0.82em; color: var(--text-muted); font-style: italic; }
@media (max-width: 1100px) {
    .css-editor-wrap { grid-template-columns: 1fr; }
    .CodeMirror { height: 55vh; }
}
</style>
HTML;

ob_start();
?>
<?php if (!file_exists($cssPath)): ?>
    <div class="message error">
        <strong><?php _e('css_file_not_found'); ?></strong><br>
        <?php _e('css_active_theme'); ?> <code><?php echo htmlspecialchars($activeTheme); ?></code><br>
        <?php _e('css_looking_here'); ?> <code><?php echo htmlspecialchars($cssPath); ?></code><br>
        <?php _e('css_adjust_path'); ?>
    </div>
<?php endif; ?>
<div class="alt-text-container">
    <p><?php _e('css_editor_desc'); ?><br><?php _e('css_editor_backup_desc'); ?></p>
</div>
<form method="post" action="" id="css-editor-form">
    <div class="css-editor-wrap">
        <div class="editor-main" id="editor-wrap">
            <div class="editor-toolbar">
                <button type="submit" class="btn btn-primary" id="save-btn"><?php _e('css_save_btn'); ?></button>
                <span class="dirty-indicator" id="dirty-indicator"><?php _e('css_unsaved_changes'); ?></span>
                <span class="file-info">style.css
                    &nbsp;·&nbsp;
                    <?php echo round(strlen($cssContent) / 1024, 1); ?> KB
                    &nbsp;·&nbsp;
                    <?php _e('last_modified'); ?> <?php echo file_exists($cssPath) ? date('M j, Y H:i', filemtime($cssPath)) : '—'; ?>
                </span>
            </div>
            <textarea id="css-textarea" name="css_content"><?php echo htmlspecialchars($cssContent); ?></textarea>
        </div>

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
                            <button type="button" class="btn-restore" data-backup="<?php echo htmlspecialchars($b['name']); ?>"><?php _e('restore'); ?></button>
                            <button type="button" class="btn-delete-backup" data-backup="<?php echo htmlspecialchars($b['name']); ?>"><?php _e('delete'); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = <<<HTML
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script>
(function () {
    const t = (k, fb) => window.CMS_LANG?.[k] ?? fb ?? k;

    var editor = CodeMirror.fromTextArea(document.getElementById('css-textarea'), {
        mode:              'css',
        theme:             'dracula',
        lineNumbers:       true,
        matchBrackets:     true,
        autoCloseBrackets: true,
        lineWrapping:      false,
        tabSize:           2,
        indentWithTabs:    false,
        extraKeys: {
            'Ctrl-S': function(cm) { syncAndSubmit(cm); },
            'Cmd-S':  function(cm) { syncAndSubmit(cm); },
            'Ctrl-/': function(cm) { cm.toggleComment(); },
            'Cmd-/':  function(cm) { cm.toggleComment(); },
        }
    });

    var isDirty    = false;
    var dirtyEl    = document.getElementById('dirty-indicator');
    var editorWrap = document.getElementById('editor-wrap');
    var form       = document.getElementById('css-editor-form');

    editor.on('change', function () {
        if (!isDirty) {
            isDirty = true;
            editorWrap.classList.add('is-dirty');
        }
    });

    form.addEventListener('submit', function () { editor.save(); });

    function syncAndSubmit(cm) { cm.save(); form.submit(); }

    window.addEventListener('beforeunload', function (e) {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    document.querySelectorAll('.btn-restore').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var backupName = btn.getAttribute('data-backup');
            showModal(
                '<strong>' + backupName + '</strong><br><br>' + t('css_restore_confirm_body'),
                t('restore_backup_btn'),
                {
                    danger: true, showCancel: true,
                    cancelText: t('cancel'), confirmText: t('restore'),
                    onConfirm: function () {
                        var input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'restore_backup'; input.value = backupName;
                        form.appendChild(input);
                        isDirty = false;
                        form.submit();
                    }
                }
            );
        });
    });

    document.querySelectorAll('.btn-delete-backup').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var backupName = btn.getAttribute('data-backup');
            showModal(
                '<strong>' + backupName + '</strong><br><br>' + t('css_delete_confirm_body'),
                t('backup_delete_confirm_title'),
                {
                    danger: true, showCancel: true,
                    cancelText: t('cancel'), confirmText: t('delete'),
                    onConfirm: function () {
                        var input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'delete_backup'; input.value = backupName;
                        form.appendChild(input);
                        isDirty = false;
                        form.submit();
                    }
                }
            );
        });
    });
})();
</script>
HTML;

require_once 'includes/layout.php';
