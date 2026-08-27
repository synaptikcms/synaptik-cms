<?php
$heading = ucfirst(__t($list_type . 's') ?: ($list_type . 's'));
?>
<div class="content-list">
    <h1 class="list-heading"><?php echo htmlspecialchars($heading); ?></h1>

    <?php if (empty($articles) && empty($projects)): ?>
    <p class="empty-state"><?php echo __t('no_content_yet') ?: 'Nothing here yet.'; ?></p>
    <?php endif; ?>

    <?php if (!empty($articles)): ?>
    <div class="articles-grid">
        <?php foreach ($articles as $article): ?>
            <?php echo render_article_card($article); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($projects)): ?>
    <div class="projects-grid">
        <?php foreach ($projects as $project): ?>
            <?php echo render_project_card($project); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
