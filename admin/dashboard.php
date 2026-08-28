<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

$_sb_htaccessWarn = false;
$_sb_htaccessCache = dirname(__DIR__) . '/cache/htaccess-check.json';
$_sb_htaccessTtl   = 3600;

if (!file_exists($_sb_htaccessCache) || (time() - filemtime($_sb_htaccessCache)) > $_sb_htaccessTtl) {
	$_sb_baseUrl = function_exists('admin_site_url') ? rtrim(admin_site_url(), '/') : '';
	if ($_sb_baseUrl !== '') {
		$_sb_probeUrl = $_sb_baseUrl . '/data/categories.json';
		$_sb_blocked  = false;
		if (function_exists('curl_init')) {
			$_sb_ch = curl_init($_sb_probeUrl);
			curl_setopt_array($_sb_ch, [
				CURLOPT_NOBODY         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 3,
				CURLOPT_SSL_VERIFYPEER => false,
			]);
			curl_exec($_sb_ch);
			$_sb_code = curl_getinfo($_sb_ch, CURLINFO_HTTP_CODE);
			$_sb_blocked = ($_sb_code === 403 || $_sb_code === 404);
		}
		$_sb_htaccessOk = $_sb_blocked;
		if (is_writable(dirname($_sb_htaccessCache))) {
			file_put_contents($_sb_htaccessCache, json_encode(['ok' => $_sb_htaccessOk, 'ts' => time()]));
		}
		$_sb_htaccessWarn = !$_sb_htaccessOk;
	}
} else {
	$_sb_cached = json_decode(file_get_contents($_sb_htaccessCache), true);
	$_sb_htaccessWarn = !($_sb_cached['ok'] ?? true);
}

// ── Media stats — read from cache, rebuild only when stale ───────────────────
$_sb_mediaCache  = dirname(__DIR__) . '/cache/media-stats.json';
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
		if (!$_sb_f->isFile()) continue;
		$_sb_name = $_sb_f->getFilename();
		if ($_sb_name[0] === '.' || strtolower($_sb_name) === '.htaccess') continue;
		$_sb_fileCount++;
		$_sb_fileSize += $_sb_f->getSize();
	}
	// Persist cache only if cache/ is writable
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

// ── Content storage size — bytes on disk per type, cached like media stats ───
$_sb_contentSizeCache    = dirname(__DIR__) . '/cache/content-size-stats.json';
$_sb_contentSize         = ['article' => 0, 'page' => 0, 'project' => 0];
$_sb_contentSizeCacheAge = 300;
$_sb_contentSizeValid = file_exists($_sb_contentSizeCache)
	&& (time() - filemtime($_sb_contentSizeCache)) < $_sb_contentSizeCacheAge;

if ($_sb_contentSizeValid) {
	$_sb_csData = json_decode(file_get_contents($_sb_contentSizeCache), true);
	if (is_array($_sb_csData)) {
		foreach (['article', 'page', 'project'] as $_sb_cst) {
			$_sb_contentSize[$_sb_cst] = (int)($_sb_csData[$_sb_cst] ?? 0);
		}
	}
} else {
	foreach (['article', 'page', 'project'] as $_sb_cst) {
		$_sb_typeDir = sl_data_dir() . '/' . sl_type_dir($_sb_cst);
		if (!is_dir($_sb_typeDir)) continue;
		foreach (glob($_sb_typeDir . '/*.json') ?: [] as $_sb_itemFile) {
			if (basename($_sb_itemFile) === '_index.json') continue;
			$_sb_contentSize[$_sb_cst] += filesize($_sb_itemFile);
		}
	}
	if (is_writable(dirname($_sb_contentSizeCache))) {
		file_put_contents(
			$_sb_contentSizeCache,
			json_encode(array_merge($_sb_contentSize, ['ts' => time()]))
		);
	}
}

// ── Settings (reuse if already loaded by caller) ─────────────────────────────
if (!isset($appSettings)) {
	$appSettings = admin_load_config();
}

// ── Update check ─────────────────────────────────────────────────────────────
$_sb_update = admin_check_for_update();
$_sb_news = admin_fetch_news();

require_once __DIR__ . '/includes/extension-update-functions.php';
$_sb_theme_updates  = admin_check_theme_updates();
$_sb_plugin_updates = admin_check_plugin_updates();
$_sb_ext_update_count = count($_sb_theme_updates) + count($_sb_plugin_updates);

