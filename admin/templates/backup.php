<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// ── Shared: build a full ZIP backup of the current site state ─────────────────
// Used both by the download handler and the safety backup before restore.
function _backup_build_zip(string $root, string $zipPath): bool
{
	if (!class_exists('ZipArchive')) return false;

	$zip = new ZipArchive();
	if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;

	$settingsFile = $root . '/settings.json';
	if (file_exists($settingsFile)) $zip->addFile($settingsFile, 'settings.json');

	$versionFile = $root . '/version.json';
	if (file_exists($versionFile)) $zip->addFile($versionFile, 'version.json');

	$addDir = function(string $absDir, string $zipPrefix) use ($zip): void {
		if (!is_dir($absDir)) return;
		$dirIter = new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS);
		$items   = new RecursiveIteratorIterator($dirIter, RecursiveIteratorIterator::SELF_FIRST);
		foreach ($items as $item) {
			$rel = $zipPrefix . '/' . str_replace('\\', '/', $items->getSubPathname());
			$item->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($item->getRealPath(), $rel);
		}
	};

	$addDir($root . '/data',  'data');
	$addDir($root . '/files', 'files');
	$zip->close();

	return file_exists($zipPath) && filesize($zipPath) > 0;
}

// ── Shared: recursively delete directory contents, preserving .htaccess ───────
function _backup_clear_dir(string $dir): void
{
	if (!is_dir($dir)) return;
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($iter as $item) {
		if (basename($item->getPathname()) === '.htaccess') continue;
		$item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
	}
}

// ── Shared: recursively copy a directory ──────────────────────────────────────
function _backup_copy_dir(string $src, string $dst): bool
{
	if (!is_dir($src)) return true; // nothing to copy is not an error
	if (!is_dir($dst)) mkdir($dst, 0755, true);
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($iter as $item) {
		$target = $dst . DIRECTORY_SEPARATOR . $iter->getSubPathname();
		if ($item->isDir()) {
			if (!is_dir($target)) mkdir($target, 0755, true);
		} else {
			if (!copy($item->getRealPath(), $target)) return false;
		}
	}
	return true;
}

// ── Delete a server-side backup ─────────────────────────────────────────────
if (isset($_POST['delete_backup'])) {
	$root      = dirname(dirname(__DIR__));
	$backupDir = realpath($root . '/bckps');
	$filepath  = $backupDir ? realpath($backupDir . DIRECTORY_SEPARATOR . basename($_POST['backup_file'] ?? '')) : false;

	if ($filepath && strpos($filepath, $backupDir) === 0 && is_file($filepath)) {
		unlink($filepath);
		$_SESSION['message'] = __t('backup_deleted');
	} else {
		$_SESSION['error'] = __t('backup_not_found');
	}
	header('Location: index.php?action=backup');
	exit;
}

// ── Download full ZIP backup ───────────────────────────────────────────────────
if (isset($_POST['create_full_zip_backup'])) {
	if (!class_exists('ZipArchive')) {
		$_SESSION['error'] = __t('backup_zip_unavailable');
		header('Location: index.php?action=backup');
		exit;
	}

	$root     = dirname(dirname(__DIR__));
	$filename = 'synaptik-full-backup-' . date('Y-m-d-His') . '.zip';
	$zipPath  = $root . '/bckps/' . $filename;

	if (!is_dir($root . '/bckps')) mkdir($root . '/bckps', 0755, true);

	if (!_backup_build_zip($root, $zipPath)) {
		$_SESSION['error'] = __t('backup_zip_open_failed');
		header('Location: index.php?action=backup');
		exit;
	}

	$filesize = filesize($zipPath);
	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . $filesize);
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');
	if (ob_get_level()) ob_end_clean();
	readfile($zipPath);
	@unlink($zipPath);
	exit;
}

