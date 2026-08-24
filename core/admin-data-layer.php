<?php
/**
 * SynaptikCMS — Split-File Data Layer (admin write functions)
 *
 * Provides all write operations for the split-file architecture.
 * Requires data-layer.php (read functions) to be loaded first.
 *
 * Placement: /core/ (same directory as data-layer.php).
 * Include from admin-functions.php after requiring data-layer.php.
 *
 * Index fields stored per type:
 *
 *   All types:   _file, slug, custom_slug, title, date, category, tags,
 *                image, show_in_menu, menu_order, show_date
 *   Articles:    + summary, show_on_homepage
 *   Projects:    + description, show_on_homepage
 *   Pages:       + page_template
 *
 * Everything else (content body, meta/OG fields, gallery data, other display
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

/**
 * Autosaved drafts live under data/drafts/, alongside trash and revisions —
 * not admin/drafts/, since drafts are content, not admin-panel internals, and
 * admin/ is a renameable folder (admin_dir) that data must not depend on.
 * One-time migration: renames a pre-existing admin/drafts/ (old location,
 * found via resolve_admin_dir() in case the admin folder was itself renamed)
 * into place the first time this is called after upgrading.
 */
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

// ─── Activity log ───────────────────────────────────────────────────────────────
//
// Records sensitive admin actions (logins, template editor saves, extension
// installs, user management, restores) to private/activity-log.json. The
// read-modify-write cycle is protected by flock() end to end — same pattern
// as private/auth_rate.json in admin/auth.php — because two admins acting at
// once would otherwise race between an unlocked read and a locked write.

const SL_ACTIVITY_LOG_MAX_ENTRIES = 2000;

function sl_admin_activity_log_path(): string
{
    return CMS_ROOT . '/private/activity-log.json';
}

/**
 * Appends one entry to the activity log.
 *
 * $action is a stable machine key (e.g. 'login_success', 'user_deleted'),
 * translated to a display label by the viewer — never a human-readable
 * string, so it stays independent of the current UI language.
 *
 * @param  string $action   Machine key identifying the action.
 * @param  string $details  Optional free-form context (e.g. affected username).
 * @return bool             True on success.
 */
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

    // Hard cap — keeps the file small without a separate purge step.
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

/**
 * Loads all activity log entries, most recent last (same order they were written).
 *
 * @return array
 */
function sl_admin_load_activity_log(): array
{
    $path = sl_admin_activity_log_path();
    if (!file_exists($path)) return [];
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

// ─── Individual item write / delete ───────────────────────────────────────────

/**
 * Picks a file slug guaranteed not to collide with an existing item file,
 * appending -2, -3, ... as needed. Falls back to "<type>-<timestamp>" when
 * $effectiveSlug is empty (e.g. a title-less item).
 *
 * @param  string $type            Internal type name.
 * @param  string $effectiveSlug   Preferred slug (custom_slug ?: slug).
 * @return string
 */
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

    // Plugin filter: lets a plugin adjust an item's data on every save
    // (create or edit) before it hits disk.
    $item = pl_apply_filter('item_before_save', $item, $type, $fileSlug);

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

// ─── Trash ──────────────────────────────────────────────────────────────────────
//
// Deletion from the admin UI moves an item's file into data/<type>s/.trash/
// instead of unlinking it. A parallel _index.json under .trash/ tracks the
// display fields plus a trashed_at timestamp, mirroring the live index so
// the trash list never needs to read every trashed item file.

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

/**
 * Moves an item file to the trash and removes it from the live index.
 * Returns false if the item does not exist or the move fails.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @return bool
 */
function sl_admin_trash_item(string $type, string $fileSlug): bool
{
    if ($fileSlug === '') return false;

    $item = sl_load_item($type, $fileSlug);
    if ($item === null) return false;

    $dir = sl_admin_trash_dir($type);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Guard against a name collision with an item already sitting in the
    // trash (delete, recreate the same slug, delete again).
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

/**
 * Moves a trashed item back to the live directory and rebuilds its index
 * entry. If the original slug is now taken, a numeric suffix is appended.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the trashed item.
 * @return bool
 */
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
        // Orphaned index entry — drop it, nothing to restore.
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

/**
 * Permanently deletes a single trashed item (file + trash index entry).
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the trashed item.
 * @return bool
 */
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

/**
 * Empties the trash for every content type. Returns the number of items
 * permanently deleted.
 *
 * @return int
 */
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

/**
 * Permanently deletes trashed items older than $maxAgeDays. Called lazily
 * when the trash admin view loads — there is no cron in this codebase.
 *
 * @param  int $maxAgeDays  Retention period in days.
 * @return int              Number of items purged.
 */
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

// ─── Revisions ──────────────────────────────────────────────────────────────────
//
// Every edit-save snapshots the item's pre-edit state into
// data/<type>s/.revisions/<fileSlug>/ before the new content overwrites the
// live file. Revisions are per-item (unlike trash, no cross-type listing is
// needed to render a history list) so a directory glob is enough — no
// separate index file.

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

/**
 * Lists revisions for an item, newest first.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @return array             ['timestamp' => int, 'path' => string][]
 */
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

/**
 * Loads a single revision's full item data.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @param  int    $timestamp Revision timestamp (its filename, without .json).
 * @return array|null
 */
function sl_admin_load_revision(string $type, string $fileSlug, int $timestamp): ?array
{
    $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    if (!file_exists($path)) return null;
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : null;
}

/**
 * Snapshots an item's current state before it gets overwritten. Called from
 * the edit-save path (with the pre-edit item) and from revision restore
 * (with the pre-restore item), so both directions stay recoverable. Prunes
 * to the last SL_ADMIN_MAX_REVISIONS afterwards.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @param  array  $item      Full item data to snapshot.
 * @return bool
 */
function sl_admin_snapshot_revision(string $type, string $fileSlug, array $item): bool
{
    if ($fileSlug === '') return false;

    $dir = sl_admin_revisions_dir($type, $fileSlug);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Filename is the timestamp — nudge forward on the rare same-second collision.
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

/**
 * Replaces the live item with an old revision's content. Snapshots the
 * pre-restore state first, so restoring is itself undoable, then rebuilds
 * the index entry to match the restored fields.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @param  int    $timestamp Revision timestamp to restore.
 * @return bool
 */
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

/**
 * Moves an item's revision history to follow a new file slug. Called after
 * an edit that changes the effective slug (and therefore the physical
 * filename) — without this, history silently orphans under the old name.
 *
 * @param  string $type         Internal type name.
 * @param  string $oldFileSlug  Previous _file value.
 * @param  string $newFileSlug  New _file value.
 */
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

    // Rare: the destination slug already has its own history (e.g. reused
    // after a prior rename) — merge file by file, nudging on collision.
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

/**
 * Removes all revisions for an item. Called when the item itself is
 * permanently purged from trash, so history does not outlive the content.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 */
function sl_admin_delete_all_revisions(string $type, string $fileSlug): void
{
    $dir = sl_admin_revisions_dir($type, $fileSlug);
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.json') as $file) { @unlink($file); }
    @rmdir($dir);
}

/**
 * Deletes a single revision from an item's history.
 *
 * @param  string $type      Internal type name.
 * @param  string $fileSlug  The _file value identifying the item.
 * @param  int    $timestamp Revision timestamp to delete.
 * @return bool
 */
function sl_admin_delete_revision(string $type, string $fileSlug, int $timestamp): bool
{
    $path = sl_admin_revision_path($type, $fileSlug, $timestamp);
    if (!file_exists($path)) return false;
    return @unlink($path);
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
