<?php
/**
 * List media files for a given target directory.
 * Query param: ?target=audio|illustrations|photos|video|special|visual
 * visual = merged illustrations + photos + video (operator Visual pool).
 * Reads the media files index only — no DirectoryIterator / filesize on GET.
 * Admin-only.
 */
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/media-reference-helpers.php';
require_once __DIR__ . '/media-delivery-helpers.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/cover-art-helpers.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
bandpromo_asset_registry_ensure_migrated($root);
bandpromo_release_ensure_seeded($root);

$dirs = [
    'audio'         => bandpromo_media_target_dir('audio'),
    'illustrations' => bandpromo_media_target_dir('illustrations'),
    'photos'        => bandpromo_media_target_dir('photos'),
    'video'         => bandpromo_media_target_dir('video'),
    'special'       => bandpromo_media_target_dir('special'),
    'visual'        => dirname(__DIR__) . '/media',
];

$target = $_GET['target'] ?? '';
if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target: ' . htmlspecialchars($target)]);
    exit;
}

$includeHidden = isset($_GET['include_hidden']) && $_GET['include_hidden'] === '1';
$releaseFilter = bandpromo_release_normalize_pool_filter((string) ($_GET['release'] ?? 'all'));

$dir = $dirs[$target];
$files = [];

$isVisualPool = $target === 'visual';
$listBuckets = $isVisualPool
    ? ['illustrations', 'photos', 'video']
    : [$target];

if ($target === 'video' || $isVisualPool) {
    bandpromo_reconcile_background_tasks(false);
}

$videoDeliveryRunning = ($target === 'video' || $isVisualPool)
    ? bandpromo_video_delivery_running_filename_map()
    : [];

function bandpromo_default_audio_metadata_health(): array {
    return [
        'inspected' => false,
        'source' => 'latest_build_validation',
        'fields' => [
            'cover' => ['label' => 'Cover', 'state' => 'unknown'],
            'artist' => ['label' => 'Artist', 'state' => 'unknown'],
            'title' => ['label' => 'Title', 'state' => 'unknown'],
            'release' => ['label' => 'Release', 'state' => 'unknown'],
            'description' => ['label' => 'Description', 'state' => 'unknown'],
            'lyrics' => ['label' => 'Lyrics', 'state' => 'unknown'],
        ],
    ];
}

function bandpromo_load_audio_validation_map(string $root): array {
    $default = [];
    $decoded = bandpromo_playlist_decode_validation_report($root);

    if (!is_array($decoded)) {
        return $default;
    }

    $tracks = is_array($decoded['tracks'] ?? null) ? $decoded['tracks'] : [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }

        $file = trim((string) ($track['file'] ?? ''));
        if ($file === '') {
            continue;
        }

        $warnings = array_values(array_filter(
            is_array($track['warnings'] ?? null) ? $track['warnings'] : [],
            static fn($value) => is_string($value) && $value !== ''
        ));
        $warning_set = array_fill_keys($warnings, true);
        $asset = bandpromo_asset_lookup_by_master_filename($root, $file)
            ?? bandpromo_asset_lookup_by_original_filename($root, $file);
        $display = bandpromo_asset_read_audio_display($asset);
        $has_description = trim((string) ($display['comment'] ?? '')) !== '';

        $default[$file] = [
            'inspected' => true,
            'source' => 'latest_build_validation',
            'display_title' => trim(str_replace(
                ["\r\n", "\r", "\n"],
                ' ',
                (string) ($display['title'] !== '' ? $display['title'] : ($track['title'] ?? ''))
            )),
            'fields' => [
                'cover' => ['label' => 'Cover', 'state' => isset($warning_set['missing_cover_art']) ? 'required' : 'good'],
                'artist' => ['label' => 'Artist', 'state' => isset($warning_set['missing_artist_tag']) ? 'required' : 'good'],
                'title' => ['label' => 'Title', 'state' => isset($warning_set['missing_title_tag']) ? 'required' : 'good'],
                'release' => ['label' => 'Release', 'state' => isset($warning_set['missing_album_tag']) ? 'improvable' : 'good'],
                'description' => ['label' => 'Description', 'state' => $has_description ? 'good' : 'improvable'],
                'lyrics' => ['label' => 'Lyrics', 'state' => isset($warning_set['missing_lyrics']) ? 'improvable' : (
                    trim((string) ($display['lyrics'] ?? '')) !== '' ? 'good' : 'improvable'
                )],
            ],
        ];
    }

    return $default;
}

