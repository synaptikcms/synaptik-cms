<?php

function sl_admin_trash_dir(string $type): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/.trash';
}

function sl_admin_trash_item_path(string $type, string $fileSlug): string
{
    $fileSlug = basename($fileSlug);
    return sl_admin_trash_dir($type) . '/' . $fileSlug . '.json';
}

function sl_admin_trash_index_path(string $type): string
{
    return sl_admin_trash_dir($type) . '/_index.json';
}

function sl_admin_load_trash_index(string $type): array
{
    $path = sl_admin_trash_index_path($type);
    if (!file_exists($path)) return [];
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function sl_admin_write_trash_index(string $type, array $index): bool
{
    $dir = sl_admin_trash_dir($type);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return _sl_write_json(sl_admin_trash_index_path($type), $index);
}

function sl_admin_trash_item(string $type, string $fileSlug): bool
{
    if ($fileSlug === '') return false;

    $item = sl_load_item($type, $fileSlug);
    if ($item === null) return false;

    $dir = sl_admin_trash_dir($type);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $trashFileSlug = $fileSlug;
    $n = 2;
    while (file_exists(sl_admin_trash_item_path($type, $trashFileSlug))) {
        $trashFileSlug = $fileSlug . '-' . $n++;
    }

    if (!rename(sl_item_path($type, $fileSlug), sl_admin_trash_item_path($type, $trashFileSlug))) {
        return false;
    }

    $entry               = sl_admin_extract_index_entry($type, $item);
    $entry['_file']      = $trashFileSlug;
    $entry['trashed_at'] = time();

    $trashIndex   = sl_admin_load_trash_index($type);
    $trashIndex[] = $entry;
    sl_admin_write_trash_index($type, $trashIndex);

    sl_admin_remove_from_index($type, $fileSlug);

    if (($item['status'] ?? 'published') === 'published' && function_exists('sm_regenerate')) {
        sm_regenerate();
    }

    return true;
}

function sl_admin_restore_trashed_item(string $type, string $fileSlug): bool
{
    $trashIndex = sl_admin_load_trash_index($type);
    $pos = null;
    foreach ($trashIndex as $i => $entry) {
        if (($entry['_file'] ?? '') === $fileSlug) { $pos = $i; break; }
    }
    if ($pos === null) return false;

    $trashPath = sl_admin_trash_item_path($type, $fileSlug);
    if (!file_exists($trashPath)) {
        unset($trashIndex[$pos]);
        sl_admin_write_trash_index($type, array_values($trashIndex));
        return false;
    }

    $restoreSlug = $fileSlug;
    $n = 2;
    while (file_exists(sl_item_path($type, $restoreSlug))) {
        $restoreSlug = $fileSlug . '-' . $n++;
    }

    if (!rename($trashPath, sl_item_path($type, $restoreSlug))) return false;

    unset($trashIndex[$pos]);
    sl_admin_write_trash_index($type, array_values($trashIndex));

    $item = sl_load_item($type, $restoreSlug);
    if ($item !== null) {
        $indexEntry          = sl_admin_extract_index_entry($type, $item);
        $indexEntry['_file'] = $restoreSlug;
        sl_admin_update_index($type, $indexEntry);

        if (($item['status'] ?? 'published') === 'published' && function_exists('sm_regenerate')) {
            sm_regenerate();
        }
    }

    return true;
}

function sl_admin_purge_trashed_item(string $type, string $fileSlug): bool
{
    if ($fileSlug === '') return false;

    $trashIndex = sl_admin_load_trash_index($type);
    $new = array_values(array_filter(
        $trashIndex,
        fn($e) => ($e['_file'] ?? '') !== $fileSlug
    ));

    $path = sl_admin_trash_item_path($type, $fileSlug);
    if (file_exists($path)) @unlink($path);
    sl_admin_delete_all_revisions($type, $fileSlug);

    if (count($new) !== count($trashIndex)) {
        sl_admin_write_trash_index($type, $new);
    }

    return true;
}

function sl_admin_purge_all_trash(): int
{
    $purged = 0;
    foreach (sl_all_type_slugs() as $type) {
        $trashIndex = sl_admin_load_trash_index($type);
        foreach ($trashIndex as $entry) {
            $path = sl_admin_trash_item_path($type, $entry['_file'] ?? '');
            if ($path && file_exists($path)) @unlink($path);
            sl_admin_delete_all_revisions($type, $entry['_file'] ?? '');
            $purged++;
        }
        if (!empty($trashIndex)) {
            sl_admin_write_trash_index($type, []);
        }
    }
    return $purged;
}

function sl_admin_purge_expired_trash(int $maxAgeDays = 30): int
{
    $purged = 0;
    $cutoff = time() - ($maxAgeDays * 86400);

    foreach (sl_all_type_slugs() as $type) {
        $trashIndex = sl_admin_load_trash_index($type);
        $keep = [];
        foreach ($trashIndex as $entry) {
            if (($entry['trashed_at'] ?? 0) < $cutoff) {
                $path = sl_admin_trash_item_path($type, $entry['_file'] ?? '');
                if ($path && file_exists($path)) @unlink($path);
                sl_admin_delete_all_revisions($type, $entry['_file'] ?? '');
                $purged++;
            } else {
                $keep[] = $entry;
            }
        }
        if (count($keep) !== count($trashIndex)) {
            sl_admin_write_trash_index($type, $keep);
        }
    }

    return $purged;
}

define('SL_ADMIN_MAX_REVISIONS', 10);

