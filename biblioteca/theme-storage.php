<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/config-loader.php';

const BANDPROMO_THEME_REGISTRY_VERSION = 1;
/** Canonical default brand id (legacy alias: setup-default). */
const BANDPROMO_BRAND_DEFAULT_ID = 'bandpromo-default';
const BANDPROMO_THEME_DEFAULT_ID = 'setup-default';
/** Opaque operator brand ids: brd_ + ULID body (same ULID helper as assets). */
const BANDPROMO_BRAND_ID_PREFIX = 'brd_';

function bandpromo_brand_canonical_id(string $id): string
{
    $id = bandpromo_theme_normalize_id($id);
    if ($id === BANDPROMO_THEME_DEFAULT_ID) {
        return BANDPROMO_BRAND_DEFAULT_ID;
    }

    return $id;
}

function bandpromo_brand_normalize_pool_filter(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === 'all') {
        return 'all';
    }
    if ($value === 'orphans') {
        return 'orphans';
    }

    return bandpromo_brand_canonical_id($value);
}

function bandpromo_brand_legacy_theme_id(string $id): string
{
    $id = bandpromo_brand_canonical_id($id);

    return $id === BANDPROMO_BRAND_DEFAULT_ID ? BANDPROMO_THEME_DEFAULT_ID : $id;
}

function bandpromo_theme_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'brands';
}

function bandpromo_theme_legacy_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'themes';
}

function bandpromo_theme_registry_path(string $root): string
{
    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_theme_document_path(string $root, string $themeId): string
{
    $themeId = bandpromo_brand_canonical_id($themeId);

    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . $themeId . '.json';
}

function bandpromo_theme_legacy_document_path(string $root, string $themeId): string
{
    return bandpromo_theme_legacy_storage_root($root) . DIRECTORY_SEPARATOR . bandpromo_theme_normalize_id($themeId) . '.json';
}

function bandpromo_theme_template_path(string $root, string $themeId): string
{
    return $root . DIRECTORY_SEPARATOR . 'biblioteca' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR
        . bandpromo_theme_normalize_id($themeId) . '.theme.template.json';
}

function bandpromo_theme_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_theme_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/brands directory.');
    }
}

function bandpromo_theme_normalize_id(string $themeId): string
{
    $themeId = strtolower(trim($themeId));
    // Allow underscore so opaque brd_{ulid} ids survive (legacy ids stay hyphenated).
    $themeId = preg_replace('/[^a-z0-9_-]+/', '-', $themeId) ?? '';
    $themeId = trim($themeId, '-_');

    return substr($themeId, 0, 48);
}

function bandpromo_generate_brand_id(): string
{
    require_once __DIR__ . '/asset-registry.php';

    return BANDPROMO_BRAND_ID_PREFIX . strtolower(bandpromo_generate_ulid());
}

function bandpromo_brand_is_opaque_id(string $value): bool
{
    $value = bandpromo_theme_normalize_id($value);
    if ($value === '' || !str_starts_with($value, BANDPROMO_BRAND_ID_PREFIX)) {
        return false;
    }

    $body = substr($value, strlen(BANDPROMO_BRAND_ID_PREFIX));

    return (bool) preg_match('/^[0-9a-hjkmnp-tv-z]{20}$/', $body);
}

function bandpromo_theme_default_color_tokens(): array
{
    return [
        'primary' => '#00d2ff',
        'secondary' => '#3a7bd5',
        'background' => '#121212',
        'text' => '#ffffff',
        'text_muted' => '#dddddd',
        'surface_mid' => '#1e1e24',
        'surface_deep' => '#000000',
        'link' => '#00d2ff',
        'link_hover' => '#5ce4ff',
        'link_visited' => '#3a7bd5',
    ];
}

function bandpromo_theme_default_effects_tokens(): array
{
    return [
        // Percent 0–100 (maps to --shell-scrim-strength 0–1). Default matches prior fixed scrim mid tone.
        'backdrop_dim' => '72',
        // Pixels 0–24 for glass panels (playlist rows, lyrics, pages, gallery, login).
        'panel_blur' => '5',
    ];
}

function bandpromo_theme_normalize_int_token(mixed $value, int $min, int $max, int $fallback): string
{
    if (is_numeric($value)) {
        $n = (int) round((float) $value);
    } else {
        $n = (int) preg_replace('/\D+/', '', trim((string) $value));
    }
    if ($n < $min || $n > $max) {
        // Empty / garbage → fallback; out-of-range clamp when clearly numeric intent
        if (!is_numeric($value) && trim((string) $value) === '') {
            $n = $fallback;
        } else {
            $n = max($min, min($max, $n));
        }
    }

    return (string) $n;
}

function bandpromo_theme_css_variable_map(): array
{
    return [
        'color.primary' => '--primary-color',
        'color.secondary' => '--secondary-color',
        'color.background' => '--bg-color',
        'color.text' => '--text-color',
        'color.text_muted' => '--color-text-muted',
        'color.surface_mid' => '--color-surface-mid',
        'color.surface_deep' => '--color-surface-deep',
        'color.link' => '--color-link',
        'color.link_hover' => '--color-link-hover',
        'color.link_visited' => '--color-link-visited',
    ];
}

/**
 * Extra CSS vars derived from effects tokens (not 1:1 path map).
 *
 * @return array<string, string>
 */
function bandpromo_theme_effects_css_variables(array $document): array
{
    $dim = (int) bandpromo_theme_normalize_int_token(
        bandpromo_theme_token_value($document, 'effects.backdrop_dim'),
        0,
        100,
        72
    );
    $blur = (int) bandpromo_theme_normalize_int_token(
        bandpromo_theme_token_value($document, 'effects.panel_blur'),
        0,
        24,
        5
    );

    return [
        '--shell-scrim-strength' => number_format($dim / 100, 2, '.', ''),
        '--panel-blur' => $blur . 'px',
    ];
}

function bandpromo_theme_derived_alpha_css_variables(): array
{
    $variables = [];
    foreach ([5, 8, 10, 12, 15, 20, 24, 25, 28, 30, 35, 40, 45, 50] as $percent) {
        $suffix = str_pad((string) $percent, 2, '0', STR_PAD_LEFT);
        $variables['--primary-a' . $suffix] = sprintf(
            'color-mix(in srgb, var(--primary-color) %d%%, transparent)',
            $percent
        );
    }
    $variables['--secondary-a20'] = 'color-mix(in srgb, var(--secondary-color) 20%, transparent)';
    $variables['--secondary-a30'] = 'color-mix(in srgb, var(--secondary-color) 30%, transparent)';

    return $variables;
}

function bandpromo_brand_normalize_narrative_field(mixed $value, int $maxLength): string
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

