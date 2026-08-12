<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/gallery-helpers.php';
require_once __DIR__ . '/media-reference-helpers.php';
require_once __DIR__ . '/demo-catalog-state.php';

const BANDPROMO_GALLERY_REGISTRY_VERSION = 1;
const BANDPROMO_GALLERY_DEMO_ID = 'bandpromo-demo';
const BANDPROMO_GALLERY_LEGACY_MAIN_ID = 'main';

function bandpromo_gallery_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'galleries';
}

function bandpromo_gallery_registry_path(string $root): string
{
    return bandpromo_gallery_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_gallery_document_path(string $root, string $galleryId): string
{
    return bandpromo_gallery_storage_root($root) . DIRECTORY_SEPARATOR . bandpromo_gallery_normalize_id($galleryId) . '.json';
}

function bandpromo_gallery_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_gallery_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/galleries directory.');
    }
}

function bandpromo_gallery_normalize_id(string $galleryId): string
{
    $galleryId = strtolower(trim($galleryId));
    $galleryId = preg_replace('/[^a-z0-9-]+/', '-', $galleryId) ?? '';
    $galleryId = trim($galleryId, '-');

    return substr($galleryId, 0, 48);
}

function bandpromo_gallery_normalize_entry(array $entry): ?array
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if ($assetId !== '' && !bandpromo_asset_is_asset_id($assetId)) {
        $assetId = '';
    }

    $src = bandpromo_gallery_normalize_src_path((string) ($entry['src'] ?? ''));
    if ($src === '' && $assetId === '') {
        return null;
    }

    $type = strtolower(trim((string) ($entry['type'] ?? 'image')));
    if (!in_array($type, ['image', 'video'], true)) {
        $type = 'image';
    }

    $normalized = [
        'src' => $src !== '' ? $src : $assetId,
        'type' => $type,
        'asset_id' => $assetId,
        'name' => trim((string) ($entry['name'] ?? '')),
        'alt' => trim((string) ($entry['alt'] ?? '')),
    ];

    if ($type === 'video') {
        $poster = trim((string) ($entry['poster'] ?? ''));
        if ($poster !== '') {
            $normalized['poster'] = $poster;
        }
    }

    return $normalized;
}

function bandpromo_gallery_normalize_document(array $input, ?string $expectedId = null): array
{
    $id = bandpromo_gallery_normalize_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid gallery id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $kind = strtolower(trim((string) ($input['kind'] ?? 'system')));
    if (!in_array($kind, ['system', 'user'], true)) {
        $kind = 'system';
    }

    $entries = [];
    if (isset($input['entries']) && is_array($input['entries'])) {
        foreach ($input['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = bandpromo_gallery_normalize_entry($entry);
            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }
    }

    $releaseId = trim((string) ($input['release_id'] ?? ''));
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        $releaseId = '';
    }

    return [
        'version' => BANDPROMO_GALLERY_REGISTRY_VERSION,
        'id' => $id,
        'title' => $title,
        'kind' => $kind,
        'release_id' => $releaseId,
        'entries' => $entries,
    ];
}

function bandpromo_gallery_default_registry(): array
{
    return [
        'version' => BANDPROMO_GALLERY_REGISTRY_VERSION,
        'galleries' => [
            [
                'id' => BANDPROMO_GALLERY_DEMO_ID,
                'title' => 'bandPromo demo',
                'kind' => 'system',
                'sort_order' => 10,
            ],
        ],
    ];
}

function bandpromo_gallery_default_document(): array
{
    return [
        'version' => BANDPROMO_GALLERY_REGISTRY_VERSION,
        'id' => BANDPROMO_GALLERY_DEMO_ID,
        'title' => 'bandPromo demo',
        'kind' => 'system',
        'release_id' => '',
        'entries' => [],
    ];
}

function bandpromo_gallery_write_registry(string $root, array $registry): void
{
    bandpromo_gallery_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_gallery_registry_path($root), $registry)) {
        throw new RuntimeException('Could not write data/galleries/registry.json');
    }
}

