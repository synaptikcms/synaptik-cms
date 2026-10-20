<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

function handleContentAddition() {
	global $data, $contentTypes;

	$formContentType = $_POST['type'] ?? '';
	$title           = trim($_POST['title'] ?? '');
	$content         = $_POST['content'] ?? '';

	if (empty($title) || empty($content) || !in_array($formContentType, $contentTypes, true)) {
		// Error handling - don't redirect, keep data for resubmission
		$_SESSION['error'] = __t('fill_required_fields');
		$_SESSION['form_data'] = $_POST;
		return;
	}

	$built = admin_build_content_item_from_post(
		$formContentType,
		$_POST,
		null,
		$data[$formContentType] ?? [],
		$_FILES
	);

	$newItem = $built['item'];

	if ($built['slug_renamed_to'] !== null) {
		// Notify the admin that the slug was automatically renamed
		$_SESSION['notice'] = sprintf(
			__t('slug_auto_renamed', 'A duplicate slug was detected. The URL slug has been automatically renamed to "%s".'),
			$built['slug_renamed_to']
		);
	}
	foreach ($built['new_tags'] as $tagSlug => $displayName) {
		if (!isset($data['tags'][$tagSlug])) $data['tags'][$tagSlug] = ['name' => $displayName];
	}
	if ($built['new_category'] !== null) {
		foreach ($built['new_category'] as $catSlug => $displayName) {
			if (!isset($data['categories'][$catSlug])) $data['categories'][$catSlug] = ['name' => $displayName];
		}
	}

	$status = $_POST['status'] ?? 'published';
	if (!in_array($status, ['published', 'scheduled', 'draft', 'unpublished'], true)) $status = 'published';

	$publishAt = trim(str_replace('T', ' ', $_POST['publish_at'] ?? ''));
	if ($status === 'scheduled' && ($publishTs = strtotime($publishAt)) !== false && $publishTs > time()) {
		$newItem['publish_at'] = $publishAt;
	} else {
		if ($status === 'scheduled') $status = 'published';
		$newItem['publish_at'] = '';
	}
	$newItem['status'] = $status;
	$data[$formContentType][] = $newItem;
	saveData($data);
	if ($status === 'published') sm_regenerate();
	$newIndex = count($data[$formContentType]) - 1;
	$_SESSION['message'] = __t('content_added');
	header('Location: index.php?action=edit&type=' . $formContentType . '&index=' . $newIndex . '&message=show');
	exit;
}