function bandpromo_theme_default_document(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => BANDPROMO_BRAND_DEFAULT_ID,
        'title' => 'bandPromo Default',
        'system' => true,
        'locked' => true,
        'release_id' => 'bandpromo-demo',
        'mood' => 'Clean demo identity for first-run installs',
        'keywords' => ['demo', 'electronic', 'modern'],
        'tone_notes' => 'Neutral platform defaults; duplicate and customize as release identity.',
        'tokens' => [
            'color' => bandpromo_theme_default_color_tokens(),
            'effects' => bandpromo_theme_default_effects_tokens(),
            'layout' => [
                'card_size_base' => '400px',
            ],
            'typography' => [
                'font_family_base' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                'font_family_heading' => '',
            ],
        ],
        // Paths are delivery URLs only (filled from asset_ids). No /media/special seeds.
        'assets' => [
            'logo' => '',
            'poster' => '',
            'background_image' => '',
            'background_video' => '',
            'welcome_audio' => '',
            'loggedin_audio' => '',
        ],
        // Shell slot → registry asset id (resolve via bandpromo_theme_resolve_shell_slot_url).
        'asset_ids' => [
            'logo' => '',
            'poster' => '',
            'background_image' => '',
            'background_video' => '',
            'welcome_audio' => '',
            'loggedin_audio' => '',
        ],
        // Player chrome preferences owned by the brand (Base brand drives /play).
        'player' => [
            'playlist_selector' => 'coverflow',
        ],
    ];
}

function bandpromo_theme_normalize_playlist_selector_mode(mixed $value): string
{
    $mode = strtolower(trim((string) $value));
    if ($mode === 'dropdown' || $mode === 'buttons' || $mode === 'coverflow') {
        return $mode;
    }

    return 'coverflow';
}

/**
 * Resolve playlist selector for a brand document, migrating from legacy web-config when absent.
 *
 * @param array<string, mixed> $input
 */
function bandpromo_theme_legacy_playlist_selector_fallback(): string
{
    try {
        if (function_exists('get_config')) {
            $raw = get_config('player.playlist_selector', '');
            if (is_string($raw) && trim($raw) !== '') {
                return bandpromo_theme_normalize_playlist_selector_mode($raw);
            }
        }
    } catch (Throwable $throwable) {
        // Config may be unavailable during early bootstrap.
    }

    return 'coverflow';
}

/**
 * @param array<string, mixed> $input
 * @return array{playlist_selector: string}
 */
function bandpromo_theme_normalize_player(array $input): array
{
    $player = is_array($input['player'] ?? null) ? $input['player'] : [];
    $raw = $player['playlist_selector'] ?? null;
    if ($raw === null || trim((string) $raw) === '') {
        $selector = bandpromo_theme_legacy_playlist_selector_fallback();
    } else {
        $selector = bandpromo_theme_normalize_playlist_selector_mode($raw);
    }

    return [
        'playlist_selector' => $selector,
    ];
}

function bandpromo_theme_default_registry(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'brands' => [
            [
                'id' => BANDPROMO_BRAND_DEFAULT_ID,
                'title' => 'bandPromo Default',
                'system' => true,
                'locked' => true,
                'sort_order' => 10,
            ],
        ],
    ];
}

function bandpromo_theme_normalize_hex_color(string $value, string $fallback): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
        return strtolower($value);
    }

    return $fallback;
}

function bandpromo_theme_normalize_font_family(string $value, string $fallback = '', bool $allowEmpty = false): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    if ($value === '') {
        return $allowEmpty ? '' : $fallback;
    }
    if (
        strlen($value) > 180
        || preg_match('/^[\p{L}\p{N}\s,\'"\-]+$/u', $value) !== 1
        || substr_count($value, "'") % 2 !== 0
        || substr_count($value, '"') % 2 !== 0
    ) {
        return $allowEmpty ? '' : $fallback;
    }

    $families = array_values(array_filter(array_map('trim', explode(',', $value)), static function (string $family): bool {
        return $family !== '';
    }));
    if ($families === []) {
        return $allowEmpty ? '' : $fallback;
    }

    $normalized = implode(', ', $families);
    if (preg_match('/(?:^|,\s*)(serif|sans-serif|monospace|cursive|fantasy|system-ui|ui-serif|ui-sans-serif|ui-monospace)$/i', $normalized) !== 1) {
        $normalized .= ', sans-serif';
    }

    return $normalized;
}

function bandpromo_theme_normalize_tokens(array $tokens): array
{
    $defaults = bandpromo_theme_default_document()['tokens'];
    $color = is_array($tokens['color'] ?? null) ? $tokens['color'] : [];
    $effects = is_array($tokens['effects'] ?? null) ? $tokens['effects'] : [];
    $layout = is_array($tokens['layout'] ?? null) ? $tokens['layout'] : [];
    $typography = is_array($tokens['typography'] ?? null) ? $tokens['typography'] : [];
    $defaultColor = bandpromo_theme_default_color_tokens();
    $defaultEffects = bandpromo_theme_default_effects_tokens();

    $normalizedColor = [];
    foreach ($defaultColor as $key => $fallback) {
        $normalizedColor[$key] = bandpromo_theme_normalize_hex_color((string) ($color[$key] ?? ''), $fallback);
    }

    $normalizedEffects = [
        'backdrop_dim' => bandpromo_theme_normalize_int_token(
            $effects['backdrop_dim'] ?? $defaultEffects['backdrop_dim'],
            0,
            100,
            (int) $defaultEffects['backdrop_dim']
        ),
        'panel_blur' => bandpromo_theme_normalize_int_token(
            $effects['panel_blur'] ?? $defaultEffects['panel_blur'],
            0,
            24,
            (int) $defaultEffects['panel_blur']
        ),
    ];

    $cardSize = trim((string) ($layout['card_size_base'] ?? $defaults['layout']['card_size_base']));
    if ($cardSize === '' || !preg_match('/^\d+(px|rem|em|%)$/', $cardSize)) {
        $cardSize = (string) $defaults['layout']['card_size_base'];
    }

    $defaultFontBase = (string) $defaults['typography']['font_family_base'];
    $fontBase = bandpromo_theme_normalize_font_family(
        (string) ($typography['font_family_base'] ?? $defaultFontBase),
        $defaultFontBase
    );
    $fontHeading = bandpromo_theme_normalize_font_family(
        (string) ($typography['font_family_heading'] ?? ''),
        '',
        true
    );

    return [
        'color' => $normalizedColor,
        'effects' => $normalizedEffects,
        'layout' => [
            'card_size_base' => $cardSize,
        ],
        'typography' => [
            'font_family_base' => $fontBase,
            'font_family_heading' => $fontHeading,
        ],
    ];
}

function bandpromo_theme_normalize_assets(array $assets): array
{
    $defaults = bandpromo_theme_default_document()['assets'];
    $requiredKeys = ['logo' => true, 'poster' => true];
    $normalized = [];
    foreach ($defaults as $key => $defaultValue) {
        $hasKey = array_key_exists($key, $assets);
        $value = $hasKey ? trim((string) $assets[$key]) : trim((string) $defaultValue);
        if ($value !== '' && ($value[0] === '/' || preg_match('/^https?:\/\//i', $value) === 1)) {
            $normalized[$key] = $value;
            continue;
        }
        if (isset($requiredKeys[$key])) {
            $normalized[$key] = (string) $defaultValue;
            continue;
        }
        // Optional shell slots (backgrounds/audio) may stay cleared.
        $normalized[$key] = '';
    }

    return $normalized;
}

/**
 * Normalize parallel shell slot → asset_id map (empty string when unset).
 *
 * @param array<string, mixed> $assetIds
 * @return array<string, string>
 */
