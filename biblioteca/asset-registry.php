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
        'by_original_filename' => [],
    ];
}

/** @return list<string> */
function bandpromo_asset_visual_intake_buckets(): array
{
    return ['img', 'photo', 'video', 'special'];
}

/** @return list<string> */
function bandpromo_asset_visual_roles(): array
{
    return [
        'unassigned',
        'brand-logo',
        'brand-portrait',
        'style-ref',
        'release-cover',
        'track-cover',
        'gallery',
        'page-illustration',
        'shell-background-image',
        'shell-background-video',
        'typography-sample',
    ];
}

function bandpromo_asset_normalize_visual_role(string $role): string
{
    $role = strtolower(trim($role));
    if ($role === '' || !in_array($role, bandpromo_asset_visual_roles(), true)) {
        return 'unassigned';
    }

    return $role;
}

function bandpromo_asset_normalize_intake_bucket(string $bucket): string
{
    $bucket = strtolower(trim($bucket));
    $aliases = [
        'illustrations' => 'img',
        'images' => 'img',
        'img' => 'img',
        'photos' => 'photo',
        'photo' => 'photo',
        'video' => 'video',
        'videos' => 'video',
        'special' => 'special',
    ];

    return $aliases[$bucket] ?? '';
}

function bandpromo_asset_files_index_target_for_intake_bucket(string $intakeBucket): string
{
    $bucket = bandpromo_asset_normalize_intake_bucket($intakeBucket);
    if ($bucket === 'img') {
        return 'illustrations';
    }
    if ($bucket === 'photo') {
        return 'photos';
    }

    return $bucket;
}

function bandpromo_asset_intake_bucket_for_files_index_target(string $target): string
{
    return bandpromo_asset_normalize_intake_bucket($target);
}

function bandpromo_asset_visual_original_dir(string $root, string $intakeBucket): string
{
    $bucket = bandpromo_asset_normalize_intake_bucket($intakeBucket);
    if ($bucket === 'img') {
        return $root . '/media/img/original';
    }
    if ($bucket === 'photo') {
        return $root . '/media/photo/original';
    }
    if ($bucket === 'video') {
        return $root . '/media/video/original';
    }
    if ($bucket === 'special') {
        return $root . '/media/special';
    }

    return '';
}

function bandpromo_asset_visual_original_path(string $root, array $asset): string
{
    $filename = basename(trim((string) ($asset['original_filename'] ?? '')));
    $bucket = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? ''));
    if ($filename === '' || $bucket === '') {
        return '';
    }

    $dir = bandpromo_asset_visual_original_dir($root, $bucket);

    return $dir === '' ? '' : ($dir . '/' . $filename);
}

function bandpromo_asset_file_sha256(string $path): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }
    $hash = @hash_file('sha256', $path);

    return is_string($hash) ? strtolower($hash) : '';
}

function bandpromo_asset_bytes_sha256(string $bytes): string
{
    if ($bytes === '') {
        return '';
    }

    return strtolower(hash('sha256', $bytes));
}

/**
 * Find a visual image asset whose intake original matches this content hash.
 */
function bandpromo_asset_lookup_visual_by_content_sha256(string $root, string $sha256): ?array
{
    $sha256 = strtolower(trim($sha256));
    if ($sha256 === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        if (($asset['media_type'] ?? '') !== 'image') {
            continue;
        }
        if (strtolower(trim((string) ($asset['content_sha256'] ?? ''))) === $sha256) {
            return bandpromo_asset_normalize_entry($asset);
        }
    }

    return null;
}

/**
 * Ensure content_sha256 is stored for a visual asset from its intake original bytes.
 */
function bandpromo_asset_ensure_visual_content_sha256(string $root, string $assetId): string
{
    $registry = bandpromo_asset_load_registry($root);
    $assetId = trim($assetId);
    if (!isset($registry['assets'][$assetId]) || !is_array($registry['assets'][$assetId])) {
        return '';
    }
    $asset = $registry['assets'][$assetId];
    if (($asset['kind'] ?? '') !== 'visual' || ($asset['media_type'] ?? '') !== 'image') {
        return '';
    }
    $existing = strtolower(trim((string) ($asset['content_sha256'] ?? '')));
    if ($existing !== '') {
        return $existing;
    }
    $path = bandpromo_asset_visual_original_path($root, $asset);
    $hash = bandpromo_asset_file_sha256($path);
    if ($hash === '') {
        return '';
    }
    $asset['content_sha256'] = $hash;
    $normalized = bandpromo_asset_normalize_entry($asset);
    if ($normalized === null) {
        return '';
    }
    $registry['assets'][$assetId] = $normalized;
    bandpromo_asset_write_registry($root, $registry);

    return $hash;
}

