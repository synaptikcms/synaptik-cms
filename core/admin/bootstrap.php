<?php

function sl_admin_ensure_dirs(): void
{
    $base = sl_data_dir();
    foreach (sl_all_type_slugs() as $type) {
        $path = $base . '/' . sl_type_dir($type);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

function sl_admin_drafts_dir(): string
{
    $dir = sl_data_dir() . '/drafts';
    if (!is_dir($dir)) {
        $legacy = CMS_ROOT . '/' . resolve_admin_dir() . '/drafts';
        if (is_dir($legacy)) {
            @rename($legacy, $dir);
        }
    }
    return $dir;
}

function sl_admin_index_fields(string $type): array
{
    $common = [
        'slug', 'custom_slug', 'title', 'date',
        'category', 'tags', 'image', 'image_alt', 'author_id',
        'show_in_menu', 'menu_order',
        'status', 'publish_at', 'show_date',
    ];

    $specific = [
        'article' => ['summary', 'show_on_homepage'],
        'project' => ['description', 'show_on_homepage'],
        'page'    => ['page_template'],
    ];

    return array_merge($common, $specific[$type] ?? []);
}

function sl_admin_extract_index_entry(string $type, array $item): array
{
    $entry = [];
    foreach (sl_admin_index_fields($type) as $field) {
        // Only include fields that are actually present in the item
        if (array_key_exists($field, $item)) {
            $entry[$field] = $item[$field];
        }
    }
    return $entry;
}

