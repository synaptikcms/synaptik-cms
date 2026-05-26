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

$action = $_GET['action'] ?? '';
$contentType = $_GET['type'] ?? '';

// Include necessary functions
require_once('includes/admin-functions.php');
require_once('image-optimization.php');
require_once('progress-helpers.php');
require_once('../data-functions.php');
require_once('../core-functions.php');

// Load lightweight indices for sidebar (counts only, no content body needed)
require_once '../data-layer.php';
$data = sl_build_data_array(['article', 'page', 'project'], false);

$baseUploadPath = '../files/';
$currentPath = isset($_GET['path']) ? $_GET['path'] : '';
// Validate path to prevent directory traversal
if (strpos($currentPath, '..') !== false) {
	$currentPath = '';
}

$fullPath = $baseUploadPath . $currentPath;

// Create directory if it doesn't exist
if (!file_exists($fullPath)) {
	mkdir($fullPath, 0755, true);
}

// Load settings
$settingsFile = '../settings.json';
$appSettings = [];
if (file_exists($settingsFile)) {
  $freshSettings = json_decode(file_get_contents($settingsFile), true);
  if (is_array($freshSettings)) {
	$appSettings = $freshSettings;
  }
}

// Get WebP settings directly from the file
$convertToWebP = isset($appSettings['convert_to_webp']) ? (bool)$appSettings['convert_to_webp'] : false;

// Use settings or fallback to defaults
$imageOptimizationEnabled = $appSettings['image_optimization_enabled'] ?? true;
$maxWidth = $appSettings['max_width'] ?? 1920;
$maxHeight = $appSettings['max_height'] ?? 1080;
$quality = $appSettings['image_quality'] ?? 85;
$createThumbnail = isset($appSettings['create_thumbnails']) ? (bool)$appSettings['create_thumbnails'] : false;
$thumbWidth = $appSettings['thumb_width'] ?? 300;
$thumbHeight = $appSettings['thumb_height'] ?? 300;
$webpConversion = $appSettings['convert_to_webp'] ?? false;

// Check if WebP is supported
$webpSupported = function_exists('imagewebp');

// List of image types that should be optimized
$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// List of safe allowed file types
$allowedTypes = [
	// Images
	'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic', 'heif', 'bmp', 'tiff', 'tif',
	// Documents
	'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp',
	// Audio
	'mp3', 'wav', 'ogg', 'm4a', 'flac',
	// Video
	'mp4', 'webm', 'ogv', 'mov',
	// Archives
	'zip', 'rar', '7z', 'tar', 'gz',
	// Other
	'csv', 'json', 'xml'
];

