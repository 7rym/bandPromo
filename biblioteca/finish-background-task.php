<?php
/**
 * Finalize a background delivery task after the async runner exits.
 *
 * CLI only:
 *   php finish-background-task.php --task-id=<id> --result-file=<path> [--error-file=<path>]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/auto-build-tasks.php';

function finish_cli_arg(string $name): string
{
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with((string) $arg, $prefix)) {
            return substr((string) $arg, strlen($prefix));
        }
    }

    return '';
}

$taskId = trim(finish_cli_arg('task-id'));
$resultFile = trim(finish_cli_arg('result-file'));
$errorFile = trim(finish_cli_arg('error-file'));

if ($taskId === '') {
    fwrite(STDERR, "Missing --task-id\n");
    exit(1);
}

$ok = bandpromo_finalize_background_task_from_files($taskId, $resultFile, $errorFile);
exit($ok ? 0 : 1);
