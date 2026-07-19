<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin'])) {
	header('HTTP/1.1 403 Forbidden');
	echo json_encode(['error' => 'Not authorized']);
	exit;
}

// Get the requested path parameter
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Validate the path to prevent directory traversal
if (strpos($path, '..') !== false) {
	header('HTTP/1.1 400 Bad Request');
	echo json_encode(['error' => 'Invalid path']);
	exit;
}

// Define the base upload directory
$baseUploadDir = '../files/';
$fullPath = $baseUploadDir . $path;

// Ensure the path exists and is a directory
if (!file_exists($fullPath) || !is_dir($fullPath)) {
	// Create the directory if it doesn't exist
	if (!mkdir($fullPath, 0755, true)) {
		header('HTTP/1.1 500 Internal Server Error');
		echo json_encode(['error' => 'Failed to create directory']);
		exit;
	}
}

// Get all files and folders in the current directory
$items = scandir($fullPath);
$folders = [];
$files = [];

foreach ($items as $item) {
	// Skip . and ..
	if ($item === '.' || $item === '..') {
		continue;
	}
	
	$itemPath = $fullPath . '/' . $item;
	
	if (is_dir($itemPath)) {
		// Add to folders list
		$folders[] = $item;
	} else {
		// Get file information
		$fileInfo = [
			'name' => $item,
			'size' => filesize($itemPath),
			'type' => pathinfo($itemPath, PATHINFO_EXTENSION),
			'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
		];
		
		// Add to files list
		$files[] = $fileInfo;
	}
}

// Format file sizes
foreach ($files as &$file) {
	$file['size'] = formatFileSize($file['size']);
	$file['is_image'] = isImageFile($file['type']);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
	'folders' => $folders,
	'files' => $files,
	'current_path' => $path
]);

/**
 * Helper function to format file size
 */
function formatFileSize($bytes) {
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	
	return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Helper function to determine if file is an image
 */
function isImageFile($type) {
	$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic', 'heif', 'bmp', 'tiff', 'tif'];
	return in_array(strtolower($type), $imageTypes);
}