// If this is a status update request for specific file or batch
if (isset($_GET['stream_status']) && isset($_GET['file_id'])) {
	// Turn off output buffering
	ini_set('output_buffering', 'off');
	ini_set('implicit_flush', true);
	ob_implicit_flush(true);
	
	// Set time limit to prevent timeout
	set_time_limit(0);
	
	// Clear the output buffer
	if (ob_get_level()) ob_end_clean();
	
	// Setup proper headers
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	header('Connection: keep-alive');
	
	// Get the file ID and session tracking info
	$fileId = $_GET['file_id'];
	
	// Get processing details from session if available
	session_start();
	$processingDetails = isset($_SESSION['processing_' . $fileId]) ? $_SESSION['processing_' . $fileId] : null;
	session_write_close(); // Close session to prevent blocking
	
	if (!$processingDetails) {
		echo "data: " . json_encode([
			'message' => 'Error: File processing details not found',
			'percentage' => 0,
			'stage' => 'error',
			'id' => $fileId
		]) . "\n\n";
		exit;
	}
	
	// Start sending updates immediately
	echo "data: " . json_encode([
		'message' => 'Starting file processing...',
		'percentage' => 0,
		'stage' => 'initializing',
		'id' => $fileId
	]) . "\n\n";
	
	// Get file details
	$fileName = $processingDetails['filename'];
	$isImage = $processingDetails['is_image'];
	$shouldOptimize = $processingDetails['optimize'];
	$createThumbnail = $processingDetails['create_thumbnail'];
	$tempPath = $processingDetails['temp_path'];
	$targetPath = $processingDetails['target_path'];
	
	// Process the file only if it exists
	if (!file_exists($tempPath)) {
		echo "data: " . json_encode([
			'message' => 'Error: Source file not found',
			'percentage' => 0,
			'stage' => 'error',
			'id' => $fileId
		]) . "\n\n";
		exit;
	}
	
	// Step 1: Analysis
	echo "data: " . json_encode([
		'message' => 'Analyzing file format and properties...',
		'percentage' => 10,
		'stage' => 'analyzing',
		'id' => $fileId
	]) . "\n\n";
	
	// Only process images that should be optimized
	if ($isImage && $shouldOptimize) {
		// Get image info
		$imageInfo = getimagesize($tempPath);
		if ($imageInfo === false) {
			echo "data: " . json_encode([
				'message' => 'Error: Cannot analyze image',
				'percentage' => 20,
				'stage' => 'error',
				'id' => $fileId
			]) . "\n\n";
			// Move the file as-is
			if (copy($tempPath, $targetPath)) {
				unlink($tempPath);
				echo "data: " . json_encode([
					'message' => 'File saved without optimization',
					'percentage' => 100,
					'stage' => 'complete',
					'id' => $fileId
				]) . "\n\n";
			} else {
				echo "data: " . json_encode([
					'message' => 'Error saving file',
					'percentage' => 0,
					'stage' => 'error',
					'id' => $fileId
				]) . "\n\n";
			}
			exit;
		}
		
		$width = $imageInfo[0];
		$height = $imageInfo[1];
		$type = $imageInfo[2];
		
		echo "data: " . json_encode([
			'message' => "Image is {$width}x{$height} pixels",
			'percentage' => 20,
			'stage' => 'analyzed',
			'id' => $fileId
		]) . "\n\n";
		
		// Step 2: Optimization
		echo "data: " . json_encode([
			'message' => 'Optimizing image quality and size...',
			'percentage' => 40,
			'stage' => 'optimizing',
			'id' => $fileId
		]) . "\n\n";
		
		// Create thumbnail path if needed
		$thumbsDir = null;
		$thumbnailPath = '';
		if ($createThumbnail) {
			$thumbsDir = ensureThumbnailsDir(dirname($targetPath));
			if ($thumbsDir) {
				// Generate the right thumbnail filename
				$thumbBasename = basename($targetPath);
				
				// For WebP conversion, adjust thumbnail name if needed
				if ($processingDetails['convert_to_webp'] && 
					strpos($thumbBasename, '.webp') === false) {
					$thumbBasename = pathinfo($thumbBasename, PATHINFO_FILENAME) . '.webp';
				}
				
				$thumbnailPath = $thumbsDir . '/thumb_' . $thumbBasename;
			}
		}
		
		// Process the image
		$optimizeResult = optimizeImage(
			$tempPath,                                        // Source file
			$targetPath,                                      // Destination file
			$processingDetails['max_width'] ?? 1920,          // Max width
			$processingDetails['max_height'] ?? 1080,         // Max height
			$processingDetails['quality'] ?? 85,              // Quality
			$createThumbnail && !empty($thumbnailPath),       // Create thumbnail (only if enabled AND path is set)
			$thumbnailPath,                                   // Thumbnail path
			$processingDetails['thumb_width'] ?? 300,         // Thumb width
			$processingDetails['thumb_height'] ?? 300,        // Thumb height
			true,                                             // Delete original after optimization
			$processingDetails['convert_to_webp']             // Convert to WebP
		);
		
		if ($optimizeResult) {
			// Get new file size
			$newSize = filesize($targetPath);
			$originalSize = $processingDetails['original_size'] ?? 0;
			
			if ($originalSize > 0) {
				$reduction = round(100 - (($newSize / $originalSize) * 100), 1);
				echo "data: " . json_encode([
					'message' => "Optimized: Reduced size by {$reduction}%",
					'percentage' => 80,
					'stage' => 'optimized',
					'id' => $fileId
				]) . "\n\n";
			} else {
				echo "data: " . json_encode([
					'message' => "Optimization complete",
					'percentage' => 80,
					'stage' => 'optimized',
					'id' => $fileId
				]) . "\n\n";
			}
			
			if ($createThumbnail && file_exists($thumbnailPath)) {
				echo "data: " . json_encode([
					'message' => "Thumbnail created: " . basename($thumbnailPath),
					'percentage' => 90,
					'stage' => 'thumbnails',
					'id' => $fileId
				]) . "\n\n";
			}
			
			echo "data: " . json_encode([
				'message' => 'Processing complete!',
				'percentage' => 100,
				'stage' => 'complete',
				'id' => $fileId
			]) . "\n\n";
		} else {
			echo "data: " . json_encode([
				'message' => 'Optimization failed, using original file',
				'percentage' => 90,
				'stage' => 'warning',
				'id' => $fileId
			]) . "\n\n";
			
			// If optimization fails, use the original file
			if (copy($tempPath, $targetPath)) {
				unlink($tempPath);
				echo "data: " . json_encode([
					'message' => 'Processing complete with original file',
					'percentage' => 100,
					'stage' => 'complete',
					'id' => $fileId
				]) . "\n\n";
			} else {
				echo "data: " . json_encode([
					'message' => 'Error saving file',
					'percentage' => 0,
					'stage' => 'error',
					'id' => $fileId
				]) . "\n\n";
			}
		}
	} else {
		// Not an image or no optimization needed, just copy the file
		echo "data: " . json_encode([
			'message' => 'Copying file to destination...',
			'percentage' => 50,
			'stage' => 'copying',
			'id' => $fileId
		]) . "\n\n";
		
		if (copy($tempPath, $targetPath)) {
			unlink($tempPath);
			echo "data: " . json_encode([
				'message' => 'Processing complete!',
				'percentage' => 100,
				'stage' => 'complete',
				'id' => $fileId
			]) . "\n\n";
		} else {
			echo "data: " . json_encode([
				'message' => 'Error saving file',
				'percentage' => 0,
				'stage' => 'error',
				'id' => $fileId
			]) . "\n\n";
		}
	}
	
	exit;
}

