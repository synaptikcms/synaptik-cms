<?php
if (!defined('CMS_ROOT')) define('CMS_ROOT', dirname(__DIR__));

if (defined('SL_DATA_LAYER_LOADED')) return;
define('SL_DATA_LAYER_LOADED', true);
define('SL_CACHE_TTL', 60);

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

function _sl_page_cache_dir(): string
{
    $dir = _sl_cache_dir() . '/pages';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function _sl_page_cache_file(string $urlPath, string $lang): string
{
    return _sl_page_cache_dir() . '/' . sha1($lang . '|' . $urlPath) . '.page.php';
}

function _sl_content_signature_path(): string
{
    return _sl_cache_dir() . '/.signature';
}

function sl_content_signature(): string
{
    $stored = @file_get_contents(_sl_content_signature_path());
    if ($stored !== false && $stored !== '') return trim($stored);
    return sl_bump_content_signature();
}

function sl_bump_content_signature(): string
{
    $dir = _sl_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $newSig = bin2hex(random_bytes(16));
    $path   = _sl_content_signature_path();
    $tmp    = $path . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $newSig, LOCK_EX) !== false) {
        @rename($tmp, $path);
    }
    return $newSig;
}

function sl_page_signature(): string
{
    static $sig = null;
    if ($sig !== null) return $sig;

    $parts = [];

    $configPath = CMS_ROOT . '/config.json';
    if (file_exists($configPath)) {
        $parts[] = 'config.json:' . filemtime($configPath) . ':' . filesize($configPath);
    }

    $parts[] = 'content:' . sl_content_signature();

    $activeTheme = loadConfig()['active_theme'] ?? 'default';
    foreach ([
        CMS_ROOT . '/theme/' . $activeTheme,
        CMS_ROOT . '/theme/child_theme/' . $activeTheme,
    ] as $themeDir) {
        if (!is_dir($themeDir)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $parts[] = $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
        }
    }

    sort($parts);
    $sig = hash('xxh3', implode('|', $parts));
    return $sig;
}

function sl_page_cache_get(string $urlPath, string $lang, ?int &$status = null): ?string
{
    $file = _sl_page_cache_file($urlPath, $lang);
    if (!file_exists($file)) return null;

    $cached = @include $file;
    if (!is_array($cached) || !isset($cached['sig'], $cached['html'], $cached['status'])) return null;
    if ($cached['sig'] !== sl_page_signature()) return null;

    $status = $cached['status'];
    return $cached['html'];
}

