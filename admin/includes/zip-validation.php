<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Shared ZIP entry validation for all archive-processing paths.
 *
 * Called before extractTo() in:
 *   - extension-upload.php        (manual theme/plugin upload)
 *   - extension-update-functions.php  (automatic remote update)
 *   - backup.php restore path     (uploaded backup archive)
 *
 * Checks performed on every entry:
 *   1. Path traversal: ".." segments, null bytes, absolute paths
 *   2. Extension whitelist (configurable per caller)
 *   3. .htaccess / .user.ini allowed only under $allowedHtaccessPrefixes
 *
 * @param ZipArchive $zip                    Open ZipArchive instance (not yet extracted).
 * @param array      $allowedExt             Lowercase extensions permitted (without leading dot).
 * @param array      $allowedHtaccessPrefixes Path prefixes under which .htaccess is permitted.
 *                                            []        = never allowed (themes)
 *                                            ['']      = allowed anywhere (plugins)
 *                                            ['data/', 'files/', 'private/'] = backup archives
 * @return array{ok: bool, error: string}  ok=true means the archive is safe to extract.
 */
function zip_validate_entries(ZipArchive $zip, array $allowedExt, array $allowedHtaccessPrefixes = []): array
{
	$_t  = function_exists('__t')  ? '__t'  : fn($k, $fb = '') => $fb;
	$_hsc = function_exists('hsc') ? 'hsc' : fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

	for ($i = 0; $i < $zip->numFiles; $i++) {
		$entry = $zip->getNameIndex($i);
		if (!is_string($entry) || $entry === '') {
			return ['ok' => false, 'error' => $_t('zip_val_traversal', 'Unsafe path in archive.')];
		}

		// Null byte injection
		if (strpos($entry, "\x00") !== false) {
			return ['ok' => false, 'error' => sprintf(
				$_t('zip_val_traversal', 'Unsafe path in archive: "%s".'),
				$_hsc($entry)
			)];
		}

		// Normalise and check for path traversal or absolute paths.
		// realpath() is not usable here (file is not extracted yet), so we
		// resolve every ".." segment and reject if the result escapes root.
		$clean = '';
		foreach (explode('/', str_replace('\\', '/', $entry)) as $seg) {
			if ($seg === '' || $seg === '.') continue;
			if ($seg === '..') {
				if ($clean === '') {
					return ['ok' => false, 'error' => sprintf(
						$_t('zip_val_traversal', 'Unsafe path in archive: "%s".'),
						$_hsc($entry)
					)];
				}
				$clean = dirname($clean) === '.' ? '' : dirname($clean);
			} else {
				$clean = $clean === '' ? $seg : $clean . '/' . $seg;
			}
		}
		// Absolute path (starts with / or a Windows drive letter)
		if ($entry[0] === '/' || (strlen($entry) > 1 && $entry[1] === ':')) {
			return ['ok' => false, 'error' => sprintf(
				$_t('zip_val_traversal', 'Unsafe path in archive: "%s".'),
				$_hsc($entry)
			)];
		}

		// Skip macOS metadata and directory entries
		if (strpos($entry, '__MACOSX/') === 0 || basename($entry) === '.DS_Store') continue;
		if (substr($entry, -1) === '/') continue;

		$baseName = basename($entry);

		// .htaccess / .user.ini — allowed only under permitted path prefixes
		if (in_array($baseName, ['.htaccess', '.user.ini'], true)) {
			$allowed = false;
			foreach ($allowedHtaccessPrefixes as $prefix) {
				if ($prefix === '' || strpos($clean, $prefix) === 0) {
					$allowed = true;
					break;
				}
			}
			if (!$allowed) {
				return ['ok' => false, 'error' => sprintf(
					$_t('zip_val_htaccess', 'Forbidden file in archive: "%s".'),
					$_hsc($entry)
				)];
			}
			continue;
		}

		// Strip trailing dots/spaces (prevents shell.php. bypass), then split on dot.
		// Strategy: the LAST segment is checked against the allow-list;
		// intermediate segments are checked against a deny-list of executable types.
		// This lets jquery.min.js pass while shell.php.jpg is still rejected.
		static $dangerous = ['php','php3','php4','php5','php7','php8','phtml','phps','phar',
							 'cgi','pl','py','rb','sh','bash','exe','htaccess','htpasswd'];

		$base  = rtrim(basename($entry), ". \t");
		$parts = explode('.', strtolower($base));
		array_shift($parts); // remove filename stem
		if ($parts === []) continue; // genuinely extensionless file

		$last = array_pop($parts); // final extension — checked against allow-list
		if (!in_array($last, $allowedExt, true)) {
			return ['ok' => false, 'error' => sprintf(
				$_t('zip_val_ext_forbidden', 'Forbidden extension ".%s" in archive (file: "%s").'),
				$_hsc($last),
				$_hsc($entry)
			)];
		}
		// Intermediate segments (e.g. ".min" in jquery.min.js) — deny-list only
		foreach ($parts as $seg) {
			if (in_array($seg, $dangerous, true)) {
				return ['ok' => false, 'error' => sprintf(
					$_t('zip_val_ext_forbidden', 'Forbidden extension ".%s" in archive (file: "%s").'),
					$_hsc($seg),
					$_hsc($entry)
				)];
			}
		}
	}

	return ['ok' => true, 'error' => ''];
}

/**
 * Validate that a download URL belongs to an explicitly allowed host.
 *
 * Prevents a compromised or malicious registry from pointing download_url
 * at an arbitrary server. Only the official SynaptikCMS update host and
 * GitHub raw content are permitted.
 *
 * @param string $url  The download_url value from extensions.json.
 * @return bool
 */
function zip_url_is_allowed(string $url): bool
{
	$allowed = [
		'raw.githubusercontent.com',
		'github.com',
		'synaptikcms.com',
		'releases.synaptikcms.com',
	];

	$host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
	if ($host === '') return false;

	foreach ($allowed as $a) {
		// Exact match or subdomain match (e.g. "releases.synaptikcms.com")
		$suffix = '.' . $a;
		if ($host === $a || substr($host, -strlen($suffix)) === $suffix) return true;
	}

	return false;
}
