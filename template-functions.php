<?php
/**
 * Template Functions
 * Contains all the rendering functions for theme files
 */

/**
 * Renders the site title in the header
 */
function render_site_title($settings, $pageTitle)
{
    if ($settings["show_site_title_in_header"]) {
        return htmlspecialchars($settings["site_title"]);
    } else {
        return $pageTitle === "Welcome to SynaptikCMS" ? $pageTitle : $settings["site_title"];
    }
}

/**
 * Sanitizes content HTML before rendering on the theme.
 * - Strips the `open` class from .c-col elements (so collapsibles start collapsed)
 * - Strips contenteditable attributes (so titles aren't editable on the theme)
 *
 * @param string $html Raw HTML stored in the content field
 * @return string Cleaned HTML safe for theme rendering
 */
/**
 * Convert a Markdown string to HTML.
 * Handles the common syntax used in CMS content. CMS shortcodes ([...]) are
 * left untouched so the shortcode parser can process them afterward.
 *
 * @param  string $md  Raw Markdown input.
 * @return string      HTML output (not yet shortcode-parsed).
 */
function _md_to_html(string $md): string
{
    // 1. Normalise line endings
    $md = str_replace("\r\n", "\n", $md);
    $md = str_replace("\r", "\n", $md);

    // 2. Container directives  :::type [optional title]\n...\n:::
    // Processed before code-block protection so the inner body gets its own
    // independent _md_to_html() pass (correct inline-code handling, etc.).
    // Supported aliases: note/info → info, warning/caution → warning,
    //                    tip/success → tip, danger/error → danger.
    if (strpos($md, ':::') !== false) {
        $md = preg_replace_callback(
            '/^:::([\w]+)([^\n]*)\n([\s\S]*?)^:::[ \t]*$/m',
            static function ($m) {
                $alias   = strtolower(trim($m[1]));
                $title   = trim($m[2]);
                $body    = trim($m[3]);

                $typeMap = [
                    'note'    => 'info',
                    'info'    => 'info',
                    'warning' => 'warning',
                    'caution' => 'warning',
                    'tip'     => 'tip',
                    'success' => 'tip',
                    'danger'  => 'danger',
                    'error'   => 'danger',
                ];
                $iconMap = [
                    'info'    => '&#x2139;&#xFE0F;',
                    'warning' => '&#x26A0;&#xFE0F;',
                    'tip'     => '&#x1F4A1;',
                    'danger'  => '&#x1F6AB;',
                ];

                $cssType  = $typeMap[$alias]  ?? 'info';
                $icon     = $iconMap[$cssType] ?? '&#x2139;&#xFE0F;';
                $bodyHtml = _md_to_html($body);
                $titleHtml = $title !== ''
                    ? '<p class="sc-callout-title"><strong>' . htmlspecialchars($title) . '</strong></p>'
                    : '';

                return '<div class="sc-callout sc-callout-' . $cssType . '">'
                     . '<span class="sc-callout-icon">' . $icon . '</span>'
                     . '<div class="sc-callout-body">' . $titleHtml . $bodyHtml . '</div>'
                     . '</div>';
            },
            $md
        );
    }

    // 3. Protect fenced code blocks  ```lang\n...\n```
    // Square brackets are encoded as HTML entities to prevent shortcode parsers
    // from processing shortcode-like syntax that appears in code examples.
    $codeBlocks = [];
    $md = preg_replace_callback(
        '/^```([^\n]*)\n([\s\S]*?)^```/m',
        function ($m) use (&$codeBlocks) {
            $lang  = htmlspecialchars(trim($m[1]));
            $code  = htmlspecialchars($m[2]);
            $code  = str_replace(['[', ']'], ['&#91;', '&#93;'], $code);
            $cls   = $lang ? ' class="language-' . $lang . '"' : '';
            $token = '\x00CODE' . count($codeBlocks) . '\x00';
            $codeBlocks[$token] = '<pre><code' . $cls . '>' . $code . '</code></pre>';
            return $token . "\n";
        },
        $md
    );

    // 4. Protect inline code `...`
    // Same bracket encoding to prevent shortcode processing inside inline code spans.
    $inlineCodes = [];
    $md = preg_replace_callback(
        '/`([^`\n]+)`/',
        function ($m) use (&$inlineCodes) {
            $token   = '\x00IC' . count($inlineCodes) . '\x00';
            $content = htmlspecialchars($m[1]);
            $content = str_replace(['[', ']'], ['&#91;', '&#93;'], $content);
            $inlineCodes[$token] = '<code>' . $content . '</code>';
            return $token;
        },
        $md
    );

    $lines  = explode("\n", $md);
    $output = '';
    $i      = 0;
    $n      = count($lines);

    while ($i < $n) {
        $line = $lines[$i];

        // Fenced code block token — already replaced, output as-is
        if (strpos($line, '\x00CODE') === 0) {
            $token = rtrim($line);
            $output .= $codeBlocks[$token] ?? $token;
            $i++;
            continue;
        }

        // Horizontal rule --- or *** or ___
        if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
            $output .= '<hr>' . "\n";
            $i++;
            continue;
        }

        // ATX headings # to ######
        if (preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
            $level  = strlen($m[1]);
            $id     = function_exists('sanitizeSlug') ? ' id="toc-' . sanitizeSlug(strip_tags($m[2])) . '"' : '';
            $output .= '<h' . $level . $id . '>' . _md_inline($m[2]) . '</h' . $level . '>' . "\n";
            $i++;
            continue;
        }

        // Blockquote >
        if (preg_match('/^>\s?(.*)/', $line, $m)) {
            $bqLines = [];
            while ($i < $n && preg_match('/^>\s?(.*)/', $lines[$i], $bm)) {
                $bqLines[] = $bm[1];
                $i++;
            }
            $output .= '<blockquote><p>' . _md_inline(implode("\n", $bqLines)) . '</p></blockquote>' . "\n";
            continue;
        }

        // Unordered list  - or * or +
        if (preg_match('/^([*+-])\s+(.+)/', $line, $m)) {
            $output .= '<ul>' . "\n";
            while ($i < $n && preg_match('/^[*+-]\s+(.+)/', $lines[$i], $lm)) {
                $output .= '<li>' . _md_inline(trim($lm[1])) . '</li>' . "\n";
                $i++;
            }
            $output .= '</ul>' . "\n";
            continue;
        }

        // Ordered list  1.
        if (preg_match('/^\d+\.\s+(.+)/', $line, $m)) {
            $output .= '<ol>' . "\n";
            while ($i < $n && preg_match('/^\d+\.\s+(.+)/', $lines[$i], $lm)) {
                $output .= '<li>' . _md_inline(trim($lm[1])) . '</li>' . "\n";
                $i++;
            }
            $output .= '</ol>' . "\n";
            continue;
        }

        // GFM table  | Col | Col |
        if (strpos($line, '|') !== false && $i + 1 < $n && preg_match('/^[|:\s-]+$/', $lines[$i + 1])) {
            $headerCells = array_map('trim', explode('|', trim($line, '|')));
            $output .= '<table><thead><tr>';
            foreach ($headerCells as $cell) {
                $output .= '<th>' . _md_inline($cell) . '</th>';
            }
            $output .= '</tr></thead><tbody>' . "\n";
            $i += 2; // skip header + separator
            while ($i < $n && strpos($lines[$i], '|') !== false) {
                $cells = array_map('trim', explode('|', trim($lines[$i], '|')));
                $output .= '<tr>';
                foreach ($cells as $cell) {
                    $output .= '<td>' . _md_inline($cell) . '</td>';
                }
                $output .= '</tr>' . "\n";
                $i++;
            }
            $output .= '</tbody></table>' . "\n";
            continue;
        }

        // Blank line — paragraph break
        if (trim($line) === '') {
            $i++;
            continue;
        }

        // Paragraph: collect consecutive non-blank, non-special lines
        $paraLines = [];
        while ($i < $n
            && trim($lines[$i]) !== ''
            && !preg_match('/^#{1,6}\s/', $lines[$i])
            && !preg_match('/^[*+-]\s/', $lines[$i])
            && !preg_match('/^\d+\.\s/', $lines[$i])
            && !preg_match('/^>/', $lines[$i])
            && !preg_match('/^\s*([-*_])\1{2,}\s*$/', $lines[$i])
            && strpos($lines[$i], '\x00CODE') !== 0
        ) {
            $paraLines[] = $lines[$i];
            $i++;
        }
        if ($paraLines) {
            $paraText = implode("\n", $paraLines);
            // Two spaces at end of line = hard line break
            $paraText = preg_replace('/  \n/', '<br>', $paraText);
            $output  .= '<p>' . _md_inline($paraText) . '</p>' . "\n";
        }
    }

    // Restore inline code tokens
    $output = strtr($output, $inlineCodes);

    return $output;
}

