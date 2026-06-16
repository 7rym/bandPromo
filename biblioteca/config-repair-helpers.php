<?php
declare(strict_types=1);

require_once __DIR__ . '/array-helpers.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/json-file-helpers.php';

function bandpromo_config_template_path(string $root): string
{
    return $root . '/biblioteca/templates/web-config.template.json';
}

function bandpromo_config_runtime_path(string $root): string
{
    return $root . '/web-config.json';
}

function bandpromo_config_missing_top_level_sections(string $root): array
{
    $templatePath = bandpromo_config_template_path($root);
    $configPath = bandpromo_config_runtime_path($root);
    if (!is_file($templatePath) || !is_file($configPath)) {
        return [];
    }

    $template = json_decode((string) file_get_contents($templatePath), true);
    $current = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($template) || !is_array($current)) {
        return [];
    }

    return array_values(array_diff(array_keys($template), array_keys($current)));
}

function bandpromo_config_repair_structure(string $root): array
{
    $result = [
        'repaired' => false,
        'added_sections' => [],
        'error' => null,
    ];

    $templatePath = bandpromo_config_template_path($root);
    $configPath = bandpromo_config_runtime_path($root);
    if (!is_file($templatePath) || !is_file($configPath)) {
        return $result;
    }

    $missing = bandpromo_config_missing_top_level_sections($root);
    if ($missing === []) {
        return $result;
    }

    $template = json_decode((string) file_get_contents($templatePath), true);
    $current = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($template) || !is_array($current)) {
        $result['error'] = 'Invalid config or template JSON';
        return $result;
    }

    $repaired = bandpromo_deep_merge($template, $current);
    bandpromo_sync_scoped_config_fields($repaired, ['site', 'social', 'media']);

    if (!bandpromo_json_write_file($configPath, $repaired)) {
        $result['error'] = 'Could not write web-config.json';
        return $result;
    }

    bandpromo_reload_runtime_config($configPath);

    $result['repaired'] = true;
    $result['added_sections'] = $missing;

    return $result;
}
