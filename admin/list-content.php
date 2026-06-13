<?php
/**
 * AJAX endpoint for content list — search, filter, sort, paginate server-side.
 *
 * GET params:
 *   type      – article | page | project (required)
 *   q         – search term (optional)
 *   category  – category name filter (optional)
 *   sort      – date-desc | date-asc | title-asc | title-desc (default: date-desc)
 *   page      – 1-based page number (default: 1)
 *   per_page  – items per page, 0 = all (default: 25)
 *
 * Returns JSON: { total, page, per_page, items[] }
 */

if (!defined('INCLUDED')) define('INCLUDED', true);
session_start();
require_once 'includes/admin-functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!admin_is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$allowedTypes = ['article', 'page', 'project'];
$type = $_GET['type'] ?? '';
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}

$q        = mb_strtolower(trim($_GET['q']        ?? ''), 'UTF-8');
$category = trim($_GET['category'] ?? '');
$sort     = $_GET['sort']     ?? 'date-desc';
$page     = max(1, (int)($_GET['page']     ?? 1));
$perPage  = max(0, (int)($_GET['per_page'] ?? 25));

if (!in_array($sort, ['date-desc', 'date-asc', 'title-asc', 'title-desc'], true)) {
    $sort = 'date-desc';
}

$index = sl_load_index($type);
if (!is_array($index)) $index = [];

$filtered = [];
foreach ($index as $idx => $item) {
    if ($q !== '') {
        $haystack = mb_strtolower(
            ($item['title']    ?? '') . ' ' .
            ($item['category'] ?? '') . ' ' .
            implode(' ', (array)($item['tags'] ?? [])) . ' ' .
            ($item['summary']  ?? ''),
            'UTF-8'
        );
        if (mb_strpos($haystack, $q, 0, 'UTF-8') === false) continue;
    }

    if ($category !== '') {
        if (mb_strtolower($item['category'] ?? '', 'UTF-8') !== mb_strtolower($category, 'UTF-8')) continue;
    }

    $filtered[] = ['_idx' => $idx, 'item' => $item];
}

usort($filtered, function ($a, $b) use ($sort) {
    $ia = $a['item'];
    $ib = $b['item'];
    switch ($sort) {
        case 'title-asc':  return strcasecmp($ia['title'] ?? '', $ib['title'] ?? '');
        case 'title-desc': return strcasecmp($ib['title'] ?? '', $ia['title'] ?? '');
        case 'date-asc':   return strcmp($ia['date'] ?? '', $ib['date'] ?? '');
        default:           return strcmp($ib['date'] ?? '', $ia['date'] ?? '');
    }
});

$total   = count($filtered);
$perPage = ($perPage === 0) ? $total : $perPage;
$offset  = ($page - 1) * max(1, $perPage);
$slice   = array_slice($filtered, $offset, $perPage ?: null);

$items = [];
foreach ($slice as $entry) {
    $idx  = $entry['_idx'];
    $item = $entry['item'];

    $effectiveSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');

    $tags = [];
    if (!empty($item['tags']) && is_array($item['tags'])) {
        $tags = array_values($item['tags']);
    }

    $items[] = [
        'idx'                     => $idx,
        'title'                   => $item['title']    ?? '',
        'date'                    => $item['date']      ?? '',
        'date_formatted'          => admin_format_date($item['date'] ?? ''),
        'time_formatted'          => admin_format_time($item['date'] ?? ''),
        'last_modified'           => $item['last_modified'] ?? $item['date'] ?? '',
        'last_modified_formatted' => admin_format_date($item['last_modified'] ?? $item['date'] ?? ''),
        'last_modified_time'      => admin_format_time($item['last_modified'] ?? $item['date'] ?? ''),
        'category'                => $item['category'] ?? '',
        'category_slug'           => sanitizeSlug($item['category'] ?? ''),
        'tags'                    => $tags,
        'image'                   => $item['image']    ?? '',
        'status'                  => $item['status']   ?? 'published',
        'publish_at'              => $item['publish_at'] ?? '',
        'slug'                    => $effectiveSlug,
        'custom_slug'             => $item['custom_slug'] ?? '',
        'view_url'                => admin_content_url(
            $type,
            $item['slug']        ?? '',
            $item['custom_slug'] ?? '',
            $item['category']    ?? ''
        ),
    ];
}

echo json_encode([
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
    'items'    => $items,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
