<?php

function sl_content_type_reserved_slugs(): array
{
    return ['category', 'tag', 'home', 'search', 'admin'];
}

function sl_url_slug_map(?string $exceptType = null): array
{
    $map = [];
    foreach (sl_content_types() as $slug => $definition) {
        if ($slug === $exceptType) continue;
        $map[sl_type_url_slug($slug, false)] = $slug;
        $map[sl_type_url_slug($slug, true)]  = $slug;
    }
    return $map;
}

function sl_url_base_available(string $urlBase, ?string $exceptType = null): bool
{
    $localizedReserved = [
        sanitizeSlug(__t('url_slug_category', 'category')),
        sanitizeSlug(__t('url_slug_tag', 'tag')),
    ];
    if (in_array($urlBase, sl_content_type_reserved_slugs(), true) || in_array($urlBase, $localizedReserved, true)) {
        return false;
    }
    return !isset(sl_url_slug_map($exceptType)[$urlBase]);
}

function sl_content_type_clean_label($value): string
{
    $label = trim(mb_substr(strip_tags((string) $value), 0, 60));
    return sl_content_type_ucfirst($label);
}

function sl_content_type_normalize_input(array $input, string $slug, ?array $current = null)
{
    $labels = [];
    foreach (['label_singular', 'label_plural'] as $key) {
        $labels[$key] = array_key_exists($key, $input)
            ? sl_content_type_clean_label($input[$key])
            : (string) ($current[$key] ?? '');
    }

    $rawUrlBase = trim((string) ($input['url_base'] ?? ($current['url_base'] ?? '')));
    if ($rawUrlBase === '') {
        $rawUrlBase = $labels['label_singular'] !== '' ? $labels['label_singular'] : $slug;
    }
    $urlBase = sanitizeSlug($rawUrlBase);
    if ($urlBase === '') $urlBase = $slug;

    $rawPlural = trim((string) ($input['url_base_plural'] ?? ($current['url_base_plural'] ?? '')));
    if ($rawPlural === '') {
        $rawPlural = $labels['label_plural'] !== '' ? $labels['label_plural'] : $urlBase;
    }
    $urlBasePlural = sanitizeSlug($rawPlural);
    if ($urlBasePlural === '') $urlBasePlural = $urlBase;

    $exceptType = $current !== null ? $slug : null;
    $taken = __t('content_type_url_base_taken', 'This URL is reserved or already used by another content type.');
    if (!sl_url_base_available($urlBase, $exceptType)) return $taken;
    if ($urlBasePlural !== $urlBase && !sl_url_base_available($urlBasePlural, $exceptType)) return $taken;

    return array_merge($labels, ['url_base' => $urlBase, 'url_base_plural' => $urlBasePlural]);
}

function sl_content_type_validate_identifier(string $slug, array $types, ?string $exceptType = null): ?string
{
    if ($slug === '' || preg_match('/^[a-z0-9\-_]+$/', $slug) !== 1) {
        return __t('content_type_invalid_slug', 'Invalid content type identifier.');
    }
    if (in_array($slug, sl_content_type_reserved_slugs(), true)) {
        return __t('content_type_reserved_slug', 'This identifier is reserved and cannot be used.');
    }
    if (isset($types[$slug])) {
        return __t('content_type_already_exists', 'A content type with this identifier already exists.');
    }
    foreach (array_keys($types) as $existing) {
        if ($existing === $exceptType) continue;
        if ($slug === $existing . 's' || $existing === $slug . 's') {
            return __t('content_type_slug_confusable', 'This identifier is too close to an existing one (singular/plural of each other).');
        }
    }
    return null;
}

function sl_admin_register_content_type(string $slug, array $definition)
{
    $slug  = sanitizeSlug($slug);
    $types = sl_content_types();

    $identifierError = sl_content_type_validate_identifier($slug, $types);
    if ($identifierError !== null) return $identifierError;

    $normalized = sl_content_type_normalize_input($definition, $slug);
    if (is_string($normalized)) return $normalized;

    $types[$slug] = array_merge([
        'supports_taxonomies' => true,
        'supports_status'     => true,
    ], $definition, $normalized, ['built_in' => false]);

    if (!_sl_write_json(sl_content_types_path(), $types)) {
        return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
    }

    sl_invalidate_content_types_cache();
    return true;
}

function sl_admin_update_content_type(string $slug, array $changes)
{
    $types = sl_content_types();
    if (!isset($types[$slug])) {
        return __t('content_type_unknown', 'Unknown content type.');
    }

    $normalized = sl_content_type_normalize_input($changes, $slug, $types[$slug]);
    if (is_string($normalized)) return $normalized;

    $types[$slug] = array_merge($types[$slug], $normalized, ['built_in' => $types[$slug]['built_in']]);

    if (!_sl_write_json(sl_content_types_path(), $types)) {
        return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
    }

    sl_invalidate_content_types_cache();
    return true;
}

function sl_admin_unregister_content_type(string $slug)
{
    $types = sl_content_types();
    if (!isset($types[$slug])) {
        return __t('content_type_unknown', 'Unknown content type.');
    }
    if (!empty($types[$slug]['built_in'])) {
        return __t('content_type_builtin_undeletable', 'Built-in content types cannot be deleted.');
    }

    $existing = sl_load_index_unfiltered($slug);
    if (!empty($existing)) {
        return __t('content_type_not_empty', 'This content type still has content — remove it first.');
    }

    unset($types[$slug]);
    if (!_sl_write_json(sl_content_types_path(), $types)) {
        return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
    }

    sl_invalidate_content_types_cache();
    return true;
}

