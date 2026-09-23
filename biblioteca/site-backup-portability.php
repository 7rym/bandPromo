<?php
declare(strict_types=1);

const BANDPROMO_SITE_BACKUP_FORMAT = 'bandpromo-site-backup';
const BANDPROMO_SITE_BACKUP_VERSION = 1;

const BANDPROMO_DATA_EXPORT_FORMAT = 'bandpromo-data-export';
const BANDPROMO_DATA_EXPORT_VERSION = 1;

const BANDPROMO_SITE_BACKUP_JOB_PENDING = 'pending';
const BANDPROMO_SITE_BACKUP_JOB_BUILDING = 'building';
const BANDPROMO_SITE_BACKUP_JOB_READY = 'ready';
const BANDPROMO_SITE_BACKUP_JOB_FAILED = 'failed';

const BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM = 'platform';
const BANDPROMO_SITE_BACKUP_COMPONENT_DATA = 'data';
const BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA = 'media';
const BANDPROMO_SITE_BACKUP_COMPONENT_LOGS = 'logs';

const BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT = 'export';
const BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT = 'import';

const BANDPROMO_SITE_BACKUP_TYPE_PRP = 'prp';
const BANDPROMO_SITE_BACKUP_TYPE_PBF = 'pbf';

const BANDPROMO_SITE_IMPORT_MODE_RESTORE = 'restore';
const BANDPROMO_SITE_IMPORT_MODE_MIGRATE = 'migrate';

const BANDPROMO_SITE_BACKUP_STAGING_TTL_SECONDS = 7200;

/** Fail `building` jobs with no heartbeat for this long (dead PHP worker / host kill). */
const BANDPROMO_SITE_BACKUP_STALE_BUILDING_SECONDS = 600;
/** Soft wall for one continue slice — stay under typical shared-host request kills. */
const BANDPROMO_SITE_BACKUP_SLICE_SECONDS = 15;
/** Plan-backed sliced jobs may pause while the Backup tab is closed. */
const BANDPROMO_SITE_BACKUP_STALE_PLAN_SECONDS = 21600;

/**
 * @return list<string>
 */
function bandpromo_site_backup_all_components(): array
{
    return [
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
        BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA,
        BANDPROMO_SITE_BACKUP_COMPONENT_LOGS,
    ];
}

/**
 * @return list<string>
 */
function bandpromo_site_backup_normalize_components(mixed $input): array
{
    if (is_string($input)) {
        $input = strtolower(trim($input));
        if ($input === 'full') {
            return bandpromo_site_backup_all_components();
        }
        if ($input === 'data') {
            return [
                BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
                BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
            ];
        }

        throw new InvalidArgumentException('Unknown backup preset.');
    }

    $selected = [];
    if (is_array($input)) {
        $isList = array_is_list($input);
        foreach ($input as $key => $value) {
            if ($isList) {
                $component = strtolower(trim((string) $value));
            } else {
                if (empty($value)) {
                    continue;
                }
                $component = strtolower(trim((string) $key));
            }
            if (!in_array($component, bandpromo_site_backup_all_components(), true)) {
                continue;
            }
            $selected[$component] = true;
        }
    }

    $components = array_keys($selected);
    sort($components);
    if ($components === []) {
        throw new InvalidArgumentException('Select at least one backup component.');
    }

    return $components;
}

/**
 * @return list<string>
 */
function bandpromo_site_backup_job_components(array $job): array
{
    if (bandpromo_site_backup_is_prp_job($job) || bandpromo_site_backup_is_pbf_job($job)) {
        return [];
    }

    if (isset($job['components']) && is_array($job['components'])) {
        try {
            return bandpromo_site_backup_normalize_components($job['components']);
        } catch (InvalidArgumentException) {
            // Fall through to legacy fields below.
        }
    }

    $type = strtolower(trim((string) ($job['type'] ?? 'full')));
    if ($type === 'data') {
        return [
            BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
            BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
        ];
    }

    $components = [
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
        BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA,
    ];
    if (!empty($job['include_log'])) {
        $components[] = BANDPROMO_SITE_BACKUP_COMPONENT_LOGS;
    }

    return $components;
}

function bandpromo_site_backup_is_prp_job(array $job): bool
{
    return strtolower(trim((string) ($job['type'] ?? ''))) === BANDPROMO_SITE_BACKUP_TYPE_PRP;
}

function bandpromo_site_backup_is_pbf_job(array $job): bool
{
    return strtolower(trim((string) ($job['type'] ?? ''))) === BANDPROMO_SITE_BACKUP_TYPE_PBF;
}

function bandpromo_site_backup_component_label(string $component): string
{
    return match ($component) {
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM => 'Install config',
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA => 'Catalogue & config',
        BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA => 'Media library',
        BANDPROMO_SITE_BACKUP_COMPONENT_LOGS => 'Support logs',
        default => ucfirst($component),
    };
}

/**
 * @param list<string> $components
 */
function bandpromo_site_backup_components_label(array $components): string
{
    $sorted = $components;
    sort($sorted);

    if ($sorted === bandpromo_site_backup_all_components()) {
        return 'Full site backup';
    }

    if ($sorted === [
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
    ]) {
        return 'Data export';
    }

    $labels = array_map('bandpromo_site_backup_component_label', $sorted);

    return implode(' + ', $labels);
}

/**
 * @param list<string> $components
 */
function bandpromo_site_backup_archive_kind(array $components): string
{
    $sorted = $components;
    sort($sorted);

    if ($sorted === bandpromo_site_backup_all_components()) {
        return 'full';
    }

    if ($sorted === [
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
    ]) {
        return 'data';
    }

    return 'custom';
}

function bandpromo_site_backup_type_label(string $type): string
{
    try {
        return bandpromo_site_backup_components_label(
            bandpromo_site_backup_normalize_components($type)
        );
    } catch (InvalidArgumentException) {
        return $type === 'data' ? 'Data export' : 'Full site backup';
    }
}

function bandpromo_site_backup_dir(string $root): string
{
    return $root . '/backups';
}

function bandpromo_site_backup_ensure_dir(string $root): string
{
    $dir = bandpromo_site_backup_dir($root);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        $contents = <<<'HTACCESS'
# Block all direct HTTP access to operator backup archives
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
        file_put_contents($htaccess, $contents);
    }

    return $dir;
}

function bandpromo_site_backup_lock_path(string $root): string
{
    return bandpromo_site_backup_ensure_dir($root) . '/.building.lock';
}

function bandpromo_site_backup_sanitize_job_id(string $jobId): string
{
    $jobId = trim($jobId);
    if ($jobId === '' || preg_match('/^[A-Za-z0-9._-]+$/', $jobId) !== 1) {
        throw new InvalidArgumentException('Invalid backup id.');
    }

    return $jobId;
}

function bandpromo_site_backup_generate_id(array $components): string
{
    $suffix = bin2hex(random_bytes(4));
    $kind = bandpromo_site_backup_archive_kind($components);

    return gmdate('Ymd-His') . 'Z-' . $suffix . '-' . $kind;
}

function bandpromo_site_backup_job_meta_path(string $root, string $jobId): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);

    return bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.json';
}

function bandpromo_site_backup_job_zip_path(string $root, string $jobId): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);

    return bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.zip';
}

function bandpromo_site_backup_job_plan_path(string $root, string $jobId): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);

    return bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.plan.json';
}

function bandpromo_site_backup_job_work_dir(string $root, string $jobId): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);

    return bandpromo_site_backup_ensure_dir($root) . '/.work/' . $jobId;
}

/**
 * @return array<string, mixed>|null
 */
function bandpromo_site_backup_read_job_plan(string $root, string $jobId): ?array
{
    $path = bandpromo_site_backup_job_plan_path($root, $jobId);
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_site_backup_write_job_plan(string $root, string $jobId, array $plan): void
{
    require_once __DIR__ . '/json-file-helpers.php';
    $path = bandpromo_site_backup_job_plan_path($root, $jobId);
    if (!bandpromo_json_write_file($path, $plan)) {
        throw new RuntimeException('Could not save export plan.');
    }
}

function bandpromo_site_backup_delete_job_plan(string $root, string $jobId): void
{
    $path = bandpromo_site_backup_job_plan_path($root, $jobId);
    if (is_file($path)) {
        @unlink($path);
    }
}

function bandpromo_site_backup_rrmdir(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            bandpromo_site_backup_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function bandpromo_site_backup_cleanup_job_build_artifacts(string $root, string $jobId): void
{
    $plan = bandpromo_site_backup_read_job_plan($root, $jobId);
    bandpromo_site_backup_delete_job_plan($root, $jobId);
    $work = bandpromo_site_backup_job_work_dir($root, $jobId);
    bandpromo_site_backup_rrmdir($work);
    if (is_array($plan)) {
        $workdir = trim((string) ($plan['workdir'] ?? ''));
        if ($workdir !== '' && is_dir($workdir) && strpos($workdir, bandpromo_site_backup_ensure_dir($root)) === 0) {
            bandpromo_site_backup_rrmdir($workdir);
        }
    }
}

function bandpromo_site_backup_read_job(string $root, string $jobId): ?array
{
    $path = bandpromo_site_backup_job_meta_path($root, $jobId);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_site_backup_write_job(string $root, array $job): void
{
    if (!isset($job['id']) || !is_string($job['id']) || $job['id'] === '') {
        throw new InvalidArgumentException('Backup job id is required.');
    }

    $path = bandpromo_site_backup_job_meta_path($root, $job['id']);
    $encoded = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        throw new RuntimeException('Could not encode backup job metadata.');
    }

    if (file_put_contents($path, $encoded . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save backup job metadata.');
    }
}

function bandpromo_site_backup_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

function bandpromo_site_backup_list_jobs(string $root): array
{
    bandpromo_site_backup_reap_stale_building_jobs($root);

    $dir = bandpromo_site_backup_ensure_dir($root);
    $jobs = [];
    $items = scandir($dir);
    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json') || str_ends_with($item, '.plan.json')) {
            continue;
        }
        $jobId = substr($item, 0, -5);
        try {
            $job = bandpromo_site_backup_read_job($root, $jobId);
        } catch (InvalidArgumentException) {
            continue;
        }
        if ($job === null) {
            continue;
        }
        $jobs[] = bandpromo_site_backup_normalize_job($root, $job);
    }

    usort($jobs, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at_utc'] ?? ''), (string) ($a['created_at_utc'] ?? ''));
    });

    return $jobs;
}

/**
 * UTC timestamp used to decide whether a building job is still alive.
 */
