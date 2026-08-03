<?php
$articlesLimit = (int)($settings['articles_per_page'] ?? 5);
$projectsLimit = (int)($settings['projects_per_page'] ?? 5);

$recentArticles = [];
if (!empty($data['article']) && !empty($settings['show_articles_on_homepage'])) {
    $articles = $data['article'];

    // Sort by date descending
    usort($articles, function ($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });

    // Respect show_on_homepage flag if set
    $articles = array_filter($articles, function ($a) {
        return !isset($a['show_on_homepage']) || $a['show_on_homepage'] === true;
    });

    $recentArticles = array_slice(array_values($articles), 0, $articlesLimit);
}

$recentProjects = [];
if (!empty($data['project']) && !empty($settings['show_projects_on_homepage'])) {
    $projects = $data['project'];

    usort($projects, function ($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });

    $projects = array_filter($projects, function ($p) {
        return !isset($p['show_on_homepage']) || $p['show_on_homepage'] === true;
    });

    $recentProjects = array_slice(array_values($projects), 0, $projectsLimit);
}
?>
        <div class="home-intro">
            <h1><?php echo htmlspecialchars($settings['site_title'] ?? 'Hello.'); ?></h1>
            <?php if (!empty($settings['site_description'])): ?>
            <p><?php echo htmlspecialchars($settings['site_description']); ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($recentArticles)): ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-label"><?php echo __t('articles'); ?></span>
                <a href="<?php echo cleanUrl('article'); ?>" class="home-section-all"><?php echo __t('view_all') ?: 'view all'; ?> →</a>
            </div>
            <ol class="title-list" role="list">
                <?php foreach ($recentArticles as $article): ?>
                    <?php echo render_article_card($article); ?>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
        <?php if (!empty($recentProjects)): ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-label"><?php echo __t('projects'); ?></span>
                <a href="<?php echo cleanUrl('project'); ?>" class="home-section-all"><?php echo __t('view_all') ?: 'view all'; ?> →</a>
            </div>
            <ol class="title-list" role="list">
                <?php foreach ($recentProjects as $project): ?>
                    <?php echo render_project_card($project); ?>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
        <?php if (empty($recentArticles) && empty($recentProjects)): ?>
        <p class="empty-state">No content published yet.</p>
        <?php endif; ?>
