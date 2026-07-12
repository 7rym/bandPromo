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
