<?php
declare(strict_types=1);

/**
 * Structured host environment snapshot for System → Environment (developer).
 * No secrets, no phpinfo dump.
 */

require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/environment-checks.php';
require_once __DIR__ . '/package-updater.php';

function bandpromo_environment_read_version(string $root): string
{
    $path = rtrim($root, '/\\') . '/VERSION';
    if (!is_file($path)) {
        return '';
    }
    $raw = trim((string) @file_get_contents($path));
    return $raw;
}

function bandpromo_environment_ffmpeg_path(string $root): string
{
    $bundled = rtrim($root, '/\\') . '/scripts/bin/ffmpeg';
    if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
        $bundled .= '.exe';
    }
    if (is_file($bundled)) {
        return $bundled;
    }

    if (bandpromo_build_function_usable('shell_exec')) {
        $which = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($which !== '') {
            return $which;
        }
    }

    return '';
}

function bandpromo_environment_python_hint(): string
{
    if (!bandpromo_build_function_usable('shell_exec')) {
        return '';
    }
    $path = trim((string) shell_exec('command -v python3 2>/dev/null'));
    if ($path === '') {
        $path = trim((string) shell_exec('command -v python 2>/dev/null'));
    }
    return $path;
}

/**
 * Human-readable byte size for environment reports.
 */