// ── Recent content items ─────────────────────────────────────────────────────
$_sb_authors = [];
foreach (admin_load_users() as $_sb_u) {
	$_sb_authors[$_sb_u['id']] = $_sb_u['display_name'] ?: $_sb_u['username'];
}
$_sb_show_authors = count($_sb_authors) > 1;

$recentItems = [];
foreach (['article', 'page', 'project'] as $_type) {
	foreach ($data[$_type] ?? [] as $_idx => $_item) {
		if (!admin_can_edit_item($_item)) continue;
		$recentItems[] = [
			'type'          => $_type,
			'index'         => $_idx,
			'title'         => $_item['title'],
			'date'          => !empty($_item['date']) ? $_item['date'] : date('Y-m-d'),
			'last_modified' => !empty($_item['last_modified']) ? $_item['last_modified'] : (!empty($_item['date']) ? $_item['date'] : date('Y-m-d')),
			'image'         => $_item['image']         ?? null,
			'author_name'   => $_sb_show_authors ? ($_sb_authors[$_item['author_id'] ?? ''] ?? '') : '',
		];
	}
}
usort($recentItems, fn($a, $b) => strcmp($b['last_modified'], $a['last_modified']));
$recentItems = array_slice($recentItems, 0, 6);

?>

<div class="dashboard-container">
	<div class="dashboard-header">
		<h2><?php _e('dashboard_greeting'); ?>, <span><?php echo hsc(admin_get_display_name()); ?>!</span></h2>
		<div class="quick-actions">
		</div>
	</div>
