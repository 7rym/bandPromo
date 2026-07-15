<?php

require_once __DIR__ . '/media-delivery-helpers.php';

const BANDPROMO_LIVING_COVER_TAG = 'BANDPROMO_LIVING_COVER';

function bandpromo_living_cover_normalize_video_filename(?string $value): string
{
    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return '';
    }

    return basename(str_replace('\\', '/', $trimmed));
}

function bandpromo_living_cover_original_relative_path(string $videoFilename): string
{
    $safe = bandpromo_living_cover_normalize_video_filename($videoFilename);
    if ($safe === '') {
        return '';
    }

    return 'media/video/original/' . $safe;
}

function bandpromo_living_cover_validate_video_path(string $root, string $path): array
{
    $relative = ltrim(str_replace('\\', '/', trim($path)), '/');
    if ($relative === '') {
        return [
            'ok' => true,
            'filename' => '',
        ];
    }

    if (stripos($relative, 'media/video/original/') !== 0) {
        return [
            'ok' => false,
            'error' => 'Living cover must be a video from Files → Visual',
        ];
    }

    $filename = basename($relative);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return [
            'ok' => false,
            'error' => 'Invalid living cover filename',
        ];
    }

    $absolute = $root . '/' . $relative;
    if (!is_file($absolute)) {
        return [
            'ok' => false,
            'error' => 'Selected living cover video was not found',
        ];
    }

    return [
        'ok' => true,
        'filename' => $filename,
    ];
}

function bandpromo_living_cover_player_url(string $root, string $videoFilename): string
{
    $safe = bandpromo_living_cover_normalize_video_filename($videoFilename);
    if ($safe === '') {
        return '';
    }

    if (!is_file($root . '/media/video/original/' . $safe)) {
        return '';
    }

    if (!bandpromo_video_delivery_ready($root, $safe)) {
        return '';
    }

    return '/media/video/optimal/' . bandpromo_video_delivery_basename($safe);
}

function bandpromo_living_cover_admin_preview_url(string $root, string $videoFilename): string
{
    $safe = bandpromo_living_cover_normalize_video_filename($videoFilename);
    if ($safe === '') {
        return '';
    }

    return bandpromo_video_admin_preview_relative_url($root, $safe);
}

function bandpromo_living_cover_enrich_detail(string $root, array $detail): array
{
    $filename = bandpromo_living_cover_normalize_video_filename((string) ($detail['living_cover'] ?? ''));
    $detail['living_cover'] = $filename;
    $detail['living_cover_player_url'] = $filename !== ''
        ? bandpromo_living_cover_player_url($root, $filename)
        : '';
    $detail['living_cover_preview_url'] = $filename !== ''
        ? bandpromo_living_cover_admin_preview_url($root, $filename)
        : '';
    $detail['living_cover_delivery_ready'] = $detail['living_cover_player_url'] !== '';
    $detail['living_cover_delivery_pending'] = $filename !== '' && !$detail['living_cover_delivery_ready'];

    return $detail;
}
