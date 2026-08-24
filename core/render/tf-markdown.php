<?php
/**
 * Markdown Parser — SynaptikCMS
 * Converts Markdown content to HTML. CMS shortcodes ([...]) are left intact
 * so tf-shortcodes.php can process them afterward.
 */

/**
 * Convert a Markdown string to HTML.
 *
 * @param  string $md  Raw Markdown input.
 * @return string      HTML output (shortcodes not yet parsed).
 */
function _md_to_html(string $md): string
{
    $md = str_replace("\r\n", "\n", $md);
    $md = str_replace("\r",   "\n", $md);

    // -------------------------------------------------------------------------
    // Step 1 — protect fenced code blocks FIRST so that [^n]: lines inside
    // them are never mistaken for footnote definitions.
    // We use a temporary placeholder map keyed by a NUL-delimited token.
    // -------------------------------------------------------------------------
    // The fence regex matches N backticks (N >= 3) and requires the closing
    // fence to use the same number, so a 4-backtick wrapper around a 3-backtick
    // inner block is handled correctly — matching CommonMark fenced-code rules.
    $earlyCodeBlocks = [];
    $md = preg_replace_callback(
        '/^(`{3,})([^\n]*)\n([\s\S]*?)^\1[ \t]*$/m',
        function ($m) use (&$earlyCodeBlocks) {
            $token = "\x00ECODE" . count($earlyCodeBlocks) . "\x00";
            $earlyCodeBlocks[$token] = $m[0]; // keep raw markdown, restored before main pass
            return $token . "\n";
        },
        $md
    );

    // Step 2 — extract footnote definitions (now safe: code blocks are opaque)
    $footnotes = [];
    $md = preg_replace_callback(
        '/^\[\^([^\]]+)\]:\s*(.+)$/m',
        function ($m) use (&$footnotes) {
            $footnotes[$m[1]] = $m[2];
            return '';
        },
        $md
    );

    // Step 3 — restore the raw fenced blocks so the main parsing pass can
    // handle them normally (syntax-highlight, bracket encoding, etc.).
    $md = strtr($md, $earlyCodeBlocks);
    $md = trim($md);

    // Container directives  :::type [optional title]\n...\n:::
    // Replaced with an opaque token, not the HTML directly — the paragraph
    // pass further down wraps any line it doesn't recognize in <p>...</p>,
    // and a <div> nested inside a <p> is invalid HTML that browsers "fix" by
    // closing the <p> early, breaking the layout. The token is restored to
    // the real HTML in the main loop below, the same way fenced code blocks
    // (search $codeBlocks) are protected from paragraph-wrapping.
    $calloutBlocks = [];
    if (strpos($md, ':::') !== false) {
        $md = preg_replace_callback(
            '/^:::([\w]+)([^\n]*)\n([\s\S]*?)^:::[ \t]*$/m',
            static function ($m) use (&$calloutBlocks) {
                $alias   = strtolower(trim($m[1]));
                $title   = trim($m[2]);
                $body    = trim($m[3]);
                $typeMap = [
                    'note' => 'info', 'info' => 'info',
                    'warning' => 'warning', 'caution' => 'warning',
                    'tip' => 'tip', 'success' => 'tip',
                    'danger' => 'danger', 'error' => 'danger',
                ];
                $iconMap = [
                    'info'    => '&#x2139;&#xFE0F;', 'warning' => '&#x26A0;&#xFE0F;',
                    'tip'     => '&#x1F4A1;',         'danger'  => '&#x1F6AB;',
                ];
                $cssType   = $typeMap[$alias]  ?? 'info';
                $icon      = $iconMap[$cssType] ?? '&#x2139;&#xFE0F;';
                $bodyHtml  = _md_to_html($body);
                $titleHtml = $title !== ''
                    ? '<p class="sc-callout-title"><strong>' . htmlspecialchars($title) . '</strong></p>'
                    : '';
                $html = '<div class="sc-callout sc-callout-' . $cssType . '">'
                      . '<span class="sc-callout-icon">' . $icon . '</span>'
                      . '<div class="sc-callout-body">' . $titleHtml . $bodyHtml . '</div>'
                      . '</div>';

                $token = '\x00CALLOUT' . count($calloutBlocks) . '\x00';
                $calloutBlocks[$token] = $html;
                return $token . "\n";
            },
            $md
        );
    }

    // Protect fenced code blocks — encode brackets so shortcode parsers ignore them.
    // Same N-backtick rule: closing fence must match the opening fence length.
    $codeBlocks = [];
    $md = preg_replace_callback(
        '/^(`{3,})([^\n]*)\n([\s\S]*?)^\1[ \t]*$/m',
        function ($m) use (&$codeBlocks) {
            $lang  = htmlspecialchars(trim($m[2]));
            $code  = htmlspecialchars($m[3]);
            $code  = str_replace(['[', ']'], ['&#91;', '&#93;'], $code);
            $cls   = $lang ? ' class="language-' . $lang . '"' : '';
            $token = '\x00CODE' . count($codeBlocks) . '\x00';
            $codeBlocks[$token] = '<pre><code' . $cls . '>' . $code . '</code></pre>';
            return $token . "\n";
        },
        $md
    );

    // Protect inline code — handles both single and double backtick spans.
    // Pattern: N backticks, any content (no newline), same N backticks.
    $inlineCodes = [];
    $md = preg_replace_callback(
        '/(`{1,2})([^`\n]+?)\1/',
        function ($m) use (&$inlineCodes) {
            $token               = '\x00IC' . count($inlineCodes) . '\x00';
            $content             = htmlspecialchars($m[2]);
            $content             = str_replace(['[', ']'], ['&#91;', '&#93;'], $content);
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

        if (strpos($line, '\x00CODE') === 0) {
            $output .= $codeBlocks[rtrim($line)] ?? rtrim($line);
            $i++;
            continue;
        }

        if (strpos($line, '\x00CALLOUT') === 0) {
            $output .= ($calloutBlocks[rtrim($line)] ?? rtrim($line)) . "\n";
            $i++;
            continue;
        }

        if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
            $output .= '<hr>' . "\n";
            $i++;
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $m)) {
            $level  = strlen($m[1]);
            $id     = function_exists('sanitizeSlug') ? ' id="toc-' . sanitizeSlug(strip_tags($m[2])) . '"' : '';
            $output .= '<h' . $level . $id . '>' . _md_inline($m[2]) . '</h' . $level . '>' . "\n";
            $i++;
            continue;
        }

        if (preg_match('/^>\s?(.*)/', $line, $m)) {
            $bqLines = [];
            while ($i < $n && preg_match('/^>\s?(.*)/', $lines[$i], $bm)) {
                $bqLines[] = $bm[1];
                $i++;
            }
            $output .= '<blockquote><p>' . _md_inline(implode("\n", $bqLines)) . '</p></blockquote>' . "\n";
            continue;
        }

        if (preg_match('/^([*+\-])\s+(.+)/', $line, $m)) {
            $output .= _md_render_list($lines, $i, $n, 0, 'ul');
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)/', $line, $m)) {
            $output .= _md_render_list($lines, $i, $n, 0, 'ol');
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
            $i += 2;
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

        if (trim($line) === '') {
            $i++;
            continue;
        }

        $paraLines = [];
        while ($i < $n
            && trim($lines[$i]) !== ''
            && !preg_match('/^#{1,6}\s/', $lines[$i])
            && !preg_match('/^[ \t]*[*+\-]\s/', $lines[$i])
            && !preg_match('/^[ \t]*\d+\.\s/', $lines[$i])
            && !preg_match('/^>/', $lines[$i])
            && !preg_match('/^\s*([-*_])\1{2,}\s*$/', $lines[$i])
            && strpos($lines[$i], '\x00CODE') !== 0
            && strpos($lines[$i], '\x00CALLOUT') !== 0
        ) {
            $paraLines[] = $lines[$i];
            $i++;
        }
        if ($paraLines) {
            $paraText = implode("\n", $paraLines);
            $paraText = preg_replace('/  \n/', '<br>', $paraText);
            $output  .= '<p>' . _md_inline($paraText) . '</p>' . "\n";
        } else {
            // Safety: if no block matched and no para lines collected, advance to prevent infinite loop
            $i++;
        }
    }

    $output = strtr($output, $inlineCodes);

    // Append footnote list if any definitions were found
    if ($footnotes) {
        $currentPath = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
        $output .= '<ol class="md-footnotes">' . "\n";
        foreach ($footnotes as $key => $text) {
            $keyEsc  = htmlspecialchars($key);
            $refId   = 'fn-'    . $keyEsc;
            $backId  = 'fnref-' . $keyEsc;
            $output .= '<li id="' . $refId . '">';
            $output .= _md_inline($text);
            $output .= ' <a href="' . $currentPath . '#' . $backId . '" class="md-fn-backref" aria-label="back">&#8617;</a>';
            $output .= "</li>\n";
        }
        $output .= '</ol>' . "\n";
    }

    return $output;
}

