<?php
/**
 * Content List Template — theme/default/content-list.php
 *
 * Renders lists of articles, projects, pages, categories, and tags.
 * Copy this file into your own theme folder to customise it.
 *
 * Available variables
 * -------------------
 * @var string $list_type    Content type being listed: 'article' | 'project' | 'page' | 'category' | 'tag'
 * @var array  $articles     Article items to display (may be empty)
 * @var array  $projects     Project items to display (may be empty)
 * @var array  $items        All found items with '_content_type' key (useful for category/tag pages)
 * @var string $filter_value Category slug or tag slug currently applied (empty for plain list pages)
 * @var array  $data         Full data store (all content types)
 * @var array  $settings     Site settings
 *
 * Render helpers available
 * ------------------------
 * render_article_card($article)  — respects partials/article-card.php if present
 * render_project_card($project)  — respects partials/project-card.php if present
 * cleanUrl(), getBaseUrl(), __t(), sanitizeSlug(), url_slug() — all core helpers
 */
?>

<?php if ($list_type === 'category'): ?>

    <section class="category-content">
        <?php if (empty($articles) && empty($projects)): ?>
            <p><?php echo __t('no_content_in_category'); ?></p>
        <?php else: ?>
            <?php if (!empty($articles)): ?>
                <h2><?php echo __t('articles'); ?></h2>
                <div class="articles-grid">
                    <?php foreach ($articles as $article): ?>
                        <?php echo render_article_card($article); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($projects)): ?>
                <h2><?php echo __t('projects'); ?></h2>
                <div class="projects-grid">
                    <?php foreach ($projects as $project): ?>
                        <?php echo render_project_card($project); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

<?php elseif ($list_type === 'tag'): ?>

    <section class="tag-content">
        <?php if (empty($articles) && empty($projects)): ?>
            <p><?php echo __t('no_content_with_tag'); ?></p>
        <?php else: ?>
            <?php if (!empty($articles)): ?>
                <h2><?php echo __t('articles'); ?></h2>
                <div class="articles-grid">
                    <?php foreach ($articles as $article): ?>
                        <?php echo render_article_card($article); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($projects)): ?>
                <h2><?php echo __t('projects'); ?></h2>
                <div class="projects-grid">
                    <?php foreach ($projects as $project): ?>
                        <?php echo render_project_card($project); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

<?php elseif ($list_type === 'project'): ?>

    <section class="content-list">
        <?php if (empty($projects)): ?>
            <p><?php echo sprintf(__t('no_type_found'), $list_type); ?></p>
        <?php else: ?>
            <section class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <?php echo render_project_card($project); ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </section>

<?php else: /* article, page */ ?>

    <section class="content-list">
        <?php if (empty($articles)): ?>
            <p><?php echo sprintf(__t('no_type_found'), $list_type); ?></p>
        <?php else: ?>
            <section class="articles-grid">
                <?php foreach ($articles as $article): ?>
                    <?php echo render_article_card($article); ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </section>

<?php endif; ?>
