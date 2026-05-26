<?php
/**
 * SynaptikCMS — Split-File Data Layer (read-only)
 *
 * Replaces the monolithic data.json with a split-file architecture:
 *
 *   data/{type}s/_index.json   — lightweight metadata array (no content body)
 *   data/{type}s/{slug}.json   — full item data including content body
 *   data/categories.json       — categories store
 *   data/tags.json             — tags store
 *
 * All public functions are prefixed sl_ (SynaptikLayer).
 * Internal helpers are prefixed _sl_ and should not be called directly.
 *
 * This file is safe to require from both the front-end (CMS root) and the
 * admin (deevious/includes/). __DIR__ is always resolved to the file's own
 * location, so sl_data_dir() always returns the correct absolute path
 * regardless of the calling context.
 */

// Guard against double-inclusion
if (defined('SL_DATA_LAYER_LOADED')) return;
define('SL_DATA_LAYER_LOADED', true);

// ─── Internal GLOBALS-based cache ─────────────────────────────────────────────
// Using GLOBALS instead of static so the admin data layer can invalidate entries
// after writes that occur within the same PHP request.

/**
 * Read a value from the internal cache. Returns null on cache miss.
 *
 * @param  string $key
 * @return mixed|null
 */
function _sl_cache_get(string $key)
{
    return $GLOBALS['_sl_cache'][$key] ?? null;
}

/**
 * Write a value to the internal cache.
 *
 * @param string $key
 * @param mixed  $value
 */
function _sl_cache_set(string $key, $value): void
{
    if (!isset($GLOBALS['_sl_cache'])) {
        $GLOBALS['_sl_cache'] = [];
    }
    $GLOBALS['_sl_cache'][$key] = $value;
}

/**
 * Delete a specific entry from the internal cache.
 * Called by admin-data-layer.php after every write operation.
 *
 * @param string $key
 */
function _sl_cache_del(string $key): void
{
    unset($GLOBALS['_sl_cache'][$key]);
}

// ─── Path helpers ──────────────────────────────────────────────────────────────

/**
 * Returns the absolute path to the data/ directory.
 * __DIR__ here is always the CMS root (the directory containing this file).
 */
function sl_data_dir(): string
{
    return __DIR__ . '/data';
}

/**
 * Maps an internal content type name to its subdirectory name.
 * article → articles, page → pages, project → projects
 *
 * @param  string $type  Internal type name (singular).
 * @return string        Directory name (plural).
 */
function sl_type_dir(string $type): string
{
    return $type . 's';
}

/**
 * Returns the absolute path to the lightweight index file for a content type.
 *
 * @param  string $type  Internal type name (article, page, project).
 */
function sl_index_path(string $type): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/_index.json';
}

/**
 * Returns the absolute path to a single item file.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The value stored in the _file field of the index entry.
 */
function sl_item_path(string $type, string $fileSlug): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/' . $fileSlug . '.json';
}

// ─── Index loading ─────────────────────────────────────────────────────────────

/**
 * Loads and returns the lightweight index for a content type.
 *
 * The index contains only metadata fields (title, slug, date, category, tags,
 * image, summary, etc.) — never the content body. This makes it fast to load
 * for listing pages, routing, the homepage, and navigation rendering.
 *
 * Results are cached in $GLOBALS['_sl_cache'] for the lifetime of the request.
 * The admin data layer invalidates the cache after every write.
 *
 * @param  string $type  Internal type name (article, page, project).
 * @return array         Array of index entries, or [] if file not found.
 */
function sl_load_index(string $type): array
{
    $cacheKey = 'idx_' . $type;
    $cached = _sl_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $path = sl_index_path($type);
    if (!file_exists($path)) {
        return _sl_cache_set($cacheKey, []) ?? [];
    }

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set($cacheKey, $result);
    return $result;
}

/**
 * Invalidates the index cache for one type, or all types if null is passed.
 * Exposed publicly so admin-data-layer.php can call it without using _sl_ internals.
 *
 * @param string|null $type  Type to invalidate, or null for all.
 */
function sl_invalidate_index_cache(?string $type = null): void
{
    if ($type !== null) {
        _sl_cache_del('idx_' . $type);
        return;
    }
    foreach (['article', 'page', 'project'] as $t) {
        _sl_cache_del('idx_' . $t);
    }
}

// ─── Item loading ──────────────────────────────────────────────────────────────

/**
 * Returns the file slug (_file) from an index entry.
 * Prefers the explicit _file field; falls back to custom_slug then slug.
 * This fallback exists for index entries that predate the _file field.
 *
 * @param  array  $entry  Index entry array.
 * @return string         File slug (used as basename without .json).
 */
function sl_file_slug(array $entry): string
{
    if (!empty($entry['_file'])) return $entry['_file'];
    if (!empty($entry['custom_slug'])) return $entry['custom_slug'];
    return $entry['slug'] ?? '';
}

/**
 * Computes the effective public slug for an item (custom_slug if set, else slug).
 * This is the value visible in URLs, not the filename.
 *
 * @param  array  $item  Index entry or full item array.
 * @return string
 */
function sl_effective_slug(array $item): string
{
    return !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
}

/**
 * Loads a single full item by its file slug (_file value).
 * Returns null if the file does not exist or cannot be decoded.
 *
 * @param  string      $type      Internal type name.
 * @param  string      $fileSlug  The _file value from the index entry.
 * @return array|null
 */
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