function bandpromo_gallery_load_registry(string $root): array
{
    bandpromo_gallery_registry_ensure_dir($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_gallery_registry_path($root));
    if ($decoded === null) {
        $default = bandpromo_gallery_default_registry();
        bandpromo_gallery_write_registry($root, $default);

        return $default;
    }

    if (!isset($decoded['galleries']) || !is_array($decoded['galleries'])) {
        $decoded['galleries'] = [];
    }

    return $decoded;
}

function bandpromo_gallery_write_document(string $root, array $document): void
{
    $document = bandpromo_gallery_normalize_document($document, (string) ($document['id'] ?? ''));
    bandpromo_gallery_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_gallery_document_path($root, $document['id']), $document)) {
        throw new RuntimeException('Could not write gallery document.');
    }
}

function bandpromo_gallery_load_document(string $root, string $galleryId): array
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    if ($galleryId === '') {
        throw new InvalidArgumentException('Invalid gallery id.');
    }

    $path = bandpromo_gallery_document_path($root, $galleryId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing gallery document: data/galleries/' . $galleryId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid gallery document: data/galleries/' . $galleryId . '.json');
    }

    return bandpromo_gallery_normalize_document($decoded, $galleryId);
}

function bandpromo_gallery_registry_entries(string $root): array
{
    return bandpromo_gallery_load_registry($root)['galleries'] ?? [];
}

function bandpromo_gallery_visible_in_admin_catalog(string $root, array $entry): bool
{
    $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
    if ($galleryId === '') {
        return false;
    }

    $owner = '';
    try {
        $document = bandpromo_gallery_load_document($root, $galleryId);
        $owner = (string) ($document['release_id'] ?? '');
    } catch (Throwable $throwable) {
        $owner = '';
    }

    return bandpromo_demo_release_container_is_visible($root, $owner, $galleryId);
}

function bandpromo_gallery_admin_registry_entries(string $root): array
{
    $entries = [];
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!bandpromo_gallery_visible_in_admin_catalog($root, $entry)) {
            continue;
        }
        $entries[] = $entry;
    }

    return $entries;
}

function bandpromo_gallery_default_admin_content_id(string $root): string
{
    foreach (bandpromo_gallery_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '' || bandpromo_demo_catalog_is_demo_entity_id($galleryId)) {
            continue;
        }
        if (!bandpromo_gallery_document_is_empty($root, $galleryId)) {
            return $galleryId;
        }
    }

    foreach (bandpromo_gallery_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId !== '' && !bandpromo_demo_catalog_is_demo_entity_id($galleryId)) {
            return $galleryId;
        }
    }

    if (bandpromo_demo_catalog_is_visible($root)) {
        return BANDPROMO_GALLERY_DEMO_ID;
    }

    return '';
}

function bandpromo_gallery_system_entries(string $root): array
{
    return array_values(array_filter(
        bandpromo_gallery_registry_entries($root),
        static fn(array $entry): bool => ($entry['kind'] ?? 'system') === 'system'
    ));
}

function bandpromo_gallery_registry_entry(string $root, string $galleryId): ?array
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $galleryId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_gallery_load_legacy_items(string $root): array
{
    $decoded = bandpromo_decode_gallery_items($root);
    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $normalized = bandpromo_gallery_normalize_entry($item);
        if ($normalized !== null) {
            $entries[] = $normalized;
        }
    }

    return $entries;
}

