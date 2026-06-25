<?php
/**
 * Core Functions
 *
 * Core functionality for the CMS that isn't directly related to data or templates.
 */

/**
 * Resolve the admin directory name reliably.
 *
 * Resolution order:
 *   1. settings.json → admin_dir key (fastest — normal runtime path)
 *   2. Filesystem scan for a folder containing admin-credentials.php (fallback
 *      for fresh installs or renamed folders not yet saved to settings)
 *   3. Hard default 'admin'
 *
 * Result is cached in $GLOBALS for the lifetime of the request.
 *
 * @return string Admin folder name (no leading or trailing slash)
 */
function resolve_admin_dir(): string
{
    if (isset($GLOBALS['_resolved_admin_dir'])) {
        return $GLOBALS['_resolved_admin_dir'];
    }

    // 1. settings.json
    if (function_exists('loadSettings')) {
        $s = loadSettings();
        $fromSettings = rtrim($s['admin_dir'] ?? '', '/');
        if ($fromSettings !== '' && is_dir(__DIR__ . '/' . $fromSettings)) {
            $GLOBALS['_resolved_admin_dir'] = $fromSettings;
            return $fromSettings;
        }
    }

    // 2. Filesystem scan
    foreach (glob(__DIR__ . '/*/admin-credentials.php') ?: [] as $f) {
        $found = basename(dirname($f));
        $GLOBALS['_resolved_admin_dir'] = $found;
        return $found;
    }

    // 3. Default
    $GLOBALS['_resolved_admin_dir'] = 'admin';
    return 'admin';
}

/**
 * Generate breadcrumb navigation.
 *
 * Handles all page types:
 *   - List pages          : Home › Articles
 *   - Category pages      : Home › Category: name
 *   - Tag pages           : Home › Tag: name
 *   - Single content item : Home › [ParentCat ›] [SubCat ›] Title
 *
 * Category links use the full hierarchical path (parent/child) so they match
 * the actual front-end routes produced by cleanUrl() / getCategoryPath().
 *
 * @param string $type     Internal content type (article, page, project, category, tag)
 * @param string $slug     Content slug (empty for list pages)
 * @param string $title    Human-readable page title (for the terminal crumb)
 * @param string $category Category slug of the leaf category (may be empty)
 * @return string          HTML breadcrumb block
 */