if (isset($_FILES['upload_files'])) {
	$successCount = 0;
	$errorCount = 0;
	$fileCount = count($_FILES['upload_files']['name']);
	$processedFiles = [];
	
	// Generate a batch ID for this upload session
	$batchId = 'batch_' . time() . '_' . mt_rand(1000, 9999);
	
	// Check if this is an AJAX request
	$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	
	if ($isAjax) {
		// Set appropriate headers for AJAX response
		header('Content-Type: application/json');
	}
	
	// Verify upload directory exists
	if (!file_exists($fullPath)) {
		mkdir($fullPath, 0755, true);
	}
	
	// Create thumbnails directory ONLY if thumbnail creation is enabled
	$thumbsDir = null;
	if ($createThumbnail) {
		$thumbsDir = ensureThumbnailsDir($fullPath);
	}
	
	// Process each file
	for ($i = 0; $i < $fileCount; $i++) {
		// Skip empty slots
		if ($_FILES['upload_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		// Check for upload errors
		if ($_FILES['upload_files']['error'][$i] !== UPLOAD_ERR_OK) {
			$errorCount++;
			continue;
		}
		
		// Get file name and prepare target path
		$fileName = preg_replace('/[^\w\._]+/', '_', basename($_FILES['upload_files']['name'][$i]));
		$targetFile = rtrim($fullPath, '/') . '/' . $fileName;
		
		// Generate unique file ID for tracking
		$fileId = 'file_' . time() . '_' . $i . '_' . mt_rand(1000, 9999);
		
		// Check file type
		$fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
		
		if (!in_array($fileType, $allowedTypes)) {
			$errorCount++;
			continue;
		}
		
		// Check if it's an image that should be optimized
		$isImage = in_array($fileType, $imageTypes);
		$shouldOptimize = $isImage && $imageOptimizationEnabled;
		
		// Determine if WebP conversion should be applied
		$doWebPConversion = $isImage && $webpConversion && $webpSupported && $fileType !== 'webp';
		
		// Move uploaded file to temporary location
		$tempFile = $fullPath . '/temp_' . $fileName;
		if (move_uploaded_file($_FILES['upload_files']['tmp_name'][$i], $tempFile)) {
			// Get original file size for comparison
			$originalSize = filesize($tempFile);
			
			// For WebP conversion, prepare filenames
			$originalFormatFile = $targetFile;
			$webpFile = $fullPath . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '.webp';

			// Create thumbnail paths
			$thumbnailPath = '';
			if ($createThumbnail && isset($thumbsDir) && $thumbsDir) {
				// Use the correct file name variable - $fileName in this context
				$thumbnailPath = $thumbsDir . '/thumb_' . $fileName;
				if ($doWebPConversion) {
					$thumbnailPath = $thumbsDir . '/thumb_' . pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
				}
			}
			
			// Store processing details in session for SSE to access
			$_SESSION['processing_' . $fileId] = [
				'filename' => $fileName,
				'is_image' => $isImage,
				'optimize' => $shouldOptimize,
				'create_thumbnail' => $isImage && $createThumbnail,
				'max_width' => $maxWidth,
				'max_height' => $maxHeight,
				'quality' => $quality,
				'temp_path' => $tempFile,
				'target_path' => $doWebPConversion ? $webpFile : $targetFile,
				'original_size' => $originalSize,
				'convert_to_webp' => $convertToWebP,
				'thumb_width' => $thumbWidth,
				'thumb_height' => $thumbHeight
			];
			
			// Add to list of files to process
			$processedFiles[] = [
				'id' => $fileId,
				'name' => $fileName,
				'is_image' => $isImage,
				'optimize' => $shouldOptimize,
				'file_type' => $fileType,
				'webp' => $doWebPConversion
			];
			
			$successCount++;
		} else {
			$errorCount++;
		}
	}
	
	// If this is an AJAX request, return the success and file IDs
	if ($isAjax) {
		echo json_encode([
			'status' => 'uploaded',
			'message' => sprintf(__t('fm_upload_complete'), $successCount),
			'batchId' => $batchId,
			'files' => $processedFiles,
			'optimize_enabled' => $imageOptimizationEnabled,
			'create_thumbnails' => $createThumbnail,
			'convert_to_webp' => $webpConversion && $webpSupported
		]);
		exit;
	}
	
	// Set message for page refresh (non-AJAX)
	if ($successCount > 0) {
		$message = sprintf(__t('fm_upload_success_count'), $successCount);
		if ($errorCount > 0) {
			$message .= ' ' . sprintf(__t('fm_upload_fail_count'), $errorCount);
		}
	} else if ($errorCount > 0) {
		$error = __t('fm_upload_all_failed');
	}
}

// Handle folder creation
if (isset($_POST['create_folder']) && !empty($_POST['folder_name'])) {
	$folderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['folder_name']);
	$newFolder = rtrim($fullPath, '/') . '/' . $folderName;
	
	if (!file_exists($newFolder)) {
		mkdir($newFolder, 0755);
		$message = __t('fm_folder_created');
		
	} else {
		$error = __t('fm_folder_exists');
	}
}

// Handle single file deletion (POST + CSRF)
if (isset($_POST['delete_file']) && !empty($_POST['delete_file'])) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$_SESSION['error'] = __t('auth_csrf_error');
		header("Location: file-manager.php?path=" . urlencode($currentPath));
		exit;
	}
	$fileToDelete = rtrim($fullPath, '/') . '/' . $_POST['delete_file'];
	if (strpos(realpath($fileToDelete), realpath($baseUploadPath)) === 0 && file_exists($fileToDelete) && is_file($fileToDelete)) {
		if (unlink($fileToDelete)) {
			$_SESSION['message'] = __t('fm_file_deleted');
		} else {
			$_SESSION['error'] = __t('fm_file_delete_failed');
		}
	} else {
		$_SESSION['error'] = __t('fm_invalid_request');
	}
	header("Location: file-manager.php?path=" . urlencode($currentPath));
	exit;
}