function handleContentEdit() {
	global $data, $index, $contentType;

	if (isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
		http_response_code(403);
		exit(__t('access_denied', 'Access denied.'));
	}

	$title   = trim($_POST['title'] ?? '');
	$content = $_POST['content'] ?? '';

	if (!isset($data[$contentType][$index]) || empty($title) || empty($content)) {
		$_SESSION['error'] = __t('fill_required_fields');
		return;
	}

	$existingItem = $data[$contentType][$index];

	$built = admin_build_content_item_from_post($contentType, $_POST, $existingItem, [], $_FILES);

	$updatedItem = $built['item'];

	foreach ($built['new_tags'] as $tagSlug => $displayName) {
		if (!isset($data['tags'][$tagSlug])) $data['tags'][$tagSlug] = ['name' => $displayName];
	}
	if ($built['new_category'] !== null) {
		foreach ($built['new_category'] as $catSlug => $displayName) {
			if (!isset($data['categories'][$catSlug])) $data['categories'][$catSlug] = ['name' => $displayName];
		}
	}

	$status = $_POST['status'] ?? 'published';
	if (!in_array($status, ['published', 'scheduled', 'draft', 'unpublished'], true)) $status = 'published';

	$publishAt = trim(str_replace('T', ' ', $_POST['publish_at'] ?? ''));
	$publishTs = ($publishAt !== '') ? strtotime($publishAt) : false;
	if ($status === 'scheduled' && $publishTs !== false && $publishTs > time()) {
		$updatedItem['publish_at'] = $publishAt;
	} else {
		if ($status === 'scheduled') $status = 'published';
		$updatedItem['publish_at'] = '';
	}
	$updatedItem['status'] = $status;

	$oldMenuSlug = !empty($existingItem['custom_slug'])
		? $existingItem['custom_slug']
		: ($existingItem['slug'] ?? '');
	$oldMenuCategory = $existingItem['category'] ?? '';

	$oldEffectiveSlug = sl_effective_slug($existingItem);
	$oldFound         = sl_find_in_index($contentType, $oldEffectiveSlug);
	$oldFileSlug      = $oldFound ? sl_file_slug($oldFound[0]) : $oldEffectiveSlug;
	sl_admin_snapshot_revision($contentType, $oldFileSlug, $existingItem);

	$data[$contentType][$index] = $updatedItem;
	saveData($data);
	if (($existingItem['status'] ?? '') === 'published' || $status === 'published') sm_regenerate();

	// saveData() keeps the item under its existing on-disk filename whenever that
	// filename's slug already matches the index (autosave silently syncs the index's
	// slug to the current title on every tick without renaming the file, so by the
	// time a real save/schedule happens there's no slug "change" left for saveData()
	// to detect). Reconcile the filename with the real target slug here instead.
	sl_admin_reconcile_file_slug($contentType, $oldFileSlug, $updatedItem);

	$newMenuSlug = sl_effective_slug($updatedItem);
	$newFound    = sl_find_in_index($contentType, $newMenuSlug);
	$newFileSlug = $newFound ? sl_file_slug($newFound[0]) : $newMenuSlug;
	if ($newFileSlug !== $oldFileSlug) {
		sl_admin_migrate_revisions($contentType, $oldFileSlug, $newFileSlug);
	}

	if ($oldMenuSlug !== $newMenuSlug || $oldMenuCategory !== $updatedItem['category']) {
		syncMenuUrls($contentType, $oldMenuSlug, $newMenuSlug, $updatedItem['category']);
	}

	$draftsDir = sl_admin_drafts_dir();
	if (is_dir($draftsDir)) {
		$files = glob($draftsDir . '/*.json');
		foreach ($files as $file) {
			$draftData = json_decode(file_get_contents($file), true);
			if ($draftData && $draftData['type'] === $contentType) {
				if (isset($draftData['index']) && $draftData['index'] == $index) {
					unlink($file);
				}
			}
		}
	}

	$_SESSION['message'] = __t('content_updated');
	// Add a redirect with message parameter
	header('Location: index.php?action=edit&type=' . $contentType . '&index=' . $index . '&message=show');
	exit;
}

function syncMenuUrls($contentType, $oldSlug, $newSlug, $newCategory) {
	$settings = menu_sync_load_config();
	if ($settings === null) return;

	$categorySlug = !empty($newCategory) ? sanitizeSlug($newCategory) : '';
	$catPath      = $categorySlug !== '' ? getCategoryPath($categorySlug, ['categories' => sl_load_categories()]) : '';

	$changed = false;
	foreach ($settings['main_menu'] as &$item) {
		if (($item['content_type'] ?? '') !== $contentType || ($item['content_slug'] ?? '') !== $oldSlug) continue;

		$item['url']          = menu_sync_content_url($contentType, $newSlug, $catPath);
		$item['content_slug'] = $newSlug;
		if (array_key_exists('content_category', $item)) {
			$item['content_category'] = $newCategory;
		}
		$changed = true;
	}
	unset($item);

	if ($changed) sl_admin_save_config($settings);
}

function handleImageUpload($file, $contentType) {
	$uploadDir = '../files/' . $contentType . 's/';
	
	// Create featured_images subdirectory for each content type
	$featuredImagesDir = $uploadDir . 'featured_images/';
	if (!file_exists($featuredImagesDir)) {
		mkdir($featuredImagesDir, 0755, true);
	}
	
	$fileName = time() . '_' . sanitizeFileName(basename($file['name']));
	$targetFile = $featuredImagesDir . $fileName;
	
	// Check if image file is valid
	$imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
	$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	
	if (in_array($imageFileType, $allowedTypes)) {
		if (move_uploaded_file($file['tmp_name'], $targetFile)) {
			// Return relative path for database
			return 'files/' . $contentType . 's/featured_images/' . $fileName;
		}
	}
	
	return false;
}
