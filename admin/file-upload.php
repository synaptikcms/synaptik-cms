<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin'])) {
	// Return error if not logged in
	header('HTTP/1.1 403 Forbidden');
	echo json_encode(['error' => 'Not authorized']);
	exit;
}

// Include image optimization functions
require_once('image-optimization.php');

// Sanitize filename function
function sanitizeFileName($filename) {
	// Remove any non-alphanumeric characters except dots, hyphens, and underscores
	$filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
	
	// Limit filename length
	$filename = substr($filename, 0, 255);
	
	// Ensure filename is not empty
	if (empty($filename)) {
		$filename = 'unnamed_file_' . time();
	}
	
	return $filename;
}

// Expanded list of allowed file types
$allowedTypes = [
	// Images
	'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic', 'heif', 'bmp', 'tiff', 'tif',
	// Documents
	'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'
];

// List of image types that should be optimized
$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$maxFileSize = 10 * 1024 * 1024; // 10MB max for Editor uploads

// Set target directory
$targetDir = '../files/';

// Create directory if it doesn't exist
if (!file_exists($targetDir)) {
	if (!mkdir($targetDir, 0755, true)) {
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'Failed to create upload directory']);
		exit;
	}
}

// Thumbnails directory
$thumbsDir = ensureThumbnailsDir($targetDir);

// Load settings directly from settings file to ensure latest values
$settingsFile = '../settings.json';
$appSettings = [];
if (file_exists($settingsFile)) {
	$loadedSettings = json_decode(file_get_contents($settingsFile), true);
	if (is_array($loadedSettings)) {
		$appSettings = $loadedSettings;
	}
}

// Image optimization settings - use consistent variable names
$imageOptimizationEnabled = $appSettings['image_optimization_enabled'] ?? true;
$maxWidth = $appSettings['max_width'] ?? 1920;
$maxHeight = $appSettings['max_height'] ?? 1080;
$quality = $appSettings['image_quality'] ?? 85;
$createThumbnail = $appSettings['create_thumbnails'] ?? true;
$thumbWidth = $appSettings['thumb_width'] ?? 300;
$thumbHeight = $appSettings['thumb_height'] ?? 300;
$convertToWebP = $appSettings['convert_to_webp'] ?? false;
// $keepOriginalFormat = $appSettings['keep_original_format'] ?? false;

// Always log settings for debugging
error_log('File Upload Settings: ' . json_encode([
	'convertToWebP' => $convertToWebP,
	//'keepOriginalFormat' => $keepOriginalFormat
]));

// Check if WebP is supported
$webpSupported = function_exists('imagewebp');

// If WebP is not supported, disable conversion
if (!$webpSupported) {
	$convertToWebP = false;
}

