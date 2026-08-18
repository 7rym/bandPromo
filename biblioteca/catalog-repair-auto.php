<?php
declare(strict_types=1);

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/content-autofix-helpers.php';

function bandpromo_catalog_repair_lock_path(string $root): string
{
    return rtrim($root, '/\\') . '/log/catalog-repair.lock';
}

function bandpromo_catalog_repair_state_path(string $root): string
{
    return rtrim($root, '/\\') . '/data/catalog-repair-auto.json';
}

function bandpromo_catalog_repair_load_state(string $root): array
{
    $path = bandpromo_catalog_repair_state_path($root);
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function bandpromo_catalog_repair_save_state(string $root, array $state): void
{
    $path = bandpromo_catalog_repair_state_path($root);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return;
    }

    @file_put_contents(
        $path,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

function bandpromo_catalog_repair_is_locked(string $root): bool
{
    $lockPath = bandpromo_catalog_repair_lock_path($root);
    if (!is_file($lockPath)) {
        return false;
    }

    $age = time() - (int) filemtime($lockPath);
    if ($age > 300) {
        @unlink($lockPath);

        return false;
    }

    return true;
}

function bandpromo_catalog_repair_should_run(string $root, array $reconcileResult = []): bool
{
    if (bandpromo_catalog_repair_is_locked($root)) {
        return false;
    }

    $state = bandpromo_catalog_repair_load_state($root);
    $lastApplyAt = (int) ($state['last_apply_at'] ?? 0);
    if ($lastApplyAt > 0 && (time() - $lastApplyAt) < 45) {
        return false;
    }

    $uncatalogued = count(bandpromo_list_uncatalogued_audio_originals($root));
    if ($uncatalogued > 0) {
        return true;
    }

    if ((int) ($reconcileResult['changed'] ?? 0) > 0) {
        return true;
    }

    if (!empty($reconcileResult['failed'])) {
        return false;
    }

    $lastPreviewAt = (int) ($state['last_preview_at'] ?? 0);
    if ($lastPreviewAt > 0 && (time() - $lastPreviewAt) < 600) {
        return false;
    }

    $preview = bandpromo_content_autofix_run($root, true);
    bandpromo_catalog_repair_save_state($root, array_merge($state, [
        'last_preview_at' => time(),
        'last_preview_changed_total' => (int) ($preview['changed_total'] ?? 0),
    ]));

    return (int) ($preview['changed_total'] ?? 0) > 0;
}

function bandpromo_catalog_repair_maybe_run(string $root, array $reconcileResult = []): array
{
    if (bandpromo_catalog_repair_is_locked($root)) {
        return [
            'status' => 'running',
            'message' => 'bandPromo is preparing uploads in the background.',
        ];
    }

    if (!bandpromo_catalog_repair_should_run($root, $reconcileResult)) {
        return [
            'status' => 'idle',
            'message' => '',
        ];
    }

    $lockPath = bandpromo_catalog_repair_lock_path($root);
    $logDir = dirname($lockPath);
    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
        return [
            'status' => 'error',
            'message' => 'Could not start catalogue preparation.',
        ];
    }

    @file_put_contents($lockPath, (string) time());

    try {
        $report = bandpromo_content_autofix_run($root, false);
        $changedTotal = (int) ($report['changed_total'] ?? 0);
        $errors = is_array($report['errors'] ?? null) ? $report['errors'] : [];

        bandpromo_catalog_repair_save_state($root, array_merge(bandpromo_catalog_repair_load_state($root), [
            'last_apply_at' => time(),
            'last_preview_at' => time(),
            'last_apply_changed_total' => $changedTotal,
            'last_errors' => array_slice($errors, 0, 5),
        ]));

        if ($errors !== []) {
            return [
                'status' => 'warning',
                'changed_total' => $changedTotal,
                'errors' => $errors,
                'message' => 'bandPromo could not finish preparing every upload automatically.',
            ];
        }

        if ($changedTotal > 0) {
            return [
                'status' => 'completed',
                'changed_total' => $changedTotal,
                'message' => 'bandPromo prepared uploads and catalogue links automatically.',
            ];
        }

        return [
            'status' => 'idle',
            'message' => '',
        ];
    } catch (Throwable $throwable) {
        return [
            'status' => 'error',
            'message' => $throwable->getMessage(),
        ];
    } finally {
        @unlink($lockPath);
    }
}
