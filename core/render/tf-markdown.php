<?php
function _md_to_html(string $md): string
{
    $md = str_replace("\r\n", "\n", $md);
    $md = str_replace("\r",   "\n", $md);

    // Step 1 — protect fenced code blocks FIRST so that [^n]: lines inside them are never mistaken for footnote definitions.
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

    $linkRefs = [];
    $md = preg_replace_callback(
        '/^[ \t]{0,3}\[([^\^\]][^\]]*)\]:[ \t]*<?([^\s>]+)>?(?:[ \t]+(?:"([^"]*)"|\'([^\']*)\'|\(([^)]*)\)))?[ \t]*$/m',
        function ($m) use (&$linkRefs) {
            $label = strtolower(trim($m[1]));
            if ($label === '') return $m[0];
            $linkRefs[$label] = [
                'url'   => $m[2],
                'title' => ($m[3] ?? '') !== '' ? $m[3] : ((($m[4] ?? '') !== '') ? $m[4] : ($m[5] ?? '')),
            ];
            return '';
        },
        $md
    );

    $prevRefs = _md_ref_registry();
    _md_ref_registry(array_replace($prevRefs, $linkRefs), true);

    // Step 3 — restore the raw fenced blocks so the main parsing pass can handle them normally (syntax-highlight, bracket encoding, etc.).
    $md = strtr($md, $earlyCodeBlocks);
    $md = trim($md);

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

    _md_ref_registry($prevRefs, true);

    return $output;
}

function _md_ref_registry(?array $set = null, bool $replace = false): array
{
    static $refs = [];
    if ($replace) {
        $refs = $set ?? [];
    }
    return $refs;
}

function _md_render_list(array $lines, int &$i, int $n, int $indent, string $tag): string
{
    $html = '<' . $tag . ">\n";

    if ($indent === 0) {
        $ulPat = '/^[*+\-]\s+(.+)/';
        $olPat = '/^\d+\.\s+(.+)/';
    } else {
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

        break;
    }

    return $html . '</' . $tag . ">\n";
}

function _md_sanitize_url(string $url): string
{
    $url = preg_replace('/[\x00-\x1F]+/', '', $url);
    $url = trim($url);
    if ($url === '') return '#';

    if (preg_match('/^([a-zA-Z][a-zA-Z0-9+\-.]*):/', $url, $m)) {
        $scheme = strtolower($m[1]);
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) return '#';
    }

    return $url;
}

function _md_inline(string $text): string {
    $refs = _md_ref_registry();
    if ($refs && strpos($text, '][') !== false) {
        $text = preg_replace_callback(
            '/(!?)\[([^\]]*)\]\[([^\]]*)\]/',
            function ($m) use ($refs) {
                $label = strtolower(trim($m[3] !== '' ? $m[3] : $m[2]));
                if (!isset($refs[$label])) return $m[0];
                $ref   = $refs[$label];
                $safeTitle = str_replace(['"', ')'], '', $ref['title']);
                $title     = $safeTitle !== '' ? ' "' . $safeTitle . '"' : '';
                return $m[1] . '[' . $m[2] . '](' . $ref['url'] . $title . ')';
            },
            $text
        );
    }

    $text = preg_replace_callback(
        '/!\[([^\]]*)\]\(\s*([^)\s]+?)(?:\s+=(\d+%?)?x(\d+%?)?)?(?:\s+(?:"([^"]*)"|\'([^\']*)\'))?\s*\)/',
        function ($m) {
            $alt   = htmlspecialchars($m[1], ENT_QUOTES);
            $src   = htmlspecialchars(trim($m[2]), ENT_QUOTES);
            $w     = $m[3] ?? '';
            $h     = $m[4] ?? '';
            $title = ($m[5] ?? '') !== '' ? $m[5] : ($m[6] ?? '');
            $attrs = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '';
            $style = 'max-width:100%';
            if ($w !== '') {
                $attrs .= ' width="' . $w . '"';
                $style  = 'width:' . $w . (ctype_digit($w) ? 'px' : '') . ';max-width:100%;height:auto';
            }
            if ($h !== '') {
                $attrs .= ' height="' . $h . '"';
            }
            return '<img src="' . $src . '" alt="' . $alt . '"' . $attrs . ' loading="lazy" decoding="async" style="' . $style . '">';
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        function ($m) {
            $target = preg_match('/\{:target="_blank"\}/', $m[2]) ? ' target="_blank" rel="noopener"' : '';
            $url    = trim(preg_replace('/\{[^}]+\}/', '', $m[2]));
            $title  = '';
            if (preg_match('/^(\S+)\s+(?:"([^"]*)"|\'([^\']*)\')$/', $url, $tm)) {
                $url   = $tm[1];
                $title = ($tm[2] ?? '') !== '' ? $tm[2] : ($tm[3] ?? '');
            }
            $url    = _md_sanitize_url($url);
            $label  = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $titleAttr . $target . '>' . $label . '</a>';
        },
        $text
    );

    $text = preg_replace_callback(
        '/\{(#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?):([^{}]+)\}/',
        function ($m) {
            return '<span style="color:' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '">' . $m[2] . '</span>';
        },
        $text
    );

    $text = preg_replace('/\*\*(.+?)\*\*|(?<!\w)__(.+?)__(?!\w)/', '<strong>$1$2</strong>', $text);
    $text = preg_replace('/\*(.+?)\*|(?<!\w)_(.+?)_(?!\w)/',       '<em>$1$2</em>',         $text);
    $text = preg_replace('/~~(.+?)~~/',                              '<s>$1</s>',             $text);

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

    $text = preg_replace('/\^([^\^\s]+)\^/', '<sup>$1</sup>', $text);
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