function bandpromo_environment_format_bytes($bytes): string
{
    if ($bytes === null || $bytes === false) {
        return '(unknown)';
    }
    $bytes = (float) $bytes;
    if ($bytes < 0) {
        return '(unknown)';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    $precision = $i === 0 ? 0 : 1;

    return number_format($bytes, $precision, '.', '') . ' ' . $units[$i];
}

/**
 * Best-effort read of a small text file (e.g. /proc/*). Empty when blocked.
 */
function bandpromo_environment_read_text_file(string $path, int $maxBytes = 65536): string
{
    $path = trim($path);
    if ($path === '' || !@is_readable($path)) {
        return '';
    }
    $raw = @file_get_contents($path, false, null, 0, $maxBytes);
    if (!is_string($raw) || $raw === '') {
        return '';
    }

    return $raw;
}

/**
 * Optional shell one-liner when PHP cannot open /proc (common with open_basedir).
 */
function bandpromo_environment_shell_line(string $command): string
{
    if (!bandpromo_build_function_usable('shell_exec')) {
        return '';
    }
    $out = trim((string) @shell_exec($command . ' 2>/dev/null'));
    if ($out === '' || stripos($out, 'Permission denied') !== false) {
        return '';
    }

    return $out;
}

/**
 * Probe host resources available to this PHP process / account.
 * Shared hosts often hide real machine hardware; report what we can and mark the rest unavailable.
 *
 * @return array<string, mixed>
 */
function bandpromo_environment_probe_resources(string $root): array
{
    $notes = [];
    $uname = function_exists('php_uname') ? trim((string) @php_uname()) : '';
    $architecture = function_exists('php_uname') ? trim((string) @php_uname('m')) : '';

    $diskTotal = @disk_total_space($root);
    $diskFree = @disk_free_space($root);
    $disk = [
        'path' => $root,
        'total_bytes' => is_float($diskTotal) || is_int($diskTotal) ? (int) $diskTotal : null,
        'free_bytes' => is_float($diskFree) || is_int($diskFree) ? (int) $diskFree : null,
        'total_label' => bandpromo_environment_format_bytes($diskTotal),
        'free_label' => bandpromo_environment_format_bytes($diskFree),
        'note' => 'Account/install visibility — may be a quota, not the whole server disk.',
    ];
    if ($disk['total_bytes'] === null && $disk['free_bytes'] === null) {
        $notes[] = 'Disk space probe unavailable for the install root.';
    }

    $memLimit = trim((string) ini_get('memory_limit'));
    $phpMemory = [
        'limit' => $memLimit !== '' ? $memLimit : '(unknown)',
        'usage_bytes' => function_exists('memory_get_usage') ? (int) memory_get_usage(true) : null,
        'peak_bytes' => function_exists('memory_get_peak_usage') ? (int) memory_get_peak_usage(true) : null,
        'usage_label' => function_exists('memory_get_usage')
            ? bandpromo_environment_format_bytes(memory_get_usage(true))
            : '(unknown)',
        'peak_label' => function_exists('memory_get_peak_usage')
            ? bandpromo_environment_format_bytes(memory_get_peak_usage(true))
            : '(unknown)',
        'note' => 'PHP process limit and current usage — not host physical RAM.',
    ];

    $loadAverage = null;
    if (function_exists('sys_getloadavg')) {
        $load = @sys_getloadavg();
        if (is_array($load) && count($load) >= 3) {
            $loadAverage = [
                '1m' => round((float) $load[0], 2),
                '5m' => round((float) $load[1], 2),
                '15m' => round((float) $load[2], 2),
            ];
        }
    }

    $cpu = [
        'available' => false,
        'model' => '',
        'logical_cpus' => null,
        'source' => '',
    ];
    $cpuinfo = bandpromo_environment_read_text_file('/proc/cpuinfo');
    $cpuSource = 'proc';
    if ($cpuinfo === '') {
        $cpuinfo = bandpromo_environment_shell_line('cat /proc/cpuinfo');
        $cpuSource = 'shell';
    }
    if ($cpuinfo !== '') {
        $model = '';
        if (preg_match('/^model name\s*:\s*(.+)$/mi', $cpuinfo, $m) === 1) {
            $model = trim((string) $m[1]);
        } elseif (preg_match('/^Hardware\s*:\s*(.+)$/mi', $cpuinfo, $m) === 1) {
            $model = trim((string) $m[1]);
        }
        $logical = preg_match_all('/^processor\s*:/mi', $cpuinfo);
        if (!is_int($logical) || $logical < 1) {
            $nproc = bandpromo_environment_shell_line('nproc');
            if ($nproc !== '' && ctype_digit($nproc)) {
                $logical = (int) $nproc;
                $cpuSource = $cpuSource === 'proc' ? 'proc+nproc' : 'shell';
            } else {
                $logical = 0;
            }
        }
        $cpu['available'] = ($model !== '' || $logical > 0);
        $cpu['model'] = $model;
        $cpu['logical_cpus'] = $logical > 0 ? $logical : null;
        $cpu['source'] = $cpu['available'] ? $cpuSource : '';
    }
    if (!$cpu['available']) {
        $notes[] = 'CPU details unavailable (no /proc/cpuinfo access on this host).';
    }

    $ram = [
        'available' => false,
        'total_bytes' => null,
        'available_bytes' => null,
        'total_label' => '(unavailable)',
        'available_label' => '(unavailable)',
        'source' => '',
        'note' => 'Host MemTotal when /proc/meminfo is readable — often the whole machine, not your account quota.',
    ];
    $meminfo = bandpromo_environment_read_text_file('/proc/meminfo');
    $ramSource = 'proc';
    if ($meminfo === '') {
        $meminfo = bandpromo_environment_shell_line('cat /proc/meminfo');
        $ramSource = 'shell';
    }
    if ($meminfo !== '') {
        $totalKb = null;
        $availKb = null;
        if (preg_match('/^MemTotal:\s+(\d+)\s+kB/mi', $meminfo, $m) === 1) {
            $totalKb = (int) $m[1];
        }
        if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/mi', $meminfo, $m) === 1) {
            $availKb = (int) $m[1];
        } elseif (preg_match('/^MemFree:\s+(\d+)\s+kB/mi', $meminfo, $m) === 1) {
            $availKb = (int) $m[1];
        }
        if ($totalKb !== null && $totalKb > 0) {
            $ram['available'] = true;
            $ram['total_bytes'] = $totalKb * 1024;
            $ram['total_label'] = bandpromo_environment_format_bytes($ram['total_bytes']);
            $ram['source'] = $ramSource;
            if ($availKb !== null) {
                $ram['available_bytes'] = $availKb * 1024;
                $ram['available_label'] = bandpromo_environment_format_bytes($ram['available_bytes']);
            }
        }
    }
    if (!$ram['available']) {
        $notes[] = 'Host RAM details unavailable (no /proc/meminfo access on this host).';
    }

    return [
        'uname' => $uname,
        'architecture' => $architecture,
        'disk' => $disk,
        'php_memory' => $phpMemory,
        'load_average' => $loadAverage,
        'cpu' => $cpu,
        'ram' => $ram,
        'notes' => $notes,
    ];
}

/**
 * Whether this PHP build can open outbound HTTPS URLs.
 */
