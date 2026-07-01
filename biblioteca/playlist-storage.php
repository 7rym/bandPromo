<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/light-build-tasks.php';

const BANDPROMO_PLAYLIST_REGISTRY_VERSION = 1;
const BANDPROMO_PLAYLIST_DEMO_ID = 'bandpromo-demo';
const BANDPROMO_PLAYLIST_LEGACY_MAIN_ID = 'main';

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

function bandpromo_playlist_player_catalog_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_playlist_system_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $entries[] = [
            'id' => $id,
            'title' => (string) ($entry['title'] ?? $id),
            'slug' => bandpromo_playlist_public_slug($root, $id),
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

    $releaseId = trim((string) ($entry['release_id'] ?? ''));

    return [
        'master_file' => $masterFile,
        'asset_id' => $assetId,
        'release_id' => $releaseId,
    ];
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

    return [
        'version' => BANDPROMO_PLAYLIST_REGISTRY_VERSION,
        'id' => $id,
        'slug' => $slug,
        'title' => $title,
        'kind' => $kind,
        'publish_date' => $publishDate,
        'description' => bandpromo_release_normalize_text_field($input['description'] ?? '', 4000),
        'short_description' => bandpromo_release_normalize_text_field($input['short_description'] ?? '', 300),
        'poster_asset_id' => $root !== null
            ? bandpromo_release_normalize_poster_asset_id($root, $input['poster_asset_id'] ?? '')
            : trim((string) ($input['poster_asset_id'] ?? '')),
        'entries' => $entries,
    ];
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
        'publish_date' => gmdate('Y-m-d'),
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

function bandpromo_playlist_default_active_id(string $root): string
{
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
        $candidates[] = [
            'id' => $id,
            'publish_value' => $publishValue,
        ];
    }

    if ($candidates === []) {
        return BANDPROMO_PLAYLIST_DEMO_ID;
    }

    usort($candidates, static fn(array $a, array $b): int => $b['publish_value'] <=> $a['publish_value']);

    return $candidates[0]['id'] !== '' ? $candidates[0]['id'] : BANDPROMO_PLAYLIST_DEMO_ID;
}

function bandpromo_playlist_resolve_id(string $root, string $requestedId = ''): string
{
    $requestedId = bandpromo_playlist_normalize_id($requestedId);

    return $requestedId !== '' ? $requestedId : bandpromo_playlist_default_active_id($root);
}

function bandpromo_playlist_built_path(string $root): string
{
    return $root . '/play/playlist.json';
}

function bandpromo_playlist_legacy_order_path(string $root): string
{
    return $root . '/data/playlist-order.json';
}

function bandpromo_playlist_load_built_tracks(string $root): array
{
    $decoded = bandpromo_json_read_array_file(bandpromo_playlist_built_path($root));
    if ($decoded === null) {
        return [];
    }

    $tracks = [];
    foreach ($decoded as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file !== '') {
            $tracks[$file] = $track;
        }
    }

    return $tracks;
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
    if (!empty($running[$root])) {
        return;
    }
    $running[$root] = true;

    try {
        bandpromo_asset_registry_ensure_migrated($root);
        bandpromo_release_ensure_seeded($root);
        bandpromo_playlist_registry_ensure_dir($root);

        if (!is_file(bandpromo_playlist_registry_path($root))) {
            bandpromo_playlist_seed_from_template($root);
        }

        bandpromo_playlist_remove_legacy_main_playlist($root);
        bandpromo_playlist_ensure_demo_playlist($root);
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
    bandpromo_release_ensure_demo_release($root);

    $registry = bandpromo_playlist_load_registry($root);
    $hasDemo = false;
    foreach ($registry['playlists'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['id'] ?? '') === BANDPROMO_PLAYLIST_DEMO_ID) {
            $hasDemo = true;
            break;
        }
    }

    if (!$hasDemo) {
        $registry['playlists'][] = [
            'id' => BANDPROMO_PLAYLIST_DEMO_ID,
            'title' => 'bandPromo demo',
            'kind' => 'system',
            'publish_date' => gmdate('Y-m-d'),
            'sort_order' => 10,
        ];
        bandpromo_playlist_write_registry($root, $registry);
    }

    bandpromo_playlist_sync_demo_playlist($root);
}