function bandpromo_asset_infer_media_type_from_filename(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return 'video';
    }
    if (in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return 'audio';
    }
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
        return 'image';
    }

    return 'other';
}

function bandpromo_asset_active_brand_id(string $root): string
{
    static $resolved = [];
    if (isset($resolved[$root])) {
        return $resolved[$root];
    }

    try {
        require_once __DIR__ . '/theme-storage.php';
        $resolved[$root] = bandpromo_brand_active_id($root);
    } catch (Throwable $throwable) {
        $resolved[$root] = 'bandpromo-default';
    }

    return $resolved[$root];
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

    $originalFilename = trim((string) ($entry['original_filename'] ?? ''));
    if ($originalFilename !== '') {
        $originalFilename = basename($originalFilename);
    }

    $masterFilename = basename(trim((string) ($entry['master_filename'] ?? '')));
    if ($masterFilename !== '' && strpbrk($masterFilename, '/\\') !== false) {
        return null;
    }

    if ($kind === 'visual') {
        if ($originalFilename === '') {
            return null;
        }
        // Visual identity is original_filename; keep master_filename as a stable index alias.
        if ($masterFilename === '') {
            $masterFilename = $originalFilename;
        }

        $intakeBucket = bandpromo_asset_normalize_intake_bucket((string) ($entry['intake_bucket'] ?? ''));
        if ($intakeBucket === '') {
            return null;
        }

        $mediaType = strtolower(trim((string) ($entry['media_type'] ?? '')));
        if ($mediaType === '') {
            $mediaType = bandpromo_asset_infer_media_type_from_filename($originalFilename);
        }
        if (!in_array($mediaType, ['image', 'video'], true)) {
            // Special can hold audio; those stay out of the visual family.
            return null;
        }

        $role = bandpromo_asset_normalize_visual_role((string) ($entry['role'] ?? 'unassigned'));
        $tags = is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [];
        if (!in_array($role, $tags, true)) {
            array_unshift($tags, $role);
        }

        return [
            'id' => $id,
            'kind' => 'visual',
            'media_type' => $mediaType,
            'intake_bucket' => $intakeBucket,
            'brand_id' => trim((string) ($entry['brand_id'] ?? '')),
            'role' => $role,
            'has_alpha' => !empty($entry['has_alpha']),
            'original_filename' => $originalFilename,
            'master_filename' => $masterFilename,
            'master_format' => strtolower(trim((string) ($entry['master_format'] ?? pathinfo($originalFilename, PATHINFO_EXTENSION)))),
            'release_id' => trim((string) ($entry['release_id'] ?? '')),
            'slug' => trim((string) ($entry['slug'] ?? '')),
            'display' => is_array($entry['display'] ?? null) ? $entry['display'] : [],
            'tags' => $tags,
            'delivery' => is_array($entry['delivery'] ?? null) ? $entry['delivery'] : [],
            'content_sha256' => strtolower(trim((string) ($entry['content_sha256'] ?? ''))),
            'created_at' => trim((string) ($entry['created_at'] ?? gmdate('c'))),
        ];
    }

    if ($kind === 'sfx') {
        if ($originalFilename === '') {
            return null;
        }
        if ($masterFilename === '') {
            $masterFilename = $originalFilename;
        }

        return [
            'id' => $id,
            'kind' => 'sfx',
            'media_type' => 'audio',
            'intake_bucket' => 'sfx',
            'brand_id' => trim((string) ($entry['brand_id'] ?? '')),
            'role' => 'sfx',
            'original_filename' => $originalFilename,
            'master_filename' => $masterFilename,
            'master_format' => strtolower(trim((string) ($entry['master_format'] ?? pathinfo($originalFilename, PATHINFO_EXTENSION)))),
            'release_id' => '',
            'slug' => '',
            'display' => is_array($entry['display'] ?? null) ? $entry['display'] : [],
            'tags' => ['sfx'],
            'delivery' => is_array($entry['delivery'] ?? null) ? $entry['delivery'] : ['ready' => true, 'source' => 'original'],
            'created_at' => trim((string) ($entry['created_at'] ?? gmdate('c'))),
        ];
    }

    if ($masterFilename === '') {
        return null;
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
        'delivery' => is_array($entry['delivery'] ?? null) ? $entry['delivery'] : [],
        'created_at' => trim((string) ($entry['created_at'] ?? gmdate('c'))),
    ];
}

