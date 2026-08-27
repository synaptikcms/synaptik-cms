<?php
function renderHierarchicalMenu($settings, $data)
{
    if (!$settings['use_custom_menu'] || empty($settings['main_menu'])) {
        return renderDefaultMenu($data);
    }
    $tree = pl_apply_filter('menu_tree', buildMenuTree($settings['main_menu']));
    return renderMenuTree($tree);
}

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

function renderMenuTree($menuTree)
{
    $html = '<ul>';
    foreach ($menuTree as $item) {
        $url    = generateMenuItemUrl($item);
        $target = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target']) . '"' : '';
        $label  = $item['label'];
        if (($item['content_type'] ?? '') === 'list' && !empty($item['label_auto']) && !empty($item['content_slug'])) {
            $label = sl_type_label($item['content_slug'], true);
        }
        $html  .= '<li' . (!empty($item['children']) ? ' class="has-submenu"' : '') . '>';
        $html  .= '<a href="' . htmlspecialchars($url) . '"' . $target . '>' . htmlspecialchars($label) . '</a>';
        if (!empty($item['children'])) {
            $html .= renderMenuTree($item['children']);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

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
            $baseType = isset($item['content_slug']) ? $item['content_slug'] : '';
            if ($baseType !== '') {
                return $baseUrl . url_slug($baseType . 's') . '/';
            }
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
            $hasDropdown = !empty($flagged);
            $html .= '<li' . ($hasDropdown ? ' class="has-submenu"' : '') . '>';
            $html .= '<a href="' . cleanUrl($type) . '">' . htmlspecialchars(sl_type_label($type, true)) . '</a>';
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