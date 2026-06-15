<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/asset-registry.php';

const BANDPROMO_RELEASE_REGISTRY_VERSION = 1;
const BANDPROMO_RELEASE_DEFAULT_ID = 'primary';
const BANDPROMO_RELEASE_DEMO_ID = 'bandpromo-demo';

function bandpromo_release_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'releases';
}

function bandpromo_release_registry_path(string $root): string
{
    return bandpromo_release_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_release_document_path(string $root, string $releaseId): string
{
    $releaseId = bandpromo_release_normalize_id($releaseId);

    return bandpromo_release_storage_root($root) . DIRECTORY_SEPARATOR . $releaseId . '.json';
}

function bandpromo_release_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_release_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/releases directory.');
    }
}

function bandpromo_release_normalize_id(string $releaseId): string
{
    $releaseId = strtolower(trim($releaseId));
    $releaseId = preg_replace('/[^a-z0-9-]+/', '-', $releaseId) ?? '';
    $releaseId = trim($releaseId, '-');

    return substr($releaseId, 0, 48);
}

function bandpromo_release_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = BANDPROMO_RELEASE_DEFAULT_ID;
    }

    return substr($slug, 0, 48);
}

function bandpromo_release_validate_date(string $value): bool
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

function bandpromo_release_normalize_track_entry(array $entry): ?array
{
    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    $trackNumber = (int) ($entry['track_number'] ?? 0);
    if ($trackNumber < 0) {
        $trackNumber = 0;
    }

    $normalized = [
        'asset_id' => $assetId,
        'slug' => trim((string) ($entry['slug'] ?? '')),
        'track_number' => $trackNumber,
        'vip_early_days' => $entry['vip_early_days'] ?? null,
        'availability_override' => $entry['availability_override'] ?? null,
    ];

    if ($normalized['slug'] !== '') {
        $normalized['slug'] = substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($normalized['slug'])) ?? '', 0, 64);
        $normalized['slug'] = trim($normalized['slug'], '-');
    }

    $override = $normalized['availability_override'];
    if ($override !== null && !in_array($override, ['force_public', 'embargo_extended'], true)) {
        $normalized['availability_override'] = null;
    }

    if ($normalized['vip_early_days'] !== null) {
        $normalized['vip_early_days'] = max(0, (int) $normalized['vip_early_days']);
    }

    return $normalized;
}

function bandpromo_release_normalize_document(array $input, ?string $expectedId = null): array
{
    $id = bandpromo_release_normalize_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid release id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $slug = trim((string) ($input['slug'] ?? ''));
    if ($slug === '') {
        $slug = $id;
    }
    $slug = bandpromo_release_normalize_id($slug);

    $releaseDate = trim((string) ($input['release_date'] ?? ''));
    if ($releaseDate === '') {
        $releaseDate = gmdate('Y-m-d');
    }
    if (!bandpromo_release_validate_date($releaseDate)) {
        throw new InvalidArgumentException('Release date must use YYYY or YYYY-MM-DD.');
    }

    $tracks = [];
    $seenAssets = [];
    if (isset($input['tracks']) && is_array($input['tracks'])) {
        foreach ($input['tracks'] as $track) {
            if (!is_array($track)) {
                continue;
            }
            $normalizedTrack = bandpromo_release_normalize_track_entry($track);
            if ($normalizedTrack === null || isset($seenAssets[$normalizedTrack['asset_id']])) {
                continue;
            }
            $seenAssets[$normalizedTrack['asset_id']] = true;
            $tracks[] = $normalizedTrack;
        }
    }

    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'id' => $id,
        'slug' => $slug,
        'title' => $title,
        'release_date' => $releaseDate,
        'locked' => !empty($input['locked']),
        'vip_early_days' => max(0, (int) ($input['vip_early_days'] ?? 7)),
        'tracks' => $tracks,
    ];
}

function bandpromo_release_default_document(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'id' => BANDPROMO_RELEASE_DEFAULT_ID,
        'slug' => BANDPROMO_RELEASE_DEFAULT_ID,
        'title' => 'Primary Release',
        'release_date' => gmdate('Y-m-d'),
        'locked' => false,
        'vip_early_days' => 7,
        'tracks' => [],
    ];
}

