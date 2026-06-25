<?php
/**
 * Translation Editor — AJAX handler
 *
 * Operations (via ?op=...):
 *   load    GET   — return { reference, current, meta } for a scope+locale
 *   save    POST  — persist edited strings to lang/{scope}/{locale}.json
 *   create  POST  — duplicate en.json into a new locale file
 *
 * Security:
 *   - Requires an authenticated admin session.
 *   - Requires a valid CSRF token on save/create (POST).
 *   - Scope is whitelisted (front|admin).
 *   - Locale codes are validated against a strict regex.
 *   - Save rejects any key absent from en.json — prevents unknown
 *     keys being injected via a forged payload.
 *
 * Writes are atomic (tmp file + rename). The lang cache for the
 * affected locale is purged after every successful save/create.
 */

session_start();
require_once __DIR__ . '/includes/admin-functions.php';

// ── Auth ────────────────────────────────────────────────────────────────────
if (!admin_is_logged_in()) {
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
	exit;
}

header('Content-Type: application/json');

$op    = $_GET['op'] ?? '';
$scope = ($_GET['scope'] ?? '') === 'admin' ? 'admin' : 'front';

// ── Path helpers ────────────────────────────────────────────────────────────
$CMS_ROOT = dirname(__DIR__);
$LANG_FRONT = $CMS_ROOT . '/lang/front/';
$LANG_ADMIN = $CMS_ROOT . '/lang/admin/';

/**
 * Return the absolute directory for a given scope.
 */
function trl_scope_dir(string $scope): string {
	global $LANG_FRONT, $LANG_ADMIN;
	return $scope === 'admin' ? $LANG_ADMIN : $LANG_FRONT;
}

/**
 * Validate a locale code: 2 lowercase letters, optionally _XX.
 */
function trl_valid_locale(string $code): bool {
	return (bool)preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $code);
}

/**
 * Read a locale JSON file. Returns [] if missing or invalid.
 */
function trl_read_locale(string $scope, string $locale): array {
	$path = trl_scope_dir($scope) . $locale . '.json';
	if (!is_file($path)) return [];
	$decoded = json_decode(file_get_contents($path), true);
	return is_array($decoded) ? $decoded : [];
}

/**
 * Write a locale JSON file atomically and purge the lang cache.
 * Preserves key order from the existing file when possible.
 */
function trl_write_locale(string $scope, string $locale, array $data): bool {
	$path    = trl_scope_dir($scope) . $locale . '.json';
	$tmpPath = $path . '.tmp.' . getmypid();

	$json = json_encode(
		$data,
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	if ($json === false) return false;

	if (file_put_contents($tmpPath, $json, LOCK_EX) === false) return false;
	if (!rename($tmpPath, $path)) {
		@unlink($tmpPath);
		return false;
	}

	// Purge the cache for this locale in BOTH contexts: the file we just
	// wrote lives in one scope, but lang_cache_purge() resolves the path
	// from LANG_CONTEXT (which is 'admin' here). We invalidate front-side
	// caches directly to keep things consistent.
	trl_purge_cache_file($scope, $locale);

	return true;
}

/**
 * Directly remove the compiled PHP cache file for a locale, regardless
 * of the active LANG_CONTEXT. The lang loader will rebuild it lazily.
 */
function trl_purge_cache_file(string $scope, string $locale): void {
	global $CMS_ROOT;
	$cacheFile = $CMS_ROOT . '/cache/lang/' . ($scope === 'admin' ? 'admin' : 'front') . '/' . $locale . '.php';
	if (is_file($cacheFile)) {
		@unlink($cacheFile);
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($cacheFile, true);
		}
	}
}

/**
 * Build a placeholder signature ("%s", "%d", "%1$s"…) for mismatch
 * detection. Returns a sorted, joined string used as a fingerprint.
 */
function trl_placeholder_sig(string $s): string {
	preg_match_all('/%[0-9]*\$?[sdf]/', $s, $m);
	$tokens = $m[0] ?? [];
	sort($tokens);
	return implode('|', $tokens);
}

