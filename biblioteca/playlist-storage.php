<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/publish-status-helpers.php';
require_once __DIR__ . '/demo-catalog-state.php';
require_once __DIR__ . '/living-cover-helpers.php';
require_once __DIR__ . '/brand-storage.php';

const BANDPROMO_PLAYLIST_REGISTRY_VERSION = 1;
const BANDPROMO_PLAYLIST_DEMO_ID = 'bandpromo-demo';
const BANDPROMO_PLAYLIST_LEGACY_MAIN_ID = 'main';

/**
 * Listening package types for operator labeling (not access kind).
 *
 * @return list<string>
 */
function bandpromo_playlist_package_types(): array
{
    return ['single', 'ep', 'album', 'show', 'podcast', 'live', 'compilation', 'other'];
}

function bandpromo_playlist_normalize_package_type(string $type): string
{
    $type = strtolower(trim($type));
    if (!in_array($type, bandpromo_playlist_package_types(), true)) {
        return 'other';
    }

    return $type;
}

function bandpromo_playlist_package_type_label(string $type): string
{
    $labels = [
        'single' => 'Single',
        'ep' => 'EP',
        'album' => 'Album',
        'show' => 'Show',
        'podcast' => 'Podcast',
        'live' => 'Live',
        'compilation' => 'Compilation',
        'other' => 'Other',
    ];
    $type = bandpromo_playlist_normalize_package_type($type);

    return $labels[$type] ?? 'Other';
}

function bandpromo_playlist_normalize_play_order(string $order): string
{
    $order = strtolower(trim($order));

    return $order === 'reverse' ? 'reverse' : 'stored';
}

/**
 * Default play order for a package type (shows/podcasts play newest first).
 */
function bandpromo_playlist_default_play_order_for_package_type(string $type): string
{
    $type = bandpromo_playlist_normalize_package_type($type);

    return in_array($type, ['show', 'podcast'], true) ? 'reverse' : 'stored';
}

function bandpromo_playlist_configured_default_id(string $root): string
{
    require_once __DIR__ . '/config-loader.php';
    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        return '';
    }

    return bandpromo_playlist_normalize_id(
        (string) bandpromo_config_get_path($config, 'install.pointers.default_playlist_id', '')
    );
}

function bandpromo_playlist_set_default_id(string $root, string $playlistId): void
{
    require_once __DIR__ . '/config-loader.php';
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        throw new InvalidArgumentException('Playlist id is required.');
    }
    try {
        bandpromo_playlist_load_document($root, $playlistId);
    } catch (Throwable $throwable) {
        throw new InvalidArgumentException('Unknown playlist.');
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        throw new RuntimeException('Missing web-config.json');
    }
    bandpromo_config_set_path($config, 'install.pointers.default_playlist_id', $playlistId);
    if (!bandpromo_json_write_file($configPath, $config)) {
        throw new RuntimeException('Could not save default playlist pointer.');
    }
}

function bandpromo_playlist_clear_default_id(string $root): void
{
    require_once __DIR__ . '/config-loader.php';
    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        throw new RuntimeException('Missing web-config.json');
    }
    bandpromo_config_set_path($config, 'install.pointers.default_playlist_id', '');
    if (!bandpromo_json_write_file($configPath, $config)) {
        throw new RuntimeException('Could not clear default playlist pointer.');
    }
}

function bandpromo_playlist_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'playlists';
}

function bandpromo_playlist_registry_path(string $root): string
{
    return bandpromo_playlist_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_playlist_document_path(string $root, string $playlistId): string
{
    return bandpromo_playlist_storage_root($root) . DIRECTORY_SEPARATOR . bandpromo_playlist_normalize_id($playlistId) . '.json';
}

/**
 * Owning campaign for a playlist (explicit campaign_id, else inferred from track membership).
 * Empty and primary (orphan bucket) both trigger inference.
 */
function bandpromo_playlist_effective_campaign_id(string $root, string $playlistId): string
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        return '';
    }

    try {
        require_once __DIR__ . '/campaign-storage.php';
        require_once __DIR__ . '/campaign-ownership-helpers.php';
        $document = bandpromo_playlist_load_document($root, $playlistId);
        $campaignId = bandpromo_document_campaign_id($document);
        if (bandpromo_campaign_id_is_unowned($campaignId)) {
            $campaignId = bandpromo_campaign_normalize_id(
                bandpromo_campaign_ownership_infer_from_playlist_entries($root, $document)
            );
        }
        if (bandpromo_campaign_id_is_unowned($campaignId)) {
            return '';
        }

        return $campaignId;
    } catch (Throwable $throwable) {
        return '';
    }
}

/**
 * Player brand for a playlist: owning campaign’s brand, else install Base (never Active).
 */
function bandpromo_playlist_effective_brand_id(string $root, string $playlistId): string
{
    require_once __DIR__ . '/brand-storage.php';
    $campaignId = bandpromo_playlist_effective_campaign_id($root, $playlistId);
    if ($campaignId !== '') {
        return bandpromo_campaign_effective_brand_id($root, $campaignId);
    }

    return BANDPROMO_BRAND_DEFAULT_ID;
}

function bandpromo_playlist_validation_report_path(string $root): ?string
{
    $path = $root . '/data/validation/playlist-validation.json';

    return is_file($path) ? $path : null;
}

function bandpromo_playlist_decode_validation_report(string $root): ?array
{
    $path = bandpromo_playlist_validation_report_path($root);
    if ($path === null) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_playlist_cover_source_validation_map(string $root): array
{
    $decoded = bandpromo_playlist_decode_validation_report($root);
    if (!is_array($decoded) || !is_array($decoded['tracks'] ?? null)) {
        return [];
    }

    $map = [];
    foreach ($decoded['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $map[$file] = strtolower(trim((string) ($track['coverSource'] ?? '')));
    }

    return $map;
}

function bandpromo_playlist_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_playlist_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/playlists directory.');
    }
}

function bandpromo_playlist_normalize_id(string $playlistId): string
{
    $playlistId = strtolower(trim($playlistId));
    $playlistId = preg_replace('/[^a-z0-9-]+/', '-', $playlistId) ?? '';
    $playlistId = trim($playlistId, '-');

    return substr($playlistId, 0, 48);
}

function bandpromo_playlist_validate_date(string $value): bool
{
    if ($value === '') {
        return false;
    }

    if (preg_match('/^\d{4}$/', $value)) {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function bandpromo_playlist_route_slug(array $document, string $fallbackId = ''): string
{
    $slug = bandpromo_playlist_normalize_id((string) ($document['slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }

    return bandpromo_playlist_normalize_id($fallbackId);
}

function bandpromo_playlist_normalize_slug(string $slug, string $fallbackId = ''): string
{
    $normalized = bandpromo_playlist_normalize_id($slug);
    if ($normalized === '' && $fallbackId !== '') {
        $normalized = bandpromo_playlist_normalize_id($fallbackId);
    }
    if ($normalized === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $normalized)) {
        throw new InvalidArgumentException('Playlist slug must use lowercase letters, numbers, and hyphens.');
    }

    return $normalized;
}

function bandpromo_playlist_find_id_by_slug(string $root, string $slug, string $excludePlaylistId = ''): string
{
    $slug = bandpromo_playlist_normalize_id($slug);
    $excludePlaylistId = bandpromo_playlist_normalize_id($excludePlaylistId);
    if ($slug === '') {
        return '';
    }

    foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
        if ($playlistId === '' || ($excludePlaylistId !== '' && $playlistId === $excludePlaylistId)) {
            continue;
        }
        if ($playlistId === $slug) {
            return $playlistId;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (bandpromo_playlist_route_slug($document, $playlistId) === $slug) {
            return $playlistId;
        }
    }

    return '';
}

function bandpromo_playlist_assert_slug_available(string $root, string $slug, string $excludePlaylistId = ''): void
{
    $existingId = bandpromo_playlist_find_id_by_slug($root, $slug, $excludePlaylistId);
    if ($existingId !== '') {
        throw new InvalidArgumentException('That playlist slug is already in use.');
    }
}

function bandpromo_playlist_resolve_route_id(string $root, string $segment): string
{
    $segment = bandpromo_playlist_normalize_id(trim($segment));
    if ($segment === '') {
        return '';
    }

    return bandpromo_playlist_find_id_by_slug($root, $segment);
}

/**
 * Parse /play/{playlist}[/{track}] or /play/{playlist}/{release}/{track} from a request path.
 *
 * @return array{playlist: string, release: string, track: string}
 */
function bandpromo_playlist_route_from_path(string $requestUri): array
{
    $empty = ['playlist' => '', 'release' => '', 'track' => ''];
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $empty;
    }

    $parts = array_values(array_filter(explode('/', rawurldecode($path)), static function ($part) {
        return $part !== '';
    }));
    $playAt = array_search('play', $parts, true);
    $after = $playAt === false ? $parts : array_slice($parts, $playAt + 1);
    if (($after[0] ?? '') === 'index.php') {
        $after = array_slice($after, 1);
    }

    $count = count($after);
    if ($count < 1) {
        return $empty;
    }
    if ($count >= 3) {
        return [
            'playlist' => (string) $after[0],
            'release' => strtolower((string) $after[1]),
            'track' => strtolower((string) $after[2]),
        ];
    }
    if ($count === 2) {
        return [
            'playlist' => (string) $after[0],
            'release' => '',
            'track' => strtolower((string) $after[1]),
        ];
    }

    return [
        'playlist' => (string) $after[0],
        'release' => '',
        'track' => '',
    ];
}

/**
 * Query-string playlist/release/track win; otherwise parse the pretty path (php -S has no .htaccess).
 *
 * @return array{playlist: string, release: string, track: string}
 */
function bandpromo_playlist_route_from_request(): array
{
    $fromGet = [
        'playlist' => trim((string) ($_GET['playlist'] ?? '')),
        'release' => strtolower(trim((string) ($_GET['release'] ?? ''))),
        'track' => strtolower(trim((string) ($_GET['track'] ?? ''))),
    ];
    $fromPath = bandpromo_playlist_route_from_path((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $pathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''));
    if ($fromPath['playlist'] === '' && $pathInfo !== '') {
        $fromPath = bandpromo_playlist_route_from_path($pathInfo);
    }

    return [
        'playlist' => $fromGet['playlist'] !== '' ? $fromGet['playlist'] : $fromPath['playlist'],
        'release' => $fromGet['release'] !== '' ? $fromGet['release'] : $fromPath['release'],
        'track' => $fromGet['track'] !== '' ? $fromGet['track'] : $fromPath['track'],
    ];
}

function bandpromo_playlist_public_slug(string $root, string $playlistId): string
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        return '';
    }

    try {
        $document = bandpromo_playlist_load_document($root, $playlistId);

        return bandpromo_playlist_route_slug($document, $playlistId);
    } catch (Throwable $throwable) {
        return $playlistId;
    }
}

function bandpromo_playlist_publish_date_is_public(string $publishDate, bool $operatorBypass): bool
{
    return bandpromo_playlist_campaign_date_is_public($publishDate, $operatorBypass);
}

function bandpromo_playlist_is_player_visible(string $root, string $playlistId, bool $operatorBypass): bool
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        return false;
    }

    try {
        $document = bandpromo_playlist_load_document($root, $playlistId);
    } catch (Throwable $throwable) {
        return false;
    }

    if (!bandpromo_demo_campaign_container_is_visible(
        $root,
        (string) ($document['release_id'] ?? ''),
        $playlistId
    )) {
        return false;
    }

    if (bandpromo_playlist_document_is_empty($root, $playlistId)) {
        return false;
    }

    return bandpromo_playlist_publish_date_is_public(
        (string) ($document['publish_date'] ?? ''),
        $operatorBypass
    );
}

