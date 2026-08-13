<?php

function bandpromo_media_library_state_path(): string
{
    return dirname(__DIR__) . '/data/media-library-state.json';
}

function bandpromo_media_library_default_state(): array
{
    return [
        'hidden' => [],
        'assets' => [],
        'files' => [],
    ];
}

function bandpromo_media_library_load_state(): array
{
    $path = bandpromo_media_library_state_path();
    if (!file_exists($path)) {
        return bandpromo_media_library_default_state();
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return bandpromo_media_library_default_state();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return bandpromo_media_library_default_state();
    }

    $state = bandpromo_media_library_default_state();
    if (isset($decoded['hidden']) && is_array($decoded['hidden'])) {
        $state['hidden'] = $decoded['hidden'];
    }
    if (isset($decoded['assets']) && is_array($decoded['assets'])) {
        $state['assets'] = $decoded['assets'];
    }
    if (isset($decoded['files']) && is_array($decoded['files'])) {
        $state['files'] = $decoded['files'];
    }

    return $state;
}

function bandpromo_media_get_asset_record(string $target, string $filename): array
{
    $state = bandpromo_media_library_load_state();
    $assets = is_array($state['assets'] ?? null) ? $state['assets'] : [];
    $key = bandpromo_media_library_key($target, $filename);
    $record = is_array($assets[$key] ?? null) ? $assets[$key] : [];

    return $record;
}

function bandpromo_media_record_asset(string $target, string $filename, array $meta): bool
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return false;
    }

    $state = bandpromo_media_library_load_state();
    if (!isset($state['assets']) || !is_array($state['assets'])) {
        $state['assets'] = [];
    }

    $key = bandpromo_media_library_key($target, $safe);
    $existing = is_array($state['assets'][$key] ?? null) ? $state['assets'][$key] : [];
    $state['assets'][$key] = array_merge($existing, $meta, [
        'recorded_at' => gmdate('Y-m-d H:i:s'),
    ]);

    return bandpromo_media_library_save_state($state);
}

function bandpromo_media_library_save_state(array $state): bool
{
    $path = bandpromo_media_library_state_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($path, $payload . PHP_EOL, LOCK_EX) !== false;
}

function bandpromo_media_library_key(string $target, string $filename): string
{
    return trim($target) . '/' . basename($filename);
}

function bandpromo_media_is_bundled_placeholder(string $filename): bool
{
    return strncmp($filename, 'bandPromo_', 10) === 0;
}

/**
 * Coarse media kind for Brand assets / shell slots (still | living | audio).
 */
function bandpromo_media_filename_kind(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'mov', 'webm', 'm4v', 'ogv'], true)) {
        return 'video';
    }
    if (in_array($ext, ['mp3', 'flac', 'ogg', 'wav', 'aac', 'm4a', 'aif', 'aiff'], true)) {
        return 'audio';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'avif'], true)) {
        return 'image';
    }

    return 'other';
}

function bandpromo_media_origin(string $filename): string
{
    return bandpromo_media_is_bundled_placeholder($filename) ? 'bundled-placeholder' : 'user-upload';
}

/**
 * Prefer files-index origin (survives operator re-upload of a bandPromo_* name).
 */
function bandpromo_media_resolved_origin(string $target, string $filename): string
{
    $filename = basename(trim($filename));
    $files = bandpromo_media_files_index_load();
    $key = bandpromo_media_files_index_key($target, $filename);
    $indexed = is_array($files[$key] ?? null) ? $files[$key] : null;
    $origin = trim((string) ($indexed['origin'] ?? ''));
    if ($origin === 'user-upload' || $origin === 'bundled-placeholder') {
        return $origin;
    }

    return bandpromo_media_origin($filename);
}

function bandpromo_media_is_hidden_for_install(string $target, string $filename): bool
{
    $state = bandpromo_media_library_load_state();
    $key = bandpromo_media_library_key($target, $filename);
    return !empty($state['hidden'][$key]);
}

function bandpromo_media_set_hidden_for_install(string $target, string $filename, bool $hidden): bool
{
    $state = bandpromo_media_library_load_state();
    $key = bandpromo_media_library_key($target, $filename);

    if ($hidden) {
        $state['hidden'][$key] = [
            'origin' => bandpromo_media_origin($filename),
            'hidden_at' => gmdate('Y-m-d H:i:s'),
        ];
    } else {
        unset($state['hidden'][$key]);
    }

    return bandpromo_media_library_save_state($state);
}