function bandpromo_environment_https_egress_available(): bool
{
    if (extension_loaded('curl') && function_exists('curl_init')) {
        return true;
    }

    return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)
        && extension_loaded('openssl');
}

/**
 * Bounded HTTP GET for Environment probes (hard time + byte budget).
 *
 * @return array{ok:bool,bytes:int,latency_ms:?float,error:string,status:int}
 */
function bandpromo_environment_http_fetch(string $url, int $maxBytes, float $timeoutSeconds): array
{
    $maxBytes = max(1, $maxBytes);
    $timeoutSeconds = max(0.5, min(5.0, $timeoutSeconds));
    $started = microtime(true);
    $result = [
        'ok' => false,
        'bytes' => 0,
        'latency_ms' => null,
        'error' => '',
        'status' => 0,
    ];

    if (extension_loaded('curl') && function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'Could not initialise cURL.';

            return $result;
        }

        $bytes = 0;
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => (int) max(1, ceil($timeoutSeconds)),
            CURLOPT_TIMEOUT => (int) max(1, ceil($timeoutSeconds)),
            CURLOPT_USERAGENT => 'bandPromo environment probe',
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$bytes, &$body, $maxBytes) {
                $len = strlen($chunk);
                if (($bytes + $len) > $maxBytes) {
                    $keep = $maxBytes - $bytes;
                    if ($keep > 0) {
                        $body .= substr($chunk, 0, $keep);
                        $bytes += $keep;
                    }

                    return 0; // abort download once budget reached
                }
                $body .= $chunk;
                $bytes += $len;

                return $len;
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $error = (string) curl_error($ch);
        // CURLE_WRITE_ERROR (23) is expected when we stop at maxBytes.
        $abortedOnBudget = ($errno === 23 && $bytes >= $maxBytes);
        curl_close($ch);

        $elapsedMs = (microtime(true) - $started) * 1000.0;
        $result['latency_ms'] = round($elapsedMs, 1);
        $result['bytes'] = $bytes;
        $result['status'] = $status;

        if ($abortedOnBudget || ($ok && $status > 0 && $status < 400 && $bytes > 0)) {
            $result['ok'] = true;

            return $result;
        }

        $result['error'] = $error !== ''
            ? $error
            : ($status > 0 ? ('HTTP ' . $status) : 'Outbound request failed');

        return $result;
    }

    if (!bandpromo_environment_https_egress_available()) {
        $result['error'] = 'Outbound HTTPS unavailable (enable curl or allow_url_fopen + openssl).';

        return $result;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'follow_location' => 1,
            'user_agent' => 'bandPromo environment probe',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $handle = @fopen($url, 'rb', false, $context);
    if ($handle === false) {
        $result['error'] = 'Could not open outbound URL.';
        $result['latency_ms'] = round((microtime(true) - $started) * 1000.0, 1);

        return $result;
    }

    $bytes = 0;
    $deadline = microtime(true) + $timeoutSeconds;
    while (!feof($handle) && $bytes < $maxBytes && microtime(true) < $deadline) {
        $chunk = fread($handle, min(8192, $maxBytes - $bytes));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $bytes += strlen($chunk);
    }
    fclose($handle);

    $elapsedMs = (microtime(true) - $started) * 1000.0;
    $result['latency_ms'] = round($elapsedMs, 1);
    $result['bytes'] = $bytes;
    $result['ok'] = $bytes > 0;
    if (!$result['ok']) {
        $result['error'] = 'No bytes received.';
    }

    return $result;
}

/**
 * Host egress probe — measures this server's outbound path, not visitor Wi‑Fi.
 * Hard budget: keep Environment GET well under shared-host PHP time limits.
 *
 * @return array<string, mixed>
 */