function bandpromo_playlist_prefer_cover_delivery_url(
    string $root,
    string $previewUrl,
    string $posterAssetId = ''
): string {
    require_once __DIR__ . '/media-delivery-helpers.php';

    $previewUrl = trim($previewUrl);
    $posterAssetId = trim($posterAssetId);

    // Asset-id / registry visual delivery (thumb first for coverflow).
    $refs = [];
    if ($posterAssetId !== '') {
        $refs[] = $posterAssetId;
    }
    if ($previewUrl !== '') {
        $path = parse_url($previewUrl, PHP_URL_PATH);
        $basename = basename(is_string($path) && $path !== '' ? $path : $previewUrl);
        if ($basename !== '') {
            $refs[] = $basename;
            $stem = pathinfo($basename, PATHINFO_FILENAME);
            if (is_string($stem) && $stem !== '' && $stem !== $basename) {
                $refs[] = $stem;
            }
        }
    }

    $refs = array_values(array_unique(array_filter($refs, static fn($ref): bool => is_string($ref) && $ref !== '')));
    foreach ($refs as $ref) {
        foreach (['thumb', 'card'] as $variant) {
            $url = bandpromo_visual_resolve_url($root, $ref, $variant, '', false);
            if ($url !== '' && str_starts_with($url, '/media/visual/delivery/')) {
                return $url;
            }
        }
    }

    if ($previewUrl !== '' && str_starts_with($previewUrl, '/media/visual/delivery/')) {
        $absolute = $root . str_replace('/', DIRECTORY_SEPARATOR, parse_url($previewUrl, PHP_URL_PATH) ?: $previewUrl);
        if (is_file($absolute)) {
            return $previewUrl;
        }
    }

    return '';
}

/**
 * Coverflow/catalogue art: playlist poster, then owning release poster, then first track cover.
 */
function bandpromo_playlist_resolve_catalog_cover_url(string $root, array $entry): string
{
    $url = bandpromo_playlist_prefer_cover_delivery_url(
        $root,
        (string) ($entry['poster_preview_url'] ?? ''),
        (string) ($entry['poster_asset_id'] ?? '')
    );
    if ($url !== '') {
        return $url;
    }

    $releaseId = trim((string) ($entry['release_id'] ?? ''));
    if ($releaseId !== '') {
        require_once __DIR__ . '/campaign-storage.php';
        try {
            $release = bandpromo_campaign_load_document($root, $releaseId);
            $poster = trim((string) ($release['poster_asset_id'] ?? ''));
            if ($poster !== '') {
                $preview = bandpromo_campaign_resolve_poster_preview_url($root, $poster);
                $url = bandpromo_playlist_prefer_cover_delivery_url($root, $preview, $poster);
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (Throwable $throwable) {
            // Fall through to track cover.
        }
    }

    $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
    if ($playlistId === '') {
        return '';
    }

    try {
        $document = bandpromo_playlist_load_document($root, $playlistId);
    } catch (Throwable $throwable) {
        return '';
    }

    $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $cover = basename(trim((string) ($track['cover'] ?? '')));
        if ($cover === '') {
            continue;
        }
        $url = bandpromo_playlist_prefer_cover_delivery_url($root, '', $cover);
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function bandpromo_playlist_player_catalog_entries(string $root, bool $operatorBypass = false): array
{
    $entries = [];
    foreach (bandpromo_playlist_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $owner = '';
        try {
            $document = bandpromo_playlist_load_document($root, $id);
            $owner = (string) ($document['release_id'] ?? '');
        } catch (Throwable $throwable) {
            continue;
        }
        if (!bandpromo_demo_campaign_container_is_visible($root, $owner, $id)) {
            continue;
        }
        if (!bandpromo_playlist_publish_date_is_public((string) ($entry['publish_date'] ?? ''), $operatorBypass)) {
            continue;
        }
        if (bandpromo_playlist_document_is_empty($root, $id)) {
            continue;
        }
        $entries[] = [
            'id' => $id,
            'title' => (string) ($entry['title'] ?? $id),
            'slug' => bandpromo_playlist_public_slug($root, $id),
            'cover' => bandpromo_playlist_resolve_catalog_cover_url($root, $entry),
        ];
    }

    return $entries;
}

function bandpromo_playlist_normalize_entry(array $entry): ?array
{
    $masterFile = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
    if ($masterFile === '' || strpbrk($masterFile, '/\\') !== false) {
        return null;
    }

    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if ($assetId !== '' && !bandpromo_asset_is_asset_id($assetId)) {
        $assetId = '';
    }

    $campaignId = '';
    if (function_exists('bandpromo_document_campaign_id')) {
        $campaignId = bandpromo_document_campaign_id($entry);
    } else {
        $campaignId = trim((string) ($entry['campaign_id'] ?? $entry['release_id'] ?? ''));
    }

    $out = [
        'master_file' => $masterFile,
        'asset_id' => $assetId,
        'campaign_id' => $campaignId,
    ];

    return $out;
}

function bandpromo_playlist_normalize_stored_track(array $track): ?array
{
    $file = basename(trim((string) ($track['file'] ?? '')));
    if ($file === '' || strpbrk($file, '/\\') !== false) {
        return null;
    }

    $title = trim((string) ($track['title'] ?? ''));
    if ($title === '') {
        $title = $file;
    }

    $normalized = [
        'file' => $file,
        'title' => $title,
        'artist' => trim((string) ($track['artist'] ?? '')),
        'album' => trim((string) ($track['album'] ?? '')),
        'duration' => max(0, (int) ($track['duration'] ?? 0)),
        'lyrics' => (string) ($track['lyrics'] ?? ''),
        'text_role' => bandpromo_asset_normalize_text_role((string) ($track['text_role'] ?? 'lyrics')),
        'notes_label' => bandpromo_asset_normalize_notes_label((string) ($track['notes_label'] ?? '')),
        'description' => (string) ($track['description'] ?? ''),
        'cover' => bandpromo_asset_normalize_media_ref((string) ($track['cover'] ?? '')),
        'living_cover' => bandpromo_living_cover_normalize_video_filename((string) ($track['living_cover'] ?? '')),
        'asset_id' => trim((string) ($track['asset_id'] ?? '')),
        'release_id' => trim((string) ($track['release_id'] ?? '')),
        'release_slug' => trim((string) ($track['release_slug'] ?? '')),
        'brand_id' => trim((string) ($track['brand_id'] ?? '')),
        'track_slug' => trim((string) ($track['track_slug'] ?? '')),
        'delivery_ready' => !empty($track['delivery_ready']),
        'delivery_mode' => trim((string) ($track['delivery_mode'] ?? '')),
        'playable' => !empty($track['playable']),
        'lock_reason' => trim((string) ($track['lock_reason'] ?? '')),
        'animated_cover' => trim((string) ($track['animated_cover'] ?? '')),
        'cover_url' => trim((string) ($track['cover_url'] ?? '')),
    ];

    if (($track['embargoed'] ?? false) === true) {
        $normalized['embargoed'] = true;
    }

    return $normalized;
}

function bandpromo_playlist_normalize_stored_tracks(array $tracks): array
{
    $normalized = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $entry = bandpromo_playlist_normalize_stored_track($track);
        if ($entry !== null) {
            $normalized[] = $entry;
        }
    }

    return $normalized;
}

/**
 * Refresh stored brand_styles snippets in every playlist that references $brandId.
 * Keeps published payloads aligned with Content → Branding without a full rematerialize.
 *
 * @return array{updated:list<string>,skipped:list<string>}
 */
function bandpromo_playlist_refresh_brand_styles_for_brand(string $root, string $brandId): array
{
    require_once __DIR__ . '/brand-storage.php';

    $brandId = bandpromo_brand_canonical_id($brandId);
    $updated = [];
    $skipped = [];
    if ($brandId === '') {
        return ['updated' => $updated, 'skipped' => $skipped];
    }

    $fresh = bandpromo_brand_player_styles_for_ids($root, [$brandId]);
    if ($fresh === [] || !isset($fresh[$brandId])) {
        return ['updated' => $updated, 'skipped' => $skipped];
    }

    try {
        bandpromo_playlist_ensure_seeded($root);
    } catch (Throwable $throwable) {
        return ['updated' => $updated, 'skipped' => $skipped];
    }

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            $skipped[] = $playlistId;
            continue;
        }

        $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
        $playlistBrandId = bandpromo_playlist_effective_brand_id($root, $playlistId);
        $usesBrand = $playlistBrandId === $brandId;
        if (!$usesBrand && is_array($document['brand_styles'] ?? null) && isset($document['brand_styles'][$brandId])) {
            $usesBrand = true;
        }
        if (!$usesBrand) {
            continue;
        }

        $document['brand_styles'] = bandpromo_playlist_normalize_stored_brand_styles($fresh);
        $document['player_built_at'] = gmdate('c');
        try {
            bandpromo_playlist_write_document($root, $document);
            $updated[] = $playlistId;
        } catch (Throwable $throwable) {
            $skipped[] = $playlistId;
        }
    }

    return ['updated' => $updated, 'skipped' => $skipped];
}

function bandpromo_playlist_normalize_stored_brand_styles(array $brandStyles): array
{
    $normalized = [];
    foreach ($brandStyles as $brandId => $style) {
        if (!is_array($style)) {
            continue;
        }
        $canonicalId = bandpromo_brand_canonical_id((string) $brandId);
        if ($canonicalId === '') {
            continue;
        }
        $cssVariables = is_array($style['css_variables'] ?? null) ? $style['css_variables'] : [];
        $rawAssets = is_array($style['assets'] ?? null) ? $style['assets'] : [];
        $assets = [
            'logo' => trim((string) ($rawAssets['logo'] ?? '')),
            'background_image' => trim((string) ($rawAssets['background_image'] ?? '')),
            'background_video' => trim((string) ($rawAssets['background_video'] ?? '')),
        ];
        $normalized[$canonicalId] = [
            'id' => $canonicalId,
            'title' => trim((string) ($style['title'] ?? $canonicalId)),
            'css_variables' => $cssVariables,
            'assets' => $assets,
        ];
    }

    return $normalized;
}

function bandpromo_playlist_normalize_stored_delivery_summary(array $summary): array
{
    return [
        'pending_count' => max(0, (int) ($summary['pending_count'] ?? 0)),
        'demo_original_count' => max(0, (int) ($summary['demo_original_count'] ?? 0)),
        'publish_required' => !empty($summary['publish_required']),
    ];
}

function bandpromo_playlist_clear_player_payload_fields(array $document): array
{
    unset(
        $document['tracks'],
        $document['brand_styles'],
        $document['delivery_summary'],
        $document['player_built_at']
    );

    return $document;
}

/**
 * Playlist ids whose entries reference this audio master (or its original upload name).
 *
 * @return list<string>
 */
