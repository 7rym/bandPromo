<?php
/**
 * List media files for a given target directory.
 * Query param: ?target=audio|illustrations|photos|video|special|sfx|visual
 * visual = merged illustrations + photos + video (operator Visual pool).
 * special = cross-media Brand assets library (Visual + SFX).
 * sfx = Sound effects pool (brand UI audio).
 * Reads the media files index only — no DirectoryIterator / filesize on GET.
 * Admin-only.
 */
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/media-reference-helpers.php';
require_once __DIR__ . '/media-delivery-helpers.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/cover-art-helpers.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
bandpromo_asset_registry_ensure_migrated($root);
bandpromo_campaign_ensure_seeded($root);

$dirs = [
    'audio'         => bandpromo_media_target_dir('audio'),
    'illustrations' => bandpromo_media_target_dir('illustrations'),
    'photos'        => bandpromo_media_target_dir('photos'),
    'video'         => bandpromo_media_target_dir('video'),
    'special'       => bandpromo_media_target_dir('special'),
    'sfx'           => bandpromo_media_target_dir('sfx'),
    'visual'        => dirname(__DIR__) . '/media',
];

$target = $_GET['target'] ?? '';
if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target: ' . htmlspecialchars($target)]);
    exit;
}

$includeHidden = isset($_GET['include_hidden']) && $_GET['include_hidden'] === '1';
$releaseFilter = bandpromo_campaign_normalize_pool_filter((string) ($_GET['release'] ?? 'all'));
$brandFilter = bandpromo_brand_normalize_pool_filter((string) ($_GET['brand'] ?? 'all'));
$isBrandOwnedPool = in_array($target, ['special', 'sfx'], true);

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
                'lyrics' => ['label' => (
                    bandpromo_asset_normalize_text_role((string) ($display['text_role'] ?? 'lyrics')) === 'notes'
                        ? bandpromo_asset_player_text_panel_label('notes', (string) ($display['notes_label'] ?? ''))
                        : 'Lyrics'
                ), 'state' => isset($warning_set['missing_lyrics']) ? 'improvable' : (
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
            'lyrics' => ['label' => (
                bandpromo_asset_normalize_text_role((string) ($display['text_role'] ?? 'lyrics')) === 'notes'
                    ? bandpromo_asset_player_text_panel_label('notes', (string) ($display['notes_label'] ?? ''))
                    : 'Lyrics'
            ), 'state' => $display['lyrics'] !== '' ? 'good' : 'improvable'],
        ],
    ];
}

/**
 * Attach brand title when an entry already has brand_id.
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function bandpromo_list_media_attach_brand_labels(string $root, array $entry): array
{
    require_once __DIR__ . '/brand-storage.php';
    $brandId = trim((string) ($entry['brand_id'] ?? ''));
    $entry['brand_orphan'] = $brandId === '';
    if ($brandId === '') {
        $entry['brand_title'] = '';

        return $entry;
    }

    try {
        $document = bandpromo_brand_load_document($root, $brandId);
        $title = trim((string) ($document['title'] ?? ''));
        $entry['brand_title'] = $title !== '' ? $title : $brandId;
    } catch (Throwable $throwable) {
        $entry['brand_title'] = $brandId;
    }

    return $entry;
}

/**
 * Attach catalogue release title/date when an entry already has release_id.
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function bandpromo_list_media_attach_campaign_labels(string $root, array $entry): array
{
    $releaseId = trim((string) ($entry['release_id'] ?? ''));
    if ($releaseId === '') {
        if (!array_key_exists('release_title', $entry)) {
            $entry['release_title'] = '';
        }
        if (!array_key_exists('release_date', $entry)) {
            $entry['release_date'] = '';
        }

        return $entry;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
        $entry['release_title'] = trim((string) ($document['title'] ?? ''));
        $entry['release_date'] = trim((string) ($document['release_date'] ?? ''));
    } catch (Throwable $throwable) {
        if (!array_key_exists('release_title', $entry)) {
            $entry['release_title'] = '';
        }
        if (!array_key_exists('release_date', $entry)) {
            $entry['release_date'] = '';
        }
    }

    return $entry;
}

/**
 * Infer media_type for Brand assets (media/special) from the file extension.
 */
