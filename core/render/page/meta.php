<?php

function render_meta_tags($settings, $metaTitle, $metaDescription, $pageData = null)
{
    global $metaKeywords, $ogImage, $ogTitle, $ogDescription, $ogType, $ogPublishedTime, $ogModifiedTime, $type, $slug, $category, $tag, $data;
    $f = ENT_COMPAT | ENT_HTML5;
    $c = 'UTF-8';

    $_type     = $type     ?? '';
    $_slug     = $slug     ?? '';
    $_category = $category ?? '';
    $_tag      = $tag      ?? '';

    if ($pageData && !empty($pageData['canonical_url'])) {
        $_canonical = $pageData['canonical_url'];
    } elseif ($_type === 'category' && !empty($_category)) {
        $_canonical = cleanUrl('category', null, null, $_category);
    } elseif ($_type === 'tag' && !empty($_tag)) {
        $_canonical = cleanUrl('tag', null, null, $_tag);
    } elseif (!empty($_type) && !empty($_slug)) {
        $_itemCategory = $_category;
        foreach ($data[$_type] ?? [] as $_candidate) {
            $_candidateSlug = !empty($_candidate['custom_slug']) ? $_candidate['custom_slug'] : ($_candidate['slug'] ?? '');
            if ($_candidateSlug === $_slug) {
                $_itemCategory = $_candidate['category'] ?? '';
                break;
            }
        }
        $_canonical = cleanUrl($_type, $_slug, null, $_itemCategory);
    } elseif (!empty($_type)) {
        // Content type list (e.g. /articles/)
        $_canonical = cleanUrl($_type);
    } else {
        $_canonical = cleanUrl('home');
    }

    ob_start(); ?>
<meta property="og:site_name" content="<?php echo hsc($settings['site_title'], $f, $c); ?>">
    <meta name="description" content="<?php echo hsc($metaDescription, $f, $c); ?>">
<?php if (!empty($metaKeywords)): ?>
    <meta name="keywords" content="<?php echo hsc($metaKeywords, $f, $c); ?>">
<?php endif; ?>
    <meta property="og:title" content="<?php echo hsc($ogTitle ? $ogTitle : $metaTitle, $f, $c); ?>">
    <meta property="og:description" content="<?php echo hsc($ogDescription ? $ogDescription : $metaDescription, $f, $c); ?>">
    <meta property="og:type" content="<?php echo hsc($ogType ?: 'website', $f, $c); ?>">
<?php if (($ogType ?? 'website') === 'article'):
        $_ogPublishedTs = !empty($ogPublishedTime) ? strtotime($ogPublishedTime) : false;
        $_ogModifiedTs  = !empty($ogModifiedTime)  ? strtotime($ogModifiedTime)  : false;
?>
<?php if ($_ogPublishedTs !== false): ?>
    <meta property="article:published_time" content="<?php echo hsc(date('c', $_ogPublishedTs), $f, $c); ?>">
<?php endif; ?>
<?php if ($_ogModifiedTs !== false): ?>
    <meta property="article:modified_time" content="<?php echo hsc(date('c', $_ogModifiedTs), $f, $c); ?>">
<?php endif; ?>
<?php endif; ?>
    <meta property="og:url" content="<?php echo hsc($_canonical, $f, $c); ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?php echo hsc($ogImage, $f, $c); ?>"><?php endif; ?>
<?php if ($settings['enable_seo']): ?>
    <?php
        $_isArchive = false;
        if ($_type === 'category' && !empty($_category)) {
            $_isArchive = empty($data['categories'][$_category]['description']);
        } elseif ($_type === 'tag' && !empty($_tag)) {
            $_isArchive = empty($data['tags'][$_tag]['description']);
        } elseif (!empty($_type) && empty($_slug) && empty($_category) && empty($_tag)
                  && !in_array($_type, ['home', '404'], true)) {
            $_isArchive = true;
        }
        if ($_isArchive) {
            echo '<meta name="robots" content="noindex, follow">';
        }
    ?>
    <link rel="canonical" href="<?php echo hsc($_canonical, $f, $c); ?>">
<?php endif; ?>
<?php
    return pl_apply_filter('head_meta_tags', ob_get_clean(), $pageData);
}

