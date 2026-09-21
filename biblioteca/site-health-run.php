<?php
declare(strict_types=1);

/**
 * Site health job control — Check / Treat / Force.
 * Launches scripts/siteHealth{Check,Treat,Force}.py via the existing build launcher.
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/job-stop.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$root = dirname(__DIR__);
$logDir = $root . '/log';
if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create log directory']);
    exit;
}

$raw = file_get_contents('php://input');
$request = [];
if (is_string($raw) && trim($raw) !== '') {
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        $request = $parsed;
    }
}

$csrfToken = isset($request['csrf_token']) ? (string) $request['csrf_token'] : '';
if ($csrfToken !== '' && !validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired or invalid request token. Refresh admin and try again.']);
    exit;
}

$mode = strtolower(trim((string) ($request['mode'] ?? 'check')));
if (!in_array($mode, ['check', 'check_full', 'treat', 'force'], true)) {
    $mode = 'check';
}

// Optional Apply filter: only run these treatment ids (from Review checkboxes).
$treatSelectionPath = $root . '/data/site-health-treat-selection.json';
if ($mode === 'treat') {
    $rawIds = $request['treatment_ids'] ?? null;
    $rawFindings = $request['finding_ids'] ?? null;
    $treatmentIds = [];
    $findingIds = [];
    if (is_array($rawIds)) {
        foreach ($rawIds as $tid) {
            $tid = trim((string) $tid);
            if ($tid !== '' && !in_array($tid, $treatmentIds, true)) {
                $treatmentIds[] = $tid;
            }
        }
    }
    if (is_array($rawFindings)) {
        foreach ($rawFindings as $fid) {
            $fid = trim((string) $fid);
            if ($fid !== '') {
                $findingIds[] = $fid;
            }
        }
    }
    if ($rawIds === null && $rawFindings === null) {
        // No selection payload — leave any prior file alone (CLI / legacy).
    } else {
        $dataDir = $root . '/data';
        if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not create data directory']);
            exit;
        }
        $payload = [
            'treatment_ids' => $treatmentIds,
            'finding_ids' => $findingIds,
        ];
        $tmp = $treatSelectionPath . '.tmp';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not store treatment selection']);
            exit;
        }
        if (is_file($treatSelectionPath)) {
            @unlink($treatSelectionPath);
        }
        if (!@rename($tmp, $treatSelectionPath)) {
            @file_put_contents($treatSelectionPath, $json . "\n");
            @unlink($tmp);
        }
        if ($treatmentIds === []) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Select at least one finding to apply.']);
            exit;
        }
    }

    // Refuse Apply on a plan older than one hour — ask for a fresh check.
    $planFile = $root . '/data/site-health-plan.json';
    if (is_file($planFile)) {
        $planRaw = @file_get_contents($planFile);
        $planData = is_string($planRaw) ? json_decode($planRaw, true) : null;
        $checkedAt = is_array($planData) ? trim((string) ($planData['checked_at'] ?? '')) : '';
        if ($checkedAt !== '') {
            $checkedTs = strtotime($checkedAt . (preg_match('/[zZ]|[+-]\d{2}:?\d{2}$/', $checkedAt) ? '' : ' UTC'));
            if ($checkedTs !== false && (time() - $checkedTs) > 3600) {
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'error' => 'This health check is over an hour old. Run a fresh Quick health check before applying treatment.',
                ]);
                exit;
            }
        }
    }
}

$scriptMap = [
    'check' => $root . '/scripts/siteHealthCheck.py',
    'check_full' => $root . '/scripts/siteHealthCheckFull.py',
    'treat' => $root . '/scripts/siteHealthTreat.py',
    'force' => $root . '/scripts/siteHealthForce.py',
];
$script = $scriptMap[$mode];
$lockFile = $logDir . '/site-health.lock';
$metaFile = $logDir . '/site-health.meta.json';
// Private process sidecar only (EXITCODE / runner chatter). Never the Activity pane —
// Python scripts/site_health/log.py is the sole writer of log/site-health.log.
$runnerLogFile = $logDir . '/site-health.runner.log';
$isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';

if (!is_file($script)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Site health script missing']);
    exit;
}

if (is_file($lockFile)) {
    $lockAge = time() - (int) @filemtime($lockFile);
    if ($lockAge < 7200) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Site health is already running', 'running' => true]);
        exit;
    }
    @unlink($lockFile);
}

$python = bandpromo_resolve_python_interpreter();
if ($python === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not resolve Python for site health']);
    exit;
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
    'actor' => trim((string) ($_SESSION['username'] ?? 'unknown')),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

@file_put_contents($lockFile, 'running');
bandpromo_job_stop_clear($root, 'site_health');
// Site update used to leave a package_update build-required nudge that told
// operators to run Quick check — clear it when Check/Treat/Force actually starts.
bandpromo_clear_build_required_reasons(['package_update']);
// Quick/Full check is the Notifications CTA for “Saved changes are not live yet”.
// Heal playlist-scan (and idle auto-delivery leftovers) so the nag can clear.
if (in_array($mode, ['check', 'check_full', 'treat', 'force'], true)) {
    bandpromo_heal_build_required_operator_nags();
}
$launch = bandpromo_build_launch_background($python, $script, $runnerLogFile, $lockFile, $runId, $isWindows, null);

if (empty($launch['started'])) {
    @unlink($lockFile);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not start site health runner',
        'debug' => $launch,
    ]);
    exit;
}

if (!empty($launch['pid'])) {
    @file_put_contents($lockFile, (string) $launch['pid']);
}

bandpromo_admin_audit_log('site_health_' . $mode, [
    'target_type' => 'site_health',
    'target_id' => $mode,
    'status' => 'ok',
    'data' => ['run_id' => $runId],
]);

echo json_encode([
    'ok' => true,
    'mode' => $mode,
    'running' => true,
    'run_id' => $runId,
    'message' => $mode === 'check'
        ? 'Quick health check started.'
        : ($mode === 'check_full'
            ? 'Full health check started.'
            : ($mode === 'treat' ? 'Treatment started.' : 'Force rebuild started.')),
]);
