<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin'])) {
	header('Location: auth.php');
	exit;
}

if (isset($_SESSION['message'])) {
	$message = $_SESSION['message'];
	unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
	$error = $_SESSION['error'];
	unset($_SESSION['error']);
}

$action      = $_GET['action'] ?? '';
$contentType = $_GET['type']   ?? '';

require_once 'includes/admin-functions.php';
require_once 'image-optimization.php';
require_once 'progress-helpers.php';
require_once '../data-functions.php';
require_once '../core-functions.php';

require_once '../data-layer.php';
$data = sl_build_data_array(['article', 'page', 'project'], false);

$baseUploadPath = '../files/';
$currentPath    = isset($_GET['path']) ? $_GET['path'] : '';
if (strpos($currentPath, '..') !== false) {
	$currentPath = '';
}

$fullPath = $baseUploadPath . $currentPath;

if (!file_exists($fullPath)) {
	mkdir($fullPath, 0755, true);
}

$settingsFile = '../settings.json';
$appSettings  = [];
if (file_exists($settingsFile)) {
	$freshSettings = json_decode(file_get_contents($settingsFile), true);
	if (is_array($freshSettings)) {
		$appSettings = $freshSettings;
	}
}

$convertToWebP            = isset($appSettings['convert_to_webp']) ? (bool)$appSettings['convert_to_webp'] : false;
$imageOptimizationEnabled = $appSettings['image_optimization_enabled'] ?? true;
$maxWidth                 = $appSettings['max_width']   ?? 1920;
$maxHeight                = $appSettings['max_height']  ?? 1080;
$quality                  = $appSettings['image_quality'] ?? 85;
$createThumbnail          = isset($appSettings['create_thumbnails']) ? (bool)$appSettings['create_thumbnails'] : false;
$thumbWidth               = $appSettings['thumb_width']  ?? 300;
$thumbHeight              = $appSettings['thumb_height'] ?? 300;
$webpConversion           = $appSettings['convert_to_webp'] ?? false;
$webpSupported            = function_exists('imagewebp');

$imageTypes   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
$allowedTypes = [
	'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'heic', 'heif', 'bmp', 'tiff', 'tif',
	'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp',
	'mp3', 'wav', 'ogg', 'm4a', 'flac',
	'mp4', 'webm', 'ogv', 'mov',
	'zip', 'rar', '7z', 'tar', 'gz',
	'csv', 'json', 'xml',
];