function bandpromo_audio_metadata_health_for_listing(
    string $root,
    string $filename,
    array $validation_map,
    array $listingContext = []
): array {
    if (isset($validation_map[$filename])) {
        return $validation_map[$filename];
    }

    $masterLookup = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($masterLookup === null) {
        return bandpromo_default_audio_metadata_health();
    }

    $label = bandpromo_audio_display_label_for_listing($root, $filename, $validation_map, $listingContext);
    $display = bandpromo_asset_read_audio_display($masterLookup);

    return [
        'inspected' => true,
        'source' => 'asset-registry',
        'display_title' => (string) ($label['display_title'] ?? ''),
        'fields' => [
            'cover' => ['label' => 'Cover', 'state' => trim((string) ($display['cover'] ?? '')) !== '' ? 'good' : 'unknown'],
            'artist' => ['label' => 'Artist', 'state' => $display['artist'] !== '' ? 'good' : 'required'],
            'title' => ['label' => 'Title', 'state' => $display['title'] !== '' ? 'good' : 'required'],
            'release' => ['label' => 'Release', 'state' => $display['album'] !== '' ? 'good' : 'improvable'],
            'description' => ['label' => 'Description', 'state' => $display['comment'] !== '' ? 'good' : 'improvable'],
            'lyrics' => ['label' => 'Lyrics', 'state' => $display['lyrics'] !== '' ? 'good' : 'improvable'],
        ],
    ];
}

/**
 * Build one listing entry from an indexed file row for a concrete intake bucket.
 */
function bandpromo_list_media_build_entry(
    string $root,
    string $bucket,
    array $indexed,
    array $context
): ?array {
    $filename = (string) ($indexed['name'] ?? '');
    if ($filename === '') {
        return null;
    }

    $entry = [
        'name'     => $filename,
        'size'     => (int) ($indexed['size'] ?? 0),
        'modified' => (int) ($indexed['modified'] ?? 0),
        'origin'   => (string) ($indexed['origin'] ?? bandpromo_media_origin($filename)),
        'release_id' => bandpromo_release_id_for_media_file($root, $bucket, $filename),
        'hidden'   => bandpromo_media_is_effectively_hidden_for_install($bucket, $filename),
        'original_format' => (string) ($indexed['original_format'] ?? strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))),
        'intake_bucket' => $bucket,
        'media_type' => $bucket === 'video' ? 'video' : (
            in_array($bucket, ['illustrations', 'photos'], true) ? 'image' : (
                $bucket === 'audio' ? 'audio' : 'other'
            )
        ),
        'audio_master' => $bucket === 'audio'
            ? (is_array($indexed['audio_master'] ?? null) ? $indexed['audio_master'] : [
                'exists' => false,
                'filename' => '',
                'editable' => false,
                'needs_materialize' => true,
                'size' => 0,
                'modified' => 0,
            ])
            : null,
        'audio_metadata_health' => $bucket === 'audio'
            ? bandpromo_audio_metadata_health_for_listing(
                $root,
                $filename,
                $context['audio_validation_map'] ?? [],
                $context['audio_listing_context'] ?? []
            )
            : null,
    ];

    if ($bucket === 'audio') {
        $entry = array_merge(
            $entry,
            bandpromo_audio_display_label_for_listing(
                $root,
                $filename,
                $context['audio_validation_map'] ?? [],
                $context['audio_listing_context'] ?? []
            )
        );
        $cataloguedAudio = $context['catalogued_audio'] ?? [];
        $entry['in_catalog'] = isset($cataloguedAudio[$filename]);
        $asset = bandpromo_asset_lookup_by_original_filename($root, $filename)
            ?? bandpromo_asset_lookup_by_master_filename($root, $filename);
        if ($asset !== null && array_key_exists('audio_optimal', is_array($asset['delivery'] ?? null) ? $asset['delivery'] : [])) {
            $entry['pool_ready'] = !empty($asset['delivery']['audio_optimal']);
        } else {
            $entry['pool_ready'] = !empty($indexed['pool_ready']);
        }
    }

    if (in_array($bucket, ['illustrations', 'photos', 'video'], true)) {
        $galleryIndex = null;
        if ($bucket === 'illustrations') {
            $galleryIndex = null;
        } elseif (isset($context['gallery_indexes'][$bucket])) {
            $galleryIndex = $context['gallery_indexes'][$bucket];
        }

        $entry['reference_info'] = bandpromo_media_reference_describe_file(
            $root,
            $bucket,
            $filename,
            $galleryIndex,
            $context['playlist_cover_context'] ?? null,
            $context['track_visual_index'] ?? null
        );
        if ($bucket === 'illustrations') {
            $entry['cover_info'] = $entry['reference_info'];
        }
    }

    if (in_array($bucket, ['photos', 'video'], true)) {
        $entry['pool_ready'] = !empty($indexed['pool_ready']);
    }

    if ($bucket === 'illustrations') {
        // Artwork may not use pool_ready the same way; leave unset or true.
        if (!array_key_exists('pool_ready', $entry)) {
            $entry['pool_ready'] = true;
        }
    }

    if ($bucket === 'video') {
        $videoMeta = is_array($indexed['video_meta'] ?? null) ? $indexed['video_meta'] : [];
        $entry['video_meta'] = $videoMeta;
        $entry['poster_url'] = (string) ($indexed['poster_url'] ?? $videoMeta['poster_url'] ?? '');
        $entry['preview_url'] = (string) ($indexed['preview_url'] ?? $videoMeta['preview_url'] ?? '');
        $entry['delivery_pending'] = !empty($indexed['delivery_pending']);
        $running = $context['video_delivery_running'] ?? [];
        $entry['delivery_running'] = isset($running[$filename]);
    }

    return $entry;
}

