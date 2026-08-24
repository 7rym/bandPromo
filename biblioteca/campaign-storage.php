<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/site-contact.php';
require_once __DIR__ . '/demo-catalog-state.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/living-cover-helpers.php';

const BANDPROMO_RELEASE_REGISTRY_VERSION = 1;
/** Invisible orphan/upload bucket id (legacy on-disk name: primary). */
const BANDPROMO_CAMPAIGN_DEFAULT_ID = 'primary';
const BANDPROMO_RELEASE_DEMO_ID = 'bandpromo-demo';

/**
 * Owning campaign id from a document or entry (canonical campaign_id, legacy release_id).
 */
function bandpromo_document_campaign_id(array $doc): string
{
    $id = trim((string) ($doc['campaign_id'] ?? ''));
    if ($id === '') {
        $id = trim((string) ($doc['release_id'] ?? ''));
    }

    return bandpromo_campaign_normalize_id($id);
}

/**
 * True when the id is empty or the invisible primary orphan bucket (not a real campaign).
 */
function bandpromo_campaign_id_is_unowned(string $campaignId): bool
{
    $campaignId = bandpromo_campaign_normalize_id($campaignId);

    return $campaignId === '' || $campaignId === BANDPROMO_CAMPAIGN_DEFAULT_ID;
}

/**
 * Set canonical campaign_id and drop legacy release_id from a document/entry array.
 *
 * @return array<string, mixed>
 */
function bandpromo_document_with_campaign_id(array $doc, string $campaignId): array
{
    $campaignId = bandpromo_campaign_normalize_id($campaignId);
    unset($doc['release_id']);
    if ($campaignId === '') {
        unset($doc['campaign_id']);
    } else {
        $doc['campaign_id'] = $campaignId;
    }

    return $doc;
}

function bandpromo_campaign_storage_legacy_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'releases';
}

function bandpromo_campaign_storage_root(string $root): string
{
    $canonical = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'campaigns';
    $legacy = bandpromo_campaign_storage_legacy_root($root);
    if (is_dir($canonical)) {
        return $canonical;
    }
    if (is_dir($legacy)) {
        return $legacy;
    }

    return $canonical;
}

function bandpromo_campaign_registry_path(string $root): string
{
    return bandpromo_campaign_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_campaign_document_path(string $root, string $releaseId): string
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);

    return bandpromo_campaign_storage_root($root) . DIRECTORY_SEPARATOR . $releaseId . '.json';
}

/**
 * Ensure data/campaigns exists; one-shot rename from data/releases when needed.
 */
function bandpromo_campaign_registry_ensure_dir(string $root): void
{
    $canonical = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'campaigns';
    $legacy = bandpromo_campaign_storage_legacy_root($root);
    if (!is_dir($canonical) && is_dir($legacy)) {
        $dataDir = $root . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true) && !is_dir($dataDir)) {
            throw new RuntimeException('Could not create data directory for campaigns.');
        }
        if (!@rename($legacy, $canonical)) {
            // Fall through: keep reading legacy until rename succeeds on a later pass.
        }
    }

    $dir = bandpromo_campaign_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/campaigns directory.');
    }
}

function bandpromo_campaign_normalize_id(string $releaseId): string
{
    $releaseId = strtolower(trim($releaseId));
    $releaseId = preg_replace('/[^a-z0-9-]+/', '-', $releaseId) ?? '';
    $releaseId = trim($releaseId, '-');

    return substr($releaseId, 0, 48);
}

function bandpromo_campaign_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = BANDPROMO_CAMPAIGN_DEFAULT_ID;
    }

    return substr($slug, 0, 48);
}

function bandpromo_campaign_validate_date(string $value): bool
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

function bandpromo_campaign_normalize_track_entry(array $entry): ?array
{
    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    $normalized = [
        'asset_id' => $assetId,
        'slug' => trim((string) ($entry['slug'] ?? '')),
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

function bandpromo_campaign_normalize_document(array $input, ?string $expectedId = null, ?string $root = null): array
{
    $id = bandpromo_campaign_normalize_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid campaign id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $slug = trim((string) ($input['slug'] ?? ''));
    if ($slug === '') {
        $slug = $id;
    }
    $slug = bandpromo_campaign_normalize_id($slug);

    $releaseDate = trim((string) ($input['release_date'] ?? ''));
    if ($releaseDate === '') {
        $releaseDate = gmdate('Y-m-d');
    }
    if (!bandpromo_campaign_validate_date($releaseDate)) {
        throw new InvalidArgumentException('Campaign date must use YYYY or YYYY-MM-DD.');
    }

    $tracks = [];
    $seenAssets = [];
    if (isset($input['tracks']) && is_array($input['tracks'])) {
        foreach ($input['tracks'] as $track) {
            if (!is_array($track)) {
                continue;
            }
            $normalizedTrack = bandpromo_campaign_normalize_track_entry($track);
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
        'short_description' => bandpromo_campaign_normalize_text_field($input['short_description'] ?? '', 300),
        'catalog_id' => bandpromo_campaign_normalize_text_field($input['catalog_id'] ?? '', 80),
        'description' => bandpromo_campaign_normalize_text_field($input['description'] ?? '', 4000),
        'poster_asset_id' => bandpromo_campaign_normalize_poster_asset_id($root, $input['poster_asset_id'] ?? ''),
        'brand_id' => bandpromo_campaign_normalize_brand_id($root, $input['brand_id'] ?? ''),
        'epk' => bandpromo_campaign_normalize_epk($input['epk'] ?? []),
        'tracks' => $tracks,
    ];
}

function bandpromo_campaign_normalize_text_field(mixed $value, int $maxLength): string
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

function bandpromo_campaign_normalize_brand_id(?string $root, mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $canonical = bandpromo_brand_canonical_id($value);
    if ($root !== null) {
        bandpromo_brand_ensure_seeded($root);
        if (bandpromo_brand_registry_entry($root, $canonical) === null) {
            return '';
        }
    }

    return $canonical;
}

function bandpromo_campaign_effective_brand_id(string $root, string $releaseId): string
{
    require_once __DIR__ . '/brand-storage.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId !== '' && !bandpromo_campaign_id_is_unowned($releaseId)) {
        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
            $brandId = bandpromo_campaign_normalize_brand_id($root, $document['brand_id'] ?? '');
            if ($brandId !== '') {
                return $brandId;
            }
        } catch (Throwable $throwable) {
            // Fall through to ownership inference / install Base.
        }

        // Same inference as ownership_children: a brand whose campaign_id points here.
        try {
            bandpromo_brand_ensure_seeded($root);
            foreach (bandpromo_brand_registry_entries($root) as $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $id = bandpromo_brand_canonical_id((string) ($meta['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                try {
                    $brand = bandpromo_brand_load_document($root, $id);
                } catch (Throwable $throwable) {
                    continue;
                }
                if (bandpromo_document_campaign_id($brand) === $releaseId) {
                    return $id;
                }
            }
        } catch (Throwable $throwable) {
            // Fall through to install Base.
        }
    }

    return BANDPROMO_BRAND_DEFAULT_ID;
}

function bandpromo_campaign_normalize_poster_asset_id(?string $root, mixed $value): string
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

function bandpromo_campaign_visual_media_bases(): array
{
    return ['/media/img/original', '/media/photo/original', '/media/special'];
}

function bandpromo_campaign_poster_filename_candidates(string $reference, ?array $asset = null): array
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

function bandpromo_campaign_resolve_poster_preview_url(string $root, string $posterReference): string
{
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/asset-registry.php';

    $posterReference = trim($posterReference);
    if ($posterReference === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $posterReference)) {
        return $posterReference;
    }

    // Path refs: map intake originals onto delivery card/thumb when registered.
    if (preg_match('#^/media/#', $posterReference)) {
        $basename = basename(str_replace('\\', '/', $posterReference));
        $stem = (string) pathinfo($basename, PATHINFO_FILENAME);
        $asset = null;
        if (bandpromo_asset_is_asset_id($stem)) {
            $asset = bandpromo_asset_lookup_by_id($root, $stem);
        }
        if ($asset === null && $basename !== '') {
            $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
            $url = bandpromo_visual_resolve_url(
                $root,
                (string) ($asset['id'] ?? ''),
                'card',
                (string) ($asset['intake_bucket'] ?? ''),
                false
            );
            if ($url !== '') {
                return $url;
            }
        }

        // Never paint multi-MB intake originals in Catalogue / release chrome.
        if (preg_match('#^/media/(?:img|photo|visual)/original/#', str_replace('\\', '/', $posterReference))) {
            return '';
        }

        $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $posterReference);

        return is_file($path) ? $posterReference : '';
    }

    $asset = bandpromo_asset_is_asset_id($posterReference)
        ? bandpromo_asset_lookup_by_id($root, $posterReference)
        : bandpromo_asset_lookup_by_original_filename($root, $posterReference);

    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $url = bandpromo_visual_resolve_url(
            $root,
            (string) ($asset['id'] ?? ''),
            'card',
            (string) ($asset['intake_bucket'] ?? ''),
            false
        );
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function bandpromo_campaign_streaming_link_sort_key(string $label): int
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

function bandpromo_campaign_sort_streaming_links(array $links): array
{
    usort($links, static function (array $a, array $b): int {
        $left = bandpromo_campaign_streaming_link_sort_key((string) ($a['label'] ?? ''));
        $right = bandpromo_campaign_streaming_link_sort_key((string) ($b['label'] ?? ''));

        return $left <=> $right ?: strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });

    return $links;
}

function bandpromo_campaign_normalize_streaming_links(mixed $input): array
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
        $label = bandpromo_campaign_normalize_text_field($entry['label'] ?? '', 80);
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

    return bandpromo_campaign_sort_streaming_links($links);
}

function bandpromo_campaign_normalize_press_photo_asset_ids(mixed $input): array
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

function bandpromo_campaign_normalize_epk(mixed $input): array
{
    if (!is_array($input)) {
        $input = [];
    }

    return [
        'tagline' => bandpromo_campaign_normalize_text_field($input['tagline'] ?? '', 160),
        'genre' => bandpromo_campaign_normalize_text_field($input['genre'] ?? '', 120),
        'credits' => bandpromo_campaign_normalize_text_field($input['credits'] ?? '', 4000),
        'press_contact' => bandpromo_site_contact_store_value((string) ($input['press_contact'] ?? ''), 240),
        'streaming_links' => bandpromo_campaign_normalize_streaming_links($input['streaming_links'] ?? []),
        'press_photo_asset_ids' => bandpromo_campaign_normalize_press_photo_asset_ids($input['press_photo_asset_ids'] ?? []),
    ];
}

function bandpromo_campaign_default_epk(): array
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

function bandpromo_campaign_default_document(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'id' => BANDPROMO_CAMPAIGN_DEFAULT_ID,
        'slug' => BANDPROMO_CAMPAIGN_DEFAULT_ID,
        'title' => 'Default release',
        'release_date' => gmdate('Y-m-d'),
        'locked' => false,
        'vip_early_days' => 7,
        'short_description' => '',
        'catalog_id' => '',
        'description' => '',
        'poster_asset_id' => '',
        'brand_id' => '',
        'epk' => bandpromo_campaign_default_epk(),
        'tracks' => [],
    ];
}

function bandpromo_campaign_default_registry(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'releases' => [
            [
                'id' => BANDPROMO_CAMPAIGN_DEFAULT_ID,
                'title' => 'Default release',
                'slug' => BANDPROMO_CAMPAIGN_DEFAULT_ID,
                'sort_order' => 10,
                'system' => true,
            ],
        ],
    ];
}

function bandpromo_campaign_normalize_registry(array $input): array
{
    $releases = [];
    $seen = [];
    if (isset($input['releases']) && is_array($input['releases'])) {
        foreach ($input['releases'] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $releases[] = [
                'id' => $id,
                'title' => trim((string) ($entry['title'] ?? ucfirst(str_replace('-', ' ', $id)))),
                'slug' => bandpromo_campaign_normalize_id((string) ($entry['slug'] ?? $id)),
                'sort_order' => (int) ($entry['sort_order'] ?? ($index + 1) * 10),
                'system' => !empty($entry['system'])
                    || $id === BANDPROMO_CAMPAIGN_DEFAULT_ID
                    || $id === BANDPROMO_RELEASE_DEMO_ID,
            ];
        }
    }

    if ($releases === []) {
        return bandpromo_campaign_default_registry();
    }

    usort($releases, static fn(array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'releases' => $releases,
    ];
}

function bandpromo_campaign_write_registry(string $root, array $registry): void
{
    bandpromo_campaign_registry_ensure_dir($root);
    $normalized = bandpromo_campaign_normalize_registry($registry);
    if (!bandpromo_json_write_file(bandpromo_campaign_registry_path($root), $normalized)) {
        throw new RuntimeException('Could not write release registry.');
    }
    bandpromo_campaign_invalidate_runtime_cache($root);
}

function &bandpromo_campaign_runtime_cache(string $root): array
{
    static $caches = [];
    if (!isset($caches[$root])) {
        $caches[$root] = [
            'registry' => null,
            'documents' => [],
            'membership_index' => null,
            'visual_membership_index' => null,
        ];
    }

    return $caches[$root];
}

function bandpromo_campaign_invalidate_runtime_cache(string $root, ?string $releaseId = null): void
{
    $cache = &bandpromo_campaign_runtime_cache($root);
    $cache['registry'] = null;
    $cache['membership_index'] = null;
    $cache['visual_membership_index'] = null;
    if ($releaseId === null) {
        $cache['documents'] = [];

        return;
    }
    unset($cache['documents'][$releaseId]);
}

function bandpromo_campaign_write_document(string $root, array $document): void
{
    bandpromo_campaign_registry_ensure_dir($root);
    $normalized = bandpromo_campaign_normalize_document($document, null, $root);
    if (!bandpromo_json_write_file(bandpromo_campaign_document_path($root, $normalized['id']), $normalized)) {
        throw new RuntimeException('Could not write release document.');
    }
    bandpromo_campaign_invalidate_runtime_cache($root, $normalized['id']);
}

function bandpromo_campaign_load_registry(string $root): array
{
    $cache = &bandpromo_campaign_runtime_cache($root);
    if (is_array($cache['registry'])) {
        return $cache['registry'];
    }

    bandpromo_campaign_ensure_seeded($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_campaign_registry_path($root));
    if ($decoded === null) {
        throw new RuntimeException('Invalid release registry file.');
    }

    $cache['registry'] = bandpromo_campaign_normalize_registry($decoded);

    return $cache['registry'];
}

function bandpromo_campaign_load_document(string $root, string $releaseId): array
{
    bandpromo_campaign_ensure_seeded($root);
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $cache = &bandpromo_campaign_runtime_cache($root);
    if (isset($cache['documents'][$releaseId])) {
        return $cache['documents'][$releaseId];
    }

    $path = bandpromo_campaign_document_path($root, $releaseId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing release document: data/releases/' . $releaseId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid release document: data/releases/' . $releaseId . '.json');
    }

    $cache['documents'][$releaseId] = bandpromo_campaign_normalize_document($decoded, $releaseId, $root);

    return $cache['documents'][$releaseId];
}

