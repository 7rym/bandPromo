<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';

const BANDPROMO_ASSET_REGISTRY_VERSION = 1;
const BANDPROMO_ASSET_ID_PREFIX = 'ast_';

function bandpromo_asset_registry_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'assets';
}

function bandpromo_asset_registry_path(string $root): string
{
    return bandpromo_asset_registry_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_asset_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_asset_registry_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/assets directory.');
    }
}

function bandpromo_asset_registry_default(): array
{
    return [
        'version' => BANDPROMO_ASSET_REGISTRY_VERSION,
        'assets' => [],
        'by_master_filename' => [],
    ];
}

function bandpromo_ulid_encode_time(int $timeMs): string
{
    $encoding = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $chars = [];
    for ($i = 9; $i >= 0; $i--) {
        $mod = $timeMs % 32;
        $chars[$i] = $encoding[$mod];
        $timeMs = intdiv($timeMs, 32);
    }

    return implode('', $chars);
}

function bandpromo_generate_ulid(): string
{
    $encoding = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $timeMs = (int) floor(microtime(true) * 1000);
    $ulid = bandpromo_ulid_encode_time($timeMs);

    $random = random_bytes(10);
    for ($i = 0; $i < 10; $i++) {
        $ulid .= $encoding[ord($random[$i]) & 31];
    }

    return $ulid;
}

function bandpromo_generate_asset_id(): string
{
    return BANDPROMO_ASSET_ID_PREFIX . bandpromo_generate_ulid();
}

function bandpromo_asset_is_asset_id(string $value): bool
{
    $value = trim($value);
    if ($value === '' || !str_starts_with($value, BANDPROMO_ASSET_ID_PREFIX)) {
        return false;
    }

    $body = substr($value, strlen(BANDPROMO_ASSET_ID_PREFIX));

    return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{20}$/', $body);
}

function bandpromo_asset_normalize_entry(array $entry): ?array
{
    $id = trim((string) ($entry['id'] ?? ''));
    if (!bandpromo_asset_is_asset_id($id)) {
        return null;
    }

    $kind = strtolower(trim((string) ($entry['kind'] ?? 'audio')));
    if ($kind === '') {
        $kind = 'audio';
    }

    $masterFilename = basename(trim((string) ($entry['master_filename'] ?? '')));
    if ($masterFilename === '' || strpbrk($masterFilename, '/\\') !== false) {
        return null;
    }

    $originalFilename = trim((string) ($entry['original_filename'] ?? ''));
    if ($originalFilename !== '') {
        $originalFilename = basename($originalFilename);
    }

    return [
        'id' => $id,
        'kind' => $kind,
        'original_filename' => $originalFilename,
        'master_filename' => $masterFilename,
        'master_format' => strtolower(trim((string) ($entry['master_format'] ?? pathinfo($masterFilename, PATHINFO_EXTENSION)))),
        'release_id' => trim((string) ($entry['release_id'] ?? '')),
        'slug' => trim((string) ($entry['slug'] ?? '')),
        'display' => is_array($entry['display'] ?? null) ? $entry['display'] : [],
        'tags' => is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [],
        'created_at' => trim((string) ($entry['created_at'] ?? gmdate('c'))),
    ];
}

function bandpromo_asset_normalize_registry(array $input): array
{
    $registry = bandpromo_asset_registry_default();
    $registry['version'] = (int) ($input['version'] ?? BANDPROMO_ASSET_REGISTRY_VERSION);

    $assets = [];
    $byMaster = [];

    if (isset($input['assets']) && is_array($input['assets'])) {
        foreach ($input['assets'] as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (!isset($entry['id']) && is_string($key) && bandpromo_asset_is_asset_id($key)) {
                $entry['id'] = $key;
            }
            $normalized = bandpromo_asset_normalize_entry($entry);
            if ($normalized === null) {
                continue;
            }
            $assets[$normalized['id']] = $normalized;
            $byMaster[$normalized['master_filename']] = $normalized['id'];
        }
    }

    if (isset($input['by_master_filename']) && is_array($input['by_master_filename'])) {
        foreach ($input['by_master_filename'] as $masterFilename => $assetId) {
            $masterFilename = basename((string) $masterFilename);
            $assetId = trim((string) $assetId);
            if ($masterFilename === '' || !bandpromo_asset_is_asset_id($assetId)) {
                continue;
            }
            if (!isset($assets[$assetId])) {
                continue;
            }
            $byMaster[$masterFilename] = $assetId;
        }
    }

    $registry['assets'] = $assets;
    $registry['by_master_filename'] = $byMaster;

    return $registry;
}

