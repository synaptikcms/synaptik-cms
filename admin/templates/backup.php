<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// ── POST handlers — doivent s'exécuter avant tout output ─────────────────────

if (isset($_POST['create_backup'])) {
	$data     = admin_load_data();
	$settings = admin_load_settings();
	$backup   = [
		'timestamp' => date('Y-m-d H:i:s'),
		'version'   => '1.0',
		'data'      => $data,
		'settings'  => $settings
	];
	$filename    = 'synaptik-backup-' . date('Y-m-d-His') . '.json';
	$jsonContent = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($jsonContent));
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');
	echo $jsonContent;
	exit;
}

if (isset($_POST['save_backup_to_server'])) {
	$data     = admin_load_data();
	$settings = admin_load_settings();
	$backup   = [
		'timestamp' => date('Y-m-d H:i:s'),
		'version'   => '1.0',
		'data'      => $data,
		'settings'  => $settings
	];
	$filename  = 'synaptik-backup-' . date('Y-m-d-His') . '.json';
	$backupDir = '../bckps/';
	if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
	$result = file_put_contents(
		$backupDir . $filename,
		json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
	);
	if ($result !== false) {
		$_SESSION['message'] = __t('backup_saved_to_server') . ': ' . $filename;
	} else {
		$_SESSION['error'] = __t('backup_save_failed');
	}
	header('Location: index.php?action=backup');
	exit;
}

