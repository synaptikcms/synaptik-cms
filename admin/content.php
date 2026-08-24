<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Load application settings
$appSettings = admin_load_config();

// Define available content types
$contentTypes = ['article', 'page', 'project'];

// Handle actions (add, edit, delete, list)
$action = $_GET['action'] ?? 'list';
$contentType = $_GET['type'] ?? '';
$index = isset($_GET['index']) ? (int)$_GET['index'] : null;

// Load the database
$data = loadData();

// Handle batch deletion — moves items to trash instead of deleting them
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && $_POST['batch_action'] === 'delete') {
	$contentType = $_POST['content_type'] ?? '';
	$selectedItemsJson = $_POST['selected_items'] ?? '[]';
	$selectedItems = json_decode($selectedItemsJson, true);

	if (json_last_error() !== JSON_ERROR_NONE) {
		$_SESSION['error'] = __t('batch_delete_invalid_data');
		header('Location: index.php');
		exit;
	}

	if (empty($contentType) || empty($selectedItems)) {
		$_SESSION['error'] = __t('batch_delete_invalid_params');
		header('Location: index.php');
		exit;
	}

	// Load via wrapper — uses split-file architecture
	$data = loadData();

	if (!isset($data[$contentType])) {
		$_SESSION['error'] = __t('content_type_not_found');
		header('Location: index.php');
		exit;
	}

	$trashedCount = 0;

	foreach ($selectedItems as $itemIndex) {
		$itemIndex = (int)$itemIndex;
		if (!isset($data[$contentType][$itemIndex])) continue;
		if (!admin_can_edit_item($data[$contentType][$itemIndex])) continue;

		$item          = $data[$contentType][$itemIndex];
		$effectiveSlug = sl_effective_slug($item);
		$found         = sl_find_in_index($contentType, $effectiveSlug);
		$fileSlug      = $found ? sl_file_slug($found[0]) : $effectiveSlug;

		if (sl_admin_trash_item($contentType, $fileSlug)) {
			$trashedCount++;
		}
	}

	$_SESSION['message'] = sprintf(__t('batch_moved_to_trash_count'), $trashedCount);

	// Redirect to content type list
	header('Location: index.php?type=' . urlencode($contentType));
	exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
		if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
			http_response_code(403);
			exit(__t('access_denied', 'Access denied.'));
		}
		// Process regular content form
		switch ($action) {
			case 'add':
				handleContentAddition();
				break;
			case 'edit':
				handleContentEdit();
				break;
		}
	}


// Handle AJAX request for content items (for menu builder)
if ($action === 'get_content_items') {
	// Make sure we're responding with proper headers
	header('Content-Type: application/json');
	
	if ($contentType && isset($data[$contentType])) {
		$items = [];
		foreach ($data[$contentType] as $item) {
			// Use custom slug if available, otherwise use default slug
			$slug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
			
			$items[] = [
				'title' => $item['title'],
				'slug' => $slug,
				'has_custom_slug' => !empty($item['custom_slug'])
			];
		}
		echo json_encode($items);
		exit;
	}
	echo json_encode([]);
	exit;
}

// Handle duplicate action via GET
if ($action === 'duplicate' && $contentType && isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
	http_response_code(403);
	exit(__t('access_denied', 'Access denied.'));
}
if ($action === 'duplicate' && $contentType && isset($data[$contentType][$index])) {
	$original = $data[$contentType][$index];
	$copy = $original;
	// Append " (copy)" to title, generate new slug, clear custom slug
	$copy['title']       = $original['title'] . ' ' . __t('duplicate_suffix', '(copy)');
	$copy['slug']        = sanitizeSlug($copy['title']);
	$copy['custom_slug'] = '';
	$copy['status']      = 'draft';
	$copy['publish_at']  = '';
	$copy['last_modified'] = date('Y-m-d H:i');
	$copy['date']        = date('Y-m-d H:i');
	// Ensure slug uniqueness
	$existingSlugs = array_map(fn($i) => !empty($i['custom_slug']) ? $i['custom_slug'] : ($i['slug'] ?? ''), $data[$contentType]);
	$base = $copy['slug']; $n = 2;
	while (in_array($copy['slug'], $existingSlugs)) { $copy['slug'] = $base . '-' . $n++; }
	$data[$contentType][] = $copy;
	saveData($data);
	$_SESSION['message'] = __t('content_duplicated', 'Item duplicated.');
	// If AJAX request, return JSON
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json');
		echo json_encode(['success' => true, 'message' => $_SESSION['message']]);
		exit;
	}
	header('Location: index.php?type=' . urlencode($contentType));
	exit;
}