// SSE stream endpoint
if (isset($_GET['stream_status']) && isset($_GET['file_id'])) {
	ini_set('output_buffering', 'off');
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);
	set_time_limit(0);
	if (ob_get_level()) ob_end_clean();
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	header('Connection: keep-alive');

	$fileId = $_GET['file_id'];
	session_start();
	$processingDetails = $_SESSION['processing_' . $fileId] ?? null;
	session_write_close();

	if (!$processingDetails) {
		echo "data: " . json_encode(['message' => 'Error: File processing details not found', 'percentage' => 0, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
		exit;
	}

	echo "data: " . json_encode(['message' => 'Starting file processing...', 'percentage' => 0, 'stage' => 'initializing', 'id' => $fileId]) . "\n\n";

	$fileName      = $processingDetails['filename'];
	$isImage       = $processingDetails['is_image'];
	$shouldOptimize = $processingDetails['optimize'];
	$createThumbnail = $processingDetails['create_thumbnail'];
	$tempPath      = $processingDetails['temp_path'];
	$targetPath    = $processingDetails['target_path'];

	if (!file_exists($tempPath)) {
		echo "data: " . json_encode(['message' => 'Error: Source file not found', 'percentage' => 0, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
		exit;
	}

	echo "data: " . json_encode(['message' => 'Analyzing file format and properties...', 'percentage' => 10, 'stage' => 'analyzing', 'id' => $fileId]) . "\n\n";

	if ($isImage && $shouldOptimize) {
		$imageInfo = getimagesize($tempPath);
		if ($imageInfo === false) {
			echo "data: " . json_encode(['message' => 'Error: Cannot analyze image', 'percentage' => 20, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
			if (copy($tempPath, $targetPath)) {
				unlink($tempPath);
				echo "data: " . json_encode(['message' => 'File saved without optimization', 'percentage' => 100, 'stage' => 'complete', 'id' => $fileId]) . "\n\n";
			} else {
				echo "data: " . json_encode(['message' => 'Error saving file', 'percentage' => 0, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
			}
			exit;
		}

		$width  = $imageInfo[0];
		$height = $imageInfo[1];
		echo "data: " . json_encode(['message' => "Image is {$width}x{$height} pixels", 'percentage' => 20, 'stage' => 'analyzed', 'id' => $fileId]) . "\n\n";
		echo "data: " . json_encode(['message' => 'Optimizing image quality and size...', 'percentage' => 40, 'stage' => 'optimizing', 'id' => $fileId]) . "\n\n";

		$thumbsDir     = null;
		$thumbnailPath = '';
		if ($createThumbnail) {
			$thumbsDir = ensureThumbnailsDir(dirname($targetPath));
			if ($thumbsDir) {
				$thumbBasename = basename($targetPath);
				if ($processingDetails['convert_to_webp'] && strpos($thumbBasename, '.webp') === false) {
					$thumbBasename = pathinfo($thumbBasename, PATHINFO_FILENAME) . '.webp';
				}
				$thumbnailPath = $thumbsDir . '/thumb_' . $thumbBasename;
			}
		}

		$optimizeResult = optimizeImage(
			$tempPath, $targetPath,
			$processingDetails['max_width']    ?? 1920,
			$processingDetails['max_height']   ?? 1080,
			$processingDetails['quality']      ?? 85,
			$createThumbnail && !empty($thumbnailPath),
			$thumbnailPath,
			$processingDetails['thumb_width']  ?? 300,
			$processingDetails['thumb_height'] ?? 300,
			true,
			$processingDetails['convert_to_webp']
		);

		if ($optimizeResult) {
			$newSize      = filesize($targetPath);
			$originalSize = $processingDetails['original_size'] ?? 0;
			if ($originalSize > 0) {
				$reduction = round(100 - (($newSize / $originalSize) * 100), 1);
				echo "data: " . json_encode(['message' => "Optimized: Reduced size by {$reduction}%", 'percentage' => 80, 'stage' => 'optimized', 'id' => $fileId]) . "\n\n";
			} else {
				echo "data: " . json_encode(['message' => 'Optimization complete', 'percentage' => 80, 'stage' => 'optimized', 'id' => $fileId]) . "\n\n";
			}
			if ($createThumbnail && file_exists($thumbnailPath)) {
				echo "data: " . json_encode(['message' => 'Thumbnail created: ' . basename($thumbnailPath), 'percentage' => 90, 'stage' => 'thumbnails', 'id' => $fileId]) . "\n\n";
			}
			echo "data: " . json_encode(['message' => 'Processing complete!', 'percentage' => 100, 'stage' => 'complete', 'id' => $fileId]) . "\n\n";
		} else {
			echo "data: " . json_encode(['message' => 'Optimization failed, using original file', 'percentage' => 90, 'stage' => 'warning', 'id' => $fileId]) . "\n\n";
			if (copy($tempPath, $targetPath)) {
				unlink($tempPath);
				echo "data: " . json_encode(['message' => 'Processing complete with original file', 'percentage' => 100, 'stage' => 'complete', 'id' => $fileId]) . "\n\n";
			} else {
				echo "data: " . json_encode(['message' => 'Error saving file', 'percentage' => 0, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
			}
		}
	} else {
		echo "data: " . json_encode(['message' => 'Copying file to destination...', 'percentage' => 50, 'stage' => 'copying', 'id' => $fileId]) . "\n\n";
		if (copy($tempPath, $targetPath)) {
			unlink($tempPath);
			echo "data: " . json_encode(['message' => 'Processing complete!', 'percentage' => 100, 'stage' => 'complete', 'id' => $fileId]) . "\n\n";
		} else {
			echo "data: " . json_encode(['message' => 'Error saving file', 'percentage' => 0, 'stage' => 'error', 'id' => $fileId]) . "\n\n";
		}
	}
	exit;
}

// File upload handler
if (isset($_FILES['upload_files'])) {
	$successCount  = 0;
	$errorCount    = 0;
	$fileCount     = count($_FILES['upload_files']['name']);
	$processedFiles = [];
	$batchId       = 'batch_' . time() . '_' . mt_rand(1000, 9999);
	$isAjax        = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
	              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

	if ($isAjax) { header('Content-Type: application/json'); }

	if (!file_exists($fullPath)) { mkdir($fullPath, 0755, true); }

	$thumbsDir = null;
	if ($createThumbnail) { $thumbsDir = ensureThumbnailsDir($fullPath); }

	for ($i = 0; $i < $fileCount; $i++) {
		if ($_FILES['upload_files']['error'][$i] === UPLOAD_ERR_NO_FILE) { continue; }
		if ($_FILES['upload_files']['error'][$i] !== UPLOAD_ERR_OK)      { $errorCount++; continue; }

		$fileName   = preg_replace('/[^\w\._]+/', '_', basename($_FILES['upload_files']['name'][$i]));
		$targetFile = rtrim($fullPath, '/') . '/' . $fileName;
		$fileId     = 'file_' . time() . '_' . $i . '_' . mt_rand(1000, 9999);
		$fileType   = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

		if (!in_array($fileType, $allowedTypes)) { $errorCount++; continue; }

		$isImage        = in_array($fileType, $imageTypes);
		$shouldOptimize = $isImage && $imageOptimizationEnabled;
		$doWebPConversion = $isImage && $webpConversion && $webpSupported && $fileType !== 'webp';
		$tempFile       = $fullPath . '/temp_' . $fileName;

		if (move_uploaded_file($_FILES['upload_files']['tmp_name'][$i], $tempFile)) {
			$originalSize = filesize($tempFile);
			$webpFile     = $fullPath . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '.webp';

			$thumbnailPath = '';
			if ($createThumbnail && isset($thumbsDir) && $thumbsDir) {
				$thumbnailPath = $thumbsDir . '/thumb_' . $fileName;
				if ($doWebPConversion) {
					$thumbnailPath = $thumbsDir . '/thumb_' . pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
				}
			}

			$_SESSION['processing_' . $fileId] = [
				'filename'        => $fileName,
				'is_image'        => $isImage,
				'optimize'        => $shouldOptimize,
				'create_thumbnail' => $isImage && $createThumbnail,
				'max_width'       => $maxWidth,
				'max_height'      => $maxHeight,
				'quality'         => $quality,
				'temp_path'       => $tempFile,
				'target_path'     => $doWebPConversion ? $webpFile : $targetFile,
				'original_size'   => $originalSize,
				'convert_to_webp' => $convertToWebP,
				'thumb_width'     => $thumbWidth,
				'thumb_height'    => $thumbHeight,
			];

			$processedFiles[] = [
				'id'       => $fileId,
				'name'     => $fileName,
				'is_image' => $isImage,
				'optimize' => $shouldOptimize,
				'file_type' => $fileType,
				'webp'     => $doWebPConversion,
			];
			$successCount++;
		} else {
			$errorCount++;
		}
	}

	if ($isAjax) {
		echo json_encode([
			'status'           => 'uploaded',
			'message'          => sprintf(__t('fm_upload_complete'), $successCount),
			'batchId'          => $batchId,
			'files'            => $processedFiles,
			'optimize_enabled' => $imageOptimizationEnabled,
			'create_thumbnails' => $createThumbnail,
			'convert_to_webp'  => $webpConversion && $webpSupported,
		]);
		exit;
	}

	if ($successCount > 0) {
		$message = sprintf(__t('fm_upload_success_count'), $successCount);
		if ($errorCount > 0) { $message .= ' ' . sprintf(__t('fm_upload_fail_count'), $errorCount); }
	} elseif ($errorCount > 0) {
		$error = __t('fm_upload_all_failed');
	}
}

// Folder creation
if (isset($_POST['create_folder']) && !empty($_POST['folder_name'])) {
	$folderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['folder_name']);
	$newFolder  = rtrim($fullPath, '/') . '/' . $folderName;
	if (!file_exists($newFolder)) {
		mkdir($newFolder, 0755);
		$message = __t('fm_folder_created');
	} else {
		$error = __t('fm_folder_exists');
	}
}

// Single file deletion
if (isset($_POST['delete_file']) && !empty($_POST['delete_file'])) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$_SESSION['error'] = __t('auth_csrf_error');
		header('Location: file-manager.php?path=' . urlencode($currentPath));
		exit;
	}
	$fileToDelete = rtrim($fullPath, '/') . '/' . $_POST['delete_file'];
	if (strpos(realpath($fileToDelete), realpath($baseUploadPath)) === 0
		&& file_exists($fileToDelete) && is_file($fileToDelete)) {
		if (unlink($fileToDelete)) {
			$_SESSION['message'] = __t('fm_file_deleted');
		} else {
			$_SESSION['error'] = __t('fm_file_delete_failed');
		}
	} else {
		$_SESSION['error'] = __t('fm_invalid_request');
	}
	header('Location: file-manager.php?path=' . urlencode($currentPath));
	exit;
}

// Bulk deletion
if (isset($_POST['batch_delete']) && (
	(isset($_POST['selected_files']) && is_array($_POST['selected_files'])) ||
	(isset($_POST['items_to_delete']) && !empty($_POST['items_to_delete']))
)) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => __t('auth_csrf_error')]);
		} else {
			$_SESSION['error'] = __t('auth_csrf_error');
			header('Location: file-manager.php?path=' . urlencode($currentPath));
		}
		exit;
	}
	$successCount = 0;
	$errorCount   = 0;

	if (isset($_POST['selected_files']) && is_array($_POST['selected_files'])) {
		foreach ($_POST['selected_files'] as $file) {
			$fileToDelete = rtrim($fullPath, '/') . '/' . $file;
			if (strpos(realpath($fileToDelete), realpath($baseUploadPath)) === 0
				&& file_exists($fileToDelete) && is_file($fileToDelete)) {
				unlink($fileToDelete) ? $successCount++ : $errorCount++;
			} else {
				$errorCount++;
			}
		}
	} elseif (isset($_POST['items_to_delete'])) {
		$itemsToDelete = json_decode($_POST['items_to_delete'], true);
		foreach ($itemsToDelete as $item) {
			$itemType = $item['type'] ?? '';
			$itemName = $item['name'] ?? '';
			if (empty($itemType) || empty($itemName)) { $errorCount++; continue; }
			$itemPath = rtrim($fullPath, '/') . '/' . $itemName;
			if (strpos(realpath($itemPath), realpath($baseUploadPath)) === 0 && file_exists($itemPath)) {
				if ($itemType === 'file' && is_file($itemPath)) {
					unlink($itemPath) ? $successCount++ : $errorCount++;
				} elseif ($itemType === 'folder' && is_dir($itemPath)) {
					$isEmpty = count(scandir($itemPath)) <= 2;
					($isEmpty && rmdir($itemPath)) ? $successCount++ : $errorCount++;
				} else { $errorCount++; }
			} else { $errorCount++; }
		}
	}

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
		&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['success' => $successCount > 0, 'message' => sprintf(__t('fm_delete_summary'), $successCount, $errorCount)]);
		exit;
	}

	if ($successCount > 0) {
		$_SESSION['message'] = sprintf(__t('fm_items_deleted'), $successCount);
		if ($errorCount > 0) { $_SESSION['message'] .= ' ' . sprintf(__t('fm_items_delete_partial'), $errorCount); }
	} else {
		$_SESSION['error'] = __t('fm_delete_failed');
	}
	header('Location: file-manager.php?path=' . urlencode($currentPath));
	exit;
}

