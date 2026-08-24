<?php
/**
 * Navigation & Menu Rendering — SynaptikCMS
 * All functions for building and rendering the front-end navigation.
 */


/**
 * Renders a hierarchical menu with dropdown sub-menu support.
 * Falls back to renderDefaultMenu() when no custom menu is configured.
 */
function renderHierarchicalMenu($settings, $data)
{
    if (!$settings['use_custom_menu'] || empty($settings['main_menu'])) {
        return renderDefaultMenu($data);
    }
    // Plugin filter: lets a plugin add, remove or reorder menu entries
    // before they turn into HTML (e.g. inject a "Book now" link).
    $tree = pl_apply_filter('menu_tree', buildMenuTree($settings['main_menu']));
    return renderMenuTree($tree);
}

/**
 * Converts a flat array of menu items into a nested tree structure.
 *
 * @param array      $menuItems Flat list of menu items.
 * @param mixed      $parentId  Parent ID to start from (null for root).
 * @return array     Nested tree.
 */
function buildMenuTree($menuItems, $parentId = null)
{
    $tree = [];
    foreach ($menuItems as $item) {
        $itemParent = $item['parent_id'] ?? null;
        if (($parentId === null && empty($itemParent)) ||
            (!empty($itemParent) && $itemParent === $parentId)) {
            $item['children'] = buildMenuTree($menuItems, $item['id']);
            $tree[] = $item;
        }
    }
    return $tree;
}

/**
 * Recursively renders a menu tree as nested <ul>/<li> elements.
 *
 * @param array  $menuTree  Output of buildMenuTree().
 * @return string HTML.
 */
function renderMenuTree($menuTree)
{
    $html = '<ul>';
    foreach ($menuTree as $item) {
        $url    = generateMenuItemUrl($item);
        $target = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target']) . '"' : '';
        $html  .= '<li' . (!empty($item['children']) ? ' class="has-submenu"' : '') . '>';
        $html  .= '<a href="' . htmlspecialchars($url) . '"' . $target . '>' . htmlspecialchars($item['label']) . '</a>';
        if (!empty($item['children'])) {
            $html .= renderMenuTree($item['children']);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Resolves the final URL for a single menu item.
 *
 * @param array $item  Menu item array.
 * @return string URL.
 */
function generateMenuItemUrl($item)
{
    $baseUrl = getBaseUrl();

    if ($item['type'] === 'custom') {
        return strpos($item['url'], 'http') === 0
            ? $item['url']
            : $baseUrl . ltrim($item['url'], '/');
    }

    if ($item['type'] === 'content') {
        if (isset($item['content_type']) && $item['content_type'] === 'list') {
            return $baseUrl . ltrim($item['url'], '/');
        }
        if (isset($item['content_type']) && isset($item['content_slug'])) {
            global $data;
            $category = null;
            if (isset($data[$item['content_type']])) {
                foreach ($data[$item['content_type']] as $contentItem) {
                    $slug = !empty($contentItem['custom_slug']) ? $contentItem['custom_slug'] : $contentItem['slug'];
                    if ($slug === $item['content_slug'] && !empty($contentItem['category'])) {
                        $category = sanitizeSlug($contentItem['category']);
                        break;
                    }
                }
            }
            return cleanUrl($item['content_type'], $item['content_slug'], null, $category);
        }
        return $baseUrl . ltrim($item['url'], '/');
    }

    return $baseUrl;
}

/**
 * Generates a flat or grouped automatic menu from content marked show_in_menu.
 * Always loads fresh indices to avoid the single-item page replacement problem.
 *
 * @param array $data  Passed for signature compatibility; indices are re-loaded internally.
 * @return string HTML <ul>.
 */
function renderDefaultMenu($data)
{
    $settings = loadConfig();
    $data = [
        'page'    => sl_load_index('page'),
        'article' => sl_load_index('article'),
        'project' => sl_load_index('project'),
    ];
    $menuStyle = $settings['default_menu_style'] ?? 'flat';
    $orderBy   = $settings['default_menu_order']  ?? 'alphabetical';

    $html  = '<ul>';
    $html .= '<li><a href="' . cleanUrl('home') . '">' . __t('home') . '</a></li>';

    foreach (['page', 'article', 'project'] as $type) {
        if (empty($data[$type])) continue;

        $flagged = array_filter($data[$type], fn($i) => !empty($i['show_in_menu']));

        if ($menuStyle === 'grouped') {
            // The type's own listing page (e.g. /articles/) already lists
            // every item of that type, so the group link belongs in the menu
            // whenever the type has any content at all — independent of
            // whether any single item opted into "Show in Main Menu". That
            // flag only controls which items additionally get their own
            // quick-link in the dropdown below.
            $hasDropdown = !empty($flagged);
            $html .= '<li' . ($hasDropdown ? ' class="has-submenu"' : '') . '>';
            $html .= '<a href="' . cleanUrl($type) . '">' . htmlspecialchars(__t($type . 's', ucfirst($type) . 's')) . '</a>';
            if ($hasDropdown) {
                $flagged = sortMenuItems(array_values($flagged), $orderBy);
                $html .= '<ul>';
                foreach ($flagged as $item) {
                    $slug     = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
                    $category = !empty($item['category']) ? sanitizeSlug($item['category']) : null;
                    $html    .= '<li><a href="' . cleanUrl($type, $slug, null, $category) . '">' . htmlspecialchars($item['title']) . '</a></li>';
                }
                $html .= '</ul>';
            }
            $html .= '</li>';
            continue;
        }

        // Flat style has no type grouping to fall back on, so only
        // explicitly flagged items make sense here — showing every
        // unflagged item would dump the entire content list into one menu.
        if (empty($flagged)) continue;
        $flagged = sortMenuItems(array_values($flagged), $orderBy);
        foreach ($flagged as $item) {
            $slug     = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
            $category = !empty($item['category']) ? sanitizeSlug($item['category']) : null;
            $html    .= '<li><a href="' . cleanUrl($type, $slug, null, $category) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
    }

    $html .= '</ul>';
    return $html;
}

/**
 * Sorts an array of menu items by the given ordering preference.
 *
 * @param array  $items   Items to sort (must be a plain array, not associative).
 * @param string $orderBy One of: menu_order | date_desc | date_asc | alphabetical.
 * @return array Sorted items.
 */
function sortMenuItems($items, $orderBy)
{
    usort($items, function ($a, $b) use ($orderBy) {
        switch ($orderBy) {
            case 'menu_order':
                $oa = (int)($a['menu_order'] ?? 0);
                $ob = (int)($b['menu_order'] ?? 0);
                return $oa !== $ob ? $oa - $ob : strcasecmp($a['title'], $b['title']);
            case 'date_desc':
                return strcmp($b['date'] ?? '', $a['date'] ?? '');
            case 'date_asc':
                return strcmp($a['date'] ?? '', $b['date'] ?? '');
            default:
                return strcasecmp($a['title'], $b['title']);
        }
    });
    return $items;
}
