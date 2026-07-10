<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/site-contact.php';
require_once __DIR__ . '/demo-catalog-state.php';

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

function bandpromo_release_normalize_document(array $input, ?string $expectedId = null, ?string $root = null): array
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
        'short_description' => bandpromo_release_normalize_text_field($input['short_description'] ?? '', 300),
        'catalog_id' => bandpromo_release_normalize_text_field($input['catalog_id'] ?? '', 80),
        'description' => bandpromo_release_normalize_text_field($input['description'] ?? '', 4000),
        'poster_asset_id' => bandpromo_release_normalize_poster_asset_id($root, $input['poster_asset_id'] ?? ''),
        'epk' => bandpromo_release_normalize_epk($input['epk'] ?? []),
        'tracks' => $tracks,
    ];
}

function bandpromo_release_normalize_text_field(mixed $value, int $maxLength): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }

    return $text;
}

function bandpromo_release_normalize_poster_asset_id(?string $root, mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (bandpromo_asset_is_asset_id($value)) {
        return $value;
    }

    $basename = basename($value);
    $stem = pathinfo($basename, PATHINFO_FILENAME);
    if (bandpromo_asset_is_asset_id($stem)) {
        return $stem;
    }

    if ($root !== null && $basename !== '') {
        $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
        if (is_array($asset) && trim((string) ($asset['id'] ?? '')) !== '') {
            return (string) $asset['id'];
        }
    }

    if ($root !== null && preg_match('#^/media/#', $value)) {
        $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $value);
        if (is_file($path)) {
            return $value;
        }
    }

    return '';
}

function bandpromo_release_visual_media_bases(): array
{
    return ['/media/img/original', '/media/photo/original', '/media/special'];
}

function bandpromo_release_poster_filename_candidates(string $reference, ?array $asset = null): array
{
    $candidates = [];
    if (is_array($asset)) {
        foreach (['master_filename', 'original_filename'] as $key) {
            $name = basename(trim((string) ($asset[$key] ?? '')));
            if ($name !== '') {
                $candidates[] = $name;
            }
        }
    }

    $basename = basename(trim($reference));
    if ($basename !== '') {
        $candidates[] = $basename;
    }

    $stem = pathinfo($basename, PATHINFO_FILENAME);
    if ($stem !== '' && bandpromo_asset_is_asset_id($stem)) {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $candidates[] = $stem . '.' . $ext;
        }
    } elseif (bandpromo_asset_is_asset_id(trim($reference))) {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $candidates[] = trim($reference) . '.' . $ext;
        }
    }

    return array_values(array_unique($candidates));
}

function bandpromo_release_resolve_poster_preview_url(string $root, string $posterReference): string
{
    $posterReference = trim($posterReference);
    if ($posterReference === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $posterReference)) {
        return $posterReference;
    }

    if (preg_match('#^/media/#', $posterReference)) {
        $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $posterReference);

        return is_file($path) ? $posterReference : '';
    }

    $asset = bandpromo_asset_is_asset_id($posterReference)
        ? bandpromo_asset_lookup_by_id($root, $posterReference)
        : null;

    foreach (bandpromo_release_poster_filename_candidates($posterReference, $asset) as $filename) {
        foreach (bandpromo_release_visual_media_bases() as $base) {
            $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $base . '/' . $filename);
            if (is_file($path)) {
                return $base . '/' . $filename;
            }
        }
    }

    return '';
}

function bandpromo_release_streaming_link_sort_key(string $label): int
{
    $normalized = strtolower(trim($label));
    if (in_array($normalized, ['bandpromo', 'this site', 'site'], true)) {
        return 0;
    }
    if ($normalized === 'spotify') {
        return 1;
    }
    if (in_array($normalized, ['apple music', 'apple'], true)) {
        return 2;
    }

    return 10;
}

function bandpromo_release_sort_streaming_links(array $links): array
{
    usort($links, static function (array $a, array $b): int {
        $left = bandpromo_release_streaming_link_sort_key((string) ($a['label'] ?? ''));
        $right = bandpromo_release_streaming_link_sort_key((string) ($b['label'] ?? ''));

        return $left <=> $right ?: strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });

    return $links;
}

