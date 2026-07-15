<?php

function bandpromo_audio_delivery_basename(string $sourceFilename): string
{
    return pathinfo($sourceFilename, PATHINFO_FILENAME) . '.mp3';
}

function bandpromo_audio_delivery_path(string $root, string $sourceFilename): string
{
    return $root . '/media/audio/optimal/' . bandpromo_audio_delivery_basename($sourceFilename);
}

function bandpromo_audio_delivery_ready(string $root, string $sourceFilename): bool
{
    return is_file(bandpromo_audio_delivery_path($root, $sourceFilename));
}

function bandpromo_video_delivery_basename(string $sourceFilename): string
{
    return pathinfo($sourceFilename, PATHINFO_FILENAME) . '.mp4';
}

function bandpromo_video_delivery_path(string $root, string $sourceFilename): string
{
    return $root . '/media/video/optimal/' . bandpromo_video_delivery_basename($sourceFilename);
}

function bandpromo_video_delivery_ready(string $root, string $sourceFilename): bool
{
    return is_file(bandpromo_video_delivery_path($root, $sourceFilename));
}

function bandpromo_photo_delivery_basename(string $sourceFilename): string
{
    return pathinfo($sourceFilename, PATHINFO_FILENAME) . '.jpg';
}

function bandpromo_photo_delivery_path(string $root, string $sourceFilename): string
{
    return $root . '/media/photo/optimal/' . bandpromo_photo_delivery_basename($sourceFilename);
}

function bandpromo_photo_delivery_ready(string $root, string $sourceFilename): bool
{
    return is_file(bandpromo_photo_delivery_path($root, $sourceFilename));
}

function bandpromo_media_pool_ready(string $root, string $target, string $filename): bool
{
    if ($target === 'audio') {
        return bandpromo_audio_delivery_ready($root, $filename);
    }
    if ($target === 'video') {
        return bandpromo_video_delivery_ready($root, $filename);
    }
    if ($target === 'photos') {
        return bandpromo_photo_delivery_ready($root, $filename);
    }

    return true;
}

function bandpromo_video_poster_absolute_path(string $root, string $sourceFilename): string
{
    require_once __DIR__ . '/gallery-helpers.php';

    return bandpromo_gallery_video_poster_absolute_path($root, $sourceFilename);
}

function bandpromo_video_poster_relative_url(string $sourceFilename): string
{
    require_once __DIR__ . '/gallery-helpers.php';

    return bandpromo_gallery_video_poster_relative_path($sourceFilename);
}

function bandpromo_video_poster_ready(string $root, string $sourceFilename): bool
{
    return is_file(bandpromo_video_poster_absolute_path($root, $sourceFilename));
}

function bandpromo_video_admin_preview_relative_url(string $root, string $sourceFilename): string
{
    $safe = basename(trim($sourceFilename));
    if ($safe === '') {
        return '';
    }

    if (bandpromo_video_delivery_ready($root, $safe)) {
        return '/media/video/optimal/' . bandpromo_video_delivery_basename($safe);
    }

    $extension = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));
    if (in_array($extension, ['mp4', 'webm'], true)) {
        return '/media/video/original/' . $safe;
    }

    return '';
}

function bandpromo_video_admin_file_meta(string $root, string $sourceFilename): array
{
    $safe = basename(trim($sourceFilename));
    $posterUrl = bandpromo_video_poster_ready($root, $safe)
        ? bandpromo_video_poster_relative_url($safe)
        : '';
    $previewUrl = bandpromo_video_admin_preview_relative_url($root, $safe);

    return [
        'delivery_ready' => bandpromo_video_delivery_ready($root, $safe),
        'needs_delivery' => bandpromo_video_needs_delivery($root, $safe),
        'poster_url' => $posterUrl,
        'preview_url' => $previewUrl,
        'has_browser_preview' => $previewUrl !== '',
        'has_poster' => $posterUrl !== '',
    ];
}

function bandpromo_video_needs_delivery(string $root, string $sourceFilename): bool
{
    $safe = basename(trim($sourceFilename));
    if ($safe === '' || strcasecmp($safe, 'desktop.ini') === 0) {
        return false;
    }

    $sourcePath = $root . '/media/video/original/' . $safe;
    if (!is_file($sourcePath)) {
        return false;
    }

    $extension = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp4', 'mov', 'webm'], true)) {
        return false;
    }

    return !bandpromo_video_delivery_ready($root, $safe)
        || !bandpromo_video_poster_ready($root, $safe);
}

function bandpromo_list_videos_needing_delivery(string $root): array
{
    $dir = $root . '/media/video/original';
    if (!is_dir($dir)) {
        return [];
    }

    $missing = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!bandpromo_video_needs_delivery($root, $entry)) {
            continue;
        }
        $missing[] = $entry;
    }

    sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

    return $missing;
}

function bandpromo_preferred_audio_variant(?string $quality): string
{
    return strtolower(trim((string) $quality)) === 'high' ? 'original' : 'optimal';
}
