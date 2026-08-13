<?php

require_once __DIR__ . '/media-delivery-helpers.php';
require_once __DIR__ . '/asset-registry.php';

const BANDPROMO_LIVING_COVER_TAG = 'BANDPROMO_LIVING_COVER';

function bandpromo_living_cover_normalize_video_filename(?string $value): string
{
    return bandpromo_asset_normalize_media_ref($value);
}

function bandpromo_living_cover_canonical_id(string $root, ?string $value): string
{
    return bandpromo_asset_canonical_id_from_media_ref($root, (string) $value);
}

function bandpromo_living_cover_validate_video_path(string $root, string $path): array
{
    $relative = ltrim(str_replace('\\', '/', trim($path)), '/');
    if ($relative === '') {
        return [
            'ok' => true,
            'filename' => '',
            'asset_id' => '',
        ];
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $relative);
    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
        return [
            'ok' => false,
            'error' => 'Living cover must be a video from Files → Visual',
        ];
    }

    $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
    if ($mediaType !== 'video') {
        return [
            'ok' => false,
            'error' => 'Living cover must be a video from Files → Visual',
        ];
    }

    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '') {
        return [
            'ok' => false,
            'error' => 'Living cover video is missing its asset id',
        ];
    }

    require_once __DIR__ . '/visual-master-helpers.php';
    $working = bandpromo_visual_working_path($root, $asset);
    if ($working === '' || !is_file($working)) {
        $stream = bandpromo_visual_resolve_url($root, $assetId, 'standard-stream', '', false);
        if ($stream === '') {
            return [
                'ok' => false,
                'error' => 'Selected living cover video was not found',
            ];
        }
    }

    return [
        'ok' => true,
        'filename' => $assetId,
        'asset_id' => $assetId,
    ];
}

function bandpromo_living_cover_player_url(string $root, string $videoFilename): string
{
    $ref = bandpromo_living_cover_canonical_id($root, $videoFilename);
    if ($ref === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $ref);
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $stream = bandpromo_visual_resolve_url(
            $root,
            (string) ($asset['id'] ?? $ref),
            'standard-stream',
            '',
            false
        );
        if ($stream !== '') {
            return $stream;
        }
    }

    return '';
}

function bandpromo_living_cover_admin_preview_url(string $root, string $videoFilename): string
{
    $ref = bandpromo_living_cover_canonical_id($root, $videoFilename);
    if ($ref === '') {
        return '';
    }

    $stream = bandpromo_visual_resolve_url($root, $ref, 'standard-stream', '', false);
    if ($stream !== '') {
        return $stream;
    }

    $poster = bandpromo_visual_resolve_url($root, $ref, 'poster', '', false);
    if ($poster !== '') {
        return $poster;
    }

    return bandpromo_visual_resolve_url($root, $ref, 'card', '', false);
}

function bandpromo_living_cover_enrich_detail(string $root, array $detail): array
{
    $raw = trim((string) ($detail['living_cover'] ?? ''));
    $assetId = bandpromo_living_cover_canonical_id($root, $raw);
    $detail['living_cover'] = $assetId;
    $detail['living_cover_asset_id'] = $assetId;
    $detail['living_cover_player_url'] = $assetId !== ''
        ? bandpromo_living_cover_player_url($root, $assetId)
        : '';
    $detail['living_cover_preview_url'] = $assetId !== ''
        ? bandpromo_living_cover_admin_preview_url($root, $assetId)
        : '';
    $detail['living_cover_delivery_ready'] = $detail['living_cover_player_url'] !== '';
    $detail['living_cover_delivery_pending'] = $assetId !== '' && !$detail['living_cover_delivery_ready'];

    return $detail;
}