function bandpromo_release_normalize_streaming_links(mixed $input): array
{
    if (!is_array($input)) {
        return [];
    }

    $links = [];
    $seen = [];
    foreach ($input as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $label = bandpromo_release_normalize_text_field($entry['label'] ?? '', 80);
        $url = trim((string) ($entry['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }
        if (in_array(strtolower($label), ['bandcamp'], true)) {
            continue;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        $key = strtolower($label) . '|' . strtolower($url);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $links[] = [
            'label' => $label,
            'url' => $url,
        ];
        if (count($links) >= 12) {
            break;
        }
    }

    return bandpromo_release_sort_streaming_links($links);
}

function bandpromo_release_normalize_press_photo_asset_ids(mixed $input): array
{
    if (!is_array($input)) {
        return [];
    }

    $assetIds = [];
    $seen = [];
    foreach ($input as $entry) {
        $assetId = trim((string) $entry);
        if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId) || isset($seen[$assetId])) {
            continue;
        }
        $seen[$assetId] = true;
        $assetIds[] = $assetId;
        if (count($assetIds) >= 12) {
            break;
        }
    }

    return $assetIds;
}

function bandpromo_release_normalize_epk(mixed $input): array
{
    if (!is_array($input)) {
        $input = [];
    }

    return [
        'tagline' => bandpromo_release_normalize_text_field($input['tagline'] ?? '', 160),
        'genre' => bandpromo_release_normalize_text_field($input['genre'] ?? '', 120),
        'credits' => bandpromo_release_normalize_text_field($input['credits'] ?? '', 4000),
        'press_contact' => bandpromo_site_contact_store_value((string) ($input['press_contact'] ?? ''), 240),
        'streaming_links' => bandpromo_release_normalize_streaming_links($input['streaming_links'] ?? []),
        'press_photo_asset_ids' => bandpromo_release_normalize_press_photo_asset_ids($input['press_photo_asset_ids'] ?? []),
    ];
}

function bandpromo_release_default_epk(): array
{
    return [
        'tagline' => '',
        'genre' => '',
        'credits' => '',
        'press_contact' => '',
        'streaming_links' => [],
        'press_photo_asset_ids' => [],
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
        'short_description' => '',
        'catalog_id' => '',
        'description' => '',
        'poster_asset_id' => '',
        'epk' => bandpromo_release_default_epk(),
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
    $normalized = bandpromo_release_normalize_document($document, null, $root);
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

    return bandpromo_release_normalize_document($decoded, $releaseId, $root);
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

    $meta = bandpromo_release_audio_listing_meta($root, $masterFilename);
    $releaseId = bandpromo_release_normalize_id(trim((string) ($meta['release_id'] ?? '')));
    if ($releaseId !== '') {
        return $releaseId;
    }

    if (bandpromo_release_is_demo_filename($masterFilename)) {
        return BANDPROMO_RELEASE_DEMO_ID;
    }

    return '';
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
    if ($value === 'orphans') {
        return 'orphans';
    }

    return bandpromo_release_normalize_id($value);
}

function bandpromo_release_audio_listing_meta(string $root, string $filename): array
{
    $filename = basename(trim($filename));
    $empty = [
        'release_id' => '',
        'release_title' => '',
        'release_date' => '',
        'release_orphan' => true,
        'on_release' => false,
    ];

    if ($filename === '') {
        return $empty;
    }

    if (bandpromo_release_is_demo_filename($filename)) {
        $releaseId = BANDPROMO_RELEASE_DEMO_ID;
        try {
            $document = bandpromo_release_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            return $empty;
        }

        return [
            'release_id' => $releaseId,
            'release_title' => trim((string) ($document['title'] ?? '')),
            'release_date' => trim((string) ($document['release_date'] ?? '')),
            'release_orphan' => false,
            'on_release' => true,
        ];
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset === null) {
        return $empty;
    }

    $assetId = trim((string) ($asset['id'] ?? ''));
    $assignedReleaseId = bandpromo_release_normalize_id(trim((string) ($asset['release_id'] ?? '')));
    $onRelease = false;
    $releaseId = '';
    $releaseTitle = '';
    $releaseDate = '';

    // Membership is defined by release document tracks, not asset release_id alone
    // (upload/autofix may set release_id=primary before an operator adds the track).
    $candidateIds = [];
    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $candidateId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
        if ($candidateId === '' || $candidateId === BANDPROMO_RELEASE_DEMO_ID) {
            continue;
        }
        if ($assetId === '' || bandpromo_release_track_entry_for_asset($root, $candidateId, $assetId) === null) {
            continue;
        }
        $candidateIds[] = $candidateId;
    }

    if ($assignedReleaseId !== '' && in_array($assignedReleaseId, $candidateIds, true)) {
        $candidateIds = array_values(array_unique(array_merge(
            [$assignedReleaseId],
            array_values(array_diff($candidateIds, [$assignedReleaseId]))
        )));
    }

    foreach ($candidateIds as $candidateId) {
        try {
            $document = bandpromo_release_load_document($root, $candidateId);
        } catch (Throwable $throwable) {
            continue;
        }

        $onRelease = true;
        $releaseId = $candidateId;
        $releaseTitle = trim((string) ($document['title'] ?? ''));
        $releaseDate = trim((string) ($document['release_date'] ?? ''));
        break;
    }

    $releaseOrphan = !$onRelease || $releaseDate === '' || $releaseTitle === '';

    return [
        'release_id' => $releaseId,
        'release_title' => $releaseTitle,
        'release_date' => $releaseDate,
        'release_orphan' => $releaseOrphan,
        'on_release' => $onRelease,
    ];
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

function bandpromo_release_is_protected_id(string $releaseId): bool
{
    $releaseId = bandpromo_release_normalize_id($releaseId);

    return $releaseId === BANDPROMO_RELEASE_DEFAULT_ID || $releaseId === BANDPROMO_RELEASE_DEMO_ID;
}

function bandpromo_release_is_system_managed(string $releaseId): bool
{
    return bandpromo_release_normalize_id($releaseId) === BANDPROMO_RELEASE_DEMO_ID;
}

function bandpromo_release_admin_registry_entry(string $root, array $registryEntry): array
{
    $releaseId = (string) ($registryEntry['id'] ?? '');
    $entry = $registryEntry;
    $entry['release_date'] = '';
    $entry['locked'] = false;
    $entry['track_count'] = 0;
    $entry['description'] = '';
    $entry['short_description'] = '';
    $entry['catalog_id'] = '';
    $entry['poster_asset_id'] = '';
    $entry['epk'] = bandpromo_release_default_epk();

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
        $entry['release_date'] = (string) ($document['release_date'] ?? '');
        $entry['locked'] = !empty($document['locked']);
        $entry['track_count'] = count($document['tracks'] ?? []);
        $entry['description'] = (string) ($document['description'] ?? '');
        $entry['short_description'] = (string) ($document['short_description'] ?? '');
        $entry['catalog_id'] = (string) ($document['catalog_id'] ?? '');
        $entry['poster_asset_id'] = (string) ($document['poster_asset_id'] ?? '');
        $entry['poster_preview_url'] = bandpromo_release_resolve_poster_preview_url(
            $root,
            $entry['poster_asset_id']
        );
        $entry['slug'] = (string) ($document['slug'] ?? ($entry['slug'] ?? $releaseId));
        $entry['epk'] = is_array($document['epk'] ?? null)
            ? bandpromo_release_normalize_epk($document['epk'])
            : bandpromo_release_default_epk();
    } catch (Throwable $throwable) {
        // Keep registry-only fields when the document is missing.
    }

    return $entry;
}

function bandpromo_release_visible_in_admin_catalog(string $root, array $entry): bool
{
    $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
    if ($releaseId === BANDPROMO_RELEASE_DEMO_ID && !bandpromo_demo_catalog_is_visible($root)) {
        return false;
    }
    if ($releaseId !== BANDPROMO_RELEASE_DEFAULT_ID) {
        return true;
    }
    if ((int) ($entry['track_count'] ?? 0) > 0) {
        return true;
    }

    foreach (bandpromo_release_registry_entries($root) as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $candidateId = bandpromo_release_normalize_id((string) ($candidate['id'] ?? ''));
        if ($candidateId === '' || $candidateId === BANDPROMO_RELEASE_DEFAULT_ID || $candidateId === BANDPROMO_RELEASE_DEMO_ID) {
            continue;
        }

        return false;
    }

    return true;
}

function bandpromo_release_admin_registry_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $adminEntry = bandpromo_release_admin_registry_entry($root, $entry);
        if (!bandpromo_release_visible_in_admin_catalog($root, $adminEntry)) {
            continue;
        }
        $entries[] = $adminEntry;
    }

    usort($entries, static function (array $left, array $right): int {
        $leftDate = (string) ($left['release_date'] ?? '');
        $rightDate = (string) ($right['release_date'] ?? '');
        $dateCompare = strcmp($rightDate, $leftDate);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return $entries;
}

function bandpromo_release_track_master_filename(string $root, string $assetId): string
{
    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    if ($asset === null) {
        return '';
    }

    return basename(trim((string) ($asset['master_filename'] ?? '')));
}

function bandpromo_release_track_row_from_pool(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim((string) ($track['release_id'] ?? $releaseId));
    $isDemoRelease = $resolvedReleaseId === BANDPROMO_RELEASE_DEMO_ID
        || ($resolvedReleaseId === '' && strncmp($file, 'bandPromo_', 10) === 0);

    return [
        'file' => $file,
        'asset_id' => trim((string) ($track['asset_id'] ?? '')),
        'title' => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($track['title'] ?? $file))),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => (string) ($track['origin'] ?? ($isDemoRelease ? 'bundled-placeholder' : 'user-upload')),
        'sourceTier' => (string) ($track['sourceTier'] ?? 'master'),
        'deliveryReady' => ($track['deliveryReady'] ?? true) !== false,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_release_pool_map_with_asset_aliases(string $root, array $poolByFile): array
{
    $map = $poolByFile;
    bandpromo_asset_registry_ensure_migrated($root);

    foreach (bandpromo_asset_load_registry($root)['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        $originalFile = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($masterFile === '') {
            continue;
        }

        $poolTrack = null;
        if (isset($poolByFile[$masterFile])) {
            $poolTrack = $poolByFile[$masterFile];
        } elseif ($originalFile !== '' && isset($poolByFile[$originalFile])) {
            $poolTrack = $poolByFile[$originalFile];
        }

        if ($poolTrack === null) {
            continue;
        }

        $row = $poolTrack;
        $row['file'] = $masterFile;
        $row['asset_id'] = (string) ($asset['id'] ?? '');
        $map[$masterFile] = $row;
        if ($originalFile !== '') {
            $map[$originalFile] = $row;
        }
    }

    return $map;
}

function bandpromo_release_title_looks_like_asset_id(string $title, string $masterFile = ''): bool
{
    $normalized = strtolower(trim($title));
    if ($normalized === '') {
        return false;
    }

    if (preg_match('/^ast_[a-z0-9]+$/', $normalized) === 1) {
        return true;
    }

    $masterStem = strtolower(pathinfo(basename(trim($masterFile)), PATHINFO_FILENAME));
    if ($masterStem !== '' && $normalized === $masterStem) {
        return preg_match('/^ast_[a-z0-9]+$/', $masterStem) === 1;
    }

    return false;
}

function bandpromo_release_normalize_display_title(string $title): string
{
    $title = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) $title));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/^\d+\.\s+/', '', $title) ?? $title;
    $title = preg_replace('/^\d{1,2}\s+(?=[A-Za-z])/', '', $title) ?? $title;

    return trim($title);
}

