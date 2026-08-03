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
}
