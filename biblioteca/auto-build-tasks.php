<?php

require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/setup-state.php';
require_once __DIR__ . '/media-delivery-helpers.php';

function bandpromo_background_tasks_file(): string
{
    return dirname(__DIR__) . '/log/background-tasks.json';
}

function bandpromo_read_background_tasks(): array
{
    $default = [
        'updated_at' => null,
        'items' => [],
    ];

    $file = bandpromo_background_tasks_file();
    if (!is_file($file)) {
        return $default;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }

    return [
        'updated_at' => isset($decoded['updated_at']) ? (string) $decoded['updated_at'] : null,
        'items' => is_array($decoded['items'] ?? null) ? array_values($decoded['items']) : [],
    ];
}

function bandpromo_write_background_tasks(array $state): void
{
    $file = bandpromo_background_tasks_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $payload = [
        'updated_at' => gmdate('c'),
        'items' => is_array($state['items'] ?? null) ? array_values($state['items']) : [],
    ];

    file_put_contents(
        $file,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function bandpromo_upsert_background_task(string $id, array $task): void
{
    $state = bandpromo_read_background_tasks();
    $items = $state['items'];
    $found = false;

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['id'] ?? '') === $id) {
            $items[$index] = array_merge($item, $task, ['id' => $id]);
            $found = true;
            break;
        }
    }

    if (!$found) {
        $items[] = array_merge($task, ['id' => $id]);
    }

    bandpromo_write_background_tasks(['items' => $items]);
}

function bandpromo_remove_background_task(string $id): void
{
    $state = bandpromo_read_background_tasks();
    $items = array_values(array_filter(
        $state['items'],
        static fn($item) => is_array($item) && (string) ($item['id'] ?? '') !== $id
    ));
    bandpromo_write_background_tasks(['items' => $items]);
}

function bandpromo_video_delivery_job_dir(): string
{
    return dirname(__DIR__) . '/log/video-delivery-jobs';
}

function bandpromo_video_delivery_lock_path(string $taskId): string
{
    return bandpromo_video_delivery_job_dir() . '/' . $taskId . '.lock';
}

function bandpromo_resolve_ffmpeg_path(): string
{
    $root = dirname(__DIR__);
    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $candidate = $root . '/scripts/bin/' . ($isWindows ? 'ffmpeg.exe' : 'ffmpeg');

    return is_file($candidate) ? $candidate : 'ffmpeg';
}

function bandpromo_video_upload_needs_async(string $filename): bool
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($ext, ['mov', 'webm'], true);
}

function bandpromo_has_running_background_video_tasks(): bool
{
    foreach (bandpromo_read_background_tasks()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['task'] ?? '') !== 'video-delivery') {
            continue;
        }
        if ((string) ($item['status'] ?? '') === 'running') {
            return true;
        }
    }

    return false;
}

function bandpromo_video_delivery_running_filename_map(): array
{
    $map = [];

    foreach (bandpromo_read_background_tasks()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['task'] ?? '') !== 'video-delivery') {
            continue;
        }
        if ((string) ($item['status'] ?? '') !== 'running') {
            continue;
        }

        foreach ((array) ($item['files'] ?? []) as $file) {
            $safe = basename(trim((string) $file));
            if ($safe !== '') {
                $map[$safe] = true;
            }
        }
    }

    return $map;
}

function bandpromo_video_delivery_auto_state_path(string $root): string
{
    return $root . '/log/video-delivery-auto.json';
}

