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
