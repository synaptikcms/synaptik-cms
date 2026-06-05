<?php
/**
 * Helper function to optimize uploaded images
 */

function optimizeImage($sourceFile, $destinationFile, $maxWidth = 1920, $maxHeight = 1080, $quality = 85, $createThumbnail = false, $thumbnailPath = '', $thumbWidth = 300, $thumbHeight = 300, $deleteOriginal = false, $convertToWebP = false) {
// Add detailed logging for debugging
error_log("Starting optimization for: " . $sourceFile);
error_log("Destination: " . $destinationFile);
error_log("Parameters: maxWidth=$maxWidth, maxHeight=$maxHeight, quality=$quality, webp=$convertToWebP");

// Check if source file exists
if (!file_exists($sourceFile)) {
	error_log("ERROR: Source file doesn't exist: " . $sourceFile);
	return false;
}

// Check write permissions
$destDir = dirname($destinationFile);
if (!is_writable($destDir)) {
	error_log("ERROR: Destination directory not writable: " . $destDir);
	return false;
}

// Check if GD is installed
if (!extension_loaded('gd')) {
	error_log("ERROR: GD library not available");
	return false;
}

 $webpSupported = function_exists('imagewebp');
 $doWebPConversion = $convertToWebP && $webpSupported;

 error_log('Optimize Image Settings: ' . json_encode([
   'convertToWebP' => $convertToWebP,
   'webpSupported' => $webpSupported,

   'destinationFile' => $destinationFile
 ]));

  if (!file_exists($sourceFile)) {
	error_log("Source file does not exist: " . $sourceFile);
	return false;
  }

  if (!extension_loaded('gd')) {
	error_log('GD Library not available. Image optimization skipped.');

	$copyResult = copy($sourceFile, $destinationFile);

	if ($deleteOriginal && $copyResult) {
	  @unlink($sourceFile);
	}
	return $copyResult;
  }

  $imageInfo = getimagesize($sourceFile);
  if ($imageInfo === false) {
	error_log('Cannot get image size: ' . $sourceFile);

	$copyResult = copy($sourceFile, $destinationFile);

	if ($deleteOriginal && $copyResult) {
	  @unlink($sourceFile);
	}

	return $copyResult;
  }

  $imageWidth = $imageInfo[0];
  $imageHeight = $imageInfo[1];
  $imageType = $imageInfo[2];

  error_log("Image dimensions: " . $imageWidth . "x" . $imageHeight . ", type: " . $imageType);

  $doWebPConversion = $convertToWebP && function_exists('imagewebp');

  $originalDestination = $destinationFile;
  $destinationExtension = strtolower(pathinfo($destinationFile, PATHINFO_EXTENSION));

  $webpFile = '';

	if ($doWebPConversion && $destinationExtension !== 'webp') {
	  $webpFile = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $destinationFile);
	  error_log("WebP file path prepared: $webpFile");

	  $destinationFile = $webpFile;
	  error_log("Destination changed to WebP: $destinationFile");
	}
  

	if ($imageWidth <= $maxWidth && $imageHeight <= $maxHeight && 
		$quality >= 100 && 
		!$convertToWebP && 
		$createThumbnail === false) {
	error_log("Image already within size limits and no thumbnail requested, copying directly");
	$copyResult = copy($sourceFile, $destinationFile);

	if ($deleteOriginal && $copyResult) {
	  @unlink($sourceFile);
	}
	return $copyResult;
  }

  $image = null;
  switch ($imageType) {
	case IMAGETYPE_JPEG:
	  $image = @imagecreatefromjpeg($sourceFile);
	  break;
	case IMAGETYPE_PNG:
	  $image = @imagecreatefrompng($sourceFile);
	  break;
	case IMAGETYPE_GIF:
	  $image = @imagecreatefromgif($sourceFile);
	  break;
	case IMAGETYPE_WEBP:
	  if (function_exists('imagecreatefromwebp')) {
		$image = @imagecreatefromwebp($sourceFile);
	  }
	  break;
	default:
	  error_log("Unsupported image type: " . $imageType);
	  $copyResult = copy($sourceFile, $destinationFile);

	  if ($deleteOriginal && $copyResult) {
		@unlink($sourceFile);
	  }

	  return $copyResult;
  }

  if (!$image) {
	error_log("Failed to create image resource from: " . $sourceFile);
	$copyResult = copy($sourceFile, $destinationFile);

	if ($deleteOriginal && $copyResult) {
	  @unlink($sourceFile);
	}

	return $copyResult;
  }

  list($newWidth, $newHeight) = calculateDimensions($imageWidth, $imageHeight, $maxWidth, $maxHeight);
  error_log("New dimensions: " . $newWidth . "x" . $newHeight);

  $needsResizing = ($newWidth !== $imageWidth || $newHeight !== $imageHeight);

  if ($needsResizing) {
	$newImage = imagecreatetruecolor($newWidth, $newHeight);
	if (!$newImage) {
	  error_log("Failed to create true color image");
	  imagedestroy($image);
	  $copyResult = copy($sourceFile, $destinationFile);

	  if ($deleteOriginal && $copyResult) {
		@unlink($sourceFile);
	  }

	  return $copyResult;
	}

	if ($imageType === IMAGETYPE_PNG) {

	  imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
	  imagealphablending($newImage, false);
	  imagesavealpha($newImage, true);
	}

	$success = imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $imageWidth, $imageHeight);
	if (!$success) {
	  error_log("Failed to resize image");
	  imagedestroy($image);
	  imagedestroy($newImage);
	  $copyResult = copy($sourceFile, $destinationFile);

	  if ($deleteOriginal && $copyResult) {
		@unlink($sourceFile);
	  }

	  return $copyResult;
	}
  } else {

	$newImage = $image;
	error_log("No resizing needed, same dimensions");
  }

  $destDir = dirname($destinationFile);
  if (!file_exists($destDir)) {
	if (!mkdir($destDir, 0755, true)) {
	  error_log("Failed to create destination directory: " . $destDir);
	  imagedestroy($image);
	  if ($needsResizing) imagedestroy($newImage);
	  return false;
	}
  }

  $result = false;
  $webpResult = false;
  
  if (!$doWebPConversion) {
	switch ($imageType) {
	  case IMAGETYPE_JPEG:
		$result = imagejpeg($newImage, $destinationFile, $quality);
		break;
	  case IMAGETYPE_PNG:
		$pngQuality = (int)floor((100 - min($quality, 100)) / 10);
		$result = imagepng($newImage, $destinationFile, $pngQuality);
		break;
	  case IMAGETYPE_GIF:
		$result = imagegif($newImage, $destinationFile);
		break;
	  case IMAGETYPE_WEBP:
		if (function_exists('imagewebp')) {
		  $result = imagewebp($newImage, $destinationFile, $quality);
		}
		break;
	}
  }

  if ($convertToWebP && function_exists('imagewebp')) {
	$webpResult = imagewebp($newImage, $destinationFile, $quality);
	error_log("WebP conversion result: " . ($webpResult ? "success" : "failed"));
  }
  
  // Add this line to better track the result
  $finalResult = $result || $webpResult;
  error_log("Final optimization result: " . ($finalResult ? "success" : "failed"));

	if ($createThumbnail && !empty($thumbnailPath)) {

	  $thumbDir = dirname($thumbnailPath);
	  if (!file_exists($thumbDir)) {
		if (!mkdir($thumbDir, 0755, true)) {

		}
	  }

	if (file_exists($thumbDir)) {

	  if ($doWebPConversion && pathinfo($thumbnailPath, PATHINFO_EXTENSION) !== 'webp') {
		$thumbnailPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $thumbnailPath);
	  }

	  //$thumbResult = createThumbnail($image, $thumbnailPath, $thumbWidth, $thumbHeight, $imageType, $quality, $doWebPConversion && !$keepOriginalFormat);
	  $thumbResult = createThumbnail($image, $thumbnailPath, $thumbWidth, $thumbHeight, $imageType, $quality, $doWebPConversion);

	}
  }

  imagedestroy($image);
  if ($needsResizing) imagedestroy($newImage);

  if ($deleteOriginal && ($result || $webpResult)) {
	@unlink($sourceFile);
  }

  return $result || $webpResult;
}

