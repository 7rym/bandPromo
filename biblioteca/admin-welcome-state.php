<?php
declare(strict_types=1);

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/setup-state.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/page-registry.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/demo-catalog-state.php';
require_once __DIR__ . '/release-storage.php';

function bandpromo_admin_default_theme_display_version(?string $rawVersion): string
{
    $version = trim((string) $rawVersion);
    if ($version === '') {
        return '1.0';
    }

    if (preg_match('/^v\d+\.\d+\s+build\s+\d+$/i', $version)) {
        return '1.0';
    }

    return $version;
}

function bandpromo_admin_starter_pack_files_present(string $root): bool
{
    $representativePaths = [
        $root . '/media/special/bandPromo_cover.png',
        $root . '/media/special/bandPromo_share.png',
        $root . '/media/img/original/bandPromo_vocalist.png',
        $root . '/media/audio/original/bandPromo_the_very_first_song.flac',
        $root . '/media/audio/original/bandPromo_the_second_song.flac',
    ];

    foreach ($representativePaths as $path) {
        if (!is_file($path)) {
            return false;
        }
    }

    return true;
}

function bandpromo_admin_demo_content_installed(string $root): bool
{
    if (is_file($root . '/data/demo-release-package.json')) {
        return true;
    }

    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        $demoId = BANDPROMO_RELEASE_DEMO_ID;
    }
    if ($demoId !== '' && is_file(bandpromo_release_document_path($root, $demoId))) {
        return true;
    }

    return bandpromo_admin_get_default_theme_status($root) !== null
        || bandpromo_admin_starter_pack_files_present($root);
}

function bandpromo_admin_latest_full_build_success(string $root): bool
{
    $logFile = $root . '/log/build.log';
    $lockFile = $root . '/log/build.lock';

    if (!is_file($logFile) || is_file($lockFile)) {
        return false;
    }

    $content = @file_get_contents($logFile);
    if (!is_string($content) || trim($content) === '') {
        return false;
    }

    return preg_match('/\nEXITCODE:0\s*$/', $content) === 1;
}

function bandpromo_admin_runtime_files_present(string $root): bool
{
    $requiredFiles = [
        $root . '/web-config.json',
        $root . '/data/terces',
    ];

    foreach ($requiredFiles as $path) {
        if (!is_file($path)) {
            return false;
        }
    }

    return bandpromo_page_runtime_present($root, 'faq');
}

