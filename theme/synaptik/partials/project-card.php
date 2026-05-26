<?php
/**
 * Project Card Partial — theme/default/partials/project-card.php
 *
 * Renders a single project card used in list pages, category pages,
 * tag pages, and the homepage projects grid.
 *
 * Copy this file into your own theme's partials/ folder and edit freely.
 * The CMS will automatically use your version instead of the built-in default.
 *
 * Available variables
 * -------------------
 * @var array  $project      Full project data array (title, image, date, tags, category, description…)
 * @var string $project_link Fully-qualified URL to the project
 * @var string $card_class   Pre-computed CSS class string ('project-card' or 'project-card project-card-no-image')
 *
 * Core helpers available
 * ----------------------
 * getBaseUrl()          — base URL with trailing slash
 * cleanUrl()            — generate any CMS URL
 * url_slug($type)       — localised URL prefix for a content type
 * sanitizeSlug($string) — convert a string to a URL-safe slug
 * format_date($date)    — format a date string using the site's date_format setting
 * __t($key)             — translate a string key using the active locale
 * loadSettings()        — return the site settings array (use sparingly)
 */
?>
<article class="<?php echo $card_class; ?>">

    <?php if (!empty($project['image'])): ?>
    <div class="project-thumbnail">
        <img src="<?php echo getBaseUrl() . htmlspecialchars($project['image']); ?>"
             alt="<?php echo htmlspecialchars($project['title']); ?>">
    </div>
    <?php endif; ?>

    <div class="project-overlay">

        <h3><?php echo htmlspecialchars($project['title']); ?></h3>

        <?php if (!empty($project['date']) && !empty($project['show_date'])): ?>
        <div class="project-date">
            <?php echo htmlspecialchars(format_date($project['date'])); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($project['category'])): ?>
        <!-- <div class="project-category">
            <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($project['category']) . '/'; ?>"
               class="category-badge">
                <?php echo htmlspecialchars($project['category']); ?>
            </a>
        </div> -->
        <?php endif; ?>

        <?php if (!empty($project['tags']) && is_array($project['tags'])): ?>
        <div class="project-tags">
            <?php foreach ($project['tags'] as $tag): ?>
            <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/'; ?>"
               class="tag-link"><?php echo htmlspecialchars($tag); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($project['description'])): ?>
        <div class="project-excerpt">
            <?php echo html_entity_decode($project['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
        </div>
        <?php elseif (!empty($project['meta_description'])): ?>
        <div class="project-seo-desc">
            <?php echo html_entity_decode($project['meta_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <a href="<?php echo $project_link; ?>" class="view-project"><?php echo __t('view_project'); ?></a>

    </div>

</article>
