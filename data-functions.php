<?php
/**
 * Build the full URL path for a category by walking up its parent chain.
 * Returns a slash-separated string of slugs: "grandparent/parent/child"
 * Falls back to just the category slug if the categories store is unavailable.
 *
 * @param string $categorySlug  The slug of the leaf category
 * @param array  $data          The full data array (must contain 'categories' key)
 * @return string               Slash-joined path, no leading/trailing slashes
 */
function getCategoryPath(string $categorySlug, array $data): string {
    if (empty($categorySlug)) return '';

    $categories = $data['categories'] ?? [];
    $parts = [];
    $current = $categorySlug;
    $visited = []; // Guard against circular references

    while (!empty($current) && !isset($visited[$current])) {
        $visited[$current] = true;
        array_unshift($parts, $current);
        $parent = $categories[$current]['parent'] ?? '';
        $current = $parent;
    }

    return implode('/', $parts);
}

/**
 * Data Functions
 *
 * Handle data loading, saving, and manipulation for the CMS.
 */

/**
 * Parse request URI and extract parameters
 * @return array Parsed URI parameters
 */
function parseRequestUri()
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = dirname($_SERVER['SCRIPT_NAME']);

    // Strip base path from URI if it exists
    if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }

    // Remove leading and trailing slashes
    $uri = trim($uri, '/');

    // If empty, it's the homepage
    if (empty($uri)) {
        return [
            'type' => '',
            'slug' => '',
            'page' => '',
            'category' => '',
        ];
    }

    // Parse URI segments
    $segments = explode('/', $uri);

    // ── Localized URL slug map ────────────────────────────────────────────────
    // Build a two-way map between incoming URL prefixes (which may be translated)
    // and internal type identifiers. __t() falls back to the English key when the
    // locale string is missing, so English always works with no extra configuration.
    //
    // singular slug  → internal type
    // plural slug    → internal type (list pages)
    // 'category' slug → 'category'
    // 'tag' slug      → 'tag'
    $typeFromSlug = []; // localized_slug → internal_type
    $slugFromType = []; // internal_type  → localized_slug (singular)
    $slugPluralFromType = []; // internal_type → localized plural slug

    foreach (['article', 'project', 'page'] as $_t) {
        $single = sanitizeSlug(__t('url_slug_' . $_t,  $_t));
        $plural = sanitizeSlug(__t('url_slug_' . $_t . 's', $_t . 's'));
        $typeFromSlug[$single] = $_t;
        $typeFromSlug[$plural] = $_t; // plural also maps to internal type
        $slugFromType[$_t]       = $single;
        $slugPluralFromType[$_t] = $plural;
    }
    $catSlug = sanitizeSlug(__t('url_slug_category', 'category'));
    $tagSlug = sanitizeSlug(__t('url_slug_tag',      'tag'));
    $typeFromSlug[$catSlug] = 'category';
    $typeFromSlug[$tagSlug] = 'tag';

    // All recognized first-segment reserved keywords (localized + English fallbacks)
    $reserved = array_keys($typeFromSlug);

    // ── 0. Single segment: root-level page (e.g. /a-propos/) ────────────────
    if (count($segments) === 1 && !isset($typeFromSlug[$segments[0]])) {
        if (isset($GLOBALS['data']['page'])) {
            foreach ($GLOBALS['data']['page'] as $page) {
                $pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];
                if ($pageSlug === $segments[0]) {
                    return ['type' => 'page', 'slug' => $segments[0], 'page' => '', 'category' => ''];
                }
            }
        }
    }

    // ── 1. Content type list: /articles/, /projets/, /pages/ ─────────────────
    if (count($segments) === 1 && isset($typeFromSlug[$segments[0]])) {
        $internalType = $typeFromSlug[$segments[0]];
        if (in_array($internalType, ['article', 'project', 'page'])) {
            return ['type' => $internalType, 'slug' => '', 'page' => '', 'category' => ''];
        }
    }

    // ── 2. Pagination: /articles/page/2 (localized plural prefix) ────────────
    if (count($segments) === 3
        && isset($typeFromSlug[$segments[0]])
        && $segments[1] === 'page'
        && is_numeric($segments[2])
    ) {
        $internalType = $typeFromSlug[$segments[0]];
        if (in_array($internalType, ['article', 'project', 'page'])) {
            return ['type' => $internalType, 'slug' => '', 'page' => $segments[2], 'category' => ''];
        }
    }

    // ── 3. Category listing: /categorie/slug/ or /categorie/parent/child/ ─────
    // Supports any depth: first segment must be the localized category prefix;
    // the leaf (last) segment is the category slug passed to the renderer.
    if (count($segments) >= 2 && $segments[0] === $catSlug) {
        $leafCat = end($segments); // last segment = leaf category slug
        return ['type' => 'category', 'slug' => '', 'page' => '', 'category' => $leafCat];
    }

    // ── 4. Tag listing: /tag/nom/ or /etiquette/nom/ ─────────────────────────
    if (count($segments) === 2 && $segments[0] === $tagSlug) {
        return ['type' => 'tag', 'slug' => '', 'page' => '', 'category' => '', 'tag' => $segments[1]];
    }

    // ── 5. Single article/page without category: /article/slug/ ─────────────
    //   or localized: /article/slug/
    if (count($segments) === 2
        && isset($typeFromSlug[$segments[0]])
        && in_array($typeFromSlug[$segments[0]], ['article', 'page'])
    ) {
        return [
            'type'     => $typeFromSlug[$segments[0]],
            'slug'     => $segments[1],
            'page'     => '',
            'category' => '',
        ];
    }

    // ── 6. Project without category: /project/slug/ (localized prefix) ───────
    if (count($segments) === 2
        && isset($typeFromSlug[$segments[0]])
        && $typeFromSlug[$segments[0]] === 'project'
    ) {
        return ['type' => 'project', 'slug' => $segments[1], 'page' => '', 'category' => ''];
    }

    // ── 7. Project with category: /project/[parent/]cat/slug/ ────────────────
    if (count($segments) >= 3
        && isset($typeFromSlug[$segments[0]])
        && $typeFromSlug[$segments[0]] === 'project'
    ) {
        $projectSlug = end($segments);
        $catParts    = array_slice($segments, 1, -1);
        $leafCatSlug = end($catParts);
        return ['type' => 'project', 'slug' => $projectSlug, 'page' => '', 'category' => $leafCatSlug];
    }

    // ── 8. Article/page with hierarchical category path ───────────────────────
    // URL shape: [cat/][subcat/]slug  — first segment is NOT a reserved keyword.
    // Handles 2–4 segments. Matches by comparing the full category path stored
    // on each content item against the incoming segment prefix.
    if (count($segments) >= 2 && count($segments) <= 4 && !isset($typeFromSlug[$segments[0]])) {
        $potentialSlug     = end($segments);
        $potentialCatParts = array_slice($segments, 0, -1);
        $potentialCatSlug  = end($potentialCatParts);
        $requestedCatPath  = implode('/', $potentialCatParts);

        $foundArticle  = false;
        $foundCategory = false;

        if (isset($GLOBALS['data']['article'])) {
            foreach ($GLOBALS['data']['article'] as $article) {
                if (!isset($article['category'])) continue;
                $itemCatSlug = sanitizeSlug($article['category']);
                $itemSlug    = !empty($article['custom_slug']) ? $article['custom_slug'] : $article['slug'];
                $fullCatPath = getCategoryPath($itemCatSlug, $GLOBALS['data']);
                if ($fullCatPath === $requestedCatPath) {
                    $foundCategory = true;
                    if ($itemSlug === $potentialSlug) { $foundArticle = true; break; }
                }
            }
        }

        if (!$foundArticle && isset($GLOBALS['data']['page'])) {
            foreach ($GLOBALS['data']['page'] as $page) {
                if (!isset($page['category'])) continue;
                $itemCatSlug = sanitizeSlug($page['category']);
                $itemSlug    = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];
                $fullCatPath = getCategoryPath($itemCatSlug, $GLOBALS['data']);
                if ($fullCatPath === $requestedCatPath && $itemSlug === $potentialSlug) {
                    return ['type' => 'page', 'slug' => $potentialSlug, 'page' => '', 'category' => $potentialCatSlug];
                }
            }
        }

        if ($foundArticle) {
            return ['type' => 'article', 'slug' => $potentialSlug, 'page' => '', 'category' => $potentialCatSlug];
        } elseif ($foundCategory) {
            return ['type' => 'category', 'slug' => '', 'page' => '', 'category' => $potentialCatSlug];
        }
    }

    // Default: not found
    return ['type' => '404', 'slug' => '', 'page' => '', 'category' => ''];
}