function bandpromo_media_target_dir(string $target): ?string
{
    $root = dirname(__DIR__);
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';
    $dirs = [
        'audio' => $root . '/media/audio/original',
        'illustrations' => $root . '/media/img/original',
        'photos' => $root . '/media/photo/original',
        'video' => $root . '/media/video/original',
        // Brand assets = Visual original (filter/role); legacy media/special is not a product path.
        'special' => bandpromo_visual_unified_original_dir($root),
        'sfx' => bandpromo_sfx_original_dir($root),
    ];

    return $dirs[$target] ?? null;
}

function bandpromo_media_starter_pack_basenames(string $root): array
{
    $basenames = [];
    $markerPath = rtrim($root, '/\\') . '/data/default-theme-package.json';
    if (is_file($markerPath)) {
        $decoded = json_decode((string) file_get_contents($markerPath), true);
        if (is_array($decoded) && is_array($decoded['paths'] ?? null)) {
            foreach ($decoded['paths'] as $path) {
                if (!is_string($path) || strpos($path, 'media/') !== 0) {
                    continue;
                }
                $basename = basename(str_replace('\\', '/', $path));
                if ($basename !== '') {
                    $basenames[$basename] = true;
                }
            }
        }
    }

    if ($basenames === []) {
        foreach ([
            'bandPromo_cover.png',
            'bandPromo_share.png',
            'bandPromo_vocalist.png',
            'bandPromo_the_very_first_song.flac',
            'bandPromo_the_second_song.flac',
        ] as $fallback) {
            $basenames[$fallback] = true;
        }
    }

    return array_keys($basenames);
}

function bandpromo_media_is_generated_delivery_artifact(string $filename): bool
{
    return preg_match('/^bandPromo_.+_(facebook|twitter)\.jpe?g$/i', $filename) === 1;
}

function bandpromo_media_is_operator_upload_filename(string $root, string $target, string $filename): bool
{
    if (strcasecmp($filename, 'desktop.ini') === 0) {
        return false;
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '' || $extension === 'htaccess') {
        return false;
    }

    static $starterLookup = null;
    static $starterRoot = '';
    if ($starterRoot !== $root || !is_array($starterLookup)) {
        $starterRoot = $root;
        $starterLookup = array_fill_keys(bandpromo_media_starter_pack_basenames($root), true);
    }

    if (isset($starterLookup[$filename])) {
        return false;
    }

    if (bandpromo_media_is_bundled_placeholder($filename)) {
        return false;
    }

    if (bandpromo_media_is_generated_delivery_artifact($filename)) {
        return false;
    }

    if (bandpromo_media_is_hidden_for_install($target, $filename)) {
        return false;
    }

    if ($target === 'illustrations') {
        require_once __DIR__ . '/cover-art-helpers.php';
        $manifest = bandpromo_cover_art_manifest_record($filename);
        $playlistContext = bandpromo_cover_art_load_playlist_context($root);
        $role = $manifest['role'] !== '' ? $manifest['role'] : bandpromo_cover_art_infer_role($filename, $playlistContext);
        $origin = bandpromo_cover_art_infer_origin($filename, $manifest, $role);
        if (in_array($origin, ['build-extracted', 'build-configured', 'build-sidecar-copy', 'bundled-placeholder'], true)) {
            return false;
        }
    }

    return true;
}

function bandpromo_media_install_has_operator_uploads(string $root): bool
{
    foreach (['audio', 'illustrations', 'photos', 'special'] as $target) {
        if (bandpromo_media_target_has_operator_uploads($root, $target)) {
            return true;
        }
    }

    return false;
}

function bandpromo_media_has_visible_user_uploads(string $target): bool
{
    return bandpromo_media_target_has_operator_uploads(dirname(__DIR__), $target);
}

