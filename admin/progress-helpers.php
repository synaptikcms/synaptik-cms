<?php
if (!function_exists('_batchProgressFile')) {
    function _batchProgressFile() {
        $sid = preg_replace('/[^a-zA-Z0-9_-]/', '', session_id());
        if (empty($sid)) $sid = 'fallback';
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'synaptik_batch_' . $sid . '.json';
    }
}

if (!function_exists('initializeProgress')) {
    function initializeProgress($status = '', $currentDir = '') {
        $data = [
            'total_files'     => 0,
            'processed_files' => 0,
            'current_dir'     => $currentDir,
            'current_file'    => '',
            'status'          => $status,
            'percent'         => 0,
        ];
        file_put_contents(_batchProgressFile(), json_encode($data), LOCK_EX);
    }
}

if (!function_exists('updateProgress')) {
    function updateProgress($processedFiles = null, $totalFiles = null, $status = null, $currentFile = null, $currentDir = null) {
        $path = _batchProgressFile();

        $data = ['total_files' => 0, 'processed_files' => 0, 'current_dir' => '', 'current_file' => '', 'status' => '', 'percent' => 0];
        if (file_exists($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $data = $decoded;
            }
        }

        if ($processedFiles !== null) $data['processed_files'] = (int)$processedFiles;
        if ($totalFiles     !== null) $data['total_files']     = (int)$totalFiles;
        if ($status         !== null) $data['status']          = $status;
        if ($currentFile    !== null) $data['current_file']    = $currentFile;
        if ($currentDir     !== null) $data['current_dir']     = $currentDir;

        if ($data['total_files'] > 0) {
            $data['percent'] = min(99, (int)round($data['processed_files'] / $data['total_files'] * 100));
        }

        file_put_contents($path, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('completeProgress')) {
    function completeProgress($message = '') {
        $path = _batchProgressFile();
        $data = ['total_files' => 0, 'processed_files' => 0, 'current_dir' => '', 'current_file' => '', 'status' => '', 'percent' => 100];
        if (file_exists($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $data = $decoded;
            }
        }
        $data['status']  = $message;
        $data['percent'] = 100;
        file_put_contents($path, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('getProgressData')) {
    function getProgressData() {
        $path = _batchProgressFile();
        if (file_exists($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $decoded;
            }
        }
        return ['status' => 'not_started', 'percent' => 0, 'processed_files' => 0, 'total_files' => 0, 'current_file' => ''];
    }
}