<?php
switch ($list_type) {
    case 'category':
        $eyebrow = __t('category') ?: 'Category';
        // Recover the display name from the first found item
        $displayName = '';
        foreach ($items as $item) {
            if (!empty($item['category'])) { $displayName = $item['category']; break; }
        }
        $heading = htmlspecialchars($displayName ?: urldecode($filter_value));
        break;

    case 'tag':
        $eyebrow = __t('tag') ?: 'Tag';
        $tagName = '';
        foreach ($items as $item) {
            if (!empty($item['tags']) && is_array($item['tags'])) {
                foreach ($item['tags'] as $t) {
                    if (sanitizeSlug($t) === $filter_value) { $tagName = $t; break 2; }
                }
            }
        }
        $heading = '#' . htmlspecialchars($tagName ?: urldecode($filter_value));
        break;

    default:
        $eyebrow = '';
        $heading = htmlspecialchars(ucfirst(__t($list_type . 's') ?: ($list_type . 's')));
}
?>

<div class="content-list-mono">
    <header class="list-header">
        <?php if (!empty($eyebrow)): ?>
        <p class="list-eyebrow"><?php echo $eyebrow; ?></p>
        <?php endif; ?>
        <h1 class="list-heading"><?php echo $heading; ?></h1>
    </header>
    <?php $hasContent = !empty($articles) || !empty($projects); ?>
    <?php if (!$hasContent): ?>
        <p class="empty-state">Nothing here yet.</p>
    <?php endif; ?>
    <?php if (!empty($articles)): ?>
        <?php if ($list_type === 'category' || $list_type === 'tag'): ?>
        <div class="title-category-group">
            <p class="title-group-label"><?php echo __t('articles') ?: 'Articles'; ?></p>
        <?php endif; ?>
        <ol class="title-list" role="list">
            <?php foreach ($articles as $article): ?>
                <?php echo render_article_card($article); ?>
            <?php endforeach; ?>
        </ol>
        <?php if ($list_type === 'category' || $list_type === 'tag'): ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($projects)): ?>
        <?php if ($list_type === 'category' || $list_type === 'tag'): ?>
        <div class="title-category-group">
            <p class="title-group-label"><?php echo __t('projects') ?: 'Projects'; ?></p>
        <?php endif; ?>
        <ol class="title-list" role="list">
            <?php foreach ($projects as $project): ?>
                <?php echo render_project_card($project); ?>
            <?php endforeach; ?>
        </ol>
        <?php if ($list_type === 'category' || $list_type === 'tag'): ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
