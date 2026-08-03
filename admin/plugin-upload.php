<?php
/**
 * plugin-upload.php — admin/plugin-upload.php
 * ZIP import endpoint for plugins. Mirrors theme-upload.php's validation
 * pipeline (method/auth/CSRF, extension/size, ZipArchive scan for path
 * traversal and forbidden extensions, extract-to-tmp then copy), adapted
 * for plugin.json instead of theme.json and /plugins/ instead of /theme/.
 */

require_once __DIR__ . '/includes/session-config.php';
session_start();

define('INCLUDED', true);
require_once __DIR__ . '/includes/admin-functions.php';
require_once dirname(__DIR__) . '/plugin-api.php';

$redirect = 'index.php?action=plugins';

// Method check — must come first so non-POST requests are handled cleanly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: ' . $redirect); exit;
}

// Auth
if (!admin_is_logged_in()) {
	header('Location: auth.php'); exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
	$_SESSION['error'] = __t('auth_csrf_error', 'Security validation failed. Please try again.');
	header('Location: ' . $redirect);
	exit;
}

function plugin_error($msg) {
	global $redirect;
	$_SESSION['error'] = '🧩 ' . $msg;
	header('Location: ' . $redirect); exit;
}
function plugin_success($msg) {
	global $redirect;
	$_SESSION['message'] = '🧩 ' . $msg;
	header('Location: ' . $redirect); exit;
}

// ── 1. File present ───────────────────────────────────────────────────────────
if (empty($_FILES['plugin_zip'])) {
	plugin_error(__t('extensions_upload_no_data', 'No file data received (check upload_max_filesize in php.ini).'));
}

$upload = $_FILES['plugin_zip'];

if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
	plugin_error(__t('extensions_upload_no_file', 'No file selected.'));
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
	plugin_error($codes[$upload['error']] ?? sprintf(__t('theme_upload_err_unknown'), $upload['error']));
}

// ── 3. .zip extension ─────────────────────────────────────────────────────────
if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'zip') {
	plugin_error(sprintf(__t('extensions_upload_not_zip', 'The file "%s" is not a .zip.'), htmlspecialchars($upload['name'])));
}

// ── 4. Max size 20 MB ─────────────────────────────────────────────────────────
if ($upload['size'] > 20 * 1024 * 1024) {
	plugin_error(sprintf(__t('extensions_upload_too_large', 'File too large: %s MB (max 20 MB).'), round($upload['size'] / 1048576, 1)));
}

// ── 5. ZipArchive ─────────────────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
	plugin_error(__t('extensions_upload_no_ziparchive', 'PHP ZipArchive extension is not available on this server.'));
}

// ── 6. /plugins/ directory ────────────────────────────────────────────────────
$pluginsDir = PL_ROOT; // defined in plugin-api.php, already ensured to exist
if (!is_dir($pluginsDir)) {
	if (!@mkdir($pluginsDir, 0755, true)) {
		plugin_error(sprintf(__t('extensions_upload_no_plugins_dir', 'The /plugins/ folder was not found and could not be created (path: %s).'), htmlspecialchars($pluginsDir)));
	}
}
$pluginsDir .= DIRECTORY_SEPARATOR;

// ── 7. Open the ZIP ───────────────────────────────────────────────────────────
$zip = new ZipArchive();
$res = $zip->open($upload['tmp_name']);
if ($res !== true) {
	plugin_error(sprintf(__t('theme_upload_zip_open_failed'), $res));
}

// ── 8. Scan the ZIP ───────────────────────────────────────────────────────────
// Plugins are PHP-heavy by nature (unlike themes, which are mostly markup/
// assets), so .php is allowed here — this is inherent to what a plugin is,
// same trust boundary as installing any other server-side code.
$allowedExt    = ['php','css','js','json','html','htm','svg','png','jpg','jpeg','webp','gif','ico','woff','woff2','ttf','eot','otf','txt','md'];
$pluginRoot    = null;
$hasPluginJson = false;

for ($i = 0; $i < $zip->numFiles; $i++) {
	$entry = $zip->getNameIndex($i);

	// Path traversal
	if (strpos($entry, '..') !== false || strpos($entry, chr(0)) !== false) {
		$zip->close();
		plugin_error(sprintf(__t('theme_upload_path_traversal'), htmlspecialchars($entry)));
	}

	// Ignore macOS metadata
	if (strpos($entry, '__MACOSX/') === 0 || basename($entry) === '.DS_Store') continue;

	// Ignore directory entries
	if (substr($entry, -1) === '/') continue;

	// Server config files have no "extension" in the pathinfo() sense (the
	// whole filename is the leading dot) but are essential to a plugin's own
	// security (denying direct access to its data/private/ stores) — allow
	// them explicitly by exact filename rather than by extension.
	$baseName = basename($entry);
	if (in_array($baseName, ['.htaccess', '.user.ini'], true)) continue;

	// Ignore extensionless files (other dotfiles)
	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if ($ext === '') continue;

	// Whitelist extensions
	if (!in_array($ext, $allowedExt, true)) {
		$zip->close();
		plugin_error(sprintf(__t('theme_upload_ext_forbidden'), htmlspecialchars($ext), htmlspecialchars($entry)));
	}

	// Locate plugin.json
	if (basename($entry) === 'plugin.json') {
		$dir           = dirname($entry);
		$pluginRoot    = ($dir === '.' || $dir === '') ? '' : rtrim($dir, '/') . '/';
		$hasPluginJson = true;
	}
}