function bandpromo_site_backup_job_liveness_utc(array $job): string
{
    foreach (['heartbeat_at_utc', 'started_at_utc', 'created_at_utc'] as $key) {
        $value = trim((string) ($job[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Persist progress / heartbeat while a job runs (throttled disk writes).
 */
function bandpromo_site_backup_touch_job_progress(
    string $root,
    string $jobId,
    string $progress = '',
    bool $force = false,
    ?int $sizeBytes = null
): void {
    static $lastWrite = [];

    try {
        $jobId = bandpromo_site_backup_sanitize_job_id($jobId);
    } catch (InvalidArgumentException $e) {
        return;
    }

    $now = microtime(true);
    $cacheKey = $root . "\0" . $jobId;
    if (!$force && isset($lastWrite[$cacheKey]) && ($now - $lastWrite[$cacheKey]) < 2.0) {
        return;
    }

    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        return;
    }
    if ((string) ($job['status'] ?? '') !== BANDPROMO_SITE_BACKUP_JOB_BUILDING) {
        return;
    }

    $job['heartbeat_at_utc'] = gmdate('c');
    if ($progress !== '') {
        $job['progress'] = $progress;
    }
    if ($sizeBytes !== null && $sizeBytes >= 0) {
        $job['size_bytes'] = $sizeBytes;
    }
    try {
        bandpromo_site_backup_write_job($root, $job);
        $lastWrite[$cacheKey] = $now;
    } catch (Throwable $e) {
        // Do not fail the export because progress metadata could not be written.
    }
}

/**
 * Abort the worker when the operator cancelled the job.
 */
function bandpromo_site_backup_throw_if_cancelled(string $root, string $jobId): void
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup job was removed while running.');
    }
    if (!empty($job['cancel_requested'])) {
        throw new RuntimeException('Cancelled by operator.');
    }
}

/**
 * Progress callback that heartbeats and honours Cancel.
 *
 * @return callable(string, ?int): void
 */
function bandpromo_site_backup_job_progress_callback(string $root, string $jobId): callable
{
    return static function (string $message, ?int $sizeBytes = null) use ($root, $jobId): void {
        bandpromo_site_backup_throw_if_cancelled($root, $jobId);
        bandpromo_site_backup_touch_job_progress($root, $jobId, $message, true, $sizeBytes);
    };
}

/**
 * Cancel a pending/building job so the operator can re-queue.
 */
function bandpromo_site_backup_cancel_job(string $root, string $jobId): array
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup job was not found.');
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        throw new RuntimeException('Only queued or building jobs can be cancelled.');
    }

    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    $job['cancel_requested'] = true;
    bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);
    bandpromo_site_backup_mark_job_failed(
        $root,
        $job,
        $zipPath,
        'Cancelled by operator.'
    );

    return bandpromo_site_backup_normalize_job($root, $job);
}

/**
 * Mark abandoned building jobs Failed so operators can delete / re-queue.
 *
 * @return int number of jobs reaped
 */
function bandpromo_site_backup_reap_stale_building_jobs(string $root): int
{
    $dir = bandpromo_site_backup_ensure_dir($root);
    $items = scandir($dir);
    if ($items === false) {
        return 0;
    }

    $now = time();
    $reaped = 0;

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json') || str_ends_with($item, '.plan.json')) {
            continue;
        }
        $jobId = substr($item, 0, -5);
        try {
            $job = bandpromo_site_backup_read_job($root, $jobId);
        } catch (InvalidArgumentException $e) {
            continue;
        }
        if ($job === null) {
            continue;
        }
        if ((string) ($job['status'] ?? '') !== BANDPROMO_SITE_BACKUP_JOB_BUILDING) {
            continue;
        }

        $hasPlan = is_file(bandpromo_site_backup_job_plan_path($root, $jobId));
        $ttl = $hasPlan
            ? BANDPROMO_SITE_BACKUP_STALE_PLAN_SECONDS
            : BANDPROMO_SITE_BACKUP_STALE_BUILDING_SECONDS;
        $liveness = bandpromo_site_backup_job_liveness_utc($job);
        $ref = $liveness !== '' ? strtotime($liveness) : false;
        if ($ref === false) {
            continue;
        }
        if (($now - $ref) < $ttl) {
            continue;
        }

        $message = $hasPlan
            ? 'This export paused too long without a continuer (Backup tab closed for '
                . (int) round($ttl / 3600)
                . ' hours). Delete it and queue again, or leave System → Backup open while large exports run.'
            : 'This job stopped responding (no progress for '
                . (int) round($ttl / 60)
                . ' minutes). The host may have ended the worker. Delete it and queue the export again.';

        $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
        bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);
        bandpromo_site_backup_mark_job_failed($root, $job, $zipPath, $message);
        $reaped++;
    }

    return $reaped;
}

function bandpromo_site_backup_normalize_job(string $root, array $job): array
{
    $jobId = (string) ($job['id'] ?? '');
    $direction = bandpromo_site_backup_job_direction($job);
    $zipPath = $jobId !== '' ? bandpromo_site_backup_job_zip_path($root, $jobId) : '';
    $uploadPath = $jobId !== '' ? bandpromo_site_backup_job_upload_path($root, $jobId) : '';
    $packageUploadPath = trim((string) ($job['package_upload_path'] ?? ''));
    $sizeBytes = (int) ($job['size_bytes'] ?? 0);
    if ($sizeBytes <= 0) {
        if ($direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT) {
            if ($packageUploadPath !== '' && is_file($packageUploadPath)) {
                $sizeBytes = (int) filesize($packageUploadPath);
            } elseif ($uploadPath !== '' && is_file($uploadPath)) {
                $sizeBytes = (int) filesize($uploadPath);
            }
        } elseif ($zipPath !== '' && is_file($zipPath)) {
            $sizeBytes = (int) filesize($zipPath);
        }
    }

    $status = (string) ($job['status'] ?? BANDPROMO_SITE_BACKUP_JOB_PENDING);
    $isPrp = bandpromo_site_backup_is_prp_job($job);
    $isPbf = bandpromo_site_backup_is_pbf_job($job);
    $components = bandpromo_site_backup_job_components($job);
    if ($isPrp) {
        $archiveKind = BANDPROMO_SITE_BACKUP_TYPE_PRP;
    } elseif ($isPbf) {
        $archiveKind = BANDPROMO_SITE_BACKUP_TYPE_PBF;
    } else {
        $archiveKind = bandpromo_site_backup_archive_kind($components);
    }
    $releaseTitle = trim((string) ($job['release_title'] ?? ''));
    $releaseId = trim((string) ($job['release_id'] ?? ''));
    $brandTitle = trim((string) ($job['brand_title'] ?? ''));
    $brandId = trim((string) ($job['brand_id'] ?? ''));
    if ($isPrp) {
        $labelCore = $releaseTitle !== '' ? $releaseTitle : ($releaseId !== '' ? $releaseId : 'campaign');
        $typeLabel = ($direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT ? 'Import PCF · ' : 'PCF · ') . $labelCore;
    } elseif ($isPbf) {
        $labelCore = $brandTitle !== '' ? $brandTitle : ($brandId !== '' ? $brandId : 'brand');
        $typeLabel = ($direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT ? 'Import PBF · ' : 'PBF · ') . $labelCore;
    } else {
        $componentsLabel = bandpromo_site_backup_components_label($components);
        $typeLabel = $direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT
            ? 'Import · ' . $componentsLabel
            : $componentsLabel;
    }

    $downloadPath = $direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT ? $uploadPath : $zipPath;
    $sha256 = strtolower(trim((string) ($job['sha256'] ?? '')));
    // Never hash multi-GB archives during Jobs list — that blocked/killed polls and
    // looked like a restart after packing finished.
    // Package imports are not downloadable archives — never mark SHA pending for them.

    return [
        'id' => $jobId,
        'direction' => $direction,
        'type' => $archiveKind,
        'type_label' => $typeLabel,
        'components' => $components,
        'release_id' => $releaseId,
        'release_title' => $releaseTitle,
        'brand_id' => $brandId,
        'brand_title' => $brandTitle,
        'status' => $status,
        'import_mode' => (string) ($job['import_mode'] ?? ''),
        'source_install_id' => (string) ($job['source_install_id'] ?? ''),
        'import_summary' => (string) ($job['import_summary'] ?? ''),
        'import_followup_href' => (string) ($job['import_followup_href'] ?? ''),
        'import_followup_label' => (string) ($job['import_followup_label'] ?? ''),
        'include_log' => in_array(BANDPROMO_SITE_BACKUP_COMPONENT_LOGS, $components, true),
        'created_at_utc' => (string) ($job['created_at_utc'] ?? ''),
        'started_at_utc' => (string) ($job['started_at_utc'] ?? ''),
        'heartbeat_at_utc' => (string) ($job['heartbeat_at_utc'] ?? ''),
        'completed_at_utc' => (string) ($job['completed_at_utc'] ?? ''),
        'progress' => (string) ($job['progress'] ?? ''),
        'cancel_requested' => !empty($job['cancel_requested']),
        'sha256_pending' => $direction === BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT
            && (
                !empty($job['sha256_pending'])
                || (
                    $status === BANDPROMO_SITE_BACKUP_JOB_READY
                    && $sha256 === ''
                    && $sizeBytes > (64 * 1024 * 1024)
                )
            ),
        'filename' => (string) ($job['filename'] ?? ''),
        'size_bytes' => $sizeBytes,
        'size_label' => bandpromo_site_backup_format_bytes($sizeBytes),
        'sha256' => $sha256,
        'error' => (string) ($job['error'] ?? ''),
        'requested_by' => (string) ($job['requested_by'] ?? ''),
        'download_ready' => $direction === BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT
            && $status === BANDPROMO_SITE_BACKUP_JOB_READY
            && $downloadPath !== ''
            && is_file($downloadPath),
    ];
}

function bandpromo_site_backup_enqueue(string $root, array $components, string $actor): array
{
    $components = bandpromo_site_backup_normalize_components($components);
    $archiveKind = bandpromo_site_backup_archive_kind($components);

    $jobId = bandpromo_site_backup_generate_id($components);
    $job = [
        'id' => $jobId,
        'direction' => BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT,
        'type' => $archiveKind,
        'components' => $components,
        'status' => BANDPROMO_SITE_BACKUP_JOB_PENDING,
        'include_log' => in_array(BANDPROMO_SITE_BACKUP_COMPONENT_LOGS, $components, true),
        'created_at_utc' => gmdate('c'),
        'started_at_utc' => '',
        'completed_at_utc' => '',
        'filename' => bandpromo_site_backup_export_filename($archiveKind, $jobId),
        'size_bytes' => 0,
        'error' => '',
        'requested_by' => $actor,
    ];
    bandpromo_site_backup_write_job($root, $job);

    return bandpromo_site_backup_normalize_job($root, $job);
}

/**
 * Queue a Portable Campaign File (.pcf) export as a background backup job.
 */
