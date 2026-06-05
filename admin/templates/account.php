<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

// Load current credentials for display
$admin_username     = 'admin';
$admin_display_name = '';
$admin_email        = '';
$admin_password     = '';
$credFile           = __DIR__ . '/../admin-credentials.php';
if (file_exists($credFile)) {
	include $credFile;
}
if (empty($admin_email)) {
	$admin_email = $appSettings['contact_email'] ?? '';
}
if (empty($admin_display_name)) {
	$admin_display_name = $admin_username;
}
?>

<div class="dashboard-container">

	<div class="tabs" id="account-tabs">
		<div class="tab active" data-tab="profile">👤 &nbsp; <?php _e('account_tab_profile'); ?></div>
		<div class="tab" data-tab="password">🔒 &nbsp; <?php _e('account_tab_password'); ?></div>
	</div>

	<?php /* ── Profile tab ─────────────────────────────────── */ ?>
	<div id="profile-tab" class="tab-content">
		<div class="site-settings-section">
			<h3><?php _e('account_tab_profile'); ?></h3>
			<div class="form-group">
				<label for="admin_username"><?php _e('profile_username_label'); ?>:</label>
				<input type="text" id="admin_username" name="admin_username"
				       value="<?php echo htmlspecialchars($admin_username); ?>"
				       pattern="[a-zA-Z0-9_\-]{3,32}" required>
				<p class="help-text"><?php _e('profile_username_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="admin_display_name"><?php _e('profile_display_name_label'); ?>:</label>
				<input type="text" id="admin_display_name" name="admin_display_name"
				       value="<?php echo htmlspecialchars($admin_display_name); ?>">
				<p class="help-text"><?php _e('profile_display_name_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="admin_email"><?php _e('lbl_contact_email', 'Admin Email'); ?>:</label>
				<input type="email" id="admin_email" name="admin_email"
				       value="<?php echo htmlspecialchars($admin_email); ?>"
				       placeholder="you@example.com">
				<p class="help-text"><?php _e('profile_email_help'); ?></p>
			</div>
			<button type="button" id="save_profile_btn" class="button"><?php _e('save_all_settings'); ?></button>
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
						<?php echo _account_eye_icon(); ?>
					</button>
				</div>
			</div>
			<div class="form-group">
				<label for="new_password"><?php _e('new_password_field'); ?>:</label>
				<div class="password-wrapper">
					<input type="password" id="new_password" autocomplete="new-password">
					<button type="button" class="toggle-password" data-target="new_password" aria-label="Toggle">
						<?php echo _account_eye_icon(); ?>
					</button>
				</div>
				<p class="help-text"><?php _e('new_password_help'); ?></p>
			</div>
			<div class="form-group">
				<label for="confirm_password"><?php _e('confirm_password_field'); ?>:</label>
				<div class="password-wrapper">
					<input type="password" id="confirm_password" autocomplete="new-password">
					<button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle">
						<?php echo _account_eye_icon(); ?>
					</button>
				</div>
			</div>
			<button type="button" id="change_password_btn" class="button"><?php _e('change_password_btn_text'); ?></button>
			<div id="password_change_message"></div>
		</div>
	</div>

</div>

<?php
function _account_eye_icon(): string {
	return '<svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {

	// ── Tab switching ─────────────────────────────────────────────────────────
	document.querySelectorAll('#account-tabs .tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			document.querySelectorAll('#account-tabs .tab').forEach(function (t) { t.classList.remove('active'); });
			document.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
			this.classList.add('active');
			document.getElementById(this.dataset.tab + '-tab').style.display = 'grid';
		});
	});

	// ── Toggle password visibility ────────────────────────────────────────────
	document.querySelectorAll('.toggle-password').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.getElementById(this.dataset.target);
			if (!input) return;
			var hidden = input.type === 'password';
			input.type = hidden ? 'text' : 'password';
			var svg = this.querySelector('svg');
			if (svg) {
				svg.innerHTML = hidden
					? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
					: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
			}
		});
	});

	var csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';

	function showMsg(containerId, message, type) {
		var el = document.getElementById(containerId);
		if (!el) return;
		el.className     = type === 'error' ? 'message error' : 'message success';
		el.textContent   = message;
		el.style.display = 'block';
		setTimeout(function () {
			el.style.opacity = '0';
			setTimeout(function () { el.style.display = 'none'; el.style.opacity = '1'; }, 500);
		}, 5000);
	}

	function xhr(url, data, cb) {
		var req = new XMLHttpRequest();
		req.open('POST', url, true);
		req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		req.onload = function () {
			try { cb(JSON.parse(req.responseText)); }
			catch (e) { cb({ status: 'error', message: t('invalid_server_response') }); }
		};
		req.onerror = function () { cb({ status: 'error', message: t('error_try_again') }); };
		req.send(data);
	}

	// ── Save profile ──────────────────────────────────────────────────────────
	var saveProfileBtn = document.getElementById('save_profile_btn');
	if (saveProfileBtn) {
		saveProfileBtn.addEventListener('click', function () {
			var username    = document.getElementById('admin_username').value.trim();
			var displayName = document.getElementById('admin_display_name').value.trim();
			var email       = document.getElementById('admin_email').value.trim();

			var params = 'save_profile=1'
				+ '&csrf_token='          + encodeURIComponent(csrfToken)
				+ '&admin_username='      + encodeURIComponent(username)
				+ '&admin_display_name='  + encodeURIComponent(displayName)
				+ '&admin_email='         + encodeURIComponent(email);

			xhr('save-profile.php', params, function (resp) {
				showMsg('profile_message', t(resp.message) || resp.message, resp.status);
				if (resp.status === 'success' && resp.display_name) {
					// Update sidebar display name live if the element exists
					var el = document.getElementById('sidebar-display-name');
					if (el) el.textContent = resp.display_name;
				}
			});
		});
	}

	// ── Change password ───────────────────────────────────────────────────────
	var changePwBtn = document.getElementById('change_password_btn');
	if (changePwBtn) {
		changePwBtn.addEventListener('click', function () {
			var current = document.getElementById('current_password').value;
			var newPw   = document.getElementById('new_password').value;
			var confirm = document.getElementById('confirm_password').value;

			if (!current || !newPw || !confirm) {
				showMsg('password_change_message', t('password_fill_all'), 'error'); return;
			}
			if (newPw !== confirm) {
				showMsg('password_change_message', t('passwords_dont_match'), 'error'); return;
			}
			if (!/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/.test(newPw)) {
				showMsg('password_change_message', t('password_too_weak'), 'error'); return;
			}
			if (newPw === current) {
				showMsg('password_change_message', t('password_same_as_current'), 'error'); return;
			}

			var params = 'csrf_token='       + encodeURIComponent(csrfToken)
				+ '&current_password=' + encodeURIComponent(current)
				+ '&new_password='     + encodeURIComponent(newPw)
				+ '&confirm_password=' + encodeURIComponent(confirm);

			xhr('change-password.php', params, function (resp) {
				showMsg('password_change_message', t(resp.message), resp.status);
				if (resp.status === 'success') {
					document.getElementById('current_password').value = '';
					document.getElementById('new_password').value     = '';
					document.getElementById('confirm_password').value = '';
				}
			});
		});
	}
});
</script>