// CKEditor 4 sends the file with the parameter name "upload"
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === 0) {
	// Check file size
	if ($_FILES['upload']['size'] > $maxFileSize) {
		header('HTTP/1.1 400 Bad Request');
		echo json_encode(['error' => 'File too large. Maximum file size is 10MB.']);
		exit;
	}
	
	// Sanitize filename
	$originalName = basename($_FILES['upload']['name']);
	$fileName = sanitizeFileName($originalName);
	
	// Check file type
	$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	if (!in_array($fileExtension, $allowedTypes)) {
		header('HTTP/1.1 400 Bad Request');
		echo json_encode([
			'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)
		]);
		exit;
	}
	
	// Generate unique filename to prevent overwriting
	$uniqueFileName = time() . '_' . $fileName;
	$targetFile = $targetDir . $uniqueFileName;
	
	// Check if it's an image that should be optimized
	$isImage = in_array($fileExtension, $imageTypes);
	$shouldOptimize = $isImage && $imageOptimizationEnabled;
	
	// Determine if WebP conversion should be applied
	$doWebPConversion = $isImage && $webpConversion && $webpSupported && $fileExtension !== 'webp';
	
	// For WebP conversion, prepare both filenames
	$originalFormatFile = $targetFile;
	$webpFile = $targetDir . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
	
	// Log all relevant settings before optimization
	error_log('File Upload WebP Settings: ' . json_encode([
		'convert_to_webp' => $webpConversion,
		'keep_original_format' => $keepOriginalFormat,
		'is_image' => $isImage,
		'file_extension' => $fileExtension,
		'webp_supported' => $webpSupported
	]));
	// Check if a specific page is set as homepage
	if ($shouldOptimize) {
	  // Create thumbnail path
	  $thumbnailPath = $thumbsDir . '/thumb_' . $uniqueFileName;
	  
	  // First move the uploaded file to a temporary location
	  $tempFile = $targetDir . 'temp_' . $uniqueFileName;
	  if (move_uploaded_file($_FILES['upload']['tmp_name'], $tempFile)) {
		// Log original file size for debugging
		$originalSize = filesize($tempFile);
		error_log("Original file size for {$uniqueFileName}: " . $originalSize . " bytes");
		
		try {
		  // Define WebP file path for later (if needed)
		  $webpFilePath = '';
		  if ($doWebPConversion) {
			$webpFilePath = $targetDir . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
		  }
		  
		  // Define the primary destination path - this will either be the original format or webp
		  $primaryDestination = $doWebPConversion && !$keepOriginalFormat ? $webpFilePath : $targetFile;
		  
		  // Define the thumbnail path
		  $thumbnailPath = $thumbsDir . '/thumb_' . $uniqueFileName;
		  if ($doWebPConversion && !$keepOriginalFormat) {
			$thumbnailPath = $thumbsDir . '/thumb_' . pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.webp';
		  }
		  
		  // Optimize the image
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
		  
		  // The destination file is what we'll return to CKEditor
		  $resultFile = $primaryDestination;
		  $resultFilename = basename($resultFile);
		  
		  // If optimization was successful
		  if ($optimizeResult) {
			// Get new file size for logging
			$newSize = 0;
			if (file_exists($primaryDestination)) {
			  $newSize += filesize($primaryDestination);
			}
			if ($doWebPConversion && $keepOriginalFormat && file_exists($webpFilePath)) {
			  $newSize += filesize($webpFilePath);
			}
			
			$reductionPercent = round(100 - (($newSize / $originalSize) * 100), 2);
			error_log("Optimized file size for {$uniqueFileName}: " . $newSize . " bytes (reduced by {$reductionPercent}%)");
		  } else {
			error_log("Optimization failed, falling back to direct copy");
			// If optimization fails, try a direct copy
			if (!copy($tempFile, $targetFile)) {
			  header('HTTP/1.1 500 Internal Server Error');
			  echo json_encode(['error' => 'Failed to process uploaded image']);
			  exit;
			}
			$resultFile = $targetFile;
			$resultFilename = $uniqueFileName;
		  }
		} catch (Exception $e) {
		  error_log("Exception during image processing: " . $e->getMessage());
		  // If there's an exception, fall back to direct copy
		  if (!copy($tempFile, $targetFile)) {
			header('HTTP/1.1 500 Internal Server Error');
			echo json_encode(['error' => 'Failed to process uploaded image']);
			exit;
		  }
		  $resultFile = $targetFile;
		  $resultFilename = $uniqueFileName;
		}
		
		// Clean up temporary file if it still exists
		if (file_exists($tempFile)) {
		  @unlink($tempFile);
		}
	  }
	}

	// Calculate the URL relative to the site root
	$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
	$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
	$fileUrl = $baseUrl . $baseDir . '/files/editor/' . basename($resultFile);
	
	// Format the response based on request type
	if (isset($_GET['CKEditorFuncNum'])) {
		// CKEditor 4 callback method
		$funcNum = $_GET['CKEditorFuncNum'];
		echo "<script>window.parent.CKEDITOR.tools.callFunction($funcNum, '$fileUrl', '');</script>";
	} else {
		// JSON response method
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
	}
	exit;
} 
else {
	$errorCode = isset($_FILES['upload']) ? $_FILES['upload']['error'] : 'No file submitted';
	
	// Return error if no file was submitted or there was an upload error
	header('HTTP/1.1 400 Bad Request');
	echo json_encode([
		'error' => 'File upload failed. Error code: ' . $errorCode,
		'allowed_types' => $allowedTypes,
		'max_size' => $maxFileSize
	]);
	exit;
}