<?php
if (defined('SL_ADMIN_LAYER_LOADED')) return;
define('SL_ADMIN_LAYER_LOADED', true);
function sl_admin_ensure_dirs(): void
{
    $base = sl_data_dir();
    foreach (['articles', 'pages', 'projects'] as $dir) {
        $path = $base . '/' . $dir;
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

function sl_admin_relink_image_path(string $oldPath, string $newPath): int
{
    $updated = 0;

    foreach (['article', 'page', 'project'] as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $changed = false;

            if (($item['image'] ?? '') === $oldPath) {
                $item['image'] = $newPath;
                $changed = true;
            }

            foreach (($item['galleries'] ?? []) as $gIdx => $gallery) {
                foreach (($gallery['images'] ?? []) as $iIdx => $img) {
                    if (($img['src'] ?? '') === $oldPath) {
                        $item['galleries'][$gIdx]['images'][$iIdx]['src'] = $newPath;
                        $changed = true;
                    }
                }
            }

            foreach (($item['gallery'] ?? []) as $iIdx => $img) {
                if (($img['src'] ?? '') === $oldPath) {
                    $item['gallery'][$iIdx]['src'] = $newPath;
                    $changed = true;
                }
            }

            if (!empty($item['content']) && strpos($item['content'], $oldPath) !== false) {
                $item['content'] = str_replace($oldPath, $newPath, $item['content']);
                $changed = true;
            }

            if ($changed) {
                sl_admin_save_item($type, $fileSlug, $item);
                $indexEntry          = sl_admin_extract_index_entry($type, $item);
                $indexEntry['_file'] = $fileSlug;
                sl_admin_update_index($type, $indexEntry);
                $updated++;
            }
        }
    }

    return $updated;
}

function sl_admin_relink_image_prefix(string $oldPrefix, string $newPrefix): int
{
    $oldPrefix = rtrim($oldPrefix, '/') . '/';
    $newPrefix = rtrim($newPrefix, '/') . '/';
    $updated   = 0;

    foreach (['article', 'page', 'project'] as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $changed = false;

            if (!empty($item['image']) && strpos($item['image'], $oldPrefix) === 0) {
                $item['image'] = $newPrefix . substr($item['image'], strlen($oldPrefix));
                $changed = true;
            }

            foreach (($item['galleries'] ?? []) as $gIdx => $gallery) {
                foreach (($gallery['images'] ?? []) as $iIdx => $img) {
                    if (!empty($img['src']) && strpos($img['src'], $oldPrefix) === 0) {
                        $item['galleries'][$gIdx]['images'][$iIdx]['src'] = $newPrefix . substr($img['src'], strlen($oldPrefix));
                        $changed = true;
                    }
                }
            }

            foreach (($item['gallery'] ?? []) as $iIdx => $img) {
                if (!empty($img['src']) && strpos($img['src'], $oldPrefix) === 0) {
                    $item['gallery'][$iIdx]['src'] = $newPrefix . substr($img['src'], strlen($oldPrefix));
                    $changed = true;
                }
            }

            if (!empty($item['content']) && strpos($item['content'], $oldPrefix) !== false) {
                $item['content'] = str_replace($oldPrefix, $newPrefix, $item['content']);
                $changed = true;
            }

            if ($changed) {
                sl_admin_save_item($type, $fileSlug, $item);
                $indexEntry          = sl_admin_extract_index_entry($type, $item);
                $indexEntry['_file'] = $fileSlug;
                sl_admin_update_index($type, $indexEntry);
                $updated++;
            }
        }
    }

    return $updated;
}

function sl_admin_find_image_usage(string $needle, bool $isPrefix = false): array
{
    $prefix = $isPrefix ? rtrim($needle, '/') . '/' : null;

    $matches = function ($src) use ($needle, $prefix) {
        if (empty($src)) return false;
        return $prefix !== null ? strpos($src, $prefix) === 0 : $src === $needle;
    };

    $usage = [];

    foreach (['article', 'page', 'project'] as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $used = $matches($item['image'] ?? '');

            if (!$used) {
                foreach (($item['galleries'] ?? []) as $gallery) {
                    foreach (($gallery['images'] ?? []) as $img) {
                        if ($matches($img['src'] ?? '')) { $used = true; break 2; }
                    }
                }
            }

            if (!$used) {
                foreach (($item['gallery'] ?? []) as $img) {
                    if ($matches($img['src'] ?? '')) { $used = true; break; }
                }
            }

            if (!$used && !empty($item['content']) && strpos($item['content'], $needle) !== false) {
                $used = true;
            }

            if ($used) {
                $usage[] = ['type' => $type, 'title' => $item['title'] ?? '', 'file_slug' => $fileSlug];
            }
        }
    }

    return $usage;
}

function _sl_write_json(string $path, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) return false;

    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;

    $ok = rename($tmp, $path);
    if ($ok && function_exists('sl_bump_content_signature')) {
        sl_bump_content_signature();
    }
    return $ok;
}

const SL_ACTIVITY_LOG_MAX_ENTRIES = 2000;
function sl_admin_activity_log_path(): string
{
    return CMS_ROOT . '/private/activity-log.json';
}

function sl_admin_log_activity(string $action, string $details = ''): bool
{
    $path = sl_admin_activity_log_path();

    if (!file_exists($path)) {
        @file_put_contents($path, '[]', LOCK_EX);
    }

    $fp = @fopen($path, 'c+');
    if (!$fp) return false;

    flock($fp, LOCK_EX);

    $raw     = stream_get_contents($fp);
    $entries = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (!is_array($entries)) $entries = [];

    $entries[] = [
        'ts'       => time(),
        'user_id'  => $_SESSION['admin_user_id']  ?? null,
        'username' => $_SESSION['admin_username'] ?? '',
        'action'   => $action,
        'details'  => $details,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    if (count($entries) > SL_ACTIVITY_LOG_MAX_ENTRIES) {
        $entries = array_slice($entries, -SL_ACTIVITY_LOG_MAX_ENTRIES);
    }

    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok   = false;
    if ($json !== false) {
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, $json) !== false;
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return $ok;
}

function sl_admin_load_activity_log(): array
{
    $path = sl_admin_activity_log_path();
    if (!file_exists($path)) return [];
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

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
    foreach (['article', 'page', 'project'] as $type) {
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

    foreach (['article', 'page', 'project'] as $type) {
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

    foreach (['article', 'page', 'project'] as $type) {
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
    return [
        'article'    => sl_load_all_items('article'),
        'page'       => sl_load_all_items('page'),
        'project'    => sl_load_all_items('project'),
        'categories' => sl_load_categories(),
        'tags'       => sl_load_tags(),
    ];
}
