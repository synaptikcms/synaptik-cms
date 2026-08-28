<?php
function resolve_admin_dir(): string
{
    if (isset($GLOBALS['_resolved_admin_dir'])) {
        return $GLOBALS['_resolved_admin_dir'];
    }

    // 1. config.json
    if (function_exists('loadConfig')) {
        $s = loadConfig();
        $fromSettings = rtrim($s['admin_dir'] ?? '', '/');
        if ($fromSettings !== '' && is_dir(CMS_ROOT . '/' . $fromSettings)) {
            $GLOBALS['_resolved_admin_dir'] = $fromSettings;
            return $fromSettings;
        }
    }

    // 2. Filesystem scan
    foreach (glob(CMS_ROOT . '/*/auth.php') ?: [] as $f) {
        $found = basename(dirname($f));
        $GLOBALS['_resolved_admin_dir'] = $found;
        return $found;
    }

    // 3. Default
    $GLOBALS['_resolved_admin_dir'] = 'admin';
    return 'admin';
}

function getBreadcrumbs($type, $slug = '', $title = '', $category = '')
{
    $output = '
        <div class="breadcrumbs">';

    // Home link — always first
    $output .= '
            <a href="' . getBaseUrl() . '">' . __t('home') . '</a>';

    // ── List page: Home › Articles ────────────────────────────────────────────
    if (!empty($type) && empty($slug) && $type !== 'category' && $type !== 'tag') {
        $listLabel = sl_type_label($type, true);
        $output .= ' &raquo; <span>' . htmlspecialchars($listLabel) . '</span>';
    }

    // ── Category listing page: Home › Category: name ──────────────────────────
    elseif ($type === 'category' && !empty($category)) {
        $output .= ' &raquo; 
            <span>' . __t('breadcrumb_category') . ': ' . htmlspecialchars(urldecode($category)) . '</span>';
    }

    // ── Tag listing page: Home › Tag: name ───────────────────────────────────
    elseif ($type === 'tag' && !empty($slug)) {
        $output .= ' &raquo; 
            <span>' . __t('breadcrumb_tag') . ': ' . htmlspecialchars(urldecode($slug)) . '</span>';
    }

    // ── Single content item ───────────────────────────────────────────────────
    elseif (!empty($type) && !empty($slug)) {
        // 1. Content-type list link (localized plural label)
        $listLabel = sl_type_label($type, true);
        $output .= ' &raquo;
            <a href="' . cleanUrl($type) . '">' . htmlspecialchars($listLabel) . '</a>';

        // 2. Category crumbs — resolve full hierarchical path so each segment links correctly
        if (!empty($category)) {
            $data    = isset($GLOBALS['data']) ? $GLOBALS['data'] : ['categories' => sl_load_categories()];
            $catPath = getCategoryPath($category, $data); // e.g. "parent/child"

            if (!empty($catPath)) {
                $segments    = explode('/', $catPath);
                $accumulated = '';

                foreach ($segments as $seg) {
                    $accumulated = $accumulated !== '' ? $accumulated . '/' . $seg : $seg;

                    // Resolve display name for this segment from the categories store
                    $catName = $seg; // fallback: slug itself
                    if (isset($data['categories'][$seg]['name'])) {
                        $catName = $data['categories'][$seg]['name'];
                    }

                    // Link target: localized category prefix + accumulated path
                    $catUrl  = getBaseUrl() . url_slug('category') . '/' . $accumulated . '/';
                    $output .= ' &raquo; 
            <a href="' . htmlspecialchars($catUrl) . '">' . htmlspecialchars($catName) . '</a>';
                }
            }
        }

        // 3. Current page title (terminal, non-linked)
        $output .= ' &raquo; 
            <span>' . htmlspecialchars($title) . '</span>';
    }

    $output .= '
        </div>';
    return $output;
}

function get404PageContent()
{
    $base_url = getBaseUrl();
    $home_url = cleanUrl('home');

    $settings    = loadConfig();
    $activeTheme = isset($settings['active_theme']) ? $settings['active_theme'] : 'default';

    $templatePath = CMS_ROOT . '/theme/child_theme/' . $activeTheme . '/404.php';
    if (!file_exists($templatePath)) {
        $templatePath = CMS_ROOT . '/theme/' . $activeTheme . '/404.php';
    }

    if (file_exists($templatePath)) {
        ob_start();
        include $templatePath; // $base_url and $home_url are available in the template
        return ob_get_clean();
    }

    return '
    <div style="text-align:center;padding:4rem 2rem;font-family:Georgia,serif;">
        <h1 style="font-size:5rem;">404</h1>
        <p>' . htmlspecialchars(__t('page_not_found_desc')) . '</p>
        <a href="' . htmlspecialchars($home_url) . '">' . htmlspecialchars(__t('back_to_home')) . '</a>
    </div>';
}