// ── 9. plugin.json required ───────────────────────────────────────────────────
if (!$hasPluginJson) {
	$zip->close();
	plugin_error(__t('extensions_upload_no_manifest', 'plugin.json is missing from the ZIP. It must be at the ZIP root with synaptik_plugin: true.'));
}

// ── 10. Read and validate plugin.json ─────────────────────────────────────────
$pluginJsonRaw = $zip->getFromName($pluginRoot . 'plugin.json');
if ($pluginJsonRaw === false) {
	$zip->close();
	plugin_error(sprintf(__t('extensions_upload_manifest_unreadable', 'Cannot read plugin.json (ZIP path: "%s").'), htmlspecialchars($pluginRoot . 'plugin.json')));
}

$meta = json_decode($pluginJsonRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
	$zip->close();
	plugin_error(sprintf(__t('extensions_upload_manifest_malformed', 'plugin.json is malformed: %s.'), json_last_error_msg()));
}
if (empty($meta['synaptik_plugin']) || $meta['synaptik_plugin'] !== true) {
	$zip->close();
	plugin_error(sprintf(__t('extensions_upload_manifest_invalid', 'plugin.json is invalid: the "synaptik_plugin" key must be true.')));
}

// ── 11. Entry file required ───────────────────────────────────────────────────
$entryFile = $meta['entry'] ?? '';
if (empty($entryFile) || $zip->locateName($pluginRoot . $entryFile) === false) {
	$zip->close();
	plugin_error(sprintf(__t('extensions_upload_entry_missing', 'plugin.json declares entry "%s", but that file is not present in the ZIP.'), htmlspecialchars($entryFile)));
}

// ── 12. Destination folder name ───────────────────────────────────────────────
$rawSlug = !empty($meta['slug']) ? $meta['slug'] : pathinfo($upload['name'], PATHINFO_FILENAME);
$slug    = preg_replace('/[^a-z0-9_\-]/', '', strtolower($rawSlug));
if (empty($slug)) $slug = 'plugin_' . time();

$destDir = $pluginsDir . $slug . DIRECTORY_SEPARATOR;

// Refuse to silently overwrite an existing plugin folder — the admin must
// delete the old one first (via the Extensions page) if they want to
// replace it, so an update never clobbers a differently-configured plugin
// that happens to share a slug.
if (is_dir($destDir)) {
	$zip->close();
	plugin_error(sprintf(__t('extensions_upload_already_exists', 'A plugin folder named "%s" already exists. Delete it first if you want to replace it.'), htmlspecialchars($slug)));
}

// ── 13. Extract to tmp ────────────────────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'synaptik_plugin_' . uniqid() . DIRECTORY_SEPARATOR;
if (!@mkdir($tmpDir, 0755, true)) {
	$zip->close();
	plugin_error(sprintf(__t('theme_upload_tmp_failed'), htmlspecialchars($tmpDir)));
}
if (!$zip->extractTo($tmpDir)) {
	$zip->close();
	plugin_error(sprintf(__t('theme_upload_extract_failed'), htmlspecialchars($tmpDir)));
}
$zip->close();

// ── 14. Copy to /plugins/{slug}/ ──────────────────────────────────────────────
$srcDir = $tmpDir . $pluginRoot;
if (!is_dir($srcDir)) {
	plugin_error(sprintf(__t('theme_upload_src_missing'), htmlspecialchars($srcDir)));
}

if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
	plugin_error(sprintf(__t('extensions_upload_dest_failed', 'Cannot create /plugins/%s/ — check server permissions.'), htmlspecialchars($slug)));
}

function plugin_copy_r($src, $dst) {
	$dh = @opendir($src);
	if (!$dh) return;
	while (($f = readdir($dh)) !== false) {
		if ($f === '.' || $f === '..' || $f === '__MACOSX' || $f === '.DS_Store') continue;
		$s = $src . $f; $d = $dst . $f;
		if (is_dir($s)) { if (!is_dir($d)) mkdir($d, 0755, true); plugin_copy_r($s . '/', $d . '/'); }
		else copy($s, $d);
	}
	closedir($dh);
}
function plugin_rmdir_r($dir) {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), ['.','..']) as $f) {
		$p = $dir . '/' . $f; is_dir($p) ? plugin_rmdir_r($p) : unlink($p);
	}
	rmdir($dir);
}

plugin_copy_r($srcDir, $destDir);
plugin_rmdir_r(rtrim($tmpDir, '/\\'));

// ── 15. Success — installed, but NOT activated automatically ─────────────────
// Activation is a separate explicit step from the Extensions page, same
// principle as themes requiring a separate "Activate" click after import.
plugin_success(sprintf(__t('extensions_upload_success', 'Plugin "%s" installed successfully. Activate it below to enable it.'), htmlspecialchars($meta['name'] ?? $slug)));