function bandpromo_campaign_registry_entry(string $root, string $releaseId): ?array
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    foreach (bandpromo_campaign_load_registry($root)['releases'] as $entry) {
        if (($entry['id'] ?? '') === $releaseId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_campaign_ensure_seeded(string $root): void
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
        bandpromo_campaign_registry_ensure_dir($root);
        $registryPath = bandpromo_campaign_registry_path($root);
        if (!is_file($registryPath)) {
            $registry = bandpromo_campaign_default_registry();
            $templateRegistry = $root . '/biblioteca/templates/releases.registry.template.json';
            if (is_file($templateRegistry)) {
                $decoded = bandpromo_json_read_array_file($templateRegistry);
                if ($decoded !== null) {
                    $registry = bandpromo_campaign_normalize_registry($decoded);
                }
            }
            bandpromo_campaign_write_registry($root, $registry);

            $document = bandpromo_campaign_default_document();
            $templateDocument = $root . '/biblioteca/templates/default.release.template.json';
            if (is_file($templateDocument)) {
                $decoded = bandpromo_json_read_array_file($templateDocument);
                if ($decoded !== null) {
                    try {
                        $document = bandpromo_campaign_normalize_document($decoded);
                    } catch (Throwable $throwable) {
                        $document = bandpromo_campaign_default_document();
                    }
                }
            }
            bandpromo_campaign_write_document($root, $document);

            bandpromo_campaign_ensure_demo_release($root);
            $completed[$root] = true;

            return;
        }

        $registry = bandpromo_campaign_normalize_registry((array) (bandpromo_json_read_array_file($registryPath) ?? []));
        foreach ($registry['releases'] as $entry) {
            $releaseId = (string) ($entry['id'] ?? '');
            if ($releaseId === '') {
                continue;
            }
            $docPath = bandpromo_campaign_document_path($root, $releaseId);
            if (!is_file($docPath)) {
                // Platform demo document arrives via PRP import only — never seed an empty shell.
                if ($releaseId === BANDPROMO_RELEASE_DEMO_ID) {
                    continue;
                }
                $document = bandpromo_campaign_default_document();
                $document['id'] = $releaseId;
                $document['slug'] = (string) ($entry['slug'] ?? $releaseId);
                $document['title'] = (string) ($entry['title'] ?? $document['title']);
                bandpromo_campaign_write_document($root, $document);
            }
        }

        bandpromo_campaign_ensure_demo_release($root);
        $completed[$root] = true;
    } finally {
        unset($running[$root]);
    }
}

function bandpromo_campaign_demo_default_document(): array
{
    return [
        'version' => BANDPROMO_RELEASE_REGISTRY_VERSION,
        'id' => BANDPROMO_RELEASE_DEMO_ID,
        'slug' => BANDPROMO_RELEASE_DEMO_ID,
        'title' => 'bandPromo Demo Release',
        'release_date' => '2020-01-01',
        'locked' => true,
        'vip_early_days' => 0,
        'brand_id' => BANDPROMO_BRAND_DEFAULT_ID,
        'tracks' => [],
    ];
}

function bandpromo_campaign_registry_entries(string $root): array
{
    return bandpromo_campaign_load_registry($root)['releases'] ?? [];
}

function bandpromo_campaign_is_demo_filename(string $filename): bool
{
    // Legacy filename hint only (shell/media hide helpers). Not ownership.
    return strncmp(basename($filename), 'bandPromo_', 10) === 0;
}

function bandpromo_campaign_is_platform_demo(string $releaseId): bool
{
    return bandpromo_campaign_normalize_id($releaseId) === BANDPROMO_RELEASE_DEMO_ID;
}

/**
 * Platform demo may be unlocked only on localhost (PRP authoring).
 * All other releases may change lock freely.
 */
function bandpromo_campaign_may_change_lock(string $releaseId): bool
{
    if (!bandpromo_campaign_is_platform_demo($releaseId)) {
        return true;
    }

    require_once __DIR__ . '/https.php';

    return bandpromo_is_local_dev_host();
}

/**
 * Keep remote installs locked if the platform demo is already present.
 * Does not create demo content — that arrives only via PRP import.
 */
function bandpromo_campaign_enforce_platform_demo_lock(string $root): void
{
    $docPath = bandpromo_campaign_document_path($root, BANDPROMO_RELEASE_DEMO_ID);
    if (!is_file($docPath)) {
        return;
    }

    require_once __DIR__ . '/https.php';
    $requestHost = bandpromo_request_host_without_port();
    if ($requestHost === '' || bandpromo_is_local_dev_host()) {
        return;
    }

    try {
        $document = bandpromo_campaign_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
    } catch (Throwable $throwable) {
        return;
    }

    if (!empty($document['locked'])) {
        return;
    }

    $document['locked'] = true;
    bandpromo_campaign_write_document($root, $document);
}

/**
 * @deprecated Demo campaign arrives via PRP only. Kept as a lock-enforce hook for old callers.
 */
function bandpromo_campaign_ensure_demo_release(string $root): void
{
    bandpromo_campaign_enforce_platform_demo_lock($root);
}

/**
 * Lock the platform demo after a successful PRP import (setup).
 */
function bandpromo_campaign_lock_platform_demo_after_import(string $root): void
{
    $docPath = bandpromo_campaign_document_path($root, BANDPROMO_RELEASE_DEMO_ID);
    if (!is_file($docPath)) {
        return;
    }

    try {
        $document = bandpromo_campaign_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
    } catch (Throwable $throwable) {
        return;
    }

    $document['locked'] = true;
    bandpromo_campaign_write_document($root, $document);
}

function bandpromo_campaign_id_for_master_filename(string $root, string $masterFilename): string
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return '';
    }

    $meta = bandpromo_campaign_audio_listing_meta($root, $masterFilename);
    $releaseId = bandpromo_campaign_normalize_id(trim((string) ($meta['release_id'] ?? '')));
    if ($releaseId !== '') {
        return $releaseId;
    }

    return '';
}

function bandpromo_campaign_id_for_media_file(string $root, string $target, string $filename): string
{
    if ($target === 'audio') {
        return bandpromo_campaign_id_for_master_filename($root, $filename);
    }

    $filename = basename(trim($filename));
    if ($filename === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_master_filename($root, $filename);
    if (!is_array($asset)) {
        return '';
    }

    return bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
}

/**
 * Resolve campaign release_id for a brand/visual/sfx asset (brand.release_id ↔ release.brand_id).
 */
function bandpromo_campaign_id_for_brand_owned_asset(string $root, string $brandId): string
{
    $brandId = trim($brandId);
    if ($brandId === '') {
        return '';
    }

    require_once __DIR__ . '/brand-storage.php';
    try {
        $brand = bandpromo_brand_load_document($root, $brandId);
        $releaseId = bandpromo_campaign_normalize_id(trim((string) ($brand['release_id'] ?? '')));
        if ($releaseId !== '') {
            return $releaseId;
        }
    } catch (Throwable $throwable) {
        // Fall through to reverse lookup.
    }

    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }
        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (bandpromo_brand_canonical_id((string) ($document['brand_id'] ?? '')) === bandpromo_brand_canonical_id($brandId)) {
            return $releaseId;
        }
    }

    return '';
}

/**
 * Resolve a cover/poster/page ref to a Visual asset id.
 */
function bandpromo_campaign_visual_asset_id_from_ref(string $root, string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $ref);
    if (!is_array($asset) || strtolower((string) ($asset['kind'] ?? '')) !== 'visual') {
        return '';
    }

    return trim((string) ($asset['id'] ?? ''));
}

/**
 * Visual Brand shell slots (logo, poster, still/living) → asset ids.
 * Library membership and SFX slots are not included.
 *
 * @return array<string, string> slot => asset_id
 */
function bandpromo_campaign_visual_shell_slot_asset_ids(string $root, array $document): array
{
    require_once __DIR__ . '/brand-storage.php';

    $slots = ['logo', 'poster', 'background_image', 'background_video'];
    $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
    $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
    $out = [];
    foreach ($slots as $slotKey) {
        $slotAssetId = trim((string) ($assetIds[$slotKey] ?? ''));
        $path = trim((string) ($assets[$slotKey] ?? ''));
        if ($slotAssetId === '' && $path !== '') {
            $slotAssetId = bandpromo_brand_lookup_asset_id_for_path($root, $path);
        }
        if ($slotAssetId === '') {
            continue;
        }
        $asset = bandpromo_asset_lookup_by_id($root, $slotAssetId);
        if (!is_array($asset) || strtolower((string) ($asset['kind'] ?? '')) !== 'visual') {
            continue;
        }
        $id = trim((string) ($asset['id'] ?? ''));
        if ($id !== '') {
            $out[$slotKey] = $id;
        }
    }

    return $out;
}

/**
 * Maps visual asset_id to campaign memberships from release-owned usage
 * (galleries, posters, press photos, playlist posters, page pictures,
 * track covers / living covers) plus Brand visual shell slots those
 * campaigns actually play. Empty Brand slots inherit the install Base
 * brand (login / player fallback). Brand library membership is not
 * catalogue — owning Brand on the asset is not either.
 *
 * @return array<string, list<array{release_id: string, release_title: string, release_date: string}>>
 */