// Folder deletion
if (isset($_POST['delete_folder']) && !empty($_POST['delete_folder'])) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$_SESSION['error'] = __t('auth_csrf_error');
		header('Location: file-manager.php?path=' . urlencode($currentPath));
		exit;
	}
	$folderToDelete = rtrim($fullPath, '/') . '/' . $_POST['delete_folder'];
	if (strpos(realpath($folderToDelete), realpath($baseUploadPath)) === 0
		&& file_exists($folderToDelete) && is_dir($folderToDelete)) {
		$success = false;
		$force   = !empty($_POST['force']);
		if ($force) {
			if (!function_exists('deleteDir')) {
				function deleteDir($dirPath) {
					if (!is_dir($dirPath)) return false;
					foreach (array_diff(scandir($dirPath), ['.', '..']) as $file) {
						$p = $dirPath . '/' . $file;
						is_dir($p) ? deleteDir($p) : unlink($p);
					}
					return rmdir($dirPath);
				}
			}
			$success = deleteDir($folderToDelete);
		} else {
			$isEmpty = count(scandir($folderToDelete)) <= 2;
			if ($isEmpty) {
				$success = rmdir($folderToDelete);
			} else {
				$_SESSION['error'] = __t('fm_folder_not_empty');
			}
		}
		if ($success) {
			$_SESSION['message'] = __t('fm_folder_deleted');
		} elseif (!isset($_SESSION['error'])) {
			$_SESSION['error'] = __t('fm_folder_delete_failed');
		}
	} else {
		$_SESSION['error'] = __t('fm_invalid_folder_request');
	}
	header('Location: file-manager.php?path=' . urlencode($currentPath));
	exit;
}