function bandpromo_asset_normalize_registry(array $input): array
{
    $registry = bandpromo_asset_registry_default();
    $registry['version'] = (int) ($input['version'] ?? BANDPROMO_ASSET_REGISTRY_VERSION);

    $assets = [];
    $byMaster = [];
    $byOriginal = [];

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
            if ($normalized['master_filename'] !== '') {
                $byMaster[$normalized['master_filename']] = $normalized['id'];
            }
            if ($normalized['original_filename'] !== '') {
                $byOriginal[$normalized['original_filename']] = $normalized['id'];
            }
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

    if (isset($input['by_original_filename']) && is_array($input['by_original_filename'])) {
        foreach ($input['by_original_filename'] as $originalFilename => $assetId) {
            $originalFilename = basename((string) $originalFilename);
            $assetId = trim((string) $assetId);
            if ($originalFilename === '' || !bandpromo_asset_is_asset_id($assetId)) {
                continue;
            }
            if (!isset($assets[$assetId])) {
                continue;
            }
            $byOriginal[$originalFilename] = $assetId;
        }
    }

    $registry['assets'] = $assets;
    $registry['by_master_filename'] = $byMaster;
    $registry['by_original_filename'] = $byOriginal;

    return $registry;
}

function &bandpromo_asset_runtime_cache(string $root): array
{
    static $caches = [];
    if (!isset($caches[$root])) {
        $caches[$root] = [
            'registry' => null,
            'filename_index' => null,
        ];
    }

    return $caches[$root];
}

function bandpromo_asset_invalidate_runtime_cache(string $root): void
{
    $cache = &bandpromo_asset_runtime_cache($root);
    $cache['registry'] = null;
    $cache['filename_index'] = null;
}

function bandpromo_asset_write_registry(string $root, array $registry): void
{
    bandpromo_asset_registry_ensure_dir($root);
    $normalized = bandpromo_asset_normalize_registry($registry);
    if (!bandpromo_json_write_file(bandpromo_asset_registry_path($root), $normalized)) {
        throw new RuntimeException('Could not write asset registry.');
    }
    bandpromo_asset_invalidate_runtime_cache($root);
}

function bandpromo_asset_load_registry(string $root): array
{
    $cache = &bandpromo_asset_runtime_cache($root);
    if (is_array($cache['registry'])) {
        return $cache['registry'];
    }

    bandpromo_asset_registry_ensure_migrated($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_asset_registry_path($root));
    if ($decoded === null) {
        bandpromo_asset_write_registry($root, bandpromo_asset_registry_default());
        $cache['registry'] = bandpromo_asset_registry_default();

        return $cache['registry'];
    }

    $cache['registry'] = bandpromo_asset_normalize_registry($decoded);

    return $cache['registry'];
}

function bandpromo_asset_filename_index(string $root): array
{
    $cache = &bandpromo_asset_runtime_cache($root);
    if (is_array($cache['filename_index'])) {
        return $cache['filename_index'];
    }

    $index = [];
    foreach (bandpromo_asset_load_registry($root)['assets'] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $assetId = trim((string) ($asset['id'] ?? ''));
        if ($assetId === '') {
            continue;
        }
        foreach (['master_filename', 'original_filename'] as $field) {
            $filename = basename(trim((string) ($asset[$field] ?? '')));
            if ($filename !== '') {
                $index[$filename] = $assetId;
            }
        }
    }

    $cache['filename_index'] = $index;

    return $cache['filename_index'];
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
    $assetId = trim((string) ($registry['by_original_filename'][$originalFilename] ?? ''));
    if ($assetId !== '' && isset($registry['assets'][$assetId])) {
        return $registry['assets'][$assetId];
    }

    $assetId = trim((string) (bandpromo_asset_filename_index($root)[$originalFilename] ?? ''));
    if ($assetId === '') {
        return null;
    }

    return bandpromo_asset_lookup_by_id($root, $assetId);
}

/**
 * Look up a visual asset by original filename.
 *
 * Visual identity is global for a basename. When $intakeBucket is set and the
 * indexed row uses a different bucket, still return that row (callers update
 * the bucket in place). Returning null on mismatch used to mint duplicate
 * ast_ IDs while orphan rows stayed in assets.
 */
