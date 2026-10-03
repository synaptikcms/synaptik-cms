<?php

function _sl_page_cache_dir(): string
{
    $dir = _sl_cache_dir() . '/pages';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function _sl_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
}

function _sl_configured_canonical_host(): string
{
    if (function_exists('loadConfig')) {
        $configured = trim((string)(loadConfig()['canonical_host'] ?? ''));
    } elseif (function_exists('admin_load_config')) {
        $configured = trim((string)(admin_load_config()['canonical_host'] ?? ''));
    } else {
        $configured = '';
    }
    if ($configured === '') return '';

    static $normalized = [];
    if (isset($normalized[$configured])) return $normalized[$configured];

    $host = preg_replace('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', '', $configured);
    $host = rtrim((string)strtok($host, '/'), '/');
    $host = preg_match('/^(\[[0-9a-fA-F:]+\]|[a-zA-Z0-9.-]+)(:\d{1,5})?$/', $host)
        ? strtolower($host)
        : '';

    return $normalized[$configured] = $host;
}

function _sl_raw_request_host(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(\[[0-9a-fA-F:]+\]|[a-zA-Z0-9.-]+)(:\d{1,5})?$/', $host)) return '';
    return strtolower($host);
}

function _sl_request_host(): string
{
    $configured = _sl_configured_canonical_host();
    if ($configured !== '') return $configured;

    return _sl_raw_request_host();
}

function _sl_page_cache_host_allowed(): bool
{
    $canonical = _sl_configured_canonical_host();
    if ($canonical === '') return true;

    return _sl_raw_request_host() === $canonical;
}

function _sl_canonical_host_redirect_target(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return null;
    if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) return null;

    $canonical = _sl_configured_canonical_host();
    $raw = _sl_raw_request_host();
    if ($canonical === '' || $raw === '' || $raw === $canonical) return null;

    $scheme = _sl_request_is_https() ? 'https' : 'http';
    return $scheme . '://' . $canonical . ($_SERVER['REQUEST_URI'] ?? '/');
}

function _sl_force_https_enabled(): bool
{
    if (function_exists('loadConfig')) {
        return !empty(loadConfig()['force_https']);
    }
    if (function_exists('admin_load_config')) {
        return !empty(admin_load_config()['force_https']);
    }
    return false;
}

function sl_enforce_https(): void
{
    if (!_sl_force_https_enabled()) return;

    if (_sl_request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
        return;
    }

    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) return;

    $host = preg_replace('/:\d+$/', '', _sl_request_host());
    if ($host === '' || $host === 'localhost' || $host === '[::1]' || preg_match('/^127\./', $host) || preg_match('/\.(localhost|test)$/', $host)) return;

    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

function _sl_page_cache_file(string $urlPath, string $lang): string
{
    $scheme = _sl_request_is_https() ? 'https' : 'http';
    $host   = _sl_request_host();
    return _sl_page_cache_dir() . '/' . sha1($lang . '|' . $scheme . '://' . $host . '|' . $urlPath) . '.page.php';
}

function _sl_content_signature_path(): string
{
    return _sl_cache_dir() . '/.signature';
}

function sl_content_signature(): string
{
    $stored = @file_get_contents(_sl_content_signature_path());
    if ($stored !== false && $stored !== '') return trim($stored);
    return sl_bump_content_signature();
}

function sl_bump_content_signature(): string
{
    $dir = _sl_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $newSig = bin2hex(random_bytes(16));
    $path   = _sl_content_signature_path();
    $tmp    = $path . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $newSig, LOCK_EX) !== false) {
        @rename($tmp, $path);
    }
    return $newSig;
}

function sl_page_signature(): string
{
    static $sig = null;
    if ($sig !== null) return $sig;

    $parts = [];

    $configPath = CMS_ROOT . '/config.json';
    if (file_exists($configPath)) {
        $parts[] = 'config.json:' . filemtime($configPath) . ':' . filesize($configPath);
    }

    $parts[] = 'content:' . sl_content_signature();

    $activeTheme = loadConfig()['active_theme'] ?? 'default';
    foreach ([
        CMS_ROOT . '/theme/' . $activeTheme,
        CMS_ROOT . '/theme/child_theme/' . $activeTheme,
    ] as $themeDir) {
        if (!is_dir($themeDir)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $parts[] = $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
        }
    }

    sort($parts);
    $sig = hash('xxh3', implode('|', $parts));
    return $sig;
}

function sl_page_cache_get(string $urlPath, string $lang, ?int &$status = null): ?string
{
    $file = _sl_page_cache_file($urlPath, $lang);
    if (!file_exists($file)) return null;

    $cached = @include $file;
    if (!is_array($cached) || !isset($cached['sig'], $cached['html'], $cached['status'])) return null;
    if ($cached['sig'] !== sl_page_signature()) return null;

    $status = $cached['status'];
    return $cached['html'];
}

function sl_page_cache_set(string $urlPath, string $lang, int $status, string $html): void
{
    $dir = _sl_page_cache_dir();
    if (!is_writable($dir)) return;

    $file = _sl_page_cache_file($urlPath, $lang);
    $tmp  = $file . '.' . getmypid() . '.tmp';
    $payload = [
        'sig'    => sl_page_signature(),
        'status' => $status,
        'html'   => $html,
    ];
    if (file_put_contents($tmp, "<?php\nreturn " . var_export($payload, true) . ";\n", LOCK_EX) !== false) {
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}