function bandpromo_theme_normalize_asset_ids(array $assetIds): array
{
    require_once __DIR__ . '/asset-registry.php';

    $defaults = bandpromo_theme_default_document()['asset_ids'];
    $normalized = [];
    foreach ($defaults as $key => $_default) {
        $value = trim((string) ($assetIds[$key] ?? ''));
        if ($value !== '' && !bandpromo_asset_is_asset_id($value)) {
            $value = '';
        }
        $normalized[$key] = $value;
    }

    return $normalized;
}

/**
 * Resolve a public media URL for a brand shell slot.
 * Prefer asset_ids → Visual/SFX delivery. No media/special dual-read.
 */
function bandpromo_theme_resolve_shell_slot_url(string $root, array $document, string $slotKey): string
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';

    $slotKey = trim($slotKey);
    $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
    $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
    $assetId = trim((string) ($assetIds[$slotKey] ?? ''));
    $pathFallback = trim((string) ($assets[$slotKey] ?? ''));

    if ($assetId === '' && $pathFallback !== '') {
        $assetId = bandpromo_theme_lookup_asset_id_for_path($root, $pathFallback);
    }

    if ($assetId !== '' && bandpromo_asset_is_asset_id($assetId)) {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($asset)) {
            $kind = (string) ($asset['kind'] ?? '');
            $mediaType = (string) ($asset['media_type'] ?? '');

            if ($kind === 'sfx' || ($kind === 'visual' && $mediaType === 'audio')) {
                $playUrl = bandpromo_sfx_resolve_play_url($root, $asset);
                if ($playUrl !== '') {
                    return $playUrl;
                }

                return '';
            }

            if ($kind === 'visual' && $mediaType === 'video') {
                return bandpromo_visual_resolve_url($root, $assetId, 'standard-stream', '', false);
            }

            if ($kind === 'visual') {
                return bandpromo_visual_resolve_url($root, $assetId, 'card', '', false);
            }
        }
    }

    // Path dual-read only when it is already a delivery URL (materialized assets[]).
    if ($pathFallback !== '' && (
        str_starts_with($pathFallback, '/media/visual/delivery/')
        || str_starts_with($pathFallback, '/media/sfx/optimal/')
        || preg_match('#^https?://#i', $pathFallback) === 1
    )) {
        return $pathFallback;
    }

    return '';
}

/**
 * Visual role for a brand shell slot.
 */
function bandpromo_theme_shell_slot_visual_role(string $slotKey): string
{
    $map = [
        'logo' => 'brand-logo',
        'poster' => 'brand-portrait',
        'background_image' => 'shell-background-image',
        'background_video' => 'shell-background-video',
    ];

    return $map[trim($slotKey)] ?? 'unassigned';
}

/**
 * Clone one shell media slot into a new Visual/SFX ast_* master owned by $brandId.
 *
 * @return array{path:string,asset_id:string}
 */
function bandpromo_theme_clone_asset_file(string $root, string $brandId, string $assetKey, string $sourcePath, string $sourceAssetId = ''): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/media-library-state.php';
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';
    require_once __DIR__ . '/media-delivery-helpers.php';

    $brandId = bandpromo_brand_canonical_id($brandId);
    $assetKey = trim($assetKey);
    $sourcePath = trim($sourcePath);
    $sourceAssetId = trim($sourceAssetId);
    $empty = ['path' => '', 'asset_id' => ''];

    $sourceAsset = null;
    if ($sourceAssetId !== '' && bandpromo_asset_is_asset_id($sourceAssetId)) {
        $sourceAsset = bandpromo_asset_lookup_by_id($root, $sourceAssetId);
    }
    if (!is_array($sourceAsset) && $sourcePath !== '') {
        $lookedUp = bandpromo_theme_lookup_asset_id_for_path($root, $sourcePath);
        if ($lookedUp !== '') {
            $sourceAsset = bandpromo_asset_lookup_by_id($root, $lookedUp);
        }
    }

    $absolute = null;
    if (is_array($sourceAsset)) {
        $kind = (string) ($sourceAsset['kind'] ?? '');
        if ($kind === 'visual') {
            $absolute = bandpromo_visual_working_path($root, $sourceAsset);
            if ($absolute === '' || !is_file($absolute)) {
                $relocated = bandpromo_visual_relocate_original($root, $sourceAsset);
                $absolute = !empty($relocated['ok']) ? (string) $relocated['path'] : '';
            }
        } elseif ($kind === 'sfx') {
            $masterName = basename((string) ($sourceAsset['master_filename'] ?? ''));
            if ($masterName !== '') {
                $candidate = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterName;
                if (is_file($candidate)) {
                    $absolute = $candidate;
                }
            }
            if ($absolute === null || $absolute === '' || !is_file($absolute)) {
                $originalName = basename((string) ($sourceAsset['original_filename'] ?? ''));
                if ($originalName !== '') {
                    $candidate = bandpromo_sfx_original_dir($root) . DIRECTORY_SEPARATOR . $originalName;
                    if (is_file($candidate)) {
                        $absolute = $candidate;
                    }
                }
            }
        }
    }
    if (($absolute === null || $absolute === '' || !is_file((string) $absolute)) && $sourcePath !== '') {
        $absolute = bandpromo_theme_resolve_media_absolute_path($root, $sourcePath);
        if ($absolute === null) {
            $seedFallbacks = bandpromo_theme_shell_seed_fallback_paths()[$assetKey] ?? [];
            $healed = bandpromo_theme_heal_media_path($root, $sourcePath, $seedFallbacks);
            if ($healed !== '') {
                $absolute = bandpromo_theme_resolve_media_absolute_path($root, $healed);
            }
        }
    }
    if ($absolute === null || $absolute === '' || !is_file((string) $absolute)) {
        return $empty;
    }

    $ext = strtolower((string) pathinfo((string) $absolute, PATHINFO_EXTENSION));
    if ($ext === '') {
        return $empty;
    }

    $isAudioSlot = in_array($assetKey, ['welcome_audio', 'loggedin_audio'], true)
        || in_array($ext, ['flac', 'mp3', 'wav', 'ogg', 'm4a'], true);

    $safeBrand = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($brandId)) ?: 'brand';
    $safeKey = preg_replace('/[^a-z0-9_]+/', '_', strtolower($assetKey)) ?: 'asset';
    $base = $safeBrand . '_' . $safeKey;
    $destName = $base . '.' . $ext;
    $suffix = 2;

    if ($isAudioSlot) {
        bandpromo_sfx_ensure_tier_dirs($root);
        $destDir = bandpromo_sfx_original_dir($root);
        while (is_file($destDir . DIRECTORY_SEPARATOR . $destName)) {
            $destName = $base . '-' . $suffix . '.' . $ext;
            $suffix++;
            if ($suffix > 100) {
                throw new RuntimeException('Could not allocate a unique brand SFX filename.');
            }
        }
        $destAbsolute = $destDir . DIRECTORY_SEPARATOR . $destName;
        if (!copy((string) $absolute, $destAbsolute)) {
            throw new RuntimeException('Could not clone brand SFX: ' . $assetKey);
        }
        $registered = bandpromo_asset_register_sfx($root, $destName, [
            'brand_id' => $brandId,
            'build_delivery' => true,
        ]);
        $assetId = trim((string) ($registered['id'] ?? ''));
        bandpromo_media_files_index_sync_file($root, 'sfx', basename((string) ($registered['master_filename'] ?? $destName)));
        $playUrl = is_array($registered) ? bandpromo_sfx_resolve_play_url($root, $registered) : '';

        return ['path' => $playUrl, 'asset_id' => $assetId];
    }

    bandpromo_visual_ensure_tier_dirs($root);
    $destDir = bandpromo_visual_unified_original_dir($root);
    while (is_file($destDir . DIRECTORY_SEPARATOR . $destName)) {
        $destName = $base . '-' . $suffix . '.' . $ext;
        $suffix++;
        if ($suffix > 100) {
            throw new RuntimeException('Could not allocate a unique brand visual filename.');
        }
    }
    $destAbsolute = $destDir . DIRECTORY_SEPARATOR . $destName;
    if (!copy((string) $absolute, $destAbsolute)) {
        throw new RuntimeException('Could not clone brand visual: ' . $assetKey);
    }

    $mediaType = in_array($ext, ['mp4', 'webm', 'mov', 'mkv'], true) ? 'video' : 'image';
    $role = bandpromo_theme_shell_slot_visual_role($assetKey);
    $registered = bandpromo_asset_register_visual($root, $destName, 'special', $mediaType, [
        'brand_id' => $brandId,
        'role' => $role,
        'has_alpha' => $ext === 'png',
    ]);
    $assetId = trim((string) ($registered['id'] ?? ''));
    $listing = basename((string) ($registered['master_filename'] ?? $destName));
    // Brand visuals live in the Visual pool index (illustrations|video); Brand tab filters by role.
    $indexTarget = $mediaType === 'video' ? 'video' : 'illustrations';
    bandpromo_media_files_index_sync_file($root, $indexTarget, $listing);
    bandpromo_media_files_index_sync_file($root, 'special', $listing);
    $delivery = $assetId !== ''
        ? bandpromo_visual_resolve_url($root, $assetId, $mediaType === 'video' ? 'standard-stream' : 'card', '', false)
        : '';

    return ['path' => $delivery, 'asset_id' => $assetId];
}

