<?php
/**
 * One-time migration script for the /core/ architecture refactor (v1.3.3).
 *
 * Runs automatically on the first page load after update. Cleans up legacy
 * files left at the CMS root that were moved into /core/ and /core/render/,
 * renames settings.json → config.json, and moves plugins.json into /plugins/.
 *
 * Deletes itself when done — zero residual code in the codebase.
 */

// Only run from index.php (CMS_ROOT is defined by core/functions.php,
// but migrate.php fires before that — use __DIR__ which is the CMS root).
$__mRoot      = __DIR__;
$__mOldConfig = $__mRoot . '/settings.json';
$__mLockFile  = $__mRoot . '/migrate.lock';

// ── 0. Guard: only run for a real update, on an install that needs it ──────
// migrate.lock is written by admin/templates/update.php right after a
// successful update copy — it is the only proof this migrate.php was just
// deposited by that flow. Without it (fresh install, restored backup,
// manual re-upload that happens to include this file), refuse to run and
// self-destruct quietly instead of touching files that may not need it.
// settings.json presence is a second, independent check: an install that
// already completed a previous migration has no settings.json left.
if (!file_exists($__mLockFile) || !file_exists($__mOldConfig)) {
    @unlink(__FILE__);
    @unlink($__mLockFile);
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
    $__mOldData = json_decode(file_get_contents($__mOldConfig), true);
    $__mNewData = json_decode(file_get_contents($__mNewConfig), true);
    if (is_array($__mOldData)) {
        $__mMerged = is_array($__mNewData) ? array_merge($__mNewData, $__mOldData) : $__mOldData;
        file_put_contents(
            $__mNewConfig,
            json_encode($__mMerged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
    @unlink($__mOldConfig);
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
@unlink($__mLockFile);

unset($__mRoot, $__mLegacyFiles, $__mFile, $__mPath, $__mLockFile,
      $__mOldConfig, $__mNewConfig, $__mOldData, $__mNewData, $__mMerged,
      $__mOldRegistry, $__mNewRegistry);
