<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Extension (theme/plugin) update system.
 *
 * Mirrors admin_check_for_update() / update.php, but scoped to individual
 * themes and plugins rather than the CMS core. A single remote registry
 * (extensions.json, alongside version.json and news.json in the public
 * synaptik-cms-updates repo) lists the latest version and download URL for
 * every officially distributed theme and plugin, keyed by slug.
 *
 * Unlike the core updater, this never touches admin/ folder remapping or
 * legacy-file migration — a theme/plugin ZIP is always a single self-
 * contained folder copied as-is into theme/{slug}/ or plugins/{slug}/.
 */

// ─── Registry fetch ─────────────────────────────────────────────────────────

/**
 * Fetch the remote extensions registry (theme + plugin versions), cached
 * for 24 hours in admin/cache/ — same TTL and fetch strategy (cURL first,
 * file_get_contents fallback) as admin_check_for_update().
 *
 * @return array{themes: array, plugins: array}  Empty sub-arrays on failure.
 */
function admin_fetch_extensions_registry(): array {
	$remoteUrl = 'https://raw.githubusercontent.com/synaptikcms/synaptik-cms-updates/main/extensions.json';
	$cacheDir  = __DIR__ . '/../cache';
	$cacheFile = $cacheDir . '/extensions-check.json';
	$cacheTtl  = 86400; // 24 hours

	$empty = ['themes' => [], 'plugins' => []];

	if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
		$cached = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cached)) {
			return $cached + $empty;
		}
	}

	$json = false;
	if (function_exists('curl_init')) {
		$ch = curl_init($remoteUrl);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 3,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
		]);
		$json = curl_exec($ch);
		if (curl_errno($ch)) $json = false;
		// No curl_close() call: deprecated since PHP 8.5 and a no-op since PHP 8.0 —
		// handles are freed automatically by garbage collection.
	}
	if ($json === false && ini_get('allow_url_fopen')) {
		$ctx  = stream_context_create(['http' => ['timeout' => 3]]);
		$json = @file_get_contents($remoteUrl, false, $ctx);
	}
	if ($json === false) return $empty;

	$remote = json_decode($json, true);
	if (!is_array($remote)) return $empty;

	if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
	if (is_writable($cacheDir)) file_put_contents($cacheFile, $json);

	return $remote + $empty;
}

/**
 * Compare every installed theme against the remote registry.
 *
 * @return array<string, array{name:string, local_version:string, remote_version:string, download_url:string}>
 *         Keyed by theme slug — only themes with a newer remote version are included.
 */
function admin_check_theme_updates(): array {
	return _admin_check_extension_updates('theme');
}

/**
 * Compare every installed plugin against the remote registry.
 *
 * @return array<string, array{name:string, local_version:string, remote_version:string, download_url:string}>
 *         Keyed by plugin slug — only plugins with a newer remote version are included.
 */
function admin_check_plugin_updates(): array {
	return _admin_check_extension_updates('plugin');
}

/**
 * Shared implementation for admin_check_theme_updates() / admin_check_plugin_updates().
 *
 * @param string $type  'theme' or 'plugin'
 */
function _admin_check_extension_updates(string $type): array {
	$root     = dirname(dirname(__DIR__));
	$isTheme  = ($type === 'theme');
	$scanDir  = $root . ($isTheme ? '/theme' : '/plugins');
	$manifest = $isTheme ? 'theme.json' : 'plugin.json';

	if (!is_dir($scanDir)) return [];

	$registry = admin_fetch_extensions_registry();
	$remoteList = $isTheme ? ($registry['themes'] ?? []) : ($registry['plugins'] ?? []);
	if (empty($remoteList)) return [];

	$updates = [];
	foreach (scandir($scanDir) as $slug) {
		if ($slug === '.' || $slug === '..' || $slug[0] === '.') continue;
		$manifestPath = $scanDir . '/' . $slug . '/' . $manifest;
		if (!file_exists($manifestPath)) continue;

		$meta = json_decode(file_get_contents($manifestPath), true);
		if (!is_array($meta)) continue;

		$localVersion = $meta['version'] ?? '0';
		$remoteEntry  = $remoteList[$slug] ?? null;
		if (!$remoteEntry || empty($remoteEntry['version']) || empty($remoteEntry['download_url'])) continue;

		if (version_compare($remoteEntry['version'], $localVersion, '>')) {
			$updates[$slug] = [
				'name'           => $meta['name'] ?? $slug,
				'local_version'  => $localVersion,
				'remote_version' => $remoteEntry['version'],
				'download_url'   => $remoteEntry['download_url'],
			];
		}
	}

	return $updates;
}

// ─── Apply update ───────────────────────────────────────────────────────────

