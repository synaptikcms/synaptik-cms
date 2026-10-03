<?php

function sl_data_dir(): string
{
    return CMS_ROOT . '/data';
}

function sl_type_dir(string $type): string
{
    if (in_array($type, ['article', 'page', 'project'], true)) {
        return $type . 's';
    }
    return $type;
}

function sl_index_path(string $type): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/_index.json';
}

function sl_item_path(string $type, string $fileSlug): string
{
    $fileSlug = basename($fileSlug);
    return sl_data_dir() . '/' . sl_type_dir($type) . '/' . $fileSlug . '.json';
}

function sl_load_index(string $type): array
{
    $globalsKey    = 'idx_' . $type;
    $persistentKey = 'sl_idx_' . $type;
    $cached = _sl_cache_get($globalsKey);
    if ($cached !== null) return $cached;
    $isAdmin = defined('LANG_CONTEXT') && LANG_CONTEXT === 'admin';
    if (!$isAdmin) {
        $persistent = _sl_persistent_get($persistentKey);
        if ($persistent !== null) {
            _sl_cache_set($globalsKey, $persistent);
            return $persistent;
        }
    }
    $path = sl_index_path($type);
    if (!file_exists($path)) {
        _sl_cache_set($globalsKey, []);
        return [];
    }

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    if (!$isAdmin) {
        $now    = time();
        $result = array_values(array_filter($result, function (array $item) use ($now): bool {
            $status = $item['status'] ?? 'published';
            if ($status === 'draft' || $status === 'unpublished') return false;
            if ($status === 'scheduled') {
                $at = isset($item['publish_at']) ? strtotime($item['publish_at']) : false;
                return $at !== false && $at <= $now;
            }
            return true;
        }));
    }

    _sl_cache_set($globalsKey, $result);

    if (!$isAdmin) {
        _sl_persistent_set($persistentKey, $result);
    }

    return $result;
}

function sl_invalidate_index_cache(?string $type = null): void
{
    $types = ($type !== null) ? [$type] : sl_all_type_slugs();

    foreach ($types as $t) {
        _sl_cache_del('idx_' . $t);
        _sl_persistent_del('sl_idx_' . $t);
    }
}

function sl_file_slug(array $entry): string
{
    if (!empty($entry['_file'])) return $entry['_file'];
    if (!empty($entry['custom_slug'])) return $entry['custom_slug'];
    return $entry['slug'] ?? '';
}

function sl_effective_slug(array $item): string
{
    return !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
}

function sl_load_item(string $type, string $fileSlug): ?array
{
    if ($fileSlug === '') return null;

    $path = sl_item_path($type, $fileSlug);
    if (!file_exists($path)) return null;

    $raw  = file_get_contents($path);
    if ($raw === false || $raw === '') return null;

    $item = json_decode($raw, true);
    return is_array($item) ? $item : null;
}

function sl_find_in_index(string $type, string $effectiveSlug): ?array
{
    $index = sl_load_index($type);
    foreach ($index as $pos => $entry) {
        if (sl_effective_slug($entry) === $effectiveSlug) {
            return [$entry, $pos];
        }
    }
    return null;
}

function sl_load_item_by_slug(string $type, string $effectiveSlug): ?array
{
    $found = sl_find_in_index($type, $effectiveSlug);
    if ($found !== null) {
        [$entry] = $found;
        return sl_load_item($type, sl_file_slug($entry));
    }
    return null;
}

function sl_load_index_unfiltered(string $type): array
{
    $path = sl_index_path($type);
    if (!file_exists($path)) return [];

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function sl_load_item_by_slug_unfiltered(string $type, string $effectiveSlug): ?array
{
    foreach (sl_load_index_unfiltered($type) as $entry) {
        if (is_array($entry) && sl_effective_slug($entry) === $effectiveSlug) {
            return sl_load_item($type, sl_file_slug($entry));
        }
    }
    return null;
}

function sl_load_all_items(string $type): array
{
    $index = sl_load_index($type);
    $items = [];
    foreach ($index as $entry) {
        $item = sl_load_item($type, sl_file_slug($entry));
        if ($item !== null) {
            $items[] = $item;
        }
    }
    return $items;
}

