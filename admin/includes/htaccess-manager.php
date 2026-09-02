<?php
if (!defined('INCLUDED')) {
	http_response_code(403);
	exit;
}

const SL_HTACCESS_BEGIN        = '# --- BEGIN CUSTOM HTACCESS (managed via Admin > Settings — edits here are overwritten on save) ---';
const SL_HTACCESS_END          = '# --- END CUSTOM HTACCESS ---';
const SL_HTACCESS_MAX_LEN      = 20000;
const SL_HTACCESS_BACKUPS_KEEP = 5;
const SL_HTACCESS_PROBE_TIMEOUT = 5;

function sl_htaccess_root_path(): string {
	return dirname(__DIR__, 2) . '/.htaccess';
}

function sl_htaccess_custom_store_path(): string {
	return dirname(__DIR__, 2) . '/private/htaccess-custom.json';
}

function sl_htaccess_backups_dir(): string {
	return dirname(__DIR__, 2) . '/private/htaccess-backups';
}

function sl_htaccess_load_custom(): string {
	$path = sl_htaccess_custom_store_path();
	if (!is_file($path)) return '';
	$data = json_decode((string) file_get_contents($path), true);
	return is_array($data) && isset($data['rules']) ? (string) $data['rules'] : '';
}

function sl_htaccess_validate(string $rules): ?string {
	if (strlen($rules) > SL_HTACCESS_MAX_LEN) {
		return __t('htaccess_error_too_long', 'These rules are too long.');
	}
	if (strpos($rules, "\0") !== false) {
		return __t('htaccess_error_invalid_chars', 'These rules contain invalid characters.');
	}
	if (strpos($rules, SL_HTACCESS_BEGIN) !== false || strpos($rules, SL_HTACCESS_END) !== false) {
		return __t('htaccess_error_marker', 'These rules cannot contain the managed-block markers.');
	}
	if (preg_match('/\b(AddHandler|SetHandler|Action)\b|\bphp_admin_(value|flag)\b|<Directory\b|<VirtualHost\b|\bAllowOverride\b/i', $rules)) {
		return __t('htaccess_error_denylist', 'These rules contain a directive that is blocked here for safety (handlers, php_admin_*, <Directory>, <VirtualHost>, AllowOverride). Add it through FTP if you really need it.');
	}

	$openTags = [];
	if (preg_match_all('/<([A-Za-z]+)(?:\s[^>]*)?>/', $rules, $opens)) {
		foreach ($opens[1] as $tag) {
			$openTags[] = strtolower($tag);
		}
	}
	$closeTags = [];
	if (preg_match_all('#</([A-Za-z]+)>#', $rules, $closes)) {
		foreach ($closes[1] as $tag) {
			$closeTags[] = strtolower($tag);
		}
	}
	sort($openTags);
	sort($closeTags);
	if ($openTags !== $closeTags) {
		return __t('htaccess_error_unbalanced', 'These rules have an unclosed tag (e.g. <IfModule> or <Files>) — check that every opening tag has a matching closing tag.');
	}

	return null;
}

function sl_htaccess_backup(string $currentContent): void {
	$dir = sl_htaccess_backups_dir();
	if (!is_dir($dir)) {
		mkdir($dir, 0755, true);
		file_put_contents(
			$dir . '/.htaccess',
			"<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
		);
	}
	file_put_contents($dir . '/htaccess-' . date('Ymd-His') . '.bak', $currentContent, LOCK_EX);

	$backups = glob($dir . '/htaccess-*.bak') ?: [];
	sort($backups);
	while (count($backups) > SL_HTACCESS_BACKUPS_KEEP) {
		@unlink(array_shift($backups));
	}
}

function sl_htaccess_latest_backup(): ?string {
	$backups = glob(sl_htaccess_backups_dir() . '/htaccess-*.bak') ?: [];
	if (!$backups) return null;
	sort($backups);
	return end($backups);
}

function sl_htaccess_write(string $path, string $content): bool {
	$tmp = $path . '.tmp';
	if (file_put_contents($tmp, $content, LOCK_EX) === false) return false;
	if (!rename($tmp, $path)) {
		@unlink($tmp);
		return false;
	}
	return true;
}

