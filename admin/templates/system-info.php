<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Returns true if OPcache is enabled and actually running for the current request.
 * Tries opcache_get_status() first (reflects real runtime state, e.g. catches
 * a module that failed to initialize despite opcache.enable=1). Falls back to
 * the opcache.enable ini directive if the status function is unavailable —
 * some shared hosts (e.g. OVH) block opcache_* functions via disable_functions
 * or opcache.restrict_api_key while OPcache itself runs normally.
 */
function sysinfo_opcache_enabled(): bool {
	if (!extension_loaded('Zend OPcache')) return false;

	if (function_exists('opcache_get_status')) {
		$status = @opcache_get_status(false);
		if (is_array($status)) {
			return !empty($status['opcache_enabled']);
		}
	}

	return filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
}

if (!isset($appSettings)) {
	$appSettings = admin_load_config();
}

/**
 * Total on-disk size of the site (cached, since walking the whole install
 * root is expensive) — same 5-minute cache pattern as the dashboard's
 * media-stats cache.
 */
function sysinfo_site_size(string $root, string $cacheFile): int {
	if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
		$cached = json_decode(file_get_contents($cacheFile), true);
		if (is_array($cached) && isset($cached['size'])) {
			return (int)$cached['size'];
		}
	}

	$size = 0;
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
	);
	foreach ($iter as $f) {
		if ($f->isFile()) $size += $f->getSize();
	}

	if (is_writable(dirname($cacheFile))) {
		file_put_contents($cacheFile, json_encode(['size' => $size]), LOCK_EX);
	}

	return $size;
}

$_si_isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

$_si_root        = dirname(__DIR__, 2);
$_si_installExists = file_exists($_si_root . '/install.php');
$_si_installLocked = file_exists($_si_root . '/install.lock');

$_si_adminDir      = $appSettings['admin_dir'] ?? 'admin';
$_si_adminRenamed  = $_si_adminDir !== 'admin';

// Same probe used by the dashboard's htaccess warning banner — reads the
// same 1-hour cache, so listing it again here costs nothing extra.
$_si_htaccessCache = dirname(__DIR__) . '/cache/htaccess-check.json';
$_si_htaccessOk    = null; // null = not yet probed (cache miss and no way to probe cheaply here)
if (file_exists($_si_htaccessCache)) {
	$_si_cached = json_decode(file_get_contents($_si_htaccessCache), true);
	if (is_array($_si_cached) && isset($_si_cached['ok'])) {
		$_si_htaccessOk = (bool)$_si_cached['ok'];
	}
}

$_si_siteSizeCache = dirname(__DIR__) . '/cache/site-size.json';
$_si_siteSize      = sysinfo_site_size($_si_root, $_si_siteSizeCache);
?>

<div class="content-header">
	<h1><?php echo admin_icon('info'); ?> <?php _e('system_information'); ?></h1>
</div>

<div class="dashboard-columns">

	<div class="dashboard-column">
		<div class="site-settings-section">
			<h3><?php _e('sysinfo_environment'); ?></h3>
			<div class="system-info">
				<div class="info-item">
					<div class="info-label"><strong><?php _e('php_version'); ?>:</strong></div>
					<div class="info-value"><?php echo phpversion(); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('opcache_support'); ?>:</strong></div>
					<div class="info-value"><?php echo sysinfo_opcache_enabled() ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('apcu_support'); ?>:</strong></div>
					<div class="info-value"><?php echo function_exists('apcu_fetch') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('gd_library'); ?>:</strong></div>
					<div class="info-value"><?php echo function_exists('gd_info') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('webp_support'); ?>:</strong></div>
					<div class="info-value"><?php echo function_exists('imagewebp') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('zip_support'); ?>:</strong></div>
					<div class="info-value"><?php echo class_exists('ZipArchive') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('mbstring_support'); ?>:</strong></div>
					<div class="info-value"><?php echo extension_loaded('mbstring') ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('active_theme'); ?>:</strong></div>
					<div class="info-value"><strong><?php echo hsc(ucfirst($appSettings['active_theme'] ?? 'default')); ?></strong></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('site_size'); ?>:</strong></div>
					<div class="info-value"><?php echo hsc(admin_format_file_size($_si_siteSize)); ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="dashboard-column">
		<div class="site-settings-section">
			<h3><?php _e('sysinfo_security'); ?></h3>
			<div class="system-info">
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_https'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_isHttps ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_session_cookie'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_isHttps ? __t('sysinfo_session_cookie_secure', 'Secure, HttpOnly, SameSite=Lax') : __t('sysinfo_session_cookie_insecure', 'HttpOnly, SameSite=Lax (Secure requires HTTPS)'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_install_php'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_installExists ? __t('sysinfo_install_php_present', '⚠ Still present — remove after installation') : __t('sysinfo_install_php_removed', '✓ Removed'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_install_lock'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_installLocked ? __t('enabled') : __t('disabled'); ?></div>
				</div>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_admin_dir'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_adminRenamed ? __t('sysinfo_admin_dir_renamed', '✓ Renamed from default') : __t('sysinfo_admin_dir_default', '⚠ Using default folder name'); ?></div>
				</div>
				<?php if ($_si_htaccessOk !== null): ?>
				<div class="info-item">
					<div class="info-label"><strong><?php _e('sysinfo_protected_dirs'); ?>:</strong></div>
					<div class="info-value"><?php echo $_si_htaccessOk ? __t('sysinfo_protected_dirs_ok', '✓ Blocked') : __t('sysinfo_protected_dirs_warn', '⚠ Publicly reachable'); ?></div>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

</div>
