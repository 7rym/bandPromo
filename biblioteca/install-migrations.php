<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/package-updater.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/build-required.php';

/** One-shot Site update migration shipped in build 422. */
const BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_ID = 'orphan-primary-uploads-b422';
const BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_MIN_BUILD = 422;

function bandpromo_install_migrations_dir(string $root): string
{
    return rtrim($root, '/\\') . '/data/install/migrations';
}

function bandpromo_install_migration_marker_path(string $root, string $migrationId): string
{
    return bandpromo_install_migrations_dir($root) . '/' . $migrationId . '.json';
}

function bandpromo_install_migration_load_marker(string $root, string $migrationId): ?array
{
    $path = bandpromo_install_migration_marker_path($root, $migrationId);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_install_migration_is_applied(string $root, string $migrationId): bool
{
    return bandpromo_install_migration_load_marker($root, $migrationId) !== null;
}

function bandpromo_install_migration_mark_applied(string $root, string $migrationId, array $meta): void
{
    $dir = bandpromo_install_migrations_dir($root);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create install migrations directory.');
    }

    $payload = array_merge([
        'id' => $migrationId,
        'applied_at' => gmdate('c'),
        'installed_version' => bandpromo_package_read_installed_version($root),
    ], $meta);

    $path = bandpromo_install_migration_marker_path($root, $migrationId);
    if (file_put_contents(
        $path,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    ) === false) {
        throw new RuntimeException('Could not write install migration marker.');
    }
}

function bandpromo_install_migration_build_number(string $version): int
{
    $parsed = bandpromo_release_parse_version(trim($version));

    return is_array($parsed) ? (int) ($parsed['build'] ?? 0) : 0;
}

/**
 * One-time build 422 migration: orphan uploads stuck on invisible primary / Default release.
 *
 * @param array{dry_run?: bool, trigger?: string, previous_version?: string} $context
 * @return array{
 *   ok: bool,
 *   id: string,
 *   applied: bool,
 *   skipped: bool,
 *   dry_run: bool,
 *   skip_reason?: string,
 *   tracks_orphaned?: int,
 *   registry_cleared?: int,
 *   changed?: int,
 *   message?: string
 * }
 */
function bandpromo_install_migration_run_orphan_primary_uploads(string $root, array $context = []): array
{
    $migrationId = BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_ID;
    $dryRun = !empty($context['dry_run']);
    $trigger = trim((string) ($context['trigger'] ?? 'unknown'));
    $installedVersion = bandpromo_package_read_installed_version($root);
    $installedBuild = bandpromo_install_migration_build_number($installedVersion);

    $base = [
        'ok' => true,
        'id' => $migrationId,
        'applied' => false,
        'skipped' => true,
        'dry_run' => $dryRun,
    ];

    if (bandpromo_install_migration_is_applied($root, $migrationId)) {
        return array_merge($base, [
            'skip_reason' => 'Migration already applied on this install.',
        ]);
    }

    if ($installedBuild < BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_MIN_BUILD) {
        return array_merge($base, [
            'skip_reason' => 'Install is older than build '
                . BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_MIN_BUILD
                . '; update first.',
        ]);
    }

    $previousVersion = trim((string) ($context['previous_version'] ?? ''));
    if ($previousVersion !== '') {
        $previousBuild = bandpromo_install_migration_build_number($previousVersion);
        if ($previousBuild >= BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_MIN_BUILD) {
            return array_merge($base, [
                'skip_reason' => 'Install was already on build '
                    . BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_MIN_BUILD
                    . ' or newer before this run.',
            ]);
        }
    }

    $result = bandpromo_campaign_orphan_uploads_on_primary($root, $dryRun);
    if (empty($result['ok'])) {
        return array_merge($base, [
            'ok' => false,
            'skipped' => false,
            'skip_reason' => (string) ($result['skip_reason'] ?? 'Orphan repair failed.'),
        ]);
    }

    if (!empty($result['skipped'])) {
        if (!$dryRun) {
            bandpromo_install_migration_mark_applied($root, $migrationId, [
                'trigger' => $trigger,
                'previous_version' => $previousVersion,
                'tracks_orphaned' => 0,
                'registry_cleared' => 0,
                'note' => (string) ($result['skip_reason'] ?? 'Nothing to repair.'),
            ]);
        }

        return array_merge($base, [
            'applied' => !$dryRun,
            'skipped' => true,
            'skip_reason' => (string) ($result['skip_reason'] ?? 'Nothing to repair.'),
            'tracks_orphaned' => 0,
            'registry_cleared' => 0,
            'changed' => 0,
        ]);
    }

    $tracksOrphaned = (int) ($result['tracks_orphaned'] ?? 0);
    $registryCleared = (int) ($result['registry_cleared'] ?? 0);
    $changed = (int) ($result['changed'] ?? 0);

    if ($dryRun) {
        return array_merge($base, [
            'skipped' => false,
            'tracks_orphaned' => $tracksOrphaned,
            'registry_cleared' => $registryCleared,
            'changed' => $changed,
            'message' => $changed > 0
                ? 'Would orphan ' . $tracksOrphaned . ' upload(s) stuck on Default release.'
                : 'No Default release upload bucket repairs needed.',
        ]);
    }

    bandpromo_install_migration_mark_applied($root, $migrationId, [
        'trigger' => $trigger,
        'previous_version' => $previousVersion,
        'tracks_orphaned' => $tracksOrphaned,
        'registry_cleared' => $registryCleared,
    ]);

    if ($changed > 0) {
        bandpromo_mark_build_required('content_autofix');
    }

    return array_merge($base, [
        'applied' => true,
        'skipped' => false,
        'tracks_orphaned' => $tracksOrphaned,
        'registry_cleared' => $registryCleared,
        'changed' => $changed,
        'message' => $changed > 0
            ? 'Orphaned ' . $tracksOrphaned . ' upload(s) that were stuck on Default release.'
            : 'Default release upload bucket cleared.',
    ]);
}

/**
 * Run pending install migrations after Site update or bootstrap.
 *
 * @return array<string, array<string, mixed>>
 */
function bandpromo_install_migrations_run_after_update(string $root, array $applyResult = []): array
{
    $previousVersion = trim((string) ($applyResult['previous_version'] ?? ''));

    return [
        BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_ID => bandpromo_install_migration_run_orphan_primary_uploads($root, [
            'trigger' => 'package_update',
            'previous_version' => $previousVersion,
        ]),
    ];
}
