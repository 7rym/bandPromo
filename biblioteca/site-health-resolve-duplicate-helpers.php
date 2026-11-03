<?php
declare(strict_types=1);

/**
 * Resolve a Site health duplicate-conflict cluster via the Python dedupe helpers.
 */

require_once __DIR__ . '/light-build-tasks.php';

/**
 * @param list<mixed> $loserIds
 * @return array{ok:bool,error?:string,keeper_id?:string,loser_ids?:list<string>,removed?:int}
 */
function bandpromo_site_health_resolve_duplicate(string $root, string $keeperId, array $loserIds): array
{
    $keeperId = trim($keeperId);
    if ($keeperId === '' || strncmp($keeperId, 'ast_', 4) !== 0) {
        return ['ok' => false, 'error' => 'Choose which file to keep.'];
    }

    $losers = [];
    foreach ($loserIds as $raw) {
        $aid = trim((string) $raw);
        if ($aid === '' || $aid === $keeperId || strncmp($aid, 'ast_', 4) !== 0) {
            continue;
        }
        if (!in_array($aid, $losers, true)) {
            $losers[] = $aid;
        }
    }
    if ($losers === []) {
        return ['ok' => false, 'error' => 'Need another copy to fold into the one you keep.'];
    }

    $tempDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'temp';
    if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        return ['ok' => false, 'error' => 'Could not prepare a temporary choices file.'];
    }

    $choicesPath = $tempDir . DIRECTORY_SEPARATOR . 'site-health-resolve-' . bin2hex(random_bytes(8)) . '.json';
    $payload = [
        'keeper_id' => $keeperId,
        'loser_ids' => $losers,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || @file_put_contents($choicesPath, $json . "\n") === false) {
        return ['ok' => false, 'error' => 'Could not write temporary choices file.'];
    }

    try {
        $run = bandpromo_run_light_task_with_args(
            'scripts/site_health/resolve_duplicate_cli.py',
            [$choicesPath]
        );
    } finally {
        if (is_file($choicesPath)) {
            @unlink($choicesPath);
        }
    }

    if (empty($run['ok'])) {
        $decoded = bandpromo_decode_light_json_output((string) ($run['output'] ?? ''));
        $err = is_array($decoded) ? trim((string) ($decoded['error'] ?? '')) : '';
        if ($err === '') {
            $err = trim((string) ($run['error'] ?? ''));
        }
        if ($err === '') {
            $err = 'Could not resolve the duplicate copies.';
        }
        return [
            'ok' => false,
            'error' => $err,
            'keeper_id' => $keeperId,
            'loser_ids' => $losers,
        ];
    }

    $decoded = bandpromo_decode_light_json_output((string) ($run['output'] ?? ''));
    if (!is_array($decoded) || empty($decoded['ok'])) {
        $err = is_array($decoded) ? trim((string) ($decoded['error'] ?? '')) : '';
        return [
            'ok' => false,
            'error' => $err !== '' ? $err : 'Resolve finished without a clear result.',
            'keeper_id' => $keeperId,
            'loser_ids' => $losers,
        ];
    }

    return [
        'ok' => true,
        'keeper_id' => (string) ($decoded['keeper_id'] ?? $keeperId),
        'loser_ids' => array_values(array_map('strval', $decoded['loser_ids'] ?? $losers)),
        'removed' => (int) ($decoded['removed'] ?? count($losers)),
    ];
}
