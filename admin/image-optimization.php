<?php
/**
 * Image optimization helpers for SynaptikCMS.
 */

function optimizeImage($sourceFile, $destinationFile, $maxWidth = 1920, $maxHeight = 1080, $quality = 85, $createThumbnail = false, $thumbnailPath = '', $thumbWidth = 300, $thumbHeight = 300, $deleteOriginal = false, $convertToWebP = false) {
	if (!file_exists($sourceFile)) return false;

	$destDir = dirname($destinationFile);
	if (!is_writable($destDir)) return false;

	if (!extension_loaded('gd')) return false;

	$webpSupported    = function_exists('imagewebp');
	$doWebPConversion = $convertToWebP && $webpSupported;

	$imageInfo = getimagesize($sourceFile);
	if ($imageInfo === false) {
		$copyResult = copy($sourceFile, $destinationFile);
		if ($deleteOriginal && $copyResult) @unlink($sourceFile);
		return $copyResult;
	}

	$imageWidth  = $imageInfo[0];
	$imageHeight = $imageInfo[1];
	$imageType   = $imageInfo[2];

	$destinationExtension = strtolower(pathinfo($destinationFile, PATHINFO_EXTENSION));
	if ($doWebPConversion && $destinationExtension !== 'webp') {
		$destinationFile = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $destinationFile);
	}

	if ($imageWidth <= $maxWidth && $imageHeight <= $maxHeight && $quality >= 100 && !$convertToWebP && !$createThumbnail) {
		$copyResult = copy($sourceFile, $destinationFile);
		if ($deleteOriginal && $copyResult) @unlink($sourceFile);
		return $copyResult;
	}

	$image = null;
	switch ($imageType) {
		case IMAGETYPE_JPEG: $image = @imagecreatefromjpeg($sourceFile); break;
		case IMAGETYPE_PNG:  $image = @imagecreatefrompng($sourceFile);  break;
		case IMAGETYPE_GIF:  $image = @imagecreatefromgif($sourceFile);  break;
		case IMAGETYPE_WEBP:
			if (function_exists('imagecreatefromwebp')) $image = @imagecreatefromwebp($sourceFile);
			break;
		default:
			$copyResult = copy($sourceFile, $destinationFile);
			if ($deleteOriginal && $copyResult) @unlink($sourceFile);
			return $copyResult;
	}

	if (!$image) {
		$copyResult = copy($sourceFile, $destinationFile);
		if ($deleteOriginal && $copyResult) @unlink($sourceFile);
		return $copyResult;
	}

	[$newWidth, $newHeight] = calculateDimensions($imageWidth, $imageHeight, $maxWidth, $maxHeight);

	$needsResizing = ($newWidth !== $imageWidth || $newHeight !== $imageHeight);

	if ($needsResizing) {
		$newImage = imagecreatetruecolor($newWidth, $newHeight);
		if (!$newImage) {
			imagedestroy($image);
			$copyResult = copy($sourceFile, $destinationFile);
			if ($deleteOriginal && $copyResult) @unlink($sourceFile);
			return $copyResult;
		}
		if ($imageType === IMAGETYPE_PNG) {
			imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
			imagealphablending($newImage, false);
			imagesavealpha($newImage, true);
		}
		$success = imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $imageWidth, $imageHeight);
		if (!$success) {
			imagedestroy($image);
			imagedestroy($newImage);
			$copyResult = copy($sourceFile, $destinationFile);
			if ($deleteOriginal && $copyResult) @unlink($sourceFile);
			return $copyResult;
		}
	} else {
		$newImage = $image;
	}

	if (!file_exists(dirname($destinationFile))) {
		if (!mkdir(dirname($destinationFile), 0755, true)) {
			imagedestroy($image);
			if ($needsResizing) imagedestroy($newImage);
			return false;
		}
	}

	$result    = false;
	$webpResult = false;

	if (!$doWebPConversion) {
		switch ($imageType) {
			case IMAGETYPE_JPEG: $result = imagejpeg($newImage, $destinationFile, $quality); break;
			case IMAGETYPE_PNG:
				$pngQuality = (int)floor((100 - min($quality, 100)) / 10);
				$result = imagepng($newImage, $destinationFile, $pngQuality);
				break;
			case IMAGETYPE_GIF:  $result = imagegif($newImage, $destinationFile);  break;
			case IMAGETYPE_WEBP:
				if (function_exists('imagewebp')) $result = imagewebp($newImage, $destinationFile, $quality);
				break;
		}
	}

	if ($convertToWebP && function_exists('imagewebp')) {
		$webpResult = imagewebp($newImage, $destinationFile, $quality);
	}

	if ($createThumbnail && !empty($thumbnailPath)) {
		$thumbDir = dirname($thumbnailPath);
		if (!file_exists($thumbDir)) @mkdir($thumbDir, 0755, true);
		if (file_exists($thumbDir)) {
			if ($doWebPConversion && pathinfo($thumbnailPath, PATHINFO_EXTENSION) !== 'webp') {
				$thumbnailPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $thumbnailPath);
			}
			createThumbnail($image, $thumbnailPath, $thumbWidth, $thumbHeight, $imageType, $quality, $doWebPConversion);
		}
	}

	imagedestroy($image);
	if ($needsResizing) imagedestroy($newImage);

	if ($deleteOriginal && ($result || $webpResult)) @unlink($sourceFile);

	return $result || $webpResult;
}

