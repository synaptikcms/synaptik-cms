<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
if (!isset($_SESSION['admin'])) {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
	} else {
		header('Location: auth.php');
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

require_once('includes/admin-functions.php');
require_once('image-optimization.php');
require_once('../data-functions.php');
require_once('../core-functions.php');

$appSettings = [];
if (($raw = file_get_contents('../settings.json')) !== false) {
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
	header('Content-Type: application/json');
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

function formatSize($bytes) {
	$units = ['B', 'KB', 'MB', 'GB'];
	$bytes = max(0, (int)$bytes);
	$i     = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
	$i     = min($i, count($units) - 1);
	return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
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

$extraFooterScripts = <<<'JSINLINE'
<script>
(function () {
	'use strict';

	var form       = document.getElementById('optimize-form');
	var btn        = document.getElementById('start-optimization');
	var select     = document.getElementById('directory');
	var container  = document.getElementById('progress-container');
	var fill       = document.getElementById('progress-fill');
	var statusEl   = document.getElementById('current-status');
	var resultsEl  = document.getElementById('optimization-results');
	var warningEl  = document.getElementById('processing-warning');
	var isRunning  = false;

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (isRunning) return;
		isRunning = true;
		container.style.display = 'block';
		resultsEl.style.display = 'none';
		warningEl.style.display = 'block';
		fill.style.width        = '0%';
		statusEl.textContent    = window.t('batch_scanning');
		btn.disabled            = true;
		btn.textContent         = window.t('optimizing');
		select.disabled         = true;
		ajaxGet(
			'batch-optimize.php?scan=1&directory=' + encodeURIComponent(select.value),
			function (data) {
				if (data.status !== 'ok') { showError(data.message || window.t('error')); return; }
				if (data.count === 0)    { showError(window.t('batch_no_images')); return; }
				statusEl.textContent = window.t('batch_images_found').replace('%d', data.count);
				processFiles(data.files, 0, { processed: 0, errors: 0, totalOriginal: 0, totalOptimized: 0, webpCount: 0 });
			},
			function (err) { showError(window.t('error') + ': ' + err); }
		);
	});

	function processFiles(files, index, stats) {
		if (index >= files.length) { finishProcessing(stats, files.length); return; }
		var file    = files[index];
		var percent = Math.min(99, Math.round((index / files.length) * 100));
		fill.style.width     = percent + '%';
		statusEl.textContent = (index + 1) + '/' + files.length + ' — ' + file.name;
		ajaxPost('batch-optimize.php', 'process_one=1&file_path=' + encodeURIComponent(file.path),
			function (result) {
				if (result.status === 'ok') {
					stats.processed++;
					stats.totalOriginal  += result.original_size  || 0;
					stats.totalOptimized += result.optimized_size || 0;
					if (result.webp) stats.webpCount++;
				} else { stats.errors++; }
				processFiles(files, index + 1, stats);
			},
			function (err) { stats.errors++; processFiles(files, index + 1, stats); }
		);
	}

	function finishProcessing(stats, total) {
		fill.style.width        = '100%';
		statusEl.textContent    = window.t('optimization_complete');
		warningEl.style.display = 'none';
		btn.disabled            = false;
		btn.textContent         = window.t('batch_start_btn');
		select.disabled         = false;
		isRunning               = false;
		var saved     = stats.totalOriginal - stats.totalOptimized;
		var reduction = stats.totalOriginal > 0 ? Math.round((saved / stats.totalOriginal) * 10000) / 100 : 0;
		setText('stat-processed', stats.processed);
		setText('stat-errors',    stats.errors);
		setText('stat-saved',     formatBytes(saved));
		setText('stat-reduction', reduction + '%');
		setText('stat-original',  formatBytes(stats.totalOriginal));
		setText('stat-optimized', formatBytes(stats.totalOptimized));
		if (document.getElementById('stat-webp')) setText('stat-webp', stats.webpCount);
		resultsEl.style.display = 'block';
		var msgHtml = window.t('batch_complete_msg').replace('%d', stats.processed).replace('%d', stats.errors).replace('%s', formatBytes(saved)).replace('%s', reduction + '%');
		if (stats.webpCount > 0) msgHtml += window.t('batch_complete_msg_webp').replace('%d', stats.webpCount);
		var msg = document.createElement('div');
		msg.className = 'message success';
		msg.innerHTML = msgHtml;
		var content = document.querySelector('.content');
		content.insertBefore(msg, content.firstChild);
		setTimeout(function () { msg.style.transition = 'opacity 0.5s ease'; msg.style.opacity = '0'; setTimeout(function () { if (msg.parentNode) msg.parentNode.removeChild(msg); }, 500); }, 5000);
	}

	function showError(msg) {
		statusEl.textContent    = msg;
		warningEl.style.display = 'none';
		btn.disabled            = false;
		btn.textContent         = window.t('batch_start_btn');
		select.disabled         = false;
		isRunning               = false;
	}

	function ajaxGet(url, onSuccess, onError) {
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.timeout = 30000;
		xhr.onload = function () {
			if (xhr.status === 200) { try { onSuccess(JSON.parse(xhr.responseText)); } catch (e) { onError('JSON parse error: ' + e.message); } }
			else { onError('HTTP ' + xhr.status); }
		};
		xhr.onerror   = function () { onError(window.t('batch_network_error')); };
		xhr.ontimeout = function () { onError('Timeout'); };
		xhr.send();
	}

	function ajaxPost(url, body, onSuccess, onError) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.timeout = 120000;
		xhr.onload = function () {
			if (xhr.status === 200) { try { onSuccess(JSON.parse(xhr.responseText)); } catch (e) { onError('JSON parse error: ' + e.message); } }
			else { onError('HTTP ' + xhr.status); }
		};
		xhr.onerror   = function () { onError(window.t('batch_network_error')); };
		xhr.ontimeout = function () { onError(window.t('batch_timeout')); };
		xhr.send(body);
	}

	function setText(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }

	function formatBytes(bytes) {
		if (!bytes || bytes <= 0) return '0 B';
		var units = ['B', 'KB', 'MB', 'GB'];
		var i = Math.min(3, Math.floor(Math.log(bytes) / Math.log(1024)));
		return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + units[i];
	}
}());
</script>
JSINLINE;

require_once 'includes/layout.php';