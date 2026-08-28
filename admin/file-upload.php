<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';
if (!admin_is_logged_in()) {
	header('HTTP/1.1 403 Forbidden');
	echo json_encode(['error' => 'Not authorized']);
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$_csrfToken = $_POST['csrf_token'] ?? (getallheaders()['X-CSRF-Token'] ?? '');
	if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_csrfToken)) {
		header('HTTP/1.1 403 Forbidden');
		echo json_encode(['error' => 'Invalid security token.']);
		exit;
	}
}

require_once('image-optimization.php');
$allowedTypes = [
	'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'tiff', 'tif',
	'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'
];

$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxFileSize = 10 * 1024 * 1024; // 10MB max for Editor uploads
$targetDir = '../files/';

if (!file_exists($targetDir)) {
	if (!mkdir($targetDir, 0755, true)) {
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'Failed to create upload directory']);
		exit;
	}
}

$thumbsDir = ensureThumbnailsDir($targetDir);

$settingsFile = dirname(__DIR__) . '/config.json';
$appSettings = [];
if (file_exists($settingsFile)) {
	$loadedSettings = json_decode(file_get_contents($settingsFile), true);
	if (is_array($loadedSettings)) {
		$appSettings = $loadedSettings;
	}
}

$imageOptimizationEnabled = $appSettings['image_optimization_enabled'] ?? true;
$maxWidth = $appSettings['max_width'] ?? 1920;
$maxHeight = $appSettings['max_height'] ?? 1080;
$quality = $appSettings['image_quality'] ?? 85;
$createThumbnail = $appSettings['create_thumbnails'] ?? true;
$thumbWidth = $appSettings['thumb_width'] ?? 300;
$thumbHeight = $appSettings['thumb_height'] ?? 300;
$convertToWebP      = $appSettings['convert_to_webp']          ?? false;
$keepOriginalFormat = $appSettings['keep_original_format'] ?? false;

$webpSupported = function_exists('imagewebp');

if (!$webpSupported) {
	$convertToWebP = false;
}