function bandpromo_site_backup_enqueue_prp(string $root, string $releaseId, string $actor): array
{
    require_once __DIR__ . '/campaign-storage.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        throw new InvalidArgumentException('Choose a release campaign to export (Primary cannot be exported).');
    }

    $document = bandpromo_campaign_load_document($root, $releaseId);
    $title = trim((string) ($document['title'] ?? $releaseId));
    if ($title === '') {
        $title = $releaseId;
    }

    $suffix = bin2hex(random_bytes(3));
    $stamp = gmdate('Ymd-His');
    $jobId = bandpromo_site_backup_sanitize_job_id('prp-' . $releaseId . '-' . $stamp . '-' . $suffix);
    $filename = 'bandPromo-' . $releaseId . '-' . $stamp . '.pcf';

    $job = [
        'id' => $jobId,
        'direction' => BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT,
        'type' => BANDPROMO_SITE_BACKUP_TYPE_PRP,
        'release_id' => $releaseId,
        'release_title' => $title,
        'components' => [],
        'status' => BANDPROMO_SITE_BACKUP_JOB_PENDING,
        'include_log' => false,
        'created_at_utc' => gmdate('c'),
        'started_at_utc' => '',
        'completed_at_utc' => '',
        'filename' => $filename,
        'size_bytes' => 0,
        'error' => '',
        'requested_by' => $actor,
    ];
    bandpromo_site_backup_write_job($root, $job);

    return bandpromo_site_backup_normalize_job($root, $job);
}

/**
 * Queue a Portable Brand File (.pbf) export as a background backup job.
 */
function bandpromo_site_backup_enqueue_pbf(string $root, string $brandId, string $actor): array
{
    require_once __DIR__ . '/brand-storage.php';

    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '') {
        throw new InvalidArgumentException('Choose a brand to export.');
    }
    if (!is_file(bandpromo_brand_document_path($root, $brandId))) {
        throw new InvalidArgumentException('Brand was not found.');
    }

    $document = bandpromo_brand_load_document($root, $brandId);
    $title = trim((string) ($document['title'] ?? $brandId));
    if ($title === '') {
        $title = $brandId;
    }

    $suffix = bin2hex(random_bytes(3));
    $stamp = gmdate('Ymd-His');
    // Job id stays on the stable storage id; download name uses the operator title.
    $safeBrandId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $brandId) ?: 'brand';
    $safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $title) ?: '';
    $safeTitle = trim((string) $safeTitle, '-');
    if ($safeTitle === '') {
        $safeTitle = $safeBrandId;
    }
    $jobId = bandpromo_site_backup_sanitize_job_id('pbf-' . $safeBrandId . '-' . $stamp . '-' . $suffix);
    $filename = 'bandPromo-brand-' . $safeTitle . '-' . $stamp . '.pbf';

    $job = [
        'id' => $jobId,
        'direction' => BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT,
        'type' => BANDPROMO_SITE_BACKUP_TYPE_PBF,
        'brand_id' => $brandId,
        'brand_title' => $title,
        'components' => [],
        'status' => BANDPROMO_SITE_BACKUP_JOB_PENDING,
        'include_log' => false,
        'created_at_utc' => gmdate('c'),
        'started_at_utc' => '',
        'completed_at_utc' => '',
        'filename' => $filename,
        'size_bytes' => 0,
        'error' => '',
        'requested_by' => $actor,
    ];
    bandpromo_site_backup_write_job($root, $job);

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_acquire_build_lock(string $root)
{
    $path = bandpromo_site_backup_lock_path($root);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        return false;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        return false;
    }

    return $handle;
}

function bandpromo_site_backup_campaign_build_lock($handle): void
{
    if (!is_resource($handle)) {
        return;
    }

    flock($handle, LOCK_UN);
    fclose($handle);
}

function bandpromo_site_backup_find_next_pending(string $root): ?array
{
    $pending = [];
    foreach (bandpromo_site_backup_list_jobs($root) as $job) {
        if (($job['status'] ?? '') === BANDPROMO_SITE_BACKUP_JOB_PENDING) {
            $pending[] = $job;
        }
    }

    if ($pending === []) {
        return null;
    }

    usort($pending, static function (array $a, array $b): int {
        return strcmp((string) ($a['created_at_utc'] ?? ''), (string) ($b['created_at_utc'] ?? ''));
    });

    return bandpromo_site_backup_read_job($root, (string) $pending[0]['id']);
}

function bandpromo_site_backup_process_pending(string $root): bool
{
    $lock = bandpromo_site_backup_acquire_build_lock($root);
    if ($lock === false) {
        return false;
    }

    try {
        $pending = bandpromo_site_backup_find_next_pending($root);
        if ($pending === null) {
            return false;
        }

        bandpromo_site_backup_run_job($root, (string) $pending['id']);

        return true;
    } finally {
        bandpromo_site_backup_campaign_build_lock($lock);
    }
}

function bandpromo_site_backup_run_job(string $root, string $jobId): array
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup job was not found.');
    }

    if (bandpromo_site_backup_job_direction($job) === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT) {
        if (bandpromo_site_backup_is_prp_job($job) || bandpromo_site_backup_is_pbf_job($job)) {
            return bandpromo_site_backup_run_package_import_job($root, $jobId);
        }

        return bandpromo_site_backup_run_import_job($root, $jobId);
    }

    if (bandpromo_site_backup_is_prp_job($job)) {
        return bandpromo_site_backup_run_prp_job($root, $jobId);
    }

    if (bandpromo_site_backup_is_pbf_job($job)) {
        return bandpromo_site_backup_run_pbf_job($root, $jobId);
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        return bandpromo_site_backup_normalize_job($root, $job);
    }

    @set_time_limit(0);
    ignore_user_abort(true);

    $components = bandpromo_site_backup_job_components($job);
    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);

    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_BUILDING;
    $job['started_at_utc'] = gmdate('c');
    $job['heartbeat_at_utc'] = $job['started_at_utc'];
    $job['progress'] = 'Starting site backup…';
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    try {
        $jobIdForProgress = $jobId;
        bandpromo_site_backup_create_archive(
            $root,
            $components,
            $zipPath,
            bandpromo_site_backup_job_progress_callback($root, $jobIdForProgress)
        );
        bandpromo_site_backup_throw_if_cancelled($root, $jobId);
        bandpromo_site_backup_mark_job_ready($root, $job, $zipPath);
    } catch (Throwable $e) {
        bandpromo_site_backup_mark_job_failed($root, $job, $zipPath, $e->getMessage());
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_run_prp_job(string $root, string $jobId): array
{
    return bandpromo_site_backup_advance_package_job($root, $jobId, 'prp');
}

function bandpromo_site_backup_run_pbf_job(string $root, string $jobId): array
{
    return bandpromo_site_backup_advance_package_job($root, $jobId, 'pbf');
}

/**
 * Advance one time-budgeted slice of a PCF/PBF export (shared-host safe).
 */
function bandpromo_site_backup_advance_package_job(string $root, string $jobId, string $kind): array
{
    if ($kind === 'prp') {
        require_once __DIR__ . '/campaign-package.php';
        require_once __DIR__ . '/campaign-storage.php';
    } else {
        require_once __DIR__ . '/brand-package.php';
        require_once __DIR__ . '/brand-storage.php';
    }
    require_once __DIR__ . '/chunked-upload.php';
    require_once __DIR__ . '/http-stream.php';
    require_once __DIR__ . '/json-file-helpers.php';

    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Export job was not found.');
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        return bandpromo_site_backup_normalize_job($root, $job);
    }

    @set_time_limit(0);
    ignore_user_abort(true);

    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    $progress = bandpromo_site_backup_job_progress_callback($root, $jobId);
    $deadline = microtime(true) + BANDPROMO_SITE_BACKUP_SLICE_SECONDS;

    if ($status === BANDPROMO_SITE_BACKUP_JOB_PENDING || trim((string) ($job['started_at_utc'] ?? '')) === '') {
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_BUILDING;
        $job['started_at_utc'] = gmdate('c');
        $job['heartbeat_at_utc'] = $job['started_at_utc'];
        $job['progress'] = $kind === 'prp'
            ? 'Starting Portable Campaign File export…'
            : 'Starting Portable Brand File export…';
        $job['error'] = '';
        bandpromo_site_backup_write_job($root, $job);
    }

    try {
        bandpromo_site_backup_throw_if_cancelled($root, $jobId);
        $plan = bandpromo_site_backup_read_job_plan($root, $jobId);
        if ($plan === null) {
            $workdir = bandpromo_site_backup_job_work_dir($root, $jobId);
            if ($kind === 'prp') {
                $releaseId = bandpromo_campaign_normalize_id((string) ($job['release_id'] ?? ''));
                if ($releaseId === '') {
                    throw new RuntimeException('PCF export job is missing release_id.');
                }
                $prepared = bandpromo_campaign_export_prepare($root, $releaseId, $workdir, $progress);
                $paths = $prepared['paths'];
                $plan = [
                    'version' => 1,
                    'kind' => 'prp',
                    'phase' => 'checksum',
                    'workdir' => $workdir,
                    'release_id' => $releaseId,
                    'title' => (string) ($prepared['title'] ?? $releaseId),
                    'platform_demo' => !empty($prepared['platform_demo']),
                    'asset_ids' => $prepared['asset_ids'],
                    'paths' => $paths,
                    'path_order' => array_keys($paths),
                    'digests' => [],
                    'checksum_index' => 0,
                    'pack_index' => 0,
                    'pack_order' => [],
                    'pack_map' => [],
                    'manifest_name' => 'release-package-manifest.json',
                ];
            } else {
                $brandId = bandpromo_brand_canonical_id((string) ($job['brand_id'] ?? ''));
                if ($brandId === '') {
                    throw new RuntimeException('PBF export job is missing brand_id.');
                }
                $prepared = bandpromo_brand_export_prepare($root, $brandId, $workdir, $progress);
                $paths = $prepared['paths'];
                $plan = [
                    'version' => 1,
                    'kind' => 'pbf',
                    'phase' => 'checksum',
                    'workdir' => $workdir,
                    'brand_id' => $brandId,
                    'title' => (string) ($prepared['title'] ?? $brandId),
                    'asset_ids' => $prepared['asset_ids'],
                    'paths' => $paths,
                    'path_order' => array_keys($paths),
                    'digests' => [],
                    'checksum_index' => 0,
                    'pack_index' => 0,
                    'pack_order' => [],
                    'pack_map' => [],
                    'manifest_name' => 'brand-package-manifest.json',
                ];
            }
            bandpromo_site_backup_write_job_plan($root, $jobId, $plan);
            $progress('Checksum starting…');
        }

        $phase = (string) ($plan['phase'] ?? 'checksum');
        if ($phase === 'finalize') {
            $fresh = bandpromo_site_backup_read_job($root, $jobId);
            if (!is_array($fresh)) {
                throw new RuntimeException('Export job was removed while finishing.');
            }
            if (is_file($zipPath) && filesize($zipPath) > 0) {
                bandpromo_site_backup_mark_job_ready($root, $fresh, $zipPath);
                bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);

                return bandpromo_site_backup_normalize_job($root, $fresh);
            }
            $plan['phase'] = 'pack';
            $plan['pack_index'] = 0;
            bandpromo_site_backup_write_job_plan($root, $jobId, $plan);
            $phase = 'pack';
        }

        if ($phase === 'checksum') {
            $pathOrder = array_values((array) ($plan['path_order'] ?? []));
            $paths = (array) ($plan['paths'] ?? []);
            $digests = is_array($plan['digests'] ?? null) ? $plan['digests'] : [];
            $checksumIndex = (int) ($plan['checksum_index'] ?? 0);
            $done = bandpromo_transfer_file_digests_slice(
                $pathOrder,
                $paths,
                $digests,
                $checksumIndex,
                $deadline,
                $progress
            );
            $plan['digests'] = $digests;
            $plan['checksum_index'] = $checksumIndex;
            if (!$done) {
                bandpromo_site_backup_write_job_plan($root, $jobId, $plan);

                return bandpromo_site_backup_normalize_job($root, bandpromo_site_backup_read_job($root, $jobId) ?? $job);
            }

            $workdir = (string) ($plan['workdir'] ?? bandpromo_site_backup_job_work_dir($root, $jobId));
            $manifestName = (string) ($plan['manifest_name'] ?? 'release-package-manifest.json');
            if (($plan['kind'] ?? '') === 'pbf') {
                $manifest = [
                    'brand_export_version' => BANDPROMO_BRAND_EXPORT_VERSION,
                    'format' => 'pbf',
                    'brand_id' => (string) ($plan['brand_id'] ?? ''),
                    'title' => (string) ($plan['title'] ?? ''),
                    'exported_at' => gmdate('c'),
                    'bandpromo_version' => bandpromo_campaign_bandpromo_version($root),
                    'asset_ids' => array_values((array) ($plan['asset_ids'] ?? [])),
                    'paths' => array_keys($paths),
                    'file_digests' => $digests,
                ];
            } else {
                $manifest = [
                    'release_export_version' => BANDPROMO_CAMPAIGN_EXPORT_VERSION,
                    'format' => 'pcf',
                    'release_id' => (string) ($plan['release_id'] ?? ''),
                    'title' => (string) ($plan['title'] ?? ''),
                    'platform_demo' => !empty($plan['platform_demo']),
                    'bandpromo_version' => bandpromo_campaign_bandpromo_version($root),
                    'paths' => array_keys($paths),
                    'file_digests' => $digests,
                    'asset_ids' => array_values((array) ($plan['asset_ids'] ?? [])),
                    'exported_at' => gmdate('c'),
                ];
            }
            $manifestPath = $workdir . DIRECTORY_SEPARATOR . $manifestName;
            if (!bandpromo_json_write_file($manifestPath, $manifest)) {
                throw new RuntimeException('Could not write package manifest.');
            }
            $packMap = array_merge([$manifestName => $manifestPath], $paths);
            $plan['phase'] = 'pack';
            $plan['pack_map'] = $packMap;
            // Smallest files first — avoids appending tiny JPG/JSON onto a multi-GB zip one-by-one.
            $plan['pack_order'] = bandpromo_transfer_zip_entry_order_by_size_asc($packMap);
            $plan['pack_index'] = 0;
            $plan['pack_sorted_by_size'] = true;
            $plan['manifest_path'] = $manifestPath;
            bandpromo_site_backup_write_job_plan($root, $jobId, $plan);
            $progress('Writing archive (smallest files first)…');
            $phase = 'pack';
        }

        if ($phase === 'pack') {
            if (microtime(true) >= $deadline) {
                bandpromo_site_backup_write_job_plan($root, $jobId, $plan);

                return bandpromo_site_backup_normalize_job($root, bandpromo_site_backup_read_job($root, $jobId) ?? $job);
            }
            $packMap = (array) ($plan['pack_map'] ?? []);
            $packOrder = array_values((array) ($plan['pack_order'] ?? []));
            $packIndex = (int) ($plan['pack_index'] ?? 0);
            // Mid-flight plans from older builds: re-order only the remaining files.
            if (empty($plan['pack_sorted_by_size']) && $packMap !== []) {
                $doneRels = array_slice($packOrder, 0, max(0, $packIndex));
                $remainingMap = [];
                foreach (array_slice($packOrder, max(0, $packIndex)) as $rel) {
                    $rel = str_replace('\\', '/', (string) $rel);
                    if ($rel !== '' && isset($packMap[$rel])) {
                        $remainingMap[$rel] = $packMap[$rel];
                    }
                }
                foreach ($packMap as $rel => $abs) {
                    if (!isset($remainingMap[$rel]) && !in_array($rel, $doneRels, true)) {
                        $remainingMap[$rel] = $abs;
                    }
                }
                $packOrder = array_merge($doneRels, bandpromo_transfer_zip_entry_order_by_size_asc($remainingMap));
                $plan['pack_order'] = $packOrder;
                $plan['pack_sorted_by_size'] = true;
                bandpromo_site_backup_write_job_plan($root, $jobId, $plan);
                $progress('Re-ordered remaining files (smallest first)…');
            }
            $done = bandpromo_transfer_zip_pack_entries_slice(
                $zipPath,
                $packOrder,
                $packMap,
                $packIndex,
                $deadline,
                $progress,
                'Archiving'
            );
            $plan['pack_index'] = $packIndex;
            if (!$done) {
                bandpromo_site_backup_write_job_plan($root, $jobId, $plan);

                return bandpromo_site_backup_normalize_job($root, bandpromo_site_backup_read_job($root, $jobId) ?? $job);
            }

            bandpromo_site_backup_throw_if_cancelled($root, $jobId);
            $fresh = bandpromo_site_backup_read_job($root, $jobId);
            if (!is_array($fresh)) {
                throw new RuntimeException('Export job was removed while finishing.');
            }
            // Mark Ready before cleanup/hash so a host kill cannot restart checksum+pack.
            $plan['phase'] = 'finalize';
            bandpromo_site_backup_write_job_plan($root, $jobId, $plan);
            $progress('Archive packed — marking Ready…');
            bandpromo_site_backup_mark_job_ready($root, $fresh, $zipPath);
            bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);
            $job = $fresh;
        }
    } catch (Throwable $e) {
        $fresh = bandpromo_site_backup_read_job($root, $jobId);
        if (is_array($fresh)) {
            $job = $fresh;
        }
        bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);
        bandpromo_site_backup_mark_job_failed($root, $job, $zipPath, $e->getMessage());
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}

