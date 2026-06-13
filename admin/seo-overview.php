<?php
session_start();
if (!isset($_SESSION['admin'])) {
	header('Location: auth.php');
	exit;
}

require_once 'includes/admin-functions.php';

$data        = admin_load_data();
$appSettings = admin_load_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_seo_save'])) {
	header('Content-Type: application/json');
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
$draftsDir  = 'drafts';
$draftCount = 0;
if (file_exists($draftsDir)) {
	$draftCount = count(glob($draftsDir . '/*.json'));
}

$message = $_SESSION['message'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['message'], $_SESSION['error']);

$pageTitle = __t('seo_overview');
$extraHead = '<link rel="stylesheet" href="assets/css/admin-content.css">';

ob_start();
?>
			<!-- ── Stats ─────────────────────────────────────────────── -->
			<div class="seo-stats">
				<a href="seo-overview.php?filter=all" class="seo-stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>">
					<div class="seo-stat-num"><?php echo $total; ?></div>
					<div class="seo-stat-label"><?php _e('seo_total_content'); ?></div>
				</a>
				<a href="seo-overview.php?filter=missing_title" class="seo-stat-card <?php echo $filter === 'missing_title' ? 'active' : ''; ?> <?php echo $missing_title > 0 ? 'warning' : ''; ?>">
					<div class="seo-stat-num"><?php echo $missing_title; ?></div>
					<div class="seo-stat-label"><?php _e('seo_missing_title'); ?></div>
				</a>
				<a href="seo-overview.php?filter=missing_desc" class="seo-stat-card <?php echo $filter === 'missing_desc' ? 'active' : ''; ?> <?php echo $missing_desc > 0 ? 'warning' : ''; ?>">
					<div class="seo-stat-num"><?php echo $missing_desc; ?></div>
					<div class="seo-stat-label"><?php _e('seo_missing_desc'); ?></div>
				</a>
				<a href="seo-overview.php?filter=complete" class="seo-stat-card <?php echo $filter === 'complete' ? 'active' : ''; ?> <?php echo ($total - $missing_both) === $total && $total > 0 ? 'success' : ''; ?>">
					<div class="seo-stat-num"><?php echo $total - $missing_both; ?></div>
					<div class="seo-stat-label"><?php _e('seo_complete'); ?></div>
				</a>
			</div>
			<!-- ── Filters ───────────────────────────────────────────── -->
			<div class="seo-filters">
				<?php
				$filters = [
					'all'           => __t('seo_filter_all'),
					'missing_any'   => __t('seo_filter_incomplete'),
					'missing_title' => __t('seo_filter_no_title'),
					'missing_desc'  => __t('seo_filter_no_desc'),
					'complete'      => __t('seo_filter_complete'),
				];
				foreach ($filters as $key => $label):
				?>
				<a href="seo-overview.php?filter=<?php echo $key; ?>" class="seo-filter-btn <?php echo $filter === $key ? 'active' : ''; ?>">
					<?php echo htmlspecialchars($label); ?>
				</a>
				<?php endforeach; ?>
			</div>
			<!-- ── Table ─────────────────────────────────────────────── -->
			<?php if (empty($filtered)): ?>
				<div class="empty-state">
					<p><?php _e('seo_no_items_filter'); ?></p>
				</div>
			<?php else: ?>
			<div class="site-settings-section" style="padding: 0; overflow: visible;">
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
						<tr data-type="<?php echo htmlspecialchars($item['type']); ?>" data-index="<?php echo (int)$item['index']; ?>">
							<!-- Titre / type / slug -->
							<td>
								<div style="font-weight: 600; font-size: 1.1em; margin-bottom: 4px;">
									<?php echo htmlspecialchars($item['title']); ?>
								</div>
								<span class="type-badge type-<?php echo $item['type']; ?>"><?php echo __t('type_' . $item['type']) ?></span>
								<div class="slug-cell" style="margin-top: 5px;">/<?php echo htmlspecialchars($item['slug']); ?></div>
							</td>

							<!-- Meta title éditable -->
							<td>
								<input type="text"
									class="seo-field <?php echo empty($item['meta_title']) ? 'empty' : ''; ?>"
									data-field="meta_title"
									data-max="60"
									value="<?php echo htmlspecialchars($item['meta_title']); ?>"
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
									maxlength="160"><?php echo htmlspecialchars($item['meta_description']); ?></textarea>
								<span class="char-counter"><?php echo mb_strlen($item['meta_description']); ?>/160</span>
								<span class="save-indicator"></span>
							</td>
							<!-- Meta keywords éditable -->
							<td>
								<input type="text"
									class="seo-field <?php echo empty($item['meta_keywords']) ? 'empty' : ''; ?>"
									data-field="meta_keywords"
									data-max="255"
									value="<?php echo htmlspecialchars($item['meta_keywords']); ?>"
									placeholder="<?php _e('meta_keywords_placeholder'); ?>"
									maxlength="255">
								<span class="save-indicator"></span>
							</td>
							<!-- Lien édition -->
							<td style="text-align: center; vertical-align: middle;">
								<a href="<?php echo $item['edit_url']; ?>" class="edit-link">✏️ <?php _e('edit'); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = <<<'JSINLINE'
<script>
(function() {
	'use strict';

	var saveTimers = new WeakMap();

	function updateCounter(field) {
		var max     = parseInt(field.dataset.max, 10);
		var len     = field.value.length;
		var counter = field.parentNode.querySelector('.char-counter');
		if (!counter) return;
		counter.textContent = len + '/' + max;
		counter.className   = 'char-counter';
		if (len > max * 0.9) counter.classList.add('warn');
		if (len >= max)      counter.classList.add('over');
		if (len === 0) { field.classList.add('empty'); } else { field.classList.remove('empty'); }
	}

	function showSaveIndicator(field, ok) {
		var ind = field.parentNode.querySelector('.save-indicator');
		if (!ind) return;
		ind.textContent  = ok ? '\u2713 ' + window.t('saved', 'Saved') : '\u2717 ' + window.t('save_error', 'Error');
		ind.className    = 'save-indicator visible' + (ok ? '' : ' error');
		clearTimeout(ind._hideTimer);
		ind._hideTimer = setTimeout(function() { ind.classList.remove('visible'); }, 2000);
	}

	function saveField(field) {
		var row   = field.closest('tr');
		var type  = row.dataset.type;
		var index = row.dataset.index;
		var fname = field.dataset.field;
		var val   = field.value;
		field.classList.add('saving');
		var body = new URLSearchParams({ ajax_seo_save: '1', type: type, index: index, field: fname, value: val });
		fetch('seo-overview.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function(r) { return r.json(); })
			.then(function(data) { field.classList.remove('saving'); showSaveIndicator(field, data.ok); })
			.catch(function() { field.classList.remove('saving'); showSaveIndicator(field, false); });
	}

	document.querySelectorAll('.seo-field').forEach(function(field) {
		updateCounter(field);
		field.addEventListener('input', function() {
			updateCounter(field);
			clearTimeout(saveTimers.get(field));
			saveTimers.set(field, setTimeout(function() { saveField(field); }, 800));
		});
		field.addEventListener('blur', function() {
			clearTimeout(saveTimers.get(field));
			saveField(field);
		});
	});

})();
</script>
JSINLINE;

require_once 'includes/layout.php';