function bandpromo_asset_lookup_visual(
    string $root,
    string $originalFilename,
    string $intakeBucket = ''
): ?array {
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return null;
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $originalFilename);
    if ($asset !== null && ($asset['kind'] ?? '') === 'visual') {
        return $asset;
    }

    // Index can lag behind orphan rows; scan assets as a safety net.
    return bandpromo_asset_find_visual_orphan_by_original($root, $originalFilename);
}

/**
 * Find any visual asset row with this original_filename (including orphans
 * not pointed at by by_original_filename).
 */
function bandpromo_asset_find_visual_orphan_by_original(string $root, string $originalFilename): ?array
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    $best = null;
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        if (basename((string) ($asset['original_filename'] ?? '')) !== $originalFilename) {
            continue;
        }
        if ($best === null
            || strcmp((string) ($asset['created_at'] ?? ''), (string) ($best['created_at'] ?? '')) > 0
        ) {
            $best = $asset;
        }
    }

    return $best;
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

/**
 * Register a visual (image or video) asset in the shared registry.
 *
 * @param array{
 *   role?: string,
 *   brand_id?: string,
 *   has_alpha?: bool,
 *   asset_id?: string|null
 * } $options
 */
function bandpromo_asset_register_visual(
    string $root,
    string $originalFilename,
    string $intakeBucket,
    string $mediaType = '',
    array $options = []
): array {
    $originalFilename = basename(trim($originalFilename));
    $intakeBucket = bandpromo_asset_normalize_intake_bucket($intakeBucket);
    if ($originalFilename === '' || $intakeBucket === '') {
        throw new InvalidArgumentException('Visual registration requires filename and intake bucket.');
    }

    if ($mediaType === '') {
        $mediaType = bandpromo_asset_infer_media_type_from_filename($originalFilename);
    }
    $mediaType = strtolower(trim($mediaType));
    if (!in_array($mediaType, ['image', 'video'], true)) {
        throw new InvalidArgumentException('Visual assets must be image or video.');
    }

    $existing = bandpromo_asset_lookup_visual($root, $originalFilename);
    if ($existing !== null) {
        $changes = [];
        $wantedBucket = bandpromo_asset_normalize_intake_bucket($intakeBucket);
        if ($wantedBucket !== '' && ($existing['intake_bucket'] ?? '') !== $wantedBucket) {
            $changes['intake_bucket'] = $wantedBucket;
        }
        if (isset($options['role'])) {
            $changes['role'] = (string) $options['role'];
        }
        if (array_key_exists('brand_id', $options)) {
            $changes['brand_id'] = (string) $options['brand_id'];
        }
        if (array_key_exists('has_alpha', $options)) {
            $changes['has_alpha'] = !empty($options['has_alpha']);
        }
        if (isset($options['content_sha256'])) {
            $changes['content_sha256'] = (string) $options['content_sha256'];
        }
        if ($changes !== []) {
            $updated = bandpromo_asset_update_entry($root, (string) $existing['id'], $changes);
            if (trim((string) ($updated['content_sha256'] ?? '')) === '') {
                bandpromo_asset_ensure_visual_content_sha256($root, (string) ($updated['id'] ?? ''));
                $updated = bandpromo_asset_lookup_by_id($root, (string) ($updated['id'] ?? '')) ?? $updated;
            }

            return $updated;
        }
        if (trim((string) ($existing['content_sha256'] ?? '')) === '') {
            bandpromo_asset_ensure_visual_content_sha256($root, (string) ($existing['id'] ?? ''));
            $existing = bandpromo_asset_lookup_by_id($root, (string) ($existing['id'] ?? '')) ?? $existing;
        }

        return $existing;
    }

    $assetId = isset($options['asset_id']) && bandpromo_asset_is_asset_id((string) $options['asset_id'])
        ? (string) $options['asset_id']
        : bandpromo_generate_asset_id();

    $role = bandpromo_asset_normalize_visual_role((string) ($options['role'] ?? 'unassigned'));
    $brandId = trim((string) ($options['brand_id'] ?? ''));
    if ($brandId === '') {
        $brandId = bandpromo_asset_active_brand_id($root);
    }

    $contentSha = strtolower(trim((string) ($options['content_sha256'] ?? '')));
    $entry = [
        'id' => $assetId,
        'kind' => 'visual',
        'media_type' => $mediaType,
        'intake_bucket' => $intakeBucket,
        'brand_id' => $brandId,
        'role' => $role,
        'has_alpha' => !empty($options['has_alpha']),
        'original_filename' => $originalFilename,
        'master_filename' => $originalFilename,
        'master_format' => strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION)),
        'release_id' => '',
        'slug' => '',
        'display' => [],
        'tags' => [$role],
        'delivery' => [],
        'content_sha256' => $contentSha,
        'created_at' => gmdate('c'),
    ];

    $registry = bandpromo_asset_load_registry($root);
    $registry['assets'][$assetId] = $entry;
    $registry['by_master_filename'][$originalFilename] = $assetId;
    $registry['by_original_filename'][$originalFilename] = $assetId;
    bandpromo_asset_write_registry($root, $registry);

    if ($contentSha === '') {
        bandpromo_asset_ensure_visual_content_sha256($root, $assetId);
    }

    return bandpromo_asset_lookup_by_id($root, $assetId)
        ?? (bandpromo_asset_normalize_entry($entry) ?? $entry);
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
    if ($originalFilename !== '') {
        $registry['by_original_filename'][$originalFilename] = $assetId;
    }
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
    $previousMaster = (string) ($entry['master_filename'] ?? '');
    $previousOriginal = (string) ($entry['original_filename'] ?? '');

    foreach (['release_id', 'slug', 'original_filename', 'brand_id', 'media_type', 'intake_bucket'] as $key) {
        if (array_key_exists($key, $changes)) {
            $entry[$key] = trim((string) $changes[$key]);
        }
    }
    if (array_key_exists('role', $changes)) {
        $entry['role'] = bandpromo_asset_normalize_visual_role((string) $changes['role']);
    }
    if (array_key_exists('has_alpha', $changes)) {
        $entry['has_alpha'] = !empty($changes['has_alpha']);
    }
    if (array_key_exists('content_sha256', $changes)) {
        $entry['content_sha256'] = strtolower(trim((string) $changes['content_sha256']));
    }
    if (isset($changes['display']) && is_array($changes['display'])) {
        $existingDisplay = is_array($entry['display'] ?? null) ? $entry['display'] : [];
        $entry['display'] = array_merge($existingDisplay, $changes['display']);
    }
    if (isset($changes['delivery']) && is_array($changes['delivery'])) {
        $existingDelivery = is_array($entry['delivery'] ?? null) ? $entry['delivery'] : [];
        // Deep-merge variants map when present.
        if (isset($changes['delivery']['variants']) && is_array($changes['delivery']['variants'])) {
            $existingVariants = is_array($existingDelivery['variants'] ?? null) ? $existingDelivery['variants'] : [];
            $existingDelivery['variants'] = array_merge($existingVariants, $changes['delivery']['variants']);
            unset($changes['delivery']['variants']);
        }
        $entry['delivery'] = array_merge($existingDelivery, $changes['delivery']);
    }
    if (isset($changes['tags']) && is_array($changes['tags'])) {
        $entry['tags'] = array_values($changes['tags']);
    } elseif (isset($entry['role']) && ($entry['kind'] ?? '') === 'visual') {
        $tags = is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [];
        $role = bandpromo_asset_normalize_visual_role((string) $entry['role']);
        $tags = array_values(array_filter($tags, static fn($tag): bool => !in_array((string) $tag, bandpromo_asset_visual_roles(), true)));
        array_unshift($tags, $role);
        $entry['tags'] = $tags;
        $entry['role'] = $role;
    }

    $normalized = bandpromo_asset_normalize_entry($entry);
    if ($normalized === null) {
        throw new InvalidArgumentException('Invalid asset entry.');
    }

    if ($previousMaster !== '' && $previousMaster !== $normalized['master_filename']) {
        unset($registry['by_master_filename'][$previousMaster]);
    }
    if ($previousOriginal !== '' && $previousOriginal !== $normalized['original_filename']) {
        unset($registry['by_original_filename'][$previousOriginal]);
    }

    $registry['assets'][$assetId] = $normalized;
    if ($normalized['master_filename'] !== '') {
        $registry['by_master_filename'][$normalized['master_filename']] = $assetId;
    }
    if ($normalized['original_filename'] !== '') {
        $registry['by_original_filename'][$normalized['original_filename']] = $assetId;
    }
    bandpromo_asset_write_registry($root, $registry);

    return $normalized;
}