function render_schema_jsonld(array $settings, string $type, string $slug, array $data): string
{
    if (empty($settings['enable_seo']) || empty($type) || empty($slug)) return '';

    $contentTypes = sl_all_type_slugs();
    if (!in_array($type, $contentTypes, true)) return '';

    $item = null;
    foreach ($data[$type] ?? [] as $candidate) {
        $effectiveSlug = !empty($candidate['custom_slug']) ? $candidate['custom_slug'] : ($candidate['slug'] ?? '');
        if ($effectiveSlug === $slug) { $item = $candidate; break; }
    }
    if ($item === null) return '';

    $schemaType = trim($item['schema_type'] ?? '');
    if ($schemaType === '') {
        $defaultSchemaTypes = ['article' => 'Article', 'project' => 'CreativeWork', 'page' => 'WebPage'];
        $schemaType = $defaultSchemaTypes[$type] ?? '';
        if ($schemaType === '') return '';
    }

    $allowedTypes = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'CreativeWork'];
    if (!in_array($schemaType, $allowedTypes, true)) return '';

    $base        = getBaseUrl();
    $siteTitle   = $settings['site_title']       ?? '';
    $authorName  = trim($settings['schema_author_name'] ?? '');
    $pubType     = in_array($settings['schema_publisher_type'] ?? '', ['Person', 'Organization'], true)
                     ? $settings['schema_publisher_type'] : 'Person';

    if (!empty($item['author_name'])) {
        $authorName = $item['author_name'];
    }

    $effectiveSlug = !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
    if ($type === 'page') {
        $itemUrl = $base . $effectiveSlug . '/';
    } else {
        $itemUrl = cleanUrl($type, $effectiveSlug, null, $item['category'] ?? null);
    }

    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => $schemaType,
        'headline' => $item['title'] ?? '',
        'url'      => $itemUrl,
    ];

    if (!empty($item['date'])) {
        $ts = strtotime($item['date']);
        if ($ts !== false) $ld['datePublished'] = date('Y-m-d', $ts);
    }
    if (!empty($item['last_modified'])) {
        $ts = strtotime($item['last_modified']);
        if ($ts !== false) $ld['dateModified'] = date('Y-m-d', $ts);
    }
    if (!empty($item['image'])) {
        $ld['image'] = $base . ltrim($item['image'], '/');
    }
    if (!empty($item['meta_description'])) {
        $ld['description'] = $item['meta_description'];
    } elseif (!empty($item['summary'])) {
        $ld['description'] = $item['summary'];
    } elseif (!empty($item['description'])) {
        $ld['description'] = $item['description'];
    }
    if ($authorName !== '') {
        $ld['author'] = ['@type' => 'Person', 'name' => $authorName];
    }
    // Publisher is always the site entity, typed per the global setting
    $publisher = ['@type' => $pubType, 'name' => $siteTitle];
    if (!empty($settings['site_logo'])) {
        $publisher['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $base . ltrim($settings['site_logo'], '/'),
        ];
    }
    $ld['publisher'] = $publisher;

    $json = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return '    <script type="application/ld+json">' . $json . '</script>';
}

function render_website_schema(array $settings): string
{
    if (empty($settings['enable_seo'])) return '';

    $base      = getBaseUrl();
    $siteTitle = trim($settings['site_title'] ?? '');
    if ($siteTitle === '') return '';

    $website = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => $siteTitle,
        'url'      => $base,
    ];
    $out = '    <script type="application/ld+json">'
         . json_encode($website, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
         . '</script>';

    if (!empty($settings['site_logo'])) {
        $orgType = in_array($settings['schema_publisher_type'] ?? '', ['Person', 'Organization'], true)
            ? $settings['schema_publisher_type'] : 'Organization';
        $organization = [
            '@context' => 'https://schema.org',
            '@type'    => $orgType,
            'name'     => $siteTitle,
            'url'      => $base,
            'logo'     => $base . ltrim($settings['site_logo'], '/'),
        ];
        $out .= "\n" . '    <script type="application/ld+json">'
              . json_encode($organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
              . '</script>';
    }

    return $out;
}

