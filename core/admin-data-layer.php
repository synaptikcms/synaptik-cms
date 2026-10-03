<?php
if (defined('SL_ADMIN_LAYER_LOADED')) return;
define('SL_ADMIN_LAYER_LOADED', true);

require_once __DIR__ . '/admin/bootstrap.php';
require_once __DIR__ . '/admin/media.php';
require_once __DIR__ . '/admin/writer.php';
require_once __DIR__ . '/admin/content-types.php';
require_once __DIR__ . '/admin/activity-log.php';
require_once __DIR__ . '/admin/items.php';
require_once __DIR__ . '/admin/trash.php';
require_once __DIR__ . '/admin/revisions.php';
require_once __DIR__ . '/admin/index-store.php';
