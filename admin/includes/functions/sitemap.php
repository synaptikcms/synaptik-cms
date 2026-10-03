<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit;
}

function sm_sitemap_path(): string
{
    return CMS_ROOT . '/sitemap.xml';
}

function sm_exclusions_path(): string
{
    return sl_data_dir() . '/sitemap-exclusions.json';
}

function sm_load_exclusions(): array
{
    $path = sm_exclusions_path();
    if (!file_exists($path)) return [];
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
}

function sm_normalize_exclusion(string $raw, string $baseUrl): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    if (strpos($raw, '://') !== false) {
        $raw = parse_url($raw, PHP_URL_PATH) ?: '';
    }
    $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '';
    if ($basePath !== '' && strpos($raw, $basePath) === 0) {
        $raw = substr($raw, strlen($basePath));
    } elseif (strpos($raw, $baseUrl) === 0) {
        $raw = substr($raw, strlen($baseUrl));
    }
    return trim($raw, '/');
}

function sm_base_url(): string
{
    $protocol = _sl_request_is_https() ? 'https' : 'http';
    $domain   = _sl_request_host();
    $baseDir  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $protocol . '://' . $domain . $baseDir;
}

function sm_atomic_write(string $path, string $contents): bool
{
    $tmp = $path . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) return false;
    return rename($tmp, $path);
}

function sm_ensure_robots_sitemap_line(string $baseUrl): void
{
    $robotsPath = CMS_ROOT . '/robots.txt';
    $existing   = file_exists($robotsPath) ? file_get_contents($robotsPath) : '';
    if ($existing === false) $existing = '';
    if (stripos($existing, 'Sitemap:') !== false) return;

    $newContent = rtrim($existing);
    $newContent = ($newContent !== '' ? $newContent . "\n\n" : '') . 'Sitemap: ' . $baseUrl . '/sitemap.xml' . "\n";
    sm_atomic_write($robotsPath, $newContent);
}

function sm_item_lastmod(array $item): string
{
    $raw = !empty($item['last_modified']) ? $item['last_modified'] : (!empty($item['date']) ? $item['date'] : date('Y-m-d'));
    return substr($raw, 0, 10);
}

function sm_regenerate(): bool
{
    $baseUrl = sm_base_url();
    $data    = admin_load_data() ?? [];

    $sitemapExclusions = sm_load_exclusions();

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->appendChild($urlset);

    $addUrl = function (string $loc, string $lastmod, string $priority) use ($xml, $urlset, $baseUrl, $sitemapExclusions): void {
        $path = trim((string)substr($loc, strlen($baseUrl)), '/');
        if (in_array($path, $sitemapExclusions, true)) return;

        $url = $xml->createElement('url');
        $url->appendChild($xml->createElement('loc',      hsc($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $url->appendChild($xml->createElement('lastmod',  $lastmod));
        $url->appendChild($xml->createElement('priority', $priority));
        $urlset->appendChild($url);
    };

    $priorities = ['page' => '0.9', 'article' => '0.8', 'project' => '0.7'];

    $siteLastmod = '';
    $catLastmod  = [];
    $tagLastmod  = [];

    foreach (sl_all_type_slugs() as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;

            $itemLastmod = sm_item_lastmod($item);
            if ($itemLastmod > $siteLastmod) $siteLastmod = $itemLastmod;

            if (!empty($item['category'])) {
                $leafSlug = sanitizeSlug($item['category']);
                $catPath  = getCategoryPath($leafSlug, $data);
                foreach (explode('/', $catPath) as $seg) {
                    if (!isset($catLastmod[$seg]) || $itemLastmod > $catLastmod[$seg]) {
                        $catLastmod[$seg] = $itemLastmod;
                    }
                }
            }

            if (!empty($item['tags']) && is_array($item['tags'])) {
                foreach ($item['tags'] as $itemTag) {
                    $tagSlug = sanitizeSlug($itemTag);
                    if ($tagSlug === '') continue;
                    if (!isset($tagLastmod[$tagSlug]) || $itemLastmod > $tagLastmod[$tagSlug]) {
                        $tagLastmod[$tagSlug] = $itemLastmod;
                    }
                }
            }
        }
    }

    $addUrl($baseUrl . '/', $siteLastmod !== '' ? $siteLastmod : date('Y-m-d'), '1.0');

    foreach (sl_all_type_slugs() as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;

            $slug       = $item['slug']        ?? '';
            $customSlug = $item['custom_slug'] ?? '';
            $category   = $item['category']    ?? '';

            if (empty($slug) && empty($customSlug)) continue;

            $itemUrl = admin_content_url($ct, $slug, $customSlug, $category);
            $addUrl($itemUrl, sm_item_lastmod($item), $priorities[$ct] ?? '0.8');
        }
    }

    $seenCatUrls = [];
    $catPrefix   = admin_front_url_slug('category');
    $categories  = $data['categories'] ?? [];

    foreach (sl_all_type_slugs() as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;
            if (empty($item['category'])) continue;

            $leafSlug = sanitizeSlug($item['category']);
            $catPath  = getCategoryPath($leafSlug, $data);

            $segments    = explode('/', $catPath);
            $accumulated = '';
            foreach ($segments as $seg) {
                $accumulated = $accumulated !== '' ? $accumulated . '/' . $seg : $seg;
                $catUrl = $baseUrl . '/' . $catPrefix . '/' . $accumulated . '/';
                if (isset($seenCatUrls[$catUrl])) continue;
                $seenCatUrls[$catUrl] = true;
                if (empty($categories[$seg]['description'])) continue;
                $addUrl($catUrl, $catLastmod[$seg] ?? date('Y-m-d'), '0.6');
            }
        }
    }

    $seenTagUrls = [];
    $tagPrefix   = admin_front_url_slug('tag');
    $tags        = $data['tags'] ?? [];

    foreach (sl_all_type_slugs() as $ct) {
        foreach ($data[$ct] ?? [] as $item) {
            if (($item['status'] ?? 'published') !== 'published') continue;
            if (empty($item['tags']) || !is_array($item['tags'])) continue;

            foreach ($item['tags'] as $itemTag) {
                $tagSlug = sanitizeSlug($itemTag);
                if ($tagSlug === '' || empty($tags[$tagSlug]['description'])) continue;

                $tagUrl = $baseUrl . '/' . $tagPrefix . '/' . $tagSlug . '/';
                if (isset($seenTagUrls[$tagUrl])) continue;
                $seenTagUrls[$tagUrl] = true;
                $addUrl($tagUrl, $tagLastmod[$tagSlug] ?? date('Y-m-d'), '0.6');
            }
        }
    }

    $xmlString = $xml->saveXML();
    if ($xmlString === false) return false;

    $ok = sm_atomic_write(sm_sitemap_path(), $xmlString);
    if ($ok) {
        sm_ensure_robots_sitemap_line($baseUrl);
    }
    return $ok;
}

