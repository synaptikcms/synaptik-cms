<?php
session_start();
if (!isset($_SESSION['admin'])) {
	header('Location: auth.php');
	exit;
}

require_once 'includes/admin-functions.php';

$data        = admin_load_data();
$appSettings = admin_load_settings();

// ── AJAX save handler ─────────────────────────────────────────────────────────
// Saves alt_text or caption for a specific image within a gallery entry.
// Expected POST fields:
//   ajax_alt_save  : '1' (trigger)
//   post_type      : 'article' | 'page' | 'project'
//   post_index     : (int) index in data[$type]
//   gallery_index  : (int) index in galleries[]
//   image_index    : (int) index in gallery.images[]
//   field          : 'alt_text' | 'caption'
//   value          : string (sanitized server-side)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_alt_save'])) {
	header('Content-Type: application/json');

	$allowed_types  = ['article', 'page', 'project'];
	$allowed_fields = ['alt_text', 'caption'];

	$post_type    = $_POST['post_type']     ?? '';
	$post_index   = (int)($_POST['post_index']    ?? -1);
	$gallery_idx  = (int)($_POST['gallery_index'] ?? -1);
	$image_idx    = (int)($_POST['image_index']   ?? -1);
	$field        = $_POST['field']          ?? '';
	$value        = trim($_POST['value']     ?? '');

	// Validate all parameters before touching data
	if (
		!in_array($post_type, $allowed_types, true)
		|| !in_array($field, $allowed_fields, true)
		|| $post_index  < 0
		|| $gallery_idx < 0
		|| $image_idx   < 0
	) {
		echo json_encode(['ok' => false, 'error' => 'invalid_params']);
		exit;
	}

	// Verify the path exists in data
	if (
		!isset($data[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx])
	) {
		echo json_encode(['ok' => false, 'error' => 'not_found']);
		exit;
	}

	// Sanitize: strip tags, limit length
	$value = strip_tags($value);
	$value = mb_substr($value, 0, 500);

	$data[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx][$field] = $value;
	$result = admin_save_data($data);

	echo json_encode(['ok' => $result !== false, 'value' => $value]);
	exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

// ── Collect all gallery images across all content types ───────────────────────
// Each entry in $allImages holds enough context to display the image card
// and write back to the correct location in data.json on save.
$contentTypes = ['article', 'page', 'project'];

$allImages = [];

