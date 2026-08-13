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
    require_once __DIR__ . '/asset-registry.php';

    $safe = basename(trim($sourceFilename));
    if ($safe === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_visual($root, $safe, 'video')
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe)
        ?? bandpromo_asset_lookup_by_master_filename($root, $safe);
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $stream = bandpromo_visual_variant_relative_url($root, (string) ($asset['id'] ?? ''), 'standard-stream');
        if ($stream !== '') {
            return $stream;
        }
    }

    return '';
}

function bandpromo_video_admin_file_meta(string $root, string $sourceFilename): array
{
    require_once __DIR__ . '/asset-registry.php';

    $safe = basename(trim($sourceFilename));
    $asset = bandpromo_asset_lookup_visual($root, $safe, 'video')
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe);

    $posterUrl = '';
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $posterUrl = bandpromo_visual_variant_relative_url($root, (string) ($asset['id'] ?? ''), 'poster');
    }
    $previewUrl = bandpromo_video_admin_preview_relative_url($root, $safe);

    $deliveryReady = false;
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual' && !empty($asset['id'])) {
        $deliveryReady = bandpromo_visual_delivery_ready($root, (string) $asset['id'], ['poster', 'standard-stream']);
    }

    return [
        'delivery_ready' => $deliveryReady,
        'needs_delivery' => bandpromo_video_needs_delivery($root, $safe),
        'poster_url' => $posterUrl,
        'preview_url' => $previewUrl,
        'has_browser_preview' => $previewUrl !== '',
        'has_poster' => $posterUrl !== '',
        'asset_id' => is_array($asset) ? (string) ($asset['id'] ?? '') : '',
    ];
}

/**
 * Whether a Visual video asset (or legacy stem) still needs delivery builds.
 * Prefer asset id / master_filename identity; original dirs are not scanned.
 */
function bandpromo_video_needs_delivery(string $root, string $sourceFilename): bool
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';

    $safe = basename(trim($sourceFilename));
    if ($safe === '' || strcasecmp($safe, 'desktop.ini') === 0) {
        return false;
    }

    $asset = bandpromo_asset_lookup_visual($root, $safe, 'video')
        ?? bandpromo_asset_lookup_from_media_ref($root, $safe)
        ?? bandpromo_asset_lookup_by_master_filename($root, $safe)
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe);

    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual'
        && strtolower((string) ($asset['media_type'] ?? '')) === 'video'
    ) {
        $working = bandpromo_visual_working_path($root, $asset);
        if ($working === '' || !is_file($working)) {
            return false;
        }
        $assetId = trim((string) ($asset['id'] ?? ''));
        if ($assetId === '') {
            return false;
        }

        return !bandpromo_visual_delivery_ready($root, $assetId, ['poster', 'standard-stream']);
    }

    // Legacy unregistered stem under video/original (pre-registry uploads).
    $sourcePath = $root . '/media/video/original/' . $safe;
    if (!is_file($sourcePath)) {
        return false;
    }

    $extension = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp4', 'mov', 'webm', 'mkv'], true)) {
        return false;
    }

    return !bandpromo_video_delivery_ready($root, $safe)
        || !bandpromo_video_poster_ready($root, $safe);
}

/**
 * Queue identities for video delivery: Visual video masters (asset id), then
 * any leftover unregistered files under media/video/original.
 *
 * @return list<string>
 */
function bandpromo_list_videos_needing_delivery(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';

    $missing = [];
    $seen = [];
    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        if (strtolower((string) ($asset['media_type'] ?? '')) !== 'video') {
            continue;
        }
        $assetId = trim((string) ($asset['id'] ?? ''));
        if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
            continue;
        }
        $working = bandpromo_visual_working_path($root, $asset);
        if ($working === '' || !is_file($working)) {
            continue;
        }
        if (bandpromo_visual_delivery_ready($root, $assetId, ['poster', 'standard-stream'])) {
            continue;
        }
        $queueName = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($queueName === '') {
            $queueName = $assetId;
        }
        $key = strtolower($queueName);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $missing[] = $queueName;
    }

    sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

    return $missing;
}

