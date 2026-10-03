<?php
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

function admin_render_template(string $relativePath, array $vars = []): void
{
	extract($vars, EXTR_SKIP);
	include $relativePath;
}
