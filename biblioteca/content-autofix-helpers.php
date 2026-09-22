<?php
declare(strict_types=1);

require_once __DIR__ . '/template-bootstrap.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/campaign-storage.php';

function bandpromo_content_autofix_step_result(string $id, string $label, array $details = []): array
{
    return array_merge([
        'id' => $id,
        'label' => $label,
        'changed' => 0,
        'skipped' => 0,
        'errors' => [],
        'warnings' => [],
        'items' => [],
    ], $details);
}

function bandpromo_content_autofix_log_path(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'catalog-repair.log';
}

/**
 * @return array{running:bool,mode:string,source:string,step:string,started_unix:float}
 */
function &bandpromo_content_autofix_log_state(): array
{
    static $state = [
        'running' => false,
        'mode' => '',
        'source' => '',
        'step' => '',
        'started_unix' => 0.0,
    ];

    return $state;
}

/**
 * CPU seconds used by this PHP process (Linux getrusage). Falls back to wall clock.
 * Shared hosts enforce max_execution_time as CPU time — wall clock alone is unsafe.
 */
function bandpromo_content_autofix_cpu_seconds_used(): float
{
    if (function_exists('getrusage')) {
        $usage = @getrusage();
        if (is_array($usage)) {
            $user = (float) ($usage['ru_utime.tv_sec'] ?? 0)
                + ((float) ($usage['ru_utime.tv_usec'] ?? 0) / 1000000.0);
            $sys = (float) ($usage['ru_stime.tv_sec'] ?? 0)
                + ((float) ($usage['ru_stime.tv_usec'] ?? 0) / 1000000.0);

            return max(0.0, $user + $sys);
        }
    }

    $state = &bandpromo_content_autofix_log_state();
    $started = (float) ($state['started_unix'] ?? 0.0);
    if ($started <= 0.0) {
        $started = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }

    return max(0.0, microtime(true) - $started);
}

/**
 * Seconds left before php.ini max_execution_time (0 / unlimited → large number).
 */
function bandpromo_content_autofix_seconds_remaining(): float
{
    $max = (float) ini_get('max_execution_time');
    if ($max <= 0) {
        return 3600.0;
    }

    return max(0.0, $max - bandpromo_content_autofix_cpu_seconds_used());
}

/**
 * True when Apply should stop starting heavy steps (leave headroom to finish cleanly).
 * Background CLI / Python-supervised Apply must not yield — web 30s limits do not apply.
 */
function bandpromo_content_autofix_should_yield(float $reserveSeconds = 6.0): bool
{
    $state = &bandpromo_content_autofix_log_state();
    $source = (string) ($state['source'] ?? '');
    if ($source === 'cli' || $source === 'background' || getenv('BANDPROMO_REPAIR_CLI') === '1') {
        return false;
    }

    $max = (float) ini_get('max_execution_time');
    if ($max <= 0) {
        return false;
    }

    return bandpromo_content_autofix_seconds_remaining() <= max(2.0, $reserveSeconds);
}

/**
 * Cooperative stop requested for catalogue Repair (background Apply).
 */
function bandpromo_content_autofix_stop_requested(string $root): bool
{
    require_once __DIR__ . '/job-stop.php';

    return bandpromo_job_stop_requested($root, 'catalog_repair');
}

/**
 * Per-step work budget for Apply on short max_execution_time hosts.
 */
function bandpromo_content_autofix_step_budget_seconds(float $preferred = 8.0): float
{
    $state = &bandpromo_content_autofix_log_state();
    $source = (string) ($state['source'] ?? '');
    if ($source === 'cli' || $source === 'background' || getenv('BANDPROMO_REPAIR_CLI') === '1') {
        // Background jobs are not web-SAPI limited — no artificial short budget.
        return max($preferred, 600.0);
    }

    $max = (float) ini_get('max_execution_time');
    if ($max <= 0) {
        return $preferred;
    }
    $remaining = bandpromo_content_autofix_seconds_remaining();
    if ($remaining <= 5.0) {
        return 0.0;
    }
    // Keep later pipeline steps alive on 30s CPU hosts (HITZ).
    $capped = min($preferred, max(2.0, $max * 0.22));

    return max(2.0, min($capped, $remaining - 5.0));
}

function bandpromo_content_autofix_log_write(string $root, string $line): void
{
    $dir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'log';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }
    $stamp = gmdate('H:i:s');
    @file_put_contents(
        bandpromo_content_autofix_log_path($root),
        $stamp . ' ' . rtrim($line) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function bandpromo_content_autofix_log_begin(string $root, bool $dryRun, string $source = 'manual'): void
{
    $state = &bandpromo_content_autofix_log_state();
    $state['running'] = true;
    $state['mode'] = $dryRun ? 'preview' : 'apply';
    $state['source'] = $source !== '' ? $source : 'manual';
    $state['step'] = 'start';
    $state['started_unix'] = microtime(true);

    $path = bandpromo_content_autofix_log_path($root);
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }
    // Background CLI appends into the log the supervisor already opened; do not wipe.
    if ($state['source'] !== 'cli') {
        @file_put_contents($path, '');
    }

    $maxTime = (string) ini_get('max_execution_time');
    $memory = (string) ini_get('memory_limit');
    bandpromo_content_autofix_log_write($root, '==== Repair catalogue — ' . ($dryRun ? 'Preview' : 'Apply') . ' ====');
    bandpromo_content_autofix_log_write($root, 'Started: ' . gmdate('Y-m-d H:i:s') . ' UTC');
    bandpromo_content_autofix_log_write($root, 'Source: ' . $state['source']);
    bandpromo_content_autofix_log_write($root, 'PHP max_execution_time: ' . ($maxTime !== '' ? $maxTime : 'unknown'));
    bandpromo_content_autofix_log_write($root, 'PHP memory_limit: ' . ($memory !== '' ? $memory : 'unknown'));
    if (!$dryRun && $state['source'] !== 'cli') {
        bandpromo_content_autofix_log_write(
            $root,
            'Note: web Apply is limited on some hosts; use background Apply (Python supervisor) for large catalogues.'
        );
    }
    if (!$dryRun && $state['source'] === 'cli') {
        bandpromo_content_autofix_log_write(
            $root,
            'Note: background Apply — runs until finished or Stop; does not depend on the browser staying open.'
        );
    }

    register_shutdown_function(static function () use ($root): void {
        $state = &bandpromo_content_autofix_log_state();
        if (empty($state['running'])) {
            return;
        }
        $last = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (is_array($last) && in_array((int) ($last['type'] ?? 0), $fatalTypes, true)) {
            $msg = trim((string) ($last['message'] ?? 'fatal error'));
            $file = basename((string) ($last['file'] ?? ''));
            $line = (int) ($last['line'] ?? 0);
            bandpromo_content_autofix_log_write(
                $root,
                '!!!! aborted during ' . ($state['step'] ?: 'unknown')
                . ' — ' . $msg
                . ($file !== '' ? ' (' . $file . ':' . $line . ')' : '')
            );
        } else {
            bandpromo_content_autofix_log_write(
                $root,
                '!!!! request ended during ' . ($state['step'] ?: 'unknown')
                . ' (no PHP fatal recorded — often a host timeout or killed process)'
            );
        }
        $state['running'] = false;
    });
}

function bandpromo_content_autofix_log_step_start(string $root, string $id, string $label): void
{
    $state = &bandpromo_content_autofix_log_state();
    $state['step'] = $id;
    // Re-arm time budget between steps when the host allows set_time_limit.
    if (($state['mode'] ?? '') === 'apply') {
        @set_time_limit(600);
    }
    bandpromo_content_autofix_log_write($root, '> start ' . $id . ' — ' . $label);
}