function bandpromo_campaign_visual_membership_index(string $root): array
{
    $cache = &bandpromo_campaign_runtime_cache($root);
    if (is_array($cache['visual_membership_index'] ?? null)) {
        return $cache['visual_membership_index'];
    }

    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/media-reference-helpers.php';
    require_once __DIR__ . '/living-cover-helpers.php';

    $index = [];
    $add = static function (string $assetId, string $releaseId, string $title, string $date) use (&$index): void {
        $assetId = trim($assetId);
        $releaseId = bandpromo_campaign_normalize_id($releaseId);
        if ($assetId === '' || $releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
            return;
        }
        if (!isset($index[$assetId])) {
            $index[$assetId] = [];
        }
        foreach ($index[$assetId] as $existing) {
            if (($existing['release_id'] ?? '') === $releaseId) {
                return;
            }
        }
        $index[$assetId][] = [
            'release_id' => $releaseId,
            'release_title' => $title,
            'release_date' => $date,
        ];
    };
    $addRef = static function (string $ref, string $releaseId, string $title, string $date) use ($root, $add): void {
        $assetId = bandpromo_campaign_visual_asset_id_from_ref($root, $ref);
        if ($assetId !== '') {
            $add($assetId, $releaseId, $title, $date);
        }
    };

    $releaseMeta = [];
    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
            continue;
        }
        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }
        $title = trim((string) ($document['title'] ?? ''));
        $date = trim((string) ($document['release_date'] ?? ''));
        $releaseMeta[$releaseId] = [
            'title' => $title,
            'date' => $date,
            'brand_id' => bandpromo_brand_canonical_id((string) ($document['brand_id'] ?? '')),
        ];
        $addRef((string) ($document['poster_asset_id'] ?? ''), $releaseId, $title, $date);
        $pressIds = is_array($document['epk']['press_photo_asset_ids'] ?? null)
            ? $document['epk']['press_photo_asset_ids']
            : [];
        foreach ($pressIds as $pressId) {
            $addRef((string) $pressId, $releaseId, $title, $date);
        }
    }

    try {
        foreach (bandpromo_gallery_registry_entries($root) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
            if ($galleryId === '') {
                continue;
            }
            try {
                $doc = bandpromo_gallery_load_document($root, $galleryId);
            } catch (Throwable $throwable) {
                continue;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($doc['release_id'] ?? ''));
            if ($releaseId === '' || !isset($releaseMeta[$releaseId])) {
                continue;
            }
            $title = $releaseMeta[$releaseId]['title'];
            $date = $releaseMeta[$releaseId]['date'];
            foreach (is_array($doc['entries'] ?? null) ? $doc['entries'] : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $assetId = bandpromo_media_reference_gallery_item_asset_id($root, $row);
                if ($assetId !== '') {
                    $add($assetId, $releaseId, $title, $date);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Gallery registry may be missing during early setup.
    }

    try {
        foreach (bandpromo_playlist_registry_entries($root) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
            if ($playlistId === '') {
                continue;
            }
            try {
                $doc = bandpromo_playlist_load_document($root, $playlistId);
            } catch (Throwable $throwable) {
                continue;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($doc['release_id'] ?? ''));
            if ($releaseId === '' || !isset($releaseMeta[$releaseId])) {
                continue;
            }
            $addRef(
                (string) ($doc['poster_asset_id'] ?? ''),
                $releaseId,
                $releaseMeta[$releaseId]['title'],
                $releaseMeta[$releaseId]['date']
            );
        }
    } catch (Throwable $throwable) {
        // Playlist registry may be missing during early setup.
    }

    try {
        foreach (bandpromo_page_registry_ids($root) as $pageId) {
            try {
                $doc = bandpromo_page_load_document($root, $pageId);
            } catch (Throwable $throwable) {
                continue;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($doc['release_id'] ?? ''));
            if ($releaseId === '' || !isset($releaseMeta[$releaseId])) {
                continue;
            }
            $title = $releaseMeta[$releaseId]['title'];
            $date = $releaseMeta[$releaseId]['date'];
            $addRef((string) ($doc['poster_asset_id'] ?? ''), $releaseId, $title, $date);
            foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
                if (!is_array($block)) {
                    continue;
                }
                foreach (['asset_id', 'src', 'poster', 'poster_asset_id'] as $field) {
                    $addRef((string) ($block[$field] ?? ''), $releaseId, $title, $date);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Page registry may be missing during early setup.
    }

    $audioMembership = bandpromo_campaign_asset_membership_index($root);
    foreach ($audioMembership as $audioAssetId => $memberships) {
        $audio = bandpromo_asset_lookup_by_id($root, (string) $audioAssetId);
        if (!is_array($audio) || strtolower((string) ($audio['kind'] ?? '')) !== 'audio') {
            continue;
        }
        $display = bandpromo_asset_read_audio_display($audio);
        $coverRef = trim((string) ($display['cover'] ?? ''));
        $livingRef = bandpromo_living_cover_normalize_video_filename((string) ($display['living_cover'] ?? ''));
        foreach ($memberships as $membership) {
            if (!is_array($membership)) {
                continue;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($membership['release_id'] ?? ''));
            if ($releaseId === '' || !isset($releaseMeta[$releaseId])) {
                continue;
            }
            $title = $releaseMeta[$releaseId]['title'];
            $date = $releaseMeta[$releaseId]['date'];
            if ($coverRef !== '') {
                $addRef($coverRef, $releaseId, $title, $date);
            }
            if ($livingRef !== '') {
                $addRef($livingRef, $releaseId, $title, $date);
            }
        }
    }

    require_once __DIR__ . '/brand-storage.php';
    $brandSlotAssets = [];
    try {
        bandpromo_brand_ensure_seeded($root);
        foreach (bandpromo_brand_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $brandId = bandpromo_brand_canonical_id((string) ($registryEntry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $brandDocument = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $brandSlotAssets[$brandId] = bandpromo_campaign_visual_shell_slot_asset_ids($root, $brandDocument);
        }
    } catch (Throwable $throwable) {
        $brandSlotAssets = [];
    }

    $baseBrandId = bandpromo_brand_canonical_id(bandpromo_brand_active_id($root));
    if ($baseBrandId === '') {
        $baseBrandId = BANDPROMO_BRAND_DEFAULT_ID;
    }
    $baseSlots = $brandSlotAssets[$baseBrandId] ?? [];
    $visualShellSlots = ['logo', 'poster', 'background_image', 'background_video'];
    foreach ($releaseMeta as $releaseId => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $brandId = bandpromo_brand_canonical_id((string) ($meta['brand_id'] ?? ''));
        if ($brandId === '' || !isset($brandSlotAssets[$brandId])) {
            $brandId = $baseBrandId;
        }
        $brandSlots = $brandSlotAssets[$brandId] ?? [];
        foreach ($visualShellSlots as $slotKey) {
            $slotAssetId = trim((string) ($brandSlots[$slotKey] ?? ''));
            if ($slotAssetId === '') {
                $slotAssetId = trim((string) ($baseSlots[$slotKey] ?? ''));
            }
            if ($slotAssetId !== '') {
                $add(
                    $slotAssetId,
                    (string) $releaseId,
                    (string) ($meta['title'] ?? ''),
                    (string) ($meta['date'] ?? '')
                );
            }
        }
    }

    $cache['visual_membership_index'] = $index;

    return $cache['visual_membership_index'];
}

/**
 * Catalogue labels for a Visual pool row. Campaign usage wins (gallery,
 * cover, poster, page, plus Brand visual shell those campaigns play,
 * including Base-brand fallback for empty slots). Brand library membership
 * is not a campaign, but it is not Orphan either — unused library files
 * list the Brand title(s). Stored asset.release_id is only a fallback when
 * nothing uses the file. The invisible `primary` bucket is never catalogue.
 *
 * @return array{release_id: string, release_ids: list<string>, release_title: string, release_date: string, release_orphan: bool}
 */
function bandpromo_campaign_visual_listing_meta(string $root, string $assetId, string $storedReleaseId = ''): array
{
    $empty = [
        'release_id' => '',
        'release_ids' => [],
        'release_title' => '',
        'release_date' => '',
        'release_orphan' => true,
    ];

    $assetId = trim($assetId);
    $storedReleaseId = bandpromo_campaign_normalize_id($storedReleaseId);
    if ($storedReleaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        $storedReleaseId = '';
    }

    $memberships = $assetId !== ''
        ? (bandpromo_campaign_visual_membership_index($root)[$assetId] ?? [])
        : [];

    if ($memberships === [] && $storedReleaseId !== '' && bandpromo_demo_catalog_entity_is_visible($root, $storedReleaseId)) {
        try {
            $document = bandpromo_campaign_load_document($root, $storedReleaseId);
            $memberships = [[
                'release_id' => $storedReleaseId,
                'release_title' => trim((string) ($document['title'] ?? '')),
                'release_date' => trim((string) ($document['release_date'] ?? '')),
            ]];
        } catch (Throwable $throwable) {
            $memberships = [];
        }
    }

    $memberships = array_values(array_filter(
        $memberships,
        static function ($row) use ($root): bool {
            if (!is_array($row)) {
                return false;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($row['release_id'] ?? ''));
            if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
                return false;
            }

            return bandpromo_demo_catalog_entity_is_visible($root, $releaseId);
        }
    ));

    if ($memberships === []) {
        $library = $assetId !== ''
            ? (bandpromo_brand_library_membership_index($root)[$assetId] ?? [])
            : [];
        $brandTitles = [];
        foreach ($library as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['brand_title'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row['brand_id'] ?? ''));
            }
            if ($title !== '' && !in_array($title, $brandTitles, true)) {
                $brandTitles[] = $title;
            }
        }
        if ($brandTitles !== []) {
            return [
                'release_id' => '',
                'release_ids' => [],
                'release_title' => implode("\n", $brandTitles),
                'release_date' => '',
                'release_orphan' => false,
            ];
        }

        return $empty;
    }

    $ids = [];
    $titles = [];
    $firstDate = '';
    foreach ($memberships as $membership) {
        if (!is_array($membership)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($membership['release_id'] ?? ''));
        if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID || isset($ids[$releaseId])) {
            continue;
        }
        $ids[$releaseId] = true;
        $title = trim((string) ($membership['release_title'] ?? ''));
        $titles[] = $title !== '' ? $title : $releaseId;
        if ($firstDate === '') {
            $firstDate = trim((string) ($membership['release_date'] ?? ''));
        }
    }

    $idList = array_keys($ids);
    if ($idList === []) {
        return $empty;
    }

    return [
        'release_id' => $idList[0],
        'release_ids' => $idList,
        'release_title' => implode("\n", $titles),
        'release_date' => $firstDate,
        'release_orphan' => false,
    ];
}

function bandpromo_campaign_normalize_pool_filter(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === 'all') {
        return 'all';
    }
    if ($value === 'orphans' || $value === 'releases') {
        return $value;
    }

    return bandpromo_campaign_normalize_id($value);
}

/**
 * Maps asset_id to release memberships built from release documents (once per request).
 *
 * @return array<string, list<array{release_id: string, release_title: string, release_date: string, track: array}>>
 */
function bandpromo_campaign_asset_membership_index(string $root): array
{
    $cache = &bandpromo_campaign_runtime_cache($root);
    if (is_array($cache['membership_index'])) {
        return $cache['membership_index'];
    }

    $index = [];
    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }

        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }

        $releaseTitle = trim((string) ($document['title'] ?? ''));
        $releaseDate = trim((string) ($document['release_date'] ?? ''));
        foreach ($document['tracks'] as $track) {
            if (!is_array($track)) {
                continue;
            }
            $assetId = trim((string) ($track['asset_id'] ?? ''));
            if ($assetId === '') {
                continue;
            }
            $index[$assetId][] = [
                'release_id' => $releaseId,
                'release_title' => $releaseTitle,
                'release_date' => $releaseDate,
                'track' => $track,
            ];
        }
    }

    $cache['membership_index'] = $index;

    return $cache['membership_index'];
}

function bandpromo_campaign_memberships_for_asset(string $root, string $assetId, string $preferredReleaseId = ''): array
{
    $assetId = trim($assetId);
    if ($assetId === '') {
        return [];
    }

    $memberships = bandpromo_campaign_asset_membership_index($root)[$assetId] ?? [];
    $preferredReleaseId = bandpromo_campaign_normalize_id($preferredReleaseId);
    if ($preferredReleaseId === '' || $memberships === []) {
        return $memberships;
    }

    $preferred = [];
    $rest = [];
    foreach ($memberships as $membership) {
        if (($membership['release_id'] ?? '') === $preferredReleaseId) {
            $preferred[] = $membership;
            continue;
        }
        $rest[] = $membership;
    }

    return array_merge($preferred, $rest);
}

function bandpromo_campaign_audio_listing_meta(string $root, string $filename): array
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

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset === null) {
        return $empty;
    }

    $assetId = trim((string) ($asset['id'] ?? ''));
    $assignedReleaseId = bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
    if ($assignedReleaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        $assignedReleaseId = '';
    }
    $memberships = bandpromo_campaign_memberships_for_asset($root, $assetId, $assignedReleaseId);
    $memberships = array_values(array_filter(
        $memberships,
        static function ($row): bool {
            if (!is_array($row)) {
                return false;
            }
            $releaseId = bandpromo_campaign_normalize_id((string) ($row['release_id'] ?? ''));

            return $releaseId !== '' && $releaseId !== BANDPROMO_CAMPAIGN_DEFAULT_ID;
        }
    ));

    if ($memberships === []) {
        return $empty;
    }

    $membership = $memberships[0];
    $releaseId = bandpromo_campaign_normalize_id((string) ($membership['release_id'] ?? ''));
    $releaseTitle = trim((string) ($membership['release_title'] ?? ''));
    $releaseDate = trim((string) ($membership['release_date'] ?? ''));
    $onRelease = $releaseId !== '';
    $releaseOrphan = !$onRelease || $releaseDate === '' || $releaseTitle === '';

    return [
        'release_id' => $releaseId,
        'release_title' => $releaseTitle,
        'release_date' => $releaseDate,
        'release_orphan' => $releaseOrphan,
        'on_release' => $onRelease,
    ];
}

function bandpromo_campaign_track_entry_for_asset(string $root, string $releaseId, string $assetId): ?array
{
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    foreach (bandpromo_campaign_memberships_for_asset($root, $assetId) as $membership) {
        if (($membership['release_id'] ?? '') !== $releaseId) {
            continue;
        }

        $track = $membership['track'] ?? null;

        return is_array($track) ? $track : null;
    }

    return null;
}

function bandpromo_campaign_find_track_number_for_master(string $root, string $masterFilename): string
{
    $masterFilename = basename(trim($masterFilename));
    $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFilename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $masterFilename);
    if ($asset === null) {
        return '';
    }

    $display = bandpromo_asset_read_audio_display($asset);

    return trim((string) ($display['tracknumber'] ?? ''));
}

function bandpromo_campaign_is_master_locked(string $root, string $masterFilename): bool
{
    $asset = bandpromo_asset_lookup_by_original_filename($root, $masterFilename);
    if ($asset === null) {
        return false;
    }

    foreach (bandpromo_campaign_memberships_for_asset($root, (string) ($asset['id'] ?? '')) as $membership) {
        $releaseId = bandpromo_campaign_normalize_id((string) ($membership['release_id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }

        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }

        if (!empty($document['locked'])) {
            return true;
        }
    }

    return false;
}

function bandpromo_campaign_assert_master_editable(string $root, string $masterFilename): void
{
    if (bandpromo_campaign_is_master_locked($root, $masterFilename)) {
        throw new RuntimeException('This track belongs to a locked release and cannot be edited.');
    }
}

function bandpromo_campaign_is_protected_id(string $releaseId): bool
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);

    return $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID || $releaseId === BANDPROMO_RELEASE_DEMO_ID;
}

function bandpromo_campaign_is_system_managed(string $releaseId): bool
{
    // Deprecated compatibility shim. Demo is a normal locked release after PRP import;
    // localhost may unlock via bandpromo_campaign_may_change_lock(). Never freeze edits
    // harder than the locked flag.
    unset($releaseId);

    return false;
}

/**
 * Build the lightweight, operator-safe track list used by Catalogue preview.
 *
 * @return list<array{asset_id:string,title:string,version:string,artist:string,duration:int,release_date:string}>
 */
function bandpromo_campaign_admin_preview_tracks(string $root, array $document): array
{
    $releaseDate = trim((string) ($document['release_date'] ?? ''));
    $tracks = [];

    foreach ($document['tracks'] ?? [] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = trim((string) ($track['asset_id'] ?? ''));
        if ($assetId === '') {
            continue;
        }
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (!is_array($asset)) {
            continue;
        }
        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        $labels = bandpromo_campaign_track_display_from_asset($asset, $masterFile);
        $display = bandpromo_asset_read_audio_display($asset);

        $tracks[] = [
            'asset_id' => $assetId,
            'title' => trim((string) ($labels['title'] ?? '')) ?: 'Untitled',
            'version' => trim((string) ($labels['version'] ?? '')),
            'artist' => trim((string) ($labels['artist'] ?? '')),
            'duration' => max(0, (int) ($labels['duration'] ?? 0)),
            'release_date' => trim((string) ($display['date'] ?? '')) ?: $releaseDate,
        ];
    }

    return $tracks;
}

