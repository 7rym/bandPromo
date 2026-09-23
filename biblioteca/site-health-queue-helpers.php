<?php
declare(strict_types=1);

/**
 * Start Site health from PHP (no HTTP round-trip).
 * Used after PCF/PBF import so the host builds player-ready files via Treat.
 */

/**
 * Deep-link to System → Status → Site health.
 */
function bandpromo_site_health_status_href(): string
{
    return '?tab=system&stab=deliverables#siteHealthCard';
}

/**
 * Treatments safe to auto-apply after Portable Campaign/Brand import.
 * Builds register + delivery + playlists/chrome; skips Review-only destructive work
 * (dedupe delete, Ready archive prune).
 *
 * @return list<string>
 */
function bandpromo_site_health_post_import_treatment_ids(): array
{
    return [
        'audio_register_in_place',
        'audio_fill_display_from_tags',
        'audio_extract_covers',
        'visual_register_in_place',
        'sfx_register_in_place',
        'orphan_home_stamp',
        'listener_delivery',
        'sfx_delivery',
        'media_janitor_prune',
        'storage_package_prune',
        'container_links',
        'files_index_rebuild',
        'playlists',
        'site_chrome',
    ];
}

/**
 * @param list<string> $treatmentIds
 * @param list<string> $findingIds
 */
function bandpromo_site_health_write_treat_selection(
    string $root,
    array $treatmentIds,
    array $findingIds = []
): bool {
    $dataDir = $root . '/data';
    if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        return false;
    }

    $clean = [];
    foreach ($treatmentIds as $tid) {
        $tid = trim((string) $tid);
        if ($tid !== '' && !in_array($tid, $clean, true)) {
            $clean[] = $tid;
        }
    }
    $findings = [];
    foreach ($findingIds as $fid) {
        $fid = trim((string) $fid);
        if ($fid !== '') {
            $findings[] = $fid;
        }
    }

    $path = $dataDir . '/site-health-treat-selection.json';
    $payload = [
        'treatment_ids' => $clean,
        'finding_ids' => $findings,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json . "\n") === false) {
        return false;
    }
    if (is_file($path)) {
        @unlink($path);
    }
    if (!@rename($tmp, $path)) {
        $ok = @file_put_contents($path, $json . "\n") !== false;
        @unlink($tmp);

        return $ok;
    }

    return true;
}

/**
 * @param array{
 *   mode?: string,
 *   actor?: string,
 *   treatment_ids?: list<string>,
 *   finding_ids?: list<string>
 * } $options
 * @return array{
 *   ok: bool,
 *   started: bool,
 *   running?: bool,
 *   error?: string,
 *   mode?: string,
 *   run_id?: string,
 *   status_href?: string
 * }
 */
function bandpromo_site_health_try_start(string $root, array $options = []): array
{
    require_once __DIR__ . '/build-launcher.php';
    require_once __DIR__ . '/build-required.php';
    require_once __DIR__ . '/light-build-tasks.php';
    require_once __DIR__ . '/auto-build-tasks.php';
    require_once __DIR__ . '/job-stop.php';

    $mode = strtolower(trim((string) ($options['mode'] ?? 'treat')));
    if (!in_array($mode, ['check', 'check_full', 'treat', 'force'], true)) {
        $mode = 'treat';
    }

    $statusHref = bandpromo_site_health_status_href();
    $scriptMap = [
        'check' => $root . '/scripts/siteHealthCheck.py',
        'check_full' => $root . '/scripts/siteHealthCheckFull.py',
        'treat' => $root . '/scripts/siteHealthTreat.py',
        'force' => $root . '/scripts/siteHealthForce.py',
    ];
    $script = $scriptMap[$mode];
    $logDir = $root . '/log';
    $lockFile = $logDir . '/site-health.lock';
    $metaFile = $logDir . '/site-health.meta.json';
    $runnerLogFile = $logDir . '/site-health.runner.log';
    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $actor = trim((string) ($options['actor'] ?? 'system'));

    if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not create log directory',
            'mode' => $mode,
            'status_href' => $statusHref,
        ];
    }

    if (!is_file($script)) {
        return [
            'ok' => false,
            'started' => false,
            'error' => 'Site health script missing',
            'mode' => $mode,
            'status_href' => $statusHref,
        ];
    }

    if (is_file($lockFile)) {
        $lockAge = time() - (int) @filemtime($lockFile);
        if ($lockAge < 7200) {
            return [
                'ok' => false,
                'started' => false,
                'running' => true,
                'error' => 'Site health is already running',
                'mode' => $mode,
                'status_href' => $statusHref,
            ];
        }
        @unlink($lockFile);
    }

    if ($mode === 'treat') {
        $treatmentIds = $options['treatment_ids'] ?? null;
        if (is_array($treatmentIds)) {
            $findingIds = is_array($options['finding_ids'] ?? null)
                ? $options['finding_ids']
                : [];
            if ($treatmentIds === []) {
                return [
                    'ok' => false,
                    'started' => false,
                    'error' => 'No treatments selected for Site health Treat',
                    'mode' => $mode,
                    'status_href' => $statusHref,
                ];
            }
            if (!bandpromo_site_health_write_treat_selection($root, $treatmentIds, $findingIds)) {
                return [
                    'ok' => false,
                    'started' => false,
                    'error' => 'Could not store treatment selection',
                    'mode' => $mode,
                    'status_href' => $statusHref,
                ];
            }
        }
    }

    $python = bandpromo_resolve_python_interpreter();
    if ($python === '') {
        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not resolve Python for site health',
            'mode' => $mode,
            'status_href' => $statusHref,
        ];
    }

    @file_put_contents($runnerLogFile, '');
    $startedAt = time();
    $runId = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('health_', true);
    @file_put_contents($metaFile, json_encode([
        'run_id' => $runId,
        'status' => 'starting',
        'mode' => $mode,
        'started_at' => $startedAt,
        'updated_at' => $startedAt,
        'heartbeat_at' => $startedAt,
        'message' => 'Starting ' . $mode . '...',
        'actor' => $actor !== '' ? $actor : 'system',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    @file_put_contents($lockFile, 'running');
    bandpromo_job_stop_clear($root, 'site_health');
    bandpromo_clear_build_required_reasons(['package_update']);
    if (in_array($mode, ['check', 'check_full', 'treat', 'force'], true)) {
        bandpromo_heal_build_required_operator_nags();
    }

    $launch = bandpromo_build_launch_background(
        $python,
        $script,
        $runnerLogFile,
        $lockFile,
        $runId,
        $isWindows,
        null
    );

    if (empty($launch['started'])) {
        @unlink($lockFile);
        @unlink($metaFile);

        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not start site health runner',
            'mode' => $mode,
            'status_href' => $statusHref,
        ];
    }

    if (!empty($launch['pid'])) {
        @file_put_contents($lockFile, (string) $launch['pid']);
    }

    return [
        'ok' => true,
        'started' => true,
        'mode' => $mode,
        'run_id' => $runId,
        'status_href' => $statusHref,
    ];
}

/**
 * After PCF/PBF import: start Treat with post-import treatment selection.
 *
 * @return array{
 *   ok: bool,
 *   started: bool,
 *   running?: bool,
 *   error?: string,
 *   mode?: string,
 *   run_id?: string,
 *   status_href: string
 * }
 */
function bandpromo_site_health_try_start_post_import(string $root, string $actor): array
{
    return bandpromo_site_health_try_start($root, [
        'mode' => 'treat',
        'actor' => $actor !== '' ? $actor : 'package_import',
        'treatment_ids' => bandpromo_site_health_post_import_treatment_ids(),
    ]);
}
