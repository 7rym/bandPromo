<?php
declare(strict_types=1);

function bandpromo_environment_pdo_sqlite_available(): bool
{
    return extension_loaded('pdo_sqlite');
}

function bandpromo_environment_check_pdo_sqlite(): array
{
    $ok = bandpromo_environment_pdo_sqlite_available();

    return [
        'label' => 'PDO SQLite available',
        'ok' => $ok,
        'detail' => $ok
            ? 'Available'
            : 'Missing pdo_sqlite extension (required for listener activity logs and analytics)',
    ];
}

function bandpromo_environment_pdo_sqlite_setup_error(): string
{
    return 'PDO SQLite (pdo_sqlite) is required for listener activity logging and analytics. Ask your hosting provider to enable the PHP pdo_sqlite extension.';
}

function bandpromo_environment_default_release_requirements(): array
{
    return [
        'php_min' => '8.0.0',
        'php_extensions' => ['pdo_sqlite'],
        'php_classes' => ['ZipArchive'],
    ];
}

function bandpromo_environment_normalize_release_requirements(?array $requirements): array
{
    $defaults = bandpromo_environment_default_release_requirements();
    if (!is_array($requirements)) {
        return $defaults;
    }

    $normalized = $defaults;

    if (isset($requirements['php_min']) && is_string($requirements['php_min']) && trim($requirements['php_min']) !== '') {
        $normalized['php_min'] = trim($requirements['php_min']);
    }

    if (isset($requirements['php_extensions']) && is_array($requirements['php_extensions'])) {
        $normalized['php_extensions'] = array_values(array_filter(array_map(
            static fn ($extension): string => trim((string) $extension),
            $requirements['php_extensions']
        ), static fn (string $extension): bool => $extension !== ''));
    }

    if (isset($requirements['php_classes']) && is_array($requirements['php_classes'])) {
        $normalized['php_classes'] = array_values(array_filter(array_map(
            static fn ($class): string => trim((string) $class),
            $requirements['php_classes']
        ), static fn (string $class): bool => $class !== ''));
    }

    return $normalized;
}

function bandpromo_environment_validate_release_requirements(array $requirements, ?string $targetVersion = null): array
{
    $requirements = bandpromo_environment_normalize_release_requirements($requirements);
    $checks = [];
    $versionLabel = trim((string) ($targetVersion ?? ''));
    $scope = $versionLabel !== '' ? (' for ' . $versionLabel) : '';

    $phpMin = (string) ($requirements['php_min'] ?? '8.0.0');
    $phpOk = version_compare(PHP_VERSION, $phpMin, '>=');
    $checks[] = [
        'id' => 'php_min',
        'label' => 'PHP ' . $phpMin . '+',
        'ok' => $phpOk,
        'detail' => $phpOk ? ('Running ' . PHP_VERSION) : ('Running ' . PHP_VERSION . '; PHP ' . $phpMin . ' or newer is required' . $scope),
        'blocking' => true,
    ];

    foreach ($requirements['php_extensions'] as $extension) {
        $ok = extension_loaded($extension);
        $checks[] = [
            'id' => 'ext_' . $extension,
            'label' => 'PHP extension: ' . $extension,
            'ok' => $ok,
            'detail' => $ok
                ? 'Available'
                : ('Missing ' . $extension . ' extension' . $scope),
            'blocking' => true,
        ];
    }

    foreach ($requirements['php_classes'] as $className) {
        $ok = class_exists($className);
        $checks[] = [
            'id' => 'class_' . $className,
            'label' => $className . ' available',
            'ok' => $ok,
            'detail' => $ok
                ? 'Available'
                : ('Missing ' . $className . $scope),
            'blocking' => true,
        ];
    }

    $ok = true;
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $ok = false;
            break;
        }
    }

    return [
        'ok' => $ok,
        'checks' => $checks,
        'requirements' => $requirements,
    ];
}

function bandpromo_environment_release_requirements_error(array $status, ?string $targetVersion = null): string
{
    $failed = [];
    foreach ($status['checks'] ?? [] as $check) {
        if (!is_array($check) || !empty($check['ok'])) {
            continue;
        }
        $label = trim((string) ($check['label'] ?? 'Requirement'));
        $detail = trim((string) ($check['detail'] ?? ''));
        $failed[] = $detail !== '' ? ($label . ': ' . $detail) : $label;
    }

    $versionLabel = trim((string) ($targetVersion ?? ''));
    $prefix = $versionLabel !== ''
        ? ('This server does not meet the requirements for ' . $versionLabel . '. ')
        : 'This server does not meet the published release requirements. ';

    if ($failed === []) {
        return $prefix . 'Fix the hosting requirements shown in the update panel before installing.';
    }

    return $prefix . implode(' ', $failed);
}
