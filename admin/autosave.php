<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';

// Set JSON header immediately
header('Content-Type: application/json');

// Check authentication
if (!admin_is_logged_in()) {
	echo json_encode(['error' => 'Not authorized']);
	exit;
}

// Make sure we have POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['error' => 'Invalid request method']);
	exit;
}

// Get the data with validation
$content = $_POST['content'] ?? '';
$title = $_POST['title'] ?? '';
$contentType = $_POST['type'] ?? '';
$itemIndex = isset($_POST['index']) ? (int)$_POST['index'] : -1;
$customSlug = $_POST['custom_slug'] ?? '';
$draftId = $_POST['draft_id'] ?? uniqid('draft_');

// Create a draft folder if it doesn't exist
$draftsDir = 'drafts';
if (!file_exists($draftsDir)) {
	if (!mkdir($draftsDir, 0755, true)) {
		echo json_encode(['error' => 'Failed to create drafts directory']);
		exit;
	}
}

// Handle named galleries data (new format)
$galleries = [];
if (isset($_POST['galleries'])) {
	if (is_string($_POST['galleries'])) {
		$galleries = json_decode($_POST['galleries'], true) ?: [];
	} elseif (is_array($_POST['galleries'])) {
		$galleries = $_POST['galleries'];
	}
}

// Handle legacy gallery data (kept for backward compat)
$gallery = [];
if (isset($_POST['gallery'])) {
	if (is_string($_POST['gallery'])) {
		$gallery = json_decode($_POST['gallery'], true) ?: [];
	} elseif (is_array($_POST['gallery'])) {
		$gallery = $_POST['gallery'];
	}
}

// Create the draft data with ALL fields
$draft = [
	'id' => $draftId,
	'content' => $content,
	'title' => $title,
	'type' => $contentType,
	'index' => $itemIndex,
	'custom_slug' => $customSlug,
	'timestamp' => time(),
	'user' => $_SESSION['admin_username'] ?? 'admin',
	
	// Category & Tags
	'category' => $_POST['category'] ?? '',
	'tags' => $_POST['tags'] ?? '',
	'show_tags_at_bottom' => isset($_POST['show_tags_at_bottom']),
	
	// Project specific
	'description' => $_POST['description'] ?? '',

	// Article specific
	'summary' => $_POST['summary'] ?? '',
	
	// Display options
	'show_featured_image' => isset($_POST['show_featured_image']),
	'show_date' => isset($_POST['show_date']),
	'show_title' => isset($_POST['show_title']),
	'show_on_homepage' => isset($_POST['show_on_homepage']),
	'show_in_menu' => isset($_POST['show_in_menu']),
	'menu_order' => isset($_POST['menu_order']) ? max(0, min(999, (int)$_POST['menu_order'])) : 0,
	// Images
	'selected_image_path' => $_POST['selected_image_path'] ?? '',
	'image' => $_POST['selected_image_path'] ?? '',
	
	// Galleries (new named system)
	'galleries' => $galleries,

	// Galleries (new named system)
	'galleries' => $galleries,

	// Gallery (legacy)
	'gallery' => $gallery,
	'gallery_layout' => $_POST['gallery_layout'] ?? 'grid',
	
	// SEO fields
	'meta_title' => $_POST['meta_title'] ?? '',
	'meta_description' => $_POST['meta_description'] ?? '',
	'meta_keywords' => $_POST['meta_keywords'] ?? '',
	'canonical_url' => $_POST['canonical_url'] ?? '',
	'schema_type' => $_POST['schema_type'] ?? '',
	'og_title' => $_POST['og_title'] ?? '',
	'og_description' => $_POST['og_description'] ?? '',
	'og_image' => $_POST['og_image'] ?? '',
	
	// Date
	'date' => $_POST['date'] ?? date('Y-m-d'),

	// Custom fields
	'custom_fields' => isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])
		? $_POST['custom_fields']
		: [],
];

// Save the draft to a file
$filename = $draftsDir . '/' . $draftId . '.json';
$jsonData = json_encode($draft, JSON_PRETTY_PRINT);

if ($jsonData === false) {
	echo json_encode(['error' => 'Failed to encode draft data: ' . json_last_error_msg()]);
	exit;
}

if (file_put_contents($filename, $jsonData)) {
	echo json_encode([
		'success' => true,
		'message' => 'Draft saved successfully',
		'draft_id' => $draftId,
		'timestamp' => time()
	]);
} else {
	echo json_encode(['error' => 'Failed to save draft file']);
}
exit;