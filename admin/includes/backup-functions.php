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
		if (file_exists($root . '/config.json')) $zip->addFile($root . '/config.json', 'config.json');
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
 * Recursively delete directory contents.
 *
 * @param string $dir           Directory to empty (the directory itself is left in place).
 * @param bool   $preserveHtaccess  When true (default), .htaccess files are left untouched —
 *                                   used when clearing a live directory (data/, bckps/) that
 *                                   must keep its access protection. Pass false to wipe a
 *                                   directory completely, e.g. a temporary extraction folder
 *                                   that must be removable afterwards regardless of what the
 *                                   extracted release ZIP happened to contain.
 */
if (!function_exists('_backup_clear_dir')) {
	function _backup_clear_dir(string $dir, bool $preserveHtaccess = true): void
	{
		if (!is_dir($dir)) return;
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			if ($preserveHtaccess && basename($f->getPathname()) === '.htaccess') continue;
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

/**
 * Recursively delete a directory and all its contents, including .htaccess.
 * Unlike _backup_clear_dir(), this removes everything and the directory
 * itself — used for temporary extraction dirs (update, restore), which
 * must be fully wiped rather than just cleared.
 */
if (!function_exists('_backup_remove_dir')) {
	function _backup_remove_dir(string $dir): void
	{
		if (!is_dir($dir)) return;
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			$f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
		}
		@rmdir($dir);
	}
}

/**
 * Builds a ZIP of the program files an update actually overwrites — core/,
 * the (possibly renamed) admin folder minus its runtime subdirs, and
 * index.php. The regular safety backup (_backup_build_zip) only covers
 * data/files/config, which is exactly what an update does NOT touch, so it
 * cannot be used to roll back a failed update. This is the second,
 * narrower archive that makes a rollback possible.
 *
 * @param  string $root             Absolute path to the site root.
 * @param  string $zipPath          Absolute path to write the ZIP to.
 * @param  string $adminFolderName  The current (possibly renamed) admin folder name.
 * @return bool
 */
if (!function_exists('_backup_build_core_zip')) {
	function _backup_build_core_zip(string $root, string $zipPath, string $adminFolderName): bool
	{
		if (!class_exists('ZipArchive')) return false;
		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;

		if (file_exists($root . '/index.php')) $zip->addFile($root . '/index.php', 'index.php');

		$addDir = function(string $absDir, string $prefix, array $skipRelPrefixes = []) use ($zip): void {
			if (!is_dir($absDir)) return;
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($absDir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ($it as $f) {
				$subPath = str_replace('\\', '/', $it->getSubPathname());
				foreach ($skipRelPrefixes as $skip) {
					// Match both the skipped dir itself ("cache") and its
					// contents ("cache/x.json") — trailing-slash-only prefix
					// matching would let the empty directory entry through.
					$bareSkip = rtrim($skip, '/');
					if ($subPath === $bareSkip || strpos($subPath, $skip) === 0) continue 2;
				}
				$rel = $prefix . '/' . $subPath;
				$f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getRealPath(), $rel);
			}
		};

		$addDir($root . '/core', 'core');
		// Skip the cache subdir — it's regenerated locally, not user data.
		// Drafts live under data/ now (included via $addDir($root . '/data', ...)
		// above), not admin/ — they're backed up like any other content.
		$addDir($root . '/' . $adminFolderName, 'admin', ['cache/']);

		$zip->close();
		return file_exists($zipPath) && filesize($zipPath) > 0;
	}
}
