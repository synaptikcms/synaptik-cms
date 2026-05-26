<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Load application settings
$appSettings = admin_load_settings();

// Define available content types
$contentTypes = ['article', 'page', 'project'];

// Handle actions (add, edit, delete, list)
$action = $_GET['action'] ?? 'list';
$contentType = $_GET['type'] ?? '';
$index = isset($_GET['index']) ? (int)$_GET['index'] : null;

// Load the database
$data = loadData();

// Handle batch deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && $_POST['batch_action'] === 'delete') {
	error_log('Batch deletion requested');
	
	$contentType = $_POST['content_type'] ?? '';
	$selectedItemsJson = $_POST['selected_items'] ?? '[]';
	
	error_log('Content type: ' . $contentType);
	error_log('Selected items JSON: ' . $selectedItemsJson);
	
	// Decode the JSON data
	$selectedItems = json_decode($selectedItemsJson, true);
	
	if (json_last_error() !== JSON_ERROR_NONE) {
		error_log('JSON decode error: ' . json_last_error_msg());
		$_SESSION['error'] = __t('batch_delete_invalid_data');
		header('Location: index.php');
		exit;
	}
	
	error_log('Decoded selected items: ' . print_r($selectedItems, true));
	
	if (!empty($contentType) && !empty($selectedItems)) {
	// Load via wrapper — uses split-file architecture
	$data = loadData();
	
	if (!isset($data[$contentType])) {
		error_log('Content type not found in data: ' . $contentType);
		$_SESSION['error'] = __t('content_type_not_found');
		header('Location: index.php');
		exit;
	}
	
	// Sort indexes in descending order to avoid index shifting problems
	rsort($selectedItems);
	$deletedCount = 0;
	
	foreach ($selectedItems as $index) {
		$index = (int)$index;
		
		// Check if index exists
		if (isset($data[$contentType][$index])) {
			// Remove the item
			unset($data[$contentType][$index]);
			$deletedCount++;
			error_log("Deleted item at index $index");
		} else {
			error_log("Index $index not found in $contentType");
		}
	}
	
	// Re-index the array to maintain sequential keys
	$data[$contentType] = array_values($data[$contentType]);
	
	// Save via wrapper — distributes to individual files
	saveData($data);
		
		// Set success message
		$_SESSION['message'] = sprintf(__t('batch_deleted_count'), $deletedCount, strtolower($contentType));
		
		error_log("Batch deletion complete: $deletedCount items deleted");
		
		// Redirect to content type list
		header('Location: index.php?type=' . urlencode($contentType));
		exit;
	} else {
		error_log('Invalid parameters for batch deletion');
		$_SESSION['error'] = __t('batch_delete_invalid_params');
		header('Location: index.php');
		exit;
	}
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Handle delete action via GET
if ($action === 'delete' && $contentType && isset($data[$contentType][$index])) {
	unset($data[$contentType][$index]);
	$data[$contentType] = array_values($data[$contentType]); // Re-index the array
	saveData($data);
	
	// Store the message in session to display after redirect
	$_SESSION['message'] = __t('content_deleted');
	
	// Redirect back to the content type list view
	header('Location: index.php?type=' . urlencode($contentType));
	exit;
}