function bandpromo_asset_write_registry(string $root, array $registry): void
{
    bandpromo_asset_registry_ensure_dir($root);
    $normalized = bandpromo_asset_normalize_registry($registry);
    if (!bandpromo_json_write_file(bandpromo_asset_registry_path($root), $normalized)) {
        throw new RuntimeException('Could not write asset registry.');
    }
}

function bandpromo_asset_load_registry(string $root): array
{
    bandpromo_asset_registry_ensure_migrated($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_asset_registry_path($root));
    if ($decoded === null) {
        bandpromo_asset_write_registry($root, bandpromo_asset_registry_default());

        return bandpromo_asset_registry_default();
    }

    return bandpromo_asset_normalize_registry($decoded);
}

function bandpromo_asset_lookup_by_master_filename(string $root, string $masterFilename): ?array
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    $assetId = trim((string) ($registry['by_master_filename'][$masterFilename] ?? ''));
    if ($assetId === '' || !isset($registry['assets'][$assetId])) {
        return null;
    }

    return $registry['assets'][$assetId];
}

function bandpromo_asset_lookup_by_original_filename(string $root, string $originalFilename): ?array
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if (basename((string) ($asset['original_filename'] ?? '')) === $originalFilename) {
            return $asset;
        }
    }

    return bandpromo_asset_lookup_by_master_filename($root, $originalFilename);
}

function bandpromo_asset_lookup_by_id(string $root, string $assetId): ?array
{
    $assetId = trim($assetId);
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);

    return $registry['assets'][$assetId] ?? null;
}

function bandpromo_asset_register_audio_master(
    string $root,
    string $originalFilename,
    string $masterFilename,
    string $masterFormat,
    ?string $assetId = null
): array {
    $originalFilename = basename(trim($originalFilename));
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '' || strpbrk($masterFilename, '/\\') !== false) {
        throw new InvalidArgumentException('Invalid master filename.');
    }

    $registry = bandpromo_asset_load_registry($root);
    $existingId = trim((string) ($registry['by_master_filename'][$masterFilename] ?? ''));
    if ($existingId !== '' && isset($registry['assets'][$existingId])) {
        return $registry['assets'][$existingId];
    }

    $assetId = $assetId !== null && bandpromo_asset_is_asset_id($assetId)
        ? $assetId
        : bandpromo_generate_asset_id();

    $entry = [
        'id' => $assetId,
        'kind' => 'audio',
        'original_filename' => $originalFilename,
        'master_filename' => $masterFilename,
        'master_format' => strtolower($masterFormat),
        'release_id' => '',
        'slug' => '',
        'display' => [],
        'tags' => [],
        'created_at' => gmdate('c'),
    ];

    $registry['assets'][$assetId] = $entry;
    $registry['by_master_filename'][$masterFilename] = $assetId;
    bandpromo_asset_write_registry($root, $registry);

    return $entry;
}