function bandpromo_campaign_admin_registry_entry(string $root, array $registryEntry): array
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
    $entry['brand_id'] = '';
    $entry['epk'] = bandpromo_campaign_default_epk();
    $entry['preview_tracks'] = [];

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
        $entry['release_date'] = (string) ($document['release_date'] ?? '');
        $entry['locked'] = !empty($document['locked']);
        $entry['track_count'] = count($document['tracks'] ?? []);
        $entry['description'] = (string) ($document['description'] ?? '');
        $entry['short_description'] = (string) ($document['short_description'] ?? '');
        $entry['catalog_id'] = (string) ($document['catalog_id'] ?? '');
        $entry['poster_asset_id'] = (string) ($document['poster_asset_id'] ?? '');
        $entry['brand_id'] = (string) ($document['brand_id'] ?? '');
        $entry['poster_preview_url'] = bandpromo_campaign_resolve_poster_preview_url(
            $root,
            $entry['poster_asset_id']
        );
        $entry['slug'] = (string) ($document['slug'] ?? ($entry['slug'] ?? $releaseId));
        $entry['epk'] = is_array($document['epk'] ?? null)
            ? bandpromo_campaign_normalize_epk($document['epk'])
            : bandpromo_campaign_default_epk();
        $entry['preview_tracks'] = bandpromo_campaign_admin_preview_tracks($root, $document);
        require_once __DIR__ . '/campaign-ownership-helpers.php';
        $entry['ownership_children'] = bandpromo_campaign_ownership_children($root, $releaseId);
        // Prefer durable campaign.brand_id; fall back to ownership inference for the editor select.
        if (trim((string) ($entry['brand_id'] ?? '')) === '') {
            $inferredBrandId = trim((string) ($entry['ownership_children']['brand_id'] ?? ''));
            if ($inferredBrandId !== '') {
                $entry['brand_id'] = $inferredBrandId;
            }
        }
    } catch (Throwable $throwable) {
        // Keep registry-only fields when the document is missing.
    }

    $entry['platform_demo'] = bandpromo_campaign_is_platform_demo($releaseId);
    $entry['can_change_lock'] = bandpromo_campaign_may_change_lock($releaseId);
    // Deprecated: always false. Kept so older clients do not treat demo as non-editable.
    $entry['system_managed'] = false;
    $entry['protected'] = bandpromo_campaign_is_protected_id($releaseId);

    return $entry;
}

function bandpromo_campaign_visible_in_admin_catalog(string $root, array $entry): bool
{
    $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
    // primary is the invisible orphan/upload bucket — never an operator-facing campaign.
    if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        return false;
    }
    if (!bandpromo_demo_catalog_entity_is_visible($root, $releaseId)) {
        return false;
    }

    return true;
}

/**
 * First operator-visible catalogue release id (never primary).
 */
function bandpromo_campaign_default_admin_content_id(string $root): string
{
    foreach (bandpromo_campaign_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
    }

    return '';
}

function bandpromo_campaign_admin_registry_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $adminEntry = bandpromo_campaign_admin_registry_entry($root, $entry);
        if (!bandpromo_campaign_visible_in_admin_catalog($root, $adminEntry)) {
            continue;
        }
        $entries[] = $adminEntry;
    }

    usort($entries, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return $entries;
}

function bandpromo_campaign_track_master_filename(string $root, string $assetId): string
{
    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    if ($asset === null) {
        return '';
    }

    return basename(trim((string) ($asset['master_filename'] ?? '')));
}

function bandpromo_campaign_track_row_from_pool(array $track, string $releaseId = ''): array
{
    $file = trim((string) ($track['file'] ?? ''));
    $resolvedReleaseId = trim((string) ($track['release_id'] ?? $releaseId));
    $origin = trim((string) ($track['origin'] ?? ''));
    if ($origin === '') {
        $origin = 'user-upload';
    }

    return [
        'file' => $file,
        'asset_id' => trim((string) ($track['asset_id'] ?? '')),
        'title' => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($track['title'] ?? $file))),
        'artist' => (string) ($track['artist'] ?? ''),
        'album' => (string) ($track['album'] ?? ''),
        'duration' => (int) ($track['duration'] ?? 0),
        'origin' => $origin,
        'sourceTier' => (string) ($track['sourceTier'] ?? 'master'),
        'deliveryReady' => ($track['deliveryReady'] ?? true) !== false,
        'release_id' => $resolvedReleaseId,
    ];
}

function bandpromo_campaign_pool_map_with_asset_aliases(string $root, array $poolByFile): array
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

function bandpromo_campaign_title_looks_like_asset_id(string $title, string $masterFile = ''): bool
{
    $normalized = strtolower(trim($title));
    if ($normalized === '') {
        return false;
    }

    // Titles may include an extension when the editor falls back to master_filename.
    $stem = strtolower((string) pathinfo($normalized, PATHINFO_FILENAME));
    if ($stem === '') {
        $stem = $normalized;
    }

    if (preg_match('/^ast_[0-9a-hjkmnp-tv-z]+$/i', $stem) === 1) {
        return true;
    }

    $masterStem = strtolower(pathinfo(basename(trim($masterFile)), PATHINFO_FILENAME));
    if ($masterStem !== '' && ($stem === $masterStem || $normalized === strtolower(basename(trim($masterFile))))) {
        return preg_match('/^ast_[0-9a-hjkmnp-tv-z]+$/i', $masterStem) === 1;
    }

    return false;
}

function bandpromo_campaign_normalize_display_title(string $title): string
{
    $title = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) $title));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/^\d+\.\s+/', '', $title) ?? $title;
    $title = preg_replace('/^\d{1,2}\s+(?=[A-Za-z])/', '', $title) ?? $title;

    return trim($title);
}

function bandpromo_campaign_title_needs_metadata_refresh(string $title, string $masterFile): bool
{
    $title = trim($title);
    if ($title === '' || bandpromo_campaign_title_looks_like_asset_id($title, $masterFile)) {
        return true;
    }

    if (preg_match('/^\d+\.\s+/', $title) === 1) {
        return true;
    }

    if (preg_match('/^\d{1,3}$/', $title) === 1) {
        return true;
    }

    return strlen($title) > 48 && preg_match('/^\d+\s+/', $title) === 1;
}

function bandpromo_campaign_split_audio_title_parts(string $value): array
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

function bandpromo_campaign_track_title_looks_messy(string $title, string $masterFile = ''): bool
{
    if (bandpromo_campaign_title_needs_metadata_refresh($title, $masterFile)) {
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

/**
 * @return array<string, array>
 */
function &bandpromo_campaign_inspect_master_metadata_cache(): array
{
    static $cache = [];

    return $cache;
}

/**
 * Drop cached Python inspect rows after master tags/cover change in this request.
 * Stale sidecar_cover here was writing old track covers back during playlist republish.
 */
function bandpromo_campaign_inspect_master_metadata_invalidate(?string $masterFile = null): void
{
    $cache = &bandpromo_campaign_inspect_master_metadata_cache();
    if ($masterFile === null || trim($masterFile) === '') {
        $cache = [];

        return;
    }

    unset($cache[basename(trim($masterFile))]);
}

function bandpromo_campaign_inspect_master_metadata(string $root, string $masterFile): array
{
    $cache = &bandpromo_campaign_inspect_master_metadata_cache();

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

function bandpromo_campaign_polish_track_title(string $title, string $artist = '', string $releaseTitle = ''): string
{
    $title = bandpromo_campaign_normalize_display_title($title);
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
                // Serial releases often share a release-name prefix ("the Retroscopy hour #08").
                // Do not reduce those titles to a bare episode number in listings.
                if (preg_match('/^#?\d{1,3}$/', $remainder) === 1) {
                    continue;
                }
                $title = $remainder;
            }
        }
    }

    return trim($title);
}

function bandpromo_campaign_combine_audio_title_parts(string $title, string $version = ''): string
{
    $parts = bandpromo_campaign_split_audio_title_parts($title);
    $baseTitle = trim((string) ($parts['title'] ?? ''));
    if ($baseTitle === '') {
        $baseTitle = trim($title);
    }

    $normalizedVersion = trim($version);
    if ($normalizedVersion === '') {
        $normalizedVersion = trim((string) ($parts['version'] ?? ''));
    }

    if ($normalizedVersion === '') {
        return $baseTitle;
    }

    return $baseTitle . ' [' . $normalizedVersion . ']';
}

function bandpromo_campaign_resolve_track_display_labels(
    string $rawTitle,
    string $artist = '',
    string $releaseTitle = ''
): array {
    $normalized = bandpromo_campaign_normalize_display_title($rawTitle);
    if ($normalized === '') {
        return ['title' => 'Untitled', 'version' => ''];
    }

    $forSplit = bandpromo_campaign_polish_track_title($normalized, $artist, '');
    $parts = bandpromo_campaign_split_audio_title_parts($forSplit);
    $displayTitle = trim((string) ($parts['title'] ?? ''));
    $displayVersion = trim((string) ($parts['version'] ?? ''));

    if ($displayTitle !== '' && $releaseTitle !== '') {
        $polishedBase = bandpromo_campaign_polish_track_title($displayTitle, '', $releaseTitle);
        if ($polishedBase !== '' && strcasecmp($polishedBase, $displayTitle) !== 0) {
            $displayTitle = $polishedBase;
        }
    }

    return [
        'title' => $displayTitle !== '' ? $displayTitle : 'Untitled',
        'version' => $displayVersion,
    ];
}

function bandpromo_asset_build_audio_display_from_inspect(array $inspect, string $releaseTitle = '', array $preserveDisplay = []): array
{
    $artist = trim((string) ($inspect['artist'] ?? ''));
    $rawTitle = trim((string) ($inspect['title'] ?? ''));
    $labels = bandpromo_campaign_resolve_track_display_labels($rawTitle, $artist, $releaseTitle);
    $preserve = bandpromo_asset_read_audio_display(['display' => $preserveDisplay]);
    $inspectCover = bandpromo_asset_normalize_media_ref(
        (string) ($inspect['sidecar_cover'] ?? $inspect['cover'] ?? '')
    );

    return [
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $artist,
        'album' => trim((string) ($inspect['album'] ?? '')),
        'duration' => max(0, (int) ($inspect['duration_seconds'] ?? $inspect['duration'] ?? 0)),
        'bitrate_kbps' => max(0, (int) ($inspect['bitrate_kbps'] ?? $preserve['bitrate_kbps'] ?? 0)),
        'sample_rate_hz' => max(0, (int) ($inspect['sample_rate_hz'] ?? $preserve['sample_rate_hz'] ?? 0)),
        'bit_depth' => max(0, (int) ($inspect['bit_depth'] ?? $preserve['bit_depth'] ?? 0)),
        'date' => trim((string) ($inspect['date'] ?? '')),
        'tracknumber' => trim((string) ($inspect['tracknumber'] ?? '')),
        'bpm' => trim((string) ($inspect['bpm'] ?? '')),
        'initialkey' => trim((string) ($inspect['initialkey'] ?? '')),
        'genre' => trim((string) ($inspect['genre'] ?? '')),
        'comment' => trim((string) ($inspect['comment'] ?? '')),
        'lyrics' => (string) ($inspect['lyrics'] ?? ''),
        'text_role' => $preserve['text_role'],
        'notes_label' => $preserve['notes_label'],
        'living_cover' => bandpromo_living_cover_normalize_video_filename((string) ($inspect['living_cover'] ?? '')),
        // Empty inspect sidecar must not wipe an operator-assigned registry cover.
        'cover' => $inspectCover !== '' ? $inspectCover : (string) ($preserve['cover'] ?? ''),
        'synced_at' => gmdate('c'),
     ];
}

