<?php
declare(strict_types=1);

/**
 * CLI: register a Visual original already on disk (media/visual/original or legacy intake).
 * Usage: php scripts/register_visual_original.php <filename> [role]
 * Prints the visual asset id on success.
 */
$root = dirname(__DIR__);
require $root . '/biblioteca/asset-registry.php';

$filename = basename(trim((string) ($argv[1] ?? '')));
$role = trim((string) ($argv[2] ?? 'track-cover'));
if ($filename === '') {
    fwrite(STDERR, "Missing filename\n");
    exit(1);
}

try {
    $visual = bandpromo_asset_register_visual($root, $filename, 'img', 'image', [
        'role' => $role !== '' ? $role : 'track-cover',
    ]);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$id = trim((string) ($visual['id'] ?? ''));
if ($id === '') {
    fwrite(STDERR, "Registration returned no asset id\n");
    exit(1);
}

fwrite(STDOUT, $id . PHP_EOL);
exit(0);