function bandpromo_asset_update_entry(string $root, string $assetId, array $changes): array
{
    $registry = bandpromo_asset_load_registry($root);
    $assetId = trim($assetId);
    if (!isset($registry['assets'][$assetId])) {
        throw new InvalidArgumentException('Unknown asset.');
    }

    $entry = $registry['assets'][$assetId];
    foreach (['release_id', 'slug', 'original_filename'] as $key) {
        if (array_key_exists($key, $changes)) {
            $entry[$key] = trim((string) $changes[$key]);
        }
    }
    if (isset($changes['display']) && is_array($changes['display'])) {
        $entry['display'] = $changes['display'];
    }
    if (isset($changes['tags']) && is_array($changes['tags'])) {
        $entry['tags'] = array_values($changes['tags']);
    }

    $normalized = bandpromo_asset_normalize_entry($entry);
    if ($normalized === null) {
        throw new InvalidArgumentException('Invalid asset entry.');
    }

    $registry['assets'][$assetId] = $normalized;
    $registry['by_master_filename'][$normalized['master_filename']] = $assetId;
    bandpromo_asset_write_registry($root, $registry);

    return $normalized;
}

function bandpromo_asset_master_filename_for_ulid(string $assetId, string $format): string
{
    $format = strtolower(trim($format));
    if ($format === '') {
        throw new InvalidArgumentException('Master format is required.');
    }

    return $assetId . '.' . $format;
}

function bandpromo_asset_id_from_master_filename(string $masterFilename): ?string
{
    $base = pathinfo(basename(trim($masterFilename)), PATHINFO_FILENAME);
    if ($base === '' || !bandpromo_asset_is_asset_id($base)) {
        return null;
    }

    return $base;
}

function bandpromo_asset_unregister(string $root, string $assetId): void
{
    $assetId = trim($assetId);
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return;
    }

    $registry = bandpromo_asset_load_registry($root);
    if (!isset($registry['assets'][$assetId])) {
        return;
    }

    $masterFilename = (string) ($registry['assets'][$assetId]['master_filename'] ?? '');
    unset($registry['assets'][$assetId]);
    if ($masterFilename !== '') {
        unset($registry['by_master_filename'][$masterFilename]);
    }

    bandpromo_asset_write_registry($root, $registry);
}

function bandpromo_asset_unregister_by_original_filename(string $root, string $originalFilename): void
{
    $asset = bandpromo_asset_lookup_by_original_filename($root, $originalFilename);
    if ($asset !== null) {
        bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
    }
}

function bandpromo_asset_find_unregistered_master_match(string $root, string $originalFilename): ?array
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return null;
    }

    $sourcePath = $root . '/media/audio/original/' . $originalFilename;
    if (!is_file($sourcePath)) {
        return null;
    }

    $sourceSize = filesize($sourcePath);
    if ($sourceSize === false) {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    $registeredMasters = array_fill_keys(array_keys($registry['by_master_filename']), true);
    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir)) {
        return null;
    }

    $best = null;
    foreach (scandir($masterDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!str_starts_with($entry, BANDPROMO_ASSET_ID_PREFIX)) {
            continue;
        }
        if (isset($registeredMasters[$entry])) {
            continue;
        }

        $path = $masterDir . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }
        $size = filesize($path);
        if ($size === false || $size !== $sourceSize) {
            continue;
        }

        $mtime = filemtime($path);
        if ($best === null || ($mtime !== false && $mtime > $best['mtime'])) {
            $best = [
                'master_filename' => $entry,
                'master_format' => strtolower((string) pathinfo($entry, PATHINFO_EXTENSION)),
                'mtime' => $mtime !== false ? $mtime : 0,
                'asset_id' => (string) (bandpromo_asset_id_from_master_filename($entry) ?? ''),
            ];
        }
    }

    return $best;
}