function bandpromo_content_autofix_log_step_finish(string $root, array $step, float $elapsedMs): void
{
    $id = (string) ($step['id'] ?? 'step');
    $changed = (int) ($step['changed'] ?? 0);
    $skipped = (int) ($step['skipped'] ?? 0);
    $errorCount = count($step['errors'] ?? []);
    $itemCount = count($step['items'] ?? []);
    bandpromo_content_autofix_log_write(
        $root,
        '< done ' . $id
        . ' changed=' . $changed
        . ' skipped=' . $skipped
        . ' errors=' . $errorCount
        . ' items=' . $itemCount
        . ' ' . (int) round($elapsedMs) . 'ms'
    );
    if (!empty($step['errors']) && is_array($step['errors'])) {
        foreach (array_slice($step['errors'], 0, 8) as $error) {
            bandpromo_content_autofix_log_write($root, '  ERROR ' . $id . ': ' . (string) $error);
        }
    }
}

function bandpromo_content_autofix_log_finish(string $root, array $report): void
{
    $state = &bandpromo_content_autofix_log_state();
    $elapsed = '';
    if (!empty($state['started_unix'])) {
        $elapsed = ' elapsed=' . number_format(microtime(true) - (float) $state['started_unix'], 2) . 's';
    }
    $changed = (int) ($report['changed_total'] ?? 0);
    $errorCount = count($report['errors'] ?? []);
    $ok = !empty($report['ok']) && $errorCount === 0;
    bandpromo_content_autofix_log_write(
        $root,
        '==== finished '
        . ($ok ? 'ok' : 'with errors')
        . ' changed_total=' . $changed
        . ' errors=' . $errorCount
        . $elapsed
        . ' ===='
    );
    $state['running'] = false;
    $state['step'] = '';
}

/**
 * One-shot original→master repair for operator Content autofix / Publish recovery.
 * Do not wire new runtime original-directory scans into hot paths (list/play/login).
 */
function bandpromo_content_autofix_materialize_audio_masters(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('materialize_masters', 'Prepare missing audio masters');
    $originalDir = $root . '/media/audio/original';
    if (!is_dir($originalDir)) {
        return $step;
    }

    foreach (scandir($originalDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }

        $master = bandpromo_find_audio_master($root, $entry);
        if (!empty($master['exists'])) {
            $step['skipped']++;
            continue;
        }

        $sourcePath = $originalDir . '/' . $entry;
        $sourceSize = is_file($sourcePath) ? filesize($sourcePath) : false;
        $sameSizeMasters = ($sourceSize !== false && (int) $sourceSize > 0)
            ? bandpromo_asset_registered_audio_masters_with_size($root, (int) $sourceSize)
            : [];

        // After masters-only recover, leftover originals often lack original_filename
        // links. Prefer linking (unique empty match) or skipping over minting duplicates.
        if ($sameSizeMasters !== []) {
            $emptyOriginalMatches = [];
            foreach ($sameSizeMasters as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                if (basename(trim((string) ($asset['original_filename'] ?? ''))) === '') {
                    $emptyOriginalMatches[] = $asset;
                }
            }
            if (count($emptyOriginalMatches) === 1) {
                if ($dryRun) {
                    $step['changed']++;
                    $step['items'][] = $entry . ' → link existing master';
                    continue;
                }
                $linked = bandpromo_asset_link_original_to_unique_empty_master($root, $entry);
                if (is_array($linked)) {
                    $step['changed']++;
                    $step['items'][] = $entry;
                    continue;
                }
            }
            // Ambiguous or already-labelled same-size master: do not create another.
            $step['skipped']++;
            continue;
        }

        if ($dryRun) {
            $step['changed']++;
            $step['items'][] = $entry;
            continue;
        }

        $prepared = bandpromo_materialize_audio_master_from_original($root, $entry);
        if (!empty($prepared['prepared'])) {
            $step['changed']++;
            $step['items'][] = $entry;
        } elseif (!empty($prepared['warning'])) {
            $step['errors'][] = $entry . ': ' . (string) $prepared['warning'];
        } else {
            $step['skipped']++;
        }
    }

    return $step;
}

function bandpromo_content_autofix_canonicalize_master_filenames(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('canonical_masters', 'Rename audio masters to ast_{ULID} filenames');
    $registry = bandpromo_asset_load_registry($root);
    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir)) {
        return $step;
    }

    $registryChanged = false;
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        if (!bandpromo_asset_is_asset_id((string) $assetId)) {
            continue;
        }

        $format = strtolower((string) ($asset['master_format'] ?? pathinfo((string) ($asset['master_filename'] ?? ''), PATHINFO_EXTENSION)));
        if ($format === '') {
            $step['errors'][] = (string) $assetId . ': missing master format';
            continue;
        }

        $canonical = bandpromo_asset_master_filename_for_ulid((string) $assetId, $format);
        $current = basename((string) ($asset['master_filename'] ?? ''));
        if ($current === '' || $current === $canonical) {
            $step['skipped']++;
            continue;
        }

        $fromPath = $masterDir . '/' . $current;
        $toPath = $masterDir . '/' . $canonical;
        if (!is_file($fromPath)) {
            $step['errors'][] = $current . ': master file missing on disk';
            continue;
        }
        if (is_file($toPath) && realpath($fromPath) !== realpath($toPath)) {
            $step['errors'][] = $canonical . ': target master filename already exists';
            continue;
        }

        if ($dryRun) {
            $step['changed']++;
            $step['items'][] = ['asset_id' => $assetId, 'from' => $current, 'to' => $canonical];
            continue;
        }

        if (!@rename($fromPath, $toPath)) {
            $step['errors'][] = $current . ': could not rename to ' . $canonical;
            continue;
        }

        unset($registry['by_master_filename'][$current]);
        $registry['assets'][$assetId]['master_filename'] = $canonical;
        $registry['by_master_filename'][$canonical] = (string) $assetId;
        $registryChanged = true;
        $step['changed']++;
        $step['items'][] = ['asset_id' => $assetId, 'from' => $current, 'to' => $canonical];
    }

    if ($registryChanged && !$dryRun) {
        bandpromo_asset_write_registry($root, $registry);
    }

    return $step;
}

function bandpromo_content_autofix_resolve_pool_asset(string $root, string $poolFile): ?array
{
    $poolFile = basename(trim($poolFile));
    if ($poolFile === '') {
        return null;
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $poolFile);
    if ($asset !== null) {
        return $asset;
    }

    $ext = strtolower((string) pathinfo($poolFile, PATHINFO_EXTENSION));
    if ($ext === 'wav') {
        $flacCandidate = pathinfo($poolFile, PATHINFO_FILENAME) . '.flac';
        $asset = bandpromo_asset_lookup_by_original_filename($root, $flacCandidate);
        if ($asset !== null) {
            return $asset;
        }
    }

    return null;
}