/**
 * Clone shell media into new Visual/SFX masters for a duplicated brand.
 *
 * @return array{assets: array<string,string>, asset_ids: array<string,string>}
 */
function bandpromo_theme_clone_assets_for_brand(string $root, array $assets, string $brandId, array $sourceAssetIds = []): array
{
    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '') {
        throw new InvalidArgumentException('Brand id is required to clone assets.');
    }

    $defaults = bandpromo_theme_default_document()['assets'];
    $clonedAssets = [];
    $clonedIds = [];
    foreach ($defaults as $key => $_defaultValue) {
        $result = bandpromo_theme_clone_asset_file(
            $root,
            $brandId,
            $key,
            trim((string) ($assets[$key] ?? '')),
            trim((string) ($sourceAssetIds[$key] ?? ''))
        );
        $clonedAssets[$key] = (string) ($result['path'] ?? '');
        $clonedIds[$key] = (string) ($result['asset_id'] ?? '');
    }

    return [
        'assets' => bandpromo_theme_normalize_assets($clonedAssets),
        'asset_ids' => bandpromo_theme_normalize_asset_ids($clonedIds),
    ];
}

/**
 * Look up a registry asset id for a shell media web path (best-effort backfill).
 */
function bandpromo_theme_lookup_asset_id_for_path(string $root, string $webPath): string
{
    require_once __DIR__ . '/asset-registry.php';

    $webPath = trim(str_replace('\\', '/', $webPath));
    if ($webPath === '' || preg_match('#^https?://#i', $webPath) === 1) {
        return '';
    }
    if ($webPath[0] !== '/') {
        $webPath = '/' . $webPath;
    }

    if (preg_match('#^/media/visual/delivery/(ast_[0-9A-HJKMNP-TV-Z]{20})/#i', $webPath, $matches) === 1) {
        // Asset ids are Crockford Base32; never strtolower the whole id (breaks registry lookup).
        $assetId = 'ast_' . strtoupper(substr($matches[1], 4));
        if (bandpromo_asset_lookup_by_id($root, $assetId) !== null) {
            return $assetId;
        }
    }

    $basename = basename($webPath);
    if ($basename === '' || $basename === '.' || $basename === '..') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $basename)
        ?? bandpromo_asset_lookup_by_master_filename($root, $basename);
    if (!is_array($asset)) {
        return '';
    }

    $kind = (string) ($asset['kind'] ?? '');
    if ($kind !== 'visual' && $kind !== 'sfx') {
        return '';
    }

    return (string) ($asset['id'] ?? '');
}

/**
 * Refresh assets[] URLs from asset_ids when resolution succeeds (keeps dual-read in sync).
 *
 * @return array{document: array, changed: bool}
 */
function bandpromo_theme_materialize_asset_urls(string $root, array $document): array
{
    $assetIds = bandpromo_theme_normalize_asset_ids(
        is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : []
    );
    $assets = bandpromo_theme_normalize_assets(
        is_array($document['assets'] ?? null) ? $document['assets'] : []
    );
    $changed = false;

    foreach ($assetIds as $key => $assetId) {
        if ($assetId === '') {
            continue;
        }
        $resolved = bandpromo_theme_resolve_shell_slot_url($root, [
            'asset_ids' => $assetIds,
            'assets' => $assets,
        ], $key);
        if ($resolved === '' || $resolved === ($assets[$key] ?? '')) {
            continue;
        }
        $assets[$key] = $resolved;
        $changed = true;
    }

    $document['asset_ids'] = $assetIds;
    $document['assets'] = $assets;

    return ['document' => $document, 'changed' => $changed];
}

/**
 * Resolve a web media path to an absolute install file under /media/.
 */
function bandpromo_theme_resolve_media_absolute_path(string $root, string $webPath): ?string
{
    $webPath = trim(str_replace('\\', '/', $webPath));
    if ($webPath === '' || preg_match('#^https?://#i', $webPath) === 1) {
        return null;
    }
    if ($webPath[0] !== '/') {
        $webPath = '/' . $webPath;
    }
    if (strpos($webPath, '/media/') !== 0) {
        return null;
    }
    if (strpos($webPath, '..') !== false) {
        return null;
    }

    $absolute = rtrim($root, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
    if (!is_file($absolute)) {
        return null;
    }

    return $absolute;
}

/**
 * Bundled demo shell files required for the locked default brand and for duplicates.
 *
 * @return array<string, list<string>>
 */
function bandpromo_theme_shell_seed_fallback_paths(): array
{
    return [
        'logo' => [
            '/media/special/bandPromo_logo.png',
            '/media/special/bandPromo_logo_simplified.png',
        ],
        'poster' => [
            '/media/special/bandPromo_cover.png',
            '/media/special/bandPromo_share.png',
        ],
        'background_image' => [
            '/media/special/bandPromo_background.png',
        ],
        'background_video' => [
            '/media/special/bandPromo_background.mp4',
        ],
        'welcome_audio' => [
            '/media/sfx/original/bandPromo_welcome.flac',
            '/media/special/bandPromo_welcome.flac',
        ],
        'loggedin_audio' => [
            '/media/sfx/original/bandPromo_loggedin.flac',
            '/media/special/bandPromo_loggedin.flac',
        ],
    ];
}

function bandpromo_theme_normalize_media_web_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    return '/' . ltrim($path, '/');
}

