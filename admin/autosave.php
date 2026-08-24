<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';

header('Content-Type: application/json');

if (!admin_is_logged_in()) {
	echo json_encode(['error' => 'Not authorized']);
	exit;
}

$_csrfToken = $_POST['csrf_token'] ?? (getallheaders()['X-CSRF-Token'] ?? '');
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_csrfToken)) {
	echo json_encode(['error' => 'Invalid security token.']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['error' => 'Invalid request method']);
	exit;
}

$contentType = $_POST['type'] ?? '';
if (!in_array($contentType, ['article', 'page', 'project'], true)) {
	echo json_encode(['error' => 'invalid_type']);
	exit;
}

// Named/legacy galleries arrive as a JSON string from FormData — decode once,
// up front, so both branches below (and admin_build_content_item_from_post())
// see a plain array like a real form submission would produce.
if (isset($_POST['galleries']) && is_string($_POST['galleries'])) {
	$_POST['galleries'] = json_decode($_POST['galleries'], true) ?: [];
}
if (isset($_POST['gallery']) && is_string($_POST['gallery'])) {
	$_POST['gallery'] = json_decode($_POST['gallery'], true) ?: [];
}

// Resolve the target: a real, already-saved item, or nothing yet.
$index        = isset($_POST['index']) && $_POST['index'] !== '' ? (int)$_POST['index'] : -1;
$existingItem = null;
$fileSlug     = '';
if ($index >= 0) {
	$indexEntries = sl_load_index($contentType);
	if (isset($indexEntries[$index])) {
		$fileSlug     = sl_file_slug($indexEntries[$index]);
		$existingItem = sl_load_item($contentType, $fileSlug);
	}
}

if ($existingItem !== null && !admin_can_edit_item($existingItem)) {
	echo json_encode(['error' => 'not_authorized']);
	exit;
}

$currentStatus = $existingItem['status'] ?? null;