function bandpromo_video_delivery_auto_load_state(string $root): array
{
    $path = bandpromo_video_delivery_auto_state_path($root);
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function bandpromo_video_delivery_auto_save_state(string $root, array $state): void
{
    $path = bandpromo_video_delivery_auto_state_path($root);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $merged = array_merge(bandpromo_video_delivery_auto_load_state($root), $state);
    file_put_contents(
        $path,
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function bandpromo_video_delivery_paused_filenames(string $root = ''): array
{
    if ($root === '') {
        $root = dirname(__DIR__);
    }

    $state = bandpromo_video_delivery_auto_load_state($root);
    $paused = is_array($state['paused_files'] ?? null) ? $state['paused_files'] : [];
    $now = time();
    $active = [];

    foreach ($paused as $filename => $until) {
        $safe = basename(trim((string) $filename));
        if ($safe === '') {
            continue;
        }
        $untilTs = is_numeric($until) ? (int) $until : strtotime((string) $until);
        if ($untilTs === false || $untilTs > $now) {
            $active[$safe] = $untilTs === false ? ($now + 3600) : $untilTs;
        }
    }

    return $active;
}

function bandpromo_video_delivery_pause_filenames(array $filenames, int $seconds = 3600, string $root = '', string $reason = ''): void
{
    if ($root === '') {
        $root = dirname(__DIR__);
    }

    $seconds = max(60, $seconds);
    $paused = bandpromo_video_delivery_paused_filenames($root);
    $until = time() + $seconds;

    foreach ($filenames as $filename) {
        $safe = basename(trim((string) $filename));
        if ($safe !== '') {
            $paused[$safe] = $until;
        }
    }

    bandpromo_video_delivery_auto_save_state($root, [
        'paused_files' => $paused,
        'paused_reason' => $reason !== '' ? $reason : 'operator_force_stop',
        'paused_at' => gmdate('c'),
    ]);
}

/**
 * Force-stop running video delivery jobs and pause auto-retry so Site update / Publish can proceed.
 */
function bandpromo_force_stop_video_delivery(array $options = []): array
{
    $root = dirname(__DIR__);
    $taskId = trim((string) ($options['task_id'] ?? ''));
    $pauseSeconds = isset($options['pause_seconds']) ? (int) $options['pause_seconds'] : 3600;
    $reason = trim((string) ($options['reason'] ?? 'Force-stopped so Site update and Publish can continue.'));
    if ($reason === '') {
        $reason = 'Force-stopped so Site update and Publish can continue.';
    }

    $stopped = [];
    $files = [];
    $jobsDir = bandpromo_video_delivery_job_dir();

    foreach (bandpromo_read_background_tasks()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['task'] ?? '') !== 'video-delivery') {
            continue;
        }

        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if ($taskId !== '' && $id !== $taskId) {
            continue;
        }

        $status = (string) ($item['status'] ?? '');
        if ($status !== 'running' && $taskId === '') {
            // Full force-stop only clears running jobs; single-task force may clear any status.
            continue;
        }

        foreach ((array) ($item['files'] ?? []) as $file) {
            $safe = basename(trim((string) $file));
            if ($safe !== '') {
                $files[$safe] = true;
            }
        }

        $lock = bandpromo_video_delivery_lock_path($id);
        if (is_file($lock)) {
            @unlink($lock);
        }
        if (is_dir($jobsDir)) {
            foreach ([$id . '.bat', $id . '.payload.json'] as $sidecar) {
                $path = $jobsDir . '/' . $sidecar;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        bandpromo_upsert_background_task($id, [
            'task' => 'video-delivery',
            'status' => 'failed',
            'label' => 'Video delivery force-stopped',
            'files' => is_array($item['files'] ?? null) ? $item['files'] : [],
            'prepared' => is_array($item['prepared'] ?? null) ? $item['prepared'] : [],
            'failed' => is_array($item['failed'] ?? null) ? $item['failed'] : [],
            'started_at' => (string) ($item['started_at'] ?? gmdate('c')),
            'finished_at' => gmdate('c'),
            'error' => $reason,
            'force_stopped' => true,
        ]);
        $stopped[] = $id;
    }

    $fileList = array_keys($files);
    if ($fileList !== []) {
        bandpromo_video_delivery_pause_filenames($fileList, $pauseSeconds, $root, $reason);
    }

    // Clear video-delivery follow-up so Publish / Site update are not gated on the loop.
    bandpromo_clear_build_required_tasks(['video-delivery']);

    return [
        'ok' => true,
        'stopped_task_ids' => $stopped,
        'paused_files' => $fileList,
        'pause_seconds' => max(60, $pauseSeconds),
        'reason' => $reason,
    ];
}

function bandpromo_video_delivery_recent_failed_filenames(int $cooldownSeconds = 120): array
{
    $blocked = [];
    $now = time();

    foreach (bandpromo_read_background_tasks()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['task'] ?? '') !== 'video-delivery' || (string) ($item['status'] ?? '') !== 'failed') {
            continue;
        }

        $finishedAt = strtotime((string) ($item['finished_at'] ?? ''));
        if ($finishedAt === false || ($now - $finishedAt) < $cooldownSeconds) {
            foreach ((array) ($item['files'] ?? []) as $file) {
                $safe = basename(trim((string) $file));
                if ($safe !== '') {
                    $blocked[$safe] = true;
                }
            }
        }
    }

    return $blocked;
}

function bandpromo_maybe_auto_queue_video_delivery(string $root): array
{
    require_once __DIR__ . '/media-delivery-helpers.php';

    if (bandpromo_has_running_background_video_tasks()) {
        return [
            'queued' => false,
            'reason' => 'running',
            'files' => [],
            'task_id' => '',
            'error' => '',
        ];
    }

    $missing = bandpromo_list_videos_needing_delivery($root);
    if ($missing === []) {
        return [
            'queued' => false,
            'reason' => 'none',
            'files' => [],
            'task_id' => '',
            'error' => '',
        ];
    }

    $runningFiles = bandpromo_video_delivery_running_filename_map();
    $recentFailed = bandpromo_video_delivery_recent_failed_filenames();
    $pausedFiles = bandpromo_video_delivery_paused_filenames($root);
    $toQueue = [];
    foreach ($missing as $filename) {
        if (isset($runningFiles[$filename]) || isset($recentFailed[$filename]) || isset($pausedFiles[$filename])) {
            continue;
        }
        $toQueue[] = $filename;
    }

    if ($toQueue === []) {
        $reason = $pausedFiles !== [] && array_diff($missing, array_keys($pausedFiles)) === []
            ? 'paused'
            : 'pending';

        return [
            'queued' => false,
            'reason' => $reason,
            'files' => $missing,
            'task_id' => '',
            'error' => '',
        ];
    }

    $state = bandpromo_video_delivery_auto_load_state($root);
    $lastQueueAt = (int) ($state['last_queue_at'] ?? 0);
    if ($lastQueueAt > 0 && (time() - $lastQueueAt) < 15) {
        return [
            'queued' => false,
            'reason' => 'cooldown',
            'files' => $toQueue,
            'task_id' => '',
            'error' => '',
        ];
    }

    $spawn = bandpromo_spawn_async_video_delivery($toQueue);
    if (!empty($spawn['ok'])) {
        bandpromo_video_delivery_auto_save_state($root, [
            'last_queue_at' => time(),
            'last_task_id' => (string) ($spawn['task_id'] ?? ''),
            'last_files' => $toQueue,
            'last_error' => '',
        ]);

        return [
            'queued' => true,
            'reason' => 'queued',
            'files' => $toQueue,
            'task_id' => (string) ($spawn['task_id'] ?? ''),
            'error' => '',
        ];
    }

    bandpromo_video_delivery_auto_save_state($root, [
        'last_queue_at' => time(),
        'last_error' => (string) ($spawn['error'] ?? 'Could not start background video delivery'),
    ]);

    return [
        'queued' => false,
        'reason' => 'spawn_failed',
        'files' => $toQueue,
        'task_id' => (string) ($spawn['task_id'] ?? ''),
        'error' => (string) ($spawn['error'] ?? 'Could not start background video delivery'),
    ];
}

function bandpromo_clear_build_required_tasks_if_no_background_video_pending(): array
{
    if (bandpromo_has_running_background_video_tasks()) {
        return bandpromo_get_build_required_state();
    }

    return bandpromo_clear_build_required_tasks(['video-delivery']);
}

function bandpromo_parse_background_task_result_file(string $resultFile): ?array
{
    if ($resultFile === '' || !is_file($resultFile)) {
        return null;
    }

    $raw = file_get_contents($resultFile);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
    for ($index = count($lines) - 1; $index >= 0; $index--) {
        $line = trim((string) $lines[$index]);
        if ($line === '' || $line[0] !== '{') {
            continue;
        }

        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $decoded = json_decode(trim($raw), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_finalize_background_task_from_files(string $taskId, string $resultFile, string $errorFile = ''): bool
{
    $resultData = bandpromo_parse_background_task_result_file($resultFile);
    $errorTail = '';
    if ($errorFile !== '' && is_file($errorFile)) {
        $stderr = file_get_contents($errorFile);
        if ($stderr !== false) {
            $lines = preg_split('/\r\n|\r|\n/', trim($stderr));
            $errorTail = trim(implode("\n", array_slice($lines, -6)));
        }
    }

    $ok = is_array($resultData) && !empty($resultData['ok']);
    $prepared = is_array($resultData['prepared'] ?? null) ? array_values($resultData['prepared']) : [];
    $failed = is_array($resultData['failed'] ?? null) ? $resultData['failed'] : [];
    $stillMissing = is_array($resultData['still_missing'] ?? null) ? array_values($resultData['still_missing']) : [];
    $errorMessage = '';

    $state = bandpromo_read_background_tasks();
    $existing = null;
    foreach ($state['items'] as $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $taskId) {
            $existing = $item;
            break;
        }
    }

    $files = is_array($existing['files'] ?? null) ? $existing['files'] : [];
    if ($files === [] && $prepared !== []) {
        $files = $prepared;
    }

    // Hard verify against PHP readiness (delivery MP4 + poster). Prevents false "done"
    // success loops when Python reports ok but posters (or MP4s) are still missing.
    require_once __DIR__ . '/media-delivery-helpers.php';
    $root = dirname(__DIR__);
    $incomplete = [];
    foreach ($files as $filename) {
        $safe = basename(trim((string) $filename));
        if ($safe === '') {
            continue;
        }
        if (bandpromo_video_needs_delivery($root, $safe)) {
            $incomplete[] = $safe;
        }
    }
    if ($incomplete !== []) {
        $ok = false;
        $stillMissing = array_values(array_unique(array_merge($stillMissing, $incomplete)));
        if ($errorMessage === '') {
            $errorMessage = 'Delivery files are still missing for: ' . implode(', ', $stillMissing);
        }
        // Pause brief auto-retry storms when a job keeps finishing incomplete.
        bandpromo_video_delivery_pause_filenames($incomplete, 300, $root, 'incomplete_delivery');
    }

    if (!$ok) {
        if ($errorMessage === '' && is_array($resultData) && !empty($resultData['error'])) {
            $errorMessage = (string) $resultData['error'];
        } elseif ($errorMessage === '' && $stillMissing !== []) {
            $errorMessage = 'Delivery files are still missing for: ' . implode(', ', $stillMissing);
        } elseif ($errorMessage === '' && $failed !== []) {
            $first = $failed[0];
            $errorMessage = is_array($first)
                ? ((string) ($first['file'] ?? 'video') . ': ' . (string) ($first['error'] ?? 'delivery_failed'))
                : 'Video delivery failed';
        } elseif ($errorMessage === '' && $errorTail !== '') {
            $errorMessage = $errorTail;
        } elseif ($errorMessage === '' && $resultFile !== '' && is_file($resultFile)) {
            $raw = trim((string) file_get_contents($resultFile));
            $errorMessage = $raw !== '' ? $raw : 'Video delivery task failed without a result payload';
        } elseif ($errorMessage === '') {
            $errorMessage = 'Video delivery task failed without a result payload';
        }
    }

    bandpromo_upsert_background_task($taskId, [
        'task' => 'video-delivery',
        'status' => $ok ? 'done' : 'failed',
        'label' => $ok ? 'Video delivery finished' : 'Video delivery failed',
        'files' => $files,
        'prepared' => $prepared,
        'failed' => $failed,
        'started_at' => (string) ($existing['started_at'] ?? gmdate('c')),
        'finished_at' => gmdate('c'),
        'error' => $ok ? '' : $errorMessage,
    ]);

    if ($ok) {
        bandpromo_clear_build_required_tasks_if_no_background_video_pending();
    }

    return $ok;
}

function bandpromo_prune_background_tasks(int $doneRetentionSeconds = 120): void
{
    $items = bandpromo_read_background_tasks()['items'];
    $now = time();
    $kept = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $status = (string) ($item['status'] ?? '');
        if ($status !== 'done') {
            $kept[] = $item;
            continue;
        }

        $finishedAt = (string) ($item['finished_at'] ?? '');
        if ($finishedAt === '') {
            $kept[] = $item;
            continue;
        }

        try {
            $finishedTs = (new DateTimeImmutable($finishedAt))->getTimestamp();
        } catch (Throwable $throwable) {
            $kept[] = $item;
            continue;
        }

        if (($now - $finishedTs) <= $doneRetentionSeconds) {
            $kept[] = $item;
        }
    }

    bandpromo_write_background_tasks(['items' => $kept]);
}

function bandpromo_reconcile_background_tasks(): array
{
    $jobsDir = bandpromo_video_delivery_job_dir();
    if (!is_dir($jobsDir)) {
        bandpromo_prune_background_tasks();
        return bandpromo_read_background_tasks();
    }

    $staleAfterSeconds = 45 * 60; // force-fail hung jobs so Site update is not blocked forever
    $now = time();

    foreach (bandpromo_read_background_tasks()['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['task'] ?? '') !== 'video-delivery') {
            continue;
        }
        if ((string) ($item['status'] ?? '') !== 'running') {
            continue;
        }

        $taskId = (string) ($item['id'] ?? '');
        if ($taskId === '') {
            continue;
        }

        $lock = bandpromo_video_delivery_lock_path($taskId);
        $startedAt = strtotime((string) ($item['started_at'] ?? ''));
        $isStale = $startedAt !== false && ($now - $startedAt) >= $staleAfterSeconds;

        if (is_file($lock) && !$isStale) {
            continue;
        }

        if ($isStale && is_file($lock)) {
            @unlink($lock);
            bandpromo_upsert_background_task($taskId, [
                'task' => 'video-delivery',
                'status' => 'failed',
                'label' => 'Video delivery timed out',
                'files' => is_array($item['files'] ?? null) ? $item['files'] : [],
                'prepared' => [],
                'failed' => [],
                'started_at' => (string) ($item['started_at'] ?? gmdate('c')),
                'finished_at' => gmdate('c'),
                'error' => 'Background video preparation ran too long and was stopped automatically. Use Notifications → Stop retrying if Site update is waiting, then try Publish again later.',
            ]);
            $files = [];
            foreach ((array) ($item['files'] ?? []) as $file) {
                $safe = basename(trim((string) $file));
                if ($safe !== '') {
                    $files[] = $safe;
                }
            }
            if ($files !== []) {
                bandpromo_video_delivery_pause_filenames($files, 1800, dirname(__DIR__), 'stale_timeout');
            }
            continue;
        }

        $resultFile = $jobsDir . '/' . $taskId . '.result.json';
        $errorFile = $jobsDir . '/' . $taskId . '.error.log';
        bandpromo_finalize_background_task_from_files($taskId, $resultFile, $errorFile);
    }

    bandpromo_prune_background_tasks();

    bandpromo_maybe_auto_queue_video_delivery(dirname(__DIR__));

    return bandpromo_read_background_tasks();
}

function bandpromo_launch_windows_batch_detached(string $batchPath, string $workingDirectory): bool
{
    if (!is_file($batchPath)) {
        return false;
    }

    $batchPath = realpath($batchPath) ?: $batchPath;
    $workingDirectory = realpath($workingDirectory) ?: $workingDirectory;

    if (bandpromo_can_proc_open()) {
        $null = 'NUL';
        $pipes = [];
        $process = @proc_open(
            ['cmd.exe', '/c', $batchPath],
            [
                0 => ['file', $null, 'r'],
                1 => ['file', $null, 'w'],
                2 => ['file', $null, 'w'],
            ],
            $pipes,
            $workingDirectory,
            null,
            ['bypass_shell' => true, 'create_new_console' => false]
        );

        if (is_resource($process)) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status = proc_get_status($process);

            return is_array($status) && (int) ($status['pid'] ?? 0) > 0;
        }
    }

    if (!function_exists('exec')) {
        return false;
    }

    $command = 'cmd /c start "" /B ' . escapeshellarg($batchPath);
    $output = [];
    $exitCode = 1;
    exec($command . ' 1>NUL 2>NUL', $output, $exitCode);

    return $exitCode === 0;
}

function bandpromo_spawn_async_video_delivery(array $filenames): array
{
    $requested = bandpromo_filter_uploaded_filenames($filenames, ['mp4', 'mov', 'webm']);
    if ($requested === []) {
        return [
            'ok' => false,
            'task_id' => '',
            'error' => 'No video files to prepare',
        ];
    }

    if (!bandpromo_can_proc_open() && !function_exists('exec')) {
        return [
            'ok' => false,
            'task_id' => '',
            'error' => 'Background process launch is unavailable on this host',
        ];
    }

    $python = bandpromo_resolve_python_interpreter();
    if ($python === '') {
        return [
            'ok' => false,
            'task_id' => '',
            'error' => 'Could not resolve Python runtime for background video delivery',
        ];
    }

    $root = dirname(__DIR__);
    $jobsDir = bandpromo_video_delivery_job_dir();
    if (!is_dir($jobsDir) && !mkdir($jobsDir, 0750, true)) {
        return [
            'ok' => false,
            'task_id' => '',
            'error' => 'Could not create background video job directory',
        ];
    }

    $taskId = 'video-delivery-' . gmdate('YmdHis') . '-' . (function_exists('random_bytes') ? bin2hex(random_bytes(4)) : substr(uniqid('', true), -8));
    $payloadFile = $jobsDir . '/' . $taskId . '.payload.json';
    $resultFile = $jobsDir . '/' . $taskId . '.result.json';
    $errorFile = $jobsDir . '/' . $taskId . '.error.log';
    $lockFile = bandpromo_video_delivery_lock_path($taskId);
    $runnerBat = $jobsDir . '/' . $taskId . '.bat';
    $script = $root . '/scripts/videoSourceDelivery.py';
    $finishScript = $root . '/biblioteca/finish-background-task.php';
    $php = bandpromo_resolve_php_cli();
    $ffmpeg = bandpromo_resolve_ffmpeg_path();

    $payload = json_encode(['filenames' => $requested], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false || file_put_contents($payloadFile, $payload . "\n", LOCK_EX) === false) {
        return [
            'ok' => false,
            'task_id' => '',
            'error' => 'Could not write background video job payload',
        ];
    }

    bandpromo_upsert_background_task($taskId, [
        'task' => 'video-delivery',
        'status' => 'running',
        'label' => 'Preparing video delivery',
        'files' => $requested,
        'prepared' => [],
        'failed' => [],
        'started_at' => gmdate('c'),
        'finished_at' => null,
        'error' => '',
    ]);

    file_put_contents($lockFile, 'running', LOCK_EX);

    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $started = false;

    if ($isWindows) {
        $bat = [];
        $bat[] = '@echo off';
        $bat[] = 'cd /d "' . str_replace('"', '""', $root) . '"';
        $bat[] = 'set "FFMPEG_PATH=' . str_replace('"', '""', $ffmpeg) . '"';
        $bat[] = '"' . str_replace('"', '""', $python) . '" -u "' . str_replace('"', '""', $script) . '" < "' . str_replace('"', '""', $payloadFile) . '" > "' . str_replace('"', '""', $resultFile) . '" 2> "' . str_replace('"', '""', $errorFile) . '"';
        $bat[] = '"' . str_replace('"', '""', $php) . '" "' . str_replace('"', '""', $finishScript) . '" --task-id=' . $taskId . ' --result-file="' . str_replace('"', '""', $resultFile) . '" --error-file="' . str_replace('"', '""', $errorFile) . '"';
        $bat[] = 'del /f /q "' . str_replace('"', '""', $lockFile) . '" >nul 2>&1';
        file_put_contents($runnerBat, implode("\r\n", $bat) . "\r\n");

        $started = bandpromo_launch_windows_batch_detached($runnerBat, $root);
    } else {
        $inner = 'cd ' . escapeshellarg($root)
            . ' && FFMPEG_PATH=' . escapeshellarg($ffmpeg)
            . ' ' . escapeshellarg($python) . ' -u ' . escapeshellarg($script)
            . ' < ' . escapeshellarg($payloadFile)
            . ' > ' . escapeshellarg($resultFile)
            . ' 2> ' . escapeshellarg($errorFile)
            . '; ' . escapeshellarg($php) . ' ' . escapeshellarg($finishScript)
            . ' --task-id=' . escapeshellarg($taskId)
            . ' --result-file=' . escapeshellarg($resultFile)
            . ' --error-file=' . escapeshellarg($errorFile)
            . '; rm -f ' . escapeshellarg($lockFile);

        $bgCmd = 'nohup sh -c ' . escapeshellarg($inner) . ' > /dev/null 2>&1 & echo $!';
        $pid = trim((string) shell_exec($bgCmd));
        $started = $pid !== '' && is_numeric($pid);
    }

    if (!$started) {
        @unlink($lockFile);
        @unlink($payloadFile);
        bandpromo_upsert_background_task($taskId, [
            'task' => 'video-delivery',
            'status' => 'failed',
            'label' => 'Video delivery failed to start',
            'files' => $requested,
            'started_at' => gmdate('c'),
            'finished_at' => gmdate('c'),
            'error' => 'Could not start background video delivery process',
        ]);

        return [
            'ok' => false,
            'task_id' => $taskId,
            'error' => 'Could not start background video delivery process',
        ];
    }

    return [
        'ok' => true,
        'task_id' => $taskId,
        'files' => $requested,
        'error' => '',
    ];
}

function bandpromo_filter_uploaded_filenames(array $filenames, array $extensions): array
{
    $allowed = array_fill_keys(array_map('strtolower', $extensions), true);
    $filtered = [];

    foreach ($filenames as $filename) {
        if (!is_string($filename) || $filename === '' || strpbrk($filename, '/\\') !== false) {
            continue;
        }
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            continue;
        }
        if (!in_array($filename, $filtered, true)) {
            $filtered[] = $filename;
        }
    }

    return $filtered;
}

