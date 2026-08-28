<?php
require_once __DIR__ . '/includes/session-config.php';
session_start();
require_once 'includes/admin-functions.php';
if (!admin_is_logged_in()) {
	header('Location: auth.php');
	exit;
}

$data        = admin_load_data();
$appSettings = admin_load_config();

function alt_extract_inline_images(string $content, string $format): array
{
	$images = [];
	if ($content === '') return $images;

	if ($format === 'markdown') {
		preg_match_all(
			'/!\[([^\]]*)\]\(\s*([^)\s]+?)(?:\s+=(?:\d+%?)?x(?:\d+%?)?)?\s*\)/',
			$content,
			$matches,
			PREG_SET_ORDER
		);
		foreach ($matches as $m) {
			$images[] = ['alt' => $m[1], 'src' => trim($m[2])];
		}
		return $images;
	}

	preg_match_all('/<img\b[^>]*>/i', $content, $matches);
	foreach ($matches[0] as $tag) {
		$src = '';
		$alt = '';
		if (preg_match('/\bsrc=(["\'])(.*?)\1/i', $tag, $m)) $src = $m[2];
		if (preg_match('/\balt=(["\'])(.*?)\1/i', $tag, $m)) $alt = html_entity_decode($m[2], ENT_QUOTES);
		if ($src === '') continue;
		$images[] = ['alt' => $alt, 'src' => $src];
	}
	return $images;
}

function alt_replace_inline_image_alt(string $content, string $format, int $targetIndex, string $newAlt): string
{
	$counter = -1;

	if ($format === 'markdown') {
		return preg_replace_callback(
			'/!\[([^\]]*)\]\(\s*([^)\s]+?)((?:\s+=(?:\d+%?)?x(?:\d+%?)?)?)\s*\)/',
			function ($m) use (&$counter, $targetIndex, $newAlt) {
				$counter++;
				$alt = ($counter === $targetIndex) ? $newAlt : $m[1];
				return '![' . $alt . '](' . $m[2] . $m[3] . ')';
			},
			$content
		);
	}

	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ($m) use (&$counter, $targetIndex, $newAlt) {
			$counter++;
			if ($counter !== $targetIndex) return $m[0];
			$tag     = $m[0];
			$encoded = htmlspecialchars($newAlt, ENT_QUOTES);
			if (preg_match('/\balt=(["\']).*?\1/i', $tag)) {
				return preg_replace('/\balt=(["\']).*?\1/i', 'alt="' . $encoded . '"', $tag, 1);
			}
			return preg_replace('/^<img\b/i', '<img alt="' . $encoded . '"', $tag, 1);
		},
		$content
	);
}