function processContent($type, $slug, $data, $settings, $category = '', $tag = '')
{
    $pageTitle = $settings['site_title'] ?? 'SynaptikCMS';
    $pageContent = "";
    $httpStatus = 200;
    $contentTypes = ["article", "page", "project"];

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
                    ob_start();
                    if ($type === 'page' && !empty($item['page_template'])) {
                        loadThemeTemplate('page-templates/' . $item['page_template'], ['item' => $item]);
                    } else {
                        loadThemeTemplate("content-{$type}s", ['item' => $item]);
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
                $pageTitle = ucfirst($type);

                $items = [];
                if (isset($data[$actualType]) && !empty($data[$actualType])) {
                    $items = $data[$actualType];
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
                    $pageTitle = htmlspecialchars($page['title']);

                    ob_start();

                    if (!empty($page['page_template'])) {
                        loadThemeTemplate('page-templates/' . $page['page_template'], ['item' => $page]);
                    } else {
                        if (isset($page['image']) && isset($page['show_featured_image']) && $page['show_featured_image']) {
                            echo '
        <div class="featured-image homepage-featured">
            <img src="' . getBaseUrl() . htmlspecialchars($page['image']) . '" alt="' . htmlspecialchars(!empty($page['image_alt']) ? $page['image_alt'] : $page['title']) . '"' . _image_dimensions_attr($page['image']) . '>
        </div>';
                        }
                        if (isset($page['show_title']) && $page['show_title']) {
                            echo '
        <h1 class="page-title">' . htmlspecialchars($page['title']) . '</h1>';
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

function getGalleryScripts($galleryLayout)
{
    $scripts = [];
    $scripts[] = '<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>';

    switch ($galleryLayout) {
        case 'masonry':
            $scripts[] = '    <script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>';
            $scripts[] = '    <script src="https://cdn.jsdelivr.net/npm/imagesloaded@4.1.4/imagesloaded.pkgd.min.js"></script>';
            break;

        case 'justified':
            $scripts[] = '    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/css/justifiedGallery.min.css">';
            $scripts[] = '    <script src="https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/js/jquery.justifiedGallery.min.js"></script>';
            break;

        case 'carousel':
            break;
    }

    $scripts[] = '    <script defer src="' . getBaseUrl() . 'assets/js/gallery-init.js'
        . (($_v = @filemtime(CMS_ROOT . '/assets/js/gallery-init.js')) ? '?v=' . $_v : '') . '"></script>';

    return $scripts;
}

function renderGallery($galleryItems, $layout = 'grid')
{
    if (empty($galleryItems) || !is_array($galleryItems)) {
        return '';
    }
    $galleryId = 'gallery-' . uniqid();
    ob_start();
    switch ($layout) {
        case 'masonry':
            renderMasonryGallery($galleryItems, $galleryId);
            break;
        case 'justified':
            renderJustifiedGallery($galleryItems, $galleryId);
            break;
        case 'carousel':
            renderCarouselGallery($galleryItems, $galleryId);
            break;
        case 'grid':
        default:
            renderGridGallery($galleryItems, $galleryId);
            break;
    }

    if (in_array($layout, ['masonry', 'justified', 'carousel'], true)) {
        echo '<script type="application/json" class="sc-gallery-config" data-gallery-id="'
            . htmlspecialchars($galleryId, ENT_QUOTES) . '">'
            . json_encode(['layout' => $layout]) . '</script>';
    }
    return ob_get_clean();
}

function renderGridGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-grid" id="' . $galleryId . '" data-gallery-type="grid">';

    foreach ($galleryItems as $galleryImage) {
        echo '
                <div class="gallery-image">';
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);
        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        echo '>
                        <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="';
        $altText = !empty($galleryImage['alt_text']) ?
            htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text'])) :
            (!empty($galleryImage['caption']) ?
                htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) :
                'Gallery Image');
        echo $altText . '">
                    </a>';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="gallery-caption">' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
        }

        echo '
                </div>';
    }

    echo '
            </div>';
}

function renderMasonryGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-masonry" id="' . $galleryId . '" data-gallery-type="masonry">';

    foreach ($galleryItems as $galleryImage) {
        echo '
                <div class="masonry-item">';

        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altMasonry = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                        <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="' . $altMasonry . '">
                    </a>';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="gallery-caption">' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
        }

        echo '
                </div>';
    }

    echo '
            </div>';
}

function renderJustifiedGallery($galleryItems, $galleryId)
{
    echo '
            <div class="justified-gallery" id="' . $galleryId . '" data-gallery-type="justified">';

    foreach ($galleryItems as $galleryImage) {
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        echo '
                <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altJustified = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                    <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="' . $altJustified . '">';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="caption">' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
        }

        echo '
                </a>';
    }

    echo '
            </div>';
}

function renderCarouselGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-carousel" id="' . $galleryId . '" data-gallery-type="carousel">
                <div class="carousel-inner">';

    foreach ($galleryItems as $index => $galleryImage) {
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);
        $activeClass = ($index === 0) ? ' active' : '';
        $altCarousel = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        $lazyAttr = ($index === 0) ? '' : ' loading="lazy"';
        echo '
                    <div class="carousel-item' . $activeClass . '">
                        <img src="' . $imageUrl . '"' . $lazyAttr . _image_dimensions_attr($imageSrc) . ' alt="' . $altCarousel . '">';

        if (!empty($galleryImage['caption'])) {
            echo '
                        <div class="carousel-caption">' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
        }

        echo '
                    </div>';
    }

    echo '
                </div>
                <a class="carousel-control carousel-control-prev" href="#' . $galleryId . '" role="button">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">' . __t('previous') . '</span>
                </a>
                <a class="carousel-control carousel-control-next" href="#' . $galleryId . '" role="button">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">' . __t('next') . '</span>
                </a>
            </div>';
}