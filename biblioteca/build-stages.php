<?php
declare(strict_types=1);

function bandpromo_build_stages_manifest_path(): string
{
    return dirname(__DIR__) . '/scripts/build-stages.json';
}

function bandpromo_build_stages_manifest(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $path = bandpromo_build_stages_manifest_path();
    if (!is_file($path)) {
        throw new RuntimeException('Build stage manifest is missing: scripts/build-stages.json');
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Could not read build stage manifest.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Build stage manifest is not valid JSON.');
    }

    $cached = $decoded;

    return $cached;
}

function bandpromo_build_stage_ids(): array
{
    $manifest = bandpromo_build_stages_manifest();
    $ids = [];
    foreach ($manifest['stages'] ?? [] as $stage) {
        if (!is_array($stage)) {
            continue;
        }
        $id = trim((string) ($stage['id'] ?? ''));
        if ($id !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

function bandpromo_build_profile_ids(): array
{
    $manifest = bandpromo_build_stages_manifest();
    $profiles = $manifest['profiles'] ?? [];
    if (!is_array($profiles)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', array_keys($profiles))));
}

function bandpromo_build_profile_is_valid(string $profile): bool
{
    return in_array($profile, bandpromo_build_profile_ids(), true);
}

function bandpromo_build_filter_stage_ids(array $requested): array
{
    $allowed = array_fill_keys(bandpromo_build_stage_ids(), true);
    $filtered = [];
    foreach ($requested as $id) {
        $id = trim((string) $id);
        if ($id === '' || !isset($allowed[$id])) {
            continue;
        }
        if (!in_array($id, $filtered, true)) {
            $filtered[] = $id;
        }
    }

    return $filtered;
}

function bandpromo_build_resolve_stage_ids(?string $profile, ?array $requestedStages): array
{
    if (is_array($requestedStages) && $requestedStages !== []) {
        $filtered = bandpromo_build_filter_stage_ids($requestedStages);
        if ($filtered !== []) {
            return $filtered;
        }
    }

    $profile = trim((string) ($profile ?? 'full'));
    if ($profile === '' || !bandpromo_build_profile_is_valid($profile)) {
        $profile = 'full';
    }

    $manifest = bandpromo_build_stages_manifest();
    $profileStages = $manifest['profiles'][$profile] ?? [];
    if (!is_array($profileStages)) {
        return [];
    }

    return bandpromo_build_filter_stage_ids($profileStages);
}