function bandpromo_list_media_special_media_type(string $filename): string
{
    require_once __DIR__ . '/media-library-state.php';
    $kind = bandpromo_media_filename_kind($filename);
    if ($kind === 'video' || $kind === 'audio' || $kind === 'image') {
        return $kind;
    }

    return 'other';
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
    $originalLabel = (string) ($indexed['original_filename'] ?? '');
    if (bandpromo_media_is_generated_delivery_artifact($filename)
        || bandpromo_media_is_generated_delivery_artifact($originalLabel)
    ) {
        return null;
    }
    if ($bucket === 'audio' && !bandpromo_media_is_audio_pool_filename($filename)) {
        return null;
    }

    $entry = [
        'name'     => $filename,
        'size'     => (int) ($indexed['size'] ?? 0),
        'modified' => (int) ($indexed['modified'] ?? 0),
        'origin'   => (string) ($indexed['origin'] ?? bandpromo_media_origin($filename)),
        'release_id' => bandpromo_campaign_id_for_media_file($root, $bucket, $filename),
        'hidden'   => bandpromo_media_is_effectively_hidden_for_install($bucket, $filename),
        'original_format' => (string) ($indexed['original_format'] ?? strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))),
        'original_filename' => (string) ($indexed['original_filename'] ?? ''),
        'intake_bucket' => $bucket,
        'media_type' => $bucket === 'video' ? 'video' : (
            in_array($bucket, ['illustrations', 'photos'], true) ? 'image' : (
                $bucket === 'audio' ? 'audio' : (
                    $bucket === 'special' ? bandpromo_list_media_special_media_type($filename) : 'other'
                )
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
        if ($entry['original_filename'] === '' && is_array($asset)) {
            $entry['original_filename'] = basename(trim((string) ($asset['original_filename'] ?? '')));
        }
    }

            if ($bucket === 'sfx') {
        $entry['media_type'] = 'audio';
        $entry['role'] = 'sfx';
        $entry['pool_ready'] = false;
        $entry['reference_info'] = bandpromo_media_reference_describe_file(
            $root,
            'sfx',
            $filename,
            null,
            null,
            null
        );
        $sfxAsset = bandpromo_asset_lookup_by_original_filename($root, $filename);
        if (!is_array($sfxAsset) || ($sfxAsset['kind'] ?? '') !== 'sfx') {
            $sfxAsset = bandpromo_asset_lookup_by_master_filename($root, $filename);
        }
        if (is_array($sfxAsset) && ($sfxAsset['kind'] ?? '') === 'sfx') {
            require_once __DIR__ . '/sfx-helpers.php';
            $entry['asset_id'] = (string) ($sfxAsset['id'] ?? '');
            $entry['brand_id'] = (string) ($sfxAsset['brand_id'] ?? '');
            $entry['role'] = 'sfx';
            if (trim((string) ($entry['original_filename'] ?? '')) === '') {
                $entry['original_filename'] = basename(trim((string) ($sfxAsset['original_filename'] ?? '')));
            }
            $delivery = is_array($sfxAsset['delivery'] ?? null) ? $sfxAsset['delivery'] : [];
            $entry['pool_ready'] = !empty($delivery['ready']) || !empty($delivery['audio_optimal']);
            $playUrl = bandpromo_sfx_resolve_play_url($root, $sfxAsset);
            if ($playUrl !== '') {
                $entry['play_url'] = $playUrl;
            }
            $sfxDisplay = is_array($sfxAsset['display'] ?? null) ? $sfxAsset['display'] : [];
            $entry['display'] = $sfxDisplay;
            if (trim((string) ($sfxDisplay['title'] ?? '')) !== '') {
                $entry['display_title'] = trim((string) $sfxDisplay['title']);
            }
        }
        $entry = bandpromo_list_media_attach_brand_labels($root, $entry);
        // Optional campaign context only — membership for this pool is brand-owned.
        $brandReleaseId = bandpromo_campaign_id_for_brand_owned_asset($root, (string) ($entry['brand_id'] ?? ''));
        if ($brandReleaseId !== '') {
            $entry['release_id'] = $brandReleaseId;
            $entry = bandpromo_list_media_attach_campaign_labels($root, $entry);
        }
    }

    if (in_array($bucket, ['illustrations', 'photos', 'video', 'special'], true)) {
        // Brand assets must not list shell/SFX audio — those live under Sound effects.
        if ($bucket === 'special' && ($entry['media_type'] ?? '') === 'audio') {
            return null;
        }

        $intakeBucket = bandpromo_asset_intake_bucket_for_files_index_target($bucket);
        $visualAsset = $intakeBucket !== ''
            ? bandpromo_asset_lookup_visual($root, $filename, $intakeBucket)
            : null;
        if (is_array($visualAsset)) {
            $entry['asset_id'] = (string) ($visualAsset['id'] ?? '');
            if ($bucket === 'special' && (string) ($visualAsset['media_type'] ?? '') === 'audio') {
                return null;
            }
        }

        $galleryIndex = null;
        if ($bucket === 'special') {
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

        if (is_array($visualAsset)) {
            $entry['brand_id'] = (string) ($visualAsset['brand_id'] ?? '');
            $entry['role'] = (string) ($visualAsset['role'] ?? 'unassigned');
            $entry['has_alpha'] = !empty($visualAsset['has_alpha']);
            $entry['width'] = max(0, (int) ($visualAsset['master_width'] ?? 0));
            $entry['height'] = max(0, (int) ($visualAsset['master_height'] ?? 0));
            $entry = bandpromo_list_media_attach_brand_labels($root, $entry);
            $visualDisplay = bandpromo_asset_read_visual_display($visualAsset);
            $entry['display'] = $visualDisplay;
            $entry['display_description'] = $visualDisplay['description'];
            $entry['display_captured_at'] = $visualDisplay['captured_at'];
            $entry['display_keywords'] = $visualDisplay['keywords'];
            $entry['operator_title'] = bandpromo_visual_operator_title($root, $visualAsset, $entry);
            $entry['display_title'] = bandpromo_visual_listing_title($root, $visualAsset, $entry);
            if (($visualAsset['media_type'] ?? '') !== '') {
                $entry['media_type'] = (string) $visualAsset['media_type'];
            }
            if ($bucket === 'special' && ($entry['media_type'] ?? '') === 'audio') {
                return null;
            }
            $delivery = is_array($visualAsset['delivery'] ?? null) ? $visualAsset['delivery'] : [];
            $deliveryVariants = is_array($delivery['variants'] ?? null) ? $delivery['variants'] : [];
            if (isset($delivery['variants']) && is_array($delivery['variants'])) {
                $entry['delivery_variants'] = $delivery['variants'];
            }

            $mediaType = (string) ($entry['media_type'] ?? 'image');
            $required = $mediaType === 'video'
                ? ['poster', 'standard-stream']
                : ['thumb', 'card'];
            $entry['required_variants'] = $required;
            $missing = [];
            foreach ($required as $variant) {
                // Registry is source of truth for pool-ready — no disk probes on list.
                if (!isset($deliveryVariants[$variant]) || !is_array($deliveryVariants[$variant])) {
                    $missing[] = $variant;
                }
            }
            $entry['missing_variants'] = $missing;
            $entry['pool_ready'] = $missing === [];
            if ($missing !== []) {
                $entry['pool_ready_reason'] = 'Missing delivery variant'
                    . (count($missing) === 1 ? '' : 's')
                    . ': ' . implode(', ', $missing);
            }

            if ($mediaType === 'video') {
                $posterResolved = bandpromo_visual_registry_variant_url($visualAsset, 'poster');
                $streamResolved = bandpromo_visual_registry_variant_url($visualAsset, 'standard-stream');
                if ($posterResolved !== '') {
                    $entry['poster_url'] = $posterResolved;
                }
                if ($streamResolved !== '') {
                    $entry['stream_url'] = $streamResolved;
                    $entry['preview_url'] = $streamResolved;
                }
            } else {
                $thumbResolved = bandpromo_visual_registry_variant_url($visualAsset, 'thumb');
                $cardResolved = bandpromo_visual_registry_variant_url($visualAsset, 'card');
                if ($thumbResolved !== '') {
                    $entry['thumb_url'] = $thumbResolved;
                }
                if ($cardResolved !== '') {
                    $entry['card_url'] = $cardResolved;
                }
            }
        }

        if ($bucket === 'special') {
            $brandReleaseId = bandpromo_campaign_id_for_brand_owned_asset($root, (string) ($entry['brand_id'] ?? ''));
            if ($brandReleaseId !== '') {
                $entry['release_id'] = $brandReleaseId;
            } elseif (trim((string) ($entry['release_id'] ?? '')) === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
                $entry['release_id'] = '';
            }
            $entry = bandpromo_list_media_attach_brand_labels($root, $entry);
            if ($brandReleaseId !== '') {
                $entry = bandpromo_list_media_attach_campaign_labels($root, $entry);
            }
        } else {
            // Catalogue is campaign usage (gallery/cover/poster/page plus Brand visual shell those campaigns play), not Brand ownership.
            $visualMeta = bandpromo_campaign_visual_listing_meta(
                $root,
                trim((string) ($entry['asset_id'] ?? '')),
                (string) ($entry['release_id'] ?? '')
            );
            $entry['release_id'] = $visualMeta['release_id'];
            $entry['release_ids'] = $visualMeta['release_ids'];
            $entry['release_orphan'] = $visualMeta['release_orphan'];
            $entry['release_title'] = $visualMeta['release_title'];
            $entry['release_date'] = $visualMeta['release_date'];
        }
    }

    if (in_array($bucket, ['photos', 'video'], true) && !array_key_exists('pool_ready', $entry)) {
        $entry['pool_ready'] = !empty($indexed['pool_ready']);
    }

    if ($bucket === 'illustrations' && !array_key_exists('pool_ready', $entry)) {
        // M4 register-or-fail: unregistered illustrations are not pool-ready.
        $entry['pool_ready'] = false;
        $entry['pool_ready_reason'] = 'Not registered in the visual asset registry';
    }

    if ($bucket === 'video') {
        $videoMeta = is_array($indexed['video_meta'] ?? null) ? $indexed['video_meta'] : [];
        $entry['video_meta'] = $videoMeta;
        $entry['delivery_pending'] = !empty($indexed['delivery_pending']);
        $running = $context['video_delivery_running'] ?? [];
        $entry['delivery_running'] = isset($running[$filename]);
        if (trim((string) ($entry['poster_url'] ?? '')) === '') {
            $entry['poster_url'] = (string) ($indexed['poster_url'] ?? $videoMeta['poster_url'] ?? '');
        }
        if (trim((string) ($entry['preview_url'] ?? '')) === '') {
            $entry['preview_url'] = (string) ($indexed['preview_url'] ?? $videoMeta['preview_url'] ?? '');
        }
        if (trim((string) ($entry['stream_url'] ?? '')) === '' && trim((string) ($entry['preview_url'] ?? '')) !== '') {
            $entry['stream_url'] = (string) $entry['preview_url'];
        }
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
foreach (['photos', 'video', 'illustrations'] as $galleryBucket) {
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
} elseif ($target === 'special') {
    foreach (['illustrations', 'photos', 'video', 'special', 'sfx'] as $libraryTarget) {
        bandpromo_media_files_index_ensure_target($root, $libraryTarget);
        foreach (bandpromo_media_files_index_list($root, $libraryTarget) as $indexed) {
            if (!is_array($indexed)) {
                continue;
            }
            $entry = bandpromo_list_media_build_entry($root, $libraryTarget, $indexed, $buildContext);
            if ($entry !== null) {
                $allFiles[] = $entry;
            }
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

$selectedBrandLibrary = null;
$selectedBrandSlotAssignments = [];
$allBrandLibrary = [];
if ($isBrandOwnedPool) {
    bandpromo_brand_ensure_seeded($root);
    foreach (bandpromo_brand_registry_entries($root) as $brandEntry) {
        if (!is_array($brandEntry)) {
            continue;
        }
        $candidateId = bandpromo_brand_canonical_id((string) ($brandEntry['id'] ?? ''));
        if ($candidateId === '') {
            continue;
        }
        try {
            $brandDocument = bandpromo_brand_load_document($root, $candidateId);
        } catch (Throwable $throwable) {
            continue;
        }
        $membership = array_fill_keys(
            is_array($brandDocument['library_asset_ids'] ?? null)
                ? $brandDocument['library_asset_ids']
                : [],
            true
        );
        $allBrandLibrary += $membership;
        if ($brandFilter !== 'all' && $brandFilter !== 'orphans' && $candidateId === $brandFilter) {
            $selectedBrandLibrary = $membership;
            $slotLabels = [
                'logo' => 'Logo',
                'poster' => 'Poster',
                'background_image' => 'Still background',
                'background_video' => 'Living background',
                'welcome_audio' => 'Welcome audio',
                'loggedin_audio' => 'Logged-in audio',
            ];
            foreach ((array) ($brandDocument['asset_ids'] ?? []) as $slotKey => $slotAssetId) {
                $slotAssetId = trim((string) $slotAssetId);
                if ($slotAssetId === '') {
                    continue;
                }
                $selectedBrandSlotAssignments[$slotAssetId][] = $slotLabels[$slotKey]
                    ?? ucfirst(str_replace('_', ' ', (string) $slotKey));
            }
        }
    }
}

if ($target === 'special') {
    $deduplicated = [];
    foreach ($allFiles as $entry) {
        $assetId = trim((string) ($entry['asset_id'] ?? ''));
        $key = $assetId !== ''
            ? 'asset:' . $assetId
            : 'file:' . (string) ($entry['intake_bucket'] ?? '') . ':' . (string) ($entry['name'] ?? '');
        if (!isset($deduplicated[$key])) {
            $deduplicated[$key] = $entry;
        }
    }
    $allFiles = array_values($deduplicated);
}

foreach ($allFiles as $entry) {
    if (!empty($entry['hidden']) && !$includeHidden && !$isBrandOwnedPool) {
        continue;
    }

    if ($isBrandOwnedPool) {
        $assetId = trim((string) ($entry['asset_id'] ?? ''));
        if ($brandFilter === 'orphans') {
            if ($assetId !== '' && isset($allBrandLibrary[$assetId])) {
                continue;
            }
        } elseif ($brandFilter === 'all') {
            if ($assetId === '' || !isset($allBrandLibrary[$assetId])) {
                continue;
            }
        } elseif ($brandFilter !== 'all' && ($assetId === '' || !isset($selectedBrandLibrary[$assetId]))) {
            continue;
        }
        if ($assetId !== '' && isset($selectedBrandSlotAssignments[$assetId])) {
            $entry['brand_slot_assigned'] = true;
            $entry['brand_slot_labels'] = array_values(array_unique($selectedBrandSlotAssignments[$assetId]));
        } else {
            $entry['brand_slot_assigned'] = false;
            $entry['brand_slot_labels'] = [];
        }
    } elseif ($releaseFilter === 'orphans') {
        if (empty($entry['release_orphan'])) {
            continue;
        }
    } elseif ($releaseFilter === 'releases') {
        if (!empty($entry['release_orphan'])) {
            continue;
        }
    } elseif ($releaseFilter !== 'all') {
        $memberIds = [];
        if (isset($entry['release_ids']) && is_array($entry['release_ids'])) {
            foreach ($entry['release_ids'] as $memberId) {
                $memberId = trim((string) $memberId);
                if ($memberId !== '') {
                    $memberIds[$memberId] = true;
                }
            }
        }
        $single = trim((string) ($entry['release_id'] ?? ''));
        if ($single !== '') {
            $memberIds[$single] = true;
        }
        if (!isset($memberIds[$releaseFilter])) {
            continue;
        }
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