function bandpromo_run_audio_source_delivery(array $filenames): array
{
    $requested = bandpromo_filter_uploaded_filenames($filenames, ['flac', 'mp3', 'wav']);
    if ($requested === []) {
        return [
            'ok' => true,
            'prepared' => [],
            'failed' => [],
            'still_missing' => [],
            'error' => '',
        ];
    }

    $result = bandpromo_run_light_json_task('scripts/audioSourceDelivery.py', [
        'filenames' => $requested,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    if (!$result['ok'] || !is_array($data)) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));
        return [
            'ok' => false,
            'prepared' => [],
            'failed' => array_map(static fn($file) => ['file' => $file, 'error' => 'delivery_task_failed'], $requested),
            'still_missing' => $requested,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not prepare audio delivery files'),
        ];
    }

    return [
        'ok' => !empty($data['ok']),
        'prepared' => is_array($data['prepared'] ?? null) ? array_values($data['prepared']) : [],
        'failed' => is_array($data['failed'] ?? null) ? $data['failed'] : [],
        'still_missing' => is_array($data['still_missing'] ?? null) ? array_values($data['still_missing']) : [],
        'error' => empty($data['ok']) ? 'Some audio delivery files could not be prepared' : '',
    ];
}

function bandpromo_list_missing_bundled_demo_audio_delivery(string $root): array
{
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/publish-status-helpers.php';

    $missing = [];

    try {
        bandpromo_release_ensure_demo_release($root);
        $release = bandpromo_release_load_document($root, BANDPROMO_RELEASE_DEMO_ID);
        foreach ($release['tracks'] as $track) {
            if (!is_array($track)) {
                continue;
            }
            $masterFile = bandpromo_release_track_master_filename($root, (string) ($track['asset_id'] ?? ''));
            if ($masterFile === '') {
                continue;
            }
            if (!bandpromo_asset_audio_delivery_ready($root, $masterFile)) {
                $missing[] = $masterFile;
            }
        }
    } catch (Throwable $throwable) {
        // Fall back to legacy bundled originals scan below.
    }

    $dir = $root . '/media/audio/original';
    if (is_dir($dir)) {
        foreach (scandir($dir) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            if (strcasecmp($filename, 'desktop.ini') === 0) {
                continue;
            }
            if (strncmp($filename, 'bandPromo_', 10) !== 0) {
                continue;
            }
            $path = $dir . '/' . $filename;
            if (!is_file($path)) {
                continue;
            }
            if (!bandpromo_audio_delivery_ready($root, $filename)) {
                $missing[] = $filename;
            }
        }
    }

    $missing = array_values(array_unique($missing));
    sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

    return $missing;
}

