<?php
/**
 * Plugin API — SynaptikCMS
 *
 * Minimal plugin registry with its own tiny hook system, deliberately kept
 * independent from theme-api.php. Reusing theme-api.php's hooks would mean
 * pulling in tf-shortcodes.php, tf-cards.php, tf-navigation.php, tf-page.php,
 * and tf-markdown.php — front-end rendering libraries with no business being
 * loaded from admin-only contexts (e.g. admin/plugins.php, which only loads
 * admin-functions.php, not the front-end functions.php chain). A few dozen
 * lines of duplicated hook logic here is a smaller footprint and a safer
 * boundary than dragging the whole front-end template stack into the admin.
 *
 * A plugin is a self-contained folder under /plugins/ (e.g. /plugins/booking/)
 * with a plugin.json manifest and an entry file. Active plugins are tracked
 * in plugins.json (created on first activation) and loaded on every request
 * by pl_load_active_plugins(), called from functions.php.
 *
 * Plugin hook points a plugin can use:
 *   - 'admin_menu'                (pl_do_hook, no args) — register admin
 *                                   sidebar entries via pl_register_admin_menu()
 *   - 'plugin_activate_{slug}'    (pl_do_hook) — fired once on activation
 *   - 'plugin_deactivate_{slug}'  (pl_do_hook) — fired once on deactivation
 *
 * Front-end plugin hooks (before_content, footer_scripts, etc.) still go
 * through theme-api.php's add_theme_action() as before — that system is
 * unaffected by this file and remains the right tool for anything rendered
 * as part of a front-end page.
 */

if (defined('PLUGIN_API_LOADED')) return;
define('PLUGIN_API_LOADED', true);

define('PL_CMS_ROOT', __DIR__);
define('PL_ROOT', __DIR__ . '/plugins');
define('PL_REGISTRY_PATH', __DIR__ . '/plugins.json');

if (!is_dir(PL_ROOT)) {
    @mkdir(PL_ROOT, 0755, true);
}

// ─── Minimal hook system (admin-context only — see file docblock) ─────────────

$GLOBALS['_pl_hooks'] = [];

function pl_add_hook(string $hook, callable $callback): void
{
    $GLOBALS['_pl_hooks'][$hook][] = $callback;
}

function pl_do_hook(string $hook): void
{
    if (empty($GLOBALS['_pl_hooks'][$hook])) return;
    foreach ($GLOBALS['_pl_hooks'][$hook] as $callback) {
        call_user_func($callback);
    }
}

// ─── Registry ───────────────────────────────────────────────────────────────

/**
 * Loads the plugin activation registry: { "slug": { "active": bool } }.
 * Missing file → empty registry (no plugins active by default; a plugin
 * must be explicitly activated from Admin → Extensions).
 */
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

/**
 * Scans /plugins/ for valid plugins: any top-level folder containing a
 * plugin.json with "synaptik_plugin": true. Returns manifests keyed by slug.
 */
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

/**
 * Returns all discovered plugins merged with their current activation
 * state, for display on the Admin → Extensions page.
 */
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

/**
 * Activates a plugin: marks it active in the registry and fires
 * 'plugin_activate_{slug}' so the plugin can run first-run setup (e.g.
 * creating its data directory) immediately, without waiting for its next
 * natural page load.
 */
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

/**
 * Deactivates a plugin: marks it inactive. Does not delete its data —
 * deactivation is reversible. A plugin wanting to clean up on deactivation
 * can hook 'plugin_deactivate_{slug}'.
 */
function pl_deactivate(string $slug): bool
{
    $registry = pl_load_registry();
    if (!isset($registry[$slug])) return true;

    // The plugin must be loaded for its deactivation callback (if any) to
    // exist — activation state alone doesn't guarantee this admin request
    // already loaded the plugin's entry file.
    $discovered = pl_discover_plugins();
    if (isset($discovered[$slug])) {
        pl_load_plugin($slug, $discovered[$slug]);
    }
    pl_do_hook('plugin_deactivate_' . $slug);

    $registry[$slug]['active'] = false;
    return pl_save_registry($registry);
}