/**
 * Helper function to calculate dimensions while maintaining aspect ratio
 */
function calculateDimensions($width, $height, $maxWidth, $maxHeight) {

  $width = max(1, intval($width));
  $height = max(1, intval($height));
  $maxWidth = max(1, intval($maxWidth));
  $maxHeight = max(1, intval($maxHeight));

  $newWidth = $width;
  $newHeight = $height;

  $ratio = $width / $height;

  if ($width > $maxWidth || $height > $maxHeight) {
	if ($ratio > 1) {

	  if ($width > $maxWidth) {
		$newWidth = $maxWidth;
		$newHeight = round($maxWidth / $ratio);
	  }

	  if ($newHeight > $maxHeight) {
		$newHeight = $maxHeight;
		$newWidth = round($maxHeight * $ratio);
	  }
	} else {

	  if ($height > $maxHeight) {
		$newHeight = $maxHeight;
		$newWidth = round($maxHeight * $ratio);
	  }

	  if ($newWidth > $maxWidth) {
		$newWidth = $maxWidth;
		$newHeight = round($maxWidth / $ratio);
	  }
	}
  }

  $newWidth = max(1, $newWidth);
  $newHeight = max(1, $newHeight);

  error_log("Original dimensions: {$width}x{$height}, New dimensions: {$newWidth}x{$newHeight}");
  return [$newWidth, $newHeight];
}