/**
 * Continue sliced PCF/PBF builds from Jobs polling (one slice each).
 */
function bandpromo_site_backup_continue_building_jobs(string $root): void
{
    $dir = bandpromo_site_backup_ensure_dir($root);
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json') || str_ends_with($item, '.plan.json')) {
            continue;
        }
        $jobId = substr($item, 0, -5);
        try {
            $job = bandpromo_site_backup_read_job($root, $jobId);
        } catch (InvalidArgumentException $e) {
            continue;
        }
        if ($job === null) {
            continue;
        }
        $status = (string) ($job['status'] ?? '');
        if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
            continue;
        }
        if (!bandpromo_site_backup_is_prp_job($job) && !bandpromo_site_backup_is_pbf_job($job)) {
            continue;
        }

        $lock = bandpromo_site_backup_acquire_build_lock($root);
        if ($lock === false) {
            return;
        }
        try {
            // Re-read under lock — another slice may have finished it.
            $job = bandpromo_site_backup_read_job($root, $jobId);
            if ($job === null) {
                continue;
            }
            $status = (string) ($job['status'] ?? '');
            if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
                continue;
            }
            bandpromo_site_backup_run_job($root, $jobId);
        } catch (Throwable $e) {
            // Leave job building/failed as run_job recorded; keep polling other jobs.
        } finally {
            bandpromo_site_backup_campaign_build_lock($lock);
        }

        // One slice per poll keeps list-site-backups under shared-host limits.
        return;
    }
}

function bandpromo_site_backup_delete_job(string $root, string $jobId): void
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);
    $metaPath = bandpromo_site_backup_job_meta_path($root, $jobId);
    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    $uploadPath = bandpromo_site_backup_job_upload_path($root, $jobId);
    $job = null;
    try {
        $job = bandpromo_site_backup_read_job($root, $jobId);
    } catch (Throwable $e) {
        $job = null;
    }
    $packageUploadPath = is_array($job) ? trim((string) ($job['package_upload_path'] ?? '')) : '';

    bandpromo_site_backup_cleanup_job_build_artifacts($root, $jobId);
    if ($packageUploadPath !== '' && is_file($packageUploadPath)) {
        @unlink($packageUploadPath);
    }
    if (is_file($uploadPath)) {
        @unlink($uploadPath);
    }
    foreach (['pcf', 'prp', 'pbf'] as $ext) {
        $extra = bandpromo_site_backup_job_package_upload_path($root, $jobId, $ext);
        if (is_file($extra)) {
            @unlink($extra);
        }
    }
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }
    if (is_file($metaPath)) {
        @unlink($metaPath);
    }
}

function bandpromo_site_backup_read_version(string $root): string
{
    $path = $root . '/VERSION';
    if (!is_file($path)) {
        return '';
    }

    return trim((string) file_get_contents($path));
}