function bandpromo_ensure_bundled_demo_audio_delivery(string $root): array
{
    $missing = bandpromo_list_missing_bundled_demo_audio_delivery($root);
    if ($missing === []) {
        return [
            'ok' => true,
            'prepared' => [],
            'still_missing' => [],
            'error' => '',
        ];
    }

    return bandpromo_run_audio_source_delivery($missing);
}

function bandpromo_run_video_source_delivery(array $filenames): array
{
    $requested = bandpromo_filter_uploaded_filenames($filenames, ['mp4', 'mov', 'webm']);
    if ($requested === []) {
        return [
            'ok' => true,
            'prepared' => [],
            'failed' => [],
            'still_missing' => [],
            'error' => '',
        ];
    }

    $result = bandpromo_run_light_json_task('scripts/videoSourceDelivery.py', [
        'filenames' => $requested,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    if (!$result['ok'] || !is_array($data)) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));
        return [
            'ok' => false,
            'prepared' => [],
            'failed' => array_map(static fn($file) => ['file' => $file, 'error' => 'delivery_task_failed'], $requested),
            'still_missing' => $requested,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not prepare video delivery files'),
        ];
    }

    return [
        'ok' => !empty($data['ok']),
        'prepared' => is_array($data['prepared'] ?? null) ? array_values($data['prepared']) : [],
        'failed' => is_array($data['failed'] ?? null) ? $data['failed'] : [],
        'still_missing' => is_array($data['still_missing'] ?? null) ? array_values($data['still_missing']) : [],
        'error' => empty($data['ok']) ? 'Some video delivery files could not be prepared' : '',
    ];
}