function bandpromo_environment_probe_network(string $root): array
{
    $notes = [
        'Host egress — not the login visitor speed test (that measures the browser path).',
    ];

    if (!bandpromo_environment_https_egress_available()) {
        return [
            'available' => false,
            'latency' => [],
            'throughput' => null,
            'notes' => array_merge($notes, [
                'Outbound HTTPS is blocked or not configured on this host (curl / allow_url_fopen + openssl).',
            ]),
        ];
    }

    $latencyTargets = [
        [
            'id' => 'cloudflare',
            'label' => 'Cloudflare speed',
            'url' => 'https://speed.cloudflare.com/__down?bytes=64',
        ],
        [
            'id' => 'github_api',
            'label' => 'GitHub API',
            'url' => 'https://api.github.com',
        ],
    ];

    $latency = [];
    foreach ($latencyTargets as $target) {
        $probe = bandpromo_environment_http_fetch($target['url'], 4096, 2.0);
        $entry = [
            'id' => $target['id'],
            'label' => $target['label'],
            'ok' => !empty($probe['ok']),
            'latency_ms' => $probe['latency_ms'],
            'status' => (int) ($probe['status'] ?? 0),
            'error' => (string) ($probe['error'] ?? ''),
        ];
        $latency[] = $entry;
    }

    $throughput = null;
    $throughputProbe = bandpromo_environment_http_fetch(
        'https://speed.cloudflare.com/__down?bytes=2000000',
        2000000,
        3.0
    );
    if (!empty($throughputProbe['ok']) && (int) $throughputProbe['bytes'] > 0 && ($throughputProbe['latency_ms'] ?? 0) > 0) {
        $seconds = ((float) $throughputProbe['latency_ms']) / 1000.0;
        $mbps = $seconds > 0
            ? round((((int) $throughputProbe['bytes']) * 8) / ($seconds * 1000000), 2)
            : null;
        $throughput = [
            'ok' => true,
            'source' => 'cloudflare',
            'bytes' => (int) $throughputProbe['bytes'],
            'bytes_label' => bandpromo_environment_format_bytes($throughputProbe['bytes']),
            'elapsed_ms' => $throughputProbe['latency_ms'],
            'mbps' => $mbps,
            'note' => 'Bounded sample (≤2 MB / ≤3 s) — indicative host egress only.',
        ];
    } else {
        // Soft fallback: same-host loop stresses PHP/disk generating bytes, not WAN.
        $loopUrl = '';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $loopUrl = ($https ? 'https://' : 'http://') . $host . '/biblioteca/speed-test.php?bytes=1048576';
        }
        $loopProbe = $loopUrl !== ''
            ? bandpromo_environment_http_fetch($loopUrl, 1048576, 2.0)
            : ['ok' => false, 'bytes' => 0, 'latency_ms' => null, 'error' => 'No host for loopback.', 'status' => 0];

        if (!empty($loopProbe['ok']) && (int) $loopProbe['bytes'] > 0 && ($loopProbe['latency_ms'] ?? 0) > 0) {
            $seconds = ((float) $loopProbe['latency_ms']) / 1000.0;
            $mbps = $seconds > 0
                ? round((((int) $loopProbe['bytes']) * 8) / ($seconds * 1000000), 2)
                : null;
            $throughput = [
                'ok' => true,
                'source' => 'loopback',
                'bytes' => (int) $loopProbe['bytes'],
                'bytes_label' => bandpromo_environment_format_bytes($loopProbe['bytes']),
                'elapsed_ms' => $loopProbe['latency_ms'],
                'mbps' => $mbps,
                'note' => 'Outbound blocked; loopback sample only (PHP/disk, not WAN).',
            ];
            $notes[] = 'Cloudflare egress download failed'
                . ((string) ($throughputProbe['error'] ?? '') !== '' ? (': ' . $throughputProbe['error']) : '')
                . ' — showing same-host loopback instead.';
        } else {
            $throughput = [
                'ok' => false,
                'source' => 'none',
                'bytes' => 0,
                'bytes_label' => '(unavailable)',
                'elapsed_ms' => $throughputProbe['latency_ms'] ?? null,
                'mbps' => null,
                'error' => (string) ($throughputProbe['error'] ?? 'Throughput probe unavailable'),
                'note' => 'Could not measure host download throughput.',
            ];
            $notes[] = 'Host download throughput unavailable on this install.';
        }
    }

    $anyLatencyOk = false;
    foreach ($latency as $row) {
        if (!empty($row['ok'])) {
            $anyLatencyOk = true;
            break;
        }
    }

    return [
        'available' => $anyLatencyOk || !empty($throughput['ok']),
        'latency' => $latency,
        'throughput' => $throughput,
        'notes' => $notes,
    ];
}

/**
 * Active build / optimize / catalogue-repair locks for multi-operator visibility.
 *
 * @return array<string, mixed>
 */
