<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';
if (!admin_is_logged_in()) {
    header('Location: auth.php');
    exit;
}
if (!admin_is_admin()) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$siteRoot    = dirname(__DIR__);
$settings    = json_decode(file_get_contents($siteRoot . '/config.json'), true);
$activeTheme = $settings['active_theme'] ?? 'default';
$themeDir    = $siteRoot . '/theme/' . $activeTheme;
$backupDir   = $siteRoot . '/bckps/templates/' . $activeTheme . '/';

$message = '';
$error   = '';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$fileGroups = theme_editor_scan_files($themeDir);

$allFiles = [];
foreach ($fileGroups as $files) {
    foreach ($files as $f) $allFiles[] = $f;
}

$requestedFile = $_GET['file'] ?? $_POST['theme_file'] ?? '';
if ($requestedFile === '' && in_array('css/style.css', $allFiles, true)) {
    $requestedFile = 'css/style.css';
} elseif ($requestedFile === '' && !empty($allFiles)) {
    $requestedFile = $allFiles[0];
}

$activeFile = theme_editor_resolve_path($themeDir, $requestedFile);

$backupKey = $activeFile ? str_replace('/', '__', $requestedFile) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = __t('auth_csrf_error', 'Invalid security token. Please try again.');
        // Block all POST processing below by clearing the method
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_content'])
    && !isset($_POST['restore_backup']) && !isset($_POST['delete_backup'])) {
    if ($activeFile === null) {
        $error = __t('te_invalid_file');
    } else {
        $newContent = $_POST['file_content'];
        $backupFile = $backupDir . $backupKey . '-' . date('Ymd-His') . '.bak';
        if (!copy($activeFile, $backupFile)) {
            $error = __t('te_save_backup_failed') . ' <code>' . hsc($backupDir) . '</code>';
        } elseif (file_put_contents($activeFile, $newContent) !== false) {
            $message = __t('te_save_success') . ' <code>' . hsc(basename($backupFile)) . '</code>';
            sl_admin_log_activity('template_save', $requestedFile);
        } else {
            $error = __t('te_save_write_failed') . ' <code>' . hsc($activeFile) . '</code>';
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
                $message = __t('te_restore_success_prefix') . ' <code>' . hsc($backupToRestore) . '</code>. '
                         . __t('te_restore_success_suffix') . ' <code>' . hsc(basename($safetyBackup)) . '</code>';
                sl_admin_log_activity('template_restore', $requestedFile . ' <- ' . $backupToRestore);
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
        $message = __t('te_backup_deleted_prefix') . ' <code>' . hsc($backupToDelete) . '</code> '
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" integrity="sha384-zaeBlB/vwYsDRSlFajnDd7OydJ0cWk+c2OWybl3eSUf6hW2EbhlCsQPqKr3gkznT" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css" integrity="sha384-ccdJwIIg/K0Ab6aXF4MPACh7ckk61tvQFTrfkhXZEALgAETURNZIAuQLcS/aPbrM" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css" integrity="sha384-MomRjC6IKuGHk2XIFKXAwFx0gytd6+ZsF9pnFM3JWZV5izBqPgoLapxRqG1h5IKm" crossorigin="anonymous">
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
    background: var(--surface);
    border: 1px solid var(--border);
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
        <?php _e('te_active_theme'); ?> <code><?php echo hsc($activeTheme); ?></code>
    </div>
<?php endif; ?>
<div class="alt-text-container">
    <p><?php _e('te_editor_desc'); ?><br><?php _e('te_editor_backup_desc'); ?></p>
</div>
<form method="post" action="template-editor.php?file=<?php echo urlencode($requestedFile); ?>" id="template-editor-form">
    <input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="theme_file" value="<?php echo hsc($requestedFile); ?>">
    <div class="te-editor-wrap">
        <div class="editor-main" id="editor-wrap">
            <div class="editor-toolbar">
                <select class="te-file-select" id="te-file-select" data-cm-mode="<?php echo hsc($cmMode); ?>" data-requested-file="<?php echo hsc($requestedFile); ?>">
                    <?php foreach ($fileGroups as $groupLabel => $files): ?>
                        <?php if ($groupLabel === ''): ?>
                            <?php foreach ($files as $f): ?>
                                <option value="<?php echo hsc($f); ?>" <?php echo ($f === $requestedFile) ? 'selected' : ''; ?>><?php echo hsc($f); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <optgroup label="<?php echo hsc($groupLabel); ?>">
                                <?php foreach ($files as $f): ?>
                                    <option value="<?php echo hsc($f); ?>" <?php echo ($f === $requestedFile) ? 'selected' : ''; ?>><?php echo hsc(basename($f)); ?></option>
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
            <textarea id="te-textarea" name="file_content"><?php echo hsc($fileContent); ?></textarea>
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
                            <span class="backup-name"><?php echo hsc($b['name']); ?></span>
                            <span class="backup-meta"><?php echo $b['modified']; ?> &nbsp;·&nbsp; <?php echo $b['size']; ?> KB</span>
                            <button type="button" class="btn-restore" data-backup="<?php echo hsc($b['name']); ?>"><?php _e('restore'); ?></button>
                            <button type="button" class="btn-delete-backup" data-backup="<?php echo hsc($b['name']); ?>"><?php _e('delete'); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php
$pageContent = ob_get_clean();

$teJsVersion = @filemtime(__DIR__ . '/assets/js/template-editor.js');
$extraFooterScripts = <<<HTML
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js" integrity="sha384-ZYmwuq4n2gOcNxMSiJ6jyTj+BbIrilr7p6dlq6q5nmSWKmsH9UU4K1qqjycMkfmR" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js" integrity="sha384-fpeIC2FZuPmw7mIsTvgB5BNc8QVxQC/nWg2W+CgPYOAiBiYVuHe2E8HiTWHBMIJQ" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js" integrity="sha384-g0o+WW9mdIxA7LaaCKTkRm0M5TVT+Bb4s9eocxPsI2G0Xm0POG9iD6G6qP1IIsfS" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js" integrity="sha384-xPpkMo5nDgD98fIcuRVYhxkZV6/9Y4L8s3p0J5c4MxgJkyKJ8BJr+xfRkq7kn6Tw" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js" integrity="sha384-xYIbc5F55vPi7pb/lUnFj3wu24HlpAMZdtBHkNrb2YhPzJV3pX7+eqXT2PXSNMrw" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js" integrity="sha384-o9m634t2Hy35pPNKd9Xe16ntbSw11jCOuKPDrzQGXI8k87L2JZthaA3rwmJjnF7Z" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js" integrity="sha384-1FUwPY2kaZKXw258/9CYBSS+zcc3CPggxE1zLjmYYiOdkcOw3KcXH5VNJWWbjw2U" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js" integrity="sha384-LjCI3E8qhhxXZvu7+FCvqx9eZYSowFvuJ7z54KsgI/BDPGKEuysqCg/vYiKHvC4Y" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js" integrity="sha384-69mJoUoPPF/C7qPs6lLjvXvrt6w225+rmxWqGO3a1glVjITdnnwPQOtG9FRTd2Ni" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js" integrity="sha384-B6Af6BES5glvxvAPc9Vrl9t1lHx1k3iL8AcT1XmsmlEVZudSW8E+8CA1TxVbdQbj" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js" integrity="sha384-v64L7YTJ/ullw5v36qIJcvWAxuEnRGu9E326vUV3Ro7sx4HCZHIDTphKO53htazT" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js" integrity="sha384-ILkploZWukdp1VMmzMnE+32H0mgy2e+w29evc4grALGOqIRGBgbBGrwkX7a6zK7y" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js" integrity="sha384-3COleknUtlGKoEOR9Wm7WKVRyS6ljwYU2x1ebD8nd6ujaLMqwY+q3F8+yDcefbXr" crossorigin="anonymous"></script>
<script src="assets/js/template-editor.js?v={$teJsVersion}"></script>
HTML;

require_once 'includes/layout.php';
