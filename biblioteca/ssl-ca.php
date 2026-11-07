<?php
declare(strict_types=1);

/**
 * Resolve a CA bundle path for outbound HTTPS (cURL / OpenSSL streams).
 *
 * On Windows, antivirus HTTPS scanning (AVG, etc.) installs a local root that
 * Windows trusts but PHP’s Mozilla cacert.pem does not. Operators can place a
 * combined bundle at %USERPROFILE%\.bandpromo\php-ca-bundle.pem (Mozilla +
 * extra roots) without disabling certificate verification.
 */
function bandpromo_ssl_ca_bundle_path(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $candidates = [];

    $env = getenv('BANDPROMO_SSL_CAFILE');
    if (is_string($env) && trim($env) !== '') {
        $candidates[] = trim($env);
    }

    $home = getenv('USERPROFILE');
    if (!is_string($home) || $home === '') {
        $home = getenv('HOME');
    }
    if (is_string($home) && $home !== '') {
        $candidates[] = rtrim($home, "\\/") . DIRECTORY_SEPARATOR . '.bandpromo' . DIRECTORY_SEPARATOR . 'php-ca-bundle.pem';
    }

    $curl = trim((string) ini_get('curl.cainfo'));
    if ($curl !== '') {
        $candidates[] = $curl;
    }
    $openssl = trim((string) ini_get('openssl.cafile'));
    if ($openssl !== '') {
        $candidates[] = $openssl;
    }

    foreach ($candidates as $path) {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if ($path !== '' && is_file($path) && is_readable($path)) {
            $resolved = $path;

            return $resolved;
        }
    }

    $resolved = '';

    return $resolved;
}

/**
 * @param array<int, mixed> $opts
 * @return array<int, mixed>
 */
function bandpromo_ssl_curl_setopt_ca(array $opts): array
{
    $ca = bandpromo_ssl_ca_bundle_path();
    if ($ca !== '') {
        $opts[CURLOPT_CAINFO] = $ca;
    }

    return $opts;
}

/**
 * @param array<string, mixed> $ssl
 * @return array<string, mixed>
 */
function bandpromo_ssl_stream_context_ca(array $ssl = []): array
{
    $ca = bandpromo_ssl_ca_bundle_path();
    if ($ca !== '') {
        $ssl['cafile'] = $ca;
    }

    return $ssl;
}