function bandpromo_run_playlist_validation_scan(): array
{
    return bandpromo_run_light_task('scripts/makePlaylists.py', [
        'BANDPROMO_PLAYLIST_SCAN_MODE' => 'validation-only',
    ]);
}

function bandpromo_maybe_run_auto_image_delivery(array $reasons, ?array $state): array
{
    $hasImageWork = in_array('media_image_upload', $reasons, true) || in_array('media_cover_upload', $reasons, true);
    if (!$hasImageWork) {
        return [
            'state' => $state,
            'auto_tasks' => [],
            'warning' => '',
            'task_output' => '',
        ];
    }

    $task = bandpromo_run_light_task('scripts/optimizeMedia.py', [
        'BANDPROMO_OPTIMIZE_MODE' => 'image-only',
    ]);
    if ($task['ok']) {
        return [
            'state' => bandpromo_clear_build_required_tasks(['image-delivery']),
            'auto_tasks' => ['image-delivery'],
            'warning' => '',
            'task_output' => trim((string) ($task['output'] ?? '')),
        ];
    }

    return [
        'state' => $state,
        'auto_tasks' => [],
        'warning' => 'Automatic image refresh failed after upload.',
        'task_output' => trim((string) ($task['output'] ?? '')),
    ];
}

function bandpromo_maybe_run_auto_audio_upload_tasks(array $reasons, array $uploadedFilenames, ?array $state): array
{
    if (!in_array('media_audio_upload', $reasons, true)) {
        return [
            'state' => $state,
            'auto_tasks' => [],
            'warning' => '',
            'task_output' => '',
            'delivery' => null,
        ];
    }

    $autoTasks = [];
    $warnings = [];
    $outputs = [];

    $scan = bandpromo_run_playlist_validation_scan();
    if ($scan['ok']) {
        $autoTasks[] = 'playlist-scan';
        $state = bandpromo_clear_build_required_tasks(['playlist-scan']);
    } else {
        $warnings[] = 'Automatic track validation refresh failed after upload.';
        $outputs[] = trim((string) ($scan['output'] ?? ''));
    }

    $delivery = bandpromo_run_audio_source_delivery($uploadedFilenames);
    if ($delivery['ok']) {
        $autoTasks[] = 'audio-delivery';
        $state = bandpromo_clear_build_required_tasks(['audio-delivery']);
    } else {
        $warnings[] = $delivery['error'] !== '' ? $delivery['error'] : 'Automatic audio delivery preparation failed after upload.';
    }

    return [
        'state' => $state,
        'auto_tasks' => $autoTasks,
        'warning' => implode(' ', array_filter($warnings)),
        'task_output' => implode("\n", array_filter($outputs)),
        'delivery' => $delivery,
    ];
}

