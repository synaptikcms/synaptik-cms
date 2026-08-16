<?php
// Set appropriate headers for JSON response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Load CMS functions (url_slug, cleanUrl, getCategoryPath, getBaseUrl, sanitizeSlug,
// sl_build_data_array). functions.php already requires data-layer.php internally —
// no separate include needed here.
require_once __DIR__ . '/functions.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Sanitize a string to valid UTF-8.
 * Strips invalid byte sequences (e.g. Windows-1252 curly quotes copy-pasted
 * into the editor) that cause json_encode() to silently return false.
 */
function sanitize_utf8(string $str): string
{
	// iconv with //IGNORE drops bytes that are invalid in UTF-8
	$clean = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
	return ($clean === false) ? '' : $clean;
}

/**
 * Build a plain-text excerpt centred on $query inside $plainText.
 * Uses mb_ functions so multibyte chars (é, ▼, 📌 …) are never split.
 */
function build_excerpt(string $plainText, string $query, int $ctx = 50, int $maxLen = 200): string
{
	$lowerText  = mb_strtolower($plainText, 'UTF-8');
	$lowerQuery = mb_strtolower($query,     'UTF-8');
	$totalLen   = mb_strlen($plainText, 'UTF-8');

	$pos = mb_strpos($lowerText, $lowerQuery, 0, 'UTF-8');
	if ($pos !== false) {
		$start  = max(0, $pos - $ctx);
		$length = min($maxLen, $totalLen - $start);
		$excerpt = mb_substr($plainText, $start, $length, 'UTF-8');
		if ($start > 0)              $excerpt = '…' . $excerpt;
		if ($start + $length < $totalLen) $excerpt .= '…';
	} else {
		$excerpt = mb_substr($plainText, 0, 150, 'UTF-8') . '…';
	}

	return $excerpt;
}

/**
 * Lightweight strip of the editor's custom block wrappers before strip_tags().
 * This avoids strip_tags() outputting inline style strings like
 * "--c-color:#60a5fa;--c-bg:#60a5fa1A" as visible text.
 */
function strip_editor_noise(string $html): string
{
	// Remove wrapped shortcodes: [foo ...]content[/foo]
	$html = preg_replace('/\[[^\]]+\].*?\[\/[^\]]+\]/s', '', $html);
	// Remove self-closing shortcodes: [foo ...] or [/foo]
	$html = preg_replace('/\[[^\]]*\]/', '', $html);
	// Remove style attributes entirely (they produce CSS noise in plain text)
	$html = preg_replace('/\s+style="[^"]*"/i', '', $html);
	// Remove data-* attributes (won't appear in text anyway, just tidier)
	$html = preg_replace('/\s+data-[a-z\-]+="[^"]*"/i', '', $html);
	return $html;
}

// ─── Input validation ─────────────────────────────────────────────────────────

$query           = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');
$searchInContent = ($_GET['content']  ?? '') === 'true';
$searchArticles  = ($_GET['articles'] ?? '') === 'true';
$searchPages     = ($_GET['pages']    ?? '') === 'true';
$searchProjects  = ($_GET['projects'] ?? '') === 'true';

if ($query === '') {
	echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
	exit;
}

// Minimum query length guard (avoid matching every word with single letters)
if (mb_strlen($query, 'UTF-8') < 2) {
	echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
	exit;
}

// ─── Rate limiting (per-IP, file-based, flock across full read-modify-write) ──
// Max 30 search requests per minute per IP.
// One JSON file shared across all IPs; expired entries pruned on every write
// to prevent unbounded growth. flock() wraps the full cycle so concurrent
// requests cannot lose increments (fixes the race that existed in the first
// implementation where the read was unlocked).
$_srRateFile = __DIR__ . '/../private/search_rate.json';
$_srIpKey    = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
$_srNow      = time();
$_srWindow   = 60;   // seconds
$_srMax      = 30;   // requests per window
$_srLimited  = false;

$_srFh = @fopen($_srRateFile, 'c+');
if ($_srFh !== false) {
	flock($_srFh, LOCK_EX);
	$_srRaw  = stream_get_contents($_srFh);
	$_srData = ($_srRaw !== '' && $_srRaw !== false) ? (json_decode($_srRaw, true) ?: []) : [];

	$_srEntry = $_srData[$_srIpKey] ?? ['count' => 0, 'window_start' => $_srNow];
	if (($_srNow - $_srEntry['window_start']) >= $_srWindow) {
		$_srEntry = ['count' => 0, 'window_start' => $_srNow];
	}
	$_srEntry['count']++;
	$_srData[$_srIpKey] = $_srEntry;

	// Prune stale entries to keep the file from growing without bound
	foreach ($_srData as $_srPruneKey => $_srPruneEntry) {
		if (($_srNow - ($_srPruneEntry['window_start'] ?? 0)) >= $_srWindow * 2) {
			unset($_srData[$_srPruneKey]);
		}
	}
	unset($_srPruneKey, $_srPruneEntry);

	ftruncate($_srFh, 0);
	rewind($_srFh);
	fwrite($_srFh, json_encode($_srData));
	flock($_srFh, LOCK_UN);
	fclose($_srFh);

	if ($_srEntry['count'] > $_srMax) $_srLimited = true;
}
unset($_srRateFile, $_srIpKey, $_srNow, $_srWindow, $_srMax, $_srData, $_srEntry, $_srRaw, $_srFh);

