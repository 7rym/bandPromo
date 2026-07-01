<?php
declare(strict_types=1);

/**
 * RFC 5322-style contact helpers for site config and release EPK fields.
 *
 * Storage policy (v0.8.4+):
 * - Accept addr-spec or named forms such as 7rym <7rym@7rym.net>.
 * - Validate on save; reject malformed values before they reach config or release JSON.
 * - Canonicalize on save: strip control characters, lowercase domain, trim display names.
 * - Empty contact is allowed when no valid mailbox can be derived (for example localhost installs).
 *
 * Outbound mail is not implemented yet; this layer exists so future mail features inherit
 * consistent, deliverability-friendly contact data.
 */

function bandpromo_site_contact_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }

    $normalized = strtolower(trim((string) $value));
    return !in_array($normalized, ['0', 'false', 'no', 'off'], true);
}

function bandpromo_site_contact_sanitize_input(string $value): string
{
    $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if (function_exists('mb_convert_encoding')) {
        $value = (string) preg_replace('/\p{Cf}/u', '', $value);
    }

    return trim($value);
}

function bandpromo_site_contact_domain_from_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    $host = strtolower($host);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return '';
    }

    return $host;
}

function bandpromo_site_contact_local_from_author(string $author): string
{
    $local = strtolower(trim($author));
    $local = (string) preg_replace('/[^a-z0-9]+/', '', $local);

    return substr($local, 0, 64);
}

function bandpromo_site_contact_format(string $name, string $addr): string
{
    $addr = trim($addr);
    if ($addr === '') {
        return '';
    }

    $name = trim($name);
    if ($name === '') {
        return $addr;
    }

    if (preg_match('/[<>"]/', $name)) {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
        return '"' . $escaped . '" <' . $addr . '>';
    }

    return $name . ' <' . $addr . '>';
}

function bandpromo_site_contact_normalize_addr_spec(string $addr): ?string
{
    $addr = strtolower(trim($addr));
    if ($addr === '' || substr_count($addr, '@') !== 1) {
        return null;
    }

    [$local, $domain] = explode('@', $addr, 2);
    $local = trim($local);
    $domain = trim($domain);

    if ($local === '' || $domain === '') {
        return null;
    }

    if (str_contains($local, '..') || str_contains($domain, '..')) {
        return null;
    }

    if (str_starts_with($local, '.') || str_ends_with($local, '.')
        || str_starts_with($domain, '.') || str_ends_with($domain, '.')) {
        return null;
    }

    if (!str_contains($domain, '.')) {
        return null;
    }

    $candidate = $local . '@' . $domain;
    if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $candidate;
}

/**
 * @return array{name: string, addr: string}|null
 */
function bandpromo_site_contact_parse(string $value): ?array
{
    $value = bandpromo_site_contact_sanitize_input($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^"((?:\\\\.|[^"\\\\])*)"\s*<([^>]+)>$/', $value, $matches)) {
        $name = stripcslashes($matches[1]);
        $addr = bandpromo_site_contact_normalize_addr_spec($matches[2]);
        if ($addr !== null) {
            return ['name' => $name, 'addr' => $addr];
        }

        return null;
    }

    if (preg_match('/^([^<]+)<([^>]+)>$/', $value, $matches)) {
        $name = trim($matches[1]);
        $addr = bandpromo_site_contact_normalize_addr_spec($matches[2]);
        if ($name !== '' && $addr !== null) {
            return ['name' => $name, 'addr' => $addr];
        }

        return null;
    }

    $addr = bandpromo_site_contact_normalize_addr_spec($value);
    if ($addr !== null) {
        return ['name' => '', 'addr' => $addr];
    }

    return null;
}

function bandpromo_site_contact_normalize(string $value): ?string
{
    $parsed = bandpromo_site_contact_parse($value);
    if ($parsed === null) {
        return null;
    }

    $name = trim((string) preg_replace('/\s+/u', ' ', $parsed['name']));

    return bandpromo_site_contact_format($name, $parsed['addr']);
}

function bandpromo_site_contact_is_valid(string $value): bool
{
    $value = bandpromo_site_contact_sanitize_input($value);
    if ($value === '') {
        return true;
    }

    return bandpromo_site_contact_normalize($value) !== null;
}

function bandpromo_site_contact_invalid_message(): string
{
    return 'Contact must be a valid RFC 5322 address (for example 7rym <7rym@7rym.net>).';
}

function bandpromo_site_contact_mailbox(string $value): string
{
    $parsed = bandpromo_site_contact_parse($value);
    return $parsed['addr'] ?? '';
}

function bandpromo_site_contact_store_value(string $value, int $maxLength = 240): string
{
    $value = bandpromo_site_contact_sanitize_input($value);
    if ($value === '') {
        return '';
    }

    $normalized = bandpromo_site_contact_normalize($value);
    if ($normalized === null) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($normalized, 0, $maxLength);
    }

    return substr($normalized, 0, $maxLength);
}

function bandpromo_site_contact_derive(string $author, string $url): string
{
    $author = trim($author);
    $domain = bandpromo_site_contact_domain_from_url($url);
    if ($domain === '') {
        return '';
    }

    $local = bandpromo_site_contact_local_from_author($author);
    if ($local === '') {
        $local = 'contact';
    }

    $derived = bandpromo_site_contact_format($author, $local . '@' . $domain);
    $normalized = bandpromo_site_contact_normalize($derived);

    return $normalized ?? '';
}

/**
 * Resolve site.email from author, url, and email_auto preference.
 *
 * @param array<string, mixed> $site
 */
function bandpromo_site_prepare_contact_fields(array &$site): ?string
{
    $author = trim((string) ($site['author'] ?? ''));
    $url = trim((string) ($site['url'] ?? ''));
    $auto = !array_key_exists('email_auto', $site) || bandpromo_site_contact_bool($site['email_auto']);

    if ($auto) {
        $site['email'] = bandpromo_site_contact_derive($author, $url);
        $site['email_auto'] = true;
    } else {
        $email = bandpromo_site_contact_sanitize_input((string) ($site['email'] ?? ''));
        if ($email === '') {
            $site['email'] = bandpromo_site_contact_derive($author, $url);
            $site['email_auto'] = true;
        } else {
            if (!bandpromo_site_contact_is_valid($email)) {
                return bandpromo_site_contact_invalid_message();
            }
            $site['email'] = bandpromo_site_contact_store_value($email);
            $site['email_auto'] = false;
        }
    }

    $resolved = trim((string) ($site['email'] ?? ''));
    if ($resolved !== '' && !bandpromo_site_contact_is_valid($resolved)) {
        return bandpromo_site_contact_invalid_message();
    }

    $site['language'] = 'en';

    return null;
}
