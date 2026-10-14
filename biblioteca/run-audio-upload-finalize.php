<?php
/**
 * Background finalize for large audio uploads (HITZ shared-host proxy 504).
 *
 * CLI only:
 *   php run-audio-upload-finalize.php --task-id=<id> --payload-file=<path>
 *
 * Moves the assembled upload into a master, registers it, assigns campaign,
 * refreshes display, indexes, runs playlist scan + delivery + cover extract.
 * Must not run inside the upload HTTP request.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@set_time_limit(0);
if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}

require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/build-catalog-helpers.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/media-intake-helpers.php';
require_once __DIR__ . '/publish-status-helpers.php';

function bandpromo_audio_upload_finalize_cli_arg(string $name): string
{
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with((string) $arg, $prefix)) {
            return substr((string) $arg, strlen($prefix));
        }
    }

    return '';
}

function bandpromo_audio_upload_finalize_fail(
    string $taskId,
    string $lockFile,
    string $resultFile,
    string $error,
    array $files = []
): void {
    $error = trim($error);
    if ($error === '') {
        $error = 'Audio upload finalize failed';
    }
    @file_put_contents(
        $resultFile,
        json_encode([
            'ok' => false,
            'prepared' => [],
            'failed' => [],
            'still_missing' => $files,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
    bandpromo_upsert_background_task($taskId, [
        'task' => 'audio-delivery',
        'status' => 'failed',
        'label' => 'Audio upload finalize failed',
        'files' => $files,
        'prepared' => [],
        'failed' => [],
        'finished_at' => gmdate('c'),
        'error' => $error,
    ]);
    @unlink($lockFile);
    fwrite(STDERR, $error . "\n");
}

$taskId = trim(bandpromo_audio_upload_finalize_cli_arg('task-id'));
$payloadFile = trim(bandpromo_audio_upload_finalize_cli_arg('payload-file'));

if ($taskId === '' || $payloadFile === '' || !is_file($payloadFile)) {
    fwrite(STDERR, "Missing --task-id or --payload-file\n");
    exit(1);
}

$root = dirname(__DIR__);
$jobsDir = bandpromo_audio_delivery_job_dir();
$lockFile = bandpromo_audio_delivery_lock_path($taskId);
$resultFile = $jobsDir . '/' . $taskId . '.result.json';
$errorFile = $jobsDir . '/' . $taskId . '.error.log';

$raw = file_get_contents($payloadFile);
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    bandpromo_audio_upload_finalize_fail($taskId, $lockFile, $resultFile, 'Invalid audio upload finalize payload');
    exit(1);
}

$assembledPath = (string) ($payload['assembled_path'] ?? '');
$safeName = basename(trim((string) ($payload['safe_name'] ?? '')));
$humanName = basename(trim((string) ($payload['human_name'] ?? $safeName)));
$ext = strtolower(trim((string) ($payload['ext'] ?? pathinfo($humanName, PATHINFO_EXTENSION))));
$campaignId = trim((string) ($payload['campaign_id'] ?? ''));
$expectedSize = (int) ($payload['expected_size'] ?? 0);

$savedName = $humanName;
$masterFilename = '';
$assetId = '';
$displayRefresh = ['ok' => false];

if ($assembledPath === '' || !is_file($assembledPath) || $humanName === '' || !in_array($ext, ['flac', 'mp3', 'wav'], true)) {
    bandpromo_audio_upload_finalize_fail(
        $taskId,
        $lockFile,
        $resultFile,
        'Assembled audio upload is missing or invalid',
        [$humanName]
    );
    exit(1);
}

$assembledSize = (int) filesize($assembledPath);
if ($expectedSize > 0 && $assembledSize !== $expectedSize) {
    @unlink($assembledPath);
    bandpromo_audio_upload_finalize_fail(
        $taskId,
        $lockFile,
        $resultFile,
        'Assembled audio size mismatch (got ' . $assembledSize . ', expected ' . $expectedSize . ')',
        [$humanName]
    );
    exit(1);
}

try {
    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir) && !mkdir($masterDir, 0755, true) && !is_dir($masterDir)) {
        throw new RuntimeException('Could not create audio master directory');
    }

    $intakeDir = bandpromo_intake_temp_dir($root, 'audio');
    $intakeBase = $safeName !== '' ? $safeName : $humanName;
    $intakePath = $intakeDir . DIRECTORY_SEPARATOR . bandpromo_intake_unique_basename($intakeBase);
    if (!@rename($assembledPath, $intakePath) && !@copy($assembledPath, $intakePath)) {
        throw new RuntimeException('Could not move assembled audio into intake');
    }
    @unlink($assembledPath);

    $savedName = bandpromo_intake_human_basename(basename($intakePath));
    if ($savedName === '') {
        $savedName = $humanName;
    }

    $master = bandpromo_materialize_audio_master_from_original($root, $savedName, true, $intakePath);
    if (empty($master['prepared']) && empty($master['attempted'])) {
        $master = bandpromo_prepare_audio_master($root, $ext, $savedName, $intakePath);
    }
    if (empty($master['prepared'])) {
        $warning = trim((string) ($master['warning'] ?? ''));
        throw new RuntimeException($warning !== '' ? $warning : 'Could not prepare audio master');
    }

    bandpromo_build_catalog_finalize_audio_upload($root, $savedName);

    $masterFilename = basename(trim((string) ($master['master_filename'] ?? '')));
    $displayRefresh = bandpromo_asset_ensure_audio_display_after_upload(
        $root,
        $masterFilename,
        $savedName
    );

    $assetId = trim((string) ($master['asset_id'] ?? ''));
    if ($assetId === '') {
        $lookup = bandpromo_asset_lookup_by_original_filename($root, $savedName);
        if (is_array($lookup)) {
            $assetId = trim((string) ($lookup['id'] ?? ''));
        }
    }

    if ($assetId !== '' && $campaignId !== '') {
        bandpromo_campaign_assign_audio_on_upload($root, $assetId, $campaignId);
    }

    if ($masterFilename !== '') {
        bandpromo_media_files_index_sync_file($root, 'audio', $masterFilename);
    } else {
        bandpromo_media_files_index_rebuild_target($root, 'audio');
    }
} catch (Throwable $throwable) {
    $msg = $throwable->getMessage();
    @file_put_contents($errorFile, $msg . "\n", FILE_APPEND);
    bandpromo_audio_upload_finalize_fail($taskId, $lockFile, $resultFile, $msg, [$humanName]);
    exit(1);
}

bandpromo_mark_build_required('media_audio_upload');
$scan = bandpromo_run_playlist_validation_scan();
if ($scan['ok']) {
    bandpromo_clear_build_required_tasks(['playlist-scan']);
}

$deliveryNames = $masterFilename !== '' ? [$masterFilename] : [$savedName];
$delivery = bandpromo_run_audio_source_delivery_and_refresh($deliveryNames);
$prepared = is_array($delivery['prepared'] ?? null) ? array_values($delivery['prepared']) : [];
$stillMissing = is_array($delivery['still_missing'] ?? null) ? array_values($delivery['still_missing']) : [];
$deliveryOk = !empty($delivery['ok']);

if ($deliveryOk) {
    bandpromo_clear_build_required_tasks(['audio-delivery']);
    // Ensure hard-verify in finalize sees the master stem (task.files stays human for toasts).
    if ($masterFilename !== '' && !in_array($masterFilename, $prepared, true)) {
        $prepared[] = $masterFilename;
    }
    if ($prepared === []) {
        $prepared = $deliveryNames;
    }
} else {
    bandpromo_set_build_required_last_error(
        trim((string) ($delivery['error'] ?? 'Automatic audio delivery preparation failed after upload'))
    );
}
// Cover extract runs inside bandpromo_finalize_background_task_from_files.

$resultPayload = [
    'ok' => $deliveryOk,
    'prepared' => $prepared,
    'failed' => is_array($delivery['failed'] ?? null) ? $delivery['failed'] : [],
    'still_missing' => $stillMissing,
    'error' => $deliveryOk ? '' : trim((string) ($delivery['error'] ?? 'Audio delivery failed')),
    'saved_as' => $savedName,
    'master_filename' => $masterFilename,
    'asset_id' => $assetId,
    'display_ok' => !empty($displayRefresh['ok']),
];
@file_put_contents(
    $resultFile,
    json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
    LOCK_EX
);

// Cover extract already ran above when delivery succeeded; finalize verifies readiness
// and clears sticky chores / refreshes inventory.
bandpromo_finalize_background_task_from_files($taskId, $resultFile, $errorFile);
@unlink($lockFile);

exit($deliveryOk ? 0 : 1);