// ── Restore from ZIP backup ────────────────────────────────────────────────────
if (isset($_POST['restore_zip_backup']) && isset($_FILES['backup_zip_file'])) {

	$root = dirname(dirname(__DIR__));

	// ── Upload validation ──────────────────────────────────────────────────────
	$uploadErr = $_FILES['backup_zip_file']['error'] ?? UPLOAD_ERR_NO_FILE;
	if ($uploadErr !== UPLOAD_ERR_OK) {
		$uploadErrMap = [
			UPLOAD_ERR_INI_SIZE   => __t('upload_err_ini_size'),
			UPLOAD_ERR_FORM_SIZE  => __t('upload_err_form_size'),
			UPLOAD_ERR_PARTIAL    => __t('upload_err_partial'),
			UPLOAD_ERR_NO_FILE    => __t('upload_err_no_file'),
			UPLOAD_ERR_NO_TMP_DIR => __t('upload_err_no_tmp'),
			UPLOAD_ERR_CANT_WRITE => __t('upload_err_cant_write'),
			UPLOAD_ERR_EXTENSION  => __t('upload_err_extension'),
		];
		$_SESSION['error'] = __t('restore_zip_upload_error') . ': ' . ($uploadErrMap[$uploadErr] ?? __t('upload_err_unknown'));
		header('Location: index.php?action=backup');
		exit;
	}

	if (!class_exists('ZipArchive')) {
		$_SESSION['error'] = __t('backup_zip_unavailable');
		header('Location: index.php?action=backup');
		exit;
	}

	$uploadedName = $_FILES['backup_zip_file']['name'];
	if (strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION)) !== 'zip') {
		$_SESSION['error'] = __t('restore_zip_invalid_type');
		header('Location: index.php?action=backup');
		exit;
	}

	// ── Open and validate ZIP structure ───────────────────────────────────────
	$zip = new ZipArchive();
	if ($zip->open($_FILES['backup_zip_file']['tmp_name']) !== true) {
		$_SESSION['error'] = __t('restore_zip_invalid');
		header('Location: index.php?action=backup');
		exit;
	}

	// Must contain settings.json and at least one file under data/
	$hasSettings = ($zip->locateName('settings.json') !== false);
	$hasData     = false;
	for ($i = 0; $i < $zip->numFiles; $i++) {
		if (strpos($zip->getNameIndex($i), 'data/') === 0) { $hasData = true; break; }
	}

	if (!$hasSettings || !$hasData) {
		$zip->close();
		$_SESSION['error'] = __t('restore_zip_invalid');
		header('Location: index.php?action=backup');
		exit;
	}

	// ── Safety backup of current state ────────────────────────────────────────
	$bckpsDir      = $root . '/bckps';
	if (!is_dir($bckpsDir)) mkdir($bckpsDir, 0755, true);
	$safetyZipPath = $bckpsDir . '/pre-restore-backup-' . date('Y-m-d-His') . '.zip';

	if (!_backup_build_zip($root, $safetyZipPath)) {
		$zip->close();
		$_SESSION['error'] = __t('restore_zip_safety_failed');
		header('Location: index.php?action=backup');
		exit;
	}

	// ── Extract to temp directory ─────────────────────────────────────────────
	$tmpDir = $bckpsDir . '/tmp-restore-' . uniqid();
	if (!mkdir($tmpDir, 0755, true)) {
		$zip->close();
		$_SESSION['error'] = __t('restore_zip_extract_failed');
		header('Location: index.php?action=backup');
		exit;
	}

	if (!$zip->extractTo($tmpDir)) {
		$zip->close();
		_backup_clear_dir($tmpDir);
		@rmdir($tmpDir);
		$_SESSION['error'] = __t('restore_zip_extract_failed');
		header('Location: index.php?action=backup');
		exit;
	}
	$zip->close();

	// ── Apply restore ─────────────────────────────────────────────────────────
	$ok = true;

	// settings.json
	$tmpSettings = $tmpDir . '/settings.json';
	if (file_exists($tmpSettings)) {
		$ok = $ok && (copy($tmpSettings, $root . '/settings.json') !== false);
		if (function_exists('loadSettings_invalidate')) loadSettings_invalidate();
	}

	// /data/ — clear existing JSON content, then copy from ZIP
	_backup_clear_dir($root . '/data');
	$ok = $ok && _backup_copy_dir($tmpDir . '/data', $root . '/data');

	// /files/ — clear existing media, then copy from ZIP (only if ZIP contains files/)
	if (is_dir($tmpDir . '/files')) {
		_backup_clear_dir($root . '/files');
		$ok = $ok && _backup_copy_dir($tmpDir . '/files', $root . '/files');
	}

	// ── Cleanup temp ──────────────────────────────────────────────────────────
	_backup_clear_dir($tmpDir);
	@rmdir($tmpDir);

	if ($ok) {
		$_SESSION['message'] = __t('restore_zip_success');
	} else {
		$_SESSION['error'] = __t('restore_zip_failed');
	}
	header('Location: index.php?action=backup');
	exit;
}
// ── Scan /bckps/ for existing backups ───────────────────────────────────────
$_bckpsDir = dirname(dirname(__DIR__)) . '/bckps';
$_backups  = [];
if (is_dir($_bckpsDir)) {
	foreach (scandir($_bckpsDir) as $_f) {
		$_ext = strtolower(pathinfo($_f, PATHINFO_EXTENSION));
		if ($_ext !== 'zip' && $_ext !== 'json') continue;
		if (strpos($_f, 'tmp-') === 0) continue;
		$_backups[] = [
			'name' => $_f,
			'size' => filesize($_bckpsDir . '/' . $_f),
			'date' => filemtime($_bckpsDir . '/' . $_f),
		];
	}
	usort($_backups, fn($a, $b) => $b['date'] - $a['date']);
}
?>

	<p><?php _e('backup_page_desc'); ?></p>

	<div class="backup-content">
		<div class="tab-content">

			<!-- ── Full ZIP backup ──────────────────────────────────────── -->
			<div class="site-settings-section">
				<h3><?php _e('backup_full_zip_title'); ?></h3>
				<div class="form-group">
					<p><?php _e('backup_full_zip_desc'); ?></p>
					<?php if (!class_exists('ZipArchive')): ?>
						<div class="warning-box" style="background:rgba(231,76,60,.08);border-left:4px solid var(--color-danger,#e74c3c);padding:15px;margin-top:16px;border-radius:0 4px 4px 0;">
							⚠️ <?php _e('backup_zip_unavailable'); ?>
						</div>
					<?php else: ?>
						<form method="POST" action="index.php?action=backup" style="margin-top:20px;">
							<button type="submit" name="create_full_zip_backup" class="button" style="background-color:#2980b9;">
								📦 <?php _e('backup_full_zip_btn'); ?>
							</button>
						</form>
						<p class="help-text" style="margin-top:10px;"><?php _e('backup_full_zip_help'); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── Restore from ZIP ─────────────────────────────────────── -->
			<div class="site-settings-section">
				<h3><?php _e('restore_zip_title'); ?></h3>
				<div class="form-group">
					<div class="warning-box" style="background:rgba(231,76,60,.08);border-left:4px solid var(--color-danger,#e74c3c);padding:15px;margin-bottom:20px;border-radius:0 4px 4px 0;">
						<strong>⚠️ <?php _e('warning'); ?> :</strong> <?php _e('restore_zip_warning'); ?>
					</div>
					<?php if (!class_exists('ZipArchive')): ?>
						<div class="warning-box" style="background:rgba(231,76,60,.08);border-left:4px solid var(--color-danger,#e74c3c);padding:15px;border-radius:0 4px 4px 0;">
							⚠️ <?php _e('backup_zip_unavailable'); ?>
						</div>
					<?php else: ?>
						<form method="POST" action="index.php?action=backup" enctype="multipart/form-data" id="restore-zip-form">
							<div class="form-group">
								<label for="backup_zip_file"><?php _e('restore_zip_select'); ?> :</label>
								<input type="file" name="backup_zip_file" id="backup_zip_file" accept=".zip" required style="padding:10px;width:100%;max-width:450px;">
							</div>
							<button type="button" class="button" style="background-color:#e67e22;margin-top:8px;" onclick="confirmRestoreZip(this);">
								🔄 <?php _e('restore_zip_btn'); ?>
							</button>
						</form>
						<p class="help-text" style="margin-top:10px;"><?php _e('restore_zip_desc'); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── Server backups table ──────────────────────────────── -->
			<div class="site-settings-section">
				<h3><?php _e('server_backups'); ?></h3>
				<div class="form-group">
					<p><?php _e('server_backups_desc'); ?></p>
					<?php if (empty($_backups)): ?>
						<div class="message info" style="margin-top:16px;"><?php _e('no_backups'); ?></div>
					<?php else: ?>
						<table style="width:100%;margin-top:20px;border-collapse:collapse;border-radius:4px;overflow:hidden;">
							<thead>
								<tr style="background:var(--bg-secondary);border-bottom:2px solid var(--border-color);">
									<th style="text-align:left;padding:12px;font-weight:600;"><?php _e('filename'); ?></th>
									<th style="text-align:left;padding:12px;font-weight:600;"><?php _e('date_created'); ?></th>
									<th style="text-align:left;padding:12px;font-weight:600;"><?php _e('size'); ?></th>
									<th style="text-align:center;padding:12px;font-weight:600;"><?php _e('actions'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($_backups as $_b): ?>
								<tr style="border-bottom:1px solid var(--border-color);">
									<td style="padding:12px;font-family:monospace;font-size:.9em;"><?php echo htmlspecialchars($_b['name']); ?></td>
									<td style="padding:12px;"><?php echo date('Y-m-d H:i:s', $_b['date']); ?></td>
									<td style="padding:12px;"><?php echo admin_format_file_size($_b['size']); ?></td>
									<td style="padding:12px;text-align:center;">
										<a href="backup-dl.php?file=<?php echo urlencode($_b['name']); ?>" class="button" style="display:inline-block;padding:6px 12px;margin-right:5px;font-size:.9em;">
											⬇️ <?php _e('download'); ?>
										</a>
										<button type="button" class="button danger" style="padding:6px 12px;font-size:.9em;" onclick="deleteBackup('<?php echo htmlspecialchars($_b['name'], ENT_QUOTES); ?>')">
											🗑️ <?php _e('delete'); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>

	<script>
	function deleteBackup(filename) {
		showModal(
			t('backup_delete_confirm'),
			t('backup_delete_confirm_title'),
			{
				showCancel:  true,
				confirmText: t('delete'),
				cancelText:  t('cancel'),
				danger:      true,
				onConfirm: function() {
					const form       = document.createElement('form');
					form.method      = 'POST';
					form.action      = 'index.php?action=backup';
					const fFile      = document.createElement('input');
					fFile.type       = 'hidden';
					fFile.name       = 'backup_file';
					fFile.value      = filename;
					const fAction    = document.createElement('input');
					fAction.type     = 'hidden';
					fAction.name     = 'delete_backup';
					fAction.value    = '1';
					form.appendChild(fFile);
					form.appendChild(fAction);
					document.body.appendChild(form);
					form.submit();
				}
			}
		);
	}

	function confirmRestoreZip(button) {
		const fileInput = document.getElementById('backup_zip_file');
		if (!fileInput.files || fileInput.files.length === 0) {
			showModal(t('restore_zip_no_file'), t('restore_zip_no_file_title'), { confirmText: 'OK', danger: false });
			return;
		}
		if (!fileInput.files[0].name.toLowerCase().endsWith('.zip')) {
			showModal(t('restore_zip_invalid_type_msg'), t('restore_zip_invalid_type_title'), { confirmText: 'OK', danger: true });
			return;
		}
		const form = button.closest('form');
		showModal(
			t('restore_zip_confirm'),
			t('restore_zip_confirm_title'),
			{
				showCancel:  true,
				confirmText: t('restore_zip_btn'),
				cancelText:  t('cancel'),
				danger:      true,
				onConfirm: function() {
					const input  = document.createElement('input');
					input.type   = 'hidden';
					input.name   = 'restore_zip_backup';
					input.value  = '1';
					form.appendChild(input);
					form.submit();
				}
			}
		);
	}
	</script>