// Breadcrumbs
$breadcrumbs    = [['name' => 'Files', 'path' => '']];
$pathParts      = array_filter(explode('/', $currentPath), 'strlen');
$cumulativePath = '';
foreach ($pathParts as $part) {
	$cumulativePath .= $part . '/';
	$breadcrumbs[] = ['name' => $part, 'path' => $cumulativePath];
}

// Directory listing
$contents = scandir($fullPath);
$folders  = [];
$files    = [];
foreach ($contents as $item) {
	if ($item === '.' || $item === '..' || $item[0] === '.') { continue; }
	$itemPath = $fullPath . '/' . $item;
	if (is_dir($itemPath)) {
		$folders[] = $item;
	} else {
		$files[] = [
			'name'     => $item,
			'size'     => formatFileSize(filesize($itemPath)),
			'type'     => pathinfo($itemPath, PATHINFO_EXTENSION),
			'modified' => date('Y-m-d H:i:s', filemtime($itemPath)),
		];
	}
}

if (!function_exists('formatFileSize')) {
	function formatFileSize($bytes) {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$pow   = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);
		return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
	}
}

function getPublicUrl($path, $file) {
	$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
	$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
	if (!empty($path)) { $path = rtrim($path, '/') . '/'; }
	return $baseUrl . $baseDir . '/files/' . $path . $file;
}