// Handle bulk deletion - with support for JSON items
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
			header("Location: file-manager.php?path=" . urlencode($currentPath));
		}
		exit;
	}
	$successCount = 0;
	$errorCount = 0;
	
	// Handle regular form submission
	if (isset($_POST['selected_files']) && is_array($_POST['selected_files'])) {
		foreach ($_POST['selected_files'] as $file) {
			$fileToDelete = rtrim($fullPath, '/') . '/' . $file;
			
			// Validate the file is within our upload directory
			if (strpos(realpath($fileToDelete), realpath($baseUploadPath)) === 0 && file_exists($fileToDelete) && is_file($fileToDelete)) {
				if (unlink($fileToDelete)) {
					$successCount++;
				} else {
					$errorCount++;
				}
			} else {
				$errorCount++;
			}
		}
	} 
	// Handle AJAX JSON submission for mixed files and folders
	else if (isset($_POST['items_to_delete'])) {
		$itemsToDelete = json_decode($_POST['items_to_delete'], true);
		
		foreach ($itemsToDelete as $item) {
			$itemType = $item['type'] ?? '';
			$itemName = $item['name'] ?? '';
			
			if (empty($itemType) || empty($itemName)) {
				$errorCount++;
				continue;
			}
			
			$itemPath = rtrim($fullPath, '/') . '/' . $itemName;
			
			// Validate the item is within our upload directory
			if (strpos(realpath($itemPath), realpath($baseUploadPath)) === 0 && file_exists($itemPath)) {
				if ($itemType === 'file' && is_file($itemPath)) {
					if (unlink($itemPath)) {
						$successCount++;
					} else {
						$errorCount++;
					}
				} else if ($itemType === 'folder' && is_dir($itemPath)) {
					// Check if folder is empty
					$folderContents = scandir($itemPath);
					$isEmpty = count($folderContents) <= 2; // . and .. are always present
					
					if ($isEmpty && rmdir($itemPath)) {
						$successCount++;
					} else {
						$errorCount++;
					}
				} else {
					$errorCount++;
				}
			} else {
				$errorCount++;
			}
		}
	}
	
	// If AJAX request, return JSON
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode([
			'success' => $successCount > 0,
			'message' => sprintf(__t('fm_delete_summary'), $successCount, $errorCount)
		]);
		exit;
	}
	
	// Otherwise set session message and redirect
	if ($successCount > 0) {
		$_SESSION['message'] = sprintf(__t('fm_items_deleted'), $successCount);
		
		if ($errorCount > 0) {
			$_SESSION['message'] .= ' ' . sprintf(__t('fm_items_delete_partial'), $errorCount);
		}
	} else {
		$_SESSION['error'] = __t('fm_delete_failed');
	}
	
	// Redirect back to the current directory
	header("Location: file-manager.php?path=" . urlencode($currentPath));
	exit;
}

// Handle folder deletion (POST + CSRF)
if (isset($_POST['delete_folder']) && !empty($_POST['delete_folder'])) {
	if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		$_SESSION['error'] = __t('auth_csrf_error');
		header("Location: file-manager.php?path=" . urlencode($currentPath));
		exit;
	}
	$folderToDelete = rtrim($fullPath, '/') . '/' . $_POST['delete_folder'];
	if (strpos(realpath($folderToDelete), realpath($baseUploadPath)) === 0 && file_exists($folderToDelete) && is_dir($folderToDelete)) {
		$success = false;
		$force    = !empty($_POST['force']);
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
	header("Location: file-manager.php?path=" . urlencode($currentPath));
	exit;
}

// Get breadcrumb navigation
$breadcrumbs = [['name' => 'Files', 'path' => '']];
$pathParts = array_filter(explode('/', $currentPath), 'strlen');
$cumulativePath = '';

foreach ($pathParts as $part) {
  $cumulativePath .= $part . '/';
  $breadcrumbs[] = [
	'name' => $part,
	'path' => $cumulativePath
  ];
}

// Get files and folders in the current directory
$contents = scandir($fullPath);
$folders = [];
$files = [];

foreach ($contents as $item) {
	if ($item === '.' || $item === '..') continue;
	// Skip hidden files and system files (dot-files: .htaccess, .DS_Store, etc.)
	if ($item[0] === '.') continue;

	$itemPath = $fullPath . '/' . $item;
	
	if (is_dir($itemPath)) {
		$folders[] = $item;
	} else {
		$files[] = [
			'name' => $item,
			'size' => formatFileSize(filesize($itemPath)),
			'type' => pathinfo($itemPath, PATHINFO_EXTENSION),
			'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
		];
	}
}