function bandpromo_asset_build_audio_display_from_fields(array $fields, array $inspectData = [], array $preserveDisplay = []): array
{
    $artist = trim((string) ($fields['artist'] ?? ''));
    $album = trim((string) ($fields['album'] ?? ''));
    $rawTitle = trim((string) ($fields['title'] ?? ''));
    $labels = bandpromo_campaign_resolve_track_display_labels($rawTitle, $artist, '');
    $preserve = bandpromo_asset_read_audio_display(['display' => $preserveDisplay]);

    $duration = max(0, (int) ($inspectData['duration_seconds'] ?? $inspectData['duration'] ?? 0));
    if ($duration <= 0) {
        $duration = max(0, (int) ($preserve['duration'] ?? 0));
    }
    $bitrate = max(0, (int) ($inspectData['bitrate_kbps'] ?? 0));
    if ($bitrate <= 0) {
        $bitrate = max(0, (int) ($preserve['bitrate_kbps'] ?? 0));
    }
    $sampleRate = max(0, (int) ($inspectData['sample_rate_hz'] ?? 0));
    if ($sampleRate <= 0) {
        $sampleRate = max(0, (int) ($preserve['sample_rate_hz'] ?? 0));
    }
    $bitDepth = max(0, (int) ($inspectData['bit_depth'] ?? 0));
    if ($bitDepth <= 0) {
        $bitDepth = max(0, (int) ($preserve['bit_depth'] ?? 0));
    }

    return [
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $artist,
        'album' => $album,
        'duration' => $duration,
        'bitrate_kbps' => $bitrate,
        'sample_rate_hz' => $sampleRate,
        'bit_depth' => $bitDepth,
        'date' => trim((string) ($fields['date'] ?? '')),
        'tracknumber' => trim((string) ($fields['tracknumber'] ?? '')),
        'bpm' => trim((string) ($fields['bpm'] ?? '')),
        'initialkey' => trim((string) ($fields['initialkey'] ?? '')),
        'genre' => trim((string) ($fields['genre'] ?? '')),
        'comment' => trim((string) ($fields['comment'] ?? '')),
        'lyrics' => (string) ($fields['lyrics'] ?? ''),
        'text_role' => bandpromo_asset_normalize_text_role((string) ($fields['text_role'] ?? 'lyrics')),
        'notes_label' => bandpromo_asset_normalize_notes_label((string) ($fields['notes_label'] ?? '')),
        'living_cover' => bandpromo_living_cover_normalize_video_filename(
            (string) ($fields['living_cover'] ?? ($inspectData['living_cover'] ?? $preserve['living_cover'] ?? ''))
        ),
        'cover' => bandpromo_asset_normalize_media_ref((string) (
            $inspectData['sidecar_cover']
            ?? $inspectData['cover']
            ?? $preserve['cover']
            ?? ''
        )),
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

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset === null) {
        return;
    }

    $existingDisplay = is_array($asset['display'] ?? null) ? $asset['display'] : [];
    bandpromo_asset_update_entry($root, (string) $asset['id'], [
        'display' => bandpromo_asset_build_audio_display_from_fields($fields, $inspectData, $existingDisplay),
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

    $inspect = bandpromo_campaign_inspect_master_metadata($root, $masterFile);
    $existingDisplay = is_array($asset['display'] ?? null) ? $asset['display'] : [];
    $display = bandpromo_asset_build_audio_display_from_inspect($inspect, $releaseTitle, $existingDisplay);
    if (trim((string) ($display['title'] ?? '')) === '') {
        return false;
    }

    // Sparse refresh fills empty lyrics/comment/cover — never replace an existing cover
    // with a stale inspect sidecar from earlier in the same PHP request.
    $existingCover = bandpromo_asset_normalize_media_ref((string) ($existingDisplay['cover'] ?? ''));
    if ($existingCover !== '') {
        $display['cover'] = $existingCover;
    }

    bandpromo_asset_update_entry($root, (string) $asset['id'], ['display' => $display]);

    return true;
}

/**
 * After upload: fill registry display from master tags, or filename-stem fallback.
 * Never leave an empty display that shows only the ULID master name.
 *
 * @return array{ok:bool,from_tags:bool,display:array,warning:string}
 */
function bandpromo_asset_ensure_audio_display_after_upload(
    string $root,
    string $masterFilename,
    string $originalFilename = ''
): array {
    $masterFilename = basename(trim($masterFilename));
    $originalFilename = basename(trim($originalFilename));
    if ($masterFilename === '') {
        return [
            'ok' => false,
            'from_tags' => false,
            'display' => [],
            'warning' => 'Master filename is required to refresh display metadata.',
        ];
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFilename);
    if ($asset === null) {
        return [
            'ok' => false,
            'from_tags' => false,
            'display' => [],
            'warning' => 'Audio asset is not registered yet.',
        ];
    }

    $inspect = bandpromo_campaign_inspect_master_metadata($root, $masterFilename);
    $rawTitle = trim((string) ($inspect['title'] ?? ''));
    $fromTags = $rawTitle !== '';

    if ($fromTags) {
        $existingDisplay = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $display = bandpromo_asset_build_audio_display_from_inspect($inspect, '', $existingDisplay);
    } else {
        $stemSource = $originalFilename !== '' ? $originalFilename : $masterFilename;
        $stem = pathinfo($stemSource, PATHINFO_FILENAME);
        if (
            $originalFilename !== ''
            && preg_match('/^ast_[0-9A-HJKMNP-TV-Z]{20}$/i', $stem) === 1
        ) {
            $stem = pathinfo($originalFilename, PATHINFO_FILENAME);
        }
        $title = trim(ucwords(str_replace(['_', '-'], ' ', $stem)));
        if ($title === '') {
            $title = 'Untitled';
        }
        $existing = bandpromo_asset_read_audio_display($asset);
        $display = bandpromo_asset_build_audio_display_from_fields([
            'title' => $title,
            'artist' => trim((string) ($inspect['artist'] ?? '')),
            'album' => trim((string) ($inspect['album'] ?? '')),
            'date' => trim((string) ($inspect['date'] ?? '')),
            'tracknumber' => trim((string) ($inspect['tracknumber'] ?? '')),
            'bpm' => trim((string) ($inspect['bpm'] ?? '')),
            'initialkey' => trim((string) ($inspect['initialkey'] ?? '')),
            'genre' => trim((string) ($inspect['genre'] ?? '')),
            'comment' => trim((string) ($inspect['comment'] ?? '')),
            'lyrics' => (string) ($inspect['lyrics'] ?? ''),
            'text_role' => $existing['text_role'],
            'notes_label' => $existing['notes_label'],
            'living_cover' => (string) ($inspect['living_cover'] ?? ''),
        ], $inspect);
    }

    bandpromo_asset_update_entry($root, (string) $asset['id'], ['display' => $display]);

    return [
        'ok' => true,
        'from_tags' => $fromTags,
        'display' => $display,
        'warning' => $fromTags
            ? ''
            : 'Could not read embedded tags from the master; using the filename as the title until you edit metadata.',
    ];
}

/**
 * Refresh registry display from master tags.
 *
 * @param bool $onlyIncomplete When true, skip rows that already have title+artist+duration
 *                             (Publish path — do not overwrite operator-saved display).
 * @return array{changed:int, items: list<string>}
 */
function bandpromo_asset_refresh_all_audio_displays(string $root, bool $onlyIncomplete = false): array
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

        if ($onlyIncomplete && bandpromo_asset_audio_display_is_complete(bandpromo_asset_read_audio_display($asset))) {
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

function bandpromo_campaign_enrich_track_row_labels(string $root, array $row, string $releaseTitle = ''): array
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

    $title = trim((string) ($display['title'] ?? ''));
    $version = trim((string) ($display['version'] ?? ''));

    if ($title !== '') {
        $row['title'] = $title;
        $row['version'] = $version;
    } else {
        $rawTitle = trim((string) ($row['title'] ?? ''));
        if ($rawTitle === '') {
            if ($asset !== null) {
                $labels = bandpromo_campaign_track_display_from_asset($asset, $masterFile);
                $rawTitle = trim((string) ($labels['title'] ?? ''));
                if ($artist === '') {
                    $artist = trim((string) ($labels['artist'] ?? ''));
                }
                if ($duration <= 0) {
                    $duration = (int) ($labels['duration'] ?? 0);
                }
                if ($version === '') {
                    $version = trim((string) ($labels['version'] ?? ''));
                }
            }
            if ($rawTitle === '') {
                $rawTitle = $masterFile;
            }
        }
        $labels = bandpromo_campaign_resolve_track_display_labels($rawTitle, $artist, $releaseTitle);
        $row['title'] = $labels['title'];
        $row['version'] = $version !== '' ? $version : $labels['version'];
    }

    $row['artist'] = $artist;
    $row['duration'] = $duration;
    if (trim((string) ($row['release_date'] ?? '')) === '') {
        $row['release_date'] = trim((string) ($display['date'] ?? ''));
    }

    return $row;
}

function bandpromo_campaign_enrich_editor_tracks(string $root, array $tracks): array
{
    $enriched = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $enriched[] = bandpromo_campaign_enrich_track_row_labels($root, $track);
    }

    return $enriched;
}

function bandpromo_campaign_sync_member_audio_tags(string $root, string $releaseId): int
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        return 0;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
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
        $masterFile = bandpromo_campaign_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }

        $inspect = bandpromo_campaign_inspect_master_metadata($root, $masterFile);
        $fields = [
            'title' => bandpromo_campaign_normalize_display_title((string) ($inspect['title'] ?? '')),
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

function bandpromo_campaign_pool_map_from_asset_registry(string $root, array $poolByFile): array
{
    $map = bandpromo_campaign_pool_map_with_asset_aliases($root, $poolByFile);
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

        $row = bandpromo_campaign_track_row_from_asset($root, $asset, $releaseId);
        $row['file'] = $masterFile;
        $row['asset_id'] = (string) ($asset['id'] ?? '');

        $originalFile = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFile !== '' && isset($poolByFile[$originalFile]) && is_array($poolByFile[$originalFile])) {
            $fromOriginal = bandpromo_campaign_track_row_from_pool($poolByFile[$originalFile], $releaseId);
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

function bandpromo_campaign_pool_map_canonical(string $root, array $poolByFile): array
{
    $aliased = bandpromo_campaign_pool_map_from_asset_registry($root, $poolByFile);
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

function bandpromo_campaign_track_display_from_asset(array $asset, string $masterFile): array
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
    if ($title === '' || bandpromo_campaign_title_looks_like_asset_id($title, $masterFile)) {
        if ($originalFile !== '') {
            $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($originalFile, PATHINFO_FILENAME)));
        }
    }
    if ($title === '' || bandpromo_campaign_title_looks_like_asset_id($title, $masterFile)) {
        $title = 'Untitled';
    }

    return [
        'title' => bandpromo_campaign_normalize_display_title($title),
        'version' => $version,
        'artist' => $artist,
        'album' => $album,
        'duration' => $duration,
        'release_date' => trim((string) ($display['date'] ?? '')),
    ];
}

function bandpromo_campaign_track_row_from_asset(string $root, array $asset, string $releaseId): array
{
    $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
    $labels = bandpromo_campaign_track_display_from_asset($asset, $masterFile);

    return [
        'file' => $masterFile,
        'asset_id' => (string) ($asset['id'] ?? ''),
        'title' => $labels['title'],
        'version' => $labels['version'],
        'artist' => $labels['artist'],
        'album' => $labels['album'],
        'duration' => $labels['duration'],
        'release_date' => $labels['release_date'],
        'origin' => trim((string) ($asset['origin'] ?? '')) !== ''
            ? trim((string) $asset['origin'])
            : 'user-upload',
        'sourceTier' => 'release-container',
        'deliveryReady' => true,
        'release_id' => $releaseId,
    ];
}

function bandpromo_editor_track_sort_label(array $track): string
{
    $artist = trim((string) ($track['artist'] ?? ''));
    $title = trim((string) ($track['title'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($track['file'] ?? ''));
    }
    if ($title === '') {
        $title = 'Untitled';
    }

    return $artist !== '' ? $artist . ' - ' . $title : $title;
}

function bandpromo_editor_sort_track_rows(array $tracks): array
{
    usort($tracks, static function (array $left, array $right): int {
        return strnatcasecmp(
            bandpromo_editor_track_sort_label($left),
            bandpromo_editor_track_sort_label($right)
        );
    });

    return $tracks;
}

function bandpromo_editor_sort_container_rows_by_title(array $rows): array
{
    usort($rows, static function (array $left, array $right): int {
        return strnatcasecmp(
            (string) ($left['title'] ?? $left['id'] ?? ''),
            (string) ($right['title'] ?? $right['id'] ?? '')
        );
    });

    return $rows;
}

function bandpromo_campaign_admin_editor_state(
    string $root,
    string $releaseId,
    array $poolByFile,
    array $meta = []
): array {
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        $releaseId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
    }

    $document = bandpromo_campaign_load_document($root, $releaseId);
    $releaseTitle = trim((string) ($document['title'] ?? ''));
    $activeFiles = [];
    $activeTracks = [];
    foreach ($document['tracks'] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = (string) ($track['asset_id'] ?? '');
        $masterFile = bandpromo_campaign_track_master_filename($root, $assetId);
        if ($masterFile === '') {
            continue;
        }
        $activeFiles[$masterFile] = true;
        $asset = $assetId !== '' ? bandpromo_asset_lookup_by_id($root, $assetId) : null;
        if (isset($poolByFile[$masterFile])) {
            $row = bandpromo_campaign_track_row_from_pool($poolByFile[$masterFile], $releaseId);
            if ($asset !== null) {
                $fromAsset = bandpromo_campaign_track_row_from_asset($root, $asset, $releaseId);
                if (bandpromo_campaign_title_looks_like_asset_id((string) ($row['title'] ?? ''), $masterFile)) {
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
                ? bandpromo_campaign_track_row_from_asset($root, $asset, $releaseId)
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
        $activeTracks[] = bandpromo_campaign_enrich_track_row_labels($root, $row, $releaseTitle);
    }

    $availableTracks = [];
    foreach ($poolByFile as $file => $track) {
        if (isset($activeFiles[$file])) {
            continue;
        }
        $trackReleaseId = bandpromo_campaign_normalize_id(
            bandpromo_campaign_id_for_master_filename($root, (string) $file)
        );
        if ($trackReleaseId !== '' && $trackReleaseId !== $releaseId) {
            continue;
        }
        $availableTracks[] = bandpromo_campaign_enrich_track_row_labels(
            $root,
            bandpromo_campaign_track_row_from_pool($track, $trackReleaseId !== '' ? $trackReleaseId : $releaseId),
            $releaseTitle
        );
    }
    $availableTracks = bandpromo_editor_sort_track_rows($availableTracks);

    return [
        'ok' => true,
        'release_id' => $releaseId,
        'locked' => !empty($document['locked']),
        'platform_demo' => bandpromo_campaign_is_platform_demo($releaseId),
        'can_change_lock' => bandpromo_campaign_may_change_lock($releaseId),
        'system_managed' => false,
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

function bandpromo_campaign_append_track_to_document(string $root, string $releaseId, string $assetId): void
{
    if ($assetId === '') {
        return;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return;
    }

    foreach ($document['tracks'] as $track) {
        if (($track['asset_id'] ?? '') === $assetId) {
            return;
        }
    }

    $asset = bandpromo_asset_lookup_by_id($root, $assetId);

    $document['tracks'][] = [
        'asset_id' => $assetId,
        'slug' => trim((string) ($asset['slug'] ?? '')),
    ];
    bandpromo_campaign_write_document($root, $document);
}

function bandpromo_campaign_remove_asset_from_document(string $root, string $releaseId, string $assetId): void
{
    if ($assetId === '') {
        return;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
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

    bandpromo_campaign_write_document($root, $document);
}

function bandpromo_campaign_save_tracks(string $root, string $releaseId, array $masterFiles): array
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Campaign id is required.');
    }

    $document = bandpromo_campaign_load_document($root, $releaseId);
    if (!empty($document['locked'])) {
        throw new RuntimeException('This campaign is locked and cannot be edited.');
    }

    $tracks = [];
    $skipped = [];
    $assetIds = [];
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
        ];
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
            bandpromo_campaign_remove_asset_from_document($root, $currentReleaseId, $assetId);
        }
    }

    $document['tracks'] = $tracks;
    bandpromo_campaign_write_document($root, $document);

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
        if (trim((string) ($registry['assets'][$assetId]['release_id'] ?? '')) !== '') {
            $registry['assets'][$assetId]['release_id'] = '';
            $registryChanged = true;
        }
    }

    if ($registryChanged) {
        bandpromo_asset_write_registry($root, $registry);
    }

    bandpromo_demo_catalog_restore_if_operator_campaign_gone($root);

    return [
        'tracks' => $tracks,
        'skipped' => $skipped,
        'count' => count($tracks),
        'tags_synced' => 0,
    ];
}