foreach ($contentTypes as $type) {
	if (empty($data[$type])) continue;

	foreach ($data[$type] as $postIndex => $post) {
		$galleries = $post['galleries'] ?? [];

		// Legacy migration: flat gallery array → named gallery
		if (empty($galleries) && !empty($post['gallery']) && is_array($post['gallery'])) {
			$galleries = [[
				'label'  => 'Gallery',
				'layout' => $post['gallery_layout'] ?? 'grid',
				'images' => $post['gallery'],
			]];
		}

		if (empty($galleries)) continue;

		foreach ($galleries as $galleryIdx => $gallery) {
			$images = $gallery['images'] ?? [];
			if (empty($images)) continue;

			foreach ($images as $imageIdx => $image) {
				$src      = $image['src'] ?? '';
				$alt      = $image['alt_text'] ?? '';
				$caption  = $image['caption']  ?? '';

				// Build a renderable URL relative to the admin folder
				if (strpos($src, 'files/') === 0) {
					$imgUrl = '../' . $src;
				} else {
					$imgUrl = '../files/' . $src;
				}

				$allImages[] = [
					'post_type'     => $type,
					'post_index'    => $postIndex,
					'post_title'    => $post['title']     ?? '',
					'post_slug'     => !empty($post['custom_slug']) ? $post['custom_slug'] : ($post['slug'] ?? ''),
					'post_published'=> $post['published'] ?? false,
					'edit_url'      => 'index.php?action=edit&type=' . urlencode($type) . '&index=' . $postIndex,
					'gallery_index' => $galleryIdx,
					'gallery_label' => $gallery['label'] ?? ('Gallery ' . ($galleryIdx + 1)),
					'image_index'   => $imageIdx,
					'src'           => $src,
					'img_url'       => $imgUrl,
					'alt_text'      => $alt,
					'caption'       => $caption,
					'has_alt'       => ($alt !== ''),
					'has_caption'   => ($caption !== ''),
				];
			}
		}
	}
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_images    = count($allImages);
$missing_alt     = count(array_filter($allImages, fn($i) => !$i['has_alt']));
$missing_caption = count(array_filter($allImages, fn($i) => !$i['has_caption']));
$missing_both    = count(array_filter($allImages, fn($i) => !$i['has_alt'] && !$i['has_caption']));
$complete        = count(array_filter($allImages, fn($i) => $i['has_alt'] && $i['has_caption']));

// ── Active filter ─────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';

$filtered = array_filter($allImages, function ($img) use ($filter) {
	if ($filter === 'missing_alt')     return !$img['has_alt'];
	if ($filter === 'missing_caption') return !$img['has_caption'];
	if ($filter === 'missing_any')     return !$img['has_alt'] || !$img['has_caption'];
	if ($filter === 'complete')        return $img['has_alt'] && $img['has_caption'];
	return true; // 'all'
});

// Reset numeric keys so we can reference them cleanly in the template
$filtered = array_values($filtered);

// ── Sidebar prerequisites ─────────────────────────────────────────────────────
$draftsDir  = 'drafts';
$draftCount = file_exists($draftsDir) ? count(glob($draftsDir . '/*.json')) : 0;

$message = $_SESSION['message'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['message'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(lang_current()); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SynaptikCMS Admin | <?php _e('alt_assistant_title', 'Alt-Text Assistant'); ?></title>
	<link rel="icon" type="image/x-icon" href="../files/favicon.ico">
	<link rel="icon" type="image/png" sizes="32x32" href="../files/favicon-32x32.png">
	<link rel="apple-touch-icon" href="../files/apple-touch-icon.png">
	<link rel="stylesheet" href="css/admin-base.css">
	<link rel="stylesheet" href="css/admin-components.css">
	<link rel="stylesheet" href="css/admin-content.css">
	<link rel="stylesheet" href="css/admin-sidebar.css">
	<script>window.CMS_LANG = <?php echo lang_js_bridge(); ?>;</script>
	<style>
		.alt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 8px; }
		.alt-card { background: var(--bg-white); border: 1px solid var(--border-light); border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: border-color .15s, box-shadow .15s; }
		.alt-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
		.alt-card-context .post-title { font-weight: 600; font-size: 1.2em; color: var(--primary); }
		.alt-card.missing-alt .alt-card-context .post-title { color: var(--danger); }
		.alt-card-thumb { width: 100%; height: 110px; overflow: hidden; background: var(--bg-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
		.alt-card-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
		.alt-card-body { padding: 5px 10px; display: flex; flex-direction: column; gap: 5px; flex: 1; }
		.alt-card-context { font-size: 0.8em; color: var(--text-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding-bottom: 8px; }
		.alt-card-context .post-title { font-weight: 600; font-size: 1.2em; }
		.alt-card-context .gallery-name { font-style: italic; }
		.alt-field-group { display: flex; flex-direction: column; gap: 3px; }
		.char-counter { color: var(--text-muted); text-align: right; margin-top: 2px; display: block; font-weight: normal; font-size: .9em; }
		.alt-field-label { font-size: 0.9em; margin-top: 0; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
		.alt-card textarea.seo-field { resize: vertical; min-height: 52px; }
		.alt-card-footer { padding: 0 16px 8px 16px; background: var(--text-white); display: flex; align-items: center; justify-content: space-between; font-size: 0.9em; }
		.alt-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 1.05em; }
		@media (max-width: 640px) {
		  .alt-grid { grid-template-columns: 1fr; }
		}
	</style>
</head>
<body>
<script>
/* Restore sidebar collapsed state before first paint to avoid flash */
(function () {
	try {
		var saved = localStorage.getItem('synaptik_sidebar_state');
		if (saved) {
			var s = JSON.parse(saved);
			document.body.classList.add(s.isExpanded === false ? 'sidebar-collapsed' : 'sidebar-expanded');
		} else {
			document.body.classList.add('sidebar-expanded');
		}
	} catch (e) { document.body.classList.add('sidebar-expanded'); }
})();
</script>
<div class="admin-container">
	<?php include_once 'includes/sidebar.php'; ?>
	<main class="content">
		<?php if ($message): ?>
			<div class="message success"><?php echo htmlspecialchars($message); ?></div>
		<?php endif; ?>
		<?php if ($error): ?>
			<div class="message error"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<h1 class="main-heading"><?php _e('alt_assistant_title', 'Alt-Text Assistant'); ?></h1>
		<a href="<?php echo $baseUrl; ?>" target="_blank" class="view-website-btn">
			<span class="icon">🌐</span> <?php _e('view_website'); ?>
		</a>
			<!-- ── Stats bar ─────────────────────────────────────────────── -->
			<div class="seo-stats">
				<a href="alt-text-assistant.php?filter=all"
				   class="seo-stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>">
					<div class="seo-stat-num"><?php echo $total_images; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_total', 'Total gallery images'); ?></div>
				</a>
				<a href="alt-text-assistant.php?filter=missing_alt"
				   class="seo-stat-card <?php echo $filter === 'missing_alt' ? 'active' : ''; ?> <?php echo $missing_alt > 0 ? 'warning' : ''; ?>">
					<div class="seo-stat-num"><?php echo $missing_alt; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_missing_alt', 'Missing alt text'); ?></div>
				</a>
				<a href="alt-text-assistant.php?filter=missing_caption"
				   class="seo-stat-card <?php echo $filter === 'missing_caption' ? 'active' : ''; ?> <?php echo $missing_caption > 0 ? 'warning' : ''; ?>">
					<div class="seo-stat-num"><?php echo $missing_caption; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_missing_caption', 'Missing caption'); ?></div>
				</a>
				<a href="alt-text-assistant.php?filter=complete"
				   class="seo-stat-card <?php echo $filter === 'complete' ? 'active' : ''; ?> <?php echo $complete === $total_images && $total_images > 0 ? 'success' : ''; ?>">
					<div class="seo-stat-num"><?php echo $complete; ?></div>
					<div class="seo-stat-label"><?php _e('alt_stat_complete', 'Alt + caption filled'); ?></div>
				</a>
			</div>
			<!-- ── Filter bar ────────────────────────────────────────────── -->
			<div class="seo-filters">
				<?php
				$filters = [
					'all'             => __t('alt_filter_all',             'All images'),
					'missing_any'     => __t('alt_filter_missing_any',     'Incomplete'),
					'missing_alt'     => __t('alt_filter_missing_alt',     'No alt text'),
					'missing_caption' => __t('alt_filter_missing_caption', 'No caption'),
					'complete'        => __t('alt_filter_complete',        'Complete'),
				];
				foreach ($filters as $key => $label):
				?>
				<a href="alt-text-assistant.php?filter=<?php echo $key; ?>"
				   class="seo-filter-btn <?php echo $filter === $key ? 'active' : ''; ?>">
					<?php echo htmlspecialchars($label); ?>
				</a>
				<?php endforeach; ?>
			</div>
			<!-- ── Image grid ────────────────────────────────────────────── -->
			<?php if (empty($filtered)): ?>
				<div class="alt-empty">
					<?php _e('alt_no_items', 'No images match this filter.'); ?>
				</div>
			<?php else: ?>
				<div class="alt-grid">
					<?php foreach ($filtered as $img): ?>
					<?php
						// CSS class signals missing alt-text on the card title (caption excluded — never required)
						$cardClasses = 'alt-card';
						if (!$img['has_alt']) $cardClasses .= ' missing-alt';
					?>
					<div class="<?php echo $cardClasses; ?>"
						 data-post-type="<?php echo htmlspecialchars($img['post_type']); ?>"
						 data-post-index="<?php echo (int)$img['post_index']; ?>"
						 data-gallery-index="<?php echo (int)$img['gallery_index']; ?>"
						 data-image-index="<?php echo (int)$img['image_index']; ?>">

						<!-- Thumbnail -->
						<div class="alt-card-thumb">
							<img src="<?php echo htmlspecialchars($img['img_url']); ?>"
								 alt="<?php echo htmlspecialchars($img['alt_text']); ?>"
								 loading="lazy">
						</div>
						<!-- Body -->
						<div class="alt-card-body">
							<!-- Post context -->
							<div class="alt-card-context">
								<span class="post-title"><?php echo htmlspecialchars($img['post_title']); ?></span>
								<span class="type-badge type-<?php echo $img['post_type']; ?>"><?php echo __t('type_' . $img['post_type']) ?></span>
								<span class="gallery-name">— <?php echo htmlspecialchars($img['gallery_label']); ?></span>
							</div>
							<!-- Alt text field -->
							<div class="alt-field-group">
								<label class="alt-field-label">
									<?php _e('alt_text', 'Alt text'); ?> - 
									<span class="char-counter"><?php echo mb_strlen($img['alt_text']); ?>/250</span>
								</label>
								<input type="text"
									   class="seo-field alt-editable <?php echo $img['has_alt'] ? '' : 'empty'; ?>"
									   data-field="alt_text"
									   data-max="250"
									   value="<?php echo htmlspecialchars($img['alt_text']); ?>"
									   placeholder="<?php _e('alt_text_placeholder', 'Describe the image…'); ?>"
									   maxlength="250">
								<span class="save-indicator"></span>
							</div>
							<!-- Caption field -->
							<div class="alt-field-group">
								<label class="alt-field-label">
									<?php _e('caption', 'Caption'); ?>
								</label>
								<textarea class="seo-field alt-editable <?php echo $img['has_caption'] ? '' : 'empty'; ?>"
										  data-field="caption"
										  data-max="500"
										  placeholder="<?php _e('caption_placeholder', 'Optional caption…'); ?>"
										  maxlength="500"
										  rows="2"><?php echo htmlspecialchars($img['caption']); ?></textarea>
								<span class="save-indicator"></span>
							</div>
						</div><!-- /.alt-card-body -->
						<!-- Footer: link to the post editor -->
						<div class="alt-card-footer">
							<span class="slug-cell">/<?php echo htmlspecialchars($img['post_slug']); ?></span>
							<a href="<?php echo $img['edit_url']; ?>" class="edit-link">
								✏️ <?php _e('edit', 'Edit post'); ?>
							</a>
						</div>
					</div><!-- /.alt-card -->
					<?php endforeach; ?>
				</div><!-- /.alt-grid -->
			<?php endif; ?>
	</main>
</div>

<script src="js/common.js"></script>
<script src="js/admin-sidebar.js"></script>
<script>
(function () {
	'use strict';

	// Debounce timers keyed to DOM elements
	var saveTimers = new WeakMap();

	/**
	 * Update character counter for a field.
	 * Also adds/removes the .empty class based on field content.
	 *
	 * @param {HTMLElement} field  The input or textarea element
	 */
	function updateCounter(field) {
		var max     = parseInt(field.dataset.max, 10);
		var len     = field.value.length;
		var counter = field.parentNode.querySelector('.char-counter');
		if (!counter) return;

		counter.textContent = len + '/' + max;
		counter.className   = 'char-counter';
		if (len > max * 0.9) counter.classList.add('warn');
		if (len >= max)      counter.classList.add('over');

		if (len === 0) {
			field.classList.add('empty');
		} else {
			field.classList.remove('empty');
		}
	}

	/**
	 * Show a transient save-success or save-error indicator below the field.
	 *
	 * @param {HTMLElement} field  The input or textarea that was saved
	 * @param {boolean}     ok     Whether the server confirmed success
	 */
	function showSaveIndicator(field, ok) {
		var ind = field.parentNode.querySelector('.save-indicator');
		if (!ind) return;

		var t = window.CMS_LANG || {};
		ind.textContent = ok
			? '✓ ' + (t['saved'] || 'Saved')
			: '✗ ' + (t['save_error'] || 'Error');
		ind.className = 'save-indicator visible' + (ok ? '' : ' error');

		clearTimeout(ind._hideTimer);
		ind._hideTimer = setTimeout(function () {
			ind.classList.remove('visible');
		}, 2000);
	}

	/**
	 * Update the card's border classes after a successful save so the visual
	 * state reflects the new reality without a page reload.
	 *
	 * @param {HTMLElement} card   The .alt-card container
	 * @param {string}      field  'alt_text' or 'caption'
	 * @param {string}      value  The saved value
	 */
	function updateCardState(card, field, value) {
		var hasValue = value.trim().length > 0;

		if (field === 'alt_text') {
			// Update the alt attribute on the thumbnail for accessibility
			var thumb = card.querySelector('.alt-card-thumb img');
			if (thumb) thumb.alt = value;

			// Toggle missing-alt class — drives post-title color via CSS
			if (hasValue) {
				card.classList.remove('missing-alt');
			} else {
				card.classList.add('missing-alt');
			}

			// Update the status dot on the label
			var dot = card.querySelector('[data-field="alt_text"]')
				.closest('.alt-field-group')
				.querySelector('.status-dot');
			if (dot) {
				dot.className = 'status-dot ' + (hasValue ? 'published' : 'draft');
			}
		}

		// Caption saves are persisted to the server but do not affect card state or color.
		if (field === 'caption') {
			var capDot = card.querySelector('[data-field="caption"]')
				.closest('.alt-field-group')
				.querySelector('.status-dot');
			if (capDot) {
				capDot.className = 'status-dot ' + (hasValue ? 'published' : 'draft');
			}
		}
	}

	/**
	 * POST a field value to the server via AJAX.
	 *
	 * @param {HTMLElement} field  The editable input/textarea
	 */
	function saveField(field) {
		var card         = field.closest('.alt-card');
		var post_type    = card.dataset.postType;
		var post_index   = card.dataset.postIndex;
		var gallery_idx  = card.dataset.galleryIndex;
		var image_idx    = card.dataset.imageIndex;
		var fname        = field.dataset.field;
		var val          = field.value;

		field.classList.add('saving');

		var body = new URLSearchParams({
			ajax_alt_save : '1',
			post_type     : post_type,
			post_index    : post_index,
			gallery_index : gallery_idx,
			image_index   : image_idx,
			field         : fname,
			value         : val
		});

		fetch('alt-text-assistant.php', {
			method  : 'POST',
			headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
			body    : body.toString()
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			field.classList.remove('saving');
			showSaveIndicator(field, data.ok);
			if (data.ok) {
				updateCardState(card, fname, data.value);
			}
		})
		.catch(function () {
			field.classList.remove('saving');
			showSaveIndicator(field, false);
		});
	}

	// Attach listeners to all editable fields
	document.querySelectorAll('.alt-editable').forEach(function (field) {
		// Initialise counter display
		updateCounter(field);

		field.addEventListener('input', function () {
			updateCounter(field);
			// Debounce: save 800ms after the last keystroke
			clearTimeout(saveTimers.get(field));
			saveTimers.set(field, setTimeout(function () {
				saveField(field);
			}, 800));
		});

		// Immediate save on blur (user tabs/clicks away)
		field.addEventListener('blur', function () {
			clearTimeout(saveTimers.get(field));
			saveField(field);
		});
	});

})();
</script>
</body>
</html>