// ════════════════════════════════════════════════════════════════════════════
// LOAD — GET ?op=load&scope=front|admin&locale=xx
// ════════════════════════════════════════════════════════════════════════════
if ($op === 'load') {
	$locale = $_GET['locale'] ?? '';
	if (!trl_valid_locale($locale)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_locale']);
		exit;
	}

	$reference = trl_read_locale($scope, 'en');
	$current   = trl_read_locale($scope, $locale);

	// Strip _meta from the reference (it is editable separately if needed,
	// but we never want it shown as a "string to translate").
	$refMeta = $reference['_meta'] ?? null;
	$curMeta = $current['_meta']   ?? null;
	unset($reference['_meta'], $current['_meta']);

	echo json_encode([
		'ok'        => true,
		'scope'     => $scope,
		'locale'    => $locale,
		'reference' => $reference,
		'current'   => $current,
		'meta'      => [
			'reference' => $refMeta,
			'current'   => $curMeta,
		],
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// ════════════════════════════════════════════════════════════════════════════
// SAVE — POST ?op=save
// Body (JSON): { csrf_token, scope, locale, strings: { key: value, ... } }
// ════════════════════════════════════════════════════════════════════════════
if ($op === 'save') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
		exit;
	}

	$raw     = file_get_contents('php://input');
	$payload = json_decode($raw, true);
	if (!is_array($payload)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
		exit;
	}

	// CSRF — read from payload (POST JSON body)
	$token = $payload['csrf_token'] ?? '';
	if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
		http_response_code(403);
		echo json_encode(['ok' => false, 'error' => 'csrf_failed']);
		exit;
	}

	$scope  = ($payload['scope']  ?? '') === 'admin' ? 'admin' : 'front';
	$locale = $payload['locale'] ?? '';
	if (!trl_valid_locale($locale)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_locale']);
		exit;
	}

	$strings = $payload['strings'] ?? null;
	if (!is_array($strings)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_strings']);
		exit;
	}

	// Locale file must exist — we never create on save, only update
	$current = trl_read_locale($scope, $locale);
	if (empty($current)) {
		http_response_code(404);
		echo json_encode(['ok' => false, 'error' => 'locale_not_found']);
		exit;
	}

	// Reference (en.json) is the schema. Reject any key not present in it.
	$reference = trl_read_locale($scope, 'en');
	if (empty($reference)) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'reference_missing']);
		exit;
	}

	$rejected = [];
	$warnings = [];
	$applied  = 0;

	foreach ($strings as $key => $value) {
		// Whitelist: must exist in reference (excluding _meta which is handled separately)
		if ($key === '_meta' || !array_key_exists($key, $reference)) {
			$rejected[] = $key;
			continue;
		}
		// Type guard
		if (!is_string($value)) {
			$rejected[] = $key;
			continue;
		}
		// Hard length cap to avoid a malicious 100MB string DoSing the file
		if (strlen($value) > 20000) {
			$rejected[] = $key;
			continue;
		}
		// Placeholder mismatch is a warning, not a rejection (Dorian wants the choice)
		if ($value !== '' && trl_placeholder_sig($reference[$key]) !== trl_placeholder_sig($value)) {
			$warnings[] = $key;
		}

		$current[$key] = $value;
		$applied++;
	}

	if (!trl_write_locale($scope, $locale, $current)) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'write_failed']);
		exit;
	}

	echo json_encode([
		'ok'       => true,
		'applied'  => $applied,
		'rejected' => $rejected,
		'warnings' => $warnings,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// ════════════════════════════════════════════════════════════════════════════
// CREATE — POST ?op=create
// Body (JSON): { csrf_token, locale, label, both_scopes: bool }
// ════════════════════════════════════════════════════════════════════════════
if ($op === 'create') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
		exit;
	}

	$raw     = file_get_contents('php://input');
	$payload = json_decode($raw, true);
	if (!is_array($payload)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
		exit;
	}

	$token = $payload['csrf_token'] ?? '';
	if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
		http_response_code(403);
		echo json_encode(['ok' => false, 'error' => 'csrf_failed']);
		exit;
	}

	$locale = $payload['locale'] ?? '';
	$label  = trim((string)($payload['label'] ?? ''));
	$both   = !empty($payload['both_scopes']);

	if (!trl_valid_locale($locale)) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_locale']);
		exit;
	}
	if ($label === '' || mb_strlen($label) > 50) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'invalid_label']);
		exit;
	}
	if ($locale === 'en') {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'cannot_overwrite_reference']);
		exit;
	}

	$created = [];
	$scopes  = $both ? ['front', 'admin'] : [$scope];

	foreach ($scopes as $sc) {
		$dest = trl_scope_dir($sc) . $locale . '.json';
		if (file_exists($dest)) {
			// Don't silently overwrite — refuse and report
			http_response_code(409);
			echo json_encode([
				'ok'    => false,
				'error' => 'already_exists',
				'scope' => $sc,
			]);
			exit;
		}

		$reference = trl_read_locale($sc, 'en');
		if (empty($reference)) {
			http_response_code(500);
			echo json_encode(['ok' => false, 'error' => 'reference_missing', 'scope' => $sc]);
			exit;
		}

		// Update _meta for the new locale; values are copied from en.json
		// untouched so the editor can show them as "reference, untranslated".
		$reference['_meta'] = [
			'language' => $label,
			'locale'   => $locale,
			'author'   => admin_get_display_name(),
			'version'  => '1.0',
		];

		if (!trl_write_locale($sc, $locale, $reference)) {
			http_response_code(500);
			echo json_encode(['ok' => false, 'error' => 'write_failed', 'scope' => $sc]);
			exit;
		}

		$created[] = $sc;
	}

	echo json_encode([
		'ok'      => true,
		'locale'  => $locale,
		'label'   => $label,
		'created' => $created,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

// ── Unknown op ──────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_op']);