function bandpromo_asset_read_audio_display(?array $asset): array
{
    if (!is_array($asset)) {
        return [
            'title' => '',
            'version' => '',
            'artist' => '',
            'album' => '',
            'duration' => 0,
            'date' => '',
            'tracknumber' => '',
            'bpm' => '',
            'initialkey' => '',
            'genre' => '',
            'comment' => '',
            'lyrics' => '',
            'living_cover' => '',
            'cover' => '',
            'synced_at' => '',
        ];
    }

    $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];

    return [
        'title' => trim((string) ($display['title'] ?? '')),
        'version' => trim((string) ($display['version'] ?? '')),
        'artist' => trim((string) ($display['artist'] ?? '')),
        'album' => trim((string) ($display['album'] ?? '')),
        'duration' => max(0, (int) ($display['duration'] ?? 0)),
        'date' => trim((string) ($display['date'] ?? '')),
        'tracknumber' => trim((string) ($display['tracknumber'] ?? '')),
        'bpm' => trim((string) ($display['bpm'] ?? '')),
        'initialkey' => trim((string) ($display['initialkey'] ?? '')),
        'genre' => trim((string) ($display['genre'] ?? '')),
        'comment' => trim((string) ($display['comment'] ?? '')),
        'lyrics' => (string) ($display['lyrics'] ?? ''),
        'living_cover' => trim((string) ($display['living_cover'] ?? '')),
        'cover' => basename(trim((string) ($display['cover'] ?? ''))),
        'synced_at' => trim((string) ($display['synced_at'] ?? '')),
    ];
}

