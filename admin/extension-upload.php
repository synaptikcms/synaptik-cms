<?php
/**
 * extension-upload.php — admin/extension-upload.php
 *
 * Unified ZIP import endpoint for themes, plugins and locale packs.
 * Accepts a POST field `_type` = 'theme' | 'plugin' | 'locale' to determine
 * the pipeline.
 *
 * Security pipeline (shared by all three): method check → auth → CSRF →
 * file present → PHP upload errors → .zip extension → size cap →
 * ZipArchive available → path-traversal/extension scan
 * (zip_validate_entries(), see includes/zip-validation.php).
 *
 * Themes and plugins then continue: manifest present & valid → required
 * files present → extract to tmp → copy to destination. Both may contain
 * PHP files; .htaccess is allowed for plugins only (they protect their
 * data/ and private/ folders) — themes must never ship one.
 *
 * Locale packs are a much narrower, JSON-only pipeline handled in their own
 * self-contained branch below (no manifest, no PHP, two fixed destinations:
 * lang/admin/ and lang/front/) — see the "Locale import" section.
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

define('INCLUDED', true);
require_once __DIR__ . '/includes/admin-functions.php';
require_once __DIR__ . '/includes/zip-validation.php';
require_once dirname(__DIR__) . '/core/plugin-api.php';

// ── Resolve type early so redirects are correct ───────────────────────────────
$type = in_array($_POST['_type'] ?? '', ['theme', 'locale'], true) ? $_POST['_type'] : 'plugin';

$redirect = match ($type) {
    'theme'  => 'index.php?action=manage_themes',
    'locale' => 'index.php?action=translations',
    default  => 'index.php?action=plugins',
};

// ── Method check ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect); exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!admin_is_logged_in()) {
    header('Location: auth.php'); exit;
}
if (!admin_is_admin()) {
    http_response_code(403);
    exit('Access denied.');
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
function ext_upload_icon(string $type): string
{
    return match ($type) {
        'theme'  => '📦',
        'locale' => '🌐',
        default  => '🧩',
    };
}

function ext_upload_error(string $msg): never
{
    global $redirect, $type;
    $_SESSION['error'] = ext_upload_icon($type) . ' ' . $msg;
    header('Location: ' . $redirect); exit;
}

function ext_upload_success(string $msg): never
{
    global $redirect, $type;
    $_SESSION['message'] = ext_upload_icon($type) . ' ' . $msg;
    header('Location: ' . $redirect); exit;
}

// ── Locale import — its own narrow pipeline, exits before the theme/plugin
// ── config section below (manifest lookup, extract-to-tmp/copy, etc. do
// ── not apply here at all — see the header comment).
if ($type === 'locale') {
    $label = trim((string)($_POST['locale_label'] ?? ''));
    if ($label === '' || mb_strlen($label) > 50) {
        ext_upload_error(__t('translations_label_required', 'The display name is required.'));
    }

    if (empty($_FILES['locale_zip'])) {
        ext_upload_error(__t('theme_upload_no_data', 'No file data received (check upload_max_filesize in php.ini).'));
    }
    $upload = $_FILES['locale_zip'];
    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        ext_upload_error(__t('theme_upload_no_file', 'No file selected.'));
    }
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
    if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'zip') {
        ext_upload_error(sprintf(__t('theme_upload_not_zip', 'The file "%s" is not a .zip.'), htmlspecialchars($upload['name'])));
    }
    // Locale packs are two small JSON files — 5 MB is already generous headroom.
    if ($upload['size'] > 5 * 1024 * 1024) {
        ext_upload_error(sprintf(__t('theme_upload_too_large', 'File too large: %s MB (max 20 MB).'), round($upload['size'] / 1048576, 1)));
    }
    if (!class_exists('ZipArchive')) {
        ext_upload_error(__t('theme_upload_no_ziparchive', 'PHP ZipArchive extension is not available on this server.'));
    }

    $zip = new ZipArchive();
    $zipOpenResult = $zip->open($upload['tmp_name']);
    if ($zipOpenResult !== true) {
        ext_upload_error(sprintf(__t('theme_upload_zip_open_failed', 'Could not open ZIP (error code: %s).'), $zipOpenResult));
    }

    // Path-traversal / dangerous-extension scan — same shared helper as
    // themes/plugins. Only .json allowed, .htaccess never (pure data, no
    // reason for it to ever appear in a locale pack).
    $valResult = zip_validate_entries($zip, ['json'], []);
    if (!$valResult['ok']) {
        $zip->close();
        ext_upload_error($valResult['error']);
    }

    // Structure: exactly two files, admin/{code}.json and front/{code}.json,
    // same code in both, nothing else (no subfolders, no stray files).
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (substr($name, -1) === '/') continue;
        if (strpos($name, '__MACOSX/') === 0 || basename($name) === '.DS_Store') continue;
        $entries[] = $name;
    }

    $found = [];
    if (count($entries) === 2) {
        foreach ($entries as $entry) {
            $parts = explode('/', $entry);
            if (count($parts) !== 2) { $found = []; break; }
            [$dir, $file] = $parts;
            if (!in_array($dir, ['admin', 'front'], true)) { $found = []; break; }
            if (!preg_match('/^([a-z]{2}(?:_[A-Z]{2})?)\.json$/', $file, $m)) { $found = []; break; }
            $found[$dir] = $m[1];
        }
    }
    if (count($found) !== 2 || $found['admin'] !== $found['front']) {
        $zip->close();
        ext_upload_error(__t('translations_import_bad_structure', 'The ZIP must contain exactly two files: admin/{code}.json and front/{code}.json, using the same locale code.'));
    }

    $locale = $found['admin'];
    if ($locale === 'en') {
        $zip->close();
        ext_upload_error(__t('translations_cannot_overwrite_reference', 'Cannot overwrite the built-in English reference.'));
    }

    $langDir = ['admin' => dirname(__DIR__) . '/lang/admin/', 'front' => dirname(__DIR__) . '/lang/front/'];

    if (file_exists($langDir['admin'] . $locale . '.json') || file_exists($langDir['front'] . $locale . '.json')) {
        $zip->close();
        ext_upload_error(sprintf(__t('translations_import_locale_exists', 'Locale "%s" already exists. Delete it first if you want to re-import.'), htmlspecialchars($locale)));
    }

    // Validate + prepare both scopes before writing anything — a half
    // -imported locale (admin written, front rejected) would be worse than
    // no import at all.
    $toWrite = [];
    $stats   = [];
    foreach (['admin', 'front'] as $scope) {
        $raw     = $zip->getFromName($scope . '/' . $locale . '.json');
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $zip->close();
            ext_upload_error(sprintf(__t('translations_import_invalid_json', '"%s/%s.json" is not valid JSON.'), $scope, $locale));
        }

        $reference = json_decode(file_get_contents($langDir[$scope] . 'en.json'), true);
        if (!is_array($reference)) {
            $zip->close();
            ext_upload_error(__t('translations_reference_missing', 'Reference translation file is missing — cannot validate the import.'));
        }
        unset($reference['_meta']);

        // Same whitelist policy as the manual translation editor's save
        // op: only keys that exist in en.json are kept, everything else is
        // silently dropped (not fatal — lets an import from a slightly
        // different CMS version through without failing outright).
        $clean   = [];
        $applied = 0;
        foreach ($decoded as $key => $value) {
            if ($key === '_meta') continue; // server sets its own _meta below
            if (!array_key_exists($key, $reference) || !is_string($value) || strlen($value) > 20000) continue;
            $clean[$key] = $value;
            $applied++;
        }
        $clean['_meta'] = [
            'language' => $label,
            'locale'   => $locale,
            'author'   => admin_get_display_name(),
            'version'  => '1.0',
        ];

        $toWrite[$scope] = $clean;
        $stats[$scope]   = $applied;
    }
    $zip->close();

    // Atomic write (tmp + rename), same pattern as translations-api.php's
    // trl_write_locale() — plus purge the compiled lang cache for each scope.
    foreach ($toWrite as $scope => $data) {
        $path = $langDir[$scope] . $locale . '.json';
        $tmp  = $path . '.tmp.' . getmypid();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            ext_upload_error(sprintf(__t('translations_import_write_failed', 'Could not write %s/%s.json.'), $scope, $locale));
        }
        $cacheFile = dirname(__DIR__) . '/cache/lang/' . $scope . '/' . $locale . '.php';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
            if (function_exists('opcache_invalidate')) opcache_invalidate($cacheFile, true);
        }
    }

    ext_upload_success(sprintf(
        __t('translations_import_success', 'Locale "%s" (%s) imported — %d admin strings, %d front strings.'),
        htmlspecialchars($label),
        htmlspecialchars($locale),
        $stats['admin'],
        $stats['front']
    ));
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
$allowedExt_htaccess = $isTheme ? [] : ['']; // plugins: allowed anywhere; themes: never

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
$extRoot     = null;
$hasManifest = false;

$valResult = zip_validate_entries($zip, $allowedExt, $allowedExt_htaccess);
if (!$valResult['ok']) {
    $zip->close();
    ext_upload_error($valResult['error']);
}

// Locate manifest separately (zip_validate_entries does not need to know about it)
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry    = $zip->getNameIndex($i);
    $baseName = basename($entry);
    if ($baseName === $manifest) {
        $dir     = dirname($entry);
        $extRoot = ($dir === '.' || $dir === '') ? '' : rtrim($dir, '/') . '/';
        $hasManifest = true;
        break;
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
    sl_admin_log_activity('theme_install', $meta['name'] ?? $dirName);
    ext_upload_success(sprintf(
        __t('theme_upload_success', 'Theme "%s" installed successfully in /theme/%s/.'),
        htmlspecialchars($meta['name'] ?? $dirName),
        htmlspecialchars($dirName)
    ));
} else {
    sl_admin_log_activity('extension_install', $meta['name'] ?? $dirName);
    ext_upload_success(sprintf(
        __t('extensions_upload_success', 'Plugin "%s" installed successfully. Activate it below to enable it.'),
        htmlspecialchars($meta['name'] ?? $dirName)
    ));
}