/**
 * Process inline Markdown within a single block of text.
 * Images, links, bold, italic, strikethrough.
 * CMS shortcodes are preserved intact.
 */
function _md_inline(string $text): string
{
    // Images ![alt](url)
    $text = preg_replace(
        '/!\[([^\]]*)\]\(([^)]+)\)/',
        '<img src="$2" alt="$1" style="max-width:100%">',
        $text
    );

    // Links [text](url) — skip CMS shortcode syntax [word ...]
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        function ($m) {
            $target = preg_match('/\{:target="_blank"\}/', $m[2]) ? ' target="_blank" rel="noopener"' : '';
            $url    = preg_replace('/\{[^}]+\}/', '', $m[2]);
            return '<a href="' . htmlspecialchars(trim($url)) . '"' . $target . '>' . $m[1] . '</a>';
        },
        $text
    );

    // Bold **text** or __text__
    $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/', '<strong>$1$2</strong>', $text);
    // Italic *text* or _text_  (must come after bold)
    $text = preg_replace('/\*(.+?)\*|_(.+?)_/', '<em>$1$2</em>', $text);
    // Strikethrough ~~text~~
    $text = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $text);

    return $text;
}

function render_content_html($html, $item = null)
{
    if (empty($html)) {
        return '';
    }

    // Convert Markdown to HTML if the item was written in Markdown format.
    // The conversion runs BEFORE shortcode parsing so shortcodes survive intact.
    if (!empty($item['content_format']) && $item['content_format'] === 'markdown') {
        $html = _md_to_html($html);
    }

    // 1. Remove "open" from any class attribute that contains "c-col"
    $html = preg_replace_callback(
        '/class\s*=\s*"([^"]*\bc-col\b[^"]*)"/i',
        function ($m) {
            $cls = preg_replace('/\bopen\b/', '', $m[1]);
            $cls = trim(preg_replace('/\s+/', ' ', $cls));
            return 'class="' . $cls . '"';
        },
        $html
    );

    // 2. Strip every contenteditable attribute (true/false, single/double quoted)
    $html = preg_replace('/\s*contenteditable\s*=\s*"(?:true|false)"/i', '', $html);
    $html = preg_replace("/\s*contenteditable\s*=\s*'(?:true|false)'/i", '', $html);

    // Parse [gallery id="X"] inline shortcodes
    if ($item !== null && isset($item['galleries']) && is_array($item['galleries'])) {
        $html = preg_replace_callback(
            '/\[gallery\s+id=["\']?(\d+)["\']?\]/i',
            function ($matches) use ($item) {
                $id = (int)$matches[1];
                if (isset($item['galleries'][$id]) && !empty($item['galleries'][$id]['images'])) {
                    $g = $item['galleries'][$id];
                    return '<div class="inline-gallery">' . renderGallery($g['images'], $g['layout'] ?? 'grid') . '</div>';
                }
                return '<!-- gallery id=' . $matches[1] . ' not found -->';
            },
            $html
        );
    }

    // ── [toc] — Table of contents ────────────────────────────────────────────
    if (strpos($html, '[toc]') !== false) {
        $tocHeadings = [];
        // Add id attributes to every h2/h3 (idempotent if already present)
        $html = preg_replace_callback(
            '/<(h[23])([^>]*)>(.*?)<\/h[23]>/is',
            function ($m) use (&$tocHeadings) {
                $tag   = $m[1];
                $attrs = $m[2];
                $text  = strip_tags($m[3]);
                $id    = 'toc-' . sanitizeSlug($text);
                $tocHeadings[] = ['level' => (int)substr($tag, 1), 'id' => $id, 'text' => $text];
                if (strpos($attrs, 'id=') === false) {
                    $attrs = ' id="' . htmlspecialchars($id) . '"' . $attrs;
                }
                return "<{$tag}{$attrs}>{$m[3]}</{$tag}>";
            },
            $html
        );
        if (!empty($tocHeadings)) {
            $toc = '<nav class="sc-toc"><ul>';
            foreach ($tocHeadings as $h) {
                $cls  = $h['level'] === 3 ? ' class="sc-toc-sub"' : '';
                $toc .= '<li' . $cls . '><a href="#' . htmlspecialchars($h['id']) . '">'
                     . htmlspecialchars($h['text']) . '</a></li>';
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
                $icons   = ['info' => '&#x2139;&#xFE0F;', 'warning' => '&#x26A0;&#xFE0F;',
                            'tip'  => '&#x1F4A1;',         'danger'  => '&#x1F6AB;'];
                $icon    = $icons[$type];
                $body    = trim($m[2]);
                return '<div class="sc-callout sc-callout-' . $type . '">'
                     . '<span class="sc-callout-icon">' . $icon . '</span>'
                     . '<div class="sc-callout-body">' . $body . '</div>'
                     . '</div>';
            },
            $html
        );
    }

    // ── [quote author="Name"]...[/quote] ─────────────────────────────────────
    if (strpos($html, '[quote') !== false) {
        $html = preg_replace_callback(
            '/\[quote(?:\s+author=["\']([^"\']*)["\'])?\](.*?)\[\/quote\]/is',
            function ($m) {
                $author = htmlspecialchars(trim($m[1] ?? ''));
                $body   = trim($m[2]);
                $footer = $author ? '<footer>&#x2014; ' . $author . '</footer>' : '';
                return '<blockquote class="sc-quote">' . $body . $footer . '</blockquote>';
            },
            $html
        );
    }

    // ── [button url="..." label="..." style="primary|secondary|outline"] ─────
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
                return '<a href="' . $url . '" class="sc-btn sc-btn-' . $style . '"' . $target . '>'
                     . $label . '</a>';
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
                $tag      = $attrs['tag']      ?? '';
                $category = $attrs['category'] ?? '';
                return render_recent_articles_shortcode($limit, $tag, $category);
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
                $limit = max(1, min(20, (int)($attrs['limit'] ?? 3)));
                return render_recent_projects_shortcode($limit);
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
                $tag   = $attrs['tag']   ?? '';
                $limit = max(1, min(20, (int)($attrs['limit'] ?? 5)));
                return render_articles_by_tag_shortcode($tag, $limit);
            },
            $html
        );
    }

    // ── [contact_form] ────────────────────────────────────────────────────────
    if (strpos($html, '[contact_form]') !== false) {
        $html = preg_replace_callback(
            '/\[contact_form\]/i',
            function () { return render_contact_form_html(); },
            $html
        );
    }

    return $html;
}


