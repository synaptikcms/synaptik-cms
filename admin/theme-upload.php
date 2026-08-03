<?php
/**
 * theme-upload.php — /deevious/theme-upload.php
 * Endpoint dédié import thème ZIP.
 */

// Pas de session_start() ici : appelé directement depuis le navigateur,
// la session n'est PAS encore démarrée (contrairement à index.php).
require_once __DIR__ . '/includes/session-config.php';
session_start();

define('INCLUDED', true);
require_once __DIR__ . '/includes/admin-functions.php';

$redirect = 'index.php?action=settings&tab=general';

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
	$_SESSION['error'] = 'CSRF validation failed.';
	header('Location: ' . $redirect);
	exit;
}

// Redirect cible : paramètre optionnel envoyé par le form appelant
$allowedRedirects = [
	'index.php?action=settings&tab=general',
	'index.php?action=manage_themes',
];
$postRedirect = $_POST['_redirect'] ?? '';
if (in_array($postRedirect, $allowedRedirects, true)) {
	$redirect = $postRedirect;
}

// Fonction utilitaire : stocker le message et rediriger
function theme_error($msg) {
	global $redirect;
	$_SESSION['error'] = '📦 Import thème : ' . $msg;
	header('Location: ' . $redirect); exit;
}
function theme_success($msg) {
	global $redirect;
	$_SESSION['message'] = '📦 Import thème : ' . $msg;
	header('Location: ' . $redirect); exit;
}

// ── 1. Fichier présent ────────────────────────────────────────────────────────
if (empty($_FILES['theme_zip'])) {
	theme_error(__t('theme_upload_no_data'));
}

$upload = $_FILES['theme_zip'];

if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
	theme_error(__t('theme_upload_no_file'));
}

// ── 2. Erreurs PHP upload ─────────────────────────────────────────────────────
if ($upload['error'] !== UPLOAD_ERR_OK) {
	$codes = [
		UPLOAD_ERR_INI_SIZE   => __t('theme_upload_err_ini_size'),
		UPLOAD_ERR_FORM_SIZE  => __t('theme_upload_err_form_size'),
		UPLOAD_ERR_PARTIAL    => __t('theme_upload_err_partial'),
		UPLOAD_ERR_NO_TMP_DIR => __t('theme_upload_err_no_tmp'),
		UPLOAD_ERR_CANT_WRITE => __t('theme_upload_err_cant_write'),
		UPLOAD_ERR_EXTENSION  => __t('theme_upload_err_extension'),
	];
	theme_error($codes[$upload['error']] ?? sprintf(__t('theme_upload_err_unknown'), $upload['error']));
}

// ── 3. Extension .zip ─────────────────────────────────────────────────────────
if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'zip') {
	theme_error(sprintf(__t('theme_upload_not_zip'), htmlspecialchars($upload['name'])));
}

// ── 4. Taille max 20 Mo ───────────────────────────────────────────────────────
if ($upload['size'] > 20 * 1024 * 1024) {
	theme_error(sprintf(__t('theme_upload_too_large'), round($upload['size'] / 1048576, 1)));
}

// ── 5. ZipArchive ─────────────────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
	theme_error(__t('theme_upload_no_ziparchive'));
}

// ── 6. Dossier /theme/ ────────────────────────────────────────────────────────
// Ce fichier est dans /votresite/deevious/theme-upload.php
// /theme/ est dans /votresite/theme/
$themesDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'theme';

if (!is_dir($themesDir)) {
	// Essayer de le créer
	if (!@mkdir($themesDir, 0755, true)) {
		theme_error(sprintf(__t('theme_upload_no_theme_dir'), htmlspecialchars($themesDir)));
	}
}
$themesDir .= DIRECTORY_SEPARATOR;

// ── 7. Ouvrir le ZIP ──────────────────────────────────────────────────────────
$zip = new ZipArchive();
$res = $zip->open($upload['tmp_name']);
if ($res !== true) {
	theme_error(sprintf(__t('theme_upload_zip_open_failed'), $res));
}

// ── 8. Scanner le ZIP ─────────────────────────────────────────────────────────
$allowedExt   = ['php','css','js','json','html','htm','svg','png','jpg','jpeg','webp','gif','ico','woff','woff2','ttf','eot','otf','txt','md'];
$themeRoot    = null;
$hasThemeJson = false;