function bandpromo_release_title_needs_metadata_refresh(string $title, string $masterFile): bool
{
    $title = trim($title);
    if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $masterFile)) {
        return true;
    }

    if (preg_match('/^\d+\.\s+/', $title) === 1) {
        return true;
    }

    return strlen($title) > 48 && preg_match('/^\d+\s+/', $title) === 1;
}

function bandpromo_release_split_audio_title_parts(string $value): array
{
    $combined = trim(str_replace(["\r\n", "\r", "\n"], ' ', $value));
    if ($combined === '') {
        return ['title' => '', 'version' => ''];
    }

    if (preg_match('/^(.*?)\s*\[([^\[\]]+)\]$/', $combined, $matches) === 1) {
        $baseTitle = trim((string) ($matches[1] ?? ''));
        $version = trim((string) ($matches[2] ?? ''));
        if ($baseTitle !== '' && $version !== '') {
            return ['title' => $baseTitle, 'version' => $version];
        }
    }

    return ['title' => $combined, 'version' => ''];
}

function bandpromo_release_track_title_looks_messy(string $title, string $masterFile = ''): bool
{
    if (bandpromo_release_title_needs_metadata_refresh($title, $masterFile)) {
        return true;
    }

    $title = trim($title);
    if ($title === '') {
        return false;
    }

    if (preg_match('/\s+\d{1,2}[AB](?:#|b)?\s+\d{2,3}$/i', $title) === 1) {
        return true;
    }
    if (preg_match('/\s+\d{1,2}[AB](?:#|b)?$/i', $title) === 1) {
        return true;
    }

    return false;
}

