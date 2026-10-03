<?php

function _sl_write_json(string $path, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) return false;

    $tmp = $path . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;

    $ok = rename($tmp, $path);
    if ($ok && function_exists('sl_bump_content_signature')) {
        sl_bump_content_signature();
    }
    return $ok;
}

function sl_admin_save_config(array $config): bool
{
    $config['admin_dir'] = resolve_admin_dir();

    $ok = _sl_write_json(CMS_ROOT . '/config.json', $config);
    if ($ok && function_exists('loadConfig_invalidate')) {
        loadConfig_invalidate();
    }
    return $ok;
}