function bandpromo_playlist_sync_demo_playlist(string $root): void
{
    if (!bandpromo_playlist_document_is_empty($root, BANDPROMO_PLAYLIST_DEMO_ID)) {
        return;
    }

    try {
        $release = bandpromo_release_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
    } catch (Throwable $throwable) {
        return;
    }

    try {
        $document = bandpromo_playlist_load_document($root, BANDPROMO_PLAYLIST_DEMO_ID);
    } catch (Throwable $throwable) {
        $document = bandpromo_playlist_new_document(BANDPROMO_PLAYLIST_DEMO_ID, 'bandPromo demo');
    }

    $entries = [];
    foreach ($release['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = trim((string) ($track['asset_id'] ?? ''));
        $masterFile = bandpromo_release_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }
        $entries[] = [
            'master_file' => $masterFile,
            'asset_id' => $assetId,
            'release_id' => BANDPROMO_RELEASE_DEMO_ID,
        ];
    }

    $document['id'] = BANDPROMO_PLAYLIST_DEMO_ID;
    $document['title'] = 'bandPromo demo';
    $document['kind'] = 'system';
    if (trim((string) ($document['publish_date'] ?? '')) === '') {
        $document['publish_date'] = trim((string) ($release['release_date'] ?? '')) ?: gmdate('Y-m-d');
    }
    $document['entries'] = $entries;
    bandpromo_playlist_write_document($root, $document);
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
    if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));

        return [
            'entries' => [],
            'missing' => $requested,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not materialize playlist entries from source audio'),
        ];
    }

    $entries = [];
    foreach (($data['entries'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $file = trim((string) ($entry['file'] ?? ''));
        if ($file !== '') {
            $entries[$file] = $entry;
        }
    }

    $missing = [];
    foreach (($data['missing'] ?? []) as $entry) {
        $file = trim((string) $entry);
        if ($file !== '') {
            $missing[] = $file;
        }
    }

    return [
        'entries' => $entries,
        'missing' => $missing,
        'error' => '',
    ];
}

function bandpromo_playlist_build_track_list(string $root, array $document, array $builtTracks = []): array
{
    if ($builtTracks === []) {
        $builtTracks = bandpromo_playlist_load_built_tracks($root);
    }

    $materializeQueue = [];
    foreach ($document['entries'] as $entry) {
        $masterFile = (string) ($entry['master_file'] ?? '');
        if ($masterFile === '') {
            continue;
        }
        if (!isset($builtTracks[$masterFile])) {
            $materializeQueue[] = $masterFile;
        }
    }

    $materialized = $materializeQueue !== []
        ? bandpromo_playlist_materialize_entries($materializeQueue)
        : ['entries' => [], 'missing' => [], 'error' => ''];

    if ($materialized['error'] !== '') {
        throw new RuntimeException($materialized['error']);
    }

    $tracks = [];
    foreach ($document['entries'] as $entry) {
        $masterFile = (string) ($entry['master_file'] ?? '');
        if ($masterFile === '') {
            continue;
        }
        if (isset($builtTracks[$masterFile])) {
            $tracks[] = $builtTracks[$masterFile];
            continue;
        }
        if (isset($materialized['entries'][$masterFile])) {
            $tracks[] = $materialized['entries'][$masterFile];
        }
    }

    return $tracks;
}

function bandpromo_playlist_sync_legacy_artifacts(string $root, string $playlistId, array $tracks): void
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    $order = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file !== '') {
            $order[] = $file;
        }
    }

    $builtPath = bandpromo_playlist_built_path($root);
    $builtDir = dirname($builtPath);
    if (!is_dir($builtDir) && !mkdir($builtDir, 0755, true) && !is_dir($builtDir)) {
        throw new RuntimeException('Could not create play directory.');
    }

    $encoded = json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || file_put_contents($builtPath, $encoded) === false) {
        throw new RuntimeException('Could not write play/playlist.json');
    }

    if (!bandpromo_json_write_file(bandpromo_playlist_legacy_order_path($root), $order)) {
        throw new RuntimeException('Could not write data/playlist-order.json');
    }
}