function bandpromo_release_inspect_master_metadata(string $root, string $masterFile): array
{
    static $cache = [];

    $masterFile = basename(trim($masterFile));
    if ($masterFile === '') {
        return [];
    }
    if (array_key_exists($masterFile, $cache)) {
        return $cache[$masterFile];
    }

    require_once __DIR__ . '/light-build-tasks.php';
    $result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
        'action' => 'inspect',
        'filename' => $masterFile,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    $cache[$masterFile] = $data;

    return $data;
}

function bandpromo_release_polish_track_title(string $title, string $artist = '', string $releaseTitle = ''): string
{
    $title = bandpromo_release_normalize_display_title($title);
    foreach ([$artist, $releaseTitle] as $prefix) {
        $prefix = trim($prefix);
        if ($prefix === '') {
            continue;
        }
        if (strcasecmp($title, $prefix) === 0) {
            continue;
        }
        if (stripos($title, $prefix) === 0) {
            $remainder = trim(substr($title, strlen($prefix)));
            $remainder = ltrim($remainder, "-–— \t");
            if ($remainder !== '' && !str_starts_with($remainder, '[')) {
                $title = $remainder;
            }
        }
    }

    return trim($title);
}

function bandpromo_release_resolve_track_display_labels(
    string $rawTitle,
    string $artist = '',
    string $releaseTitle = ''
): array {
    $normalized = bandpromo_release_normalize_display_title($rawTitle);
    if ($normalized === '') {
        return ['title' => 'Untitled', 'version' => ''];
    }

    $forSplit = bandpromo_release_polish_track_title($normalized, $artist, '');
    $parts = bandpromo_release_split_audio_title_parts($forSplit);
    $displayTitle = trim((string) ($parts['title'] ?? ''));
    $displayVersion = trim((string) ($parts['version'] ?? ''));

    if ($displayTitle !== '' && $releaseTitle !== '') {
        $polishedBase = bandpromo_release_polish_track_title($displayTitle, '', $releaseTitle);
        if ($polishedBase !== '' && strcasecmp($polishedBase, $displayTitle) !== 0) {
            $displayTitle = $polishedBase;
        }
    }

    return [
        'title' => $displayTitle !== '' ? $displayTitle : 'Untitled',
        'version' => $displayVersion,
    ];
}

function bandpromo_asset_build_audio_display_from_inspect(array $inspect, string $releaseTitle = ''): array
{
    $artist = trim((string) ($inspect['artist'] ?? ''));
    $rawTitle = trim((string) ($inspect['title'] ?? ''));
    $labels = bandpromo_release_resolve_track_display_labels($rawTitle, $artist, $releaseTitle);

    return [
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $artist,
        'album' => trim((string) ($inspect['album'] ?? '')),
        'duration' => max(0, (int) ($inspect['duration_seconds'] ?? $inspect['duration'] ?? 0)),
        'synced_at' => gmdate('c'),
     ];
}

function bandpromo_asset_build_audio_display_from_fields(array $fields, array $inspectData = []): array
{
    $artist = trim((string) ($fields['artist'] ?? ''));
    $album = trim((string) ($fields['album'] ?? ''));
    $rawTitle = trim((string) ($fields['title'] ?? ''));
    $labels = bandpromo_release_resolve_track_display_labels($rawTitle, $artist, $album);

    return [
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $artist,
        'album' => $album,
        'duration' => max(0, (int) ($inspectData['duration_seconds'] ?? $inspectData['duration'] ?? 0)),
        'synced_at' => gmdate('c'),
    ];
}

function bandpromo_asset_sync_audio_display_from_fields(
    string $root,
    string $filename,
    array $fields,
    array $inspectData = []
): void {
    $filename = basename(trim($filename));
    if ($filename === '') {
        return;
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename);
    if ($asset === null) {
        return;
    }

    bandpromo_asset_update_entry($root, (string) $asset['id'], [
        'display' => bandpromo_asset_build_audio_display_from_fields($fields, $inspectData),
    ]);
}