function alt_resolve_thumb_url(string $src): string
{
	if (stripos($src, 'http://') === 0 || stripos($src, 'https://') === 0) {
		return $src;
	}
	if (strpos($src, 'files/') === 0) {
		return '../' . $src;
	}
	return '../files/' . $src;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_alt_save'])) {
	header('Content-Type: application/json');

	$_csrfToken = $_POST['csrf_token'] ?? (getallheaders()['X-CSRF-Token'] ?? '');
	if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_csrfToken)) {
		echo json_encode(['ok' => false, 'error' => 'invalid_token']);
		exit;
	}

	$allowed_types   = ['article', 'page', 'project'];
	$allowed_fields  = ['alt_text', 'caption'];
	$allowed_targets = ['gallery', 'featured', 'inline'];

	$target     = $_POST['target']     ?? 'gallery';
	$post_type  = $_POST['post_type']  ?? '';
	$post_index = (int)($_POST['post_index'] ?? -1);
	$field      = $_POST['field']      ?? '';
	$value      = trim($_POST['value'] ?? '');

	if (
		!in_array($target, $allowed_targets, true)
		|| !in_array($post_type, $allowed_types, true)
		|| !in_array($field, $allowed_fields, true)
		|| $post_index < 0
		|| !isset($data[$post_type][$post_index])
	) {
		echo json_encode(['ok' => false, 'error' => 'invalid_params']);
		exit;
	}

	if (!admin_can_edit_item($data[$post_type][$post_index])) {
		echo json_encode(['ok' => false, 'error' => 'not_authorized']);
		exit;
	}

	// Sanitize: strip tags, limit length
	$value = strip_tags($value);
	$value = mb_substr($value, 0, 500);

	if ($target === 'gallery') {
		$gallery_idx = (int)($_POST['gallery_index'] ?? -1);
		$image_idx   = (int)($_POST['image_index']   ?? -1);

		if (
			$gallery_idx < 0 || $image_idx < 0
			|| !isset($data[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx])
		) {
			echo json_encode(['ok' => false, 'error' => 'not_found']);
			exit;
		}

		$data[$post_type][$post_index]['galleries'][$gallery_idx]['images'][$image_idx][$field] = $value;
	} elseif ($target === 'featured') {
		if ($field !== 'alt_text' || empty($data[$post_type][$post_index]['image'])) {
			echo json_encode(['ok' => false, 'error' => 'not_found']);
			exit;
		}

		$data[$post_type][$post_index]['image_alt'] = $value;
	} else { // inline
		if ($field !== 'alt_text') {
			echo json_encode(['ok' => false, 'error' => 'invalid_params']);
			exit;
		}

		$inline_idx = (int)($_POST['inline_index'] ?? -1);
		$content    = $data[$post_type][$post_index]['content']        ?? '';
		$format     = $data[$post_type][$post_index]['content_format'] ?? 'html';
		$images     = alt_extract_inline_images($content, $format);

		if ($inline_idx < 0 || !isset($images[$inline_idx])) {
			echo json_encode(['ok' => false, 'error' => 'not_found']);
			exit;
		}

		$data[$post_type][$post_index]['content'] = alt_replace_inline_image_alt($content, $format, $inline_idx, $value);
	}

	$result = admin_save_data($data);

	echo json_encode(['ok' => $result !== false, 'value' => $value]);
	exit;
}

$protocol = _sl_request_is_https() ? 'https' : 'http';
$baseUrl  = $protocol . '://' . _sl_request_host() . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

$contentTypes = ['article', 'page', 'project'];

$allImages = [];

