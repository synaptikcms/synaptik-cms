<?php
/**
 * Content Rendering & Shortcodes — SynaptikCMS
 * render_content_html() pipeline, all shortcode render functions,
 * excerpt helpers, and the contact form.
 */

/**
 * Renders content HTML with full shortcode + Markdown pipeline.
 * ALWAYS use this instead of echoing $item['content'] directly.
 *
 * Pipeline order:
 *  1. Markdown → HTML (when content_format === 'markdown')
 *  2. Strip c-col open class and contenteditable attributes
 *  3. [gallery id="X"]
 *  4. [toc]
 *  5. [callout type="..."]...[/callout]
 *  6. [quote author="..."]...[/quote]
 *  7. [button ...]
 *  8. [recent_articles ...]
 *  9. [recent_projects ...]
 * 10. [articles_by_tag ...]
 * 11. [contact_form]
 *
 * @param string     $html Raw HTML stored in the content field.
 * @param array|null $item Full content item (required for inline gallery shortcode).
 * @return string    Processed HTML safe for theme output.
 */
function render_content_html($html, $item = null)
{
    if (empty($html)) {
        return '';
    }

    if (!empty($item['content_format']) && $item['content_format'] === 'markdown') {
        $html = _md_to_html($html);
    }

    // Strip the 'open' class from .c-col elements (so collapsibles start collapsed)
    $html = preg_replace_callback(
        '/class\s*=\s*"([^"]*\bc-col\b[^"]*)"/i',
        function ($m) {
            $cls = preg_replace('/\bopen\b/', '', $m[1]);
            return 'class="' . trim(preg_replace('/\s+/', ' ', $cls)) . '"';
        },
        $html
    );

    // Strip contenteditable attributes
    $html = preg_replace('/\s*contenteditable\s*=\s*"(?:true|false)"/i', '', $html);
    $html = preg_replace("/\s*contenteditable\s*=\s*'(?:true|false)'/i", '', $html);

    // ── [gallery id="X"] ──────────────────────────────────────────────────────
    if ($item !== null && !empty($item['galleries']) && is_array($item['galleries'])) {
        $html = preg_replace_callback(
            '/\[gallery\s+id=["\']?(\d+)["\']?\]/i',
            function ($matches) use ($item) {
                $id = (int)$matches[1];
                if (!empty($item['galleries'][$id]['images'])) {
                    $g = $item['galleries'][$id];
                    return '<div class="inline-gallery">' . renderGallery($g['images'], $g['layout'] ?? 'grid') . '</div>';
                }
                return '<!-- gallery id=' . $matches[1] . ' not found -->';
            },
            $html
        );
    }

    // ── [toc] ─────────────────────────────────────────────────────────────────
    if (strpos($html, '[toc]') !== false) {
        $tocHeadings = [];
        $html = preg_replace_callback(
            '/<(h[23])([^>]*)>(.*?)<\/h[23]>/is',
            function ($m) use (&$tocHeadings) {
                $tag   = $m[1];
                $attrs = $m[2];
                $text  = html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $id    = 'toc-' . sanitizeSlug($text);
                $tocHeadings[] = ['level' => (int)substr($tag, 1), 'id' => $id, 'text' => $text];
                // Always overwrite any existing id so the TOC link and heading anchor always match
                $attrs = preg_replace('/\s*id\s*=\s*(["\'][^"\']*["\']|\S+)/i', '', $attrs);
                $attrs = ' id="' . htmlspecialchars($id) . '"' . $attrs;
                return "<{$tag}{$attrs}>{$m[3]}</{$tag}>";
            },
            $html
        );
        if (!empty($tocHeadings)) {
            $pageUrl = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'));
            $tocLabel = __t('toc_label', 'On this page');
            $toc = '<nav class="sc-toc" data-label="' . htmlspecialchars($tocLabel) . '"><ul>';
            foreach ($tocHeadings as $h) {
                $cls  = $h['level'] === 3 ? ' class="sc-toc-sub"' : '';
                $toc .= '<li' . $cls . '><a href="' . $pageUrl . '#' . htmlspecialchars($h['id']) . '">' . htmlspecialchars($h['text']) . '</a></li>';
            }
            $toc .= '</ul></nav>';
            $html = str_replace('[toc]', $toc, $html);
        }
    }

    // ── [callout type="info|warning|tip|danger"]...[/callout] ────────────────
    if (strpos($html, '[callout') !== false) {
        $html = preg_replace_callback(
            '/\[callout(?:\s+type=["\']?(\w+)["\']?)?\](.*?)\[\/callout\]/is',
            function ($m) {
                $type    = strtolower($m[1] ?: 'info');
                $allowed = ['info', 'warning', 'tip', 'danger'];
                if (!in_array($type, $allowed)) $type = 'info';
                $icons = ['info' => '&#x2139;&#xFE0F;', 'warning' => '&#x26A0;&#xFE0F;', 'tip' => '&#x1F4A1;', 'danger' => '&#x1F6AB;'];
                return '<div class="sc-callout sc-callout-' . $type . '">'
                     . '<span class="sc-callout-icon">' . $icons[$type] . '</span>'
                     . '<div class="sc-callout-body">' . trim($m[2]) . '</div>'
                     . '</div>';
            },
            $html
        );
    }

    // ── [quote author="Name"]...[/quote] ──────────────────────────────────────
    if (strpos($html, '[quote') !== false) {
        $html = preg_replace_callback(
            '/\[quote(?:\s+author=["\']([^"\']*)["\'])?\](.*?)\[\/quote\]/is',
            function ($m) {
                $author = htmlspecialchars(trim($m[1] ?? ''));
                $footer = $author ? '<footer>&#x2014; ' . $author . '</footer>' : '';
                return '<blockquote class="sc-quote">' . trim($m[2]) . $footer . '</blockquote>';
            },
            $html
        );
    }

    // ── [button url="..." label="..." style="primary|secondary|outline"] ──────
    if (strpos($html, '[button') !== false) {
        $html = preg_replace_callback(
            '/\[button([^\]]*)\]/i',
            function ($m) {
                $attrs  = _shortcode_parse_attrs($m[1]);
                $url    = htmlspecialchars($attrs['url']   ?? '#');
                $label  = htmlspecialchars($attrs['label'] ?? 'Click');
                $styles = ['primary', 'secondary', 'outline'];
                $style  = in_array($attrs['style'] ?? '', $styles) ? $attrs['style'] : 'primary';
                $target = !empty($attrs['target']) ? ' target="' . htmlspecialchars($attrs['target']) . '"' : '';
                return '<a href="' . $url . '" class="sc-btn sc-btn-' . $style . '"' . $target . '>' . $label . '</a>';
            },
            $html
        );
    }

    // ── [recent_articles limit="N" tag="slug" category="slug"] ───────────────
    if (strpos($html, '[recent_articles') !== false) {
        $html = preg_replace_callback(
            '/\[recent_articles([^\]]*)\]/i',
            function ($m) {
                $attrs    = _shortcode_parse_attrs($m[1]);
                $limit    = max(1, min(20, (int)($attrs['limit'] ?? 3)));
                return render_recent_articles_shortcode($limit, $attrs['tag'] ?? '', $attrs['category'] ?? '');
            },
            $html
        );
    }

    // ── [recent_projects limit="N"] ───────────────────────────────────────────
    if (strpos($html, '[recent_projects') !== false) {
        $html = preg_replace_callback(
            '/\[recent_projects([^\]]*)\]/i',
            function ($m) {
                $attrs = _shortcode_parse_attrs($m[1]);
                return render_recent_projects_shortcode(max(1, min(20, (int)($attrs['limit'] ?? 3))));
            },
            $html
        );
    }

    // ── [articles_by_tag tag="slug" limit="N"] ────────────────────────────────
    if (strpos($html, '[articles_by_tag') !== false) {
        $html = preg_replace_callback(
            '/\[articles_by_tag([^\]]*)\]/i',
            function ($m) {
                $attrs = _shortcode_parse_attrs($m[1]);
                return render_articles_by_tag_shortcode($attrs['tag'] ?? '', max(1, min(20, (int)($attrs['limit'] ?? 5))));
            },
            $html
        );
    }

    // ── [contact_form] ────────────────────────────────────────────────────────
    if (strpos($html, '[contact_form]') !== false) {
        $html = preg_replace_callback(
            '/\[contact_form\]/i',
            fn() => render_contact_form_html(),
            $html
        );
    }

    return $html;
}

