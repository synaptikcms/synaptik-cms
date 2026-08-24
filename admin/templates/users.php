<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

if (!admin_is_admin()) {
	http_response_code(403);
	echo '<div class="site-settings-section"><p>' . hsc(__t('access_denied', 'Access denied.')) . '</p></div>';
	return;
}

$users        = admin_load_users();
$editUserId   = $_GET['edit'] ?? '';
$editUser     = $editUserId !== '' ? admin_find_user_by_id($editUserId) : null;
$currentId    = admin_current_user_id();
$roleLabels   = [
	'admin'  => __t('users_role_admin', 'Admin'),
	'editor' => __t('users_role_editor', 'Editor'),
	'author' => __t('users_role_author', 'Author'),
];
?>

<?php admin_render_settings_tabs('users', false); ?>

<div class="site-settings-section">
	<h3 style="margin-top:0;"><?php echo $editUser ? hsc(__t('users_edit_title', 'Edit user')) : hsc(__t('users_add_title', 'Add new user')); ?></h3>
	<form method="post" action="manage-users.php">
		<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
		<input type="hidden" name="user_action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
		<?php if ($editUser): ?>
		<input type="hidden" name="user_id" value="<?php echo hsc($editUser['id']); ?>">
		<?php endif; ?>

		<div class="form-group">
			<label for="username"><?php _e('profile_username_label'); ?></label>
			<input type="text" id="username" name="username"
			       value="<?php echo hsc($editUser['username'] ?? ''); ?>"
			       pattern="[a-zA-Z0-9_\-]{3,32}" required>
		</div>
		<div class="form-group">
			<label for="display_name"><?php _e('profile_display_name_label'); ?></label>
			<input type="text" id="display_name" name="display_name"
			       value="<?php echo hsc($editUser['display_name'] ?? ''); ?>">
		</div>
		<div class="form-group">
			<label for="email"><?php _e('lbl_contact_email', 'Email'); ?></label>
			<input type="email" id="email" name="email"
			       value="<?php echo hsc($editUser['email'] ?? ''); ?>"
			       placeholder="you@example.com">
		</div>
		<div class="form-group">
			<label for="password"><?php echo $editUser ? hsc(__t('users_new_password', 'New password (leave blank to keep current)')) : hsc(__t('new_password_field')); ?></label>
			<input type="password" id="password" name="password" autocomplete="new-password" <?php echo $editUser ? '' : 'required'; ?>>
			<p class="help-text"><?php _e('new_password_help'); ?></p>
		</div>
		<div class="form-group">
			<label for="role"><?php _e('users_role_label', 'Role'); ?></label>
			<select id="role" name="role" <?php echo ($editUser && $editUser['id'] === $currentId) ? 'disabled' : ''; ?>>
				<?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
				<option value="<?php echo hsc($roleKey); ?>" <?php echo (($editUser['role'] ?? 'author') === $roleKey) ? 'selected' : ''; ?>><?php echo hsc($roleLabel); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ($editUser && $editUser['id'] === $currentId): ?>
			<input type="hidden" name="role" value="<?php echo hsc($editUser['role']); ?>">
			<p class="help-text"><?php _e('users_cannot_change_own_role', "You can't change your own role."); ?></p>
			<?php endif; ?>
		</div>

		<button class="btn btn-primary" type="submit"><?php echo $editUser ? hsc(__t('save_all_settings')) : hsc(__t('users_add_btn', 'Add user')); ?></button>
		<?php if ($editUser): ?>
		<a href="index.php?action=users" class="btn btn-outline"><?php _e('cancel'); ?></a>
		<?php endif; ?>
	</form>
</div>

<h3 style="margin-top:30px;"><?php _e('users_existing_title', 'Existing users'); ?></h3>
<div class="table-wrap">
	<table id="users-table">
		<thead>
			<tr>
				<th><?php _e('profile_username_label'); ?></th>
				<th><?php _e('profile_display_name_label'); ?></th>
				<th><?php _e('lbl_contact_email', 'Email'); ?></th>
				<th><?php _e('users_role_label', 'Role'); ?></th>
				<th><?php _e('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($users as $u): ?>
			<tr>
				<td>
					<?php echo hsc($u['username']); ?>
					<?php if ($u['id'] === $currentId): ?>
						<span class="badge-orphan"><?php _e('users_you_badge', 'you'); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo hsc($u['display_name'] ?: $u['username']); ?></td>
				<td><?php echo hsc($u['email']); ?></td>
				<td><?php echo hsc($roleLabels[$u['role']] ?? $u['role']); ?></td>
				<td>
					<a href="index.php?action=users&edit=<?php echo urlencode($u['id']); ?>" class="table-btn edit-btn small"><?php _e('edit'); ?></a>
					<?php if ($u['id'] !== $currentId): ?>
					<form method="post" action="manage-users.php" style="display:inline"
					      data-confirm="<?php echo hsc(__t('confirm_delete_user', 'Delete this user? This cannot be undone.')); ?>">
						<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token']); ?>">
						<input type="hidden" name="user_action" value="delete">
						<input type="hidden" name="user_id" value="<?php echo hsc($u['id']); ?>">
						<button type="submit" class="table-btn delete-btn small danger"><?php _e('delete'); ?></button>
					</form>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
