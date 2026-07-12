<?php
declare(strict_types=1);

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/setup-state.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/page-registry.php';
require_once __DIR__ . '/brand-storage.php';

function bandpromo_admin_has_custom_brand(string $root): bool
{
    bandpromo_brand_ensure_seeded($root);
    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        if (empty($entry['system'])) {
            return true;
        }
    }

    return false;
}

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

function bandpromo_admin_normalize_text(string $value): string
{
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = strip_tags($decoded);
    $decoded = strtolower($decoded);
    $decoded = preg_replace('/\s+/u', ' ', $decoded);
    return trim((string) $decoded);
}

function bandpromo_admin_starter_pack_files_present(string $root): bool
{
    $representativePaths = [
        $root . '/media/special/bandPromo_share.png',
        $root . '/media/img/original/bandPromo_vocalist.png',
        $root . '/media/audio/original/bandPromo_the_very_first_song.flac',
    ];

    foreach ($representativePaths as $path) {
        if (!is_file($path)) {
            return false;
        }
    }

    return true;
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
            'media/special/bandPromo_share.png',
            'media/img/original/bandPromo_vocalist.png',
            'media/audio/original/bandPromo_the_very_first_song.flac',
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

    $defaultThemeStatus = bandpromo_admin_get_default_theme_status($root);
    $siteName = get_config('release.identity.title', 'Admin');
    $siteShortLabel = trim((string) get_config('release.identity.short_label', ''));
    $siteDescription = trim((string) get_config('release.identity.description', ''));
    $releaseCover = trim((string) get_config('release.theme.cover', ''));
    $installLogo = trim((string) get_config('install.theme.logo', ''));
    $supportUrl = trim((string) get_config('support.url', ''));
    $hasUploadedAudio = bandpromo_media_has_visible_user_uploads('audio');
    $hasUploadedIllustrations = bandpromo_media_has_visible_user_uploads('illustrations');
    $hasUploadedPhotos = bandpromo_media_has_visible_user_uploads('photos');
    $hasUploadedSpecial = bandpromo_media_has_visible_user_uploads('special');
    $hasUploadedVisualMedia = $hasUploadedIllustrations || $hasUploadedPhotos || $hasUploadedSpecial;
    $hasUploadedOwnMedia = $hasUploadedAudio || $hasUploadedVisualMedia;
    $starterPackInstalled = $defaultThemeStatus !== null || bandpromo_admin_starter_pack_files_present($root);
    $starterPackDetail = $starterPackInstalled
        ? 'Starter design pack ' . (($defaultThemeStatus['display_version'] ?? '') !== '' ? $defaultThemeStatus['display_version'] : '1.0') . ' is recorded for this installation.'
        : 'The starter design files are not fully available yet. Run a full build to install them.';

    $defaultIdentityNames = ['bandpromo demo site', 'your site name', 'bandpromo'];
    $defaultShortLabels = ['bandpromo', 'short name'];
    $defaultDescriptions = [
        '',
        'a demo site for the bandpromo publishing and marketing tool',
        'site description for manifest and meta tags',
    ];
    $identityNormalized = bandpromo_admin_normalize_text($siteName);
    $shortLabelNormalized = bandpromo_admin_normalize_text($siteShortLabel);
    $descriptionNormalized = bandpromo_admin_normalize_text($siteDescription);
    $coverPersonalized = $releaseCover !== '' && $releaseCover !== '/media/special/bandPromo_cover.png';
    $logoPersonalized = $installLogo !== '' && $installLogo !== '/media/special/bandPromo_logo.png';
    $installationPersonalized =
        !in_array($identityNormalized, $defaultIdentityNames, true)
        || !in_array($shortLabelNormalized, $defaultShortLabels, true)
        || !in_array($descriptionNormalized, $defaultDescriptions, true)
        || $coverPersonalized
        || $logoPersonalized
        || $supportUrl !== '';

    $pagesPublished =
        bandpromo_page_runtime_present($root, 'faq')
        && !bandpromo_page_matches_starter_template($root, 'faq');

    $fullBuildSucceeded = bandpromo_admin_latest_full_build_success($root);
    $installationRunning = bandpromo_is_setup_complete() && bandpromo_admin_runtime_files_present($root);
    $publishStatus = bandpromo_publish_status_summary($root);
    $missingDeliveryCount = (int) ($publishStatus['summary']['missing_delivery'] ?? 0);

    return [
        [
            'label' => 'Starter pack installed',
            'action_label' => 'Install the starter pack',
            'severity' => 'blocking',
            'complete' => $starterPackInstalled,
            'detail' => $starterPackDetail,
            'href' => '?tab=system&stab=publish',
            'next' => 'Open System → Publish and run a full build so bandPromo can install the starter design files.',
        ],
        [
            'label' => 'Installation personalized',
            'action_label' => 'Personalize the installation',
            'severity' => 'nonblocking',
            'complete' => $installationPersonalized,
            'detail' => $installationPersonalized
                ? 'The site identity or theme has been changed away from the shipped starter defaults.'
                : 'The site is still using the shipped demo identity or default branding values.',
            'href' => '?tab=settings',
            'next' => 'Open Settings and replace the starter name, description, branding, or support details with your own.',
        ],
        [
            'label' => 'Custom brand created',
            'action_label' => 'Duplicate the default brand',
            'severity' => 'nonblocking',
            'complete' => bandpromo_admin_has_custom_brand($root),
            'detail' => bandpromo_admin_has_custom_brand($root)
                ? 'At least one editable brand exists beyond the locked bandPromo Default seed.'
                : 'Only the locked bandPromo Default brand is present. Duplicate it to start your artist era identity.',
            'href' => '?tab=content&cntab=themes',
            'next' => 'Open Content → Brands, duplicate bandPromo Default, customize colors and narrative fields, then Set active.',
        ],
        [
            'label' => 'Your own media content is present',
            'action_label' => 'Upload your own media',
            'severity' => 'nonblocking',
            'complete' => $hasUploadedOwnMedia,
            'detail' => $hasUploadedOwnMedia
                ? 'Visible uploaded media is already present in this installation.'
                : 'No visible uploaded media has been detected yet.',
            'href' => '?tab=files&fpanel=audio',
            'next' => 'Open Files and upload your own audio and artwork so the site stops depending on starter media.',
        ],
        [
            'label' => 'Your own pages are published',
            'action_label' => 'Publish your own info',
            'severity' => 'nonblocking',
            'complete' => $pagesPublished,
            'detail' => $pagesPublished
                ? 'The required FAQ page no longer looks like the shipped starter copy.'
                : 'FAQ still looks like starter content, so the login info lightbox is not fully personalized yet.',
            'href' => '?tab=content&cntab=pages&page=faq',
            'next' => 'Open Content → Pages and replace the starter FAQ with your own login info copy. Add optional pages (Bio, Tour, News, …) as needed.',
        ],
        [
            'label' => 'The full build process ran successfully',
            'action_label' => 'Run the full build',
            'severity' => 'blocking',
            'complete' => $fullBuildSucceeded,
            'detail' => $fullBuildSucceeded
                ? 'The latest full build finished successfully.'
                : 'No successful full build has been recorded yet, or the last full build failed.',
            'href' => '?tab=system&stab=publish',
            'next' => 'Open System → Publish and run a full build until it completes successfully.',
        ],
        [
            'label' => 'Streaming MP3 delivery is ready',
            'action_label' => 'Build streaming delivery',
            'severity' => 'blocking',
            'complete' => $missingDeliveryCount === 0 || !$hasUploadedAudio,
            'detail' => $missingDeliveryCount === 0 || !$hasUploadedAudio
                ? 'Catalogued audio has publish-ready MP3 delivery files (or no uploaded audio yet).'
                : $missingDeliveryCount . ' catalogued audio file' . ($missingDeliveryCount === 1 ? '' : 's') . ' still lack streaming MP3 delivery.',
            'href' => '?tab=system&stab=publish',
            'next' => 'Open System → Publish and run Publish Build so listeners stream MP3s instead of large originals.',
        ],
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
    ];
}

function bandpromo_admin_welcome_state(string $root): array
{
    bandpromo_admin_write_inferred_starter_pack_marker($root);

    $checklist = bandpromo_admin_build_welcome_checklist($root);
    $completedCount = 0;
    $nextSteps = [];

    foreach ($checklist as $item) {
        if (!empty($item['complete'])) {
            $completedCount++;
            continue;
        }

        $nextSteps[] = [
            'label' => $item['action_label'],
            'href' => $item['href'],
            'severity' => (string) ($item['severity'] ?? 'nonblocking'),
            'description' => $item['next'],
        ];
    }

    if ($nextSteps === []) {
        $nextSteps[] = [
            'label' => 'Documentation',
            'href' => '?tab=docs&doc_scope=operator',
            'description' => 'You are in a good place. Use Documentation when you want the deeper explanations and workflow guides.',
        ];
    }

    $totalCount = count($checklist);

    return [
        'checklist' => $checklist,
        'completed_count' => $completedCount,
        'total_count' => $totalCount,
        'setup_complete' => $completedCount >= $totalCount,
        'next_steps' => $nextSteps,
    ];
}
