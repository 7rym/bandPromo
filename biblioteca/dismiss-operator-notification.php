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

echo json_encode(['error' => 'Unknown notification type']);
exit;
