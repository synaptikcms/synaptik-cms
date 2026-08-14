<?php
/**
 * Admin Session Config
 * Must be require'd BEFORE session_start() in every admin entry point.
 */

if (defined('ADMIN_SESSION_CONFIGURED')) return;
define('ADMIN_SESSION_CONFIGURED', true);

if (session_status() === PHP_SESSION_NONE) {
    $__installId = substr(hash('sha256', dirname(__DIR__, 2)), 0, 12);
    session_name('snkadm_' . $__installId);
    unset($__installId);

    // Enforce secure cookie parameters in PHP code so they apply under
    // both mod_php and PHP-FPM. The .htaccess php_value directives are
    // mod_php only and silently ignored under FPM.
    $__secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $__secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    unset($__secure);
}
