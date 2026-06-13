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
$missing_any     = count(array_filter($allImages, fn($i) => !$i['has_alt'] || !$i['has_caption']));
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

$pageTitle = __t('alt_assistant_title', 'Alt-Text Assistant');

ob_start();
?>
			<!-- ── Stats bar ─────────────────────────────────────────────── -->
			<div class="tabs">
				<?php
				$altTabs = [
					'all'             => [__t('alt_filter_all',             'All images'),  $total_images],
					'missing_any'     => [__t('alt_filter_missing_any',     'Incomplete'),  $missing_any],
					'missing_alt'     => [__t('alt_filter_missing_alt',     'No alt text'), $missing_alt],
					'missing_caption' => [__t('alt_filter_missing_caption', 'No caption'),  $missing_caption],
					'complete'        => [__t('alt_filter_complete',        'Complete'),    $complete],
				];
				foreach ($altTabs as $key => $tab):
				?>
				<a href="alt-text-assistant.php?filter=<?php echo $key; ?>"
				   class="tab <?php echo $filter === $key ? 'active' : ''; ?>">
					<?php echo htmlspecialchars($tab[0]); ?>
					<span class="badge"><?php echo $tab[1]; ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<!-- ── Filter bar ────────────────────────────────────────────── -->
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
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = <<<'JSINLINE'
<script>
(function () {
	'use strict';
	var saveTimers = new WeakMap();

	function updateCounter(field) {
		var max = parseInt(field.dataset.max, 10), len = field.value.length;
		var counter = field.parentNode.querySelector('.char-counter');
		if (!counter) return;
		counter.textContent = len + '/' + max;
		counter.className = 'char-counter';
		if (len > max * 0.9) counter.classList.add('warn');
		if (len >= max) counter.classList.add('over');
		field.classList.toggle('empty', len === 0);
	}

	function showSaveIndicator(field, ok) {
		var ind = field.parentNode.querySelector('.save-indicator');
		if (!ind) return;
		var t = window.CMS_LANG || {};
		ind.textContent = ok ? '\u2713 ' + (t['saved'] || 'Saved') : '\u2717 ' + (t['save_error'] || 'Error');
		ind.className = 'save-indicator visible' + (ok ? '' : ' error');
		clearTimeout(ind._hideTimer);
		ind._hideTimer = setTimeout(function () { ind.classList.remove('visible'); }, 2000);
	}

	function updateCardState(card, field, value) {
		var hasValue = value.trim().length > 0;
		if (field === 'alt_text') {
			var thumb = card.querySelector('.alt-card-thumb img');
			if (thumb) thumb.alt = value;
			card.classList.toggle('missing-alt', !hasValue);
		}
	}

	function saveField(field) {
		var card = field.closest('.alt-card');
		field.classList.add('saving');
		var body = new URLSearchParams({
			ajax_alt_save: '1',
			post_type:     card.dataset.postType,
			post_index:    card.dataset.postIndex,
			gallery_index: card.dataset.galleryIndex,
			image_index:   card.dataset.imageIndex,
			field:         field.dataset.field,
			value:         field.value
		});
		fetch('alt-text-assistant.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				field.classList.remove('saving');
				showSaveIndicator(field, data.ok);
				if (data.ok) updateCardState(card, field.dataset.field, data.value);
			})
			.catch(function () { field.classList.remove('saving'); showSaveIndicator(field, false); });
	}

	document.querySelectorAll('.alt-editable').forEach(function (field) {
		updateCounter(field);
		field.addEventListener('input', function () {
			updateCounter(field);
			clearTimeout(saveTimers.get(field));
			saveTimers.set(field, setTimeout(function () { saveField(field); }, 800));
		});
		field.addEventListener('blur', function () {
			clearTimeout(saveTimers.get(field));
			saveField(field);
		});
	});
})();
</script>
JSINLINE;

require_once 'includes/layout.php';