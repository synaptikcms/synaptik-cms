<?php
$files = [
    __DIR__ . '/search.css',
    __DIR__ . '/shortcodes.css',
    __DIR__ . '/gallery-layout.css',
];

// ── ETag / 304 handling ───────────────────────────────────────
$lastModified = filemtime(__FILE__);
foreach ($files as $f) {
    if (file_exists($f)) {
        $lastModified = max($lastModified, filemtime($f));
    }
}

$etag = '"' . md5($lastModified) . '"';

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);

// Return 304 Not Modified if the client already has this version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
    http_response_code(304);
    exit;
}

// ── Output combined CSS ───────────────────────────────────────
foreach ($files as $f) {
    if (file_exists($f)) {
        echo '/* ' . basename($f) . ' */' . "\n";
        echo file_get_contents($f) . "\n";
    }
}
?>
.sl-logo-dark { display: none; }
[data-theme="dark"] .sl-logo-light { display: none; }
[data-theme="dark"] .sl-logo-dark { display: inline-block; }
/* Themes with no JS toggle (e.g. sudo, shebang) never set [data-theme] and
   switch purely off the OS preference — mirror that here. Guarded on
   :not([data-theme="light"]) so an explicit toggle choice on themes that do
   set the attribute always wins over the OS preference. */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) .sl-logo-light { display: none; }
  :root:not([data-theme="light"]) .sl-logo-dark { display: inline-block; }
}