<?php
declare(strict_types=1);

/**
 * Install security sanity check and managed-stub repair.
 *
 * Managed Apache/PHP protection stubs are owned by biblioteca/templates/runtime/.
 * Audit compares install paths to those templates. Repair rewrites only those stubs
 * (never web-config.json or operator content).
 */

require_once __DIR__ . '/template-bootstrap.php';

/**
 * @return list<array{id:string,label:string,template:string,target:string,relative:string,kind:string}>
 */
function bandpromo_security_sanity_stub_specs(string $root): array
{
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
    $specs = [];

    foreach (bandpromo_template_map() as $spec) {
        if (($spec['kind'] ?? '') !== 'text') {
            continue;
        }

        $template = (string) ($spec['template'] ?? '');
        $target = (string) ($spec['target'] ?? '');
        if ($template === '' || $target === '') {
            continue;
        }

        $targetNorm = str_replace('\\', '/', $target);
        $relative = $targetNorm;
        if (str_starts_with($targetNorm, $rootNorm . '/')) {
            $relative = substr($targetNorm, strlen($rootNorm) + 1);
        }

        $id = str_replace(['/', '\\', '.'], ['_', '_', '_'], $relative);
        $specs[] = [
            'id' => $id,
            'label' => bandpromo_security_sanity_stub_label($relative),
            'template' => $template,
            'target' => $target,
            'relative' => $relative,
            'kind' => 'text',
        ];
    }

    return $specs;
}

function bandpromo_security_sanity_stub_label(string $relative): string
{
    return match ($relative) {
        '.htaccess' => 'Root Apache rules',
        '.user.ini' => 'PHP upload limits (.user.ini)',
        'play/.htaccess' => 'Player Apache rules',
        'data/.htaccess' => 'Deny HTTP access to data/',
        'log/.htaccess' => 'Deny HTTP access to log/',
        'backups/.htaccess' => 'Deny HTTP access to backups/',
        'media/.htaccess' => 'Media directory listing rules',
        default => $relative,
    };
}

/**
 * @return list<array{id:string,label:string,path:string}>
 */
function bandpromo_security_sanity_directory_specs(): array
{
    return [
        ['id' => 'dir_data', 'label' => 'Runtime data directory', 'path' => 'data'],
        ['id' => 'dir_log', 'label' => 'Runtime log directory', 'path' => 'log'],
        ['id' => 'dir_backups', 'label' => 'Runtime backups directory', 'path' => 'backups'],
        ['id' => 'dir_media', 'label' => 'Runtime media directory', 'path' => 'media'],
    ];
}

function bandpromo_security_normalize_text(string $content): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    return rtrim($normalized) . "\n";
}

/**
 * @return array{status:string,detail:string,repairable:bool,expected_hash:?string,actual_hash:?string}
 */
function bandpromo_security_evaluate_stub(array $spec): array
{
    $templatePath = (string) $spec['template'];
    $targetPath = (string) $spec['target'];

    if (!is_file($templatePath)) {
        return [
            'status' => 'template_missing',
            'detail' => 'Tracked template is missing: ' . $templatePath,
            'repairable' => false,
            'expected_hash' => null,
            'actual_hash' => null,
        ];
    }

    $templateRaw = file_get_contents($templatePath);
    if ($templateRaw === false) {
        return [
            'status' => 'template_missing',
            'detail' => 'Could not read tracked template: ' . $templatePath,
            'repairable' => false,
            'expected_hash' => null,
            'actual_hash' => null,
        ];
    }

    $expected = bandpromo_security_normalize_text($templateRaw);
    $expectedHash = hash('sha256', $expected);

    if (!file_exists($targetPath)) {
        return [
            'status' => 'missing',
            'detail' => 'File is missing and should be seeded from the template.',
            'repairable' => true,
            'expected_hash' => $expectedHash,
            'actual_hash' => null,
        ];
    }

    if (!is_file($targetPath) || !is_readable($targetPath)) {
        return [
            'status' => 'unreadable',
            'detail' => 'Path exists but is not a readable file.',
            'repairable' => true,
            'expected_hash' => $expectedHash,
            'actual_hash' => null,
        ];
    }

    $actualRaw = file_get_contents($targetPath);
    if ($actualRaw === false) {
        return [
            'status' => 'unreadable',
            'detail' => 'Could not read install file.',
            'repairable' => true,
            'expected_hash' => $expectedHash,
            'actual_hash' => null,
        ];
    }

    if (trim($actualRaw) === '') {
        return [
            'status' => 'empty',
            'detail' => 'File exists but is empty.',
            'repairable' => true,
            'expected_hash' => $expectedHash,
            'actual_hash' => hash('sha256', ''),
        ];
    }

    $actual = bandpromo_security_normalize_text($actualRaw);
    $actualHash = hash('sha256', $actual);
    if ($actualHash !== $expectedHash) {
        return [
            'status' => 'drifted',
            'detail' => 'File content does not match the managed template.',
            'repairable' => true,
            'expected_hash' => $expectedHash,
            'actual_hash' => $actualHash,
        ];
    }

    return [
        'status' => 'ok',
        'detail' => 'Matches managed template.',
        'repairable' => false,
        'expected_hash' => $expectedHash,
        'actual_hash' => $actualHash,
    ];
}