// 'draft' (never published) and 'unpublished' (was live, pulled back) are
// both safe for autosave to write directly — nothing public is at stake
// either way. 'published' and 'scheduled' are not.
if ($existingItem !== null && !in_array($currentStatus, ['draft', 'unpublished'], true)) {
	// ─── Case B: the item is already published or scheduled ──────────────────
	// Never silently overwrite live/queued content — autosave here writes a
	// separate pending snapshot instead, surfaced in the content list as
	// "unsaved changes" until the admin explicitly resumes (loads it into
	// the editor) or it's superseded by a real Update/Publish.
	$draftsDir = sl_admin_drafts_dir();
	if (!is_dir($draftsDir) && !mkdir($draftsDir, 0755, true)) {
		echo json_encode(['error' => 'Failed to create drafts directory']);
		exit;
	}

	// The hidden draft_id field is empty until the first snapshot for this
	// editing session — after that it's carried forward so every subsequent
	// autosave updates the same file instead of creating a new one each time.
	$draftId = !empty($_POST['draft_id']) ? $_POST['draft_id'] : uniqid('draft_');

	$normalizedImagePath = trim($_POST['selected_image_path'] ?? '');
	if ($normalizedImagePath !== '' && strpos($normalizedImagePath, 'files/') !== 0) {
		$normalizedImagePath = 'files/' . ltrim($normalizedImagePath, '/');
	}

	// Same purification the real save path applies (admin_build_content_item_from_post())
	// — this snapshot is rendered back into the editor/diff view on resume, and
	// previewed like real content in the meantime, so it can't skip sanitization
	// just because it's "only" a pending snapshot.
	$pendingContentFormat = in_array($_POST['content_format'] ?? '', ['html', 'markdown'], true)
		? $_POST['content_format']
		: ($existingItem['content_format'] ?? 'html');
	$pendingContent = $pendingContentFormat === 'markdown'
		? admin_purify_markdown($_POST['content'] ?? '')
		: admin_purify_html($_POST['content'] ?? '');

	// Nothing to snapshot if this tick matches the published item exactly —
	// same fields the pending-diff view itself compares (title, category,
	// summary, content), so "no differences shown there" and "no snapshot
	// written here" always agree. Covers both a genuinely no-op autosave tick
	// and a change that was made, autosaved, then undone back to the
	// published version — in the second case any earlier snapshot under this
	// draft_id is removed too, so the "unsaved changes" badge clears instead
	// of pointing at a now-identical draft.
	$pendingIsUnchanged =
		(string)($existingItem['title']    ?? '') === (string)($_POST['title']    ?? '') &&
		(string)($existingItem['category'] ?? '') === (string)($_POST['category'] ?? '') &&
		(string)($existingItem['summary']  ?? '') === (string)($_POST['summary']  ?? '') &&
		(string)($existingItem['content']  ?? '') === $pendingContent;

	if ($pendingIsUnchanged) {
		$staleFile = $draftsDir . '/' . $draftId . '.json';
		if (is_file($staleFile)) @unlink($staleFile);
		echo json_encode(['success' => true, 'draft_id' => $draftId, 'timestamp' => time(), 'unchanged' => true]);
		exit;
	}

	$draft = [
		'id'             => $draftId,
		'content'        => $pendingContent,
		'title'          => $_POST['title'] ?? '',
		'type'           => $contentType,
		'index'          => $index,
		'custom_slug'    => $_POST['custom_slug'] ?? '',
		'timestamp'      => time(),
		'user'           => $_SESSION['admin_username'] ?? 'admin',
		'admin_user_id'  => admin_current_user_id(),
		'category'       => $_POST['category'] ?? '',
		'tags'           => $_POST['tags'] ?? '',
		'show_tags_at_bottom'  => isset($_POST['show_tags_at_bottom']),
		'description'    => $_POST['description'] ?? '',
		'summary'        => $_POST['summary'] ?? '',
		'show_featured_image'  => isset($_POST['show_featured_image']),
		'show_date'      => isset($_POST['show_date']),
		'show_title'     => isset($_POST['show_title']),
		'show_on_homepage'     => isset($_POST['show_on_homepage']),
		'show_in_menu'   => isset($_POST['show_in_menu']),
		'menu_order'     => isset($_POST['menu_order']) ? max(0, min(999, (int)$_POST['menu_order'])) : 0,
		'selected_image_path'  => $normalizedImagePath,
		'image'          => $normalizedImagePath,
		'galleries'      => is_array($_POST['galleries'] ?? null) ? $_POST['galleries'] : [],
		'gallery'        => is_array($_POST['gallery'] ?? null) ? $_POST['gallery'] : [],
		'gallery_layout' => $_POST['gallery_layout'] ?? 'grid',
		'meta_title'     => $_POST['meta_title'] ?? '',
		'meta_description'     => $_POST['meta_description'] ?? '',
		'meta_keywords'  => $_POST['meta_keywords'] ?? '',
		'canonical_url'  => $_POST['canonical_url'] ?? '',
		'schema_type'    => $_POST['schema_type'] ?? '',
		'og_title'       => $_POST['og_title'] ?? '',
		'og_description' => $_POST['og_description'] ?? '',
		'og_image'       => $_POST['og_image'] ?? '',
		// Empty string, not absent — the "date" field is a hidden input that
		// starts blank until a publish date is explicitly set; '??' wouldn't
		// catch that, only a real empty check does.
		'date'           => !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d H:i'),
		'custom_fields'  => isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])
			? $_POST['custom_fields']
			: [],
	];

	$filename = $draftsDir . '/' . $draftId . '.json';
	$jsonData = json_encode($draft, JSON_PRETTY_PRINT);
	if ($jsonData === false) {
		echo json_encode(['error' => 'Failed to encode draft data: ' . json_last_error_msg()]);
		exit;
	}

	if (file_put_contents($filename, $jsonData)) {
		echo json_encode(['success' => true, 'draft_id' => $draftId, 'timestamp' => time()]);
	} else {
		echo json_encode(['error' => 'Failed to save draft file']);
	}
	exit;
}