if ($_srLimited) {
	http_response_code(429);
	echo json_encode(['error' => 'Too many requests. Please slow down.'], JSON_UNESCAPED_UNICODE);
	exit;
}
unset($_srLimited);

// ─── Load data — only the types actually requested ───────────────────────────
// Build the type list first so sl_build_data_array() reads only necessary files.

$contentTypes = [];
if ($searchArticles) $contentTypes[] = 'article';
if ($searchPages)    $contentTypes[] = 'page';
if ($searchProjects) $contentTypes[] = 'project';
if (empty($contentTypes)) $contentTypes = ['article', 'page', 'project'];

// Full items are needed only when content-body search is enabled.
$data = sl_build_data_array($contentTypes, $searchInContent);

// Expose globally so cleanUrl() / getCategoryPath() can resolve category paths.
$GLOBALS['data'] = $data;

$results = [];

foreach ($contentTypes as $contentType) {
	if (!isset($data[$contentType]) || !is_array($data[$contentType])) {
		continue;
	}

	foreach ($data[$contentType] as $item) {
		$titleMatch   = false;
		$contentMatch = false;
		$tagsMatch    = false;
		$plainText    = null; // compute once, reuse

		// ── Title match (cheap, always run first) ──
		if (isset($item['title'])) {
			$lowerTitle = mb_strtolower($item['title'], 'UTF-8');
			if (mb_strpos($lowerTitle, $query, 0, 'UTF-8') !== false) {
				$titleMatch = true;
			}
		}

		// ── Tag match ──
		if (isset($item['tags']) && is_array($item['tags'])) {
			foreach ($item['tags'] as $tag) {
				if (mb_strpos(mb_strtolower($tag, 'UTF-8'), $query, 0, 'UTF-8') !== false) {
					$tagsMatch = true;
					break;
				}
			}
		}

		// ── Content match (expensive — only run when needed) ──
		if ($searchInContent && isset($item['content'])) {
			$cleanedHtml = strip_editor_noise($item['content']);
			$plainText   = strip_tags($cleanedHtml);
			$plainText   = sanitize_utf8($plainText);
			// Collapse whitespace produced by stripped tags
			$plainText   = preg_replace('/\s+/', ' ', $plainText);
			$plainText   = trim($plainText);

			$lowerPlain = mb_strtolower($plainText, 'UTF-8');
			if (mb_strpos($lowerPlain, $query, 0, 'UTF-8') !== false) {
				$contentMatch = true;
			}
		}

		if (!$titleMatch && !$contentMatch && !$tagsMatch) {
			continue;
		}

		// ── Build excerpt ──
		// Use article summary if available, otherwise build from content
		if ($contentType === 'article' && !empty($item['summary'])) {
			$excerpt = sanitize_utf8(trim($item['summary']));
		} elseif (isset($item['content'])) {
			// Reuse $plainText if already computed, otherwise build it now
			if ($plainText === null) {
				$cleanedHtml = strip_editor_noise($item['content']);
				$plainText   = strip_tags($cleanedHtml);
				$plainText   = sanitize_utf8($plainText);
				$plainText   = preg_replace('/\s+/', ' ', $plainText);
				$plainText   = trim($plainText);
			}
			$excerpt = build_excerpt($plainText, $query);
		} else {
			$excerpt = '';
		}

		$matchType = $titleMatch ? 'title' : ($tagsMatch ? 'tag' : 'content');

		// Resolve final slug (custom_slug takes priority)
		$finalSlug = isset($item['custom_slug']) && $item['custom_slug'] !== ''
			? $item['custom_slug']
			: ($item['slug'] ?? '');

		// Resolve category slug and full hierarchical path
		$categoryName = $item['category'] ?? '';
		$categorySlug = !empty($categoryName) ? sanitizeSlug($categoryName) : '';
		$catPath      = !empty($categorySlug) ? getCategoryPath($categorySlug, $data) : '';

		// Build the canonical front-end URL via the same cleanUrl() used everywhere else
		$contentUrl = cleanUrl($contentType, $finalSlug, null, $categorySlug ?: null);

		$results[] = [
			'type'      => $contentType,
			'title'     => sanitize_utf8($item['title'] ?? ''),
			'slug'      => $finalSlug,
			'url'       => $contentUrl,   // Fully resolved, localized URL
			'cat_path'  => $catPath,      // e.g. "parent/child" — for display only
			'excerpt'   => $excerpt,
			'date'      => $item['date']     ?? '',
			'category'  => $categoryName,
			'image'     => $item['image']    ?? null,
			'has_image' => !empty($item['image']),
			'match_type'=> $matchType,
		];
	}
}

// ─── Sort ─────────────────────────────────────────────────────────────────────

usort($results, function ($a, $b) {
	$order = ['title' => 1, 'tag' => 2, 'content' => 3];
	$oa = $order[$a['match_type']] ?? 4;
	$ob = $order[$b['match_type']] ?? 4;
	if ($oa !== $ob) return $oa - $ob;
	if (($a['date'] ?? '') !== ($b['date'] ?? '')) {
		return strcmp($b['date'] ?? '', $a['date'] ?? ''); // newest first
	}
	return strcmp($a['title'], $b['title']);
});

// ─── Output ───────────────────────────────────────────────────────────────────

$json = json_encode([
	'base_url' => getBaseUrl(),
	'results'  => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
	// Last-resort fallback: report the encoding error instead of a silent empty body
	http_response_code(500);
	echo json_encode([
		'error'  => 'JSON encoding failed',
		'detail' => json_last_error_msg(),
	]);
	exit;
}

echo $json;
