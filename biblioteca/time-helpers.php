<?php
declare(strict_types=1);

/**
 * UTC storage and operator-facing time display helpers.
 */

function bandpromo_utc_now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function bandpromo_utc_now_unix(): int
{
    return time();
}

function bandpromo_entry_unix_timestamp(array $entry): int
{
    if (isset($entry['timestamp_unix']) && is_numeric($entry['timestamp_unix'])) {
        return (int) $entry['timestamp_unix'];
    }

    $raw = trim((string) ($entry['timestamp'] ?? ''));
    if ($raw === '') {
        return 0;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $raw)) {
        $parsed = strtotime($raw);
        return $parsed !== false ? $parsed : 0;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
        // Legacy rows: admin audit used gmdate (UTC wall clock); listener logs may be server-local.
        $parsed = strtotime($raw . ' UTC');
        return $parsed !== false ? $parsed : 0;
    }

    $parsed = strtotime($raw);
    return $parsed !== false ? $parsed : 0;
}

function bandpromo_operator_prefs(?array $config = null): array
{
    if ($config === null) {
        require_once __DIR__ . '/config-loader.php';
        $config = bandpromo_load_runtime_config_raw();
    }

    $operator = is_array($config['operator'] ?? null) ? $config['operator'] : [];
    $display = strtolower(trim((string) ($operator['time_display'] ?? 'utc')));
    if (!in_array($display, ['utc', 'local'], true)) {
        $display = 'utc';
    }

    $timezone = trim((string) ($operator['timezone'] ?? 'UTC'));
    if ($timezone === '') {
        $timezone = 'UTC';
    }

    try {
        new DateTimeZone($timezone);
    } catch (Exception $e) {
        $timezone = 'UTC';
    }

    return [
        'time_display' => $display,
        'timezone' => $timezone,
    ];
}

function bandpromo_admin_time_display_mode(): string
{
    return bandpromo_operator_prefs()['time_display'];
}

function bandpromo_admin_timezone(): string
{
    $prefs = bandpromo_operator_prefs();
    return $prefs['time_display'] === 'local' ? $prefs['timezone'] : 'UTC';
}

function bandpromo_admin_format_timestamp($unixOrEntry, ?string $mode = null): string
{
    if (is_array($unixOrEntry)) {
        $unix = bandpromo_entry_unix_timestamp($unixOrEntry);
    } else {
        $unix = (int) $unixOrEntry;
    }

    if ($unix <= 0) {
        return '';
    }

    if ($mode === null) {
        $mode = bandpromo_admin_time_display_mode();
    }

    $timezone = $mode === 'local' ? bandpromo_admin_timezone() : 'UTC';

    try {
        $dt = (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        $dt = (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone('UTC'));
        $timezone = 'UTC';
        $mode = 'utc';
    }

    $formatted = $dt->format('Y-m-d H:i:s');
    if ($mode === 'utc') {
        return $formatted . ' UTC';
    }

    if ($timezone === 'UTC') {
        return $formatted . ' UTC';
    }

    return $formatted;
}

function bandpromo_admin_time_axis_label(): string
{
    if (bandpromo_admin_time_display_mode() === 'utc') {
        return 'UTC';
    }

    $timezone = bandpromo_admin_timezone();
    return $timezone === 'UTC' ? 'local time' : $timezone;
}

function bandpromo_admin_time_policy_note(): string
{
    return 'bandPromo stores activity and audit timestamps in UTC. Release dates use UTC calendar days until timed drops ship in v2.';
}

function bandpromo_admin_hour_from_unix(int $unix, ?string $timezone = null): int
{
    $timezone = $timezone ?? bandpromo_admin_timezone();

    try {
        $dt = (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        $dt = (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone('UTC'));
    }

    return (int) $dt->format('G');
}

function bandpromo_admin_day_from_unix(int $unix, ?string $timezone = null): string
{
    $timezone = $timezone ?? bandpromo_admin_timezone();

    try {
        $dt = (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $e) {
        return gmdate('Y-m-d', $unix);
    }

    return $dt->format('Y-m-d');
}

function bandpromo_utc_date_range_bounds(string $dateStart, string $dateEnd): array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStart, new DateTimeZone('UTC'));
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $dateEnd, new DateTimeZone('UTC'));

    if (!$start) {
        $start = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    }
    if (!$end) {
        $end = $start;
    }
    if ($end < $start) {
        [$start, $end] = [$end, $start];
    }

    return [
        'start_unix' => $start->getTimestamp(),
        'end_unix' => $end->modify('+1 day')->getTimestamp() - 1,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
    ];
}

function bandpromo_analytics_bucket_timezone(): string
{
    return bandpromo_admin_time_display_mode() === 'local'
        ? bandpromo_admin_timezone()
        : 'UTC';
}