function bandpromo_theme_first_existing_media_path(string $root, array $candidates): string
{
    foreach ($candidates as $candidate) {
        $normalized = bandpromo_theme_normalize_media_web_path((string) $candidate);
        if ($normalized !== '' && bandpromo_theme_resolve_media_absolute_path($root, $normalized) !== null) {
            return $normalized;
        }
    }

    return '';
}

/**
 * If $path is empty or missing on disk, return the first existing fallback.
 */
function bandpromo_theme_heal_media_path(string $root, string $path, array $fallbacks): string
{
    $normalized = bandpromo_theme_normalize_media_web_path($path);
    if ($normalized !== '' && bandpromo_theme_resolve_media_absolute_path($root, $normalized) !== null) {
        return $normalized;
    }

    return bandpromo_theme_first_existing_media_path($root, $fallbacks);
}

/**
 * Repair brand shell slots and synced config when demo seed files were deleted or never extracted.
 * Locked bandPromo Default cannot be edited in the UI — installs must self-heal.
 *
 * @return list<string> human-readable repair notes
 */
function bandpromo_theme_heal_install_shell_media(string $root): array
{
    $notes = [];
    $fallbacks = bandpromo_theme_shell_seed_fallback_paths();
    $activeId = bandpromo_brand_canonical_id(bandpromo_theme_active_id($root));

    foreach (bandpromo_theme_registry_entries($root) as $entry) {
        $brandId = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($brandId === '') {
            continue;
        }
        $path = bandpromo_theme_document_path($root, $brandId);
        if (!is_file($path)) {
            continue;
        }
        try {
            $document = bandpromo_theme_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            continue;
        }
        $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
        $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
        $changed = false;
        $isLockedDefault = !empty($document['locked']) || !empty($document['system'])
            || $brandId === BANDPROMO_BRAND_DEFAULT_ID;
        foreach ($fallbacks as $slot => $candidates) {
            $before = bandpromo_theme_normalize_media_web_path((string) ($assets[$slot] ?? ''));
            if ($before === '' && !$isLockedDefault) {
                // Do not invent demo media for operator brands that left a slot empty.
                continue;
            }
            $after = bandpromo_theme_heal_media_path($root, $before, $candidates);
            if ($after !== '' && $after !== $before) {
                $assets[$slot] = $after;
                $changed = true;
                $notes[] = 'Brand ' . $brandId . ' ' . $slot . ': '
                    . ($before !== '' ? $before : '(empty)') . ' → ' . $after;
            } elseif ($before !== '' && $after === '') {
                $notes[] = 'Brand ' . $brandId . ' ' . $slot . ': still missing on disk: ' . $before;
            }
            $pathForId = bandpromo_theme_normalize_media_web_path((string) ($assets[$slot] ?? $after));
            $currentId = trim((string) ($assetIds[$slot] ?? ''));
            if ($currentId === '' && $pathForId !== '') {
                $found = bandpromo_theme_lookup_asset_id_for_path($root, $pathForId);
                if ($found !== '') {
                    $assetIds[$slot] = $found;
                    $changed = true;
                    $notes[] = 'Brand ' . $brandId . ' ' . $slot . ' asset_id → ' . $found;
                }
            }
        }
        if ($changed) {
            $document['assets'] = $assets;
            $document['asset_ids'] = $assetIds;
            bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);
            if ($brandId === $activeId) {
                bandpromo_theme_sync_assets_to_config($root, $document);
            }
        }
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        return $notes;
    }

    $configMap = [
        'social.share_image' => $fallbacks['poster'],
        'release.social.share_image' => $fallbacks['poster'],
        'release.brand.poster' => $fallbacks['poster'],
        'release.theme.cover' => $fallbacks['poster'],
        'media.cover' => $fallbacks['poster'],
        'media.logo' => $fallbacks['logo'],
        'install.brand.logo' => $fallbacks['logo'],
        'install.theme.logo' => $fallbacks['logo'],
    ];
    $configChanged = false;
    foreach ($configMap as $dotted => $candidates) {
        $before = bandpromo_theme_normalize_media_web_path((string) bandpromo_config_get_path($config, $dotted, ''));
        $after = bandpromo_theme_heal_media_path($root, $before, $candidates);
        if ($after !== '' && $after !== $before) {
            bandpromo_config_set_path($config, $dotted, $after);
            $configChanged = true;
            $notes[] = 'web-config ' . $dotted . ': '
                . ($before !== '' ? $before : '(empty)') . ' → ' . $after;
        }
    }
    if ($configChanged) {
        bandpromo_json_write_file($configPath, $config);
    }

    return $notes;
}

function bandpromo_theme_normalize_document(array $input, ?string $expectedId = null): array
{
    $id = bandpromo_brand_canonical_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9_-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $locked = !empty($input['locked']);
    $system = !empty($input['system']);
    if ($id === BANDPROMO_BRAND_DEFAULT_ID) {
        // Platform default stays system-owned; lock is sticky from the document
        // (forced on remote / after PRP import). Do not re-force locked here —
        // localhost may unlock for PRP authoring.
        $system = true;
    }

    $releaseId = trim((string) ($input['release_id'] ?? ''));
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        $releaseId = '';
    }

    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => $id,
        'title' => $title,
        'system' => $system,
        'locked' => $locked,
        'release_id' => $releaseId,
        'mood' => bandpromo_brand_normalize_narrative_field($input['mood'] ?? '', 500),
        'keywords' => array_values(array_filter(array_map(
            static fn(mixed $item): string => bandpromo_brand_normalize_narrative_field($item, 80),
            is_array($input['keywords'] ?? null)
                ? $input['keywords']
                : (preg_split('/\s*,\s*/', (string) ($input['keywords'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ))),
        'tone_notes' => bandpromo_brand_normalize_narrative_field($input['tone_notes'] ?? '', 2000),
        'tokens' => bandpromo_theme_normalize_tokens(is_array($input['tokens'] ?? null) ? $input['tokens'] : []),
        'assets' => bandpromo_theme_normalize_assets(is_array($input['assets'] ?? null) ? $input['assets'] : []),
        'asset_ids' => bandpromo_theme_normalize_asset_ids(is_array($input['asset_ids'] ?? null) ? $input['asset_ids'] : []),
        'player' => bandpromo_theme_normalize_player($input),
    ];
}

function bandpromo_theme_write_registry(string $root, array $registry): void
{
    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_registry_path($root), $registry)) {
        throw new RuntimeException('Could not write data/brands/registry.json');
    }
}

function bandpromo_theme_registry_list_key(array $registry): string
{
    if (isset($registry['brands']) && is_array($registry['brands'])) {
        return 'brands';
    }

    return 'themes';
}

