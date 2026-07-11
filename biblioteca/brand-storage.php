<?php
declare(strict_types=1);

/**
 * Brand containers (visual identity packages). Implementation currently lives in
 * theme-storage.php while the admin UI migrates from Themes to Brands.
 */
require_once __DIR__ . '/theme-storage.php';

function bandpromo_brand_storage_root(string $root): string
{
    return bandpromo_theme_storage_root($root);
}

function bandpromo_brand_registry_path(string $root): string
{
    return bandpromo_theme_registry_path($root);
}

function bandpromo_brand_document_path(string $root, string $brandId): string
{
    return bandpromo_theme_document_path($root, $brandId);
}

function bandpromo_brand_normalize_id(string $brandId): string
{
    return bandpromo_brand_canonical_id($brandId);
}

function bandpromo_brand_registry_ensure_dir(string $root): void
{
    bandpromo_theme_registry_ensure_dir($root);
}

function bandpromo_brand_ensure_seeded(string $root): void
{
    bandpromo_theme_ensure_seeded($root);
}

function bandpromo_brand_registry_entries(string $root): array
{
    bandpromo_theme_ensure_seeded($root);
    $entries = bandpromo_theme_load_registry($root)['brands'] ?? [];
    foreach ($entries as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entries[$index]['id'] = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
    }

    return $entries;
}

function bandpromo_brand_registry_entry(string $root, string $brandId): ?array
{
    $brandId = bandpromo_brand_canonical_id($brandId);
    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $brandId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_brand_load_document(string $root, string $brandId): array
{
    return bandpromo_theme_load_document($root, $brandId);
}

function bandpromo_brand_set_active_id(string $root, string $brandId): void
{
    bandpromo_theme_set_active_id($root, $brandId);
}

function bandpromo_brand_load_active_document(string $root): array
{
    return bandpromo_theme_load_active_document($root);
}

function bandpromo_brand_duplicate(string $root, string $sourceId, string $newId = '', string $title = ''): array
{
    return bandpromo_theme_duplicate($root, $sourceId, $newId, $title);
}

function bandpromo_brand_delete(string $root, string $brandId): void
{
    bandpromo_theme_delete($root, $brandId);
}

function bandpromo_brand_update_title(string $root, string $brandId, string $title): array
{
    return bandpromo_theme_update_title($root, $brandId, $title);
}

function bandpromo_brand_render_css(string $root): string
{
    return bandpromo_theme_render_css($root);
}

function bandpromo_brand_css_variables(array $document): array
{
    $vars = [];
    foreach (bandpromo_theme_css_variable_map() as $tokenPath => $cssVar) {
        $value = bandpromo_theme_token_value($document, $tokenPath);
        if ($value !== '') {
            $vars[$cssVar] = $value;
        }
    }

    $fontBase = bandpromo_theme_token_value($document, 'typography.font_family_base');
    if ($fontBase !== '') {
        $vars['font-family'] = $fontBase;
    }

    return $vars;
}

function bandpromo_brand_render_css_for_document(array $document): string
{
    $vars = bandpromo_brand_css_variables($document);
    if ($vars === []) {
        return '';
    }

    $rules = [];
    foreach ($vars as $key => $value) {
        if ($key === 'font-family') {
            $rules[] = 'font-family:' . $value;
            continue;
        }
        $rules[] = $key . ':' . $value;
    }

    foreach (bandpromo_theme_derived_alpha_css_variables() as $cssVar => $value) {
        $rules[] = $cssVar . ':' . $value;
    }

    return '<style id="bandpromo-theme-vars">:root{' . implode(';', $rules) . ';}</style>' . "\n";
}

function bandpromo_brand_render_css_for_id(string $root, string $brandId): string
{
    bandpromo_theme_ensure_seeded($root);
    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '' || !is_file(bandpromo_brand_document_path($root, $brandId))) {
        try {
            return bandpromo_brand_render_css_for_document(bandpromo_brand_load_active_document($root));
        } catch (Throwable $throwable) {
            return '';
        }
    }

    try {
        return bandpromo_brand_render_css_for_document(bandpromo_brand_load_document($root, $brandId));
    } catch (Throwable $throwable) {
        return '';
    }
}

function bandpromo_brand_player_styles_for_ids(string $root, array $brandIds): array
{
    $styles = [];
    bandpromo_theme_ensure_seeded($root);

    foreach ($brandIds as $brandId) {
        $brandId = bandpromo_brand_canonical_id((string) $brandId);
        if ($brandId === '' || isset($styles[$brandId])) {
            continue;
        }

        try {
            $document = bandpromo_brand_load_document($root, $brandId);
            $styles[$brandId] = [
                'id' => $brandId,
                'title' => (string) ($document['title'] ?? $brandId),
                'css_variables' => bandpromo_brand_css_variables($document),
            ];
        } catch (Throwable $throwable) {
            continue;
        }
    }

    return $styles;
}