function bandpromo_environment_probe_locks(string $root): array
{
    require_once __DIR__ . '/build-lock.php';
    require_once __DIR__ . '/catalog-repair-auto.php';

    $buildActive = bandpromo_build_lock_active($root, 'full');
    $optimizeActive = bandpromo_build_lock_active($root, 'optimize');
    $repairActive = bandpromo_catalog_repair_is_locked($root);

    $buildPaths = bandpromo_build_paths($root, 'full');
    $optPaths = bandpromo_build_paths($root, 'optimize');
    $repairPath = bandpromo_catalog_repair_lock_path($root);

    return [
        'build' => [
            'active' => $buildActive,
            'label' => 'Publish / Refresh',
            'path' => $buildPaths['lock'],
            'mtime' => ($buildActive && is_file($buildPaths['lock']))
                ? gmdate('c', (int) @filemtime($buildPaths['lock']))
                : null,
        ],
        'optimize' => [
            'active' => $optimizeActive,
            'label' => 'Optimize',
            'path' => $optPaths['lock'],
            'mtime' => ($optimizeActive && is_file($optPaths['lock']))
                ? gmdate('c', (int) @filemtime($optPaths['lock']))
                : null,
        ],
        'catalog_repair' => [
            'active' => $repairActive,
            'label' => 'Repair / catalogue prep',
            'path' => $repairPath,
            'mtime' => ($repairActive && is_file($repairPath))
                ? gmdate('c', (int) @filemtime($repairPath))
                : null,
        ],
        'any_active' => $buildActive || $optimizeActive || $repairActive,
        'note' => 'Site update refuses while any of these locks are held. Manual Repair shares the catalogue-repair lock with background prep.',
    ];
}

/**
 * @return array<string, mixed>
 */
function bandpromo_environment_collect_report(string $root): array
{
    $phpCli = bandpromo_resolve_php_cli();
    $packageChecks = bandpromo_package_collect_environment_checks($root);
    $diagCachePath = $root . '/log/build-launch-diag.json';
    $diagCache = null;
    if (is_file($diagCachePath)) {
        $decoded = json_decode((string) @file_get_contents($diagCachePath), true);
        if (is_array($decoded)) {
            $diagCache = [
                'resolved_php' => (string) ($decoded['resolved_php'] ?? ''),
                'recommended_method' => (string) ($decoded['recommended_method'] ?? ''),
                'recommended_reason' => (string) ($decoded['recommended_reason'] ?? ''),
                'cached_at' => (string) ($decoded['cached_at'] ?? ''),
            ];
        }
    }

    $disabled = trim((string) ini_get('disable_functions'));
    $openBasedir = trim((string) ini_get('open_basedir'));
    $resources = bandpromo_environment_probe_resources($root);
    $resources['network'] = bandpromo_environment_probe_network($root);

    return [
        'app' => [
            'version' => bandpromo_environment_read_version($root),
            'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
            'install_root' => $root,
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? (function_exists('gethostname') ? (string) gethostname() : '')),
            'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'binary' => defined('PHP_BINARY') ? (string) PHP_BINARY : '',
            'bindir' => defined('PHP_BINDIR') ? (string) PHP_BINDIR : '',
            'cli' => $phpCli,
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'open_basedir' => $openBasedir !== '' ? $openBasedir : '(none)',
            'disable_functions' => $disabled !== '' ? $disabled : '(none)',
            'extensions' => [
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'zip' => class_exists('ZipArchive'),
                'curl' => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
            ],
        ],
        'functions' => [
            'proc_open' => bandpromo_build_function_usable('proc_open'),
            'shell_exec' => bandpromo_build_function_usable('shell_exec'),
            'exec' => bandpromo_build_function_usable('exec'),
            'popen' => bandpromo_build_function_usable('popen'),
            'putenv' => bandpromo_build_function_usable('putenv'),
        ],
        'resources' => $resources,
        'locks' => bandpromo_environment_probe_locks($root),
        'build_tools' => [
            'python' => bandpromo_environment_python_hint(),
            'ffmpeg' => bandpromo_environment_ffmpeg_path($root),
            'launch_diag_cache' => $diagCache,
        ],
        'package_checks' => $packageChecks,
        'generated_at' => gmdate('c'),
    ];
}
