<?php
/**
 * Navigation & Menu Rendering — SynaptikCMS
 * All functions for building and rendering the front-end navigation.
 */

/**
 * Renders the main navigation menu.
 * Uses the custom menu when enabled, otherwise falls back to renderDefaultMenu().
 */
function render_menu($settings, $data)
{
    ob_start(); ?>
<ul>
    <?php if ($settings['use_custom_menu'] && !empty($settings['main_menu'])): ?>
    <li><a href="<?php echo cleanUrl('home'); ?>"><?php echo __t('home'); ?></a></li>
    <?php foreach ($settings['main_menu'] as $menuItem):
        $url = '';
        if ($menuItem['type'] === 'custom') {
            $url = strpos($menuItem['url'], 'http') === 0
                ? htmlspecialchars($menuItem['url'])
                : getBaseUrl() . ltrim(htmlspecialchars($menuItem['url']), '/');
        } elseif ($menuItem['type'] === 'content') {
            if (isset($menuItem['content_type']) && $menuItem['content_type'] === 'list') {
                $url = cleanUrl($menuItem['content_slug']);
            } elseif (isset($menuItem['content_type']) && isset($menuItem['content_slug'])) {
                $url = cleanUrl($menuItem['content_type'], $menuItem['content_slug']);
            } else {
                $url = getBaseUrl() . ltrim(htmlspecialchars($menuItem['url']), '/');
            }
        } ?>
    <li>
        <a href="<?php echo $url; ?>"<?php echo !empty($menuItem['target']) ? ' target="' . htmlspecialchars($menuItem['target']) . '"' : ''; ?>><?php echo htmlspecialchars($menuItem['label']); ?></a>
    </li>
    <?php endforeach; else: ?>
    <li><a href="<?php echo cleanUrl('home'); ?>"><?php echo __t('home'); ?></a></li>
    <li><a href="<?php echo cleanUrl('project'); ?>"><?php echo __t('projects'); ?></a></li>
    <li><a href="<?php echo cleanUrl('article'); ?>"><?php echo __t('articles'); ?></a></li>
    <li><a href="<?php echo cleanUrl('page'); ?>"><?php echo __t('pages'); ?></a></li>
    <?php
        if (isset($data['page'])) {
            foreach ($data['page'] as $page) {
                if (!empty($page['show_in_menu'])) {
                    $pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];
                    echo '<li><a href="' . cleanUrl('page', $pageSlug) . '">' . htmlspecialchars($page['title']) . '</a></li>';
                }
            }
        }
        if (isset($data['article'])) {
            foreach ($data['article'] as $article) {
                if (!empty($article['show_in_menu'])) {
                    $articleSlug = !empty($article['custom_slug']) ? $article['custom_slug'] : $article['slug'];
                    echo '<li><a href="' . cleanUrl('article', $articleSlug) . '">' . htmlspecialchars($article['title']) . '</a></li>';
                }
            }
        }
    endif; ?>
</ul>
<?php
    return ob_get_clean();
}

/**
 * Renders a hierarchical menu with dropdown sub-menu support.
 * Falls back to renderDefaultMenu() when no custom menu is configured.
 */
function renderHierarchicalMenu($settings, $data)
{
    if (!$settings['use_custom_menu'] || empty($settings['main_menu'])) {
        return renderDefaultMenu($data);
    }
    return renderMenuTree(buildMenuTree($settings['main_menu']));
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
    $settings = loadSettings();
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

        $items = array_filter($data[$type], fn($i) => !empty($i['show_in_menu']));
        if (empty($items)) continue;

        $items = sortMenuItems(array_values($items), $orderBy);

        if ($menuStyle === 'grouped') {
            $html .= '<li class="has-submenu"><a href="' . cleanUrl($type) . '">' . htmlspecialchars(ucfirst($type) . 's') . '</a><ul>';
        }
        foreach ($items as $item) {
            $slug     = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
            $category = !empty($item['category']) ? sanitizeSlug($item['category']) : null;
            $html    .= '<li><a href="' . cleanUrl($type, $slug, null, $category) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
        if ($menuStyle === 'grouped') {
            $html .= '</ul></li>';
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