function sl_htaccess_store_rules(string $rules): void {
	$json = json_encode(['rules' => $rules], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) return;
	$path = sl_htaccess_custom_store_path();
	$tmp  = $path . '.tmp';
	if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
		rename($tmp, $path);
	}
}

function sl_htaccess_probe(): ?int {
	if (!function_exists('admin_site_url')) return null;
	$url = admin_site_url();
	if (!is_string($url) || $url === '') return null;

	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => SL_HTACCESS_PROBE_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => SL_HTACCESS_PROBE_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		]);
		curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		return $code > 0 ? $code : null;
	}

	$ctx = stream_context_create(['http' => [
		'method'          => 'HEAD',
		'timeout'         => SL_HTACCESS_PROBE_TIMEOUT,
		'follow_location' => 0,
		'ignore_errors'   => true,
	], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

	$headers = @get_headers($url, false, $ctx);
	if (!is_array($headers) || !isset($headers[0])) return null;
	return preg_match('#^HTTP/[\d.]+\s+(\d{3})#', $headers[0], $m) ? (int) $m[1] : null;
}

function sl_htaccess_apply(string $rules): array {
	$error = sl_htaccess_validate($rules);
	if ($error !== null) {
		return ['ok' => false, 'error' => $error];
	}

	$path    = sl_htaccess_root_path();
	$current = is_file($path) ? file_get_contents($path) : false;
	if ($current === false) {
		return ['ok' => false, 'error' => __t('htaccess_error_missing_file', 'No .htaccess file was found at the site root.')];
	}

	$rules = trim($rules);
	$block = SL_HTACCESS_BEGIN . "\n" . $rules . ($rules !== '' ? "\n" : '') . SL_HTACCESS_END;

	$pattern = '/' . preg_quote(SL_HTACCESS_BEGIN, '/') . '.*?' . preg_quote(SL_HTACCESS_END, '/') . '/s';
	if (preg_match($pattern, $current)) {
		$updated = preg_replace($pattern, $block, $current, 1);
	} elseif (preg_match('/^RewriteEngine\s+On\s*$/mi', $current, $m, PREG_OFFSET_CAPTURE)) {
		$insertAt = $m[0][1] + strlen($m[0][0]);
		$updated  = substr($current, 0, $insertAt) . "\n\n" . $block . substr($current, $insertAt);
	} else {
		$updated = $block . "\n\n" . $current;
	}

	sl_htaccess_backup($current);

	if (!sl_htaccess_write($path, $updated)) {
		return ['ok' => false, 'error' => __t('htaccess_error_write_failed', 'Could not write the .htaccess file.')];
	}

	$status = sl_htaccess_probe();
	if ($status !== null && $status >= 500) {
		sl_htaccess_write($path, $current);
		return ['ok' => false, 'error' => sprintf(__t('htaccess_error_broke_site'), $status)];
	}

	sl_htaccess_store_rules($rules);

	return ['ok' => true, 'error' => null, 'unverified' => $status === null];
}

function sl_htaccess_restore_latest(): array {
	$backup = sl_htaccess_latest_backup();
	if ($backup === null) {
		return ['ok' => false, 'error' => __t('htaccess_error_no_backup', 'No previous version to restore.')];
	}

	$content = file_get_contents($backup);
	if ($content === false) {
		return ['ok' => false, 'error' => __t('htaccess_error_write_failed', 'Could not write the .htaccess file.')];
	}

	if (!sl_htaccess_write(sl_htaccess_root_path(), $content)) {
		return ['ok' => false, 'error' => __t('htaccess_error_write_failed', 'Could not write the .htaccess file.')];
	}

	$restoredRules = '';
	$pattern = '/' . preg_quote(SL_HTACCESS_BEGIN, '/') . '\n?(.*?)\n?' . preg_quote(SL_HTACCESS_END, '/') . '/s';
	if (preg_match($pattern, $content, $m)) {
		$restoredRules = trim($m[1]);
	}
	sl_htaccess_store_rules($restoredRules);

	return ['ok' => true, 'error' => null];
}