/**
 * Recursively render a Markdown list (ul or ol) from a lines array.
 * Handles arbitrary nesting via indentation (2 or 4 spaces, or a tab).
 *
 * @param  array  $lines    All lines of the document.
 * @param  int    &$i       Current line index, advanced by reference.
 * @param  int    $n        Total line count.
 * @param  int    $indent   Expected indentation level in spaces for this list.
 * @param  string $tag      'ul' or 'ol'.
 * @return string           HTML for the complete list.
 */
function _md_render_list(array $lines, int &$i, int $n, int $indent, string $tag): string
{
    $html = '<' . $tag . ">\n";

    if ($indent === 0) {
        // Root level: no leading whitespace
        $ulPat = '/^[*+\-]\s+(.+)/';
        $olPat = '/^\d+\.\s+(.+)/';
    } else {
        // Nested level: exactly $indent spaces/tabs, then the marker
        $ulPat = '/^[ \t]{' . $indent . '}[*+\-]\s+(.+)/';
        $olPat = '/^[ \t]{' . $indent . '}\d+\.\s+(.+)/';
    }
    $pat = ($tag === 'ul') ? $ulPat : $olPat;

    while ($i < $n) {
        $line = $lines[$i];

        // Current-level item
        if (preg_match($pat, $line, $m)) {
            $i++;
            $itemText = trim($m[1]);
            $html    .= '<li>' . _md_inline($itemText);

            // Detect child indentation from the very next line
            if ($i < $n) {
                $nextLine = $lines[$i];
                if (preg_match('/^([ \t]+)[*+\-\d]/', $nextLine, $ind)) {
                    $childIndent = strlen(str_replace("\t", '  ', $ind[1]));
                    if ($childIndent > $indent) {
                        $childTag = preg_match('/^[ \t]+\d+\./', $nextLine) ? 'ol' : 'ul';
                        $html    .= "\n" . _md_render_list($lines, $i, $n, $childIndent, $childTag);
                    }
                }
            }

            $html .= "</li>\n";
            continue;
        }

        // A blank line is tolerated; continue only if the next non-blank line
        // is still a list item at the current indent level
        if (trim($line) === '') {
            $j = $i + 1;
            while ($j < $n && trim($lines[$j]) === '') {
                $j++;
            }
            if ($j < $n && preg_match($pat, $lines[$j])) {
                $i = $j;
                continue;
            }
            break;
        }

        // Any other content ends this list level
        break;
    }

    return $html . '</' . $tag . ">\n";
}

