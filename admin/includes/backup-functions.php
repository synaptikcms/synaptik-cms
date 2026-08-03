<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Build a full ZIP backup of the current site state (data, files, settings).
 * Shared between the backup download handler, restore safety backup, and pre-update safety backup.
 */
if (!function_exists('_backup_build_zip')) {
	function _backup_build_zip(string $root, string $zipPath): bool
	{
		if (!class_exists('ZipArchive')) return false;
		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
		if (file_exists($root . '/settings.json')) $zip->addFile($root . '/settings.json', 'settings.json');
		if (file_exists($root . '/version.json'))  $zip->addFile($root . '/version.json',  'version.json');
		$addDir = function(string $absDir, string $prefix) use ($zip): void {
			if (!is_dir($absDir)) return;
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ($it as $f) {
				$rel = $prefix . '/' . str_replace('\\', '/', $it->getSubPathname());
				$f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getRealPath(), $rel);
			}
		};
		$addDir($root . '/data',  'data');
		$addDir($root . '/files', 'files');
		$zip->close();
		return file_exists($zipPath) && filesize($zipPath) > 0;
	}
}

/**
 * Recursively delete directory contents, preserving .htaccess files.
 */
if (!function_exists('_backup_clear_dir')) {
	function _backup_clear_dir(string $dir): void
	{
		if (!is_dir($dir)) return;
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			if (basename($f->getPathname()) === '.htaccess') continue;
			$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
		}
	}
}

/**
 * Recursively copy a directory tree.
 */
if (!function_exists('_backup_copy_dir')) {
	function _backup_copy_dir(string $src, string $dst): bool
	{
		if (!is_dir($src)) return true;
		if (!is_dir($dst)) mkdir($dst, 0755, true);
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ($it as $f) {
			$target = $dst . DIRECTORY_SEPARATOR . $it->getSubPathname();
			if ($f->isDir()) {
				if (!is_dir($target)) mkdir($target, 0755, true);
			} else {
				if (!copy($f->getRealPath(), $target)) return false;
			}
		}
		return true;
	}
}