function bandpromo_theme_load_registry(string $root): array
{
    bandpromo_theme_registry_ensure_dir($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_theme_registry_path($root));
    if ($decoded === null) {
        $default = bandpromo_theme_default_registry();
        bandpromo_theme_write_registry($root, $default);

        return $default;
    }

    $listKey = bandpromo_theme_registry_list_key($decoded);
    if (!isset($decoded[$listKey]) || !is_array($decoded[$listKey])) {
        $decoded[$listKey] = [];
    }
    if ($listKey === 'themes' && !isset($decoded['brands'])) {
        $decoded['brands'] = $decoded['themes'];
        unset($decoded['themes']);
    }
    foreach ($decoded['brands'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $decoded['brands'][$index]['id'] = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
    }
    if ($listKey === 'themes') {
        bandpromo_theme_write_registry($root, $decoded);
    }

    return $decoded;
}

/**
 * @param array{allow_locked?: bool} $options
 */
function bandpromo_theme_write_document(string $root, array $document, array $options = []): void
{
    $document = bandpromo_theme_normalize_document($document, (string) ($document['id'] ?? ''));
    if (!empty($document['locked']) && empty($options['allow_locked']) && !bandpromo_brand_may_edit_document($document)) {
        throw new RuntimeException('Brand is locked and cannot be edited.');
    }

    $materialized = bandpromo_theme_materialize_asset_urls($root, $document);
    $document = $materialized['document'];

    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_document_path($root, $document['id']), $document)) {
        throw new RuntimeException('Could not write theme document.');
    }
}

function bandpromo_brand_is_platform_default(string $brandId): bool
{
    return bandpromo_brand_canonical_id($brandId) === BANDPROMO_BRAND_DEFAULT_ID;
}

/**
 * Locked brands are editable only when unlocked, except platform default on localhost
 * (PRP source edits for the demo campaign identity).
 */
function bandpromo_brand_may_edit_document(array $document): bool
{
    if (empty($document['locked'])) {
        return true;
    }

    if (!bandpromo_brand_is_platform_default((string) ($document['id'] ?? ''))) {
        return false;
    }

    require_once __DIR__ . '/https.php';

    return bandpromo_is_local_dev_host();
}

function bandpromo_brand_may_change_lock(string $brandId): bool
{
    if (!bandpromo_brand_is_platform_default($brandId)) {
        return true;
    }

    require_once __DIR__ . '/https.php';

    return bandpromo_is_local_dev_host();
}

/**
 * Keep remote installs locked if the platform default brand is already present.
 */
function bandpromo_brand_enforce_platform_default_lock(string $root): void
{
    $path = bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    if (!is_file($path)) {
        return;
    }

    require_once __DIR__ . '/https.php';
    $requestHost = bandpromo_request_host_without_port();
    if ($requestHost === '' || bandpromo_is_local_dev_host()) {
        return;
    }

    try {
        $document = bandpromo_theme_load_document($root, BANDPROMO_BRAND_DEFAULT_ID);
    } catch (Throwable $throwable) {
        return;
    }

    if (!empty($document['locked'])) {
        return;
    }

    $document['locked'] = true;
    bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);
}

function bandpromo_brand_lock_platform_default_after_import(string $root): void
{
    $path = bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    if (!is_file($path)) {
        return;
    }

    try {
        $document = bandpromo_theme_load_document($root, BANDPROMO_BRAND_DEFAULT_ID);
    } catch (Throwable $throwable) {
        return;
    }

    $document['locked'] = true;
    bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);
}

/**
 * Enrich a brand document for admin API responses (capabilities, not persisted).
 *
 * @return array<string, mixed>
 */
function bandpromo_theme_api_document(array $document): array
{
    $id = bandpromo_brand_canonical_id((string) ($document['id'] ?? ''));
    $document['id'] = bandpromo_brand_legacy_theme_id($id);
    $document['platform_default'] = bandpromo_brand_is_platform_default($id);
    $document['can_edit'] = bandpromo_brand_may_edit_document($document);
    $document['can_change_lock'] = bandpromo_brand_may_change_lock($id);

    return $document;
}

function bandpromo_theme_load_document(string $root, string $themeId): array
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $path = bandpromo_theme_document_path($root, $themeId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing brand document: data/brands/' . $themeId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid brand document: data/brands/' . $themeId . '.json');
    }

    return bandpromo_theme_normalize_document($decoded, $themeId);
}

function bandpromo_theme_registry_entries(string $root): array
{
    $registry = bandpromo_theme_load_registry($root);
    $entries = $registry['brands'] ?? [];
    foreach ($entries as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $canonical = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        $entries[$index]['id'] = bandpromo_brand_legacy_theme_id($canonical);
        if ($canonical === BANDPROMO_BRAND_DEFAULT_ID) {
            $entries[$index]['title'] = (string) ($entry['title'] ?? 'bandPromo Default');
        }
        $locked = !empty($entry['locked']);
        try {
            $document = bandpromo_theme_load_document($root, $canonical);
            $locked = !empty($document['locked']);
            $entries[$index]['title'] = (string) ($document['title'] ?? $entries[$index]['title']);
        } catch (Throwable $throwable) {
            // Keep registry fields when the document is missing.
        }
        $entries[$index]['locked'] = $locked;
        $entries[$index]['platform_default'] = $canonical === BANDPROMO_BRAND_DEFAULT_ID;
        $entries[$index]['can_edit'] = bandpromo_brand_may_edit_document([
            'id' => $canonical,
            'locked' => $locked,
        ]);
        $entries[$index]['can_change_lock'] = bandpromo_brand_may_change_lock($canonical);
    }

    return $entries;
}

function bandpromo_theme_registry_entry(string $root, string $themeId): ?array
{
    $canonical = bandpromo_brand_canonical_id($themeId);
    foreach (bandpromo_theme_load_registry($root)['brands'] ?? [] as $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === $canonical) {
            $entry['id'] = bandpromo_brand_legacy_theme_id($canonical);

            return $entry;
        }
    }

    return null;
}

function bandpromo_theme_assets_from_config(array $config): array
{
    $poster = (string) bandpromo_config_get_nonempty_value($config, 'release.brand.poster', '');
    if ($poster === '') {
        $poster = (string) bandpromo_config_get_nonempty_value($config, 'release.theme.cover', '');
    }
    if ($poster === '') {
        $poster = (string) bandpromo_config_get_nonempty_value($config, 'media.cover', '');
    }

    return [
        'logo' => (string) bandpromo_config_get_nonempty_value($config, 'install.brand.logo', ''),
        'poster' => $poster,
        'background_image' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_image', ''),
        'background_video' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_video', ''),
        'welcome_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.welcome_audio', ''),
        'loggedin_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.loggedin_audio', ''),
    ];
}

function bandpromo_theme_sync_assets_to_config(string $root, array $document): void
{
    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        return;
    }

    $materialized = bandpromo_theme_materialize_asset_urls($root, $document);
    $document = $materialized['document'];
    $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
    $map = [
        'logo' => ['install.brand.logo', 'install.theme.logo', 'media.logo'],
        'poster' => [
            'release.brand.poster',
            'release.social.share_image',
            'social.share_image',
            'release.theme.cover',
            'media.cover',
        ],
        'background_image' => ['release.theme.background_image', 'media.background_image'],
        'background_video' => ['release.theme.background_video', 'media.background_video'],
        'welcome_audio' => ['install.theme.welcome_audio', 'media.welcome_audio'],
        'loggedin_audio' => ['install.theme.loggedin_audio', 'media.loggedin_audio'],
    ];

    foreach ($map as $assetKey => $paths) {
        $value = bandpromo_theme_resolve_shell_slot_url($root, $document, $assetKey);
        if ($value === '') {
            $value = trim((string) ($assets[$assetKey] ?? ''));
        }
        foreach ($paths as $path) {
            bandpromo_config_set_path($config, $path, $value);
        }
    }

    bandpromo_json_write_file($configPath, $config);
}

