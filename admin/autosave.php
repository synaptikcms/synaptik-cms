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

if ($existingItem !== null && !in_array($currentStatus, ['draft', 'unpublished'], true)) {

	$draftsDir = sl_admin_drafts_dir();
	if (!is_dir($draftsDir) && !mkdir($draftsDir, 0755, true)) {
		echo json_encode(['error' => 'Failed to create drafts directory']);
		exit;
	}

	$draftId = !empty($_POST['draft_id']) ? $_POST['draft_id'] : uniqid('draft_');

	$normalizedImagePath = trim($_POST['selected_image_path'] ?? '');
	if ($normalizedImagePath !== '' && strpos($normalizedImagePath, 'files/') !== 0) {
		$normalizedImagePath = 'files/' . ltrim($normalizedImagePath, '/');
	}

	$pendingContentFormat = in_array($_POST['content_format'] ?? '', ['html', 'markdown'], true)
		? $_POST['content_format']
		: ($existingItem['content_format'] ?? 'html');
	$pendingContent = $pendingContentFormat === 'markdown'
		? admin_purify_markdown($_POST['content'] ?? '')
		: admin_purify_html($_POST['content'] ?? '');

	$pendingCategorySlug = trim($_POST['category'] ?? '') !== '' ? sanitizeSlug(trim($_POST['category'])) : '';

	$pendingIsUnchanged =
		(string)($existingItem['title']    ?? '') === (string)($_POST['title']    ?? '') &&
		(string)($existingItem['category'] ?? '') === $pendingCategorySlug &&
		(string)($existingItem['summary']  ?? '') === (string)($_POST['summary']  ?? '') &&
		(string)($existingItem['content']  ?? '') === $pendingContent;

	if ($pendingIsUnchanged) {
		$staleFile = $draftsDir . '/' . $draftId . '.json';
		if (is_file($staleFile)) @unlink($staleFile);
		echo json_encode([
			'success'     => true,
			'draft_id'    => $draftId,
			'timestamp'   => time(),
			'unchanged'   => true,
			// This item is already published — the real file isn't touched by
			// the pending-draft flow above, so this is the current live URL,
			// not a preview of the unsaved edit. Same caveat as the
			// "materialized"/existing-item branches below: the button always
			// opens what's actually on disk right now.
			'preview_url' => admin_content_url($contentType, $existingItem['slug'] ?? '', $existingItem['custom_slug'] ?? '', $existingItem['category'] ?? ''),
		]);
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
		echo json_encode([
			'success'     => true,
			'draft_id'    => $draftId,
			'timestamp'   => time(),
			// Same caveat as the "unchanged" branch above: this is the current
			// live URL on disk, not a preview of the pending draft content.
			'preview_url' => admin_content_url($contentType, $existingItem['slug'] ?? '', $existingItem['custom_slug'] ?? '', $existingItem['category'] ?? ''),
		]);
	} else {
		echo json_encode(['error' => 'Failed to save draft file']);
	}
	exit;
}

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

	echo json_encode([
		'success'     => true,
		'timestamp'   => time(),
		'preview_url' => admin_content_url($contentType, $item['slug'] ?? '', $item['custom_slug'] ?? '', $item['category'] ?? ''),
	]);
	exit;
}

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
	'preview_url' => admin_content_url($contentType, $item['slug'] ?? '', $item['custom_slug'] ?? '', $item['category'] ?? ''),
	'timestamp'   => time(),
]);