/**
 * Finds an index entry by effective public slug (custom_slug ?: slug).
 * Returns [array $indexEntry, int $indexPosition] or null if not found.
 *
 * @param  string     $type           Internal type name.
 * @param  string     $effectiveSlug  The public-facing slug to look up.
 * @return array|null                 [entry, position] pair, or null.
 */
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

/**
 * Loads a single full item by its effective public slug.
 *
 * First looks up the index to find the correct _file value, then loads the
 * item file. If the slug is not found in the index, falls back to a direct
 * filename attempt (handles edge cases from old data or direct file access).
 *
 * @param  string      $type           Internal type name.
 * @param  string      $effectiveSlug  The public-facing slug (from URL).
 * @return array|null                  Full item data, or null if not found.
 */
function sl_load_item_by_slug(string $type, string $effectiveSlug): ?array
{
    $found = sl_find_in_index($type, $effectiveSlug);
    if ($found !== null) {
        [$entry] = $found;
        return sl_load_item($type, sl_file_slug($entry));
    }

    // Fallback: try a direct filename match (in case index is out of sync)
    return sl_load_item($type, $effectiveSlug);
}

/**
 * Loads ALL full item files for a given type in index order.
 *
 * WARNING: This is expensive for large datasets. Only use when the full content
 * body is actually needed (search, SEO overview, sitemap with descriptions, etc.)
 * For listing pages, routing, and navigation, use sl_load_index() instead.
 *
 * @param  string  $type  Internal type name.
 * @return array          Array of full item arrays, in the same order as the index.
 */
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

// ─── Categories and tags ───────────────────────────────────────────────────────

/**
 * Loads the categories store.
 *
 * @return array
 */
function sl_load_categories(): array
{
    $cached = _sl_cache_get('categories');
    if ($cached !== null) return $cached;

    $path    = sl_data_dir() . '/categories.json';
    $raw     = file_exists($path) ? file_get_contents($path) : false;
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set('categories', $result);
    return $result;
}

/**
 * Loads the tags store.
 *
 * @return array
 */
function sl_load_tags(): array
{
    $cached = _sl_cache_get('tags');
    if ($cached !== null) return $cached;

    $path    = sl_data_dir() . '/tags.json';
    $raw     = file_exists($path) ? file_get_contents($path) : false;
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set('tags', $result);
    return $result;
}

// ─── Backward-compatibility helper ────────────────────────────────────────────

/**
 * Builds the legacy $data array structure used throughout the CMS.
 *
 * This function exists for backward compatibility during the migration phase.
 * It allows existing code that expects $data['article'], $data['page'], etc.
 * to keep working while each file is migrated to use the new functions.
 *
 * @param string[] $types      Content types to include in the output array.
 * @param bool     $fullItems  If true, load full item content (body, meta, etc.)
 *                             for each type. If false, use the lightweight index
 *                             only (much faster — no body content loaded).
 *                             Pass true only when body content is actually needed
 *                             (e.g. search.php). Pass false for routing, listing,
 *                             and navigation rendering.
 *
 * @return array  Legacy-style data array: ['article' => [...], 'categories' => [...], ...]
 */
/**
 * Promotes scheduled items whose publish_at datetime is now in the past.
 * Updates both the _index.json entry and the individual item file on disk.
 * Called automatically by sl_build_data_array() for front-end requests.
 * No-op when nothing is due, so the cost on ordinary requests is one array scan.
 *
 * @param string $type  Internal type name (article, page, project).
 */
function sl_promote_scheduled(string $type): void
{
    $index = sl_load_index($type);
    $now   = time();
    $changed = false;

    foreach ($index as $pos => $entry) {
        if (($entry['status'] ?? '') !== 'scheduled') continue;

        $publishAt = isset($entry['publish_at']) ? strtotime($entry['publish_at']) : false;
        if ($publishAt === false || $publishAt > $now) continue;

        $index[$pos]['status'] = 'published';
        $changed = true;

        $itemPath = sl_item_path($type, sl_file_slug($entry));
        if (file_exists($itemPath)) {
            $raw  = file_get_contents($itemPath);
            $item = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($item)) {
                $item['status'] = 'published';
                file_put_contents(
                    $itemPath,
                    json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }

    if ($changed) {
        file_put_contents(
            sl_index_path($type),
            json_encode(array_values($index), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        sl_invalidate_index_cache($type);
    }
}

function sl_build_data_array(
    array $types     = ['article', 'page', 'project'],
    bool  $fullItems = false
): array {
    // LANG_CONTEXT is defined as 'admin' by admin-functions.php on every admin request.
    // Its absence means we are serving the public front-end.
    $isAdmin = defined('LANG_CONTEXT') && LANG_CONTEXT === 'admin';
    $now     = time();

    $data = [
        'categories' => sl_load_categories(),
        'tags'       => sl_load_tags(),
    ];

    foreach ($types as $type) {
        if (!$isAdmin) {
            // Promote any scheduled items that are now due before reading the index.
            sl_promote_scheduled($type);
        }

        $items = $fullItems ? sl_load_all_items($type) : sl_load_index($type);

        if (!$isAdmin) {
            // Hide items that are still scheduled for a future datetime.
            $items = array_values(array_filter($items, function (array $item) use ($now): bool {
                if (($item['status'] ?? 'published') !== 'scheduled') return true;
                $at = isset($item['publish_at']) ? strtotime($item['publish_at']) : false;
                return $at !== false && $at <= $now;
            }));
        }

        $data[$type] = $items;
    }

    return $data;
}
