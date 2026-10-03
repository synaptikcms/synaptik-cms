<?php

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