function bandpromo_asset_prune_unregistered_duplicate_masters(string $root, int $sourceSize, string $keepMasterFilename): int
{
    $keepMasterFilename = basename(trim($keepMasterFilename));
    if ($keepMasterFilename === '' || $sourceSize <= 0) {
        return 0;
    }

    $registry = bandpromo_asset_load_registry($root);
    $registeredMasters = array_fill_keys(array_keys($registry['by_master_filename']), true);
    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir)) {
        return 0;
    }

    $removed = 0;
    foreach (scandir($masterDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === $keepMasterFilename) {
            continue;
        }
        if (!str_starts_with($entry, BANDPROMO_ASSET_ID_PREFIX)) {
            continue;
        }
        if (isset($registeredMasters[$entry])) {
            continue;
        }

        $path = $masterDir . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }
        $size = filesize($path);
        if ($size === false || $size !== $sourceSize) {
            continue;
        }

        if (@unlink($path)) {
            $removed++;
        }
    }

    return $removed;
}

function bandpromo_asset_reconcile_audio_originals(string $root): void
{
    $originalDir = $root . '/media/audio/original';
    if (!is_dir($originalDir)) {
        return;
    }

    $registry = bandpromo_asset_load_registry($root);
    $registryChanged = false;
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $originalFilename = basename((string) ($asset['original_filename'] ?? ''));
        $masterFilename = basename((string) ($asset['master_filename'] ?? ''));
        if ($originalFilename === '' || $masterFilename === '') {
            continue;
        }
        if ($originalFilename !== $masterFilename) {
            continue;
        }
        if (!str_starts_with($originalFilename, BANDPROMO_ASSET_ID_PREFIX)) {
            continue;
        }

        unset($registry['assets'][$assetId]);
        unset($registry['by_master_filename'][$masterFilename]);
        $registryChanged = true;
    }
    if ($registryChanged) {
        bandpromo_asset_write_registry($root, $registry);
    }

    foreach (scandir($originalDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }

        $existing = bandpromo_asset_lookup_by_original_filename($root, $entry);
        if ($existing !== null) {
            $masterFilename = (string) ($existing['master_filename'] ?? '');
            $masterPath = $root . '/media/audio/master/' . $masterFilename;
            if ($masterFilename !== '' && is_file($masterPath)) {
                continue;
            }
        }

        $match = bandpromo_asset_find_unregistered_master_match($root, $entry);
        if ($match === null) {
            continue;
        }

        $assetId = trim((string) ($match['asset_id'] ?? ''));
        if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
            continue;
        }

        try {
            bandpromo_asset_register_audio_master(
                $root,
                $entry,
                (string) $match['master_filename'],
                (string) $match['master_format'],
                $assetId
            );
        } catch (Throwable $throwable) {
            continue;
        }

        $sourcePath = $originalDir . '/' . $entry;
        $sourceSize = is_file($sourcePath) ? filesize($sourcePath) : false;
        if ($sourceSize !== false) {
            bandpromo_asset_prune_unregistered_duplicate_masters(
                $root,
                (int) $sourceSize,
                (string) $match['master_filename']
            );
        }
    }
}

function bandpromo_asset_registry_ensure_migrated(string $root): void
{
    static $done = [];
    if (isset($done[$root])) {
        return;
    }
    $done[$root] = true;

    bandpromo_asset_registry_ensure_dir($root);
    $path = bandpromo_asset_registry_path($root);
    $registry = is_file($path)
        ? bandpromo_asset_normalize_registry((array) (bandpromo_json_read_array_file($path) ?? []))
        : bandpromo_asset_registry_default();

    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir)) {
        if (!is_file($path)) {
            bandpromo_asset_write_registry($root, $registry);
        }

        return;
    }

    $entries = scandir($masterDir);
    if (!is_array($entries)) {
        return;
    }

    $changed = false;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }
        if (str_starts_with($entry, BANDPROMO_ASSET_ID_PREFIX)) {
            continue;
        }
        if (isset($registry['by_master_filename'][$entry])) {
            continue;
        }

        $assetId = bandpromo_asset_id_from_master_filename($entry) ?? bandpromo_generate_asset_id();
        $releaseId = strncmp($entry, 'bandPromo_', 10) === 0 ? 'bandpromo-demo' : 'primary';
        $normalized = bandpromo_asset_normalize_entry([
            'id' => $assetId,
            'kind' => 'audio',
            'original_filename' => $entry,
            'master_filename' => $entry,
            'master_format' => $ext,
            'release_id' => $releaseId,
            'created_at' => gmdate('c'),
        ]);
        if ($normalized === null) {
            continue;
        }

        $registry['assets'][$assetId] = $normalized;
        $registry['by_master_filename'][$entry] = $assetId;
        $changed = true;
    }

    if ($changed || !is_file($path)) {
        bandpromo_asset_write_registry($root, $registry);
    }

    bandpromo_asset_reconcile_audio_originals($root);
}