function getFileIcon($fileType) {
	$fileType = strtolower($fileType);
	$iconMap  = [
		'image'        => ['jpg','jpeg','png','gif','webp','svg','heic','heif','bmp','tiff','tif'],
		'pdf'          => ['pdf'],
		'doc'          => ['doc','docx','odt','rtf'],
		'spreadsheet'  => ['xls','xlsx','ods','csv'],
		'presentation' => ['ppt','pptx','odp'],
		'audio'        => ['mp3','wav','ogg','m4a','flac'],
		'video'        => ['mp4','webm','ogv','mov'],
		'archive'      => ['zip','rar','7z','tar','gz'],
		'code'         => ['txt','json','xml','html','css','js','php'],
	];
	$icons = ['image' => '🖼️','pdf' => '📕','doc' => '📝','spreadsheet' => '📊',
	          'presentation' => '📑','audio' => '🎵','video' => '🎬','archive' => '📦','code' => '📄'];
	foreach ($iconMap as $icon => $types) {
		if (in_array($fileType, $types)) { return $icons[$icon] ?? '📄'; }
	}
	return '📄';
}

/**
 * Handle file and folder rename operations.
 */
function handleRename() {
	global $baseUploadPath, $currentPath;
	$itemType     = $_POST['item_type']     ?? '';
	$originalName = $_POST['original_name'] ?? '';
	$newName      = $_POST['new_name']      ?? '';
	if (empty($itemType) || empty($originalName) || empty($newName)) {
		return ['success' => false, 'message' => __t('fm_rename_missing_data')];
	}
	$fullPath        = $baseUploadPath . $currentPath;
	$originalItemPath = rtrim($fullPath, '/') . '/' . $originalName;
	$newItemPath      = rtrim($fullPath, '/') . '/' . $newName;
	if (!file_exists($originalItemPath)) {
		return ['success' => false, 'message' => sprintf(__t('fm_item_not_found'), $originalItemPath)];
	}
	if (file_exists($newItemPath)) {
		return ['success' => false, 'message' => __t('fm_rename_exists')];
	}
	$success = rename($originalItemPath, $newItemPath);
	return ['success' => $success, 'message' => $success ? __t('fm_rename_success') : __t('fm_rename_failed')];
}

/**
 * Handle file and folder move operations.
 */
function handleMove() {
	global $baseUploadPath, $currentPath;
	$items        = json_decode($_POST['items']         ?? '[]', true);
	$targetFolder = $_POST['target_folder'] ?? '';
	$targetPath   = $_POST['target_path']   ?? '';
	if (empty($items) || (empty($targetFolder) && empty($targetPath))) {
		return ['success' => false, 'message' => __t('fm_move_missing_data')];
	}
	$currentDirPath = rtrim($baseUploadPath . $currentPath, '/');
	if ($targetFolder === 'root') {
		$targetFolderPath = $baseUploadPath;
	} elseif (!empty($targetPath)) {
		$targetFolderPath = $targetPath === '' ? $baseUploadPath
			: str_replace('//', '/', $baseUploadPath . '/' . $targetPath);
	} else {
		$targetFolderPath = $currentDirPath . '/' . $targetFolder;
	}
	if (!file_exists($targetFolderPath) || !is_dir($targetFolderPath)) {
		return ['success' => false, 'message' => __t('fm_move_target_not_found')];
	}
	$successCount = 0;
	$failCount    = 0;
	$errors       = [];
	foreach ($items as $item) {
		$itemType = $item['type'] ?? '';
		$itemName = $item['name'] ?? '';
		if (empty($itemType) || empty($itemName)) { $failCount++; $errors[] = __t('fm_invalid_item_data'); continue; }
		$sourcePath      = $currentDirPath . '/' . $itemName;
		$destinationPath = $targetFolderPath . '/' . $itemName;
		if (!file_exists($sourcePath))       { $failCount++; $errors[] = sprintf(__t('fm_item_not_found'), $itemName); continue; }
		if (file_exists($destinationPath))   { $failCount++; $errors[] = sprintf(__t('fm_destination_exists'), $itemName); continue; }
		if ($itemType === 'folder') {
			$srcNorm = rtrim($sourcePath, '/') . '/';
			$tgtNorm = rtrim($targetFolderPath, '/') . '/';
			if ($srcNorm === $tgtNorm || strpos($tgtNorm, $srcNorm) === 0) {
				$failCount++; $errors[] = sprintf(__t('fm_move_item_failed'), $itemName); continue;
			}
		}
		rename($sourcePath, $destinationPath) ? $successCount++ : ($failCount++ || $errors[] = sprintf(__t('fm_move_item_failed'), $itemName));
	}
	if ($failCount === 0) {
		return ['success' => true, 'message' => sprintf(__t('fm_move_success'), $successCount)];
	}
	return ['success' => $successCount > 0, 'message' => sprintf(__t('fm_move_partial'), $successCount, $failCount), 'errors' => $errors];
}

// AJAX rename / move
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	header('Content-Type: application/json');
	switch ($_POST['action']) {
		case 'rename': echo json_encode(handleRename()); exit;
		case 'move':   echo json_encode(handleMove());   exit;
	}
}

// ── Layout ────────────────────────────────────────────────────────────────────
$pageTitle = __t('file_manager');