/**
 * @return array{status:string,detail:string,repairable:bool}
 */
function bandpromo_security_evaluate_directory(string $root, array $spec): array
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $spec['path']);
    if (is_dir($path)) {
        return [
            'status' => 'ok',
            'detail' => 'Directory exists.',
            'repairable' => false,
        ];
    }

    return [
        'status' => 'missing',
        'detail' => 'Directory is missing.',
        'repairable' => true,
    ];
}

/**
 * @return array{status:string,detail:string,repairable:bool}
 */
function bandpromo_security_evaluate_web_config(string $root): array
{
    $path = $root . DIRECTORY_SEPARATOR . 'web-config.json';
    if (!is_file($path)) {
        return [
            'status' => 'missing',
            'detail' => 'web-config.json is missing. Run setup or seed runtime templates; this tool does not overwrite site config.',
            'repairable' => false,
        ];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return [
            'status' => 'unreadable',
            'detail' => 'web-config.json exists but could not be read.',
            'repairable' => false,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'status' => 'invalid',
            'detail' => 'web-config.json is not valid JSON. Repair via setup/config tools, not security stub overwrite.',
            'repairable' => false,
        ];
    }

    return [
        'status' => 'ok',
        'detail' => 'web-config.json is present and valid JSON.',
        'repairable' => false,
    ];
}

/**
 * @return array{
 *   ok:bool,
 *   secure:bool,
 *   message:string,
 *   checked_at:string,
 *   summary:array<string,int>,
 *   repairable_count:int,
 *   checks:list<array<string,mixed>>
 * }
 */
function bandpromo_security_sanity_check(string $root): array
{
    $checks = [];
    $summary = [
        'ok' => 0,
        'missing' => 0,
        'empty' => 0,
        'drifted' => 0,
        'unreadable' => 0,
        'invalid' => 0,
        'template_missing' => 0,
        'error' => 0,
    ];
    $repairableCount = 0;

    foreach (bandpromo_security_sanity_directory_specs() as $dirSpec) {
        $result = bandpromo_security_evaluate_directory($root, $dirSpec);
        $status = $result['status'];
        if (!isset($summary[$status])) {
            $summary['error']++;
        } else {
            $summary[$status]++;
        }
        if (!empty($result['repairable'])) {
            $repairableCount++;
        }
        $checks[] = [
            'id' => $dirSpec['id'],
            'group' => 'directory',
            'label' => $dirSpec['label'],
            'path' => $dirSpec['path'],
            'status' => $status,
            'detail' => $result['detail'],
            'repairable' => !empty($result['repairable']),
        ];
    }

    $webConfig = bandpromo_security_evaluate_web_config($root);
    $webStatus = $webConfig['status'];
    if (!isset($summary[$webStatus])) {
        $summary['error']++;
    } else {
        $summary[$webStatus]++;
    }
    $checks[] = [
        'id' => 'web_config',
        'group' => 'config',
        'label' => 'Site configuration (web-config.json)',
        'path' => 'web-config.json',
        'status' => $webStatus,
        'detail' => $webConfig['detail'],
        'repairable' => false,
    ];

    foreach (bandpromo_security_sanity_stub_specs($root) as $spec) {
        $result = bandpromo_security_evaluate_stub($spec);
        $status = $result['status'];
        if (!isset($summary[$status])) {
            $summary['error']++;
        } else {
            $summary[$status]++;
        }
        if (!empty($result['repairable'])) {
            $repairableCount++;
        }
        $checks[] = [
            'id' => $spec['id'],
            'group' => 'stub',
            'label' => $spec['label'],
            'path' => $spec['relative'],
            'status' => $status,
            'detail' => $result['detail'],
            'repairable' => !empty($result['repairable']),
            'expected_hash' => $result['expected_hash'],
            'actual_hash' => $result['actual_hash'],
        ];
    }

    $issueCount = (int) $summary['missing']
        + (int) $summary['empty']
        + (int) $summary['drifted']
        + (int) $summary['unreadable']
        + (int) $summary['invalid']
        + (int) $summary['template_missing']
        + (int) $summary['error'];

    $secure = $issueCount === 0;
    if ($secure) {
        $message = 'Install security checks passed. Managed protection stubs match templates.';
    } elseif ($repairableCount > 0) {
        $message = 'Security issues found. Managed stubs can be repaired from templates.';
    } else {
        $message = 'Security issues found that need manual follow-up (not auto-repairable here).';
    }

    $report = [
        'ok' => true,
        'secure' => $secure,
        'message' => $message,
        'checked_at' => gmdate('c'),
        'summary' => $summary,
        'repairable_count' => $repairableCount,
        'checks' => $checks,
    ];

    bandpromo_security_sanity_write_report($root, $report);

    return $report;
}