function bandpromo_maybe_run_auto_video_upload_tasks(array $reasons, array $uploadedFilenames, ?array $state): array
{
    if (!in_array('media_video_upload', $reasons, true)) {
        return [
            'state' => $state,
            'auto_tasks' => [],
            'warning' => '',
            'delivery' => null,
            'background_tasks' => [],
        ];
    }

    $requested = bandpromo_filter_uploaded_filenames($uploadedFilenames, ['mp4', 'mov', 'webm']);
    if ($requested === []) {
        return [
            'state' => $state,
            'auto_tasks' => [],
            'warning' => '',
            'delivery' => null,
            'background_tasks' => [],
        ];
    }

    $autoTasks = [];
    $warnings = [];
    $delivery = [
        'ok' => true,
        'prepared' => [],
        'failed' => [],
        'still_missing' => $requested,
        'error' => '',
    ];
    $backgroundTasks = [];

    $spawn = bandpromo_spawn_async_video_delivery($requested);
    if ($spawn['ok']) {
        $backgroundTasks[] = [
            'id' => (string) ($spawn['task_id'] ?? ''),
            'task' => 'video-delivery',
            'status' => 'running',
            'files' => $requested,
        ];
    } else {
        $warnings[] = $spawn['error'] !== ''
            ? $spawn['error']
            : 'Could not start background video delivery.';
    }

    return [
        'state' => $state,
        'auto_tasks' => $autoTasks,
        'warning' => implode(' ', array_filter($warnings)),
        'delivery' => $delivery,
        'background_tasks' => $backgroundTasks,
    ];
}

