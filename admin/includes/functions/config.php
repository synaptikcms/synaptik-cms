<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function admin_load_config(): array {
	$settings = loadDefaultConfig();

	$configFile = dirname(__DIR__, 3) . '/config.json';
	if (file_exists($configFile)) {
		$loaded = json_decode(file_get_contents($configFile), true);
		if (is_array($loaded)) {
			$settings = array_merge($settings, $loaded);
			if (!empty($settings['timezone'])) {
				@date_default_timezone_set($settings['timezone']);
			}
		}
	}

	$settings['available_themes'] = function_exists('getAvailableThemes') ? getAvailableThemes() : ['default'];

	return $settings;
}

function admin_add_custom_field(string $type, string $label, string $fieldType): array|false {
	if (!sl_content_type_exists($type)) return false;

	$label = trim($label);
	if ($label === '') return false;

	$allowedTypes = ['text', 'textarea', 'number', 'url', 'checkbox'];
	if (!in_array($fieldType, $allowedTypes, true)) $fieldType = 'text';

	$baseKey = sanitizeSlug($label);
	if ($baseKey === '') return false;

	$config = admin_load_config();
	$schema = $config['custom_fields_schema'][$type] ?? [];

	$existingKeys = array_column($schema, 'key');
	$key = $baseKey;
	$i = 2;
	while (in_array($key, $existingKeys, true)) {
		$key = $baseKey . '_' . $i;
		$i++;
	}

	$field = [
		'key'      => $key,
		'label'    => hsc($label),
		'type'     => $fieldType,
		'required' => false,
	];

	$schema[] = $field;
	$config['custom_fields_schema'][$type] = $schema;

	if (!sl_admin_save_config($config)) return false;

	return $field;
}

function admin_get_pinned_plugins(): array {
	$config = admin_load_config();
	$pinned = $config['pinned_plugins'] ?? [];
	return is_array($pinned) ? array_values(array_unique($pinned)) : [];
}

function admin_set_plugin_pinned(string $slug, bool $pinned): bool {
	$config = admin_load_config();
	$current = is_array($config['pinned_plugins'] ?? null) ? $config['pinned_plugins'] : [];

	if ($pinned) {
		if (!in_array($slug, $current, true)) $current[] = $slug;
	} else {
		$current = array_values(array_diff($current, [$slug]));
	}

	$config['pinned_plugins'] = $current;

	return sl_admin_save_config($config);
}

function admin_format_date($date) {
	if (empty($date)) return '';

	$appSettings = admin_load_config();
	$format      = $appSettings['date_format'] ?? 'Y-m-d';

	$timestamp = strtotime($date);
	if ($timestamp === false) return $date;

	return date($format, $timestamp);
}

function admin_format_time($date) {
	if (empty($date)) return '';

	if (!preg_match('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $date)) return '';

	$timestamp = strtotime($date);
	if ($timestamp === false) return '';

	return date('H:i', $timestamp);
}

function admin_extract_time(string $date = '', bool $defaultNow = false): string {
	if (!empty($date) && preg_match('/\d{4}-\d{2}-\d{2}[T ](?P<t>\d{2}:\d{2})/', $date, $m)) {
		return $m['t'];
	}
	return $defaultNow ? date('H:i') : '';
}

function admin_diff_lines(string $old, string $new): ?array {
	if ($old === $new) return [];

	$oldLines = $old === '' ? [] : explode("\n", $old);
	$newLines = $new === '' ? [] : explode("\n", $new);
	$m = count($oldLines);
	$n = count($newLines);

	if ($m * $n > 4_000_000) return null;

	$lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
	for ($i = $m - 1; $i >= 0; $i--) {
		for ($j = $n - 1; $j >= 0; $j--) {
			$lcs[$i][$j] = $oldLines[$i] === $newLines[$j]
				? $lcs[$i + 1][$j + 1] + 1
				: max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
		}
	}

	$result = [];
	$i = 0; $j = 0;
	while ($i < $m && $j < $n) {
		if ($oldLines[$i] === $newLines[$j]) {
			$result[] = ['type' => 'same', 'text' => $oldLines[$i]];
			$i++; $j++;
		} elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
			$result[] = ['type' => 'removed', 'text' => $oldLines[$i]];
			$i++;
		} else {
			$result[] = ['type' => 'added', 'text' => $newLines[$j]];
			$j++;
		}
	}
	while ($i < $m) { $result[] = ['type' => 'removed', 'text' => $oldLines[$i]]; $i++; }
	while ($j < $n) { $result[] = ['type' => 'added', 'text' => $newLines[$j]]; $j++; }

	return $result;
}