function bandpromo_gallery_materialize_items(string $root, string $galleryId): array
{
    $document = bandpromo_gallery_load_document($root, $galleryId);
    $items = [];
    foreach ($document['entries'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $item = [
            'src' => (string) ($entry['src'] ?? ''),
            'type' => (string) ($entry['type'] ?? 'image'),
            'name' => (string) ($entry['name'] ?? ''),
            'alt' => (string) ($entry['alt'] ?? ''),
            'asset_id' => trim((string) ($entry['asset_id'] ?? '')),
        ];
        if (($entry['type'] ?? '') === 'video' && !empty($entry['poster'])) {
            $item['poster'] = (string) $entry['poster'];
        }
        $items[] = $item;
    }

    return bandpromo_gallery_normalize_items($root, $items);
}

function bandpromo_gallery_save_items(string $root, string $galleryId, array $items): array
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    if ($galleryId === '') {
        $galleryId = BANDPROMO_GALLERY_DEMO_ID;
    }

    $entries = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $normalized = bandpromo_gallery_normalize_entry($item);
        if ($normalized !== null) {
            $entries[] = $normalized;
        }
    }

    $document = bandpromo_gallery_load_document($root, $galleryId);
    $document['entries'] = $entries;
    bandpromo_gallery_write_document($root, $document);

    $materialized = bandpromo_gallery_materialize_items($root, $galleryId);

    return [
        'items' => $materialized,
        'count' => count($materialized),
    ];
}

function bandpromo_gallery_resolve_id(string $requestedId = ''): string
{
    $requestedId = bandpromo_gallery_normalize_id($requestedId);
    if ($requestedId === BANDPROMO_GALLERY_LEGACY_MAIN_ID) {
        return BANDPROMO_GALLERY_DEMO_ID;
    }

    return $requestedId !== '' ? $requestedId : BANDPROMO_GALLERY_DEMO_ID;
}

function bandpromo_gallery_document_is_empty(string $root, string $galleryId): bool
{
    try {
        $document = bandpromo_gallery_load_document($root, $galleryId);
    } catch (Throwable $throwable) {
        return true;
    }

    foreach ($document['entries'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (trim((string) ($entry['src'] ?? '')) !== '') {
            return false;
        }
    }

    return true;
}

function bandpromo_gallery_remove_legacy_main_gallery(string $root): void
{
    $legacyId = BANDPROMO_GALLERY_LEGACY_MAIN_ID;
    $registryPath = bandpromo_gallery_registry_path($root);
    if (!is_file($registryPath)) {
        return;
    }

    $decoded = bandpromo_json_read_array_file($registryPath);
    if ($decoded === null) {
        return;
    }

    if (!isset($decoded['galleries']) || !is_array($decoded['galleries'])) {
        $decoded['galleries'] = [];
    }

    $hadLegacyRegistry = false;
    foreach ($decoded['galleries'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['id'] ?? '') === $legacyId) {
            $hadLegacyRegistry = true;
            break;
        }
    }

    $legacyPath = bandpromo_gallery_document_path($root, $legacyId);
    $legacyExists = is_file($legacyPath);
    if (!$hadLegacyRegistry && !$legacyExists) {
        return;
    }

    if ($legacyExists) {
        $legacyDoc = bandpromo_json_read_array_file($legacyPath);
        $legacyEntries = is_array($legacyDoc) && is_array($legacyDoc['entries'] ?? null)
            ? $legacyDoc['entries']
            : [];
        if ($legacyEntries !== [] && bandpromo_gallery_document_is_empty($root, BANDPROMO_GALLERY_DEMO_ID)) {
            try {
                $document = bandpromo_gallery_load_document($root, BANDPROMO_GALLERY_DEMO_ID);
            } catch (Throwable $throwable) {
                $document = bandpromo_gallery_default_document();
            }
            $document['entries'] = $legacyEntries;
            bandpromo_gallery_write_document($root, $document);
        }
    }

    if ($hadLegacyRegistry) {
        $decoded['galleries'] = array_values(array_filter(
            $decoded['galleries'],
            static fn($entry): bool => is_array($entry) && (string) ($entry['id'] ?? '') !== $legacyId
        ));
        bandpromo_json_write_file($registryPath, $decoded);
    }

    if ($legacyExists) {
        @unlink($legacyPath);
    }
}

function bandpromo_gallery_demo_template_path(string $root): string
{
    return $root . '/biblioteca/templates/bandpromo-demo.gallery.template.json';
}

/**
 * Seed or heal the demo gallery from the tracked template when empty or missing
 * the rollercoaster video entry. Resolves asset_ids from the registry when known.
 */
/**
 * @deprecated Demo gallery content comes from PRP import — no parallel heal/seed path.
 */
