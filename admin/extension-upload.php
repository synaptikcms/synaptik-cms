<?php
/**
 * extension-upload.php — admin/extension-upload.php
 *
 * Unified ZIP import endpoint for both themes and plugins.
 * Accepts a POST field `_type` = 'theme' | 'plugin' to determine the pipeline.
 *
 * Security pipeline (identical for both types):
 *   method check → auth → CSRF → file present → PHP upload errors
 *   → .zip extension → 20 MB size cap → ZipArchive available
 *   → path-traversal scan → extension whitelist → manifest present & valid
 *   → required files present → extract to tmp → copy to destination
 *
 * Both themes and plugins may contain PHP files.
 * .htaccess is allowed for plugins only (they protect their data/ and private/ folders).
 * Themes additionally validate header.php / footer.php / home.php / css/style.css.
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

define('INCLUDED', true);
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/core/plugin-api.php';

// ── Resolve type early so redirects are correct ───────────────────────────────
$type = ($_POST['_type'] ?? '') === 'theme' ? 'theme' : 'plugin';

$redirect = $type === 'theme'
    ? 'index.php?action=manage_themes'
    : 'index.php?action=plugins';

// ── Method check ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect); exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!admin_is_logged_in()) {
    header('Location: auth.php'); exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error'] = __t('auth_csrf_error', 'Security validation failed. Please try again.');
    header('Location: ' . $redirect); exit;
}

// ── Optional redirect override (theme manager sends one) ─────────────────────
$allowedRedirects = [
    'index.php?action=manage_themes',
    'index.php?action=plugins',
];
$postRedirect = $_POST['_redirect'] ?? '';
if (in_array($postRedirect, $allowedRedirects, true)) {
    $redirect = $postRedirect;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function ext_upload_error(string $msg): never
{
    global $redirect, $type;
    $icon = $type === 'theme' ? '📦' : '🧩';
    $_SESSION['error'] = $icon . ' ' . $msg;
    header('Location: ' . $redirect); exit;
}

function ext_upload_success(string $msg): never
{
    global $redirect, $type;
    $icon = $type === 'theme' ? '📦' : '🧩';
    $_SESSION['message'] = $icon . ' ' . $msg;
    header('Location: ' . $redirect); exit;
}

// ── Config per type ───────────────────────────────────────────────────────────
$isTheme  = $type === 'theme';
$fileKey  = $isTheme ? 'theme_zip'   : 'plugin_zip';
$manifest = $isTheme ? 'theme.json'  : 'plugin.json';
$flagKey  = $isTheme ? 'synaptik_theme' : 'synaptik_plugin';
$destRoot = $isTheme
    ? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'theme'
    : PL_ROOT;                          // defined in plugin-api.php

// Both themes and plugins contain PHP files — PHP is always allowed.
// .htaccess is allowed for plugins only (they protect their data/ and private/ folders).
// Themes must never ship .htaccess files.
$allowedExt = ['php','css','js','json','html','htm','svg','png','jpg','jpeg','webp','gif','ico','woff','woff2','ttf','eot','otf','txt','md'];
$allowedExt_htaccess = !$isTheme; // true for plugins, false for themes

// ── 1. File present ───────────────────────────────────────────────────────────
if (empty($_FILES[$fileKey])) {
    ext_upload_error(__t('theme_upload_no_data', 'No file data received (check upload_max_filesize in php.ini).'));
}

$upload = $_FILES[$fileKey];

if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
    ext_upload_error(__t('theme_upload_no_file', 'No file selected.'));
}

// ── 2. PHP upload errors ──────────────────────────────────────────────────────
if ($upload['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => __t('theme_upload_err_ini_size'),
        UPLOAD_ERR_FORM_SIZE  => __t('theme_upload_err_form_size'),
        UPLOAD_ERR_PARTIAL    => __t('theme_upload_err_partial'),
        UPLOAD_ERR_NO_TMP_DIR => __t('theme_upload_err_no_tmp'),
        UPLOAD_ERR_CANT_WRITE => __t('theme_upload_err_cant_write'),
        UPLOAD_ERR_EXTENSION  => __t('theme_upload_err_extension'),
    ];
    ext_upload_error($codes[$upload['error']] ?? sprintf(__t('theme_upload_err_unknown', 'Upload error code: %s.'), $upload['error']));
}

// ── 3. .zip extension ─────────────────────────────────────────────────────────
if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'zip') {
    ext_upload_error(sprintf(__t('theme_upload_not_zip', 'The file "%s" is not a .zip.'), htmlspecialchars($upload['name'])));
}

// ── 4. Max size 20 MB ─────────────────────────────────────────────────────────
if ($upload['size'] > 20 * 1024 * 1024) {
    ext_upload_error(sprintf(__t('theme_upload_too_large', 'File too large: %s MB (max 20 MB).'), round($upload['size'] / 1048576, 1)));
}

// ── 5. ZipArchive ─────────────────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    ext_upload_error(__t('theme_upload_no_ziparchive', 'PHP ZipArchive extension is not available on this server.'));
}

// ── 6. Destination directory ──────────────────────────────────────────────────
if (!is_dir($destRoot) && !@mkdir($destRoot, 0755, true)) {
    ext_upload_error(sprintf(
        __t('extensions_upload_no_dir', 'The /%s/ folder was not found and could not be created.'),
        $isTheme ? 'theme' : 'plugins'
    ));
}
$destRoot = rtrim($destRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// ── 7. Open the ZIP ───────────────────────────────────────────────────────────
$zip = new ZipArchive();
$zipOpenResult = $zip->open($upload['tmp_name']);
if ($zipOpenResult !== true) {
    ext_upload_error(sprintf(__t('theme_upload_zip_open_failed', 'Could not open ZIP (error code: %s).'), $zipOpenResult));
}

// ── 8. Scan entries ───────────────────────────────────────────────────────────
$extRoot        = null;
$hasManifest    = false;

for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);

    // Path traversal
    if (strpos($entry, '..') !== false || strpos($entry, chr(0)) !== false) {
        $zip->close();
        ext_upload_error(sprintf(__t('theme_upload_path_traversal', 'Unsafe path in ZIP: "%s".'), htmlspecialchars($entry)));
    }

    // macOS metadata
    if (strpos($entry, '__MACOSX/') === 0 || basename($entry) === '.DS_Store') continue;

    // Directory entries
    if (substr($entry, -1) === '/') continue;

    $baseName = basename($entry);

    // .htaccess and .user.ini: allowed for plugins (security files), blocked for themes
    if (in_array($baseName, ['.htaccess', '.user.ini'], true)) {
        if (!$allowedExt_htaccess) {
            $zip->close();
            // Re-use theme_upload_ext_forbidden which expects (ext, filepath)
            ext_upload_error(sprintf(__t('theme_upload_ext_forbidden', 'Forbidden extension: .%s (file: %s).'), ltrim(strrchr($baseName, '.'), '.'), htmlspecialchars($entry)));
        }
        continue; // plugins: allowed, skip extension check
    }

    $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if ($ext === '') continue; // skip other dotfiles

    if (!in_array($ext, $allowedExt, true)) {
        $zip->close();
        ext_upload_error(sprintf(__t('theme_upload_ext_forbidden', 'Forbidden extension: .%s (file: %s).'), htmlspecialchars($ext), htmlspecialchars($entry)));
    }

    // Locate manifest
    if ($baseName === $manifest) {
        $dir      = dirname($entry);
        $extRoot  = ($dir === '.' || $dir === '') ? '' : rtrim($dir, '/') . '/';
        $hasManifest = true;
    }
}

// ── 9. Manifest required ──────────────────────────────────────────────────────
if (!$hasManifest) {
    $zip->close();
    ext_upload_error(sprintf(
        __t('extensions_upload_no_manifest', '"%s" is missing from the ZIP.'),
        $manifest
    ));
}

// ── 10. Read and validate manifest ────────────────────────────────────────────
$manifestRaw = $zip->getFromName($extRoot . $manifest);
if ($manifestRaw === false) {
    $zip->close();
    ext_upload_error(sprintf(__t('extensions_upload_manifest_unreadable', 'Cannot read "%s" from the ZIP.'), htmlspecialchars($extRoot . $manifest)));
}

$meta = json_decode($manifestRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $zip->close();
    ext_upload_error(sprintf(__t('extensions_upload_manifest_malformed', '"%s" is malformed: %s.'), $manifest, json_last_error_msg()));
}
if (empty($meta[$flagKey]) || $meta[$flagKey] !== true) {
    $zip->close();
    ext_upload_error(sprintf(__t('extensions_upload_manifest_invalid', '"%s" is invalid: "%s" must be true.'), $manifest, $flagKey));
}

// ── 11. Required files ────────────────────────────────────────────────────────
if ($isTheme) {
    $required = ['header.php', 'footer.php', 'home.php', 'css/style.css'];
    $missing  = [];
    foreach ($required as $req) {
        if ($zip->locateName($extRoot . $req) === false) $missing[] = $req;
    }
    if (!empty($missing)) {
        $zip->close();
        ext_upload_error(sprintf(__t('theme_upload_files_missing', 'Required files missing from ZIP: %s.'), implode(', ', $missing)));
    }
} else {
    // Plugin: entry file declared in manifest must exist in the ZIP
    $entryFile = $meta['entry'] ?? '';
    if (empty($entryFile) || $zip->locateName($extRoot . $entryFile) === false) {
        $zip->close();
        ext_upload_error(sprintf(
            __t('extensions_upload_entry_missing', 'plugin.json declares entry "%s" but that file is not in the ZIP.'),
            htmlspecialchars($entryFile)
        ));
    }
}

// ── 12. Destination folder name ───────────────────────────────────────────────
if ($isTheme) {
    $rawName  = $meta['folder'] ?? $meta['name'] ?? pathinfo($upload['name'], PATHINFO_FILENAME);
    $dirName  = preg_replace('/[^a-z0-9_\-]/', '', strtolower($rawName));
    if (empty($dirName)) $dirName = 'theme_' . time();
} else {
    $rawName  = $meta['slug'] ?? pathinfo($upload['name'], PATHINFO_FILENAME);
    $dirName  = preg_replace('/[^a-z0-9_\-]/', '', strtolower($rawName));
    if (empty($dirName)) $dirName = 'plugin_' . time();
}

$destDir = $destRoot . $dirName . DIRECTORY_SEPARATOR;

// Plugins refuse to overwrite silently; themes overwrite (update flow)
if (!$isTheme && is_dir($destDir)) {
    $zip->close();
    ext_upload_error(sprintf(
        __t('extensions_upload_already_exists', 'A plugin folder named "%s" already exists. Delete it first.'),
        htmlspecialchars($dirName)
    ));
}

// ── 13. Extract to tmp ────────────────────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'synaptik_ext_' . uniqid() . DIRECTORY_SEPARATOR;
if (!@mkdir($tmpDir, 0755, true)) {
    $zip->close();
    ext_upload_error(sprintf(__t('theme_upload_tmp_failed', 'Cannot create temp directory: %s.'), htmlspecialchars($tmpDir)));
}
if (!$zip->extractTo($tmpDir)) {
    $zip->close();
    ext_upload_error(sprintf(__t('theme_upload_extract_failed', 'Extraction to temp failed: %s.'), htmlspecialchars($tmpDir)));
}
$zip->close();

// ── 14. Copy to destination ───────────────────────────────────────────────────
$srcDir = $tmpDir . $extRoot;
if (!is_dir($srcDir)) {
    ext_upload_error(sprintf(__t('theme_upload_src_missing', 'Expected source directory not found: %s.'), htmlspecialchars($srcDir)));
}

if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
    ext_upload_error(sprintf(__t('theme_upload_dest_failed', 'Cannot create destination directory: %s.'), htmlspecialchars($dirName)));
}

function _ext_copy_r(string $src, string $dst): void
{
    $dh = @opendir($src);
    if (!$dh) return;
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..' || $f === '__MACOSX' || $f === '.DS_Store') continue;
        $s = $src . $f;
        $d = $dst . $f;
        if (is_dir($s)) {
            if (!is_dir($d)) mkdir($d, 0755, true);
            _ext_copy_r($s . '/', $d . '/');
        } else {
            copy($s, $d);
        }
    }
    closedir($dh);
}

function _ext_rmdir_r(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
        $p = $dir . '/' . $f;
        is_dir($p) ? _ext_rmdir_r($p) : unlink($p);
    }
    rmdir($dir);
}

_ext_copy_r($srcDir, $destDir);
_ext_rmdir_r(rtrim($tmpDir, '/\\'));

// ── 15. Success ───────────────────────────────────────────────────────────────
if ($isTheme) {
    ext_upload_success(sprintf(
        __t('theme_upload_success', 'Theme "%s" installed successfully in /theme/%s/.'),
        htmlspecialchars($meta['name'] ?? $dirName),
        htmlspecialchars($dirName)
    ));
} else {
    ext_upload_success(sprintf(
        __t('extensions_upload_success', 'Plugin "%s" installed successfully. Activate it below to enable it.'),
        htmlspecialchars($meta['name'] ?? $dirName)
    ));
}
