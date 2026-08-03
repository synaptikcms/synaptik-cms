<?php
/**
 * Mono Theme — partials/project-card.php
 *
 * Renders a single project as a title-only list item with hover animation.
 * Used by render_project_card() in list pages and the homepage.
 *
 * Available variables:
 * @var array  $project      Full project data array
 * @var string $project_link Fully-qualified URL to the project
 * @var string $card_class   Pre-computed CSS class (ignored here — no card UI)
 */
?>
<li class="title-item">
    <a href="<?php echo $project_link; ?>" class="title-link">
        <span class="title-arrow" aria-hidden="true">→</span>
        <span class="title-name"><?php echo htmlspecialchars($project['title']); ?></span>
        <?php if (!empty($project['date']) && !empty($project['show_date'])): ?>
        <span class="title-date"><?php echo htmlspecialchars(format_date($project['date'])); ?></span>
        <?php endif; ?>
    </a>
</li>
