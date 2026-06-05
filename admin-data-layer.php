<?php
/**
 * SynaptikCMS — Split-File Data Layer (admin write functions)
 *
 * Provides all write operations for the split-file architecture.
 * Requires data-layer.php (read functions) to be loaded first.
 *
 * Placement: CMS root (same directory as data-layer.php).
 * Include from admin-functions.php after requiring data-layer.php.
 *
 * Index fields stored per type:
 *
 *   All types:   _file, slug, custom_slug, title, date, category, tags,
 *                image, show_in_menu, menu_order
 *   Articles:    + summary, show_on_homepage
 *   Projects:    + description, show_on_homepage
 *   Pages:       + page_template
 *
 * Everything else (content body, meta/OG fields, gallery data, display
 * toggles) lives exclusively in the individual item files.
 */

if (defined('SL_ADMIN_LAYER_LOADED')) return;
define('SL_ADMIN_LAYER_LOADED', true);

// data-layer.php must already be loaded by the caller (admin-functions.php).
// We do NOT re-require it here to avoid double-inclusion issues with the guard.

// ─── Directory bootstrap ───────────────────────────────────────────────────────

/**
 * Ensures the required directory structure exists under data/.
 * Creates data/articles/, data/pages/, data/projects/ if missing.
 * Safe to call multiple times (checks existence first).
 */
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

// ─── Index field extraction ────────────────────────────────────────────────────

/**
 * Defines which fields belong in the lightweight index for each content type.
 *
 * @param  string $type  Internal type name (article, page, project).
 * @return string[]      List of field names to copy to the index entry.
 */
function sl_admin_index_fields(string $type): array
{
    // Fields present in every type's index
    $common = [
        'slug', 'custom_slug', 'title', 'date',
        'category', 'tags', 'image',
        'show_in_menu', 'menu_order',
        'status', 'publish_at',
    ];

    $specific = [
        'article' => ['summary', 'show_on_homepage'],
        'project' => ['description', 'show_on_homepage'],
        'page'    => ['page_template'],
    ];

    return array_merge($common, $specific[$type] ?? []);
}

/**
 * Extracts the lightweight index entry from a full item array.
 *
 * The _file field is NOT extracted from the item (it is not stored inside
 * individual item files). It must be added separately by the caller.
 *
 * @param  string $type  Internal type name.
 * @param  array  $item  Full item data array.
 * @return array         Index entry (without _file).
 */
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

// ─── Unique file slug resolution ───────────────────────────────────────────────

/**
 * Resolves a unique file slug for a new or renamed item.
 *
 * The desired slug is custom_slug ?: slug. If another file in the same type
 * already uses that slug (collision), appends -2, -3, etc. until unique.
 *
 * Optionally pass $currentFileSlug to exclude the item's own current file
 * from the collision check (used during edits where the slug does not change).
 *
 * @param  string      $type             Internal type name.
 * @param  array       $item             Item array (must have slug, custom_slug).
 * @param  string|null $currentFileSlug  Current _file value to exclude from check.
 * @return string                        Unique file slug (without .json extension).
 */
function sl_admin_resolve_file_slug(
    string  $type,
    array   $item,
    ?string $currentFileSlug = null
): string {
    $desired = sl_effective_slug($item);
    if ($desired === '') {
        // Fallback: generate from timestamp if both slug fields are empty
        $desired = $type . '-' . time();
    }

    // Collect all file slugs already in use for this type
    $existing = [];
    foreach (sl_load_index($type) as $entry) {
        $fs = sl_file_slug($entry);
        if ($fs !== $currentFileSlug) {
            $existing[] = $fs;
        }
    }

    // Also check for physical files that might not be in the index yet
    $dir = sl_data_dir() . '/' . sl_type_dir($type);
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.json') as $file) {
            $base = basename($file, '.json');
            if ($base !== '_index' && $base !== $currentFileSlug) {
                if (!in_array($base, $existing)) {
                    $existing[] = $base;
                }
            }
        }
    }

    // Find a unique slug
    $fileSlug = $desired;
    $n = 2;
    while (in_array($fileSlug, $existing)) {
        $fileSlug = $desired . '-' . $n++;
    }

    return $fileSlug;
}

// ─── Atomic JSON write helper ──────────────────────────────────────────────────

/**
 * Writes an array to a JSON file atomically (write to .tmp, then rename).
 * Prevents partial writes from corrupting the file if PHP crashes mid-write.
 *
 * @param  string $path   Absolute path to the target file.
 * @param  array  $data   Data to encode and write.
 * @return bool           True on success.
 */
function _sl_write_json(string $path, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) return false;

    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;

    return rename($tmp, $path);
}

// ─── Individual item write / delete ───────────────────────────────────────────

/**
 * Writes a full item array to its individual file.
 *
 * The _file field is stripped before writing (it belongs to the index, not
 * the item file). The caller is responsible for passing the correct $fileSlug.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  Filename to use (without .json).
 * @param  array  $item      Full item data array.
 * @return bool              True on success.
 */
function sl_admin_save_item(string $type, string $fileSlug, array $item): bool
{
    sl_admin_ensure_dirs();

    // _file is an index-only field — never persisted inside item files
    unset($item['_file']);

    $path = sl_item_path($type, $fileSlug);
    return _sl_write_json($path, $item);
}

/**
 * Deletes an individual item file.
 * Returns true if the file was deleted or did not exist; false on error.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the file to delete.
 * @return bool
 */