function bandpromo_asset_audio_display_is_complete(array $display): bool
{
    return trim((string) ($display['title'] ?? '')) !== ''
        && trim((string) ($display['artist'] ?? '')) !== ''
        && (int) ($display['duration'] ?? 0) > 0;
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
    $originalFilename = (string) ($registry['assets'][$assetId]['original_filename'] ?? '');
    unset($registry['assets'][$assetId]);
    if ($masterFilename !== '') {
        unset($registry['by_master_filename'][$masterFilename]);
    }
    if ($originalFilename !== '') {
        unset($registry['by_original_filename'][$originalFilename]);
    }

    bandpromo_asset_write_registry($root, $registry);
}

function bandpromo_asset_unregister_by_original_filename(string $root, string $originalFilename): void
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return;
    }

    $registry = bandpromo_asset_load_registry($root);
    $ids = [];
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if (basename((string) ($asset['original_filename'] ?? '')) !== $originalFilename) {
            continue;
        }
        $ids[] = (string) $assetId;
    }

    foreach ($ids as $assetId) {
        bandpromo_asset_unregister($root, $assetId);
    }
}

/**
 * Keep one visual asset per original_filename; drop orphan duplicates.
 *
 * @return bool True when assets were removed.
 */
function bandpromo_asset_registry_prune_duplicate_visuals(array &$registry): bool
{
    if (!isset($registry['assets']) || !is_array($registry['assets'])) {
        return false;
    }

    $byOriginal = [];
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $original = basename((string) ($asset['original_filename'] ?? ''));
        if ($original === '') {
            continue;
        }
        $byOriginal[$original][] = (string) $assetId;
    }

    $changed = false;
    $indexOriginal = is_array($registry['by_original_filename'] ?? null)
        ? $registry['by_original_filename']
        : [];

    foreach ($byOriginal as $original => $ids) {
        if (count($ids) < 2) {
            continue;
        }

        $preferred = trim((string) ($indexOriginal[$original] ?? ''));
        if ($preferred === '' || !in_array($preferred, $ids, true)) {
            $preferred = $ids[0];
            $newest = '';
            foreach ($ids as $assetId) {
                $created = (string) ($registry['assets'][$assetId]['created_at'] ?? '');
                if ($newest === '' || strcmp($created, $newest) > 0) {
                    $newest = $created;
                    $preferred = $assetId;
                }
            }
        }

        foreach ($ids as $assetId) {
            if ($assetId === $preferred) {
                continue;
            }
            unset($registry['assets'][$assetId]);
            $changed = true;
        }

        $registry['by_original_filename'][$original] = $preferred;
        $registry['by_master_filename'][$original] = $preferred;
    }

    return $changed;
}

/**
 * Backfill content_sha256 on visual image assets from intake originals.
 */