$extraHead = '<link rel="stylesheet" href="assets/css/admin-filemanager.css?v=' . @filemtime(__DIR__ . '/assets/css/admin-filemanager.css') . '">'
           . "\n<script>window.CMS_CSRF_TOKEN = " . json_encode($_SESSION['csrf_token']) . ";</script>"
           . "\n<style>"
           . "\n.folder-drag-overlay { display: none; }"
           . "\n.folder-item.drag-over { z-index:100!important; background-color:var(--primary-soft)!important; border:2px dashed var(--primary)!important; box-shadow:0 0 12px rgba(79,167,92,.3)!important; transform:translateY(-2px); transition:transform .2s ease,box-shadow .2s ease; }"
           . "\n.folder-item.drag-over .folder-drag-overlay { display:flex!important; position:absolute; inset:0; background:var(--primary-soft); justify-content:center; align-items:center; z-index:101!important; font-weight:bold; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,.3); animation:pulseOverlay 1.5s infinite alternate; }"
           . "\n@keyframes pulseOverlay { 0%{background-color:rgba(79,167,92,.2)} 100%{background-color:rgba(79,167,92,.4)} }"
           . "\n.file-grid.grid-drag-over:has(.folder-item.drag-over) { background-color:transparent!important; border:none!important; }"
           . "\n.file-item,.folder-item { -webkit-user-drag:element; }"
           . "\n.breadcrumbs a.breadcrumb-drag-over { background-color:var(--primary-soft); color:var(--primary-text); text-decoration:underline; border-radius:3px; outline:2px dashed var(--primary); outline-offset:2px; }"
           . "\n</style>";