function bandpromo_content_autofix_playlist_campaign_ownership(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/campaign-ownership-helpers.php';
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/playlist-storage.php';

    $step = bandpromo_content_autofix_step_result(
        'playlist_campaign_ownership',
        'Stamp playlist campaign ownership from unanimous track membership'
    );
    bandpromo_playlist_ensure_seeded($root);

    foreach (bandpromo_playlist_registry_entries($root) as $playlistMeta) {
        if (!is_array($playlistMeta)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($playlistMeta['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
            continue;
        }
        $owner = bandpromo_document_campaign_id($document);
        if (!bandpromo_campaign_id_is_unowned($owner)) {
            $step['skipped']++;
            continue;
        }
        $desired = bandpromo_campaign_ownership_infer_from_playlist_entries($root, $document);
        if ($desired === '') {
            $step['skipped']++;
            continue;
        }
        $step['changed']++;
        $step['items'][] = [
            'playlist' => $playlistId,
            'campaign_id' => $desired,
        ];
        if ($dryRun) {
            continue;
        }
        try {
            $document = bandpromo_document_with_campaign_id($document, $desired);
            bandpromo_playlist_write_document($root, $document);
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
        }
    }

    return $step;
}

function bandpromo_content_autofix_normalize_playlist_kind(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('playlist_kind', 'Use system playlists in admin and player');
    bandpromo_playlist_ensure_seeded($root);
    $registry = bandpromo_playlist_load_registry($root);
    $registryChanged = false;

    foreach ($registry['playlists'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $kind = strtolower(trim((string) ($entry['kind'] ?? 'system')));
        if ($kind === 'system') {
            $step['skipped']++;
            continue;
        }

        $playlistId = trim((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        $step['changed']++;
        $step['items'][] = ['playlist' => $playlistId, 'from' => $kind, 'to' => 'system'];

        if ($dryRun) {
            continue;
        }

        $registry['playlists'][$index]['kind'] = 'system';
        $registryChanged = true;

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
            if (strtolower(trim((string) ($document['kind'] ?? 'system'))) !== 'system') {
                $document['kind'] = 'system';
                bandpromo_playlist_write_document($root, $document);
            }
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
        }
    }

    if ($registryChanged && !$dryRun) {
        bandpromo_playlist_write_registry($root, $registry);
    }

    return $step;
}

function bandpromo_content_autofix_sync_playlist_entries(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('playlist_links', 'Link playlist entries to asset registry');
    bandpromo_playlist_ensure_seeded($root);
    $registry = bandpromo_playlist_load_registry($root);

    foreach ($registry['playlists'] as $playlistMeta) {
        if (!is_array($playlistMeta)) {
            continue;
        }
        $playlistId = trim((string) ($playlistMeta['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
            continue;
        }

        $changed = false;
        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $poolFile = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
            if ($poolFile === '') {
                continue;
            }

            $asset = bandpromo_content_autofix_resolve_pool_asset($root, $poolFile);
            if ($asset === null) {
                $step['errors'][] = $playlistId . ': no asset for ' . $poolFile;
                continue;
            }

            $assetId = (string) ($asset['id'] ?? '');
            $campaignId = bandpromo_document_campaign_id($asset);

            $currentAssetId = trim((string) ($entry['asset_id'] ?? ''));
            $currentCampaignId = bandpromo_document_campaign_id($entry);
            if ($currentAssetId === $assetId && $currentCampaignId === $campaignId) {
                $step['skipped']++;
                continue;
            }

            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'playlist' => $playlistId,
                'file' => $poolFile,
                'asset_id' => $assetId,
                'campaign_id' => $campaignId,
            ];

            if (!$dryRun) {
                $entries[$index]['master_file'] = $poolFile;
                $entries[$index]['asset_id'] = $assetId;
                $entries[$index] = bandpromo_document_with_campaign_id($entries[$index], $campaignId);
            }
        }

        if ($changed && !$dryRun) {
            $document['entries'] = $entries;
            $document = bandpromo_playlist_clear_player_payload_fields($document);
            bandpromo_playlist_write_document($root, $document);
        }
    }

    return $step;
}

function bandpromo_content_autofix_orphan_visual_delivery(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/media-delivery-helpers.php';

    $step = bandpromo_content_autofix_step_result(
        'orphan_visual_delivery',
        'Orphan Visual delivery folders with no registry asset'
    );
    $result = bandpromo_visual_delivery_prune_orphans($root, $dryRun);
    $deleted = is_array($result['deleted'] ?? null) ? $result['deleted'] : [];
    $step['changed'] = count($deleted);
    if ($deleted !== []) {
        $step['items'][] = [
            'deleted_count' => count($deleted),
            'kept' => (int) ($result['kept'] ?? 0),
            'sample' => array_slice($deleted, 0, 8),
        ];
    } else {
        $step['skipped'] = 1;
    }

    return $step;
}

function bandpromo_campaign_sync_primary_audio_assets(string $root): void
{
    bandpromo_campaign_repair_catalog_release_ids($root);
}

function bandpromo_content_autofix_orphan_primary_uploads(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/install-migrations.php';

    $step = bandpromo_content_autofix_step_result(
        'orphan_primary_uploads',
        'Orphan uploads stuck on the hidden Default release bucket'
    );
    $result = bandpromo_install_migration_run_orphan_primary_uploads($root, [
        'dry_run' => $dryRun,
        'trigger' => 'content_autofix',
    ]);

    if (empty($result['ok'])) {
        $step['errors'][] = (string) ($result['skip_reason'] ?? 'Orphan repair failed.');

        return $step;
    }

    if (!empty($result['skipped'])) {
        $step['skipped'] = 1;

        return $step;
    }

    $changed = (int) ($result['changed'] ?? 0);
    $step['changed'] = $changed;
    if ($changed > 0) {
        $step['items'][] = [
            'tracks_orphaned' => (int) ($result['tracks_orphaned'] ?? 0),
            'registry_cleared' => (int) ($result['registry_cleared'] ?? 0),
        ];
    }

    return $step;
}

function bandpromo_content_autofix_sync_campaigns(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('release_membership', 'Repair catalogue release links on audio assets');
    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $membershipIndex = bandpromo_campaign_asset_membership_index($root);
        $staleCount = 0;
        $missingMembershipIds = 0;
        foreach ($registry['assets'] as $assetId => $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
                continue;
            }
            $assignedReleaseId = bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
            $memberships = $membershipIndex[(string) $assetId] ?? [];
            $documentReleaseId = '';
            if (count($memberships) === 1) {
                $documentReleaseId = bandpromo_campaign_normalize_id((string) ($memberships[0]['release_id'] ?? ''));
            }
            if ($documentReleaseId === '') {
                if ($assignedReleaseId !== '') {
                    $staleCount++;
                }
                continue;
            }
            if ($assignedReleaseId !== $documentReleaseId) {
                $staleCount++;
            }
        }
        foreach (bandpromo_campaign_registry_entries($root) as $entry) {
            $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
            if ($releaseId === '') {
                continue;
            }
            try {
                $document = bandpromo_campaign_load_document($root, $releaseId);
            } catch (Throwable $throwable) {
                continue;
            }
            foreach ($document['tracks'] ?? [] as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $assetId = trim((string) ($track['asset_id'] ?? ''));
                if ($assetId === '' || bandpromo_asset_lookup_by_id($root, $assetId) !== null) {
                    continue;
                }
                $missingMembershipIds++;
            }
        }
        $step['changed'] = $staleCount + $missingMembershipIds;
        $step['items'][] = [
            'stale_catalog_links' => $staleCount,
            'missing_membership_asset_ids' => $missingMembershipIds,
        ];
        return $step;
    }

    $membershipRepair = bandpromo_campaign_repair_stale_membership_asset_ids($root);
    require_once __DIR__ . '/playlist-storage.php';
    $playlistRepair = bandpromo_playlist_repair_stale_track_asset_ids($root, $membershipRepair['remaps'] ?? []);
    $repaired = bandpromo_campaign_repair_catalog_release_ids($root);
    $step['changed'] = (int) ($membershipRepair['rebound'] ?? 0)
        + (int) ($membershipRepair['dropped'] ?? 0)
        + (int) ($playlistRepair['changed'] ?? 0)
        + ($repaired > 0 ? $repaired : 0);
    if (($membershipRepair['rebound'] ?? 0) > 0) {
        $step['items'][] = [
            'rebound_membership_asset_ids' => (int) $membershipRepair['rebound'],
            'releases' => $membershipRepair['releases'] ?? [],
        ];
    }
    if (($membershipRepair['dropped'] ?? 0) > 0) {
        $step['items'][] = [
            'dropped_missing_membership_asset_ids' => (int) $membershipRepair['dropped'],
            'releases' => $membershipRepair['releases'] ?? [],
        ];
    }
    if (($playlistRepair['changed'] ?? 0) > 0) {
        $step['items'][] = [
            'rebound_playlist_tracks' => (int) $playlistRepair['changed'],
            'playlists' => $playlistRepair['playlists'] ?? [],
        ];
    }
    if ($repaired > 0) {
        $step['items'][] = ['repaired_catalog_links' => $repaired];
    }

    return $step;
}

function bandpromo_content_autofix_sync_config_scope(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('config_scope', 'Dual-write scoped config fields');
    $configPath = $root . '/web-config.json';
    if (!is_file($configPath)) {
        $step['skipped'] = 1;
        return $step;
    }

    $decoded = bandpromo_json_read_array_file($configPath);
    if (!is_array($decoded)) {
        $step['errors'][] = 'web-config.json is invalid';
        return $step;
    }

    $before = json_encode($decoded);
    $probe = $decoded;
    bandpromo_sync_scoped_config_fields($probe, ['site', 'social', 'media']);
    $after = json_encode($probe);
    if ($before === $after) {
        $step['skipped'] = 1;
        return $step;
    }

    if ($dryRun) {
        $step['changed'] = 1;
        return $step;
    }

    bandpromo_sync_scoped_config_fields($decoded, ['site', 'social', 'media']);
    if (!bandpromo_json_write_file($configPath, $decoded)) {
        $step['errors'][] = 'Could not write web-config.json';
        return $step;
    }

    $step['changed'] = 1;
    return $step;
}

function bandpromo_content_autofix_sync_audio_display(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result(
        'audio_display_cache',
        'Refresh asset registry display cache from master tags'
    );

    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $pending = 0;
        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
                continue;
            }
            $display = bandpromo_asset_read_audio_display($asset);
            if (!bandpromo_asset_audio_display_is_complete($display)) {
                $pending++;
            }
        }
        $step['changed'] = $pending;
        if ($pending > 0) {
            $step['items'][] = ['pending' => $pending];
        } else {
            $step['skipped'] = 1;
        }

        return $step;
    }

    // Incomplete-only refresh. Web Apply keeps a short budget; CLI runs to completion / Stop.
    $state = &bandpromo_content_autofix_log_state();
    $cli = (($state['source'] ?? '') === 'cli') || getenv('BANDPROMO_REPAIR_CLI') === '1';
    $budget = $cli ? 0.0 : bandpromo_content_autofix_step_budget_seconds(6.0);
    $maxInspects = $cli ? 0 : 10;
    $result = bandpromo_asset_refresh_all_audio_displays($root, true, $budget, $maxInspects);
    $step['changed'] = (int) ($result['changed'] ?? 0);
    $step['items'] = is_array($result['items'] ?? null) ? $result['items'] : [];
    $remaining = (int) ($result['remaining'] ?? 0);
    if ($remaining > 0) {
        $step['items'][] = [
            'remaining_incomplete' => $remaining,
            'note' => 'More audio display rows left — run Apply again.',
        ];
        $step['warnings'][] = $remaining . ' audio display row(s) still incomplete; re-run Apply.';
    }

    if (!bandpromo_content_autofix_should_yield(5.0)) {
        $metaRestore = bandpromo_asset_restore_audio_meta_from_unregistered_masters($root);
        $restored = (int) ($metaRestore['restored'] ?? 0);
        if ($restored > 0) {
            $step['changed'] += $restored;
            $step['items'][] = [
                'restored_from_leftover_masters' => $restored,
                'covers' => (int) ($metaRestore['covers'] ?? 0),
                'details' => $metaRestore['items'] ?? [],
            ];
        }
    }

    if ($step['changed'] === 0 && $remaining === 0 && empty($step['warnings'])) {
        $step['skipped'] = 1;
    }

    return $step;
}

