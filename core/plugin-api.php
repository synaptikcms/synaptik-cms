<?php
if (defined('PLUGIN_API_LOADED')) return;
define('PLUGIN_API_LOADED', true);

define('PL_CMS_ROOT', CMS_ROOT);
define('PL_ROOT', CMS_ROOT . '/plugins');
define('PL_REGISTRY_PATH', PL_ROOT . '/plugins.json');

if (!is_dir(PL_ROOT)) {
    @mkdir(PL_ROOT, 0755, true);
}

$GLOBALS['_pl_hooks']   = [];
$GLOBALS['_pl_filters'] = [];

function pl_add_hook(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['_pl_hooks'][$hook][$priority][] = $callback;
}

function pl_do_hook(string $hook, mixed $arg = null): void
{
    if (empty($GLOBALS['_pl_hooks'][$hook])) return;
    $buckets = $GLOBALS['_pl_hooks'][$hook];
    ksort($buckets);
    foreach ($buckets as $callbacks) {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $arg);
        }
    }
}

function pl_add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['_pl_filters'][$hook][$priority][] = $callback;
}

function pl_apply_filter(string $hook, mixed $value, mixed ...$args): mixed
{
    if (empty($GLOBALS['_pl_filters'][$hook])) return $value;
    $buckets = $GLOBALS['_pl_filters'][$hook];
    ksort($buckets);
    foreach ($buckets as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = call_user_func($callback, $value, ...$args);
        }
    }
    return $value;
}

function pl_load_registry(): array
{
    if (!file_exists(PL_REGISTRY_PATH)) {
        return [];
    }
    $decoded = json_decode(file_get_contents(PL_REGISTRY_PATH), true);
    return is_array($decoded) ? $decoded : [];
}

function pl_save_registry(array $registry): bool
{
    $json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    $tmp = PL_REGISTRY_PATH . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;

    return rename($tmp, PL_REGISTRY_PATH);
}

function pl_discover_plugins(): array
{
    $found = [];
    $entries = @scandir(PL_ROOT);
    if ($entries === false) return $found;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $dir = PL_ROOT . '/' . $entry;
        if (!is_dir($dir)) continue;

        $manifestPath = $dir . '/plugin.json';
        if (!file_exists($manifestPath)) continue;

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['synaptik_plugin'])) continue;

        $slug = $manifest['slug'] ?? $entry;
        $manifest['_folder'] = $entry;
        $found[$slug] = $manifest;
    }

    return $found;
}

function pl_list_plugins(): array
{
    $plugins  = pl_discover_plugins();
    $registry = pl_load_registry();

    foreach ($plugins as $slug => &$manifest) {
        $manifest['active'] = !empty($registry[$slug]['active']);
    }
    unset($manifest);

    return $plugins;
}

function pl_is_active(string $slug): bool
{
    $registry = pl_load_registry();
    return !empty($registry[$slug]['active']);
}

function pl_activate(string $slug): bool
{
    $plugins = pl_discover_plugins();
    if (!isset($plugins[$slug])) return false;

    $registry = pl_load_registry();
    $registry[$slug] = ['active' => true];
    if (!pl_save_registry($registry)) return false;

    pl_load_plugin($slug, $plugins[$slug]);
    pl_do_hook('plugin_activate_' . $slug);

    return true;
}

function pl_deactivate(string $slug): bool
{
    $registry = pl_load_registry();
    if (!isset($registry[$slug])) return true;
    $discovered = pl_discover_plugins();
    if (isset($discovered[$slug])) {
        pl_load_plugin($slug, $discovered[$slug]);
    }
    pl_do_hook('plugin_deactivate_' . $slug);

    $registry[$slug]['active'] = false;
    return pl_save_registry($registry);
}

