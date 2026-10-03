<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_build_content_item_from_post(
	string $type,
	array $post,
	?array $existingItem,
	array $existingIndexForDedup = [],
	array $files = []
): array {
	$title = $post['title'] ?? '';

	$contentFormatSubmitted = $post['content_format'] ?? '';
	$contentFormat = in_array($contentFormatSubmitted, ['html', 'markdown'], true)
		? $contentFormatSubmitted
		: ($existingItem['content_format'] ?? 'html');
	$content = isset($post['content']) && $post['content'] !== ''
		? ($contentFormat === 'markdown' ? admin_purify_markdown($post['content']) : admin_purify_html($post['content']))
		: '';

	$slug       = sanitizeSlug($title);
	$customSlug = !empty($post['custom_slug']) ? sanitizeSlug($post['custom_slug'], true) : '';

	$slugRenamedTo = null;
	if (!empty($existingIndexForDedup)) {
		$existingSlugs = array_map(
			fn($entry) => !empty($entry['custom_slug']) ? $entry['custom_slug'] : ($entry['slug'] ?? ''),
			$existingIndexForDedup
		);
		$effectiveSlug = $customSlug !== '' ? $customSlug : $slug;
		if (in_array($effectiveSlug, $existingSlugs, true)) {
			$base = $effectiveSlug;
			$n    = 2;
			while (in_array($base . '-' . $n, $existingSlugs, true)) $n++;
			$uniqueSlug    = $base . '-' . $n;
			$slugRenamedTo = $uniqueSlug;
			if ($customSlug !== '') { $customSlug = $uniqueSlug; } else { $slug = $uniqueSlug; }
		}
	}

	$tags       = [];
	$newTags    = [];
	if (!empty($post['tags'])) {
		$tagsStore = sl_load_tags();
		foreach (explode(',', $post['tags']) as $tagInput) {
			$displayName = trim($tagInput);
			if ($displayName === '') continue;
			$tagSlug = sanitizeSlug($displayName);
			if ($tagSlug === '') continue;
			$tags[] = $tagSlug;
			if (!isset($tagsStore[$tagSlug])) $newTags[$tagSlug] = $displayName;
		}
	}

	$category    = '';
	$newCategory = null;
	if (isset($post['category']) && trim($post['category']) !== '') {
		$displayCat = trim($post['category']);
		$catSlug    = sanitizeSlug($displayCat);
		if ($catSlug !== '') {
			$category = $catSlug;
			if (!isset(sl_load_categories()[$catSlug])) $newCategory = [$catSlug => $displayCat];
		}
	}

	$item = [
		'title'                => $title,
		'author_id'            => $existingItem['author_id'] ?? admin_current_user_id(),
		'slug'                 => $slug,
		'custom_slug'          => $customSlug,
		'content'              => $content,
		'meta_title'           => trim($post['meta_title'] ?? ''),
		'meta_description'     => trim($post['meta_description'] ?? ''),
		'meta_keywords'        => trim($post['meta_keywords'] ?? ''),
		'canonical_url'        => trim($post['canonical_url'] ?? ''),
		'schema_type'          => trim($post['schema_type'] ?? ''),
		'og_title'             => trim($post['og_title'] ?? ''),
		'og_description'       => trim($post['og_description'] ?? ''),
		'og_image'             => trim($post['og_image'] ?? ''),
		'show_featured_image'  => isset($post['show_featured_image']),
		'show_date'            => isset($post['show_date']),
		'show_title'           => isset($post['show_title']),
		'show_related_items'   => isset($post['show_related_items']),
		'gallery_layout'       => $post['gallery_layout'] ?? 'grid',
		'category'             => $category,
		'tags'                 => $tags,
		'show_tags_at_bottom'  => isset($post['show_tags_at_bottom']),
		'content_format'       => $contentFormat,
	];

	if (!empty($post['remove_featured_image'])) {
	} elseif (!empty($post['selected_image_path'])) {
		$selectedImagePath = $post['selected_image_path'];
		if (strpos($selectedImagePath, 'files/') !== 0) {
			$selectedImagePath = 'files/' . ltrim($selectedImagePath, '/');
		}
		$item['image'] = $selectedImagePath;
		if ($selectedImagePath === ($existingItem['image'] ?? null)) {
			$item['image_alt'] = $existingItem['image_alt'] ?? '';
		}
	} elseif (isset($files['image']) && ($files['image']['error'] ?? 1) === 0) {
		$uploadedImagePath = handleImageUpload($files['image'], $type);
		if ($uploadedImagePath) $item['image'] = $uploadedImagePath;
	} elseif (!empty($existingItem['image'])) {
		$item['image'] = $existingItem['image'];
		$item['image_alt'] = $existingItem['image_alt'] ?? '';
	}

	$dtRaw = trim($post['publish_datetime'] ?? '');
	if ($dtRaw !== '') {
		$date = str_replace('T', ' ', $dtRaw);
	} else {
		$timeRaw = trim($post['time'] ?? '');
		if (!empty($post['date'])) {
			$date = ($timeRaw !== '') ? $post['date'] . ' ' . $timeRaw : $post['date'];
		} else {
			$date = date('Y-m-d H:i');
		}
	}
	$_isBuiltInType = in_array($type, ['article', 'page', 'project'], true);
	if ($type === 'project') {
		$item['date']        = $date;
		$item['description'] = hsc($post['description'] ?? '');
	}
	if ($type === 'article' || $type === 'page' || !$_isBuiltInType) {
		$item['date'] = $date;
	}
	if ($type === 'article' || !$_isBuiltInType) {
		$item['summary'] = trim($post['summary'] ?? '');
	}
	if ($type === 'page') {
		$item['page_template'] = trim($post['page_template'] ?? '');
	}
	if ($type === 'article' || $type === 'project') {
		$item['show_on_homepage'] = isset($post['show_on_homepage']);
	}

	$item['show_in_menu'] = isset($post['show_in_menu']);
	$item['menu_order']   = isset($post['menu_order']) ? max(0, min(999, (int)$post['menu_order'])) : 0;

	if (isset($post['custom_fields']) && is_array($post['custom_fields'])) {
		$cleanCf = [];
		foreach ($post['custom_fields'] as $cfKey => $cfVal) {
			$cleanCf[sanitizeSlug($cfKey, true)] = is_array($cfVal) ? '' : trim((string)$cfVal);
		}
		$item['custom_fields'] = $cleanCf;
	} elseif (!empty($existingItem['custom_fields'])) {
		$item['custom_fields'] = $existingItem['custom_fields'];
	}

	$riRaw = (string)($post['related_items'] ?? '');
	if ($riRaw !== '') {
		$riDecoded = json_decode(stripslashes($riRaw), true);
		if (is_array($riDecoded)) {
			$riClean = [];
			foreach ($riDecoded as $riRef) {
				$riType  = $riRef['type']  ?? '';
				$riSlug  = $riRef['slug']  ?? '';
				$riTitle = mb_substr(strip_tags((string)($riRef['title'] ?? '')), 0, 300);
				if (sl_content_type_exists($riType) && $riSlug !== '') {
					$riClean[] = ['type' => $riType, 'slug' => sanitizeSlug($riSlug), 'title' => $riTitle];
				}
			}
			$item['related_items'] = $riClean;
		}
	} elseif (!empty($existingItem['related_items'])) {
		$item['related_items'] = $existingItem['related_items'];
	}

	// Legacy flat gallery
	if (isset($post['gallery']) && is_array($post['gallery'])) {
		$galleryItems = [];
		foreach ($post['gallery'] as $galleryItem) {
			if (!empty($galleryItem['src'])) {
				$galleryItems[] = [
					'src'      => hsc($galleryItem['src']),
					'caption'  => $galleryItem['caption']  ?? '',
					'alt_text' => $galleryItem['alt_text'] ?? '',
				];
			}
		}
		if (!empty($galleryItems)) $item['gallery'] = $galleryItems;
	}

	if (isset($post['galleries']) && is_array($post['galleries'])) {
		$galleries = [];
		foreach ($post['galleries'] as $gIdx => $galleryData) {
			$images = [];
			if (!empty($galleryData['images']) && is_array($galleryData['images'])) {
				foreach ($galleryData['images'] as $img) {
					if (!empty($img['src'])) {
						$images[] = [
							'src'      => hsc($img['src']),
							'caption'  => $img['caption']  ?? '',
							'alt_text' => $img['alt_text'] ?? '',
						];
					}
				}
			}
			$galleries[] = [
				'label'  => $galleryData['label'] ?? ('Galerie ' . $gIdx),
				'layout' => in_array($galleryData['layout'] ?? 'grid', ['grid', 'masonry', 'justified', 'carousel'], true)
							? $galleryData['layout'] : 'grid',
				'images' => $images,
			];
		}
		if (!empty($galleries)) $item['galleries'] = $galleries;
	}

	if ($existingItem !== null) {
		$item['last_modified'] = date('Y-m-d H:i');
		$managedFields = [
			'title', 'author_id', 'slug', 'custom_slug', 'content',
			'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'schema_type',
			'og_title', 'og_description', 'og_image',
			'show_featured_image', 'show_date', 'show_title', 'show_related_items',
			'gallery_layout', 'category', 'tags', 'show_tags_at_bottom', 'content_format',
			'image', 'image_alt', 'date', 'description', 'page_template', 'summary',
			'show_on_homepage', 'show_in_menu', 'menu_order',
			'custom_fields', 'related_items', 'gallery', 'galleries', 'last_modified',
		];
		foreach ($existingItem as $key => $value) {
			if (!array_key_exists($key, $item) && !in_array($key, $managedFields, true)) {
				$item[$key] = $value;
			}
		}
	}

	return [
		'item'            => $item,
		'slug_renamed_to' => $slugRenamedTo,
		'new_tags'        => $newTags,
		'new_category'    => $newCategory,
	];
}