function bandpromo_gallery_heal_demo_entries(string $root): void
{
    // Intentionally empty. Kept as a no-op so older call sites do not fatally error.
}

function bandpromo_gallery_ensure_demo_gallery(string $root): void
{
    // Demo gallery arrives via PRP import only — do not seed empty docs here.
    unset($root);
}

function bandpromo_gallery_migrate_from_legacy(string $root): void
{
    $mainPath = bandpromo_gallery_document_path($root, BANDPROMO_GALLERY_DEMO_ID);
    if (is_file($mainPath)) {
        return;
    }

    $entries = bandpromo_gallery_load_legacy_items($root);
    $document = bandpromo_gallery_default_document();
    $document['entries'] = $entries;
    bandpromo_gallery_write_document($root, $document);

    $registry = bandpromo_gallery_load_registry($root);
    $hasMain = false;
    foreach ($registry['galleries'] as $entry) {
        if (($entry['id'] ?? '') === BANDPROMO_GALLERY_DEMO_ID) {
            $hasMain = true;
            break;
        }
    }
    if (!$hasMain) {
        $registry['galleries'][] = [
            'id' => BANDPROMO_GALLERY_DEMO_ID,
            'title' => 'bandPromo demo',
            'kind' => 'system',
            'sort_order' => 10,
        ];
        bandpromo_gallery_write_registry($root, $registry);
    }
}

function bandpromo_gallery_ensure_seeded(string $root): void
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
        bandpromo_gallery_registry_ensure_dir($root);
        if (!is_file(bandpromo_gallery_registry_path($root))) {
            bandpromo_gallery_write_registry($root, bandpromo_gallery_default_registry());
        }

        bandpromo_gallery_remove_legacy_main_gallery($root);
        bandpromo_gallery_ensure_demo_gallery($root);

        if (!is_file(bandpromo_gallery_document_path($root, BANDPROMO_GALLERY_DEMO_ID))) {
            bandpromo_gallery_migrate_from_legacy($root);
        }
        $completed[$root] = true;
    } finally {
        unset($running[$root]);
    }
}

function bandpromo_gallery_detach_media(string $root, string $target, string $filename): int
{
    bandpromo_gallery_ensure_seeded($root);
    $removed = 0;

    foreach (bandpromo_gallery_registry_entries($root) as $registryEntry) {
        $galleryId = (string) ($registryEntry['id'] ?? '');
        if ($galleryId === '') {
            continue;
        }

        try {
            $document = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }

        $nextEntries = [];
        $changed = false;
        foreach ($document['entries'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $materialized = [
                'src' => (string) ($entry['src'] ?? ''),
                'type' => (string) ($entry['type'] ?? 'image'),
                'asset_id' => trim((string) ($entry['asset_id'] ?? '')),
            ];
            if (bandpromo_media_reference_gallery_matches_target($root, $target, $filename, $materialized)) {
                $removed++;
                $changed = true;
                continue;
            }
            $nextEntries[] = $entry;
        }

        if (!$changed) {
            continue;
        }

        $document['entries'] = $nextEntries;
        bandpromo_gallery_write_document($root, $document);
    }

    return $removed;
}

function bandpromo_gallery_optimal_image_src(string $root, string $src): string
{
    return bandpromo_gallery_resolve_image_src($root, $src);
}

function bandpromo_gallery_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'gallery';
    }

    return substr($slug, 0, 48);
}

function bandpromo_gallery_is_protected_id(string $galleryId): bool
{
    return bandpromo_gallery_normalize_id($galleryId) === BANDPROMO_GALLERY_DEMO_ID;
}

