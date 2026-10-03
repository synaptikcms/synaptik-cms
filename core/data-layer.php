<?php
if (!defined('CMS_ROOT')) define('CMS_ROOT', dirname(__DIR__));

if (defined('SL_DATA_LAYER_LOADED')) return;
define('SL_DATA_LAYER_LOADED', true);
define('SL_CACHE_TTL', 60);

require_once __DIR__ . '/data/cache.php';
require_once __DIR__ . '/data/page-cache.php';
require_once __DIR__ . '/data/item-store.php';
require_once __DIR__ . '/data/taxonomy-store.php';
require_once __DIR__ . '/data/type-registry.php';
require_once __DIR__ . '/data/misc.php';
