<?php
declare(strict_types=1);

require_once __DIR__ . '/config-repair-helpers.php';

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

    if ($summary['errors'] !== []) {
        $summary['ok'] = false;
        foreach ($summary['errors'] as $error) {
            bandpromo_publish_preflight_log_line('[preflight] Warning: ' . $error, $logger);
        }
    } else {
        bandpromo_publish_preflight_log_line('[preflight] Site settings check passed.', $logger);
        bandpromo_publish_preflight_log_line(
            '[preflight] Catalog repair is not run automatically. Use Repair catalog on the Publish tab if uploads need registry or master fixes.',
            $logger
        );
    }

    return $summary;
}
