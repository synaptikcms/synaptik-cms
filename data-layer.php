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
 * Cache strategy (3 levels, fastest first):
 *   1. APCu  — shared memory, survives across requests (TTL: SL_CACHE_TTL seconds)
 *   2. File  — serialized PHP array in /cache/ (TTL: SL_CACHE_TTL seconds)
 *   3. Disk  — raw JSON read (always available)
 *
 * APCu is used when available; the file cache is the fallback.
 * The in-request GLOBALS cache (level 0) sits on top and avoids any I/O
 * for repeated reads within the same PHP request.
 *
 * Cache invalidation: every sl_admin_write_index() / sl_admin_save_*()
 * call in admin-data-layer.php triggers sl_invalidate_index_cache(), which
 * purges all three levels so the next front-end request sees fresh data.
 */

if (defined('SL_DATA_LAYER_LOADED')) return;
define('SL_DATA_LAYER_LOADED', true);

// Persistent cache TTL in seconds (safety net — explicit invalidation on write
// should always fire first).
define('SL_CACHE_TTL', 60);

// ─── Internal GLOBALS-based request cache ─────────────────────────────────────
// Level 0: per-request only. Using GLOBALS so admin-data-layer.php can
// invalidate entries after writes within the same PHP request.

function _sl_cache_get(string $key)
{
    return $GLOBALS['_sl_cache'][$key] ?? null;
}

function _sl_cache_set(string $key, $value): void
{
    if (!isset($GLOBALS['_sl_cache'])) {
        $GLOBALS['_sl_cache'] = [];
    }
    $GLOBALS['_sl_cache'][$key] = $value;
}

function _sl_cache_del(string $key): void
{
    unset($GLOBALS['_sl_cache'][$key]);
}

// ─── Persistent cache helpers (APCu + file fallback) ──────────────────────────

/**
 * Returns the absolute path to the persistent file cache directory.
 * __DIR__ is the CMS root (the directory containing this file).
 */
function _sl_cache_dir(): string
{
    return __DIR__ . '/cache';
}

/**
 * Returns the file path for a given persistent cache key.
 *
 * @param string $key  Cache key (e.g. 'sl_idx_article').
 */
function _sl_cache_file(string $key): string
{
    return _sl_cache_dir() . '/' . $key . '.cache';
}

/**
 * Read a value from the persistent cache (APCu first, then file).
 * Returns null on miss or when both backends are unavailable.
 *
 * @param  string $key  Cache key.
 * @return mixed|null
 */
function _sl_persistent_get(string $key)
{
    // APCu
    if (function_exists('apcu_fetch')) {
        $success = false;
        $value   = apcu_fetch($key, $success);
        if ($success) return $value;
    }

    // File cache
    $file = _sl_cache_file($key);
    if (!file_exists($file)) return null;
    if ((time() - filemtime($file)) >= SL_CACHE_TTL) return null;

    $raw = file_get_contents($file);
    if ($raw === false) return null;

    $data = @unserialize($raw);
    return ($data !== false) ? $data : null;
}

/**
 * Write a value to the persistent cache (APCu + file).
 * Silently skips unavailable backends.
 *
 * @param string $key    Cache key.
 * @param mixed  $value  Value to cache.
 */
function _sl_persistent_set(string $key, $value): void
{
    // APCu
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, SL_CACHE_TTL);
    }

    // File cache
    $dir = _sl_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_writable($dir)) {
        $tmp  = _sl_cache_file($key) . '.tmp';
        $file = _sl_cache_file($key);
        if (file_put_contents($tmp, serialize($value), LOCK_EX) !== false) {
            rename($tmp, $file);
        }
    }
}

/**
 * Delete a value from all persistent cache backends.
 *
 * @param string $key  Cache key.
 */
function _sl_persistent_del(string $key): void
{
    // APCu
    if (function_exists('apcu_delete')) {
        apcu_delete($key);
    }

    // File cache
    $file = _sl_cache_file($key);
    if (file_exists($file)) {
        @unlink($file);
    }
}