// Handle delete action via GET — moves the item to trash instead of deleting it
if ($action === 'delete' && $contentType && isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
	http_response_code(403);
	exit(__t('access_denied', 'Access denied.'));
}
if ($action === 'delete' && $contentType && isset($data[$contentType][$index])) {
	$item          = $data[$contentType][$index];
	$effectiveSlug = sl_effective_slug($item);
	$found         = sl_find_in_index($contentType, $effectiveSlug);
	$fileSlug      = $found ? sl_file_slug($found[0]) : $effectiveSlug;

	sl_admin_trash_item($contentType, $fileSlug);

	// Store the message in session to display after redirect
	$_SESSION['message'] = __t('content_moved_to_trash');

	// Redirect back to the content type list view
	header('Location: index.php?type=' . urlencode($contentType));
	exit;
}

// Handle unpublish action via GET — pulls a published/scheduled item back
// off the site so it can be reworked. Status becomes 'unpublished', not
// 'draft': the two are shown differently (a never-published item vs. one
// that used to be live) even though both are hidden from the front end
// and treated the same way by autosave/trash. A pure status change: no
// content is touched, so no revision snapshot (nothing to diff against).
if ($action === 'unpublish' && $contentType && isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
	http_response_code(403);
	exit(__t('access_denied', 'Access denied.'));
}
if ($action === 'unpublish' && $contentType && isset($data[$contentType][$index])) {
	$item = $data[$contentType][$index];
	if (($item['status'] ?? 'published') !== 'unpublished') {
		$item['status']        = 'unpublished';
		$item['publish_at']    = '';
		$item['last_modified'] = date('Y-m-d H:i');

		$effectiveSlug = sl_effective_slug($item);
		$found         = sl_find_in_index($contentType, $effectiveSlug);
		$fileSlug      = $found ? sl_file_slug($found[0]) : $effectiveSlug;

		sl_admin_save_item($contentType, $fileSlug, $item);
		$indexEntry          = sl_admin_extract_index_entry($contentType, $item);
		$indexEntry['_file'] = $fileSlug;
		sl_admin_update_index($contentType, $indexEntry);

		$_SESSION['message'] = __t('content_unpublished');
	}
	header('Location: index.php?action=edit&type=' . urlencode($contentType) . '&index=' . $index);
	exit;
}

// Prepare data for edit form
$editItem  = null;
$revisions = [];
if ($action === 'edit' && $contentType && isset($data[$contentType][$index]) && admin_can_edit_item($data[$contentType][$index])) {
	$editItem = $data[$contentType][$index];

	$editEffectiveSlug = sl_effective_slug($editItem);
	$editFound         = sl_find_in_index($contentType, $editEffectiveSlug);
	$editFileSlug      = $editFound ? sl_file_slug($editFound[0]) : $editEffectiveSlug;
	$revisions          = sl_admin_list_revisions($contentType, $editFileSlug);
}


