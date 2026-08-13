<?php

require_once __DIR__ . '/asset-registry.php';

if (!function_exists('bandpromo_first_command_path')) {
    function bandpromo_first_command_path(string $raw): string {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        return trim((string) ($lines[0] ?? ''));
    }
}

if (!function_exists('bandpromo_resolve_ffmpeg_binary')) {
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

    $asset_id = bandpromo_generate_asset_id();
    $master_format = $ext === 'wav' ? 'flac' : $ext;
    $master_filename = bandpromo_asset_master_filename_for_ulid($asset_id, $master_format);
    $master_path = $master_dir . '/' . $master_filename;

    if ($ext === 'wav') {
        $conversion = bandpromo_convert_wav_to_flac($root_dir, $source_path, $master_path, 'Could not prepare WAV master');
        if (!$conversion['ok']) {
            return [
                'attempted' => true,
                'prepared' => false,
                'warning' => $conversion['warning'],
                'master_filename' => $master_filename,
                'master_format' => $master_format,
                'asset_id' => $asset_id,
            ];
        }
    } elseif (!copy($source_path, $master_path)) {
        return [
            'attempted' => true,
            'prepared' => false,
            'warning' => 'Could not prepare audio master copy',
            'master_filename' => $master_filename,
            'master_format' => $master_format,
            'asset_id' => $asset_id,
        ];
    }

    try {
        bandpromo_asset_register_audio_master(
            $root_dir,
            $safe_name,
            $master_filename,
            $master_format,
            $asset_id
        );
    } catch (Throwable $throwable) {
        @unlink($master_path);

        return [
            'attempted' => true,
            'prepared' => false,
            'warning' => 'Could not register audio asset: ' . $throwable->getMessage(),
            'master_filename' => $master_filename,
            'master_format' => $master_format,
            'asset_id' => $asset_id,
        ];
    }

    return [
        'attempted' => true,
        'prepared' => true,
        'warning' => '',
        'master_filename' => $master_filename,
        'master_format' => $master_format,
        'asset_id' => $asset_id,
    ];
}

function bandpromo_find_audio_master(string $root_dir, string $filename): array {
    $filename = basename(trim($filename));
    $asset = bandpromo_asset_lookup_by_original_filename($root_dir, $filename);
    if ($asset === null) {
        $asset = bandpromo_asset_lookup_by_master_filename($root_dir, $filename);
    }
    if ($asset !== null) {
        $master_filename = (string) ($asset['master_filename'] ?? '');
        $format = strtolower((string) ($asset['master_format'] ?? pathinfo($master_filename, PATHINFO_EXTENSION)));
        $path = $root_dir . '/media/audio/master/' . $master_filename;
        if ($master_filename !== '' && is_file($path)) {
            return [
                'exists' => true,
                'filename' => $master_filename,
                'format' => $format,
                'editable' => in_array($format, ['flac', 'mp3'], true),
                'asset_id' => (string) ($asset['id'] ?? ''),
                'original_filename' => basename((string) ($asset['original_filename'] ?? $filename)),
            ];
        }
    }

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
        $candidateAsset = bandpromo_asset_lookup_by_master_filename($root_dir, $candidate);

        return [
            'exists' => true,
            'filename' => $candidate,
            'format' => $format,
            'editable' => in_array($format, ['flac', 'mp3'], true),
            'asset_id' => (string) ($candidateAsset['id'] ?? ''),
            'original_filename' => $filename,
        ];
    }

    return [
        'exists' => false,
        'filename' => '',
        'format' => '',
        'editable' => false,
        'asset_id' => '',
        'original_filename' => $filename,
    ];
}