// ─── Case A: materializing a new item, or updating an existing draft/unpublished item ──
// Nothing public is at stake either way, so autosave writes straight to the
// real item — with a revision snapshot first, same as an explicit save.
// Never auto-publishes and never changes draft <-> unpublished: status is
// preserved as-is (or set to 'draft' when there's nothing to preserve, i.e.
// materializing a brand new item), regardless of any publish_at the form
// may carry. Going live is only ever the result of an explicit
// Update/Publish click (see content.php); explicit Unpublish is the only
// way back from 'published'/'scheduled' to 'unpublished' (also content.php).
//
// Unlike the explicit Add/Edit forms, title and content aren't both required
// here — the editor's own autosave gate already only calls this endpoint
// once there's a real title or real content (see editor-common.js). Reject
// only the true edge case of neither being present at all.
if (trim($_POST['title'] ?? '') === '' && trim($_POST['content'] ?? '') === '') {
	echo json_encode(['error' => 'Content too short']);
	exit;
}

$built = admin_build_content_item_from_post(
	$contentType,
	$_POST,
	$existingItem,
	$existingItem === null ? sl_load_index($contentType) : [],
	[] // autosave never carries a real file upload
);

$item = $built['item'];
$item['status']     = $existingItem['status'] ?? 'draft';
$item['publish_at'] = $existingItem['publish_at'] ?? '';

foreach ($built['new_tags'] as $tagSlug => $displayName) {
	$tagsStore = sl_load_tags();
	if (!isset($tagsStore[$tagSlug])) {
		$tagsStore[$tagSlug] = ['name' => $displayName];
		sl_admin_save_tags($tagsStore);
	}
}
if ($built['new_category'] !== null) {
	$categoriesStore = sl_load_categories();
	foreach ($built['new_category'] as $catSlug => $displayName) {
		if (!isset($categoriesStore[$catSlug])) {
			$categoriesStore[$catSlug] = ['name' => $displayName];
			sl_admin_save_categories($categoriesStore);
		}
	}
}

if ($existingItem !== null) {
	// Updating an existing draft-status item in place. Only snapshot a
	// revision when something the revision-diff view would actually show
	// has changed — otherwise consecutive autosaves on an untouched draft
	// pile up empty "no changes" revisions.
	$contentChanged = (string)($existingItem['title']    ?? '') !== (string)($item['title']    ?? '')
		|| (string)($existingItem['summary']  ?? '') !== (string)($item['summary']  ?? '')
		|| (string)($existingItem['category'] ?? '') !== (string)($item['category'] ?? '')
		|| (string)($existingItem['content']  ?? '') !== (string)($item['content']  ?? '');

	if ($contentChanged) {
		sl_admin_snapshot_revision($contentType, $fileSlug, $existingItem);
	}
	sl_admin_save_item($contentType, $fileSlug, $item);

	$indexEntry          = sl_admin_extract_index_entry($contentType, $item);
	$indexEntry['_file'] = $fileSlug;
	sl_admin_update_index($contentType, $indexEntry);

	echo json_encode(['success' => true, 'timestamp' => time()]);
	exit;
}

// Never-published item — materializing it for the first time. A title-less
// autosave (real content, no title typed yet) needs a placeholder — both for
// display and because an empty title produces an empty slug.
if (empty($item['title'])) {
	$item['title'] = __t('untitled_draft', 'Untitled draft');
	$item['slug']  = sanitizeSlug($item['title']);
}
$effectiveSlug = $item['custom_slug'] !== '' ? $item['custom_slug'] : $item['slug'];
$newFileSlug   = sl_unique_file_slug($contentType, $effectiveSlug);

sl_admin_save_item($contentType, $newFileSlug, $item);

$indexEntry          = sl_admin_extract_index_entry($contentType, $item);
$indexEntry['_file'] = $newFileSlug;
sl_admin_update_index($contentType, $indexEntry);

// The front end needs the new item's array position to switch itself from
// the "add" form to the "edit" URL — find it in the index we just wrote to.
$newIndexEntries = sl_load_index($contentType);
$newPosition     = null;
foreach ($newIndexEntries as $pos => $entry) {
	if (sl_file_slug($entry) === $newFileSlug) { $newPosition = $pos; break; }
}

echo json_encode([
	'success'     => true,
	'materialized'=> true,
	'type'        => $contentType,
	'index'       => $newPosition,
	'timestamp'   => time(),
]);
