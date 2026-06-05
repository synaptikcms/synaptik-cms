<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

require_once dirname(__DIR__) . '/includes/backup-functions.php';

$root      = dirname(dirname(__DIR__));
$_upd_info = admin_check_for_update();

$_skipPrefixes = ['__MACOSX/', 'data/', 'files/', 'bckps/', 'cache/', 'private/', 'admin/cache/', 'admin/drafts/'];
$_skipFiles    = ['.DS_Store', 'settings.json', 'install.lock', 'install.php', 'admin/admin-credentials.php'];

if (!function_exists('_upd_detect_prefix')) {
	/**
	 * Detects a common root prefix in a ZIP archive (e.g. "SynaptikCMS-release/").
	 * Returns "" if files are at root level.
	 */
	function _upd_detect_prefix(ZipArchive $zip): string
	{
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
}

if (!function_exists('_upd_should_skip')) {
	/**
	 * Returns true if the ZIP entry should be skipped (user data, runtime dirs).
	 * Uses the original ZIP path names (always 'admin/...'), not the remapped ones.
	 */
	function _upd_should_skip(string $entryName, array $skipPrefixes, array $skipFiles): bool
	{
		foreach ($skipPrefixes as $prefix) {
			if (strpos($entryName, $prefix) === 0) return true;
		}
		return in_array($entryName, $skipFiles, true);
	}
}

// ── Apply update ──────────────────────────────────────────────────────────────
if (isset($_POST['apply_update'])) {

	if (!class_exists('ZipArchive')) {
		$_SESSION['error'] = __t('update_failed_no_ziparchive');
		header('Location: index.php?action=update');
		exit;
	}

	if (empty($_upd_info['download_url']) || strtolower(substr($_upd_info['download_url'], -4)) !== '.zip') {
		$_SESSION['error'] = __t('update_no_zip');
		header('Location: index.php?action=update');
		exit;
	}

	$downloadUrl = $_upd_info['download_url'];
	$bckpsDir    = $root . '/bckps';
	if (!is_dir($bckpsDir)) mkdir($bckpsDir, 0755, true);
	$releaseZip  = $bckpsDir . '/update-download-' . date('Y-m-d-His') . '.zip';

	// ── Download ───────────────────────────────────────────────────────────────
	$downloaded = false;
	if (function_exists('curl_init')) {
		$ch = curl_init($downloadUrl);
		$fh = fopen($releaseZip, 'wb');
		curl_setopt_array($ch, [
			CURLOPT_FILE           => $fh,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_USERAGENT      => 'SynaptikCMS-Updater/1.0',
		]);
		curl_exec($ch);
		$curlErr = curl_errno($ch);
		curl_close($ch);
		fclose($fh);
		$downloaded = ($curlErr === 0 && file_exists($releaseZip) && filesize($releaseZip) > 0);
	}

	if (!$downloaded && ini_get('allow_url_fopen')) {
		$ctx        = stream_context_create(['http' => ['timeout' => 120]]);
		$data       = @file_get_contents($downloadUrl, false, $ctx);
		$downloaded = ($data !== false && file_put_contents($releaseZip, $data) !== false);
		unset($data);
	}

	if (!$downloaded) {
		@unlink($releaseZip);
		$_SESSION['error'] = __t('update_failed_download');
		header('Location: index.php?action=update');
		exit;
	}

	// ── Validate ───────────────────────────────────────────────────────────────
	$zip = new ZipArchive();
	if ($zip->open($releaseZip) !== true) {
		@unlink($releaseZip);
		$_SESSION['error'] = __t('update_failed_invalid');
		header('Location: index.php?action=update');
		exit;
	}

	$_zipPrefix     = _upd_detect_prefix($zip);
	$hasVersionJson = ($zip->locateName($_zipPrefix . 'version.json') !== false);
	$hasCoreFile    = ($zip->locateName($_zipPrefix . 'index.php') !== false
	                || $zip->locateName($_zipPrefix . 'functions.php') !== false);

	if (!$hasVersionJson || !$hasCoreFile) {
		$zip->close();
		@unlink($releaseZip);
		$_SESSION['error'] = __t('update_failed_invalid');
		header('Location: index.php?action=update');
		exit;
	}
	$zip->close();

	// ── Safety backup ──────────────────────────────────────────────────────────
	$safetyZip = $bckpsDir . '/pre-update-backup-' . date('Y-m-d-His') . '.zip';
	if (!_backup_build_zip($root, $safetyZip)) {
		@unlink($releaseZip);
		$_SESSION['error'] = __t('update_failed_safety');
		header('Location: index.php?action=update');
		exit;
	}

	// ── Extract ────────────────────────────────────────────────────────────────
	$tmpDir = $bckpsDir . '/tmp-update-' . uniqid();
	if (!mkdir($tmpDir, 0755, true)) {
		@unlink($releaseZip);
		$_SESSION['error'] = __t('update_failed_extract');
		header('Location: index.php?action=update');
		exit;
	}

	$zip = new ZipArchive();
	$zip->open($releaseZip);
	$_extractPrefix = _upd_detect_prefix($zip);
	if (!$zip->extractTo($tmpDir)) {
		$zip->close();
		@unlink($releaseZip);
		_backup_clear_dir($tmpDir);
		@rmdir($tmpDir);
		$_SESSION['error'] = __t('update_failed_extract');
		header('Location: index.php?action=update');
		exit;
	}
	$zip->close();
	@unlink($releaseZip);

	// ── Copy to production ─────────────────────────────────────────────────────
	// The release ZIP always contains an 'admin/' directory. If the user renamed
	// their admin folder at install time, we detect the real name from __DIR__
	// and remap every 'admin/...' path to the actual folder name before copying.
	$_adminFolderName = basename(dirname(__DIR__)); // e.g. 'adminy'

	$ok      = true;
	$dirIter = new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS);
	$items   = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);
	foreach ($items as $item) {
		$rel = str_replace('\\', '/', $items->getSubPathname());

		// Strip common root prefix if ZIP is wrapped in a subfolder
		if ($_extractPrefix !== '' && strpos($rel, $_extractPrefix) === 0) {
			$rel = substr($rel, strlen($_extractPrefix));
		}
		if ($rel === '') continue;

		// Skip list uses original ZIP names ('admin/...') — check BEFORE remapping
		if (_upd_should_skip($rel, $_skipPrefixes, $_skipFiles)) continue;

		// Remap 'admin/' to the actual admin folder name
		if ($_adminFolderName !== 'admin') {
			if ($rel === 'admin') {
				$rel = $_adminFolderName;
			} elseif (strpos($rel, 'admin/') === 0) {
				$rel = $_adminFolderName . '/' . substr($rel, strlen('admin/'));
			}
		}

		$dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
		if ($item->isDir()) {
			if (!is_dir($dest)) mkdir($dest, 0755, true);
		} else {
			$destDir = dirname($dest);
			if (!is_dir($destDir)) mkdir($destDir, 0755, true);
			if (!copy($item->getRealPath(), $dest)) $ok = false;
		}
	}

	// ── Cleanup ────────────────────────────────────────────────────────────────
	_backup_clear_dir($tmpDir);
	@rmdir($tmpDir);

	$updateCacheFile = dirname(__DIR__) . '/cache/update-check.json';
	if (file_exists($updateCacheFile)) @unlink($updateCacheFile);
	if (function_exists('sl_clear_all_cache')) sl_clear_all_cache();

	if ($ok) {
		$_SESSION['message'] = __t('update_success');
		header('Location: index.php');
	} else {
		$_SESSION['error'] = __t('update_failed_apply');
		header('Location: index.php?action=update');
	}
	exit;
}

