<?php
/**
 * One-time migration script covering two architecture refactors:
 *
 *   v1.3.3 — /core/ restructure: core PHP files moved from root to /core/,
 *             settings.json renamed to config.json, plugins.json moved to /plugins/.
 *
 *   v1.3.4 — feed.php and search.php moved from root to /core/.
 *
 * Runs automatically on the first page load after each update. Cleans up
 * legacy files left at the CMS root. Deletes itself once all migrations
 * are complete — zero residual code in the codebase.
 *
 * Trigger conditions (either causes the script to run):
 *   - settings.json present at root  → v1.3.3 migration pending
 *   - feed.php or search.php present at root → v1.3.4 migration pending
 */

$__mRoot            = __DIR__;
$__mHasLegacyConfig = file_exists($__mRoot . '/settings.json');
$__mHasLegacyRoot   = file_exists($__mRoot . '/feed.php') || file_exists($__mRoot . '/search.php');

if (!$__mHasLegacyConfig && !$__mHasLegacyRoot) {
    // All migrations already applied — self-destruct.
    @unlink(__FILE__);
    return;
}

// ── 1. Remove legacy PHP files moved to /core/ ──────────────────────────────
// Covers both v1.3.3 (core restructure) and v1.3.4 (feed + search moved).
// Safe to attempt unconditionally: unlink() on an absent file is a no-op.
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
    'feed.php',
    'search.php',
];

foreach ($__mLegacyFiles as $__mFile) {
    $__mPath = $__mRoot . '/' . $__mFile;
    if (file_exists($__mPath)) {
        @unlink($__mPath);
    }
}
unset($__mFile, $__mPath);

// ── 2. Rename settings.json → config.json (v1.3.3 only) ─────────────────────
if ($__mHasLegacyConfig) {
    $__mOldConfig = $__mRoot . '/settings.json';
    $__mNewConfig = $__mRoot . '/config.json';

    if (!file_exists($__mNewConfig)) {
        // Normal path: plain filesystem rename, no JSON parsing.
        // All site settings (including the custom menu) are preserved as-is.
        rename($__mOldConfig, $__mNewConfig);
    } else {
        // config.json already exists — merge rather than silently discard.
        // settings.json values take priority (it holds the live configuration).
        // settings.json is only removed if the merged write succeeds.
        $__mOldRaw  = file_get_contents($__mOldConfig);
        $__mOldData = $__mOldRaw !== false ? json_decode($__mOldRaw, true) : null;
        if (is_array($__mOldData)) {
            $__mNewRaw  = file_get_contents($__mNewConfig);
            $__mNewData = $__mNewRaw !== false ? json_decode($__mNewRaw, true) : null;
            $__mMerged  = is_array($__mNewData) ? array_merge($__mNewData, $__mOldData) : $__mOldData;
            $__mWriteOk = file_put_contents(
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
}

// ── 3. Move plugins.json into /plugins/ (v1.3.3 only) ───────────────────────
if ($__mHasLegacyConfig) {
    $__mOldRegistry = $__mRoot . '/plugins.json';
    $__mNewRegistry = $__mRoot . '/plugins/plugins.json';
    if (file_exists($__mOldRegistry)) {
        if (!is_dir($__mRoot . '/plugins')) {
            @mkdir($__mRoot . '/plugins', 0755, true);
        }
        if (!file_exists($__mNewRegistry)) {
            rename($__mOldRegistry, $__mNewRegistry);
        } else {
            @unlink($__mOldRegistry);
        }
    }
}

// ── 4. Self-destruct ─────────────────────────────────────────────────────────
@unlink(__FILE__);

unset($__mRoot, $__mHasLegacyConfig, $__mHasLegacyRoot, $__mLegacyFiles,
      $__mOldConfig, $__mNewConfig, $__mOldRaw, $__mOldData,
      $__mNewRaw, $__mNewData, $__mMerged, $__mWriteOk,
      $__mOldRegistry, $__mNewRegistry);