/**
 * Purge all SynaptikCMS persistent cache entries (content indices + taxonomy).
 * Removes all *.cache files from /cache/ and clears matching APCu keys.
 * Called by the admin "Clear Cache" action.
 */
function sl_clear_all_cache(): void
{
    // APCu — clear only sl_* keys to avoid nuking unrelated entries
    if (function_exists('apcu_delete') && function_exists('apcu_cache_info')) {
        $info = @apcu_cache_info(false);
        if (isset($info['cache_list'])) {
            foreach ($info['cache_list'] as $entry) {
                $k = $entry['info'] ?? $entry['key'] ?? '';
                if (strncmp($k, 'sl_', 3) === 0) {
                    apcu_delete($k);
                }
            }
        }
    }

    // File cache — delete all *.cache files
    $dir = _sl_cache_dir();
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
        // Also remove stale .tmp leftovers
        foreach (glob($dir . '/*.cache.tmp') ?: [] as $f) {
            @unlink($f);
        }
    }

    // Reset the in-request GLOBALS cache
    $GLOBALS['_sl_cache'] = [];
}

// ─── Path helpers ──────────────────────────────────────────────────────────────

function sl_data_dir(): string
{
    return __DIR__ . '/data';
}

function sl_type_dir(string $type): string
{
    return $type . 's';
}

function sl_index_path(string $type): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/_index.json';
}

function sl_item_path(string $type, string $fileSlug): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/' . $fileSlug . '.json';
}

// ─── Index loading ─────────────────────────────────────────────────────────────

/**
 * Loads and returns the lightweight index for a content type.
 *
 * Cache lookup order: GLOBALS (L0) → APCu/file (L1/L2) → disk (L3).
 * Results are stored in every available layer on a cache miss.
 *
 * @param  string $type  Internal type name (article, page, project).
 * @return array         Array of index entries, or [] if file not found.
 */
function sl_load_index(string $type): array
{
    $globalsKey    = 'idx_' . $type;
    $persistentKey = 'sl_idx_' . $type;

    // L0 — in-request GLOBALS cache
    $cached = _sl_cache_get($globalsKey);
    if ($cached !== null) return $cached;

    // L1/L2 — APCu or file cache (only on front-end; admin always reads fresh data)
    $isAdmin = defined('LANG_CONTEXT') && LANG_CONTEXT === 'admin';
    if (!$isAdmin) {
        $persistent = _sl_persistent_get($persistentKey);
        if ($persistent !== null) {
            _sl_cache_set($globalsKey, $persistent);
            return $persistent;
        }
    }

    // L3 — disk
    $path = sl_index_path($type);
    if (!file_exists($path)) {
        _sl_cache_set($globalsKey, []);
        return [];
    }

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set($globalsKey, $result);

    if (!$isAdmin) {
        _sl_persistent_set($persistentKey, $result);
    }

    return $result;
}

/**
 * Invalidates the index cache for one type, or all types if null is passed.
 * Purges GLOBALS (L0), APCu (L1), and file cache (L2).
 *
 * @param string|null $type  Type to invalidate, or null for all.
 */
function sl_invalidate_index_cache(?string $type = null): void
{
    $types = ($type !== null) ? [$type] : ['article', 'page', 'project'];

    foreach ($types as $t) {
        _sl_cache_del('idx_' . $t);
        _sl_persistent_del('sl_idx_' . $t);
    }
}

// ─── Item loading ──────────────────────────────────────────────────────────────

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

    return sl_load_item($type, $effectiveSlug);
}

/**
 * Loads ALL full item files for a given type in index order.
 *
 * WARNING: expensive for large datasets. Only use when the full content
 * body is needed (search, SEO overview, sitemap).
 *
 * @param  string $type  Internal type name.
 * @return array
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

function sl_load_categories(): array
{
    $globalsKey    = 'categories';
    $persistentKey = 'sl_categories';

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

    $path    = sl_data_dir() . '/categories.json';
    $raw     = file_exists($path) ? file_get_contents($path) : false;
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set($globalsKey, $result);
    if (!$isAdmin) {
        _sl_persistent_set($persistentKey, $result);
    }

    return $result;
}

function sl_load_tags(): array
{
    $globalsKey    = 'tags';
    $persistentKey = 'sl_tags';

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

    $path    = sl_data_dir() . '/tags.json';
    $raw     = file_exists($path) ? file_get_contents($path) : false;
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    _sl_cache_set($globalsKey, $result);
    if (!$isAdmin) {
        _sl_persistent_set($persistentKey, $result);
    }

    return $result;
}

// ─── Taxonomy cache invalidation ──────────────────────────────────────────────

/**
 * Invalidates the persistent cache for categories or tags after a write.
 * Called by sl_admin_save_categories() and sl_admin_save_tags().
 *
 * @param string $type  'categories' or 'tags'.
 */
