<?php
/**
 * Template bootstrap utilities.
 *
 * Ensures required runtime files are seeded from tracked templates.
 * Missing or invalid templates are treated as setup/build errors.
 */

require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/gallery-storage.php';
require_once __DIR__ . '/theme-storage.php';
require_once __DIR__ . '/release-ownership-helpers.php';

function bandpromo_template_map(): array {
    $root = dirname(__DIR__);
    return [
        [
            'template' => $root . '/biblioteca/templates/web-config.template.json',
            'target' => $root . '/web-config.json',
            'kind' => 'json',
        ],
    ];
}

/**
 * Seed runtime files from templates if missing.
 * Returns an array of human-readable validation/setup errors.
 */
function bandpromo_ensure_runtime_files_seeded(): array {
    $errors = [];
    $root = dirname(__DIR__);

    foreach (bandpromo_template_map() as $spec) {
        $template = $spec['template'];
        $target = $spec['target'];
        $kind = $spec['kind'];

        if (!file_exists($template)) {
            $errors[] = 'Missing template file: ' . $template;
            continue;
        }

        $templateContent = file_get_contents($template);
        if ($templateContent === false) {
            $errors[] = 'Could not read template file: ' . $template;
            continue;
        }

        if ($kind === 'json') {
            $decoded = json_decode($templateContent, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid JSON template: ' . $template;
                continue;
            }
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true)) {
            $errors[] = 'Could not create runtime directory: ' . $targetDir;
            continue;
        }

        if (!file_exists($target)) {
            if (file_put_contents($target, $templateContent) === false) {
                $errors[] = 'Could not write runtime file: ' . $target;
                continue;
            }
        }

        if ($kind === 'json') {
            $targetContent = file_get_contents($target);
            $targetDecoded = ($targetContent === false) ? null : json_decode($targetContent, true);
            if (!is_array($targetDecoded)) {
                $errors[] = 'Invalid runtime JSON file: ' . $target;
            }
        }
    }

    $errors = array_merge($errors, bandpromo_page_seed_all_if_missing($root));

    try {
        bandpromo_asset_registry_ensure_migrated($root);
        bandpromo_release_ensure_seeded($root);
        bandpromo_playlist_ensure_seeded($root);
        bandpromo_gallery_ensure_seeded($root);
        bandpromo_theme_ensure_seeded($root);
        bandpromo_release_ownership_migrate($root);
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
    }

    return $errors;
}
