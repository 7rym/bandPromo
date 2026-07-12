<?php
declare(strict_types=1);

require_once __DIR__ . '/activity-store.php';

const BANDPROMO_ACTIVITY_LOG_PACKAGE_FORMAT = 'bandpromo-activity-package';
const BANDPROMO_ACTIVITY_LOG_PACKAGE_VERSION = 1;

function bandpromo_activity_log_require_developer_role(): void
{
    require_once __DIR__ . '/auth.php';
    $role = getUserRole($_SESSION['username'] ?? '');
    if ($role !== 'developer') {
        throw new RuntimeException('Developer access is required for activity log export and import.');
    }
}

function bandpromo_activity_log_store_counts(string $root): array
{
    try {
        $pdo = bandpromo_activity_store_open($root);
    } catch (Throwable $e) {
        return [
            'listener' => 0,
            'audit' => 0,
            'ok' => false,
            'message' => $e->getMessage(),
        ];
    }

    $listener = (int) $pdo->query('SELECT COUNT(*) FROM listener_events')->fetchColumn();
    $audit = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();

    return [
        'listener' => $listener,
        'audit' => $audit,
        'ok' => true,
        'message' => '',
    ];
}

function bandpromo_activity_log_listener_fingerprint(array $normalized): string
{
    return implode('|', [
        (string) ($normalized['ts_utc'] ?? ''),
        (string) ($normalized['username'] ?? ''),
        (string) ($normalized['activity'] ?? ''),
        (string) ($normalized['data_json'] ?? '{}'),
    ]);
}

function bandpromo_activity_log_audit_fingerprint(array $normalized): string
{
    return implode('|', [
        (string) ($normalized['ts_utc'] ?? ''),
        (string) ($normalized['actor'] ?? ''),
        (string) ($normalized['action'] ?? ''),
        (string) ($normalized['target_type'] ?? ''),
        (string) ($normalized['target_id'] ?? ''),
        (string) ($normalized['status'] ?? ''),
        (string) ($normalized['data_json'] ?? '{}'),
    ]);
}

function bandpromo_activity_log_collect_listener_fingerprints(PDO $pdo): array
{
    $fingerprints = [];
    $stmt = $pdo->query('SELECT ts_utc, username, activity, data_json FROM listener_events');
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $fingerprints[bandpromo_activity_log_listener_fingerprint($row)] = true;
    }

    return $fingerprints;
}

function bandpromo_activity_log_collect_audit_fingerprints(PDO $pdo): array
{
    $fingerprints = [];
    $stmt = $pdo->query('SELECT ts_utc, actor, action, target_type, target_id, status, data_json FROM audit_events');
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $fingerprints[bandpromo_activity_log_audit_fingerprint($row)] = true;
    }

    return $fingerprints;
}

function bandpromo_activity_log_export_package(string $root): array
{
    $pdo = bandpromo_activity_store_ensure_ready($root);
    $versionFile = rtrim($root, '/\\') . '/VERSION';
    $installedVersion = is_file($versionFile)
        ? trim((string) file_get_contents($versionFile))
        : 'unknown';

    $listenerStmt = $pdo->query(
        'SELECT ts_utc, timestamp_iso, username, activity, ip, user_agent, data_json
         FROM listener_events
         ORDER BY ts_utc ASC, id ASC'
    );
    $listenerEvents = [];
    while ($row = $listenerStmt->fetch()) {
        if (is_array($row)) {
            $listenerEvents[] = bandpromo_activity_store_listener_row_to_entry($row);
        }
    }

    $auditStmt = $pdo->query(
        'SELECT ts_utc, timestamp_iso, actor, action, target_type, target_id, status, ip, user_agent, data_json
         FROM audit_events
         ORDER BY ts_utc ASC, id ASC'
    );
    $auditEvents = [];
    while ($row = $auditStmt->fetch()) {
        if (is_array($row)) {
            $auditEvents[] = bandpromo_activity_store_audit_row_to_entry($row);
        }
    }

    return [
        'format' => BANDPROMO_ACTIVITY_LOG_PACKAGE_FORMAT,
        'format_version' => BANDPROMO_ACTIVITY_LOG_PACKAGE_VERSION,
        'exported_at_utc' => bandpromo_utc_now_iso(),
        'source_version' => $installedVersion,
        'counts' => [
            'listener' => count($listenerEvents),
            'audit' => count($auditEvents),
        ],
        'listener_events' => $listenerEvents,
        'audit_events' => $auditEvents,
    ];
}