function bandpromo_theme_active_id(string $root): string
{
    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    $brandId = bandpromo_brand_canonical_id((string) bandpromo_config_get_path($config, 'install.pointers.active_brand_id', ''));
    if ($brandId === '') {
        $brandId = bandpromo_brand_canonical_id((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', ''));
    }
    if ($brandId !== '' && is_file(bandpromo_theme_document_path($root, $brandId))) {
        return bandpromo_brand_legacy_theme_id($brandId);
    }

    return BANDPROMO_THEME_DEFAULT_ID;
}

function bandpromo_brand_active_id(string $root): string
{
    return bandpromo_brand_canonical_id(bandpromo_theme_active_id($root));
}

function bandpromo_theme_set_active_id(string $root, string $themeId): void
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '' || !is_file(bandpromo_theme_document_path($root, $themeId))) {
        throw new InvalidArgumentException('Unknown brand.');
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        throw new RuntimeException('Missing web-config.json');
    }

    $legacyId = bandpromo_brand_legacy_theme_id($themeId);
    bandpromo_config_set_path($config, 'install.pointers.active_brand_id', $themeId);
    bandpromo_config_set_path($config, 'install.pointers.active_theme_id', $legacyId);
    if (!bandpromo_json_write_file($configPath, $config)) {
        throw new RuntimeException('Could not update base brand pointer.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    bandpromo_theme_sync_assets_to_config($root, $document);
}

function bandpromo_theme_load_active_document(string $root): array
{
    bandpromo_theme_ensure_seeded($root);

    return bandpromo_theme_load_document($root, bandpromo_theme_active_id($root));
}

function bandpromo_theme_token_value(array $document, string $path): string
{
    $segments = explode('.', $path);
    $value = $document['tokens'] ?? [];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return '';
        }
        $value = $value[$segment];
    }

    return is_scalar($value) ? trim((string) $value) : '';
}

function bandpromo_theme_render_css(string $root): string
{
    try {
        $document = bandpromo_theme_load_active_document($root);
    } catch (Throwable $throwable) {
        return '';
    }

    $rules = [];
    foreach (bandpromo_theme_css_variable_map() as $tokenPath => $cssVar) {
        $value = bandpromo_theme_token_value($document, $tokenPath);
        if ($value !== '') {
            $rules[] = $cssVar . ':' . $value;
        }
    }

    $fontBase = bandpromo_theme_token_value($document, 'typography.font_family_base');
    if ($fontBase !== '') {
        $rules[] = 'font-family:' . $fontBase;
        $rules[] = '--brand-font-body:' . $fontBase;
    }
    $fontHeading = bandpromo_theme_token_value($document, 'typography.font_family_heading');
    $rules[] = '--brand-font-heading:' . ($fontHeading !== '' ? $fontHeading : 'var(--brand-font-body)');

    foreach (bandpromo_theme_derived_alpha_css_variables() as $cssVar => $value) {
        $rules[] = $cssVar . ':' . $value;
    }

    foreach (bandpromo_theme_effects_css_variables($document) as $cssVar => $value) {
        $rules[] = $cssVar . ':' . $value;
    }

    if ($rules === []) {
        return '';
    }

    return '<style id="bandpromo-theme-vars">:root{' . implode(';', $rules) . ';}</style>' . "\n";
}

function bandpromo_theme_migrate_from_themes(string $root): void
{
    $brandsRoot = bandpromo_theme_storage_root($root);
    $legacyRoot = bandpromo_theme_legacy_storage_root($root);
    if (is_file($brandsRoot . DIRECTORY_SEPARATOR . 'registry.json')) {
        return;
    }
    if (!is_dir($legacyRoot)) {
        return;
    }

    bandpromo_theme_registry_ensure_dir($root);
    $legacyRegistryPath = $legacyRoot . DIRECTORY_SEPARATOR . 'registry.json';
    if (is_file($legacyRegistryPath)) {
        $legacyRegistry = bandpromo_json_read_array_file($legacyRegistryPath) ?? [];
        $entries = is_array($legacyRegistry['themes'] ?? null) ? $legacyRegistry['themes'] : [];
        $brands = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry['id'] = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
            if ($entry['id'] === BANDPROMO_BRAND_DEFAULT_ID) {
                $entry['title'] = 'bandPromo Default';
            }
            $brands[] = $entry;
        }
        if ($brands === []) {
            $brands = bandpromo_theme_default_registry()['brands'];
        }
        bandpromo_theme_write_registry($root, [
            'version' => BANDPROMO_THEME_REGISTRY_VERSION,
            'brands' => $brands,
        ]);
    }

    foreach (glob($legacyRoot . DIRECTORY_SEPARATOR . '*.json') ?: [] as $legacyDocPath) {
        if (basename($legacyDocPath) === 'registry.json') {
            continue;
        }
        $stem = pathinfo($legacyDocPath, PATHINFO_FILENAME);
        $canonical = bandpromo_brand_canonical_id($stem);
        $target = bandpromo_theme_document_path($root, $canonical);
        if (is_file($target)) {
            continue;
        }
        $decoded = bandpromo_json_read_array_file($legacyDocPath);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['id'] = $canonical;
        bandpromo_json_write_file($target, bandpromo_theme_normalize_document($decoded, $canonical));
    }
}

function bandpromo_theme_migrate_from_config(string $root): void
{
    $defaultPath = bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    if (is_file($defaultPath)) {
        return;
    }

    $templatePath = bandpromo_theme_template_path($root, BANDPROMO_THEME_DEFAULT_ID);
    if (!is_file($templatePath)) {
        $templatePath = bandpromo_theme_template_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    }
    if (is_file($templatePath)) {
        $decoded = bandpromo_json_read_array_file($templatePath);
        $document = is_array($decoded)
            ? bandpromo_theme_normalize_document($decoded, BANDPROMO_BRAND_DEFAULT_ID)
            : bandpromo_theme_default_document();
    } else {
        $document = bandpromo_theme_default_document();
    }

    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    if ($config !== []) {
        $document['assets'] = bandpromo_theme_normalize_assets(
            array_merge($document['assets'], bandpromo_theme_assets_from_config($config))
        );
    }

    bandpromo_json_write_file($defaultPath, $document);

    $registry = bandpromo_theme_load_registry($root);
    $hasDefault = false;
    foreach ($registry['brands'] ?? [] as $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === BANDPROMO_BRAND_DEFAULT_ID) {
            $hasDefault = true;
            break;
        }
    }
    if (!$hasDefault) {
        $registry['brands'][] = [
            'id' => BANDPROMO_BRAND_DEFAULT_ID,
            'title' => 'bandPromo Default',
            'system' => true,
            'locked' => true,
            'sort_order' => 10,
        ];
        bandpromo_theme_write_registry($root, $registry);
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config !== []) {
        $changed = false;
        if (trim((string) bandpromo_config_get_path($config, 'install.pointers.active_brand_id', '')) === '') {
            bandpromo_config_set_path($config, 'install.pointers.active_brand_id', BANDPROMO_BRAND_DEFAULT_ID);
            $changed = true;
        }
        if (trim((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', '')) === '') {
            bandpromo_config_set_path($config, 'install.pointers.active_theme_id', BANDPROMO_THEME_DEFAULT_ID);
            $changed = true;
        }
        if ($changed) {
            bandpromo_json_write_file($configPath, $config);
        }
    }
}

function bandpromo_theme_ensure_seeded(string $root): void
{
    static $completed = [];
    if (!empty($completed[$root])) {
        return;
    }

    bandpromo_theme_migrate_from_themes($root);
    bandpromo_theme_registry_ensure_dir($root);
    if (!is_file(bandpromo_theme_registry_path($root))) {
        bandpromo_theme_write_registry($root, bandpromo_theme_default_registry());
    }

    if (!is_file(bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID))) {
        bandpromo_theme_migrate_from_config($root);
    }

    // Heal once per PHP process — shell path probes are costly on synced folders.
    bandpromo_theme_heal_install_shell_media($root);
    bandpromo_brand_enforce_platform_default_lock($root);
    $completed[$root] = true;
}

