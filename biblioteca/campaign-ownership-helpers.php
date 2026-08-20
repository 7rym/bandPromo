<?php
declare(strict_types=1);

/**
 * Release campaign ownership: brand / playlist / gallery / page → release_id.
 * Dual-read friendly migrate for installs that still use free-floating brands.
 */
require_once __DIR__ . '/json-file-helpers.php';

/**
 * @return array{brands: int, playlists: int, galleries: int, pages: int}
 */
function bandpromo_campaign_ownership_migrate(string $root): array
{
    $result = ['brands' => 0, 'playlists' => 0, 'galleries' => 0, 'pages' => 0];

    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-registry.php';

    bandpromo_campaign_ensure_seeded($root);
    bandpromo_brand_ensure_seeded($root);
    bandpromo_playlist_ensure_seeded($root);
    bandpromo_gallery_ensure_seeded($root);

    $demoReleaseId = BANDPROMO_RELEASE_DEMO_ID;

    // Brands: set release_id from reverse release.brand_id, or demo defaults.
    try {
        $releasesByBrand = [];
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
            $brandId = trim((string) ($document['brand_id'] ?? ''));
            if ($brandId !== '') {
                $releasesByBrand[$brandId] = $releaseId;
            }
        }

        foreach (bandpromo_brand_registry_entries($root) as $brandMeta) {
            if (!is_array($brandMeta)) {
                continue;
            }
            $brandId = trim((string) ($brandMeta['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $brand = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $current = trim((string) ($brand['release_id'] ?? ''));
            $desired = $releasesByBrand[$brandId] ?? '';
            if ($desired === '' || $current === $desired) {
                continue;
            }
            $brand['release_id'] = $desired;
            bandpromo_brand_write_document($root, $brand, ['allow_locked' => true]);
            $result['brands']++;

            // Keep release.brand_id pointing at this identity when empty.
            try {
                $releaseDoc = bandpromo_campaign_load_document($root, $desired);
                if (trim((string) ($releaseDoc['brand_id'] ?? '')) === '') {
                    $releaseDoc['brand_id'] = $brandId;
                    bandpromo_campaign_write_document($root, $releaseDoc);
                }
            } catch (Throwable $throwable) {
                // Best-effort.
            }
        }
    } catch (Throwable $throwable) {
        // Best-effort migrate.
    }

    // Playlists: own under demo when untitled campaign, or infer from homogeneous track release_ids.
    try {
        foreach (bandpromo_playlist_registry_entries($root) as $playlistMeta) {
            if (!is_array($playlistMeta)) {
                continue;
            }
            $playlistId = bandpromo_playlist_normalize_id((string) ($playlistMeta['id'] ?? ''));
            if ($playlistId === '') {
                continue;
            }
            try {
                $document = bandpromo_playlist_load_document($root, $playlistId);
            } catch (Throwable $throwable) {
                continue;
            }
            if (trim((string) ($document['release_id'] ?? '')) !== '') {
                continue;
            }
            // Infer ownership from homogeneous track release_ids only — never force by playlist id.
            $desired = bandpromo_campaign_ownership_infer_from_playlist_entries($root, $document);
            if ($desired === '') {
                continue;
            }
            $document['release_id'] = $desired;
            bandpromo_playlist_write_document($root, $document);
            $result['playlists']++;
        }
    } catch (Throwable $throwable) {
        // Best-effort.
    }

    // Galleries / pages: ownership comes from PRP import + release associations.
    // Do not force demo release_id here — locked demo + localhost unlock is enough.

    // Ensure demo release has a brand link when empty (seed/import gap only).
    try {
        $demo = bandpromo_campaign_load_document($root, $demoReleaseId);
        $changed = false;
        if (trim((string) ($demo['brand_id'] ?? '')) === '') {
            $demo['brand_id'] = BANDPROMO_BRAND_DEFAULT_ID;
            $changed = true;
        }
        if ($changed) {
            bandpromo_campaign_write_document($root, $demo);
        }
    } catch (Throwable $throwable) {
        // Best-effort.
    }

    return $result;
}

function bandpromo_campaign_ownership_infer_from_playlist_entries(string $root, array $document): string
{
    $ids = [];
    $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = trim((string) ($entry['release_id'] ?? ''));
        if ($releaseId === '') {
            $master = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
            if ($master !== '') {
                $releaseId = bandpromo_campaign_id_for_master_filename($root, $master);
            }
        }
        if ($releaseId === '') {
            continue;
        }
        $ids[$releaseId] = true;
    }

    if (count($ids) === 1) {
        return array_key_first($ids) ?: '';
    }

    return '';
}

/**
 * List child containers owned by a release (for Release preview hub).
 *
 * @return array{
 *   playlists: list<array{id:string,title:string,publish_date:string}>,
 *   galleries: list<array{id:string,title:string}>,
 *   pages: list<array{id:string,title:string}>,
 *   brand_id: string,
 *   brand: array{id:string,title:string,mood:string,logo:string,background_image:string,tokens:array<string,string>}|null
 * }
 */
function bandpromo_campaign_ownership_children(string $root, string $releaseId): array
{
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-registry.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $out = [
        'playlists' => [],
        'galleries' => [],
        'pages' => [],
        'brand_id' => '',
        'brand' => null,
    ];
    if ($releaseId === '') {
        return $out;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $releaseId);
        $out['brand_id'] = trim((string) ($document['brand_id'] ?? ''));
    } catch (Throwable $throwable) {
        return $out;
    }

    try {
        foreach (bandpromo_playlist_registry_entries($root) as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = bandpromo_playlist_normalize_id((string) ($meta['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            try {
                $doc = bandpromo_playlist_load_document($root, $id);
            } catch (Throwable $throwable) {
                continue;
            }
            if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
                continue;
            }
            $out['playlists'][] = [
                'id' => $id,
                'title' => trim((string) ($doc['title'] ?? $meta['title'] ?? $id)) ?: $id,
                'publish_date' => trim((string) ($doc['publish_date'] ?? $meta['publish_date'] ?? '')),
            ];
        }
    } catch (Throwable $throwable) {
        // ignore
    }

    usort($out['playlists'], static function (array $left, array $right): int {
        $leftDate = (string) ($left['publish_date'] ?? '');
        $rightDate = (string) ($right['publish_date'] ?? '');
        if ($leftDate !== $rightDate) {
            return strcmp($rightDate, $leftDate);
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    try {
        foreach (bandpromo_gallery_registry_entries($root) as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = bandpromo_gallery_normalize_id((string) ($meta['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            try {
                $doc = bandpromo_gallery_load_document($root, $id);
            } catch (Throwable $throwable) {
                continue;
            }
            if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
                continue;
            }
            $out['galleries'][] = [
                'id' => $id,
                'title' => trim((string) ($doc['title'] ?? $meta['title'] ?? $id)) ?: $id,
            ];
        }
    } catch (Throwable $throwable) {
        // ignore
    }

    usort($out['galleries'], static function (array $left, array $right): int {
        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    foreach (bandpromo_page_registry_entries($root) as $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $pageId = trim((string) ($meta['id'] ?? ''));
        if ($pageId === '' || !bandpromo_page_runtime_present($root, $pageId)) {
            continue;
        }
        try {
            $doc = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
            continue;
        }
        $out['pages'][] = [
            'id' => $pageId,
            'title' => bandpromo_page_operator_title($meta, $doc),
        ];
    }

    usort($out['pages'], static function (array $left, array $right): int {
        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    if ($out['brand_id'] === '') {
        try {
            foreach (bandpromo_brand_registry_entries($root) as $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $id = trim((string) ($meta['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                try {
                    $brand = bandpromo_brand_load_document($root, $id);
                } catch (Throwable $throwable) {
                    continue;
                }
                if (trim((string) ($brand['release_id'] ?? '')) === $releaseId) {
                    $out['brand_id'] = $id;
                    break;
                }
            }
        } catch (Throwable $throwable) {
            // ignore
        }
    }

    $brandId = trim((string) ($out['brand_id'] ?? ''));
    if ($brandId !== '') {
        try {
            $brand = bandpromo_brand_load_document($root, $brandId);
            $tokens = is_array($brand['tokens'] ?? null) ? $brand['tokens'] : [];
            $colors = is_array($tokens['color'] ?? null) ? $tokens['color'] : [];
            $assets = is_array($brand['assets'] ?? null) ? $brand['assets'] : [];
            $out['brand'] = [
                'id' => $brandId,
                'title' => trim((string) ($brand['title'] ?? $brandId)) ?: $brandId,
                'mood' => trim((string) ($brand['mood'] ?? '')),
                'logo' => trim((string) ($assets['logo'] ?? '')),
                'background_image' => trim((string) ($assets['background_image'] ?? '')),
                'tokens' => [
                    'primary' => trim((string) ($colors['primary'] ?? '')),
                    'secondary' => trim((string) ($colors['secondary'] ?? '')),
                    'background' => trim((string) ($colors['background'] ?? '')),
                    'text' => trim((string) ($colors['text'] ?? '')),
                ],
            ];
        } catch (Throwable $throwable) {
            $out['brand'] = [
                'id' => $brandId,
                'title' => $brandId,
                'mood' => '',
                'logo' => '',
                'background_image' => '',
                'tokens' => [
                    'primary' => '',
                    'secondary' => '',
                    'background' => '',
                    'text' => '',
                ],
            ];
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function bandpromo_campaign_association_kinds(): array
{
    return ['playlists', 'galleries', 'pages'];
}

function bandpromo_campaign_association_normalize_kind(string $kind): string
{
    $kind = strtolower(trim($kind));
    if (!in_array($kind, bandpromo_campaign_association_kinds(), true)) {
        throw new InvalidArgumentException('Association kind must be playlists, galleries, or pages.');
    }

    return $kind;
}

function bandpromo_campaign_normalize_optional_id(string $releaseId): string
{
    $releaseId = trim($releaseId);
    if ($releaseId === '') {
        return '';
    }
    if (!preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        return '';
    }

    return $releaseId;
}

/**
 * @return array{id:string,title:string,publish_date:string,release_id:string,movable:bool}
 */
function bandpromo_campaign_association_item(
    string $id,
    string $title,
    string $releaseId,
    bool $movable,
    string $publishDate = ''
): array {
    return [
        'id' => $id,
        'title' => $title !== '' ? $title : $id,
        'publish_date' => $publishDate,
        'release_id' => $releaseId,
        'movable' => $movable,
    ];
}

/**
 * @return array{active:list<array{id:string,title:string,publish_date:string,release_id:string,movable:bool}>,available:list<array{id:string,title:string,publish_date:string,release_id:string,movable:bool}>}
 */
function bandpromo_campaign_association_pools(string $root, string $releaseId, string $kind): array
{
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/demo-catalog-state.php';

    $kind = bandpromo_campaign_association_normalize_kind($kind);
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $active = [];
    $available = [];

    if ($releaseId === '') {
        return ['active' => $active, 'available' => $available];
    }

    if ($kind === 'playlists') {
        foreach (bandpromo_playlist_registry_entries($root) as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = bandpromo_playlist_normalize_id((string) ($meta['id'] ?? ''));
            if ($id === '' || !bandpromo_demo_catalog_entity_is_visible($root, $id)) {
                continue;
            }
            try {
                $doc = bandpromo_playlist_load_document($root, $id);
            } catch (Throwable $throwable) {
                continue;
            }
            $owner = bandpromo_campaign_normalize_optional_id((string) ($doc['release_id'] ?? ''));
            $item = bandpromo_campaign_association_item(
                $id,
                trim((string) ($doc['title'] ?? $meta['title'] ?? $id)),
                $owner,
                !bandpromo_playlist_is_protected_id($id),
                trim((string) ($doc['publish_date'] ?? $meta['publish_date'] ?? ''))
            );
            if ($owner === $releaseId) {
                $active[] = $item;
            } elseif (
                $item['movable']
                && ($owner === '' || $owner === BANDPROMO_CAMPAIGN_DEFAULT_ID)
            ) {
                // Unassigned or stuck on invisible primary / Default release — offer for association.
                $available[] = $item;
            }
        }
        usort($active, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
        usort($available, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
    } elseif ($kind === 'galleries') {
        foreach (bandpromo_gallery_registry_entries($root) as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $id = bandpromo_gallery_normalize_id((string) ($meta['id'] ?? ''));
            if ($id === '' || !bandpromo_demo_catalog_entity_is_visible($root, $id)) {
                continue;
            }
            try {
                $doc = bandpromo_gallery_load_document($root, $id);
            } catch (Throwable $throwable) {
                continue;
            }
            $owner = bandpromo_campaign_normalize_optional_id((string) ($doc['release_id'] ?? ''));
            // Protected demo gallery stays undeletable; reassign only when unlocked on localhost.
            $galleryMovable = !bandpromo_gallery_is_protected_id($id);
            if (!$galleryMovable) {
                try {
                    $demoRelease = bandpromo_campaign_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
                    $galleryMovable = empty($demoRelease['locked'])
                        && bandpromo_campaign_may_change_lock(BANDPROMO_RELEASE_DEMO_ID);
                } catch (Throwable $throwable) {
                    $galleryMovable = false;
                }
            }
            $item = bandpromo_campaign_association_item(
                $id,
                trim((string) ($doc['title'] ?? $meta['title'] ?? $id)),
                $owner,
                $galleryMovable
            );
            if ($owner === $releaseId) {
                $active[] = $item;
            } elseif (
                $item['movable']
                && ($owner === '' || $owner === BANDPROMO_CAMPAIGN_DEFAULT_ID)
            ) {
                $available[] = $item;
            }
        }
        usort($active, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
        usort($available, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
    } else {
        require_once __DIR__ . '/page-registry.php';
        bandpromo_page_ensure_system_pages($root);
        foreach (bandpromo_page_registry_entries($root) as $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $pageId = trim((string) ($meta['id'] ?? ''));
            if ($pageId === '' || !bandpromo_page_runtime_present($root, $pageId)) {
                continue;
            }
            // FAQ / login-surface pages are install shell, not campaign associations.
            if ($pageId === BANDPROMO_PAGE_REQUIRED_ID
                || (string) ($meta['surface'] ?? '') === 'login'
                || !empty($meta['required'])
            ) {
                continue;
            }
            try {
                $doc = bandpromo_page_load_document($root, $pageId);
            } catch (Throwable $throwable) {
                continue;
            }
            $owner = bandpromo_campaign_normalize_optional_id((string) ($doc['release_id'] ?? ''));
            $item = bandpromo_campaign_association_item(
                $pageId,
                bandpromo_page_operator_title($meta, $doc),
                $owner,
                true
            );
            $item['sort_order'] = (int) ($meta['sort_order'] ?? 0);
            $item['show_in_player'] = !empty($meta['show_in_player']);
            if ($owner === $releaseId) {
                $active[] = $item;
            } elseif (
                $item['movable']
                && ($owner === '' || $owner === BANDPROMO_CAMPAIGN_DEFAULT_ID)
            ) {
                $available[] = $item;
            }
        }
        usort($active, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
            if ($order !== 0) {
                return $order;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
        usort($available, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
    }

    return [
        'active' => $active,
        'available' => $available,
    ];
}

/**
 * Replace association membership for one kind. Available pool is unassigned-only:
 * ids may only move onto this release when currently unassigned (or already owned by it).
 *
 * @param list<string> $activeIds
 * @return array{active:list<array>,available:list<array>,changed:int}
 */
function bandpromo_campaign_save_associations(string $root, string $releaseId, string $kind, array $activeIds): array
{
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';

    $kind = bandpromo_campaign_association_normalize_kind($kind);
    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '') {
        throw new InvalidArgumentException('Campaign id is required.');
    }

    $releaseMeta = bandpromo_campaign_registry_entry($root, $releaseId);
    if ($releaseMeta === null) {
        throw new InvalidArgumentException('Unknown campaign.');
    }
    try {
        $releaseDocument = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        throw new InvalidArgumentException('Unknown campaign.');
    }
    if (!empty($releaseDocument['locked'])) {
        throw new InvalidArgumentException('This campaign is locked. Unlock it before changing associations.');
    }

    $desired = [];
    foreach ($activeIds as $rawId) {
        if (!is_string($rawId)) {
            continue;
        }
        $id = trim($rawId);
        if ($id === '' || isset($desired[$id])) {
            continue;
        }
        $desired[$id] = true;
    }

    $pools = bandpromo_campaign_association_pools($root, $releaseId, $kind);
    $currentActive = [];
    foreach ($pools['active'] as $item) {
        $currentActive[(string) ($item['id'] ?? '')] = $item;
    }
    $currentAvailable = [];
    foreach ($pools['available'] as $item) {
        $currentAvailable[(string) ($item['id'] ?? '')] = $item;
    }

    $changed = 0;

    foreach ($desired as $id => $_true) {
        if (isset($currentActive[$id])) {
            continue;
        }
        if (!isset($currentAvailable[$id])) {
            throw new InvalidArgumentException(
                'Cannot associate "' . $id . '": it is missing, locked, or already owned by another campaign.'
            );
        }
        if (empty($currentAvailable[$id]['movable'])) {
            throw new InvalidArgumentException('Cannot move protected container "' . $id . '".');
        }
        if ($kind === 'playlists') {
            bandpromo_playlist_set_campaign_id($root, $id, $releaseId);
        } elseif ($kind === 'galleries') {
            bandpromo_gallery_set_release_id($root, $id, $releaseId);
        } else {
            bandpromo_page_set_release_id($root, $id, $releaseId);
        }
        $changed++;
    }

    foreach ($currentActive as $id => $item) {
        if (isset($desired[$id])) {
            continue;
        }
        if (empty($item['movable'])) {
            throw new InvalidArgumentException('Cannot remove protected container "' . $id . '" from this campaign.');
        }
        if ($kind === 'playlists') {
            bandpromo_playlist_set_campaign_id($root, $id, '');
        } elseif ($kind === 'galleries') {
            bandpromo_gallery_set_release_id($root, $id, '');
        } else {
            bandpromo_page_set_release_id($root, $id, '');
            require_once __DIR__ . '/page-registry.php';
            bandpromo_page_update_registry_entry($root, $id, [
                'show_in_player' => false,
            ]);
        }
        $changed++;
    }

    if ($kind === 'pages') {
        require_once __DIR__ . '/page-registry.php';
        $order = 10;
        foreach (array_keys($desired) as $pageId) {
            bandpromo_page_update_registry_entry($root, $pageId, [
                'show_in_player' => true,
                'sort_order' => $order,
            ]);
            $order += 10;
        }
    }

    $fresh = bandpromo_campaign_association_pools($root, $releaseId, $kind);

    return [
        'active' => $fresh['active'],
        'available' => $fresh['available'],
        'changed' => $changed,
    ];
}