function bandpromo_site_backup_install_id(string $root): ?string
{
    $candidates = [
        $root . '/data/install/identity.json',
        $root . '/data/install/id.json',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach (['install_id', 'id', 'uid'] as $key) {
            $value = trim((string) ($decoded[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
    }

    return null;
}

function bandpromo_site_backup_normalize_zip_entry(string $entry): string
{
    $entry = str_replace('\\', '/', $entry);
    $entry = ltrim($entry, '/');
    $parts = [];
    foreach (explode('/', $entry) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            throw new RuntimeException('Invalid backup path segment.');
        }
        $parts[] = $part;
    }

    if ($parts === []) {
        throw new RuntimeException('Backup path is empty.');
    }

    return implode('/', $parts);
}

function bandpromo_site_backup_absolute_path(string $root, string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    if ($relativePath === 'backups' || str_starts_with($relativePath, 'backups/')) {
        throw new RuntimeException('Backup archives are excluded from site backups.');
    }

    $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $rootReal = realpath($root);
    if ($rootReal === false) {
        throw new RuntimeException('Site root is unavailable.');
    }

    if (is_file($absolute) || is_dir($absolute)) {
        $resolved = realpath($absolute);
        if ($resolved === false || !str_starts_with($resolved, $rootReal)) {
            throw new RuntimeException('Backup path escapes site root: ' . $relativePath);
        }
    }

    return $absolute;
}

function bandpromo_site_backup_add_file(ZipArchive $zip, string $root, string $relativePath): void
{
    $absolute = bandpromo_site_backup_absolute_path($root, $relativePath);
    if (!is_file($absolute)) {
        return;
    }

    $zip->addFile($absolute, bandpromo_site_backup_normalize_zip_entry($relativePath));
}

function bandpromo_site_backup_add_tree(
    ZipArchive $zip,
    string $root,
    string $relativePath,
    ?array &$digestPaths = null,
    bool $skipMediaDigests = false
): void {
    $trackDigests = $digestPaths !== null;
    $absolute = bandpromo_site_backup_absolute_path($root, $relativePath);
    if (is_file($absolute)) {
        bandpromo_site_backup_add_file($zip, $root, $relativePath);
        if ($trackDigests) {
            $norm = bandpromo_site_backup_normalize_zip_entry($relativePath);
            if (!$skipMediaDigests || !str_starts_with($norm, 'media/')) {
                $digestPaths[$norm] = $absolute;
            }
        }

        return;
    }

    if (!is_dir($absolute)) {
        return;
    }

    $prefix = rtrim(str_replace('\\', '/', $relativePath), '/');
    $skipDigests = !$trackDigests || ($skipMediaDigests && ($prefix === 'media' || str_starts_with($prefix, 'media/')));
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $local = substr($fileInfo->getPathname(), strlen($absolute) + 1);
        $local = str_replace('\\', '/', $local);
        $zipEntry = bandpromo_site_backup_normalize_zip_entry($prefix . '/' . $local);
        $zip->addFile($fileInfo->getPathname(), $zipEntry);
        if (!$skipDigests) {
            $digestPaths[$zipEntry] = $fileInfo->getPathname();
        }
    }
}

/**
 * @param list<string> $components
 * @return list<string>
 */
function bandpromo_site_backup_paths_for_components(array $components): array
{
    $components = bandpromo_site_backup_normalize_components($components);
    $paths = [];

    if (in_array(BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM, $components, true)) {
        $paths[] = 'web-config.json';
    }
    if (in_array(BANDPROMO_SITE_BACKUP_COMPONENT_DATA, $components, true)) {
        $paths[] = 'data';
    }
    if (in_array(BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA, $components, true)) {
        $paths[] = 'media';
    }
    if (in_array(BANDPROMO_SITE_BACKUP_COMPONENT_LOGS, $components, true)) {
        $paths[] = 'log';
    }

    return $paths;
}

function bandpromo_site_backup_build_manifest(
    string $root,
    array $components,
    array $includedPaths,
    array $fileDigests = []
): array {
    $now = gmdate('c');
    $version = bandpromo_site_backup_read_version($root);
    $installId = bandpromo_site_backup_install_id($root);
    $components = bandpromo_site_backup_normalize_components($components);
    $includesMedia = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA, $components, true);
    $includesLogs = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_LOGS, $components, true);
    $includesPlatform = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM, $components, true);

    if (!$includesMedia) {
        return [
            'format' => BANDPROMO_DATA_EXPORT_FORMAT,
            'export_version' => BANDPROMO_DATA_EXPORT_VERSION,
            'backup_type' => 'data',
            'components' => $components,
            'bandpromo_version' => $version,
            'exported_at_utc' => $now,
            'install_id' => $installId,
            'includes_media' => false,
            'includes_log' => $includesLogs,
            'paths' => $includedPaths,
            'file_digests' => $fileDigests,
            'media_integrity' => 'file_digests',
        ];
    }

    return [
        'format' => BANDPROMO_SITE_BACKUP_FORMAT,
        'backup_version' => BANDPROMO_SITE_BACKUP_VERSION,
        'backup_type' => bandpromo_site_backup_archive_kind($components),
        'components' => $components,
        'bandpromo_version' => $version,
        'exported_at_utc' => $now,
        'install_id' => $installId,
        'includes_log' => $includesLogs,
        'includes_env' => $includesPlatform && is_file($root . '/.env'),
        'includes_media' => true,
        'paths' => $includedPaths,
        'file_digests' => $fileDigests,
        'media_integrity' => 'archive_sha256',
    ];
}

function bandpromo_site_backup_create_archive(
    string $root,
    array $components,
    string $destinationPath,
    ?callable $onProgress = null
): void {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }

    $components = bandpromo_site_backup_normalize_components($components);
    $paths = bandpromo_site_backup_paths_for_components($components);
    $includedPaths = [];
    $digestPaths = [];
    $includesPlatform = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM, $components, true);
    $includesMedia = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA, $components, true);

    $zip = new ZipArchive();
    $openResult = $zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
        throw new RuntimeException('Could not open backup archive for writing.');
    }

    try {
        require_once __DIR__ . '/chunked-upload.php';
        $pathCount = count($paths);
        foreach ($paths as $index => $relativePath) {
            if ($onProgress !== null) {
                $onProgress('Packing ' . ($index + 1) . '/' . $pathCount . ': ' . $relativePath);
            }
            bandpromo_site_backup_add_tree($zip, $root, $relativePath, $digestPaths, $includesMedia);
            $includedPaths[] = str_replace('\\', '/', $relativePath);
        }

        if ($includesPlatform && is_file($root . '/.env')) {
            if ($onProgress !== null) {
                $onProgress('Packing .env');
            }
            bandpromo_site_backup_add_file($zip, $root, '.env');
            $includedPaths[] = '.env';
            $digestPaths['.env'] = $root . DIRECTORY_SEPARATOR . '.env';
        }

        $fileDigests = bandpromo_transfer_file_digests($digestPaths, $onProgress);
        $manifest = bandpromo_site_backup_build_manifest($root, $components, $includedPaths, $fileDigests);
        $manifestName = $includesMedia ? 'backup-manifest.json' : 'data-export-manifest.json';
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Could not encode backup manifest.');
        }
        if ($onProgress !== null) {
            $onProgress('Writing manifest…');
        }
        $zip->addFromString($manifestName, $encoded . "\n");
    } catch (Throwable $e) {
        $zip->close();
        if (is_file($destinationPath)) {
            @unlink($destinationPath);
        }
        throw $e;
    }

    if ($onProgress !== null) {
        $onProgress('Closing archive…');
    }
    if (!$zip->close()) {
        if (is_file($destinationPath)) {
            @unlink($destinationPath);
        }
        throw new RuntimeException('Could not finalize backup archive.');
    }

    if (!is_file($destinationPath) || filesize($destinationPath) === 0) {
        if (is_file($destinationPath)) {
            @unlink($destinationPath);
        }
        throw new RuntimeException('Backup archive was empty. Check that runtime files exist on this install.');
    }
}

function bandpromo_site_backup_export_filename(string $archiveKind, ?string $jobId = null): string
{
    if (is_string($jobId) && $jobId !== '') {
        $safeId = preg_replace('/[^A-Za-z0-9._-]+/', '-', $jobId) ?? $jobId;
        if ($archiveKind === 'data') {
            return 'bandpromo-data-export-' . $safeId . '.zip';
        }
        if ($archiveKind === 'custom') {
            return 'bandpromo-backup-' . $safeId . '.zip';
        }

        return 'bandpromo-site-backup-' . $safeId . '.zip';
    }

    $stamp = gmdate('Ymd-His') . 'Z';
    if ($archiveKind === 'data') {
        return 'bandpromo-data-export-' . $stamp . '.zip';
    }
    if ($archiveKind === 'custom') {
        return 'bandpromo-backup-' . $stamp . '.zip';
    }

    return 'bandpromo-site-backup-' . $stamp . '.zip';
}

function bandpromo_site_backup_status(string $root): array
{
    bandpromo_site_backup_ensure_dir($root);

    return [
        'zip_available' => class_exists('ZipArchive'),
        'bandpromo_version' => bandpromo_site_backup_read_version($root),
        'has_web_config' => is_file($root . '/web-config.json'),
        'has_env' => is_file($root . '/.env'),
        'has_data' => is_dir($root . '/data'),
        'has_media' => is_dir($root . '/media'),
        'has_log' => is_dir($root . '/log'),
        'backup_dir' => 'backups/',
        // Do not nest list_jobs here — callers already list, and nested continue killed list responses on limited hosts.
    ];
}

function bandpromo_site_backup_stream_file(string $path, string $downloadName, string $sha256 = ''): void
{
    require_once __DIR__ . '/http-stream.php';

    bandpromo_http_stream_file($path, $downloadName, [
        'sha256' => $sha256,
        'exit' => true,
    ]);
}

function bandpromo_site_backup_assert_archive_readable(string $zipPath): void
{
    if (!is_file($zipPath) || filesize($zipPath) === 0) {
        throw new RuntimeException('Archive is missing or empty after packing.');
    }
    if (!class_exists('ZipArchive')) {
        return;
    }
    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status !== true) {
        $code = is_int($status) ? (string) $status : 'unknown';
        throw new RuntimeException(
            'Archive is not a readable zip after packing (status ' . $code . '). Export failed — try again.'
        );
    }
    $count = (int) $zip->numFiles;
    $zip->close();
    if ($count < 1) {
        throw new RuntimeException('Archive has no files after packing.');
    }
}

function bandpromo_site_backup_mark_job_ready(string $root, array &$job, string $zipPath): void
{
    require_once __DIR__ . '/http-stream.php';

    $jobId = (string) ($job['id'] ?? '');
    if ($jobId !== '') {
        $fresh = bandpromo_site_backup_read_job($root, $jobId);
        if (is_array($fresh) && !empty($fresh['cancel_requested'])) {
            bandpromo_site_backup_mark_job_failed($root, $job, $zipPath, 'Cancelled by operator.');

            return;
        }
    }

    $sizeBytes = is_file($zipPath) ? (int) filesize($zipPath) : 0;
    try {
        bandpromo_site_backup_assert_archive_readable($zipPath);
    } catch (Throwable $e) {
        bandpromo_site_backup_mark_job_failed($root, $job, $zipPath, $e->getMessage());

        return;
    }

    // Commit Ready immediately. Hashing a multi-GB PCF after pack used to run for
    // minutes; host kill left status=building with no plan → full export restart.
    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_READY;
    $job['completed_at_utc'] = gmdate('c');
    $job['heartbeat_at_utc'] = $job['completed_at_utc'];
    $job['progress'] = '';
    $job['cancel_requested'] = false;
    $job['size_bytes'] = $sizeBytes;
    $job['sha256'] = '';
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    // Best-effort digest only for modest archives (download still works without it).
    $maxInlineShaBytes = 64 * 1024 * 1024;
    if ($sizeBytes > 0 && $sizeBytes <= $maxInlineShaBytes && is_file($zipPath)) {
        $sha = bandpromo_transfer_sha256_file($zipPath);
        if ($sha !== '') {
            $job['sha256'] = $sha;
            $job['sha256_pending'] = false;
            $job['heartbeat_at_utc'] = gmdate('c');
            try {
                bandpromo_site_backup_write_job($root, $job);
            } catch (Throwable $e) {
                // Ready already committed.
            }
        }
    } elseif ($sizeBytes > $maxInlineShaBytes) {
        $job['sha256_pending'] = true;
        $job['progress'] = 'Checksumming archive…';
        try {
            bandpromo_site_backup_write_job($root, $job);
        } catch (Throwable $e) {
            // Ready already committed.
        }
        bandpromo_site_backup_spawn_archive_sha($root, (string) ($job['id'] ?? ''));
    }
}

/**
 * Start a CLI worker to SHA-256 a large Ready archive without blocking FPM.
 */