function bandpromo_media_is_effectively_hidden_for_install(string $target, string $filename): bool
{
    if (bandpromo_media_is_hidden_for_install($target, $filename)) {
        return true;
    }

    // Brand shell pools stay listable regardless of demo hide (operators duplicate brand).
    if ($target === 'special' || $target === 'sfx') {
        return false;
    }

    $root = dirname(__DIR__);
    require_once __DIR__ . '/demo-catalog-state.php';

    if (bandpromo_demo_catalog_is_visible($root)) {
        return false;
    }

    // Hide only campaign media owned by the install's protected demo release.
    // Filename prefixes are display provenance only — not a hide policy.
    return bandpromo_demo_release_owns_media_file($root, $target, $filename);
}

/**
 * Files index — listing metadata written on upload/delete/delivery/Publish.
 * Admin Files GET must read this index only (no DirectoryIterator / filesize probes).
 */
function bandpromo_media_files_index_key(string $target, string $filename): string
{
    return bandpromo_media_library_key($target, $filename);
}

function bandpromo_media_files_index_load(): array
{
    $state = bandpromo_media_library_load_state();
    $files = is_array($state['files'] ?? null) ? $state['files'] : [];

    return $files;
}

function bandpromo_media_files_index_save(array $files): bool
{
    $state = bandpromo_media_library_load_state();
    $state['files'] = $files;

    return bandpromo_media_library_save_state($state);
}

function bandpromo_media_files_index_remove(string $target, string $filename): void
{
    $files = bandpromo_media_files_index_load();
    $key = bandpromo_media_files_index_key($target, $filename);
    if (!isset($files[$key])) {
        return;
    }
    unset($files[$key]);
    bandpromo_media_files_index_save($files);
}

/**
 * Resolve listing bytes for Files index: master working copy when present.
 * Original is provenance only (label); pre-materialize orphans may still list original.
 *
 * @return array{path:string,name:string,original_filename?:string}|null
 */