/**
 * Download and apply an update for a single installed theme or plugin.
 *
 * Flow mirrors update.php (download → validate → safety backup → extract →
 * copy → cleanup), scoped to the extension's own folder instead of the CMS
 * root. For plugins, data/ and private/ (user data, never shipped in
 * distribution ZIPs per the plugin-system convention) are preserved
 * unconditionally even if the downloaded ZIP happens to contain them.
 *
 * @param string $type  'theme' or 'plugin'
 * @param string $slug  Folder name under theme/ or plugins/
 * @return array{success: bool, error?: string}
 */
function admin_apply_extension_update(string $type, string $slug): array {
	require_once __DIR__ . '/backup-functions.php';

	$isTheme = ($type === 'theme');
	$slug    = preg_replace('/[^a-z0-9_\-]/', '', strtolower($slug));
	if ($slug === '') {
		return ['success' => false, 'error' => __t('ext_update_invalid_slug', 'Invalid theme/plugin identifier.')];
	}

	if (!class_exists('ZipArchive')) {
		return ['success' => false, 'error' => __t('update_failed_no_ziparchive')];
	}

	$root      = dirname(dirname(__DIR__));
	$updates   = $isTheme ? admin_check_theme_updates() : admin_check_plugin_updates();
	$entry     = $updates[$slug] ?? null;
	if (!$entry) {
		return ['success' => false, 'error' => __t('ext_update_not_found', 'No update available for this item — refresh and try again.')];
	}

	$downloadUrl = $entry['download_url'];
	if (strtolower(substr($downloadUrl, -4)) !== '.zip') {
		return ['success' => false, 'error' => __t('update_no_zip')];
	}

	$destDir = $root . '/' . ($isTheme ? 'theme' : 'plugins') . '/' . $slug;
	if (!is_dir($destDir)) {
		return ['success' => false, 'error' => __t('ext_update_not_installed', 'This item is not currently installed.')];
	}

	$bckpsDir = $root . '/bckps';
	if (!is_dir($bckpsDir)) mkdir($bckpsDir, 0755, true);

	// ── Download ─────────────────────────────────────────────────────────────
	$releaseZip  = $bckpsDir . '/ext-update-download-' . date('Y-m-d-His') . '.zip';
	$downloaded  = false;
	$httpFailure = false;
	if (function_exists('curl_init')) {
		$ch = curl_init($downloadUrl);
		$fh = fopen($releaseZip, 'wb');
		curl_setopt_array($ch, [
			CURLOPT_FILE           => $fh,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_FAILONERROR    => true,
			CURLOPT_USERAGENT      => 'SynaptikCMS-Updater/1.0',
		]);
		curl_exec($ch);
		$curlErr  = curl_errno($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		fclose($fh);
		if ($curlErr !== 0 || $httpCode >= 400) {
			$httpFailure = true;
			@unlink($releaseZip);
		} else {
			$downloaded = (file_exists($releaseZip) && filesize($releaseZip) > 0);
		}
		// No curl_close() call: deprecated since PHP 8.5 and a no-op since PHP 8.0 —
		// handles are freed automatically by garbage collection.
	}
	if (!$downloaded && !$httpFailure && ini_get('allow_url_fopen')) {
		$ctx  = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
		$data = @file_get_contents($downloadUrl, false, $ctx);
		$statusLine = $http_response_header[0] ?? '';
		if ($data !== false && preg_match('#^HTTP/\S+\s+(\d+)#', $statusLine, $m) && (int)$m[1] >= 400) {
			$httpFailure = true;
		} elseif ($data !== false) {
			$downloaded = (file_put_contents($releaseZip, $data) !== false);
		}
		unset($data);
	}
	if (!$downloaded) {
		@unlink($releaseZip);
		return ['success' => false, 'error' => $httpFailure
			? __t('ext_update_download_404', 'The download link for this update returned an error (file missing or renamed on the server). Contact the theme/plugin author.')
			: __t('update_failed_download')];
	}

	// ── Validate — the ZIP must contain the expected manifest ─────────────────
	$manifestName = $isTheme ? 'theme.json' : 'plugin.json';
	$zip = new ZipArchive();
	if ($zip->open($releaseZip) !== true) {
		@unlink($releaseZip);
		return ['success' => false, 'error' => __t('update_failed_invalid')];
	}
	$zipPrefix = _ext_upd_detect_prefix($zip);
	if ($zip->locateName($zipPrefix . $manifestName) === false) {
		$zip->close();
		@unlink($releaseZip);
		return ['success' => false, 'error' => __t('update_failed_invalid')];
	}
	$zip->close();

	// ── Safety backup of the current extension folder only ────────────────────
	$safetyZip = $bckpsDir . '/pre-ext-update-' . $slug . '-' . date('Y-m-d-His') . '.zip';
	if (!_ext_upd_backup_dir($destDir, $safetyZip)) {
		@unlink($releaseZip);
		return ['success' => false, 'error' => __t('update_failed_safety')];
	}

	// ── Extract to a temp folder ────────────────────────────────────────────
	$tmpDir = $bckpsDir . '/tmp-ext-update-' . uniqid();
	if (!mkdir($tmpDir, 0755, true)) {
		@unlink($releaseZip);
		return ['success' => false, 'error' => __t('update_failed_extract')];
	}
	register_shutdown_function(function () use ($tmpDir) {
		if (is_dir($tmpDir)) {
			_backup_clear_dir($tmpDir, false);
			@rmdir($tmpDir);
		}
	});

	$zip = new ZipArchive();
	$zip->open($releaseZip);
	if (!$zip->extractTo($tmpDir)) {
		$zip->close();
		@unlink($releaseZip);
		_backup_clear_dir($tmpDir, false);
		@rmdir($tmpDir);
		return ['success' => false, 'error' => __t('update_failed_extract')];
	}
	$zip->close();
	@unlink($releaseZip);

	// The extracted source is either $tmpDir itself (files at ZIP root) or
	// $tmpDir/{prefix} if the ZIP wraps everything in a single folder —
	// same convention as theme-upload.php / plugin-upload.php.
	$srcDir = $zipPrefix !== '' ? rtrim($tmpDir . '/' . $zipPrefix, '/') : $tmpDir;
	if (!is_dir($srcDir)) {
		_backup_clear_dir($tmpDir, false);
		@rmdir($tmpDir);
		return ['success' => false, 'error' => __t('update_failed_extract')];
	}

	// ── Copy over the existing folder ──────────────────────────────────────
	// Plugins: data/ and private/ hold user data (subscribers, bookings,
	// CSRF secrets, rate-limit stores) and are never part of a distributed
	// ZIP by convention — but skip them here too as a hard guarantee, in
	// case a release ZIP was built incorrectly.
	$preserve = $isTheme ? [] : ['data', 'private'];
	$ok = _ext_upd_copy_over($srcDir, $destDir, $preserve);

	// ── Cleanup ──────────────────────────────────────────────────────────────
	_backup_clear_dir($tmpDir, false);
	@rmdir($tmpDir);

	// Force a fresh registry check next page load so the badge disappears
	// immediately instead of waiting out the 24h cache.
	$cacheFile = __DIR__ . '/../cache/extensions-check.json';
	if (file_exists($cacheFile)) @unlink($cacheFile);
	if (function_exists('sl_clear_all_cache')) sl_clear_all_cache();

	if (!$ok) {
		return ['success' => false, 'error' => __t('update_failed_apply')];
	}
	return ['success' => true];
}

// ─── Internal helpers ───────────────────────────────────────────────────────

/**
 * Detects a common root prefix in a ZIP archive (e.g. "vanta-1.3.0/").
 * Same logic as _upd_detect_prefix() in update.php, duplicated locally
 * to keep this file self-contained and independently includable.
 */
function _ext_upd_detect_prefix(ZipArchive $zip): string {
	if ($zip->numFiles === 0) return '';
	$first = $zip->getNameIndex(0);
	$slash = strpos($first, '/');
	if ($slash === false) return '';
	$candidate = substr($first, 0, $slash + 1);
	for ($i = 1; $i < $zip->numFiles; $i++) {
		if (strpos($zip->getNameIndex($i), $candidate) !== 0) return '';
	}
	return $candidate;
}

/**
 * Build a safety-backup ZIP of a single theme/plugin folder before an
 * update overwrites it — scoped version of _backup_build_zip() from
 * backup-functions.php, which always backs up data/ and files/ at the
 * CMS root rather than an arbitrary folder.
 */
function _ext_upd_backup_dir(string $srcDir, string $zipPath): bool {
	if (!class_exists('ZipArchive') || !is_dir($srcDir)) return false;
	$zip = new ZipArchive();
	if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;

	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $f) {
		$rel = str_replace('\\', '/', $it->getSubPathname());
		$f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getRealPath(), $rel);
	}
	$zip->close();
	return file_exists($zipPath) && filesize($zipPath) > 0;
}

/**
 * Recursively copy $src over $dst, overwriting existing files, while never
 * touching any top-level subfolder named in $preserve (e.g. a plugin's
 * data/ and private/ directories).
 */
function _ext_upd_copy_over(string $src, string $dst, array $preserve = []): bool {
	$ok = true;
	$dh = @opendir($src);
	if (!$dh) return false;

	while (($item = readdir($dh)) !== false) {
		if ($item === '.' || $item === '..' || $item === '__MACOSX' || $item === '.DS_Store') continue;
		if (in_array($item, $preserve, true)) continue;

		$srcPath = $src . '/' . $item;
		$dstPath = $dst . '/' . $item;

		if (is_dir($srcPath)) {
			if (!is_dir($dstPath) && !mkdir($dstPath, 0755, true)) { $ok = false; continue; }
			if (!_ext_upd_copy_over($srcPath, $dstPath, [])) $ok = false; // preserve only applies at the top level
		} else {
			if (!copy($srcPath, $dstPath)) $ok = false;
		}
	}
	closedir($dh);
	return $ok;
}
