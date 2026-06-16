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
        'autofix' => [],
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
        $autofix = bandpromo_content_autofix_run($root, false);
        $summary['autofix'] = $autofix;
        if ((int) ($autofix['changed_total'] ?? 0) > 0) {
            bandpromo_publish_preflight_log_line(
                '[preflight] Prepared content links and catalog entries (' . (int) $autofix['changed_total'] . ' updates).',
                $logger
            );
        }
        if (!empty($autofix['errors']) && is_array($autofix['errors'])) {
            foreach ($autofix['errors'] as $error) {
                $summary['errors'][] = (string) $error;
            }
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
        bandpromo_publish_preflight_log_line('[preflight] Site content is ready for publish.', $logger);
    }

    return $summary;
}