function bandpromo_activity_log_validate_package($package): array
{
    if (!is_array($package)) {
        throw new RuntimeException('Activity log package must be a JSON object.');
    }

    if (($package['format'] ?? '') !== BANDPROMO_ACTIVITY_LOG_PACKAGE_FORMAT) {
        throw new RuntimeException('Unrecognized activity log package format.');
    }

    $version = (int) ($package['format_version'] ?? 0);
    if ($version !== BANDPROMO_ACTIVITY_LOG_PACKAGE_VERSION) {
        throw new RuntimeException('Unsupported activity log package version: ' . $version);
    }

    $listenerEvents = $package['listener_events'] ?? null;
    $auditEvents = $package['audit_events'] ?? null;
    if (!is_array($listenerEvents) || !is_array($auditEvents)) {
        throw new RuntimeException('Activity log package is missing listener_events or audit_events arrays.');
    }

    return [
        'listener_events' => $listenerEvents,
        'audit_events' => $auditEvents,
        'source_version' => trim((string) ($package['source_version'] ?? '')),
        'exported_at_utc' => trim((string) ($package['exported_at_utc'] ?? '')),
    ];
}

function bandpromo_activity_log_import_package(string $root, array $package, string $mode = 'merge'): array
{
    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['merge', 'replace'], true)) {
        throw new RuntimeException('Import mode must be merge or replace.');
    }

    $validated = bandpromo_activity_log_validate_package($package);
    $pdo = bandpromo_activity_store_ensure_ready($root);

    $insertListener = $pdo->prepare(
        'INSERT INTO listener_events (ts_utc, timestamp_iso, username, activity, ip, user_agent, data_json)
         VALUES (:ts_utc, :timestamp_iso, :username, :activity, :ip, :user_agent, :data_json)'
    );
    $insertAudit = $pdo->prepare(
        'INSERT INTO audit_events (ts_utc, timestamp_iso, actor, action, target_type, target_id, status, ip, user_agent, data_json)
         VALUES (:ts_utc, :timestamp_iso, :actor, :action, :target_type, :target_id, :status, :ip, :user_agent, :data_json)'
    );

    $result = [
        'mode' => $mode,
        'listener_imported' => 0,
        'listener_skipped' => 0,
        'listener_invalid' => 0,
        'audit_imported' => 0,
        'audit_skipped' => 0,
        'audit_invalid' => 0,
        'source_version' => $validated['source_version'],
        'exported_at_utc' => $validated['exported_at_utc'],
    ];

    $pdo->beginTransaction();
    try {
        if ($mode === 'replace') {
            $pdo->exec('DELETE FROM listener_events');
            $pdo->exec('DELETE FROM audit_events');
            $pdo->exec('DELETE FROM rollup_hourly');
            $listenerFingerprints = [];
            $auditFingerprints = [];
        } else {
            $listenerFingerprints = bandpromo_activity_log_collect_listener_fingerprints($pdo);
            $auditFingerprints = bandpromo_activity_log_collect_audit_fingerprints($pdo);
        }

        foreach ($validated['listener_events'] as $entry) {
            if (!is_array($entry)) {
                $result['listener_invalid']++;
                continue;
            }
            $normalized = bandpromo_activity_store_normalize_listener_entry($entry);
            if ($normalized === null || $normalized['activity'] === '') {
                $result['listener_invalid']++;
                continue;
            }
            $fingerprint = bandpromo_activity_log_listener_fingerprint($normalized);
            if (isset($listenerFingerprints[$fingerprint])) {
                $result['listener_skipped']++;
                continue;
            }
            $insertListener->execute($normalized);
            $listenerFingerprints[$fingerprint] = true;
            $result['listener_imported']++;
        }

        foreach ($validated['audit_events'] as $entry) {
            if (!is_array($entry)) {
                $result['audit_invalid']++;
                continue;
            }
            $normalized = bandpromo_activity_store_normalize_audit_entry($entry);
            if ($normalized === null || $normalized['action'] === '') {
                $result['audit_invalid']++;
                continue;
            }
            $fingerprint = bandpromo_activity_log_audit_fingerprint($normalized);
            if (isset($auditFingerprints[$fingerprint])) {
                $result['audit_skipped']++;
                continue;
            }
            $insertAudit->execute($normalized);
            $auditFingerprints[$fingerprint] = true;
            $result['audit_imported']++;
        }

        bandpromo_activity_store_rebuild_hourly_rollups($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    bandpromo_activity_store_set_meta($pdo, 'legacy_import_failed', '');

    return $result;
}

function bandpromo_activity_log_export_filename(): string
{
    return 'bandpromo-activity-log-' . gmdate('Ymd-His') . 'Z.json';
}