function bandpromo_media_files_index_resolve_source(string $root, string $target, string $filename): ?array
{
    $filename = basename(trim($filename));
    if ($filename === '' || strcasecmp($filename, 'desktop.ini') === 0) {
        return null;
    }

    $dir = bandpromo_media_target_dir($target);
    if ($dir === null) {
        return null;
    }

    if ($target === 'audio') {
        require_once __DIR__ . '/asset-registry.php';
        require_once __DIR__ . '/audio-master-helpers.php';
        $asset = bandpromo_asset_lookup_from_media_ref($root, $filename);
        if (is_array($asset) && ($asset['kind'] ?? '') === 'audio') {
            $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
            $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($masterName !== '') {
                $masterPath = $root . '/media/audio/master/' . $masterName;
                if (is_file($masterPath)) {
                    return [
                        'path' => $masterPath,
                        'name' => $masterName,
                        'original_filename' => $originalName,
                    ];
                }
            }
            if ($originalName !== '' && is_file($dir . '/' . $originalName)) {
                return [
                    'path' => $dir . '/' . $originalName,
                    'name' => $originalName,
                    'original_filename' => $originalName,
                ];
            }
        }

        $master = bandpromo_find_audio_master($root, $filename);
        if (!empty($master['exists']) && !empty($master['filename'])) {
            $masterPath = $root . '/media/audio/master/' . basename((string) $master['filename']);
            if (is_file($masterPath)) {
                return [
                    'path' => $masterPath,
                    'name' => basename((string) $master['filename']),
                    'original_filename' => basename((string) ($master['original_filename'] ?? '')),
                ];
            }
        }

        $originalPath = $dir . '/' . $filename;
        if (is_file($originalPath)) {
            return [
                'path' => $originalPath,
                'name' => $filename,
                'original_filename' => $filename,
            ];
        }

        return null;
    }

    if (in_array($target, ['illustrations', 'photos', 'video'], true)) {
        require_once __DIR__ . '/asset-registry.php';
        require_once __DIR__ . '/visual-master-helpers.php';
        $asset = bandpromo_asset_lookup_from_media_ref($root, $filename);
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            $originalPath = $dir . '/' . $filename;
            if (is_file($originalPath)) {
                return [
                    'path' => $originalPath,
                    'name' => $filename,
                    'original_filename' => $filename,
                ];
            }

            return null;
        }
        $intake = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? ''));
        $expected = bandpromo_asset_intake_bucket_for_files_index_target($target);
        if ($expected !== '' && $intake !== '' && $intake !== $expected) {
            // Brand visuals (intake special) are indexed under illustrations|video after T4.
            if (!($intake === 'special' && in_array($target, ['illustrations', 'photos', 'video'], true))) {
                return null;
            }
        }
        $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
        $working = bandpromo_visual_working_path($root, $asset);
        if ($working !== '' && is_file($working)) {
            $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($listing === '') {
                $listing = $filename;
            }

            return [
                'path' => $working,
                'name' => $listing,
                'original_filename' => $originalName,
            ];
        }
        if ($originalName !== '') {
            $legacy = bandpromo_asset_visual_legacy_original_path($root, $asset);
            if ($legacy !== '' && is_file($legacy)) {
                return [
                    'path' => $legacy,
                    'name' => $originalName,
                    'original_filename' => $originalName,
                ];
            }
            $unified = bandpromo_visual_unified_original_path($root, $originalName);
            if ($unified !== '' && is_file($unified)) {
                return [
                    'path' => $unified,
                    'name' => $originalName,
                    'original_filename' => $originalName,
                ];
            }
        }

        return null;
    }

    if ($target === 'special') {
        require_once __DIR__ . '/asset-registry.php';
        require_once __DIR__ . '/visual-master-helpers.php';
        $asset = bandpromo_asset_lookup_from_media_ref($root, $filename);
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
                ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
            $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
            $working = bandpromo_visual_working_path($root, $asset);
            if ($working !== '' && is_file($working)) {
                $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
                if ($listing === '') {
                    $listing = $filename;
                }

                return [
                    'path' => $working,
                    'name' => $listing,
                    'original_filename' => $originalName,
                ];
            }
            if ($originalName !== '') {
                $unified = bandpromo_visual_unified_original_path($root, $originalName);
                if ($unified !== '' && is_file($unified)) {
                    return [
                        'path' => $unified,
                        'name' => $originalName,
                        'original_filename' => $originalName,
                    ];
                }
                $legacySpecial = $root . '/media/special/' . $originalName;
                if (is_file($legacySpecial)) {
                    return [
                        'path' => $legacySpecial,
                        'name' => $originalName,
                        'original_filename' => $originalName,
                    ];
                }
            }
        }

        $legacyPath = $root . '/media/special/' . $filename;
        if (is_file($legacyPath)) {
            return [
                'path' => $legacyPath,
                'name' => $filename,
                'original_filename' => $filename,
            ];
        }
        $unifiedPath = bandpromo_visual_unified_original_path($root, $filename);
        if ($unifiedPath !== '' && is_file($unifiedPath)) {
            return [
                'path' => $unifiedPath,
                'name' => $filename,
                'original_filename' => $filename,
            ];
        }

        return null;
    }

    if ($target === 'sfx') {
        require_once __DIR__ . '/asset-registry.php';
        require_once __DIR__ . '/sfx-helpers.php';
        $asset = bandpromo_asset_lookup_from_media_ref($root, $filename);
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'sfx') {
            $asset = bandpromo_asset_lookup_by_master_filename($root, $filename);
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'sfx') {
                $asset = bandpromo_asset_lookup_by_original_filename($root, $filename);
            }
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'sfx') {
            $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
            $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($masterName !== '') {
                $masterPath = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterName;
                if (is_file($masterPath)) {
                    return [
                        'path' => $masterPath,
                        'name' => $masterName,
                        'original_filename' => $originalName,
                    ];
                }
            }
            if ($originalName !== '' && is_file($dir . '/' . $originalName)) {
                return [
                    'path' => $dir . '/' . $originalName,
                    'name' => $originalName,
                    'original_filename' => $originalName,
                ];
            }
        }

        $originalPath = $dir . '/' . $filename;
        if (is_file($originalPath)) {
            return [
                'path' => $originalPath,
                'name' => $filename,
                'original_filename' => $filename,
            ];
        }

        return null;
    }

    $originalPath = $dir . '/' . $filename;
    if (is_file($originalPath)) {
        return [
            'path' => $originalPath,
            'name' => $filename,
            'original_filename' => $filename,
        ];
    }

    return null;
}

