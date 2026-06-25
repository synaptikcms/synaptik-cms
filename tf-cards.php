<?php
/**
 * Content Cards & Home Sections — SynaptikCMS
 * Article/project card renderers, home page sections,
 * article summary helper, related items, and custom fields.
 */

// ── Custom fields ─────────────────────────────────────────────────────────────

/**
 * Renders custom fields for a content item respecting schema order and labels.
 *
 * @param array  $item The full content item array.
 * @param string $type Content type: 'article', 'page', or 'project'.
 * @return string HTML string, or empty string when no fields have values.
 */
function render_item_custom_fields(array $item, string $type): string
{
    $values = $item['custom_fields'] ?? [];
    if (empty($values)) return '';

    $schema = loadSettings()['custom_fields_schema'][$type] ?? [];
    if (empty($schema)) return '';

    $rows = '';
    foreach ($schema as $cf) {
        $key = $cf['key'] ?? '';
        $val = $values[$key] ?? '';
        if ($val === '' || $val === null) continue;

        if ($cf['type'] === 'checkbox') {
            $rendered = $val ? '&#x2713;' : '';
            if ($rendered === '') continue;
        } elseif ($cf['type'] === 'url') {
            $rendered = '<a href="' . htmlspecialchars($val) . '" target="_blank" rel="noopener">' . htmlspecialchars($val) . '</a>';
        } else {
            $rendered = nl2br(htmlspecialchars((string)$val));
        }
        $rows .= '<div class="cf-row"><dt>' . htmlspecialchars($cf['label'] ?? $key) . '</dt><dd>' . $rendered . '</dd></div>';
    }

    if ($rows === '') return '';
    return '<div class="custom-fields-block"><dl class="custom-fields">' . $rows . '</dl></div>';
}

/**
 * Returns the value of a single custom field for a content item.
 *
 * @param array  $item    Content item array.
 * @param string $key     Custom field key.
 * @param mixed  $default Returned when the field is absent or empty.
 * @return mixed
 */
function get_custom_field(array $item, string $key, $default = '')
{
    return $item['custom_fields'][$key] ?? $default;
}

/**
 * Renders all custom fields as a plain definition list (no schema ordering).
 *
 * @param array $item Content item array.
 * @return string HTML or empty string.
 */
function render_custom_fields(array $item): string
{
    $fields = $item['custom_fields'] ?? [];
    if (empty($fields)) return '';

    $html = '<dl class="custom-fields">';
    foreach ($fields as $key => $value) {
        if ($value === '' || $value === null) continue;
        $html .= '<dt>' . htmlspecialchars($key) . '</dt>';
        $html .= '<dd>' . nl2br(htmlspecialchars((string)$value)) . '</dd>';
    }
    $html .= '</dl>';
    return $html;
}

// ── Home page sections ────────────────────────────────────────────────────────

/**
 * Renders the articles grid for the homepage with pagination.
 */