function bandpromo_site_backup_spawn_archive_sha(string $root, string $jobId): bool
{
    $jobId = trim($jobId);
    if ($jobId === '') {
        return false;
    }

    require_once __DIR__ . '/light-build-tasks.php';
    require_once __DIR__ . '/build-launcher.php';

    $php = bandpromo_resolve_php_cli();
    if ($php === '') {
        return false;
    }

    $script = __DIR__ . DIRECTORY_SEPARATOR . 'compute-site-backup-sha.php';
    if (!is_file($script)) {
        return false;
    }

    $lockPath = bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.sha.lock';
    if (is_file($lockPath)) {
        $age = time() - (int) filemtime($lockPath);
        if ($age >= 0 && $age < 7200) {
            return true;
        }
        @unlink($lockPath);
    }
    @file_put_contents($lockPath, gmdate('c'), LOCK_EX);

    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $phpArg = $php;
    $scriptArg = $script;
    $rootArg = $root;
    $jobArg = $jobId;

    if ($isWindows) {
        if (!function_exists('popen') && !bandpromo_can_proc_open()) {
            @unlink($lockPath);

            return false;
        }
        $cmd = 'start /B "" '
            . escapeshellarg($phpArg)
            . ' -d max_execution_time=0 '
            . escapeshellarg($scriptArg)
            . ' --job-id=' . escapeshellarg($jobArg)
            . ' --root=' . escapeshellarg($rootArg);
        $handle = @popen($cmd, 'r');
        if ($handle === false) {
            @unlink($lockPath);

            return false;
        }
        @pclose($handle);

        return true;
    }

    if (!function_exists('exec') && !bandpromo_can_proc_open()) {
        @unlink($lockPath);

        return false;
    }

    $cmd = escapeshellarg($phpArg)
        . ' -d max_execution_time=0 '
        . escapeshellarg($scriptArg)
        . ' --job-id=' . escapeshellarg($jobArg)
        . ' --root=' . escapeshellarg($rootArg)
        . ' > /dev/null 2>&1 &';
    @exec($cmd);

    return true;
}

/**
 * Ensure large Ready jobs without SHA keep a background hasher running.
 */
function bandpromo_site_backup_continue_archive_sha_jobs(string $root): void
{
    $dir = bandpromo_site_backup_ensure_dir($root);
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json') || str_ends_with($item, '.plan.json')) {
            continue;
        }
        $jobId = substr($item, 0, -5);
        try {
            $job = bandpromo_site_backup_read_job($root, $jobId);
        } catch (InvalidArgumentException $e) {
            continue;
        }
        if ($job === null) {
            continue;
        }
        if ((string) ($job['status'] ?? '') !== BANDPROMO_SITE_BACKUP_JOB_READY) {
            continue;
        }
        if (strtolower(trim((string) ($job['sha256'] ?? ''))) !== '') {
            $lockPath = $dir . '/' . $jobId . '.sha.lock';
            if (is_file($lockPath)) {
                @unlink($lockPath);
            }
            if (!empty($job['sha256_pending']) || trim((string) ($job['progress'] ?? '')) !== '') {
                $job['sha256_pending'] = false;
                $job['progress'] = '';
                try {
                    bandpromo_site_backup_write_job($root, $job);
                } catch (Throwable $e) {
                    // Ignore.
                }
            }
            continue;
        }
        $size = (int) ($job['size_bytes'] ?? 0);
        if ($size <= 64 * 1024 * 1024) {
            continue;
        }
        if (empty($job['sha256_pending'])) {
            $job['sha256_pending'] = true;
            $job['progress'] = 'Checksumming archive…';
            try {
                bandpromo_site_backup_write_job($root, $job);
            } catch (Throwable $e) {
                // Ignore.
            }
        }
        bandpromo_site_backup_spawn_archive_sha($root, $jobId);

        return;
    }
}

function bandpromo_site_backup_mark_job_failed(string $root, array &$job, string $zipPath, string $message): void
{
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }
    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_FAILED;
    $job['completed_at_utc'] = gmdate('c');
    $job['heartbeat_at_utc'] = $job['completed_at_utc'];
    $job['progress'] = '';
    $job['size_bytes'] = 0;
    $job['sha256'] = '';
    $job['error'] = $message;
    // Keep cancel_requested so a late worker still refuses mark_ready.
    if (stripos($message, 'Cancelled by operator') !== false) {
        $job['cancel_requested'] = true;
    }
    bandpromo_site_backup_write_job($root, $job);
}

function bandpromo_site_backup_dispatch_job(string $root, string $jobId): void
{
    $lock = bandpromo_site_backup_acquire_build_lock($root);
    if ($lock === false) {
        return;
    }

    try {
        bandpromo_site_backup_run_job($root, $jobId);
    } finally {
        bandpromo_site_backup_campaign_build_lock($lock);
    }
}

/**
 * Close the HTTP response when possible, then run the queued job.
 * Required on PHP built-in / non-FPM hosts where fastcgi_finish_request() is missing —
 * otherwise jobs stay forever on "Importing…" / "Building…".
 */
function bandpromo_site_backup_finish_response_and_dispatch(string $root, string $jobId): void
{
    ignore_user_abort(true);
    @set_time_limit(0);

    if (function_exists('session_write_close')) {
        @session_write_close();
    }

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    bandpromo_site_backup_dispatch_job($root, $jobId);
}

function bandpromo_site_backup_job_direction(array $job): string
{
    $direction = strtolower(trim((string) ($job['direction'] ?? BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT)));

    return $direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT
        ? BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT
        : BANDPROMO_SITE_BACKUP_DIRECTION_EXPORT;
}

function bandpromo_site_backup_staging_dir(string $root): string
{
    return bandpromo_site_backup_ensure_dir($root) . '/.staging';
}

function bandpromo_site_backup_ensure_staging_dir(string $root): string
{
    $dir = bandpromo_site_backup_staging_dir($root);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }

    return $dir;
}

function bandpromo_site_backup_sanitize_staging_id(string $stagingId): string
{
    $stagingId = trim($stagingId);
    if ($stagingId === '' || preg_match('/^[A-Za-z0-9._-]+$/', $stagingId) !== 1) {
        throw new InvalidArgumentException('Invalid staging id.');
    }

    return $stagingId;
}

function bandpromo_site_backup_staging_zip_path(string $root, string $stagingId): string
{
    $stagingId = bandpromo_site_backup_sanitize_staging_id($stagingId);

    return bandpromo_site_backup_ensure_staging_dir($root) . '/' . $stagingId . '.zip';
}

function bandpromo_site_backup_staging_meta_path(string $root, string $stagingId): string
{
    $stagingId = bandpromo_site_backup_sanitize_staging_id($stagingId);

    return bandpromo_site_backup_ensure_staging_dir($root) . '/' . $stagingId . '.json';
}

function bandpromo_site_backup_job_upload_path(string $root, string $jobId): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);

    return bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.upload.zip';
}

function bandpromo_site_backup_job_package_upload_path(string $root, string $jobId, string $extension = 'pcf'): string
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);
    $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', $extension) ?? '');
    if ($extension === '') {
        $extension = 'pcf';
    }

    return bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.upload.' . $extension;
}

/**
 * Queue a Portable Campaign/Brand File import after chunked upload assembled on disk.
 *
 * @param 'prp'|'pbf' $kind
 */
