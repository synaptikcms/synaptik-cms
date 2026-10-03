<?php

function sl_admin_write_index(string $type, array $index): bool
{
    sl_admin_ensure_dirs();

    $path    = sl_index_path($type);
    $success = _sl_write_json($path, $index);

    if ($success) {
        sl_invalidate_index_cache($type);
    }

    return $success;
}

function sl_admin_update_index(
    string  $type,
    array   $indexEntry,
    ?string $oldFileSlug = null
): bool {
    $index    = sl_load_index($type);
    $newSlug  = $indexEntry['_file'] ?? '';

    // Remove old entry if a rename is happening
    if ($oldFileSlug !== null && $oldFileSlug !== $newSlug) {
        $index = array_values(array_filter(
            $index,
            fn($e) => sl_file_slug($e) !== $oldFileSlug
        ));
    }

    // Find and replace existing entry, or append
    $found = false;
    foreach ($index as $i => $entry) {
        if (sl_file_slug($entry) === $newSlug) {
            $index[$i] = $indexEntry;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $index[] = $indexEntry;
    }

    return sl_admin_write_index($type, array_values($index));
}

function sl_admin_remove_from_index(string $type, string $fileSlug): bool
{
    $index = sl_load_index($type);
    $new   = array_values(array_filter(
        $index,
        fn($e) => sl_file_slug($e) !== $fileSlug
    ));

    // No change needed if not found
    if (count($new) === count($index)) return true;

    return sl_admin_write_index($type, $new);
}

function sl_admin_save_categories(array $categories): bool
{
    $path    = sl_data_dir() . '/categories.json';
    $success = _sl_write_json($path, $categories);
    if ($success) sl_invalidate_taxonomy_cache('categories');
    return $success;
}

function sl_admin_save_tags(array $tags): bool
{
    $path    = sl_data_dir() . '/tags.json';
    $success = _sl_write_json($path, $tags);
    if ($success) sl_invalidate_taxonomy_cache('tags');
    return $success;
}

function sl_admin_save_all(array $data): bool
{
    sl_admin_ensure_dirs();
    $success = true;
    if (isset($data['categories'])) {
        if (!sl_admin_save_categories($data['categories'])) $success = false;
    }
    if (isset($data['tags'])) {
        if (!sl_admin_save_tags($data['tags'])) $success = false;
    }

    foreach (sl_all_type_slugs() as $type) {
        $newItems = $data[$type] ?? [];
        $oldIndex       = sl_load_index($type);
        $oldSlugToFile  = [];
        $oldFileSlugs   = [];
        foreach ($oldIndex as $entry) {
            $es = sl_effective_slug($entry);
            $fs = sl_file_slug($entry);
            $oldSlugToFile[$es] = $fs;
            $oldFileSlugs[]     = $fs;
        }

        $newIndex       = [];
        $newFileSlugsUsed = [];

        foreach ($newItems as $item) {
            $effectiveSlug = sl_effective_slug($item);

            if (!empty($oldSlugToFile[$effectiveSlug])) {
                $fileSlug = $oldSlugToFile[$effectiveSlug];
            } else {
                $fileSlug = $effectiveSlug !== '' ? $effectiveSlug : ($type . '-' . time());
                $base     = $fileSlug;
                $n        = 2;
                while (in_array($fileSlug, $newFileSlugsUsed) ||
                       file_exists(sl_item_path($type, $fileSlug))) {
                    $fileSlug = $base . '-' . $n++;
                }
            }

            $newFileSlugsUsed[] = $fileSlug;
            if (!sl_admin_save_item($type, $fileSlug, $item)) {
                $success = false;
            }

            $indexEntry          = sl_admin_extract_index_entry($type, $item);
            $indexEntry['_file'] = $fileSlug;
            $newIndex[]          = $indexEntry;
        }

        foreach ($oldFileSlugs as $oldSlug) {
            if (!in_array($oldSlug, $newFileSlugsUsed)) {
                sl_admin_delete_item($type, $oldSlug);
            }
        }

        if (!sl_admin_write_index($type, $newIndex)) {
            $success = false;
        }
    }

    return $success;
}

function sl_admin_load_all(): array
{
    $data = [
        'categories' => sl_load_categories(),
        'tags'       => sl_load_tags(),
    ];
    foreach (sl_all_type_slugs() as $type) {
        $data[$type] = sl_load_all_items($type);
    }
    return $data;
}