function bandpromo_run_auto_upload_tasks(array $reasons, array $uploadedFilenames, ?array $state): array
{
    $autoTasks = [];
    $warnings = [];
    $taskOutput = [];
    $deliveryPrepared = [];
    $deliveryMissing = [];
    $backgroundTasks = [];

    $image = bandpromo_maybe_run_auto_image_delivery($reasons, $state);
    $state = $image['state'];
    $autoTasks = array_merge($autoTasks, $image['auto_tasks']);
    if ($image['warning'] !== '') {
        $warnings[] = $image['warning'];
    }
    if ($image['task_output'] !== '') {
        $taskOutput[] = $image['task_output'];
    }

    $audio = bandpromo_maybe_run_auto_audio_upload_tasks($reasons, $uploadedFilenames, $state);
    $state = $audio['state'];
    $autoTasks = array_merge($autoTasks, $audio['auto_tasks']);
    if ($audio['warning'] !== '') {
        $warnings[] = $audio['warning'];
    }
    if ($audio['task_output'] !== '') {
        $taskOutput[] = $audio['task_output'];
    }
    if (is_array($audio['delivery'] ?? null)) {
        $deliveryPrepared = array_merge($deliveryPrepared, $audio['delivery']['prepared'] ?? []);
        $deliveryMissing = array_merge($deliveryMissing, $audio['delivery']['still_missing'] ?? []);
    }

    $video = bandpromo_maybe_run_auto_video_upload_tasks($reasons, $uploadedFilenames, $state);
    $state = $video['state'];
    $autoTasks = array_merge($autoTasks, $video['auto_tasks']);
    if ($video['warning'] !== '') {
        $warnings[] = $video['warning'];
    }
    if (is_array($video['delivery'] ?? null)) {
        $deliveryPrepared = array_merge($deliveryPrepared, $video['delivery']['prepared'] ?? []);
        $deliveryMissing = array_merge($deliveryMissing, $video['delivery']['still_missing'] ?? []);
    }
    if (is_array($video['background_tasks'] ?? null)) {
        $backgroundTasks = array_merge($backgroundTasks, $video['background_tasks']);
    }

    return [
        'state' => $state,
        'auto_tasks' => array_values(array_unique($autoTasks)),
        'warning' => implode(' ', array_filter($warnings)),
        'task_output' => implode("\n", array_filter($taskOutput)),
        'delivery_prepared' => array_values(array_unique($deliveryPrepared)),
        'delivery_missing' => array_values(array_unique($deliveryMissing)),
        'background_tasks' => array_values($backgroundTasks),
    ];
}