function bandpromo_campaign_create(string $root, string $title, string $preferredId = ''): array
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Campaign name is required.');
    }

    $registry = bandpromo_campaign_load_registry($root);
    $baseId = $preferredId !== ''
        ? bandpromo_campaign_normalize_id($preferredId)
        : bandpromo_campaign_slug_from_title($title);
    if ($baseId === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        throw new InvalidArgumentException('Campaign id is invalid. Use lowercase letters, numbers, and hyphens.');
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
    bandpromo_campaign_write_registry($root, $registry);

    $document = bandpromo_campaign_default_document();
    $document['id'] = $id;
    $document['slug'] = $id;
    $document['title'] = $title;
    $document['tracks'] = [];
    bandpromo_campaign_write_document($root, $document);

    return bandpromo_campaign_admin_registry_entry($root, bandpromo_campaign_registry_entry($root, $id) ?? []);
}

function bandpromo_campaign_update_details(string $root, string $releaseId, array $fields): array
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Campaign id is required.');
    }

    $title = trim((string) ($fields['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Campaign name is required.');
    }

    $releaseDate = trim((string) ($fields['release_date'] ?? ''));
    if (!bandpromo_campaign_validate_date($releaseDate)) {
        throw new InvalidArgumentException('Campaign date must use YYYY or YYYY-MM-DD.');
    }

    $locked = array_key_exists('locked', $fields) ? !empty($fields['locked']) : null;
    if ($locked === false && !bandpromo_campaign_may_change_lock($releaseId)) {
        throw new InvalidArgumentException('The bandPromo demo campaign can only be unlocked on localhost.');
    }

    $registry = bandpromo_campaign_load_registry($root);
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
        throw new InvalidArgumentException('Unknown campaign.');
    }
    bandpromo_campaign_write_registry($root, $registry);

    $document = bandpromo_campaign_load_document($root, $releaseId);
    $document['title'] = $title;
    $document['release_date'] = $releaseDate;
    if ($locked !== null) {
        $document['locked'] = $locked;
    }
    if (array_key_exists('short_description', $fields)) {
        $document['short_description'] = bandpromo_campaign_normalize_text_field($fields['short_description'], 300);
    }
    if (array_key_exists('catalog_id', $fields)) {
        $document['catalog_id'] = bandpromo_campaign_normalize_text_field($fields['catalog_id'], 80);
    }
    if (array_key_exists('description', $fields)) {
        $document['description'] = bandpromo_campaign_normalize_text_field($fields['description'], 4000);
    }
    if (array_key_exists('poster_asset_id', $fields)) {
        $document['poster_asset_id'] = bandpromo_campaign_normalize_poster_asset_id($root, $fields['poster_asset_id']);
    }
    if (array_key_exists('brand_id', $fields)) {
        $previousBrandId = bandpromo_campaign_normalize_brand_id($root, $document['brand_id'] ?? '');
        $document['brand_id'] = bandpromo_campaign_normalize_brand_id($root, $fields['brand_id']);
        $nextBrandId = (string) ($document['brand_id'] ?? '');

        // Keep brand.campaign_id aligned so ownership inference and player shell stay in sync.
        if ($nextBrandId !== '') {
            try {
                $brandDocument = bandpromo_brand_load_document($root, $nextBrandId);
                if (bandpromo_document_campaign_id($brandDocument) !== $releaseId) {
                    $brandDocument = bandpromo_document_with_campaign_id($brandDocument, $releaseId);
                    bandpromo_brand_write_document($root, $brandDocument, ['allow_locked' => true]);
                }
            } catch (Throwable $throwable) {
                // Brand document may be missing; campaign pointer still saved.
            }
        }

        if ($previousBrandId !== $nextBrandId) {
            require_once __DIR__ . '/playlist-storage.php';
            if ($nextBrandId !== '') {
                bandpromo_playlist_refresh_brand_styles_for_brand($root, $nextBrandId);
            }
            // Republish owned playlists so SSR/player payloads pick up the new brand shell.
            foreach (bandpromo_playlist_registry_entries($root) as $playlistEntry) {
                if (!is_array($playlistEntry)) {
                    continue;
                }
                $playlistId = bandpromo_playlist_normalize_id((string) ($playlistEntry['id'] ?? ''));
                if ($playlistId === '') {
                    continue;
                }
                try {
                    $playlistDocument = bandpromo_playlist_load_document($root, $playlistId);
                } catch (Throwable $throwable) {
                    continue;
                }
                if (bandpromo_document_campaign_id($playlistDocument) !== $releaseId) {
                    continue;
                }
                try {
                    bandpromo_playlist_publish_player_payload($root, $playlistId);
                } catch (Throwable $throwable) {
                    // Leave build-required; operator can Publish manually.
                }
            }
        }
    }
    if (array_key_exists('epk', $fields)) {
        $pressContact = bandpromo_site_contact_sanitize_input((string) (($fields['epk']['press_contact'] ?? '') ?: ''));
        if ($pressContact !== '' && !bandpromo_site_contact_is_valid($pressContact)) {
            throw new InvalidArgumentException(bandpromo_site_contact_invalid_message());
        }
        $document['epk'] = bandpromo_campaign_normalize_epk($fields['epk']);
    }
    bandpromo_campaign_write_document($root, $document);

    $updated = bandpromo_campaign_registry_entry($root, $releaseId);
    if ($updated === null) {
        throw new RuntimeException('Could not load updated release.');
    }

    return bandpromo_campaign_admin_registry_entry($root, $updated);
}

/**
 * True when primary is the invisible upload bucket, not an operator-named campaign.
 */
function bandpromo_campaign_primary_looks_like_upload_bucket(array $document): bool
{
    $title = trim((string) ($document['title'] ?? ''));
    if ($title === '') {
        return true;
    }

    foreach (['Default release', 'Default campaign'] as $legacyTitle) {
        if (strcasecmp($title, $legacyTitle) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Orphan audio uploads stuck on the invisible `primary` bucket (legacy Default release).
 * Clears primary track membership and stale release_id=primary registry tags.
 *
 * @return array{
 *   ok: bool,
 *   dry_run: bool,
 *   skipped: bool,
 *   skip_reason?: string,
 *   tracks_orphaned: int,
 *   registry_cleared: int,
 *   changed: int
 * }
 */
function bandpromo_campaign_orphan_uploads_on_primary(string $root, bool $dryRun = false): array
{
    require_once __DIR__ . '/asset-registry.php';

    $releaseId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
    $empty = [
        'ok' => true,
        'dry_run' => $dryRun,
        'skipped' => true,
        'tracks_orphaned' => 0,
        'registry_cleared' => 0,
        'changed' => 0,
    ];

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return array_merge($empty, [
            'ok' => false,
            'skip_reason' => 'Could not load primary release: ' . $throwable->getMessage(),
        ]);
    }

    if (!bandpromo_campaign_primary_looks_like_upload_bucket($document)) {
        return array_merge($empty, [
            'skip_reason' => 'primary holds a named operator campaign; skipped automatic orphan repair.',
        ]);
    }

    $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
    $trackCount = count($tracks);

    $registry = bandpromo_asset_load_registry($root);
    $registryPrimaryCount = 0;
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        if (bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? ''))) === $releaseId) {
            $registryPrimaryCount++;
        }
    }

    if ($trackCount === 0 && $registryPrimaryCount === 0) {
        return array_merge($empty, [
            'skip_reason' => 'primary upload bucket already empty.',
        ]);
    }

    if ($dryRun) {
        return [
            'ok' => true,
            'dry_run' => true,
            'skipped' => false,
            'tracks_orphaned' => $trackCount,
            'registry_cleared' => $registryPrimaryCount,
            'changed' => $trackCount + $registryPrimaryCount,
        ];
    }

    $emptyPrimary = bandpromo_campaign_default_document();
    $emptyPrimary['release_date'] = gmdate('Y-m-d');
    bandpromo_campaign_write_document($root, $emptyPrimary);
    bandpromo_campaign_invalidate_runtime_cache($root);

    $registryChanged = 0;
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        if (bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? ''))) !== $releaseId) {
            continue;
        }
        $registry['assets'][$assetId]['release_id'] = '';
        $registryChanged++;
    }
    if ($registryChanged > 0) {
        bandpromo_asset_write_registry($root, $registry);
    }

    bandpromo_campaign_repair_catalog_release_ids($root);
    bandpromo_campaign_invalidate_runtime_cache($root);

    return [
        'ok' => true,
        'dry_run' => false,
        'skipped' => false,
        'tracks_orphaned' => $trackCount,
        'registry_cleared' => $registryChanged,
        'changed' => $trackCount + $registryChanged,
    ];
}

/**
 * Move a real campaign that was incorrectly stored as the invisible `primary`
 * orphan/upload bucket onto a normal operator release id, then restore an empty
 * primary document. Local installs that renamed Default release in place need this.
 *
 * @return array{ok:bool,dry_run:bool,from:string,to:string,actions:list<string>,skipped?:string}
 */
function bandpromo_campaign_migrate_campaign_off_primary(string $root, string $newId = '', bool $dryRun = false): array
{
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $fromId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
    $actions = [];

    try {
        $source = bandpromo_campaign_load_document($root, $fromId);
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'from' => $fromId,
            'to' => '',
            'actions' => [],
            'skipped' => 'Could not load primary release: ' . $throwable->getMessage(),
        ];
    }

    $title = trim((string) ($source['title'] ?? ''));
    $tracks = is_array($source['tracks'] ?? null) ? $source['tracks'] : [];
    $isDefaultEmpty = ($title === '' || strcasecmp($title, 'Default release') === 0)
        && $tracks === []
        && trim((string) ($source['brand_id'] ?? '')) === ''
        && trim((string) ($source['poster_asset_id'] ?? '')) === '';

    if ($isDefaultEmpty) {
        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'from' => $fromId,
            'to' => '',
            'actions' => [],
            'skipped' => 'primary is already an empty orphan bucket.',
        ];
    }

    $preferred = bandpromo_campaign_normalize_id($newId !== '' ? $newId : bandpromo_campaign_slug_from_title($title));
    if ($preferred === '' || $preferred === $fromId || $preferred === BANDPROMO_RELEASE_DEMO_ID) {
        $preferred = 'operator-release';
    }

    $registry = bandpromo_campaign_load_registry($root);
    $existing = [];
    foreach ($registry['releases'] as $entry) {
        $existing[(string) ($entry['id'] ?? '')] = true;
    }
    $toId = $preferred;
    $suffix = 2;
    while (isset($existing[$toId])) {
        $toId = substr($preferred, 0, 44) . '-' . $suffix;
        $suffix++;
    }

    $trackAssetIds = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $assetId = trim((string) ($track['asset_id'] ?? ''));
        if ($assetId !== '') {
            $trackAssetIds[$assetId] = true;
        }
    }
    $posterId = trim((string) ($source['poster_asset_id'] ?? ''));
    if ($posterId !== '') {
        $trackAssetIds[$posterId] = true;
    }

    $actions[] = 'Create campaign ' . $toId . ' from primary campaign "' . $title . '" (' . count($tracks) . ' tracks).';
    $actions[] = 'Reset primary to empty orphan/upload bucket.';

    if ($dryRun) {
        return [
            'ok' => true,
            'dry_run' => true,
            'from' => $fromId,
            'to' => $toId,
            'actions' => $actions,
        ];
    }

    $maxOrder = 0;
    foreach ($registry['releases'] as &$regEntry) {
        $maxOrder = max($maxOrder, (int) ($regEntry['sort_order'] ?? 0));
        if ((string) ($regEntry['id'] ?? '') === $fromId) {
            $regEntry['title'] = 'Default release';
            $regEntry['slug'] = $fromId;
        }
    }
    unset($regEntry);

    $registry['releases'][] = [
        'id' => $toId,
        'title' => $title !== '' ? $title : $toId,
        'slug' => $toId,
        'sort_order' => $maxOrder + 10,
        'system' => false,
    ];
    bandpromo_campaign_write_registry($root, $registry);

    $document = $source;
    $document['id'] = $toId;
    $document['slug'] = $toId;
    $document['title'] = $title !== '' ? $title : $toId;
    bandpromo_campaign_write_document($root, bandpromo_campaign_normalize_document($document, $toId, $root));

    $emptyPrimary = bandpromo_campaign_default_document();
    $emptyPrimary['release_date'] = gmdate('Y-m-d');
    bandpromo_campaign_write_document($root, $emptyPrimary);

    // Assets tagged to primary that belong to this campaign.
    $assetRegistry = bandpromo_asset_load_registry($root);
    $assetChanged = 0;
    foreach ($assetRegistry['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if ((string) ($asset['release_id'] ?? '') !== $fromId) {
            continue;
        }
        if (!isset($trackAssetIds[$assetId]) && (string) ($asset['kind'] ?? '') === 'audio') {
            // Leave true orphans on primary.
            continue;
        }
        if ((string) ($asset['kind'] ?? '') === 'audio' || $assetId === $posterId) {
            $asset['release_id'] = $toId;
            $normalized = bandpromo_asset_normalize_entry($asset);
            if ($normalized !== null) {
                $assetRegistry['assets'][$assetId] = $normalized;
                $assetChanged++;
            }
        }
    }
    if ($assetChanged > 0) {
        bandpromo_asset_write_registry($root, $assetRegistry);
        $actions[] = 'Retagged ' . $assetChanged . ' asset registry release_id value(s).';
    }

    // Brands pointing at primary.
    try {
        bandpromo_brand_ensure_seeded($root);
        foreach (bandpromo_brand_registry_entries($root) as $brandEntry) {
            $brandId = trim((string) ($brandEntry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $brandDoc = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            if ((string) ($brandDoc['release_id'] ?? '') !== $fromId) {
                continue;
            }
            $brandDoc['release_id'] = $toId;
            bandpromo_brand_write_document($root, $brandDoc);
            $actions[] = 'Brand ' . $brandId . ' → release_id ' . $toId . '.';
        }
    } catch (Throwable $throwable) {
        $actions[] = 'Brand retarget skipped: ' . $throwable->getMessage();
    }

    // Playlists: retarget release_id; rename playlist id "primary" when it is the campaign playlist.
    try {
        bandpromo_playlist_ensure_seeded($root);
        $playlistRegistry = bandpromo_playlist_load_registry($root);
        $playlistIds = [];
        foreach ($playlistRegistry['playlists'] as $entry) {
            if (is_array($entry)) {
                $playlistIds[] = (string) ($entry['id'] ?? '');
            }
        }

        foreach ($playlistIds as $playlistId) {
            if ($playlistId === '') {
                continue;
            }
            try {
                $playlist = bandpromo_playlist_load_document($root, $playlistId);
            } catch (Throwable $throwable) {
                continue;
            }
            $releaseId = (string) ($playlist['release_id'] ?? '');
            if ($releaseId !== $fromId && $playlistId !== $fromId) {
                continue;
            }

            if ($playlistId === $fromId) {
                // Rename primary playlist → new campaign id (file + registry).
                $newPlaylistId = $toId;
                $suffix = 2;
                while ($newPlaylistId !== $fromId && is_file(bandpromo_playlist_document_path($root, $newPlaylistId))) {
                    $newPlaylistId = substr($toId, 0, 44) . '-' . $suffix;
                    $suffix++;
                }
                $playlist['id'] = $newPlaylistId;
                $playlist['slug'] = bandpromo_playlist_normalize_slug(
                    (string) ($playlist['slug'] ?? $newPlaylistId),
                    $newPlaylistId
                );
                $playlist['release_id'] = $toId;
                if (isset($playlist['entries']) && is_array($playlist['entries'])) {
                    foreach ($playlist['entries'] as &$entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        if ((string) ($entry['release_id'] ?? '') === $fromId) {
                            $entry['release_id'] = $toId;
                        }
                    }
                    unset($entry);
                }
                bandpromo_playlist_write_document($root, $playlist);

                $playlistRegistry['playlists'] = array_values(array_filter(
                    $playlistRegistry['playlists'],
                    static fn(array $entry): bool => (string) ($entry['id'] ?? '') !== $fromId
                ));
                $playlistRegistry['playlists'][] = [
                    'id' => $newPlaylistId,
                    'title' => (string) ($playlist['title'] ?? $newPlaylistId),
                    'kind' => (string) ($playlist['kind'] ?? 'system'),
                    'publish_date' => (string) ($playlist['publish_date'] ?? ''),
                    'sort_order' => 20,
                ];
                bandpromo_playlist_write_registry($root, $playlistRegistry);
                $oldPath = bandpromo_playlist_document_path($root, $fromId);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $actions[] = 'Renamed playlist primary → ' . $newPlaylistId . '.';
                continue;
            }

            $playlist['release_id'] = $toId;
            bandpromo_playlist_write_document($root, $playlist);
            $actions[] = 'Playlist ' . $playlistId . ' → release_id ' . $toId . '.';
        }
    } catch (Throwable $throwable) {
        $actions[] = 'Playlist retarget skipped: ' . $throwable->getMessage();
    }

    return [
        'ok' => true,
        'dry_run' => false,
        'from' => $fromId,
        'to' => $toId,
        'actions' => $actions,
    ];
}

function bandpromo_campaign_delete(string $root, string $releaseId): void
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if (bandpromo_campaign_is_protected_id($releaseId)) {
        throw new InvalidArgumentException('This campaign cannot be deleted.');
    }

    $registry = bandpromo_campaign_load_registry($root);
    $before = count($registry['releases']);
    $registry['releases'] = array_values(array_filter(
        $registry['releases'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $releaseId
    ));
    if (count($registry['releases']) === $before) {
        throw new InvalidArgumentException('Unknown campaign.');
    }

    bandpromo_campaign_write_registry($root, $registry);

    $path = bandpromo_campaign_document_path($root, $releaseId);
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
        $registryAssets['assets'][$assetId]['release_id'] = '';
        $changed = true;
    }
    if ($changed) {
        bandpromo_asset_write_registry($root, $registryAssets);
    }

    bandpromo_demo_catalog_restore_if_operator_campaign_gone($root);
}

