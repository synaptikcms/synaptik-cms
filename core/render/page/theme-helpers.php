<?php

function getThemeResourcePath($resource, $file = '')
{
    $theme = loadConfig()['active_theme'] ?? 'default';
    return "theme/{$theme}/{$resource}/{$file}";
}

function loadThemePartial(string $name, array $vars = []): ?string
{
    $theme = loadConfig()['active_theme'] ?? 'default';
    $path  = CMS_ROOT . '/theme/child_theme/' . $theme . '/partials/' . $name . '.php';
    if (!file_exists($path)) {
        $path = CMS_ROOT . '/theme/' . $theme . '/partials/' . $name . '.php';
    }
    if (!file_exists($path)) {
        return null;
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $path;
    return ob_get_clean();
}

