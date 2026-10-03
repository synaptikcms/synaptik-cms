<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function theme_editor_allowed_extensions(): array {
	return ['php', 'css', 'js', 'json'];
}

function theme_editor_scan_files(string $themeDir): array {
	$allowed = theme_editor_allowed_extensions();
	$groups  = [];

	if (!is_dir($themeDir)) {
		return $groups;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $fileInfo) {
		if ($fileInfo->isDir()) continue;

		$filename = $fileInfo->getFilename();
		if ($filename[0] === '.') continue;

		$ext = strtolower($fileInfo->getExtension());
		if (!in_array($ext, $allowed, true)) continue;

		$relativePath = substr($fileInfo->getPathname(), strlen($themeDir) + 1);
		$relativePath = str_replace('\\', '/', $relativePath);

		$folder = dirname($relativePath);
		$group  = ($folder === '.') ? '' : $folder . '/';

		$groups[$group][$relativePath] = $relativePath;
	}
	$rootGroup = $groups[''] ?? [];
	unset($groups['']);
	ksort($groups, SORT_NATURAL);
	foreach ($groups as &$files) {
		ksort($files, SORT_NATURAL);
	}
	unset($files);
	ksort($rootGroup, SORT_NATURAL);

	return ($rootGroup ? ['' => $rootGroup] : []) + $groups;
}

function theme_editor_resolve_path(string $themeDir, string $requestedFile): ?string {
	if ($requestedFile === '') return null;

	$ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
	if (!in_array($ext, theme_editor_allowed_extensions(), true)) return null;

	$themeReal = realpath($themeDir);
	if ($themeReal === false) return null;

	$candidate = $themeDir . '/' . $requestedFile;
	$candidateReal = realpath($candidate);
	if ($candidateReal === false) return null;
	if (strpos($candidateReal, $themeReal . DIRECTORY_SEPARATOR) !== 0) return null;
	return $candidateReal;
}

function theme_editor_sanitize_relative_path(string $requestedFile): ?string {
	if ($requestedFile === '' || strpos($requestedFile, "\0") !== false) return null;

	$requestedFile = str_replace('\\', '/', $requestedFile);
	if ($requestedFile[0] === '/') return null;

	foreach (explode('/', $requestedFile) as $part) {
		if ($part === '' || $part === '.' || $part === '..') return null;
	}

	$ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
	if (!in_array($ext, theme_editor_allowed_extensions(), true)) return null;

	return $requestedFile;
}

function theme_editor_resolve_write_target(string $childThemeDir, string $requestedFile): ?string {
	$relativePath = theme_editor_sanitize_relative_path($requestedFile);
	if ($relativePath === null) return null;

	$childThemeDir = rtrim($childThemeDir, '/');
	$subDir        = dirname($relativePath);
	$targetDir     = ($subDir === '.') ? $childThemeDir : $childThemeDir . '/' . $subDir;

	if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
		return null;
	}

	$childReal  = realpath($childThemeDir);
	$targetReal = realpath($targetDir);
	if ($childReal === false || $targetReal === false) return null;
	if ($targetReal !== $childReal && strpos($targetReal, $childReal . DIRECTORY_SEPARATOR) !== 0) {
		return null;
	}

	return $targetReal . '/' . basename($relativePath);
}

function theme_editor_scan_theme_files(string $parentThemeDir, string $childThemeDir): array {
	$merged = theme_editor_scan_files($parentThemeDir);

	if (is_dir($childThemeDir)) {
		foreach (theme_editor_scan_files($childThemeDir) as $group => $files) {
			foreach ($files as $relPath) {
				$merged[$group][$relPath] = $relPath;
			}
		}
	}

	foreach ($merged as $group => $files) {
		ksort($merged[$group], SORT_NATURAL);
	}

	return $merged;
}

function theme_editor_is_overridden(string $childThemeDir, string $requestedFile): bool {
	$relativePath = theme_editor_sanitize_relative_path($requestedFile);
	if ($relativePath === null) return false;
	return is_file(rtrim($childThemeDir, '/') . '/' . $relativePath);
}

function theme_editor_cleanup_empty_dirs(string $childThemeDir, string $relativeDir): void {
	$childReal = realpath($childThemeDir);
	if ($childReal === false || $relativeDir === '.' || $relativeDir === '') return;

	$dir = rtrim($childThemeDir, '/') . '/' . $relativeDir;
	while (true) {
		$dirReal = realpath($dir);
		if ($dirReal === false || $dirReal === $childReal) break;
		if (strpos($dirReal, $childReal . DIRECTORY_SEPARATOR) !== 0) break;

		$contents = @scandir($dirReal);
		if ($contents === false || count(array_diff($contents, ['.', '..'])) > 0) break;
		if (!@rmdir($dirReal)) break;

		$dir = dirname($dirReal);
	}
}

function theme_editor_prune_if_identical(string $childThemeDir, string $parentThemeDir, string $requestedFile): bool {
	$relativePath = theme_editor_sanitize_relative_path($requestedFile);
	if ($relativePath === null) return false;

	$childFile  = theme_editor_resolve_path($childThemeDir, $relativePath);
	$parentFile = theme_editor_resolve_path($parentThemeDir, $relativePath);
	if ($childFile === null || $parentFile === null) return false;

	if (filesize($childFile) !== filesize($parentFile)) return false;
	if (md5_file($childFile) !== md5_file($parentFile)) return false;
	if (!@unlink($childFile)) return false;

	theme_editor_cleanup_empty_dirs($childThemeDir, dirname($relativePath));
	return true;
}