function bandpromo_asset_refresh_audio_display(string $root, string $masterFile, string $releaseTitle = ''): bool
{
    $masterFile = basename(trim($masterFile));
    if ($masterFile === '') {
        return false;
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
    if ($asset === null) {
        return false;
    }

    $inspect = bandpromo_release_inspect_master_metadata($root, $masterFile);
    $display = bandpromo_asset_build_audio_display_from_inspect($inspect, $releaseTitle);
    if (trim((string) ($display['title'] ?? '')) === '') {
        return false;
    }

    bandpromo_asset_update_entry($root, (string) $asset['id'], ['display' => $display]);

    return true;
}

function bandpromo_asset_refresh_all_audio_displays(string $root): array
{
    bandpromo_asset_registry_ensure_migrated($root);

    $changed = 0;
    $items = [];
    foreach (bandpromo_asset_load_registry($root)['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFile === '') {
            continue;
        }

        if (bandpromo_asset_refresh_audio_display($root, $masterFile)) {
            $changed++;
            $items[] = $masterFile;
        }
    }

    return [
        'changed' => $changed,
        'items' => $items,
    ];
}

function bandpromo_release_enrich_track_row_labels(string $root, array $row, string $releaseTitle = ''): array
{
    $masterFile = basename(trim((string) ($row['file'] ?? '')));
    if ($masterFile === '') {
        return $row;
    }

    $asset = null;
    $assetId = trim((string) ($row['asset_id'] ?? ''));
    if ($assetId !== '') {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    }
    if ($asset === null) {
        $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
    }

    $display = bandpromo_asset_read_audio_display($asset);

    $artist = trim((string) ($row['artist'] ?? ''));
    if ($artist === '' && $display['artist'] !== '') {
        $artist = $display['artist'];
    }

    $duration = (int) ($row['duration'] ?? 0);
    if ($duration <= 0 && $display['duration'] > 0) {
        $duration = $display['duration'];
    }

    if ($display['title'] !== '') {
        $row['title'] = $display['title'];
        $row['version'] = $display['version'];
    } else {
        $rawTitle = trim((string) ($row['title'] ?? ''));
        if ($rawTitle === '') {
            $rawTitle = $masterFile;
        }
        $labels = bandpromo_release_resolve_track_display_labels($rawTitle, $artist, $releaseTitle);
        $row['title'] = $labels['title'];
        $row['version'] = $labels['version'];
    }

    $row['artist'] = $artist;
    $row['duration'] = $duration;

    return $row;
}

function bandpromo_release_enrich_editor_tracks(string $root, array $tracks): array
{
    $enriched = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $enriched[] = bandpromo_release_enrich_track_row_labels($root, $track);
    }

    return $enriched;
}

function bandpromo_release_sync_member_audio_tags(string $root, string $releaseId): int
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '' || bandpromo_release_is_system_managed($releaseId)) {
        return 0;
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return 0;
    }

    if (!empty($document['locked'])) {
        return 0;
    }

    $releaseTitle = trim((string) ($document['title'] ?? ''));
    if ($releaseTitle === '') {
        return 0;
    }

    $releaseDate = trim((string) ($document['release_date'] ?? ''));
    require_once __DIR__ . '/light-build-tasks.php';
    require_once __DIR__ . '/build-required.php';

    $synced = 0;
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }

        $assetId = (string) ($track['asset_id'] ?? '');
        $masterFile = bandpromo_release_track_master_filename($root, $assetId);
        if ($masterFile === '' || bandpromo_release_is_demo_filename($masterFile)) {
            continue;
        }

        $inspect = bandpromo_release_inspect_master_metadata($root, $masterFile);
        $fields = [
            'title' => bandpromo_release_normalize_display_title((string) ($inspect['title'] ?? '')),
            'artist' => trim((string) ($inspect['artist'] ?? '')),
            'album' => $releaseTitle,
            'date' => $releaseDate,
            'tracknumber' => (string) max(1, (int) ($track['track_number'] ?? 0)),
            'bpm' => trim((string) ($inspect['bpm'] ?? '')),
            'initialkey' => trim((string) ($inspect['initialkey'] ?? '')),
            'genre' => trim((string) ($inspect['genre'] ?? '')),
            'comment' => trim((string) ($inspect['comment'] ?? '')),
            'lyrics' => trim((string) ($inspect['lyrics'] ?? '')),
        ];
        if ($fields['title'] === '') {
            continue;
        }

        $result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
            'action' => 'update',
            'filename' => $masterFile,
            'fields' => $fields,
        ]);
        if (!empty($result['ok'])) {
            bandpromo_asset_refresh_audio_display($root, $masterFile, $releaseTitle);
            $synced++;
        }
    }

    if ($synced > 0) {
        bandpromo_mark_build_required('release_tags_sync');
    }

    return $synced;
}

function bandpromo_release_pool_map_from_asset_registry(string $root, array $poolByFile): array
{
    $map = bandpromo_release_pool_map_with_asset_aliases($root, $poolByFile);
    bandpromo_asset_registry_ensure_migrated($root);

    foreach (bandpromo_asset_load_registry($root)['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFile === '') {
            continue;
        }

        $releaseId = trim((string) ($asset['release_id'] ?? ''));
        if ($releaseId === '') {
            $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
        }

        $row = bandpromo_release_track_row_from_asset($root, $asset, $releaseId);
        $row['file'] = $masterFile;
        $row['asset_id'] = (string) ($asset['id'] ?? '');

        $originalFile = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFile !== '' && isset($poolByFile[$originalFile]) && is_array($poolByFile[$originalFile])) {
            $fromOriginal = bandpromo_release_track_row_from_pool($poolByFile[$originalFile], $releaseId);
            foreach (['title', 'artist', 'album', 'duration'] as $key) {
                if ($key === 'duration') {
                    if ((int) ($row[$key] ?? 0) > 0) {
                        continue;
                    }
                } elseif (trim((string) ($row[$key] ?? '')) !== '') {
                    continue;
                }
                $value = trim((string) ($fromOriginal[$key] ?? ''));
                if ($value !== '') {
                    $row[$key] = $fromOriginal[$key];
                }
            }
        }

        $map[$masterFile] = $row;
        if ($originalFile !== '') {
            $map[$originalFile] = $row;
        }
    }

    return $map;
}