function bandpromo_gallery_create(string $root, string $title, string $preferredId = ''): array
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Gallery name is required.');
    }

    $registry = bandpromo_gallery_load_registry($root);
    $baseId = $preferredId !== ''
        ? bandpromo_gallery_normalize_id($preferredId)
        : bandpromo_gallery_slug_from_title($title);
    if ($baseId === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        throw new InvalidArgumentException('Gallery id is invalid. Use lowercase letters, numbers, and hyphens.');
    }

    $id = $baseId;
    $suffix = 2;
    $existing = [];
    foreach ($registry['galleries'] as $entry) {
        $existing[(string) ($entry['id'] ?? '')] = true;
    }
    while (isset($existing[$id])) {
        $id = substr($baseId, 0, 44) . '-' . $suffix;
        $suffix++;
    }

    $maxOrder = 0;
    foreach ($registry['galleries'] as $entry) {
        $maxOrder = max($maxOrder, (int) ($entry['sort_order'] ?? 0));
    }

    $registry['galleries'][] = [
        'id' => $id,
        'title' => $title,
        'kind' => 'user',
        'sort_order' => $maxOrder + 10,
    ];
    bandpromo_gallery_write_registry($root, $registry);

    $document = bandpromo_gallery_default_document();
    $document['id'] = $id;
    $document['title'] = $title;
    $document['kind'] = 'user';
    $document['entries'] = [];
    bandpromo_gallery_write_document($root, $document);

    return bandpromo_gallery_registry_entry($root, $id) ?? [];
}

function bandpromo_gallery_set_release_id(string $root, string $galleryId, string $releaseId): void
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    if ($galleryId === '') {
        throw new InvalidArgumentException('Gallery id is required.');
    }
    if (bandpromo_gallery_is_protected_id($galleryId)) {
        require_once __DIR__ . '/release-storage.php';
        // Protected demo gallery: reassignment only when the platform demo is unlocked
        // (localhost may unlock for PRP source edits).
        try {
            $demoRelease = bandpromo_release_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
            if (!empty($demoRelease['locked'])) {
                throw new InvalidArgumentException('The bandPromo demo gallery cannot be reassigned while the demo release is locked.');
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('The bandPromo demo gallery cannot be reassigned.');
        }
        if (!bandpromo_release_may_change_lock(BANDPROMO_RELEASE_DEMO_ID)) {
            throw new InvalidArgumentException('The bandPromo demo gallery can only be reassigned on localhost.');
        }
    }

    $releaseId = trim($releaseId);
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        throw new InvalidArgumentException('Invalid release id.');
    }

    $document = bandpromo_gallery_load_document($root, $galleryId);
    $document['release_id'] = $releaseId;
    bandpromo_gallery_write_document($root, $document);
}

function bandpromo_gallery_update_details(string $root, string $galleryId, string $title): array
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    if ($galleryId === '') {
        throw new InvalidArgumentException('Gallery id is required.');
    }

    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Gallery name is required.');
    }

    $registry = bandpromo_gallery_load_registry($root);
    $found = false;
    foreach ($registry['galleries'] as $index => $entry) {
        if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $galleryId) {
            continue;
        }
        $registry['galleries'][$index]['title'] = $title;
        $found = true;
        break;
    }
    if (!$found) {
        throw new InvalidArgumentException('Unknown gallery.');
    }
    bandpromo_gallery_write_registry($root, $registry);

    $document = bandpromo_gallery_load_document($root, $galleryId);
    $document['title'] = $title;
    bandpromo_gallery_write_document($root, $document);

    return bandpromo_gallery_registry_entry($root, $galleryId) ?? [];
}

function bandpromo_gallery_delete(string $root, string $galleryId): void
{
    $galleryId = bandpromo_gallery_normalize_id($galleryId);
    if (bandpromo_gallery_is_protected_id($galleryId)) {
        throw new InvalidArgumentException('The bandPromo demo gallery cannot be deleted.');
    }

    $registry = bandpromo_gallery_load_registry($root);
    $before = count($registry['galleries']);
    $registry['galleries'] = array_values(array_filter(
        $registry['galleries'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $galleryId
    ));
    if (count($registry['galleries']) === $before) {
        throw new InvalidArgumentException('Unknown gallery.');
    }

    bandpromo_gallery_write_registry($root, $registry);

    $path = bandpromo_gallery_document_path($root, $galleryId);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not delete gallery document.');
    }
}
