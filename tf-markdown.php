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

    // Container directives  :::type [optional title]\n...\n:::
    if (strpos($md, ':::') !== false) {
        $md = preg_replace_callback(
            '/^:::([\w]+)([^\n]*)\n([\s\S]*?)^:::[ \t]*$/m',
            static function ($m) {
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
                return '<div class="sc-callout sc-callout-' . $cssType . '">'
                     . '<span class="sc-callout-icon">' . $icon . '</span>'
                     . '<div class="sc-callout-body">' . $titleHtml . $bodyHtml . '</div>'
                     . '</div>';
            },
            $md
        );
    }

    // Protect fenced code blocks — encode brackets so shortcode parsers ignore them
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

    // Protect inline code — same bracket encoding
    $inlineCodes = [];
    $md = preg_replace_callback(
        '/`([^`\n]+)`/',
        function ($m) use (&$inlineCodes) {
            $token               = '\x00IC' . count($inlineCodes) . '\x00';
            $content             = htmlspecialchars($m[1]);
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

        if (preg_match('/^([*+-])\s+(.+)/', $line, $m)) {
            $output .= '<ul>' . "\n";
            while ($i < $n && preg_match('/^[*+-]\s+(.+)/', $lines[$i], $lm)) {
                $output .= '<li>' . _md_inline(trim($lm[1])) . '</li>' . "\n";
                $i++;
            }
            $output .= '</ul>' . "\n";
            continue;
        }

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
            $paraText = preg_replace('/  \n/', '<br>', $paraText);
            $output  .= '<p>' . _md_inline($paraText) . '</p>' . "\n";
        }
    }

    return strtr($output, $inlineCodes);
}

/**
 * Process inline Markdown: images, links, bold, italic, strikethrough.
 * CMS shortcodes are preserved intact.
 */
function _md_inline(string $text): string
{
    $text = preg_replace(
        '/!\[([^\]]*)\]\(([^)]+)\)/',
        '<img src="$2" alt="$1" style="max-width:100%">',
        $text
    );

    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        function ($m) {
            $target = preg_match('/\{:target="_blank"\}/', $m[2]) ? ' target="_blank" rel="noopener"' : '';
            $url    = preg_replace('/\{[^}]+\}/', '', $m[2]);
            return '<a href="' . htmlspecialchars(trim($url)) . '"' . $target . '>' . $m[1] . '</a>';
        },
        $text
    );

    $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/', '<strong>$1$2</strong>', $text);
    $text = preg_replace('/\*(.+?)\*|_(.+?)_/', '<em>$1$2</em>',               $text);
    $text = preg_replace('/~~(.+?)~~/',           '<s>$1</s>',                  $text);

    return $text;
}
