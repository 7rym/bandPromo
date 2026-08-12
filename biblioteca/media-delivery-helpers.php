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
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe);
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $stream = bandpromo_visual_variant_relative_url($root, (string) ($asset['id'] ?? ''), 'standard-stream');
        if ($stream !== '') {
            return $stream;
        }
    }

    if (bandpromo_video_delivery_ready($root, $safe)) {
        return '/media/video/optimal/' . bandpromo_video_delivery_basename($safe);
    }

    $extension = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));
    if (in_array($extension, ['mp4', 'webm', 'mov'], true)) {
        return '/media/video/original/' . $safe;
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
    if ($posterUrl === '') {
        $posterUrl = bandpromo_video_poster_ready($root, $safe)
            ? bandpromo_video_poster_relative_url($safe)
            : '';
    }
    $previewUrl = bandpromo_video_admin_preview_relative_url($root, $safe);

    $deliveryReady = bandpromo_video_delivery_ready($root, $safe);
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual' && !empty($asset['id'])) {
        $deliveryReady = bandpromo_visual_delivery_ready($root, (string) $asset['id'], ['poster', 'standard-stream'])
            || $deliveryReady;
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

    $variant = strtolower(trim($variant));
    if ($variant === 'optimal' || $variant === 'lightbox') {
        $variant = 'card';
    }

    $asset = null;
    $ref = trim($filenameOrAssetId);
    if (bandpromo_asset_is_asset_id($ref)) {
        $asset = bandpromo_asset_lookup_by_id($root, $ref);
    } else {
        $asset = bandpromo_asset_lookup_visual($root, $ref, $intakeBucket)
            ?? bandpromo_asset_lookup_by_original_filename($root, $ref);
    }

    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        // Prefer the requested variant, then the other small delivery size.
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
        if (!$allowOriginalFallback) {
            return '';
        }
        $filename = basename((string) ($asset['original_filename'] ?? ''));
        $bucket = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? $intakeBucket));
    } else {
        if (!$allowOriginalFallback) {
            return '';
        }
        $filename = basename($ref);
        $bucket = bandpromo_asset_normalize_intake_bucket($intakeBucket);
    }

    if ($filename === '') {
        return '';
    }

    // M4: no stem optimal/thumb dual-read. Prefer unified visual original, then master file URL,
    // then legacy intake originals so admin previews work before delivery is rebuilt.
    require_once __DIR__ . '/visual-master-helpers.php';
    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $originalFilename = basename((string) ($asset['original_filename'] ?? $filename));
        if ($originalFilename !== '') {
            $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
            if ($unified !== '' && is_file($unified)) {
                return '/media/visual/original/' . $originalFilename;
            }
        }
        $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo($originalFilename, PATHINFO_EXTENSION))));
        $assetId = trim((string) ($asset['id'] ?? ''));
        if ($assetId !== '' && $format !== '') {
            $masterPath = bandpromo_visual_master_path($root, $assetId, $format);
            if ($masterPath !== '' && is_file($masterPath)) {
                return '/media/visual/master/' . basename($masterPath);
            }
        }
    }

    if ($variant === 'standard-stream' || $variant === 'poster') {
        // Video delivery variants only — no legacy media/video/optimal dual-read.
        return '';
    }

    $bucket = bandpromo_asset_normalize_intake_bucket(
        is_array($asset) ? (string) ($asset['intake_bucket'] ?? $intakeBucket) : $intakeBucket
    );
    $originalBases = [
        'photo' => '/media/photo/original',
        'img' => '/media/img/original',
        'special' => '/media/special',
        'video' => '/media/video/original',
    ];
    $preferred = $originalBases[$bucket] ?? '';
    if ($preferred !== '') {
        $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $preferred . '/' . $filename);
        if (is_file($path)) {
            return $preferred . '/' . $filename;
        }
    }
    foreach ($originalBases as $base) {
        if ($base === $preferred) {
            continue;
        }
        $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $base . '/' . $filename);
        if (is_file($path)) {
            return $base . '/' . $filename;
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
            // Dual-read fallback via original filename.
            $url = bandpromo_visual_resolve_url(
                $root,
                (string) ($asset['original_filename'] ?? ''),
                (string) $variant,
                (string) ($asset['intake_bucket'] ?? '')
            );
            if ($url === '') {
                return false;
            }
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
