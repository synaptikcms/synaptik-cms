<?php

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

function _sl_cache_dir(): string
{
    return CMS_ROOT . '/cache';
}

function _sl_cache_file(string $key): string
{
    return _sl_cache_dir() . '/' . $key . '.cache.php';
}

function _sl_persistent_get(string $key)
{
    if (function_exists('apcu_fetch')) {
        $success = false;
        $value   = apcu_fetch($key, $success);
        if ($success) return $value;
    }

    $file = _sl_cache_file($key);
    if (!file_exists($file)) return null;
    if ((time() - filemtime($file)) >= SL_CACHE_TTL) return null;

    $data = @include $file;
    return is_array($data) ? $data : null;
}

function _sl_persistent_set(string $key, $value): void
{
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, SL_CACHE_TTL);
    }

    $dir = _sl_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (is_writable($dir)) {
        $file = _sl_cache_file($key);
        $tmp  = $file . '.' . getmypid() . '.tmp';
        $php  = "<?php\nreturn " . var_export($value, true) . ";\n";
        if (file_put_contents($tmp, $php, LOCK_EX) !== false) {
            rename($tmp, $file);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        }
    }
}

function _sl_persistent_del(string $key): void
{
    // APCu
    if (function_exists('apcu_delete')) {
        apcu_delete($key);
    }
    $file = _sl_cache_file($key);
    if (file_exists($file)) {
        @unlink($file);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}

function sl_clear_all_cache(): void
{
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

    $dir = _sl_cache_dir();
    $canInvalidate = function_exists('opcache_invalidate');
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.cache.php') ?: [] as $f) {
            @unlink($f);
            if ($canInvalidate) {
                opcache_invalidate($f, true);
            }
        }
        foreach (glob($dir . '/*.cache.php.*.tmp') ?: [] as $f) {
            @unlink($f);
        }
    }

    $pagesDir = _sl_page_cache_dir();
    if (is_dir($pagesDir)) {
        foreach (glob($pagesDir . '/*.page.php') ?: [] as $f) {
            @unlink($f);
            if ($canInvalidate) {
                opcache_invalidate($f, true);
            }
        }
        foreach (glob($pagesDir . '/*.page.php.*.tmp') ?: [] as $f) {
            @unlink($f);
        }
    }

    $GLOBALS['_sl_cache'] = [];
}
