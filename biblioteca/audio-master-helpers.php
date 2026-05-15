<?php

function bandpromo_first_command_path(string $raw): string {
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    return trim((string) ($lines[0] ?? ''));
}

function bandpromo_resolve_ffmpeg_binary(string $root_dir): string {
    $local = $root_dir . '/scripts/bin/' . (DIRECTORY_SEPARATOR === '\\' ? 'ffmpeg.exe' : 'ffmpeg');
    if (is_file($local)) {
        return $local;
    }

    $env = trim((string) getenv('FFMPEG_PATH'));
    if ($env !== '' && is_file($env)) {
        return $env;
    }

    foreach (['ffmpeg'] as $candidate) {
        $test = shell_exec("where $candidate 2>nul") ?? shell_exec("which $candidate 2>/dev/null");
        if (!$test) {
            continue;
        }
        $resolved = bandpromo_first_command_path($test);
        if ($resolved !== '') {
            return $resolved;
        }
    }

    return '';
}

function bandpromo_convert_wav_to_flac(string $root_dir, string $source_path, string $flac_path, string $failure_label = 'Could not prepare WAV-to-FLAC conversion'): array {
    $ffmpeg = bandpromo_resolve_ffmpeg_binary($root_dir);
    if ($ffmpeg === '') {
        return ['ok' => false, 'warning' => 'ffmpeg is required to convert WAV to FLAC'];
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $command = [
        $ffmpeg,
        '-y',
        '-i',
        $source_path,
        '-map_metadata',
        '0',
        '-vn',
        '-c:a',
        'flac',
        $flac_path,
    ];

    $process = proc_open($command, $descriptors, $pipes, $root_dir);
    if (!is_resource($process)) {
        return ['ok' => false, 'warning' => $failure_label];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    if ($exit_code !== 0 || !is_file($flac_path)) {
        if (is_file($flac_path)) {
            @unlink($flac_path);
        }
        $output = trim((string) $stdout . "\n" . (string) $stderr);
        return [
            'ok' => false,
            'warning' => $output !== ''
                ? $failure_label . ': ' . preg_replace('/\s+/', ' ', $output)
                : $failure_label,
        ];
    }

    return ['ok' => true, 'warning' => ''];
}

function bandpromo_remove_stale_audio_masters(string $master_dir, string $safe_name, string $keep_filename): void {
    $stem = pathinfo($safe_name, PATHINFO_FILENAME);
    foreach (['flac', 'mp3', 'wav'] as $ext) {
        $candidate = $stem . '.' . $ext;
        if ($candidate === $keep_filename) {
            continue;
        }
        $candidate_path = $master_dir . '/' . $candidate;
        if (is_file($candidate_path)) {
            @unlink($candidate_path);
        }
    }
}

function bandpromo_prepare_audio_master(string $root_dir, string $ext, string $safe_name, string $source_path): array {
    if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return ['attempted' => false, 'prepared' => false, 'warning' => ''];
    }

    $master_dir = $root_dir . '/media/audio/master';
    if (!is_dir($master_dir) && !mkdir($master_dir, 0755, true) && !is_dir($master_dir)) {
        return ['attempted' => true, 'prepared' => false, 'warning' => 'Could not create audio master directory'];
    }

    $master_filename = $ext === 'wav'
        ? pathinfo($safe_name, PATHINFO_FILENAME) . '.flac'
        : $safe_name;
    $master_format = strtolower((string) pathinfo($master_filename, PATHINFO_EXTENSION));
    $master_path = $master_dir . '/' . $master_filename;
    bandpromo_remove_stale_audio_masters($master_dir, $safe_name, $master_filename);

    if ($ext === 'wav') {
        $conversion = bandpromo_convert_wav_to_flac($root_dir, $source_path, $master_path, 'Could not prepare WAV master');
        if (!$conversion['ok']) {
            return [
                'attempted' => true,
                'prepared' => false,
                'warning' => $conversion['warning'],
                'master_filename' => $master_filename,
                'master_format' => $master_format,
            ];
        }
    } elseif (!copy($source_path, $master_path)) {
        return [
            'attempted' => true,
            'prepared' => false,
            'warning' => 'Could not prepare audio master copy',
            'master_filename' => $master_filename,
            'master_format' => $master_format,
        ];
    }

    return [
        'attempted' => true,
        'prepared' => true,
        'warning' => '',
        'master_filename' => $master_filename,
        'master_format' => $master_format,
    ];
}

function bandpromo_find_audio_master(string $root_dir, string $filename): array {
    $master_dir = $root_dir . '/media/audio/master';
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $source_ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $preferred_exts = $source_ext === 'wav' ? ['flac', 'mp3', 'wav'] : [$source_ext, 'flac', 'mp3', 'wav'];
    $candidates = [];
    foreach ($preferred_exts as $ext) {
        $candidate = $stem . '.' . $ext;
        if (!in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    foreach ($candidates as $candidate) {
        $path = $master_dir . '/' . $candidate;
        if (!is_file($path)) {
            continue;
        }

        $format = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
        return [
            'exists' => true,
            'filename' => $candidate,
            'format' => $format,
            'editable' => in_array($format, ['flac', 'mp3'], true),
        ];
    }

    return [
        'exists' => false,
        'filename' => '',
        'format' => '',
        'editable' => false,
    ];
}

function bandpromo_prepare_audio_master_from_original(string $root_dir, string $filename): array {
    $safe_name = basename($filename);
    $ext = strtolower((string) pathinfo($safe_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return ['attempted' => false, 'prepared' => false, 'warning' => ''];
    }

    $source_path = $root_dir . '/media/audio/original/' . $safe_name;
    if (!is_file($source_path)) {
        return ['attempted' => false, 'prepared' => false, 'warning' => ''];
    }

    return bandpromo_prepare_audio_master($root_dir, $ext, $safe_name, $source_path);
}