function bandpromo_release_pool_map_canonical(string $root, array $poolByFile): array
{
    $aliased = bandpromo_release_pool_map_from_asset_registry($root, $poolByFile);
    $canonical = [];
    foreach ($aliased as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = basename(trim((string) ($track['file'] ?? '')));
        if ($file === '') {
            continue;
        }
        $track['file'] = $file;
        $canonical[$file] = $track;
    }

    return $canonical;
}

function bandpromo_release_track_display_from_asset(array $asset, string $masterFile): array
{
    $display = bandpromo_asset_read_audio_display($asset);
    $title = $display['title'];
    $artist = $display['artist'];
    $album = $display['album'];
    $version = $display['version'];
    $duration = $display['duration'];

    if ($title === '') {
        $title = trim((string) ($asset['display_title'] ?? ''));
    }

    $originalFile = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $masterFile)) {
        if ($originalFile !== '') {
            $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($originalFile, PATHINFO_FILENAME)));
        }
    }
    if ($title === '') {
        $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($masterFile, PATHINFO_FILENAME)));
    }

    return [
        'title' => bandpromo_release_normalize_display_title($title),
        'version' => $version,
        'artist' => $artist,
        'album' => $album,
        'duration' => $duration,
    ];
}

function bandpromo_release_track_row_from_asset(string $root, array $asset, string $releaseId): array
{
    $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
    $labels = bandpromo_release_track_display_from_asset($asset, $masterFile);

    return [
        'file' => $masterFile,
        'asset_id' => (string) ($asset['id'] ?? ''),
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $labels['artist'],
        'album' => $labels['album'],
        'duration' => $labels['duration'],
        'origin' => bandpromo_release_is_demo_filename($masterFile) ? 'bundled-placeholder' : 'user-upload',
        'sourceTier' => 'release-container',
        'deliveryReady' => true,
        'release_id' => $releaseId,
    ];
}

function bandpromo_release_admin_editor_state(
    string $root,
    string $releaseId,
    array $poolByFile,
    array $meta = []
): array {
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
    }

    $document = bandpromo_release_load_document($root, $releaseId);
    $releaseTitle = trim((string) ($document['title'] ?? ''));
    $activeFiles = [];
    $activeTracks = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = (string) ($track['asset_id'] ?? '');
        $masterFile = bandpromo_release_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }
        $activeFiles[$masterFile] = true;
        $asset = $assetId !== '' ? bandpromo_asset_lookup_by_id($root, $assetId) : null;
        if (isset($poolByFile[$masterFile])) {
            $row = bandpromo_release_track_row_from_pool($poolByFile[$masterFile], $releaseId);
            if ($asset !== null) {
                $fromAsset = bandpromo_release_track_row_from_asset($root, $asset, $releaseId);
                if (bandpromo_release_title_looks_like_asset_id((string) ($row['title'] ?? ''), $masterFile)) {
                    $row['title'] = $fromAsset['title'];
                }
                if (trim((string) ($row['artist'] ?? '')) === '' && trim((string) ($fromAsset['artist'] ?? '')) !== '') {
                    $row['artist'] = $fromAsset['artist'];
                }
                if (trim((string) ($row['album'] ?? '')) === '' && trim((string) ($fromAsset['album'] ?? '')) !== '') {
                    $row['album'] = $fromAsset['album'];
                }
                if ((int) ($row['duration'] ?? 0) <= 0 && (int) ($fromAsset['duration'] ?? 0) > 0) {
                    $row['duration'] = $fromAsset['duration'];
                }
                $row['asset_id'] = (string) ($asset['id'] ?? $assetId);
            }
        } else {
            $row = $asset !== null
                ? bandpromo_release_track_row_from_asset($root, $asset, $releaseId)
                : [
                    'file' => $masterFile,
                    'asset_id' => $assetId,
                    'title' => $masterFile,
                    'artist' => '',
                    'album' => '',
                    'duration' => 0,
                    'origin' => 'user-upload',
                    'sourceTier' => 'release-container',
                    'deliveryReady' => true,
                    'release_id' => $releaseId,
                ];
        }
        $row['track_number'] = (int) ($track['track_number'] ?? 0);
        $activeTracks[] = bandpromo_release_enrich_track_row_labels($root, $row, $releaseTitle);
    }

    $availableTracks = [];
    foreach ($poolByFile as $file => $track) {
        if (isset($activeFiles[$file])) {
            continue;
        }
        if (bandpromo_release_is_demo_filename((string) $file)) {
            continue;
        }
        $trackReleaseId = bandpromo_release_normalize_id(
            bandpromo_release_id_for_master_filename($root, (string) $file)
        );
        if ($trackReleaseId !== '' && $trackReleaseId !== $releaseId) {
            continue;
        }
        $availableTracks[] = bandpromo_release_enrich_track_row_labels(
            $root,
            bandpromo_release_track_row_from_pool($track, $trackReleaseId !== '' ? $trackReleaseId : $releaseId),
            $releaseTitle
        );
    }
    usort($availableTracks, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['file'] ?? ''), (string) ($right['file'] ?? ''));
    });

    return [
        'ok' => true,
        'release_id' => $releaseId,
        'locked' => !empty($document['locked']),
        'system_managed' => bandpromo_release_is_system_managed($releaseId),
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
        'previewSource' => (string) ($meta['previewSource'] ?? 'release-container'),
    ];
}