function _sl_rekey_array(array $source, string $oldKey, string $newKey): array
{
    $result = [];
    foreach ($source as $key => $value) {
        $result[$key === $oldKey ? $newKey : $key] = $value;
    }
    return $result;
}

function _sl_rename_type_in_config(string $oldSlug, string $newSlug): ?array
{
    $file = CMS_ROOT . '/config.json';
    $config = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($config)) return null;

    foreach (['type_labels', 'custom_fields_schema'] as $key) {
        if (isset($config[$key][$oldSlug])) {
            $config[$key] = _sl_rekey_array($config[$key], $oldSlug, $newSlug);
        }
    }

    foreach ($config['main_menu'] ?? [] as $i => $item) {
        if (($item['content_type'] ?? '') === $oldSlug) {
            $config['main_menu'][$i]['content_type'] = $newSlug;
            if (isset($item['url']) && strpos($item['url'], $oldSlug . '/') === 0) {
                $config['main_menu'][$i]['url'] = $newSlug . substr($item['url'], strlen($oldSlug));
            }
        } elseif (($item['content_type'] ?? '') === 'list' && ($item['content_slug'] ?? '') === $oldSlug) {
            $config['main_menu'][$i]['content_slug'] = $newSlug;
        }
    }

    return $config;
}

function _sl_rename_type_references(string $oldSlug, string $newSlug): void
{
    foreach (sl_all_type_slugs() as $type) {
        foreach (glob(sl_data_dir() . '/' . sl_type_dir($type) . '/*.json') ?: [] as $file) {
            if (basename($file) === '_index.json') continue;
            $item = json_decode((string) file_get_contents($file), true);
            if (!is_array($item) || empty($item['related_items']) || !is_array($item['related_items'])) continue;
            $changed = false;
            foreach ($item['related_items'] as $i => $ref) {
                if (($ref['type'] ?? '') === $oldSlug) {
                    $item['related_items'][$i]['type'] = $newSlug;
                    $changed = true;
                }
            }
            if ($changed) _sl_write_json($file, $item);
        }
    }

    foreach (glob(sl_admin_drafts_dir() . '/*.json') ?: [] as $file) {
        $draft = json_decode((string) file_get_contents($file), true);
        if (is_array($draft) && ($draft['type'] ?? '') === $oldSlug) {
            $draft['type'] = $newSlug;
            _sl_write_json($file, $draft);
        }
    }

    $commentsDir = CMS_ROOT . '/plugins/comments/data';
    $longer = array_filter(sl_all_type_slugs(), fn($t) => $t !== $newSlug && strlen($t) > strlen($oldSlug) && strpos($t, $oldSlug . '-') === 0);
    foreach (glob($commentsDir . '/' . $oldSlug . '-*.json') ?: [] as $file) {
        $base = basename($file);
        foreach ($longer as $other) {
            if (strpos($base, $other . '-') === 0) continue 2;
        }
        @rename($file, $commentsDir . '/' . $newSlug . substr($base, strlen($oldSlug)));
    }
}

function sl_admin_rename_content_type(string $oldSlug, string $newSlug)
{
    $types = sl_content_types();
    if (!isset($types[$oldSlug])) {
        return __t('content_type_unknown', 'Unknown content type.');
    }
    if (!empty($types[$oldSlug]['built_in'])) {
        return __t('content_type_builtin_unrenamable', 'Built-in content types cannot be renamed.');
    }

    $newSlug = sanitizeSlug($newSlug);
    if ($newSlug === $oldSlug) return true;

    $identifierError = sl_content_type_validate_identifier($newSlug, $types, $oldSlug);
    if ($identifierError !== null) return $identifierError;

    $oldDir = sl_data_dir() . '/' . sl_type_dir($oldSlug);
    $newDir = sl_data_dir() . '/' . sl_type_dir($newSlug);
    if (file_exists($newDir)) {
        return __t('content_type_rename_dir_exists', 'A data folder with this identifier already exists.');
    }

    $movedDir = false;
    if (is_dir($oldDir)) {
        if (!rename($oldDir, $newDir)) {
            return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
        }
        $movedDir = true;
    }

    $rollback = function () use ($movedDir, $oldDir, $newDir, $types): void {
        _sl_write_json(sl_content_types_path(), $types);
        if ($movedDir) rename($newDir, $oldDir);
        sl_invalidate_content_types_cache();
    };

    if (!_sl_write_json(sl_content_types_path(), _sl_rekey_array($types, $oldSlug, $newSlug))) {
        $rollback();
        return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
    }

    $config = _sl_rename_type_in_config($oldSlug, $newSlug);
    if ($config !== null && !sl_admin_save_config($config)) {
        $rollback();
        return __t('content_type_write_failed', 'Could not write the content type registry to disk.');
    }

    sl_invalidate_content_types_cache();
    _sl_rename_type_references($oldSlug, $newSlug);

    foreach ([$oldSlug, $newSlug] as $type) {
        sl_invalidate_taxonomy_cache($type);
        _sl_cache_del('idx_' . $type);
        _sl_persistent_del('sl_idx_' . $type);
    }
    if (function_exists('sl_bump_content_signature')) sl_bump_content_signature();

    return true;
}