// ── Page display ──────────────────────────────────────────────────────────────
$_canAutoUpdate = !empty($_upd_info['download_url'])
	&& strtolower(substr($_upd_info['download_url'], -4)) === '.zip'
	&& class_exists('ZipArchive');

$_localVersion = '';
$_vPath        = $root . '/version.json';
if (file_exists($_vPath)) {
	$_vData        = json_decode(file_get_contents($_vPath), true);
	$_localVersion = $_vData['version'] ?? '';
}
?>

	<?php if (empty($_upd_info)): ?>
		<div class="site-settings-section">
			<p><?php _e('update_up_to_date'); ?></p>
		</div>

	<?php else: ?>
		<div class="site-settings-section">
			<h3>⬆ <?php _e('update_available'); ?> — <?php echo htmlspecialchars($_upd_info['version']); ?></h3>
			<div class="form-group">
				<div style="display:grid;gap:10px;max-width:520px;margin-bottom:20px;">
					<div style="display:flex;gap:12px;">
						<span style="color:var(--text-muted);min-width:140px;"><?php _e('update_current_version'); ?></span>
						<strong><?php echo htmlspecialchars($_localVersion); ?></strong>
					</div>
					<div style="display:flex;gap:12px;">
						<span style="color:var(--text-muted);min-width:140px;"><?php _e('update_available_version'); ?></span>
						<strong style="color:var(--color-success,#27ae60);"><?php echo htmlspecialchars($_upd_info['version']); ?></strong>
					</div>
					<?php if (!empty($_upd_info['released'])): ?>
					<div style="display:flex;gap:12px;">
						<span style="color:var(--text-muted);min-width:140px;"><?php _e('update_release_date'); ?></span>
						<span><?php echo htmlspecialchars($_upd_info['released']); ?></span>
					</div>
					<?php endif; ?>
					<?php if (!empty($_upd_info['notes'])): ?>
					<div style="display:flex;gap:12px;">
						<span style="color:var(--text-muted);min-width:140px;"><?php _e('update_release_notes'); ?></span>
						<span><?php echo htmlspecialchars($_upd_info['notes']); ?></span>
					</div>
					<?php endif; ?>
					<?php if (!empty($_upd_info['changelog_url'])): ?>
					<div style="display:flex;gap:12px;">
						<span style="color:var(--text-muted);min-width:140px;"></span>
						<a href="<?php echo htmlspecialchars($_upd_info['changelog_url']); ?>" target="_blank" rel="noopener">
							<?php _e('update_changelog_link'); ?> →
						</a>
					</div>
					<?php endif; ?>
				</div>

				<?php if ($_canAutoUpdate): ?>
					<div class="warning-box" style="background:rgba(52,152,219,.08);border-left:4px solid var(--color-info,#3498db);padding:14px;margin-bottom:18px;border-radius:0 4px 4px 0;">
						ℹ️ <?php _e('update_warning'); ?>
					</div>
					<form method="POST" action="index.php?action=update" id="update-form">
						<button type="button" class="button" style="background-color:#27ae60;" onclick="confirmUpdate();">
							⬆ <?php _e('update_apply_btn'); ?>
						</button>
					</form>
				<?php else: ?>
					<div class="warning-box" style="background:rgba(231,76,60,.08);border-left:4px solid var(--color-danger,#e74c3c);padding:14px;border-radius:0 4px 4px 0;">
						⚠️ <?php _e('update_no_zip'); ?>
						<?php if (!empty($_upd_info['download_url'])): ?>
							<br><a href="<?php echo htmlspecialchars($_upd_info['download_url']); ?>" target="_blank" rel="noopener" style="margin-top:6px;display:inline-block;">
								⬇ <?php _e('download'); ?> →
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<script>
	function confirmUpdate() {
		showModal(
			t('update_apply_confirm'),
			t('update_apply_confirm_title'),
			{
				showCancel:  true,
				confirmText: t('update_apply_btn'),
				cancelText:  t('cancel'),
				danger:      false,
				onConfirm: function() {
					const form  = document.getElementById('update-form');
					const input = document.createElement('input');
					input.type  = 'hidden';
					input.name  = 'apply_update';
					input.value = '1';
					form.appendChild(input);
					form.submit();
				}
			}
		);
	}
	</script>