/**
 * Allow-list URL sanitizer for Markdown links.
 * Strips control characters (browsers strip them before scheme evaluation),
 * then permits only safe schemes and relative targets.
 * Everything else becomes '#'.
 */
function _md_sanitize_url(string $url): string
{
    // Strip control characters (U+0000–U+001F) that browsers ignore in schemes
    $url = preg_replace('/[\x00-\x1F]+/', '', $url);
    $url = trim($url);
    if ($url === '') return '#';

    // Extract scheme (everything before the first ':')
    if (preg_match('/^([a-zA-Z][a-zA-Z0-9+\-.]*):/', $url, $m)) {
        $scheme = strtolower($m[1]);
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) return '#';
    }
    // No scheme: relative path, #anchor, or protocol-relative //host — all safe

    return $url;
}

/**
 * Process inline Markdown: images, links, bold, italic, strikethrough, text color.
 * CMS shortcodes are preserved intact.
 */
function _md_inline(string $text): string
{
    // Images support an optional size suffix inside the URL parentheses:
    //   ![alt](url =300x)      -> width=300
    //   ![alt](url =x200)      -> height=200
    //   ![alt](url =300x200)   -> width=300 height=200
    // Values without units default to pixels; a trailing '%' keeps the unit.
    $text = preg_replace_callback(
        '/!\[([^\]]*)\]\(\s*([^)\s]+?)(?:\s+=(\d+%?)?x(\d+%?)?)?\s*\)/',
        function ($m) {
            $alt   = htmlspecialchars($m[1], ENT_QUOTES);
            $src   = htmlspecialchars(trim($m[2]), ENT_QUOTES);
            $w     = $m[3] ?? '';
            $h     = $m[4] ?? '';
            $attrs = '';
            $style = 'max-width:100%';
            if ($w !== '') {
                $attrs .= ' width="' . $w . '"';
                $style  = 'width:' . $w . (ctype_digit($w) ? 'px' : '') . ';max-width:100%;height:auto';
            }
            if ($h !== '') {
                $attrs .= ' height="' . $h . '"';
            }
            // Body-content images sit below the header, title and intro, so
            // they are never the LCP element — unlike the site logo and the
            // featured image, which stay eager on purpose.
            return '<img src="' . $src . '" alt="' . $alt . '"' . $attrs . ' loading="lazy" decoding="async" style="' . $style . '">';
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        function ($m) {
            $target = preg_match('/\{:target="_blank"\}/', $m[2]) ? ' target="_blank" rel="noopener"' : '';
            $url    = trim(preg_replace('/\{[^}]+\}/', '', $m[2]));
            $url    = _md_sanitize_url($url);
            $label  = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $target . '>' . $label . '</a>';
        },
        $text
    );

    // Text color — {#hex:text} -> <span style="color:#hex">text</span>. Runs
    // before bold/italic/strike below so those can still apply inside colored
    // text (e.g. {#e74c3c:some **bold** word}). The editor's color picker
    // inserts this syntax directly (see editor-markdown.js) instead of raw
    // HTML, matching the plain-text feel of the rest of the Markdown editor.
    $text = preg_replace_callback(
        '/\{(#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?):([^{}]+)\}/',
        function ($m) {
            return '<span style="color:' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '">' . $m[2] . '</span>';
        },
        $text
    );

    // Underscore emphasis is restricted to non-word boundaries (CommonMark rule)
    // so intraword underscores in filenames or URLs (e.g. editor_wysiwyg_mode.webp)
    // are not converted to <em>/<strong> tags inside img/link attributes.
    $text = preg_replace('/\*\*(.+?)\*\*|(?<!\w)__(.+?)__(?!\w)/', '<strong>$1$2</strong>', $text);
    $text = preg_replace('/\*(.+?)\*|(?<!\w)_(.+?)_(?!\w)/',       '<em>$1$2</em>',         $text);
    $text = preg_replace('/~~(.+?)~~/',                              '<s>$1</s>',             $text);

    // Footnote references [^1] -> <sup><a href="{url}#fn-1" id="fnref-1">[1]</a></sup>
    // href must include the current page path because <base href="..."> in the
    // <head> rebases bare fragment-only hrefs ("#fn-1") to the site root.
    $currentPath = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), ENT_QUOTES, 'UTF-8');
    $text = preg_replace_callback(
        '/\[\^([^\]]+)\]/',
        function ($m) use ($currentPath) {
            $key    = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $refId  = 'fn-'    . $key;
            $backId = 'fnref-' . $key;
            return '<sup><a href="' . $currentPath . '#' . $refId . '" id="' . $backId . '" class="md-fnref">[' . $key . ']</a></sup>';
        },
        $text
    );

    // Superscript ^text^
    $text = preg_replace('/\^([^\^\s]+)\^/', '<sup>$1</sup>', $text);

    // Autolink bare URLs not already wrapped in an <a> tag or href attribute
    $text = preg_replace_callback(
        '/(?<!["\'=>])\b(https?:\/\/[^\s<>"\)\]]+)/',
        function ($m) {
            $url = htmlspecialchars($m[1], ENT_QUOTES);
            return '<a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>';
        },
        $text
    );

    return $text;
}
