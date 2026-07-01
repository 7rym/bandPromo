<?php
declare(strict_types=1);

function bandpromo_build_log_iso_timestamp(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function bandpromo_build_log_started_lines(string $mode): string
{
    $timestamp = bandpromo_build_log_iso_timestamp();
    $human = gmdate('Y-m-d H:i:s');
    $label = $mode === 'optimize' ? 'Image refresh started' : 'Publish build started';

    return 'LOG_STARTED:' . $timestamp . "\n"
        . '[' . $human . ' UTC] ' . $label . "\n";
}