function getBreadcrumbs($type, $slug = '', $title = '', $category = '')
{
    $output = '
        <div class="breadcrumbs">';

    // Home link — always first
    $output .= '
            <a href="' . getBaseUrl() . '">' . __t('home') . '</a>';

    // ── List page: Home › Articles ────────────────────────────────────────────
    if (!empty($type) && empty($slug) && $type !== 'category' && $type !== 'tag') {
        // Use the localized plural slug as the display label
        $listLabel = __t('url_slug_' . $type . 's', ucfirst($type) . 's');
        $output .= ' &raquo; <span>' . htmlspecialchars(ucfirst($listLabel)) . '</span>';
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
        $listLabel = __t('url_slug_' . $type . 's', ucfirst($type) . 's');
        $output .= ' &raquo; 
            <a href="' . cleanUrl($type) . '">' . htmlspecialchars(ucfirst($listLabel)) . '</a>';

        // 2. Category crumbs — resolve full hierarchical path so each segment links correctly
        if (!empty($category)) {
            // getCategoryPath() only needs the categories store.
            // $GLOBALS['data'] is always set by index.php; the fallback loads
            // categories only (no full item data needed for breadcrumb resolution).
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

/**
 * Returns the HTML for the 404 page.
 * Loads 404.php from the active theme with a minimal fallback if the file is absent.
 *
 * @return string Full HTML for the 404 page.
 */
function get404PageContent()
{
    $base_url = getBaseUrl();
    $home_url = cleanUrl('home');

    $settings    = loadSettings();
    $activeTheme = isset($settings['active_theme']) ? $settings['active_theme'] : 'default';

    $templatePath = __DIR__ . '/theme/' . $activeTheme . '/404.php';

    if (file_exists($templatePath)) {
        ob_start();
        include $templatePath; // $base_url and $home_url are available in the template
        return ob_get_clean();
    }

    // Minimal fallback when no 404.php is found in the active theme
    return '
    <div style="text-align:center;padding:4rem 2rem;font-family:Georgia,serif;">
        <h1 style="font-size:5rem;">404</h1>
        <p>' . htmlspecialchars(__t('page_not_found_desc')) . '</p>
        <a href="' . htmlspecialchars($home_url) . '">' . htmlspecialchars(__t('back_to_home')) . '</a>
    </div>';
}

/**
 * Process page content based on type and slug
 * @param string $type Content type (article, page, project)
 * @param string $slug Content slug
 * @param array $data Data array
 * @param array $settings Settings array
 * @param string $category Category name (if applicable)
 * @param string $tag Tag name (if applicable)
 * @return array Page data (title, content)
 */
function processContent($type, $slug, $data, $settings, $category = '', $tag = '')
{
    // Initialize variables
    $pageTitle = $settings['site_title'] ?? 'SynaptikCMS';
    $pageContent = "";
    $httpStatus = 200;
    $contentTypes = ["article", "page", "project"];

    // ROUTING LOGIC - Handle different page types
    // 1. Single content item (article/my-article)
    if (!empty($type) && !empty($slug)) {
        if (in_array($type, $contentTypes)) {
            $contentFound = false;
            foreach ($data[$type] as $item) {
                // Check for custom slug or default slug
                $itemSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
                // Check if category matches when specified
                if (!empty($category) && (!isset($item['category']) || sanitizeSlug($item['category']) !== $category)) {
                    continue;
                }

                if ($itemSlug === $slug) {
                    $contentFound = true;
                    $pageTitle = decodeHtmlEntities($item['title']);

                    // Use template files for content rendering
                    ob_start();
                    // Pass the item to the appropriate template file
                    // Load custom page template if assigned, else default content-{type}s
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
            // Handle plural forms too (articles, pages, projects)
            $actualType = $type;
            if (in_array($type, ['articles', 'pages', 'projects'])) {
                $actualType = rtrim($type, 's');
            }

            // Only proceed if the type is valid
            if (in_array($actualType, $contentTypes)) {
                $pageTitle = ucfirst($type);

                // Sort items by date descending
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

                // Pre-compute typed subsets for the content-list.php template
                $articles = ($actualType !== 'project') ? $items : [];
                $projects = ($actualType === 'project') ? $items : [];

                // Use the theme's content-list.php template when available.
                // Falls back to hardcoded rendering so existing themes keep working.
                $contentListTpl = __DIR__ . '/theme/' . ($settings['active_theme'] ?? 'default') . '/content-list.php';

                ob_start();
                if (file_exists($contentListTpl)) {
                    $list_type    = $actualType;
                    $filter_value = '';
                    include $contentListTpl;
                } else {
                    // Legacy hardcoded fallback — delegates to render_article_card() / render_project_card()
                    // so partial overrides still apply even without content-list.php
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
        // Check if a specific page is set as homepage
        if ($settings['homepage_type'] === 'page' && !empty($settings['homepage_page_id'])) {
            // Find the selected page
            $homePageFound = false;
            foreach ($data['page'] as $page) {
                $pageSlug = !empty($page['custom_slug']) ? $page['custom_slug'] : $page['slug'];

                if ($pageSlug === $settings['homepage_page_id']) {
                    $pageTitle = htmlspecialchars($page['title']);

                    ob_start();

                    // Respect page_template if set — same logic as regular page routing.
                    // Without this, custom page-templates (landing, contact…) are ignored
                    // when the page is used as the homepage.
                    if (!empty($page['page_template'])) {
                        loadThemeTemplate('page-templates/' . $page['page_template'], ['item' => $page]);
                    } else {
                        // Standard page rendering
                        // Only display featured image if the setting is enabled
                        if (isset($page['image']) && isset($page['show_featured_image']) && $page['show_featured_image']) {
                            echo '
        <div class="featured-image homepage-featured">
            <img src="' . getBaseUrl() . htmlspecialchars($page['image']) . '" alt="' . htmlspecialchars($page['title']) . '">
        </div>';
                        }

                        // Only show title if show_title setting is true
                        if (isset($page['show_title']) && $page['show_title']) {
                            echo '
        <h1 class="page-title">' . htmlspecialchars($page['title']) . '</h1>';
                        }

                        // Render the page content — must go through render_content_html()
                        // so inline shortcodes ([gallery], [toc], [contact_form]…) are resolved.
                        echo '
        <section class="page-content">
            ' . render_content_html($page['content'] ?? '', $page) . '
        </section>';

                        // Check and render gallery if exists
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
            // If the selected homepage page wasn't found, fall back to default
            if (!$homePageFound) {
                ob_start(); // Capture the formatted HTML
                loadThemeTemplate('home', ['data' => $data, 'settings' => $settings]);
                $pageContent = ob_get_clean();
            }
        } else {
            // No specific page set as homepage, show default
            ob_start(); // Capture the formatted HTML
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

/**
 * Function to get required gallery scripts based on layout
 * @param string $galleryLayout The gallery layout type
 * @return array Array of script HTML tags
 */
function getGalleryScripts($galleryLayout)
{
    $scripts = [];

    // Common scripts for all gallery types (only include jQuery once)
    $scripts[] = '<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>';

    // Add specific scripts based on layout
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
            // Custom carousel implementation scripts if needed
            break;
    }

    return $scripts;
}

/**
 * Render a gallery with the specified layout
 * @param array $galleryItems The gallery items array
 * @param string $layout The gallery layout (grid, masonry, justified, carousel)
 * @return string The HTML output for the gallery
 */
function renderGallery($galleryItems, $layout = 'grid')
{
    if (empty($galleryItems) || !is_array($galleryItems)) {
        return '';
    }

    // Generate a unique ID for this gallery
    $galleryId = 'gallery-' . uniqid();

    // Buffer output
    ob_start();

    // Select the appropriate rendering function based on layout
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

    // Initialize the gallery.
    //
    // Dependencies (jQuery, justifiedGallery, masonry, imagesLoaded) are expected
    // to be loaded in <head> by the theme via render_header_scripts($headerScripts).
    // index.php scans the page's data, calls getGalleryScripts() for each detected
    // layout, and passes the resulting <script>/<link> tags to the theme header.
    // Because synchronous <script src> tags in <head> block parsing until executed,
    // the deps are guaranteed to be present (and in the correct order: jQuery first,
    // then plugin) by the time this inline init runs.
    //
    // If a dependency is missing, we log a clear console error rather than silently
    // fail — this almost always means the active theme is not calling
    // render_header_scripts($headerScripts) in its <head>.
    echo '<script>document.addEventListener("DOMContentLoaded", function() {';
    switch ($layout) {
        case 'masonry':
            echo '
            if (typeof Masonry === "undefined" || typeof imagesLoaded === "undefined") {
                console.error("[Gallery] Masonry/imagesLoaded missing. Verify the active theme outputs render_header_scripts($headerScripts) in <head>.");
                return;
            }
            var masonryGallery = document.querySelector("#' . $galleryId . '");
            if (masonryGallery) {
                imagesLoaded(masonryGallery, function() {
                    new Masonry(masonryGallery, {
                        itemSelector: ".masonry-item",
                        percentPosition: true,
                        gutter: 15
                    });
                });
            }';
            break;
        case 'justified':
            echo '
            (function() {
                if (typeof jQuery === "undefined") {
                    console.error("[Gallery] jQuery missing. Verify the active theme outputs render_header_scripts($headerScripts) in <head>.");
                    return;
                }
                var $jg = jQuery("#' . $galleryId . '");
                function apply() {
                    $jg.justifiedGallery({
                        rowHeight: 200,
                        margins: 5,
                        lastRow: "justify",
                        captions: true
                    });
                }
                if (typeof jQuery.fn.justifiedGallery !== "undefined") {
                    apply();
                } else {
                    // Plugin not on the current jQuery instance. Most common cause:
                    // the theme loads its own jQuery AFTER render_header_scripts(),
                    // overwriting window.jQuery and wiping $.fn.justifiedGallery
                    // that the head-loaded plugin had registered. Re-load the plugin
                    // now onto whichever jQuery is currently active.
                    var s = document.createElement("script");
                    s.src = "https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/js/jquery.justifiedGallery.min.js";
                    s.onload = apply;
                    s.onerror = function() {
                        console.error("[Gallery] Failed to load justifiedGallery plugin from CDN.");
                    };
                    document.head.appendChild(s);
                }
            })();';
            break;
        case 'carousel':
            echo '
            var carousel = document.getElementById("' . $galleryId . '");
            if (carousel) {
                var currentSlide = 0;
                var slides = carousel.querySelectorAll(".carousel-item");
                var totalSlides = slides.length;

                // Activate first slide
                if (slides.length > 0) {
                    slides[0].classList.add("active");
                }

                // Next button
                carousel.querySelector(".carousel-control-next").addEventListener("click", function(e) {
                    e.preventDefault();
                    slides[currentSlide].classList.remove("active");
                    currentSlide = (currentSlide + 1) % totalSlides;
                    slides[currentSlide].classList.add("active");
                });

                // Previous button
                carousel.querySelector(".carousel-control-prev").addEventListener("click", function(e) {
                    e.preventDefault();
                    slides[currentSlide].classList.remove("active");
                    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                    slides[currentSlide].classList.add("active");
                });
            }';
            break;
    }
    echo '});</script>';
    // Return buffered output
    return ob_get_clean();
}

function renderGridGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-grid" id="' . $galleryId . '" data-gallery-type="grid">';

    foreach ($galleryItems as $galleryImage) {
        echo '
                <div class="gallery-image">';

        // Construct full image URL
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        // Create a lightbox link
        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        echo '>
                        <img src="' . $imageUrl . '" alt="';
        // Get alt text, fallback to caption if alt text isn't available
        $altText = !empty($galleryImage['alt_text']) ?
            htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text'])) :
            (!empty($galleryImage['caption']) ?
                htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) :
                'Gallery Image');
        echo $altText . '">
                    </a>';

        // Show caption if exists
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

/**
 * Render a masonry gallery
 * @param array $galleryItems The gallery items array
 * @param string $galleryId Unique gallery ID
 */
function renderMasonryGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-masonry" id="' . $galleryId . '" data-gallery-type="masonry">';

    foreach ($galleryItems as $galleryImage) {
        echo '
                <div class="masonry-item">';

        // Construct full image URL
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        // Create a lightbox link
        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altMasonry = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                        <img src="' . $imageUrl . '" alt="' . $altMasonry . '">
                    </a>';

        // Show caption if exists
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

/**
 * Render a justified gallery
 * @param array $galleryItems The gallery items array
 * @param string $galleryId Unique gallery ID
 */
function renderJustifiedGallery($galleryItems, $galleryId)
{
    echo '
            <div class="justified-gallery" id="' . $galleryId . '" data-gallery-type="justified">';

    foreach ($galleryItems as $galleryImage) {
        // Construct full image URL
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        // Create a lightbox link
        echo '
                <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altJustified = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                    <img src="' . $imageUrl . '" alt="' . $altJustified . '">';

        // This caption will be used by the justified gallery plugin
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

/**
 * Render a carousel gallery
 * @param array $galleryItems The gallery items array
 * @param string $galleryId Unique gallery ID
 */
function renderCarouselGallery($galleryItems, $galleryId)
{
    echo '
            <div class="gallery-carousel" id="' . $galleryId . '" data-gallery-type="carousel">
                <div class="carousel-inner">';

    foreach ($galleryItems as $index => $galleryImage) {
        // Construct full image URL
        $imageSrc = $galleryImage['src'];
        if (strpos($imageSrc, 'files/') !== 0) {
            $imageSrc = 'files/' . $imageSrc;
        }
        $imageUrl = getBaseUrl() . htmlspecialchars($imageSrc);

        // First slide is active by default
        $activeClass = ($index === 0) ? ' active' : '';

        $altCarousel = !empty($galleryImage['alt_text'])
            ? htmlspecialchars(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? htmlspecialchars(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '
                    <div class="carousel-item' . $activeClass . '">
                        <img src="' . $imageUrl . '" alt="' . $altCarousel . '">';

        // Show caption if exists
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

/**
 * Helper function to generate the search icon HTML
 * @return string Search icon HTML
 */
function get_search_icon_html()
{
    global $settings;

    // Check if search icon should be shown
    if (isset($settings['show_search_icon']) && !$settings['show_search_icon']) {
        return '';
    }

    ob_start(); ?>
<li class="search-icon">
    <a href="#" id="search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </a>
</li>
<?php
    return ob_get_clean();
}

/**
 * Helper function to get the search overlay HTML
 * @return string Search overlay HTML
 */
function get_search_overlay_html()
{
    global $settings;

    // Only output search overlay if search is enabled
    if (isset($settings['show_search_icon']) && !$settings['show_search_icon']) {
        return '';
    }

    ob_start(); ?>
<div id="search-overlay" class="search-overlay">
    <div class="search-container">
        <div class="search-input-container">
            <input type="text" id="search-overlay-input" placeholder="<?php echo htmlspecialchars(__t('search_placeholder')); ?>">
            <button class="search-clear-btn" id="overlay-clear-btn" aria-label="<?php echo htmlspecialchars(__t('search_clear')); ?>">×</button>
        </div>
        <div class="search-options">
            <label>
                <input type="checkbox" id="search-in-content" checked>
                <?php echo __t('search_in_content'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-articles" checked>
                <?php echo __t('articles'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-pages" checked>
                <?php echo __t('pages'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-projects" checked>
                <?php echo __t('projects'); ?>
            </label>
        </div>
        <div id="search-results-overlay"></div>
    </div>
</div>
<?php
    return ob_get_clean();
}

/**
 * Function to include search functionality in a theme
 * Now uses consolidated CSS/JS
 */
function include_search_functionality()
{
    global $settings;

    // If search is disabled, don't add anything
    if (isset($settings['show_search_icon']) && !$settings['show_search_icon']) {
        return;
    }

    // Add search icon to navigation only if not already added by JS or HTML
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Check if search icon or toggle already exists anywhere in the document
        if (document.querySelector(".search-icon, .search-toggle, #search-toggle")) {
            return; // Already exists, dont add another one
        }
        
        // Check if we\'re using a custom menu
        const customMenuMarker = document.querySelector(".custom-menu-marker");
        
        // Only add search icon if we\'re NOT using a custom menu
        if (!customMenuMarker) {
            const navMenu = document.querySelector("nav ul");
            if (navMenu) {
                const searchListItem = document.createElement("li");
                searchListItem.classList.add("search-icon");
                searchListItem.innerHTML = "<a href=\"#\" id=\"search-toggle\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"11\" cy=\"11\" r=\"8\"></circle><line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"></line></svg></a>";
                navMenu.appendChild(searchListItem);
            }
        }
    });
    </script>';
}