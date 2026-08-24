<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Load current logged-in user's record for display
$currentUser         = admin_find_user_by_id((string)admin_current_user_id());
$admin_username      = $currentUser['username']     ?? '';
$admin_display_name  = $currentUser['display_name'] ?? '';
$admin_email         = $currentUser['email']        ?? '';
if (empty($admin_display_name)) {
	$admin_display_name = $admin_username;
}
$admin_role       = $currentUser['role'] ?? 'author';
$admin_role_label = [
	'admin'  => __t('users_role_admin', 'Admin'),
	'editor' => __t('users_role_editor', 'Editor'),
	'author' => __t('users_role_author', 'Author'),
][$admin_role] ?? $admin_role;
?>

<div class="dashboard-container">

	<div class="tabs" id="account-tabs">
		<div class="tab active" data-tab="profile"><?php echo admin_icon('account', 'style="vertical-align:-2px;margin-right:5px"', 14); ?><?php _e('account_tab_profile'); ?></div>
		<div class="tab" data-tab="password"><?php echo admin_icon('lock', 'style="vertical-align:-2px;margin-right:5px"', 14); ?><?php _e('account_tab_password'); ?></div>
	</div>

	<?php /* ── Profile tab ─────────────────────────────────── */ ?>
	<div id="profile-tab" class="tab-content">
		<div class="site-settings-section">
			<h3><?php _e('account_tab_profile'); ?> <span class="badge-role badge-role-<?php echo hsc($admin_role); ?>"><?php echo hsc($admin_role_label); ?></span></h3>
			<div class="form-group">
				<label for="admin_username"><?php _e('profile_username_label'); ?>:</label>
				<input type="text" id="admin_username" name="admin_username"
				       value="<?php echo hsc($admin_username); ?>"
				       pattern="[a-zA-Z0-9_\-]{3,32}" required>
				<p class="help-text"><?php _e('profile_username_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="admin_display_name"><?php _e('profile_display_name_label'); ?>:</label>
				<input type="text" id="admin_display_name" name="admin_display_name"
				       value="<?php echo hsc($admin_display_name); ?>">
				<p class="help-text"><?php _e('profile_display_name_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="admin_email"><?php _e('lbl_contact_email', 'Admin Email'); ?>:</label>
				<input type="email" id="admin_email" name="admin_email"
				       value="<?php echo hsc($admin_email); ?>"
				       placeholder="you@example.com">
				<p class="help-text"><?php _e('profile_email_help'); ?></p>
			</div>
			<button type="button" id="save_profile_btn" class="btn btn-primary"><?php _e('save_all_settings'); ?></button>
			<div id="profile_message"></div>
		</div>
	</div>

	<?php /* ── Password tab ─────────────────────────────────── */ ?>
	<div id="password-tab" class="tab-content" style="display:none;">
		<div class="site-settings-section">
			<h3><?php _e('change_password_title'); ?></h3>
			<div class="form-group">
				<label for="current_password"><?php _e('current_password_field'); ?>:</label>
				<div class="password-wrapper">
					<input type="password" id="current_password" autocomplete="current-password">
					<button type="button" class="toggle-password" data-target="current_password" aria-label="Toggle">
						<?php echo admin_icon('eye', '', 18, 'eye-icon'); ?>
					</button>
				</div>
			</div>
			<div class="form-group">
				<label for="new_password"><?php _e('new_password_field'); ?>:</label>
				<div class="password-wrapper">
					<input type="password" id="new_password" autocomplete="new-password">
					<button type="button" class="toggle-password" data-target="new_password" aria-label="Toggle">
						<?php echo admin_icon('eye', '', 18, 'eye-icon'); ?>
					</button>
				</div>
				<p class="help-text"><?php _e('new_password_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="confirm_password"><?php _e('confirm_password_field'); ?>:</label>
				<div class="password-wrapper">
					<input type="password" id="confirm_password" autocomplete="new-password">
					<button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle">
						<?php echo admin_icon('eye', '', 18, 'eye-icon'); ?>
					</button>
				</div>
			</div>
			<button type="button" id="change_password_btn" class="btn btn-primary"><?php _e('change_password_btn_text'); ?></button>
			<div id="password_change_message"></div>
		</div>
	</div>

</div>

<script src="assets/js/account.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/account.js'); ?>"></script>
