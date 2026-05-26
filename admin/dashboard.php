<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// ── Media stats — read from cache, rebuild only when stale ───────────────────
$_sb_mediaCache  = dirname(__DIR__) . '/private/media-stats.json';
$_sb_filesDir    = dirname(__DIR__) . '/files';
$_sb_fileCount   = 0;
$_sb_fileSize    = 0;
$_sb_cacheMaxAge = 300; // Rebuild cache after 5 minutes

$_sb_cacheValid = file_exists($_sb_mediaCache)
	&& (time() - filemtime($_sb_mediaCache)) < $_sb_cacheMaxAge;

if ($_sb_cacheValid) {
	$_sb_cacheData = json_decode(file_get_contents($_sb_mediaCache), true);
	if (is_array($_sb_cacheData)) {
		$_sb_fileCount = (int)($_sb_cacheData['count'] ?? 0);
		$_sb_fileSize  = (int)($_sb_cacheData['size']  ?? 0);
	}
} elseif (is_dir($_sb_filesDir)) {
	$_sb_iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($_sb_filesDir, RecursiveDirectoryIterator::SKIP_DOTS)
	);
	foreach ($_sb_iter as $_sb_f) {
		if ($_sb_f->isFile()) {
			$_sb_fileCount++;
			$_sb_fileSize += $_sb_f->getSize();
		}
	}
	// Persist cache only if bckps/ is writable
	if (is_writable(dirname($_sb_mediaCache))) {
		file_put_contents(
			$_sb_mediaCache,
			json_encode(['count' => $_sb_fileCount, 'size' => $_sb_fileSize, 'ts' => time()])
		);
	}
}

// ── Content stats ────────────────────────────────────────────────────────────
$contentStats = [
	'article' => count($data['article'] ?? []),
	'page'    => count($data['page']    ?? []),
	'project' => count($data['project'] ?? []),
];

// ── Draft count ──────────────────────────────────────────────────────────────
$_sb_draftsDir  = __DIR__ . '/drafts';
$_sb_draftCount = 0;
if (is_dir($_sb_draftsDir)) {
	$_sb_draftFiles = glob($_sb_draftsDir . '/*.json');
	$_sb_draftCount = $_sb_draftFiles ? count($_sb_draftFiles) : 0;
}

// ── Settings (reuse if already loaded by caller) ─────────────────────────────
if (!isset($appSettings)) {
	$appSettings = admin_load_settings();
}

// ── Update check ─────────────────────────────────────────────────────────────
$_sb_update = admin_check_for_update();
$_sb_news = admin_fetch_news();

// ── Recent content items ─────────────────────────────────────────────────────
$recentItems = [];
foreach (['article', 'page', 'project'] as $_type) {
	foreach ($data[$_type] ?? [] as $_idx => $_item) {
		$recentItems[] = [
			'type'          => $_type,
			'index'         => $_idx,
			'title'         => $_item['title'],
			'date'          => $_item['date']          ?? date('Y-m-d'),
			'last_modified' => $_item['last_modified'] ?? $_item['date'] ?? date('Y-m-d'),
			'image'         => $_item['image']         ?? null,
		];
	}
}
usort($recentItems, fn($a, $b) => strcmp($b['last_modified'], $a['last_modified']));
$recentItems = array_slice($recentItems, 0, 9);

// ── Total content count (used for empty-state detection) ─────────────────────
$_totalContent = array_sum($contentStats);

/**
 * Return an inline Lucide SVG icon sized for the dashboard quick-task grid.
 */
function dash_icon(string $name): string {
	$base = 'width="22" height="22" viewBox="0 0 24 24" fill="none" '
		. 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';

	$paths = [
		'compress'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
		'css'        => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
		'menu'       => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
		'settings'   => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="2" fill="currentColor" stroke="none"/>',
		'article'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
		'page'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
		'project'    => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
	];

	$inner = $paths[$name] ?? '';
	if ($inner === '') return '';
	return '<svg class="task-svg-icon" aria-hidden="true" ' . $base . '>' . $inner . '</svg>';
}
?>

