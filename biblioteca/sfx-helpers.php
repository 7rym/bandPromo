<?php
declare(strict_types=1);

/**
 * Sound effects pool — brand UI audio (welcome, login, future interaction clips).
 * Not catalog music: no release membership, FLAC master packaging, or playlist coupling.
 */
require_once __DIR__ . '/json-file-helpers.php';

function bandpromo_sfx_original_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'sfx' . DIRECTORY_SEPARATOR . 'original';
}

function bandpromo_sfx_ensure_dir(string $root): void
{
    $dir = bandpromo_sfx_original_dir($root);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create media/sfx/original.');
    }
}

function bandpromo_sfx_web_path(string $filename): string
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return '';
    }

    return '/media/sfx/original/' . $filename;
}

function bandpromo_sfx_is_audio_filename(string $filename): bool
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($ext, ['flac', 'mp3', 'wav', 'ogg', 'm4a'], true);
}

function bandpromo_sfx_rewrite_special_audio_path(string $webPath): string
{
    $webPath = trim(str_replace('\\', '/', $webPath));
    if ($webPath === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $webPath) === 1) {
        return $webPath;
    }
    if ($webPath[0] !== '/') {
        $webPath = '/' . $webPath;
    }

    if (preg_match('#^/media/special/([^/]+)$#i', $webPath, $matches) !== 1) {
        return $webPath;
    }

    $filename = basename((string) ($matches[1] ?? ''));
    if ($filename === '' || !bandpromo_sfx_is_audio_filename($filename)) {
        return $webPath;
    }

    return bandpromo_sfx_web_path($filename);
}

/**
 * Register or refresh a Sound effects asset (kind=sfx, role=sfx).
 *
 * @param array{brand_id?: string} $options
 * @return array<string, mixed>
 */
function bandpromo_asset_register_sfx(
    string $root,
    string $originalFilename,
    array $options = []
): array {
    require_once __DIR__ . '/asset-registry.php';

    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '' || !bandpromo_sfx_is_audio_filename($originalFilename)) {
        throw new InvalidArgumentException('Sound effects must be an audio file.');
    }

    bandpromo_sfx_ensure_dir($root);
    $absolute = bandpromo_sfx_original_dir($root) . DIRECTORY_SEPARATOR . $originalFilename;
    if (!is_file($absolute)) {
        throw new RuntimeException('Sound effect file not found: ' . $originalFilename);
    }

    $existing = bandpromo_asset_lookup_by_original_filename($root, $originalFilename);
    if (is_array($existing) && ($existing['kind'] ?? '') === 'sfx') {
        $changes = [];
        if (array_key_exists('brand_id', $options)) {
            $changes['brand_id'] = trim((string) $options['brand_id']);
        }
        if ($changes !== []) {
            return bandpromo_asset_update_entry($root, (string) $existing['id'], $changes);
        }

        return $existing;
    }

    $brandId = trim((string) ($options['brand_id'] ?? ''));
    if ($brandId === '') {
        $brandId = bandpromo_asset_active_brand_id($root);
    }

    $id = bandpromo_generate_asset_id();
    $now = gmdate('c');
    $entry = [
        'id' => $id,
        'kind' => 'sfx',
        'media_type' => 'audio',
        'intake_bucket' => 'sfx',
        'brand_id' => $brandId,
        'role' => 'sfx',
        'original_filename' => $originalFilename,
        'master_filename' => $originalFilename,
        'master_format' => strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION)),
        'release_id' => '',
        'slug' => '',
        'display' => [],
        'tags' => ['sfx'],
        'delivery' => [
            'ready' => true,
            'source' => 'original',
        ],
        'created_at' => $now,
    ];

    $normalized = bandpromo_asset_normalize_entry($entry);
    if ($normalized === null) {
        throw new RuntimeException('Could not normalize sound effect asset.');
    }

    $registry = bandpromo_asset_load_registry($root);
    $registry['assets'][$id] = $normalized;
    $registry['by_original_filename'][$originalFilename] = $id;
    $registry['by_master_filename'][$originalFilename] = $id;
    bandpromo_asset_write_registry($root, $registry);

    return $normalized;
}

/**
 * Copy special-folder shell audio into Sound effects and rewrite brand/config refs.
 *
 * @return array{copied: int, rewritten: int}
 */
function bandpromo_sfx_migrate_from_special(string $root): array
{
    require_once __DIR__ . '/media-library-state.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/theme-storage.php';

    $result = ['copied' => 0, 'rewritten' => 0];
    bandpromo_sfx_ensure_dir($root);

    $specialDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'special';
    $sfxDir = bandpromo_sfx_original_dir($root);

    if (is_dir($specialDir)) {
        foreach (scandir($specialDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            if (!bandpromo_sfx_is_audio_filename($entry)) {
                continue;
            }
            $source = $specialDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($source)) {
                continue;
            }
            $dest = $sfxDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($dest)) {
                if (!@copy($source, $dest)) {
                    continue;
                }
                $result['copied']++;
            }
            bandpromo_media_files_index_sync_file($root, 'sfx', $entry);
            try {
                bandpromo_asset_register_sfx($root, $entry);
            } catch (Throwable $throwable) {
                // Registry optional for migrate success.
            }
        }
    }

    try {
        $registry = bandpromo_theme_load_registry($root);
        foreach ($registry['brands'] ?? [] as $brandMeta) {
            if (!is_array($brandMeta)) {
                continue;
            }
            $brandId = trim((string) ($brandMeta['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $document = bandpromo_theme_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
            $changed = false;
            foreach (['welcome_audio', 'loggedin_audio'] as $key) {
                $current = trim((string) ($assets[$key] ?? ''));
                if ($current === '') {
                    continue;
                }
                $next = bandpromo_sfx_rewrite_special_audio_path($current);
                if ($next !== $current) {
                    // Only rewrite when the SFX file exists (or was just copied).
                    $absolute = bandpromo_theme_resolve_media_absolute_path($root, $next);
                    if ($absolute === null) {
                        continue;
                    }
                    $assets[$key] = $next;
                    $changed = true;
                    $result['rewritten']++;
                }
            }
            if ($changed) {
                $document['assets'] = $assets;
                bandpromo_theme_write_document($root, $document);
            }
        }
    } catch (Throwable $throwable) {
        // Brand rewrite best-effort.
    }

    $configPath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'web-config.json';
    if (is_file($configPath)) {
        $config = bandpromo_json_read_array_file($configPath);
        if (is_array($config)) {
            $paths = [
                ['install', 'theme', 'welcome_audio'],
                ['install', 'theme', 'loggedin_audio'],
                ['media', 'welcome_audio'],
                ['media', 'loggedin_audio'],
            ];
            $changed = false;
            foreach ($paths as $segments) {
                $cursor = &$config;
                $ok = true;
                foreach ($segments as $i => $segment) {
                    if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                        $ok = false;
                        break;
                    }
                    if ($i === count($segments) - 1) {
                        $current = trim((string) $cursor[$segment]);
                        if ($current === '') {
                            break;
                        }
                        $next = bandpromo_sfx_rewrite_special_audio_path($current);
                        if ($next !== $current) {
                            $absolute = rtrim($root, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $next);
                            if (!is_file($absolute)) {
                                break;
                            }
                            $cursor[$segment] = $next;
                            $changed = true;
                            $result['rewritten']++;
                        }
                        break;
                    }
                    $cursor = &$cursor[$segment];
                }
                unset($cursor);
                if (!$ok) {
                    continue;
                }
            }
            if ($changed) {
                bandpromo_json_write_file($configPath, $config);
            }
        }
    }

    return $result;
}
