<?php
/**
 * List media files for a given target directory.
 * Query param: ?target=audio|cover|photos|video
 * Returns: { files: [{name, size, modified}] }
 * Admin-only.
 */
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/media-reference-helpers.php';
require_once __DIR__ . '/media-delivery-helpers.php';

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
    $validation_file = $root . '/play/playlist-validation.json';
    $playlist_map = [];

    $playlist_file = $root . '/play/playlist.json';
    if (is_file($playlist_file)) {
        $playlist_raw = file_get_contents($playlist_file);
        $playlist_decoded = $playlist_raw !== false ? json_decode($playlist_raw, true) : null;
        if (is_array($playlist_decoded)) {
            foreach ($playlist_decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $file = trim((string) ($entry['file'] ?? ''));
                if ($file === '') {
                    continue;
                }
                $playlist_map[$file] = $entry;
            }
        }
    }

    if (!is_file($validation_file)) {
        return $default;
    }

    $raw = file_get_contents($validation_file);
    if ($raw === false) {
        return $default;
    }

    $decoded = json_decode($raw, true);
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
        $playlist_entry = is_array($playlist_map[$file] ?? null) ? $playlist_map[$file] : [];
        $has_description = trim((string) ($playlist_entry['description'] ?? '')) !== '';

        $default[$file] = [
            'inspected' => true,
            'source' => 'latest_build_validation',
            'display_title' => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($track['title'] ?? ''))),
            'fields' => [
                'cover' => ['label' => 'Cover', 'state' => isset($warning_set['missing_cover_art']) ? 'required' : 'good'],
                'artist' => ['label' => 'Artist', 'state' => isset($warning_set['missing_artist_tag']) ? 'required' : 'good'],
                'title' => ['label' => 'Title', 'state' => isset($warning_set['missing_title_tag']) ? 'required' : 'good'],
                'release' => ['label' => 'Release', 'state' => isset($warning_set['missing_album_tag']) ? 'improvable' : 'good'],
                'description' => ['label' => 'Description', 'state' => $has_description ? 'good' : 'improvable'],
                'lyrics' => ['label' => 'Lyrics', 'state' => isset($warning_set['missing_lyrics']) ? 'improvable' : 'good'],
            ],
        ];
    }

    return $default;
}

function bandpromo_audio_metadata_health(string $filename, array $validation_map): array {
    return $validation_map[$filename] ?? bandpromo_default_audio_metadata_health();
}

function bandpromo_audio_metadata_health_for_listing(
    string $root,
    string $filename,
    array $validation_map,
    array $listingContext = []
): array {
    if (isset($validation_map[$filename])) {
        return bandpromo_audio_metadata_health($filename, $validation_map);
    }

    if (!bandpromo_audio_is_catalogued($root, $filename)) {
        return bandpromo_default_audio_metadata_health();
    }

    $label = bandpromo_audio_display_label_for_listing($root, $filename, $validation_map, $listingContext);

    return [
        'inspected' => true,
        'source' => 'auto_registered',
        'display_title' => (string) ($label['display_title'] ?? ''),
        'fields' => [
            'cover' => ['label' => 'Cover', 'state' => 'good'],
            'artist' => ['label' => 'Artist', 'state' => 'good'],
            'title' => ['label' => 'Title', 'state' => 'good'],
            'release' => ['label' => 'Release', 'state' => 'good'],
            'description' => ['label' => 'Description', 'state' => 'improvable'],
            'lyrics' => ['label' => 'Lyrics', 'state' => 'improvable'],
        ],
    ];
}

function bandpromo_audio_master_info(string $root, string $filename): array {
    $master = bandpromo_find_audio_master($root, $filename);
    if ($master['exists']) {
        $masterPath = $root . '/media/audio/master/' . $master['filename'];
        $master['size'] = is_file($masterPath) ? filesize($masterPath) : 0;
        $master['modified'] = is_file($masterPath) ? filemtime($masterPath) : 0;
        return $master;
    }

    $prepared = bandpromo_prepare_audio_master_from_original($root, $filename);
    if (!empty($prepared['prepared'])) {
        $master = bandpromo_find_audio_master($root, $filename);
        if ($master['exists']) {
            $masterPath = $root . '/media/audio/master/' . $master['filename'];
            $master['size'] = is_file($masterPath) ? filesize($masterPath) : 0;
            $master['modified'] = is_file($masterPath) ? filemtime($masterPath) : 0;
        }
        return $master;
    }

    if (!$master['exists'] && !empty($prepared['warning'])) {
        $master['prepare_warning'] = (string) $prepared['warning'];
    }

    $originalExt = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($originalExt, ['flac', 'mp3', 'wav'], true)) {
        $master['editable'] = true;
        if (!$master['exists']) {
            $master['needs_materialize'] = true;
        }
    }

    return $master;
}

if (is_dir($dir)) {
    if ($target === 'audio') {
        bandpromo_reconcile_uncatalogued_audio_originals($root);
    }

    $audio_validation_map = $target === 'audio' ? bandpromo_load_audio_validation_map($root) : [];
    $audio_listing_context = $target === 'audio' ? bandpromo_audio_files_listing_context($root) : [];
    $allFiles = [];
    foreach (new DirectoryIterator($dir) as $f) {
        if ($f->isDot() || $f->isDir()) continue;

        $filename = $f->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) continue;

        $entry = [
            'name'     => $filename,
            'size'     => $f->getSize(),
            'modified' => $f->getMTime(),
            'origin'   => bandpromo_media_origin($filename),
            'release_id' => bandpromo_release_id_for_media_file($root, $target, $filename),
            'hidden'   => bandpromo_media_is_hidden_for_install($target, $filename),
            'original_format' => strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)),
            'audio_master' => $target === 'audio' ? bandpromo_audio_master_info($root, $filename) : null,
            'audio_metadata_health' => $target === 'audio'
                ? bandpromo_audio_metadata_health_for_listing($root, $filename, $audio_validation_map, $audio_listing_context)
                : null,
        ];
        if ($target === 'audio') {
            $entry = array_merge(
                $entry,
                bandpromo_audio_display_label_for_listing($root, $filename, $audio_validation_map, $audio_listing_context)
            );
            $entry['in_catalog'] = bandpromo_audio_is_catalogued($root, $filename);
        }
        if (in_array($target, ['illustrations', 'photos', 'video'], true)) {
            $entry['reference_info'] = bandpromo_media_reference_describe_file($root, $target, $filename);
            if ($target === 'illustrations') {
                $entry['cover_info'] = $entry['reference_info'];
            }
        }
        if (in_array($target, ['audio', 'photos', 'video'], true)) {
            $entry['pool_ready'] = bandpromo_media_pool_ready($root, $target, $filename);
        }
        $allFiles[] = $entry;
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
}

echo json_encode(['files' => $files, 'dir' => str_replace($root, '', $dir)], JSON_UNESCAPED_UNICODE);
