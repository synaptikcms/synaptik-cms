<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

require_once 'includes/admin-functions.php';

// ── Backup POST handlers — must run before any output ─────────────────────────
if (isset($_POST['create_backup'])) {
	$data     = admin_load_data();
	$settings = admin_load_settings();
	$backup   = ['timestamp' => date('Y-m-d H:i:s'), 'version' => '1.0', 'data' => $data, 'settings' => $settings];
	$filename = 'synaptik-backup-' . date('Y-m-d-His') . '.json';
	$json     = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($json));
	header('Cache-Control: no-cache, no-store, must-revalidate');
	echo $json;
	exit;
}

if (isset($_POST['save_backup_to_server'])) {
	$data     = admin_load_data();
	$settings = admin_load_settings();
	$backup   = ['timestamp' => date('Y-m-d H:i:s'), 'version' => '1.0', 'data' => $data, 'settings' => $settings];
	$filename = 'synaptik-backup-' . date('Y-m-d-His') . '.json';
	$dir      = '../bckps/';
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	if (file_put_contents($dir . $filename, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
		$_SESSION['message'] = __t('backup_saved_to_server') . ': ' . $filename;
	} else {
		$_SESSION['error'] = __t('backup_save_failed');
	}
	header('Location: index.php?action=tools&tab=backup');
	exit;
}

if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
	if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
		$ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
		if ($ext !== 'json') { $_SESSION['error'] = __t('backup_invalid_json_type'); header('Location: index.php?action=tools&tab=backup'); exit; }
		$content = file_get_contents($_FILES['backup_file']['tmp_name']);
		$bk = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE) { $_SESSION['error'] = __t('backup_invalid_json') . ': ' . json_last_error_msg(); header('Location: index.php?action=tools&tab=backup'); exit; }
		if (!isset($bk['data'], $bk['settings'])) { $_SESSION['error'] = __t('backup_invalid_structure'); header('Location: index.php?action=tools&tab=backup'); exit; }
		// Safety backup before restore
		$safety = ['timestamp' => date('Y-m-d H:i:s'), 'version' => '1.0', 'data' => admin_load_data(), 'settings' => admin_load_settings()];
		$dir = '../bckps/';
		if (!is_dir($dir)) mkdir($dir, 0755, true);
		file_put_contents($dir . 'pre-restore-backup-' . date('Y-m-d-His') . '.json', json_encode($safety, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$r1 = admin_save_data($bk['data']);
		$r2 = file_put_contents('../settings.json', json_encode($bk['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$_SESSION[$r1 !== false && $r2 !== false ? 'message' : 'error'] = __t($r1 !== false && $r2 !== false ? 'restore_success' : 'restore_failed');
	} else {
		$_SESSION['error'] = __t('backup_upload_error');
	}
	header('Location: index.php?action=tools&tab=backup');
	exit;
}

if (isset($_POST['delete_backup'])) {
	$dir      = '../bckps/';
	$filepath = $dir . basename($_POST['backup_file']);
	if (file_exists($filepath) && strpos(realpath($filepath), realpath($dir)) === 0) {
		unlink($filepath);
		$_SESSION['message'] = __t('backup_deleted');
	} else {
		$_SESSION['error'] = __t('backup_not_found');
	}
	header('Location: index.php?action=tools&tab=backup');
	exit;
}

// ── Sitemap generation POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sitemap'])) {
	$data_sm    = admin_load_data();
	$protocol   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	$baseUrl_sm = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
	$sitemapFile = '../sitemap.xml';

	$xml    = new DOMDocument('1.0', 'UTF-8');
	$xml->formatOutput = true;
	$urlset = $xml->createElement('urlset');
	$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
	$xml->appendChild($urlset);

	$addUrl = function(string $loc, string $lastmod, string $priority) use ($xml, $urlset): void {
		$url = $xml->createElement('url');
		$url->appendChild($xml->createElement('loc',      htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
		$url->appendChild($xml->createElement('lastmod',  $lastmod));
		$url->appendChild($xml->createElement('priority', $priority));
		$urlset->appendChild($url);
	};

	$addUrl($baseUrl_sm . '/', date('Y-m-d'), '1.0');
	$priorities = ['page' => '0.9', 'article' => '0.8', 'project' => '0.7'];
	foreach (['article', 'page', 'project'] as $ct) {
		foreach ($data_sm[$ct] ?? [] as $item) {
			if (($item['status'] ?? 'published') !== 'published') continue;
			$slug = $item['slug'] ?? ''; $customSlug = $item['custom_slug'] ?? ''; $category = $item['category'] ?? '';
			if (empty($slug) && empty($customSlug)) continue;
			$addUrl(admin_content_url($ct, $slug, $customSlug, $category), $item['last_modified'] ?? ($item['date'] ?? date('Y-m-d')), $priorities[$ct] ?? '0.8');
		}
	}
	$seenCats = []; $catPrefix = admin_front_url_slug('category');
	foreach (['article', 'project', 'page'] as $ct) {
		foreach ($data_sm[$ct] ?? [] as $item) {
			if (($item['status'] ?? 'published') !== 'published' || empty($item['category'])) continue;
			$catPath = getCategoryPath(sanitizeSlug($item['category']), $data_sm);
			$acc = '';
			foreach (explode('/', $catPath) as $seg) {
				$acc = $acc !== '' ? $acc . '/' . $seg : $seg;
				$url = $baseUrl_sm . '/' . $catPrefix . '/' . $acc . '/';
				if (!isset($seenCats[$url])) { $seenCats[$url] = true; $addUrl($url, date('Y-m-d'), '0.6'); }
			}
		}
	}
	foreach (['article', 'project', 'page'] as $ct) {
		if (!empty($data_sm[$ct])) $addUrl($baseUrl_sm . '/' . admin_front_url_slug($ct . 's') . '/', date('Y-m-d'), '0.5');
	}

	try {
		$xml->save($sitemapFile);
		$msg = __t('sitemap_generated') . ' <a href="' . $baseUrl_sm . '/sitemap.xml" target="_blank">' . __t('view') . '</a>';
		if (!empty($_POST['ping_search_engines'])) {
			$enc = urlencode($baseUrl_sm . '/sitemap.xml');
			@file_get_contents('https://www.google.com/ping?sitemap=' . $enc);
			@file_get_contents('https://www.bing.com/ping?sitemap='   . $enc);
			$msg .= '<br>' . __t('sitemap_pinged');
		}
		$_SESSION['message'] = $msg;
	} catch (Exception $e) {
		$_SESSION['error'] = __t('sitemap_error_generating') . $e->getMessage();
	}
	header('Location: index.php?action=tools&tab=sitemap');
	exit;
}

// ── Alt-text AJAX save ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_alt_save'])) {
	header('Content-Type: application/json');
	$data_alt       = admin_load_data();
	$allowed_types  = ['article', 'page', 'project'];
	$allowed_fields = ['alt_text', 'caption'];
	$post_type   = $_POST['post_type']     ?? '';
	$post_index  = (int)($_POST['post_index']    ?? -1);
	$gallery_idx = (int)($_POST['gallery_index'] ?? -1);
	$image_idx   = (int)($_POST['image_index']   ?? -1);
	$field       = $_POST['field']         ?? '';
	$value       = trim($_POST['value']    ?? '');
	if (!in_array($post_type, $allowed_types, true) || !in_array($field, $allowed_fields, true)
		|| $post_index < 0 || $gallery_idx < 0 || $image_idx < 0) {
		echo json_encode(['ok' => false, 'error' => 'invalid_params']); exit;
	}
	if (!isset($data_alt[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx])) {
		echo json_encode(['ok' => false, 'error' => 'not_found']); exit;
	}
	$value = mb_substr(strip_tags($value), 0, 500);
	$data_alt[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx][$field] = $value;
	$result = admin_save_data($data_alt);
	echo json_encode(['ok' => $result !== false, 'value' => $value]);
	exit;
}

// ── Active tab ────────────────────────────────────────────────────────────────
$activeToolTab = $_GET['tab'] ?? 'backup';

// ── Backup data ───────────────────────────────────────────────────────────────
$backupDir = '../bckps/';
$backups   = [];
if (is_dir($backupDir)) {
	foreach (scandir($backupDir) as $file) {
		if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
			$backups[] = ['name' => $file, 'size' => filesize($backupDir . $file), 'date' => filemtime($backupDir . $file)];
		}
	}
	usort($backups, fn($a, $b) => $b['date'] - $a['date']);
}

// ── Sitemap data ──────────────────────────────────────────────────────────────
$protocol    = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl     = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$sitemapPath = '../sitemap.xml';

// ── Alt-text data ─────────────────────────────────────────────────────────────
$data_main = admin_load_data();
$allImages = [];
foreach (['article', 'page', 'project'] as $type) {
	if (empty($data_main[$type])) continue;
	foreach ($data_main[$type] as $postIndex => $post) {
		$galleries = $post['galleries'] ?? [];
		if (empty($galleries) && !empty($post['gallery']) && is_array($post['gallery'])) {
			$galleries = [['label' => __t('gallery'), 'layout' => $post['gallery_layout'] ?? 'grid', 'images' => $post['gallery']]];
		}
		if (empty($galleries)) continue;
		foreach ($galleries as $gi => $gallery) {
			foreach ($gallery['images'] ?? [] as $ii => $image) {
				$src = $image['src'] ?? '';
				$allImages[] = [
					'post_type'     => $type,
					'post_index'    => $postIndex,
					'post_title'    => $post['title'] ?? '',
					'post_slug'     => !empty($post['custom_slug']) ? $post['custom_slug'] : ($post['slug'] ?? ''),
					'edit_url'      => 'index.php?action=edit&type=' . urlencode($type) . '&index=' . $postIndex,
					'gallery_index' => $gi,
					'gallery_label' => $gallery['label'] ?? (__t('gallery_default_label') . ' ' . ($gi + 1)),
					'image_index'   => $ii,
					'img_url'       => (strpos($src, 'files/') === 0) ? '../' . $src : '../files/' . $src,
					'alt_text'      => $image['alt_text'] ?? '',
					'caption'       => $image['caption']  ?? '',
					'has_alt'       => ($image['alt_text'] ?? '') !== '',
					'has_caption'   => ($image['caption']  ?? '') !== '',
				];
			}
		}
	}
}

$alt_filter   = $_GET['alt_filter'] ?? 'all';
$alt_filtered = array_values(array_filter($allImages, function($img) use ($alt_filter) {
	if ($alt_filter === 'missing_alt')     return !$img['has_alt'];
	if ($alt_filter === 'missing_caption') return !$img['has_caption'];
	if ($alt_filter === 'missing_any')     return !$img['has_alt'] || !$img['has_caption'];
	if ($alt_filter === 'complete')        return $img['has_alt'] && $img['has_caption'];
	return true;
}));
$alt_total    = count($allImages);
$alt_missing  = count(array_filter($allImages, fn($i) => !$i['has_alt']));
$alt_complete = count(array_filter($allImages, fn($i) => $i['has_alt'] && $i['has_caption']));
?>

		<div class="tabs">
			<div class="tab <?php echo $activeToolTab === 'backup'  ? 'active' : ''; ?>" data-tab="backup">💾 &nbsp; <?php _e('backup_export'); ?></div>
			<div class="tab <?php echo $activeToolTab === 'sitemap' ? 'active' : ''; ?>" data-tab="sitemap">🗺️ &nbsp; <?php _e('sitemap_generator'); ?></div>
			<div class="tab <?php echo $activeToolTab === 'alttext' ? 'active' : ''; ?>" data-tab="alttext">📋 &nbsp; <?php _e('alt_assistant_title'); ?></div>
		</div>

		<!-- ══════════════════════ BACKUP TAB ══════════════════════ -->
		<div id="backup-tab" class="tab-content" <?php echo $activeToolTab !== 'backup' ? 'style="display:none;"' : ''; ?>>

			<div class="site-settings-section">
				<h3>💾 <?php _e('save_backup_to_server'); ?></h3>
				<p><?php _e('backup_server_desc'); ?></p>
				<form method="POST" action="index.php?action=tools&tab=backup" style="margin-top:20px;">
					<button type="submit" name="save_backup_to_server" class="button" style="background-color:#27ae60;">
						💾 <?php _e('save_backup_to_server'); ?>
					</button>
				</form>
				<p class="help-text" style="margin-top:10px;"><?php _e('backup_server_help'); ?></p>
			</div>

			<div class="site-settings-section">
				<h3>🔄 <?php _e('restore_from_backup'); ?></h3>
				<div class="warning-box" style="background:rgba(231,76,60,0.08);border-left:4px solid var(--color-danger,#e74c3c);padding:15px;margin-bottom:20px;border-radius:0 4px 4px 0;">
					<strong>⚠️ <?php _e('warning'); ?> :</strong> <?php _e('restore_backup_warning'); ?>
				</div>
				<form method="POST" action="index.php?action=tools&tab=backup" enctype="multipart/form-data" id="restore-backup-form">
					<div class="form-group">
						<label for="backup_file"><?php _e('select_backup_file'); ?> :</label>
						<input type="file" name="backup_file" id="backup_file" accept=".json" required style="padding:10px;width:100%;max-width:450px;">
					</div>
					<button type="button" class="button" style="background-color:#f39c12;" onclick="validateAndConfirmRestore(this);">
						🔄 <?php _e('restore_backup_btn'); ?>
					</button>
				</form>
			</div>

			<div class="site-settings-section">
				<h3>💾 <?php _e('server_backups'); ?></h3>
				<p><?php _e('server_backups_desc'); ?></p>
				<?php if (empty($backups)): ?>
					<div class="message info" style="margin-top:20px;"><?php _e('no_backups'); ?></div>
				<?php else: ?>
					<table style="width:100%;margin-top:20px;">
						<thead>
							<tr>
								<th><?php _e('filename'); ?></th>
								<th><?php _e('date_created'); ?></th>
								<th><?php _e('size'); ?></th>
								<th style="text-align:center;"><?php _e('actions'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($backups as $bk): ?>
							<tr>
								<td style="font-family:monospace;font-size:0.9em;"><?php echo htmlspecialchars($bk['name']); ?></td>
								<td><?php echo date('Y-m-d H:i:s', $bk['date']); ?></td>
								<td><?php echo admin_format_file_size($bk['size']); ?></td>
								<td style="text-align:center;">
									<a href="backup-dl.php?file=<?php echo urlencode($bk['name']); ?>" class="button" style="font-size:0.9em;">⬇️ <?php _e('download'); ?></a>
									<button type="button" class="button danger" style="font-size:0.9em;" onclick="deleteBackupFile('<?php echo htmlspecialchars($bk['name'], ENT_QUOTES); ?>');">🗑️ <?php _e('delete'); ?></button>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<p class="help-text" style="margin-top:15px;"><strong>💡 <?php _e('tip'); ?> :</strong> <?php _e('backup_tip'); ?></p>
			</div>
		</div>

		<!-- ══════════════════════ SITEMAP TAB ══════════════════════ -->
		<div id="sitemap-tab" class="tab-content" <?php echo $activeToolTab !== 'sitemap' ? 'style="display:none;"' : ''; ?>>

			<div class="site-settings-section">
				<div class="form-group">
					<h3><?php _e('sitemap_how_to_use'); ?></h3>
					<ol>
						<li><?php _e('sitemap_step_1'); ?></li>
						<li><?php _e('sitemap_step_2'); ?><pre>Sitemap: <?php echo $baseUrl; ?>/sitemap.xml</pre></li>
						<li><?php _e('sitemap_step_3'); ?></li>
					</ol>
				</div>
				<div class="form-group">
					<h3><?php _e('sitemap_current_status'); ?></h3>
					<?php if (file_exists($sitemapPath)): ?>
						<p><strong><?php _e('sitemap_location'); ?></strong> <a href="<?php echo $baseUrl . '/sitemap.xml'; ?>" target="_blank"><?php echo $baseUrl . '/sitemap.xml'; ?></a></p>
						<p><strong><?php _e('sitemap_last_updated'); ?></strong> <?php echo date('F j, Y, g:i a', filemtime($sitemapPath)); ?></p>
						<p><strong><?php _e('sitemap_file_size'); ?></strong> <?php echo round(filesize($sitemapPath) / 1024, 2); ?> KB</p>
					<?php else: ?>
						<p><?php _e('sitemap_not_generated'); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="site-settings-section">
				<h3><?php echo file_exists($sitemapPath) ? __t('sitemap_update_btn') : __t('generate_sitemap'); ?></h3>
				<p><?php _e('sitemap_desc'); ?></p>
				<form method="post" action="index.php?action=tools&tab=sitemap">
					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" name="ping_search_engines" value="1" checked>
							<?php _e('sitemap_ping_label'); ?>
						</label>
					</div>
					<button type="submit" name="generate_sitemap" class="button">
						<?php echo file_exists($sitemapPath) ? __t('sitemap_update_btn') : __t('generate_sitemap'); ?>
					</button>
				</form>
			</div>
		</div>

		<!-- ══════════════════════ ALT-TEXT TAB ══════════════════════ -->
		<div id="alttext-tab" class="tab-content" <?php echo $activeToolTab !== 'alttext' ? 'style="display:none;"' : 'style="display:block;"'; ?>>

			<div class="seo-stats">
				<a href="index.php?action=tools&tab=alttext&alt_filter=all" class="seo-stat-card <?php echo $alt_filter === 'all' ? 'active' : ''; ?>">
					<div class="seo-stat-num"><?php echo $alt_total; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_total'); ?></div>
				</a>
				<a href="index.php?action=tools&tab=alttext&alt_filter=missing_alt" class="seo-stat-card <?php echo $alt_filter === 'missing_alt' ? 'active' : ''; ?> <?php echo $alt_missing > 0 ? 'warning' : ''; ?>">
					<div class="seo-stat-num"><?php echo $alt_missing; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_missing_alt'); ?></div>
				</a>
				<a href="index.php?action=tools&tab=alttext&alt_filter=complete" class="seo-stat-card <?php echo $alt_filter === 'complete' ? 'active' : ''; ?> <?php echo $alt_complete === $alt_total && $alt_total > 0 ? 'success' : ''; ?>">
					<div class="seo-stat-num"><?php echo $alt_complete; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_complete'); ?></div>
				</a>
			</div>

			<div class="seo-filters">
				<?php
				$alt_filters = [
					'all'         => __t('alt_filter_all'),
					'missing_any' => __t('alt_filter_missing_any'),
					'missing_alt' => __t('alt_filter_missing_alt'),
					'complete'    => __t('alt_filter_complete'),
				];
				foreach ($alt_filters as $key => $label):
				?>
				<a href="index.php?action=tools&tab=alttext&alt_filter=<?php echo $key; ?>" class="seo-filter-btn <?php echo $alt_filter === $key ? 'active' : ''; ?>">
					<?php echo htmlspecialchars($label); ?>
				</a>
				<?php endforeach; ?>
			</div>

			<?php if (empty($alt_filtered)): ?>
				<div class="empty-state" style="text-align:center;padding:60px 20px;color:var(--text-muted);">
					<?php _e('alt_no_items'); ?>
				</div>
			<?php else: ?>
				<div class="alt-grid">
					<?php foreach ($alt_filtered as $img): ?>
					<div class="alt-card <?php echo !$img['has_alt'] ? 'missing-alt' : ''; ?>"
						 data-post-type="<?php echo htmlspecialchars($img['post_type']); ?>"
						 data-post-index="<?php echo (int)$img['post_index']; ?>"
						 data-gallery-index="<?php echo (int)$img['gallery_index']; ?>"
						 data-image-index="<?php echo (int)$img['image_index']; ?>">
						<div class="alt-card-thumb">
							<img src="<?php echo htmlspecialchars($img['img_url']); ?>" alt="<?php echo htmlspecialchars($img['alt_text']); ?>" loading="lazy">
						</div>
						<div class="alt-card-body">
							<div class="alt-card-context">
								<span class="post-title"><?php echo htmlspecialchars($img['post_title']); ?></span>
								<span class="type-badge type-<?php echo $img['post_type']; ?>"><?php echo __t('type_' . $img['post_type']); ?></span>
								<span class="gallery-name">— <?php echo htmlspecialchars($img['gallery_label']); ?></span>
							</div>
							<div class="alt-field-group">
								<label class="alt-field-label"><?php _e('alt_text'); ?></label>
								<input type="text" class="seo-field alt-editable" data-field="alt_text" data-max="250"
								value="<?php echo htmlspecialchars($img['alt_text']); ?>"
								placeholder="<?php _e('alt_text_placeholder'); ?>"
								maxlength="250">
								<span class="save-indicator"></span>
							</div>
							<div class="alt-field-group">
								<label class="alt-field-label"><?php _e('caption'); ?></label>
								<textarea class="seo-field alt-editable" data-field="caption" data-max="500"
								placeholder="<?php _e('caption_placeholder'); ?>"
								maxlength="500" rows="2"><?php echo htmlspecialchars($img['caption']); ?></textarea>
								<span class="save-indicator"></span>
							</div>
						</div>
						<div class="alt-card-footer">
							<span class="slug-cell">/<?php echo htmlspecialchars($img['post_slug']); ?></span>
							<a href="<?php echo $img['edit_url']; ?>" class="edit-link">✏️ <?php _e('edit'); ?></a>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<style>
			/* Alt-text grid — scoped to tools page only */
			.alt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:20px; margin-top:8px; }
			.alt-card { background:var(--bg-white); border:1px solid var(--border-light); border-radius:var(--radius-md); overflow:hidden; box-shadow:var(--shadow-sm); display:flex; flex-direction:column; }
			.alt-card:hover { border-color:var(--primary); box-shadow:var(--shadow-md); }
			.alt-card-thumb { width:100%; height:100px; overflow:hidden; background:var(--bg-light); }
			.alt-card-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
			.alt-card-body { padding:14px 16px; display:flex; flex-direction:column; gap:10px; flex:1; }
			.alt-card-context { font-size:0.8em; color:var(--text-muted); display:flex; align-items:center; gap:6px; flex-wrap:wrap; padding-bottom:8px; }
			.alt-card-context .post-title { font-weight:600; font-size:1.2em; color:var(--primary); }
			.alt-card.missing-alt .alt-card-context .post-title { color:var(--danger); }
			.alt-field-group { display:flex; flex-direction:column; gap:3px; }
			.alt-field-label { font-size:0.8em; margin-top:0; margin-bottom: 2px; color:var(--text-muted); }
			.alt-card-footer { padding:0 16px 10px; display:flex; align-items:center; justify-content:space-between; font-size:0.9em; }
			</style>
		</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {

		// ── Tab switching ─────────────────────────────────────────────────────
		document.querySelectorAll('.tabs .tab').forEach(function(tab) {
			tab.addEventListener('click', function() {
				document.querySelectorAll('.tab-content').forEach(function(c) { c.style.display = 'none'; });
				document.querySelectorAll('.tabs .tab').forEach(function(t) { t.classList.remove('active'); });
				const id = this.getAttribute('data-tab');
				const el = document.getElementById(id + '-tab');
				if (el) el.style.display = (id === 'alttext') ? 'block' : 'grid';
				this.classList.add('active');
				const url = new URL(window.location.href);
				url.searchParams.set('tab', id);
				history.replaceState(null, '', url);
			});
		});

		// ── Backup helpers ────────────────────────────────────────────────────
		window.validateAndConfirmRestore = function(button) {
			const fileInput = document.getElementById('backup_file');
			if (!fileInput.files || fileInput.files.length === 0) {
				showModal(t('backup_no_file_selected'), t('backup_no_file_title'), { confirmText: 'OK', danger: false });
				return;
			}
			if (!fileInput.files[0].name.toLowerCase().endsWith('.json')) {
				showModal(t('backup_invalid_file'), t('backup_invalid_file_title'), { confirmText: 'OK', danger: true });
				return;
			}
			const form = button.closest('form');
			showModal(t('backup_restore_confirm'), t('backup_restore_confirm_title'), {
				showCancel: true, confirmText: t('restore_backup_btn'), cancelText: t('cancel'), danger: true,
				onConfirm: function() {
					const inp = document.createElement('input');
					inp.type = 'hidden'; inp.name = 'restore_backup'; inp.value = '1';
					form.appendChild(inp); form.submit();
				}
			});
		};

		window.deleteBackupFile = function(filename) {
			showModal(t('backup_delete_confirm'), t('backup_delete_confirm_title'), {
				showCancel: true, confirmText: t('delete'), cancelText: t('cancel'), danger: true,
				onConfirm: function() {
					const form = document.createElement('form');
					form.method = 'POST'; form.action = 'index.php?action=tools&tab=backup';
					const f1 = document.createElement('input'); f1.type='hidden'; f1.name='backup_file'; f1.value=filename;
					const f2 = document.createElement('input'); f2.type='hidden'; f2.name='delete_backup'; f2.value='1';
					form.appendChild(f1); form.appendChild(f2);
					document.body.appendChild(form); form.submit();
				}
			});
		};

		// ── Alt-text inline AJAX save ─────────────────────────────────────────
		var saveTimers = new WeakMap();

		function showSaveIndicator(field, ok) {
			var ind = field.parentNode.querySelector('.save-indicator');
			if (!ind) return;
			ind.textContent = ok ? '✓ ' + (window.t ? t('saved') : 'Saved') : '✗ ' + (window.t ? t('save_error') : 'Error');
			ind.className   = 'save-indicator visible' + (ok ? '' : ' error');
			clearTimeout(ind._t);
			ind._t = setTimeout(function() { ind.classList.remove('visible'); }, 2000);
		}

		function saveAltField(field) {
			var card = field.closest('.alt-card');
			var body = new URLSearchParams({
				ajax_alt_save : '1',
				post_type     : card.dataset.postType,
				post_index    : card.dataset.postIndex,
				gallery_index : card.dataset.galleryIndex,
				image_index   : card.dataset.imageIndex,
				field         : field.dataset.field,
				value         : field.value
			});
			fetch('index.php?action=tools', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
			.then(function(r) { return r.json(); })
			.then(function(d) { showSaveIndicator(field, d.ok); })
			.catch(function()  { showSaveIndicator(field, false); });
		}

		document.querySelectorAll('.alt-editable').forEach(function(field) {
			field.addEventListener('input', function() {
				clearTimeout(saveTimers.get(field));
				saveTimers.set(field, setTimeout(function() { saveAltField(field); }, 800));
			});
			field.addEventListener('blur', function() {
				clearTimeout(saveTimers.get(field));
				saveAltField(field);
			});
		});
	});
	</script>
	</main>
