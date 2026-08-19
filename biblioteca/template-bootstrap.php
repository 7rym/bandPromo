<?php
/**
 * Template bootstrap utilities.
 *
 * Ensures required runtime files are seeded from tracked templates.
 * Missing or invalid templates are treated as setup/build errors.
 */

require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/gallery-storage.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/campaign-ownership-helpers.php';

function bandpromo_template_map(): array {
    $root = dirname(__DIR__);
    $runtimeTemplates = $root . '/biblioteca/templates/runtime';
    $denyAll = $runtimeTemplates . '/deny-all.htaccess';

    return [
        [
            'template' => $root . '/biblioteca/templates/web-config.template.json',
            'target' => $root . '/web-config.json',
            'kind' => 'json',
        ],
        [
            'template' => $runtimeTemplates . '/root.htaccess',
            'target' => $root . '/.htaccess',
            'kind' => 'text',
        ],
        [
            'template' => $runtimeTemplates . '/user.ini',
            'target' => $root . '/.user.ini',
            'kind' => 'text',
        ],
        [
            'template' => $runtimeTemplates . '/play.htaccess',
            'target' => $root . '/play/.htaccess',
            'kind' => 'text',
        ],
        [
            'template' => $denyAll,
            'target' => $root . '/data/.htaccess',
            'kind' => 'text',
        ],
        [
            'template' => $denyAll,
            'target' => $root . '/log/.htaccess',
            'kind' => 'text',
        ],
        [
            'template' => $denyAll,
            'target' => $root . '/backups/.htaccess',
            'kind' => 'text',
        ],
        [
            'template' => $runtimeTemplates . '/media.htaccess',
            'target' => $root . '/media/.htaccess',
            'kind' => 'text',
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
        bandpromo_asset_registry_ensure_migrated($root, true);
        bandpromo_campaign_ensure_seeded($root);
        bandpromo_playlist_ensure_seeded($root);
        bandpromo_gallery_ensure_seeded($root);
        bandpromo_brand_ensure_seeded($root);
        bandpromo_campaign_ownership_migrate($root);
        require_once __DIR__ . '/install-migrations.php';
        bandpromo_install_migrations_run_after_update($root);
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
    }

    return $errors;
}
