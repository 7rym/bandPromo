<?php
/**
 * Move a real campaign off the invisible `primary` orphan/upload bucket.
 *
 * Usage (from repo root):
 *   php scripts/migrate_primary_campaign.php --dry-run
 *   php scripts/migrate_primary_campaign.php
 *   php scripts/migrate_primary_campaign.php --new-id=winter-party
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/biblioteca/campaign-storage.php';

$dryRun = in_array('--dry-run', $argv, true);
$newId = 'winter-party';
foreach ($argv as $arg) {
    if (strpos($arg, '--new-id=') === 0) {
        $newId = substr($arg, strlen('--new-id='));
    }
}

$result = bandpromo_campaign_migrate_campaign_off_primary($root, $newId, $dryRun);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
