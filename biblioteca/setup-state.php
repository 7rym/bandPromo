<?php

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/config-loader.php';

function bandpromo_is_setup_complete(): bool
{
    $marker = __DIR__ . '/../data/.setup_complete';
    if (file_exists($marker)) {
        return true;
    }

    if (!bandpromo_is_local_dev_host()) {
        return false;
    }

    $configPath = __DIR__ . '/../web-config.json';
    $tercesPath = __DIR__ . '/../data/terces';

    if (!file_exists($configPath) || !file_exists($tercesPath)) {
        return false;
    }

    $configRaw = @file_get_contents($configPath);
    $config = json_decode($configRaw ?: '{}', true);
    if (!is_array($config) || trim((string) bandpromo_config_get_value($config, 'release.identity.title', '')) === '') {
        return false;
    }

    $credentials = trim((string) @file_get_contents($tercesPath));
    return $credentials !== '';
}