function bandpromo_release_append_track_to_document(string $root, string $releaseId, string $assetId): void
{
    if ($assetId === '') {
        return;
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return;
    }

    foreach ($document['tracks'] as $track) {
        if (($track['asset_id'] ?? '') === $assetId) {
            return;
        }
    }

    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    $maxNumber = 0;
    foreach ($document['tracks'] as $track) {
        $maxNumber = max($maxNumber, (int) ($track['track_number'] ?? 0));
    }

    $document['tracks'][] = [
        'asset_id' => $assetId,
        'slug' => trim((string) ($asset['slug'] ?? '')),
        'track_number' => $maxNumber + 1,
    ];
    bandpromo_release_write_document($root, $document);
}

function bandpromo_release_remove_asset_from_document(string $root, string $releaseId, string $assetId): void
{
    if ($assetId === '') {
        return;
    }

    try {
        $document = bandpromo_release_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return;
    }

    $before = count($document['tracks']);
    $document['tracks'] = array_values(array_filter(
        $document['tracks'],
        static fn(array $track): bool => ($track['asset_id'] ?? '') !== $assetId
    ));
    if (count($document['tracks']) === $before) {
        return;
    }

    $trackNumber = 1;
    foreach ($document['tracks'] as $index => $track) {
        $document['tracks'][$index]['track_number'] = $trackNumber;
        $trackNumber++;
    }

    bandpromo_release_write_document($root, $document);
}

function bandpromo_release_save_tracks(string $root, string $releaseId, array $masterFiles): array
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Release id is required.');
    }

    $document = bandpromo_release_load_document($root, $releaseId);
    if (!empty($document['locked'])) {
        throw new RuntimeException('This release is locked and cannot be edited.');
    }

    $tracks = [];
    $skipped = [];
    $assetIds = [];
    $trackNumber = 1;
    foreach ($masterFiles as $masterFile) {
        $masterFile = basename((string) $masterFile);
        if ($masterFile === '') {
            continue;
        }

        $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
        if ($asset === null) {
            $asset = bandpromo_asset_lookup_by_original_filename($root, $masterFile);
        }
        if ($asset === null) {
            $skipped[] = $masterFile;
            continue;
        }

        $assetId = (string) ($asset['id'] ?? '');
        if ($assetId === '' || isset($assetIds[$assetId])) {
            continue;
        }
        $assetIds[$assetId] = true;

        $tracks[] = [
            'asset_id' => $assetId,
            'slug' => trim((string) ($asset['slug'] ?? '')),
            'track_number' => $trackNumber,
        ];
        $trackNumber++;
    }

    $oldAssetIds = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = (string) ($track['asset_id'] ?? '');
        if ($assetId !== '') {
            $oldAssetIds[$assetId] = true;
        }
    }

    foreach (array_keys($assetIds) as $assetId) {
        $currentReleaseId = trim((string) (bandpromo_asset_lookup_by_id($root, $assetId)['release_id'] ?? ''));
        if ($currentReleaseId !== '' && $currentReleaseId !== $releaseId) {
            bandpromo_release_remove_asset_from_document($root, $currentReleaseId, $assetId);
        }
    }

    $document['tracks'] = $tracks;
    bandpromo_release_write_document($root, $document);

    $registry = bandpromo_asset_load_registry($root);
    $registryChanged = false;
    foreach (array_keys($assetIds) as $assetId) {
        if (!isset($registry['assets'][$assetId]) || !is_array($registry['assets'][$assetId])) {
            continue;
        }
        if ((string) ($registry['assets'][$assetId]['release_id'] ?? '') !== $releaseId) {
            $registry['assets'][$assetId]['release_id'] = $releaseId;
            $registryChanged = true;
        }
    }

    foreach (array_keys($oldAssetIds) as $assetId) {
        if (isset($assetIds[$assetId])) {
            continue;
        }
        if (!isset($registry['assets'][$assetId]) || !is_array($registry['assets'][$assetId])) {
            continue;
        }
        if ((string) ($registry['assets'][$assetId]['release_id'] ?? '') !== BANDPROMO_RELEASE_DEFAULT_ID) {
            $registry['assets'][$assetId]['release_id'] = BANDPROMO_RELEASE_DEFAULT_ID;
            $registryChanged = true;
        }
    }

    if ($registryChanged) {
        bandpromo_asset_write_registry($root, $registry);
    }

    foreach (array_keys($oldAssetIds) as $assetId) {
        if (isset($assetIds[$assetId])) {
            continue;
        }
        if ($releaseId !== BANDPROMO_RELEASE_DEFAULT_ID) {
            bandpromo_release_append_track_to_document($root, BANDPROMO_RELEASE_DEFAULT_ID, $assetId);
        }
    }

    $tagsSynced = bandpromo_release_sync_member_audio_tags($root, $releaseId);

    return [
        'tracks' => $tracks,
        'skipped' => $skipped,
        'count' => count($tracks),
        'tags_synced' => $tagsSynced,
    ];
}