$audio_validation_map = $target === 'audio' ? bandpromo_load_audio_validation_map($root) : [];
$audio_listing_context = $target === 'audio' ? bandpromo_audio_files_listing_context($root) : [];
$cataloguedAudio = $target === 'audio'
    ? array_fill_keys(bandpromo_audio_catalogued_filenames($root), true)
    : [];

$trackVisualIndex = null;
$playlistCoverContext = null;
$galleryIndexes = [];
if ($isVisualPool || in_array($target, ['illustrations', 'photos', 'video'], true)) {
    $trackVisualIndex = bandpromo_media_reference_build_track_visual_index($root);
}
if ($isVisualPool || $target === 'illustrations') {
    $playlistCoverContext = bandpromo_cover_art_load_playlist_context($root, $trackVisualIndex);
}
foreach (['photos', 'video'] as $galleryBucket) {
    if ($isVisualPool || $target === $galleryBucket) {
        $galleryIndexes[$galleryBucket] = bandpromo_media_reference_build_gallery_index($root, $galleryBucket);
    }
}

$buildContext = [
    'audio_validation_map' => $audio_validation_map,
    'audio_listing_context' => $audio_listing_context,
    'catalogued_audio' => $cataloguedAudio,
    'track_visual_index' => $trackVisualIndex,
    'playlist_cover_context' => $playlistCoverContext,
    'gallery_indexes' => $galleryIndexes,
    'video_delivery_running' => $videoDeliveryRunning,
];

$allFiles = [];
if ($isVisualPool) {
    foreach (bandpromo_media_files_index_list_visual($root) as $indexed) {
        $bucket = (string) ($indexed['intake_bucket'] ?? $indexed['target'] ?? '');
        if ($bucket === '') {
            continue;
        }
        $entry = bandpromo_list_media_build_entry($root, $bucket, $indexed, $buildContext);
        if ($entry !== null) {
            $allFiles[] = $entry;
        }
    }
} else {
    bandpromo_media_files_index_ensure_target($root, $target);
    foreach (bandpromo_media_files_index_list($root, $target) as $indexed) {
        if (!is_array($indexed)) {
            continue;
        }
        $entry = bandpromo_list_media_build_entry($root, $target, $indexed, $buildContext);
        if ($entry !== null) {
            $allFiles[] = $entry;
        }
    }
}

foreach ($allFiles as $entry) {
    if ($entry['hidden'] && !$includeHidden) {
        continue;
    }

    if ($releaseFilter === 'orphans') {
        if ($target !== 'audio' || empty($entry['release_orphan'])) {
            continue;
        }
    } elseif ($releaseFilter !== 'all' && (string) ($entry['release_id'] ?? '') !== $releaseFilter) {
        continue;
    }

    $files[] = $entry;
}

if ($target === 'audio') {
    usort($files, 'bandpromo_audio_files_listing_sort');
} else {
    usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
}

echo json_encode([
    'files' => $files,
    'dir' => str_replace($root, '', (string) $dir),
    'target' => $target,
], JSON_UNESCAPED_UNICODE);