// ════════════════════════════════════════════════════════════════════════════
// Shortcode helpers & render functions
// ════════════════════════════════════════════════════════════════════════════

/**
 * Parse shortcode attribute string into a key=>value array.
 * Handles key="value", key='value', key=value.
 */
/**
 * Generate a clean plain-text excerpt from raw HTML content.
 * Strips shortcode tags (e.g. [recent_articles limit="3"]) BEFORE strip_tags()
 * so shortcodes never bleed into card excerpts or list previews.
 *
 * @param string $html   Raw HTML content (as stored in data.json)
 * @param int    $length Max character length of the returned excerpt
 * @return string        Plain text, trimmed, no trailing punctuation artifacts
 */
function _clean_excerpt(string $html, int $length = 150): string
{
    // 1. Remove wrapped shortcodes: [foo ...]content[/foo]
    $text = preg_replace('/\[[^\]]+\].*?\[\/[^\]]+\]/s', '', $html);
    // 2. Remove self-closing shortcodes: [foo ...] or [/foo]
    $text = preg_replace('/\[[^\]]*\]/', '', $text);
    // 3. Strip HTML tags
    $text = strip_tags($text);
    // 4. Collapse whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    // Cut at word boundary
    $cut = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($cut, ' ');
    return ($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut);
}

function _shortcode_parse_attrs(string $str): array
{
    $attrs = [];
    preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/', $str, $m, PREG_SET_ORDER);
    foreach ($m as $match) {
        $attrs[$match[1]] = $match[2] !== '' ? $match[2]
                          : ($match[3] !== '' ? $match[3] : $match[4]);
    }
    return $attrs;
}

/**
 * Render [recent_articles limit="N" tag="slug" category="slug"]
 * Returns an article list filtered by optional tag or category.
 */
function render_recent_articles_shortcode(int $limit, string $tag = '', string $category = ''): string
{
    // Always load the full article index directly.
    // $GLOBALS['data']['article'] cannot be used here: on single-item pages,
    // index.php replaces it with an array containing only the current item,
    // which would make any tag/category filter return 0 or 1 result at most.
    require_once dirname(__FILE__) . '/data-layer.php';
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
        $articles = array_filter($articles, function ($a) use ($catSlug) {
            return sanitizeSlug($a['category'] ?? '') === $catSlug;
        });
    }

    usort($articles, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $articles = array_slice(array_values($articles), 0, $limit);

    if (empty($articles)) {
        return '<!-- [recent_articles]: no results -->';
    }

    // Use the same HTML structure and CSS classes as the theme's native article cards
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
            // Index mode: load full item on demand for articles without a summary field
            $_fs  = $a['_file'] ?? (!empty($a['custom_slug']) ? $a['custom_slug'] : ($a['slug'] ?? ''));
            $_fll = $_fs !== '' ? sl_load_item('article', $_fs) : null;
            $summary = $_fll !== null ? _clean_excerpt($_fll['content'] ?? '', 150) : '';
        }

        $html .= '<article class="article-card">';

        if (!empty($a['image'])) {
            $html .= '<div class="article-thumbnail">'
                   . '<a href="' . $url . '">'
                   . '<img src="' . getBaseUrl() . htmlspecialchars($a['image'])
                   . '" alt="' . htmlspecialchars($a['title']) . '" loading="lazy">'
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
            $ellipsis = empty($a['summary']) ? '…' : '';
            $html .= '<div class="article-summary">' . htmlspecialchars($summary) . $ellipsis . '</div>';
        }
        $html .= '</div">';
        $html .= '<a href="' . $url . '" class="read-more">' . __t('read_more', 'Read more') . '</a>';
        $html .= '</article>';
    }
    $html .= '</section>';
    return $html;
}

/**
 * Render [recent_projects limit="N"]
 */