/**
 * Get all categories for a specific content type
 * @param string $contentType The content type ('article' or 'project')
 * @param array $data The data array containing all content
 * @return array List of categories
 */
function getCategories($contentType, $data)
{
    if (!in_array($contentType, ['article', 'project']) || !isset($data[$contentType])) {
        return [];
    }

    $categories = [];
    foreach ($data[$contentType] as $item) {
        if (isset($item['category']) && !empty($item['category'])) {
            $categorySlug = sanitizeSlug($item['category']);
            $categories[$categorySlug] = $item['category'];
        }
    }

    return $categories;
}

/**
 * Get all tags for a specific content type
 * @param string $contentType The content type ('article' or 'project')
 * @param array $data The data array containing all content
 * @return array List of tags
 */
function getTags($contentType, $data)
{
    if (!in_array($contentType, ['article', 'project']) || !isset($data[$contentType])) {
        return [];
    }

    $tags = [];
    foreach ($data[$contentType] as $item) {
        if (isset($item['tags']) && is_array($item['tags'])) {
            foreach ($item['tags'] as $tag) {
                $tagSlug = sanitizeSlug($tag);
                $tags[$tagSlug] = $tag;
            }
        }
    }

    return $tags;
}

/**
 * Generate SEO title and description based on page context
 * @param string $pageTitle The current page title
 * @param string $type The content type
 * @param string $slug The content slug
 * @param array $data The data array
 * @param array $settings The settings array
 * @return array SEO data
 */