/**
 * Repair managed directories and protection stubs.
 *
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   message:string,
 *   changed_total:int,
 *   repaired:list<array<string,mixed>>,
 *   skipped:list<array<string,mixed>>,
 *   errors:list<string>,
 *   check:array<string,mixed>
 * }
 */
function bandpromo_security_sanity_repair(string $root, bool $dryRun = false): array
{
    $repaired = [];
    $skipped = [];
    $errors = [];

    foreach (bandpromo_security_sanity_directory_specs() as $dirSpec) {
        $result = bandpromo_security_evaluate_directory($root, $dirSpec);
        if ($result['status'] === 'ok') {
            $skipped[] = [
                'id' => $dirSpec['id'],
                'path' => $dirSpec['path'],
                'reason' => 'already_ok',
            ];
            continue;
        }

        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $dirSpec['path']);
        if ($dryRun) {
            $repaired[] = [
                'id' => $dirSpec['id'],
                'path' => $dirSpec['path'],
                'action' => 'create_directory',
                'from_status' => $result['status'],
            ];
            continue;
        }

        if (!@mkdir($absolute, 0750, true) && !is_dir($absolute)) {
            $errors[] = 'Could not create directory: ' . $dirSpec['path'];
            continue;
        }

        $repaired[] = [
            'id' => $dirSpec['id'],
            'path' => $dirSpec['path'],
            'action' => 'create_directory',
            'from_status' => $result['status'],
        ];
    }

    foreach (bandpromo_security_sanity_stub_specs($root) as $spec) {
        $result = bandpromo_security_evaluate_stub($spec);
        if ($result['status'] === 'ok') {
            $skipped[] = [
                'id' => $spec['id'],
                'path' => $spec['relative'],
                'reason' => 'already_ok',
            ];
            continue;
        }

        if ($result['status'] === 'template_missing') {
            $errors[] = $result['detail'];
            continue;
        }

        if (empty($result['repairable'])) {
            $skipped[] = [
                'id' => $spec['id'],
                'path' => $spec['relative'],
                'reason' => 'not_repairable',
                'status' => $result['status'],
            ];
            continue;
        }

        $templateRaw = file_get_contents((string) $spec['template']);
        if ($templateRaw === false) {
            $errors[] = 'Could not read template for ' . $spec['relative'];
            continue;
        }

        if ($dryRun) {
            $repaired[] = [
                'id' => $spec['id'],
                'path' => $spec['relative'],
                'action' => 'write_stub',
                'from_status' => $result['status'],
            ];
            continue;
        }

        $targetDir = dirname((string) $spec['target']);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            $errors[] = 'Could not create parent directory for ' . $spec['relative'];
            continue;
        }

        if (file_put_contents((string) $spec['target'], $templateRaw) === false) {
            $errors[] = 'Could not write ' . $spec['relative'];
            continue;
        }

        $repaired[] = [
            'id' => $spec['id'],
            'path' => $spec['relative'],
            'action' => 'write_stub',
            'from_status' => $result['status'],
        ];
    }

    $changedTotal = count($repaired);
    $check = bandpromo_security_sanity_check($root);

    if (!empty($errors)) {
        $message = $dryRun
            ? 'Preview finished with errors.'
            : 'Repair finished with errors.';
    } elseif ($changedTotal === 0) {
        $message = 'Nothing to repair. Managed protection files already look correct.';
    } elseif ($dryRun) {
        $message = $changedTotal === 1
            ? 'Preview: 1 managed item would be repaired.'
            : 'Preview: ' . $changedTotal . ' managed items would be repaired.';
    } else {
        $message = $changedTotal === 1
            ? 'Repaired 1 managed security item.'
            : 'Repaired ' . $changedTotal . ' managed security items.';
    }

    return [
        'ok' => empty($errors),
        'dry_run' => $dryRun,
        'message' => $message,
        'changed_total' => $changedTotal,
        'repaired' => $repaired,
        'skipped' => $skipped,
        'errors' => $errors,
        'check' => $check,
    ];
}

/**
 * Persist latest report under log/ for support diagnostics.
 *
 * @param array<string,mixed> $report
 */
function bandpromo_security_sanity_write_report(string $root, array $report): void
{
    $logDir = $root . DIRECTORY_SEPARATOR . 'log';
    if (!is_dir($logDir) && !@mkdir($logDir, 0750, true) && !is_dir($logDir)) {
        return;
    }

    $path = $logDir . DIRECTORY_SEPARATOR . 'security-sanity-latest.json';
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return;
    }

    @file_put_contents($path, $json . "\n");
}