function bandpromo_playlist_save_order(string $root, string $playlistId, array $masterFiles): array
{
    $playlistId = bandpromo_playlist_normalize_id($playlistId);
    $document = bandpromo_playlist_load_document($root, $playlistId);
    $builtTracks = bandpromo_playlist_load_built_tracks($root);

    $materializeQueue = [];
    foreach ($masterFiles as $masterFile) {
        $masterFile = basename((string) $masterFile);
        if ($masterFile === '' || isset($builtTracks[$masterFile])) {
            continue;
        }
        $materializeQueue[] = $masterFile;
    }

    if ($materializeQueue !== []) {
        $materialized = bandpromo_playlist_materialize_entries($materializeQueue);
        if ($materialized['error'] !== '') {
            throw new RuntimeException($materialized['error']);
        }
        foreach ($materialized['entries'] as $file => $track) {
            $builtTracks[$file] = $track;
        }
    }

    $entries = [];
    $skipped = [];
    foreach ($masterFiles as $masterFile) {
        $masterFile = basename((string) $masterFile);
        if ($masterFile === '') {
            continue;
        }
        if (!isset($builtTracks[$masterFile])) {
            $skipped[] = $masterFile;
            continue;
        }
        $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
        $entries[] = [
            'master_file' => $masterFile,
            'asset_id' => (string) ($asset['id'] ?? ''),
            'release_id' => (string) ($asset['release_id'] ?? ''),
        ];
    }

    $document['entries'] = $entries;
    bandpromo_playlist_write_document($root, $document);

    $tracks = [];
    foreach ($entries as $entry) {
        $masterFile = (string) ($entry['master_file'] ?? '');
        if ($masterFile !== '' && isset($builtTracks[$masterFile])) {
            $tracks[] = $builtTracks[$masterFile];
        }
    }

    bandpromo_playlist_sync_legacy_artifacts($root, $playlistId, $tracks);

    return [
        'tracks' => $tracks,
        'skipped' => $skipped,
        'count' => count($tracks),
    ];
}

function bandpromo_playlist_release_date_is_public(string $releaseDate, bool $operatorBypass): bool
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

function bandpromo_playlist_enrich_tracks_for_player(string $root, array $tracks, bool $operatorBypass): array
{
    $enriched = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = trim((string) ($track['file'] ?? ''));
        $asset = $masterFile !== '' ? bandpromo_asset_lookup_by_master_filename($root, $masterFile) : null;
        $releaseId = trim((string) ($asset['release_id'] ?? ''));
        if ($releaseId === '') {
            $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
        }

        $release = null;
        $releaseTrack = null;
        try {
            $release = bandpromo_release_load_document($root, $releaseId);
            if ($asset !== null) {
                $releaseTrack = bandpromo_release_track_entry_for_asset($root, $releaseId, (string) $asset['id']);
            }
        } catch (Throwable $throwable) {
            $release = null;
        }

        $releaseSlug = (string) ($release['slug'] ?? $releaseId);
        $releaseDate = (string) ($release['release_date'] ?? '');
        $playable = bandpromo_playlist_release_date_is_public($releaseDate, $operatorBypass);
        $lockReason = $playable ? '' : 'embargoed';

        $enriched[] = array_merge($track, [
            'asset_id' => (string) ($asset['id'] ?? ''),
            'release_id' => $releaseId,
            'release_slug' => $releaseSlug,
            'track_slug' => bandpromo_playlist_track_slug($track, $asset, $releaseTrack),
            'playable' => $playable,
            'lock_reason' => $lockReason,
        ]);
    }

    return $enriched;
}