/**
  * Create thumbnail from an image
  */
 function createThumbnail($sourceImage, $thumbnailPath, $width, $height, $imageType, $quality = 85, $outputWebP = false) {

   $srcWidth = imagesx($sourceImage);
   $srcHeight = imagesy($sourceImage);

   $ratio = $srcWidth / $srcHeight;

   if ($width / $height > $ratio) {
	 $newWidth = $height * $ratio;
	 $newHeight = $height;
   } else {
	 $newHeight = $width / $ratio;
	 $newWidth = $width;
   }

   $newWidth = round($newWidth);
   $newHeight = round($newHeight);

   $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

   error_log("Creating thumbnail: dimensions " . $newWidth . "x" . $newHeight . ", path: " . $thumbnailPath);

   if ($imageType === IMAGETYPE_PNG) {
	 imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
	 imagealphablending($thumbnail, false);
	 imagesavealpha($thumbnail, true);
   }

   $resizeResult = imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
   if (!$resizeResult) {
	 error_log("Failed to resize image for thumbnail");
	 return false;
   }

   $result = false;

   if ($outputWebP && function_exists('imagewebp')) {
	 $result = imagewebp($thumbnail, $thumbnailPath, $quality);
	 error_log("Created WebP thumbnail: " . ($result ? "success" : "failed") . " path: " . $thumbnailPath);
   } else {

	 switch ($imageType) {
	   case IMAGETYPE_JPEG:
		 $result = imagejpeg($thumbnail, $thumbnailPath, $quality);
		 error_log("Created JPEG thumbnail: " . ($result ? "success" : "failed") . " path: " . $thumbnailPath);
		 break;
	   case IMAGETYPE_PNG:

		 $pngQuality = (int)floor((100 - min($quality, 100)) / 10);
		 $result = imagepng($thumbnail, $thumbnailPath, $pngQuality);
		 error_log("Created PNG thumbnail: " . ($result ? "success" : "failed") . " path: " . $thumbnailPath);
		 break;
	   case IMAGETYPE_GIF:
		 $result = imagegif($thumbnail, $thumbnailPath);
		 error_log("Created GIF thumbnail: " . ($result ? "success" : "failed") . " path: " . $thumbnailPath);
		 break;
	   case IMAGETYPE_WEBP:
		 if (function_exists('imagewebp')) {
		   $result = imagewebp($thumbnail, $thumbnailPath, $quality);
		   error_log("Created WebP thumbnail: " . ($result ? "success" : "failed") . " path: " . $thumbnailPath);
		 }
		 break;
	 }
   }

   imagedestroy($thumbnail);

   return $result;
 }

/**
  * Create thumbnails directory if it doesn't exist
  */
  function ensureThumbnailsDir($path) {
	  if (empty($path)) {
		  error_log("Empty path provided to ensureThumbnailsDir");
		  return false;
	  }

	  $thumbsPath = rtrim($path, '/') . '/thumbs';

	  if (!file_exists($thumbsPath)) {

		  $parentDir = dirname($thumbsPath);
		  if (!is_writable($parentDir)) {
			  error_log("Parent directory is not writable: " . $parentDir);

			  @chmod($parentDir, 0755);
			  if (!is_writable($parentDir)) {
				  error_log("Failed to make parent directory writable");
				  return false;
			  }
		  }

		  if (!@mkdir($thumbsPath, 0755, true)) {
			  $error = error_get_last();
			  error_log("Failed to create thumbnails directory: " . $thumbsPath . " - Error: " . ($error ? $error['message'] : 'Unknown error'));
			  return false;
		  }
	  }

	  return $thumbsPath;
  }

/**
 * Check if WebP conversion is supported
 */
function isWebPSupported() {

  if (!extension_loaded('gd')) {
	return false;
  }

  if (!function_exists('imagewebp')) {
	return false;
  }

  $gdInfo = gd_info();
  if (isset($gdInfo['GD Version'])) {

	preg_match('/\d+\.\d+\.\d+/', $gdInfo['GD Version'], $matches);
	if (isset($matches[0])) {
	  $version = $matches[0];

	  return version_compare($version, '2.2.0', '>=');
	}
  }

  return true;
}