if (isset($_FILES['upload']) && $_FILES['upload']['error'] === 0) {
	if ($_FILES['upload']['size'] > $maxFileSize) {
		header('HTTP/1.1 400 Bad Request');
		echo json_encode(['error' => 'File too large. Maximum file size is 10MB.']);
		exit;
	}
	$originalName = basename($_FILES['upload']['name']);
	$fileName = sanitizeFileName($originalName);
	$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	if (!in_array($fileExtension, $allowedTypes)) {
		header('HTTP/1.1 400 Bad Request');
		echo json_encode([
			'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)
		]);
		exit;
	}
	
	$uniqueFileName = time() . '_' . $fileName;
	$targetFile = $targetDir . $uniqueFileName;
	$isImage = in_array($fileExtension, $imageTypes);
	$shouldOptimize = $isImage && $imageOptimizationEnabled;
	
	if (extension_loaded('fileinfo')) {
		$finfo    = new finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($_FILES['upload']['tmp_name']);
		$strictMimes = [
			'jpg'  => ['image/jpeg'],
			'jpeg' => ['image/jpeg'],
			'png'  => ['image/png'],
			'gif'  => ['image/gif'],
			'webp' => ['image/webp'],
			'heic' => ['image/heic', 'image/heif'],
			'heif' => ['image/heic', 'image/heif'],
			'bmp'  => ['image/bmp', 'image/x-bmp', 'image/x-ms-bmp'],
			'tiff' => ['image/tiff'],
			'tif'  => ['image/tiff'],
			'pdf'  => ['application/pdf'],
			'txt'  => ['text/plain'],
		];

		$bannedMimes = [
			'text/html', 'application/x-php', 'application/php',
			'text/x-php', 'application/x-httpd-php', 'application/x-httpd-php3',
		];

		if (in_array($mimeType, $bannedMimes, true)) {
			header('HTTP/1.1 400 Bad Request');
			echo json_encode(['error' => 'File type not allowed.']);
			exit;
		}

		if (isset($strictMimes[$fileExtension]) && !in_array($mimeType, $strictMimes[$fileExtension], true)) {
			header('HTTP/1.1 400 Bad Request');
			echo json_encode(['error' => 'File content does not match declared extension.']);
			exit;
		}
	}

	$doWebPConversion = $isImage && $convertToWebP && $webpSupported && $fileExtension !== 'webp';
	$originalFormatFile = $targetFile;
	$webpFile = $targetDir . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
	
	if ($shouldOptimize) {
	  $thumbnailPath = $thumbsDir . '/thumb_' . $uniqueFileName;
	  $tempFile = $targetDir . 'temp_' . $uniqueFileName;
	  if (move_uploaded_file($_FILES['upload']['tmp_name'], $tempFile)) {
		$originalSize = filesize($tempFile);
		try {
		  $webpFilePath = '';
		  if ($doWebPConversion) {
			$webpFilePath = $targetDir . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
		  }
		  $primaryDestination = $doWebPConversion && !$keepOriginalFormat ? $webpFilePath : $targetFile;
		  $thumbnailPath = $thumbsDir . '/thumb_' . $uniqueFileName;
		  if ($doWebPConversion && !$keepOriginalFormat) {
			$thumbnailPath = $thumbsDir . '/thumb_' . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
		  }
		  
		  $optimizeResult = optimizeImage(
			$tempFile,                       // Source file
			$primaryDestination,             // Destination file
			$maxWidth,                       // Max width
			$maxHeight,                      // Max height
			$quality,                        // Quality
			$createThumbnail,                // Create thumbnail
			$thumbnailPath,                  // Thumbnail path
			$thumbWidth,                     // Thumbnail width
			$thumbHeight,                    // Thumbnail height
			true,                            // Delete original after optimization
			$convertToWebP,                  // Convert to WebP
			$keepOriginalFormat              // Keep original format
		  );
		  
		  $resultFile     = $primaryDestination;
		  $resultFilename = basename($resultFile);

		  if (!$optimizeResult) {
			if (!copy($tempFile, $targetFile)) {
				header('HTTP/1.1 500 Internal Server Error');
				echo json_encode(['error' => 'Failed to process uploaded image']);
				exit;
			}
			$resultFile     = $targetFile;
			$resultFilename = $uniqueFileName;
		  }
		} catch (Exception $e) {
		  if (!copy($tempFile, $targetFile)) {
			header('HTTP/1.1 500 Internal Server Error');
			echo json_encode(['error' => 'Failed to process uploaded image']);
			exit;
		  }
		  $resultFile = $targetFile;
		  $resultFilename = $uniqueFileName;
		}
		
		if (file_exists($tempFile)) {
		  @unlink($tempFile);
		}
	  }
	}

	$baseUrl = (_sl_request_is_https() ? "https" : "http") . "://" . _sl_request_host();
	$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
	$fileUrl = $baseUrl . $baseDir . '/files/editor/' . basename($resultFile);
	
	header('Content-Type: application/json');
	echo json_encode([
		'uploaded' => 1,
		'fileName' => basename($resultFile),
		'url' => $fileUrl,
		'optimized' => $shouldOptimize ? 1 : 0,
		'convertedToWebP' => $doWebPConversion ? 1 : 0,
		'thumbnail' => $shouldOptimize && $createThumbnail ? 1 : 0,
		'thumbnailUrl' => $shouldOptimize && $createThumbnail ?
			$baseUrl . $baseDir . '/files/editor/thumbs/thumb_' . basename($resultFile) : ''
	]);
	exit;
} 
else {
	$errorCode = isset($_FILES['upload']) ? $_FILES['upload']['error'] : 'No file submitted';
	
	header('HTTP/1.1 400 Bad Request');
	echo json_encode([
		'error' => 'File upload failed. Error code: ' . $errorCode,
		'allowed_types' => $allowedTypes,
		'max_size' => $maxFileSize
	]);
	exit;
}