function bandpromo_release_default_registry(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'releases' => [
            [
                'id' => BANDPROMO_RELEASE_DEMO_ID,
                'title' => 'bandPromo demo',
                'slug' => BANDPROMO_RELEASE_DEMO_ID,
                'sort_order' => 5,
                'system' => true,
            ],
            [
                'id' => BANDPROMO_RELEASE_DEFAULT_ID,
                'title' => 'Primary Release',
                'slug' => BANDPROMO_RELEASE_DEFAULT_ID,
                'sort_order' => 10,
                'system' => true,
            ],
        ],
    ];
}

function bandpromo_release_normalize_registry(array $input): array
{
    $releases = [];
    $seen = [];
    if (isset($input['releases']) && is_array($input['releases'])) {
        foreach ($input['releases'] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $releases[] = [
                'id' => $id,
                'title' => trim((string) ($entry['title'] ?? ucfirst(str_replace('-', ' ', $id)))),
                'slug' => bandpromo_release_normalize_id((string) ($entry['slug'] ?? $id)),
                'sort_order' => (int) ($entry['sort_order'] ?? ($index + 1) * 10),
                'system' => !empty($entry['system'])
                    || $id === BANDPROMO_RELEASE_DEFAULT_ID
                    || $id === BANDPROMO_RELEASE_DEMO_ID,
            ];
        }
    }

    if ($releases === []) {
        return bandpromo_release_default_registry();
    }

    usort($releases, static fn(array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'releases' => $releases,
    ];
}

function bandpromo_release_write_registry(string $root, array $registry): void
{
    bandpromo_release_registry_ensure_dir($root);
    $normalized = bandpromo_release_normalize_registry($registry);
    if (!bandpromo_json_write_file(bandpromo_release_registry_path($root), $normalized)) {
        throw new RuntimeException('Could not write release registry.');
    }
}

function bandpromo_release_write_document(string $root, array $document): void
{
    bandpromo_release_registry_ensure_dir($root);
    $normalized = bandpromo_release_normalize_document($document);
    if (!bandpromo_json_write_file(bandpromo_release_document_path($root, $normalized['id']), $normalized)) {
        throw new RuntimeException('Could not write release document.');
    }
}

function bandpromo_release_load_registry(string $root): array
{
    bandpromo_release_ensure_seeded($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_release_registry_path($root));
    if ($decoded === null) {
        throw new RuntimeException('Invalid release registry file.');
    }

    return bandpromo_release_normalize_registry($decoded);
}

function bandpromo_release_load_document(string $root, string $releaseId): array
{
    bandpromo_release_ensure_seeded($root);
    $releaseId = bandpromo_release_normalize_id($releaseId);
    $path = bandpromo_release_document_path($root, $releaseId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing release document: data/releases/' . $releaseId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid release document: data/releases/' . $releaseId . '.json');
    }

    return bandpromo_release_normalize_document($decoded, $releaseId);
}