function bandpromo_content_autofix_refresh_validation(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('validation_refresh', 'Refresh playlist validation report');
    if ($dryRun) {
        $step['skipped'] = 1;
        return $step;
    }

    // Validation-only: refresh data/validation/playlist-validation.json.
    // Full makePlaylists publish needs a PHP CLI and is for Refresh site files / build.
    $result = bandpromo_run_light_task('scripts/makePlaylists.py', [
        'BANDPROMO_PLAYLIST_SCAN_MODE' => 'validation-only',
    ]);
    if (empty($result['ok'])) {
        $output = trim((string) ($result['output'] ?? ''));
        $step['errors'][] = $output !== '' ? $output : 'Playlist validation refresh failed';
        return $step;
    }

    $step['changed'] = 1;
    return $step;
}

/**
 * Backfill brand shell asset_ids from current path slots (dual-write map).
 */
function bandpromo_content_autofix_sync_brand_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result('brand_asset_ids', 'Backfill brand shell asset_ids');
    bandpromo_brand_ensure_seeded($root);

    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        $brandId = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($brandId === '') {
            continue;
        }
        try {
            $document = bandpromo_brand_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $brandId . ': ' . $throwable->getMessage();
            continue;
        }

        $assetIds = bandpromo_brand_normalize_asset_ids(
            is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : []
        );
        $assets = bandpromo_brand_normalize_assets(
            is_array($document['assets'] ?? null) ? $document['assets'] : []
        );
        $changed = false;

        foreach ($assets as $slot => $path) {
            $current = trim((string) ($assetIds[$slot] ?? ''));
            if ($current !== '') {
                $step['skipped']++;
                continue;
            }
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            $found = bandpromo_brand_lookup_asset_id_for_path($root, $path);
            if ($found === '') {
                continue;
            }
            $assetIds[$slot] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'brand' => $brandId,
                'slot' => $slot,
                'asset_id' => $found,
            ];
        }

        if (!$changed) {
            continue;
        }

        $document['asset_ids'] = $assetIds;
        $materialized = bandpromo_brand_materialize_asset_urls($root, $document);
        $document = $materialized['document'];
        if (!$dryRun) {
            bandpromo_brand_write_document($root, $document, ['allow_locked' => true]);
            if (bandpromo_brand_active_id($root) === $brandId) {
                bandpromo_brand_sync_assets_to_config($root, $document);
            }
        }
    }

    return $step;
}

/**
 * Reseed Brand libraries emptied by dead membership ids (Branding library blank).
 */