// Handle category & tag management
if ($action === 'manage_categories' || $action === 'manage_tags') {

	if (!isset($data['categories'])) $data['categories'] = [];
	if (!isset($data['tags']))       $data['tags']       = [];

	// --- Categories POST ---
	if (isset($_POST['category_action'])) {
		$categoryAction = $_POST['category_action'];

		if ($categoryAction === 'add' && isset($_POST['category_name']) && !empty($_POST['category_name'])) {
			$categoryName = trim($_POST['category_name']);
			$categoryParent = isset($_POST['category_parent']) ? trim($_POST['category_parent']) : '';
			if (!isset($data['categories'])) $data['categories'] = [];
			$newSlug = sanitizeSlug($categoryName);
			$entry = ['name' => $categoryName];
			// Only store parent if it references an existing category slug
			if (!empty($categoryParent) && isset($data['categories'][$categoryParent])) {
				$entry['parent'] = $categoryParent;
			}
			$data['categories'][$newSlug] = $entry;
			saveData($data);
			$_SESSION['message'] = sprintf(__t('category_added'), $categoryName);
			header('Location: index.php?action=manage_categories'); exit;
		}

		if ($categoryAction === 'purge_orphans') {
			$usedSlugs = [];
			foreach (['article', 'project', 'page'] as $ct) {
				if (!isset($data[$ct])) continue;
				foreach ($data[$ct] as $item) {
					if (!empty($item['category'])) $usedSlugs[sanitizeSlug($item['category'])] = true;
				}
			}
			$purged = 0;
			foreach (array_keys($data['categories']) as $slug) {
				if (!isset($usedSlugs[$slug])) {
					unset($data['categories'][$slug]);
					$purged++;
				}
			}
			saveData($data);
			$_SESSION['message'] = sprintf(__t('orphans_purged'), $purged);
			header('Location: index.php?action=manage_categories'); exit;
		}

		if ($categoryAction === 'merge' && isset($_POST['source_slug'], $_POST['target_slug'])) {
			$sourceSlug = $_POST['source_slug'];
			$targetSlug = $_POST['target_slug'];

			// Resolve target name: check the dedicated categories store first,
			// then fall back to scanning content items for inline-only categories
			// (categories that were never explicitly added/edited via the admin).
			$targetName = $data['categories'][$targetSlug]['name'] ?? null;
			if ($targetName === null) {
				foreach (['article', 'project'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as $item) {
						if (!empty($item['category']) && sanitizeSlug($item['category']) === $targetSlug) {
							$targetName = $item['category'];
							break 2;
						}
					}
				}
			}

			if ($sourceSlug !== $targetSlug && $targetName !== null) {
				foreach (['article', 'project', 'page'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as &$item) {
						if (isset($item['category']) && sanitizeSlug($item['category']) === $sourceSlug) {
							$item['category'] = $targetSlug;
						}
					}
					unset($item);
				}
				unset($data['categories'][$sourceSlug]);
				syncMenuUrlsForCategory($data, $sourceSlug, $targetName);
				saveData($data);
				$_SESSION['message'] = __t('cats_merged');
			}
			header('Location: index.php?action=manage_categories'); exit;
		}

		if ($categoryAction === 'edit' && isset($_POST['category_slug'], $_POST['category_name'])) {
			$oldSlug   = $_POST['category_slug'];
			$newName   = trim($_POST['category_name']);
			$newSlug   = sanitizeSlug($newName);
			$newParent = isset($_POST['category_parent']) ? trim($_POST['category_parent']) : '';
			$anyChanges = false;

			// Update the display name and/or slug in the categories store
			if (isset($data['categories'][$oldSlug])) {
				$existingData         = $data['categories'][$oldSlug];
				$existingData['name'] = $newName;
				if (!empty($newParent)) {
					$existingData['parent'] = $newParent;
				} else {
					unset($existingData['parent']);
				}
				unset($data['categories'][$oldSlug]);
				$data['categories'][$newSlug] = $existingData;
				$anyChanges = true;
			} else {
				$entry = ['name' => $newName];
				if (!empty($newParent)) $entry['parent'] = $newParent;
				$data['categories'][$newSlug] = $entry;
				$anyChanges = true;
			}

			// Only update item files when the slug itself changes.
			// A name-only change (same slug) requires no item updates.
			if ($anyChanges && $oldSlug !== $newSlug) {
				foreach (['article', 'project', 'page'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as &$item) {
						if (isset($item['category']) && sanitizeSlug($item['category']) === $oldSlug) {
							$item['category'] = $newSlug;
						}
					}
					unset($item);
				}
			}

			if ($anyChanges) { saveData($data); syncMenuUrlsForCategory($data, $oldSlug, $newName); $_SESSION['message'] = __t('category_updated'); }
			else { $_SESSION['message'] = __t('category_no_changes'); }
			header('Location: index.php?action=manage_categories'); exit;
		}
	}

	// --- Tags POST ---
	if (isset($_POST['tag_action'])) {
		$tagAction = $_POST['tag_action'];

		if ($tagAction === 'add' && isset($_POST['tag_name']) && !empty($_POST['tag_name'])) {
			$tagName = trim($_POST['tag_name']);
			if (!isset($data['tags'])) $data['tags'] = [];
			$data['tags'][sanitizeSlug($tagName)] = ['name' => $tagName];
			saveData($data);
			$_SESSION['message'] = sprintf(__t('tag_added'), $tagName);
			header('Location: index.php?action=manage_tags'); exit;
		}

		if ($tagAction === 'purge_orphans') {
			// Collect slugs used by actual content
			$usedSlugs = [];
			foreach (['article', 'project', 'page'] as $ct) {
				if (!isset($data[$ct])) continue;
				foreach ($data[$ct] as $item) {
					if (!isset($item['tags']) || !is_array($item['tags'])) continue;
					foreach ($item['tags'] as $tag) {
						$usedSlugs[sanitizeSlug($tag)] = true;
					}
				}
			}
			$purged = 0;
			foreach (array_keys($data['tags']) as $slug) {
				if (!isset($usedSlugs[$slug])) {
					unset($data['tags'][$slug]);
					$purged++;
				}
			}
			saveData($data);
			$_SESSION['message'] = sprintf(__t('orphans_purged'), $purged);
			header('Location: index.php?action=manage_tags'); exit;
		}

		if ($tagAction === 'merge' && isset($_POST['source_slug'], $_POST['target_slug'])) {
			$sourceSlug = $_POST['source_slug'];
			$targetSlug = $_POST['target_slug'];

			// Resolve target name: check the dedicated tags store first,
			// then fall back to scanning content items for inline-only tags
			// (tags that were never explicitly added/edited via the admin).
			$targetName = $data['tags'][$targetSlug]['name'] ?? null;
			if ($targetName === null) {
				foreach (['article', 'project'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as $item) {
						if (!isset($item['tags']) || !is_array($item['tags'])) continue;
						foreach ($item['tags'] as $tag) {
							if (sanitizeSlug($tag) === $targetSlug) {
								$targetName = $tag;
								break 3;
							}
						}
					}
				}
			}

			if ($sourceSlug !== $targetSlug && $targetName !== null) {
				foreach (['article', 'project', 'page'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as &$item) {
						if (!isset($item['tags']) || !is_array($item['tags'])) continue;
						$newTags = [];
						$hasTarget = false;
						foreach ($item['tags'] as $tag) {
							$s = sanitizeSlug($tag);
							if ($s === $sourceSlug) {
								if (!$hasTarget) { $newTags[] = $targetName; $hasTarget = true; }
							} elseif ($s === $targetSlug) {
								if (!$hasTarget) { $newTags[] = $tag; $hasTarget = true; }
							} else {
								$newTags[] = $tag;
							}
						}
						$item['tags'] = $newTags;
					}
					unset($item);
				}
				unset($data['tags'][$sourceSlug]);
				syncMenuUrlsForTag($sourceSlug, null);
				saveData($data);
				$_SESSION['message'] = __t('tags_merged');
			}
			header('Location: index.php?action=manage_tags'); exit;
		}

		if ($tagAction === 'edit' && isset($_POST['tag_slug'], $_POST['tag_name'])) {
			$oldSlug = $_POST['tag_slug'];
			$newName = trim($_POST['tag_name']);
			$newSlug = sanitizeSlug($newName);
			$anyChanges = false;

			// Update the display name and/or slug in the tags store
			if (isset($data['tags'][$oldSlug])) {
				$existingData         = $data['tags'][$oldSlug];
				$existingData['name'] = $newName;
				unset($data['tags'][$oldSlug]);
				$data['tags'][$newSlug] = $existingData;
				$anyChanges = true;
			} elseif ($oldSlug !== $newSlug) {
				// Orphan tag (not in store) with slug change — create store entry
				$data['tags'][$newSlug] = ['name' => $newName];
				$anyChanges = true;
			}

			// When the slug changes, update the stored slug in every item that uses it.
			// When only the display name changes (same slug), no item files need touching.
			if ($anyChanges && $oldSlug !== $newSlug) {
				foreach (['article', 'project', 'page'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as &$item) {
						if (!isset($item['tags']) || !is_array($item['tags'])) continue;
						foreach ($item['tags'] as &$tag) {
							if ($tag === $oldSlug) $tag = $newSlug;
						}
						unset($tag);
					}
					unset($item);
				}
			}

			if ($anyChanges) { saveData($data); syncMenuUrlsForTag($oldSlug, $newName); $_SESSION['message'] = __t('tag_updated'); }
			else { $_SESSION['message'] = __t('tag_no_changes'); }
			header('Location: index.php?action=manage_tags'); exit;
		}
	}

	// --- Category delete via GET ---
	if ($action === 'manage_categories' && isset($_GET['category_action']) && $_GET['category_action'] === 'delete' && isset($_GET['slug'])) {
		$categorySlug = $_GET['slug'];
		$anyChanges = false;
		foreach (['article', 'project', 'page'] as $ct) {
			if (isset($data[$ct])) {
				foreach ($data[$ct] as &$item) {
					if (isset($item['category']) && sanitizeSlug($item['category']) === $categorySlug) {
						unset($item['category']); $anyChanges = true;
					}
				}
				unset($item); // Break reference
			}
		}
		// Clear parent references pointing to the deleted category
		if (isset($data['categories'])) {
			foreach ($data['categories'] as &$cat) {
				if (isset($cat['parent']) && $cat['parent'] === $categorySlug) {
					unset($cat['parent']);
				}
			}
			unset($cat);
		}
		if (isset($data['categories'][$categorySlug])) { unset($data['categories'][$categorySlug]); $anyChanges = true; }
		saveData($data);
		$_SESSION['message'] = $anyChanges ? __t('category_deleted') : __t('category_no_changes');
		header('Location: index.php?action=manage_categories'); exit;
	}

	// --- Tag delete via GET ---
	if ($action === 'manage_tags' && isset($_GET['tag_action']) && $_GET['tag_action'] === 'delete' && isset($_GET['slug'])) {
		$tagSlug = $_GET['slug'];
		$anyChanges = false;
		foreach (['article', 'project', 'page'] as $ct) {
			if (isset($data[$ct])) {
				foreach ($data[$ct] as &$item) {
					if (isset($item['tags']) && is_array($item['tags'])) {
						$newTags = [];
						foreach ($item['tags'] as $tag) {
							if (sanitizeSlug($tag) !== $tagSlug) { $newTags[] = $tag; } else { $anyChanges = true; }
						}
						$item['tags'] = $newTags;
					}
				}
				unset($item); // Break reference
			}
		}
		if (isset($data['tags'][$tagSlug])) { unset($data['tags'][$tagSlug]); $anyChanges = true; }
		saveData($data);
		syncMenuUrlsForTag($tagSlug, null);
		$_SESSION['message'] = $anyChanges ? __t('tag_deleted') : __t('tag_no_changes');
		header('Location: index.php?action=manage_tags'); exit;
	}
}