/**
 * True when another remaining campaign/brand still needs this asset.
 */
function bandpromo_campaign_purge_asset_still_needed(string $root, string $assetId): bool
{
    require_once __DIR__ . '/campaign-package.php';
    require_once __DIR__ . '/brand-storage.php';

    $assetId = trim($assetId);
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return false;
    }

    foreach (bandpromo_campaign_load_registry($root)['releases'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $otherId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($otherId === '') {
            continue;
        }
        foreach (bandpromo_campaign_campaign_collect_asset_ids($root, $otherId) as $usedId) {
            if ($usedId === $assetId) {
                return true;
            }
        }
    }

    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $brandId = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($brandId === '') {
            continue;
        }
        try {
            $brand = bandpromo_brand_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            continue;
        }
        foreach (is_array($brand['asset_ids'] ?? null) ? $brand['asset_ids'] : [] as $slotId) {
            if (trim((string) $slotId) === $assetId) {
                return true;
            }
        }
        foreach (is_array($brand['library_asset_ids'] ?? null) ? $brand['library_asset_ids'] : [] as $libraryId) {
            if (trim((string) $libraryId) === $assetId) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Delete on-disk tiers + registry row for one asset (best-effort).
 */
function bandpromo_campaign_purge_delete_asset(string $root, array $asset): void
{
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/audio-master-helpers.php';

    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return;
    }

    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'visual') {
        bandpromo_visual_delivery_delete_for_asset($root, $assetId);
        bandpromo_visual_delete_tier_files($root, $asset);
    } elseif ($kind === 'sfx') {
        bandpromo_sfx_delete_tier_files($root, $asset);
    } elseif ($kind === 'audio') {
        $original = basename((string) ($asset['original_filename'] ?? ''));
        $master = basename((string) ($asset['master_filename'] ?? ''));
        $listing = $original !== '' ? $original : $master;
        if ($listing !== '') {
            foreach (bandpromo_audio_master_paths_for_original($root, $listing) as $masterPath) {
                if (is_file($masterPath)) {
                    @unlink($masterPath);
                }
            }
            foreach (bandpromo_audio_delivery_paths_for_original($root, $listing) as $deliveryPath) {
                if (is_file($deliveryPath)) {
                    @unlink($deliveryPath);
                }
            }
            $originalPath = $root . '/media/audio/original/' . $listing;
            if (is_file($originalPath)) {
                @unlink($originalPath);
            }
        }
        if ($master !== '') {
            $masterPath = $root . '/media/audio/master/' . $master;
            if (is_file($masterPath)) {
                @unlink($masterPath);
            }
            $deliveryById = $root . '/media/audio/optimal/' . $assetId . '.mp3';
            if (is_file($deliveryById)) {
                @unlink($deliveryById);
            }
        }
    }

    bandpromo_asset_unregister($root, $assetId);
}

/**
 * Delete a release. mode=container keeps media; mode=purge removes owned campaign content
 * and media that nothing else still references (shared duplicate media is kept).
 *
 * @return array{
 *   ok: bool,
 *   mode: string,
 *   release_id: string,
 *   deleted_playlists: list<string>,
 *   deleted_galleries: list<string>,
 *   deleted_pages: list<string>,
 *   deleted_brand_id: string,
 *   deleted_assets: list<string>,
 *   retained_shared_assets: list<string>
 * }
 */
function bandpromo_campaign_delete_with_mode(string $root, string $releaseId, string $mode = 'container'): array
{
    require_once __DIR__ . '/campaign-ownership-helpers.php';
    require_once __DIR__ . '/campaign-package.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/brand-storage.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $mode = strtolower(trim($mode));
    if ($mode !== 'purge') {
        $mode = 'container';
    }

    if (bandpromo_campaign_is_protected_id($releaseId)) {
        throw new InvalidArgumentException('This campaign cannot be deleted.');
    }
    if (!is_file(bandpromo_campaign_document_path($root, $releaseId))) {
        throw new InvalidArgumentException('Unknown campaign.');
    }

    $result = [
        'ok' => true,
        'mode' => $mode,
        'release_id' => $releaseId,
        'deleted_playlists' => [],
        'deleted_galleries' => [],
        'deleted_pages' => [],
        'deleted_brand_id' => '',
        'deleted_assets' => [],
        'retained_shared_assets' => [],
    ];

    if ($mode === 'container') {
        bandpromo_campaign_delete($root, $releaseId);

        return $result;
    }

    $children = bandpromo_campaign_ownership_children($root, $releaseId);
    $assetIds = bandpromo_campaign_campaign_collect_asset_ids($root, $releaseId);
    $registryAssets = bandpromo_asset_load_registry($root);
    foreach ($registryAssets['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if ((string) ($asset['release_id'] ?? '') === $releaseId) {
            $assetIds[] = (string) $assetId;
        }
    }
    $assetIds = array_values(array_unique(array_filter($assetIds)));

    foreach ($children['playlists'] as $playlist) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($playlist['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            bandpromo_playlist_delete($root, $playlistId);
            $result['deleted_playlists'][] = $playlistId;
        } catch (Throwable $throwable) {
            // Protected/demo playlists stay; purge continues.
        }
    }

    foreach ($children['galleries'] as $gallery) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($gallery['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            bandpromo_gallery_delete($root, $galleryId);
            $result['deleted_galleries'][] = $galleryId;
        } catch (Throwable $throwable) {
            // Protected galleries stay.
        }
    }

    foreach ($children['pages'] as $page) {
        $pageId = bandpromo_page_normalize_id((string) ($page['id'] ?? ''));
        if ($pageId === '' || !bandpromo_campaign_campaign_page_is_portable($pageId)) {
            continue;
        }
        try {
            bandpromo_page_delete_page($root, $pageId);
            $result['deleted_pages'][] = $pageId;
        } catch (Throwable $throwable) {
            // FAQ / unknown pages stay.
        }
    }

    $brandId = bandpromo_brand_canonical_id((string) ($children['brand_id'] ?? ''));
    if ($brandId !== '' && $brandId !== BANDPROMO_BRAND_DEFAULT_ID) {
        $brandExclusive = true;
        try {
            $brandDoc = bandpromo_brand_load_document($root, $brandId);
            $brandOwner = bandpromo_campaign_normalize_id((string) ($brandDoc['release_id'] ?? ''));
            if ($brandOwner !== '' && $brandOwner !== $releaseId) {
                $brandExclusive = false;
            }
            if (!empty($brandDoc['locked']) || !empty($brandDoc['system'])) {
                $brandExclusive = false;
            }
        } catch (Throwable $throwable) {
            $brandExclusive = false;
        }
        if ($brandExclusive) {
            foreach (bandpromo_campaign_load_registry($root)['releases'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $otherId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
                if ($otherId === '' || $otherId === $releaseId) {
                    continue;
                }
                try {
                    $otherDoc = bandpromo_campaign_load_document($root, $otherId);
                } catch (Throwable $throwable) {
                    continue;
                }
                if (bandpromo_brand_canonical_id((string) ($otherDoc['brand_id'] ?? '')) === $brandId) {
                    $brandExclusive = false;
                    break;
                }
            }
        }
        if ($brandExclusive) {
            try {
                if (bandpromo_brand_active_id($root) === $brandId) {
                    bandpromo_brand_set_active_id($root, BANDPROMO_BRAND_DEFAULT_ID);
                }
                bandpromo_brand_delete($root, $brandId);
                $result['deleted_brand_id'] = $brandId;
            } catch (Throwable $throwable) {
                // Keep brand when active/locked constraints still apply.
            }
        }
    }

    // Remove release document before asset GC so membership scans skip it.
    bandpromo_campaign_delete($root, $releaseId);

    $registryAssets = bandpromo_asset_load_registry($root);
    foreach ($assetIds as $assetId) {
        $asset = $registryAssets['assets'][$assetId] ?? null;
        if (!is_array($asset)) {
            continue;
        }
        if (bandpromo_campaign_purge_asset_still_needed($root, $assetId)) {
            $result['retained_shared_assets'][] = $assetId;
            continue;
        }
        try {
            bandpromo_campaign_purge_delete_asset($root, $asset);
            $result['deleted_assets'][] = $assetId;
        } catch (Throwable $throwable) {
            $result['retained_shared_assets'][] = $assetId;
        }
        $registryAssets = bandpromo_asset_load_registry($root);
    }

    try {
        require_once __DIR__ . '/media-library-state.php';
        bandpromo_media_files_index_rebuild_all($root);
    } catch (Throwable $throwable) {
        // Files list heals on next GET.
    }

    return $result;
}

function bandpromo_campaign_repair_catalog_release_ids(string $root): int
{
    $registry = bandpromo_asset_load_registry($root);
    $membershipIndex = bandpromo_campaign_asset_membership_index($root);
    $changed = 0;

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $assetId = trim((string) $assetId);
        if ($assetId === '') {
            continue;
        }

        $assignedReleaseId = bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
        $memberships = $membershipIndex[$assetId] ?? [];
        $documentReleaseId = '';
        if (count($memberships) === 1) {
            $documentReleaseId = bandpromo_campaign_normalize_id((string) ($memberships[0]['release_id'] ?? ''));
        }

        if ($documentReleaseId === '') {
            if ($assignedReleaseId !== '') {
                $registry['assets'][$assetId]['release_id'] = '';
                $changed++;
            }
            continue;
        }

        if ($assignedReleaseId !== $documentReleaseId) {
            $registry['assets'][$assetId]['release_id'] = $documentReleaseId;
            $changed++;
        }
    }

    if ($changed > 0) {
        bandpromo_asset_write_registry($root, $registry);
    }

    return $changed;
}

/**
 * Normalize artist+title for matching re-registered audio assets.
 */
function bandpromo_audio_identity_fingerprint(string $artist, string $title): string
{
    $normalize = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    };

    return $normalize($artist) . "\n" . $normalize($title);
}

