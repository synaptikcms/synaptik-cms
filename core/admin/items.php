<?php

function sl_unique_file_slug(string $type, string $effectiveSlug): string
{
    $fileSlug = $effectiveSlug !== '' ? $effectiveSlug : ($type . '-' . time());
    $base     = $fileSlug;
    $n        = 2;
    while (file_exists(sl_item_path($type, $fileSlug))) {
        $fileSlug = $base . '-' . $n++;
    }
    return $fileSlug;
}

function sl_admin_save_item(string $type, string $fileSlug, array $item): bool
{
    sl_admin_ensure_dirs();

    // _file is an index-only field — never persisted inside item files
    unset($item['_file']);

    $item = pl_apply_filter('item_before_save', $item, $type, $fileSlug);

    $path = sl_item_path($type, $fileSlug);
    return _sl_write_json($path, $item);
}

function sl_admin_delete_item(string $type, string $fileSlug): bool
{
    if ($fileSlug === '') return false;
    $path = sl_item_path($type, $fileSlug);
    if (!file_exists($path)) return true; // already gone
    return unlink($path);
}

