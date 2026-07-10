<?php
declare(strict_types=1);

function bandpromo_demo_catalog_preferences_path(string $root): string
{
    return rtrim($root, '/\\') . '/data/install-preferences.json';
}

function bandpromo_demo_catalog_default_preferences(): array
{
    return [
        'demo_catalog_visible' => true,
    ];
}

function bandpromo_demo_catalog_load_preferences(string $root): array
{
    $path = bandpromo_demo_catalog_preferences_path($root);
    if (!is_file($path)) {
        return bandpromo_demo_catalog_default_preferences();
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return bandpromo_demo_catalog_default_preferences();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return bandpromo_demo_catalog_default_preferences();
    }

    $prefs = bandpromo_demo_catalog_default_preferences();
    if (array_key_exists('demo_catalog_visible', $decoded)) {
        $prefs['demo_catalog_visible'] = (bool) $decoded['demo_catalog_visible'];
    }

    return $prefs;
}

function bandpromo_demo_catalog_save_preferences(string $root, array $preferences): bool
{
    $path = bandpromo_demo_catalog_preferences_path($root);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $payload = array_merge(bandpromo_demo_catalog_default_preferences(), $preferences, [
        'updated_at_utc' => gmdate('c'),
    ]);

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function bandpromo_demo_catalog_is_visible(string $root): bool
{
    $preferences = bandpromo_demo_catalog_load_preferences($root);

    return !empty($preferences['demo_catalog_visible']);
}

function bandpromo_demo_catalog_set_visible(string $root, bool $visible): bool
{
    return bandpromo_demo_catalog_save_preferences($root, [
        'demo_catalog_visible' => $visible,
    ]);
}

function bandpromo_demo_catalog_is_demo_entity_id(string $entityId): bool
{
    return strtolower(trim($entityId)) === 'bandpromo-demo';
}

function bandpromo_demo_catalog_entity_is_visible(string $root, string $entityId): bool
{
    if (!bandpromo_demo_catalog_is_demo_entity_id($entityId)) {
        return true;
    }

    return bandpromo_demo_catalog_is_visible($root);
}

function bandpromo_demo_catalog_install_has_operator_content(string $root): bool
{
    require_once __DIR__ . '/media-library-state.php';

    if (bandpromo_media_has_visible_user_uploads('audio')
        || bandpromo_media_has_visible_user_uploads('illustrations')
        || bandpromo_media_has_visible_user_uploads('photos')
        || bandpromo_media_has_visible_user_uploads('special')) {
        return true;
    }

    require_once __DIR__ . '/release-storage.php';
    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '' || bandpromo_demo_catalog_is_demo_entity_id($releaseId)) {
            continue;
        }
        if ($releaseId === BANDPROMO_RELEASE_DEFAULT_ID) {
            continue;
        }
        if ((int) ($entry['track_count'] ?? 0) > 0) {
            return true;
        }
    }

    require_once __DIR__ . '/playlist-storage.php';
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '' || bandpromo_demo_catalog_is_demo_entity_id($playlistId)) {
            continue;
        }
        if ((int) ($entry['track_count'] ?? 0) > 0) {
            return true;
        }
    }

    require_once __DIR__ . '/gallery-storage.php';
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '' || bandpromo_demo_catalog_is_demo_entity_id($galleryId)) {
            continue;
        }
        if (($entry['kind'] ?? 'system') !== 'user') {
            continue;
        }
        if (!bandpromo_gallery_document_is_empty($root, $galleryId)) {
            return true;
        }
    }

    return false;
}

function bandpromo_demo_catalog_should_suggest_hide(string $root): bool
{
    return bandpromo_demo_catalog_is_visible($root)
        && bandpromo_demo_catalog_install_has_operator_content($root);
}