/**
 * Recursively deletes a directory and all its contents. Used only by
 * pl_delete_plugin() below — kept local to this file rather than added as
 * a general-purpose core helper, since it is only ever called on a path
 * already validated to be a plugin folder under PL_ROOT.
 */
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

/**
 * Permanently deletes a plugin's folder from /plugins/. Refuses to delete
 * an active plugin — the caller must deactivate it first, so any
 * 'plugin_deactivate_{slug}' cleanup hook has already run and the plugin
 * isn't removed out from under a request that's still using it.
 *
 * Also removes the plugin's entry from the activation registry, if present.
 *
 * @return bool True on success. False if the plugin is active, not found,
 *              or the folder could not be fully removed.
 */
function pl_delete_plugin(string $slug): bool
{
    if (pl_is_active($slug)) return false;

    $discovered = pl_discover_plugins();
    if (!isset($discovered[$slug])) return false;

    $folder = $discovered[$slug]['_folder'] ?? $slug;
    // Defense in depth: folder name must be a plain path segment, never
    // escaping PL_ROOT via '..' or an absolute path — even though
    // pl_discover_plugins() only ever returns real subdirectory names.
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

/**
 * Includes a single plugin's entry file, guarding against a missing or
 * misconfigured manifest.
 */
function pl_load_plugin(string $slug, array $manifest): void
{
    $entry = $manifest['entry'] ?? null;
    if (empty($entry)) return;

    $entryPath = PL_ROOT . '/' . ($manifest['_folder'] ?? $slug) . '/' . $entry;
    if (file_exists($entryPath)) {
        require_once $entryPath;
    }
}

/**
 * Loads every plugin marked active in the registry. Called once per request
 * from functions.php (front-end) and from admin/plugins.php (admin-only
 * context, so the plugin's admin_menu hook registration runs there too).
 */
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

// ─── Admin menu registration ───────────────────────────────────────────────────

$GLOBALS['_pl_admin_menu_items'] = [];

/**
 * Registers an admin sidebar entry for a plugin. Call this during the
 * 'admin_menu' hook, fired once from admin/includes/sidebar.php.
 *
 * @param string $slug  Plugin slug (used for the active-state CSS class)
 * @param string $label Menu label (already translated by the caller)
 * @param string $url   Absolute or relative URL for the link
 * @param string $icon  Inline SVG path data (Lucide-style, stroke currentColor)
 */
function pl_register_admin_menu(string $slug, string $label, string $url, string $icon = ''): void
{
    $GLOBALS['_pl_admin_menu_items'][] = [
        'slug'  => $slug,
        'label' => $label,
        'url'   => $url,
        'icon'  => $icon,
    ];
}

/**
 * Registers a callback for the 'admin_menu' hook. Thin wrapper around
 * pl_add_hook() so plugin code doesn't need to know about the underlying
 * hook name — mirrors the add_theme_action() naming plugins already use
 * for front-end hooks, for consistency.
 */
function pl_on_admin_menu(callable $callback): void
{
    pl_add_hook('admin_menu', $callback);
}

/**
 * Returns all admin menu entries registered so far. Fires 'admin_menu'
 * first so active plugins have a chance to register before the list is read.
 * Also ensures active plugins are loaded first — admin-only requests (e.g.
 * admin/plugins.php) don't go through functions.php's pl_load_active_plugins()
 * call, so a plugin's admin_menu registration would otherwise never run.
 */
function pl_get_admin_menu_items(): array
{
    static $loaded = false;
    if (!$loaded) {
        pl_load_active_plugins();
        $loaded = true;
    }

    pl_do_hook('admin_menu');
    return $GLOBALS['_pl_admin_menu_items'];
}