function sl_admin_delete_item(string $type, string $fileSlug): bool
{
    if ($fileSlug === '') return false;
    $path = sl_item_path($type, $fileSlug);
    if (!file_exists($path)) return true; // already gone
    return unlink($path);
}

// ─── Index write operations ────────────────────────────────────────────────────

/**
 * Writes an index array to disk and invalidates the in-memory cache.
 *
 * @param  string $type   Internal type name.
 * @param  array  $index  Complete index array to write.
 * @return bool           True on success.
 */
function sl_admin_write_index(string $type, array $index): bool
{
    sl_admin_ensure_dirs();

    $path    = sl_index_path($type);
    $success = _sl_write_json($path, $index);

    if ($success) {
        // Invalidate the read-layer cache so subsequent sl_load_index() calls
        // in the same request see the freshly written data
        sl_invalidate_index_cache($type);
    }

    return $success;
}

/**
 * Adds or updates a single entry in the index.
 *
 * Matching is done by _file value. If an entry with the same _file exists,
 * it is replaced in-place (preserving position). Otherwise appended.
 *
 * When renaming a file slug, pass $oldFileSlug to remove the old entry first.
 *
 * @param  string      $type          Internal type name.
 * @param  array       $indexEntry    New or updated index entry (must have _file).
 * @param  string|null $oldFileSlug   Previous _file value to remove (rename case).
 * @return bool
 */
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

/**
 * Removes a single entry from the index by its _file slug.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value to remove.
 * @return bool
 */
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

// ─── Categories and tags write ────────────────────────────────────────────────

/**
 * Saves the categories store and invalidates its cache.
 *
 * @param  array $categories  Categories array.
 * @return bool
 */
function sl_admin_save_categories(array $categories): bool
{
    $path    = sl_data_dir() . '/categories.json';
    $success = _sl_write_json($path, $categories);
    if ($success) sl_invalidate_taxonomy_cache('categories');
    return $success;
}

/**
 * Saves the tags store and invalidates its cache.
 *
 * @param  array $tags  Tags array.
 * @return bool
 */
function sl_admin_save_tags(array $tags): bool
{
    $path    = sl_data_dir() . '/tags.json';
    $success = _sl_write_json($path, $tags);
    if ($success) sl_invalidate_taxonomy_cache('tags');
    return $success;
}

// ─── Backward-compatibility: full-data save ───────────────────────────────────

/**
 * Saves a full legacy $data array by distributing it to individual files.
 *
 * This is the drop-in replacement for the original admin_save_data() function.
 * It accepts the same $data array structure and writes each item to its own
 * file, rebuilding all index files and deleting orphaned item files.
 *
 * Matching logic for renames:
 *   - Each item's effective slug (custom_slug ?: slug) is computed.
 *   - If an existing index entry shares that effective slug, the same _file
 *     is reused (in-place update, no file rename needed).
 *   - If no existing entry matches, a new unique _file slug is generated.
 *   - Old files whose _file slug is no longer present in the new data are
 *     deleted automatically.
 *
 * @param  array $data  Legacy data array (same structure as old data.json).
 * @return bool         True if all writes succeeded.
 */
function sl_admin_save_all(array $data): bool
{
    sl_admin_ensure_dirs();

    $success = true;

    // Save categories and tags
    if (isset($data['categories'])) {
        if (!sl_admin_save_categories($data['categories'])) $success = false;
    }
    if (isset($data['tags'])) {
        if (!sl_admin_save_tags($data['tags'])) $success = false;
    }

    foreach (['article', 'page', 'project'] as $type) {
        $newItems = $data[$type] ?? [];

        // Build a lookup of the current index: effectiveSlug → _file
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

            // Reuse the existing _file when effective slug has not changed
            if (!empty($oldSlugToFile[$effectiveSlug])) {
                $fileSlug = $oldSlugToFile[$effectiveSlug];
            } else {
                // New item or renamed item — generate a unique file slug
                $fileSlug = $effectiveSlug !== '' ? $effectiveSlug : ($type . '-' . time());
                $base     = $fileSlug;
                $n        = 2;
                while (in_array($fileSlug, $newFileSlugsUsed) ||
                       file_exists(sl_item_path($type, $fileSlug))) {
                    $fileSlug = $base . '-' . $n++;
                }
            }

            $newFileSlugsUsed[] = $fileSlug;

            // Write the full item file (without _file inside)
            if (!sl_admin_save_item($type, $fileSlug, $item)) {
                $success = false;
            }

            // Build the index entry
            $indexEntry          = sl_admin_extract_index_entry($type, $item);
            $indexEntry['_file'] = $fileSlug;
            $newIndex[]          = $indexEntry;
        }

        // Delete item files that are no longer referenced in the new data
        foreach ($oldFileSlugs as $oldSlug) {
            if (!in_array($oldSlug, $newFileSlugsUsed)) {
                sl_admin_delete_item($type, $oldSlug);
            }
        }

        // Write the rebuilt index
        if (!sl_admin_write_index($type, $newIndex)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Loads the full legacy $data array from split files.
 *
 * Drop-in replacement for admin_load_data(). Returns the same structure as the
 * old data.json decode. Loads full item content (body, meta, galleries, etc.)
 * for all types, which is appropriate for admin operations that may read or
 * modify any field.
 *
 * @return array  Legacy-style data array.
 */
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