// Handle drafts actions — data/drafts/*.json only ever holds a pending
// autosave snapshot layered on top of an already-published/scheduled item
// (see autosave.php). A never-published item now materializes as a real
// content item on its first autosave, so there is no "restore into a blank
// add form" case here anymore — restoring always means "load this pending
// snapshot into that item's normal edit page".
if ($action === 'drafts') {
	$draftsDir = sl_admin_drafts_dir();
	$draftSubAction = $_GET['draft_action'] ?? '';

	if ($draftSubAction === 'restore' && isset($_GET['id'])) {
		$draftFile = $draftsDir . '/' . basename($_GET['id']) . '.json';
		if (file_exists($draftFile)) {
			$draftData = json_decode(file_get_contents($draftFile), true);
			if ($draftData && !admin_can_edit_draft($draftData)) {
				http_response_code(403);
				exit(__t('access_denied', 'Access denied.'));
			}
			if ($draftData) {
				$_SESSION['draft_data'] = $draftData;
				header('Location: index.php?action=edit&type=' . urlencode($draftData['type']) . '&index=' . $draftData['index'] . '&restore=1');
				exit;
			}
		}
		$_SESSION['error'] = __t('draft_restore_failed');
		header('Location: index.php');
		exit;
	}

	// Discard a pending snapshot without applying it — the published/scheduled
	// item it was layered on top of is untouched either way, so this is a
	// simple, permanent delete (no trash, nothing "content"-like is lost).
	if ($draftSubAction === 'delete' && isset($_GET['id'])) {
		$draftFile = $draftsDir . '/' . basename($_GET['id']) . '.json';
		$draftData = file_exists($draftFile) ? json_decode(file_get_contents($draftFile), true) : null;
		if ($draftData && !admin_can_edit_draft($draftData)) {
			http_response_code(403);
			exit(__t('access_denied', 'Access denied.'));
		}
		$_backTo = 'index.php';
		if ($draftData) {
			unlink($draftFile);
			$_SESSION['message'] = __t('pending_discarded');
			$_backTo = 'index.php?action=edit&type=' . urlencode($draftData['type']) . '&index=' . $draftData['index'];
		} else {
			$_SESSION['error'] = __t('draft_not_found');
		}
		header('Location: ' . $_backTo);
		exit;
	}

	header('Location: index.php');
	exit;
}