function bandpromo_site_backup_enqueue_package_import(
    string $root,
    string $assembledPath,
    string $filename,
    string $kind,
    string $collision,
    string $actor
): array {
    if (!is_file($assembledPath)) {
        throw new RuntimeException('Assembled package is missing.');
    }
    $kind = strtolower(trim($kind));
    if ($kind !== BANDPROMO_SITE_BACKUP_TYPE_PRP && $kind !== BANDPROMO_SITE_BACKUP_TYPE_PBF) {
        throw new InvalidArgumentException('Package import kind must be PCF or PBF.');
    }

    $extension = $kind === BANDPROMO_SITE_BACKUP_TYPE_PBF ? 'pbf' : 'pcf';
    $jobId = bandpromo_site_backup_sanitize_job_id(
        ($kind === BANDPROMO_SITE_BACKUP_TYPE_PBF ? 'pbf-import-' : 'pcf-import-')
        . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3))
    );
    $uploadPath = bandpromo_site_backup_job_package_upload_path($root, $jobId, $extension);
    if (!rename($assembledPath, $uploadPath)) {
        if (!copy($assembledPath, $uploadPath)) {
            throw new RuntimeException('Could not stage the package for import.');
        }
        @unlink($assembledPath);
    }

    $baseName = basename($filename);
    $title = preg_replace('/\.(pcf|prp|pbf)$/i', '', $baseName) ?? $baseName;
    $job = [
        'id' => $jobId,
        'direction' => BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT,
        'type' => $kind,
        'components' => [],
        'package_collision' => $collision,
        'package_upload_path' => $uploadPath,
        'status' => BANDPROMO_SITE_BACKUP_JOB_PENDING,
        'created_at_utc' => gmdate('c'),
        'started_at_utc' => '',
        'completed_at_utc' => '',
        'filename' => $baseName !== '' ? $baseName : ('package.' . $extension),
        'size_bytes' => is_file($uploadPath) ? (int) filesize($uploadPath) : 0,
        'error' => '',
        'import_summary' => '',
        'progress' => 'Queued package import…',
        'requested_by' => $actor,
    ];
    if ($kind === BANDPROMO_SITE_BACKUP_TYPE_PRP) {
        $job['release_title'] = $title;
    } else {
        $job['brand_title'] = $title;
    }
    bandpromo_site_backup_write_job($root, $job);

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_run_package_import_job(string $root, string $jobId): array
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Package import job was not found.');
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        return bandpromo_site_backup_normalize_job($root, $job);
    }

    @set_time_limit(0);
    ignore_user_abort(true);

    $isPbf = bandpromo_site_backup_is_pbf_job($job);
    $uploadPath = trim((string) ($job['package_upload_path'] ?? ''));
    if ($uploadPath === '' || !is_file($uploadPath)) {
        $uploadPath = bandpromo_site_backup_job_package_upload_path(
            $root,
            $jobId,
            $isPbf ? 'pbf' : 'pcf'
        );
    }
    $collision = trim((string) ($job['package_collision'] ?? 'refuse'));
    $filename = (string) ($job['filename'] ?? ($isPbf ? 'package.pbf' : 'package.pcf'));

    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_BUILDING;
    $job['started_at_utc'] = gmdate('c');
    $job['heartbeat_at_utc'] = $job['started_at_utc'];
    $job['progress'] = $isPbf ? 'Importing Portable Brand File…' : 'Importing Portable Campaign File…';
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    try {
        if (!is_file($uploadPath)) {
            throw new RuntimeException('Uploaded package is missing.');
        }
        // Call package libraries directly — do not include the HTTP entry scripts.
        require_once __DIR__ . '/admin-audit.php';
        if ($isPbf) {
            require_once __DIR__ . '/brand-package.php';
            require_once __DIR__ . '/brand-storage.php';
            $result = bandpromo_brand_import_from_zip($root, $uploadPath, [
                'collision' => $collision,
            ]);
            bandpromo_admin_audit_log('brand_package_imported', [
                'target_type' => 'brand',
                'target_id' => (string) ($result['brand_id'] ?? ''),
                'status' => 'ok',
                'data' => [
                    'imported_files' => (int) ($result['imported_files'] ?? 0),
                    'filename' => $filename,
                    'collision' => (string) ($result['collision'] ?? $collision),
                    'mode' => 'queued_job',
                    'queue_deliverables' => !empty($result['queue_deliverables']),
                ],
            ]);
            $releaseOrBrandId = (string) ($result['brand_id'] ?? '');
            $message = (string) ($result['message'] ?? 'Portable Brand File imported.');
        } else {
            require_once __DIR__ . '/campaign-package.php';
            require_once __DIR__ . '/campaign-storage.php';
            $result = bandpromo_campaign_import_from_zip($root, $uploadPath, [
                'mode' => 'operator',
                'allow_demo_overwrite' => false,
                'set_active_brand' => false,
                'collision' => $collision,
            ]);
            bandpromo_admin_audit_log('release_package_imported', [
                'target_type' => 'release',
                'target_id' => (string) ($result['release_id'] ?? ''),
                'status' => 'ok',
                'data' => [
                    'imported_files' => (int) ($result['imported_files'] ?? 0),
                    'filename' => $filename,
                    'collision' => (string) ($result['collision'] ?? $collision),
                    'mode' => 'queued_job',
                    'queue_deliverables' => !empty($result['queue_deliverables']),
                ],
            ]);
            $releaseOrBrandId = (string) ($result['release_id'] ?? '');
            $message = (string) ($result['message'] ?? 'Portable Campaign File imported.');
        }
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_READY;
        $job['completed_at_utc'] = gmdate('c');
        $job['heartbeat_at_utc'] = $job['completed_at_utc'];
        $job['progress'] = '';
        $job['import_summary'] = $message;
        $followupHref = trim((string) ($result['import_followup_href'] ?? $result['status_href'] ?? ''));
        if ($followupHref === '') {
            require_once __DIR__ . '/site-health-queue-helpers.php';
            $followupHref = bandpromo_site_health_status_href();
        }
        $job['import_followup_href'] = $followupHref;
        $job['import_followup_label'] = trim((string) ($result['import_followup_label'] ?? 'Open Status')) ?: 'Open Status';
        $job['error'] = '';
        if ($isPbf) {
            if ($releaseOrBrandId !== '') {
                $job['brand_id'] = $releaseOrBrandId;
            }
        } elseif ($releaseOrBrandId !== '') {
            $job['release_id'] = $releaseOrBrandId;
        }
        bandpromo_site_backup_write_job($root, $job);
        if (is_file($uploadPath)) {
            @unlink($uploadPath);
        }
    } catch (Throwable $e) {
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_FAILED;
        $job['completed_at_utc'] = gmdate('c');
        $job['heartbeat_at_utc'] = $job['completed_at_utc'];
        $job['progress'] = '';
        $job['error'] = $e->getMessage();
        bandpromo_site_backup_write_job($root, $job);
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_generate_import_id(): string
{
    return gmdate('Ymd-His') . 'Z-' . bin2hex(random_bytes(4)) . '-import';
}

function bandpromo_site_backup_normalize_import_mode(string $mode): string
{
    $mode = strtolower(trim($mode));

    return $mode === BANDPROMO_SITE_IMPORT_MODE_MIGRATE
        ? BANDPROMO_SITE_IMPORT_MODE_MIGRATE
        : BANDPROMO_SITE_IMPORT_MODE_RESTORE;
}

function bandpromo_site_backup_manifest_path_candidates(): array
{
    return [
        'backup-manifest.json',
        'data-export-manifest.json',
    ];
}

function bandpromo_site_backup_read_manifest_from_zip(string $zipPath): ?array
{
    if (!class_exists('ZipArchive') || !is_file($zipPath)) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return null;
    }

    try {
        foreach (bandpromo_site_backup_manifest_path_candidates() as $candidate) {
            $raw = $zip->getFromName($candidate);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    } finally {
        $zip->close();
    }

    return null;
}

/**
 * @return list<string>
 */
function bandpromo_site_backup_infer_components_from_zip(string $zipPath): array
{
    if (!class_exists('ZipArchive') || !is_file($zipPath)) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return [];
    }

    $found = [];
    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }
            $entry = bandpromo_site_backup_normalize_zip_entry((string) $stat['name']);
            if (str_ends_with($entry, '-manifest.json')) {
                continue;
            }
            if ($entry === 'web-config.json' || $entry === '.env') {
                $found[BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM] = true;
            } elseif (str_starts_with($entry, 'data/')) {
                $found[BANDPROMO_SITE_BACKUP_COMPONENT_DATA] = true;
            } elseif (str_starts_with($entry, 'media/')) {
                $found[BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA] = true;
            } elseif (str_starts_with($entry, 'log/')) {
                $found[BANDPROMO_SITE_BACKUP_COMPONENT_LOGS] = true;
            }
        }
    } finally {
        $zip->close();
    }

    $components = array_keys($found);
    sort($components);

    return $components;
}

/**
 * @return list<string>
 */
function bandpromo_site_backup_manifest_components(array $manifest, string $zipPath): array
{
    if (isset($manifest['components']) && is_array($manifest['components'])) {
        try {
            return bandpromo_site_backup_normalize_components($manifest['components']);
        } catch (InvalidArgumentException) {
            // Fall through to inference.
        }
    }

    $inferred = bandpromo_site_backup_infer_components_from_zip($zipPath);
    if ($inferred !== []) {
        return $inferred;
    }

    $components = [
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM,
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA,
    ];
    if (!empty($manifest['includes_media'])) {
        $components[] = BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA;
    }
    if (!empty($manifest['includes_log'])) {
        $components[] = BANDPROMO_SITE_BACKUP_COMPONENT_LOGS;
    }

    return bandpromo_site_backup_normalize_components($components);
}

function bandpromo_site_backup_validate_import_manifest(array $manifest): void
{
    $format = trim((string) ($manifest['format'] ?? ''));
    $allowedFormats = [
        BANDPROMO_SITE_BACKUP_FORMAT,
        BANDPROMO_DATA_EXPORT_FORMAT,
    ];
    if (!in_array($format, $allowedFormats, true)) {
        throw new InvalidArgumentException('This file is not a bandPromo backup archive.');
    }

    if ($format === BANDPROMO_DATA_EXPORT_FORMAT) {
        $version = (int) ($manifest['export_version'] ?? 0);
        if ($version !== BANDPROMO_DATA_EXPORT_VERSION) {
            throw new InvalidArgumentException('This data export version is not supported on this install.');
        }
    } else {
        $version = (int) ($manifest['backup_version'] ?? 0);
        if ($version !== BANDPROMO_SITE_BACKUP_VERSION) {
            throw new InvalidArgumentException('This backup version is not supported on this install.');
        }
    }
}

function bandpromo_site_backup_read_site_url_from_config(string $configPath): string
{
    if (!is_file($configPath)) {
        return '';
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($decoded)) {
        return '';
    }

    foreach (['install.site.url', 'site.url'] as $dotted) {
        $parts = explode('.', $dotted);
        $cursor = $decoded;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                $cursor = null;
                break;
            }
            $cursor = $cursor[$part];
        }
        if (is_string($cursor) && trim($cursor) !== '') {
            return rtrim(trim($cursor), '/');
        }
    }

    return '';
}

function bandpromo_site_backup_read_site_url_from_zip(string $zipPath): string
{
    if (!class_exists('ZipArchive') || !is_file($zipPath)) {
        return '';
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return '';
    }

    try {
        $raw = $zip->getFromName('web-config.json');
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }
        $temp = tempnam(sys_get_temp_dir(), 'bp_cfg_');
        if ($temp === false) {
            return '';
        }
        file_put_contents($temp, $raw);
        $url = bandpromo_site_backup_read_site_url_from_config($temp);
        @unlink($temp);

        return $url;
    } finally {
        $zip->close();
    }
}

function bandpromo_site_backup_current_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host;
}

function bandpromo_site_backup_suggest_import_mode(string $root, array $manifest): string
{
    $sourceInstallId = trim((string) ($manifest['install_id'] ?? ''));
    $localInstallId = bandpromo_site_backup_install_id($root) ?? '';
    if ($sourceInstallId !== '' && $localInstallId !== '' && hash_equals($localInstallId, $sourceInstallId)) {
        return BANDPROMO_SITE_IMPORT_MODE_RESTORE;
    }

    return BANDPROMO_SITE_IMPORT_MODE_MIGRATE;
}

function bandpromo_site_backup_cleanup_staging(string $root): void
{
    $dir = bandpromo_site_backup_staging_dir($root);
    if (!is_dir($dir)) {
        return;
    }

    $now = time();
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json')) {
            continue;
        }
        $metaPath = $dir . '/' . $item;
        $decoded = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($decoded)) {
            continue;
        }
        $expiresAt = strtotime((string) ($decoded['expires_at_utc'] ?? ''));
        if ($expiresAt !== false && $expiresAt < $now) {
            $stagingId = substr($item, 0, -5);
            bandpromo_site_backup_delete_staging($root, $stagingId);
        }
    }
}

function bandpromo_site_backup_delete_staging(string $root, string $stagingId): void
{
    $zipPath = bandpromo_site_backup_staging_zip_path($root, $stagingId);
    $metaPath = bandpromo_site_backup_staging_meta_path($root, $stagingId);
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }
    if (is_file($metaPath)) {
        @unlink($metaPath);
    }
}