function bandpromo_release_registry_entry(string $root, string $releaseId): ?array
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    foreach (bandpromo_release_load_registry($root)['releases'] as $entry) {
        if (($entry['id'] ?? '') === $releaseId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_release_ensure_seeded(string $root): void
{
    bandpromo_release_registry_ensure_dir($root);
    $registryPath = bandpromo_release_registry_path($root);
    if (!is_file($registryPath)) {
        $registry = bandpromo_release_default_registry();
        $templateRegistry = $root . '/biblioteca/templates/releases.registry.template.json';
        if (is_file($templateRegistry)) {
            $decoded = bandpromo_json_read_array_file($templateRegistry);
            if ($decoded !== null) {
                $registry = bandpromo_release_normalize_registry($decoded);
            }
        }
        bandpromo_release_write_registry($root, $registry);

        $document = bandpromo_release_default_document();
        $templateDocument = $root . '/biblioteca/templates/primary.release.template.json';
        if (is_file($templateDocument)) {
            $decoded = bandpromo_json_read_array_file($templateDocument);
            if ($decoded !== null) {
                try {
                    $document = bandpromo_release_normalize_document($decoded);
                } catch (Throwable $throwable) {
                    $document = bandpromo_release_default_document();
                }
            }
        }
        bandpromo_release_write_document($root, $document);

        bandpromo_release_ensure_demo_release($root);

        return;
    }

    $registry = bandpromo_release_normalize_registry((array) (bandpromo_json_read_array_file($registryPath) ?? []));
    foreach ($registry['releases'] as $entry) {
        $releaseId = (string) ($entry['id'] ?? '');
        if ($releaseId === '') {
            continue;
        }
        $docPath = bandpromo_release_document_path($root, $releaseId);
        if (!is_file($docPath)) {
            $document = bandpromo_release_default_document();
            if ($releaseId === BANDPROMO_RELEASE_DEMO_ID) {
                $document = bandpromo_release_demo_default_document();
            }
            $document['id'] = $releaseId;
            $document['slug'] = (string) ($entry['slug'] ?? $releaseId);
            $document['title'] = (string) ($entry['title'] ?? $document['title']);
            bandpromo_release_write_document($root, $document);
        }
    }

    bandpromo_release_ensure_demo_release($root);
}

function bandpromo_release_demo_default_document(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'id' => BANDPROMO_RELEASE_DEMO_ID,
        'slug' => BANDPROMO_RELEASE_DEMO_ID,
        'title' => 'bandPromo demo',
        'release_date' => '2020-01-01',
        'locked' => true,
        'vip_early_days' => 0,
        'tracks' => [],
    ];
}

function bandpromo_release_registry_entries(string $root): array
{
    return bandpromo_release_load_registry($root)['releases'] ?? [];
}

function bandpromo_release_is_demo_filename(string $filename): bool
{
    return strncmp(basename($filename), 'bandPromo_', 10) === 0;
}

function bandpromo_release_ensure_demo_release(string $root): void
{
    static $running = [];
    if (!empty($running[$root])) {
        return;
    }
    $running[$root] = true;

    try {
        bandpromo_asset_registry_ensure_migrated($root);
        bandpromo_release_registry_ensure_dir($root);

        $registryPath = bandpromo_release_registry_path($root);
        $registry = is_file($registryPath)
            ? bandpromo_release_normalize_registry((array) (bandpromo_json_read_array_file($registryPath) ?? []))
            : bandpromo_release_default_registry();

        $hasDemo = false;
        foreach ($registry['releases'] as $entry) {
            if (($entry['id'] ?? '') === BANDPROMO_RELEASE_DEMO_ID) {
                $hasDemo = true;
                break;
            }
        }

        if (!$hasDemo) {
            $registry['releases'][] = [
                'id' => BANDPROMO_RELEASE_DEMO_ID,
                'title' => 'bandPromo demo',
                'slug' => BANDPROMO_RELEASE_DEMO_ID,
                'sort_order' => 5,
                'system' => true,
            ];
            bandpromo_release_write_registry($root, $registry);
        }

        $docPath = bandpromo_release_document_path($root, BANDPROMO_RELEASE_DEMO_ID);
        if (!is_file($docPath)) {
            $document = bandpromo_release_demo_default_document();
            $templateDocument = $root . '/biblioteca/templates/bandpromo-demo.release.template.json';
            if (is_file($templateDocument)) {
                $decoded = bandpromo_json_read_array_file($templateDocument);
                if ($decoded !== null) {
                    try {
                        $document = bandpromo_release_normalize_document($decoded);
                    } catch (Throwable $throwable) {
                        $document = bandpromo_release_demo_default_document();
                    }
                }
            }
            bandpromo_release_write_document($root, $document);
        }

        bandpromo_release_sync_demo_audio_assets($root);
    } finally {
        unset($running[$root]);
    }
}

function bandpromo_release_sync_demo_audio_assets(string $root): void
{
    $registry = bandpromo_asset_load_registry($root);
    $changed = false;
    $demoAssets = [];

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $masterFilename = basename((string) ($asset['master_filename'] ?? ''));
        if ($masterFilename === '') {
            continue;
        }

        $releaseId = trim((string) ($asset['release_id'] ?? ''));
        if (bandpromo_release_is_demo_filename($masterFilename)) {
            if ($releaseId !== BANDPROMO_RELEASE_DEMO_ID) {
                $registry['assets'][$assetId]['release_id'] = BANDPROMO_RELEASE_DEMO_ID;
                $changed = true;
                $releaseId = BANDPROMO_RELEASE_DEMO_ID;
            }
        } elseif ($releaseId === '') {
            $registry['assets'][$assetId]['release_id'] = BANDPROMO_RELEASE_DEFAULT_ID;
            $changed = true;
            $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
        }

        if ($releaseId === BANDPROMO_RELEASE_DEMO_ID) {
            $demoAssets[] = $registry['assets'][$assetId];
        }
    }

    if ($changed) {
        bandpromo_asset_write_registry($root, $registry);
    }

    usort($demoAssets, static fn(array $left, array $right): int => strnatcasecmp(
        (string) ($left['master_filename'] ?? ''),
        (string) ($right['master_filename'] ?? '')
    ));

    $tracks = [];
    $trackNumber = 1;
    foreach ($demoAssets as $asset) {
        $tracks[] = [
            'asset_id' => (string) ($asset['id'] ?? ''),
            'slug' => trim((string) ($asset['slug'] ?? '')),
            'track_number' => $trackNumber,
        ];
        $trackNumber++;
    }

    $document = bandpromo_release_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
    $document['locked'] = true;
    $document['title'] = 'bandPromo demo';
    $document['tracks'] = $tracks;
    bandpromo_release_write_document($root, $document);
}