function bandpromo_playlist_materialize_for_player(string $root, string $playlistId, bool $operatorBypass): array
{
    $document = bandpromo_playlist_load_document($root, $playlistId);
    $tracks = bandpromo_playlist_build_track_list($root, $document);

    return bandpromo_playlist_enrich_tracks_for_player($root, $tracks, $operatorBypass);
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

function bandpromo_playlist_enrich_track_release_meta(string $root, array $row): array
{
    $file = trim((string) ($row['file'] ?? ''));
    if ($file === '') {
        return $row;
    }

    $meta = bandpromo_release_audio_listing_meta($root, $file);
    $releaseTitle = trim((string) ($meta['release_title'] ?? ''));
    if ($releaseTitle !== '') {
        $row['release_title'] = $releaseTitle;
    }
    if (trim((string) ($row['release_id'] ?? '')) === '') {
        $releaseId = bandpromo_release_normalize_id(trim((string) ($meta['release_id'] ?? '')));
        if ($releaseId !== '') {
            $row['release_id'] = $releaseId;
        }
    }

    return $row;
}

function bandpromo_playlist_release_track_order_map(string $root, string $releaseId): array
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        return [];
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return [];
    }

    $order = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $masterFile = bandpromo_release_track_master_filename($root, (string) ($track['asset_id'] ?? ''));
        if ($masterFile === '') {
            continue;
        }
        $order[$masterFile] = (int) ($track['track_number'] ?? 0);
    }

    return $order;
}

function bandpromo_playlist_release_title(string $root, string $releaseId): string
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        return '';
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return '';
    }

    return trim((string) ($document['title'] ?? ''));
}

function bandpromo_playlist_build_pool_track_row(string $root, array $track, string $masterFile): array
{
    $masterFile = basename(trim($masterFile));
    $releaseId = bandpromo_release_id_for_master_filename($root, $masterFile);
    $releaseTitle = bandpromo_playlist_release_title($root, $releaseId);

    $row = bandpromo_release_enrich_track_row_labels(
        $root,
        bandpromo_release_track_row_from_pool($track, $releaseId),
        $releaseTitle
    );

    return bandpromo_playlist_enrich_track_release_meta($root, $row);
}

