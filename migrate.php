<?php
/**
 * One-time migration script covering two architecture refactors:
 *
 *   v1.3.3 — /core/ restructure: core PHP files moved from root to /core/,
 *             settings.json renamed to config.json, plugins.json moved to /plugins/.
 *
 *   v1.3.4 — feed.php and search.php moved from root to /core/.
 *
 *   v1.3.4.1 — plugin-upload.php and theme-upload.php merged into extension-upload.php.
 *
 *   v1.3.5 — site_url added to config.json for canonical password reset URLs.
 *
 * Runs automatically on the first page load after each update. Cleans up
 * legacy files left at the CMS root. Deletes itself once all migrations
 * are complete — zero residual code in the codebase.
 *
 * Trigger conditions (either causes the script to run):
 *   - settings.json present at root  → v1.3.3 migration pending
 *   - feed.php or search.php present at root → v1.3.4 migration pending
 *   - plugin-upload.php or theme-upload.php present in /admin/ → v1.3.4.1 migration pending
 *   - site_url missing from config.json → v1.3.5 migration pending
 */

$__mRoot            = __DIR__;
$__mHasLegacyConfig = file_exists($__mRoot . '/settings.json');
$__mHasLegacyRoot   = file_exists($__mRoot . '/feed.php') || file_exists($__mRoot . '/search.php');

// Resolve admin_dir before checking for legacy upload files.
$__mAdminDirEarly = 'admin';
foreach (['config.json', 'settings.json'] as $__mCf) {
    $__mCfPath = $__mRoot . '/' . $__mCf;
    if (file_exists($__mCfPath)) {
        $__mCfData = json_decode(file_get_contents($__mCfPath), true);
        if (is_array($__mCfData) && !empty($__mCfData['admin_dir'])) {
            $__mAdminDirEarly = $__mCfData['admin_dir'];
            break;
        }
    }
}
$__mHasLegacyUpload = file_exists($__mRoot . '/' . $__mAdminDirEarly . '/plugin-upload.php')
                   || file_exists($__mRoot . '/' . $__mAdminDirEarly . '/theme-upload.php');
unset($__mCf, $__mCfPath, $__mCfData, $__mAdminDirEarly);

// v1.3.5: check if site_url is missing from config.json
$__mNeedsSiteUrl = false;
if (file_exists($__mRoot . '/config.json')) {
    $__mCfCheck = json_decode(file_get_contents($__mRoot . '/config.json'), true);
    if (is_array($__mCfCheck) && empty($__mCfCheck['site_url'])) {
        $__mNeedsSiteUrl = true;
    }
    unset($__mCfCheck);
}

if (!$__mHasLegacyConfig && !$__mHasLegacyRoot && !$__mHasLegacyUpload && !$__mNeedsSiteUrl) {
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

// ── 1b. Remove legacy admin upload files merged into extension-upload.php (v1.3.4.1) ───
// Read admin_dir from config.json — never assume the folder is named 'admin'.
$__mAdminDir = 'admin';
$__mConfigPath = $__mRoot . '/config.json';
if (file_exists($__mConfigPath)) {
    $__mConfigRaw = json_decode(file_get_contents($__mConfigPath), true);
    if (is_array($__mConfigRaw) && !empty($__mConfigRaw['admin_dir'])) {
        $__mAdminDir = $__mConfigRaw['admin_dir'];
    }
}
// Also try settings.json for sites not yet migrated to config.json (v1.3.3 pending).
if ($__mAdminDir === 'admin' && file_exists($__mRoot . '/settings.json')) {
    $__mSettingsRaw = json_decode(file_get_contents($__mRoot . '/settings.json'), true);
    if (is_array($__mSettingsRaw) && !empty($__mSettingsRaw['admin_dir'])) {
        $__mAdminDir = $__mSettingsRaw['admin_dir'];
    }
}

$__mLegacyAdminFiles = [
    $__mAdminDir . '/plugin-upload.php',
    $__mAdminDir . '/theme-upload.php',
];

foreach ($__mLegacyAdminFiles as $__mFile) {
    $__mPath = $__mRoot . '/' . $__mFile;
    if (file_exists($__mPath)) {
        @unlink($__mPath);
    }
}
unset($__mFile, $__mPath, $__mAdminDir, $__mConfigPath, $__mConfigRaw, $__mSettingsRaw);

// ── 2. Rename settings.json → config.json (v1.3.3 only) ─────────────────────
if ($__mHasLegacyConfig) {
    $__mOldConfig = $__mRoot . '/settings.json';
    $__mNewConfig = $__mRoot . '/config.json';

    if (!file_exists($__mNewConfig)) {
        rename($__mOldConfig, $__mNewConfig);
    } else {
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

// ── 4. Add site_url to config.json (v1.3.5) ──────────────────────────────────
if ($__mNeedsSiteUrl) {
    $__mConfigPath = $__mRoot . '/config.json';
    $__mConfigData = json_decode(file_get_contents($__mConfigPath), true) ?: [];
    $__mProtocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__mHost       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__mDocRoot    = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $__mCmsPath    = $__mDocRoot !== '' ? str_replace($__mDocRoot, '', rtrim($__mRoot, '/')) : '';
    $__mConfigData['site_url'] = rtrim($__mProtocol . '://' . $__mHost . $__mCmsPath, '/');
    $__mTmp = $__mConfigPath . '.tmp';
    if (file_put_contents($__mTmp, json_encode($__mConfigData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
        rename($__mTmp, $__mConfigPath);
    } else {
        error_log('[SynaptikCMS migrate.php] Failed to write site_url to config.json — check file permissions.');
    }
    unset($__mConfigPath, $__mConfigData, $__mProtocol, $__mHost, $__mDocRoot, $__mCmsPath, $__mTmp);
}

// ── 5. Self-destruct ─────────────────────────────────────────────────────────
@unlink(__FILE__);

unset($__mRoot, $__mHasLegacyConfig, $__mHasLegacyRoot, $__mHasLegacyUpload, $__mNeedsSiteUrl,
      $__mLegacyFiles, $__mLegacyAdminFiles,
      $__mOldConfig, $__mNewConfig, $__mOldRaw, $__mOldData,
      $__mNewRaw, $__mNewData, $__mMerged, $__mWriteOk,
      $__mOldRegistry, $__mNewRegistry);