// Handle trash actions
if ($action === 'trash') {
	$trashSubAction = $_GET['trash_action'] ?? '';
	$trashTypes = ['article', 'page', 'project'];

	// Lazy sweep — no cron in this codebase, so expired items are purged
	// whenever the trash view is opened.
	sl_admin_purge_expired_trash(30);

	// Trash entries carry the same index fields as live items (including
	// author_id), so ownership can be checked the same way as content.php's
	// other guards. Looks up by type+file since that's all GET/POST carry.
	$_trash_find_entry = function (string $type, string $file) use ($trashTypes) {
		if (!in_array($type, $trashTypes, true)) return null;
		foreach (sl_admin_load_trash_index($type) as $entry) {
			if (($entry['_file'] ?? '') === $file) return $entry;
		}
		return null;
	};

	if ($trashSubAction === 'restore' && isset($_GET['type'], $_GET['file']) && in_array($_GET['type'], $trashTypes, true)) {
		$_trashEntry = $_trash_find_entry($_GET['type'], basename($_GET['file']));
		if ($_trashEntry === null || !admin_can_edit_item($_trashEntry)) {
			http_response_code(403);
			exit(__t('access_denied', 'Access denied.'));
		}
		if (sl_admin_restore_trashed_item($_GET['type'], basename($_GET['file']))) {
			$_SESSION['message'] = __t('content_restored');
			sl_admin_log_activity('item_restored_from_trash', $_GET['type'] . ':' . basename($_GET['file']));
		} else {
			$_SESSION['error'] = __t('restore_failed');
		}
		header('Location: index.php?action=trash');
		exit;
	}

	if ($trashSubAction === 'purge' && isset($_GET['type'], $_GET['file']) && in_array($_GET['type'], $trashTypes, true)) {
		$_trashEntry = $_trash_find_entry($_GET['type'], basename($_GET['file']));
		if ($_trashEntry === null || !admin_can_edit_item($_trashEntry)) {
			http_response_code(403);
			exit(__t('access_denied', 'Access denied.'));
		}
		sl_admin_purge_trashed_item($_GET['type'], basename($_GET['file']));
		$_SESSION['message'] = __t('item_purged');
		header('Location: index.php?action=trash');
		exit;
	}

	if ($trashSubAction === 'batch_restore' && isset($_POST['selected_items'])) {
		$selected = json_decode($_POST['selected_items'], true);
		$restoredCount = 0;
		if (is_array($selected)) {
			foreach ($selected as $sel) {
				$selType = $sel['type'] ?? '';
				$selFile = $sel['file'] ?? '';
				if ($selFile === '') continue;
				$_trashEntry = $_trash_find_entry($selType, basename($selFile));
				if ($_trashEntry === null || !admin_can_edit_item($_trashEntry)) continue;
				if (sl_admin_restore_trashed_item($selType, basename($selFile))) {
					$restoredCount++;
				}
			}
		}
		if ($restoredCount > 0) sl_admin_log_activity('item_restored_from_trash', $restoredCount . ' items (batch)');
		$_SESSION['message'] = sprintf(__t('batch_restored_count'), $restoredCount);
		header('Location: index.php?action=trash');
		exit;
	}

	if ($trashSubAction === 'batch_purge' && isset($_POST['selected_items'])) {
		$selected = json_decode($_POST['selected_items'], true);
		$purgedCount = 0;
		if (is_array($selected)) {
			foreach ($selected as $sel) {
				$selType = $sel['type'] ?? '';
				$selFile = $sel['file'] ?? '';
				if ($selFile === '') continue;
				$_trashEntry = $_trash_find_entry($selType, basename($selFile));
				if ($_trashEntry === null || !admin_can_edit_item($_trashEntry)) continue;
				sl_admin_purge_trashed_item($selType, basename($selFile));
				$purgedCount++;
			}
		}
		$_SESSION['message'] = sprintf(__t('batch_purged_count'), $purgedCount);
		header('Location: index.php?action=trash');
		exit;
	}

	if ($trashSubAction === 'purge_all') {
		if (admin_can_manage_all_content()) {
			$purgedCount = sl_admin_purge_all_trash();
		} else {
			// Author: only purge trashed items they own, not everyone's.
			$purgedCount = 0;
			foreach ($trashTypes as $trashType) {
				foreach (sl_admin_load_trash_index($trashType) as $entry) {
					if (!admin_can_edit_item($entry)) continue;
					if (sl_admin_purge_trashed_item($trashType, $entry['_file'] ?? '')) $purgedCount++;
				}
			}
		}
		$_SESSION['message'] = sprintf(__t('batch_purged_count'), $purgedCount);
		header('Location: index.php?action=trash');
		exit;
	}

	// Build the trash list for the template — merge all types, newest first
	$trashItems = [];
	foreach ($trashTypes as $trashType) {
		foreach (sl_admin_load_trash_index($trashType) as $entry) {
			if (!admin_can_edit_item($entry)) continue;
			$entry['type'] = $trashType;
			$trashItems[]  = $entry;
		}
	}
	usort($trashItems, fn($a, $b) => ($b['trashed_at'] ?? 0) <=> ($a['trashed_at'] ?? 0));
}