/**
 * Extra identity keys so "#06" can match "#06 FINAL" / "#11 NEWER WIP".
 *
 * @return list<string>
 */
function bandpromo_audio_identity_keys(string $artist, string $title): array
{
    $title = trim($title);
    $artist = trim($artist);
    if ($title === '') {
        return [];
    }

    $keys = [bandpromo_audio_identity_fingerprint($artist, $title)];
    $looseTitle = trim((string) preg_replace(
        '/\s+(\(|\[)?(final|newer\s+wip|wip|trance|radio\s+edit|extended(?:\s+version)?|original(?:\s+club)?\s+mix)(\)|\])?\s*$/iu',
        '',
        $title
    ));
    if ($looseTitle !== '' && strcasecmp($looseTitle, $title) !== 0) {
        $keys[] = bandpromo_audio_identity_fingerprint($artist, $looseTitle);
    }

    if (preg_match('/^(.*?#\s*\d{1,3})\b/u', $looseTitle !== '' ? $looseTitle : $title, $matches) === 1) {
        $seriesTitle = trim((string) ($matches[1] ?? ''));
        if ($seriesTitle !== '') {
            $keys[] = bandpromo_audio_identity_fingerprint($artist, $seriesTitle);
            $keys[] = bandpromo_audio_identity_fingerprint('', $seriesTitle);
        }
    }

    return array_values(array_unique(array_filter($keys, static fn(string $key): bool => $key !== "\n" && $key !== '')));
}

/**
 * Index live audio assets by identity fingerprint and master basename.
 *
 * @return array{by_fingerprint: array<string, list<string>>, by_master: array<string, string>}
 */
function bandpromo_campaign_live_audio_identity_index(string $root): array
{
    $byFingerprint = [];
    $byMaster = [];

    foreach (bandpromo_asset_load_registry($root)['assets'] as $assetId => $asset) {
        if (!is_array($asset) || strtolower((string) ($asset['kind'] ?? 'audio')) !== 'audio') {
            continue;
        }
        $assetId = trim((string) ($asset['id'] ?? $assetId));
        if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
            continue;
        }

        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFile !== '') {
            $byMaster[strtolower($masterFile)] = $assetId;
            $stem = strtolower((string) pathinfo($masterFile, PATHINFO_FILENAME));
            if ($stem !== '') {
                $byMaster[$stem] = $assetId;
            }
        }

        $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $title = trim((string) ($display['title'] ?? ''));
        $artist = trim((string) ($display['artist'] ?? ''));
        if ($title === '' && $masterFile !== '') {
            $inspect = bandpromo_campaign_inspect_master_metadata($root, $masterFile);
            $title = trim((string) ($inspect['title'] ?? ''));
            if ($artist === '') {
                $artist = trim((string) ($inspect['artist'] ?? ''));
            }
        }
        if ($title === '') {
            continue;
        }
        foreach (bandpromo_audio_identity_keys($artist, $title) as $key) {
            if (!isset($byFingerprint[$key])) {
                $byFingerprint[$key] = [];
            }
            if (!in_array($assetId, $byFingerprint[$key], true)) {
                $byFingerprint[$key][] = $assetId;
            }
        }
    }

    return [
        'by_fingerprint' => $byFingerprint,
        'by_master' => $byMaster,
    ];
}

/**
 * Resolve a replacement live asset id for a stale membership reference.
 */
function bandpromo_campaign_resolve_replacement_audio_asset_id(
    string $root,
    string $staleAssetId,
    array $identityIndex,
    string $hintTitle = '',
    string $hintArtist = ''
): string {
    $staleAssetId = trim($staleAssetId);
    if ($staleAssetId !== '' && bandpromo_asset_is_asset_id($staleAssetId)) {
        $existing = bandpromo_asset_lookup_by_id($root, $staleAssetId);
        if (is_array($existing) && strtolower((string) ($existing['kind'] ?? 'audio')) === 'audio') {
            return (string) ($existing['id'] ?? $staleAssetId);
        }
    }

    $byMaster = $identityIndex['by_master'] ?? [];
    foreach ([$staleAssetId . '.mp3', $staleAssetId . '.flac', $staleAssetId . '.wav', $staleAssetId] as $candidate) {
        $hit = $byMaster[strtolower($candidate)] ?? '';
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }
    }

    $title = trim($hintTitle);
    $artist = trim($hintArtist);
    if ($title === '' && $staleAssetId !== '') {
        foreach (['.mp3', '.flac', '.wav'] as $ext) {
            $leftover = $root . '/media/audio/master/' . $staleAssetId . $ext;
            if (!is_file($leftover)) {
                continue;
            }
            $inspect = bandpromo_campaign_inspect_master_metadata($root, $staleAssetId . $ext);
            $title = trim((string) ($inspect['title'] ?? ''));
            if ($artist === '') {
                $artist = trim((string) ($inspect['artist'] ?? ''));
            }
            break;
        }
    }

    if ($title === '') {
        return '';
    }

    foreach (bandpromo_audio_identity_keys($artist, $title) as $key) {
        $matches = $identityIndex['by_fingerprint'][$key] ?? [];
        if (count($matches) === 1) {
            return (string) $matches[0];
        }
    }

    return '';
}

/**
 * Rebind campaign track asset_ids that point at deleted registry entries onto live audio
 * assets with the same embedded artist/title (common after re-upload/re-register).
 * Tracks with no live replacement and no registry asset are dropped.
 *
 * @return array{rebound:int, dropped:int, unresolved:int, remaps: array<string,string>, releases: list<string>}
 */
function bandpromo_campaign_repair_stale_membership_asset_ids(string $root): array
{
    $identityIndex = bandpromo_campaign_live_audio_identity_index($root);
    $remaps = [];
    $rebound = 0;
    $dropped = 0;
    $unresolved = 0;
    $touchedReleases = [];

    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }
        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }

        $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
        if ($tracks === []) {
            continue;
        }

        $changed = false;
        $seen = [];
        $nextTracks = [];
        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }
            $oldId = trim((string) ($track['asset_id'] ?? ''));
            if ($oldId === '') {
                continue;
            }

            $replacement = $remaps[$oldId] ?? '';
            if ($replacement === '') {
                $replacement = bandpromo_campaign_resolve_replacement_audio_asset_id(
                    $root,
                    $oldId,
                    $identityIndex
                );
                if ($replacement !== '' && $replacement !== $oldId) {
                    $remaps[$oldId] = $replacement;
                }
            }

            if ($replacement === '') {
                if (!bandpromo_asset_is_asset_id($oldId) || bandpromo_asset_lookup_by_id($root, $oldId) === null) {
                    $unresolved++;
                    $dropped++;
                    $changed = true;
                    continue;
                }
                $normalized = bandpromo_campaign_normalize_track_entry($track);
                if ($normalized !== null && !isset($seen[$normalized['asset_id']])) {
                    $seen[$normalized['asset_id']] = true;
                    $nextTracks[] = $normalized;
                }
                continue;
            }

            if ($replacement !== $oldId) {
                $track['asset_id'] = $replacement;
                $changed = true;
                $rebound++;
            }
            $normalized = bandpromo_campaign_normalize_track_entry($track);
            if ($normalized === null || isset($seen[$normalized['asset_id']])) {
                continue;
            }
            $seen[$normalized['asset_id']] = true;
            $nextTracks[] = $normalized;
        }

        if ($changed) {
            $document['tracks'] = $nextTracks;
            bandpromo_campaign_write_document(
                $root,
                bandpromo_campaign_normalize_document($document, $releaseId, $root)
            );
            $touchedReleases[] = $releaseId;
        }
    }

    return [
        'rebound' => $rebound,
        'dropped' => $dropped,
        'unresolved' => $unresolved,
        'remaps' => $remaps,
        'releases' => $touchedReleases,
    ];
}

/**
 * Copy description/lyrics/cover from unregistered leftover masters onto matching live
 * audio assets (common after re-upload left rich tags on the old ast_* files).
 *
 * Only fills empty live fields. Writes tags onto the live master and re-links pool covers.
 *
 * @return array{restored:int, covers:int, skipped:int, items: list<array<string,string>>}
 */
function bandpromo_asset_restore_audio_meta_from_unregistered_masters(string $root): array
{
    require_once __DIR__ . '/light-build-tasks.php';
    require_once __DIR__ . '/audio-master-detail-helpers.php';

    $identityIndex = bandpromo_campaign_live_audio_identity_index($root);
    $registry = bandpromo_asset_load_registry($root);
    $masterDir = $root . '/media/audio/master';
    $imgDir = $root . '/media/img/original';

    $restored = 0;
    $covers = 0;
    $skipped = 0;
    $items = [];
    $seenLive = [];

    if (!is_dir($masterDir)) {
        return [
            'restored' => 0,
            'covers' => 0,
            'skipped' => 0,
            'items' => [],
        ];
    }

    foreach (scandir($masterDir) ?: [] as $filename) {
        if ($filename === '.' || $filename === '..') {
            continue;
        }
        $filename = basename((string) $filename);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3', 'flac', 'wav'], true)) {
            continue;
        }

        $staleId = bandpromo_asset_id_from_master_filename($filename);
        if ($staleId === null || isset($registry['assets'][$staleId])) {
            continue;
        }

        $inspect = bandpromo_campaign_inspect_master_metadata($root, $filename);
        $title = trim((string) ($inspect['title'] ?? ''));
        $artist = trim((string) ($inspect['artist'] ?? ''));
        $comment = trim((string) ($inspect['comment'] ?? ''));
        $lyrics = (string) ($inspect['lyrics'] ?? '');
        $sidecar = basename(trim((string) ($inspect['sidecar_cover'] ?? '')));
        if ($sidecar === '' || !is_file($imgDir . '/' . $sidecar)) {
            $sidecar = '';
        }

        if ($comment === '' && trim($lyrics) === '' && $sidecar === '') {
            $skipped++;
            continue;
        }

        $liveId = bandpromo_campaign_resolve_replacement_audio_asset_id(
            $root,
            $staleId,
            $identityIndex,
            $title,
            $artist
        );
        if ($liveId === '' || $liveId === $staleId || isset($seenLive[$liveId])) {
            $skipped++;
            continue;
        }

        $liveAsset = bandpromo_asset_lookup_by_id($root, $liveId);
        if ($liveAsset === null || strtolower((string) ($liveAsset['kind'] ?? '')) !== 'audio') {
            $skipped++;
            continue;
        }

        $liveMaster = basename(trim((string) ($liveAsset['master_filename'] ?? '')));
        if ($liveMaster === '') {
            $skipped++;
            continue;
        }

        $display = bandpromo_asset_read_audio_display($liveAsset);
        $liveComment = trim((string) ($display['comment'] ?? ''));
        $liveLyrics = trim((string) ($display['lyrics'] ?? ''));
        $liveCover = basename(trim((string) ($display['cover'] ?? '')));

        $nextComment = $liveComment !== '' ? $liveComment : $comment;
        $nextLyrics = $liveLyrics !== '' ? $liveLyrics : $lyrics;
        $needCover = $liveCover === '' && $sidecar !== '';
        $needText = ($liveComment === '' && $comment !== '') || ($liveLyrics === '' && trim($lyrics) !== '');

        if (!$needText && !$needCover) {
            $skipped++;
            continue;
        }

        $seenLive[$liveId] = true;

        $fields = [
            'title' => trim((string) ($display['title'] ?? '')) !== ''
                ? trim((string) $display['title'])
                : $title,
            'artist' => trim((string) ($display['artist'] ?? '')) !== ''
                ? trim((string) $display['artist'])
                : $artist,
            'album' => trim((string) ($display['album'] ?? '')) !== ''
                ? trim((string) $display['album'])
                : trim((string) ($inspect['album'] ?? '')),
            'date' => trim((string) ($display['date'] ?? '')) !== ''
                ? trim((string) $display['date'])
                : trim((string) ($inspect['date'] ?? '')),
            'tracknumber' => trim((string) ($display['tracknumber'] ?? '')) !== ''
                ? trim((string) $display['tracknumber'])
                : trim((string) ($inspect['tracknumber'] ?? '')),
            'bpm' => trim((string) ($display['bpm'] ?? '')),
            'initialkey' => trim((string) ($display['initialkey'] ?? '')),
            'genre' => trim((string) ($display['genre'] ?? '')),
            'comment' => $nextComment,
            'lyrics' => $nextLyrics,
            'living_cover' => trim((string) ($display['living_cover'] ?? '')),
        ];

        if ($needText) {
            $tagResult = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
                'action' => 'update',
                'filename' => $liveMaster,
                'fields' => $fields,
            ]);
            $tagData = is_array($tagResult['data'] ?? null) ? $tagResult['data'] : null;
            if (!$tagResult['ok'] || !is_array($tagData) || empty($tagData['ok'])) {
                $skipped++;
                continue;
            }
        }

        $coverBasename = $liveCover;
        if ($needCover) {
            $coverResult = bandpromo_audio_master_apply_cover_selection(
                $root,
                $liveMaster,
                'media/img/original/' . $sidecar
            );
            if (!empty($coverResult['ok'])) {
                $coverBasename = basename(trim((string) ($coverResult['sidecar_cover'] ?? $sidecar)));
                $covers++;
            } else {
                // Still point the pool file even if embed sync fails.
                $coverBasename = $sidecar;
                $covers++;
            }
        }

        bandpromo_asset_update_entry($root, $liveId, [
            'display' => bandpromo_asset_build_audio_display_from_fields($fields, [
                'duration_seconds' => (int) ($display['duration'] ?? $inspect['duration_seconds'] ?? 0),
                'sidecar_cover' => $coverBasename,
                'living_cover' => $fields['living_cover'],
            ]),
        ]);

        $restored++;
        $items[] = [
            'from' => $filename,
            'to' => $liveMaster,
            'asset_id' => $liveId,
        ];
    }

    return [
        'restored' => $restored,
        'covers' => $covers,
        'skipped' => $skipped,
        'items' => $items,
    ];
}