// Prepare data for edit form
$editItem = null;
if ($action === 'edit' && $contentType && isset($data[$contentType][$index])) {
	$editItem = $data[$contentType][$index];
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
			if ($sourceSlug !== $targetSlug && isset($data['categories'][$targetSlug])) {
				$targetName = $data['categories'][$targetSlug]['name'];
				foreach (['article', 'project', 'page'] as $ct) {
					if (!isset($data[$ct])) continue;
					foreach ($data[$ct] as &$item) {
						if (isset($item['category']) && sanitizeSlug($item['category']) === $sourceSlug) {
							$item['category'] = $targetName;
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
			$oldSlug = $_POST['category_slug'];
			$newName = trim($_POST['category_name']);
			$newSlug = sanitizeSlug($newName);
			$newParent = isset($_POST['category_parent']) ? trim($_POST['category_parent']) : '';
			$anyChanges = false;
			foreach (['article', 'project', 'page'] as $ct) {
				if (isset($data[$ct])) {
					foreach ($data[$ct] as &$item) {
						if (isset($item['category']) && sanitizeSlug($item['category']) === $oldSlug) {
							$item['category'] = $newName; $anyChanges = true;
						}
					}
					unset($item);
				}
			}
			if (isset($data['categories'][$oldSlug])) {
				// Category exists in the dedicated store — update in place.
				$existingData = $data['categories'][$oldSlug];
				$existingData['name'] = $newName;
				// Persist parent regardless of whether the parent itself is in the
				// dedicated store (it may be an inline-created "ghost" category too).
				if (!empty($newParent)) {
					$existingData['parent'] = $newParent;
				} else {
					unset($existingData['parent']);
				}
				unset($data['categories'][$oldSlug]);
				$data['categories'][$newSlug] = $existingData;
				$anyChanges = true;
			} else {
				// Category was created inline (e.g. via content-add) and has no
				// entry in $data['categories'] yet. Upsert it unconditionally so
				// that name and parent changes are always persisted, not only when
				// the slug happens to change.
				$entry = ['name' => $newName];
				if (!empty($newParent)) {
					$entry['parent'] = $newParent;
				}
				$data['categories'][$newSlug] = $entry;
				$anyChanges = true;
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
			if ($sourceSlug !== $targetSlug && isset($data['tags'][$targetSlug])) {
				$targetName = $data['tags'][$targetSlug]['name'];
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
			foreach (['article', 'project', 'page'] as $ct) {
				if (isset($data[$ct])) {
					foreach ($data[$ct] as &$item) {
						if (isset($item['tags']) && is_array($item['tags'])) {
							foreach ($item['tags'] as &$tag) {
								if (sanitizeSlug($tag) === $oldSlug) { $tag = $newName; $anyChanges = true; }
							}
							unset($tag);
						}
					}
					unset($item);
				}
			}
			if (isset($data['tags'][$oldSlug])) {
				$existingData = $data['tags'][$oldSlug];
				$existingData['name'] = $newName;
				unset($data['tags'][$oldSlug]);
				$data['tags'][$newSlug] = $existingData;
				$anyChanges = true;
			} elseif ($oldSlug !== $newSlug) {
				$data['tags'][$newSlug] = ['name' => $newName];
				$anyChanges = true;
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

// Handle drafts actions
if ($action === 'drafts') {
	$draftsDir = 'drafts';
	$draftSubAction = $_GET['draft_action'] ?? '';

	if ($draftSubAction === 'delete' && isset($_GET['id'])) {
		$draftFile = $draftsDir . '/' . basename($_GET['id']) . '.json';
		if (file_exists($draftFile)) {
			unlink($draftFile);
			$_SESSION['message'] = __t('draft_deleted');
		} else {
			$_SESSION['error'] = __t('draft_not_found');
		}
		header('Location: index.php?action=drafts');
		exit;
	}

	if ($draftSubAction === 'restore' && isset($_GET['id'])) {
		$draftFile = $draftsDir . '/' . basename($_GET['id']) . '.json';
		if (file_exists($draftFile)) {
			$draftData = json_decode(file_get_contents($draftFile), true);
			if ($draftData) {
				$_SESSION['draft_data'] = $draftData;
				if ($draftData['index'] >= 0) {
					header('Location: index.php?action=edit&type=' . urlencode($draftData['type']) . '&index=' . $draftData['index'] . '&restore=1');
				} else {
					header('Location: index.php?action=add&type=' . urlencode($draftData['type']) . '&restore=1');
				}
				exit;
			}
		}
		$_SESSION['error'] = __t('draft_restore_failed');
		header('Location: index.php?action=drafts');
		exit;
	}

	if ($draftSubAction === 'batch_delete' && isset($_POST['selected_drafts'])) {
		$deletedCount = 0;
		if (is_array($_POST['selected_drafts']) && !empty($_POST['selected_drafts'])) {
			foreach ($_POST['selected_drafts'] as $draftId) {
				$draftFile = $draftsDir . '/' . basename($draftId) . '.json';
				if (file_exists($draftFile)) { unlink($draftFile); $deletedCount++; }
			}
			$_SESSION['message'] = sprintf(__t('drafts_deleted_count'), $deletedCount);
		}
		header('Location: index.php?action=drafts');
		exit;
	}

	if ($draftSubAction === 'purge_all') {
		$deletedCount = 0;
		if (is_dir($draftsDir)) {
			foreach (glob($draftsDir . '/*.json') as $file) {
				if (unlink($file)) $deletedCount++;
			}
			$_SESSION['message'] = sprintf(__t('drafts_deleted_count'), $deletedCount);
		}
		header('Location: index.php?action=drafts');
		exit;
	}

	// Build drafts list for the template
	$drafts = [];
	if (file_exists($draftsDir)) {
		foreach (glob($draftsDir . '/*.json') as $file) {
			$draftData = json_decode(file_get_contents($file), true);
			if ($draftData) $drafts[] = $draftData;
		}
		usort($drafts, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
	}
}

// Display appropriate content template based on action
switch ($action) {
	case 'add':
		include 'templates/content-add.php';
		break;
	case 'edit':
		include 'templates/content-edit.php';
		break;
	case 'drafts':
		include 'templates/drafts.php';
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
	
	// Extract common form data
	$formContentType = $_POST['type'] ?? '';
	$title = $_POST['title'] ?? '';
	
	$contentFormat = in_array($_POST['content_format'] ?? 'html', ['html', 'markdown']) 
		? $_POST['content_format'] : 'html';
	$content = isset($_POST['content']) && !empty($_POST['content']) 
		? ($contentFormat === 'markdown' ? $_POST['content'] : admin_purify_html($_POST['content'])) 
		: '';
	
	// Format the HTML
	// $content = format_html_indentation($content);
	$slug = sanitizeSlug($title);
	
	// Use custom slug if provided
	if (!empty($_POST['custom_slug'])) {
		$customSlug = sanitizeSlug($_POST['custom_slug'], true);
	} else {
		$customSlug = '';
	}

	// Deduplicate slug against existing items of the same type.
	// If the effective slug (custom_slug ?: slug) already exists, append -2, -3, etc.
	// This prevents a same-title article from overwriting an existing file in the data layer.
	if (in_array($formContentType, $contentTypes) && !empty($data[$formContentType])) {
		$existingSlugs = array_map(function ($item) {
			return !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
		}, $data[$formContentType]);

		$effectiveSlug = !empty($customSlug) ? $customSlug : $slug;

		if (in_array($effectiveSlug, $existingSlugs)) {
			$base = $effectiveSlug;
			$n    = 2;
			while (in_array($base . '-' . $n, $existingSlugs)) {
				$n++;
			}
			$uniqueSlug = $base . '-' . $n;

			// Apply the unique suffix to whichever field drives the effective slug
			if (!empty($customSlug)) {
				$customSlug = $uniqueSlug;
			} else {
				$slug = $uniqueSlug;
			}

			// Notify the admin that the slug was automatically renamed
			$_SESSION['notice'] = sprintf(
				__t('slug_auto_renamed', 'A duplicate slug was detected. The URL slug has been automatically renamed to "%s".'),
				$uniqueSlug
			);
		}
	}

	// Additional fields for projects
	$date = $_POST['date'] ?? date('Y-m-d');
	
	// Handle tags
	$tags = [];
	if (isset($_POST['tags']) && !empty($_POST['tags'])) {
		$tagList = explode(',', $_POST['tags']);
		foreach ($tagList as $tag) {
			$trimmedTag = trim($tag);
			if (!empty($trimmedTag)) {
				$tags[] = $trimmedTag;
			}
		}
	}
	
	// Handle category
	$category = '';
	if (isset($_POST['category']) && trim($_POST['category']) !== '') {
		$category = trim($_POST['category']);
	}
	
	if (!empty($title) && !empty($content) && in_array($formContentType, $contentTypes)) {
		$newItem = [
			'title' => $title,
				'slug' => $slug,
				'custom_slug' => $customSlug,
				'content' => $content,
				// SEO fields: store raw — htmlspecialchars() is applied at output time only
				'meta_title' => trim($_POST['meta_title'] ?? ''),
				'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
				'meta_description' => trim($_POST['meta_description'] ?? ''),
				'canonical_url' => trim($_POST['canonical_url'] ?? ''),
				'og_title' => trim($_POST['og_title'] ?? ''),
				'og_description' => trim($_POST['og_description'] ?? ''),
				'og_image' => trim($_POST['og_image'] ?? ''),
				'schema_type' => trim($_POST['schema_type'] ?? ''),
				'show_featured_image' => isset($_POST['show_featured_image']) ? true : false,
				'show_date' => isset($_POST['show_date']) ? true : false,
				'show_title' => isset($_POST['show_title']) ? true : false,
				'gallery_layout' => $_POST['gallery_layout'] ?? 'grid',
				'category' => $category,
				'tags' => $tags,
				'show_tags_at_bottom' => isset($_POST['show_tags_at_bottom']) ? true : false,
			];
		
		// Handle image upload logic
		if (!empty($_POST['selected_image_path'])) {
			// Selected image path handling
			$selectedImagePath = $_POST['selected_image_path'];
			// Make sure the path is properly formatted
			if (strpos($selectedImagePath, 'files/') !== 0) {
				$selectedImagePath = 'files/' . ltrim($selectedImagePath, '/');
			}
			$newItem['image'] = $selectedImagePath;
		} elseif (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
			// Handle file upload - implement handleImageUpload function
			$uploadedImagePath = handleImageUpload($_FILES['image'], $formContentType);
			if ($uploadedImagePath) {
				$newItem['image'] = $uploadedImagePath;
			}
		}
		
		// Add content type specific fields
		if ($formContentType === 'project') {
			$newItem['date'] = $date;
			$newItem['description'] = htmlspecialchars($_POST['description'] ?? '');
			// $newItem['description'] = $_POST['description'] ?? '';
		}
		
		// Add article/page-specific fields
		if ($formContentType === 'article' || $formContentType === 'page') {
			$newItem['date'] = $date;
		}
		// Save article summary (replaces auto-generated excerpt in cards)
		if ($formContentType === 'article') {
			$newItem['summary'] = trim($_POST['summary'] ?? '');
		}
		// Save page template (pages only)
		if ($formContentType === 'page') {
			$newItem['page_template'] = trim($_POST['page_template'] ?? '');
		}
		
		// Show on homepage (articles and projects only)
		if ($formContentType === 'article' || $formContentType === 'project') {
			$newItem['show_on_homepage'] = isset($_POST['show_on_homepage']) ? true : false;
		}
		
		// Show in menu (all content types)
		$newItem['show_in_menu'] = isset($_POST['show_in_menu']) ? true : false;
		$newItem['menu_order'] = isset($_POST['menu_order']) ? max(0, min(999, (int)$_POST['menu_order'])) : 0;

		// Content format: html (WYSIWYG) or markdown
		$newItem['content_format'] = in_array($_POST['content_format'] ?? 'html', ['html', 'markdown'])
			? $_POST['content_format'] : 'html';

		// Scheduling: normalize datetime-local format (browser sends "Y-m-dTH:i", store as "Y-m-d H:i")
		$publishAt = trim(str_replace('T', ' ', $_POST['publish_at'] ?? ''));
		if ($publishAt !== '' && ($publishTs = strtotime($publishAt)) !== false && $publishTs > time()) {
			$newItem['status']     = 'scheduled';
			$newItem['publish_at'] = $publishAt;
		} else {
			$newItem['status']     = 'published';
			$newItem['publish_at'] = $publishAt;
		}
		
		// Handle gallery images
		if (isset($_POST['gallery']) && is_array($_POST['gallery'])) {
			$galleryItems = [];
			foreach ($_POST['gallery'] as $galleryItem) {
				// Validate and sanitize gallery item
				if (!empty($galleryItem['src'])) {
					$galleryItems[] = [
						'src' => htmlspecialchars($galleryItem['src']),
						'caption' => !empty($galleryItem['caption']) ? $galleryItem['caption'] : ''
					];
				}
			}
			
			// Only add gallery if there are valid items
			if (!empty($galleryItems)) {
				$newItem['gallery'] = $galleryItems;
			}
		}
		

		// Handle named inline galleries
		if (isset($_POST['galleries']) && is_array($_POST['galleries'])) {
			$galleries = [];
			foreach ($_POST['galleries'] as $gIdx => $galleryData) {
				$images = [];
				if (!empty($galleryData['images']) && is_array($galleryData['images'])) {
					foreach ($galleryData['images'] as $img) {
						if (!empty($img['src'])) {
							$images[] = [
								'src'      => htmlspecialchars($img['src']),
								'caption'  => $img['caption'] ?? '',
								'alt_text' => $img['alt_text'] ?? '',
							];
						}
					}
				}
				$galleries[] = [
					'label'  => $galleryData['label'] ?? ('Galerie ' . $gIdx),
					'layout' => in_array($galleryData['layout'] ?? 'grid', ['grid', 'masonry', 'justified', 'carousel'])
								? $galleryData['layout'] : 'grid',
					'images' => $images,
				];
			}
			if (!empty($galleries)) {
				$newItem['galleries'] = $galleries;
			}
		}

		$data[$formContentType][] = $newItem;
		saveData($data);
		
		// Delete ALL drafts related to this item (by type and title)
		$draftsDir = 'drafts';
		if (is_dir($draftsDir)) {
			$files = glob($draftsDir . '/*.json');
			foreach ($files as $file) {
				$draftData = json_decode(file_get_contents($file), true);
				if ($draftData && 
					$draftData['type'] === $formContentType && 
					strtolower(trim($draftData['title'])) === strtolower(trim($title)) &&
					(!isset($draftData['index']) || $draftData['index'] === -1)) {
					unlink($file);
				}
			}
		}
		
		// Find the index of the newly added item
		$newIndex = count($data[$formContentType]) - 1;
		
		$_SESSION['message'] = __t('content_added');
		// Redirect to edit page for the new content
		header('Location: index.php?action=edit&type=' . $formContentType . '&index=' . $newIndex . '&message=show');
		exit;
	} else {
		// Error handling - don't redirect, keep data for resubmission
		$_SESSION['error'] = __t('fill_required_fields');
		
		// Store the submitted form data to repopulate the form
		$_SESSION['form_data'] = $_POST;
	}
}

/**
 * Handle editing existing content
 */
function handleContentEdit() {
	global $data, $index, $contentType;
	
	error_log("handleContentEdit - Type: $contentType, Index: $index");
	
	// Extract common form data
	$title = $_POST['title'] ?? '';
	
	$submittedFormat = in_array($_POST['content_format'] ?? 'html', ['html', 'markdown']) 
		? $_POST['content_format'] : 'html';
	$content = isset($_POST['content']) 
		? ($submittedFormat === 'markdown' ? $_POST['content'] : admin_purify_html($_POST['content'])) 
		: '';
	
	if (!empty($title) && !empty($content) && isset($data[$contentType][$index])) {
		// Generate slug from title
		$slug = sanitizeSlug($title);
		
		// Use custom slug if provided
		if (!empty($_POST['custom_slug'])) {
			$customSlug = sanitizeSlug($_POST['custom_slug'], true);
		} else {
			$customSlug = '';
		}
		
		// Handle tags
		$tags = [];
		if (isset($_POST['tags']) && !empty($_POST['tags'])) {
			$tagList = explode(',', $_POST['tags']);
			foreach ($tagList as $tag) {
				$trimmedTag = trim($tag);
				if (!empty($trimmedTag)) {
					$tags[] = $trimmedTag;
				}
			}
		}
		
		// Get category
		$category = '';
		if (isset($_POST['category']) && trim($_POST['category']) !== '') {
			$category = trim($_POST['category']);
		}
		
		$updatedItem = [
			'title' => $title,
			'slug' => $slug,
			'custom_slug' => $customSlug,
			'content' => $content,
			// SEO fields: store raw — htmlspecialchars() is applied at output time only
			'meta_title' => trim($_POST['meta_title'] ?? ''),
			'meta_description' => trim($_POST['meta_description'] ?? ''),
			'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
			'canonical_url' => trim($_POST['canonical_url'] ?? ''),
			'schema_type' => trim($_POST['schema_type'] ?? ''),
			'og_title' => trim($_POST['og_title'] ?? ''),
			'og_description' => trim($_POST['og_description'] ?? ''),
			'og_image' => trim($_POST['og_image'] ?? ''),
			'show_featured_image' => isset($_POST['show_featured_image']) ? true : false,
			'show_date' => isset($_POST['show_date']) ? true : false,
			'show_title' => isset($_POST['show_title']) ? true : false,
			'gallery_layout' => $_POST['gallery_layout'] ?? 'grid',
			'category' => $category,
			'tags' => $tags,
			'show_tags_at_bottom' => isset($_POST['show_tags_at_bottom']) ? true : false,
		];
		
		// Handle image upload/selection
		// Check if featured image should be removed
		if (isset($_POST['remove_featured_image']) && $_POST['remove_featured_image'] == '1') {
			// Explicitly don't add the image field to remove it
			// Do nothing here - image won't be in $updatedItem
		} elseif (!empty($_POST['selected_image_path'])) {
			// User selected an existing image from the file manager
			$selectedImagePath = $_POST['selected_image_path'];
			// Make sure the path is properly formatted
			if (strpos($selectedImagePath, 'files/') !== 0) {
				$selectedImagePath = 'files/' . ltrim($selectedImagePath, '/');
			}
			$updatedItem['image'] = $selectedImagePath;
		} elseif (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
			// Handle new image upload
			$uploadedImagePath = handleImageUpload($_FILES['image'], $contentType);
			if ($uploadedImagePath) {
				$updatedItem['image'] = $uploadedImagePath;
			}
		} else {
			// Keep existing image if no new one uploaded and not removed
			if (isset($data[$contentType][$index]['image'])) {
				$updatedItem['image'] = $data[$contentType][$index]['image'];
			}
		}
		
		// Update content type specific fields
		if ($contentType === 'project') {
			$updatedItem['date'] = $_POST['date'] ?? date('Y-m-d');
			$updatedItem['description'] = htmlspecialchars($_POST['description'] ?? '');
		}
		
		if ($contentType === 'article' || $contentType === 'page') {
			$updatedItem['date'] = $_POST['date'] ?? date('Y-m-d');
		}
		// Save article summary (replaces auto-generated excerpt in cards)
		if ($contentType === 'article') {
			$updatedItem['summary'] = trim($_POST['summary'] ?? '');
		}
		// Save page template (pages only)
		if ($contentType === 'page') {
			$updatedItem['page_template'] = trim($_POST['page_template'] ?? '');
		}
		
		// Show on homepage (articles and projects only)
		if ($contentType === 'article' || $contentType === 'project') {
			$updatedItem['show_on_homepage'] = isset($_POST['show_on_homepage']) ? true : false;
		}
		
		// Show in menu (all content types)
		$updatedItem['show_in_menu'] = isset($_POST['show_in_menu']) ? true : false;
		$updatedItem['menu_order'] = isset($_POST['menu_order']) ? max(0, min(999, (int)$_POST['menu_order'])) : 0;

		// Content format: preserve the existing value; only update if the form explicitly sends a valid value
		$submittedFormat = $_POST['content_format'] ?? '';
		if (in_array($submittedFormat, ['html', 'markdown'])) {
			$updatedItem['content_format'] = $submittedFormat;
		} else {
			$updatedItem['content_format'] = $data[$contentType][$index]['content_format'] ?? 'html';
		}

		// Scheduling: re-evaluate status based on submitted publish_at.
		// Stays 'scheduled' as long as publish_at is still in the future; otherwise 'published'.
		$publishAt = trim(str_replace('T', ' ', $_POST['publish_at'] ?? ''));
		$publishTs = ($publishAt !== '') ? strtotime($publishAt) : false;
		if ($publishTs !== false && $publishTs > time()) {
			$updatedItem['status']     = 'scheduled';
			$updatedItem['publish_at'] = $publishAt;
		} else {
			$updatedItem['status']     = 'published';
			$updatedItem['publish_at'] = $publishAt;
		}

		// Handle gallery images
		if (isset($_POST['gallery']) && is_array($_POST['gallery'])) {
			$galleryItems = [];
			foreach ($_POST['gallery'] as $galleryItem) {
				// Validate and sanitize gallery item
				if (!empty($galleryItem['src'])) {
					$galleryItems[] = [
						'src' => htmlspecialchars($galleryItem['src']),
						'caption' => !empty($galleryItem['caption']) ? $galleryItem['caption'] : '',
						'alt_text' => !empty($galleryItem['alt_text']) ? $galleryItem['alt_text'] : ''
					];
				}
			}
			
			// Only add gallery if there are valid items
			if (!empty($galleryItems)) {
				$updatedItem['gallery'] = $galleryItems;
			}
		}
		
		$updatedItem['last_modified'] = date('Y-m-d');
		
		// Capture old slug/category BEFORE overwrite, for menu sync
		$oldMenuSlug = !empty($data[$contentType][$index]['custom_slug'])
			? $data[$contentType][$index]['custom_slug']
			: ($data[$contentType][$index]['slug'] ?? '');
		$oldMenuCategory = $data[$contentType][$index]['category'] ?? '';
		

		// Handle named inline galleries
		if (isset($_POST['galleries']) && is_array($_POST['galleries'])) {
			$galleries = [];
			foreach ($_POST['galleries'] as $gIdx => $galleryData) {
				$images = [];
				if (!empty($galleryData['images']) && is_array($galleryData['images'])) {
					foreach ($galleryData['images'] as $img) {
						if (!empty($img['src'])) {
							$images[] = [
								'src'      => htmlspecialchars($img['src']),
								'caption'  => $img['caption'] ?? '',
								'alt_text' => $img['alt_text'] ?? '',
							];
						}
					}
				}
				$galleries[] = [
					'label'  => $galleryData['label'] ?? ('Galerie ' . $gIdx),
					'layout' => in_array($galleryData['layout'] ?? 'grid', ['grid', 'masonry', 'justified', 'carousel'])
								? $galleryData['layout'] : 'grid',
					'images' => $images,
				];
			}
			if (!empty($galleries)) {
				$updatedItem['galleries'] = $galleries;
			}
		}

		$data[$contentType][$index] = $updatedItem;
		saveData($data);
		
		// Sync menu URLs if slug or category changed
		$newMenuSlug = !empty($customSlug) ? $customSlug : $slug;
		if ($oldMenuSlug !== $newMenuSlug || $oldMenuCategory !== $category) {
			syncMenuUrls($contentType, $oldMenuSlug, $newMenuSlug, $category);
		}
		
		// Delete ALL drafts related to this item (by type and index OR title)
		$draftsDir = 'drafts';
		if (is_dir($draftsDir)) {
			$files = glob($draftsDir . '/*.json');
			foreach ($files as $file) {
				$draftData = json_decode(file_get_contents($file), true);
				if ($draftData && $draftData['type'] === $contentType) {
					// Match by index (exact match for existing items)
					$matchesByIndex = isset($draftData['index']) && $draftData['index'] == $index;
					
					// Match by title (for drafts that might have different versions)
					$matchesByTitle = strtolower(trim($draftData['title'])) === strtolower(trim($title));
					
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
	} else {
		$_SESSION['error'] = __t('fill_required_fields');
	}
}

/**
 * Sync menu item URLs in settings.json when a post's slug or category changes
 */
function syncMenuUrls($contentType, $oldSlug, $newSlug, $newCategory) {
	$settingsFile = '../settings.json';
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

/**
 * Sanitize a filename to make it safe for storage
 */
function sanitizeFileName($filename) {
	// Remove any non-alphanumeric characters except dots, hyphens, and underscores
	$filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
	
	// Limit filename length
	$filename = substr($filename, 0, 255);
	
	// Ensure filename is not empty
	if (empty($filename)) {
		$filename = 'unnamed_file_' . time();
	}
	
	return $filename;
}