function bandpromo_playlist_ids_containing_master(string $root, string $masterFile): array
{
    $masterFile = basename(trim($masterFile));
    if ($masterFile === '') {
        return [];
    }

    $ids = [];
    foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
        if (!is_array($registryEntry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        foreach ($document['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (bandpromo_playlist_entry_matches_audio_filename($root, $entry, $masterFile)) {
                $ids[] = $playlistId;
                break;
            }
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Rebuild player payloads for playlists that include this master without wiping first.
 * Last-good tracks stay readable until each write completes.
 *
 * @return array{published: list<array<string, mixed>>, errors: list<array<string, string>>}
 */
function bandpromo_playlist_republish_player_payloads_for_master(string $root, string $masterFile): array
{
    $published = [];
    $errors = [];

    foreach (bandpromo_playlist_ids_containing_master($root, $masterFile) as $playlistId) {
        try {
            $published[] = bandpromo_playlist_publish_player_payload($root, $playlistId);
        } catch (Throwable $throwable) {
            $errors[] = [
                'playlist_id' => $playlistId,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    return [
        'published' => $published,
        'errors' => $errors,
    ];
}

function bandpromo_playlist_invalidate_player_payloads_for_master(string $root, string $masterFile): int
{
    $masterFile = basename(trim($masterFile));
    if ($masterFile === '') {
        return 0;
    }

    $cleared = 0;
    foreach (bandpromo_playlist_ids_containing_master($root, $masterFile) as $playlistId) {
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        if (!isset($document['tracks']) && !isset($document['player_built_at'])) {
            continue;
        }

        $document = bandpromo_playlist_clear_player_payload_fields($document);
        bandpromo_playlist_write_document($root, $document);
        $cleared++;
    }

    return $cleared;
}

function bandpromo_playlist_normalize_document(array $input, ?string $expectedId = null, ?string $root = null): array
{
    $id = bandpromo_playlist_normalize_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid playlist id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $kind = strtolower(trim((string) ($input['kind'] ?? 'system')));
    if (!in_array($kind, ['system', 'user'], true)) {
        $kind = 'system';
    }

    $publishDate = trim((string) ($input['publish_date'] ?? ''));
    if ($publishDate === '') {
        $publishDate = gmdate('Y-m-d');
    }
    if (!bandpromo_playlist_validate_date($publishDate)) {
        throw new InvalidArgumentException('Playlist publish_date must use YYYY or YYYY-MM-DD.');
    }

    $slug = bandpromo_playlist_normalize_slug((string) ($input['slug'] ?? ''), $id);

    $entries = [];
    if (isset($input['entries']) && is_array($input['entries'])) {
        foreach ($input['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = bandpromo_playlist_normalize_entry($entry);
            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }
    }

    $campaignId = bandpromo_document_campaign_id($input);
    if ($campaignId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $campaignId)) {
        $campaignId = '';
    }
    if (bandpromo_campaign_id_is_unowned($campaignId) && $root !== null && $entries !== []) {
        require_once __DIR__ . '/campaign-ownership-helpers.php';
        $inferred = bandpromo_campaign_ownership_infer_from_playlist_entries($root, [
            'entries' => $entries,
        ]);
        if ($inferred !== '' && preg_match('/^[a-z][a-z0-9-]{0,47}$/', $inferred)
            && !bandpromo_campaign_id_is_unowned($inferred)
        ) {
            $campaignId = $inferred;
        } else {
            $campaignId = '';
        }
    }

    $packageType = array_key_exists('package_type', $input)
        ? bandpromo_playlist_normalize_package_type((string) $input['package_type'])
        : 'other';
    $playOrder = array_key_exists('play_order', $input)
        ? bandpromo_playlist_normalize_play_order((string) $input['play_order'])
        : bandpromo_playlist_default_play_order_for_package_type($packageType);

    $document = [
        'version' => BANDPROMO_PLAYLIST_REGISTRY_VERSION,
        'id' => $id,
        'slug' => $slug,
        'title' => $title,
        'kind' => $kind,
        'package_type' => $packageType,
        'play_order' => $playOrder,
        'publish_date' => $publishDate,
        'campaign_id' => $campaignId,
        'description' => bandpromo_campaign_normalize_text_field($input['description'] ?? '', 4000),
        'short_description' => bandpromo_campaign_normalize_text_field($input['short_description'] ?? '', 300),
        'poster_asset_id' => $root !== null
            ? bandpromo_campaign_normalize_poster_asset_id($root, $input['poster_asset_id'] ?? '')
            : trim((string) ($input['poster_asset_id'] ?? '')),
        'entries' => $entries,
    ];

    if (array_key_exists('tracks', $input) && is_array($input['tracks'])) {
        $document['tracks'] = bandpromo_playlist_normalize_stored_tracks($input['tracks']);
    }
    if (array_key_exists('brand_styles', $input) && is_array($input['brand_styles'])) {
        $document['brand_styles'] = bandpromo_playlist_normalize_stored_brand_styles($input['brand_styles']);
    }
    if (array_key_exists('delivery_summary', $input) && is_array($input['delivery_summary'])) {
        $document['delivery_summary'] = bandpromo_playlist_normalize_stored_delivery_summary($input['delivery_summary']);
    }
    if (array_key_exists('player_built_at', $input)) {
        $builtAt = trim((string) $input['player_built_at']);
        if ($builtAt !== '') {
            $document['player_built_at'] = $builtAt;
        }
    }

    return $document;
}

function bandpromo_playlist_default_registry(): array
{
    return [
        'version' => BANDPROMO_PLAYLIST_REGISTRY_VERSION,
        'playlists' => [
            [
                'id' => BANDPROMO_PLAYLIST_DEMO_ID,
                'title' => 'bandPromo demo',
                'kind' => 'system',
                'publish_date' => gmdate('Y-m-d'),
                'sort_order' => 10,
            ],
        ],
    ];
}

function bandpromo_playlist_new_document(string $id, string $title): array
{
    $id = bandpromo_playlist_normalize_id($id);
    if ($id === '') {
        throw new InvalidArgumentException('Playlist id is required.');
    }

    return [
        'version' => BANDPROMO_PLAYLIST_REGISTRY_VERSION,
        'id' => $id,
        'slug' => $id,
        'title' => trim($title) !== '' ? trim($title) : ucfirst(str_replace('-', ' ', $id)),
        'kind' => 'system',
        'package_type' => 'other',
        'play_order' => 'stored',
        'publish_date' => gmdate('Y-m-d'),
        'release_id' => '',
        'description' => '',
        'short_description' => '',
        'poster_asset_id' => '',
        'entries' => [],
    ];
}

function bandpromo_playlist_normalize_registry(array $input): array
{
    $playlists = [];
    $seen = [];
    if (isset($input['playlists']) && is_array($input['playlists'])) {
        foreach ($input['playlists'] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $publishDate = trim((string) ($entry['publish_date'] ?? gmdate('Y-m-d')));
            if (!bandpromo_playlist_validate_date($publishDate)) {
                $publishDate = gmdate('Y-m-d');
            }
            $playlists[] = [
                'id' => $id,
                'title' => trim((string) ($entry['title'] ?? ucfirst(str_replace('-', ' ', $id)))),
                'kind' => strtolower((string) ($entry['kind'] ?? 'system')) === 'user' ? 'user' : 'system',
                'publish_date' => $publishDate,
                'sort_order' => (int) ($entry['sort_order'] ?? ($index + 1) * 10),
            ];
        }
    }

    if ($playlists === []) {
        return bandpromo_playlist_default_registry();
    }

    usort($playlists, static fn(array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

    return [
        'version' => BANDPROMO_PLAYLIST_REGISTRY_VERSION,
        'playlists' => $playlists,
    ];
}

function bandpromo_playlist_write_registry(string $root, array $registry): void
{
    bandpromo_playlist_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_playlist_registry_path($root), bandpromo_playlist_normalize_registry($registry))) {
        throw new RuntimeException('Could not write playlist registry.');
    }
}

function bandpromo_playlist_write_document(string $root, array $document): void
{
    bandpromo_playlist_registry_ensure_dir($root);
    $normalized = bandpromo_playlist_normalize_document($document, null, $root);
    if (!bandpromo_json_write_file(bandpromo_playlist_document_path($root, $normalized['id']), $normalized)) {
        throw new RuntimeException('Could not write playlist document.');
    }
}

function bandpromo_playlist_load_registry(string $root): array
{
    bandpromo_playlist_ensure_seeded($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_playlist_registry_path($root));
    if ($decoded === null) {
        throw new RuntimeException('Invalid playlist registry file.');
    }

    return bandpromo_playlist_normalize_registry($decoded);
}

function bandpromo_playlist_load_document(string $root, string $playlistId): array
{
    bandpromo_playlist_ensure_seeded($root);
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    $path = bandpromo_playlist_document_path($root, $playlistId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing playlist document: data/playlists/' . $playlistId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid playlist document: data/playlists/' . $playlistId . '.json');
    }

    return bandpromo_playlist_normalize_document($decoded, $playlistId, $root);
}

function bandpromo_playlist_registry_entries(string $root): array
{
    return bandpromo_playlist_load_registry($root)['playlists'] ?? [];
}

function bandpromo_playlist_system_entries(string $root): array
{
    return array_values(array_filter(
        bandpromo_playlist_registry_entries($root),
        static fn(array $entry): bool => ($entry['kind'] ?? 'system') === 'system'
    ));
}

function bandpromo_playlist_publish_date_sort_value(string $publishDate): int
{
    $publishDate = trim($publishDate);
    if (preg_match('/^\d{4}$/', $publishDate)) {
        return (int) ($publishDate . '0101');
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $publishDate);

    return $date instanceof DateTimeImmutable ? (int) $date->format('Ymd') : 0;
}

function bandpromo_playlist_first_visible_non_demo_id(string $root): string
{
    $now = (int) gmdate('Ymd');
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($id === '' || bandpromo_demo_catalog_is_demo_entity_id($id)) {
            continue;
        }
        $publishValue = bandpromo_playlist_publish_date_sort_value((string) ($entry['publish_date'] ?? ''));
        if ($publishValue <= 0 || $publishValue > $now) {
            continue;
        }
        if (bandpromo_playlist_document_is_empty($root, $id)) {
            continue;
        }

        return $id;
    }

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($id === '' || bandpromo_demo_catalog_is_demo_entity_id($id)) {
            continue;
        }
        if (bandpromo_playlist_document_is_empty($root, $id)) {
            continue;
        }

        return $id;
    }

    return '';
}

function bandpromo_playlist_operator_default_candidate(string $root): string
{
    $bestId = '';
    $bestValue = -1;
    $bestTracks = -1;

    foreach (bandpromo_playlist_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['ownership'] ?? '') !== 'operator') {
            continue;
        }
        $tracks = (int) ($entry['track_count'] ?? 0);
        if ($tracks <= 0) {
            continue;
        }
        $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $value = bandpromo_playlist_publish_date_sort_value((string) ($entry['publish_date'] ?? ''));
        if ($value > $bestValue || ($value === $bestValue && $tracks > $bestTracks)) {
            $bestValue = $value;
            $bestTracks = $tracks;
            $bestId = $id;
        }
    }

    return $bestId;
}

function bandpromo_playlist_default_active_id(string $root): string
{
    $configured = bandpromo_playlist_configured_default_id($root);
    if ($configured !== '' && bandpromo_playlist_is_player_visible($root, $configured, false)) {
        return $configured;
    }

    $now = (int) gmdate('Ymd');
    $candidates = [];
    foreach (bandpromo_playlist_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $publishValue = bandpromo_playlist_publish_date_sort_value((string) ($entry['publish_date'] ?? ''));
        if ($publishValue <= 0 || $publishValue > $now) {
            continue;
        }
        $id = trim((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $owner = '';
        try {
            $document = bandpromo_playlist_load_document($root, $id);
            $owner = (string) ($document['release_id'] ?? '');
        } catch (Throwable $throwable) {
            continue;
        }
        if (!bandpromo_demo_campaign_container_is_visible($root, $owner, $id)) {
            continue;
        }
        if ((int) ($entry['track_count'] ?? 0) <= 0) {
            continue;
        }
        $candidates[] = [
            'id' => $id,
            'publish_value' => $publishValue,
        ];
    }

    if ($candidates !== []) {
        usort($candidates, static fn(array $a, array $b): int => $b['publish_value'] <=> $a['publish_value']);
        $picked = (string) ($candidates[0]['id'] ?? '');
        if ($picked === BANDPROMO_PLAYLIST_DEMO_ID) {
            $operatorId = bandpromo_playlist_operator_default_candidate($root);
            if ($operatorId !== '') {
                return $operatorId;
            }
        }
        if ($picked !== '') {
            return $picked;
        }
    }

    $operatorId = bandpromo_playlist_operator_default_candidate($root);
    if ($operatorId !== '') {
        return $operatorId;
    }

    if (bandpromo_demo_catalog_is_visible($root)) {
        return BANDPROMO_PLAYLIST_DEMO_ID;
    }

    return bandpromo_playlist_first_visible_non_demo_id($root);
}

function bandpromo_playlist_resolve_id(string $root, string $requestedId = ''): string
{
    $requestedId = bandpromo_playlist_normalize_id($requestedId);

    return $requestedId !== '' ? $requestedId : bandpromo_playlist_default_active_id($root);
}

function bandpromo_playlist_seed_from_template(string $root): void
{
    $registry = bandpromo_playlist_default_registry();
    $templateRegistry = $root . '/biblioteca/templates/playlists.registry.template.json';
    if (is_file($templateRegistry)) {
        $decoded = bandpromo_json_read_array_file($templateRegistry);
        if ($decoded !== null) {
            $registry = bandpromo_playlist_normalize_registry($decoded);
        }
    }

    bandpromo_playlist_write_registry($root, $registry);

    foreach ($registry['playlists'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        $path = bandpromo_playlist_document_path($root, $playlistId);
        if (is_file($path)) {
            continue;
        }
        bandpromo_playlist_write_document(
            $root,
            bandpromo_playlist_new_document($playlistId, (string) ($entry['title'] ?? $playlistId))
        );
    }
}

function bandpromo_playlist_ensure_seeded(string $root): void
{
    static $running = [];
    static $completed = [];
    if (!empty($completed[$root])) {
        return;
    }
    if (!empty($running[$root])) {
        return;
    }
    $running[$root] = true;

    try {
        bandpromo_campaign_ensure_seeded($root);
        bandpromo_playlist_registry_ensure_dir($root);

        if (!is_file(bandpromo_playlist_registry_path($root))) {
            bandpromo_playlist_seed_from_template($root);
        }

        bandpromo_playlist_remove_legacy_main_playlist($root);
        bandpromo_playlist_ensure_demo_playlist($root);
        $completed[$root] = true;
    } finally {
        unset($running[$root]);
    }
}

function bandpromo_playlist_remove_legacy_main_playlist(string $root): void
{
    $legacyId = BANDPROMO_PLAYLIST_LEGACY_MAIN_ID;
    $registryPath = bandpromo_playlist_registry_path($root);
    if (!is_file($registryPath)) {
        return;
    }

    $decoded = bandpromo_json_read_array_file($registryPath);
    if ($decoded === null) {
        return;
    }

    $registry = bandpromo_playlist_normalize_registry($decoded);
    $hadLegacyRegistry = false;
    foreach ($registry['playlists'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['id'] ?? '') === $legacyId) {
            $hadLegacyRegistry = true;
            break;
        }
    }

    $legacyPath = bandpromo_playlist_document_path($root, $legacyId);
    $legacyExists = is_file($legacyPath);
    if (!$hadLegacyRegistry && !$legacyExists) {
        return;
    }

    if ($legacyExists) {
        $legacyDoc = bandpromo_json_read_array_file($legacyPath);
        $legacyEntries = is_array($legacyDoc) && is_array($legacyDoc['entries'] ?? null)
            ? $legacyDoc['entries']
            : [];
        if ($legacyEntries !== [] && bandpromo_playlist_document_is_empty($root, BANDPROMO_PLAYLIST_DEMO_ID)) {
            try {
                $document = bandpromo_playlist_load_document($root, BANDPROMO_PLAYLIST_DEMO_ID);
            } catch (Throwable $throwable) {
                $document = bandpromo_playlist_new_document(BANDPROMO_PLAYLIST_DEMO_ID, 'bandPromo demo');
            }
            $document['entries'] = $legacyEntries;
            bandpromo_playlist_write_document($root, $document);
        }
    }

    if ($hadLegacyRegistry) {
        $registry['playlists'] = array_values(array_filter(
            $registry['playlists'],
            static fn($entry): bool => is_array($entry) && (string) ($entry['id'] ?? '') !== $legacyId
        ));
        bandpromo_playlist_write_registry($root, $registry);
    }

    if ($legacyExists) {
        @unlink($legacyPath);
    }
}

function bandpromo_playlist_document_is_empty(string $root, string $playlistId): bool
{
    try {
        $document = bandpromo_playlist_load_document($root, $playlistId);
    } catch (Throwable $throwable) {
        return true;
    }

    return bandpromo_playlist_entry_master_files($document) === [];
}

function bandpromo_playlist_ensure_demo_playlist(string $root): void
{
    // Demo playlist arrives via PRP import only — do not seed or sync tracks here.
    require_once __DIR__ . '/campaign-storage.php';
    bandpromo_campaign_enforce_platform_demo_lock($root);
}

/**
 * @deprecated No-op. Demo playlist content comes from PRP, not a heal/sync path.
 */
function bandpromo_playlist_sync_demo_playlist(string $root): void
{
    unset($root);
}

function bandpromo_playlist_resolve_source_audio_path(string $root, string $filename): ?string
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return null;
    }

    $resolved = bandpromo_resolve_playable_audio_file($root, $filename, 'master');
    return $resolved !== null ? $resolved['path'] : null;
}

function bandpromo_playlist_build_php_track_entry(string $root, string $filename): ?array
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return null;
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset === null || ($asset['kind'] ?? '') !== 'audio') {
        return null;
    }

    $canonical = basename(trim((string) ($asset['master_filename'] ?? $filename)));
    $display = bandpromo_asset_read_audio_display($asset);
    $stem = pathinfo($canonical, PATHINFO_FILENAME);
    $fallbackTitle = ucwords(str_replace(['_', '-'], ' ', preg_replace('/^bandPromo_/', '', $stem) ?? $stem));
    $title = trim((string) ($display['title'] ?? ''));
    if ($title === '') {
        $title = $fallbackTitle !== '' ? $fallbackTitle : $canonical;
    }

    return [
        'file' => $canonical,
        'title' => $title,
        'artist' => trim((string) ($display['artist'] ?? '')),
        'album' => trim((string) ($display['album'] ?? '')),
        'duration' => max(0, (int) ($display['duration'] ?? 0)),
        'lyrics' => (string) ($display['lyrics'] ?? ''),
        'text_role' => bandpromo_asset_normalize_text_role((string) ($display['text_role'] ?? 'lyrics')),
        'notes_label' => bandpromo_asset_normalize_notes_label((string) ($display['notes_label'] ?? '')),
        'description' => trim((string) ($display['comment'] ?? '')),
        'cover' => bandpromo_asset_canonical_id_from_media_ref($root, (string) ($display['cover'] ?? '')),
        'living_cover' => bandpromo_living_cover_canonical_id($root, (string) ($display['living_cover'] ?? '')),
    ];
}