function bandpromo_theme_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'brand-copy';
    }

    return substr($slug, 0, 48);
}

function bandpromo_theme_propose_duplicate_title(string $sourceTitle): string
{
    $sourceTitle = trim($sourceTitle);
    if ($sourceTitle === '') {
        return 'Brand copy';
    }

    if (preg_match('/^(.+?)\s+copy(?:\s+(\d+))?$/iu', $sourceTitle, $matches) === 1) {
        $base = trim((string) ($matches[1] ?? ''));
        $number = isset($matches[2]) && $matches[2] !== '' ? ((int) $matches[2]) + 1 : 2;

        return $base . ' copy ' . $number;
    }

    return $sourceTitle . ' copy';
}

function bandpromo_theme_allocate_duplicate_id(string $root, string $title = ''): string
{
    // Title is operator-facing only; new brands get opaque brd_{ulid} storage ids.
    unset($title);
    bandpromo_theme_registry_ensure_dir($root);

    $existing = [];
    foreach (bandpromo_theme_registry_entries($root) as $entry) {
        $canonical = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($canonical !== '') {
            $existing[$canonical] = true;
        }
    }

    for ($attempt = 0; $attempt < 100; $attempt++) {
        $id = bandpromo_brand_canonical_id(bandpromo_generate_brand_id());
        if ($id === '' || $id === BANDPROMO_BRAND_DEFAULT_ID || $id === BANDPROMO_THEME_DEFAULT_ID) {
            continue;
        }
        if (!isset($existing[$id]) && !is_file(bandpromo_theme_document_path($root, $id))) {
            return $id;
        }
    }

    throw new RuntimeException('Could not allocate a unique brand id.');
}

function bandpromo_theme_duplicate(string $root, string $sourceId, string $newId, string $title = ''): array
{
    $sourceId = bandpromo_brand_canonical_id($sourceId);
    if ($sourceId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $source = bandpromo_theme_load_document($root, $sourceId);
    $duplicateTitle = trim($title) !== ''
        ? trim($title)
        : bandpromo_theme_propose_duplicate_title((string) ($source['title'] ?? $sourceId));

    if (trim($newId) === '') {
        $newId = bandpromo_theme_allocate_duplicate_id($root, $duplicateTitle);
    }

    $newId = bandpromo_brand_canonical_id($newId);
    if ($sourceId === '' || $newId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }
    if ($newId === BANDPROMO_BRAND_DEFAULT_ID) {
        throw new InvalidArgumentException('Reserved brand id.');
    }
    if (is_file(bandpromo_theme_document_path($root, $newId))) {
        throw new InvalidArgumentException('Brand id already exists.');
    }

    $duplicate = bandpromo_theme_normalize_document([
        'id' => $newId,
        'title' => $duplicateTitle,
        'system' => false,
        'locked' => false,
        'mood' => $source['mood'] ?? '',
        'keywords' => $source['keywords'] ?? [],
        'tone_notes' => $source['tone_notes'] ?? '',
        'tokens' => is_array($source['tokens'] ?? null) ? $source['tokens'] : [],
        'assets' => [],
        'asset_ids' => [],
        'player' => is_array($source['player'] ?? null) ? $source['player'] : [],
    ], $newId);

    // Clone into new Visual/SFX ast_* masters owned by the new brand (not media/special copies).
    $cloned = bandpromo_theme_clone_assets_for_brand(
        $root,
        is_array($source['assets'] ?? null) ? $source['assets'] : [],
        $newId,
        is_array($source['asset_ids'] ?? null) ? $source['asset_ids'] : []
    );
    $duplicate['assets'] = is_array($cloned['assets'] ?? null) ? $cloned['assets'] : [];
    $duplicate['asset_ids'] = is_array($cloned['asset_ids'] ?? null) ? $cloned['asset_ids'] : [];

    bandpromo_json_write_file(bandpromo_theme_document_path($root, $newId), $duplicate);

    $registry = bandpromo_theme_load_registry($root);
    $registry['brands'][] = [
        'id' => $newId,
        'title' => $duplicate['title'],
        'system' => false,
        'locked' => false,
        'sort_order' => 50,
    ];
    bandpromo_theme_write_registry($root, $registry);

    $duplicate['id'] = bandpromo_brand_legacy_theme_id($newId);

    return $duplicate;
}

function bandpromo_theme_update_title(string $root, string $themeId, string $title): array
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Brand id is required.');
    }

    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Brand name is required.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!bandpromo_brand_may_edit_document($document)) {
        throw new InvalidArgumentException('This brand is locked.');
    }

    $document['title'] = $title;
    bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);

    $registry = bandpromo_theme_load_registry($root);
    foreach ($registry['brands'] as $index => $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === $themeId) {
            $registry['brands'][$index]['title'] = $title;
            break;
        }
    }
    bandpromo_theme_write_registry($root, $registry);

    return bandpromo_theme_registry_entry($root, $themeId) ?? [];
}

function bandpromo_theme_delete(string $root, string $themeId): void
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '' || $themeId === BANDPROMO_BRAND_DEFAULT_ID) {
        throw new InvalidArgumentException('This brand cannot be deleted.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!empty($document['locked'])) {
        throw new InvalidArgumentException('This brand is locked.');
    }

    if (bandpromo_brand_active_id($root) === $themeId) {
        throw new InvalidArgumentException('Set another brand active before deleting this one.');
    }

    $registry = bandpromo_theme_load_registry($root);
    $before = count($registry['brands'] ?? []);
    $registry['brands'] = array_values(array_filter(
        $registry['brands'] ?? [],
        static fn(array $entry): bool => bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) !== $themeId
    ));
    if (count($registry['brands']) === $before) {
        throw new InvalidArgumentException('Unknown brand.');
    }

    bandpromo_theme_write_registry($root, $registry);

    $path = bandpromo_theme_document_path($root, $themeId);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not delete brand document.');
    }
}
