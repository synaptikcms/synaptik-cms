<?php

function sl_admin_relink_image_path(string $oldPath, string $newPath): int
{
    $updated = 0;

    foreach (sl_all_type_slugs() as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $changed = false;

            if (($item['image'] ?? '') === $oldPath) {
                $item['image'] = $newPath;
                $changed = true;
            }

            foreach (($item['galleries'] ?? []) as $gIdx => $gallery) {
                foreach (($gallery['images'] ?? []) as $iIdx => $img) {
                    if (($img['src'] ?? '') === $oldPath) {
                        $item['galleries'][$gIdx]['images'][$iIdx]['src'] = $newPath;
                        $changed = true;
                    }
                }
            }

            foreach (($item['gallery'] ?? []) as $iIdx => $img) {
                if (($img['src'] ?? '') === $oldPath) {
                    $item['gallery'][$iIdx]['src'] = $newPath;
                    $changed = true;
                }
            }

            if (!empty($item['content']) && strpos($item['content'], $oldPath) !== false) {
                $item['content'] = str_replace($oldPath, $newPath, $item['content']);
                $changed = true;
            }

            if ($changed) {
                sl_admin_save_item($type, $fileSlug, $item);
                $indexEntry          = sl_admin_extract_index_entry($type, $item);
                $indexEntry['_file'] = $fileSlug;
                sl_admin_update_index($type, $indexEntry);
                $updated++;
            }
        }
    }

    return $updated;
}

function sl_admin_relink_image_prefix(string $oldPrefix, string $newPrefix): int
{
    $oldPrefix = rtrim($oldPrefix, '/') . '/';
    $newPrefix = rtrim($newPrefix, '/') . '/';
    $updated   = 0;

    foreach (sl_all_type_slugs() as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $changed = false;

            if (!empty($item['image']) && strpos($item['image'], $oldPrefix) === 0) {
                $item['image'] = $newPrefix . substr($item['image'], strlen($oldPrefix));
                $changed = true;
            }

            foreach (($item['galleries'] ?? []) as $gIdx => $gallery) {
                foreach (($gallery['images'] ?? []) as $iIdx => $img) {
                    if (!empty($img['src']) && strpos($img['src'], $oldPrefix) === 0) {
                        $item['galleries'][$gIdx]['images'][$iIdx]['src'] = $newPrefix . substr($img['src'], strlen($oldPrefix));
                        $changed = true;
                    }
                }
            }

            foreach (($item['gallery'] ?? []) as $iIdx => $img) {
                if (!empty($img['src']) && strpos($img['src'], $oldPrefix) === 0) {
                    $item['gallery'][$iIdx]['src'] = $newPrefix . substr($img['src'], strlen($oldPrefix));
                    $changed = true;
                }
            }

            if (!empty($item['content']) && strpos($item['content'], $oldPrefix) !== false) {
                $item['content'] = str_replace($oldPrefix, $newPrefix, $item['content']);
                $changed = true;
            }

            if ($changed) {
                sl_admin_save_item($type, $fileSlug, $item);
                $indexEntry          = sl_admin_extract_index_entry($type, $item);
                $indexEntry['_file'] = $fileSlug;
                sl_admin_update_index($type, $indexEntry);
                $updated++;
            }
        }
    }

    return $updated;
}

function sl_admin_find_image_usage(string $needle, bool $isPrefix = false): array
{
    $prefix = $isPrefix ? rtrim($needle, '/') . '/' : null;

    $matches = function ($src) use ($needle, $prefix) {
        if (empty($src)) return false;
        return $prefix !== null ? strpos($src, $prefix) === 0 : $src === $needle;
    };

    $usage = [];

    foreach (sl_all_type_slugs() as $type) {
        foreach (sl_load_index($type) as $entry) {
            $fileSlug = sl_file_slug($entry);
            $item     = sl_load_item($type, $fileSlug);
            if ($item === null) continue;

            $used = $matches($item['image'] ?? '');

            if (!$used) {
                foreach (($item['galleries'] ?? []) as $gallery) {
                    foreach (($gallery['images'] ?? []) as $img) {
                        if ($matches($img['src'] ?? '')) { $used = true; break 2; }
                    }
                }
            }

            if (!$used) {
                foreach (($item['gallery'] ?? []) as $img) {
                    if ($matches($img['src'] ?? '')) { $used = true; break; }
                }
            }

            if (!$used && !empty($item['content']) && strpos($item['content'], $needle) !== false) {
                $used = true;
            }

            if ($used) {
                $usage[] = ['type' => $type, 'title' => $item['title'] ?? '', 'file_slug' => $fileSlug];
            }
        }
    }

    return $usage;
}

