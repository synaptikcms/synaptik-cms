<?php

function processContent($type, $slug, $data, $settings, $category = '', $tag = '')
{
    $pageTitle = $settings['site_title'] ?? 'Synaptik CMS';
    $pageContent = "";
    $httpStatus = 200;
    $contentTypes = sl_all_type_slugs();

    // 1. Single content item (article/my-article)
    if (!empty($type) && !empty($slug)) {
        if (in_array($type, $contentTypes)) {
            $contentFound = false;
            foreach ($data[$type] as $item) {
                $itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
                if (!empty($category) && (!isset($item['category']) || sanitizeSlug($item['category']) !== $category)) {
                    continue;
                }

                if ($itemSlug === $slug) {
                    $contentFound = true;
                    $pageTitle = decodeHtmlEntities($item['title']);
                    $item['_content_type'] = $type;
                    ob_start();
                    if ($type === 'page' && !empty($item['page_template'])) {
                        loadThemeTemplate('page-templates/' . $item['page_template'], ['item' => $item]);
                    } else {
                        loadThemeTemplate(sl_single_template_name($type), ['item' => $item, 'type' => $type]);
                    }
                    $pageContent = ob_get_clean();

                    break;
                }
            }
            
            if (!$contentFound) {
                $httpStatus = 404;
                $pageTitle = '404 — ' . __t('page_not_found');
                $pageContent = get404PageContent();
            }
            } else {
                $httpStatus = 404;
                $pageTitle = '404 — ' . __t('page_not_found');
                $pageContent = get404PageContent();
            }
    }
    // 2. ================  Content type list (articles/ or pages/ or projects/) ====================
    elseif (!empty($type) && empty($slug)) {
        if (in_array($type, $contentTypes) || $type === 'articles' || $type === 'pages' || $type === 'projects') {
            $actualType = $type;
            if (in_array($type, ['articles', 'pages', 'projects'])) {
                $actualType = rtrim($type, 's');
            }

            if (in_array($actualType, $contentTypes)) {
                $pageTitle = in_array($actualType, ['article', 'page', 'project'], true)
                    ? ucfirst($type)
                    : sl_type_label($actualType, true);

                $items = [];
                if (isset($data[$actualType]) && !empty($data[$actualType])) {
                    $items = $data[$actualType];
                    foreach ($items as &$__listItem) {
                        $__listItem['_content_type'] = $actualType;
                    }
                    unset($__listItem);
                    usort($items, function ($a, $b) {
                        if (isset($a['date']) && isset($b['date'])) {
                            return strcmp($b['date'], $a['date']);
                        }
                        return 0;
                    });
                }

                $articles = ($actualType !== 'project') ? $items : [];
                $projects = ($actualType === 'project') ? $items : [];

                $contentListTpl = CMS_ROOT . '/theme/child_theme/' . ($settings['active_theme'] ?? 'default') . '/content-list.php';
                if (!file_exists($contentListTpl)) {
                    $contentListTpl = CMS_ROOT . '/theme/' . ($settings['active_theme'] ?? 'default') . '/content-list.php';
                }

                ob_start();
                if (file_exists($contentListTpl)) {
                    $list_type    = $actualType;
                    $filter_value = '';
                    include $contentListTpl;
                } else {
                    echo '<section class="content-list">';
                    if (!empty($items)) {
                        if ($actualType === 'project') {
                            echo '<section class="projects-grid">';
                            foreach ($items as $project) {
                                echo render_project_card($project);
                            }
                            echo '</section>';
                        } else {
                            echo '<section class="articles-grid">';
                            foreach ($items as $item) {
                                echo render_article_card($item);
                            }
                            echo '</section>';
                        }
                    } else {
                        echo '<p>' . sprintf(__t('no_type_found'), $type) . '</p>';
                    }
                    echo '</section>';
                }
                $pageContent = ob_get_clean();
            } else {
                $httpStatus = 404;
                $pageTitle = '404 — ' . __t('page_not_found');
                $pageContent = get404PageContent();
            }
        } else {
            $httpStatus = 404;
            $pageTitle = '404 — ' . __t('page_not_found');
            $pageContent = get404PageContent();
        }
    }

    // 2.5 ================ Category listing ================================
    elseif ($type === 'category' && !empty($category)) {
        $pageTitle = __t('breadcrumb_category') . ': ' . ucfirst($category);
        $pageContent = renderCategoryPage($category, $data);
    }
    // 2.6 ================ Tag listing ================================
    elseif ($type === 'tag' && !empty($tag)) {
        $pageTitle = __t('breadcrumb_tag') . ': ' . ucfirst($tag);
        $pageContent = renderTagPage($tag, $data);
    }
    // 3. ================ Homepage ================================
    elseif (empty($type) && empty($slug)) {
        if ($settings['homepage_type'] === 'page' && !empty($settings['homepage_page_id'])) {
            $homePageFound = false;
            foreach ($data['page'] as $page) {
                $pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];

                if ($pageSlug === $settings['homepage_page_id']) {
                    $pageTitle = hsc($page['title']);

                    ob_start();

                    if (!empty($page['page_template'])) {
                        loadThemeTemplate('page-templates/' . $page['page_template'], ['item' => $page]);
                    } else {
                        if (isset($page['image']) && isset($page['show_featured_image']) && $page['show_featured_image']) {
                            echo '
        <div class="featured-image homepage-featured">
            <img src="' . getBaseUrl() . hsc($page['image']) . '" alt="' . hsc(!empty($page['image_alt']) ? $page['image_alt'] : $page['title']) . '"' . _image_dimensions_attr($page['image']) . '>
        </div>';
                        }
                        if (isset($page['show_title']) && $page['show_title']) {
                            echo '
        <h1 class="page-title">' . hsc($page['title']) . '</h1>';
                        }
                        echo '
        <section class="page-content">
            ' . render_content_html($page['content'] ?? '', $page) . '
        </section>';
                        if (isset($page['gallery']) && is_array($page['gallery']) && !empty($page['gallery'])) {
                            echo '
        <section class="content-gallery">
            <h2>' . __t('gallery') . '</h2>';
                            $galleryLayout = isset($page['gallery_layout']) ? $page['gallery_layout'] : 'grid';
                            echo renderGallery($page['gallery'], $galleryLayout);
                            echo '
        </section>';
                            $GLOBALS['galleryLayout'] = $galleryLayout;
                        }
                    }

                    $pageContent = ob_get_clean();

                    $homePageFound = true;
                    break;
                }
            }
            if (!$homePageFound) {
                ob_start();
                loadThemeTemplate('home', ['data' => $data, 'settings' => $settings]);
                $pageContent = ob_get_clean();
            }
        } else {
            ob_start();
            loadThemeTemplate('home', ['data' => $data, 'settings' => $settings]);
            $pageContent = ob_get_clean();
        }
    }

    return [
        'title' => $pageTitle,
        'content' => $pageContent,
        'http_status' => $httpStatus,
        'meta_title' => $metaTitle ?? '',
        'meta_description' => $metaDescription ?? '',
        'meta_keywords' => $item['meta_keywords'] ?? '',
        'canonical_url' => $item['canonical_url'] ?? '',
        'og_title' => $item['og_title'] ?? '',
        'og_description' => $item['og_description'] ?? '',
        'og_image' => isset($item['og_image']) ? $item['og_image'] : (isset($item['image']) ? $item['image'] : ''),
    ];
}