function bandpromo_content_autofix_heal_brand_libraries(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/brand-storage.php';

    $step = bandpromo_content_autofix_step_result(
        'brand_libraries',
        'Heal empty or dead Brand asset libraries'
    );

    if ($dryRun) {
        // Preview: count brands whose library resolves to nothing but owned eligible assets exist.
        bandpromo_brand_ensure_seeded($root);
        require_once __DIR__ . '/asset-registry.php';
        $registry = bandpromo_asset_load_registry($root);
        $assets = is_array($registry['assets'] ?? null) ? $registry['assets'] : [];
        foreach (bandpromo_brand_registry_entries($root) as $entry) {
            $brandId = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $document = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $library = bandpromo_brand_normalize_library_asset_ids(
                is_array($document['library_asset_ids'] ?? null) ? $document['library_asset_ids'] : []
            );
            $live = 0;
            foreach ($library as $libraryId) {
                if (isset($assets[$libraryId]) && is_array($assets[$libraryId])) {
                    $live++;
                }
            }
            if ($live > 0) {
                $step['skipped']++;
                continue;
            }
            $eligible = 0;
            foreach ($assets as $assetId => $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $kind = (string) ($asset['kind'] ?? '');
                if ($kind !== 'visual' && $kind !== 'sfx') {
                    continue;
                }
                if (bandpromo_brand_canonical_id((string) ($asset['brand_id'] ?? '')) !== $brandId) {
                    continue;
                }
                if (bandpromo_brand_list_entry_is_library_eligible($asset)) {
                    $eligible++;
                }
            }
            if ($eligible > 0) {
                $step['changed']++;
                $step['items'][] = [
                    'brand' => $brandId,
                    'eligible' => $eligible,
                ];
            } else {
                $step['skipped']++;
            }
        }

        return $step;
    }

    $notes = bandpromo_brand_heal_empty_libraries($root);
    $step['changed'] = count($notes);
    $step['items'] = $notes;
    if ($step['changed'] === 0) {
        $step['skipped'] = 1;
    }

    return $step;
}

/**
 * Backfill gallery entry asset_ids from src paths.
 */
function bandpromo_content_autofix_sync_gallery_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result('gallery_asset_ids', 'Backfill gallery entry asset_ids');
    bandpromo_gallery_ensure_seeded($root);

    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $document = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $galleryId . ': ' . $throwable->getMessage();
            continue;
        }

        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        $changed = false;
        foreach ($entries as $index => $galleryEntry) {
            if (!is_array($galleryEntry)) {
                continue;
            }
            $current = trim((string) ($galleryEntry['asset_id'] ?? ''));
            if ($current !== '' && bandpromo_asset_is_asset_id($current)) {
                $step['skipped']++;
                continue;
            }
            $src = trim((string) ($galleryEntry['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $found = bandpromo_brand_lookup_asset_id_for_path($root, $src);
            if ($found === '' && bandpromo_asset_is_asset_id($src)) {
                $found = $src;
            }
            if ($found === '') {
                $basename = basename($src);
                $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
                if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                    $found = (string) ($asset['id'] ?? '');
                }
            }
            if ($found === '') {
                continue;
            }
            $entries[$index]['asset_id'] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'gallery' => $galleryId,
                'src' => $src,
                'asset_id' => $found,
            ];
        }

        if ($changed && !$dryRun) {
            $document['entries'] = $entries;
            bandpromo_gallery_write_document($root, $document);
        }
    }

    return $step;
}

/**
 * Backfill page picture block + poster asset_ids.
 */
function bandpromo_content_autofix_sync_page_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/page-blocks.php';

    $step = bandpromo_content_autofix_step_result('page_asset_ids', 'Backfill page picture asset_ids');
    bandpromo_page_seed_all_if_missing($root);

    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        try {
            $document = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $pageId . ': ' . $throwable->getMessage();
            continue;
        }

        $changed = false;
        $posterId = trim((string) ($document['poster_asset_id'] ?? ''));
        if ($posterId === '') {
            // No path field for poster; skip unless already set.
            $step['skipped']++;
        } elseif (!bandpromo_asset_is_asset_id($posterId)) {
            $document['poster_asset_id'] = '';
            $changed = true;
            $step['changed']++;
        }

        $blocks = is_array($document['blocks'] ?? null) ? $document['blocks'] : [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if ($type !== 'picture' && $type !== 'picture_richtext') {
                continue;
            }
            $current = trim((string) ($block['asset_id'] ?? ''));
            if ($current !== '' && bandpromo_asset_is_asset_id($current)) {
                $step['skipped']++;
                continue;
            }
            $src = trim((string) ($block['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $found = bandpromo_brand_lookup_asset_id_for_path($root, $src);
            if ($found === '') {
                $basename = basename(parse_url($src, PHP_URL_PATH) ?: $src);
                $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
                if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                    $found = (string) ($asset['id'] ?? '');
                }
            }
            if ($found === '') {
                continue;
            }
            $blocks[$index]['asset_id'] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'page' => $pageId,
                'block' => $index,
                'asset_id' => $found,
            ];
        }

        if ($changed && !$dryRun) {
            $document['blocks'] = $blocks;
            bandpromo_page_save_document($root, $document);
        }
    }

    return $step;
}

/**
 * Rewrite audio display.cover / living_cover filename refs to asset_ids when known.
 */
function bandpromo_content_autofix_sync_audio_visual_refs(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/playlist-storage.php';

    $step = bandpromo_content_autofix_step_result(
        'audio_visual_refs',
        'Rewrite audio cover/living-cover refs to asset_ids'
    );
    $registry = bandpromo_asset_load_registry($root);

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $changes = [];

        $cover = trim((string) ($display['cover'] ?? ''));
        if ($cover !== '') {
            $visualId = '';
            $resolved = bandpromo_asset_canonical_id_from_media_ref($root, $cover);
            $coverAsset = $resolved !== '' ? bandpromo_asset_lookup_by_id($root, $resolved) : null;
            if (is_array($coverAsset) && ($coverAsset['kind'] ?? '') === 'visual') {
                $visualId = $resolved;
            } else {
                $visual = bandpromo_asset_lookup_from_media_ref($root, $cover);
                if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
                    $visualId = trim((string) ($visual['id'] ?? ''));
                }
            }
            if ($visualId !== '') {
                if ($visualId !== $cover) {
                    $changes['cover'] = $visualId;
                } else {
                    $step['skipped']++;
                }
            } else {
                // Non-visual refs (audio id, ast_{audio}.jpg, stem paths) are invalid covers.
                $changes['cover'] = '';
            }
        } else {
            $step['skipped']++;
        }

        $living = trim((string) ($display['living_cover'] ?? ''));
        if ($living !== '') {
            $livingId = '';
            $resolvedLiving = bandpromo_asset_canonical_id_from_media_ref($root, $living);
            $livingAsset = $resolvedLiving !== '' ? bandpromo_asset_lookup_by_id($root, $resolvedLiving) : null;
            if (is_array($livingAsset) && ($livingAsset['kind'] ?? '') === 'visual'
                && strtolower((string) ($livingAsset['media_type'] ?? '')) === 'video'
            ) {
                $livingId = $resolvedLiving;
            } else {
                $visual = bandpromo_asset_lookup_from_media_ref($root, $living);
                if (is_array($visual) && ($visual['kind'] ?? '') === 'visual'
                    && strtolower((string) ($visual['media_type'] ?? '')) === 'video'
                ) {
                    $livingId = trim((string) ($visual['id'] ?? ''));
                }
            }
            if ($livingId !== '') {
                if ($livingId !== $living) {
                    $changes['living_cover'] = $livingId;
                } else {
                    $step['skipped']++;
                }
            } else {
                $changes['living_cover'] = '';
            }
        }

        if ($changes === []) {
            continue;
        }

        $step['changed']++;
        $step['items'][] = [
            'asset_id' => (string) $assetId,
            'changes' => $changes,
        ];
        if (!$dryRun) {
            bandpromo_asset_update_entry($root, (string) $assetId, [
                'display' => $changes,
            ]);
        }
    }

    // Optional player-payload track covers only (entries stay source of truth).
    // Never call bandpromo_playlist_clear_player_payload_fields here — it strips tracks.
    bandpromo_playlist_ensure_seeded($root);
    foreach (bandpromo_playlist_registry_entries($root) as $playlistMeta) {
        $playlistId = trim((string) ($playlistMeta['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
            continue;
        }
        if (!array_key_exists('tracks', $document) || !is_array($document['tracks'])) {
            continue;
        }
        $tracks = $document['tracks'];
        $changed = false;
        foreach ($tracks as $index => $track) {
            if (!is_array($track)) {
                continue;
            }
            $cover = trim((string) ($track['cover'] ?? ''));
            if ($cover === '') {
                continue;
            }
            $resolved = bandpromo_asset_canonical_id_from_media_ref($root, $cover);
            $coverAsset = $resolved !== '' ? bandpromo_asset_lookup_by_id($root, $resolved) : null;
            $visualId = '';
            if (is_array($coverAsset) && ($coverAsset['kind'] ?? '') === 'visual') {
                $visualId = $resolved;
            } else {
                $visual = bandpromo_asset_lookup_from_media_ref($root, $cover);
                if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
                    $visualId = trim((string) ($visual['id'] ?? ''));
                }
                if ($visualId === '') {
                    $file = basename(trim((string) ($track['file'] ?? '')));
                    $audio = $file !== '' ? bandpromo_asset_lookup_by_master_filename($root, $file) : null;
                    $displayCover = is_array($audio)
                        ? trim((string) (($audio['display']['cover'] ?? '')))
                        : '';
                    if ($displayCover !== '') {
                        $fallback = bandpromo_asset_canonical_id_from_media_ref($root, $displayCover);
                        $fallbackAsset = $fallback !== '' ? bandpromo_asset_lookup_by_id($root, $fallback) : null;
                        if (is_array($fallbackAsset) && ($fallbackAsset['kind'] ?? '') === 'visual') {
                            $visualId = $fallback;
                        }
                    }
                }
            }
            if ($visualId === $cover) {
                continue;
            }
            $tracks[$index]['cover'] = $visualId;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'playlist' => $playlistId,
                'file' => (string) ($track['file'] ?? ''),
                'cover' => $visualId,
                'was' => $cover,
            ];
        }
        if ($changed && !$dryRun) {
            $document['tracks'] = $tracks;
            bandpromo_playlist_write_document($root, $document);
        }
    }

    return $step;
}