<div class="dashboard-container">

	<?php /* ── Header ─────────────────────────────────────────── */ ?>
	<div class="dashboard-header">
		<h2><?php _e('dashboard_greeting'); ?></h2>
		<div class="quick-actions">
			<div class="dropdown">
				<button class="button dropdown-toggle">
					<?php _e('quick_add'); ?> <span class="caret">▼</span>
				</button>
				<ul class="dropdown-menu">
					<li><a href="index.php?action=add&type=article"><?php _e('new_article'); ?></a></li>
					<li><a href="index.php?action=add&type=page"><?php _e('new_page'); ?></a></li>
					<li><a href="index.php?action=add&type=project"><?php _e('new_project'); ?></a></li>
				</ul>
			</div>
		</div>
	</div>
<?php if (!empty($_sb_update)): ?>
	<div class="update-notice">
		<strong>⬆ <?php _e('update_available'); ?></strong>
		<?php echo __t('update_version'); ?> <b><?php echo htmlspecialchars($_sb_update['version']); ?></b>
		<?php if (!empty($_sb_update['notes'])): ?>
			— <?php echo htmlspecialchars($_sb_update['notes']); ?>
		<?php endif; ?>
		<?php if (!empty($_sb_update['download_url'])): ?>
			<a href="<?php echo htmlspecialchars($_sb_update['download_url']); ?>" target="_blank" rel="noopener">
				<?php _e('download'); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	
	<?php if (!empty($_sb_news)): ?>
	<div class="dashboard-news">
		<h3><?php _e('news_feed'); ?></h3>
		<?php foreach ($_sb_news as $_n): ?>
		<div class="news-item news-item--<?php echo htmlspecialchars($_n['type'] ?? 'info'); ?>">
			<span class="news-date"><?php echo htmlspecialchars($_n['date'] ?? ''); ?></span>
			<span class="news-message">
				<?php echo htmlspecialchars($_n['message'] ?? ''); ?>
				<?php if (!empty($_n['url'])): ?>
					<a href="<?php echo htmlspecialchars($_n['url']); ?>" target="_blank" rel="noopener">
						<?php echo htmlspecialchars($_n['url_label'] ?? 'Learn more'); ?>
					</a>
				<?php endif; ?>
			</span>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php /* ── Stat cards ────────────────────────────────────── */ ?>
	<div class="dashboard-stats">

		<div class="stat-card<?php echo $contentStats['article'] === 0 ? ' stat-card--empty' : ''; ?>">
			<div class="stat-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['article']; ?></div>
				<div class="stat-label"><?php _e('articles'); ?></div>
			</div>
			<div class="stat-action">
				<?php if ($contentStats['article'] === 0): ?>
					<a href="index.php?action=add&type=article"><?php _e('add_new'); ?></a>
				<?php else: ?>
					<a href="index.php?type=article"><?php _e('view_all'); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="stat-card<?php echo $contentStats['page'] === 0 ? ' stat-card--empty' : ''; ?>">
			<div class="stat-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['page']; ?></div>
				<div class="stat-label"><?php _e('pages'); ?></div>
			</div>
			<div class="stat-action">
				<?php if ($contentStats['page'] === 0): ?>
					<a href="index.php?action=add&type=page"><?php _e('add_new'); ?></a>
				<?php else: ?>
					<a href="index.php?type=page"><?php _e('view_all'); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="stat-card<?php echo $contentStats['project'] === 0 ? ' stat-card--empty' : ''; ?>">
			<div class="stat-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['project']; ?></div>
				<div class="stat-label"><?php _e('projects'); ?></div>
			</div>
			<div class="stat-action">
				<?php if ($contentStats['project'] === 0): ?>
					<a href="index.php?action=add&type=project"><?php _e('add_new'); ?></a>
				<?php else: ?>
					<a href="index.php?type=project"><?php _e('view_all'); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="stat-card">
			<div class="stat-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $_sb_fileCount; ?></div>
				<div class="stat-label"><?php printf(__t('media_files_size'), admin_format_file_size($_sb_fileSize)); ?></div>
			</div>
			<div class="stat-action">
				<a href="file-manager.php"><?php _e('manage'); ?></a>
			</div>
		</div>

		<?php if ($_sb_draftCount > 0): ?>
		<div class="stat-card stat-card--drafts">
			<div class="stat-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $_sb_draftCount; ?></div>
				<div class="stat-label"><?php _e('drafts'); ?></div>
			</div>
			<div class="stat-action">
				<a href="index.php?action=drafts"><?php _e('view_all'); ?></a>
			</div>
		</div>
		<?php endif; ?>

	</div>

	<?php /* ── Main columns ─────────────────────────────────── */ ?>
	<div class="dashboard-columns">

		<?php /* ── Left column : recent content ─────────────── */ ?>
		<div class="dashboard-column">
			<div class="dashboard-panel">
				<h3><?php _e('recent_activity'); ?></h3>
				<div class="activity-list">
					<?php if (empty($recentItems)): ?>

						<?php /* Empty state with contextual CTA */ ?>
						<div class="dashboard-empty-state">
							<p><?php _e('no_recent_activity'); ?></p>
							<div class="dashboard-empty-cta">
								<a href="index.php?action=add&type=article" class="button"><?php _e('new_article'); ?></a>
								<a href="index.php?action=add&type=page" class="button button--secondary"><?php _e('new_page'); ?></a>
							</div>
						</div>

					<?php else: ?>

						<?php foreach ($recentItems as $item): ?>
						<div class="activity-item">
							<?php if (!empty($item['image'])): ?>
							<div class="mini-preview">
								<img src="<?php echo '../' . htmlspecialchars($item['image']); ?>"
								     alt="<?php echo htmlspecialchars($item['title']); ?>"
								     loading="lazy">
							</div>
							<?php else: ?>
							<div class="activity-icon <?php echo $item['type']; ?>-icon"></div>
							<?php endif; ?>

							<div class="activity-content">
								<div class="activity-title">
									<a class="edit-list-link"
									   href="index.php?action=edit&type=<?php echo $item['type']; ?>&index=<?php echo $item['index']; ?>">
										<?php echo htmlspecialchars($item['title']); ?>
									</a>
								</div>
								<div class="activity-meta">
									<span class="type-badge type-<?php echo $item['type']; ?>"><?php echo __t('type_' . $item['type']); ?></span>&nbsp;
									<b><?php _e('published'); ?></b>: <?php echo admin_format_date($item['date']); ?>
									<?php if ($item['last_modified'] !== $item['date']): ?>
										• <b><?php _e('updated'); ?></b>: <?php echo admin_format_date($item['last_modified']); ?>
									<?php endif; ?>
								</div>
							</div>

							<div class="activity-actions">
								<a class="table-btn edit-btn small"
								   href="index.php?action=edit&type=<?php echo $item['type']; ?>&index=<?php echo $item['index']; ?>">
									<?php _e('edit'); ?>
								</a>
							</div>
						</div>
						<?php endforeach; ?>

					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php /* ── Right column : quick tasks ───────────────── */ ?>
		<div class="dashboard-column">
			<div class="dashboard-panel">
				<h3><?php _e('quick_tasks'); ?></h3>
				<div class="task-buttons">
					<a href="batch-optimize.php" class="task-button">
						<?php echo dash_icon('compress'); ?>
						<div class="task-label"><?php _e('optimize_images'); ?></div>
					</a>
					<a href="css-editor.php" class="task-button">
						<?php echo dash_icon('css'); ?>
						<div class="task-label"><?php _e('css_theme_editor'); ?></div>
					</a>
					<a href="index.php?action=menu_builder" class="task-button">
						<?php echo dash_icon('menu'); ?>
						<div class="task-label"><?php _e('edit_menu'); ?></div>
					</a>
					<a href="index.php?action=settings" class="task-button">
						<?php echo dash_icon('settings'); ?>
						<div class="task-label"><?php _e('settings'); ?></div>
					</a>
				</div>
			</div>
		<div class="dashboard-panel">
			<h3><?php _e('system_information'); ?></h3>
			<div class="system-info">
				<div class="info-item">
					<div class="info-label"><strong><?php _e('php_version'); ?>:</strong></div>
					<div class="info-value"><?php echo phpversion(); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('gd_library'); ?>:</strong></div>
					<div class="info-value"><?php echo function_exists('gd_info') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('webp_support'); ?>:</strong></div>
					<div class="info-value"><?php echo function_exists('imagewebp') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('active_theme'); ?>:</strong></div>
					<div class="info-value"><strong><?php echo ucfirst($appSettings['active_theme'] ?? 'default'); ?></strong></div>
				</div>
			</div>
		</div>

	</div>
</div>
