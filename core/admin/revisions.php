<?php

function sl_admin_revisions_dir(string $type, string $fileSlug): string
{
    $fileSlug = basename($fileSlug);
    return sl_data_dir() . '/' . sl_type_dir($type) . '/.revisions/' . $fileSlug;
}

function sl_admin_revision_path(string $type, string $fileSlug, int $timestamp): string
{
    return sl_admin_revisions_dir($type, $fileSlug) . '/' . $timestamp . '.json';
}

function sl_admin_list_revisions(string $type, string $fileSlug): array
{
    $dir = sl_admin_revisions_dir($type, $fileSlug);
    if (!is_dir($dir)) return [];

    $revisions = [];
    foreach (glob($dir . '/*.json') as $file) {
        $ts = (int)basename($file, '.json');
        if ($ts <= 0) continue;
        $revisions[] = ['timestamp' => $ts, 'path' => $file];
    }
    usort($revisions, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    return $revisions;
}

function sl_admin_load_revision(string $type, string $fileSlug, int $timestamp): ?array
{
    $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    if (!file_exists($path)) return null;
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : null;
}

function sl_admin_snapshot_revision(string $type, string $fileSlug, array $item): bool
{
    if ($fileSlug === '') return false;

    $dir = sl_admin_revisions_dir($type, $fileSlug);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $timestamp = time();
    $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    while (file_exists($path)) {
        $timestamp++;
        $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    }

    if (!_sl_write_json($path, $item)) return false;

    $revisions = sl_admin_list_revisions($type, $fileSlug);
    if (count($revisions) > SL_ADMIN_MAX_REVISIONS) {
        foreach (array_slice($revisions, SL_ADMIN_MAX_REVISIONS) as $old) {
            @unlink($old['path']);
        }
    }

    return true;
}

function sl_admin_restore_revision(string $type, string $fileSlug, int $timestamp): bool
{
    $revision = sl_admin_load_revision($type, $fileSlug, $timestamp);
    if ($revision === null) return false;

    $current = sl_load_item($type, $fileSlug);
    if ($current === null) return false;

    sl_admin_snapshot_revision($type, $fileSlug, $current);

    if (!sl_admin_save_item($type, $fileSlug, $revision)) return false;

    $indexEntry          = sl_admin_extract_index_entry($type, $revision);
    $indexEntry['_file'] = $fileSlug;
    sl_admin_update_index($type, $indexEntry);

    return true;
}

function sl_admin_reconcile_file_slug(string $type, string $oldFileSlug, array $item): string
{
    $desiredFileSlug = sl_effective_slug($item);
    if ($desiredFileSlug === '' || $desiredFileSlug === $oldFileSlug) {
        return $oldFileSlug;
    }
    if (!file_exists(sl_item_path($type, $oldFileSlug))) {
        return $oldFileSlug;
    }

    $newFileSlug = sl_unique_file_slug($type, $desiredFileSlug);

    if (!sl_admin_save_item($type, $newFileSlug, $item)) {
        return $oldFileSlug;
    }
    sl_admin_delete_item($type, $oldFileSlug);

    $renamedEntry = sl_admin_extract_index_entry($type, $item);
    $renamedEntry['_file'] = $newFileSlug;
    sl_admin_update_index($type, $renamedEntry, $oldFileSlug);

    return $newFileSlug;
}

function sl_admin_migrate_revisions(string $type, string $oldFileSlug, string $newFileSlug): void
{
    if ($oldFileSlug === '' || $newFileSlug === '' || $oldFileSlug === $newFileSlug) return;

    $oldDir = sl_admin_revisions_dir($type, $oldFileSlug);
    if (!is_dir($oldDir)) return;

    $newDir = sl_admin_revisions_dir($type, $newFileSlug);

    if (!is_dir($newDir)) {
        $parent = dirname($newDir);
        if (!is_dir($parent)) mkdir($parent, 0755, true);
        @rename($oldDir, $newDir);
        return;
    }

    foreach (glob($oldDir . '/*.json') ?: [] as $file) {
        $ts     = (int)basename($file, '.json');
        $target = sl_admin_revision_path($type, $newFileSlug, $ts);
        while (file_exists($target)) {
            $ts++;
            $target = sl_admin_revision_path($type, $newFileSlug, $ts);
        }
        @rename($file, $target);
    }
    @rmdir($oldDir);

    $merged = sl_admin_list_revisions($type, $newFileSlug);
    if (count($merged) > SL_ADMIN_MAX_REVISIONS) {
        foreach (array_slice($merged, SL_ADMIN_MAX_REVISIONS) as $old) {
            @unlink($old['path']);
        }
    }
}

function sl_admin_delete_all_revisions(string $type, string $fileSlug): void
{
    $dir = sl_admin_revisions_dir($type, $fileSlug);
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.json') as $file) { @unlink($file); }
    @rmdir($dir);
}

function sl_admin_delete_revision(string $type, string $fileSlug, int $timestamp): bool
{
    $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    if (!file_exists($path)) return false;
    return @unlink($path);
}

