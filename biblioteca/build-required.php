<?php
/**
 * Build-required state helpers.
 *
 * Tracks whether admin actions changed source media/config and therefore
 * require a new build to publish changes.
 */

function bandpromo_build_required_file(): string {
    return dirname(__DIR__) . '/log/build-required.json';
}

function bandpromo_get_build_required_state(): array {
    $default = [
        'required' => false,
        'action' => 'none',
        'updated_at' => null,
        'reasons' => [],
    ];

    $file = bandpromo_build_required_file();
    if (!file_exists($file)) {
        return $default;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }

    return [
        'required' => !empty($decoded['required']),
        'action' => isset($decoded['action']) ? (string) $decoded['action'] : 'none',
        'updated_at' => isset($decoded['updated_at']) ? (string) $decoded['updated_at'] : null,
        'reasons' => isset($decoded['reasons']) && is_array($decoded['reasons']) ? array_values($decoded['reasons']) : [],
    ];
}

function bandpromo_reason_action(string $reason): string {
    $map = [
        'media_audio_upload' => 'full',
        'media_audio_master_changed' => 'full',
        'media_cover_upload' => 'full',
        'web_config_changed' => 'full',
        'theme_config_changed' => 'full',
        'theme_cover_changed' => 'optimize',
        'site_config_changed' => 'full',
        'social_config_changed' => 'full',
        'media_image_upload' => 'optimize',
    ];
    return $map[$reason] ?? 'none';
}

function bandpromo_action_rank(string $action): int {
    $rank = [
        'none' => 0,
        'optimize' => 1,
        'full' => 2,
    ];
    return $rank[$action] ?? 0;
}

function bandpromo_max_action(array $reasons): string {
    $max = 'none';
    foreach ($reasons as $reason) {
        $action = bandpromo_reason_action((string) $reason);
        if (bandpromo_action_rank($action) > bandpromo_action_rank($max)) {
            $max = $action;
        }
    }
    return $max;
}

function bandpromo_write_build_required_state(array $state): void {
    $file = bandpromo_build_required_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $payload = [
        'required' => !empty($state['required']),
        'action' => isset($state['action']) ? (string) $state['action'] : 'none',
        'updated_at' => $state['updated_at'] ?? gmdate('c'),
        'reasons' => isset($state['reasons']) && is_array($state['reasons']) ? array_values(array_unique($state['reasons'])) : [],
    ];

    file_put_contents(
        $file,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function bandpromo_mark_build_required(string $reason): array {
    $state = bandpromo_get_build_required_state();
    $reasons = $state['reasons'] ?? [];
    if ($reason !== '' && !in_array($reason, $reasons, true)) {
        $reasons[] = $reason;
    }

    $state['required'] = true;
    $state['action'] = bandpromo_max_action($reasons);
    $state['updated_at'] = gmdate('c');
    $state['reasons'] = $reasons;
    bandpromo_write_build_required_state($state);
    return $state;
}

function bandpromo_clear_build_required(): array {
    $state = [
        'required' => false,
        'action' => 'none',
        'updated_at' => gmdate('c'),
        'reasons' => [],
    ];
    bandpromo_write_build_required_state($state);
    return $state;
}

function bandpromo_clear_build_required_for_action(string $completed_action): array {
    if ($completed_action === 'full') {
        return bandpromo_clear_build_required();
    }

    $state = bandpromo_get_build_required_state();
    $reasons = isset($state['reasons']) && is_array($state['reasons']) ? $state['reasons'] : [];

    if ($completed_action === 'optimize') {
        $reasons = array_values(array_filter(
            $reasons,
            static fn($reason) => bandpromo_reason_action((string) $reason) !== 'optimize'
        ));
    }

    $action = bandpromo_max_action($reasons);
    $next = [
        'required' => !empty($reasons),
        'action' => $action,
        'updated_at' => gmdate('c'),
        'reasons' => $reasons,
    ];
    bandpromo_write_build_required_state($next);
    return $next;
}