function sm_indexnow_state_path(): string
{
    return CMS_ROOT . '/private/indexnow.json';
}

function sm_indexnow_load_state(): array
{
    $path = sm_indexnow_state_path();
    if (!file_exists($path)) return [];
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function sm_indexnow_save_state(array $state): bool
{
    return sm_atomic_write(sm_indexnow_state_path(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function sm_indexnow_key(): string
{
    $state = sm_indexnow_load_state();
    $key   = (string)($state['key'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
        $key = bin2hex(random_bytes(16));
        $state['key'] = $key;
        if (!sm_indexnow_save_state($state)) return '';
    }
    $keyFile = CMS_ROOT . '/' . $key . '.txt';
    if (!file_exists($keyFile) && !sm_atomic_write($keyFile, $key)) return '';
    return $key;
}

function sm_sitemap_entries(): array
{
    $path = sm_sitemap_path();
    if (!file_exists($path)) return [];
    $xml = simplexml_load_file($path);
    if ($xml === false) return [];
    $entries = [];
    foreach ($xml->url as $url) {
        $entries[(string)$url->loc] = (string)$url->lastmod;
    }
    return $entries;
}

function sm_indexnow_submit(string $baseUrl): array
{
    $key = sm_indexnow_key();
    if ($key === '') return ['ok' => false, 'sent' => 0, 'status' => 0];

    $state = sm_indexnow_load_state();
    $since = (int)($state['last'] ?? 0) > 0 ? date('Y-m-d', (int)$state['last']) : '';
    $host  = (string)parse_url($baseUrl, PHP_URL_HOST);

    $urls = [];
    foreach (sm_sitemap_entries() as $loc => $lastmod) {
        if (parse_url($loc, PHP_URL_HOST) !== $host) continue;
        if ($since === '' || $lastmod >= $since) $urls[] = $loc;
    }
    $urls = array_slice($urls, 0, 10000);
    if (!$urls) return ['ok' => true, 'sent' => 0, 'status' => 0];

    $payload = json_encode([
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => $baseUrl . '/' . $key . '.txt',
        'urlList'     => $urls,
    ], JSON_UNESCAPED_SLASHES);

    $context = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json; charset=utf-8\r\n",
        'content'       => $payload,
        'timeout'       => 15,
        'ignore_errors' => true,
    ]]);
    @file_get_contents('https://api.indexnow.org/indexnow', false, $context);

    $status = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $m)) {
        $status = (int)$m[1];
    }
    if ($status !== 200 && $status !== 202) return ['ok' => false, 'sent' => 0, 'status' => $status];

    $state['last'] = time();
    sm_indexnow_save_state($state);
    return ['ok' => true, 'sent' => count($urls), 'status' => $status];
}
