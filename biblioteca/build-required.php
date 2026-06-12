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
        'tasks' => [],
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
        'action' => isset($decoded['tasks']) && is_array($decoded['tasks'])
            ? bandpromo_max_action_for_tasks($decoded['tasks'])
            : (isset($decoded['action']) ? (string) $decoded['action'] : 'none'),
        'updated_at' => isset($decoded['updated_at']) ? (string) $decoded['updated_at'] : null,
        'reasons' => isset($decoded['reasons']) && is_array($decoded['reasons']) ? array_values($decoded['reasons']) : [],
        'tasks' => isset($decoded['tasks']) && is_array($decoded['tasks']) ? array_values($decoded['tasks']) : bandpromo_collect_tasks_for_reasons(isset($decoded['reasons']) && is_array($decoded['reasons']) ? $decoded['reasons'] : []),
    ];
}

function bandpromo_reason_tasks(string $reason): array {
    $map = [
        'media_audio_upload' => ['playlist-scan', 'audio-delivery'],
        'media_audio_master_changed' => ['audio-delivery'],
        'media_cover_upload' => ['audio-delivery', 'image-delivery'],
        'media_video_upload' => ['video-delivery'],
        'web_config_changed' => ['manifest'],
        'theme_config_changed' => ['image-delivery', 'social-assets'],
        'theme_cover_changed' => ['image-delivery'],
        'site_config_changed' => ['manifest'],
        'social_config_changed' => ['social-assets', 'manifest'],
        'media_image_upload' => ['image-delivery'],
    ];

    return $map[$reason] ?? [];
}

function bandpromo_collect_tasks_for_reasons(array $reasons): array {
    $tasks = [];
    foreach ($reasons as $reason) {
        foreach (bandpromo_reason_tasks((string) $reason) as $task) {
            if (!in_array($task, $tasks, true)) {
                $tasks[] = $task;
            }
        }
    }

    return $tasks;
}

function bandpromo_merge_tasks(array $existingTasks, array $newTasks): array {
    $merged = [];
    foreach (array_merge($existingTasks, $newTasks) as $task) {
        $task = trim((string) $task);
        if ($task === '' || in_array($task, $merged, true)) {
            continue;
        }
        $merged[] = $task;
    }

    return $merged;
}

function bandpromo_task_action(string $task): string {
    $map = [
        'playlist-scan' => 'full',
        'audio-delivery' => 'full',
        'video-delivery' => 'full',
        'image-delivery' => 'optimize',
        'social-assets' => 'full',
        'manifest' => 'full',
    ];

    return $map[$task] ?? 'none';
}

function bandpromo_max_action_for_tasks(array $tasks): string {
    $max = 'none';
    foreach ($tasks as $task) {
        $action = bandpromo_task_action((string) $task);
        if (bandpromo_action_rank($action) > bandpromo_action_rank($max)) {
            $max = $action;
        }
    }
    return $max;
}

function bandpromo_filter_reasons_for_tasks(array $reasons, array $tasks): array {
    if (empty($tasks)) {
        return [];
    }

    $filtered = [];
    foreach ($reasons as $reason) {
        foreach (bandpromo_reason_tasks((string) $reason) as $task) {
            if (in_array($task, $tasks, true)) {
                $filtered[] = (string) $reason;
                break;
            }
        }
    }

    return array_values(array_unique($filtered));
}

function bandpromo_reason_action(string $reason): string {
    $map = [
        'media_audio_upload' => 'full',
        'media_audio_master_changed' => 'full',
        'media_cover_upload' => 'full',
        'media_video_upload' => 'full',
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
        'tasks' => isset($state['tasks']) && is_array($state['tasks']) ? array_values(array_unique($state['tasks'])) : bandpromo_collect_tasks_for_reasons(isset($state['reasons']) && is_array($state['reasons']) ? $state['reasons'] : []),
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

    $tasks = bandpromo_merge_tasks($state['tasks'] ?? [], bandpromo_collect_tasks_for_reasons($reasons));

    $state['required'] = !empty($tasks);
    $state['action'] = bandpromo_max_action_for_tasks($tasks);
    $state['updated_at'] = gmdate('c');
    $state['reasons'] = $reasons;
    $state['tasks'] = $tasks;
    bandpromo_write_build_required_state($state);
    return $state;
}

function bandpromo_clear_build_required(): array {
    $state = [
        'required' => false,
        'action' => 'none',
        'updated_at' => gmdate('c'),
        'reasons' => [],
        'tasks' => [],
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
    $tasks = isset($state['tasks']) && is_array($state['tasks']) ? $state['tasks'] : bandpromo_collect_tasks_for_reasons($reasons);

    if ($completed_action === 'optimize') {
        $tasks = array_values(array_filter(
            $tasks,
            static fn($task) => bandpromo_task_action((string) $task) !== 'optimize'
        ));
    }

    $reasons = bandpromo_filter_reasons_for_tasks($reasons, $tasks);
    $action = bandpromo_max_action_for_tasks($tasks);
    $next = [
        'required' => !empty($tasks),
        'action' => $action,
        'updated_at' => gmdate('c'),
        'reasons' => $reasons,
        'tasks' => $tasks,
    ];
    bandpromo_write_build_required_state($next);
    return $next;
}

function bandpromo_clear_build_required_tasks(array $completedTasks): array {
    if ($completedTasks === []) {
        return bandpromo_get_build_required_state();
    }

    $state = bandpromo_get_build_required_state();
    $tasks = isset($state['tasks']) && is_array($state['tasks']) ? $state['tasks'] : [];
    $remainingTasks = array_values(array_filter(
        $tasks,
        static fn($task) => !in_array((string) $task, $completedTasks, true)
    ));
    $reasons = bandpromo_filter_reasons_for_tasks(isset($state['reasons']) && is_array($state['reasons']) ? $state['reasons'] : [], $remainingTasks);

    $next = [
        'required' => !empty($remainingTasks),
        'action' => bandpromo_max_action_for_tasks($remainingTasks),
        'updated_at' => gmdate('c'),
        'reasons' => $reasons,
        'tasks' => $remainingTasks,
    ];
    bandpromo_write_build_required_state($next);
    return $next;
}