function bandpromo_release_create(string $root, string $title, string $preferredId = ''): array
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Release name is required.');
    }

    $registry = bandpromo_release_load_registry($root);
    $baseId = $preferredId !== ''
        ? bandpromo_release_normalize_id($preferredId)
        : bandpromo_release_slug_from_title($title);
    if ($baseId === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        throw new InvalidArgumentException('Release id is invalid. Use lowercase letters, numbers, and hyphens.');
    }

    $id = $baseId;
    $suffix = 2;
    $existing = [];
    foreach ($registry['releases'] as $entry) {
        $existing[(string) ($entry['id'] ?? '')] = true;
    }
    while (isset($existing[$id])) {
        $id = substr($baseId, 0, 44) . '-' . $suffix;
        $suffix++;
    }

    $maxOrder = 0;
    foreach ($registry['releases'] as $entry) {
        $maxOrder = max($maxOrder, (int) ($entry['sort_order'] ?? 0));
    }

    $registry['releases'][] = [
        'id' => $id,
        'title' => $title,
        'slug' => $id,
        'sort_order' => $maxOrder + 10,
        'system' => false,
    ];
    bandpromo_release_write_registry($root, $registry);

    $document = bandpromo_release_default_document();
    $document['id'] = $id;
    $document['slug'] = $id;
    $document['title'] = $title;
    $document['tracks'] = [];
    bandpromo_release_write_document($root, $document);

    return bandpromo_release_admin_registry_entry($root, bandpromo_release_registry_entry($root, $id) ?? []);
}

function bandpromo_release_update_details(string $root, string $releaseId, array $fields): array
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Release id is required.');
    }

    if (bandpromo_release_is_system_managed($releaseId)) {
        throw new InvalidArgumentException('The bandPromo demo release is managed automatically.');
    }

    $title = trim((string) ($fields['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Release name is required.');
    }

    $releaseDate = trim((string) ($fields['release_date'] ?? ''));
    if (!bandpromo_release_validate_date($releaseDate)) {
        throw new InvalidArgumentException('Release date must use YYYY or YYYY-MM-DD.');
    }

    $locked = array_key_exists('locked', $fields) ? !empty($fields['locked']) : null;

    $registry = bandpromo_release_load_registry($root);
    $found = false;
    foreach ($registry['releases'] as $index => $entry) {
        if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $releaseId) {
            continue;
        }
        $registry['releases'][$index]['title'] = $title;
        $found = true;
        break;
    }
    if (!$found) {
        throw new InvalidArgumentException('Unknown release.');
    }
    bandpromo_release_write_registry($root, $registry);

    $document = bandpromo_release_load_document($root, $releaseId);
    $document['title'] = $title;
    $document['release_date'] = $releaseDate;
    if ($locked !== null) {
        $document['locked'] = $locked;
    }
    if (array_key_exists('short_description', $fields)) {
        $document['short_description'] = bandpromo_release_normalize_text_field($fields['short_description'], 300);
    }
    if (array_key_exists('catalog_id', $fields)) {
        $document['catalog_id'] = bandpromo_release_normalize_text_field($fields['catalog_id'], 80);
    }
    if (array_key_exists('description', $fields)) {
        $document['description'] = bandpromo_release_normalize_text_field($fields['description'], 4000);
    }
    if (array_key_exists('poster_asset_id', $fields)) {
        $document['poster_asset_id'] = bandpromo_release_normalize_poster_asset_id($root, $fields['poster_asset_id']);
    }
    if (array_key_exists('epk', $fields)) {
        $pressContact = bandpromo_site_contact_sanitize_input((string) (($fields['epk']['press_contact'] ?? '') ?: ''));
        if ($pressContact !== '' && !bandpromo_site_contact_is_valid($pressContact)) {
            throw new InvalidArgumentException(bandpromo_site_contact_invalid_message());
        }
        $document['epk'] = bandpromo_release_normalize_epk($fields['epk']);
    }
    bandpromo_release_write_document($root, $document);

    bandpromo_release_sync_member_audio_tags($root, $releaseId);

    $updated = bandpromo_release_registry_entry($root, $releaseId);
    if ($updated === null) {
        throw new RuntimeException('Could not load updated release.');
    }

    return bandpromo_release_admin_registry_entry($root, $updated);
}

function bandpromo_release_delete(string $root, string $releaseId): void
{
    $releaseId = bandpromo_release_normalize_id($releaseId);
    if (bandpromo_release_is_protected_id($releaseId)) {
        throw new InvalidArgumentException('This release cannot be deleted.');
    }

    $registry = bandpromo_release_load_registry($root);
    $before = count($registry['releases']);
    $registry['releases'] = array_values(array_filter(
        $registry['releases'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $releaseId
    ));
    if (count($registry['releases']) === $before) {
        throw new InvalidArgumentException('Unknown release.');
    }

    bandpromo_release_write_registry($root, $registry);

    $path = bandpromo_release_document_path($root, $releaseId);
    if (is_file($path)) {
        unlink($path);
    }

    $registryAssets = bandpromo_asset_load_registry($root);
    $changed = false;
    foreach ($registryAssets['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if ((string) ($asset['release_id'] ?? '') !== $releaseId) {
            continue;
        }
        $registryAssets['assets'][$assetId]['release_id'] = BANDPROMO_RELEASE_DEFAULT_ID;
        $changed = true;
    }
    if ($changed) {
        bandpromo_asset_write_registry($root, $registryAssets);
    }
}