function bandpromo_site_backup_read_staging_meta(string $root, string $stagingId): ?array
{
    $path = bandpromo_site_backup_staging_meta_path($root, $stagingId);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_site_backup_stage_uploaded_archive(
    string $root,
    string $sourcePath,
    string $originalFilename
): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }
    if (!is_file($sourcePath) || filesize($sourcePath) === 0) {
        throw new InvalidArgumentException('Upload an archive file first.');
    }

    bandpromo_site_backup_cleanup_staging($root);

    $manifest = bandpromo_site_backup_read_manifest_from_zip($sourcePath);
    if ($manifest === null) {
        $inferred = bandpromo_site_backup_infer_components_from_zip($sourcePath);
        if ($inferred === []) {
            throw new InvalidArgumentException('Could not find a bandPromo backup manifest in this archive.');
        }
        $manifest = [
            'format' => in_array(BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA, $inferred, true)
                ? BANDPROMO_SITE_BACKUP_FORMAT
                : BANDPROMO_DATA_EXPORT_FORMAT,
            'backup_version' => BANDPROMO_SITE_BACKUP_VERSION,
            'export_version' => BANDPROMO_DATA_EXPORT_VERSION,
            'components' => $inferred,
            'install_id' => null,
            'bandpromo_version' => '',
            'exported_at_utc' => '',
        ];
    }

    bandpromo_site_backup_validate_import_manifest($manifest);

    $availableComponents = bandpromo_site_backup_manifest_components($manifest, $sourcePath);
    $stagingId = 'stg_' . bin2hex(random_bytes(8));
    $destination = bandpromo_site_backup_staging_zip_path($root, $stagingId);
    if (!copy($sourcePath, $destination)) {
        throw new RuntimeException('Could not store uploaded archive for import.');
    }

    $localInstallId = bandpromo_site_backup_install_id($root);
    $sourceInstallId = trim((string) ($manifest['install_id'] ?? ''));
    $sourceSiteUrl = bandpromo_site_backup_read_site_url_from_zip($destination);
    $currentOrigin = bandpromo_site_backup_current_request_origin();
    $urlMismatch = $sourceSiteUrl !== '' && $currentOrigin !== '' && !hash_equals($sourceSiteUrl, $currentOrigin);
    $suggestedMode = bandpromo_site_backup_suggest_import_mode($root, $manifest);
    $createdAt = gmdate('c');
    $expiresAt = gmdate('c', time() + BANDPROMO_SITE_BACKUP_STAGING_TTL_SECONDS);

    $meta = [
        'staging_id' => $stagingId,
        'created_at_utc' => $createdAt,
        'expires_at_utc' => $expiresAt,
        'original_filename' => $originalFilename,
        'manifest' => $manifest,
        'available_components' => $availableComponents,
        'source_install_id' => $sourceInstallId,
        'local_install_id' => $localInstallId,
        'same_install' => $sourceInstallId !== '' && $localInstallId !== '' && hash_equals($sourceInstallId, $localInstallId),
        'source_site_url' => $sourceSiteUrl,
        'current_site_url' => $currentOrigin,
        'url_mismatch' => $urlMismatch,
        'suggested_mode' => $suggestedMode,
        'size_bytes' => (int) filesize($destination),
    ];

    $encoded = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        @unlink($destination);
        throw new RuntimeException('Could not save import staging metadata.');
    }
    file_put_contents(bandpromo_site_backup_staging_meta_path($root, $stagingId), $encoded . "\n", LOCK_EX);

    return $meta;
}

/**
 * @return list<string>
 */
function bandpromo_site_backup_identity_preserve_paths(): array
{
    return [
        'data/install/identity.json',
        'data/install/id.json',
    ];
}

function bandpromo_site_backup_capture_preserved_files(string $root, array $relativePaths): array
{
    $captured = [];
    foreach ($relativePaths as $relativePath) {
        $absolute = bandpromo_site_backup_absolute_path($root, $relativePath);
        if (is_file($absolute)) {
            $captured[$relativePath] = (string) file_get_contents($absolute);
        }
    }

    return $captured;
}

function bandpromo_site_backup_restore_preserved_files(string $root, array $captured): void
{
    foreach ($captured as $relativePath => $contents) {
        $absolute = bandpromo_site_backup_absolute_path($root, $relativePath);
        $parent = dirname($absolute);
        if (!is_dir($parent)) {
            mkdir($parent, 0750, true);
        }
        file_put_contents($absolute, $contents, LOCK_EX);
    }
}

function bandpromo_site_backup_zip_entry_component(string $entry): ?string
{
    if ($entry === 'web-config.json' || $entry === '.env') {
        return BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM;
    }
    if (str_starts_with($entry, 'data/')) {
        return BANDPROMO_SITE_BACKUP_COMPONENT_DATA;
    }
    if (str_starts_with($entry, 'media/')) {
        return BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA;
    }
    if (str_starts_with($entry, 'log/')) {
        return BANDPROMO_SITE_BACKUP_COMPONENT_LOGS;
    }

    return null;
}

function bandpromo_site_backup_should_skip_import_entry(string $entry, string $importMode): bool
{
    if (str_ends_with($entry, '-manifest.json')) {
        return true;
    }

    if ($importMode !== BANDPROMO_SITE_IMPORT_MODE_MIGRATE) {
        return false;
    }

    return in_array($entry, bandpromo_site_backup_identity_preserve_paths(), true);
}

function bandpromo_site_backup_repair_site_url(string $root, string $newUrl): bool
{
    $configPath = $root . '/web-config.json';
    if (!is_file($configPath)) {
        return false;
    }

    $config = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($config)) {
        return false;
    }

    $newUrl = rtrim(trim($newUrl), '/');
    if ($newUrl === '') {
        return false;
    }

    if (!isset($config['install']) || !is_array($config['install'])) {
        $config['install'] = [];
    }
    if (!isset($config['install']['site']) || !is_array($config['install']['site'])) {
        $config['install']['site'] = [];
    }
    $config['install']['site']['url'] = $newUrl;

    if (!isset($config['site']) || !is_array($config['site'])) {
        $config['site'] = [];
    }
    $config['site']['url'] = $newUrl;

    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents($configPath, $encoded . "\n", LOCK_EX) !== false;
}

function bandpromo_site_backup_extract_archive(
    string $root,
    string $zipPath,
    array $components,
    string $importMode
): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Import archive is missing.');
    }

    $components = bandpromo_site_backup_normalize_components($components);
    $importMode = bandpromo_site_backup_normalize_import_mode($importMode);
    $preserved = $importMode === BANDPROMO_SITE_IMPORT_MODE_MIGRATE
        ? bandpromo_site_backup_capture_preserved_files($root, bandpromo_site_backup_identity_preserve_paths())
        : [];

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open import archive.');
    }

    $extracted = 0;
    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }

            $entry = bandpromo_site_backup_normalize_zip_entry((string) $stat['name']);
            if (bandpromo_site_backup_should_skip_import_entry($entry, $importMode)) {
                continue;
            }

            $component = bandpromo_site_backup_zip_entry_component($entry);
            if ($component === null || !in_array($component, $components, true)) {
                continue;
            }

            $destination = bandpromo_site_backup_absolute_path($root, $entry);
            $parent = dirname($destination);
            if (!is_dir($parent)) {
                mkdir($parent, 0750, true);
            }

            $stream = $zip->getStream((string) $stat['name']);
            if ($stream === false) {
                throw new RuntimeException('Could not read archive entry: ' . $entry);
            }

            $out = fopen($destination, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new RuntimeException('Could not write restored file: ' . $entry);
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
            $extracted++;
        }
    } finally {
        $zip->close();
    }

    if ($extracted === 0) {
        throw new RuntimeException('No matching files were found in the archive for the selected components.');
    }

    if ($preserved !== []) {
        bandpromo_site_backup_restore_preserved_files($root, $preserved);
    }

    return [
        'extracted_files' => $extracted,
        'preserved_identity' => $preserved !== [],
    ];
}

function bandpromo_site_backup_enqueue_import(
    string $root,
    string $stagingId,
    array $components,
    string $importMode,
    bool $repairSiteUrl,
    string $actor
): array {
    $stagingId = bandpromo_site_backup_sanitize_staging_id($stagingId);
    $meta = bandpromo_site_backup_read_staging_meta($root, $stagingId);
    if ($meta === null) {
        throw new InvalidArgumentException('Upload expired or not found. Choose the archive again.');
    }

    $expiresAt = strtotime((string) ($meta['expires_at_utc'] ?? ''));
    if ($expiresAt !== false && $expiresAt < time()) {
        bandpromo_site_backup_delete_staging($root, $stagingId);
        throw new InvalidArgumentException('Upload expired. Choose the archive again.');
    }

    $available = is_array($meta['available_components'] ?? null) ? $meta['available_components'] : [];
    $components = bandpromo_site_backup_normalize_components($components);
    foreach ($components as $component) {
        if (!in_array($component, $available, true)) {
            throw new InvalidArgumentException('Archive does not include: ' . bandpromo_site_backup_component_label($component) . '.');
        }
    }

    $importMode = bandpromo_site_backup_normalize_import_mode($importMode);
    $stagingZip = bandpromo_site_backup_staging_zip_path($root, $stagingId);
    if (!is_file($stagingZip)) {
        throw new RuntimeException('Staged import archive is missing.');
    }

    $jobId = bandpromo_site_backup_generate_import_id();
    $uploadPath = bandpromo_site_backup_job_upload_path($root, $jobId);
    if (!rename($stagingZip, $uploadPath)) {
        if (!copy($stagingZip, $uploadPath)) {
            throw new RuntimeException('Could not prepare import archive.');
        }
        @unlink($stagingZip);
    }
    bandpromo_site_backup_delete_staging($root, $stagingId);

    $manifest = is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [];
    $job = [
        'id' => $jobId,
        'direction' => BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT,
        'type' => bandpromo_site_backup_archive_kind($components),
        'components' => $components,
        'import_mode' => $importMode,
        'repair_site_url' => $repairSiteUrl,
        'source_install_id' => (string) ($meta['source_install_id'] ?? ''),
        'status' => BANDPROMO_SITE_BACKUP_JOB_PENDING,
        'created_at_utc' => gmdate('c'),
        'started_at_utc' => '',
        'completed_at_utc' => '',
        'filename' => (string) ($meta['original_filename'] ?? 'import.zip'),
        'size_bytes' => is_file($uploadPath) ? (int) filesize($uploadPath) : 0,
        'error' => '',
        'import_summary' => '',
        'requested_by' => $actor,
    ];
    bandpromo_site_backup_write_job($root, $job);

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_run_import_job(string $root, string $jobId): array
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Import job was not found.');
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        return bandpromo_site_backup_normalize_job($root, $job);
    }

    @set_time_limit(0);
    ignore_user_abort(true);

    $components = bandpromo_site_backup_job_components($job);
    $importMode = bandpromo_site_backup_normalize_import_mode((string) ($job['import_mode'] ?? BANDPROMO_SITE_IMPORT_MODE_RESTORE));
    $repairSiteUrl = !empty($job['repair_site_url']);
    $uploadPath = bandpromo_site_backup_job_upload_path($root, $jobId);

    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_BUILDING;
    $job['started_at_utc'] = gmdate('c');
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    try {
        $result = bandpromo_site_backup_extract_archive($root, $uploadPath, $components, $importMode);
        $summaryParts = [
            'Imported ' . (int) ($result['extracted_files'] ?? 0) . ' file(s).',
        ];
        if (!empty($result['preserved_identity'])) {
            $summaryParts[] = 'Kept this site install identity.';
        }
        if ($repairSiteUrl) {
            $origin = bandpromo_site_backup_current_request_origin();
            if ($origin !== '' && bandpromo_site_backup_repair_site_url($root, $origin)) {
                $summaryParts[] = 'Updated site URL to ' . $origin . '.';
            }
        }
        $summaryParts[] = 'Open Status after import if you want to refresh listener-ready files via Site health.';

        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_READY;
        $job['completed_at_utc'] = gmdate('c');
        $job['import_summary'] = implode(' ', $summaryParts);
        $job['error'] = '';
        bandpromo_site_backup_write_job($root, $job);

        if (is_file($uploadPath)) {
            @unlink($uploadPath);
        }
    } catch (Throwable $e) {
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_FAILED;
        $job['completed_at_utc'] = gmdate('c');
        $job['error'] = $e->getMessage();
        bandpromo_site_backup_write_job($root, $job);
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}