// ── Excerpt & attribute helpers ───────────────────────────────────────────────

/**
 * Generates a clean plain-text excerpt from raw HTML content.
 * Strips shortcodes before strip_tags() so they never appear in card excerpts.
 *
 * @param string $html   Raw HTML content.
 * @param int    $length Maximum character length.
 * @return string        Plain text excerpt, word-boundary trimmed.
 */
function _clean_excerpt(string $html, int $length = 150): string
{
    $text = preg_replace('/\[[^\]]+\].*?\[\/[^\]]+\]/s', '', $html); // wrapped shortcodes
    $text = preg_replace('/\[[^\]]*\]/', '', $text);                   // self-closing shortcodes
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/', ' ', $text));

    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $cut       = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($cut, ' ');
    return $lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut;
}

/**
 * Parses a shortcode attribute string into a key => value array.
 * Handles key="value", key='value', and key=value.
 */
function _shortcode_parse_attrs(string $str): array
{
    $attrs = [];
    preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/', $str, $m, PREG_SET_ORDER);
    foreach ($m as $match) {
        $attrs[$match[1]] = $match[2] !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
    }
    return $attrs;
}

// ── Shortcode render functions ────────────────────────────────────────────────

/**
 * Renders [recent_articles limit="N" tag="slug" category="slug"].
 * Always uses sl_load_index() directly — never $GLOBALS['data']['article'],
 * which is replaced by a single item on single-item page views.
 */