function bandpromo_asset_registry_backfill_visual_content_hashes(string $root, array &$registry): bool
{
    if (!isset($registry['assets']) || !is_array($registry['assets'])) {
        return false;
    }

    $changed = false;
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual' || ($asset['media_type'] ?? '') !== 'image') {
            continue;
        }
        if (strtolower(trim((string) ($asset['content_sha256'] ?? ''))) !== '') {
            continue;
        }
        $path = bandpromo_asset_visual_original_path($root, $asset);
        $hash = bandpromo_asset_file_sha256($path);
        if ($hash === '') {
            continue;
        }
        $asset['content_sha256'] = $hash;
        $normalized = bandpromo_asset_normalize_entry($asset);
        if ($normalized === null) {
            continue;
        }
        $registry['assets'][$assetId] = $normalized;
        $changed = true;
    }

    return $changed;
}

/**
 * Collapse visual images that share identical intake bytes into one asset.
 * Re-points audio display.cover and deletes redundant originals + delivery dirs.
 *
 * @return array{changed: bool, removed: int, relinked: int}
 */
function bandpromo_asset_registry_dedupe_visuals_by_content_hash(string $root, array &$registry): array
{
    $result = ['changed' => false, 'removed' => 0, 'relinked' => 0];
    if (!isset($registry['assets']) || !is_array($registry['assets'])) {
        return $result;
    }

    $byHash = [];
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual' || ($asset['media_type'] ?? '') !== 'image') {
            continue;
        }
        $hash = strtolower(trim((string) ($asset['content_sha256'] ?? '')));
        if ($hash === '') {
            continue;
        }
        $byHash[$hash][] = (string) $assetId;
    }

    $replacements = []; // discarded original_filename => kept original_filename
    $removeIds = [];

    foreach ($byHash as $hash => $ids) {
        if (count($ids) < 2) {
            continue;
        }

        $preferred = $ids[0];
        $bestScore = -1;
        foreach ($ids as $assetId) {
            $asset = $registry['assets'][$assetId] ?? null;
            if (!is_array($asset)) {
                continue;
            }
            $role = (string) ($asset['role'] ?? 'unassigned');
            $name = basename((string) ($asset['original_filename'] ?? ''));
            $score = 0;
            if ($role === 'track-cover') {
                $score += 10;
            }
            if ($role !== 'unassigned') {
                $score += 5;
            }
            // Prefer human upload names over ULID/stem extracts.
            if ($name !== '' && !preg_match('/^ast_[0-9A-HJKMNP-TV-Z]{20}/i', $name)) {
                $score += 3;
            }
            $created = (string) ($asset['created_at'] ?? '');
            if ($score > $bestScore || ($score === $bestScore && strcmp($created, (string) ($registry['assets'][$preferred]['created_at'] ?? '')) < 0)) {
                $bestScore = $score;
                $preferred = $assetId;
            }
        }

        $keptName = basename((string) ($registry['assets'][$preferred]['original_filename'] ?? ''));
        if ($keptName === '') {
            continue;
        }

        foreach ($ids as $assetId) {
            if ($assetId === $preferred) {
                continue;
            }
            $discardName = basename((string) ($registry['assets'][$assetId]['original_filename'] ?? ''));
            if ($discardName !== '') {
                $replacements[$discardName] = $keptName;
            }
            $removeIds[] = $assetId;
        }
    }

    if ($removeIds === []) {
        return $result;
    }

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $cover = basename(trim((string) ($display['cover'] ?? '')));
        if ($cover === '' || !isset($replacements[$cover])) {
            continue;
        }
        $display['cover'] = $replacements[$cover];
        $asset['display'] = $display;
        $normalized = bandpromo_asset_normalize_entry($asset);
        if ($normalized === null) {
            continue;
        }
        $registry['assets'][$assetId] = $normalized;
        $result['relinked']++;
        $result['changed'] = true;
    }

    require_once __DIR__ . '/media-delivery-helpers.php';
    foreach ($removeIds as $assetId) {
        $asset = $registry['assets'][$assetId] ?? null;
        if (!is_array($asset)) {
            continue;
        }
        $path = bandpromo_asset_visual_original_path($root, $asset);
        $original = basename((string) ($asset['original_filename'] ?? ''));
        unset($registry['assets'][$assetId]);
        if ($original !== '') {
            unset($registry['by_original_filename'][$original], $registry['by_master_filename'][$original]);
        }
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
        bandpromo_visual_delivery_delete_for_asset($root, $assetId);
        $result['removed']++;
        $result['changed'] = true;
    }

    return $result;
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

/**
 * Backfill visual assets from legacy intake folders.
 *
 * @return bool True when the registry was modified.
 */