/**
 * Probe disk once and store listing metadata for one original or master file.
 *
 * Masters-only PRP audio (no media/audio/original) is indexed by master_filename.
 *
 * @param array{origin?: string} $options
 */
function bandpromo_media_files_index_sync_file(string $root, string $target, string $filename, array $options = []): ?array
{
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/audio-master-helpers.php';

    $filename = basename(trim($filename));
    if ($filename === '' || strcasecmp($filename, 'desktop.ini') === 0) {
        return null;
    }

    $source = bandpromo_media_files_index_resolve_source($root, $target, $filename);
    if ($source === null) {
        bandpromo_media_files_index_remove($target, $filename);

        return null;
    }

    $path = $source['path'];
    $listingName = $source['name'];
    $originalLabel = basename(trim((string) ($source['original_filename'] ?? '')));
    $size = (int) filesize($path);
    $modified = (int) filemtime($path);
    $extension = strtolower((string) pathinfo(
        $originalLabel !== '' ? $originalLabel : $listingName,
        PATHINFO_EXTENSION
    ));

    $files = bandpromo_media_files_index_load();
    $key = bandpromo_media_files_index_key($target, $listingName);
    $existing = is_array($files[$key] ?? null) ? $files[$key] : null;
    if ($listingName !== $filename) {
        $requestedKey = bandpromo_media_files_index_key($target, $filename);
        if ($requestedKey !== $key) {
            unset($files[$requestedKey]);
        }
    }
    if ($originalLabel !== '' && $originalLabel !== $listingName) {
        $originalKey = bandpromo_media_files_index_key($target, $originalLabel);
        if ($originalKey !== $key) {
            unset($files[$originalKey]);
        }
    }
    $originOption = trim((string) ($options['origin'] ?? ''));
    if ($originOption === 'user-upload' || $originOption === 'bundled-placeholder') {
        $origin = $originOption;
    } elseif (is_array($existing) && trim((string) ($existing['origin'] ?? '')) === 'user-upload') {
        // Keep operator-upload provenance across rebuilds even for bandPromo_* names.
        $origin = 'user-upload';
    } else {
        $origin = bandpromo_media_origin($originalLabel !== '' ? $originalLabel : $listingName);
    }

    $entry = [
        'target' => $target,
        'name' => $listingName,
        'size' => $size,
        'modified' => $modified,
        'origin' => $origin,
        'original_format' => $extension,
        'original_filename' => $originalLabel,
        'pool_ready' => in_array($target, ['audio', 'photos', 'video', 'sfx'], true)
            ? bandpromo_media_pool_ready($root, $target, $listingName)
            : true,
        'indexed_at' => gmdate('c'),
    ];

    if ($target === 'audio') {
        $master = bandpromo_find_audio_master($root, $listingName);
        $masterFilename = basename(trim((string) ($master['filename'] ?? '')));
        $masterExists = !empty($master['exists']) && $masterFilename !== '';
        $masterPath = $masterExists ? $root . '/media/audio/master/' . $masterFilename : '';
        $entry['audio_master'] = [
            'exists' => $masterExists,
            'filename' => $masterExists ? $masterFilename : '',
            'editable' => $masterExists || in_array($extension, ['flac', 'mp3', 'wav'], true),
            'needs_materialize' => !$masterExists && in_array($extension, ['flac', 'mp3', 'wav'], true),
            'size' => ($masterExists && is_file($masterPath)) ? (int) filesize($masterPath) : 0,
            'modified' => ($masterExists && is_file($masterPath)) ? (int) filemtime($masterPath) : 0,
        ];
        if ($originalLabel === '' && !empty($master['original_filename'])) {
            $entry['original_filename'] = basename((string) $master['original_filename']);
        }
    }

    if ($target === 'video') {
        $videoMeta = bandpromo_video_admin_file_meta($root, $listingName);
        $entry['video_meta'] = $videoMeta;
        $entry['poster_url'] = (string) ($videoMeta['poster_url'] ?? '');
        $entry['preview_url'] = (string) ($videoMeta['preview_url'] ?? '');
        $entry['delivery_pending'] = !empty($videoMeta['needs_delivery']);
    }

    $files[$key] = $entry;
    bandpromo_media_files_index_save($files);

    return $entry;
}

