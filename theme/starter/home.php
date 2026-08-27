<div class="home-intro">
    <h1><?php echo htmlspecialchars($settings['site_title'] ?? ''); ?></h1>
    <?php if (!empty($settings['site_description'])): ?>
    <p><?php echo htmlspecialchars($settings['site_description']); ?></p>
    <?php endif; ?>
</div>

<?php $recentArticles = array_slice($data['article'] ?? [], 0, 6); ?>
<?php if (!empty($recentArticles)): ?>
<section class="home-section">
    <div class="articles-grid">
        <?php foreach ($recentArticles as $article): ?>
            <?php echo render_article_card($article); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $recentProjects = array_slice($data['project'] ?? [], 0, 6); ?>
<?php if (!empty($recentProjects)): ?>
<section class="home-section">
    <div class="projects-grid">
        <?php foreach ($recentProjects as $project): ?>
            <?php echo render_project_card($project); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (empty($recentArticles) && empty($recentProjects)): ?>
<p class="empty-state"><?php echo __t('no_content_yet') ?: 'No content published yet.'; ?></p>
<?php endif; ?>