<?php if ($_sb_htaccessWarn): ?>
	<div class="update-notice" style="border-left-color: var(--danger); background: var(--danger-soft);">
		<strong><?php echo admin_icon('warning'); ?> <?php _e('dashboard_htaccess_warning_title', 'Security warning: sensitive directories may be publicly accessible'); ?></strong>
		<?php _e('dashboard_htaccess_warning_desc', 'The /data/ directory returned a 200 response — your web server may not be applying .htaccess rules (common on nginx). Add manual deny rules for /data/, /bckps/, /private/, and /cache/ or move them above the web root.'); ?>
	</div>
	<?php endif; ?>

	<?php if (!empty($_sb_update)): ?>
	<div class="update-notice">
		<strong><?php echo admin_icon('update'); ?> <?php _e('update_available'); ?></strong>
		<?php echo __t('update_version'); ?> <b><?php echo hsc($_sb_update['version']); ?></b>
		<?php if (!empty($_sb_update['notes'])): ?>
			— <?php echo hsc($_sb_update['notes']); ?>
		<?php endif; ?>
		<?php if (!empty($_sb_update['changelog_url'])): ?>
			<a href="<?php echo hsc($_sb_update['changelog_url']); ?>" target="_blank" rel="noopener"><?php _e('update_changelog_link'); ?></a>
		<?php endif; ?>
		<a href="index.php?action=update"><?php _e('update_apply_btn'); ?> →</a>
	</div>
	<?php endif; ?>

	<?php if ($_sb_ext_update_count > 0): ?>
	<div class="update-notice">
		<strong><?php echo admin_icon('update'); ?> <?php echo sprintf(__t('dashboard_ext_updates_available', '%d theme/plugin update(s) available'), $_sb_ext_update_count); ?></strong>
		<?php if (!empty($_sb_theme_updates)): ?>
			<a href="index.php?action=manage_themes"><?php echo sprintf(__t('dashboard_ext_updates_themes', '%d theme(s)'), count($_sb_theme_updates)); ?> →</a>
		<?php endif; ?>
		<?php if (!empty($_sb_plugin_updates)): ?>
			<a href="index.php?action=plugins"><?php echo sprintf(__t('dashboard_ext_updates_plugins', '%d plugin(s)'), count($_sb_plugin_updates)); ?> →</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	
	<?php if (!empty($_sb_news)): ?>
	<div class="dashboard-news">
		<h3><?php _e('news_feed'); ?></h3>
		<?php foreach ($_sb_news as $_n): ?>
		<div class="news-item news-item--<?php echo hsc($_n['type'] ?? 'info'); ?>">
			<span class="news-date"><?php echo hsc($_n['date'] ?? ''); ?></span>
			<span class="news-message">
				<?php echo nl2br(hsc($_n['message'] ?? '')); ?>
				<?php if (!empty($_n['url'])): ?>
					<a href="<?php echo hsc($_n['url']); ?>" target="_blank" rel="noopener">
						<?php echo hsc($_n['url_label'] ?? 'Learn more'); ?>
					</a>
				<?php endif; ?>
			</span>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="dashboard-widgets">
	<?php pl_do_hook('admin_dashboard'); ?>
	</div>
	<?php if (!pl_is_active('analytics')): ?>
	<div class="update-notice">
		<strong><?php echo admin_icon('chart'); ?> <?php _e('dashboard_analytics_cta_title'); ?></strong>
		<?php _e('dashboard_analytics_cta_desc'); ?>
		<a href="https://synaptikcms.com/plugins/" target="_blank" rel="noopener"><?php _e('dashboard_analytics_cta_btn'); ?> →</a>
	</div>
	<?php endif; ?>
	<?php /* ── Stat cards ────────────────────────────────────── */ ?>
	<div class="dashboard-stats">

		<div class="stat-card<?php echo $contentStats['article'] === 0 ? ' stat-card--empty' : ''; ?>">
			<div class="stat-icon">
				<?php echo admin_icon('article', '', 20); ?>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['article']; ?></div>
				<div class="stat-label"><?php echo hsc(sl_type_label('article', true)); ?></div>
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
				<?php echo admin_icon('page', '', 20); ?>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['page']; ?></div>
				<div class="stat-label"><?php echo hsc(sl_type_label('page', true)); ?></div>
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
				<?php echo admin_icon('project', '', 20); ?>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $contentStats['project']; ?></div>
				<div class="stat-label"><?php echo hsc(sl_type_label('project', true)); ?></div>
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
				<?php echo admin_icon('images', '', 20); ?>
			</div>
			<div class="stat-content">
				<div class="stat-value"><?php echo $_sb_fileCount; ?></div>
				<div class="stat-label"><?php printf(__t('media_files_size'), admin_format_file_size($_sb_fileSize)); ?></div>
			</div>
			<div class="stat-action">
				<a href="file-manager.php"><?php _e('manage'); ?></a>
			</div>
		</div>

	</div>


	<?php /* ── Storage breakdown: media / articles / other, by size on disk — iPhone-storage style ── */ ?>
	<?php
	$_sb_compo = [
		'media'   => ['size' => $_sb_fileSize, 'count' => $_sb_fileCount, 'label_key' => 'dashboard_type_media', 'class' => 'compo-media'],
		'article' => ['size' => $_sb_contentSize['article'], 'count' => $contentStats['article'], 'label' => sl_type_label('article', true), 'class' => 'compo-articles'],
		'other'   => ['size' => $_sb_contentSize['page'] + $_sb_contentSize['project'], 'count' => $contentStats['page'] + $contentStats['project'], 'label_key' => 'dashboard_type_other', 'class' => 'compo-other'],
	];
	$_sb_compoTotalSize = array_sum(array_column($_sb_compo, 'size'));
	?>
	<?php if ($_sb_compoTotalSize > 0): ?>
	<div class="dashboard-panel">
		<h3><?php _e('dashboard_storage_breakdown'); ?></h3>
		<div class="composition-bar">
			<?php foreach ($_sb_compo as $_sb_seg): if ($_sb_seg['size'] <= 0) continue; ?>
			<?php $_sb_pct = round($_sb_seg['size'] / $_sb_compoTotalSize * 100, 2); ?>
			<span class="composition-seg <?php echo $_sb_seg['class']; ?>" style="width:<?php echo $_sb_pct; ?>%" title="<?php echo hsc(admin_format_file_size($_sb_seg['size'])); ?>">
				<?php if ($_sb_pct >= 10): ?>
				<span class="composition-seg-label"><?php echo number_format($_sb_seg['size'] / 1048576, 2); ?> MB</span>
				<?php endif; ?>
			</span>
			<?php endforeach; ?>
		</div>
		<div class="composition-legend">
			<?php foreach ($_sb_compo as $_sb_seg): if ($_sb_seg['count'] <= 0) continue; ?>
			<span class="composition-legend-item">
				<span class="composition-dot <?php echo $_sb_seg['class']; ?>"></span>
				<?php echo hsc($_sb_seg['label'] ?? __t($_sb_seg['label_key'])); ?> — <?php echo $_sb_seg['count']; ?>
			</span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ── Main columns ─────────────────────────────────── */ ?>
	<div class="dashboard-columns">

		<?php /* ── Left column : recent content ─────────────── */ ?>
		<div class="dashboard-column">
			<div class="dashboard-panel">
				<h3><?php _e('recent_posts'); ?></h3>
				<div class="activity-list">
					<?php if (empty($recentItems)): ?>

						<?php /* Empty state with contextual CTA */ ?>
						<div class="dashboard-empty-state">
							<p><?php _e('no_recent_activity'); ?></p>
							<div class="dashboard-empty-cta">
								<a href="index.php?action=add&type=article" class="btn btn-primary"><?php printf(hsc(__t('add_new_type')), hsc(sl_type_label('article'))); ?></a>
								<a href="index.php?action=add&type=page" class="btn btn-outline"><?php printf(hsc(__t('add_new_type')), hsc(sl_type_label('page'))); ?></a>
							</div>
						</div>

					<?php else: ?>

						<?php foreach ($recentItems as $item): ?>
						<div class="activity-item">
							<?php if (!empty($item['image'])): ?>
							<div class="mini-preview">
								<img src="<?php echo '../' . hsc($item['image']); ?>"
								     alt="<?php echo hsc($item['title']); ?>"
								     loading="lazy">
							</div>
							<?php else: ?>
							<div class="activity-icon">
						<?php if ($item['type'] === 'article'): ?>
							<?php echo admin_icon('article', '', 18); ?>
						<?php elseif ($item['type'] === 'page'): ?>
							<?php echo admin_icon('page', '', 18); ?>
						<?php else: ?>
							<?php echo admin_icon('project', '', 18); ?>
						<?php endif; ?>
					</div>
							<?php endif; ?>

							<div class="activity-content">
								<div class="activity-title">
									<a class="edit-list-link"
									   href="index.php?action=edit&type=<?php echo $item['type']; ?>&index=<?php echo $item['index']; ?>">
									   <?php echo hsc($item['title']); ?>
									</a>
								</div>
								<div class="activity-meta">
									<span class="type-badge type-<?php echo hsc($item['type']); ?>"><?php echo hsc(sl_type_label($item['type'])); ?></span>&nbsp;
									<b><?php _e('published'); ?></b>: <?php echo admin_format_date($item['date']); ?>
									<?php if ($item['last_modified'] !== $item['date']): ?>
										• <b><?php _e('updated'); ?></b>: <?php echo admin_format_date($item['last_modified']); ?>
									<?php endif; ?>
									<?php if (!empty($item['author_name'])): ?>
										• <?php echo hsc($item['author_name']); ?>
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

		<?php /* ── Right column ─────────────────────────────── */ ?>
		<div class="dashboard-column">

			<?php /* ── Recent activity (login, saves, updates, etc.) — admin only, same as the Activity Log page itself ── */ ?>
			<?php $_sb_activity = admin_is_admin() ? array_slice(array_reverse(sl_admin_load_activity_log()), 0, 5) : []; ?>
			<?php if (!empty($_sb_activity)): ?>
			<div class="dashboard-panel">
				<h3><?php _e('recent_activity'); ?></h3>
				<div class="activity-list">
					<?php foreach ($_sb_activity as $_sb_entry): ?>
					<div class="activity-item">
						<div class="activity-icon"><?php echo admin_icon('clock', '', 18); ?></div>
						<div class="activity-content">
							<div class="activity-title"><?php echo hsc(admin_activity_action_label($_sb_entry['action'] ?? '')); ?></div>
							<?php $_sb_entryDate = date('Y-m-d H:i', (int)($_sb_entry['ts'] ?? 0)); ?>
							<div class="activity-meta">
								<?php echo hsc($_sb_entry['username'] ?? ''); ?>
								• <?php echo hsc(admin_format_date($_sb_entryDate)); ?>
								<?php $_sb_t = admin_format_time($_sb_entryDate); if ($_sb_t): ?> - <?php echo hsc($_sb_t); ?><?php endif; ?>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="stat-action" style="margin-top:10px;">
					<a href="index.php?action=activity_log"><?php _e('view_all'); ?></a>
				</div>
			</div>
			<?php endif; ?>

		</div>
	</div>
</div>