/**
 * Index registry assets by master_filename (working copy identity).
 * Originals are labels only — do not skip when an original also exists on disk.
 */
function bandpromo_media_files_index_rebuild_registry_rows(string $root, string $target): int
{
    require_once __DIR__ . '/asset-registry.php';

    $count = 0;
    $registry = bandpromo_asset_load_registry($root);

    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset)) {
            continue;
        }

        if ($target === 'audio') {
            if (($asset['kind'] ?? '') !== 'audio') {
                continue;
            }
            $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($listing === '') {
                $listing = basename(trim((string) ($asset['original_filename'] ?? '')));
            }
            if ($listing === '') {
                continue;
            }
            if (bandpromo_media_files_index_sync_file($root, $target, $listing) !== null) {
                $count++;
            }
            continue;
        }

        if ($target === 'sfx') {
            require_once __DIR__ . '/sfx-helpers.php';
            if (($asset['kind'] ?? '') !== 'sfx') {
                continue;
            }
            $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($listing === '') {
                $listing = basename(trim((string) ($asset['original_filename'] ?? '')));
            }
            if ($listing === '') {
                continue;
            }
            if (bandpromo_media_files_index_sync_file($root, $target, $listing) !== null) {
                $count++;
            }
            continue;
        }

        if ($target === 'special') {
            if (($asset['kind'] ?? '') !== 'visual') {
                continue;
            }
            $intake = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? ''));
            $role = bandpromo_asset_normalize_visual_role((string) ($asset['role'] ?? 'unassigned'));
            $brandRoles = [
                'brand-logo',
                'brand-portrait',
                'shell-background-image',
                'shell-background-video',
            ];
            if ($intake !== 'special' && !in_array($role, $brandRoles, true)) {
                continue;
            }
            $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($listing === '') {
                $listing = basename(trim((string) ($asset['original_filename'] ?? '')));
            }
            if ($listing === '') {
                continue;
            }
            if (bandpromo_media_files_index_sync_file($root, $target, $listing) !== null) {
                $count++;
            }
            continue;
        }

        if (!in_array($target, ['illustrations', 'photos', 'video'], true)) {
            continue;
        }
        if (($asset['kind'] ?? '') !== 'visual') {
            continue;
        }

        $mediaType = strtolower(trim((string) ($asset['media_type'] ?? 'image')));
        $intake = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? ''));
        $mapped = bandpromo_asset_files_index_target_for_intake_bucket($intake);
        if ($mapped === '' && $mediaType === 'image') {
            $mapped = 'illustrations';
        }
        if ($mapped === '' && $mediaType === 'video') {
            $mapped = 'video';
        }
        if ($mapped !== $target) {
            continue;
        }

        $listing = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($listing === '') {
            $listing = basename(trim((string) ($asset['original_filename'] ?? '')));
        }
        if ($listing === '') {
            continue;
        }
        if (bandpromo_media_files_index_sync_file($root, $target, $listing) !== null) {
            $count++;
        }
    }

    return $count;
}

/**
 * Full rebuild for one Files target (Publish / migration / PRP import write path).
 * Registry masters first; original-dir scan only for unregistered orphans.
 */
