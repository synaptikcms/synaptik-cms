<?php

function sl_promote_scheduled(string $type): void
{
    $index   = sl_load_index_unfiltered($type);
    $now     = time();
    $changed = false;

    foreach ($index as $pos => $entry) {
        if (($entry['status'] ?? '') !== 'scheduled') continue;

        $publishAt = isset($entry['publish_at']) ? strtotime($entry['publish_at']) : false;
        if ($publishAt === false || $publishAt > $now) continue;

        $index[$pos]['status'] = 'published';
        $changed = true;

        $itemPath = sl_item_path($type, sl_file_slug($entry));
        if (file_exists($itemPath)) {
            $raw  = file_get_contents($itemPath);
            $item = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($item)) {
                $item['status'] = 'published';
                file_put_contents(
                    $itemPath,
                    json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }

    if ($changed) {
        file_put_contents(
            sl_index_path($type),
            json_encode(array_values($index), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        sl_invalidate_index_cache($type);
        sl_bump_content_signature();
    }
}

function sl_build_data_array(
    ?array $types    = null,
    bool  $fullItems = false
): array {
    $types   = $types ?? sl_all_type_slugs();
    $isAdmin = defined('LANG_CONTEXT') && LANG_CONTEXT === 'admin';
    $now     = time();

    $data = [
        'categories' => sl_load_categories(),
        'tags'       => sl_load_tags(),
    ];

    foreach ($types as $type) {
        if (!$isAdmin) {
            sl_promote_scheduled($type);
        }

        $items = $fullItems ? sl_load_all_items($type) : sl_load_index($type);

        if (!$isAdmin) {
            $items = array_values(array_filter($items, function (array $item) use ($now): bool {
                if (($item['status'] ?? 'published') !== 'scheduled') return true;
                $at = isset($item['publish_at']) ? strtotime($item['publish_at']) : false;
                return $at !== false && $at <= $now;
            }));
        }

        $data[$type] = $items;
    }
    return pl_apply_filter('content_data_array', $data);
}

if (!function_exists('format_date')) {
function format_date(string $date): string
{
    if (empty($date)) return '';
    $ts = strtotime($date);
    if ($ts === false) return $date;

    $settings = function_exists('loadConfig') ? loadConfig() : [];
    return date($settings['date_format'] ?? 'Y-m-d', $ts);
}
}

if (!function_exists('output_canonical_url')) {
function output_canonical_url(?array $pageData = null): string
{
    if (!empty($pageData['canonical_url'])) {
        return '<link rel="canonical" href="' . hsc($pageData['canonical_url']) . '">';
    }
    $protocol = _sl_request_is_https() ? 'https' : 'http';
    $uri      = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return '<link rel="canonical" href="' . hsc($protocol . '://' . _sl_request_host() . $uri) . '">';
}
}

function loadDefaultConfig(): array
{
    return [
        'articles_per_page'          => 6,
        'projects_per_page'          => 3,
        'show_articles_on_homepage'  => true,
        'show_projects_on_homepage'  => true,
        'show_breadcrumbs'           => false,
        'main_menu'                  => [],
        'use_custom_menu'            => false,
        'show_search_icon'           => false,
        'default_menu_style'         => 'grouped',
        'default_menu_order'         => 'date_desc',
        'site_title'                 => 'Synaptik CMS',
        'site_description'           => 'A powerful, lightweight, blazing fast and very flexible file-based CMS, to create portfolio, personal or business websites in one click.',
        'default_meta_title'         => '{page_title} | {site_title}',
        'default_meta_description'   => '{site_description}',
        'enable_seo'                 => true,
        'show_site_title_in_header'  => true,
        'date_format'                => 'Y-m-d',
        'homepage_type'              => 'default',
        'homepage_page_id'           => '',
        'active_theme'               => 'default',
        'available_themes'           => ['default'],
        'active_language'            => 'en',
        'image_optimization_enabled' => true,
        'max_width'                  => 1920,
        'max_height'                 => 1080,
        'image_quality'              => 85,
        'create_thumbnails'          => true,
        'thumb_width'                => 350,
        'thumb_height'               => 350,
        'convert_to_webp'            => true,
        'footer_text'                => 'Powered by <a href="https://synaptikcms.com">Synaptik CMS</a> • &copy; {year}',
        'footer_show_login'          => false,
        'footer_show_social'         => false,
        'footer_social_links'        => [],
        'autosave_enabled'           => true,
        'autosave_interval'          => 10,
        'default_editor'             => 'html',
        'type_labels'                => [],
        'schema_author_name'         => '',
        'schema_publisher_type'      => 'Person',
        'canonical_host'             => '',
        'force_https'                => false,
    ];
}