foreach ($contentTypes as $type) {
	if (empty($data[$type])) continue;

	foreach ($data[$type] as $postIndex => $post) {
		if (!admin_can_edit_item($post)) continue;

		$postTitle     = $post['title'] ?? '';
		$postSlug      = !empty($post['custom_slug']) ? $post['custom_slug'] : ($post['slug'] ?? '');
		$postPublished = $post['published'] ?? false;
		$editUrl       = 'index.php?action=edit&type=' . urlencode($type) . '&index=' . $postIndex;

		// ── Featured image ──────────────────────────────────────────────────
		if (!empty($post['image'])) {
			$alt = $post['image_alt'] ?? '';
			$allImages[] = [
				'target'        => 'featured',
				'post_type'     => $type,
				'post_index'    => $postIndex,
				'post_title'    => $postTitle,
				'post_slug'     => $postSlug,
				'post_published'=> $postPublished,
				'edit_url'      => $editUrl,
				'gallery_index' => -1,
				'gallery_label' => __t('alt_source_featured', 'Featured image'),
				'image_index'   => -1,
				'inline_index'  => -1,
				'src'           => $post['image'],
				'img_url'       => alt_resolve_thumb_url($post['image']),
				'alt_text'      => $alt,
				'caption'       => '',
				'has_alt'       => ($alt !== ''),
				'has_caption'   => true, // no caption field for featured images — never "missing"
				'has_caption_field' => false,
			];
		}

		// ── Gallery images ───────────────────────────────────────────────────
		$galleries = $post['galleries'] ?? [];

		// Legacy migration: flat gallery array → named gallery
		if (empty($galleries) && !empty($post['gallery']) && is_array($post['gallery'])) {
			$galleries = [[
				'label'  => 'Gallery',
				'layout' => $post['gallery_layout'] ?? 'grid',
				'images' => $post['gallery'],
			]];
		}

		foreach ($galleries as $galleryIdx => $gallery) {
			$images = $gallery['images'] ?? [];

			foreach ($images as $imageIdx => $image) {
				$src     = $image['src'] ?? '';
				$alt     = $image['alt_text'] ?? '';
				$caption = $image['caption']  ?? '';

				$allImages[] = [
					'target'        => 'gallery',
					'post_type'     => $type,
					'post_index'    => $postIndex,
					'post_title'    => $postTitle,
					'post_slug'     => $postSlug,
					'post_published'=> $postPublished,
					'edit_url'      => $editUrl,
					'gallery_index' => $galleryIdx,
					'gallery_label' => $gallery['label'] ?? ('Gallery ' . ($galleryIdx + 1)),
					'image_index'   => $imageIdx,
					'inline_index'  => -1,
					'src'           => $src,
					'img_url'       => alt_resolve_thumb_url($src),
					'alt_text'      => $alt,
					'caption'       => $caption,
					'has_alt'       => ($alt !== ''),
					'has_caption'   => ($caption !== ''),
					'has_caption_field' => true,
				];
			}
		}

		// ── Images embedded directly in the body content ────────────────────
		$inlineImages = alt_extract_inline_images($post['content'] ?? '', $post['content_format'] ?? 'html');

		foreach ($inlineImages as $inlineIdx => $img) {
			$alt = $img['alt'];
			$allImages[] = [
				'target'        => 'inline',
				'post_type'     => $type,
				'post_index'    => $postIndex,
				'post_title'    => $postTitle,
				'post_slug'     => $postSlug,
				'post_published'=> $postPublished,
				'edit_url'      => $editUrl,
				'gallery_index' => -1,
				'gallery_label' => __t('alt_source_inline', 'In content'),
				'image_index'   => -1,
				'inline_index'  => $inlineIdx,
				'src'           => $img['src'],
				'img_url'       => alt_resolve_thumb_url($img['src']),
				'alt_text'      => $alt,
				'caption'       => '',
				'has_alt'       => ($alt !== ''),
				'has_caption'   => true, // no caption field for inline images — never "missing"
				'has_caption_field' => false,
			];
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
$draftsDir  = sl_admin_drafts_dir();
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
						 data-target="<?php echo htmlspecialchars($img['target']); ?>"
						 data-post-type="<?php echo htmlspecialchars($img['post_type']); ?>"
						 data-post-index="<?php echo (int)$img['post_index']; ?>"
						 data-gallery-index="<?php echo (int)$img['gallery_index']; ?>"
						 data-image-index="<?php echo (int)$img['image_index']; ?>"
						 data-inline-index="<?php echo (int)$img['inline_index']; ?>">

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
								<span class="type-badge type-<?php echo hsc($img['post_type']); ?>"><?php echo hsc(sl_type_label($img['post_type'])); ?></span>
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
							<!-- Caption field — gallery images only; featured/inline images have no caption in this CMS -->
							<?php if ($img['has_caption_field']): ?>
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
							<?php endif; ?>
						</div><!-- /.alt-card-body -->
						<!-- Footer: link to the post editor -->
						<div class="alt-card-footer">
							<span class="slug-cell">/<?php echo htmlspecialchars($img['post_slug']); ?></span>
							<a href="<?php echo $img['edit_url']; ?>" class="table-btn edit-btn small"><?php echo admin_icon('writing', '', 13); ?><?php _e('edit', 'Edit post'); ?></a>
						</div>
					</div><!-- /.alt-card -->
					<?php endforeach; ?>
				</div><!-- /.alt-grid -->
			<?php endif; ?>
<?php
$pageContent = ob_get_clean();

$extraFooterScripts = '<script src="assets/js/alt-text-assistant.js?v='
	. @filemtime(__DIR__ . '/assets/js/alt-text-assistant.js') . '"></script>';

require_once 'includes/layout.php';