if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
	if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
		$fileName      = $_FILES['backup_file']['name'];
		$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

		if ($fileExtension !== 'json') {
			$_SESSION['error'] = __t('backup_invalid_json_type');
			header('Location: index.php?action=backup'); exit;
		}

		$backupContent = file_get_contents($_FILES['backup_file']['tmp_name']);
		if ($backupContent === false || empty($backupContent)) {
			$_SESSION['error'] = __t('backup_read_failed');
			header('Location: index.php?action=backup'); exit;
		}

		$backup = json_decode($backupContent, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$_SESSION['error'] = __t('backup_invalid_json') . ': ' . json_last_error_msg();
			header('Location: index.php?action=backup'); exit;
		}

		if (!isset($backup['data']) || !is_array($backup['data']) || !isset($backup['settings']) || !is_array($backup['settings'])) {
			$_SESSION['error'] = __t('backup_invalid_structure');
			header('Location: index.php?action=backup'); exit;
		}

		$requiredSettingsKeys = ['site_title', 'active_theme'];
		foreach ($requiredSettingsKeys as $key) {
			if (!isset($backup['settings'][$key])) {
				$_SESSION['error'] = __t('backup_missing_key') . ': "' . $key . '"';
				header('Location: index.php?action=backup'); exit;
			}
		}

		// Safety backup avant restauration
		$safetyBackup = [
			'timestamp' => date('Y-m-d H:i:s'),
			'version'   => '1.0',
			'data'      => admin_load_data(),
			'settings'  => admin_load_settings()
		];
		$backupDir = '../bckps/';
		if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

		$safetyResult = file_put_contents(
			$backupDir . 'pre-restore-backup-' . date('Y-m-d-His') . '.json',
			json_encode($safetyBackup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);

		if ($safetyResult === false) {
			$_SESSION['error'] = __t('backup_safety_failed');
			header('Location: index.php?action=backup'); exit;
		}

		$dataResult     = admin_save_data($backup['data']);
		$settingsResult = file_put_contents(
			'../settings.json',
			json_encode($backup['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		);

		if ($dataResult === false || $settingsResult === false) {
			$_SESSION['error'] = __t('restore_failed');
		} else {
			$_SESSION['message'] = __t('restore_success');
		}
	} else {
		$errorMessages = [
			UPLOAD_ERR_INI_SIZE   => __t('upload_err_ini_size'),
			UPLOAD_ERR_FORM_SIZE  => __t('upload_err_form_size'),
			UPLOAD_ERR_PARTIAL    => __t('upload_err_partial'),
			UPLOAD_ERR_NO_FILE    => __t('upload_err_no_file'),
			UPLOAD_ERR_NO_TMP_DIR => __t('upload_err_no_tmp'),
			UPLOAD_ERR_CANT_WRITE => __t('upload_err_cant_write'),
			UPLOAD_ERR_EXTENSION  => __t('upload_err_extension'),
		];
		$errorCode    = $_FILES['backup_file']['error'];
		$errorMessage = $errorMessages[$errorCode] ?? __t('upload_err_unknown') . ' (code: ' . $errorCode . ')';
		$_SESSION['error'] = __t('backup_upload_error') . ': ' . $errorMessage;
	}
	header('Location: index.php?action=backup');
	exit;
}

if (isset($_POST['delete_backup'])) {
	$backupDir = '../bckps/';
	$filepath  = $backupDir . basename($_POST['backup_file']);
	if (file_exists($filepath) && strpos(realpath($filepath), realpath($backupDir)) === 0) {
		unlink($filepath);
		$_SESSION['message'] = __t('backup_deleted');
	} else {
		$_SESSION['error'] = __t('backup_not_found');
	}
	header('Location: index.php?action=backup');
	exit;
}

// ── Lecture des backups existants ─────────────────────────────────────────────

$backupDir = '../bckps/';
$backups   = [];
if (is_dir($backupDir)) {
	foreach (scandir($backupDir) as $file) {
		if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
			$backups[] = [
				'name' => $file,
				'size' => filesize($backupDir . $file),
				'date' => filemtime($backupDir . $file)
			];
		}
	}
	usort($backups, function($a, $b) { return $b['date'] - $a['date']; });
}
?>

		<p><?php _e('backup_page_desc'); ?></p>

		<div class="sitemap-content">
			<div class="tab-content">

			<!-- ── Sauvegarder sur le serveur ──────────────────────────────── -->
			<div class="site-settings-section">
				<h3>💾 <?php _e('save_backup_to_server'); ?></h3>
				<p><?php _e('backup_server_desc'); ?></p>
				<form method="POST" action="index.php?action=backup" style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
					<button type="submit" name="save_backup_to_server" class="button" style="background-color: #27ae60;">
						💾 <?php _e('save_backup_to_server'); ?>
					</button>
				</form>
				<p class="help-text" style="margin-top: 10px;"><?php _e('backup_server_help'); ?></p>
			</div>

			<!-- ── Restaurer ────────────────────────────────────────────────── -->
			<div class="site-settings-section">
				<h3>🔄 <?php _e('restore_from_backup'); ?></h3>
				<div class="warning-box" style="background: rgba(231,76,60,0.08); border-left: 4px solid var(--color-danger, #e74c3c); padding: 15px; margin-bottom: 20px; border-radius: 0 4px 4px 0;">
					<strong>⚠️ <?php _e('warning'); ?> :</strong> <?php _e('restore_backup_warning'); ?>
				</div>
				<form method="POST" action="index.php?action=backup" enctype="multipart/form-data" id="restore-backup-form">
					<div class="form-group">
						<label for="backup_file"><?php _e('select_backup_file'); ?> :</label>
						<input type="file" name="backup_file" id="backup_file" accept=".json" required style="padding: 10px; width: 100%; max-width: 450px;">
					</div>
					<button type="button" class="button" style="background-color: #f39c12;" onclick="validateAndConfirmRestore(this);">
						🔄 <?php _e('restore_backup_btn'); ?>
					</button>
				</form>
			</div>

			<!-- ── Backups sur le serveur ───────────────────────────────────── -->
			<div class="site-settings-section">
				<h3>💾 <?php _e('server_backups'); ?></h3>
				<p><?php _e('server_backups_desc'); ?></p>

				<?php if (empty($backups)): ?>
					<div class="message info" style="margin-top: 20px;"><?php _e('no_backups'); ?></div>
				<?php else: ?>
					<table style="width: 100%; margin-top: 20px; border-collapse: collapse; border-radius: 4px; overflow: hidden;">
						<thead>
							<tr style="background: var(--bg-secondary, #f8f9fa); border-bottom: 2px solid var(--border-color, #dee2e6);">
								<th style="text-align: left; padding: 12px; font-weight: 600;"><?php _e('filename'); ?></th>
								<th style="text-align: left; padding: 12px; font-weight: 600;"><?php _e('date_created'); ?></th>
								<th style="text-align: left; padding: 12px; font-weight: 600;"><?php _e('size'); ?></th>
								<th style="text-align: center; padding: 12px; font-weight: 600;"><?php _e('actions'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($backups as $backup): ?>
							<tr style="border-bottom: 1px solid var(--border-color, #dee2e6);">
								<td style="padding: 12px; font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($backup['name']); ?></td>
								<td style="padding: 12px;"><?php echo date('Y-m-d H:i:s', $backup['date']); ?></td>
								<td style="padding: 12px;"><?php echo admin_format_file_size($backup['size']); ?></td>
								<td style="padding: 12px; text-align: center;">
									<a href="backup-dl.php?file=<?php echo urlencode($backup['name']); ?>" class="button" style="display: inline-block; padding: 6px 12px; margin-right: 5px; font-size: 0.9em;">
										⬇️ <?php _e('download'); ?>
									</a>
									<button type="button" class="button danger" style="padding: 6px 12px; font-size: 0.9em;" onclick="deleteBackupFile('<?php echo htmlspecialchars($backup['name'], ENT_QUOTES); ?>');">
										🗑️ <?php _e('delete'); ?>
									</button>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<p class="help-text" style="margin-top: 15px;">
					<strong>💡 <?php _e('tip'); ?> :</strong> <?php _e('backup_tip'); ?>
				</p>
			</div>

		</div><!-- .tab-content -->
		</div><!-- .sitemap-content -->

	<script>
	function validateAndConfirmRestore(button) {
		const fileInput = document.getElementById('backup_file');
		if (!fileInput.files || fileInput.files.length === 0) {
			showModal(t('backup_no_file_selected'), t('backup_no_file_title'), { confirmText: 'OK', danger: false });
			return;
		}
		if (!fileInput.files[0].name.toLowerCase().endsWith('.json')) {
			showModal(t('backup_invalid_file'), t('backup_invalid_file_title'), { confirmText: 'OK', danger: true });
			return;
		}
		const form = button.closest('form');
		showModal(
			t('backup_restore_confirm'),
			t('backup_restore_confirm_title'),
			{
				showCancel:  true,
				confirmText: t('restore_backup_btn'),
				cancelText:  t('cancel'),
				danger:      true,
				onConfirm: function() {
					const input   = document.createElement('input');
					input.type    = 'hidden';
					input.name    = 'restore_backup';
					input.value   = '1';
					form.appendChild(input);
					form.submit();
				}
			}
		);
	}

	function deleteBackupFile(filename) {
		showModal(
			t('backup_delete_confirm'),
			t('backup_delete_confirm_title'),
			{
				showCancel:  true,
				confirmText: t('delete'),
				cancelText:  t('cancel'),
				danger:      true,
				onConfirm: function() {
					const form        = document.createElement('form');
					form.method       = 'POST';
					form.action       = 'index.php?action=backup';
					const fileInput   = document.createElement('input');
					fileInput.type    = 'hidden';
					fileInput.name    = 'backup_file';
					fileInput.value   = filename;
					form.appendChild(fileInput);
					const deleteInput = document.createElement('input');
					deleteInput.type  = 'hidden';
					deleteInput.name  = 'delete_backup';
					deleteInput.value = '1';
					form.appendChild(deleteInput);
					document.body.appendChild(form);
					form.submit();
				}
			}
		);
	}
	</script>