function render_home_articles($data, $settings)
{
    if (!$settings['show_articles_on_homepage'] || empty($data['article'])) {
        return '';
    }
    ob_start();

    $currentPage     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $articlesPerPage = $settings['articles_per_page'];

    $articles = $data['article'];
    usort($articles, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $articles = array_filter($articles, fn($a) => !isset($a['show_on_homepage']) || $a['show_on_homepage'] === true);

    $totalArticles = count($articles);
    $totalPages    = (int)ceil($totalArticles / $articlesPerPage);
    $subset        = array_slice(array_values($articles), ($currentPage - 1) * $articlesPerPage, $articlesPerPage); ?>
    <h2><?php echo __t('latest_work'); ?></h2>
    <section class="articles-grid">
    <?php foreach ($subset as $article): echo render_article_card($article); endforeach; ?>
    </section>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
        <span class="current-page"><?php echo $i; ?></span>
        <?php else: ?>
        <a href="<?php echo cleanUrl('home'); ?>?page=<?php echo $i; ?>" class="page-link"><?php echo $i; ?></a>
        <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif;
    return ob_get_clean();
}

/**
 * Renders the projects grid for the homepage.
 */
function render_home_projects($data, $settings)
{
    if (empty($data['project']) || !$settings['show_projects_on_homepage']) {
        return '';
    }
    $perPage  = (int)($settings['projects_per_page'] ?? 3);
    $projects = $data['project'];
    usort($projects, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $projects = array_filter($projects, fn($p) => !isset($p['show_on_homepage']) || $p['show_on_homepage'] === true);
    $featured = array_slice(array_values($projects), 0, $perPage);

    ob_start(); ?>
    <h2><?php echo __t('featured_projects'); ?></h2>
    <section class="projects-grid">
    <?php foreach ($featured as $project): echo render_project_card($project); endforeach; ?>
    </section>
    <div class="view-all">
        <a href="<?php echo cleanUrl('project'); ?>"><?php echo __t('view_all_projects'); ?></a>
    </div>
<?php
    return ob_get_clean();
}

// ── Article card ──────────────────────────────────────────────────────────────

/**
 * Renders a single article card.
 * Delegates to partials/article-card.php in the active theme if it exists.
 */
function render_article_card($article)
{
    $articleSlug  = !empty($article['custom_slug']) ? $article['custom_slug'] : $article['slug'];
    $_itemType    = ($article['_content_type'] ?? null) ?: (array_key_exists('page_template', $article) ? 'page' : 'article');
    $categorySlug = ($_itemType !== 'page' && !empty($article['category'])) ? sanitizeSlug($article['category']) : null;
    $articleLink  = cleanUrl($_itemType, $articleSlug, null, $categorySlug);

    $html = loadThemePartial('article-card', ['article' => $article, 'article_link' => $articleLink]);
    if ($html !== null) {
        return $html;
    }

    ob_start(); ?>
    <article class="article-card">
        <?php if (!empty($article['image'])): ?>
        <div class="article-thumbnail">
            <a href="<?php echo $articleLink; ?>">
                <img src="<?php echo getBaseUrl() . htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
            </a>
        </div>
        <?php endif; ?>
        <h3><a href="<?php echo $articleLink; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h3>
        <?php if (!empty($article['date']) && !empty($article['show_date'])): ?>
        <div class="article-date"><?php
            $settings = loadSettings();
            echo htmlspecialchars(date($settings['date_format'] ?? 'Y-m-d', strtotime($article['date'])));
        ?></div>
        <?php endif; ?>
        <?php if (!empty($article['tags']) && is_array($article['tags'])): ?>
        <div class="article-tags">
            <?php foreach ($article['tags'] as $tag): ?>
            <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>" class="tag-link"><?php echo htmlspecialchars($tag); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php $__summary = get_article_summary($article); if ($__summary !== ''): ?>
        <div class="article-summary"><?php echo $__summary; ?></div>
        <?php endif; ?>
        <a href="<?php echo $articleLink; ?>" class="read-more"><?php echo __t('read_more'); ?></a>
    </article>
<?php
    return ob_get_clean();
}

/**
 * Returns the display summary for an article.
 * Uses the explicit summary field when set, otherwise auto-generates a clean excerpt.
 * Safe to echo directly — already htmlspecialchars'd.
 *
 * @param array $article Content item (index entry or full item).
 * @param int   $length  Maximum excerpt length.
 * @return string HTML-safe summary string, or empty string.
 */
function get_article_summary(array $article, int $length = 150): string
{
    if (!empty($article['summary'])) {
        return htmlspecialchars($article['summary']);
    }
    if (!empty($article['content'])) {
        return htmlspecialchars(_clean_excerpt($article['content'], $length)) . '...';
    }
    // Index mode: load full item on demand for articles without a summary field
    $fileSlug = $article['_file']
        ?? (!empty($article['custom_slug']) ? $article['custom_slug'] : ($article['slug'] ?? ''));
    if ($fileSlug !== '') {
        require_once __DIR__ . '/data-layer.php';
        $full = sl_load_item('article', $fileSlug);
        if ($full !== null && !empty($full['content'])) {
            return htmlspecialchars(_clean_excerpt($full['content'], $length)) . '...';
        }
    }
    return '';
}

// ── Project card ──────────────────────────────────────────────────────────────

/**
 * Renders a single project card.
 * Delegates to partials/project-card.php in the active theme if it exists.
 */
function render_project_card($project)
{
    $projectSlug  = !empty($project['custom_slug']) ? $project['custom_slug'] : $project['slug'];
    $categorySlug = !empty($project['category']) ? sanitizeSlug($project['category']) : null;
    $projectLink  = cleanUrl('project', $projectSlug, null, $categorySlug);
    $cardClass    = !empty($project['image']) ? 'project-card' : 'project-card project-card-no-image';

    $html = loadThemePartial('project-card', ['project' => $project, 'project_link' => $projectLink, 'card_class' => $cardClass]);
    if ($html !== null) {
        return $html;
    }

    ob_start(); ?>
    <article class="<?php echo $cardClass; ?>">
        <?php if (!empty($project['image'])): ?>
        <div class="project-thumbnail">
            <img src="<?php echo getBaseUrl() . htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
        </div>
        <?php endif; ?>
        <div class="project-overlay">
            <h3><?php echo htmlspecialchars($project['title']); ?></h3>
            <?php if (!empty($project['date']) && !empty($project['show_date'])): ?>
            <div class="project-date"><?php
                $settings = loadSettings();
                echo htmlspecialchars(date($settings['date_format'] ?? 'Y-m-d', strtotime($project['date'])));
            ?></div>
            <?php endif; ?>
            <?php if (!empty($project['tags']) && is_array($project['tags'])): ?>
            <div class="project-tags">
                <?php foreach ($project['tags'] as $tag): ?>
                <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>" class="tag-link"><?php echo htmlspecialchars($tag); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($project['description'])): ?>
            <div class="project-excerpt"><?php echo html_entity_decode($project['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
            <?php elseif (!empty($project['meta_description'])): ?>
            <div class="project-seo-desc"><?php echo html_entity_decode($project['meta_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
            <?php endif; ?>
            <a href="<?php echo $projectLink; ?>" class="view-project"><?php echo __t('view_project'); ?></a>
        </div>
    </article>
<?php
    return ob_get_clean();
}

// ── Related items ─────────────────────────────────────────────────────────────

/**
 * Renders a related content section for a content item.
 *
 * Manual mode: uses $item['related_items'] if defined.
 * Auto mode: scores candidates by shared tags (+1 each) and category (+2),
 * returns the top-scoring items up to $limit.
 *
 * Only reads index data — no full item files loaded, keeping it fast.
 * Opt-in: item must have show_related_items === true.
 *
 * @param array $item  Full content item.
 * @param int   $limit Maximum items (auto mode only).
 * @return string HTML <section> or empty string.
 */
function render_related_items(array $item, int $limit = 5): string
{
    if (!($item['show_related_items'] ?? false)) return '';

    require_once __DIR__ . '/data-layer.php';

    $manual        = $item['related_items'] ?? [];
    $resolvedItems = [];

    if (!empty($manual) && is_array($manual)) {
        foreach ($manual as $ref) {
            $type = $ref['type'] ?? '';
            $slug = $ref['slug'] ?? '';
            if ($type === '' || $slug === '') continue;
            $found = sl_find_in_index($type, $slug);
            if ($found === null) continue;
            [$entry] = $found;
            $resolvedItems[] = [
                'type'     => $type,
                'title'    => $entry['title'] ?? ($ref['title'] ?? ''),
                'category' => $entry['category'] ?? '',
                'url'      => cleanUrl($type, sl_effective_slug($entry), null, !empty($entry['category']) ? sanitizeSlug($entry['category']) : null),
            ];
        }
    } else {
        $currentSlug = sl_effective_slug($item);
        $currentTags = array_map('sanitizeSlug', (array)($item['tags'] ?? []));
        $currentCat  = sanitizeSlug($item['category'] ?? '');
        $candidates  = [];

        foreach (['article', 'page', 'project'] as $type) {
            foreach (sl_load_index($type) as $candidate) {
                if (sl_effective_slug($candidate) === $currentSlug) continue;
                $score = 0;
                if ($currentCat !== '' && sanitizeSlug($candidate['category'] ?? '') === $currentCat) $score += 2;
                $score += count(array_intersect($currentTags, array_map('sanitizeSlug', (array)($candidate['tags'] ?? []))));
                if ($score > 0) {
                    $candidates[] = ['type' => $type, 'entry' => $candidate, 'score' => $score];
                }
            }
        }

        usort($candidates, fn($a, $b) => $b['score'] - $a['score']);
        foreach (array_slice($candidates, 0, $limit) as $c) {
            $resolvedItems[] = [
                'type'     => $c['type'],
                'title'    => $c['entry']['title'] ?? '',
                'category' => $c['entry']['category'] ?? '',
                'url'      => cleanUrl($c['type'], sl_effective_slug($c['entry']), null, !empty($c['entry']['category']) ? sanitizeSlug($c['entry']['category']) : null),
            ];
        }
    }

    if (empty($resolvedItems)) return '';

    $html  = '<section class="related-items">';
    $html .= '<h3 class="related-items-title">' . htmlspecialchars(__t('related_content', 'Related Content')) . '</h3>';
    $html .= '<ul class="related-items-list">';
    foreach ($resolvedItems as $r) {
        $badgeLabel = !empty($r['category']) ? $r['category'] : ucfirst($r['type']);
        $html .= '<li class="related-item related-item--' . htmlspecialchars($r['type']) . '">';
        $html .= '<a href="' . htmlspecialchars($r['url']) . '">';
        $html .= '<span class="related-item-type">' . htmlspecialchars($badgeLabel) . '</span>';
        $html .= htmlspecialchars($r['title']) . '</a></li>';
    }
    $html .= '</ul></section>';
    return $html;
}
