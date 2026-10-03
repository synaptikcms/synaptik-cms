<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit(__t('direct_access_denied'));
}

$contentTypes = sl_content_types();
?>

<div class="sitemap-content">
	<div class="site-settings-section">
		<h3 style="margin-top:0;"><?php _e('add_content_type', 'Add a content type'); ?></h3>
		<p class="help-text"><?php _e('content_type_add_help', 'Creates a new content type, routable and manageable like Articles or Pages.'); ?></p>
		<form method="post" action="index.php?action=manage_content_types">
			<input type="hidden" name="content_type_action" value="add">
			<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
			<div class="form-group">
				<label for="content_type_label_singular"><?php _e('content_type_label_singular', 'Name'); ?></label>
				<input type="text" id="content_type_label_singular" name="content_type_label_singular" maxlength="60" required placeholder="<?php echo hsc(__t('content_type_label_singular_placeholder', 'Recipe')); ?>">
			</div>
			<div class="form-group">
				<label for="content_type_label_plural"><?php _e('content_type_label_plural', 'Plural name'); ?></label>
				<input type="text" id="content_type_label_plural" name="content_type_label_plural" maxlength="60" placeholder="<?php echo hsc(__t('content_type_label_plural_placeholder', 'Recipes')); ?>">
				<p class="help-text"><?php _e('content_type_name_help', 'Leave the plural empty if it is the same as the name. URLs are generated from the names.'); ?></p>
			</div>
			<details class="form-group admin-details">
				<summary><?php _e('content_type_advanced', 'Advanced options'); ?></summary>
				<div class="form-group">
					<label for="content_type_slug"><?php _e('content_type_identifier', 'Identifier'); ?></label>
					<input type="text" id="content_type_slug" name="content_type_slug" placeholder="<?php echo hsc(__t('content_type_auto_placeholder', 'Generated from the name')); ?>">
					<p class="help-text"><?php _e('content_type_identifier_help', 'Internal name of the type (folder name). Lowercase letters, numbers and hyphens.'); ?></p>
				</div>
				<div class="form-group">
					<label for="content_type_url_base"><?php _e('content_type_url_base', 'URL of an item'); ?></label>
					<input type="text" id="content_type_url_base" name="content_type_url_base" placeholder="<?php echo hsc(__t('content_type_auto_placeholder', 'Generated from the name')); ?>">
				</div>
				<div class="form-group">
					<label for="content_type_url_base_plural"><?php _e('content_type_url_base_plural', 'URL of the list'); ?></label>
					<input type="text" id="content_type_url_base_plural" name="content_type_url_base_plural" placeholder="<?php echo hsc(__t('content_type_auto_placeholder', 'Generated from the name')); ?>">
				</div>
			</details>
			<button class="btn btn-primary" type="submit"><?php _e('add_content_type_btn', 'Add content type'); ?></button>
		</form>
	</div>
</div>

<h3 style="margin-top:30px;"><?php _e('existing_content_types', 'Existing content types'); ?></h3>
<div class="table-wrap">
	<table>
		<thead>
			<tr>
				<th><?php _e('content_type_label_singular', 'Name'); ?></th>
				<th><?php _e('content_type_col_plural', 'Plural'); ?></th>
				<th><?php _e('content_type_col_urls', 'URLs'); ?></th>
				<th><?php _e('item_count'); ?></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($contentTypes as $ctSlug => $ctDef):
				$ctCount = count($data[$ctSlug] ?? []);
				$ctUrlSingle = sl_type_url_slug($ctSlug, false);
				$ctUrlPlural = sl_type_url_slug($ctSlug, true);
				$ctFormId = 'ctf-' . $ctSlug;
			?>
			<tr>
				<?php if (empty($ctDef['built_in'])): ?>
				<td>
					<form id="<?php echo hsc($ctFormId); ?>" method="post" action="index.php?action=manage_content_types">
						<input type="hidden" name="content_type_action" value="edit">
						<input type="hidden" name="content_type_slug" value="<?php echo hsc($ctSlug); ?>">
						<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
					</form>
					<input form="<?php echo hsc($ctFormId); ?>" type="text" name="content_type_label_singular" value="<?php echo hsc(sl_type_label($ctSlug, false)); ?>" maxlength="60" required style="width:150px;">
				</td>
				<td><input form="<?php echo hsc($ctFormId); ?>" type="text" name="content_type_label_plural" value="<?php echo hsc(sl_type_label($ctSlug, true)); ?>" maxlength="60" style="width:150px;"></td>
				<td>
					<details class="admin-details">
						<summary><code class="slug-display">/<?php echo hsc($ctUrlSingle); ?>/</code> <code class="slug-display">/<?php echo hsc($ctUrlPlural); ?>/</code></summary>
						<div class="admin-details-fields">
							<label><?php _e('content_type_identifier', 'Identifier'); ?>
								<input form="<?php echo hsc($ctFormId); ?>" type="text" name="content_type_new_slug" value="<?php echo hsc($ctSlug); ?>" required>
							</label>
							<label><?php _e('content_type_url_base', 'URL of an item'); ?>
								<input form="<?php echo hsc($ctFormId); ?>" type="text" name="content_type_url_base" value="<?php echo hsc($ctUrlSingle); ?>">
							</label>
							<label><?php _e('content_type_url_base_plural', 'URL of the list'); ?>
								<input form="<?php echo hsc($ctFormId); ?>" type="text" name="content_type_url_base_plural" value="<?php echo hsc($ctUrlPlural); ?>">
							</label>
						</div>
					</details>
				</td>
				<?php else: ?>
				<td><?php echo hsc(sl_type_label($ctSlug, false)); ?></td>
				<td><?php echo hsc(sl_type_label($ctSlug, true)); ?></td>
				<td><code class="slug-display">/<?php echo hsc($ctUrlSingle); ?>/</code> <code class="slug-display">/<?php echo hsc($ctUrlPlural); ?>/</code></td>
				<?php endif; ?>
				<td><?php echo $ctCount; ?></td>
				<td>
					<?php if (empty($ctDef['built_in'])): ?>
						<button form="<?php echo hsc($ctFormId); ?>" type="submit" class="btn btn-outline btn-sm"><?php _e('save'); ?></button>
						<?php if ($ctCount === 0): ?>
						<form method="post" action="index.php?action=manage_content_types" style="display:inline;" data-confirm="<?php echo hsc(__t('confirm_delete_content_type', 'Delete this content type?')); ?>">
							<input type="hidden" name="content_type_action" value="delete">
							<input type="hidden" name="content_type_slug" value="<?php echo hsc($ctSlug); ?>">
							<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
							<button type="submit" class="table-btn delete-btn small danger"><?php echo admin_icon('trash', '', 14); ?></button>
						</form>
						<?php else: ?>
						<span class="help-text" style="display:block;"><?php _e('content_type_not_empty', 'This content type still has content — remove it first.'); ?></span>
						<?php endif; ?>
					<?php else: ?>
						<span style="opacity:.4"><?php _e('content_type_builtin', 'Built-in'); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