function render_recent_projects_shortcode(int $limit): string
{
    // Always load the full project index directly.
    // Same reason as render_recent_articles_shortcode: on single-item pages,
    // $GLOBALS['data']['project'] contains only the current item, not the full index.
    require_once dirname(__FILE__) . '/data-layer.php';
    $projects = sl_load_index('project');

    usort($projects, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $projects = array_slice($projects, 0, $limit);

    if (empty($projects)) {
        return '<!-- [recent_projects]: no results -->';
    }

    // Use the same HTML structure and CSS classes as the theme's native project cards
    $html = '<section class="projects-grid sc-projects-grid">';
    foreach ($projects as $p) {
        $slug    = !empty($p['custom_slug']) ? $p['custom_slug'] : $p['slug'];
        $catPath = !empty($p['category']) ? sanitizeSlug($p['category']) : null;
        $url     = cleanUrl('project', $slug, null, $catPath);
        $desc    = decodeHtmlEntities($p['meta_description'] ?? $p['description'] ?? '');
        $cardClass = !empty($p['image']) ? 'project-card' : 'project-card project-card-no-image';

        $html .= '<article class="' . $cardClass . '">';

        if (!empty($p['image'])) {
            $html .= '<div class="project-thumbnail">'
                   . '<img src="' . getBaseUrl() . htmlspecialchars($p['image'])
                   . '" alt="' . htmlspecialchars($p['title']) . '" loading="lazy">'
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
 * Render [articles_by_tag tag="slug" limit="N"]
 */
function render_articles_by_tag_shortcode(string $tag, int $limit): string
{
    return render_recent_articles_shortcode($limit, $tag);
}


/**
 * Generate a stateless CSRF token for the contact form.
 * Format: "{timestamp}.{HMAC-SHA256(timestamp, secret)}"
 */
function _contact_generate_csrf(): string
 {
     $secretFile = dirname(__FILE__) . '/private/contact.secret';
     if (file_exists($secretFile)) {
         $secret = trim(file_get_contents($secretFile));
         if (strlen($secret) === 64) {
             $ts = time();
             return $ts . '.' . hash_hmac('sha256', (string)$ts, $secret);
         }
     }
     // Generate, persist, and return a new secret
     $secret = bin2hex(random_bytes(32));
     @file_put_contents($secretFile, $secret, LOCK_EX);
     $ts = time();
     return $ts . '.' . hash_hmac('sha256', (string)$ts, $secret);
 }

/**
 * Generates the HTML for a contact form.
 * Called by the [contact_form] shortcode and contact page templates.
 * Includes CSRF token, honeypot, timing check, and optional hCaptcha.
 */
function render_contact_form_html(): string
{
    static $cssInjected = false;
    $cssLink = '';
    if (!$cssInjected) {
        $s           = loadSettings();
        $theme       = $s['active_theme'] ?? 'default';
        $cssLink     = '<link rel="stylesheet" href="' . getBaseUrl()
                     . 'theme/' . htmlspecialchars($theme) . '/css/contact.css">' . "\n";
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
        $msg        = htmlspecialchars($settings['contact_success_message']
                        ?? __t('contact_sent_ok', 'Your message has been sent. Thank you!'));
        $statusHtml = '<p class="contact-status success">' . $msg . '</p>' . "\n";
    } elseif (!empty($_GET['contact_error'])) {
        $msg        = htmlspecialchars($settings['contact_error_message']
                        ?? __t('contact_sent_error', 'An error occurred. Please try again.'));
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
    $html .= '<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off"'
           . ' style="display:none!important;position:absolute;left:-9999px;" aria-hidden="true">' . "\n";

    $html .= '<div class="contact-field"><label for="contact_name">'
           . htmlspecialchars($labelName) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<input type="text" id="contact_name" name="contact_name"'
           . ' placeholder="' . htmlspecialchars($placeholderN) . '" required autocomplete="name" maxlength="100"></div>' . "\n";

    $html .= '<div class="contact-field"><label for="contact_email">'
           . htmlspecialchars($labelEmail) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<input type="email" id="contact_email" name="contact_email"'
           . ' placeholder="' . htmlspecialchars($placeholderE) . '" required autocomplete="email" maxlength="254"></div>' . "\n";

    $html .= '<div class="contact-field"><label for="contact_message">'
           . htmlspecialchars($labelMessage) . ' <span class="required-mark" aria-hidden="true">*</span></label>'
           . '<textarea id="contact_message" name="contact_message" rows="6"'
           . ' placeholder="' . htmlspecialchars($placeholderM) . '" required minlength="10" maxlength="5000"></textarea></div>' . "\n";

    if ($hcaptchaEnabled) {
        $html .= '<div class="contact-field">'
               . '<div class="h-captcha" data-sitekey="' . htmlspecialchars($hcaptchaSiteKey) . '"></div>'
               . '</div>' . "\n";
    }

    $html .= '<div class="contact-field contact-submit">'
           . '<button type="submit">' . htmlspecialchars($labelSubmit) . '</button>'
           . '</div>' . "\n";
    $html .= '</form>' . "\n";

    return $html;
}

/**
 * Renders the content title based on user preferences
 * @param array $item Content item data
 * @return string HTML for content title
 */
function render_content_title($item)
{
    // Only show title if the show_title preference is enabled or not set (default to true)
    if (!isset($item['show_title']) || $item['show_title']) {
        return '<h1 class="content-title">' . htmlspecialchars($item['title']) . '</h1>';
    }

    return ''; // Return empty string if title should not be shown
}

/**
 * Renders meta tags for SEO
 */
function render_meta_tags($settings, $metaTitle, $metaDescription, $pageData = null)
{
    global $metaKeywords, $ogImage, $ogTitle, $ogDescription;
    // ENT_COMPAT encodes only double quotes (") — apostrophes are safe unencoded
    // inside double-quoted HTML attributes and should not appear as &#039; in source.
    $f = ENT_COMPAT | ENT_HTML5;
    $c = 'UTF-8';
    ob_start(); ?><meta property="og:site_name" content="<?php echo htmlspecialchars($settings['site_title'], $f, $c); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, $f, $c); ?>">
<?php if (!empty($metaKeywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords, $f, $c); ?>">
<?php endif; ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle ? $ogTitle : $metaTitle, $f, $c); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription ? $ogDescription : $metaDescription, $f, $c); ?>">
    <meta property="og:type" content="website">    
    <meta property="og:url" content="<?php echo htmlspecialchars("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", $f, $c); ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?php echo htmlspecialchars($ogImage, $f, $c); ?>">
<?php endif; ?>
<?php if ($settings["enable_seo"]): ?>
    <?php
        // Check if there's a custom canonical URL in the page data
        if ($pageData && !empty($pageData['canonical_url'])) {
            echo '
            <link rel="canonical" href="' . htmlspecialchars($pageData['canonical_url'], $f, $c) . '">';
        } else {
            // Use the auto-generated canonical URL as before
echo '<link rel="canonical" href="' . htmlspecialchars("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", $f, $c) . '">';
        } ?>
<?php endif; ?>
<?php
    return ob_get_clean();
}

/**
 * Renders script tags in the header
 */
function render_header_scripts($headerScripts)
{
    if (!isset($headerScripts) || !is_array($headerScripts)) {
        $headerScripts = [];
    }
    $base  = getBaseUrl();
    $theme = loadSettings()['active_theme'] ?? 'default';

    // ── System assets — prepended automatically for every theme ──
    // Themes no longer need to hardcode these individually in header.php.
    $system = [
        // Combined CMS system CSS (search, shortcodes, gallery-layout, lightbox) — 1 request
        '<link rel="stylesheet" href="' . $base . 'css/synaptikCSS.php">',
        // Active theme main stylesheet
        '    <link rel="stylesheet" href="' . $base . 'theme/' . $theme . '/css/style.css">',
        // CMS core JavaScript (search overlay, UI helpers)
        '    <script src="' . $base . 'js/main.js"></script>',
    ];
    // ── CMS language bridge — appended last so it runs after all scripts ──
    $lang = '    <script>window.CMS_LANG = ' . lang_js_bridge() . ';</script>';
    // ── Theme script.js — injected if the file exists ────────────────────
    $themeScript = [];
    if (file_exists(__DIR__ . '/theme/' . $theme . '/js/script.js')) {
        $themeScript[] = '    <script defer src="' . $base . 'theme/' . $theme . '/js/script.js"></script>';
    }
    return implode("\n", array_merge($system, $headerScripts, $themeScript, [$lang])) . "\n";
}

/**
 * Renders the navigation menu
 */
function render_menu($settings, $data)
{
    ob_start(); ?>
<ul>
    <?php if ($settings["use_custom_menu"] && !empty($settings["main_menu"])): ?>
    <li><a href="<?php echo cleanUrl("home"); ?>"><?php echo __t('home'); ?></a></li>
    <?php foreach ($settings["main_menu"] as $menuItem):
            $url = "";
    if ($menuItem["type"] === "custom") {
        if (strpos($menuItem["url"], "http") === 0) {
            $url = htmlspecialchars($menuItem["url"]);
        } else {
            $url = getBaseUrl() . ltrim(htmlspecialchars($menuItem["url"]), "/");
        }
    } elseif ($menuItem["type"] === "content") {
        if (isset($menuItem["content_type"]) && $menuItem["content_type"] === "list") {
            $url = cleanUrl($menuItem["content_slug"]);
        } elseif (isset($menuItem["content_type"]) && isset($menuItem["content_slug"])) {
            $url = cleanUrl($menuItem["content_type"], $menuItem["content_slug"]);
        } else {
            $url = getBaseUrl() . ltrim(htmlspecialchars($menuItem["url"]), "/");
        }
    } ?>
    <li>
        <a href="<?php echo $url; ?>"<?php echo !empty($menuItem['target']) ? ' target="' . htmlspecialchars($menuItem['target']) . '"' : ''; ?>><?php echo htmlspecialchars($menuItem["label"]); ?></a>
    </li>
    <?php endforeach; else: ?>
    <li><a href="<?php echo cleanUrl("home"); ?>"><?php echo __t('home'); ?></a></li>
    <li><a href="<?php echo cleanUrl("project"); ?>"><?php echo __t('projects'); ?></a></li>
    <li><a href="<?php echo cleanUrl("article"); ?>"><?php echo __t('articles'); ?></a></li>
    <li><a href="<?php echo cleanUrl("page"); ?>"><?php echo __t('pages'); ?></a></li>
    <?php
        // Display pages in the navigation
        if (isset($data["page"])) {
            foreach ($data["page"] as $page) {
                if (isset($page["show_in_menu"]) && $page["show_in_menu"]) {
                    $pageSlug = !empty($page["custom_slug"]) ? $page["custom_slug"] : $page["slug"];
                    echo '<li><a href="' . cleanUrl("page", $pageSlug) . '">' . htmlspecialchars($page["title"]) . '</a></li>';
                }
            }
        }
    // Display articles marked to show in menu
    if (isset($data["article"])) {
        foreach ($data["article"] as $article) {
            if (isset($article["show_in_menu"]) && $article["show_in_menu"]) {
                $articleSlug = !empty($article["custom_slug"]) ? $article["custom_slug"] : $article["slug"];
                echo '<li><a href="' . cleanUrl("article", $articleSlug) . '">' . htmlspecialchars($article["title"]) . '</a></li>';
            }
        }
    }
    endif; ?>
</ul>
<?php
    return ob_get_clean();
}

function renderHierarchicalMenu($settings, $data)
{
    if (!$settings["use_custom_menu"] || empty($settings["main_menu"])) {
        // Fallback to default menu rendering
        return renderDefaultMenu($data);
    }
    // First, organize the menu items by their parent-child relationships
    $menuTree = buildMenuTree($settings["main_menu"]);
    // Then render the menu HTML
    return renderMenuTree($menuTree);
}

function buildMenuTree($menuItems, $parentId = null)
{
    $tree = [];
    foreach ($menuItems as $item) {
        // Check if this item is a child of the current parent
        if (($parentId === null && empty($item['parent_id'])) ||
            (!empty($item['parent_id']) && $item['parent_id'] === $parentId)) {

            // Create a copy of the item for the tree
            $treeItem = $item;

            // Find children of this item
            $treeItem['children'] = buildMenuTree($menuItems, $item['id']);

            // Add the item to the tree
            $tree[] = $treeItem;
        }
    }
    return $tree;
}

function renderMenuTree($menuTree)
{
    $html = '<ul>';
    foreach ($menuTree as $item) {
        // Generate the URL for this item
        $url = generateMenuItemUrl($item);

        // Start the list item
        $html .= '
            <li';

        // Add submenu class if it has children
        if (!empty($item['children'])) {
            $html .= ' class="has-submenu"';
        }

        $html .= '>';

        // Add the link
        $target = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target']) . '"' : '';
$html .= '<a href="' . htmlspecialchars($url) . '"' . $target . '>' . htmlspecialchars($item['label']) . '</a>';

        // If this item has children, render them as a submenu
        if (!empty($item['children'])) {
            $html .= renderMenuTree($item['children']);
        }
        $html .= '</li>';
    }

    $html .= '
        </ul>';
    return $html;
}

function generateMenuItemUrl($item)
{
    $baseUrl = getBaseUrl();

    if ($item['type'] === 'custom') {
        // If it's an external URL (starts with http), use it as is
        if (strpos($item['url'], 'http') === 0) {
            return $item['url'];
        }

        // Otherwise, prefix with base URL
        return $baseUrl . ltrim($item['url'], '/');
    } elseif ($item['type'] === 'content') {
        // For content items, we need to recreate the URL with proper category
        global $data;

        // For content lists, just use the existing URL
        if (isset($item['content_type']) && $item['content_type'] === 'list') {
            return $baseUrl . ltrim($item['url'], '/');
        }

        // For specific content items, look up their category
        elseif (isset($item['content_type']) && isset($item['content_slug'])) {
            $contentType = $item['content_type'];
            $contentSlug = $item['content_slug'];
            $category = null;

            // Look up the actual content item to get its category
            if (isset($data[$contentType])) {
                foreach ($data[$contentType] as $contentItem) {
                    $itemSlug = !empty($contentItem['custom_slug']) ? $contentItem['custom_slug'] : $contentItem['slug'];

                    if ($itemSlug === $contentSlug && isset($contentItem['category']) && !empty($contentItem['category'])) {
                        $category = sanitizeSlug($contentItem['category']);
                        break;
                    }
                }
            }

            // Generate URL with correct category
            return cleanUrl($contentType, $contentSlug, null, $category);
        }

        // Fallback to stored URL
        return $baseUrl . ltrim($item['url'], '/');
    }

    return $baseUrl;
}

function renderDefaultMenu($data)
{
    $settings = loadSettings();

    // Always load fresh indices for menu rendering.
    // The $data parameter passed from the header may be filtered to a single item
    // on individual content pages (index.php replaces $data[$type] with just the
    // current item for single-item views), which would cause the menu to show
    // missing or incomplete sub-items depending on which page is being visited.
    $data = [
        'page'    => sl_load_index('page'),
        'article' => sl_load_index('article'),
        'project' => sl_load_index('project'),
    ];
    // Get menu style and ordering preferences
    $menuStyle = $settings['default_menu_style'] ?? 'flat';
    $orderBy   = $settings['default_menu_order'] ?? 'alphabetical';
    
    $html = '<ul>';
    $html .= '<li><a href="' . cleanUrl("home") . '">' . __t('home') . '</a></li>';
    
    $contentTypes = ['page', 'article', 'project'];
    
    // If flat style, just render all items in order
    if ($menuStyle === 'flat') {
        foreach ($contentTypes as $type) {
            if (!isset($data[$type]) || !is_array($data[$type])) {
                continue;
            }
            
            // Filter and sort items
            $items = array_filter($data[$type], function($item) {
                return !empty($item['show_in_menu']);
            });
            
            if (empty($items)) {
                continue;
            }
            
            $items = sortMenuItems($items, $orderBy);
            
            foreach ($items as $item) {
                $slug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
                $category = isset($item['category']) && !empty($item['category']) ? sanitizeSlug($item['category']) : null;
                $html .= '<li><a href="' . cleanUrl($type, $slug, null, $category) . '">' . htmlspecialchars($item['title']) . '</a></li>';
            }
        }
    } 
    // If grouped style, organize by content type with dropdowns
    else {
        foreach ($contentTypes as $type) {
            if (!isset($data[$type]) || !is_array($data[$type])) {
                continue;
            }
            
            // Filter items that should show in menu
            $items = array_filter($data[$type], function($item) {
                return !empty($item['show_in_menu']);
            });
            
            if (empty($items)) {
                continue;
            }
            
            $items = sortMenuItems($items, $orderBy);
            
            // Add parent type label with submenu
            $typeLabel = ucfirst($type) . 's'; // "Pages", "Articles", "Projects"
            $html .= '<li class="has-submenu">';
            $html .= '<a href="' . cleanUrl($type) . '">' . htmlspecialchars($typeLabel) . '</a>';
            $html .= '<ul>';
            
            foreach ($items as $item) {
                $slug = !empty($item['custom_slug']) ? $item['custom_slug'] : $item['slug'];
                $category = isset($item['category']) && !empty($item['category']) ? sanitizeSlug($item['category']) : null;
                $html .= '<li><a href="' . cleanUrl($type, $slug, null, $category) . '">' . htmlspecialchars($item['title']) . '</a></li>';
            }
            
            $html .= '</ul>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to sort menu items based on ordering preference
 */
function sortMenuItems($items, $orderBy) {
    usort($items, function($a, $b) use ($orderBy) {
        switch ($orderBy) {
            case 'menu_order':
                $orderA = isset($a['menu_order']) ? (int)$a['menu_order'] : 0;
                $orderB = isset($b['menu_order']) ? (int)$b['menu_order'] : 0;
                if ($orderA === $orderB) {
                    return strcasecmp($a['title'], $b['title']);
                }
                return $orderA - $orderB;
                
            case 'date_desc':
                return strcmp($b['date'] ?? '', $a['date'] ?? '');
                
            case 'date_asc':
                return strcmp($a['date'] ?? '', $b['date'] ?? '');
                
            case 'alphabetical':
            default:
                return strcasecmp($a['title'], $b['title']);
        }
    });
    return $items;
}

/**
 * Renders featured image for content
 */
function render_featured_image($item)
{
    if (!isset($item['image']) || (isset($item['show_featured_image']) && !$item['show_featured_image'])) {
        return '';
    }
    ob_start(); ?>
        
            <div class="featured-image">
                <img src="<?php echo getBaseUrl() . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
            </div>
<?php
    return ob_get_clean();
}

/**
 * Renders content publication date
 */
function render_content_date($item)
{
    if (!isset($item['date']) || !isset($item['show_date']) || !$item['show_date']) {
        return '';
    }
    $formattedDate = format_date($item['date']);
    return '<div class="article-date">' . htmlspecialchars($formattedDate) . '</div>';
}

/**
 * Renders content category
 */
function render_content_category($item)
{
    if (!isset($item['category']) || empty($item['category'])) {
        return '';
    }

    ob_start(); ?>
<div class="article-category">
    <a href="<?php echo getBaseUrl() . url_slug('category') . '/' . sanitizeSlug($item['category']); ?>/" class="category-badge">
        <?php echo htmlspecialchars($item['category']); ?>
    </a>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders content tags
 */
function render_content_tags($item)
{
    if (!isset($item['tags']) || !is_array($item['tags']) || empty($item['tags'])) {
        return '';
    }
    ob_start(); ?>
<div class="article-tags">
    <?php foreach ($item['tags'] as $tag): ?>
    <a href="<?php echo getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag); ?>/" class="tag-link">
        <?php echo htmlspecialchars($tag); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders content gallery
 */
function render_content_gallery($item)
{
    if (!isset($item['gallery']) || !is_array($item['gallery']) || empty($item['gallery'])) {
        return '';
    }
    ob_start(); ?>
<div class="content-gallery">
    <h3><?php echo __t('gallery'); ?></h3>
    <?php
        // Get the gallery layout, default to grid if not specified
        $galleryLayout = isset($item['gallery_layout']) ? $item['gallery_layout'] : 'grid';
    // Output the gallery
    echo renderGallery($item['gallery'], $galleryLayout); ?>
</div>
<?php
    return ob_get_clean();
}

/**
 * Renders footer content
 */
function render_footer_content()
{
    global $settings;

    ob_start();

    // Open the paragraph tag
    echo '<p class="footer-text">';
    $footer_text = isset($settings['footer_text']) ? $settings['footer_text'] : 'Developed with ♥ by Dorian • &copy; {year}';
    if (strpos($footer_text, '{year}') !== false) {
        $footer_text = str_replace('{year}', date("Y"), $footer_text);
    }

    echo $footer_text;

    // Login link
    if (isset($settings['footer_show_login']) && $settings['footer_show_login']) {
        echo ' | <span><a target="_blank" href="' . getBaseUrl() . 'admin/auth.php">Login</a></span>';
    }
    echo '</p>';

    // Social links
    if (isset($settings['footer_show_social']) && $settings['footer_show_social'] &&
        isset($settings['footer_social_links']) && is_array($settings['footer_social_links'])) {
        echo '<div class="social-links">';
        foreach ($settings['footer_social_links'] as $social) {
            if (isset($social['platform']) && isset($social['url'])) {
                $icon = get_social_icon($social['platform']);
                echo '<a href="' . htmlspecialchars($social['url']) . '" class="social-icon" target="_blank">' . $icon . '</a>';
            }
        }
        echo '</div>';
    }

    return ob_get_clean();
}

// Helper function to get social icon SVG
function get_social_icon($platform)
{
    switch (strtolower($platform)) {
        case 'instagram':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>';
        case 'twitter':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path></svg>';
        case 'github':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>';
        case 'facebook':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>';
        case 'linkedin':
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>';
        default:
            return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';
    }
}

/**
 * Renders home page articles section
 */
function render_home_articles($data, $settings)
{
    if (!$settings['show_articles_on_homepage'] || !isset($data['article']) || empty($data['article'])) {
        return '';
    }
    ob_start();

    // Initialize pagination variables
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $articlesPerPage = $settings['articles_per_page'];
    $totalPages = 1;

    // Sort articles by date if available
    $articles = $data['article'];
    usort($articles, function ($a, $b) {
        if (isset($a['date']) && isset($b['date'])) {
            return strcmp($b['date'], $a['date']); // Sort in descending order
        }
        return 0;
    });

    // Filter articles to only show those marked for homepage display
    if (isset($settings['show_articles_on_homepage'])) {
        $articles = array_filter($articles, function ($article) {
            return !isset($article['show_on_homepage']) || $article['show_on_homepage'] === true;
        });
    }

    $totalArticles = count($articles);
    $totalPages = ceil($totalArticles / $articlesPerPage);

    // Calculate start and end indices for current page
    $startIndex = ($currentPage - 1) * $articlesPerPage;

    // Display articles for current page
    $articlesSubset = array_slice($articles, $startIndex, $articlesPerPage); ?>
    <h2><?php echo __t('latest_work'); ?></h2>
    <section class="articles-grid">
    <?php foreach ($articlesSubset as $article):
                echo render_article_card($article);
    endforeach; ?>
    </section>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
        <span class="current-page"><?php echo $i; ?></span>
        <?php else: ?>
        <a href="<?php echo cleanUrl('home'); ?>?page=<?php echo $i; ?>" class="page-link"><?php echo $i; ?></a>
        <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif;

    return ob_get_clean();
}

/**
 * Renders a single article card
 */
function render_article_card($article)
{
    $articleSlug = !empty($article['custom_slug']) ? $article['custom_slug'] : $article['slug'];
    // Detect whether this item is actually a page — pages have a page_template field,
    // articles and projects never do. _content_type is set explicitly on category/tag results.
    $_itemType    = ($article['_content_type'] ?? null) ?: (array_key_exists('page_template', $article) ? 'page' : 'article');
    $categorySlug = ($_itemType !== 'page' && isset($article['category']) && !empty($article['category'])) ? sanitizeSlug($article['category']) : null;
    $articleLink  = cleanUrl($_itemType, $articleSlug, null, $categorySlug);

    // Delegate to theme partial if theme/active/partials/article-card.php exists
    $html = loadThemePartial('article-card', [
        'article'      => $article,
        'article_link' => $articleLink,
    ]);
    if ($html !== null) {
        return $html;
    }

    ob_start(); ?>
        <article class="article-card">
        <?php if (isset($article['image'])): ?>
        <div class="article-thumbnail">
                <a href="<?php echo $articleLink; ?>">
                    <img src="<?php echo getBaseUrl() . htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                </a>
            </div>
        <?php endif; ?>
        <h3><a href="<?php echo $articleLink; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h3>
        <?php if (isset($article['date']) && isset($article['show_date']) && $article['show_date']): ?>
        <div class="article-date"><?php $settings = loadSettings();
        echo htmlspecialchars(date($settings['date_format'] ?? 'Y-m-d', strtotime($article['date']))); ?></div>
        <?php endif; ?>
        <?php
                if (isset($article['tags']) && is_array($article['tags']) && !empty($article['tags'])) {
                    echo '
            <div class="article-tags">';
                    foreach ($article['tags'] as $tag) {
                        echo '
                <a href="' . getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/" class="tag-link">' . htmlspecialchars($tag) . '</a>';
                    }
                    echo '
            </div>';
                }
        // Display a short excerpt from the content
                $excerpt = _clean_excerpt($article['content'] ?? '', 150);
                ?>
    
            <!-- <div class="article-summary">
                <?php echo get_article_summary($article); ?>
            </div> -->
            <?php $__summary = get_article_summary($article); if ($__summary !== ''): ?>
            <div class="article-summary">
                <?php echo $__summary; ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo $articleLink; ?>" class="read-more"><?php echo __t('read_more'); ?></a>
        </article>
<?php
    return ob_get_clean();
}

/**
 * Returns the display summary for an article.
 * Uses explicit summary if set, otherwise falls back to a clean auto-generated excerpt.
 */
function get_article_summary(array $article, int $length = 150): string
 {
     if (!empty($article['summary'])) {
         return htmlspecialchars($article['summary']);
     }
     // Full content already loaded (single item view)
     if (!empty($article['content'])) {
         return htmlspecialchars(_clean_excerpt($article['content'], $length)) . '...';
     }
     // Lightweight index mode: content not loaded for this article.
     // Load the individual item file on demand to generate the excerpt.
     // Only triggers for articles that have no summary field — typically a small subset.
     $fileSlug = !empty($article['_file'])
         ? $article['_file']
         : (!empty($article['custom_slug']) ? $article['custom_slug'] : ($article['slug'] ?? ''));
     if ($fileSlug !== '') {
         require_once dirname(__FILE__) . '/data-layer.php';
         $full = sl_load_item('article', $fileSlug);
         if ($full !== null && !empty($full['content'])) {
             return htmlspecialchars(_clean_excerpt($full['content'], $length)) . '...';
         }
     }
     return '';
 }

/**
 * Renders home page projects section
 */
function render_home_projects($data, $settings)
{
    if (!isset($data['project']) || empty($data['project']) || !$settings['show_projects_on_homepage']) {
        return '';
    }
    // Get the number of projects to display from settings
    $projectsPerPage = isset($settings['projects_per_page']) ? (int)$settings['projects_per_page'] : 3;

    ob_start(); ?>
        <h2><?php echo __t('featured_projects'); ?></h2>
        <section class="projects-grid">
    <?php
        // Sort projects by date if available
        $projects = $data['project'];
    usort($projects, function ($a, $b) {
        if (isset($a['date']) && isset($b['date'])) {
            return strcmp($b['date'], $a['date']); // Sort in descending order
        }
        return 0;
    });

    // Filter projects to only show those marked for homepage display
    $projects = array_filter($projects, function ($project) {
        return !isset($project['show_on_homepage']) || $project['show_on_homepage'] === true;
    });

    // Get the number of projects based on the setting
    $featuredProjects = array_slice($projects, 0, $projectsPerPage);

    foreach ($featuredProjects as $project):
            echo render_project_card($project);
    endforeach; ?>
    </section>
    <div class="view-all">
        <a href="<?php echo cleanUrl('project'); ?>"><?php echo __t('view_all_projects'); ?></a>
    </div>
<?php
    return ob_get_clean();
}

/**
 * Renders a single project card
 */
function render_project_card($project)
{
    $projectSlug = !empty($project['custom_slug']) ? $project['custom_slug'] : $project['slug'];
    $categorySlug = (isset($project['category']) && !empty($project['category'])) ? sanitizeSlug($project['category']) : null;
    $projectLink = cleanUrl('project', $projectSlug, null, $categorySlug);
    $cardClass = isset($project['image']) ? 'project-card' : 'project-card project-card-no-image';

    // Delegate to theme partial if theme/active/partials/project-card.php exists
    $html = loadThemePartial('project-card', [
        'project'      => $project,
        'project_link' => $projectLink,
        'card_class'   => $cardClass,
    ]);
    if ($html !== null) {
        return $html;
    }

    ob_start(); ?>
        <article class="<?php echo $cardClass; ?>">
    <?php if (isset($project['image'])): ?>
        <div class="project-thumbnail">
                <img src="<?php echo getBaseUrl() . htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>">
            </div>
    <?php endif; ?>
        <div class="project-overlay">
                <h3><?php echo htmlspecialchars($project['title']); ?></h3>
        <?php if (isset($project['date']) && isset($project['show_date']) && $project['show_date']): ?>
        <div class="project-date"><?php $settings = loadSettings();
                echo htmlspecialchars(date($settings['date_format'] ?? 'Y-m-d', strtotime($project['date']))); ?></div>
            <?php endif; ?>
            <?php
            if (isset($project['tags']) && is_array($project['tags']) && !empty($project['tags'])) {
                echo '<div class="project-tags">';
                foreach ($project['tags'] as $tag) {
                    echo '
                <a href="' . getBaseUrl() . url_slug('tag') . '/' . sanitizeSlug($tag) . '/" class="tag-link">' . htmlspecialchars($tag) . '</a>';
                }
                echo '
            </div>';
            } ?>
            <?php if (isset($project['description']) && !empty($project['description'])): ?>
            
            <div class="project-excerpt"><?php echo html_entity_decode($project['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
            <?php elseif (isset($project['meta_description']) && !empty($project['meta_description'])): ?>
            <div class="project-seo-desc"><?php echo html_entity_decode($project['meta_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
            <?php endif; ?>
            
            <a href="<?php echo $projectLink; ?>" class="view-project"><?php echo __t('view_project'); ?></a>
            </div>
        </article>
<?php
    return ob_get_clean();
}

/**
 * Get theme resource path
 * @param string $resource Resource type (css, js, template)
 * @param string $file Filename within the resource directory
 * @return string Path to the resource file
 */
function getThemeResourcePath($resource, $file = '')
{
    $settings = loadSettings();
    $theme = isset($settings['active_theme']) ? $settings['active_theme'] : 'default';
    return "theme/{$theme}/{$resource}/{$file}";
}

/**
 * Load a theme partial file and return its rendered HTML.
 *
 * Looks for theme/{active}/partials/{name}.php. Returns null when the file does
 * not exist so the caller can transparently fall back to its own default output.
 *
 * Variables passed via $vars are extracted into the partial's local scope.
 * EXTR_SKIP is used so the partial cannot accidentally overwrite $name or $vars.
 *
 * Usage:
 *   $html = loadThemePartial('article-card', ['article' => $article, 'article_link' => $link]);
 *   if ($html !== null) { return $html; }
 *   // ... default fallback rendering ...
 *
 * @param string $name  Partial filename without .php extension (e.g. 'article-card')
 * @param array  $vars  Variables to expose inside the partial
 * @return string|null  Rendered HTML, or null if the partial was not found
 */
function loadThemePartial(string $name, array $vars = []): ?string
{
    $settings = loadSettings();
    $theme    = $settings['active_theme'] ?? 'default';
    $path     = __DIR__ . '/theme/' . $theme . '/partials/' . $name . '.php';

    if (!file_exists($path)) {
        return null;
    }

    ob_start();
    extract($vars, EXTR_SKIP);
    include $path;
    return ob_get_clean();
}

/**
 * Renders search UI elements
 * @return string HTML for search UI
 */
function render_search_ui()
{
    // The overlay is always rendered regardless of the showSearchIcon setting.
    // The icon visibility is controlled separately by render_search_icon().
    // This ensures Ctrl+K always works even when the visible icon is disabled.
    ob_start(); ?>
<div id="search-overlay" class="search-overlay">
    <div class="search-container">
        <button id="close-search">×</button>
        <div class="search-input-container">
            <input type="text" id="search-input" placeholder="<?php echo htmlspecialchars(__t('search_placeholder')); ?>">
            <button class="search-clear-btn">×</button>
        </div>
        <div class="search-options">
            <label>
                <input type="checkbox" id="search-in-content" checked>
                <?php echo __t('search_in_content'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-articles" checked>
                <?php echo __t('articles'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-pages" checked>
                <?php echo __t('pages'); ?>
            </label>
            <label>
                <input type="checkbox" id="search-projects" checked>
                <?php echo __t('projects'); ?>
            </label>
        </div>
        <div id="search-results"></div>
    </div>
</div>
<?php
    return ob_get_clean();
}

function render_search_icon()
{
    global $appSettings;
    // Only render if the setting is enabled
    if (isset($appSettings['show_search_icon']) && !$appSettings['show_search_icon']) {
        return '';
    }

    ob_start(); ?>
<li class="search-icon">
    <a href="#" id="search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </a>
</li>
<?php
    return ob_get_clean();
}