for ($i = 0; $i < $zip->numFiles; $i++) {
	$entry = $zip->getNameIndex($i);

	// Path traversal
	if (strpos($entry, '..') !== false || strpos($entry, chr(0)) !== false) {
		$zip->close();
		theme_error(sprintf(__t('theme_upload_path_traversal'), htmlspecialchars($entry)));
	}

	// Ignorer métadonnées macOS (créées par Finder)
	if (strpos($entry, '__MACOSX/') === 0 || basename($entry) === '.DS_Store') continue;

	// Ignorer les dossiers
	if (substr($entry, -1) === '/') continue;

	// Ignorer les fichiers sans extension (dotfiles)
	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if ($ext === '') continue;

	// Whitelist extensions
	if (!in_array($ext, $allowedExt, true)) {
		$zip->close();
		theme_error(sprintf(__t('theme_upload_ext_forbidden'), htmlspecialchars($ext), htmlspecialchars($entry)));
	}

	// Localiser theme.json
	if (basename($entry) === 'theme.json') {
		$dir       = dirname($entry);
		$themeRoot = ($dir === '.' || $dir === '') ? '' : rtrim($dir, '/') . '/';
		$hasThemeJson = true;
	}
}

// ── 9. theme.json obligatoire ─────────────────────────────────────────────────
if (!$hasThemeJson) {
	$zip->close();
	theme_error(__t('theme_upload_no_theme_json'));
}

// ── 10. Lire et valider theme.json ────────────────────────────────────────────
$themeJsonRaw = $zip->getFromName($themeRoot . 'theme.json');
if ($themeJsonRaw === false) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_theme_json_unreadable'), htmlspecialchars($themeRoot . 'theme.json')));
}

$meta = json_decode($themeJsonRaw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_theme_json_malformed'), json_last_error_msg()));
}
if (empty($meta['synaptik_theme']) || $meta['synaptik_theme'] !== true) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_theme_json_invalid'), htmlspecialchars(substr($themeJsonRaw, 0, 200))));
}

// ── 11. Fichiers requis ───────────────────────────────────────────────────────
$required = ['header.php', 'footer.php', 'home.php', 'css/style.css'];
$missing  = [];
foreach ($required as $req) {
	if ($zip->locateName($themeRoot . $req) === false) $missing[] = $req;
}
if (!empty($missing)) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_files_missing'), implode(', ', $missing)));
}

// ── 12. Nom du dossier destination ────────────────────────────────────────────
$rawName   = !empty($meta['folder']) ? $meta['folder']
		   : (!empty($meta['name'])   ? $meta['name']
		   : pathinfo($upload['name'], PATHINFO_FILENAME));
$themeName = preg_replace('/[^a-z0-9_\-]/', '', strtolower($rawName));
if (empty($themeName)) $themeName = 'theme_' . time();

$destDir = $themesDir . $themeName . DIRECTORY_SEPARATOR;

// ── 13. Extraire dans tmp ─────────────────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'synaptik_' . uniqid() . DIRECTORY_SEPARATOR;
if (!@mkdir($tmpDir, 0755, true)) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_tmp_failed'), htmlspecialchars($tmpDir)));
}
if (!$zip->extractTo($tmpDir)) {
	$zip->close();
	theme_error(sprintf(__t('theme_upload_extract_failed'), htmlspecialchars($tmpDir)));
}
$zip->close();

// ── 14. Copier vers /theme/{name}/ ───────────────────────────────────────────
$srcDir = $tmpDir . $themeRoot;
if (!is_dir($srcDir)) {
	theme_error(sprintf(__t('theme_upload_src_missing'), htmlspecialchars($srcDir)));
}

if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
	theme_error(sprintf(__t('theme_upload_dest_failed'), htmlspecialchars($themeName)));
}

function theme_copy_r($src, $dst) {
	$dh = @opendir($src);
	if (!$dh) return;
	while (($f = readdir($dh)) !== false) {
		if ($f === '.' || $f === '..' || $f === '__MACOSX' || $f === '.DS_Store') continue;
		$s = $src . $f; $d = $dst . $f;
		if (is_dir($s)) { if (!is_dir($d)) mkdir($d, 0755, true); theme_copy_r($s . '/', $d . '/'); }
		else copy($s, $d);
	}
	closedir($dh);
}
function theme_rmdir_r($dir) {
	if (!is_dir($dir)) return;
	foreach (array_diff(scandir($dir), ['.','..']) as $f) {
		$p = $dir . '/' . $f; is_dir($p) ? theme_rmdir_r($p) : unlink($p);
	}
	rmdir($dir);
}

theme_copy_r($srcDir, $destDir);
theme_rmdir_r(rtrim($tmpDir, '/\\'));

// ── 15. Succès ────────────────────────────────────────────────────────────────
theme_success(sprintf(__t('theme_upload_success'), htmlspecialchars($meta['name'] ?? $themeName), htmlspecialchars($themeName)));