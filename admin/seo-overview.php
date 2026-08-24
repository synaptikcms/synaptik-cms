<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';
if (!admin_is_logged_in()) {
	header('Location: auth.php');
	exit;
}
if (!admin_can_manage_all_content()) {
	http_response_code(403);
	exit('Access denied.');
}

$data        = admin_load_data();
$appSettings = admin_load_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_seo_save'])) {
	header('Content-Type: application/json');

	$_csrfToken = $_POST['csrf_token'] ?? (getallheaders()['X-CSRF-Token'] ?? '');
	if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_csrfToken)) {
		echo json_encode(['ok' => false, 'error' => 'invalid_token']);
		exit;
	}
	$type  = $_POST['type']  ?? '';
	$index = (int)($_POST['index'] ?? -1);
	$field = $_POST['field'] ?? '';

	$allowed_types  = ['article', 'page', 'project'];
	$allowed_fields = ['meta_title', 'meta_description', 'meta_keywords'];

	if (!in_array($type, $allowed_types) || !in_array($field, $allowed_fields) || $index < 0) {
		echo json_encode(['ok' => false, 'error' => 'invalid_params']);
		exit;
	}

	if (!isset($data[$type][$index])) {
		echo json_encode(['ok' => false, 'error' => 'not_found']);
		exit;
	}

	$value = trim($_POST['value'] ?? '');
	$maxlen = $field === 'meta_title' ? 60 : ($field === 'meta_description' ? 160 : 255);
	$value = mb_substr($value, 0, $maxlen);

	$data[$type][$index][$field] = $value;
	$result = admin_save_data($data);

	echo json_encode(['ok' => $result !== false, 'value' => $value]);
	exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

$contentTypes = ['article', 'page', 'project'];

// Regrouper tous les items avec leur type et index pour affichage
$allItems = [];
foreach ($contentTypes as $type) {
	if (!empty($data[$type])) {
		foreach ($data[$type] as $index => $item) {
			$slug = !empty($item['custom_slug']) ? $item['custom_slug'] : ($item['slug'] ?? '');
			$allItems[] = [
				'type'             => $type,
				'index'            => $index,
				'title'            => $item['title'] ?? '',
				'slug'             => $slug,
				'meta_title'       => $item['meta_title'] ?? '',
				'meta_description' => $item['meta_description'] ?? '',
				'meta_keywords'    => $item['meta_keywords'] ?? '',
				'og_image'         => $item['og_image'] ?? '',
				'published'        => $item['published'] ?? false,
				'edit_url'         => 'index.php?action=edit&type=' . urlencode($type) . '&index=' . $index,
			];
		}
	}
}

// Stats globales
$total        = count($allItems);
$missing_title = 0;
$missing_desc  = 0;
$missing_both  = 0;
foreach ($allItems as $item) {
	$no_title = empty($item['meta_title']);
	$no_desc  = empty($item['meta_description']);
	if ($no_title) $missing_title++;
	if ($no_desc)  $missing_desc++;
	if ($no_title && $no_desc) $missing_both++;
}

// Filtre actif (URL param)
$filter = $_GET['filter'] ?? 'all';

$filtered = array_filter($allItems, function($item) use ($filter) {
	if ($filter === 'missing_title') return empty($item['meta_title']);
	if ($filter === 'missing_desc')  return empty($item['meta_description']);
	if ($filter === 'missing_any')   return empty($item['meta_title']) || empty($item['meta_description']);
	if ($filter === 'complete')      return !empty($item['meta_title']) && !empty($item['meta_description']);
	return true; // 'all'
});

// Sidebar
$draftsDir  = sl_admin_drafts_dir();
$draftCount = 0;
if (file_exists($draftsDir)) {
	$draftCount = count(glob($draftsDir . '/*.json'));
}

$message = $_SESSION['message'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['message'], $_SESSION['error']);

$pageTitle = __t('seo_overview');
$extraHead = '<link rel="stylesheet" href="assets/css/admin-content.css">';

// Pre-compute counts for each tab (matches the missing_any / complete semantics used by the filter)
$missing_any_count = count(array_filter($allItems, fn($i) => empty($i['meta_title']) || empty($i['meta_description'])));
$complete_count    = $total - $missing_both;