// Handle revision diff/restore/delete actions
if (($action === 'revision_diff' || $action === 'revision_restore' || $action === 'delete_revision') && $contentType && isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
	http_response_code(403);
	exit(__t('access_denied', 'Access denied.'));
}
if (($action === 'revision_diff' || $action === 'revision_restore' || $action === 'delete_revision') && $contentType && isset($data[$contentType][$index])) {
	$revTimestamp = isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : 0;

	$currentItem          = $data[$contentType][$index];
	$revEffectiveSlug     = sl_effective_slug($currentItem);
	$revFound             = sl_find_in_index($contentType, $revEffectiveSlug);
	$revFileSlug          = $revFound ? sl_file_slug($revFound[0]) : $revEffectiveSlug;

	if ($action === 'revision_restore') {
		if ($revTimestamp > 0 && sl_admin_restore_revision($contentType, $revFileSlug, $revTimestamp)) {
			$_SESSION['message'] = __t('revision_restored');
			sl_admin_log_activity('revision_restored', $contentType . ':' . $revFileSlug);
		} else {
			$_SESSION['error'] = __t('revision_restore_failed');
		}
		header('Location: index.php?action=edit&type=' . urlencode($contentType) . '&index=' . $index);
		exit;
	}

	if ($action === 'delete_revision') {
		if ($revTimestamp > 0 && sl_admin_delete_revision($contentType, $revFileSlug, $revTimestamp)) {
			$_SESSION['message'] = __t('revision_deleted');
			sl_admin_log_activity('revision_deleted', $contentType . ':' . $revFileSlug);
		} else {
			$_SESSION['error'] = __t('revision_delete_failed');
		}
		header('Location: index.php?action=edit&type=' . urlencode($contentType) . '&index=' . $index);
		exit;
	}

	// revision_diff — build the comparison for the template
	$revisionItem = $revTimestamp > 0 ? sl_admin_load_revision($contentType, $revFileSlug, $revTimestamp) : null;
	if ($revisionItem === null) {
		$_SESSION['error'] = __t('revision_not_found');
		header('Location: index.php?action=edit&type=' . urlencode($contentType) . '&index=' . $index);
		exit;
	}
	$revisionTimestamp = $revTimestamp;
}