function bandpromo_preferred_audio_variant(?string $quality): string
{
    // Public playback is delivery-only; quality preference does not stream originals.
    unset($quality);

    return 'optimal';
}

function bandpromo_visual_delivery_root(string $root): string
{
    return $root . '/media/visual/delivery';
}

function bandpromo_visual_delivery_dir(string $root, string $assetId): string
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim($assetId);
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return '';
    }

    return bandpromo_visual_delivery_root($root) . '/' . $assetId;
}

/**
 * Absolute path for a named visual delivery variant when the file exists.
 * Tries common extensions for image variants.
 */
function bandpromo_visual_variant_path(string $root, string $assetId, string $variant): string
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim($assetId);
    $variant = strtolower(trim($variant));
    if ($variant === 'optimal' || $variant === 'lightbox') {
        $variant = 'card';
    }
    if ($assetId === '' || $variant === '') {
        return '';
    }

    $dir = bandpromo_visual_delivery_dir($root, $assetId);
    if ($dir === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    $delivery = is_array($asset['delivery'] ?? null) ? $asset['delivery'] : [];
    $variants = is_array($delivery['variants'] ?? null) ? $delivery['variants'] : [];
    if (isset($variants[$variant]) && is_array($variants[$variant])) {
        $rel = trim((string) ($variants[$variant]['path'] ?? ''));
        if ($rel !== '') {
            // Trust registry delivery paths for listing/URL paint. Avoid is_file() probes —
            // on Google Drive / slow hosts they dominate Files → Visual list-media time.
            return $root . '/' . ltrim(str_replace('\\', '/', $rel), '/');
        }
    }

    if ($variant === 'standard-stream') {
        $candidate = $dir . '/standard-stream.mp4';

        return is_file($candidate) ? $candidate : '';
    }

    if ($variant === 'poster') {
        $candidate = $dir . '/poster.jpg';

        return is_file($candidate) ? $candidate : '';
    }

    foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
        $candidate = $dir . '/' . $variant . '.' . $ext;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Relative URL for a registered visual delivery variant (registry-first, no disk probe).
 */
function bandpromo_visual_registry_variant_url(array $asset, string $variant): string
{
    $variant = strtolower(trim($variant));
    if ($variant === 'optimal' || $variant === 'lightbox') {
        $variant = 'card';
    }
    $delivery = is_array($asset['delivery'] ?? null) ? $asset['delivery'] : [];
    $variants = is_array($delivery['variants'] ?? null) ? $delivery['variants'] : [];
    if (!isset($variants[$variant]) || !is_array($variants[$variant])) {
        return '';
    }
    $rel = trim((string) ($variants[$variant]['path'] ?? ''));
    if ($rel === '') {
        return '';
    }

    return '/' . ltrim(str_replace('\\', '/', $rel), '/');
}

function bandpromo_visual_variant_relative_url(string $root, string $assetId, string $variant): string
{
    $path = bandpromo_visual_variant_path($root, $assetId, $variant);
    if ($path === '') {
        return '';
    }

    $normalizedRoot = str_replace('\\', '/', rtrim($root, '/\\'));
    $normalizedPath = str_replace('\\', '/', $path);
    if (str_starts_with($normalizedPath, $normalizedRoot)) {
        $rel = substr($normalizedPath, strlen($normalizedRoot));

        return $rel === '' ? '' : (str_starts_with($rel, '/') ? $rel : '/' . $rel);
    }

    return '';
}

/**
 * Primary Files / picker label: human display title when set, else role label.
 */
function bandpromo_visual_listing_title(string $root, array $asset, array $entry = []): string
{
    require_once __DIR__ . '/asset-registry.php';
    $display = bandpromo_asset_read_visual_display($asset);
    if ($display['title'] !== '') {
        return $display['title'];
    }

    return bandpromo_visual_operator_title($root, $asset, $entry);
}

/**
 * Operator-facing visual role label (Files / pickers when display.title is empty).
 */
function bandpromo_visual_operator_title(string $root, array $asset, array $entry = []): string
{
    $role = bandpromo_asset_normalize_visual_role((string) ($asset['role'] ?? $entry['role'] ?? 'unassigned'));
    $roleLabels = [
        'unassigned' => 'Unassigned',
        'track-cover' => 'Track cover',
        'release-cover' => 'Release cover',
        'brand-logo' => 'Brand logo',
        'brand-portrait' => 'Brand portrait',
        'shell-background-image' => 'Shell background',
        'shell-background-video' => 'Shell living background',
        'living-cover' => 'Living cover',
        'gallery' => 'Gallery',
        'page-illustration' => 'Page picture',
        'style-ref' => 'Style reference',
        'typography-sample' => 'Typography sample',
        'share' => 'Share image',
    ];

    return $roleLabels[$role] ?? ucwords(str_replace('-', ' ', $role));
}

/**
 * Resolve a public URL for a visual asset/variant (delivery first; originals for admin preview).
 *
 * @param bool $allowOriginalFallback When false (Catalogue / list thumbs), never return
 *                                    multi-MB intake originals — empty means show a placeholder.
 */
function bandpromo_visual_resolve_url(
    string $root,
    string $filenameOrAssetId,
    string $variant = 'card',
    string $intakeBucket = '',
    bool $allowOriginalFallback = true
): string {
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';

    $variant = strtolower(trim($variant));
    if ($variant === 'optimal' || $variant === 'lightbox') {
        $variant = 'card';
    }

    $asset = null;
    $ref = trim($filenameOrAssetId);
    $lookedUp = bandpromo_asset_lookup_from_media_ref($root, $ref);
    if (is_array($lookedUp) && ($lookedUp['kind'] ?? '') === 'visual') {
        $asset = $lookedUp;
    } elseif (!bandpromo_asset_is_asset_id($ref)) {
        $asset = bandpromo_asset_lookup_visual($root, $ref, $intakeBucket)
            ?? bandpromo_asset_lookup_by_original_filename($root, $ref);
    }

    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
        return '';
    }

    // Public / default: delivery variants only.
    foreach ([$variant, $variant === 'thumb' ? 'card' : 'thumb'] as $tryVariant) {
        $url = bandpromo_visual_registry_variant_url($asset, $tryVariant);
        if ($url !== '') {
            return $url;
        }
        $url = bandpromo_visual_variant_relative_url($root, (string) ($asset['id'] ?? ''), $tryVariant);
        if ($url !== '') {
            return $url;
        }
    }

    // Admin preview may use the master file URL — never original as a page <img src>.
    // $allowOriginalFallback retained for call-site compatibility; means allowMasterPreview.
    if (!$allowOriginalFallback) {
        return '';
    }
    if ($variant === 'standard-stream' || $variant === 'poster') {
        return '';
    }

    $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo(
        (string) ($asset['original_filename'] ?? ''),
        PATHINFO_EXTENSION
    ))));
    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId !== '' && $format !== '') {
        $masterPath = bandpromo_visual_master_path($root, $assetId, $format);
        if ($masterPath !== '' && is_file($masterPath)) {
            return '/media/visual/master/' . basename($masterPath);
        }
    }
    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
    if ($masterFilename !== '') {
        $candidate = bandpromo_visual_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        if (is_file($candidate)) {
            return '/media/visual/master/' . $masterFilename;
        }
    }

    return '';
}

/**
 * @param list<string> $requiredVariants
 */
function bandpromo_visual_delivery_ready(string $root, string $assetId, array $requiredVariants = []): bool
{
    require_once __DIR__ . '/asset-registry.php';

    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    if ($asset === null || ($asset['kind'] ?? '') !== 'visual') {
        return false;
    }

    if ($requiredVariants === []) {
        $mediaType = (string) ($asset['media_type'] ?? 'image');
        $requiredVariants = $mediaType === 'video'
            ? ['poster', 'standard-stream']
            : ['thumb', 'card'];
    }

    foreach ($requiredVariants as $variant) {
        if (bandpromo_visual_variant_path($root, $assetId, (string) $variant) === '') {
            return false;
        }
    }

    return true;
}

function bandpromo_visual_delivery_delete_for_asset(string $root, string $assetId): void
{
    $dir = bandpromo_visual_delivery_dir($root, $assetId);
    if ($dir === '' || !is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