ob_start();
?>
			<!-- ── Stats bar (tabs, matches Alt-Text Assistant) ─────────── -->
			<div class="tabs">
				<?php
				$seoTabs = [
					'all'             => [__t('seo_filter_all'),        $total],
					'missing_any'     => [__t('seo_filter_incomplete'), $missing_any_count],
					'missing_title'   => [__t('seo_filter_no_title'),   $missing_title],
					'missing_desc'    => [__t('seo_filter_no_desc'),    $missing_desc],
					'complete'        => [__t('seo_filter_complete'),   $complete_count],
				];
				foreach ($seoTabs as $key => $tab):
				?>
				<a href="seo-overview.php?filter=<?php echo $key; ?>"
				   class="tab <?php echo $filter === $key ? 'active' : ''; ?>">
					<?php echo hsc($tab[0]); ?>
					<span class="badge"><?php echo $tab[1]; ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<!-- ── Table ─────────────────────────────────────────────── -->
			<?php if (empty($filtered)): ?>
				<div class="empty-state">
					<p><?php _e('seo_no_items_filter'); ?></p>
				</div>
			<?php else: ?>
				<div class="table-wrap">
					<table class="seo-table">
					<thead>
						<tr>
							<th style="width: 20%"><?php _e('title'); ?> / <?php _e('type'); ?></th>
							<th style="width: 22%"><?php _e('meta_title'); ?> <small>(max 60)</small></th>
							<th style="width: 30%"><?php _e('meta_description'); ?> <small>(max 160)</small></th>
							<th style="width: 18%"><?php _e('meta_keywords'); ?></th>
							<th style="width: 10%"></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($filtered as $item): ?>
						<tr data-type="<?php echo hsc($item['type']); ?>" data-index="<?php echo (int)$item['index']; ?>">
							<!-- Titre / type / slug -->
							<td>
								<div style="font-weight: 600; font-size: 1.1em; margin-bottom: 4px;">
								<?php echo hsc($item['title']); ?>
								</div>
								<span class="type-badge type-<?php echo hsc($item['type']); ?>"><?php echo hsc(sl_type_label($item['type'])); ?></span>
								<div class="slug-cell" style="margin-top: 5px;">/<?php echo hsc($item['slug']); ?></div>
							</td>

							<!-- Meta title éditable -->
							<td>
								<input type="text"
									class="seo-field <?php echo empty($item['meta_title']) ? 'empty' : ''; ?>"
									data-field="meta_title"
									data-max="60"
									value="<?php echo hsc($item['meta_title']); ?>"
									placeholder="<?php _e('meta_title_placeholder'); ?>"
									maxlength="60">
								<span class="char-counter"><?php echo mb_strlen($item['meta_title']); ?>/60</span>
								<span class="save-indicator"></span>
							</td>
							<!-- Meta description éditable -->
							<td>
								<textarea
									class="seo-field <?php echo empty($item['meta_description']) ? 'empty' : ''; ?>"
									data-field="meta_description"
									data-max="160"
									rows="3"
									placeholder="<?php _e('meta_description_placeholder'); ?>"
									maxlength="160"><?php echo hsc($item['meta_description']); ?></textarea>
								<span class="char-counter"><?php echo mb_strlen($item['meta_description']); ?>/160</span>
								<span class="save-indicator"></span>
							</td>
							<!-- Meta keywords éditable -->
							<td>
								<input type="text"
									class="seo-field <?php echo empty($item['meta_keywords']) ? 'empty' : ''; ?>"
									data-field="meta_keywords"
									data-max="255"
									value="<?php echo hsc($item['meta_keywords']); ?>"
									placeholder="<?php _e('meta_keywords_placeholder'); ?>"
									maxlength="255">
								<span class="save-indicator"></span>
							</td>
							<!-- Lien édition -->
							<td style="text-align: center; vertical-align: middle;">
								<a href="<?php echo $item['edit_url']; ?>" class="table-btn edit-btn small"><?php echo admin_icon('writing', '', 13); ?><?php _e('edit'); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = '<script src="assets/js/seo-overview.js?v=' . @filemtime(__DIR__ . '/assets/js/seo-overview.js') . '"></script>';

require_once 'includes/layout.php';