// Handle the pending-autosave diff view — shows what differs between the
// live saved item and a not-yet-applied Case B snapshot (see autosave.php),
// so clicking the "unsaved changes" badge explains itself instead of just
// dumping the pending content into the editor with no context.
if ($action === 'pending_diff' && $contentType && isset($data[$contentType][$index]) && !admin_can_edit_item($data[$contentType][$index])) {
	http_response_code(403);
	exit(__t('access_denied', 'Access denied.'));
}
if ($action === 'pending_diff' && $contentType && isset($data[$contentType][$index]) && isset($_GET['draft_id'])) {
	$pendingDraftId   = basename($_GET['draft_id']);
	$pendingDraftFile = sl_admin_drafts_dir() . '/' . $pendingDraftId . '.json';
	$pendingDraftData = file_exists($pendingDraftFile) ? json_decode(file_get_contents($pendingDraftFile), true) : null;

	if ($pendingDraftData === null || !admin_can_edit_draft($pendingDraftData) || (int)($pendingDraftData['index'] ?? -1) !== $index) {
		$_SESSION['error'] = __t('draft_not_found');
		header('Location: index.php?action=edit&type=' . urlencode($contentType) . '&index=' . $index);
		exit;
	}

	$currentItem      = $data[$contentType][$index];
	$pendingItem      = $pendingDraftData;
	$pendingTimestamp = $pendingDraftData['timestamp'] ?? time();
}

// Display appropriate content template based on action
switch ($action) {
	case 'add':
		include 'templates/content-add.php';
		break;
	case 'edit':
		include 'templates/content-edit.php';
		break;
	case 'trash':
		include 'templates/trash.php';
		break;
	case 'revision_diff':
		include 'templates/revision-diff.php';
		break;
	case 'pending_diff':
		include 'templates/pending-diff.php';
		break;
	case 'manage_categories':
		include 'templates/categories-manage.php';
		break;
	case 'manage_tags':
		include 'templates/tags-manage.php';
		break;
	case 'manage_themes':
		include 'templates/theme-manager.php';
		break;
	default:
		// List view
		if ($contentType) {
			include 'templates/content-list.php';
		}
}

/**
 * Handle adding new content
 */
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

	// Status is chosen explicitly via the sidebar dropdown. "Scheduled" still needs
	// a valid future publish_at to stick — otherwise it falls back to published,
	// same safety net the old auto-detection relied on.
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

	// Find the index of the newly added item
	$newIndex = count($data[$formContentType]) - 1;

	$_SESSION['message'] = __t('content_added');
	// Redirect to edit page for the new content
	header('Location: index.php?action=edit&type=' . $formContentType . '&index=' . $newIndex . '&message=show');
	exit;
}