function _pl_rrmdir(string $dir): bool
{
    if (!is_dir($dir)) return false;

    $items = @scandir($dir);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path) && !is_link($path)) {
            _pl_rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

function pl_delete_plugin(string $slug): bool
{
    if (pl_is_active($slug)) return false;

    $discovered = pl_discover_plugins();
    if (!isset($discovered[$slug])) return false;

    $folder = $discovered[$slug]['_folder'] ?? $slug;

    if ($folder === '' || $folder === '.' || $folder === '..' || strpos($folder, '/') !== false) {
        return false;
    }

    $pluginDir = PL_ROOT . '/' . $folder;
    $realPluginDir = realpath($pluginDir);
    $realPluginRoot = realpath(PL_ROOT);
    if ($realPluginDir === false || $realPluginRoot === false || strpos($realPluginDir, $realPluginRoot . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    $deleted = _pl_rrmdir($realPluginDir);

    $registry = pl_load_registry();
    if (isset($registry[$slug])) {
        unset($registry[$slug]);
        pl_save_registry($registry);
    }

    return $deleted;
}

// ─── Loading ──────────────────────────────────────────────────────────────────
function pl_load_plugin(string $slug, array $manifest): void
{
    $entry = $manifest['entry'] ?? null;
    if (empty($entry)) return;

    $entryPath = PL_ROOT . '/' . ($manifest['_folder'] ?? $slug) . '/' . $entry;
    if (file_exists($entryPath)) {
        require_once $entryPath;
    }
}

function pl_load_active_plugins(): void
{
    $registry = pl_load_registry();
    if (empty($registry)) return;

    $discovered = pl_discover_plugins();

    foreach ($registry as $slug => $state) {
        if (empty($state['active'])) continue;
        if (!isset($discovered[$slug])) continue; // plugin folder removed/renamed
        pl_load_plugin($slug, $discovered[$slug]);
    }
}

$GLOBALS['_pl_options_cache'] = [];

function _pl_options_path(string $slug): string
{
    return PL_ROOT . '/' . $slug . '/data/options.json';
}

function _pl_load_options(string $slug): array
{
    if (isset($GLOBALS['_pl_options_cache'][$slug])) {
        return $GLOBALS['_pl_options_cache'][$slug];
    }
    $path = _pl_options_path($slug);
    $data = [];
    if (file_exists($path)) {
        $decoded = json_decode(file_get_contents($path), true);
        if (is_array($decoded)) $data = $decoded;
    }
    $GLOBALS['_pl_options_cache'][$slug] = $data;
    return $data;
}

function _pl_save_options(string $slug, array $data): bool
{
    $path = _pl_options_path($slug);
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        $ht = dirname($dir) . '/data/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    $ok = rename($tmp, $path);
    if ($ok) $GLOBALS['_pl_options_cache'][$slug] = $data;
    return $ok;
}

function pl_get_option(string $slug, string $key, mixed $default = null): mixed
{
    $data = _pl_load_options($slug);
    return array_key_exists($key, $data) ? $data[$key] : $default;
}

function pl_set_option(string $slug, string $key, mixed $value): bool
{
    $data        = _pl_load_options($slug);
    $data[$key]  = $value;
    return _pl_save_options($slug, $data);
}

function pl_delete_option(string $slug, string $key): bool
{
    $data = _pl_load_options($slug);
    if (!array_key_exists($key, $data)) return true;
    unset($data[$key]);
    return _pl_save_options($slug, $data);
}

// ─── Admin menu registration ───────────────────────────────────────────────────
$GLOBALS['_pl_admin_menu_items'] = [];

function pl_register_admin_menu(string $slug, string $label, string $url, string $icon = ''): void
{
    $GLOBALS['_pl_admin_menu_items'][] = [
        'slug'  => $slug,
        'label' => $label,
        'url'   => $url,
        'icon'  => $icon,
    ];
}

function pl_on_admin_menu(callable $callback): void
{
    pl_add_hook('admin_menu', $callback);
}

function pl_on_admin_dashboard(callable $callback): void
{
    pl_add_hook('admin_dashboard', $callback);
}

function pl_get_admin_menu_items(): array
{
    static $loaded = false;
    static $fired  = false;

    if (!$loaded) {
        pl_load_active_plugins();
        $loaded = true;
    }

    if (!$fired) {
        pl_do_hook('admin_menu');
        $fired = true;
    }

    return $GLOBALS['_pl_admin_menu_items'];
}