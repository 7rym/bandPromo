<?php
declare(strict_types=1);

/**
 * CLI publish prep — reconcile masters, heal brands, optional Demo PCF ensure.
 * Run by scripts/publish_prep.py before the Python publish stages.
 * Not bound by web max_execution_time.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "publish-prep-cli.php is CLI-only.\n");
    exit(1);
}

$root = dirname(__DIR__);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/campaign-package.php';
require_once __DIR__ . '/publish-preflight-helpers.php';
require_once __DIR__ . '/job-stop.php';

$metaPath = trim((string) (getenv('BANDPROMO_BUILD_META') ?: ''));
if ($metaPath === '' || !is_file($metaPath)) {
    $metaPath = $root . '/log/build.meta.json';
}
$meta = [];
if (is_file($metaPath)) {
    $decoded = json_decode((string) @file_get_contents($metaPath), true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}

$mode = strtolower(trim((string) ($meta['mode'] ?? 'full')));
if (!in_array($mode, ['full', 'optimize'], true)) {
    $mode = 'full';
}
$ensureDemo = !empty($meta['ensure_demo']);
$jobKey = $mode === 'optimize' ? 'optimize' : 'build';

$logLine = static function (string $line): void {
    fwrite(STDOUT, rtrim($line) . "\n");
    fflush(STDOUT);
};

$touchMeta = static function (string $stage, string $message) use ($metaPath, $meta): void {
    $meta['stage'] = $stage;
    $meta['message'] = $message;
    $meta['updated_at'] = time();
    $meta['heartbeat_at'] = time();
    @file_put_contents(
        $metaPath,
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
};

if (bandpromo_job_stop_requested($root, $jobKey)) {
    $logLine('[prep] Stop requested before prep — exiting.');
    fwrite(STDOUT, "PREP_STOPPED\n");
    exit(0);
}

$touchMeta('prep', 'Preparing your site for publish…');
$logLine('[prep] Preparing your site for publish…');

if ($mode === 'full') {
    bandpromo_run_publish_preflight($root, static function (string $line) use ($logLine): void {
        $logLine(rtrim($line));
    });
}

$logLine('[prep] Starting publish preparation…');

if ($ensureDemo) {
    try {
        $logLine('[prep] Preparing Demo PCF download/import (progress appears below)…');
        $touchMeta('prep', 'Preparing Demo campaign package…');
        $package = bandpromo_ensure_demo_campaign_package(
            $root,
            BANDPROMO_RELEASE_MANIFEST_URL,
            static function (string $message) use ($logLine): void {
                $logLine($message);
            }
        );
        require_once __DIR__ . '/demo-catalog-state.php';
        $ensuredReleaseId = '';
        if (is_array($package)) {
            $ensuredReleaseId = (string) ($package['release_id'] ?? '');
        }
        bandpromo_demo_campaign_ensure_preferences($root, $ensuredReleaseId);
        try {
            bandpromo_ensure_install_icons(
                $root,
                BANDPROMO_RELEASE_MANIFEST_URL,
                static function (string $message) use ($logLine): void {
                    $logLine($message);
                }
            );
        } catch (Throwable $iconThrowable) {
            $logLine('[icons] Warning: ' . $iconThrowable->getMessage());
        }
        if (is_array($package)) {
            $demoState = !empty($package['installed']) ? 'imported' : 'already present / local seed';
            $demoVersion = (string) ($package['version'] ?? $package['source'] ?? 'n/a');
            $logLine('[prep] Demo PCF: ' . $demoState . ' (' . $demoVersion . ')');
        }
    } catch (Throwable $throwable) {
        $logLine('[demo release] Failed: ' . $throwable->getMessage());
        fwrite(STDERR, 'Demo PCF prep failed: ' . $throwable->getMessage() . "\n");
        exit(1);
    }
} else {
    require_once __DIR__ . '/demo-catalog-state.php';
    bandpromo_demo_campaign_ensure_preferences($root);
    $logLine('[prep] Skipping Demo PCF ensure (Publish uses content already on this host).');
}

if (bandpromo_job_stop_requested($root, $jobKey)) {
    $logLine('[prep] Stop requested — exiting after Demo step.');
    fwrite(STDOUT, "PREP_STOPPED\n");
    exit(0);
}

try {
    require_once __DIR__ . '/brand-storage.php';
    $touchMeta('prep', 'Checking brand and shell media…');
    bandpromo_brand_ensure_seeded($root);
    foreach (bandpromo_brand_heal_install_shell_media($root) as $note) {
        $logLine('[shell media] ' . $note);
    }
    foreach (bandpromo_brand_heal_empty_libraries($root) as $note) {
        $logLine('[brand library] ' . $note);
    }
} catch (Throwable $throwable) {
    $logLine('[shell media] Heal skipped: ' . $throwable->getMessage());
}

try {
    require_once __DIR__ . '/visual-master-helpers.php';
    $touchMeta('prep', 'Checking visual intake…');
    $legacyRelocate = bandpromo_visual_relocate_all_legacy_originals($root);
    if (!empty($legacyRelocate['ran'])) {
        $logLine('[visual intake] ' . (string) ($legacyRelocate['message'] ?? 'Legacy Visual intake check finished.'));
        foreach (($legacyRelocate['warnings'] ?? []) as $warning) {
            if (!is_string($warning) || trim($warning) === '') {
                continue;
            }
            $logLine('[visual intake] Warning: ' . $warning);
        }
    }
} catch (Throwable $throwable) {
    $logLine('[visual intake] Legacy relocate skipped: ' . $throwable->getMessage());
}

if (bandpromo_job_stop_requested($root, $jobKey)) {
    $logLine('[prep] Stop requested — exiting before audio masters.');
    fwrite(STDOUT, "PREP_STOPPED\n");
    exit(0);
}

try {
    require_once __DIR__ . '/asset-registry.php';
    $touchMeta('prep', 'Checking audio masters for Files…');
    $logLine('[prep] Checking audio masters for Files → Audio…');
    $audioMasterReconcile = bandpromo_reconcile_uncatalogued_audio_masters($root);
    $audioRecovered = (int) ($audioMasterReconcile['changed'] ?? 0);
    if ($audioRecovered > 0) {
        $logLine('[audio masters] Re-registered ' . $audioRecovered . ' uncatalogued master(s) into Files → Audio.');
        $shown = 0;
        foreach (($audioMasterReconcile['fixed'] ?? []) as $fixedName) {
            if (!is_string($fixedName) || trim($fixedName) === '') {
                continue;
            }
            if ($shown < 12) {
                $logLine('[audio masters] + ' . $fixedName);
            }
            $shown++;
        }
        if ($shown > 12) {
            $logLine('[audio masters] … and ' . ($shown - 12) . ' more (omitted from log).');
        }
    } elseif (!empty($audioMasterReconcile['index_rebuilt'])) {
        $logLine('[audio masters] Rebuilt Files → Audio index from registry (stale pool listing).');
    } else {
        $logLine('[audio masters] No uncatalogued masters to recover.');
    }
    foreach (($audioMasterReconcile['failed'] ?? []) as $failure) {
        if (!is_array($failure)) {
            continue;
        }
        $failName = trim((string) ($failure['filename'] ?? 'audio'));
        $failError = trim((string) ($failure['error'] ?? 'Could not register'));
        $logLine('[audio masters] Failed ' . $failName . ': ' . $failError);
    }
} catch (Throwable $throwable) {
    $logLine('[audio masters] Reconcile skipped: ' . $throwable->getMessage());
}

if (bandpromo_job_stop_requested($root, $jobKey)) {
    $logLine('[prep] Stop requested — exiting before audio uploads.');
    fwrite(STDOUT, "PREP_STOPPED\n");
    exit(0);
}

try {
    require_once __DIR__ . '/asset-registry.php';
    $touchMeta('prep', 'Finishing uploads that never registered…');
    $logLine('[prep] Checking uploads still waiting to register…');
    $originalReconcile = bandpromo_reconcile_uncatalogued_audio_originals($root);
    $origFixed = (int) ($originalReconcile['changed'] ?? 0);
    if ($origFixed > 0) {
        $logLine('[audio uploads] Registered ' . $origFixed . ' waiting upload(s) into the catalogue.');
        $shown = 0;
        foreach (($originalReconcile['fixed'] ?? []) as $fixedName) {
            if (!is_string($fixedName) || trim($fixedName) === '') {
                continue;
            }
            if ($shown < 12) {
                $logLine('[audio uploads] + ' . $fixedName);
            }
            $shown++;
        }
        if ($shown > 12) {
            $logLine('[audio uploads] … and ' . ($shown - 12) . ' more (omitted from log).');
        }
    } else {
        $logLine('[audio uploads] No waiting uploads to register.');
    }
    foreach (($originalReconcile['failed'] ?? []) as $failure) {
        if (!is_array($failure)) {
            continue;
        }
        $logLine(
            '[audio uploads] Could not register '
            . (string) ($failure['filename'] ?? '?')
            . ': '
            . (string) ($failure['error'] ?? ($failure['warning'] ?? 'unknown'))
        );
    }
} catch (Throwable $throwable) {
    $logLine('[audio uploads] Reconcile skipped: ' . $throwable->getMessage());
}

$logLine('[prep] Checking visual masters…');
$touchMeta('prep', 'Checking visual masters…');

try {
    require_once __DIR__ . '/asset-registry.php';
    $masterReconcile = bandpromo_reconcile_uncatalogued_visual_masters($root);
    $recovered = (int) ($masterReconcile['changed'] ?? 0);
    if ($recovered > 0) {
        $logLine('[visual masters] Re-registered ' . $recovered . ' uncatalogued master(s) into the Visual pool.');
        $shown = 0;
        foreach (($masterReconcile['fixed'] ?? []) as $fixedName) {
            if (!is_string($fixedName) || trim($fixedName) === '') {
                continue;
            }
            if ($shown < 12) {
                $logLine('[visual masters] + ' . $fixedName);
            }
            $shown++;
        }
        if ($shown > 12) {
            $logLine('[visual masters] … and ' . ($shown - 12) . ' more (omitted from log).');
        }
    } elseif (!empty($masterReconcile['index_rebuilt'])) {
        $logLine('[visual masters] Rebuilt Files → Visual index from registry (stale pool listing).');
    } else {
        $logLine('[visual masters] No uncatalogued masters to recover.');
    }
    foreach (($masterReconcile['failed'] ?? []) as $failure) {
        if (!is_array($failure)) {
            continue;
        }
        $logLine(
            '[visual masters] Failed '
            . (string) ($failure['filename'] ?? '?')
            . ': '
            . (string) ($failure['error'] ?? 'unknown')
        );
    }
} catch (Throwable $throwable) {
    $logLine('[visual masters] Master reconcile skipped: ' . $throwable->getMessage());
}

$touchMeta('prep', 'Preparation finished — starting publish stages…');
$logLine('[prep] Preparation finished.');
fwrite(STDOUT, "PREP_OK\n");
exit(0);