function bandpromo_playlist_materialize_entries(array $filenames): array
{
    $requested = array_values(array_filter($filenames, static function ($entry) {
        return is_string($entry) && $entry !== '' && strpbrk($entry, '/\\') === false;
    }));
    if ($requested === []) {
        return [
            'entries' => [],
            'missing' => [],
            'error' => '',
        ];
    }

    $result = bandpromo_run_light_json_task('scripts/playlistTrackEntries.py', [
        'filenames' => $requested,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    $entries = [];
    $missing = $requested;
    $taskError = '';

    if ($result['ok'] && is_array($data) && !empty($data['ok'])) {
        $missing = [];
        foreach (($data['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $file = trim((string) ($entry['file'] ?? ''));
            if ($file !== '') {
                $entries[$file] = $entry;
            }
        }

        foreach (($data['missing'] ?? []) as $entry) {
            $file = trim((string) $entry);
            if ($file !== '') {
                $missing[] = $file;
            }
        }
    } else {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));
        $taskError = $error !== '' ? $error : ($output !== '' ? $output : (string) ($result['error'] ?? ''));
    }

    $root = dirname(__DIR__);
    foreach ($missing as $index => $filename) {
        $phpEntry = bandpromo_playlist_build_php_track_entry($root, $filename);
        if ($phpEntry === null) {
            continue;
        }
        $entries[$filename] = $phpEntry;
        unset($missing[$index]);
    }
    $missing = array_values($missing);

    if ($entries === [] && $missing !== []) {
        return [
            'entries' => [],
            'missing' => $missing,
            'error' => $taskError !== '' ? $taskError : 'Could not materialize playlist entries from source audio',
        ];
    }

    return [
        'entries' => $entries,
        'missing' => $missing,
        'error' => '',
    ];
}

function bandpromo_playlist_build_track_list(string $root, array $document, array $builtTracks = []): array
{
    // Read path: registry/display (+ optional caller-supplied map). Never spawn Python here.
    // Publish uses bandpromo_playlist_materialize_entries via bandpromo_playlist_materialize_for_player.
    $tracks = [];
    foreach ($document['entries'] as $entry) {
        $masterFile = (string) ($entry['master_file'] ?? '');
        if ($masterFile === '') {
            continue;
        }
        if (isset($builtTracks[$masterFile]) && is_array($builtTracks[$masterFile])) {
            $built = $builtTracks[$masterFile];
            // Tag materialization is preferred when present; fill empty living_cover from registry.
            $builtLiving = bandpromo_living_cover_canonical_id($root, (string) ($built['living_cover'] ?? ''));
            if ($builtLiving === '') {
                $phpEntry = bandpromo_playlist_build_php_track_entry($root, $masterFile);
                $registryLiving = is_array($phpEntry)
                    ? bandpromo_living_cover_canonical_id($root, (string) ($phpEntry['living_cover'] ?? ''))
                    : '';
                if ($registryLiving !== '') {
                    $built['living_cover'] = $registryLiving;
                }
            } else {
                $built['living_cover'] = $builtLiving;
            }
            $tracks[] = $built;
            continue;
        }
        $phpEntry = bandpromo_playlist_build_php_track_entry($root, $masterFile);
        if ($phpEntry !== null) {
            $tracks[] = $phpEntry;
        }
    }

    return $tracks;
}

function bandpromo_playlist_save_order(string $root, string $playlistId, array $masterFiles): array
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    $masterFiles = array_values(array_filter($masterFiles, static function ($masterFile): bool {
        return is_string($masterFile) && trim($masterFile) !== '';
    }));
    if ($masterFiles === []) {
        throw new InvalidArgumentException('A playlist must include at least one track.');
    }

    $document = bandpromo_playlist_load_document($root, $playlistId);

    $entries = [];
    $skipped = [];
    $tracks = [];
    foreach ($masterFiles as $masterFile) {
        $masterFile = basename((string) $masterFile);
        if ($masterFile === '') {
            continue;
        }
        $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile)
            ?? bandpromo_asset_lookup_by_original_filename($root, $masterFile);
        if ($asset === null || ($asset['kind'] ?? '') !== 'audio') {
            $skipped[] = $masterFile;
            continue;
        }
        $canonicalMaster = basename(trim((string) ($asset['master_filename'] ?? $masterFile)));
        if ($canonicalMaster === '') {
            $skipped[] = $masterFile;
            continue;
        }
        $entries[] = [
            'master_file' => $canonicalMaster,
            'asset_id' => (string) ($asset['id'] ?? ''),
            'release_id' => (string) ($asset['release_id'] ?? ''),
        ];
        $phpEntry = bandpromo_playlist_build_php_track_entry($root, $canonicalMaster);
        if ($phpEntry !== null) {
            $tracks[] = $phpEntry;
        }
    }

    if ($entries === []) {
        throw new InvalidArgumentException('A playlist must include at least one valid track.');
    }

    $document['entries'] = $entries;
    $document = bandpromo_playlist_clear_player_payload_fields($document);
    bandpromo_playlist_write_document($root, $document);

    return [
        'tracks' => $tracks,
        'skipped' => $skipped,
        'count' => count($tracks),
    ];
}

function bandpromo_playlist_entry_matches_audio_filename(string $root, array $entry, string $filename): bool
{
    if (!is_array($entry)) {
        return false;
    }

    $filename = basename(trim($filename));
    if ($filename === '') {
        return false;
    }

    $masterFile = basename(trim((string) ($entry['master_file'] ?? '')));
    if ($masterFile !== '' && $masterFile === $filename) {
        return true;
    }

    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if ($assetId === '') {
        return false;
    }

    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    if ($asset === null) {
        return false;
    }

    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    $masterFromAsset = basename(trim((string) ($asset['master_filename'] ?? '')));

    return $filename === $originalFilename || $filename === $masterFromAsset;
}

function bandpromo_playlist_collect_audio_references(string $root, string $filename): array
{
    $references = [];
    bandpromo_playlist_ensure_seeded($root);

    foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
        if (!is_array($registryEntry)) {
            continue;
        }

        $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        $playlistTitle = trim((string) ($registryEntry['title'] ?? $playlistId));
        foreach ($document['entries'] ?? [] as $entry) {
            if (!bandpromo_playlist_entry_matches_audio_filename($root, $entry, $filename)) {
                continue;
            }

            $references[] = [
                'scope' => 'playlist',
                'kind' => 'playlist-track',
                'label' => $playlistTitle !== '' ? $playlistTitle : $playlistId,
                'playlist_id' => $playlistId,
            ];
        }
    }

    return $references;
}

