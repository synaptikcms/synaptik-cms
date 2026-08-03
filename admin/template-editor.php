<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: auth.php');
    exit;
}

require_once 'includes/admin-functions.php';

$siteRoot    = dirname(__DIR__);
$settings    = json_decode(file_get_contents($siteRoot . '/settings.json'), true);
$activeTheme = $settings['active_theme'] ?? 'default';
$themeDir    = $siteRoot . '/theme/' . $activeTheme;
$backupDir   = $siteRoot . '/bckps/templates/' . $activeTheme . '/';

$message = '';
$error   = '';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$fileGroups = theme_editor_scan_files($themeDir);

// Flatten groups into a single ordered list for fallback/validation purposes.
$allFiles = [];
foreach ($fileGroups as $files) {
    foreach ($files as $f) $allFiles[] = $f;
}

// Resolve which file is being edited: request param, falling back to style.css
// then to the first available file.
$requestedFile = $_GET['file'] ?? $_POST['theme_file'] ?? '';
if ($requestedFile === '' && in_array('css/style.css', $allFiles, true)) {
    $requestedFile = 'css/style.css';
} elseif ($requestedFile === '' && !empty($allFiles)) {
    $requestedFile = $allFiles[0];
}

$activeFile = theme_editor_resolve_path($themeDir, $requestedFile);

// A backup filename must not collide across files with the same basename in
// different folders (e.g. partials/article-card.php vs page-templates/article-card.php).
$backupKey = $activeFile ? str_replace('/', '__', $requestedFile) : '';

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_content'])
    && !isset($_POST['restore_backup']) && !isset($_POST['delete_backup'])) {
    if ($activeFile === null) {
        $error = __t('te_invalid_file');
    } else {
        $newContent = $_POST['file_content'];
        $backupFile = $backupDir . $backupKey . '-' . date('Ymd-His') . '.bak';
        if (!copy($activeFile, $backupFile)) {
            $error = __t('te_save_backup_failed') . ' <code>' . htmlspecialchars($backupDir) . '</code>.';
        } elseif (file_put_contents($activeFile, $newContent) !== false) {
            $message = __t('te_save_success') . ' <code>' . htmlspecialchars(basename($backupFile)) . '</code>';
        } else {
            $error = __t('te_save_write_failed') . ' <code>' . htmlspecialchars($activeFile) . '</code>.';
        }
    }
}

// Restore backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if ($activeFile === null) {
        $error = __t('te_invalid_file');
    } else {
        $backupToRestore = basename($_POST['restore_backup']);
        $fullBackupPath  = $backupDir . $backupToRestore;
        // Backups must belong to the file currently being edited.
        if (strpos($backupToRestore, $backupKey . '-') !== 0 || !file_exists($fullBackupPath)) {
            $error = __t('te_backup_not_found');
        } else {
            $safetyBackup = $backupDir . $backupKey . '-pre-restore-' . date('Ymd-His') . '.bak';
            copy($activeFile, $safetyBackup);
            if (copy($fullBackupPath, $activeFile)) {
                $message = __t('te_restore_success_prefix') . ' <code>' . htmlspecialchars($backupToRestore) . '</code>. '
                         . __t('te_restore_success_suffix') . ' <code>' . htmlspecialchars(basename($safetyBackup)) . '</code>.';
            } else {
                $error = __t('te_restore_failed');
            }
        }
    }
}

// Delete backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $backupToDelete = basename($_POST['delete_backup']);
    $fullDeletePath = $backupDir . $backupToDelete;
    if (strpos($backupToDelete, $backupKey . '-') !== 0 || !file_exists($fullDeletePath)) {
        $error = __t('te_backup_not_found');
    } elseif (unlink($fullDeletePath)) {
        $message = __t('te_backup_deleted_prefix') . ' <code>' . htmlspecialchars($backupToDelete) . '</code> '
                 . __t('te_backup_deleted_suffix') . '.';
    } else {
        $error = __t('te_backup_delete_failed');
    }
}

$fileContent = ($activeFile && file_exists($activeFile)) ? file_get_contents($activeFile) : '';
$fileExt     = $activeFile ? strtolower(pathinfo($activeFile, PATHINFO_EXTENSION)) : '';

// CodeMirror mode per extension
$cmModes = [
    'php'  => 'application/x-httpd-php',
    'css'  => 'css',
    'js'   => 'javascript',
    'json' => 'application/json',
];
$cmMode = $cmModes[$fileExt] ?? 'php';
$cmModeJson = json_encode($cmMode);
$requestedFileJson = json_encode($requestedFile);