ob_start();
?>
<div class="file-manager">
	<div class="fm-controls-header">
		<button class="btn btn-outline" id="fm-controls-toggle">
			<span id="fm-controls-toggle-icon">▶</span>&ensp;<?php _e('file_upload'); ?>
		</button>
	</div>
	<div class="file-manager-controls" id="fm-controls-panel">
		<div class="upload-form">
			<h3 class="form-title"><?php _e('file_upload'); ?></h3>
			<form method="post" enctype="multipart/form-data" id="upload-form">
				<input type="file" name="upload_files[]" id="file-input" multiple style="position:absolute;width:0;height:0;overflow:hidden;opacity:0;">
				<div class="dropzone" id="dropzone" tabindex="0">
					<p><?php _e('fm_dropzone_text'); ?></p>
					<p style="font-size:.8em;color:var(--text-muted)"><?php _e('fm_upload_limit'); ?></p>
				</div>
				<div class="upload-progress" id="upload-progress" style="display:none;">
					<p><?php _e('fm_uploading_files'); ?> <span id="file-count">0</span> :</p>
					<span id="progress-stage" class="progress-stage"><?php _e('fm_starting'); ?></span>
					<div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
					<p id="progress-message" class="progress-message"></p>
				</div>
				<button class="btn btn-primary" type="submit" id="upload-button" style="margin-top:10px;"><?php _e('fm_upload_btn'); ?></button>
			</form>
		</div>
		<div class="folder-form">
			<h3 class="form-title"><?php _e('new_folder'); ?></h3>
			<form method="post">
				<input type="text" name="folder_name" placeholder="<?php _e('fm_folder_name_placeholder'); ?>" required>
				<button class="btn btn-primary" type="submit" name="create_folder"><?php _e('create'); ?></button>
			</form>
		</div>
	</div>

	<div class="bulk-actions" id="bulk-actions" style="display:none;">
		<form method="post" id="bulk-form">
			<input type="hidden" name="bulk_delete" value="1">
			<div id="selected-files-container"></div>
			<button type="submit" class="btn btn-danger"><?php _e('delete_selected'); ?></button>
		</form>
		<div class="select-all-container">
			<input type="checkbox" id="select-all">
			<label for="select-all"><?php _e('fm_select_all'); ?></label>
		</div>
	</div>

	<div class="file-manager-navigation">
		<div class="help-text" style="margin-top:8px;font-size:.8em;"><?php _e('fm_breadcrumb_drop_info'); ?></div>
		<div class="breadcrumbs-container">
			<div class="breadcrumbs">
				<?php foreach ($breadcrumbs as $i => $crumb): ?>
				<?php if ($i > 0): ?> / <?php endif; ?>
				<a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
				<?php endforeach; ?>
				<span class="folder-info"><?php echo sprintf(__t('fm_folder_file_count'), count($folders), count($files)); ?></span>
			</div>
			<div class="view-controls">
				<button id="toggle-view-mode" class="btn-cl" data-mode="grid"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg><?php _e('fm_list_view'); ?></button>
			</div>
		</div>
	</div>

	<div class="fm-toolbar" id="fm-toolbar">
		<input type="search" id="fm-search" placeholder="<?php _e('fm_search_placeholder'); ?>" autocomplete="off">
		<select id="fm-type-filter">
			<option value="all"><?php _e('fm_filter_all'); ?></option>
			<option value="image"><?php _e('fm_filter_images'); ?></option>
			<option value="video"><?php _e('fm_filter_videos'); ?></option>
			<option value="audio"><?php _e('fm_filter_audio'); ?></option>
			<option value="document"><?php _e('fm_filter_docs'); ?></option>
			<option value="archive"><?php _e('fm_filter_archives'); ?></option>
			<option value="folder"><?php _e('fm_filter_folders'); ?></option>
		</select>
		<select id="fm-sort">
			<option value="name-asc"><?php _e('fm_sort_name_asc'); ?></option>
			<option value="name-desc"><?php _e('fm_sort_name_desc'); ?></option>
			<option value="date-desc"><?php _e('fm_sort_date_desc'); ?></option>
			<option value="date-asc"><?php _e('fm_sort_date_asc'); ?></option>
			<option value="size-desc"><?php _e('fm_sort_size_desc'); ?></option>
			<option value="size-asc"><?php _e('fm_sort_size_asc'); ?></option>
		</select>
		<span id="fm-results-count" class="fm-results-count"></span>
	</div>

	<div class="file-grid">
		<?php foreach ($folders as $folder):
			$folderPath     = $fullPath . '/' . $folder;
			$folderContents = scandir($folderPath);
			$hasContents    = count($folderContents) > 2;
		?>
		<div class="folder-item" draggable="true"
			data-type="folder"
			data-name="<?php echo htmlspecialchars($folder); ?>"
			data-has-contents="<?php echo $hasContents ? 'true' : 'false'; ?>"
			data-filetype="folder"
			data-modified="<?php echo filemtime($folderPath); ?>"
			data-bytes="0">
			<div class="folder-content">
				<div class="folder-icon">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<path opacity="0.6" d="M1.4 1.9C0.65 1.9,0,2.6,0,3.4L0,21.1C0,21.2,0,21.3,0,21.4C0.16,22,0.74,22.5,1.4,22.5L20.2,22.5C20.9,22.5,21.5,22,21.6,21.3C21.6,21.3,21.6,21.2,21.6,21.2L24,8.2L24,8.1C24,7.4,23.4,6.7,22.6,6.7L22.6,5.3C22.6,4.5,22,3.8,21.1,3.8L8.6,3.8C8.6,3.8,8.5,3.8,8.5,3.8C8.4,3.6,8.2,3.4,8.1,3.1C7.9,2.9,7.8,2.6,7.6,2.4C7.4,2.1,7,1.9,6.6,1.9Z"/>
						<path class="folder-icon-front" d="M3.8 7.7L22.6,7.7C22.9,7.7,23,7.9,23,8.1L20.7,20.9C20.6,20.9,20.6,21,20.6,21C20.6,21.1,20.6,21.1,20.5,21.2C20.4,21.4,20.2,21.5,20,21.5L1.4,21.5C1.1,21.5,1,21.3,1,21.1L3.3,8.3C3.3,8.1,3.5,7.7,3.8,7.7Z"/>
					</svg>
				</div>
				<div class="folder-name"><?php echo htmlspecialchars($folder); ?></div>
			</div>
			<a href="?path=<?php echo urlencode($currentPath . $folder . '/'); ?>" class="folder-navigate-btn">
				<span class="navigate-icon"><?php _e('fm_open'); ?></span>
			</a>
			<div class="folder-drag-overlay"><?php _e('fm_drop_here'); ?></div>
			<div class="file-actions">
				<a href="#" class="rename-btn fm-icon-btn" data-name="<?php echo htmlspecialchars($folder); ?>" data-type="folder" title="<?php _e('file_rename'); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
				</a>
				<a href="?path=<?php echo urlencode($currentPath); ?>&delete_folder=<?php echo urlencode($folder); ?>" class="delete-folder-link fm-icon-btn" title="<?php _e('delete'); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
				</a>
			</div>
		</div>
		<?php endforeach; ?>

		<?php foreach ($files as $file):
			$_fm_ext      = strtolower($file['type']);
			$_fm_imageExts = ['jpg','jpeg','png','gif','webp','svg','heic','heif','bmp','tiff','tif'];
			$_fm_videoExts = ['mp4','webm','ogv','mov'];
			$_fm_audioExts = ['mp3','wav','ogg','m4a','flac'];
			$_fm_docExts   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','odt','ods','odp','csv'];
			$_fm_archExts  = ['zip','rar','7z','tar','gz'];
			if      (in_array($_fm_ext, $_fm_imageExts)) $_fm_ftype = 'image';
			elseif  (in_array($_fm_ext, $_fm_videoExts)) $_fm_ftype = 'video';
			elseif  (in_array($_fm_ext, $_fm_audioExts)) $_fm_ftype = 'audio';
			elseif  (in_array($_fm_ext, $_fm_docExts))   $_fm_ftype = 'document';
			elseif  (in_array($_fm_ext, $_fm_archExts))  $_fm_ftype = 'archive';
			else                                         $_fm_ftype = 'other';
			$_fm_bytes    = filesize($fullPath . '/' . $file['name']);
			$_fm_modified = filemtime($fullPath . '/' . $file['name']);
		?>
		<div class="file-item" draggable="true"
			data-type="file"
			data-name="<?php echo htmlspecialchars($file['name']); ?>"
			data-filetype="<?php echo $_fm_ftype; ?>"
			data-modified="<?php echo $_fm_modified; ?>"
			data-bytes="<?php echo $_fm_bytes; ?>">
			<input type="checkbox" class="selection-checkbox" data-filename="<?php echo htmlspecialchars($file['name']); ?>" style="display:none;">
			<?php if (in_array(strtolower($file['type']), $_fm_imageExts)): ?>
			<div class="file-thumbnail">
				<img src="<?php echo getPublicUrl($currentPath, $file['name']); ?>" alt="<?php echo htmlspecialchars($file['name']); ?>">
			</div>
			<?php else: ?>
			<div class="file-icon"><?php echo getFileIcon($file['type']); ?></div>
			<?php endif; ?>
			<div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
			<div class="file-size"><?php echo $file['size']; ?></div>
			<div class="file-actions">
				<a href="<?php echo getPublicUrl($currentPath, $file['name']); ?>" class="view-img-btn fm-icon-btn" target="_blank" title="<?php _e('view'); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
				</a>
				<a href="#" class="copy-url-btn fm-icon-btn" data-url="<?php echo getPublicUrl($currentPath, $file['name']); ?>" title="<?php _e('fm_copy_url_btn'); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
				</a>
				<a href="#" class="rename-btn fm-icon-btn" data-name="<?php echo htmlspecialchars($file['name']); ?>" data-type="file" title="<?php _e('file_rename'); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
				</a>
				<a href="?path=<?php echo urlencode($currentPath); ?>&delete=<?php echo urlencode($file['name']); ?>" class="delete-file-link fm-icon-btn" title="<?php _e('delete'); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
				</a>
			</div>
		</div>
		<?php endforeach; ?>

		<?php if (empty($folders) && empty($files)): ?>
		<p class="empty-message"><?php _e('fm_folder_empty'); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