function generateSEO($pageTitle, $type, $slug, $data, $settings)
{
    $metaTitle = $settings["site_title"]; // Default
    $metaDescription = $settings["site_description"]; // Default

    if (!empty($type) && !empty($slug)) {
        // Individual content page
        if (in_array($type, ["article", "page", "project"])) {
            foreach ($data[$type] as $item) {
                // Check for custom slug or default slug
                $itemSlug = !empty($item["custom_slug"]) ? $item["custom_slug"] : $item["slug"];

                if ($itemSlug === $slug) {
                    // Use custom meta title if available
                    if (!empty($item["meta_title"])) {
                        $metaTitle = decodeHtmlEntities($item["meta_title"]);
                    } else {
                        // Use default format
                        $metaTitle = str_replace(
                            ["{page_title}", "{site_title}"],
                            [decodeHtmlEntities($item["title"]), decodeHtmlEntities($settings["site_title"])],
                            $settings["default_meta_title"]
                        );
                    }
                    // Use custom meta description if available
                    if (!empty($item["meta_description"])) {
                        $metaDescription = decodeHtmlEntities($item["meta_description"]);
                    } else {
                        // Use default format
                        $metaDescription = str_replace(
                            ["{site_description}"],
                            [decodeHtmlEntities($settings["site_description"])],
                            $settings["default_meta_description"]
                        );
                    }
                    break;
                }
            }
        }
    }

    return [
        'title' => $metaTitle,
        'description' => $metaDescription
    ];
}

/**
 * Render a category page
 * @param string $category The category slug
 * @param array $data The data array containing all content
 * @return string The HTML output for the category page
 */