function bandpromo_playlist_sort_available_tracks(string $root, string $releaseFilter, array $tracks): array
{
    $releaseFilter = bandpromo_playlist_normalize_release_filter($releaseFilter);
    if ($releaseFilter !== 'all' && $releaseFilter !== 'orphans') {
        $orderMap = bandpromo_playlist_release_track_order_map($root, $releaseFilter);
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
        $releaseCompare = strcasecmp(
            (string) ($left['release_title'] ?? $left['release_id'] ?? ''),
            (string) ($right['release_title'] ?? $right['release_id'] ?? '')
        );
        if ($releaseCompare !== 0) {
            return $releaseCompare;
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return $tracks;
}

function bandpromo_playlist_track_row_from_pool(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim((string) ($track['release_id'] ?? $releaseId));
    $isDemoRelease = $resolvedReleaseId === BANDPROMO_RELEASE_DEMO_ID
        || ($resolvedReleaseId === '' && strncmp($file, 'bandPromo_', 10) === 0);

    return [
        'file' => $file,
        'title' => (string) ($track['title'] ?? $file),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => (string) ($track['origin'] ?? ($isDemoRelease ? 'bundled-placeholder' : 'user-upload')),
        'sourceTier' => (string) ($track['sourceTier'] ?? 'master'),
        'deliveryReady' => ($track['deliveryReady'] ?? true) !== false,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_playlist_track_row_from_built(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim($releaseId);
    $isDemoRelease = $resolvedReleaseId === BANDPROMO_RELEASE_DEMO_ID
        || ($resolvedReleaseId === '' && strncmp($file, 'bandPromo_', 10) === 0);

    return [
        'file' => $file,
        'title' => (string) ($track['title'] ?? $file),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => $isDemoRelease ? 'bundled-placeholder' : 'user-upload',
        'sourceTier' => 'playlist-container',
        'deliveryReady' => true,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_playlist_normalize_release_filter(string $value): string
{
    return bandpromo_release_normalize_pool_filter($value);
}

function bandpromo_playlist_track_matches_release_filter(
    string $root,
    string $masterFile,
    string $releaseFilter
): bool {
    $releaseFilter = bandpromo_playlist_normalize_release_filter($releaseFilter);
    if ($releaseFilter === 'all') {
        return true;
    }

    $meta = bandpromo_release_audio_listing_meta($root, $masterFile);
    if ($releaseFilter === 'orphans') {
        return !empty($meta['release_orphan']);
    }

    return bandpromo_release_normalize_id((string) ($meta['release_id'] ?? '')) === $releaseFilter;
}

function bandpromo_playlist_enrich_pool_release_ids(string $root, array $poolByFile): array
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
        $track['release_id'] = bandpromo_release_id_for_master_filename($root, $masterFile);
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
        } elseif (bandpromo_asset_lookup_by_master_filename($root, $masterFile) !== null) {
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
    $releaseFilter = bandpromo_playlist_normalize_release_filter($releaseFilter);

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

        $releaseId = bandpromo_release_id_for_master_filename($root, $file);
        $row = bandpromo_release_enrich_track_row_labels(
            $root,
            bandpromo_playlist_track_row_from_built($builtByFile[$file], $releaseId),
            bandpromo_playlist_release_title($root, $releaseId)
        );
        $activeTracks[] = bandpromo_playlist_enrich_track_release_meta($root, $row);
    }

    $availableTracks = [];
    foreach ($poolByFile as $file => $track) {
        if (isset($activeSet[$file])) {
            continue;
        }
        if (!bandpromo_playlist_track_matches_release_filter($root, $file, $releaseFilter)) {
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
        $entry['poster_preview_url'] = bandpromo_release_resolve_poster_preview_url(
            $root,
            $entry['poster_asset_id']
        );
        $entry['kind'] = strtolower((string) ($document['kind'] ?? $entry['kind'] ?? 'system')) === 'user'
            ? 'user'
            : 'system';
        $entry['track_count'] = count($document['entries'] ?? []);
    } catch (Throwable $throwable) {
        // Keep registry-only fields when the document is missing.
        $entry['slug'] = (string) ($entry['slug'] ?? $playlistId);
    }

    return $entry;
}

function bandpromo_playlist_admin_registry_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entries[] = bandpromo_playlist_admin_registry_entry($root, $entry);
    }

    usort($entries, static function (array $left, array $right): int {
        $leftDate = (string) ($left['publish_date'] ?? '');
        $rightDate = (string) ($right['publish_date'] ?? '');
        $dateCompare = strcmp($rightDate, $leftDate);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

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

function bandpromo_playlist_create_from_release(string $root, string $releaseId): array
{
    require_once __DIR__ . '/release-storage.php';

    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Release id is required.');
    }

    $document = bandpromo_release_load_document($root, $releaseId);
    $title = trim((string) ($document['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Release title is required to create a playlist.');
    }

    $entries = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = trim((string) ($track['asset_id'] ?? ''));
        $masterFile = bandpromo_release_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }
        $entries[] = [
            'master_file' => $masterFile,
            'asset_id' => $assetId,
            'release_id' => $releaseId,
        ];
    }

    if ($entries === []) {
        throw new InvalidArgumentException('Add tracks to the release before creating a playlist.');
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
    ]);

    $playlistDocument = bandpromo_playlist_load_document($root, $playlistId);
    $playlistDocument['entries'] = $entries;
    bandpromo_playlist_write_document($root, $playlistDocument);

    $updated = bandpromo_playlist_registry_entry($root, $playlistId);
    if ($updated === null) {
        throw new RuntimeException('Could not load created playlist.');
    }

    return bandpromo_playlist_admin_registry_entry($root, $updated);
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
        $document['short_description'] = bandpromo_release_normalize_text_field($fields['short_description'], 300);
    }
    if (array_key_exists('description', $fields)) {
        $document['description'] = bandpromo_release_normalize_text_field($fields['description'], 4000);
    }
    if (array_key_exists('poster_asset_id', $fields)) {
        $document['poster_asset_id'] = bandpromo_release_normalize_poster_asset_id($root, $fields['poster_asset_id']);
    }
    bandpromo_playlist_write_document($root, $document);

    $updated = bandpromo_playlist_registry_entry($root, $playlistId);
    if ($updated === null) {
        throw new RuntimeException('Could not load updated playlist.');
    }

    return bandpromo_playlist_admin_registry_entry($root, $updated);
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
}