$pageContent = ob_get_clean();

ob_start();
?>
<!-- Custom Modal -->
<div id="custom-modal" class="modal-overlay">
	<div class="modal-container">
		<div class="modal-header danger">
			<span class="modal-title"><?php _e('notification'); ?></span>
			<span class="modal-close">&times;</span>
		</div>
		<div class="modal-message-content"><p id="modal-message"></p></div>
		<div class="modal-footer"></div>
	</div>
</div>
<div id="context-menu" class="context-menu">
	<ul>
		<li data-action="rename"><span class="action-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span> <?php _e('file_rename'); ?></li>
		<li data-action="delete" class="ctx-delete"><span class="action-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></span> <?php _e('delete'); ?></li>
		<li data-action="copy-url" class="file-only"><span class="action-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span> <?php _e('fm_copy_url'); ?></li>
		<li data-action="view" class="file-only"><span class="action-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span> <?php _e('view'); ?></li>
	</ul>
</div>
<!-- Rename Dialog -->
<div id="rename-dialog" class="modal-overlay">
	<div class="modal-container">
		<div class="modal-header"><span class="modal-title"><?php _e('fm_rename_item'); ?></span><span class="modal-close">&times;</span></div>
		<div class="modal-content">
			<form id="rename-form">
				<input type="hidden" id="rename-item-type" value="">
				<input type="hidden" id="rename-original-name" value="">
				<div class="form-group">
					<label for="rename-new-name"><?php _e('fm_new_name'); ?></label>
					<input type="text" id="rename-new-name" required>
				</div>
			</form>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-neutral" id="rename-cancel"><?php _e('cancel'); ?></button>
			<button type="button" class="btn btn-primary" id="rename-confirm"><?php _e('file_rename'); ?></button>
		</div>
	</div>
</div>
<!-- Selection Indicator -->
<div id="selection-indicator" class="selection-indicator" style="display:none;">
	<span id="selection-count">0</span>&nbsp; <?php _e('items'); ?>
	<div class="selection-actions">
		<button id="select-all-btn" class="selection-action-btn"><?php _e('fm_select_all'); ?></button>
		<button id="delete-selected" class="selection-action-btn danger"><?php _e('delete'); ?></button>
		<button id="cancel-selection" class="selection-action-btn"><?php _e('cancel'); ?></button>
	</div>
</div>
<div id="folder-drag-overlay" class="folder-drag-overlay"><?php _e('fm_drop_here'); ?></div>
<script src="assets/js/file-manager.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/file-manager.js'); ?>"></script>
<?php
$extraFooterScripts = ob_get_clean();

require_once 'includes/layout.php';