function renderCategoryPage($category, $data)
{
    // Collect matching items before opening the output buffer
    $categoryName = '';
    $foundItems   = [];

    foreach (['article', 'project'] as $contentType) {
        if (isset($data[$contentType])) {
            foreach ($data[$contentType] as $item) {
                if (isset($item['category']) && sanitizeSlug($item['category']) === $category) {
                    if (empty($categoryName)) {
                        $categoryName = $item['category'];
                    }
                    $item['_content_type'] = $contentType;
                    $foundItems[]          = $item;
                }
            }
        }
    }

    // Sort by date descending
    if (!empty($foundItems)) {
        usort($foundItems, function ($a, $b) {
            if (isset($a['date']) && isset($b['date'])) {
                return strcmp($b['date'], $a['date']);
            }
            return 0;
        });
    }

    // Pre-compute typed subsets for the content-list.php template
    $articles = array_values(array_filter($foundItems, function ($item) {
        return $item['_content_type'] === 'article';
    }));
    $projects = array_values(array_filter($foundItems, function ($item) {
        return $item['_content_type'] === 'project';
    }));

    // Use the theme's content-list.php template when available
    $settings       = loadSettings();
    $contentListTpl = __DIR__ . '/theme/' . ($settings['active_theme'] ?? 'default') . '/content-list.php';

    ob_start();
    if (file_exists($contentListTpl)) {
        $list_type    = 'category';
        $filter_value = $category;
        $items        = $foundItems;
        include $contentListTpl;
    } else {
        // Legacy hardcoded fallback — delegates to render_article_card() / render_project_card()
        // so partial overrides still apply even without content-list.php
        echo '<section class="category-content">';
        if (empty($foundItems)) {
            echo '<p>' . __t('no_content_in_category') . '</p>';
        } else {
            if (!empty($articles)) {
                echo '<h2>' . __t('articles') . '</h2>';
                echo '<div class="articles-grid">';
                foreach ($articles as $article) {
                    echo render_article_card($article);
                }
                echo '</div>';
            }
            if (!empty($projects)) {
                echo '<h2>' . __t('projects') . '</h2>';
                echo '<div class="projects-grid">';
                foreach ($projects as $project) {
                    echo render_project_card($project);
                }
                echo '</div>';
            }
        }
        echo '</section>';
    }
    return ob_get_clean();
}

/**
 * Render a tag page
 * @param string $tag The tag slug
 * @param array $data The data array containing all content
 * @return string The HTML output for the tag page
 */
function renderTagPage($tag, $data)
{
    // Collect matching items before opening the output buffer
    $tagName    = '';
    $foundItems = [];

    foreach (['article', 'project'] as $contentType) {
        if (isset($data[$contentType])) {
            foreach ($data[$contentType] as $item) {
                if (isset($item['tags']) && is_array($item['tags'])) {
                    foreach ($item['tags'] as $itemTag) {
                        if (sanitizeSlug($itemTag) === $tag) {
                            if (empty($tagName)) {
                                $tagName = $itemTag;
                            }
                            $item['_content_type'] = $contentType;
                            $foundItems[]          = $item;
                            break;
                        }
                    }
                }
            }
        }
    }

    // Sort by date descending
    if (!empty($foundItems)) {
        usort($foundItems, function ($a, $b) {
            if (isset($a['date']) && isset($b['date'])) {
                return strcmp($b['date'], $a['date']);
            }
            return 0;
        });
    }

    // Pre-compute typed subsets for the content-list.php template
    $articles = array_values(array_filter($foundItems, function ($item) {
        return $item['_content_type'] === 'article';
    }));
    $projects = array_values(array_filter($foundItems, function ($item) {
        return $item['_content_type'] === 'project';
    }));

    // Use the theme's content-list.php template when available
    $settings       = loadSettings();
    $contentListTpl = __DIR__ . '/theme/' . ($settings['active_theme'] ?? 'default') . '/content-list.php';

    ob_start();
    if (file_exists($contentListTpl)) {
        $list_type    = 'tag';
        $filter_value = $tag;
        $items        = $foundItems;
        include $contentListTpl;
    } else {
        // Legacy hardcoded fallback — delegates to render_article_card() / render_project_card()
        // so partial overrides still apply even without content-list.php
        echo '<section class="tag-content">';
        if (empty($foundItems)) {
            echo '<p>' . __t('no_content_with_tag') . '</p>';
        } else {
            if (!empty($articles)) {
                echo '<h2>' . __t('articles') . '</h2>';
                echo '<div class="articles-grid">';
                foreach ($articles as $article) {
                    echo render_article_card($article);
                }
                echo '</div>';
            }
            if (!empty($projects)) {
                echo '<h2>' . __t('projects') . '</h2>';
                echo '<div class="projects-grid">';
                foreach ($projects as $project) {
                    echo render_project_card($project);
                }
                echo '</div>';
            }
        }
        echo '</section>';
    }
    return ob_get_clean();
}