/**
 * Reclassify campaign/cover visuals wrongly stamped intake_bucket=special so they
 * stay in Files → Visual when the demo campaign is hidden.
 */
function bandpromo_content_autofix_heal_misfiled_special_intake(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result(
        'special_intake_heal',
        'Move misfiled special visuals into the Visual pool (img/video)'
    );

    $registry = bandpromo_asset_load_registry($root);
    $pendingIds = [];
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $intake = bandpromo_asset_normalize_intake_bucket((string) ($asset['intake_bucket'] ?? ''));
        if ($intake !== 'special') {
            continue;
        }
        $role = strtolower(trim((string) ($asset['role'] ?? '')));
        if (bandpromo_asset_visual_role_is_brand_shell($role)) {
            continue;
        }
        $pendingIds[] = (string) $assetId;
    }

    if ($pendingIds === []) {
        $step['skipped'] = 1;

        return $step;
    }

    $step['changed'] = count($pendingIds);
    $step['items'][] = ['pending' => count($pendingIds)];
    if ($dryRun) {
        return $step;
    }

    $result = bandpromo_asset_heal_misfiled_special_intake($root);
    $step['changed'] = (int) ($result['changed'] ?? 0);
    $step['items'] = [['healed' => $step['changed']]];
    if ($step['changed'] > 0) {
        require_once __DIR__ . '/media-library-state.php';
        // Refresh Visual + special indexes so pool lists match the healed stamps.
        bandpromo_media_files_index_rebuild_target($root, 'illustrations');
        bandpromo_media_files_index_rebuild_target($root, 'video');
        bandpromo_media_files_index_rebuild_target($root, 'special');
    } else {
        $step['skipped'] = 1;
    }

    return $step;
}

/**
 * Heal empty visual display by inventing title/captured_at in the registry.
 * Bulk Apply does not remux video masters (that timed out shared-host requests).
 */
function bandpromo_content_autofix_heal_visual_display(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result(
        'visual_display_heal',
        'Heal empty visual display (invent title/date in the registry)'
    );

    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $pending = 0;
        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
                continue;
            }
            $display = bandpromo_asset_read_visual_display($asset);
            // Title and captured_at are invented on apply when embeds are empty.
            // Empty description alone is optional and must not keep Preview noisy.
            if ($display['title'] === '' || $display['captured_at'] === '') {
                $pending++;
            }
        }
        $step['changed'] = $pending;
        if ($pending > 0) {
            $step['items'][] = ['pending' => $pending];
        } else {
            $step['skipped'] = 1;
        }

        return $step;
    }

    require_once __DIR__ . '/visual-master-helpers.php';

    $healed = bandpromo_visual_invent_empty_registry_displays($root);
    $step['changed'] = count($healed);
    $step['items'] = $healed;
    if ($healed === []) {
        $step['skipped'] = 1;
    }

    return $step;
}

/**
 * Backfill content_xxh3 / content_sha256 for visual images (original or master bytes).
 * Clears legacy Welcome “missing content hashes” nag when still invoked from
 * bootstrap / seed migrate. Welcome no longer surfaces that nag (Site health
 * owns operator health; registry hashes remain for intake / shared covers).
 */
function bandpromo_content_autofix_backfill_visual_content_hashes(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result(
        'visual_content_hashes',
        'Backfill visual content hashes for dedupe'
    );

    $registry = bandpromo_asset_load_registry($root);
    $pending = 0;
    $items = [];
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual' || ($asset['media_type'] ?? '') !== 'image') {
            continue;
        }
        $hasXxh3 = strtolower(trim((string) ($asset['content_xxh3'] ?? ''))) !== '';
        $hasSha = strtolower(trim((string) ($asset['content_sha256'] ?? ''))) !== '';
        // xxh3 alone clears the Welcome nag / dedupe need; SHA-256 is optional.
        if ($hasXxh3 || ($hasSha && !in_array('xxh3', hash_algos(), true))) {
            continue;
        }
        $source = bandpromo_asset_visual_content_hash_source_path($root, $asset);
        if ($source === '') {
            continue;
        }
        $pending++;
        if (count($items) < 12) {
            $items[] = [
                'asset_id' => (string) $assetId,
                'source' => basename($source),
            ];
        }
    }

    if ($dryRun) {
        $step['changed'] = $pending;
        $step['items'] = $items;
        if ($pending === 0) {
            $step['skipped'] = 1;
        }

        return $step;
    }

    if ($pending === 0) {
        $step['skipped'] = 1;

        return $step;
    }

    $hashBudget = bandpromo_content_autofix_step_budget_seconds(8.0);
    $changed = bandpromo_asset_registry_backfill_visual_content_hashes($root, $registry, $hashBudget);
    if ($changed) {
        bandpromo_asset_write_registry($root, $registry);
    }
    $step['changed'] = $changed ? $pending : 0;
    $step['items'] = $items;
    if (!$changed) {
        $step['skipped'] = 1;
    } else {
        // Recount remaining so operators know to re-run Apply on large catalogues.
        $left = 0;
        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual' || ($asset['media_type'] ?? '') !== 'image') {
                continue;
            }
            if (strtolower(trim((string) ($asset['content_xxh3'] ?? ''))) !== '') {
                continue;
            }
            if (strtolower(trim((string) ($asset['content_sha256'] ?? ''))) !== ''
                && !in_array('xxh3', hash_algos(), true)
            ) {
                continue;
            }
            if (bandpromo_asset_visual_content_hash_source_path($root, $asset) !== '') {
                $left++;
            }
        }
        if ($left > 0) {
            $step['warnings'][] = 'Hash backfill paused with ' . $left
                . ' visual(s) still pending — run Repair Apply again.';
            $step['changed'] = max(1, $pending - $left);
        }
    }

    return $step;
}

