<?php
declare(strict_types=1);

/**
 * Start a background publish/deliverables run without going through build.php HTTP.
 * Used after PRP import so hosted hosts do not depend on a follow-up browser fetch.
 *
 * @param array{
 *   mode?: string,
 *   profile?: string,
 *   actor?: string,
 *   skip_preflight?: bool
 * } $options
 * @return array{
 *   ok: bool,
 *   started: bool,
 *   running?: bool,
 *   error?: string,
 *   mode?: string,
 *   profile?: string
 * }
 */
function bandpromo_build_try_start(string $root, array $options = []): array
{
    require_once __DIR__ . '/build-lock.php';
    require_once __DIR__ . '/build-launcher.php';
    require_once __DIR__ . '/build-launch-diagnostics.php';
    require_once __DIR__ . '/build-stages.php';
    require_once __DIR__ . '/build-log-helpers.php';
    require_once __DIR__ . '/light-build-tasks.php';

    $mode = strtolower(trim((string) ($options['mode'] ?? 'full')));
    if (!in_array($mode, ['full', 'optimize'], true)) {
        $mode = 'full';
    }

    $profile = 'full';
    $stageIds = [];
    if ($mode === 'full') {
        $rawProfile = trim((string) ($options['profile'] ?? 'deliverables-only'));
        if ($rawProfile !== '' && bandpromo_build_profile_is_valid($rawProfile)) {
            $profile = $rawProfile;
        }
        $stageIds = bandpromo_build_resolve_stage_ids($profile, null);
    }

    $paths = bandpromo_build_paths($root, $mode);
    $logFile = $paths['log'];
    $lockFile = $paths['lock'];
    $metaFile = $paths['meta'];
    $logDir = dirname($logFile);
    $script = $root . ($mode === 'optimize' ? '/scripts/optimizeMedia.py' : '/scripts/build.py');
    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $runId = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('build_', true);
    $actor = trim((string) ($options['actor'] ?? 'system'));

    if (bandpromo_build_lock_active($root, $mode)) {
        return [
            'ok' => false,
            'started' => false,
            'running' => true,
            'error' => 'Build already in progress',
            'mode' => $mode,
            'profile' => $profile,
        ];
    }

    if (!is_dir($logDir) && !mkdir($logDir, 0750, true) && !is_dir($logDir)) {
        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not create log directory',
            'mode' => $mode,
            'profile' => $profile,
        ];
    }

    file_put_contents($lockFile, 'preparing');
    file_put_contents($logFile, '');
    file_put_contents($logFile, bandpromo_build_log_started_lines($mode), FILE_APPEND);
    file_put_contents($logFile, "[setup] Queued deliverables rebuild after release package import...\n", FILE_APPEND);

    if ($mode === 'full' && empty($options['skip_preflight'])) {
        require_once __DIR__ . '/publish-preflight-helpers.php';
        bandpromo_run_publish_preflight($root, static function (string $line) use ($logFile): void {
            file_put_contents($logFile, $line, FILE_APPEND);
        });
    }

    file_put_contents($logFile, "[setup] Starting publish build...\n", FILE_APPEND);
    file_put_contents($metaFile, json_encode([
        'run_id' => $runId,
        'mode' => $mode,
        'profile' => $profile,
        'stages' => $stageIds,
        'actor' => $actor !== '' ? $actor : 'system',
        'ip' => '',
        'user_agent' => 'release-package-import',
        'started_at' => time(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $python = bandpromo_resolve_python_interpreter();
    if ($python === '' || !is_file($script)) {
        @unlink($lockFile);
        @unlink($metaFile);
        file_put_contents($logFile, "FAILED Could not resolve build runtime or script path.\n", FILE_APPEND);

        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not resolve build runtime',
            'mode' => $mode,
            'profile' => $profile,
        ];
    }

    file_put_contents($lockFile, 'running');
    $diagnostics = bandpromo_build_run_launch_diagnostics($root, $logFile, $python, $script, $isWindows);
    $launch = bandpromo_build_launch_background(
        $python,
        $script,
        $logFile,
        $lockFile,
        $runId,
        $isWindows,
        $diagnostics
    );

    if (empty($launch['started'])) {
        @unlink($lockFile);
        @unlink($metaFile);
        $tail = trim((string) ($launch['launch_output_tail'] ?? ''));
        file_put_contents(
            $logFile,
            'FAILED Could not start build process' . ($tail !== '' ? (': ' . $tail) : '') . "\n",
            FILE_APPEND
        );

        return [
            'ok' => false,
            'started' => false,
            'error' => 'Could not start build process',
            'mode' => $mode,
            'profile' => $profile,
        ];
    }

    if (!$isWindows && !empty($launch['pid'])) {
        file_put_contents($lockFile, (string) $launch['pid']);
    }
    file_put_contents($logFile, 'DEBUG Python: ' . $python . "\n", FILE_APPEND);

    return [
        'ok' => true,
        'started' => true,
        'mode' => $mode,
        'profile' => $profile,
    ];
}
