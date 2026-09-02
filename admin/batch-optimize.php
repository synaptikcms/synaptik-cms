<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once('includes/admin-functions.php');
if (!admin_is_logged_in()) {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
	} else {
		header('Location: auth.php');
	}
	exit;
}
if (!admin_can_manage_all_content()) {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		http_response_code(403);
		echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
	} else {
		http_response_code(403);
		exit('Access denied.');
	}
	exit;
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
$error   = isset($_SESSION['error'])   ? $_SESSION['error']   : null;
unset($_SESSION['message'], $_SESSION['error']);

session_write_close();

set_time_limit(120);
ini_set('memory_limit', '256M');

require_once('image-optimization.php');
require_once(dirname(__DIR__) . '/core/data-functions.php');
require_once(dirname(__DIR__) . '/core/core-functions.php');

$appSettings = [];
if (($raw = file_get_contents(dirname(__DIR__) . '/config.json')) !== false) {
	$dec = json_decode($raw, true);
	if (is_array($dec)) $appSettings = $dec;
}
$maxWidth       = (int)($appSettings['max_width']        ?? 1920);
$maxHeight      = (int)($appSettings['max_height']       ?? 1080);
$quality        = (int)($appSettings['image_quality']    ?? 85);
$webpConversion = (bool)($appSettings['convert_to_webp'] ?? true);
$webpSupported  = function_exists('imagewebp');
if (!$webpSupported) $webpConversion = false;

$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$baseDirAbs = realpath(__DIR__ . '/../files');

if (isset($_GET['scan'])) {
	header('Content-Type: application/json');

	if ($baseDirAbs === false) {
		echo json_encode(['status' => 'error', 'message' => __t('batch_files_dir_missing')]);
		exit;
	}

	$dir = $_GET['directory'] ?? '';

	if (strpos($dir, '..') !== false) {
		echo json_encode(['status' => 'error', 'message' => __t('batch_invalid_path')]);
		exit;
	}

	if ($dir === '') {
		$scanPath = $baseDirAbs;
	} else {
		$scanPath = $baseDirAbs . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
	}

	$realScan = realpath($scanPath);

	if ($realScan === false || !is_dir($realScan)) {
		echo json_encode(['status' => 'error', 'message' => __t('batch_dir_not_found')]);
		exit;
	}

	if ($realScan !== $baseDirAbs && strpos($realScan, $baseDirAbs . DIRECTORY_SEPARATOR) !== 0) {
		echo json_encode(['status' => 'error', 'message' => __t('batch_access_denied')]);
		exit;
	}

	$files = [];
	scanImageFiles($realScan, $files);

	echo json_encode(['status' => 'ok', 'files' => $files, 'count' => count($files)], JSON_UNESCAPED_UNICODE);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_one'])) {
	ignore_user_abort(true);
	header('Content-Type: application/json');
	$_csrfToken = $_POST['csrf_token'] ?? (getallheaders()['X-CSRF-Token'] ?? '');
	if (!is_string($_csrfToken) || !hash_equals($_SESSION['csrf_token'], $_csrfToken)) {
		http_response_code(403);
		echo json_encode(['status' => 'error', 'message' => __t('auth_csrf_error')]);
		exit;
	}
	echo json_encode(processOneFile($_POST['file_path'] ?? ''), JSON_UNESCAPED_UNICODE);
	exit;
}

function scanImageFiles($dir, array &$files) {
	global $imageExtensions;
	if (!is_dir($dir)) return;
	$items = scandir($dir);
	if ($items === false) return;
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') continue;
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if (is_dir($path)) {
			if ($item !== 'thumbs') scanImageFiles($path, $files);
		} elseif (is_file($path)) {
			$ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
			if (in_array($ext, $imageExtensions, true)
				&& strpos($item, 'thumb_')  !== 0
				&& strpos($item, 'backup_') !== 0) {
				$files[] = ['path' => $path, 'name' => $item];
			}
		}
	}
}

function processOneFile($filePath) {
	global $maxWidth, $maxHeight, $quality, $webpConversion, $webpSupported, $baseDirAbs;

	if (empty($filePath)) {
		return ['status' => 'error', 'file' => '', 'message' => __t('batch_empty_path')];
	}

	$realFile = realpath($filePath);
	if ($realFile === false || !is_file($realFile)) {
		return ['status' => 'error', 'file' => basename($filePath), 'message' => __t('batch_file_not_found_error')];
	}

	if (strpos($realFile, $baseDirAbs . DIRECTORY_SEPARATOR) !== 0) {
		return ['status' => 'error', 'file' => basename($filePath), 'message' => __t('batch_access_denied')];
	}

	$dir          = dirname($realFile);
	$file         = basename($realFile);
	$extension    = strtolower(pathinfo($file, PATHINFO_EXTENSION));
	$originalSize = filesize($realFile);
	$backupPath   = $dir . DIRECTORY_SEPARATOR . 'backup_' . $file;
	$doWebP       = $webpConversion && $webpSupported && $extension !== 'webp';
	$webpFile     = $doWebP
		? $dir . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '.webp'
		: '';

	if (!copy($realFile, $backupPath)) {
		return ['status' => 'error', 'file' => $file, 'message' => __t('batch_backup_failed')];
	}

	$result = optimizeImage(
		$backupPath,   // source = backup
		$realFile,     // destination = fichier original (sera remplacé)
		$maxWidth, $maxHeight, $quality,
		false, '', 0, 0,
		true,          // deleteOriginal → supprime $backupPath après succès
		$webpConversion
	);

	if ($result) {
		$optimizedSize = 0;
		if ($doWebP && file_exists($webpFile)) {
			// Conversion WebP réussie : le fichier original (JPEG/PNG) peut être supprimé
			$optimizedSize = filesize($webpFile);
			if (file_exists($realFile)) @unlink($realFile);

			$oldRelPath = 'files/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($realFile, strlen($baseDirAbs))), '/');
			$newRelPath = 'files/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($webpFile, strlen($baseDirAbs))), '/');
			sl_admin_relink_image_path($oldRelPath, $newRelPath);
		} elseif (file_exists($realFile)) {
			$optimizedSize = filesize($realFile);
		}
		// Le backup est supprimé par optimizeImage (deleteOriginal=true) mais on double-vérifie
		if (file_exists($backupPath)) @unlink($backupPath);

		return [
			'status'         => 'ok',
			'file'           => $file,
			'original_size'  => $originalSize,
			'optimized_size' => $optimizedSize,
			'webp'           => $doWebP,
		];
	} else {
		// Optimisation échouée → restaure l'original
		if (file_exists($backupPath)) {
			copy($backupPath, $realFile);
			@unlink($backupPath);
		}
		return ['status' => 'error', 'file' => $file, 'message' => __t('batch_optimize_failed')];
	}
}