function admin_pending_candidate_item(string $type, array $post, array $existingItem): array {
	$built = admin_build_content_item_from_post($type, $post, $existingItem, [], []);
	$item  = $built['item'];

	$status = $post['status'] ?? '';
	if (in_array($status, ['published', 'scheduled', 'draft', 'unpublished'], true)) {
		$item['status']     = $status;
		$item['publish_at'] = $status === 'scheduled'
			? trim(str_replace('T', ' ', (string)($post['publish_at'] ?? '')))
			: '';
	}

	return $item;
}

function admin_pending_item_changed(array $existingItem, array $candidate): bool {
	$ignored = ['last_modified' => true, 'slug' => true, 'author_id' => true];
	$isEmpty = static fn($v) => $v === null || $v === '' || $v === [] || $v === false || $v === 0;

	foreach (array_unique(array_merge(array_keys($existingItem), array_keys($candidate))) as $key) {
		if (isset($ignored[$key])) continue;
		$old = $existingItem[$key] ?? null;
		$new = $candidate[$key] ?? null;
		if ($isEmpty($old) && $isEmpty($new)) continue;
		if (is_array($old) || is_array($new)) {
			if (json_encode($old) !== json_encode($new)) return true;
			continue;
		}
		if ((string)$old !== (string)$new) return true;
	}
	return false;
}