// Helper functions
if (!function_exists('formatFileSize')) {
	function formatFileSize($bytes) {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		
		return round($bytes, 2) . ' ' . $units[$pow];
	}
}

function getPublicUrl($path, $file) {
  $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  $baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
  
  // Fix path handling - ensure proper slash handling
  if (!empty($path)) {
	$path = rtrim($path, '/') . '/';
  }
  
  return $baseUrl . $baseDir . '/files/' . $path . $file;
}

function getFileIcon($fileType) {
	$fileType = strtolower($fileType);
	
	$iconMap = [
		'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic', 'heif', 'bmp', 'tiff', 'tif'],
		'pdf' => ['pdf'],
		'doc' => ['doc', 'docx', 'odt', 'rtf'],
		'spreadsheet' => ['xls', 'xlsx', 'ods', 'csv'],
		'presentation' => ['ppt', 'pptx', 'odp'],
		'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'flac'],
		'video' => ['mp4', 'webm', 'ogv', 'mov'],
		'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
		'code' => ['txt', 'json', 'xml', 'html', 'css', 'js', 'php']
	];
	
	foreach ($iconMap as $icon => $types) {
		if (in_array($fileType, $types)) {
			switch ($icon) {
				case 'image': return '🖼️';
				case 'pdf': return '📕';
				case 'doc': return '📝';
				case 'spreadsheet': return '📊';
				case 'presentation': return '📑';
				case 'audio': return '🎵';
				case 'video': return '🎬';
				case 'archive': return '📦';
				case 'code': return '📄';
			}
		}
	}
	
	return '📄';
}

/**
 * Handle file and folder rename operations
 */
function handleRename() {
   global $baseUploadPath, $currentPath;
   
   $itemType = $_POST['item_type'] ?? '';
   $originalName = $_POST['original_name'] ?? '';
   $newName = $_POST['new_name'] ?? '';
   
   // Validate data
   if (empty($itemType) || empty($originalName) || empty($newName)) {
	 return [
	   'success' => false, 
	   'message' => __t('fm_rename_missing_data')
	 ];
   }
   
   // Setup paths - using currentPath from POST if available
   $fullPath = $baseUploadPath . $currentPath;
   $originalItemPath = rtrim($fullPath, '/') . '/' . $originalName;
   $newItemPath = rtrim($fullPath, '/') . '/' . $newName;
   
   // Check if the original item exists
   if (!file_exists($originalItemPath)) {
	 return [
	   'success' => false, 
	   'message' => sprintf(__t('fm_item_not_found'), $originalItemPath)
	 ];
   }
   
   // Check if the new name would overwrite an existing item
   if (file_exists($newItemPath)) {
	 return [
	   'success' => false, 
	   'message' => __t('fm_rename_exists')
	 ];
   }
   
   // Perform the rename
   $success = rename($originalItemPath, $newItemPath);
   
   return [
	 'success' => $success,
	 'message' => $success ? __t('fm_rename_success') : __t('fm_rename_failed')
   ];
 }

/**
 * Handle file and folder move operations
 */
function handleMove() {
	 global $baseUploadPath, $currentPath;
	 
	 $items = json_decode($_POST['items'] ?? '[]', true);
	 $targetFolder = $_POST['target_folder'] ?? '';
	 $targetPath = $_POST['target_path'] ?? '';
	 
	 // Validate data
	 if (empty($items) || (empty($targetFolder) && empty($targetPath))) {

	   return [
		 'success' => false, 
		 'message' => __t('fm_move_missing_data')
	   ];
	 }
	 
	 // Setup paths
	 $currentDirPath = rtrim($baseUploadPath . $currentPath, '/');
	 
	 // Handle different path cases
	 if ($targetFolder === 'root') {
	   $targetFolderPath = $baseUploadPath;
	 } 
	 elseif (!empty($targetPath)) {
	   // When the target is a folder from navigation tree
	   if ($targetPath === '') {
		 // Root folder
		 $targetFolderPath = $baseUploadPath;
	   } else {
		 $targetFolderPath = $baseUploadPath . '/' . $targetPath;
		 // Ensure no double slashes
		 $targetFolderPath = str_replace('//', '/', $targetFolderPath);
	   }
	 }
	 else {
	   // When the target is a folder in the current directory
	   $targetFolderPath = $currentDirPath . '/' . $targetFolder;
	 }
	
	 // Check if the target folder exists
	 if (!file_exists($targetFolderPath) || !is_dir($targetFolderPath)) {
	   return [
		 'success' => false, 
		 'message' => __t('fm_move_target_not_found')
	   ];
	 }
	 
	 $successCount = 0;
	 $failCount = 0;
	 $errors = [];
	 
	 // Process each item
	 foreach ($items as $item) {
	   $itemType = $item['type'] ?? '';
	   $itemName = $item['name'] ?? '';
	   
	   if (empty($itemType) || empty($itemName)) {
		 $failCount++;
		 $errors[] = __t('fm_invalid_item_data');
		 continue;
	   }
	   
	   $sourcePath = $currentDirPath . '/' . $itemName;
	   $destinationPath = $targetFolderPath . '/' . $itemName;
	   
	   // Skip if source doesn't exist
	   if (!file_exists($sourcePath)) {
		 $failCount++;
		 $errors[] = sprintf(__t('fm_item_not_found'), $itemName);
		 continue;
	   }
	   
	   // Skip if destination already exists
	   if (file_exists($destinationPath)) {
		 $failCount++;
		 $errors[] = sprintf(__t('fm_destination_exists'), $itemName);
		 continue;
	   }
	   
	   // Skip if trying to move a folder into itself or its subdirectory
	   if ($itemType === 'folder') {
		 $sourcePathNorm = rtrim($sourcePath, '/') . '/';
		 $targetPathNorm = rtrim($targetFolderPath, '/') . '/';
		 
		 if ($sourcePathNorm === $targetPathNorm || 
			 strpos($targetPathNorm, $sourcePathNorm) === 0) {
		   $failCount++;
		   $errors[] = sprintf(__t('fm_move_item_failed'), $itemName);
		   continue;
		 }
	   }
	   
	   // Move the item
	   $success = rename($sourcePath, $destinationPath);
	   
	   if ($success) {
		 $successCount++;
	   } else {
		 $failCount++;
		 $errors[] = sprintf(__t('fm_move_item_failed'), $itemName);
	   }
	 }
	 
	 // Return response
	 if ($failCount === 0) {
	   return [
		 'success' => true,
		 'message' => sprintf(__t('fm_move_success'), $successCount)
	   ];
	 } else {
	   return [
		 'success' => $successCount > 0,
		 'message' => sprintf(__t('fm_move_partial'), $successCount, $failCount),
		 'errors' => $errors
	   ];
	 }
 }

