<?php
declare(strict_types=1);

require_once __DIR__ . '/config-repair-helpers.php';
require_once __DIR__ . '/content-autofix-helpers.php';

function bandpromo_publish_preflight_log_line(string $message, ?callable $logger = null): void
{
    $line = rtrim($message) . "\n";
    if ($logger !== null) {
        $logger($line);
    }
}

function bandpromo_run_publish_preflight(string $root, ?callable $logger = null): array
{
    $summary = [
        'ok' => true,
        'config' => [],
        'release_membership' => [],
        'audio_display' => [],
        'errors' => [],
    ];

    try {
        $configResult = bandpromo_config_repair_structure($root);
        $summary['config'] = $configResult;
        if (!empty($configResult['repaired'])) {
            bandpromo_publish_preflight_log_line(
                '[preflight] Updated site settings structure (' . implode(', ', $configResult['added_sections']) . ').',
                $logger
            );
        }
        if (!empty($configResult['error'])) {
            $summary['errors'][] = (string) $configResult['error'];
        }
    } catch (Throwable $throwable) {
        $summary['errors'][] = $throwable->getMessage();
    }

    try {
        $membership = bandpromo_content_autofix_sync_releases($root, false);
        $summary['release_membership'] = $membership;
        $rebound = (int) ($membership['changed'] ?? 0);
        if ($rebound > 0) {
            bandpromo_publish_preflight_log_line(
                '[preflight] Repaired ' . $rebound . ' stale release/playlist audio link(s).',
                $logger
            );
        }
        foreach (($membership['errors'] ?? []) as $error) {
            $summary['errors'][] = (string) $error;
        }
    } catch (Throwable $throwable) {
        $summary['errors'][] = $throwable->getMessage();
    }

    try {
        // Fill incomplete registry display only — do not overwrite operator-saved metadata.
        $displayResult = bandpromo_asset_refresh_all_audio_displays($root, true);
        $summary['audio_display'] = $displayResult;
        $filled = (int) ($displayResult['changed'] ?? 0);
        if ($filled > 0) {
            bandpromo_publish_preflight_log_line(
                '[preflight] Filled registry display from master tags for ' . $filled . ' audio asset(s).',
                $logger
            );
        }
    } catch (Throwable $throwable) {
        $summary['errors'][] = $throwable->getMessage();
    }

    try {
        $metaRestore = bandpromo_asset_restore_audio_meta_from_unregistered_masters($root);
        $summary['leftover_meta_restore'] = $metaRestore;
        $restored = (int) ($metaRestore['restored'] ?? 0);
        if ($restored > 0) {
            bandpromo_publish_preflight_log_line(
                '[preflight] Restored description/lyrics/cover from leftover masters onto ' . $restored . ' live audio asset(s).',
                $logger
            );
        }
    } catch (Throwable $throwable) {
        $summary['errors'][] = $throwable->getMessage();
    }

    if ($summary['errors'] !== []) {
        $summary['ok'] = false;
        foreach ($summary['errors'] as $error) {
            bandpromo_publish_preflight_log_line('[preflight] Warning: ' . $error, $logger);
        }
    } else {
        bandpromo_publish_preflight_log_line('[preflight] Site settings check passed.', $logger);
        bandpromo_publish_preflight_log_line(
            '[preflight] Catalog preparation runs automatically in the background when uploads need registry or master fixes.',
            $logger
        );
    }

    return $summary;
}