function render_recent_articles_shortcode(int $limit, string $tag = '', string $category = ''): string
{
    require_once __DIR__ . '/data-layer.php';
    $articles = sl_load_index('article');

    if (!empty($tag)) {
        $tagSlug  = sanitizeSlug($tag);
        $articles = array_filter($articles, function ($a) use ($tagSlug) {
            if (empty($a['tags']) || !is_array($a['tags'])) return false;
            foreach ($a['tags'] as $t) {
                if (sanitizeSlug($t) === $tagSlug) return true;
            }
            return false;
        });
    }

    if (!empty($category)) {
        $catSlug  = sanitizeSlug($category);
        $articles = array_filter($articles, fn($a) => sanitizeSlug($a['category'] ?? '') === $catSlug);
    }

    usort($articles, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $articles = array_slice(array_values($articles), 0, $limit);

    if (empty($articles)) {
        return '<!-- [recent_articles]: no results -->';
    }

    $html = '<section class="articles-grid sc-articles-grid">';
    foreach ($articles as $a) {
        $slug    = !empty($a['custom_slug']) ? $a['custom_slug'] : $a['slug'];
        $catPath = !empty($a['category']) ? sanitizeSlug($a['category']) : null;
        $url     = cleanUrl('article', $slug, null, $catPath);

        if (!empty($a['summary'])) {
            $summary = $a['summary'];
        } elseif (!empty($a['content'])) {
            $summary = _clean_excerpt($a['content'], 150);
        } else {
            $_fs     = $a['_file'] ?? (!empty($a['custom_slug']) ? $a['custom_slug'] : ($a['slug'] ?? ''));
            $_full   = $_fs !== '' ? sl_load_item('article', $_fs) : null;
            $summary = $_full !== null ? _clean_excerpt($_full['content'] ?? '', 150) : '';
        }

        $html .= '<article class="article-card">';
        if (!empty($a['image'])) {
            $html .= '<div class="article-thumbnail"><a href="' . $url . '">'
                   . '<img src="' . getBaseUrl() . htmlspecialchars($a['image']) . '" alt="' . htmlspecialchars($a['title']) . '" loading="lazy">'
                   . '</a></div>';
        }
        $html .= '<div class="article-details">';
        $html .= '<h3><a href="' . $url . '">' . htmlspecialchars($a['title']) . '</a></h3>';
        if (!empty($a['date']) && !empty($a['show_date'])) {
            $html .= '<div class="article-date">' . htmlspecialchars(format_date($a['date'])) . '</div>';
        }
        if (!empty($a['tags']) && is_array($a['tags'])) {
            $html .= '<div class="article-tags">';
            foreach ($a['tags'] as $t) {
                $html .= '<a href="' . getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($t) . '/" class="tag-link">' . htmlspecialchars($t) . '</a>';
            }
            $html .= '</div>';
        }
        if (!empty($summary)) {
            $ellipsis = empty($a['summary']) ? '&#8230;' : '';
            $html .= '<div class="article-summary">' . htmlspecialchars($summary) . $ellipsis . '</div>';
        }
        $html .= '</div>';
        $html .= '<a href="' . $url . '" class="read-more">' . __t('read_more', 'Read more') . '</a>';
        $html .= '</article>';
    }
    $html .= '</section>';
    return $html;
}

/**
 * Renders [recent_projects limit="N"].
 * Always uses sl_load_index() directly — never $GLOBALS['data']['project'].
 */
function render_recent_projects_shortcode(int $limit): string
{
    require_once __DIR__ . '/data-layer.php';
    $projects = sl_load_index('project');

    usort($projects, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $projects = array_slice($projects, 0, $limit);

    if (empty($projects)) {
        return '<!-- [recent_projects]: no results -->';
    }

    $html = '<section class="projects-grid sc-projects-grid">';
    foreach ($projects as $p) {
        $slug      = !empty($p['custom_slug']) ? $p['custom_slug'] : $p['slug'];
        $catPath   = !empty($p['category']) ? sanitizeSlug($p['category']) : null;
        $url       = cleanUrl('project', $slug, null, $catPath);
        $desc      = decodeHtmlEntities($p['meta_description'] ?? $p['description'] ?? '');
        $cardClass = !empty($p['image']) ? 'project-card' : 'project-card project-card-no-image';

        $html .= '<article class="' . $cardClass . '">';
        if (!empty($p['image'])) {
            $html .= '<div class="project-thumbnail">'
                   . '<img src="' . getBaseUrl() . htmlspecialchars($p['image']) . '" alt="' . htmlspecialchars($p['title']) . '" loading="lazy">'
                   . '</div>';
        }
        $html .= '<div class="project-overlay">';
        $html .= '<h3>' . htmlspecialchars($p['title']) . '</h3>';
        if (!empty($desc)) {
            $html .= '<div class="project-excerpt">' . htmlspecialchars($desc) . '</div>';
        }
        $html .= '<a href="' . $url . '" class="view-project">' . __t('view_project', 'View project') . '</a>';
        $html .= '</div></article>';
    }
    $html .= '</section>';
    return $html;
}

/**
 * Renders [articles_by_tag tag="slug" limit="N"].
 * Alias for render_recent_articles_shortcode() with a mandatory tag filter.
 */
function render_articles_by_tag_shortcode(string $tag, int $limit): string
{
    return render_recent_articles_shortcode($limit, $tag);
}

// ── Contact form ──────────────────────────────────────────────────────────────

/**
 * Generates a stateless HMAC CSRF token for the contact form.
 * Format: "{timestamp}.{HMAC-SHA256(timestamp, secret)}"
 */
function _contact_generate_csrf(): string
{
    $secretFile = __DIR__ . '/private/contact.secret';
    if (file_exists($secretFile)) {
        $secret = trim(file_get_contents($secretFile));
        if (strlen($secret) === 64) {
            $ts = time();
            return $ts . '.' . hash_hmac('sha256', (string)$ts, $secret);
        }
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secretFile, $secret, LOCK_EX);
    $ts = time();
    return $ts . '.' . hash_hmac('sha256', (string)$ts, $secret);
}

/**
 * Renders the full contact form HTML.
 * Used by [contact_form] shortcode and contact page templates.
 * Injects contact.css once per page via a static flag.
 */
function render_contact_form_html(): string
{
    static $cssInjected = false;
    $cssLink = '';
    if (!$cssInjected) {
        $theme       = loadSettings()['active_theme'] ?? 'default';
        $cssLink     = '<link rel="stylesheet" href="' . getBaseUrl() . 'theme/' . htmlspecialchars($theme) . '/css/contact.css">' . "\n";
        $cssInjected = true;
    }

    $settings        = loadSettings();
    $hcaptchaSiteKey = trim($settings['hcaptcha_site_key'] ?? '');
    $hcaptchaEnabled = !empty($hcaptchaSiteKey);
    $csrfToken       = _contact_generate_csrf();

    $labelName    = __t('contact_name',        'Name');
    $labelEmail   = __t('contact_email_field', 'Email');
    $labelMessage = __t('contact_message',     'Message');
    $labelSubmit  = __t('contact_submit',      'Send message');
    $placeholderN = __t('contact_name_ph',     'Your name');
    $placeholderE = __t('contact_email_ph',    'Your email address');
    $placeholderM = __t('contact_message_ph',  "Your message\u2026");

    $statusHtml = '';
    if (isset($_GET['contact_sent']) && $_GET['contact_sent'] === '1') {
        $msg        = htmlspecialchars($settings['contact_success_message'] ?? __t('contact_sent_ok', 'Your message has been sent. Thank you!'));
        $statusHtml = '<p class="contact-status success">' . $msg . '</p>' . "\n";
    } elseif (!empty($_GET['contact_error'])) {
        $msg        = htmlspecialchars($settings['contact_error_message'] ?? __t('contact_sent_error', 'An error occurred. Please try again.'));
        $statusHtml = '<p class="contact-status error">' . $msg . '</p>' . "\n";
    }

    $handlerUrl = getBaseUrl() . 'contact-process.php';
    $timestamp  = time();

    $html  = $cssLink;
    if ($hcaptchaEnabled) {
        $html .= '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>' . "\n";
    }
    $html .= $statusHtml;
    $html .= '<form class="contact-form" method="post" action="' . htmlspecialchars($handlerUrl) . '" novalidate>' . "\n";
    $html .= '<input type="hidden" name="_csrf" value="' . htmlspecialchars($csrfToken) . '">' . "\n";
    $html .= '<input type="hidden" name="_ft"   value="' . $timestamp . '">' . "\n";
    $html .= '<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off" style="display:none!important;position:absolute;left:-9999px;" aria-hidden="true">' . "\n";

    $html .= '<div class="contact-field"><label for="contact_name">'
           . htmlspecialchars($labelName) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<input type="text" id="contact_name" name="contact_name" placeholder="' . htmlspecialchars($placeholderN) . '" required autocomplete="name" maxlength="100"></div>' . "\n";

    $html .= '<div class="contact-field"><label for="contact_email">'
           . htmlspecialchars($labelEmail) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<input type="email" id="contact_email" name="contact_email" placeholder="' . htmlspecialchars($placeholderE) . '" required autocomplete="email" maxlength="254"></div>' . "\n";

    $html .= '<div class="contact-field"><label for="contact_message">'
           . htmlspecialchars($labelMessage) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<textarea id="contact_message" name="contact_message" rows="6" placeholder="' . htmlspecialchars($placeholderM) . '" required minlength="10" maxlength="5000"></textarea></div>' . "\n";

    if ($hcaptchaEnabled) {
        $html .= '<div class="contact-field"><div class="h-captcha" data-sitekey="' . htmlspecialchars($hcaptchaSiteKey) . '"></div></div>' . "\n";
    }

    $html .= '<div class="contact-field contact-submit"><button type="submit">' . htmlspecialchars($labelSubmit) . '</button></div>' . "\n";
    $html .= '</form>' . "\n";

    return $html;
}