function bandpromo_playlist_remove_audio_reference(string $root, string $filename): array
{
    $summary = [
        'playlists_updated' => 0,
        'entries_removed' => 0,
    ];

    bandpromo_playlist_ensure_seeded($root);

    foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
        if (!is_array($registryEntry)) {
            continue;
        }

        $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        $before = count($document['entries'] ?? []);
        $entries = [];
        foreach ($document['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (bandpromo_playlist_entry_matches_audio_filename($root, $entry, $filename)) {
                continue;
            }
            $entries[] = $entry;
        }

        $after = count($entries);
        if ($after === $before) {
            continue;
        }

        $document['entries'] = $entries;
        $document = bandpromo_playlist_clear_player_payload_fields($document);
        bandpromo_playlist_write_document($root, $document);

        $summary['playlists_updated']++;
        $summary['entries_removed'] += ($before - $after);
    }

    return $summary;
}

function bandpromo_playlist_merged_built_track_map(string $root): array
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    $map = [];
    bandpromo_playlist_ensure_seeded($root);

    foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
        if (!is_array($registryEntry)) {
            continue;
        }

        $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        // Prefer stored published player tracks (cover/lyrics/description) when present.
        $storedTracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
        if ($storedTracks !== []) {
            foreach ($storedTracks as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $file = trim((string) ($track['file'] ?? ''));
                if ($file !== '') {
                    $map[$file] = $track;
                }
            }
            continue;
        }

        foreach (bandpromo_playlist_build_track_list($root, $document) as $track) {
            if (!is_array($track)) {
                continue;
            }
            $file = trim((string) ($track['file'] ?? ''));
            if ($file !== '') {
                $map[$file] = $track;
            }
        }
    }

    $cache[$root] = $map;

    return $map;
}

function bandpromo_playlist_campaign_date_is_public(string $releaseDate, bool $operatorBypass): bool
{
    if ($operatorBypass) {
        return true;
    }

    $releaseDate = trim($releaseDate);
    if ($releaseDate === '') {
        return true;
    }

    $today = (int) gmdate('Ymd');
    if (preg_match('/^\d{4}$/', $releaseDate)) {
        return (int) ($releaseDate . '0101') <= $today;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $releaseDate);

    return $date instanceof DateTimeImmutable ? (int) $date->format('Ymd') <= $today : true;
}

function bandpromo_playlist_track_slug(array $track, ?array $asset, ?array $releaseTrack): string
{
    if ($releaseTrack !== null) {
        $slug = trim((string) ($releaseTrack['slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }
    }

    if ($asset !== null) {
        $slug = trim((string) ($asset['slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }
    }

    $title = trim((string) ($track['title'] ?? ''));
    $title = explode("\n", $title)[0] ?? $title;
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? substr($slug, 0, 64) : 'track';
}

function bandpromo_playlist_track_stream_state(
    string $root,
    string $masterFile,
    string $preferredVariant,
    bool $embargoPlayable
): array {
    if (!$embargoPlayable) {
        return [
            'delivery_ready' => false,
            'delivery_mode' => 'embargoed',
            'playable' => false,
            'lock_reason' => 'embargoed',
        ];
    }

    if ($preferredVariant === 'original' || $preferredVariant === 'master') {
        $sourceReady = bandpromo_playlist_resolve_source_audio_path($root, $masterFile) !== null;

        // Operator/high-quality listen uses master; public preferred variant is always optimal.
        return [
            'delivery_ready' => $sourceReady,
            'delivery_mode' => $sourceReady ? 'master' : 'source_missing',
            'playable' => $sourceReady,
            'lock_reason' => $sourceReady ? '' : 'source_missing',
        ];
    }

    $deliveryReady = bandpromo_asset_audio_delivery_ready($root, $masterFile);
    if ($deliveryReady) {
        return [
            'delivery_ready' => true,
            'delivery_mode' => 'optimal',
            'playable' => true,
            'lock_reason' => '',
        ];
    }

    return [
        'delivery_ready' => false,
        'delivery_mode' => 'pending',
        'playable' => false,
        'lock_reason' => 'delivery_pending',
    ];
}

function bandpromo_playlist_delivery_summary(array $tracks): array
{
    $pending = 0;
    $demoOriginal = 0;

    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $mode = (string) ($track['delivery_mode'] ?? '');
        if ($mode === 'pending' && ($track['lock_reason'] ?? '') === 'delivery_pending') {
            $pending++;
        }
        if ($mode === 'demo_original') {
            $demoOriginal++;
        }
    }

    return [
        'pending_count' => $pending,
        'demo_original_count' => $demoOriginal,
        'publish_required' => $pending > 0,
    ];
}

function bandpromo_playlist_enrich_tracks_for_player(
    string $root,
    array $tracks,
    bool $operatorBypass,
    string $preferredVariant = 'optimal'
): array {
    $enriched = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = trim((string) ($track['file'] ?? ''));
        $asset = $masterFile !== '' ? bandpromo_asset_lookup_by_master_filename($root, $masterFile) : null;
        $releaseId = trim((string) ($asset['release_id'] ?? ''));
        if ($releaseId === '') {
            $releaseId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
        }

        $release = null;
        $releaseTrack = null;
        try {
            $release = bandpromo_campaign_load_document($root, $releaseId);
            if ($asset !== null) {
                $releaseTrack = bandpromo_campaign_track_entry_for_asset($root, $releaseId, (string) $asset['id']);
            }
        } catch (Throwable $throwable) {
            $release = null;
        }

        $releaseSlug = (string) ($release['slug'] ?? $releaseId);
        $releaseDate = (string) ($release['release_date'] ?? '');
        $embargoPlayable = bandpromo_playlist_campaign_date_is_public($releaseDate, $operatorBypass);
        $streamState = bandpromo_playlist_track_stream_state(
            $root,
            $masterFile,
            $preferredVariant,
            $embargoPlayable
        );

        $display = bandpromo_asset_read_audio_display($asset);

        $livingCover = bandpromo_living_cover_canonical_id($root, (string) ($track['living_cover'] ?? ''));
        $registryLiving = bandpromo_living_cover_canonical_id(
            $root,
            (string) ($display['living_cover'] ?? '')
        );
        if ($livingCover === '' && $registryLiving !== '') {
            $livingCover = $registryLiving;
        }
        $animatedCover = $livingCover !== ''
            ? bandpromo_living_cover_player_url($root, $livingCover)
            : '';
        if (
            $livingCover !== ''
            && is_array($asset)
            && trim((string) ($asset['id'] ?? '')) !== ''
            && $registryLiving !== $livingCover
        ) {
            try {
                bandpromo_asset_update_entry($root, (string) $asset['id'], [
                    'display' => ['living_cover' => $livingCover],
                ]);
            } catch (Throwable $ignored) {
                // Player payload can still ship the resolved id/URL.
            }
        }

        $coverCandidate = trim((string) ($display['cover'] ?? ''));
        if ($coverCandidate === '') {
            $coverCandidate = trim((string) ($track['cover'] ?? ''));
        }
        $coverRef = bandpromo_asset_canonical_id_from_media_ref($root, $coverCandidate);
        if ($coverRef !== '') {
            $coverAsset = bandpromo_asset_lookup_by_id($root, $coverRef);
            if (!is_array($coverAsset) || ($coverAsset['kind'] ?? '') !== 'visual') {
                // Fail loud on non-visual cover refs (often the audio id by mistake).
                $fallback = trim((string) ($display['cover'] ?? ''));
                $coverRef = $fallback !== ''
                    ? bandpromo_asset_canonical_id_from_media_ref($root, $fallback)
                    : '';
                $coverAsset = $coverRef !== '' ? bandpromo_asset_lookup_by_id($root, $coverRef) : null;
                if (!is_array($coverAsset) || ($coverAsset['kind'] ?? '') !== 'visual') {
                    $coverRef = '';
                }
            }
        }
        $coverUrl = '';
        if ($coverRef !== '') {
            require_once __DIR__ . '/media-delivery-helpers.php';
            $coverUrl = bandpromo_visual_resolve_url($root, $coverRef, 'card', '', false);
            if ($coverUrl === '') {
                $coverUrl = bandpromo_playlist_prefer_cover_delivery_url($root, '', $coverRef);
            }
            if ($coverUrl !== '' && !str_starts_with($coverUrl, '/media/visual/delivery/')) {
                $coverUrl = '';
            }
        }

        $textRole = $display['text_role'];
        $notesLabel = $display['notes_label'];
        if ($textRole !== 'notes') {
            $notesLabel = '';
        }

        $enriched[] = array_merge($track, [
            'asset_id' => (string) ($asset['id'] ?? ''),
            'release_id' => $releaseId,
            'release_slug' => $releaseSlug,
            // Player brand comes from the playlist’s owning release, not per-track.
            'brand_id' => '',
            'track_slug' => bandpromo_playlist_track_slug($track, $asset, $releaseTrack),
            'delivery_ready' => (bool) ($streamState['delivery_ready'] ?? false),
            'delivery_mode' => (string) ($streamState['delivery_mode'] ?? ''),
            'playable' => (bool) ($streamState['playable'] ?? false),
            'lock_reason' => (string) ($streamState['lock_reason'] ?? ''),
            'cover' => $coverRef,
            'living_cover' => $livingCover,
            'animated_cover' => $animatedCover,
            'cover_url' => $coverUrl,
            'text_role' => $textRole,
            'notes_label' => $notesLabel,
        ]);
    }

    return $enriched;
}

function bandpromo_playlist_materialize_for_player(
    string $root,
    string $playlistId,
    bool $operatorBypass,
    string $preferredVariant = 'optimal'
): array {
    $document = bandpromo_playlist_load_document($root, $playlistId);
    $filenames = [];
    foreach ($document['entries'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $masterFile = basename(trim((string) ($entry['master_file'] ?? '')));
        if ($masterFile !== '') {
            $filenames[] = $masterFile;
        }
    }

    $builtTracks = [];
    if ($filenames !== []) {
        // Prefer tag/sidecar materialization (lyrics, description, embedded cover)
        // over sparse registry display — publish must not ship empty player fields.
        $materialized = bandpromo_playlist_materialize_entries($filenames);
        $builtTracks = is_array($materialized['entries'] ?? null) ? $materialized['entries'] : [];
    }

    $tracks = bandpromo_playlist_build_track_list($root, $document, $builtTracks);

    return bandpromo_playlist_enrich_tracks_for_player($root, $tracks, $operatorBypass, $preferredVariant);
}

function bandpromo_playlist_publish_player_payload(string $root, string $playlistId): array
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        throw new InvalidArgumentException('Invalid playlist id.');
    }

    $document = bandpromo_playlist_load_document($root, $playlistId);
    $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
    if ($entries === []) {
        $hadPayload = isset($document['tracks'])
            || isset($document['brand_styles'])
            || isset($document['delivery_summary'])
            || isset($document['player_built_at']);
        if ($hadPayload) {
            $document = bandpromo_playlist_clear_player_payload_fields($document);
            bandpromo_playlist_write_document($root, $document);
        }

        return [
            'playlist_id' => $playlistId,
            'track_count' => 0,
            'player_built_at' => '',
            'changed' => $hadPayload,
        ];
    }

    // Heal primary/empty ownership onto the unanimous track campaign before materialize.
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/campaign-ownership-helpers.php';
    $owned = bandpromo_document_campaign_id($document);
    if (bandpromo_campaign_id_is_unowned($owned)) {
        $inferred = bandpromo_campaign_normalize_id(
            bandpromo_campaign_ownership_infer_from_playlist_entries($root, $document)
        );
        if ($inferred !== '' && !bandpromo_campaign_id_is_unowned($inferred)) {
            $document = bandpromo_document_with_campaign_id($document, $inferred);
            bandpromo_playlist_write_document($root, $document);
        }
    }

    // Refresh sparse registry display from master tags before PHP fallback materialization.
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $masterFile = basename(trim((string) ($entry['master_file'] ?? '')));
        if ($masterFile === '') {
            continue;
        }
        $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
        if ($asset === null) {
            continue;
        }
        $display = bandpromo_asset_read_audio_display($asset);
        $lyrics = trim((string) ($display['lyrics'] ?? ''));
        $cover = trim((string) ($display['cover'] ?? ''));
        $comment = trim((string) ($display['comment'] ?? ''));
        if ($lyrics === '' || $cover === '' || $comment === '') {
            bandpromo_asset_refresh_audio_display($root, $masterFile);
        }
    }

    $tracks = bandpromo_playlist_materialize_for_player($root, $playlistId, false, 'optimal');
    foreach ($tracks as $index => $track) {
        if (!is_array($track)) {
            continue;
        }
        if (($track['lock_reason'] ?? '') === 'embargoed') {
            $tracks[$index]['embargoed'] = true;
        }
    }

    $brandId = bandpromo_playlist_effective_brand_id($root, $playlistId);
    $brandStyles = $brandId !== ''
        ? bandpromo_brand_player_styles_for_ids($root, [$brandId])
        : [];
    $deliverySummary = bandpromo_playlist_delivery_summary($tracks);

    $beforeFingerprint = bandpromo_playlist_player_payload_fingerprint($document);
    $document['tracks'] = bandpromo_playlist_normalize_stored_tracks($tracks);
    $document['brand_styles'] = bandpromo_playlist_normalize_stored_brand_styles(
        is_array($brandStyles) ? $brandStyles : []
    );
    $document['delivery_summary'] = bandpromo_playlist_normalize_stored_delivery_summary(
        is_array($deliverySummary) ? $deliverySummary : []
    );
    $afterFingerprint = bandpromo_playlist_player_payload_fingerprint($document);
    $changed = ($beforeFingerprint === '' || $beforeFingerprint !== $afterFingerprint);

    if ($changed) {
        $document['player_built_at'] = gmdate('c');
        bandpromo_playlist_write_document($root, $document);
    }

    return [
        'playlist_id' => $playlistId,
        'track_count' => count($tracks),
        'player_built_at' => (string) ($document['player_built_at'] ?? ''),
        'brand_id' => $brandId,
        'changed' => $changed,
    ];
}

/**
 * Fingerprint player payload fields that matter to listeners (ignore build timestamp).
 */
function bandpromo_playlist_player_payload_fingerprint(array $document): string
{
    $slice = [
        'tracks' => $document['tracks'] ?? null,
        'brand_styles' => $document['brand_styles'] ?? null,
        'delivery_summary' => $document['delivery_summary'] ?? null,
    ];
    $encoded = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || $encoded === '') {
        return '';
    }

    return hash('sha256', $encoded);
}

function bandpromo_playlist_publish_all_player_payloads(string $root): array
{
    $published = [];
    $errors = [];

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $published[] = bandpromo_playlist_publish_player_payload($root, $playlistId);
        } catch (Throwable $throwable) {
            $errors[] = [
                'playlist_id' => $playlistId,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    return [
        'published' => $published,
        'errors' => $errors,
    ];
}

function bandpromo_playlist_load_player_response(
    string $root,
    string $playlistId,
    string $preferredVariant = 'optimal'
): array {
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        throw new InvalidArgumentException('Invalid playlist id.');
    }

    $document = bandpromo_playlist_load_document($root, $playlistId);
    $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
    if ($tracks === []) {
        throw new RuntimeException('This playlist has not been published yet. Run System → Publish to build the player playlist.');
    }

    $brandId = bandpromo_playlist_effective_brand_id($root, $playlistId);
    $brandStyles = is_array($document['brand_styles'] ?? null)
        ? bandpromo_playlist_normalize_stored_brand_styles($document['brand_styles'])
        : [];
    $deliverySummary = is_array($document['delivery_summary'] ?? null)
        ? bandpromo_playlist_normalize_stored_delivery_summary($document['delivery_summary'])
        : bandpromo_playlist_delivery_summary($tracks);

    // Always resolve brand tokens live so Content → Branding edits show without a full Publish.
    if ($brandId !== '') {
        $liveBrandStyles = bandpromo_brand_player_styles_for_ids($root, [$brandId]);
        if ($liveBrandStyles !== []) {
            $brandStyles = bandpromo_playlist_normalize_stored_brand_styles($liveBrandStyles);
        }
    }

    $tracks = bandpromo_playlist_normalize_stored_tracks($tracks);
    // Live text-panel role/label from registry (same content field; presentation only).
    // Re-resolve cover_url so Visual rebuilds show without requiring a playlist rewrite.
    require_once __DIR__ . '/media-delivery-helpers.php';
    foreach ($tracks as $index => $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = trim((string) ($track['file'] ?? ''));
        $asset = $masterFile !== '' ? bandpromo_asset_lookup_by_master_filename($root, $masterFile) : null;
        $display = bandpromo_asset_read_audio_display($asset);
        $tracks[$index]['text_role'] = $display['text_role'];
        $tracks[$index]['notes_label'] = $display['text_role'] === 'notes' ? $display['notes_label'] : '';

        $coverCandidate = trim((string) ($display['cover'] ?? ''));
        if ($coverCandidate === '') {
            $coverCandidate = trim((string) ($track['cover'] ?? ''));
        }
        $coverRef = bandpromo_asset_canonical_id_from_media_ref($root, $coverCandidate);
        if ($coverRef !== '') {
            $coverAsset = bandpromo_asset_lookup_by_id($root, $coverRef);
            if (!is_array($coverAsset) || ($coverAsset['kind'] ?? '') !== 'visual') {
                $coverRef = '';
            }
        }
        $coverUrl = '';
        if ($coverRef !== '') {
            $coverUrl = bandpromo_visual_resolve_url($root, $coverRef, 'card', '', false);
            if ($coverUrl === '') {
                $coverUrl = bandpromo_playlist_prefer_cover_delivery_url($root, '', $coverRef);
            }
            if ($coverUrl !== '' && !str_starts_with($coverUrl, '/media/visual/delivery/')) {
                $coverUrl = '';
            }
        }
        $tracks[$index]['cover'] = $coverRef;
        $tracks[$index]['cover_url'] = $coverUrl;
    }

    if (bandpromo_playlist_normalize_play_order((string) ($document['play_order'] ?? 'stored')) === 'reverse') {
        $tracks = array_values(array_reverse($tracks));
    }

    $effectiveReleaseId = bandpromo_playlist_effective_campaign_id($root, $playlistId);
    if ($effectiveReleaseId !== '') {
        foreach ($tracks as $index => $track) {
            if (!is_array($track)) {
                continue;
            }
            if (trim((string) ($track['release_id'] ?? '')) === '') {
                $tracks[$index]['release_id'] = $effectiveReleaseId;
            }
        }
    }

    return [
        'playlist_id' => $playlistId,
        'playlist_slug' => bandpromo_playlist_public_slug($root, $playlistId),
        'playlist_title' => (string) ($document['title'] ?? $playlistId),
        'release_id' => $effectiveReleaseId,
        'brand_id' => $brandId,
        'package_type' => bandpromo_playlist_normalize_package_type((string) ($document['package_type'] ?? 'other')),
        'play_order' => bandpromo_playlist_normalize_play_order(
            (string) ($document['play_order'] ?? 'stored')
        ),
        'preferred_audio_variant' => $preferredVariant,
        'delivery_summary' => $deliverySummary,
        'brand_styles' => $brandStyles,
        'tracks' => $tracks,
        'player_built_at' => trim((string) ($document['player_built_at'] ?? '')),
    ];
}

function bandpromo_playlist_resolve_track_index(array $tracks, string $releaseSlug, string $trackSlug): int
{
    $releaseSlug = strtolower(trim($releaseSlug));
    $trackSlug = strtolower(trim($trackSlug));
    foreach ($tracks as $index => $track) {
        if (!is_array($track)) {
            continue;
        }
        if (strtolower((string) ($track['release_slug'] ?? '')) === $releaseSlug
            && strtolower((string) ($track['track_slug'] ?? '')) === $trackSlug) {
            return (int) $index;
        }
    }

    return -1;
}

function bandpromo_playlist_resolve_track_index_by_track_slug(array $tracks, string $trackSlug): int
{
    $trackSlug = strtolower(trim($trackSlug));
    if ($trackSlug === '') {
        return -1;
    }

    foreach ($tracks as $index => $track) {
        if (!is_array($track)) {
            continue;
        }
        if (strtolower((string) ($track['track_slug'] ?? '')) === $trackSlug) {
            return (int) $index;
        }
    }

    return -1;
}

function bandpromo_playlist_resolve_player_track_index(
    array $tracks,
    string $releaseSlug,
    string $trackSlug
): int {
    $releaseSlug = strtolower(trim($releaseSlug));
    $trackSlug = strtolower(trim($trackSlug));
    if ($trackSlug === '') {
        return -1;
    }

    if ($releaseSlug !== '') {
        return bandpromo_playlist_resolve_track_index($tracks, $releaseSlug, $trackSlug);
    }

    return bandpromo_playlist_resolve_track_index_by_track_slug($tracks, $trackSlug);
}

function bandpromo_playlist_entry_master_files(array $document): array
{
    $files = [];
    foreach ($document['entries'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $file = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
        if ($file !== '') {
            $files[] = $file;
        }
    }

    return $files;
}

function bandpromo_playlist_pool_map_from_preview_tracks(array $tracks): array
{
    $map = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $map[$file] = $track;
    }

    return $map;
}

function bandpromo_playlist_enrich_track_campaign_meta(string $root, array $row): array
{
    $file = trim((string) ($row['file'] ?? ''));
    if ($file === '') {
        return $row;
    }

    $meta = bandpromo_campaign_audio_listing_meta($root, $file);
    $releaseTitle = trim((string) ($meta['release_title'] ?? ''));
    if ($releaseTitle !== '') {
        $row['release_title'] = $releaseTitle;
    }
    if (trim((string) ($row['release_id'] ?? '')) === '') {
        $releaseId = bandpromo_campaign_normalize_id(trim((string) ($meta['release_id'] ?? '')));
        if ($releaseId !== '') {
            $row['release_id'] = $releaseId;
        }
    }

    return $row;
}

function bandpromo_playlist_campaign_track_order_map(string $root, string $releaseId): array
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        return [];
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return [];
    }

    $order = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = bandpromo_campaign_track_master_filename($root, (string) ($track['asset_id'] ?? ''));
        if ($masterFile === '') {
            continue;
        }
        $order[$masterFile] = (int) ($track['track_number'] ?? 0);
    }

    return $order;
}