// Handle AJAX requests for rename and move operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['action'])) {
		header('Content-Type: application/json');
		
		switch ($_POST['action']) {
			case 'rename':
				echo json_encode(handleRename());
				exit;
				
			case 'move':
				echo json_encode(handleMove());
				exit;
		}
	}
}

?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(lang_current()); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php _e('file_manager'); ?> | SynaptiKCMS Admin</title>
	<link rel="stylesheet" href="css/admin-base.css">
	<link rel="stylesheet" href="css/admin-components.css">
	<link rel="stylesheet" href="css/admin-filemanager.css">
	<link rel="stylesheet" href="css/admin-sidebar.css">
	<link rel="icon" type="image/x-icon" href="../files/favicon.ico">
	<link rel="icon" type="image/png" sizes="32x32" href="../files/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="32x32" href="../files/apple-touch-icon.png">
	<script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
	<script>window.CMS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;</script>
	<style>
	/* Folder drag and drop styling */
	.folder-drag-overlay {
		display: none;
	}
	/* Folder item highlight state */
	.folder-item.drag-over {
		z-index: 100 !important;
		background-color: #e0f7e5 !important;
		border: 2px dashed #4fa75c !important;
		box-shadow: 0 0 12px rgba(79, 167, 92, 0.5) !important;
		position: relative;
		transform: translateY(-2px);
		transition: transform 0.2s ease, box-shadow 0.2s ease;
	}
	/* Folder drag overlay */
	.folder-item.drag-over .folder-drag-overlay {
		display: flex !important;
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: rgba(79, 167, 92, 0.2);
		justify-content: center;
		align-items: center;
		z-index: 101 !important;
		font-weight: bold;
		color: #fff;
		text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
		animation: pulseOverlay 1.5s infinite alternate;
	}
	/* Animation for overlay pulsing */
	@keyframes pulseOverlay {
		0% { background-color: rgba(79, 167, 92, 0.2); }
		100% { background-color: rgba(79, 167, 92, 0.4); }
	}
	/* File grid and folder interaction */
	.file-grid.grid-drag-over:has(.folder-item.drag-over) {
		background-color: transparent !important;
		border: none !important;
	}
	/* Ensure folder highlight takes precedence */
	.file-grid.grid-drag-over .folder-item.drag-over {
		background-color: #e0f7e5 !important;
		border: 2px dashed #4fa75c !important;
	}
	/* Safari requires -webkit-user-drag for draggable elements */
	.file-item,
	.folder-item {
		-webkit-user-drag: element;
	}
	/* Breadcrumb drop targets during drag */
	.breadcrumbs a.breadcrumb-drag-over {
		background-color: rgba(79, 167, 92, 0.15);
		color: #4fa75c;
		text-decoration: underline;
		border-radius: 3px;
		outline: 2px dashed #4fa75c;
		outline-offset: 2px;
	}
	</style>
