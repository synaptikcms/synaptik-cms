<?php

const SL_ACTIVITY_LOG_MAX_ENTRIES = 2000;
function sl_admin_activity_log_path(): string
{
    return CMS_ROOT . '/private/activity-log.json';
}

function sl_admin_log_activity(string $action, string $details = ''): bool
{
    $path = sl_admin_activity_log_path();

    if (!file_exists($path)) {
        @file_put_contents($path, '[]', LOCK_EX);
    }

    $fp = @fopen($path, 'c+');
    if (!$fp) return false;

    flock($fp, LOCK_EX);

    $raw     = stream_get_contents($fp);
    $entries = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (!is_array($entries)) $entries = [];

    $entries[] = [
        'ts'       => time(),
        'user_id'  => $_SESSION['admin_user_id']  ?? null,
        'username' => $_SESSION['admin_username'] ?? '',
        'action'   => $action,
        'details'  => $details,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    if (count($entries) > SL_ACTIVITY_LOG_MAX_ENTRIES) {
        $entries = array_slice($entries, -SL_ACTIVITY_LOG_MAX_ENTRIES);
    }

    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok   = false;
    if ($json !== false) {
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, $json) !== false;
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return $ok;
}

function sl_admin_load_activity_log(): array
{
    $path = sl_admin_activity_log_path();
    if (!file_exists($path)) return [];
    $raw     = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