function bandpromo_playlist_campaign_title(string $root, string $releaseId): string
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        return '';
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return '';
    }

    return trim((string) ($document['title'] ?? ''));
}

function bandpromo_playlist_build_pool_track_row(string $root, array $track, string $masterFile): array
{
    $masterFile = basename(trim($masterFile));
    $releaseId = bandpromo_campaign_normalize_id(trim((string) ($track['release_id'] ?? '')));
    if ($releaseId === '') {
        $releaseId = bandpromo_campaign_id_for_master_filename($root, $masterFile);
    }
    $releaseTitle = trim((string) ($track['release_title'] ?? ''));
    if ($releaseTitle === '') {
        $releaseTitle = bandpromo_playlist_campaign_title($root, $releaseId);
    }

    $row = bandpromo_campaign_enrich_track_row_labels(
        $root,
        bandpromo_campaign_track_row_from_pool($track, $releaseId),
        $releaseTitle
    );

    if ($releaseTitle !== '') {
        $row['release_title'] = $releaseTitle;
    }
    if ($releaseId !== '') {
        $row['release_id'] = $releaseId;
    }

    return $row;
}

function bandpromo_playlist_sort_available_tracks(string $root, string $releaseFilter, array $tracks): array
{
    $releaseFilter = bandpromo_playlist_normalize_campaign_filter($releaseFilter);
    if ($releaseFilter !== 'all' && $releaseFilter !== 'orphans') {
        $orderMap = bandpromo_playlist_campaign_track_order_map($root, $releaseFilter);
        usort($tracks, static function (array $left, array $right) use ($orderMap): int {
            $leftFile = (string) ($left['file'] ?? '');
            $rightFile = (string) ($right['file'] ?? '');
            $leftOrder = $orderMap[$leftFile] ?? 9999;
            $rightOrder = $orderMap[$rightFile] ?? 9999;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $tracks;
    }

    usort($tracks, static function (array $left, array $right): int {
        return strnatcasecmp(
            bandpromo_editor_track_sort_label($left),
            bandpromo_editor_track_sort_label($right)
        );
    });

    return $tracks;
}

function bandpromo_playlist_track_row_from_pool(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim((string) ($track['release_id'] ?? $releaseId));
    $origin = trim((string) ($track['origin'] ?? ''));
    if ($origin === '') {
        $origin = 'user-upload';
    }

    return [
        'file' => $file,
        'title' => (string) ($track['title'] ?? $file),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => $origin,
        'sourceTier' => (string) ($track['sourceTier'] ?? 'master'),
        'deliveryReady' => ($track['deliveryReady'] ?? true) !== false,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_playlist_track_row_from_built(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim($releaseId);
    $origin = trim((string) ($track['origin'] ?? ''));
    if ($origin === '') {
        $origin = 'user-upload';
    }

    return [
        'file' => $file,
        'title' => (string) ($track['title'] ?? $file),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => $origin,
        'sourceTier' => 'playlist-container',
        'deliveryReady' => true,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_playlist_normalize_campaign_filter(string $value): string
{
    return bandpromo_campaign_normalize_pool_filter($value);
}

function bandpromo_playlist_track_matches_campaign_filter(
    string $root,
    string $masterFile,
    string $releaseFilter
): bool {
    $releaseFilter = bandpromo_playlist_normalize_campaign_filter($releaseFilter);
    if ($releaseFilter === 'all') {
        return true;
    }

    $meta = bandpromo_campaign_audio_listing_meta($root, $masterFile);
    if ($releaseFilter === 'orphans') {
        return !empty($meta['release_orphan']);
    }

    return bandpromo_campaign_normalize_id((string) ($meta['release_id'] ?? '')) === $releaseFilter;
}

function bandpromo_playlist_enrich_pool_campaign_ids(string $root, array $poolByFile): array
{
    foreach ($poolByFile as $file => $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = basename(trim((string) ($track['file'] ?? $file)));
        if ($masterFile === '') {
            continue;
        }
        $track['file'] = $masterFile;
        $meta = bandpromo_campaign_audio_listing_meta($root, $masterFile);
        $releaseId = bandpromo_campaign_normalize_id(trim((string) ($meta['release_id'] ?? '')));
        if ($releaseId !== '') {
            $track['release_id'] = $releaseId;
        }
        $releaseTitle = trim((string) ($meta['release_title'] ?? ''));
        if ($releaseTitle !== '') {
            $track['release_title'] = $releaseTitle;
        }
        $poolByFile[$file] = $track;
    }

    return bandpromo_playlist_pool_dedupe_master_files($poolByFile);
}

function bandpromo_playlist_pool_dedupe_master_files(array $poolByFile): array
{
    $deduped = [];
    foreach ($poolByFile as $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = basename(trim((string) ($track['file'] ?? '')));
        if ($masterFile === '') {
            continue;
        }
        $track['file'] = $masterFile;
        $deduped[$masterFile] = $track;
    }

    return $deduped;
}

function bandpromo_playlist_enrich_pool_delivery_ready(string $root, array $poolByFile): array
{
    require_once __DIR__ . '/publish-status-helpers.php';

    $registry = bandpromo_asset_load_registry($root);
    $registeredMasters = is_array($registry['by_master_filename'] ?? null)
        ? $registry['by_master_filename']
        : [];

    foreach ($poolByFile as $file => $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = basename(trim((string) ($track['file'] ?? $file)));
        if ($masterFile === '') {
            continue;
        }

        if (($track['sourceTier'] ?? '') === 'release-container') {
            $track['deliveryReady'] = true;
        } elseif (isset($registeredMasters[$masterFile])) {
            $track['deliveryReady'] = true;
        } else {
            $track['deliveryReady'] = bandpromo_asset_audio_delivery_ready($root, $masterFile);
        }

        $poolByFile[$file] = $track;
    }

    return $poolByFile;
}

function bandpromo_playlist_admin_editor_state(
    string $root,
    string $playlistId,
    string $releaseFilter,
    array $poolByFile,
    array $meta = []
): array {
    $playlistId = bandpromo_playlist_resolve_id($root, $playlistId);
    $releaseFilter = bandpromo_playlist_normalize_campaign_filter($releaseFilter);

    $document = bandpromo_playlist_load_document($root, $playlistId);
    $activeFiles = bandpromo_playlist_entry_master_files($document);
    $activeSet = array_fill_keys($activeFiles, true);

    $builtByFile = [];
    foreach (bandpromo_playlist_build_track_list($root, $document) as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file !== '') {
            $builtByFile[$file] = $track;
        }
    }

    $activeTracks = [];
    foreach ($activeFiles as $file) {
        if (isset($poolByFile[$file])) {
            $activeTracks[] = bandpromo_playlist_build_pool_track_row($root, $poolByFile[$file], $file);
            continue;
        }

        if (!isset($builtByFile[$file])) {
            continue;
        }

        $releaseId = bandpromo_campaign_id_for_master_filename($root, $file);
        $row = bandpromo_campaign_enrich_track_row_labels(
            $root,
            bandpromo_playlist_track_row_from_built($builtByFile[$file], $releaseId),
            bandpromo_playlist_campaign_title($root, $releaseId)
        );
        $activeTracks[] = bandpromo_playlist_enrich_track_campaign_meta($root, $row);
    }

    $availableTracks = [];
    foreach ($poolByFile as $file => $track) {
        if (isset($activeSet[$file])) {
            continue;
        }
        if (!bandpromo_playlist_track_matches_campaign_filter($root, $file, $releaseFilter)) {
            continue;
        }
        $availableTracks[] = bandpromo_playlist_build_pool_track_row($root, $track, $file);
    }
    $availableTracks = bandpromo_playlist_sort_available_tracks($root, $releaseFilter, $availableTracks);

    return [
        'ok' => true,
        'tracks' => $activeTracks,
        'activeTracks' => $activeTracks,
        'availableTracks' => $availableTracks,
        'hiddenBundledSourceFiles' => array_values(array_filter(
            $meta['hiddenBundledSourceFiles'] ?? [],
            static fn($file) => is_string($file) && $file !== ''
        )),
        'unsupportedSourceFiles' => array_values(array_filter(
            $meta['unsupportedSourceFiles'] ?? [],
            static fn($file) => is_string($file) && $file !== ''
        )),
        'release_filter' => $releaseFilter,
        'previewSource' => (string) ($meta['previewSource'] ?? 'playlist-container'),
        'playlist_id' => $playlistId,
    ];
}

function bandpromo_playlist_admin_preview(string $root, string $playlistId): array
{
    return bandpromo_playlist_admin_editor_state($root, $playlistId, 'all', [], [
        'previewSource' => 'playlist-container',
    ]);
}

function bandpromo_playlist_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'playlist';
    }

    return substr($slug, 0, 48);
}

function bandpromo_playlist_registry_entry(string $root, string $playlistId): ?array
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $playlistId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_playlist_admin_registry_entry(string $root, array $registryEntry): array
{
    $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
    $entry = $registryEntry;
    $entry['track_count'] = 0;
    $entry['ownership'] = bandpromo_playlist_is_system_owned($playlistId) ? 'system' : 'operator';

    try {
        $document = bandpromo_playlist_load_document($root, $playlistId);
        $entry['title'] = (string) ($document['title'] ?? $entry['title'] ?? $playlistId);
        $entry['publish_date'] = (string) ($document['publish_date'] ?? $entry['publish_date'] ?? '');
        $entry['slug'] = bandpromo_playlist_route_slug($document, $playlistId);
        $entry['description'] = (string) ($document['description'] ?? '');
        $entry['short_description'] = (string) ($document['short_description'] ?? '');
        $entry['poster_asset_id'] = (string) ($document['poster_asset_id'] ?? '');
        $entry['poster_preview_url'] = bandpromo_campaign_resolve_poster_preview_url(
            $root,
            $entry['poster_asset_id']
        );
        $entry['kind'] = strtolower((string) ($document['kind'] ?? $entry['kind'] ?? 'system')) === 'user'
            ? 'user'
            : 'system';
        $entry['track_count'] = count($document['entries'] ?? []);
        $entry['release_id'] = trim((string) ($document['release_id'] ?? ''));
        $entry['release_title'] = $entry['release_id'] !== ''
            ? bandpromo_playlist_campaign_title($root, $entry['release_id'])
            : '';
        $entry['package_type'] = bandpromo_playlist_normalize_package_type(
            (string) ($document['package_type'] ?? 'other')
        );
        $entry['package_type_label'] = bandpromo_playlist_package_type_label($entry['package_type']);
        $entry['play_order'] = bandpromo_playlist_normalize_play_order(
            (string) ($document['play_order'] ?? bandpromo_playlist_default_play_order_for_package_type($entry['package_type']))
        );
    } catch (Throwable $throwable) {
        // Keep registry-only fields when the document is missing.
        $entry['slug'] = (string) ($entry['slug'] ?? $playlistId);
        $entry['release_id'] = '';
        $entry['release_title'] = '';
        $entry['package_type'] = 'other';
        $entry['package_type_label'] = bandpromo_playlist_package_type_label('other');
        $entry['play_order'] = 'stored';
    }

    $configuredDefault = bandpromo_playlist_configured_default_id($root);
    $entry['is_default'] = $configuredDefault !== '' && $configuredDefault === $playlistId;

    return $entry;
}

function bandpromo_playlist_admin_registry_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        $owner = '';
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
            $owner = (string) ($document['release_id'] ?? '');
        } catch (Throwable $throwable) {
            $owner = '';
        }
        if (!bandpromo_demo_campaign_container_is_visible($root, $owner, $playlistId)) {
            continue;
        }
        $entries[] = bandpromo_playlist_admin_registry_entry($root, $entry);
    }

    usort($entries, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return $entries;
}

function bandpromo_playlist_is_system_owned(string $playlistId): bool
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);

    return bandpromo_playlist_normalize_id($playlistId) === BANDPROMO_PLAYLIST_DEMO_ID;
}