/**
 * Handle editing existing content
 */
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

	// Status is chosen explicitly via the sidebar dropdown. "Scheduled" still needs
	// a valid future publish_at to stick — otherwise it falls back to published,
	// same safety net the old auto-detection relied on.
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

	// Capture old slug/category BEFORE overwrite, for menu sync
	$oldMenuSlug = !empty($existingItem['custom_slug'])
		? $existingItem['custom_slug']
		: ($existingItem['slug'] ?? '');
	$oldMenuCategory = $existingItem['category'] ?? '';

	// Snapshot the pre-edit state before it gets overwritten
	$oldEffectiveSlug = sl_effective_slug($existingItem);
	$oldFound         = sl_find_in_index($contentType, $oldEffectiveSlug);
	$oldFileSlug      = $oldFound ? sl_file_slug($oldFound[0]) : $oldEffectiveSlug;
	sl_admin_snapshot_revision($contentType, $oldFileSlug, $existingItem);

	$data[$contentType][$index] = $updatedItem;
	saveData($data);

	// A slug change renames the underlying file — move revision history
	// along with it, or it silently orphans under the old filename.
	$newMenuSlug = sl_effective_slug($updatedItem);
	$newFound    = sl_find_in_index($contentType, $newMenuSlug);
	$newFileSlug = $newFound ? sl_file_slug($newFound[0]) : $newMenuSlug;
	if ($newFileSlug !== $oldFileSlug) {
		sl_admin_migrate_revisions($contentType, $oldFileSlug, $newFileSlug);
	}

	// Sync menu URLs if slug or category changed
	if ($oldMenuSlug !== $newMenuSlug || $oldMenuCategory !== $updatedItem['category']) {
		syncMenuUrls($contentType, $oldMenuSlug, $newMenuSlug, $updatedItem['category']);
	}

	// Delete ALL drafts related to this item (by type and index OR title) — these
	// are Case B pending-autosave snapshots (see autosave.php); an explicit save
	// supersedes them.
	$draftsDir = sl_admin_drafts_dir();
	if (is_dir($draftsDir)) {
		$files = glob($draftsDir . '/*.json');
		foreach ($files as $file) {
			$draftData = json_decode(file_get_contents($file), true);
			if ($draftData && $draftData['type'] === $contentType) {
				$matchesByIndex = isset($draftData['index']) && $draftData['index'] == $index;
				$matchesByTitle = strtolower(trim($draftData['title'])) === strtolower(trim($updatedItem['title']));
				if ($matchesByIndex || $matchesByTitle) {
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

/**
 * Sync menu item URLs in config.json when a post's slug or category changes
 */
function syncMenuUrls($contentType, $oldSlug, $newSlug, $newCategory) {
	$settingsFile = '../config.json';
	if (!file_exists($settingsFile)) return;

	$settings = json_decode(file_get_contents($settingsFile), true);
	if (!is_array($settings) || empty($settings['main_menu'])) return;

	// Load categories only — all getCategoryPath() needs
	$data = ['categories' => sl_load_categories()];

	$changed = false;
	$categorySlug = !empty($newCategory) ? sanitizeSlug($newCategory) : '';

	// Resolve full hierarchical category path for URL construction
	$catPath = !empty($categorySlug) ? getCategoryPath($categorySlug, $data) : '';

	foreach ($settings['main_menu'] as &$item) {
		if (
			isset($item['content_type']) && $item['content_type'] === $contentType &&
			isset($item['content_slug']) && $item['content_slug'] === $oldSlug
		) {
			if ($contentType === 'article' && !empty($catPath)) {
				$newUrl = $catPath . '/' . $newSlug . '/';
			} elseif ($contentType === 'project' && !empty($catPath)) {
				$newUrl = 'project/' . $catPath . '/' . $newSlug . '/';
			} elseif ($contentType === 'page' && !empty($catPath)) {
				$newUrl = $catPath . '/' . $newSlug . '/';
			} else {
				$newUrl = $contentType . '/' . $newSlug . '/';
			}

			$item['url']          = $newUrl;
			$item['content_slug'] = $newSlug;
			if (array_key_exists('content_category', $item)) {
				$item['content_category'] = $newCategory;
			}
			$changed = true;
		}
	}
	unset($item);

	if ($changed) {
		file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}

/**
 * Handle image upload for content
 * @param array $file The uploaded file data
 * @param string $contentType The type of content (article, page, project)
 * @return string|false The path to the uploaded image or false on failure
 */
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

// sanitizeFileName() lives in includes/admin-functions.php, loaded by
// admin/index.php before this file is included.