// Backups belonging to the currently selected file only
$backups = [];
if ($activeFile && is_dir($backupDir)) {
    $files = glob($backupDir . $backupKey . '-*.bak');
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

$pageTitle = __t('template_editor_title');

$extraHead = <<<HTML
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">
<link rel="stylesheet" href="assets/css/admin-content.css">
<style>
.te-editor-wrap {
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
.te-file-select {
    max-width: 250px;
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
    .te-editor-wrap { grid-template-columns: 1fr; }
    .CodeMirror { height: 55vh; }
}
</style>
HTML;

ob_start();
?>
<?php if ($activeFile === null): ?>
    <div class="message error">
        <strong><?php _e('te_no_file_found'); ?></strong><br>
        <?php _e('te_active_theme'); ?> <code><?php echo htmlspecialchars($activeTheme); ?></code>
    </div>
<?php endif; ?>
<div class="alt-text-container">
    <p><?php _e('te_editor_desc'); ?><br><?php _e('te_editor_backup_desc'); ?></p>
</div>
<form method="post" action="template-editor.php?file=<?php echo urlencode($requestedFile); ?>" id="template-editor-form">
    <input type="hidden" name="theme_file" value="<?php echo htmlspecialchars($requestedFile); ?>">
    <div class="te-editor-wrap">
        <div class="editor-main" id="editor-wrap">
            <div class="editor-toolbar">
                <select class="te-file-select" id="te-file-select" onchange="if(this.value) window.location.href='template-editor.php?file=' + encodeURIComponent(this.value);">
                    <?php foreach ($fileGroups as $groupLabel => $files): ?>
                        <?php if ($groupLabel === ''): ?>
                            <?php foreach ($files as $f): ?>
                                <option value="<?php echo htmlspecialchars($f); ?>" <?php echo ($f === $requestedFile) ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                <?php foreach ($files as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo ($f === $requestedFile) ? 'selected' : ''; ?>><?php echo htmlspecialchars(basename($f)); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" id="save-btn" <?php echo ($activeFile === null) ? 'disabled' : ''; ?>><?php _e('te_save_btn'); ?></button>
                <span class="dirty-indicator" id="dirty-indicator"><?php _e('te_unsaved_changes'); ?></span>
                <span class="file-info">
                    <?php echo round(strlen($fileContent) / 1024, 1); ?> KB
                    &nbsp;·&nbsp;
                    <?php _e('last_modified'); ?> <?php echo $activeFile ? date('M j, Y H:i', filemtime($activeFile)) : '—'; ?>
                </span>
            </div>
            <textarea id="te-textarea" name="file_content"><?php echo htmlspecialchars($fileContent); ?></textarea>
        </div>

        <div class="editor-panel">
            <h3><?php _e('te_file_stats'); ?></h3>
            <?php
                $lines = $fileContent !== '' ? substr_count($fileContent, "\n") + 1 : 0;
                if ($fileExt === 'css') {
                    $statA = ['te_rules', substr_count($fileContent, '{')];
                    $statB = ['te_variables', substr_count($fileContent, '--')];
                } elseif ($fileExt === 'php') {
                    $statA = ['te_functions', preg_match_all('/\b(?:(?:public|private|protected|static|abstract|final)\s+)*function\s*(?:&\s*)?(?:\w+\s*)?\(|\bfn\s*\(/i', $fileContent)];
                    $statB = ['te_comments', preg_match_all('#//|/\*#', $fileContent)];
                } elseif ($fileExt === 'js') {
                    $statA = ['te_functions', preg_match_all('/\bfunction\b|=>\s*[{(]/', $fileContent)];
                    $statB = ['te_comments', preg_match_all('#//|/\*#', $fileContent)];
                } else { // json
                    $statA = ['te_rules', substr_count($fileContent, ':')];
                    $statB = ['te_comments', 0];
                }
            ?>
            <div class="css-stats">
                <div class="css-stat">
                    <span class="css-stat-value"><?php echo number_format($lines); ?></span>
                    <span class="css-stat-label"><?php _e('te_lines'); ?></span>
                </div>
                <div class="css-stat">
                    <span class="css-stat-value"><?php echo number_format($statA[1]); ?></span>
                    <span class="css-stat-label"><?php _e($statA[0]); ?></span>
                </div>
                <div class="css-stat">
                    <span class="css-stat-value"><?php echo number_format($statB[1]); ?></span>
                    <span class="css-stat-label"><?php _e($statB[0]); ?></span>
                </div>
                <div class="css-stat">
                    <span class="css-stat-value"><?php echo strtoupper($fileExt ?: '—'); ?></span>
                    <span class="css-stat-label"><?php _e('te_filetype'); ?></span>
                </div>
            </div>

            <h3><?php _e('backups'); ?></h3>
            <?php if (empty($backups)): ?>
                <p class="no-backups"><?php _e('te_no_backups'); ?></p>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script>
(function () {
    const t = (k, fb) => window.CMS_LANG?.[k] ?? fb ?? k;

    var editor = CodeMirror.fromTextArea(document.getElementById('te-textarea'), {
        mode:              {$cmModeJson},
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
    var form       = document.getElementById('template-editor-form');

    editor.on('change', function () {
        if (!isDirty) {
            isDirty = true;
            editorWrap.classList.add('is-dirty');
        }
    });

    form.addEventListener('submit', function () { editor.save(); isDirty = false; });

    function syncAndSubmit(cm) { cm.save(); form.submit(); }

    window.addEventListener('beforeunload', function (e) {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    document.getElementById('te-file-select').addEventListener('change', function (e) {
        if (isDirty && !window.confirm(t('confirm_cancel'))) {
            e.preventDefault();
            this.value = {$requestedFileJson};
        }
    });

    document.querySelectorAll('.btn-restore').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var backupName = btn.getAttribute('data-backup');
            showModal(
                '<strong>' + backupName + '</strong><br><br>' + t('te_restore_confirm_body'),
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
                '<strong>' + backupName + '</strong><br><br>' + t('te_delete_confirm_body'),
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