function bandpromo_playlist_is_protected_id(string $playlistId): bool
{
    return bandpromo_playlist_is_system_owned($playlistId);
}

function bandpromo_playlist_create(string $root, string $title, string $preferredId = ''): array
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Playlist name is required.');
    }

    $registry = bandpromo_playlist_load_registry($root);
    $baseId = $preferredId !== ''
        ? bandpromo_playlist_normalize_id($preferredId)
        : bandpromo_playlist_slug_from_title($title);
    if ($baseId === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        throw new InvalidArgumentException('Playlist id is invalid. Use lowercase letters, numbers, and hyphens.');
    }

    $id = $baseId;
    $suffix = 2;
    $existing = [];
    foreach ($registry['playlists'] as $entry) {
        $existing[(string) ($entry['id'] ?? '')] = true;
    }
    while (isset($existing[$id])) {
        $id = substr($baseId, 0, 44) . '-' . $suffix;
        $suffix++;
    }

    $maxOrder = 0;
    foreach ($registry['playlists'] as $entry) {
        $maxOrder = max($maxOrder, (int) ($entry['sort_order'] ?? 0));
    }

    $registry['playlists'][] = [
        'id' => $id,
        'title' => $title,
        'kind' => 'system',
        'publish_date' => gmdate('Y-m-d'),
        'sort_order' => $maxOrder + 10,
    ];
    bandpromo_playlist_write_registry($root, $registry);

    $document = bandpromo_playlist_new_document($id, $title);
    bandpromo_playlist_write_document($root, $document);

    return bandpromo_playlist_admin_registry_entry(
        $root,
        bandpromo_playlist_registry_entry($root, $id) ?? ['id' => $id]
    );
}