function bandpromo_asset_registry_backfill_visuals(string $root, array &$registry): bool
{
    $imageExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $videoExts = ['mp4', 'webm', 'mov'];
    $changed = false;
    $brandId = bandpromo_asset_active_brand_id($root);

    $buckets = [
        'img' => $imageExts,
        'photo' => $imageExts,
        'video' => $videoExts,
        'special' => array_merge($imageExts, $videoExts),
    ];

    foreach ($buckets as $intakeBucket => $allowedExts) {
        $dir = bandpromo_asset_visual_original_dir($root, $intakeBucket);
        if ($dir === '' || !is_dir($dir)) {
            continue;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }

            $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts, true)) {
                continue;
            }

            $mediaType = in_array($ext, $videoExts, true) ? 'video' : 'image';

            // Prefer any existing visual with this basename (any bucket) — never mint
            // another ast_ ID when the index points at a different intake folder.
            $existingId = '';
            $existing = null;
            $indexedId = trim((string) ($registry['by_original_filename'][$entry] ?? ''));
            if ($indexedId !== '' && isset($registry['assets'][$indexedId])
                && ($registry['assets'][$indexedId]['kind'] ?? '') === 'visual'
            ) {
                $existingId = $indexedId;
                $existing = $registry['assets'][$indexedId];
            } else {
                foreach ($registry['assets'] as $assetId => $asset) {
                    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
                        continue;
                    }
                    if (basename((string) ($asset['original_filename'] ?? '')) !== $entry) {
                        continue;
                    }
                    $existingId = (string) $assetId;
                    $existing = $asset;
                    break;
                }
            }

            if ($existing !== null && $existingId !== '') {
                if (($existing['intake_bucket'] ?? '') !== $intakeBucket) {
                    $registry['assets'][$existingId]['intake_bucket'] = $intakeBucket;
                    $changed = true;
                }
                $registry['by_original_filename'][$entry] = $existingId;
                $registry['by_master_filename'][$entry] = $existingId;
                continue;
            }

            $assetId = bandpromo_generate_asset_id();
            $normalized = bandpromo_asset_normalize_entry([
                'id' => $assetId,
                'kind' => 'visual',
                'media_type' => $mediaType,
                'intake_bucket' => $intakeBucket,
                'brand_id' => $brandId,
                'role' => 'unassigned',
                'has_alpha' => false,
                'original_filename' => $entry,
                'master_filename' => $entry,
                'master_format' => $ext,
                'tags' => ['unassigned'],
                'delivery' => [],
                'created_at' => gmdate('c'),
            ]);
            if ($normalized === null) {
                continue;
            }

            $registry['assets'][$assetId] = $normalized;
            $registry['by_master_filename'][$entry] = $assetId;
            $registry['by_original_filename'][$entry] = $assetId;
            $changed = true;
        }
    }

    return $changed;
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

    $changed = false;

    $masterDir = $root . '/media/audio/master';
    if (is_dir($masterDir)) {
        $entries = scandir($masterDir);
        if (is_array($entries)) {
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
                $releaseId = strncmp($entry, 'bandPromo_', 10) === 0 ? 'bandpromo-demo' : '';
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
                if ($normalized['original_filename'] !== '') {
                    $registry['by_original_filename'][$normalized['original_filename']] = $assetId;
                }
                $changed = true;
            }
        }
    }

    if (bandpromo_asset_registry_backfill_visuals($root, $registry)) {
        $changed = true;
    }

    if (bandpromo_asset_registry_prune_duplicate_visuals($registry)) {
        $changed = true;
    }

    if (bandpromo_asset_registry_backfill_visual_content_hashes($root, $registry)) {
        $changed = true;
    }

    $hashDedupe = bandpromo_asset_registry_dedupe_visuals_by_content_hash($root, $registry);
    if (!empty($hashDedupe['changed'])) {
        $changed = true;
    }

    if ($changed || !is_file($path)) {
        bandpromo_asset_write_registry($root, $registry);
        if (is_dir($masterDir)) {
            bandpromo_asset_reconcile_audio_originals($root);
        }
    }

    // Sound effects: copy special shell audio into media/sfx and rewrite brand/config refs.
    require_once __DIR__ . '/sfx-helpers.php';
    try {
        bandpromo_sfx_migrate_from_special($root);
    } catch (Throwable $throwable) {
        // Best-effort migrate; Files → Sound effects still works for new uploads.
    }
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
        bandpromo_release_repair_catalog_release_ids($root);
        bandpromo_mark_build_required('media_audio_upload');
    }

    return $result;
}