function bandpromo_media_files_index_rebuild_target(string $root, string $target): int
{
    require_once __DIR__ . '/asset-registry.php';

    $dir = bandpromo_media_target_dir($target);
    $files = bandpromo_media_files_index_load();
    $prefix = $target . '/';
    foreach (array_keys($files) as $key) {
        if (str_starts_with((string) $key, $prefix)) {
            unset($files[$key]);
        }
    }
    bandpromo_media_files_index_save($files);

    $count = bandpromo_media_files_index_rebuild_registry_rows($root, $target);

    // Brand assets are a filter/role on Visual — never treat unified visual/original as Brand intake.
    if ($target === 'special') {
        $legacyDir = $root . '/media/special';
        if (is_dir($legacyDir)) {
            foreach (new DirectoryIterator($legacyDir) as $entry) {
                if ($entry->isDot() || $entry->isDir()) {
                    continue;
                }
                $name = $entry->getFilename();
                if (strcasecmp($name, 'desktop.ini') === 0) {
                    continue;
                }
                $registered = bandpromo_asset_lookup_by_original_filename($root, $name)
                    ?? bandpromo_asset_lookup_from_media_ref($root, $name);
                if (is_array($registered)) {
                    continue;
                }
                if (bandpromo_media_files_index_sync_file($root, $target, $name) !== null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    if ($dir !== null && is_dir($dir)) {
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot() || $entry->isDir()) {
                continue;
            }
            $name = $entry->getFilename();
            if (strcasecmp($name, 'desktop.ini') === 0) {
                continue;
            }
            $registered = bandpromo_asset_lookup_by_original_filename($root, $name)
                ?? bandpromo_asset_lookup_from_media_ref($root, $name);
            if (is_array($registered)) {
                // Already indexed via master_filename in the registry pass.
                continue;
            }
            if (bandpromo_media_files_index_sync_file($root, $target, $name) !== null) {
                $count++;
            }
        }
    }

    return $count;
}

function bandpromo_media_files_index_rebuild_all(string $root): array
{
    $counts = [];
    foreach (['audio', 'illustrations', 'photos', 'video', 'special', 'sfx'] as $target) {
        $counts[$target] = bandpromo_media_files_index_rebuild_target($root, $target);
    }

    return $counts;
}

/**
 * Pure read — never walks disk.
 */
function bandpromo_media_files_index_list(string $root, string $target): array
{
    $files = bandpromo_media_files_index_load();
    $prefix = $target . '/';
    $rows = [];
    foreach ($files as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!str_starts_with((string) $key, $prefix) && ($entry['target'] ?? '') !== $target) {
            continue;
        }
        $rows[] = $entry;
    }

    return $rows;
}

function bandpromo_media_files_index_ensure_target(string $root, string $target): void
{
    $rows = bandpromo_media_files_index_list($root, $target);
    if ($rows !== []) {
        return;
    }

    // One-time migration / empty index: rebuild from disk (write path).
    bandpromo_media_files_index_rebuild_target($root, $target);
}

/**
 * Visual pool = illustrations + photos + video intake buckets.
 * Ensures each bucket index, merges rows, annotates intake_bucket + media_type.
 *
 * @return list<array<string, mixed>>
 */
function bandpromo_media_files_index_list_visual(string $root): array
{
    $buckets = ['illustrations', 'photos', 'video'];
    $rows = [];

    foreach ($buckets as $bucket) {
        bandpromo_media_files_index_ensure_target($root, $bucket);
        foreach (bandpromo_media_files_index_list($root, $bucket) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry['intake_bucket'] = $bucket;
            $entry['media_type'] = $bucket === 'video' ? 'video' : 'image';
            $entry['target'] = $bucket;
            $rows[] = $entry;
        }
    }

    usort($rows, static function (array $left, array $right): int {
        return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $rows;
}

/**
 * Update listing pool_ready without re-probing filesize/mtime.
 */
function bandpromo_media_files_index_patch_pool_ready(
    string $root,
    string $target,
    string $filename,
    bool $poolReady
): void {
    $filename = basename(trim($filename));
    if ($filename === '') {
        return;
    }

    $files = bandpromo_media_files_index_load();
    $key = bandpromo_media_files_index_key($target, $filename);
    if (!isset($files[$key]) || !is_array($files[$key])) {
        bandpromo_media_files_index_sync_file($root, $target, $filename);

        return;
    }

    $files[$key]['pool_ready'] = $poolReady;
    if ($target === 'video') {
        $files[$key]['delivery_pending'] = !$poolReady;
        if (is_array($files[$key]['video_meta'] ?? null)) {
            $files[$key]['video_meta']['delivery_ready'] = $poolReady;
            $files[$key]['video_meta']['needs_delivery'] = !$poolReady;
        }
    }
    $files[$key]['indexed_at'] = gmdate('c');
    bandpromo_media_files_index_save($files);
}

function bandpromo_media_target_has_operator_uploads(string $root, string $target): bool
{
    bandpromo_media_files_index_ensure_target($root, $target);
    foreach (bandpromo_media_files_index_list($root, $target) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $filename = (string) ($entry['name'] ?? '');
        if ($filename !== '' && bandpromo_media_is_operator_upload_filename($root, $target, $filename)) {
            return true;
        }
    }

    return false;
}