<?php
if (!defined('INCLUDED')) define('INCLUDED', true);
require_once dirname(__DIR__) . '/admin-icons.php';
require_once dirname(__DIR__) . '/content-purify.php';

if (!function_exists('hsc')) {
    function hsc(?string $s, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $enc = 'UTF-8'): string
    {
        return htmlspecialchars((string) ($s ?? ''), $flags, $enc);
    }
}

if (!function_exists('themePreviewSecret')) {
    function themePreviewSecret(): string {
        $secretFile = dirname(__DIR__, 3) . '/private/theme_preview.secret';
        if (file_exists($secretFile)) {
            $secret = file_get_contents($secretFile);
            if ($secret !== false && strlen(trim($secret)) >= 32) return trim($secret);
        }
        $secret = bin2hex(random_bytes(32));
        @file_put_contents($secretFile, $secret, LOCK_EX);
        return $secret;
    }
}

if (!function_exists('sl_type_label')) {
    function sl_type_label(string $type, bool $plural = false): string {
        $settings = admin_load_config();
        $labels   = $settings['type_labels'][$type] ?? [];
        $override = $labels[$plural ? 'plural' : 'singular'] ?? '';
        if ($override !== '') return $override;
        $fallback = $labels[$plural ? 'singular' : 'plural'] ?? '';
        if ($fallback !== '') return $fallback;
        $registryLabel = sl_type_registry_label($type, $plural);
        if ($registryLabel !== '') return $registryLabel;
        return __t($plural ? $type . 's' : $type, ucfirst($type));
    }
}

if (!function_exists('sanitizeFileName')) {
	function sanitizeFileName($filename) {
		$filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
		$filename = substr($filename, 0, 255);
		if (empty($filename)) {
			$filename = 'unnamed_file_' . time();
		}
		return $filename;
	}
}

if (!function_exists('sanitizeSlug')) {
	function sanitizeSlug($string) {
		$string = trim($string);
		$accents = [
			'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
			'Ç'=>'C',
			'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
			'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
			'Ð'=>'D','Ñ'=>'N',
			'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
			'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
			'Ý'=>'Y','Þ'=>'TH','ß'=>'ss',
			'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
			'ç'=>'c',
			'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
			'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
			'ð'=>'d','ñ'=>'n',
			'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
			'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
			'ý'=>'y','þ'=>'th','ÿ'=>'y',
			'œ'=>'oe','Œ'=>'OE','Ÿ'=>'Y',
		];
		$string = strtr($string, $accents);
		$string = strtolower($string);
		$string = preg_replace('/\s+/', '-', $string);
		$string = preg_replace('/[^a-z0-9\-_]/', '', $string);
		$string = preg_replace('/-+/', '-', $string);
		$string = trim($string, '-_');
		return $string;
	}
}

if (!function_exists('sl_resolve_taxonomy_slug')) {
	function sl_resolve_taxonomy_slug(string $requestedSlug, string $name, array $existingSlugs, ?string $excludeSlug = null): string {
		$base = trim($requestedSlug) !== '' ? sanitizeSlug($requestedSlug) : sanitizeSlug($name);
		$existingSlugs = array_values(array_diff($existingSlugs, $excludeSlug !== null ? [$excludeSlug] : []));
		if (!in_array($base, $existingSlugs, true)) {
			return $base;
		}
		$n = 2;
		while (in_array($base . '-' . $n, $existingSlugs, true)) $n++;
		return $base . '-' . $n;
	}
}

