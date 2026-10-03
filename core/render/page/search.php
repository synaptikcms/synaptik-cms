<?php

function render_search_ui()
{
    ob_start(); ?>
<div id="search-overlay" class="search-overlay">
    <button id="close-search" class="close-btn">&#215;</button>
    <div class="search-bar">
        <div class="search-input-container">
            <input type="text" id="search-input" placeholder="<?php echo hsc(__t('search_placeholder')); ?>">
            <button class="search-clear-btn">&#215;</button>
        </div>
        <div class="search-options">
            <label><input type="checkbox" id="search-in-content"> <?php echo __t('search_in_content'); ?></label>
            <label><input type="checkbox" id="search-articles" checked> <?php echo hsc(sl_type_label('article', true)); ?></label>
            <label><input type="checkbox" id="search-pages"    checked> <?php echo hsc(sl_type_label('page', true)); ?></label>
            <label><input type="checkbox" id="search-projects" checked> <?php echo hsc(sl_type_label('project', true)); ?></label>
        </div>
    </div>
    <div class="search-results" id="search-results">
        <div class="search-loading" style="display: none;"><?php echo __t('search_loading'); ?></div>
        <div class="search-results-content"></div>
    </div>
</div>
<?php
    return ob_get_clean();
}

function render_search_icon()
{
    $settings = loadConfig();
    if (isset($settings['show_search_icon']) && !$settings['show_search_icon']) {
        return '';
    }
    ob_start(); ?>
<li class="search-icon">
    <a href="#" id="search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </a>
</li>
<?php
    return ob_get_clean();
}