function bandpromo_materialize_audio_master_from_original(string $root_dir, string $filename): array {
    $safe_name = basename(trim($filename));
    $ext = strtolower((string) pathinfo($safe_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return ['attempted' => false, 'prepared' => false, 'warning' => ''];
    }

    $source_path = $root_dir . '/media/audio/original/' . $safe_name;
    if (!is_file($source_path)) {
        return ['attempted' => false, 'prepared' => false, 'warning' => ''];
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root_dir, $safe_name);
    if ($asset !== null) {
        $master_filename = (string) ($asset['master_filename'] ?? '');
        $master_format = strtolower((string) ($asset['master_format'] ?? pathinfo($master_filename, PATHINFO_EXTENSION)));
        if ($master_filename === '') {
            return ['attempted' => true, 'prepared' => false, 'warning' => 'Audio asset is missing its master filename'];
        }

        $master_dir = $root_dir . '/media/audio/master';
        if (!is_dir($master_dir) && !mkdir($master_dir, 0755, true) && !is_dir($master_dir)) {
            return ['attempted' => true, 'prepared' => false, 'warning' => 'Could not create audio master directory'];
        }

        $master_path = $master_dir . '/' . $master_filename;
        if (is_file($master_path)) {
            return [
                'attempted' => false,
                'prepared' => true,
                'warning' => '',
                'master_filename' => $master_filename,
                'master_format' => $master_format,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
        }

        if ($ext === 'wav' && $master_format === 'flac') {
            $conversion = bandpromo_convert_wav_to_flac($root_dir, $source_path, $master_path, 'Could not prepare WAV master');
            if (!$conversion['ok']) {
                return [
                    'attempted' => true,
                    'prepared' => false,
                    'warning' => $conversion['warning'],
                    'master_filename' => $master_filename,
                    'master_format' => $master_format,
                    'asset_id' => (string) ($asset['id'] ?? ''),
                ];
            }
        } elseif (!copy($source_path, $master_path)) {
            return [
                'attempted' => true,
                'prepared' => false,
                'warning' => 'Could not prepare audio master copy',
                'master_filename' => $master_filename,
                'master_format' => $master_format,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
        }

        return [
            'attempted' => true,
            'prepared' => true,
            'warning' => '',
            'master_filename' => $master_filename,
            'master_format' => $master_format,
            'asset_id' => (string) ($asset['id'] ?? ''),
        ];
    }

    $orphan = bandpromo_asset_find_unregistered_master_match($root_dir, $safe_name);
    if ($orphan !== null) {
        $assetId = trim((string) ($orphan['asset_id'] ?? ''));
        if ($assetId !== '' && bandpromo_asset_is_asset_id($assetId)) {
            try {
                bandpromo_asset_register_audio_master(
                    $root_dir,
                    $safe_name,
                    (string) $orphan['master_filename'],
                    (string) $orphan['master_format'],
                    $assetId
                );
            } catch (Throwable $throwable) {
                return [
                    'attempted' => true,
                    'prepared' => false,
                    'warning' => 'Could not link audio asset: ' . $throwable->getMessage(),
                ];
            }

            $sourceSize = filesize($source_path);
            if ($sourceSize !== false) {
                bandpromo_asset_prune_unregistered_duplicate_masters(
                    $root_dir,
                    (int) $sourceSize,
                    (string) $orphan['master_filename']
                );
            }

            return [
                'attempted' => true,
                'prepared' => true,
                'warning' => '',
                'master_filename' => (string) $orphan['master_filename'],
                'master_format' => (string) $orphan['master_format'],
                'asset_id' => $assetId,
            ];
        }
    }

    return bandpromo_prepare_audio_master($root_dir, $ext, $safe_name, $source_path);
}

function bandpromo_audio_master_paths_for_original(string $root_dir, string $filename): array
{
    $filename = basename(trim($filename));
    $paths = [];
    $master = bandpromo_find_audio_master($root_dir, $filename);
    if (!empty($master['exists']) && !empty($master['filename'])) {
        $paths[] = $root_dir . '/media/audio/master/' . $master['filename'];
    }

    $master_dir = $root_dir . '/media/audio/master';
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    foreach (['flac', 'mp3', 'wav'] as $ext) {
        $candidate = $master_dir . '/' . $stem . '.' . $ext;
        if (is_file($candidate)) {
            $paths[] = $candidate;
        }
    }

    return array_values(array_unique($paths));
}

const BANDPROMO_DEMO_ORIGINAL_FALLBACK_MAX_BYTES = 15 * 1024 * 1024;

function bandpromo_resolve_optimal_audio_file(string $root_dir, string $filename): ?array
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return null;
    }

    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $requestedExt = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $candidates = [
        $root_dir . '/media/audio/optimal/' . $filename,
    ];
    if ($requestedExt !== 'mp3') {
        $candidates[] = $root_dir . '/media/audio/optimal/' . $stem . '.mp3';
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return [
                'path' => $candidate,
                'filename' => basename($candidate),
            ];
        }
    }

    return null;
}

function bandpromo_resolve_source_audio_file(string $root_dir, string $filename): ?array
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return null;
    }

    $candidates = [];
    $candidates[] = $root_dir . '/media/audio/original/' . $filename;
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    foreach (['flac', 'mp3', 'wav'] as $ext) {
        $candidates[] = $root_dir . '/media/audio/original/' . $stem . '.' . $ext;
    }
    foreach (bandpromo_audio_master_paths_for_original($root_dir, $filename) as $path) {
        $candidates[] = $path;
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if (is_file($candidate)) {
            return [
                'path' => $candidate,
                'filename' => basename($candidate),
            ];
        }
    }

    return null;
}

function bandpromo_audio_demo_original_fallback_allowed(string $root_dir, string $filename): bool
{
    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/demo-catalog-state.php';

    if (!bandpromo_demo_catalog_is_visible($root_dir)) {
        return false;
    }

    $filename = basename(trim($filename));
    if ($filename === '') {
        return false;
    }

    // Prefer release ownership; legacy bandPromo_* names remain a soft hint only.
    if (!bandpromo_demo_release_owns_media_file($root_dir, 'audio', $filename)
        && !bandpromo_release_is_demo_filename($filename)
    ) {
        return false;
    }

    $resolved = bandpromo_resolve_source_audio_file($root_dir, $filename);
    if ($resolved === null) {
        return false;
    }

    $size = filesize($resolved['path']);

    return $size !== false && $size <= BANDPROMO_DEMO_ORIGINAL_FALLBACK_MAX_BYTES;
}

function bandpromo_resolve_playable_audio_file(string $root_dir, string $filename, string $variant = 'optimal'): ?array
{
    $variant = strtolower(trim($variant));
    if ($variant === 'original') {
        return bandpromo_resolve_source_audio_file($root_dir, $filename);
    }

    $resolved = bandpromo_resolve_optimal_audio_file($root_dir, $filename);
    if ($resolved !== null) {
        return $resolved;
    }

    if (bandpromo_audio_demo_original_fallback_allowed($root_dir, $filename)) {
        return bandpromo_resolve_source_audio_file($root_dir, $filename);
    }

    return null;
}

function bandpromo_audio_delivery_paths_for_original(string $root_dir, string $filename): array
{
    $filename = basename(trim($filename));
    $optimal_dir = $root_dir . '/media/audio/optimal';
    $paths = [];
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $candidate = $optimal_dir . '/' . $stem . '.mp3';
    if (is_file($candidate)) {
        $paths[] = $candidate;
    }

    return $paths;
}

function bandpromo_prepare_audio_master_from_original(string $root_dir, string $filename): array {
    return bandpromo_materialize_audio_master_from_original($root_dir, $filename);
}