function bandpromo_admin_write_inferred_starter_pack_marker(string $root): bool
{
    $markerPath = $root . '/data/default-theme-package.json';
    if (is_file($markerPath) || !bandpromo_admin_starter_pack_files_present($root)) {
        return false;
    }

    $payload = [
        'version' => 'local-source-tree',
        'display_version' => '1.0',
        'sha256' => '',
        'package_file' => '',
        'package_url' => '',
        'release_tag' => 'local-source-tree',
        'paths' => [
            'media/special/bandPromo_cover.png',
            'media/special/bandPromo_share.png',
            'media/img/original/bandPromo_vocalist.png',
            'media/audio/original/bandPromo_the_very_first_song.flac',
            'media/audio/original/bandPromo_the_second_song.flac',
        ],
        'installed_at_utc' => gmdate('c'),
        'source' => 'inferred-from-local-files',
    ];

    return @file_put_contents(
        $markerPath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;
}

function bandpromo_admin_get_default_theme_status(string $root): ?array
{
    $markerPath = $root . '/data/default-theme-package.json';
    if (!is_file($markerPath)) {
        return null;
    }

    $defaultThemeMarker = json_decode((string) file_get_contents($markerPath), true);
    if (!is_array($defaultThemeMarker)) {
        return null;
    }

    $installedAt = trim((string) ($defaultThemeMarker['installed_at_utc'] ?? ''));
    $installedLabel = '';
    if ($installedAt !== '') {
        try {
            $installedLabel = (new DateTimeImmutable($installedAt))->format('j M Y, H:i');
        } catch (Throwable $throwable) {
            $installedLabel = $installedAt;
        }
    }

    $displayVersion = trim((string) ($defaultThemeMarker['display_version'] ?? ''));
    if ($displayVersion === '') {
        $displayVersion = bandpromo_admin_default_theme_display_version((string) ($defaultThemeMarker['version'] ?? ''));
    }

    return [
        'version' => trim((string) ($defaultThemeMarker['version'] ?? '')),
        'display_version' => $displayVersion,
        'installed_at' => $installedLabel,
        'path_count' => is_array($defaultThemeMarker['paths'] ?? null) ? count($defaultThemeMarker['paths']) : 0,
    ];
}

function bandpromo_admin_build_welcome_checklist(string $root): array
{
    require_once __DIR__ . '/publish-status-helpers.php';

    $hasOperatorCampaign = bandpromo_demo_catalog_install_has_operator_content($root);
    $starterPackInstalled = bandpromo_admin_demo_content_installed($root);
    $starterPackDetail = $starterPackInstalled
        ? 'The Demo Portable Release Package is installed on this site.'
        : 'The demo catalog is not fully available yet. Finish setup or run a full build so bandPromo can install it.';

    $pagesPublished =
        bandpromo_page_runtime_present($root, 'faq')
        && !bandpromo_page_matches_starter_template($root, 'faq');

    $fullBuildSucceeded = bandpromo_admin_latest_full_build_success($root);
    $installationRunning = bandpromo_is_setup_complete() && bandpromo_admin_runtime_files_present($root);
    $publishStatus = bandpromo_publish_status_summary($root);
    $missingDeliveryCount = (int) ($publishStatus['summary']['missing_delivery'] ?? 0);
    $deliveryReady = $missingDeliveryCount === 0;
    // Install + starter + one successful full build = first-run done. Later missing delivery is live ops.
    if ($installationRunning && $starterPackInstalled && $fullBuildSucceeded) {
        bandpromo_admin_latch_core_setup($root);
    }
    $setupLatched = bandpromo_admin_is_core_setup_latched($root);
    $deliverySeverity = $setupLatched ? 'nonblocking' : 'blocking';

    return [
        [
            'label' => 'This installation is up and running',
            'action_label' => 'Finish the installation',
            'severity' => 'blocking',
            'complete' => $installationRunning,
            'detail' => $installationRunning
                ? 'Setup is complete and the required runtime files are available.'
                : 'Setup is incomplete or required runtime files are still missing.',
            'href' => '?tab=docs&doc_scope=operator',
            'next' => 'Finish setup and make sure the required runtime files are in place before treating the install as live.',
        ],
        [
            'label' => 'Demo catalog installed',
            'action_label' => 'Install the demo catalog',
            'severity' => 'blocking',
            'complete' => $starterPackInstalled,
            'detail' => $starterPackDetail,
            'href' => '?tab=system&stab=deliverables',
            'next' => 'Open System → Deliverables and run a full build so bandPromo can install the demo catalog from the Demo Portable Release Package.',
        ],
        [
            'label' => 'The full build process ran successfully',
            'action_label' => 'Run the full build',
            'severity' => 'blocking',
            'complete' => $fullBuildSucceeded,
            'detail' => $fullBuildSucceeded
                ? 'The latest full build finished successfully.'
                : 'No successful full build has been recorded yet, or the last full build failed.',
            'href' => '?tab=system&stab=deliverables',
            'next' => 'Open System → Deliverables and run a full build until it completes successfully.',
        ],
        [
            'label' => 'Delivery files are created and ready',
            'action_label' => 'Build delivery files',
            'severity' => $deliverySeverity,
            'complete' => $deliveryReady,
            'detail' => $deliveryReady
                ? 'Publish-ready delivery files exist for catalogued audio and artwork.'
                : $missingDeliveryCount . ' catalogued audio file' . ($missingDeliveryCount === 1 ? '' : 's') . ' still lack streaming MP3 delivery.',
            'href' => '?tab=system&stab=deliverables',
            'next' => 'Open System → Deliverables and rebuild all deliverables so listeners stream MP3s instead of large originals.',
        ],
        [
            'label' => $hasOperatorCampaign ? 'Your own catalog is present' : 'Your own catalog is not present yet',
            'action_label' => 'Add your own catalog',
            'severity' => 'nonblocking',
            'complete' => $hasOperatorCampaign,
            'detail' => $hasOperatorCampaign
                ? 'An operator release with a track is exposed on a playlist.'
                : 'The demo catalog is the only listening campaign so far. Add your own release with a track, then a playlist that plays it.',
            'href' => '?tab=content&cntab=release',
            'next' => 'Create a release with at least one track and a playlist that exposes that track.',
        ],
        [
            'label' => 'FAQ is personalized',
            'action_label' => 'Personalize the FAQ',
            'severity' => 'nonblocking',
            'complete' => $pagesPublished,
            'detail' => $pagesPublished
                ? 'The required FAQ page no longer looks like the shipped starter copy.'
                : 'FAQ still looks like starter content, so the login info lightbox is not fully personalized yet.',
            'href' => '?tab=content&cntab=pages&page=faq',
            'next' => 'Open Content → Pages and replace the starter FAQ with your own login info copy.',
        ],
    ];
}

function bandpromo_admin_core_setup_latch_path(string $root): string
{
    return $root . '/data/install/welcome-core-setup-complete.json';
}

function bandpromo_admin_is_core_setup_latched(string $root): bool
{
    return is_file(bandpromo_admin_core_setup_latch_path($root));
}

function bandpromo_admin_latch_core_setup(string $root): void
{
    $path = bandpromo_admin_core_setup_latch_path($root);
    if (is_file($path)) {
        return;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    file_put_contents(
        $path,
        json_encode([
            'completed_at' => gmdate('c'),
            'note' => 'Core Welcome setup completed once. Later missing delivery files must not reopen first-install mode.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function bandpromo_admin_core_setup_complete(array $checklist): bool
{
    foreach ($checklist as $item) {
        if (($item['severity'] ?? '') !== 'blocking') {
            continue;
        }

        if (empty($item['complete'])) {
            return false;
        }
    }

    return true;
}

function bandpromo_admin_build_post_setup_suggestions(string $root): array
{
    $suggestions = [];

    if (!bandpromo_demo_catalog_install_has_operator_content($root)) {
        $suggestions[] = [
            'label' => 'Add your own catalog',
            'href' => '?tab=content&cntab=release',
            'severity' => 'nonblocking',
            'description' => 'Create a release with at least one track and a playlist that plays it when you are ready to move beyond the demo catalog.',
        ];
    }

    if (
        bandpromo_page_runtime_present($root, 'faq')
        && bandpromo_page_matches_starter_template($root, 'faq')
    ) {
        $suggestions[] = [
            'label' => 'Personalize the FAQ',
            'href' => '?tab=content&cntab=pages&page=faq',
            'severity' => 'nonblocking',
            'description' => 'Replace the shipped login-info copy with your own support details and house rules.',
        ];
    }

    $suggestions[] = [
        'label' => 'Try the Pages editor',
        'href' => '?tab=content&cntab=pages',
        'severity' => 'nonblocking',
        'description' => 'Add Bio, Tour, News, or other optional pages when you want more than the player shell.',
    ];

    $suggestions[] = [
        'label' => 'Import a backup',
        'href' => '?tab=system&stab=backup',
        'severity' => 'nonblocking',
        'description' => 'If you already run bandPromo elsewhere, import a site backup to migrate content into this install.',
    ];

    return $suggestions;
}

function bandpromo_admin_build_incomplete_setup_steps(array $checklist): array
{
    $nextSteps = [];

    foreach ($checklist as $item) {
        if (!empty($item['complete'])) {
            continue;
        }

        if (($item['severity'] ?? '') !== 'blocking') {
            continue;
        }

        $nextSteps[] = [
            'label' => $item['action_label'],
            'href' => $item['href'],
            'severity' => (string) ($item['severity'] ?? 'blocking'),
            'description' => $item['next'],
        ];
    }

    if ($nextSteps !== []) {
        return $nextSteps;
    }

    foreach ($checklist as $item) {
        if (!empty($item['complete'])) {
            continue;
        }

        $nextSteps[] = [
            'label' => $item['action_label'],
            'href' => $item['href'],
            'severity' => (string) ($item['severity'] ?? 'nonblocking'),
            'description' => $item['next'],
        ];
    }

    return $nextSteps;
}

/**
 * Fast path for Notifications / badge wording — never rebuilds the first-install checklist.
 */
function bandpromo_admin_welcome_setup_is_complete(string $root): bool
{
    return bandpromo_admin_is_core_setup_latched($root);
}

function bandpromo_admin_welcome_state(string $root): array
{
    bandpromo_demo_catalog_restore_if_operator_campaign_gone($root);
    bandpromo_admin_write_inferred_starter_pack_marker($root);

    try {
        bandpromo_brand_ensure_operator_brand($root);
    } catch (Throwable $throwable) {
        // Welcome should still render if brand auto-provision fails.
    }

    // Once first-install is done, never recompute the checklist (Dashboard speed + no reopen).
    if (bandpromo_admin_is_core_setup_latched($root)) {
        $nextSteps = bandpromo_admin_build_post_setup_suggestions($root);
        if ($nextSteps === []) {
            $nextSteps[] = [
                'label' => 'Documentation',
                'href' => '?tab=docs&doc_scope=operator',
                'severity' => 'nonblocking',
                'description' => 'You are in a good place. Use Documentation when you want the deeper explanations and workflow guides.',
            ];
        }

        return [
            'checklist' => [],
            'completed_count' => 0,
            'total_count' => 0,
            'setup_complete' => true,
            'setup_latched' => true,
            'next_steps' => $nextSteps,
        ];
    }

    $checklist = bandpromo_admin_build_welcome_checklist($root);
    $checklistComplete = bandpromo_admin_core_setup_complete($checklist);
    if ($checklistComplete || bandpromo_admin_is_core_setup_latched($root)) {
        bandpromo_admin_latch_core_setup($root);

        $nextSteps = bandpromo_admin_build_post_setup_suggestions($root);
        if ($nextSteps === []) {
            $nextSteps[] = [
                'label' => 'Documentation',
                'href' => '?tab=docs&doc_scope=operator',
                'severity' => 'nonblocking',
                'description' => 'You are in a good place. Use Documentation when you want the deeper explanations and workflow guides.',
            ];
        }

        return [
            'checklist' => [],
            'completed_count' => count($checklist),
            'total_count' => count($checklist),
            'setup_complete' => true,
            'setup_latched' => true,
            'next_steps' => $nextSteps,
        ];
    }

    $completedCount = 0;
    foreach ($checklist as $item) {
        if (!empty($item['complete'])) {
            $completedCount++;
        }
    }

    $nextSteps = bandpromo_admin_build_incomplete_setup_steps($checklist);
    if ($nextSteps === []) {
        $nextSteps[] = [
            'label' => 'Documentation',
            'href' => '?tab=docs&doc_scope=operator',
            'severity' => 'nonblocking',
            'description' => 'You are in a good place. Use Documentation when you want the deeper explanations and workflow guides.',
        ];
    }

    return [
        'checklist' => $checklist,
        'completed_count' => $completedCount,
        'total_count' => count($checklist),
        'setup_complete' => false,
        'setup_latched' => false,
        'next_steps' => $nextSteps,
    ];
}
