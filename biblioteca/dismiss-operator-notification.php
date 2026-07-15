<?php
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/auto-build-tasks.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$type = strtolower(trim((string) ($body['type'] ?? '')));
if ($type === 'background-task') {
    $taskId = trim((string) ($body['id'] ?? ''));
    if ($taskId === '') {
        echo json_encode(['error' => 'Task id is required']);
        exit;
    }

    bandpromo_remove_background_task($taskId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($type === 'force-stop-video-delivery') {
    $taskId = trim((string) ($body['id'] ?? ''));
    $result = bandpromo_force_stop_video_delivery([
        'task_id' => $taskId,
        'pause_seconds' => isset($body['pause_seconds']) ? (int) $body['pause_seconds'] : 3600,
        'reason' => 'Force-stopped from Notifications so Site update and Publish can continue.',
    ]);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['error' => 'Unknown notification type']);
exit;
