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

const BANDPROMO_SITE_IMPORT_MODE_RESTORE = 'restore';
const BANDPROMO_SITE_IMPORT_MODE_MIGRATE = 'migrate';

const BANDPROMO_SITE_BACKUP_STAGING_TTL_SECONDS = 7200;

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
    if (bandpromo_site_backup_is_prp_job($job)) {
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

function bandpromo_site_backup_component_label(string $component): string
{
    return match ($component) {
        BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM => 'Platform',
        BANDPROMO_SITE_BACKUP_COMPONENT_DATA => 'Data',
        BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA => 'Media',
        BANDPROMO_SITE_BACKUP_COMPONENT_LOGS => 'Logs',
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
    $dir = bandpromo_site_backup_ensure_dir($root);
    $jobs = [];
    $items = scandir($dir);
    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if (!str_ends_with($item, '.json')) {
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

function bandpromo_site_backup_normalize_job(string $root, array $job): array
{
    $jobId = (string) ($job['id'] ?? '');
    $direction = bandpromo_site_backup_job_direction($job);
    $zipPath = $jobId !== '' ? bandpromo_site_backup_job_zip_path($root, $jobId) : '';
    $uploadPath = $jobId !== '' ? bandpromo_site_backup_job_upload_path($root, $jobId) : '';
    $sizeBytes = (int) ($job['size_bytes'] ?? 0);
    if ($sizeBytes <= 0) {
        if ($direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT && $uploadPath !== '' && is_file($uploadPath)) {
            $sizeBytes = (int) filesize($uploadPath);
        } elseif ($zipPath !== '' && is_file($zipPath)) {
            $sizeBytes = (int) filesize($zipPath);
        }
    }

    $status = (string) ($job['status'] ?? BANDPROMO_SITE_BACKUP_JOB_PENDING);
    $isPrp = bandpromo_site_backup_is_prp_job($job);
    $components = bandpromo_site_backup_job_components($job);
    $archiveKind = $isPrp ? BANDPROMO_SITE_BACKUP_TYPE_PRP : bandpromo_site_backup_archive_kind($components);
    $releaseTitle = trim((string) ($job['release_title'] ?? ''));
    $releaseId = trim((string) ($job['release_id'] ?? ''));
    if ($isPrp) {
        $labelCore = $releaseTitle !== '' ? $releaseTitle : ($releaseId !== '' ? $releaseId : 'campaign');
        $typeLabel = 'PRP · ' . $labelCore;
    } else {
        $componentsLabel = bandpromo_site_backup_components_label($components);
        $typeLabel = $direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT
            ? 'Import · ' . $componentsLabel
            : $componentsLabel;
    }

    $downloadPath = $direction === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT ? $uploadPath : $zipPath;

    return [
        'id' => $jobId,
        'direction' => $direction,
        'type' => $archiveKind,
        'type_label' => $typeLabel,
        'components' => $components,
        'release_id' => $releaseId,
        'release_title' => $releaseTitle,
        'status' => $status,
        'import_mode' => (string) ($job['import_mode'] ?? ''),
        'source_install_id' => (string) ($job['source_install_id'] ?? ''),
        'import_summary' => (string) ($job['import_summary'] ?? ''),
        'include_log' => in_array(BANDPROMO_SITE_BACKUP_COMPONENT_LOGS, $components, true),
        'created_at_utc' => (string) ($job['created_at_utc'] ?? ''),
        'started_at_utc' => (string) ($job['started_at_utc'] ?? ''),
        'completed_at_utc' => (string) ($job['completed_at_utc'] ?? ''),
        'filename' => (string) ($job['filename'] ?? ''),
        'size_bytes' => $sizeBytes,
        'size_label' => bandpromo_site_backup_format_bytes($sizeBytes),
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
 * Queue a portable release package (.prp) export as a background backup job.
 */
function bandpromo_site_backup_enqueue_prp(string $root, string $releaseId, string $actor): array
{
    require_once __DIR__ . '/release-storage.php';

    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '' || $releaseId === BANDPROMO_RELEASE_DEFAULT_ID) {
        throw new InvalidArgumentException('Choose a release campaign to export (Primary cannot be exported).');
    }

    $document = bandpromo_release_load_document($root, $releaseId);
    $title = trim((string) ($document['title'] ?? $releaseId));
    if ($title === '') {
        $title = $releaseId;
    }

    $suffix = bin2hex(random_bytes(3));
    $stamp = gmdate('Ymd-His');
    $jobId = bandpromo_site_backup_sanitize_job_id('prp-' . $releaseId . '-' . $stamp . '-' . $suffix);
    $filename = 'bandPromo-' . $releaseId . '-' . $stamp . '.prp';

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

function bandpromo_site_backup_release_build_lock($handle): void
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
        bandpromo_site_backup_release_build_lock($lock);
    }
}

function bandpromo_site_backup_run_job(string $root, string $jobId): array
{
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup job was not found.');
    }

    if (bandpromo_site_backup_job_direction($job) === BANDPROMO_SITE_BACKUP_DIRECTION_IMPORT) {
        return bandpromo_site_backup_run_import_job($root, $jobId);
    }

    if (bandpromo_site_backup_is_prp_job($job)) {
        return bandpromo_site_backup_run_prp_job($root, $jobId);
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
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    try {
        bandpromo_site_backup_create_archive($root, $components, $zipPath);
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_READY;
        $job['completed_at_utc'] = gmdate('c');
        $job['size_bytes'] = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        $job['error'] = '';
        bandpromo_site_backup_write_job($root, $job);
    } catch (Throwable $e) {
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_FAILED;
        $job['completed_at_utc'] = gmdate('c');
        $job['size_bytes'] = 0;
        $job['error'] = $e->getMessage();
        bandpromo_site_backup_write_job($root, $job);
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_run_prp_job(string $root, string $jobId): array
{
    require_once __DIR__ . '/release-campaign-package.php';
    require_once __DIR__ . '/release-storage.php';

    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('PRP export job was not found.');
    }

    $status = (string) ($job['status'] ?? '');
    if (!in_array($status, [BANDPROMO_SITE_BACKUP_JOB_PENDING, BANDPROMO_SITE_BACKUP_JOB_BUILDING], true)) {
        return bandpromo_site_backup_normalize_job($root, $job);
    }

    @set_time_limit(0);
    ignore_user_abort(true);

    $releaseId = bandpromo_release_normalize_id((string) ($job['release_id'] ?? ''));
    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);

    $job['status'] = BANDPROMO_SITE_BACKUP_JOB_BUILDING;
    $job['started_at_utc'] = gmdate('c');
    $job['error'] = '';
    bandpromo_site_backup_write_job($root, $job);

    try {
        if ($releaseId === '') {
            throw new RuntimeException('PRP export job is missing release_id.');
        }
        bandpromo_release_campaign_export_to_zip($root, $releaseId, $zipPath);
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_READY;
        $job['completed_at_utc'] = gmdate('c');
        $job['size_bytes'] = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        $job['error'] = '';
        bandpromo_site_backup_write_job($root, $job);
    } catch (Throwable $e) {
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
        $job['status'] = BANDPROMO_SITE_BACKUP_JOB_FAILED;
        $job['completed_at_utc'] = gmdate('c');
        $job['size_bytes'] = 0;
        $job['error'] = $e->getMessage();
        bandpromo_site_backup_write_job($root, $job);
    }

    return bandpromo_site_backup_normalize_job($root, $job);
}

function bandpromo_site_backup_delete_job(string $root, string $jobId): void
{
    $jobId = bandpromo_site_backup_sanitize_job_id($jobId);
    $metaPath = bandpromo_site_backup_job_meta_path($root, $jobId);
    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    $uploadPath = bandpromo_site_backup_job_upload_path($root, $jobId);

    if (is_file($uploadPath)) {
        @unlink($uploadPath);
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

function bandpromo_site_backup_add_tree(ZipArchive $zip, string $root, string $relativePath): void
{
    $absolute = bandpromo_site_backup_absolute_path($root, $relativePath);
    if (is_file($absolute)) {
        bandpromo_site_backup_add_file($zip, $root, $relativePath);

        return;
    }

    if (!is_dir($absolute)) {
        return;
    }

    $prefix = rtrim(str_replace('\\', '/', $relativePath), '/');
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
    array $includedPaths
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
    ];
}

function bandpromo_site_backup_create_archive(string $root, array $components, string $destinationPath): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }

    $components = bandpromo_site_backup_normalize_components($components);
    $paths = bandpromo_site_backup_paths_for_components($components);
    $includedPaths = [];
    $includesPlatform = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_PLATFORM, $components, true);
    $includesMedia = in_array(BANDPROMO_SITE_BACKUP_COMPONENT_MEDIA, $components, true);
    $archiveKind = bandpromo_site_backup_archive_kind($components);

    $zip = new ZipArchive();
    $openResult = $zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
        throw new RuntimeException('Could not open backup archive for writing.');
    }

    try {
        foreach ($paths as $relativePath) {
            bandpromo_site_backup_add_tree($zip, $root, $relativePath);
            $includedPaths[] = str_replace('\\', '/', $relativePath);
        }

        if ($includesPlatform && is_file($root . '/.env')) {
            bandpromo_site_backup_add_file($zip, $root, '.env');
            $includedPaths[] = '.env';
        }

        $manifest = bandpromo_site_backup_build_manifest($root, $components, $includedPaths);
        $manifestName = $includesMedia ? 'backup-manifest.json' : 'data-export-manifest.json';
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Could not encode backup manifest.');
        }
        $zip->addFromString($manifestName, $encoded . "\n");
    } catch (Throwable $e) {
        $zip->close();
        if (is_file($destinationPath)) {
            @unlink($destinationPath);
        }
        throw $e;
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
        'jobs' => bandpromo_site_backup_list_jobs($root),
    ];
}

