<?php
/**
 * One-time migration script for the /core/ architecture refactor (v1.3.3).
 *
 * Runs automatically on the first page load after update. Cleans up legacy
 * files left at the CMS root that were moved into /core/ and /core/render/,
 * renames settings.json → config.json, and moves plugins.json into /plugins/.
 *
 * Deletes itself when done — zero residual code in the codebase.
 *
 * Trigger condition: settings.json still present at the CMS root. That file
 * only exists on an install that hasn't completed this migration yet — a
 * fresh v1.3.3+ install never creates it, and a previously-migrated site has
 * already renamed it to config.json. No other guard is needed: this script
 * ships inside the release ZIP itself, so its mere presence on disk already
 * means it was deposited by a real update.
 */

$__mRoot      = __DIR__;
$__mOldConfig = $__mRoot . '/settings.json';

if (!file_exists($__mOldConfig)) {
    // Already migrated (or a fresh install that never had settings.json).
    // Nothing to do — just remove this script so it stops running on
    // every request.
    @unlink(__FILE__);
    return;
}

// ── 1. Remove legacy PHP files moved to /core/ ──────────────────────────────
$__mLegacyFiles = [
    'functions.php',
    'core-functions.php',
    'data-functions.php',
    'data-layer.php',
    'admin-data-layer.php',
    'lang-cache.php',
    'plugin-api.php',
    'theme-api.php',
    'tf-cards.php',
    'tf-markdown.php',
    'tf-navigation.php',
    'tf-page.php',
    'tf-shortcodes.php',
    'contact-process.php',
];

foreach ($__mLegacyFiles as $__mFile) {
    $__mPath = $__mRoot . '/' . $__mFile;
    if (file_exists($__mPath)) {
        @unlink($__mPath);
    }
}

// ── 2. Rename settings.json → config.json ────────────────────────────────────
$__mNewConfig = $__mRoot . '/config.json';
if (!file_exists($__mNewConfig)) {
    // Normal path: config.json doesn't exist yet — plain filesystem rename,
    // byte-for-byte, no JSON parsing involved. All site settings (including
    // the custom menu) are preserved exactly as they were.
    rename($__mOldConfig, $__mNewConfig);
} else {
    // config.json already exists (e.g. a prior partial migration, or a stray
    // file left by a previous install attempt). A silent no-op here would
    // strand settings.json on disk, unread by loadConfig(), which looks like
    // a full settings reset (custom menu, theme, everything) even though
    // nothing was actually deleted. Merge instead: settings.json holds the
    // real, actively-used site configuration, so its values take priority
    // over whatever is already in config.json.
    //
    // settings.json is only removed if the merge actually succeeds — never
    // unconditionally. A read/decode failure here (permissions, corrupt
    // file) leaves settings.json untouched on disk rather than silently
    // discarding it with nothing written in its place.
    $__mOldRaw  = file_get_contents($__mOldConfig);
    $__mOldData = $__mOldRaw !== false ? json_decode($__mOldRaw, true) : null;
    if (is_array($__mOldData)) {
        $__mNewRaw    = file_get_contents($__mNewConfig);
        $__mNewData   = $__mNewRaw !== false ? json_decode($__mNewRaw, true) : null;
        $__mMerged    = is_array($__mNewData) ? array_merge($__mNewData, $__mOldData) : $__mOldData;
        $__mWriteOk   = file_put_contents(
            $__mNewConfig,
            json_encode($__mMerged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ) !== false;
        if ($__mWriteOk) {
            unlink($__mOldConfig);
        } else {
            error_log('[SynaptikCMS migrate.php] Failed to write merged config.json — settings.json left in place for manual recovery.');
        }
    } else {
        error_log('[SynaptikCMS migrate.php] Could not read/decode settings.json — left in place untouched. config.json was not modified.');
    }
}

// ── 3. Move plugins.json into /plugins/ ──────────────────────────────────────
$__mOldRegistry = $__mRoot . '/plugins.json';
$__mNewRegistry = $__mRoot . '/plugins/plugins.json';
if (file_exists($__mOldRegistry)) {
    if (!is_dir($__mRoot . '/plugins')) {
        @mkdir($__mRoot . '/plugins', 0755, true);
    }
    // Only move if destination doesn't already exist (avoid overwriting
    // a registry that was already migrated by a manual update).
    if (!file_exists($__mNewRegistry)) {
        rename($__mOldRegistry, $__mNewRegistry);
    } else {
        @unlink($__mOldRegistry);
    }
}

// ── 4. Self-destruct ─────────────────────────────────────────────────────────
@unlink(__FILE__);

unset($__mRoot, $__mLegacyFiles, $__mFile, $__mPath,
      $__mOldConfig, $__mNewConfig, $__mOldData, $__mNewData, $__mMerged,
      $__mOldRegistry, $__mNewRegistry);