/**
 * Relocate visual originals + materialize media/visual/master/ast_* (M2).
 * Video masters remux to MKV.
 */
function bandpromo_content_autofix_materialize_visual_masters(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/visual-master-helpers.php';

    $step = bandpromo_content_autofix_step_result(
        'materialize_visual_masters',
        'Prepare visual originals/masters under media/visual/'
    );
    $registry = bandpromo_asset_load_registry($root);

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $working = bandpromo_visual_working_path($root, $asset);
        $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
        $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo(
            (string) ($asset['original_filename'] ?? ''),
            PATHINFO_EXTENSION
        ))));
        $expectedFormat = $mediaType === 'video' ? 'mkv' : $format;
        $masterPath = $expectedFormat !== ''
            ? bandpromo_visual_master_path($root, (string) $assetId, $expectedFormat)
            : '';
        $needsMaster = $masterPath === '' || !is_file($masterPath);
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        $unified = $originalFilename !== ''
            ? bandpromo_visual_unified_original_path($root, $originalFilename)
            : '';
        $needsOriginal = $unified !== '' && !is_file($unified);
        $currentMaster = basename(trim((string) ($asset['master_filename'] ?? '')));
        $needsCanonical = $currentMaster === ''
            || !bandpromo_asset_is_asset_id((string) pathinfo($currentMaster, PATHINFO_FILENAME))
            || ($mediaType === 'video' && strtolower(trim((string) ($asset['master_format'] ?? ''))) !== 'mkv');

        // Original is provenance/download only after materialize. Missing originals with a
        // healthy master are intentional after masters-only PCF — do not invent original bytes.
        if (!$needsMaster && !$needsCanonical) {
            $step['skipped']++;
            continue;
        }
        if ($working === '' && $needsMaster) {
            // True orphan (no master/original/legacy bytes): warn and continue — do not fail
            // publish catalogue. Playlist cover heal can re-extract from audio later.
            $step['warnings'][] = (string) $assetId . ': no source bytes for visual master (skipped)';
            $step['skipped']++;
            continue;
        }

        $step['changed']++;
        $step['items'][] = [
            'asset_id' => (string) $assetId,
            'original' => $originalFilename,
            'needs_original' => $needsOriginal,
            'needs_master' => $needsMaster || $needsCanonical,
        ];
        if (!$dryRun) {
            bandpromo_visual_ensure_tiers_for_asset($root, (string) $assetId);
        }
    }

    return $step;
}

