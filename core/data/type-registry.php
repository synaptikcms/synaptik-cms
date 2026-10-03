<?php

function sl_content_types_path(): string
{
    return sl_data_dir() . '/content-types.json';
}

function sl_default_content_types(): array
{
    return [
        'article' => ['url_base' => 'article', 'supports_taxonomies' => true, 'supports_status' => true, 'built_in' => true],
        'page'    => ['url_base' => 'page',    'supports_taxonomies' => true, 'supports_status' => true, 'built_in' => true],
        'project' => ['url_base' => 'project', 'supports_taxonomies' => true, 'supports_status' => true, 'built_in' => true],
    ];
}

function sl_content_types(): array
{
    $globalsKey = 'content_types';

    $cached = _sl_cache_get($globalsKey);
    if ($cached !== null) return $cached;

    $path    = sl_content_types_path();
    $raw     = file_exists($path) ? file_get_contents($path) : false;
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = (is_array($decoded) && !empty($decoded)) ? $decoded : sl_default_content_types();

    _sl_cache_set($globalsKey, $result);
    return $result;
}

function sl_all_type_slugs(): array
{
    return array_keys(sl_content_types());
}

function sl_content_type(string $type): ?array
{
    return sl_content_types()[$type] ?? null;
}

function sl_content_type_exists(string $type): bool
{
    return isset(sl_content_types()[$type]);
}

function sl_type_url_slug(string $type, bool $plural = false): string
{
    $settings = function_exists('loadConfig') ? loadConfig() : admin_load_config();
    $override = $settings['type_labels'][$type][$plural ? 'plural' : 'singular'] ?? '';
    if ($override !== '') return sanitizeSlug($override);

    $definition = sl_content_type($type);
    if ($definition !== null && empty($definition['built_in']) && !empty($definition['url_base'])) {
        if (!$plural) return sanitizeSlug($definition['url_base']);
        $pluralBase = !empty($definition['url_base_plural']) ? $definition['url_base_plural'] : $definition['url_base'] . 's';
        return sanitizeSlug($pluralBase);
    }

    $key = $type . ($plural ? 's' : '');
    return sanitizeSlug(__t('url_slug_' . $key, $key));
}

function sl_type_registry_label(string $type, bool $plural): string
{
    $definition = sl_content_type($type);
    if ($definition === null || !empty($definition['built_in'])) return '';
    $label = $definition[$plural ? 'label_plural' : 'label_singular'] ?? '';
    if ($label === '') $label = $definition[$plural ? 'label_singular' : 'label_plural'] ?? '';
    if ($label === '') $label = str_replace(['-', '_'], ' ', $type);
    return sl_content_type_ucfirst($label);
}

function sl_content_type_ucfirst(string $text): string
{
    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
}

function sl_single_template_name(string $type): string
{
    if (in_array($type, ['article', 'page', 'project'], true)) {
        return sl_resolve_template_name("content-{$type}s", 'content-generic');
    }
    if (sl_resolve_template_name("content-{$type}", '') !== '') {
        return "content-{$type}";
    }
    $theme = loadConfig()['active_theme'] ?? 'default';
    if ($theme === 'default') return 'content-generic';
    foreach (["theme/child_theme/{$theme}", "theme/{$theme}"] as $dir) {
        if (is_file(CMS_ROOT . "/{$dir}/content-articles.php")) return 'content-articles';
    }
    return 'content-generic';
}

function sl_invalidate_content_types_cache(): void
{
    _sl_cache_del('content_types');
}

function sl_invalidate_taxonomy_cache(string $type): void
{
    _sl_cache_del($type);
    _sl_persistent_del('sl_' . $type);
}

