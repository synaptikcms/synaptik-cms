<?php

function getGalleryScripts($galleryLayout)
{
    $scripts = [];

    $vendor = getBaseUrl() . 'assets/vendor/';

    switch ($galleryLayout) {
        case 'masonry':
            $scripts[] = '<script src="' . $vendor . 'masonry-4.2.2.pkgd.min.js"></script>';
            $scripts[] = '    <script src="' . $vendor . 'imagesloaded-4.1.4.pkgd.min.js"></script>';
            break;

        case 'justified':
            $scripts[] = '<script src="' . $vendor . 'jquery-3.6.0.min.js"></script>';
            $scripts[] = '    <link rel="stylesheet" href="' . $vendor . 'justifiedGallery-3.8.1.min.css">';
            $scripts[] = '    <script src="' . $vendor . 'jquery.justifiedGallery-3.8.1.min.js"></script>';
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
            . hsc($galleryId, ENT_QUOTES) . '">'
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
        $imageUrl = getBaseUrl() . hsc($imageSrc);
        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        echo '>
                        <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="';
        $altText = !empty($galleryImage['alt_text']) ?
            hsc(decodeHtmlEntities($galleryImage['alt_text'])) :
            (!empty($galleryImage['caption']) ?
                hsc(decodeHtmlEntities($galleryImage['caption'])) :
                'Gallery Image');
        echo $altText . '">
                    </a>';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="gallery-caption">' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
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
        $imageUrl = getBaseUrl() . hsc($imageSrc);

        echo '
                    <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altMasonry = !empty($galleryImage['alt_text'])
            ? hsc(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? hsc(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                        <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="' . $altMasonry . '">
                    </a>';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="gallery-caption">' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
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
        $imageUrl = getBaseUrl() . hsc($imageSrc);

        echo '
                <a href="' . $imageUrl . '" data-lightbox="' . $galleryId . '"';
        if (!empty($galleryImage['caption'])) {
            echo ' data-title="' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '"';
        }
        $altJustified = !empty($galleryImage['alt_text'])
            ? hsc(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? hsc(decodeHtmlEntities($galleryImage['caption'])) : '');
        echo '>
                    <img src="' . $imageUrl . '" loading="lazy"' . _image_dimensions_attr($imageSrc) . ' alt="' . $altJustified . '">';

        if (!empty($galleryImage['caption'])) {
            echo '
                    <div class="caption">' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
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
        $imageUrl = getBaseUrl() . hsc($imageSrc);
        $activeClass = ($index === 0) ? ' active' : '';
        $altCarousel = !empty($galleryImage['alt_text'])
            ? hsc(decodeHtmlEntities($galleryImage['alt_text']))
            : (!empty($galleryImage['caption']) ? hsc(decodeHtmlEntities($galleryImage['caption'])) : '');
        $lazyAttr = ($index === 0) ? '' : ' loading="lazy"';
        echo '
                    <div class="carousel-item' . $activeClass . '">
                        <img src="' . $imageUrl . '"' . $lazyAttr . _image_dimensions_attr($imageSrc) . ' alt="' . $altCarousel . '">';

        if (!empty($galleryImage['caption'])) {
            echo '
                        <div class="carousel-caption">' . hsc(decodeHtmlEntities($galleryImage['caption'])) . '</div>';
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