function calculateDimensions($width, $height, $maxWidth, $maxHeight) {
	$width     = max(1, (int)$width);
	$height    = max(1, (int)$height);
	$maxWidth  = max(1, (int)$maxWidth);
	$maxHeight = max(1, (int)$maxHeight);

	$newWidth  = $width;
	$newHeight = $height;
	$ratio     = $width / $height;

	if ($width > $maxWidth || $height > $maxHeight) {
		if ($ratio > 1) {
			if ($width > $maxWidth) { $newWidth = $maxWidth; $newHeight = round($maxWidth / $ratio); }
			if ($newHeight > $maxHeight) { $newHeight = $maxHeight; $newWidth = round($maxHeight * $ratio); }
		} else {
			if ($height > $maxHeight) { $newHeight = $maxHeight; $newWidth = round($maxHeight * $ratio); }
			if ($newWidth > $maxWidth) { $newWidth = $maxWidth; $newHeight = round($maxWidth / $ratio); }
		}
	}

	$newWidth  = max(1, (int)$newWidth);
	$newHeight = max(1, (int)$newHeight);

	return [$newWidth, $newHeight];
}

function createThumbnail($sourceImage, $thumbnailPath, $width, $height, $imageType, $quality = 85, $outputWebP = false) {
	$srcWidth  = imagesx($sourceImage);
	$srcHeight = imagesy($sourceImage);
	$ratio     = $srcWidth / $srcHeight;

	if ($width / $height > $ratio) {
		$newWidth  = (int)round($height * $ratio);
		$newHeight = $height;
	} else {
		$newWidth  = $width;
		$newHeight = (int)round($width / $ratio);
	}

	$thumbnail = imagecreatetruecolor($newWidth, $newHeight);

	if ($imageType === IMAGETYPE_PNG) {
		imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
		imagealphablending($thumbnail, false);
		imagesavealpha($thumbnail, true);
	}

	if (!imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight)) {
		return false;
	}

	$result = false;
	if ($outputWebP && function_exists('imagewebp')) {
		$result = imagewebp($thumbnail, $thumbnailPath, $quality);
	} else {
		switch ($imageType) {
			case IMAGETYPE_JPEG: $result = imagejpeg($thumbnail, $thumbnailPath, $quality); break;
			case IMAGETYPE_PNG:
				$pngQuality = (int)floor((100 - min($quality, 100)) / 10);
				$result = imagepng($thumbnail, $thumbnailPath, $pngQuality);
				break;
			case IMAGETYPE_GIF:  $result = imagegif($thumbnail, $thumbnailPath); break;
			case IMAGETYPE_WEBP:
				if (function_exists('imagewebp')) $result = imagewebp($thumbnail, $thumbnailPath, $quality);
				break;
		}
	}

	imagedestroy($thumbnail);
	return $result;
}

function ensureThumbnailsDir($path) {
	if (empty($path)) return false;

	$thumbsPath = rtrim($path, '/') . '/thumbs';
	if (!file_exists($thumbsPath)) {
		$parentDir = dirname($thumbsPath);
		if (!is_writable($parentDir)) {
			@chmod($parentDir, 0755);
			if (!is_writable($parentDir)) return false;
		}
		if (!@mkdir($thumbsPath, 0755, true)) return false;
	}

	return $thumbsPath;
}

function isWebPSupported() {
	if (!extension_loaded('gd') || !function_exists('imagewebp')) return false;
	$gdInfo = gd_info();
	if (isset($gdInfo['GD Version'])) {
		preg_match('/\d+\.\d+\.\d+/', $gdInfo['GD Version'], $matches);
		if (isset($matches[0])) return version_compare($matches[0], '2.2.0', '>=');
	}
	return true;
}