function listDirectories($dir, $rel = '') {
	$dirs  = [];
	$items = scandir($dir);
	if ($items === false) return $dirs;
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') continue;
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if (is_dir($path)) {
			$relPath = $rel !== '' ? $rel . '/' . $item : $item;
			$dirs[]  = $relPath;
			$dirs    = array_merge($dirs, listDirectories($path, $relPath));
		}
	}
	return $dirs;
}

$availableDirs = ($baseDirAbs !== false)
	? array_merge([''], listDirectories($baseDirAbs))
	: [''];
$pageTitle = __t('image_optimizer');
$extraHead = '<style>#processing-warning{display:none;background-color:var(--warning-soft);color:var(--warning-text);border:1px solid var(--warning);border-left:4px solid var(--warning);padding:12px 20px;border-radius:var(--radius-sm);margin-bottom:20px;font-size:1.1em;}</style>';

ob_start();
?>

	<div id="processing-warning">
		⚠️ <?php _e('batch_do_not_close'); ?>
	</div>

	<div class="form-group" style="background-color:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;">
		<p><?php _e('batch_description'); ?></p>
		<p><b><?php _e('batch_current_settings'); ?></b></p>
		<ul>
			<li><?php _e('batch_setting_resolution'); ?> <?php echo $maxWidth . 'x' . $maxHeight; ?></li>
			<li><?php _e('batch_setting_quality'); ?> <?php echo $quality; ?>%</li>
			<li><?php _e('batch_setting_webp'); ?> <?php echo $webpConversion ? __t('batch_webp_enabled') : __t('batch_webp_disabled'); ?></li>
		</ul>
		<?php if (!$webpSupported && $webpConversion): ?>
			<p class="message error"><?php _e('batch_webp_not_supported'); ?></p>
		<?php endif; ?>
		<p><strong><?php _e('note'); ?></strong> <?php _e('batch_settings_note'); ?>
		   <a href="index.php?action=settings&tab=images"><?php _e('settings'); ?></a>.</p>
	</div>

	<form id="optimize-form" method="post" action="">
		<div class="form-group">
			<label for="directory"><?php _e('batch_select_directory'); ?></label>
			<select id="directory" name="directory" required>
				<?php foreach ($availableDirs as $d): ?>
					<option value="<?php echo htmlspecialchars($d); ?>">
						<?php echo $d !== '' ? htmlspecialchars($d) : '/ (' . __t('batch_root_directory') . ')'; ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="form-group">
			<button type="submit" name="optimize" id="start-optimization" class="btn btn-primary">
				<?php _e('batch_start_btn'); ?>
			</button>
		</div>
	</form>
	<div class="progress-container" id="progress-container" style="display:none;">
		<h3><?php _e('batch_progress_heading'); ?></h3>
		<div class="progress-bar">
			<div class="progress-fill" id="progress-fill" style="width:0%"></div>
		</div>
		<div class="current-status" id="current-status"><?php _e('batch_starting'); ?></div>
	</div>
	<div class="progress-container" id="optimization-results" style="display:none;">
		<h3><?php _e('batch_results_heading'); ?></h3>
		<div class="stats-grid">
			<div class="stat-card">
				<h4><?php _e('batch_stat_processed'); ?></h4>
				<div class="stat-value" id="stat-processed">0</div>
			</div>
			<div class="stat-card">
				<h4><?php _e('batch_stat_errors'); ?></h4>
				<div class="stat-value" id="stat-errors">0</div>
			</div>
			<div class="stat-card">
				<h4><?php _e('batch_stat_space_saved'); ?></h4>
				<div class="stat-value" id="stat-saved">0 B</div>
			</div>
			<div class="stat-card">
				<h4><?php _e('batch_stat_reduction'); ?></h4>
				<div class="stat-value" id="stat-reduction">0%</div>
			</div>
			<div class="stat-card">
				<h4><?php _e('batch_stat_original_size'); ?></h4>
				<div class="stat-value" id="stat-original">0 B</div>
			</div>
			<div class="stat-card">
				<h4><?php _e('batch_stat_optimized_size'); ?></h4>
				<div class="stat-value" id="stat-optimized">0 B</div>
			</div>
			<?php if ($webpConversion && $webpSupported): ?>
			<div class="stat-card">
				<h4><?php _e('batch_stat_webp'); ?></h4>
				<div class="stat-value" id="stat-webp">0</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = '<script src="assets/js/batch-optimize.js?v=' . @filemtime(__DIR__ . '/assets/js/batch-optimize.js') . '"></script>';

require_once 'includes/layout.php';