</head>
<body>
	<script>
	  // Apply sidebar state immediately to prevent flash
	  (function() {
		try {
		  var saved = localStorage.getItem('synaptik_sidebar_state');
		  if (saved) {
			var state = JSON.parse(saved);
			if (state.isExpanded === false) {
			  document.body.classList.add('sidebar-collapsed');
			} else {
			  document.body.classList.add('sidebar-expanded');
			}
		  } else {
			document.body.classList.add('sidebar-expanded');
		  }
		} catch(e) {
		  document.body.classList.add('sidebar-expanded');
		}
	  })();
	</script>
	<div class="admin-container">
		<?php include_once 'includes/sidebar.php'; ?>
		<main class="content">
			<h1 class="main-heading"><?php _e('file_manager'); ?></h1>
			<a href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/'; ?>" target="_blank" class="view-website-btn">
				<span class="icon">🌐</span> <?php _e('view_website'); ?>
			</a>
			<?php if (isset($message)): ?>
			<div class="message success"><?php echo $message; ?></div>
			<?php endif; ?>

			<?php if (isset($error)): ?>
			<div class="message error"><?php echo $error; ?></div>
			<?php endif; ?>

			<div class="file-manager">
				<!-- Collapsible upload/folder panel -->
				<div class="fm-controls-header">
					<button class="button small" id="fm-controls-toggle">
						<span id="fm-controls-toggle-icon">▶</span>&ensp;<?php _e('file_upload'); ?>
					</button>
				</div>
				<div class="file-manager-controls" id="fm-controls-panel">
					<div class="upload-form">
						<h3 class="form-title"><?php _e('file_upload'); ?></h3>
						<form method="post" enctype="multipart/form-data" id="upload-form">
							<input type="file" name="upload_files[]" id="file-input" multiple style="position: absolute; width: 0; height: 0; overflow: hidden; opacity: 0;">
							<div class="dropzone" id="dropzone" tabindex="0">
								<p><?php _e('fm_dropzone_text'); ?></p>
								<p style="font-size:.8em;color: rgb(82, 82, 82)"><?php _e('fm_upload_limit'); ?></p>
							</div>

							<!-- Progress display -->
							<div class="upload-progress" id="upload-progress" style="display: none;">
								<p><?php _e('fm_uploading_files'); ?> <span id="file-count">0</span> :</p>
								<span id="progress-stage" class="progress-stage"><?php _e('fm_starting'); ?></span>
								<div class="progress-bar">
									<div class="progress-fill" id="progress-fill"></div>
								</div>
								<p id="progress-message" class="progress-message"></p>
							</div>

							<button class="button" type="submit" id="upload-button" style="margin-top: 10px;"><?php _e('fm_upload_btn'); ?></button>
						</form>
					</div>
					<div class="folder-form">
						<h3 class="form-title"><?php _e('new_folder'); ?></h3>
						<form method="post">
							<input type="text" name="folder_name" placeholder="<?php _e('fm_folder_name_placeholder'); ?>" required>
							<button class="button" type="submit" name="create_folder"><?php _e('create'); ?></button>
						</form>
					</div>
				</div>

				<!-- Bulk actions toolbar - hidden by default -->
				<div class="bulk-actions" id="bulk-actions" style="display: none;">
					<form method="post" id="bulk-form">
						<input type="hidden" name="bulk_delete" value="1">
						<div id="selected-files-container"></div>
						<button type="submit" class="button danger"><?php _e('delete_selected'); ?></button>
					</form>
					<div class="select-all-container">
						<input type="checkbox" id="select-all">
						<label for="select-all"><?php _e('fm_select_all'); ?></label>
					</div>
				</div>
				<!-- New breadcrumb and folder navigation -->
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
							<button id="toggle-view-mode" class="button" data-mode="grid">▦&ensp;<?php _e('fm_list_view'); ?></button>
						</div>
					</div>
				</div>
				<!-- Search / filter / sort toolbar -->
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
					<?php foreach ($folders as $folder): ?>
					<?php
					  // Check if folder has contents
					  $folderPath = $fullPath . '/' . $folder;
					  $folderContents = scandir($folderPath);
					  $hasContents = count($folderContents) > 2; // More than just . and ..
					?>
					<div class="folder-item" draggable="true" data-type="folder" data-name="<?php echo htmlspecialchars($folder); ?>" data-has-contents="<?php echo $hasContents ? 'true' : 'false'; ?>" data-filetype="folder" data-modified="<?php echo filemtime($folderPath); ?>" data-bytes="0">
					  <!-- Separate the drag handler from the navigation link -->
					  <div class="folder-content">
						<div class="folder-icon">📁</div>
						<div class="folder-name"><?php echo htmlspecialchars($folder); ?></div>
					  </div>
					  <!-- Navigation link as a separate clickable button -->
					  <a href="?path=<?php echo urlencode($currentPath . $folder . '/'); ?>" class="folder-navigate-btn">
						<span class="navigate-icon"><?php _e('fm_open'); ?></span>
					  </a>
					  <div class="folder-drag-overlay"><?php _e('fm_drop_here'); ?></div>
					  <div class="file-actions">
						<a href="#" class="rename-btn" data-name="<?php echo htmlspecialchars($folder); ?>" data-type="folder"><?php _e('file_rename'); ?></a>
						<a href="?path=<?php echo urlencode($currentPath); ?>&delete_folder=<?php echo urlencode($folder); ?>"><?php _e('delete'); ?></a>
					  </div>
					</div>
					<?php endforeach; ?>

					<?php foreach ($files as $file): ?>
					<?php
					$_fm_ext = strtolower($file['type']);
					$_fm_imageExts = ['jpg','jpeg','png','gif','webp','svg','heic','heif','bmp','tiff','tif'];
					$_fm_videoExts = ['mp4','webm','ogv','mov'];
					$_fm_audioExts = ['mp3','wav','ogg','m4a','flac'];
					$_fm_docExts   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','odt','ods','odp','csv'];
					$_fm_archExts  = ['zip','rar','7z','tar','gz'];
					if (in_array($_fm_ext, $_fm_imageExts)) $_fm_ftype = 'image';
					elseif (in_array($_fm_ext, $_fm_videoExts)) $_fm_ftype = 'video';
					elseif (in_array($_fm_ext, $_fm_audioExts)) $_fm_ftype = 'audio';
					elseif (in_array($_fm_ext, $_fm_docExts))   $_fm_ftype = 'document';
					elseif (in_array($_fm_ext, $_fm_archExts))  $_fm_ftype = 'archive';
					else $_fm_ftype = 'other';
					$_fm_bytes    = filesize($fullPath . '/' . $file['name']);
					$_fm_modified = filemtime($fullPath . '/' . $file['name']);
				?>
					<div class="file-item" draggable="true" data-type="file" data-name="<?php echo htmlspecialchars($file['name']); ?>" data-filetype="<?php echo $_fm_ftype; ?>" data-modified="<?php echo $_fm_modified; ?>" data-bytes="<?php echo $_fm_bytes; ?>">
						<input type="checkbox" class="selection-checkbox" data-filename="<?php echo htmlspecialchars($file['name']); ?>" style="display: none;">
						<?php if (in_array(strtolower($file['type']), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic', 'heif', 'bmp', 'tiff', 'tif'])): ?>
						<div class="file-thumbnail">
							<img src="<?php echo getPublicUrl($currentPath, $file['name']); ?>" alt="<?php echo htmlspecialchars($file['name']); ?>">
						</div>
						<?php else: ?>
						<div class="file-icon">
							<?php echo getFileIcon($file['type']); ?>
						</div>
						<?php endif; ?>
						<div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
						<div class="file-size"><?php echo $file['size']; ?></div>
						<div class="file-actions">
							<a href="<?php echo getPublicUrl($currentPath, $file['name']); ?>" class="view-img-btn" target="_blank"><?php _e('view'); ?></a>
							<a href="#" class="copy-url-btn" data-url="<?php echo getPublicUrl($currentPath, $file['name']); ?>"><?php _e('fm_copy_url_btn'); ?></a>
							<a href="#" class="rename-btn" data-name="<?php echo htmlspecialchars($file['name']); ?>" data-type="file"><?php _e('file_rename'); ?></a>
							<a href="?path=<?php echo urlencode($currentPath); ?>&delete=<?php echo urlencode($file['name']); ?>" class="delete-file-link"><?php _e('delete'); ?></a>
						</div>
					</div>
					<?php endforeach; ?>

					<?php if (empty($folders) && empty($files)): ?>
					<p class="empty-message"><?php _e('fm_folder_empty'); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</main>
	</div>
	<!-- Custom Modal -->
	<div id="custom-modal" class="modal-overlay">
		<div class="modal-container">
			<div class="modal-header danger">
				<span class="modal-title"><?php _e('notification'); ?></span>
				<span class="modal-close">&times;</span>
			</div>
			<div class="modal-message-content">
				<p id="modal-message"></p>
			</div>
			<div class="modal-footer">
				<!-- Buttons will be inserted dynamically via JavaScript -->
			</div>
		</div>
	</div>
	<!-- Context Menu for Files and Folders -->
	<div id="context-menu" class="context-menu">
		<ul>
			<li data-action="rename"><span class="action-icon">✏️</span> <?php _e('file_rename'); ?></li>
			<li data-action="delete"><span class="action-icon">🗑️</span> <?php _e('delete'); ?></li>
			<li data-action="copy-url" class="file-only"><span class="action-icon">📋</span> <?php _e('fm_copy_url'); ?></li>
			<li data-action="view" class="file-only"><span class="action-icon">👁️</span> <?php _e('view'); ?></li>
		</ul>
	</div>
	<!-- Rename Dialog -->
	<div id="rename-dialog" class="modal-overlay">
		<div class="modal-container">
			<div class="modal-header">
				<span class="modal-title"><?php _e('fm_rename_item'); ?></span>
				<span class="modal-close">&times;</span>
			</div>
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
				<button type="button" class="button cancel" id="rename-cancel"><?php _e('cancel'); ?></button>
				<button type="button" class="button" id="rename-confirm"><?php _e('file_rename'); ?></button>
			</div>
		</div>
	</div>
	<!-- Multiple Selection Indicator -->
	<div id="selection-indicator" class="selection-indicator" style="display: none;">
		<span id="selection-count">0</span>&nbsp; <?php _e('items'); ?>
		<div class="selection-actions">
			<button id="select-all-btn" class="button selection-action-btn"><?php _e('fm_select_all'); ?></button>
			<button id="delete-selected" class="button selection-action-btn danger"><?php _e('delete'); ?></button>
			<button id="cancel-selection" class="button selection-action-btn cancel"><?php _e('cancel'); ?></button>
		</div>
	</div>
	<!-- Drag overlay for folders -->
	<div id="folder-drag-overlay" class="folder-drag-overlay"><?php _e('fm_drop_here'); ?></div>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script src="js/common.js"></script>
	<script src="js/file-manager.js"></script>
	<script src="js/admin-sidebar.js"></script>
</body>
</html>