function bandpromo_site_backup_stream_file(string $path, string $downloadName): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Backup file is missing.');
    }

    $size = filesize($path);
    if ($size === false) {
        throw new RuntimeException('Could not read backup file size.');
    }
    $size = (int) $size;

    $safeName = str_replace(['"', "\r", "\n"], '', $downloadName);
    if ($safeName === '') {
        $safeName = 'bandpromo-package.zip';
    }

    @set_time_limit(0);
    ignore_user_abort(true);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $start = 0;
    $end = max(0, $size - 1);
    $status = 200;
    $rangeHeader = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    if ($rangeHeader !== '' && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches) === 1) {
        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $end = min($end, $size - 1);
        $status = 206;
    }

    $length = ($end - $start) + 1;
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open backup file for download.');
    }

    if ($start > 0 && fseek($handle, $start) !== 0) {
        fclose($handle);
        throw new RuntimeException('Could not seek in backup file for ranged download.');
    }

    http_response_code($status);
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) $length);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: public');
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $remaining = $length;
    $chunkSize = 1024 * 1024; // 1 MiB
    try {
        while ($remaining > 0 && !feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $read = fread($handle, (int) min($chunkSize, $remaining));
            if ($read === false || $read === '') {
                break;
            }
            echo $read;
            $remaining -= strlen($read);
            if (function_exists('flush')) {
                flush();
            }
        }
    } finally {
        fclose($handle);
    }

    // Avoid falling through into JSON error handlers after a partial stream.
    exit;
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
        bandpromo_site_backup_release_build_lock($lock);
    }
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
        $summaryParts[] = 'Open Deliverables after import if you want to refresh listener-ready files.';

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