function sl_page_cache_set(string $urlPath, string $lang, int $status, string $html): void
{
    $dir = _sl_page_cache_dir();
    if (!is_writable($dir)) return;

    $file = _sl_page_cache_file($urlPath, $lang);
    $tmp  = $file . '.' . getmypid() . '.tmp';
    $payload = [
        'sig'    => sl_page_signature(),
        'status' => $status,
        'html'   => $html,
    ];
    if (file_put_contents($tmp, "<?php\nreturn " . var_export($payload, true) . ";\n", LOCK_EX) !== false) {
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}

function sl_data_dir(): string
{
    return CMS_ROOT . '/data';
}

function sl_type_dir(string $type): string
{
    return $type . 's';
}

function sl_index_path(string $type): string
{
    return sl_data_dir() . '/' . sl_type_dir($type) . '/_index.json';
}

function sl_item_path(string $type, string $fileSlug): string
{
    $fileSlug = basename($fileSlug);
    return sl_data_dir() . '/' . sl_type_dir($type) . '/' . $fileSlug . '.json';
}

function sl_load_index(string $type): array
{
    $globalsKey    = 'idx_' . $type;
    $persistentKey = 'sl_idx_' . $type;
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
    $path = sl_index_path($type);
    if (!file_exists($path)) {
        _sl_cache_set($globalsKey, []);
        return [];
    }

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    $result  = is_array($decoded) ? $decoded : [];

    if (!$isAdmin) {
        $now    = time();
        $result = array_values(array_filter($result, function (array $item) use ($now): bool {
            $status = $item['status'] ?? 'published';
            if ($status === 'draft' || $status === 'unpublished') return false;
            if ($status === 'scheduled') {
                $at = isset($item['publish_at']) ? strtotime($item['publish_at']) : false;
                return $at !== false && $at <= $now;
            }
            return true;
        }));
    }

    _sl_cache_set($globalsKey, $result);

    if (!$isAdmin) {
        _sl_persistent_set($persistentKey, $result);
    }

    return $result;
}

function sl_invalidate_index_cache(?string $type = null): void
{
    $types = ($type !== null) ? [$type] : ['article', 'page', 'project'];

    foreach ($types as $t) {
        _sl_cache_del('idx_' . $t);
        _sl_persistent_del('sl_idx_' . $t);
    }
}

function sl_file_slug(array $entry): string
{
    if (!empty($entry['_file'])) return $entry['_file'];
    if (!empty($entry['custom_slug'])) return $entry['custom_slug'];
    return $entry['slug'] ?? '';
}

function sl_effective_slug(array $item): string
{
    return !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
}

function sl_load_item(string $type, string $fileSlug): ?array
{
    if ($fileSlug === '') return null;

    $path = sl_item_path($type, $fileSlug);
    if (!file_exists($path)) return null;

    $raw  = file_get_contents($path);
    if ($raw === false || $raw === '') return null;

    $item = json_decode($raw, true);
    return is_array($item) ? $item : null;
}

function sl_find_in_index(string $type, string $effectiveSlug): ?array
{
    $index = sl_load_index($type);
    foreach ($index as $pos => $entry) {
        if (sl_effective_slug($entry) === $effectiveSlug) {
            return [$entry, $pos];
        }
    }
    return null;
}

function sl_load_item_by_slug(string $type, string $effectiveSlug): ?array
{
    $found = sl_find_in_index($type, $effectiveSlug);
    if ($found !== null) {
        [$entry] = $found;
        return sl_load_item($type, sl_file_slug($entry));
    }
    return null;
}

function sl_load_index_unfiltered(string $type): array
{
    $path = sl_index_path($type);
    if (!file_exists($path)) return [];

    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function sl_load_item_by_slug_unfiltered(string $type, string $effectiveSlug): ?array
{
    foreach (sl_load_index_unfiltered($type) as $entry) {
        if (is_array($entry) && sl_effective_slug($entry) === $effectiveSlug) {
            return sl_load_item($type, sl_file_slug($entry));
        }
    }
    return null;
}

function sl_load_all_items(string $type): array
{
    $index = sl_load_index($type);
    $items = [];
    foreach ($index as $entry) {
        $item = sl_load_item($type, sl_file_slug($entry));
        if ($item !== null) {
            $items[] = $item;
        }
    }
    return $items;
}

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

function sl_invalidate_taxonomy_cache(string $type): void
{
    _sl_cache_del($type);
    _sl_persistent_del('sl_' . $type);
}

function sl_promote_scheduled(string $type): void
{
    $index   = sl_load_index($type);
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
    array $types     = ['article', 'page', 'project'],
    bool  $fullItems = false
): array {
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
        return '<link rel="canonical" href="' . htmlspecialchars($pageData['canonical_url']) . '">';
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $uri      = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return '<link rel="canonical" href="' . htmlspecialchars($protocol . '://' . $_SERVER['HTTP_HOST'] . $uri) . '">';
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
        'footer_text'                => 'Powered by <a href="https://synaptikcms.com">SynaptikCMS</a> • &copy; {year}',
        'footer_show_login'          => false,
        'footer_show_social'         => false,
        'footer_social_links'        => [],
        'autosave_enabled'           => true,
        'autosave_interval'          => 10,
        'type_labels'                => [],
        'schema_author_name'         => '',
        'schema_publisher_type'      => 'Person',
    ];
}