function bandpromo_playlist_create_from_campaign(string $root, string $releaseId): array
{
    require_once __DIR__ . '/campaign-storage.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Campaign id is required.');
    }

    $document = bandpromo_campaign_load_document($root, $releaseId);
    $title = trim((string) ($document['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Campaign title is required to create a playlist.');
    }

    $entries = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = trim((string) ($track['asset_id'] ?? ''));
        $masterFile = bandpromo_campaign_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }
        $entries[] = [
            'master_file' => $masterFile,
            'asset_id' => $assetId,
            'campaign_id' => $releaseId,
        ];
    }

    if ($entries === []) {
        throw new InvalidArgumentException('Add tracks to the campaign before creating a playlist.');
    }

    $preferredId = bandpromo_playlist_slug_from_title($title);

    $entry = bandpromo_playlist_create($root, $title, $preferredId);
    $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
    if ($playlistId === '') {
        throw new RuntimeException('Could not create playlist.');
    }

    $publishDate = trim((string) ($document['release_date'] ?? ''));
    if ($publishDate === '' || !bandpromo_playlist_validate_date($publishDate)) {
        $publishDate = gmdate('Y-m-d');
    }

    bandpromo_playlist_update_details($root, $playlistId, [
        'title' => $title,
        'publish_date' => $publishDate,
        'description' => (string) ($document['description'] ?? ''),
        'short_description' => (string) ($document['short_description'] ?? ''),
        'poster_asset_id' => (string) ($document['poster_asset_id'] ?? ''),
        'campaign_id' => $releaseId,
    ]);

    $playlistDocument = bandpromo_playlist_load_document($root, $playlistId);
    $playlistDocument['entries'] = $entries;
    $playlistDocument = bandpromo_document_with_campaign_id($playlistDocument, $releaseId);
    $playlistDocument = bandpromo_playlist_clear_player_payload_fields($playlistDocument);
    bandpromo_playlist_write_document($root, $playlistDocument);

    $updated = bandpromo_playlist_registry_entry($root, $playlistId);
    if ($updated === null) {
        throw new RuntimeException('Could not load created playlist.');
    }

    return bandpromo_playlist_admin_registry_entry($root, $updated);
}

function bandpromo_playlist_set_campaign_id(string $root, string $playlistId, string $releaseId): void
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        throw new InvalidArgumentException('Playlist id is required.');
    }
    if (bandpromo_playlist_is_protected_id($playlistId)) {
        throw new InvalidArgumentException('The bandPromo demo playlist cannot be reassigned.');
    }

    require_once __DIR__ . '/campaign-storage.php';
    $releaseId = trim($releaseId);
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        throw new InvalidArgumentException('Invalid campaign id.');
    }

    $document = bandpromo_playlist_load_document($root, $playlistId);
    $document = bandpromo_document_with_campaign_id($document, $releaseId);
    $document = bandpromo_playlist_clear_player_payload_fields($document);
    bandpromo_playlist_write_document($root, $document);
}

function bandpromo_playlist_update_details(string $root, string $playlistId, array $fields): array
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if ($playlistId === '') {
        throw new InvalidArgumentException('Playlist id is required.');
    }

    $title = trim((string) ($fields['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Playlist name is required.');
    }

    $publishDate = trim((string) ($fields['publish_date'] ?? ''));
    if (!bandpromo_playlist_validate_date($publishDate)) {
        throw new InvalidArgumentException('Publish date must use YYYY or YYYY-MM-DD.');
    }

    $registry = bandpromo_playlist_load_registry($root);
    $found = false;
    foreach ($registry['playlists'] as $index => $entry) {
        if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $playlistId) {
            continue;
        }
        $registry['playlists'][$index]['title'] = $title;
        $registry['playlists'][$index]['publish_date'] = $publishDate;
        $found = true;
        break;
    }
    if (!$found) {
        throw new InvalidArgumentException('Unknown playlist.');
    }
    bandpromo_playlist_write_registry($root, $registry);

    $document = bandpromo_playlist_load_document($root, $playlistId);
    $document['title'] = $title;
    $document['publish_date'] = $publishDate;
    if (array_key_exists('slug', $fields)) {
        $slug = bandpromo_playlist_normalize_slug((string) $fields['slug'], $playlistId);
        bandpromo_playlist_assert_slug_available($root, $slug, $playlistId);
        $document['slug'] = $slug;
    }
    if (array_key_exists('short_description', $fields)) {
        $document['short_description'] = bandpromo_campaign_normalize_text_field($fields['short_description'], 300);
    }
    if (array_key_exists('description', $fields)) {
        $document['description'] = bandpromo_campaign_normalize_text_field($fields['description'], 4000);
    }
    if (array_key_exists('poster_asset_id', $fields)) {
        $document['poster_asset_id'] = bandpromo_campaign_normalize_poster_asset_id($root, $fields['poster_asset_id']);
    }
    if (array_key_exists('campaign_id', $fields) || array_key_exists('release_id', $fields)) {
        $campaignId = array_key_exists('campaign_id', $fields)
            ? trim((string) $fields['campaign_id'])
            : trim((string) $fields['release_id']);
        if ($campaignId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $campaignId)) {
            $campaignId = '';
        }
        $document = bandpromo_document_with_campaign_id($document, $campaignId);
    }
    $packageTypeChanged = false;
    if (array_key_exists('package_type', $fields)) {
        $nextType = bandpromo_playlist_normalize_package_type((string) $fields['package_type']);
        $packageTypeChanged = $nextType !== bandpromo_playlist_normalize_package_type(
            (string) ($document['package_type'] ?? 'other')
        );
        $document['package_type'] = $nextType;
    }
    if (array_key_exists('play_order', $fields)) {
        $document['play_order'] = bandpromo_playlist_normalize_play_order((string) $fields['play_order']);
    } elseif ($packageTypeChanged) {
        $document['play_order'] = bandpromo_playlist_default_play_order_for_package_type(
            (string) ($document['package_type'] ?? 'other')
        );
    }
    if (!empty($fields['set_as_default'])) {
        bandpromo_playlist_set_default_id($root, $playlistId);
    } elseif (array_key_exists('set_as_default', $fields) && empty($fields['set_as_default'])) {
        $configured = bandpromo_playlist_configured_default_id($root);
        if ($configured === $playlistId) {
            bandpromo_playlist_clear_default_id($root);
        }
    }
    // Keep published player tracks: package_type / play_order apply at load time;
    // brand styles also resolve live. Membership/entry edits clear payloads elsewhere.
    bandpromo_playlist_write_document($root, $document);

    $updated = bandpromo_playlist_registry_entry($root, $playlistId);
    if ($updated === null) {
        throw new RuntimeException('Could not load updated playlist.');
    }

    return bandpromo_playlist_admin_registry_entry($root, $updated);
}

/**
 * After release membership remaps, rewrite playlist track file/asset_id pointers onto live masters.
 *
 * @param array<string, string> $remaps old asset id → new asset id
 * @return array{changed:int, playlists: list<string>}
 */
/**
 * Resolve a live audio asset id for a playlist entry/track reference.
 */
function bandpromo_playlist_resolve_replacement_audio_asset_id(
    string $root,
    string $assetId,
    string $masterFile,
    array $identityIndex,
    array $remaps,
    string $hintTitle = '',
    string $hintArtist = ''
): string {
    $assetId = trim($assetId);
    $masterFile = basename(trim($masterFile));
    $stem = strtolower((string) pathinfo($masterFile !== '' ? $masterFile : $assetId, PATHINFO_FILENAME));

    if ($assetId !== '' && isset($remaps[$assetId])) {
        return (string) $remaps[$assetId];
    }
    if ($stem !== '' && isset($remaps[$stem])) {
        return (string) $remaps[$stem];
    }
    if ($assetId !== '' && bandpromo_asset_lookup_by_id($root, $assetId) !== null) {
        return $assetId;
    }
    if ($masterFile !== '') {
        $byMaster = $identityIndex['by_master'] ?? [];
        $hit = $byMaster[strtolower($masterFile)] ?? $byMaster[$stem] ?? '';
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }
    }

    $hintId = $assetId !== '' ? $assetId : ($stem !== '' ? $stem : '');
    return bandpromo_campaign_resolve_replacement_audio_asset_id(
        $root,
        $hintId,
        $identityIndex,
        $hintTitle,
        $hintArtist
    );
}

/**
 * Rebind playlist entries (and legacy tracks) that point at deleted registry assets.
 *
 * @param array<string, string> $remaps
 * @return array{changed:int, playlists: list<string>}
 */
function bandpromo_playlist_repair_stale_track_asset_ids(string $root, array $remaps = []): array
{
    require_once __DIR__ . '/campaign-storage.php';

    $identityIndex = bandpromo_campaign_live_audio_identity_index($root);
    $changedPlaylists = [];
    $changedTracks = 0;

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        $playlistChanged = false;
        $playlistRelease = bandpromo_campaign_normalize_id((string) ($document['release_id'] ?? ''));

        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        if ($entries !== []) {
            $nextEntries = [];
            foreach ($entries as $playlistEntry) {
                if (!is_array($playlistEntry)) {
                    continue;
                }

                $masterFile = basename(trim((string) ($playlistEntry['master_file'] ?? $playlistEntry['file'] ?? '')));
                $assetId = trim((string) ($playlistEntry['asset_id'] ?? ''));
                $replacement = bandpromo_playlist_resolve_replacement_audio_asset_id(
                    $root,
                    $assetId,
                    $masterFile,
                    $identityIndex,
                    $remaps
                );

                if ($replacement !== '') {
                    $asset = bandpromo_asset_lookup_by_id($root, $replacement);
                    $liveMaster = is_array($asset)
                        ? basename(trim((string) ($asset['master_filename'] ?? '')))
                        : '';
                    if ($liveMaster !== '' && ($masterFile !== $liveMaster || $assetId !== $replacement)) {
                        $playlistEntry['master_file'] = $liveMaster;
                        $playlistEntry['asset_id'] = $replacement;
                        if ($playlistRelease !== '') {
                            $playlistEntry['release_id'] = $playlistRelease;
                        }
                        $playlistChanged = true;
                        $changedTracks++;
                    }
                }

                $normalized = bandpromo_playlist_normalize_entry($playlistEntry);
                if ($normalized !== null) {
                    $nextEntries[] = $normalized;
                }
            }
            $document['entries'] = $nextEntries;
        }

        $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
        if ($tracks !== []) {
            $nextTracks = [];
            foreach ($tracks as $track) {
                if (!is_array($track)) {
                    continue;
                }

                $file = basename(trim((string) ($track['file'] ?? '')));
                $assetId = trim((string) ($track['asset_id'] ?? ''));
                $title = trim((string) ($track['title'] ?? ''));
                $artist = trim((string) ($track['artist'] ?? ''));
                $replacement = bandpromo_playlist_resolve_replacement_audio_asset_id(
                    $root,
                    $assetId,
                    $file,
                    $identityIndex,
                    $remaps,
                    $title,
                    $artist
                );

                if ($replacement !== '') {
                    $asset = bandpromo_asset_lookup_by_id($root, $replacement);
                    $liveMaster = is_array($asset)
                        ? basename(trim((string) ($asset['master_filename'] ?? '')))
                        : '';
                    if ($liveMaster !== '' && ($file !== $liveMaster || $assetId !== $replacement)) {
                        $track['file'] = $liveMaster;
                        $track['asset_id'] = $replacement;
                        if ($playlistRelease !== '') {
                            $track['release_id'] = $playlistRelease;
                            $track['release_slug'] = $playlistRelease;
                        }
                        $playlistChanged = true;
                        $changedTracks++;
                    }
                }

                $normalized = bandpromo_playlist_normalize_stored_track($track);
                if ($normalized !== null) {
                    $nextTracks[] = $normalized;
                }
            }
            $document['tracks'] = $nextTracks;
        }

        if ($playlistChanged) {
            $document = bandpromo_playlist_clear_player_payload_fields($document);
            bandpromo_playlist_write_document($root, $document);
            $changedPlaylists[] = $playlistId;
        }
    }

    return [
        'changed' => $changedTracks,
        'playlists' => $changedPlaylists,
    ];
}

function bandpromo_playlist_delete(string $root, string $playlistId): void
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    if (bandpromo_playlist_is_protected_id($playlistId)) {
        throw new InvalidArgumentException('The bandPromo demo playlist cannot be deleted.');
    }

    $registry = bandpromo_playlist_load_registry($root);
    $before = count($registry['playlists']);
    $registry['playlists'] = array_values(array_filter(
        $registry['playlists'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $playlistId
    ));
    if (count($registry['playlists']) === $before) {
        throw new InvalidArgumentException('Unknown playlist.');
    }

    bandpromo_playlist_write_registry($root, $registry);

    $path = bandpromo_playlist_document_path($root, $playlistId);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not delete playlist document.');
    }

    bandpromo_demo_catalog_restore_if_operator_campaign_gone($root);
}