function bandpromo_release_id_for_master_filename(string $root, string $masterFilename): string
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $masterFilename);
    if ($asset !== null) {
        $releaseId = trim((string) ($asset['release_id'] ?? ''));
        if ($releaseId !== '') {
            return $releaseId;
        }
    }

    if (bandpromo_release_is_demo_filename($masterFilename)) {
        return BANDPROMO_RELEASE_DEMO_ID;
    }

    return BANDPROMO_RELEASE_DEFAULT_ID;
}

function bandpromo_release_id_for_media_file(string $root, string $target, string $filename): string
{
    if ($target === 'audio') {
        return bandpromo_release_id_for_master_filename($root, $filename);
    }

    if (bandpromo_release_is_demo_filename($filename)) {
        return BANDPROMO_RELEASE_DEMO_ID;
    }

    return BANDPROMO_RELEASE_DEFAULT_ID;
}

function bandpromo_release_normalize_pool_filter(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === 'all') {
        return 'all';
    }

    return bandpromo_release_normalize_id($value);
}

function bandpromo_release_track_entry_for_asset(string $root, string $releaseId, string $assetId): ?array
{
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return null;
    }

    foreach ($document['tracks'] as $track) {
        if (($track['asset_id'] ?? '') === $assetId) {
            return $track;
        }
    }

    return null;
}

function bandpromo_release_find_track_number_for_master(string $root, string $masterFilename): string
{
    $asset = bandpromo_asset_lookup_by_original_filename($root, $masterFilename);
    if ($asset === null) {
        return '';
    }

    $releaseId = trim((string) ($asset['release_id'] ?? ''));
    if ($releaseId === '') {
        $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
    }

    $track = bandpromo_release_track_entry_for_asset($root, $releaseId, (string) $asset['id']);
    if ($track === null) {
        return '';
    }

    $trackNumber = (int) ($track['track_number'] ?? 0);

    return $trackNumber > 0 ? (string) $trackNumber : '';
}

function bandpromo_release_is_master_locked(string $root, string $masterFilename): bool
{
    $asset = bandpromo_asset_lookup_by_original_filename($root, $masterFilename);
    if ($asset === null) {
        return false;
    }

    $releaseId = trim((string) ($asset['release_id'] ?? ''));
    if ($releaseId === '') {
        $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return false;
    }

    return !empty($document['locked']);
}

function bandpromo_release_assert_master_editable(string $root, string $masterFilename): void
{
    if (bandpromo_release_is_master_locked($root, $masterFilename)) {
        throw new RuntimeException('This track belongs to a locked release and cannot be edited.');
    }
}