function bandpromo_audio_catalogued_filenames(string $root): array
{
    $catalogued = [];

    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        $originalName = basename((string) ($asset['original_filename'] ?? ''));
        if ($originalName !== '') {
            $catalogued[$originalName] = true;
        }
    }

    require_once __DIR__ . '/playlist-storage.php';
    bandpromo_playlist_ensure_seeded($root);
    $playlistRegistry = bandpromo_playlist_load_registry($root);
    foreach ($playlistRegistry['playlists'] as $playlistMeta) {
        if (!is_array($playlistMeta)) {
            continue;
        }
        $playlistId = trim((string) ($playlistMeta['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }
        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $masterFile = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
            if ($masterFile !== '') {
                $catalogued[$masterFile] = true;
            }
        }
    }

    return array_keys($catalogued);
}

function bandpromo_audio_is_catalogued(string $root, string $originalFilename): bool
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return false;
    }

    return in_array($originalFilename, bandpromo_audio_catalogued_filenames($root), true);
}

function bandpromo_list_uncatalogued_audio_originals(string $root): array
{
    require_once __DIR__ . '/media-library-state.php';

    $catalogued = array_fill_keys(bandpromo_audio_catalogued_filenames($root), true);
    $originalDir = $root . '/media/audio/original';
    if (!is_dir($originalDir)) {
        return [];
    }

    $items = [];
    foreach (scandir($originalDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $originalDir . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }
        if (bandpromo_media_is_bundled_placeholder($entry)) {
            continue;
        }
        if (isset($catalogued[$entry])) {
            continue;
        }

        $stem = (string) pathinfo($entry, PATHINFO_FILENAME);
        $items[] = [
            'filename' => $entry,
            'display_title' => ucwords(str_replace(['_', '-'], ' ', $stem)),
        ];
    }

    usort($items, static fn(array $left, array $right): int => strnatcasecmp(
        (string) ($left['display_title'] ?? ''),
        (string) ($right['display_title'] ?? '')
    ));

    return $items;
}

function bandpromo_reconcile_uncatalogued_audio_originals(string $root): array
{
    require_once __DIR__ . '/audio-master-helpers.php';
    require_once __DIR__ . '/build-required.php';
    require_once __DIR__ . '/release-storage.php';

    $result = [
        'fixed' => [],
        'failed' => [],
        'changed' => 0,
    ];

    foreach (bandpromo_list_uncatalogued_audio_originals($root) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $filename = basename(trim((string) ($item['filename'] ?? '')));
        if ($filename === '') {
            continue;
        }

        $materialized = bandpromo_materialize_audio_master_from_original($root, $filename);
        if (!empty($materialized['prepared'])) {
            $result['fixed'][] = $filename;
            $result['changed']++;
            continue;
        }

        if (!empty($materialized['attempted'])) {
            $warning = trim((string) ($materialized['warning'] ?? ''));
            $result['failed'][] = [
                'filename' => $filename,
                'display_title' => trim((string) ($item['display_title'] ?? $filename)),
                'error' => $warning !== '' ? $warning : 'Could not register audio asset automatically',
            ];
        }
    }

    if ($result['changed'] > 0) {
        bandpromo_release_sync_primary_audio_assets($root);
        bandpromo_mark_build_required('media_audio_upload');
    }

    return $result;
}