function bandpromo_content_autofix_run(string $root, bool $dryRun = false, string $source = 'manual'): array
{
    $steps = [];
    $errors = [];
    $changedTotal = 0;
    bandpromo_content_autofix_log_begin($root, $dryRun, $source);

    try {
        bandpromo_content_autofix_log_step_start($root, 'seed_containers', 'Seed platform containers');
        $seedStarted = microtime(true);
        if ($dryRun) {
            // Preview must be read-only: never heavy-migrate or reconcile (those write registry/index).
            bandpromo_asset_registry_ensure_migrated($root, false);
            $pendingMasters = bandpromo_list_uncatalogued_audio_masters($root);
            $changedTotal += count($pendingMasters);
            if ($pendingMasters !== []) {
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio_masters', 'Register uncatalogued audio masters', [
                    'changed' => count($pendingMasters),
                    'items' => array_map(static fn(array $item): string => (string) ($item['master_filename'] ?? ''), $pendingMasters),
                ]);
            }
            $pending = bandpromo_list_uncatalogued_audio_originals($root);
            $changedTotal += count($pending);
            if ($pending !== []) {
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio', 'Register uncatalogued audio uploads', [
                    'changed' => count($pending),
                    'items' => array_map(static fn(array $item): string => (string) ($item['filename'] ?? ''), $pending),
                ]);
            }
            $pendingVisual = bandpromo_list_uncatalogued_visual_masters($root);
            $changedTotal += count($pendingVisual);
            if ($pendingVisual !== []) {
                $steps[] = bandpromo_content_autofix_step_result('auto_register_visual_masters', 'Register uncatalogued Visual masters', [
                    'changed' => count($pendingVisual),
                    'items' => array_map(static fn(array $item): string => (string) ($item['master_filename'] ?? ''), $pendingVisual),
                ]);
            }
        } else {
            // Light only: heavy migrate SHA-256s every visual master and times out on
            // HITZ-class hosts (php.ini 30s CPU; set_time_limit often ignored).
            // Hash backfill / reconcile / tier heal live in the pipeline steps below.
            bandpromo_asset_registry_ensure_migrated($root, false);
            bandpromo_campaign_ensure_seeded($root);
            bandpromo_playlist_ensure_seeded($root);
            bandpromo_gallery_ensure_seeded($root);
            bandpromo_brand_ensure_seeded($root);
            bandpromo_page_seed_all_if_missing($root);
            $reconcileMasters = bandpromo_reconcile_uncatalogued_audio_masters($root);
            if (!empty($reconcileMasters['changed']) || !empty($reconcileMasters['index_rebuilt'])) {
                $changedTotal += (int) ($reconcileMasters['changed'] ?? 0);
                if (!empty($reconcileMasters['index_rebuilt']) && (int) ($reconcileMasters['changed'] ?? 0) === 0) {
                    $changedTotal++;
                }
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio_masters', 'Register uncatalogued audio masters', [
                    'changed' => max(1, (int) ($reconcileMasters['changed'] ?? 0)),
                    'items' => $reconcileMasters['fixed'] ?? [],
                ]);
            }
            if (!empty($reconcileMasters['failed'])) {
                foreach ($reconcileMasters['failed'] as $failure) {
                    if (!is_array($failure)) {
                        continue;
                    }
                    $errors[] = (string) ($failure['filename'] ?? 'audio') . ': ' . (string) ($failure['error'] ?? 'Could not register audio master');
                }
            }
            $reconcile = bandpromo_reconcile_uncatalogued_audio_originals($root);
            if (!empty($reconcile['changed'])) {
                $changedTotal += (int) $reconcile['changed'];
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio', 'Register uncatalogued audio uploads', [
                    'changed' => (int) $reconcile['changed'],
                    'items' => $reconcile['fixed'],
                ]);
            }
            if (!empty($reconcile['failed'])) {
                foreach ($reconcile['failed'] as $failure) {
                    if (!is_array($failure)) {
                        continue;
                    }
                    $errors[] = (string) ($failure['filename'] ?? 'audio') . ': ' . (string) ($failure['error'] ?? 'Could not register automatically');
                }
            }
            $reconcileVisual = bandpromo_reconcile_uncatalogued_visual_masters($root);
            if (!empty($reconcileVisual['changed']) || !empty($reconcileVisual['index_rebuilt'])) {
                $changedTotal += (int) ($reconcileVisual['changed'] ?? 0);
                if (!empty($reconcileVisual['index_rebuilt']) && (int) ($reconcileVisual['changed'] ?? 0) === 0) {
                    $changedTotal++;
                }
                $steps[] = bandpromo_content_autofix_step_result('auto_register_visual_masters', 'Register uncatalogued Visual masters', [
                    'changed' => max(1, (int) ($reconcileVisual['changed'] ?? 0)),
                    'items' => $reconcileVisual['fixed'] ?? [],
                ]);
            }
            if (!empty($reconcileVisual['failed'])) {
                foreach ($reconcileVisual['failed'] as $failure) {
                    if (!is_array($failure)) {
                        continue;
                    }
                    $errors[] = (string) ($failure['filename'] ?? 'visual') . ': ' . (string) ($failure['error'] ?? 'Could not register Visual master');
                }
            }
        }
        $seedStep = bandpromo_content_autofix_step_result('seed_containers', 'Seed platform containers', [
            'changed' => 0,
            'skipped' => 1,
            'items' => $dryRun
                ? ['preview-read-only']
                : ['assets', 'releases', 'playlists', 'galleries', 'themes', 'pages'],
        ]);
        $steps[] = $seedStep;
        bandpromo_content_autofix_log_step_finish($root, $seedStep, (microtime(true) - $seedStarted) * 1000);
        if (isset($reconcileMasters) && !empty($reconcileMasters['failed'])) {
            bandpromo_content_autofix_log_write($root, '  auto_register_audio_masters failures=' . count($reconcileMasters['failed']));
        }
        if (isset($reconcile) && !empty($reconcile['failed'])) {
            bandpromo_content_autofix_log_write($root, '  auto_register failures=' . count($reconcile['failed']));
        }
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
        bandpromo_content_autofix_log_write($root, '  ERROR seed_containers: ' . $throwable->getMessage());
    }

    $pipeline = [
        'bandpromo_content_autofix_materialize_audio_masters',
        'bandpromo_content_autofix_canonicalize_master_filenames',
        'bandpromo_content_autofix_materialize_visual_masters',
        'bandpromo_content_autofix_backfill_visual_content_hashes',
        'bandpromo_content_autofix_heal_misfiled_special_intake',
        'bandpromo_content_autofix_heal_visual_display',
        'bandpromo_content_autofix_orphan_primary_uploads',
        'bandpromo_content_autofix_orphan_visual_delivery',
        'bandpromo_content_autofix_normalize_playlist_kind',
        'bandpromo_content_autofix_playlist_campaign_ownership',
        'bandpromo_content_autofix_sync_playlist_entries',
        'bandpromo_content_autofix_sync_campaigns',
        'bandpromo_content_autofix_sync_audio_display',
        'bandpromo_content_autofix_sync_brand_asset_ids',
        'bandpromo_content_autofix_heal_brand_libraries',
        'bandpromo_content_autofix_sync_gallery_asset_ids',
        'bandpromo_content_autofix_sync_page_asset_ids',
        'bandpromo_content_autofix_sync_audio_visual_refs',
        'bandpromo_content_autofix_sync_config_scope',
        'bandpromo_content_autofix_refresh_validation',
    ];

    $deferredSteps = [];
    $stoppedByOperator = false;
    foreach ($pipeline as $callable) {
        $stepId = (string) preg_replace('/^bandpromo_content_autofix_/', '', $callable);
        if (!$dryRun && bandpromo_content_autofix_stop_requested($root)) {
            $stoppedByOperator = true;
            bandpromo_content_autofix_log_write(
                $root,
                '~ stop honoured before ' . $stepId . ' — finishing cleanly'
            );
            break;
        }
        if (!$dryRun && bandpromo_content_autofix_should_yield(6.0)) {
            $deferredSteps[] = $stepId;
            bandpromo_content_autofix_log_write(
                $root,
                '~ defer ' . $stepId . ' — CPU budget low ('
                . number_format(bandpromo_content_autofix_seconds_remaining(), 1)
                . 's left); re-run Apply'
            );
            continue;
        }
        bandpromo_content_autofix_log_step_start($root, $stepId, $callable);
        $started = microtime(true);
        try {
            $step = $callable($root, $dryRun);
            $steps[] = $step;
            $changedTotal += (int) ($step['changed'] ?? 0);
            if (!empty($step['errors'])) {
                foreach ($step['errors'] as $error) {
                    $errors[] = (string) $error;
                }
            }
            bandpromo_content_autofix_log_step_finish($root, $step, (microtime(true) - $started) * 1000);
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
            bandpromo_content_autofix_log_write($root, '  ERROR ' . $stepId . ': ' . $throwable->getMessage());
        }
        if (!$dryRun && bandpromo_content_autofix_stop_requested($root)) {
            $stoppedByOperator = true;
            bandpromo_content_autofix_log_write(
                $root,
                '~ stop honoured after ' . $stepId
            );
            break;
        }
    }

    if ($stoppedByOperator) {
        require_once __DIR__ . '/job-stop.php';
        bandpromo_job_stop_clear($root, 'catalog_repair');
        $steps[] = bandpromo_content_autofix_step_result('stopped_by_operator', 'Stopped by operator', [
            'changed' => 0,
            'skipped' => 1,
            'items' => [],
            'warnings' => ['Repair stopped after the current step. Start Apply again to continue.'],
        ]);
        bandpromo_content_autofix_log_write($root, '==== stopped by operator ====');
    }

    if ($deferredSteps !== []) {
        $steps[] = bandpromo_content_autofix_step_result('deferred_for_budget', 'Deferred steps (re-run Apply)', [
            'changed' => 0,
            'skipped' => count($deferredSteps),
            'items' => $deferredSteps,
            'warnings' => [
                'Host CPU time ran low; deferred '
                . count($deferredSteps)
                . ' step(s). Run Apply again to continue.',
            ],
        ]);
        // Keep Preview noisy so the operator knows to continue.
        $changedTotal = max(1, $changedTotal);
    }

    $recommendBuild = !$dryRun && $changedTotal > 0 && !$stoppedByOperator;
    if ($recommendBuild) {
        bandpromo_mark_build_required('content_autofix');
    }

    $partialNote = $deferredSteps !== []
        ? ' Some steps were deferred because this host limits PHP CPU time — run Apply again until Preview goes quiet.'
        : '';
    if ($stoppedByOperator) {
        $partialNote = ' Stopped by operator after the current step.';
    }

    $report = [
        'ok' => true,
        'dry_run' => $dryRun,
        'changed_total' => $changedTotal,
        'recommend_build' => $recommendBuild,
        'stopped' => $stoppedByOperator,
        'steps' => $steps,
        'errors' => $errors,
        'has_warnings' => $errors !== [] || $deferredSteps !== [] || $stoppedByOperator,
        'deferred_steps' => $deferredSteps,
        'message' => $dryRun
            ? ($changedTotal > 0
                ? 'Preview complete. Apply will perform the listed repairs. Preview again afterwards — a healthy catalogue should then show everything up to date.'
                : 'Preview complete. Catalogue looks healthy — nothing to repair.')
            : ($stoppedByOperator
                ? 'Catalogue repair stopped. Start Apply again when ready. Missing visual thumbnails need Refresh site files.'
                : ($changedTotal > 0
                    ? 'Catalogue repair finished.' . $partialNote
                        . ' Preview again to confirm. Missing visual thumbnails need Refresh site files, not another Repair pass.'
                    : 'Catalogue already matches the current registry and container links.')),
    ];
    bandpromo_content_autofix_log_finish($root, $report);

    return $report;
}
