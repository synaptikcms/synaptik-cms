<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// _backup_build_zip(), _backup_clear_dir(), _backup_copy_dir() and
// _backup_remove_dir() are shared with the updater (which needs them for
// its own safety backup and tmp-dir cleanup) — defined once, guarded, in
// includes/backup-functions.php. Do not redefine them here.
require_once dirname(__DIR__) . '/includes/backup-functions.php';

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

	require_once dirname(__DIR__) . '/includes/zip-validation.php';

	// ── Open and validate ZIP structure ───────────────────────────────────────
	$zip = new ZipArchive();
	if ($zip->open($_FILES['backup_zip_file']['tmp_name']) !== true) {
		$_SESSION['error'] = __t('restore_zip_invalid');
		header('Location: index.php?action=backup');
		exit;
	}

	// Must contain config.json (or legacy settings.json) and at least one file under data/
	$hasSettings = ($zip->locateName('config.json') !== false) || ($zip->locateName('settings.json') !== false);
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

	// Full entry scan: path traversal + extension whitelist
	// Backup archives contain JSON and media — .htaccess allowed only under data/, files/, private/.
	$bckpAllowedExt = ['json','jpg','jpeg','png','gif','webp','svg','ico','mp4','webm','pdf','txt','md','csv'];
	$valResult = zip_validate_entries($zip, $bckpAllowedExt, ['data/', 'files/', 'private/']);
	if (!$valResult['ok']) {
		$zip->close();
		$_SESSION['error'] = $valResult['error'];
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

	// Guarantee tmpDir is removed even if the script dies mid-restore
	// (PHP timeout, fatal error, memory limit on large /files/ copies).
	// Without this, a crash leaves tmp-restore-* directories on disk forever.
	register_shutdown_function(function () use ($tmpDir) {
		if (is_dir($tmpDir)) {
			_backup_remove_dir($tmpDir);
		}
	});

	if (!$zip->extractTo($tmpDir)) {
		$zip->close();
		_backup_remove_dir($tmpDir);
		$_SESSION['error'] = __t('restore_zip_extract_failed');
		header('Location: index.php?action=backup');
		exit;
	}
	$zip->close();

	// ── Apply restore ─────────────────────────────────────────────────────────
	$ok = true;

	// config.json (or legacy settings.json)
	$tmpSettings = $tmpDir . '/config.json';
	if (!file_exists($tmpSettings)) {
		$tmpSettings = $tmpDir . '/settings.json';
	}
	if (file_exists($tmpSettings)) {
		$ok = $ok && (copy($tmpSettings, $root . '/config.json') !== false);
		if (function_exists('loadConfig_invalidate')) loadConfig_invalidate();
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
	_backup_remove_dir($tmpDir);

	if ($ok) {
		$_SESSION['message'] = __t('restore_zip_success');
		sl_admin_log_activity('backup_restored');
	} else {
		$_SESSION['error'] = __t('restore_zip_failed');
	}
	header('Location: index.php?action=backup');
	exit;
}
// ── Scan /bckps/ for existing backups ───────────────────────────────────────
$_bckpsDir = dirname(dirname(__DIR__)) . '/bckps';

// Purge orphaned tmp-restore-* directories left over from interrupted/failed
// restores (e.g. extractTo() failure, PHP timeout mid-restore). These never
// persist past a single request normally, so any found here are stale.
if (is_dir($_bckpsDir)) {
	foreach (scandir($_bckpsDir) as $_d) {
		if (strpos($_d, 'tmp-restore-') === 0 && is_dir($_bckpsDir . '/' . $_d)) {
			_backup_remove_dir($_bckpsDir . '/' . $_d);
		}
	}
}

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

	<p class="help-text"><?php _e('backup_page_desc'); ?></p>

	<div class="backup-content">
		<div class="tab-content">

			<!-- ── Full ZIP backup ──────────────────────────────────────── -->
			<div class="site-settings-section">
				<h3><?php _e('backup_full_zip_title'); ?></h3>
				<div class="form-group">
					<p><?php _e('backup_full_zip_desc'); ?></p>
					<?php if (!class_exists('ZipArchive')): ?>
						<div class="message warning"><?php _e('backup_zip_unavailable'); ?></div>
					<?php else: ?>
						<form method="POST" action="index.php?action=backup" style="margin-top:16px;">
							<button type="submit" name="create_full_zip_backup" class="btn btn-primary">
								<?php echo admin_icon('download', 'style="margin-right:5px;vertical-align:-1px"', 13); ?><?php _e('backup_full_zip_btn'); ?>
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
					<div class="blockquote warning"><strong><?php _e('warning'); ?> :</strong> <?php _e('restore_zip_warning'); ?></div>
					<?php if (!class_exists('ZipArchive')): ?>
						<div class="blockquote warning" style="margin-top:10px;"><?php _e('backup_zip_unavailable'); ?></div>
					<?php else: ?>
						<form method="POST" action="index.php?action=backup" enctype="multipart/form-data" id="restore-zip-form" style="margin-top:16px;">
							<div class="form-group">
								<label for="backup_zip_file"><?php _e('restore_zip_select'); ?> :</label>
								<input type="file" style="width:auto;" name="backup_zip_file" id="backup_zip_file" accept=".zip" required>
							</div>
							<button type="button" class="btn btn-warning" id="restore-zip-confirm-btn">
								<?php echo admin_icon('update', 'style="margin-right:5px;vertical-align:-1px"', 13); ?><?php _e('restore_zip_btn'); ?>
							</button>
						</form>
						<p class="help-text" style="margin-top:10px;"><?php _e('restore_zip_desc'); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── Server backups table ──────────────────────────────────── -->
			<div class="site-settings-section">
				<h3><?php _e('server_backups'); ?></h3>
				<div class="form-group">
					<p><?php _e('server_backups_desc'); ?></p>
					<?php if (empty($_backups)): ?>
						<p class="help-text" style="margin-top:12px;"><?php _e('no_backups'); ?></p>
					<?php else: ?>
						<table style="margin-top:16px;">
							<thead>
								<tr>
									<th><?php _e('filename'); ?></th>
									<th><?php _e('date_created'); ?></th>
									<th><?php _e('size'); ?></th>
									<th><?php _e('actions'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($_backups as $_b): ?>
								<tr>
									<td style="font-family:monospace;font-size:.82em;"><?php echo htmlspecialchars($_b['name']); ?></td>
									<td><?php echo date('Y-m-d H:i', $_b['date']); ?></td>
									<td><?php echo admin_format_file_size($_b['size']); ?></td>
									<td>
										<a href="backup-dl.php?file=<?php echo urlencode($_b['name']); ?>" class="table-btn view-btn">
											<?php echo admin_icon('download', '', 12); ?><?php _e('download'); ?>
										</a>
										<button type="button" class="table-btn delete-btn delete-backup-btn" data-backup-name="<?php echo htmlspecialchars($_b['name'], ENT_QUOTES); ?>">
											<?php echo admin_icon('trash', '', 12); ?><?php _e('delete'); ?>
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

	<script src="assets/js/backup.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/backup.js'); ?>"></script>