function sl_invalidate_taxonomy_cache(string $type): void
{
    _sl_cache_del($type);
    _sl_persistent_del('sl_' . $type);
}

// ─── Backward-compatibility helper ────────────────────────────────────────────

/**
 * Promotes scheduled items whose publish_at datetime is now in the past.
 *
 * @param string $type  Internal type name (article, page, project).
 */
function sl_promote_scheduled(string $type): void
{
    $index   = sl_load_index($type);
    $now     = time();
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
    $isAdmin = defined('LANG_CONTEXT') && LANG_CONTEXT === 'admin';
    $now     = time();

    $data = [
        'categories' => sl_load_categories(),
        'tags'       => sl_load_tags(),
    ];

    foreach ($types as $type) {
        if (!$isAdmin) {
            sl_promote_scheduled($type);
        }

        $items = $fullItems ? sl_load_all_items($type) : sl_load_index($type);

        if (!$isAdmin) {
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

// ─── Shared utility functions ──────────────────────────────────────────────────

if (!function_exists('format_date')) {
function format_date(string $date): string
{
    if (empty($date)) return '';
    $settings = function_exists('loadSettings') ? loadSettings() : admin_load_settings();
    $ts       = strtotime($date);
    return $ts !== false ? date($settings['date_format'] ?? 'Y-m-d', $ts) : $date;
}
}

if (!function_exists('output_canonical_url')) {
function output_canonical_url(?array $pageData = null): string
{
    if (!empty($pageData['canonical_url'])) {
        return '<link rel="canonical" href="' . htmlspecialchars($pageData['canonical_url']) . '">';
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $uri      = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return '<link rel="canonical" href="' . htmlspecialchars($protocol . '://' . $_SERVER['HTTP_HOST'] . $uri) . '">';
}
}

function loadDefaultSettings(): array
{
    return [
        'articles_per_page'          => 6,
        'projects_per_page'          => 3,
        'show_articles_on_homepage'  => true,
        'show_projects_on_homepage'  => true,
        'show_breadcrumbs'           => false,
        'main_menu'                  => [],
        'use_custom_menu'            => false,
        'show_search_icon'           => false,
        'default_menu_style'         => 'grouped',
        'default_menu_order'         => 'date_desc',
        'site_title'                 => 'Synaptik CMS',
        'site_description'           => 'A powerful, lightweight, blazing fast and very flexible file-based CMS, to create portfolio, personal or business websites in one click.',
        'default_meta_title'         => '{page_title} | {site_title}',
        'default_meta_description'   => '{site_description}',
        'enable_seo'                 => true,
        'show_site_title_in_header'  => true,
        'date_format'                => 'Y-m-d',
        'homepage_type'              => 'default',
        'homepage_page_id'           => '',
        'active_theme'               => 'default',
        'available_themes'           => ['default'],
        'active_language'            => 'en',
        'image_optimization_enabled' => true,
        'max_width'                  => 1920,
        'max_height'                 => 1080,
        'image_quality'              => 85,
        'create_thumbnails'          => true,
        'thumb_width'                => 350,
        'thumb_height'               => 350,
        'convert_to_webp'            => true,
        'footer_text'                => 'Powered by <a href="https://synaptikcms.com">SynaptikCMS</a> • &copy; {year}',
        'footer_show_login'          => false,
        'footer_show_social'         => false,
        'footer_social_links'        => [],
        'autosave_enabled'           => true,
        'autosave_interval'          => 10,
    ];
}
