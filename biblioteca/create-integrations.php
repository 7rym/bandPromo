<?php
declare(strict_types=1);

/**
 * Per-admin Create tab integrations (OpenAI + fal.ai).
 *
 * Keys live under data/admin-integrations/{username}.json — never in web-config,
 * PCF/PBF, logs, or audit payloads. Optional install fallbacks: OPENAI_API_KEY
 * and FAL_KEY from process env or gitignored .env.
 */

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/create-integrations-prefs.php';
require_once __DIR__ . '/ssl-ca.php';

/**
 * @return array{
 *   openai_api_key:string,
 *   fal_api_key:string,
 *   llm_price_tier:string,
 *   image_price_tier:string,
 *   video_price_tier:string,
 *   price_tier:string,
 *   llm_model:string,
 *   image_model:string,
 *   video_model:string,
 *   openai_model:string,
 *   fal_model:string,
 *   setup_complete:bool,
 *   prompt_tested:bool,
 *   image_tested:bool,
 *   setup_completed_at_utc:string,
 *   updated_at_utc:string
 * }
 */
function bandpromo_create_integrations_defaults(): array
{
    $tier = 'cheap';

    return [
        'openai_api_key' => '',
        'fal_api_key' => '',
        'llm_price_tier' => $tier,
        'image_price_tier' => $tier,
        'video_price_tier' => $tier,
        // Legacy single-tier alias (mirrors llm_price_tier).
        'price_tier' => $tier,
        'llm_model' => bandpromo_create_integrations_default_llm_model($tier),
        'image_model' => bandpromo_create_integrations_default_image_model($tier),
        'video_model' => bandpromo_create_integrations_default_video_model($tier),
        // Legacy aliases kept in sync with llm/image prefs.
        'openai_model' => 'gpt-6-luna', // legacy alias; Preferences LLM uses fal OpenRouter ids
        'fal_model' => bandpromo_create_integrations_default_image_model($tier),
        'setup_complete' => false,
        // Provider proofs (each key: text + image). Legacy aliases kept in sync.
        'openai_tested' => false,
        'fal_tested' => false,
        'prompt_tested' => false,
        'image_tested' => false,
        'setup_completed_at_utc' => '',
        'updated_at_utc' => '',
        // Last successful Start proofs + Preferences tests (media under user results dir).
        'last_results' => [],
    ];
}

/** @deprecated Prefer llm_models() */
function bandpromo_create_integrations_openai_models(): array
{
    $out = [];
    foreach (bandpromo_create_integrations_llm_models() as $row) {
        if (($row['route'] ?? '') !== 'openai') {
            continue;
        }
        $out[] = [
            'id' => (string) $row['provider_model'],
            'label' => (string) $row['label'],
            'price_hint' => (string) $row['price_hint'],
            'input_per_mtok' => 0.0,
            'output_per_mtok' => 0.0,
        ];
    }

    return $out;
}

/** @deprecated Prefer image_models() */
function bandpromo_create_integrations_fal_models(): array
{
    return bandpromo_create_integrations_image_models();
}

function bandpromo_create_integrations_default_openai_model(): string
{
    return 'gpt-6-luna';
}

function bandpromo_create_integrations_default_fal_model(): string
{
    return bandpromo_create_integrations_default_image_model('cheap');
}

function bandpromo_create_integrations_normalize_openai_model(string $model): string
{
    $meta = bandpromo_create_integrations_llm_model_meta(
        strpos($model, 'openai:') === 0 ? $model : ('openai:' . $model)
    );
    if ($meta && ($meta['route'] ?? '') === 'openai') {
        return (string) $meta['provider_model'];
    }

    return bandpromo_create_integrations_default_openai_model();
}

function bandpromo_create_integrations_normalize_fal_model(string $model): string
{
    return bandpromo_create_integrations_normalize_image_model($model);
}

/**
 * @return array<string, mixed>|null
 */
function bandpromo_create_integrations_openai_model_meta(string $model): ?array
{
    return bandpromo_create_integrations_llm_model_meta(
        strpos($model, 'openai:') === 0 ? $model : ('openai:' . $model)
    );
}

/**
 * @return array<string, mixed>|null
 */
function bandpromo_create_integrations_fal_model_meta(string $model): ?array
{
    return bandpromo_create_integrations_image_model_meta($model);
}

/**
 * @param array{prompt_tokens?:int|float,completion_tokens?:int|float,total_tokens?:int|float}|null $usage
 * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int,estimate_usd:float,estimate_label:string}
 */
function bandpromo_create_integrations_openai_cost_from_usage(string $model, $usage): array
{
    $meta = bandpromo_create_integrations_openai_model_meta($model);
    $promptTokens = is_array($usage) ? (int) ($usage['prompt_tokens'] ?? 0) : 0;
    $completionTokens = is_array($usage) ? (int) ($usage['completion_tokens'] ?? 0) : 0;
    $totalTokens = is_array($usage) ? (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens)) : ($promptTokens + $completionTokens);
    $estimate = 0.0;
    $inRate = is_array($meta) ? (float) ($meta['input_per_mtok'] ?? 0) : 0.0;
    $outRate = is_array($meta) ? (float) ($meta['output_per_mtok'] ?? 0) : 0.0;
    if ($inRate > 0 || $outRate > 0) {
        $estimate = ($promptTokens / 1000000.0) * $inRate
            + ($completionTokens / 1000000.0) * $outRate;
    }
    $hint = is_array($meta) ? trim((string) ($meta['price_hint'] ?? '')) : '';
    if ($totalTokens > 0 && $estimate > 0) {
        $label = sprintf('About $%s (%s tokens)', rtrim(rtrim(number_format($estimate, 6, '.', ''), '0'), '.'), number_format($totalTokens));
    } elseif ($totalTokens > 0) {
        $label = sprintf('%s tokens%s', number_format($totalTokens), $hint !== '' ? ' — ' . $hint : '');
    } else {
        $label = $hint !== '' ? $hint : 'Cost unknown (no token usage returned)';
    }

    return [
        'prompt_tokens' => $promptTokens,
        'completion_tokens' => $completionTokens,
        'total_tokens' => $totalTokens,
        'estimate_usd' => round($estimate, 8),
        'estimate_label' => $label,
    ];
}

/**
 * @return array{estimate_usd:float,estimate_label:string,supports_logo:bool}
 */
function bandpromo_create_integrations_fal_cost_estimate(string $model): array
{
    $meta = bandpromo_create_integrations_fal_model_meta($model);
    $usd = $meta ? (float) $meta['estimate_usd'] : 0.0;
    $hint = $meta ? (string) $meta['price_hint'] : '';
    $label = $usd > 0
        ? sprintf('About $%s%s', rtrim(rtrim(number_format($usd, 4, '.', ''), '0'), '.'), $hint !== '' ? ' — ' . $hint : '')
        : 'Cost unknown';

    return [
        'estimate_usd' => $usd,
        'estimate_label' => $label,
        'supports_logo' => $meta ? !empty($meta['supports_logo']) : false,
    ];
}

function bandpromo_create_integrations_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'admin-integrations';
}

function bandpromo_create_integrations_sanitize_username(string $username): string
{
    $username = trim($username);
    if ($username === '') {
        return '';
    }

    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $username);
    if (!is_string($safe)) {
        return '';
    }

    $safe = trim($safe, '._-');
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return (string) mb_substr($safe, 0, 80, 'UTF-8');
    }

    return substr($safe, 0, 80);
}

function bandpromo_create_integrations_path(string $root, string $username): string
{
    $safe = bandpromo_create_integrations_sanitize_username($username);
    if ($safe === '') {
        return '';
    }

    return bandpromo_create_integrations_dir($root) . DIRECTORY_SEPARATOR . $safe . '.json';
}

/**
 * Local copies of last Create test media (remote fal/OpenAI URLs expire).
 */
function bandpromo_create_integrations_results_dir(string $root, string $username): string
{
    $safe = bandpromo_create_integrations_sanitize_username($username);
    if ($safe === '') {
        return '';
    }

    return bandpromo_create_integrations_dir($root)
        . DIRECTORY_SEPARATOR . $safe
        . DIRECTORY_SEPARATOR . 'results';
}

/**
 * @return array<string, array{ext:string, mime:string, type:string}>
 */
function bandpromo_create_integrations_result_media_kinds(): array
{
    return [
        'openai_proof_image' => ['ext' => 'png', 'mime' => 'image/png', 'type' => 'image'],
        'fal_proof_image' => ['ext' => 'png', 'mime' => 'image/png', 'type' => 'image'],
        'prefs_image' => ['ext' => 'png', 'mime' => 'image/png', 'type' => 'image'],
        'prefs_video' => ['ext' => 'mp4', 'mime' => 'video/mp4', 'type' => 'video'],
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, array<string, mixed>>
 */
function bandpromo_create_integrations_normalize_last_results(array $raw): array
{
    $slots = ['openai_proof', 'fal_proof', 'prefs_llm', 'prefs_image', 'prefs_video'];
    $out = [];
    foreach ($slots as $slot) {
        if (!isset($raw[$slot]) || !is_array($raw[$slot])) {
            continue;
        }
        $row = $raw[$slot];
        $out[$slot] = [
            'ok' => !empty($row['ok']),
            'at_utc' => trim((string) ($row['at_utc'] ?? '')),
            'brand_title' => trim((string) ($row['brand_title'] ?? '')),
            'brand_id' => trim((string) ($row['brand_id'] ?? '')),
            'prompt' => trim((string) ($row['prompt'] ?? '')),
            'text_model' => trim((string) ($row['text_model'] ?? '')),
            'image_model' => trim((string) ($row['image_model'] ?? '')),
            'video_model' => trim((string) ($row['video_model'] ?? '')),
            'logo_attached' => !empty($row['logo_attached']),
            'cost_label' => trim((string) ($row['cost_label'] ?? '')),
            'media_kind' => trim((string) ($row['media_kind'] ?? '')),
            'media_ext' => trim((string) ($row['media_ext'] ?? '')),
        ];
    }

    return $out;
}

function bandpromo_create_integrations_result_media_path(string $root, string $username, string $kind): string
{
    $kinds = bandpromo_create_integrations_result_media_kinds();
    if (!isset($kinds[$kind])) {
        return '';
    }
    $dir = bandpromo_create_integrations_results_dir($root, $username);
    if ($dir === '' || !is_dir($dir)) {
        return '';
    }
    $matches = glob($dir . DIRECTORY_SEPARATOR . $kind . '.*') ?: [];
    foreach ($matches as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function bandpromo_create_integrations_delete_result_media(string $root, string $username, string $kind): void
{
    $kinds = bandpromo_create_integrations_result_media_kinds();
    if (!isset($kinds[$kind])) {
        return;
    }
    $dir = bandpromo_create_integrations_results_dir($root, $username);
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . $kind . '.*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * @return array{ok:bool,bytes:string,mime:string,ext:string,error:string}
 */
function bandpromo_create_integrations_fetch_result_bytes(string $source): array
{
    $source = trim($source);
    if ($source === '') {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'ext' => '', 'error' => 'Empty media source.'];
    }

    if (preg_match('#^data:([a-z0-9.+/-]+);base64,(.+)$#is', $source, $m) === 1) {
        $mime = strtolower(trim((string) $m[1]));
        $raw = base64_decode((string) $m[2], true);
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'bytes' => '', 'mime' => '', 'ext' => '', 'error' => 'Could not decode data URL.'];
        }
        $ext = 'bin';
        if (strpos($mime, 'png') !== false) {
            $ext = 'png';
        } elseif (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($mime, 'webp') !== false) {
            $ext = 'webp';
        } elseif (strpos($mime, 'gif') !== false) {
            $ext = 'gif';
        } elseif (strpos($mime, 'mp4') !== false) {
            $ext = 'mp4';
        } elseif (strpos($mime, 'webm') !== false) {
            $ext = 'webm';
        }

        return ['ok' => true, 'bytes' => $raw, 'mime' => $mime, 'ext' => $ext, 'error' => ''];
    }

    if (preg_match('#^https?://#i', $source) !== 1) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'ext' => '', 'error' => 'Unsupported media source.'];
    }

    $ch = curl_init($source);
    if ($ch === false) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'ext' => '', 'error' => 'Could not start download.'];
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: */*'],
        CURLOPT_USERAGENT => 'bandPromo-create-results/1.0',
    ];
    if (function_exists('bandpromo_ssl_curl_setopt_ca')) {
        $opts = bandpromo_ssl_curl_setopt_ca($opts);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $mime = strtolower(trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'bytes' => '',
            'mime' => '',
            'ext' => '',
            'error' => $err !== '' ? $err : ('Download failed (HTTP ' . $status . ').'),
        ];
    }
    if (strlen($body) > 25 * 1024 * 1024) {
        return ['ok' => false, 'bytes' => '', 'mime' => '', 'ext' => '', 'error' => 'Media too large to keep locally.'];
    }
    if (strpos($mime, ';') !== false) {
        $mime = trim(explode(';', $mime, 2)[0]);
    }
    $ext = 'bin';
    if (strpos($mime, 'png') !== false || preg_match('#\\.png(\\?|$)#i', $source) === 1) {
        $ext = 'png';
    } elseif (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false || preg_match('#\\.jpe?g(\\?|$)#i', $source) === 1) {
        $ext = 'jpg';
    } elseif (strpos($mime, 'webp') !== false) {
        $ext = 'webp';
    } elseif (strpos($mime, 'gif') !== false) {
        $ext = 'gif';
    } elseif (strpos($mime, 'mp4') !== false || preg_match('#\\.mp4(\\?|$)#i', $source) === 1) {
        $ext = 'mp4';
    } elseif (strpos($mime, 'webm') !== false || preg_match('#\\.webm(\\?|$)#i', $source) === 1) {
        $ext = 'webm';
    } elseif (strpos($source, 'image') !== false) {
        $ext = 'png';
        $mime = $mime !== '' ? $mime : 'image/png';
    }

    return ['ok' => true, 'bytes' => $body, 'mime' => $mime, 'ext' => $ext, 'error' => ''];
}

/**
 * @return array{ok:bool,media_kind:string,media_ext:string,error:string}
 */
function bandpromo_create_integrations_store_result_media(
    string $root,
    string $username,
    string $kind,
    string $sourceUrl
): array {
    $kinds = bandpromo_create_integrations_result_media_kinds();
    if (!isset($kinds[$kind])) {
        return ['ok' => false, 'media_kind' => $kind, 'media_ext' => '', 'error' => 'Unknown result media kind.'];
    }
    $dir = bandpromo_create_integrations_results_dir($root, $username);
    if ($dir === '') {
        return ['ok' => false, 'media_kind' => $kind, 'media_ext' => '', 'error' => 'Missing results directory.'];
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'media_kind' => $kind, 'media_ext' => '', 'error' => 'Could not create results directory.'];
    }

    $fetched = bandpromo_create_integrations_fetch_result_bytes($sourceUrl);
    if (!$fetched['ok']) {
        return [
            'ok' => false,
            'media_kind' => $kind,
            'media_ext' => '',
            'error' => (string) $fetched['error'],
        ];
    }

    $ext = (string) $fetched['ext'];
    if ($ext === '' || $ext === 'bin') {
        $ext = (string) $kinds[$kind]['ext'];
    }
    // Prefer kind default for video/image family mismatches.
    if (($kinds[$kind]['type'] ?? '') === 'video' && !in_array($ext, ['mp4', 'webm'], true)) {
        $ext = 'mp4';
    }
    if (($kinds[$kind]['type'] ?? '') === 'image' && !in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
        $ext = 'png';
    }

    bandpromo_create_integrations_delete_result_media($root, $username, $kind);
    $path = $dir . DIRECTORY_SEPARATOR . $kind . '.' . $ext;
    if (@file_put_contents($path, $fetched['bytes']) === false) {
        return ['ok' => false, 'media_kind' => $kind, 'media_ext' => '', 'error' => 'Could not write result media.'];
    }

    return ['ok' => true, 'media_kind' => $kind, 'media_ext' => $ext, 'error' => ''];
}

function bandpromo_create_integrations_result_media_url(string $kind, string $atUtc = ''): string
{
    $kinds = bandpromo_create_integrations_result_media_kinds();
    if (!isset($kinds[$kind])) {
        return '';
    }
    $t = preg_replace('/[^0-9A-Za-z._-]/', '', $atUtc) ?: (string) time();

    return '/biblioteca/create-integrations-result-media.php?kind=' . rawurlencode($kind) . '&t=' . rawurlencode((string) $t);
}

/**
 * @param mixed ...$costs
 */
function bandpromo_create_integrations_join_cost_labels(...$costs): string
{
    $bits = [];
    foreach ($costs as $cost) {
        if (!is_array($cost)) {
            continue;
        }
        $label = trim((string) ($cost['estimate_label'] ?? ''));
        if ($label !== '') {
            $bits[] = $label;
        }
    }

    return implode(' · ', $bits);
}

/**
 * @param array<string, mixed> $payload
 */
function bandpromo_create_integrations_record_last_result(
    string $root,
    string $username,
    string $slot,
    array $payload
): bool {
    $path = bandpromo_create_integrations_path($root, $username);
    if ($path === '') {
        return false;
    }
    $current = bandpromo_create_integrations_load($root, $username);
    if (!isset($current['last_results']) || !is_array($current['last_results'])) {
        $current['last_results'] = [];
    }
    $current['last_results'][$slot] = [
        'ok' => !empty($payload['ok']),
        'at_utc' => trim((string) ($payload['at_utc'] ?? gmdate('Y-m-d\TH:i:s\Z'))),
        'brand_title' => trim((string) ($payload['brand_title'] ?? '')),
        'brand_id' => trim((string) ($payload['brand_id'] ?? '')),
        'prompt' => trim((string) ($payload['prompt'] ?? '')),
        'text_model' => trim((string) ($payload['text_model'] ?? '')),
        'image_model' => trim((string) ($payload['image_model'] ?? '')),
        'video_model' => trim((string) ($payload['video_model'] ?? '')),
        'logo_attached' => !empty($payload['logo_attached']),
        'cost_label' => trim((string) ($payload['cost_label'] ?? '')),
        'media_kind' => trim((string) ($payload['media_kind'] ?? '')),
        'media_ext' => trim((string) ($payload['media_ext'] ?? '')),
    ];
    $current['last_results'] = bandpromo_create_integrations_normalize_last_results($current['last_results']);
    $current['updated_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');

    return bandpromo_json_write_file($path, $current);
}

/**
 * @return array<string, array<string, mixed>>
 */
function bandpromo_create_integrations_last_results_payload(string $root, string $username): array
{
    $stored = bandpromo_create_integrations_load($root, $username);
    $rows = is_array($stored['last_results'] ?? null) ? $stored['last_results'] : [];
    $out = [];
    foreach ($rows as $slot => $row) {
        if (!is_array($row)) {
            continue;
        }
        $kind = trim((string) ($row['media_kind'] ?? ''));
        $mediaUrl = '';
        if ($kind !== '' && bandpromo_create_integrations_result_media_path($root, $username, $kind) !== '') {
            $mediaUrl = bandpromo_create_integrations_result_media_url($kind, (string) ($row['at_utc'] ?? ''));
        }
        $out[$slot] = [
            'ok' => !empty($row['ok']),
            'at_utc' => (string) ($row['at_utc'] ?? ''),
            'brand_title' => (string) ($row['brand_title'] ?? ''),
            'brand_id' => (string) ($row['brand_id'] ?? ''),
            'prompt' => (string) ($row['prompt'] ?? ''),
            'text_model' => (string) ($row['text_model'] ?? ''),
            'image_model' => (string) ($row['image_model'] ?? ''),
            'video_model' => (string) ($row['video_model'] ?? ''),
            'logo_attached' => !empty($row['logo_attached']),
            'cost_label' => (string) ($row['cost_label'] ?? ''),
            'media_kind' => $kind,
            'media_ext' => (string) ($row['media_ext'] ?? ''),
            'media_url' => $mediaUrl,
        ];
    }

    return $out;
}

function bandpromo_create_integrations_normalize(array $decoded): array
{
    $out = bandpromo_create_integrations_defaults();
    $out['openai_api_key'] = trim((string) ($decoded['openai_api_key'] ?? ''));
    $out['fal_api_key'] = trim((string) ($decoded['fal_api_key'] ?? ''));

    $legacyTier = bandpromo_create_integrations_normalize_price_tier(
        (string) ($decoded['price_tier'] ?? 'cheap')
    );
    $out['llm_price_tier'] = bandpromo_create_integrations_normalize_price_tier(
        (string) ($decoded['llm_price_tier'] ?? $legacyTier)
    );
    $out['image_price_tier'] = bandpromo_create_integrations_normalize_price_tier(
        (string) ($decoded['image_price_tier'] ?? $legacyTier)
    );
    $out['video_price_tier'] = bandpromo_create_integrations_normalize_price_tier(
        (string) ($decoded['video_price_tier'] ?? $legacyTier)
    );
    $out['price_tier'] = $out['llm_price_tier'];

    $legacyOpenAi = trim((string) ($decoded['openai_model'] ?? ''));
    $legacyFal = trim((string) ($decoded['fal_model'] ?? ''));
    $llmRaw = trim((string) ($decoded['llm_model'] ?? ''));
    if ($llmRaw === '' && $legacyOpenAi !== '') {
        $llmRaw = 'openai:' . $legacyOpenAi;
    }
    $imageRaw = trim((string) ($decoded['image_model'] ?? $legacyFal));
    $videoRaw = trim((string) ($decoded['video_model'] ?? ''));

    $out['llm_model'] = bandpromo_create_integrations_normalize_llm_model(
        $llmRaw,
        $out['llm_price_tier']
    );
    $out['image_model'] = bandpromo_create_integrations_normalize_image_model(
        $imageRaw,
        $out['image_price_tier']
    );
    $out['video_model'] = bandpromo_create_integrations_normalize_video_model(
        $videoRaw,
        $out['video_price_tier']
    );

    $llmMeta = bandpromo_create_integrations_llm_model_meta($out['llm_model']);
    $out['openai_model'] = ($llmMeta && ($llmMeta['route'] ?? '') === 'openai')
        ? (string) $llmMeta['provider_model']
        : bandpromo_create_integrations_default_openai_model();
    $out['fal_model'] = $out['image_model'];

    $out['setup_complete'] = !empty($decoded['setup_complete']);
    // Prefer explicit provider flags; fall back to legacy prompt/image tested.
    $out['openai_tested'] = !empty($decoded['openai_tested']) || !empty($decoded['prompt_tested']);
    $out['fal_tested'] = !empty($decoded['fal_tested']) || !empty($decoded['image_tested']);
    $out['prompt_tested'] = $out['openai_tested'];
    $out['image_tested'] = $out['fal_tested'];
    $out['setup_completed_at_utc'] = trim((string) ($decoded['setup_completed_at_utc'] ?? ''));
    $out['updated_at_utc'] = trim((string) ($decoded['updated_at_utc'] ?? ''));
    $out['last_results'] = bandpromo_create_integrations_normalize_last_results(
        is_array($decoded['last_results'] ?? null) ? $decoded['last_results'] : []
    );

    if ($out['setup_complete'] && (!$out['openai_tested'] || !$out['fal_tested'])) {
        $out['setup_complete'] = false;
    }

    return $out;
}

function bandpromo_create_integrations_load(string $root, string $username): array
{
    $path = bandpromo_create_integrations_path($root, $username);
    if ($path === '' || !is_file($path)) {
        return bandpromo_create_integrations_defaults();
    }

    $decoded = bandpromo_json_read_array_file($path);
    if (!is_array($decoded)) {
        return bandpromo_create_integrations_defaults();
    }

    return bandpromo_create_integrations_normalize($decoded);
}

/**
 * @param array<string, mixed> $patch
 */
function bandpromo_create_integrations_save(string $root, string $username, array $patch): bool
{
    $path = bandpromo_create_integrations_path($root, $username);
    if ($path === '') {
        return false;
    }

    $current = bandpromo_create_integrations_load($root, $username);

    if (array_key_exists('openai_api_key', $patch)) {
        $current['openai_api_key'] = trim((string) $patch['openai_api_key']);
        // Every connected key must be re-proven after change or clear.
        $current['openai_tested'] = false;
        $current['prompt_tested'] = false;
        $current['setup_complete'] = false;
        $current['setup_completed_at_utc'] = '';
        if (isset($current['last_results']) && is_array($current['last_results'])) {
            unset($current['last_results']['openai_proof']);
        }
        bandpromo_create_integrations_delete_result_media($root, $username, 'openai_proof_image');
    }
    if (array_key_exists('fal_api_key', $patch)) {
        $current['fal_api_key'] = trim((string) $patch['fal_api_key']);
        $current['fal_tested'] = false;
        $current['image_tested'] = false;
        $current['setup_complete'] = false;
        $current['setup_completed_at_utc'] = '';
        if (isset($current['last_results']) && is_array($current['last_results'])) {
            unset($current['last_results']['fal_proof']);
        }
        bandpromo_create_integrations_delete_result_media($root, $username, 'fal_proof_image');
    }

    if (array_key_exists('llm_price_tier', $patch)) {
        $current['llm_price_tier'] = bandpromo_create_integrations_normalize_price_tier((string) $patch['llm_price_tier']);
    } elseif (array_key_exists('price_tier', $patch)) {
        $current['llm_price_tier'] = bandpromo_create_integrations_normalize_price_tier((string) $patch['price_tier']);
    }
    if (array_key_exists('image_price_tier', $patch)) {
        $current['image_price_tier'] = bandpromo_create_integrations_normalize_price_tier((string) $patch['image_price_tier']);
    }
    if (array_key_exists('video_price_tier', $patch)) {
        $current['video_price_tier'] = bandpromo_create_integrations_normalize_price_tier((string) $patch['video_price_tier']);
    }

    $llmTier = (string) $current['llm_price_tier'];
    $imageTier = (string) $current['image_price_tier'];
    $videoTier = (string) $current['video_price_tier'];

    if (array_key_exists('llm_model', $patch)) {
        $current['llm_model'] = bandpromo_create_integrations_normalize_llm_model((string) $patch['llm_model'], $llmTier);
    } elseif (array_key_exists('openai_model', $patch)) {
        $current['llm_model'] = bandpromo_create_integrations_normalize_llm_model(
            'openai:' . trim((string) $patch['openai_model']),
            $llmTier
        );
    }
    if (array_key_exists('image_model', $patch)) {
        $current['image_model'] = bandpromo_create_integrations_normalize_image_model((string) $patch['image_model'], $imageTier);
    } elseif (array_key_exists('fal_model', $patch)) {
        $current['image_model'] = bandpromo_create_integrations_normalize_image_model((string) $patch['fal_model'], $imageTier);
    }
    if (array_key_exists('video_model', $patch)) {
        $current['video_model'] = bandpromo_create_integrations_normalize_video_model((string) $patch['video_model'], $videoTier);
    }

    if (array_key_exists('openai_tested', $patch)) {
        $current['openai_tested'] = !empty($patch['openai_tested']);
        $current['prompt_tested'] = $current['openai_tested'];
    } elseif (array_key_exists('prompt_tested', $patch)) {
        $current['prompt_tested'] = !empty($patch['prompt_tested']);
        $current['openai_tested'] = $current['prompt_tested'];
    }
    if (array_key_exists('fal_tested', $patch)) {
        $current['fal_tested'] = !empty($patch['fal_tested']);
        $current['image_tested'] = $current['fal_tested'];
    } elseif (array_key_exists('image_tested', $patch)) {
        $current['image_tested'] = !empty($patch['image_tested']);
        $current['fal_tested'] = $current['image_tested'];
    }
    if (array_key_exists('setup_complete', $patch)) {
        $current['setup_complete'] = !empty($patch['setup_complete']);
        if ($current['setup_complete']) {
            $current['setup_completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        } else {
            $current['setup_completed_at_utc'] = '';
        }
    }
    if (array_key_exists('clear_setup', $patch) && !empty($patch['clear_setup'])) {
        $current['setup_complete'] = false;
        $current['openai_tested'] = false;
        $current['fal_tested'] = false;
        $current['prompt_tested'] = false;
        $current['image_tested'] = false;
        $current['setup_completed_at_utc'] = '';
        // Keep last proof media so Reconfigure still shows what passed before.
    }

    // Keep each selection inside its own panel tier. Preference edits never clear Start setup.
    $llmMeta = bandpromo_create_integrations_llm_model_meta((string) $current['llm_model']);
    if (!$llmMeta || ($llmMeta['price_tier'] ?? '') !== $llmTier) {
        $current['llm_model'] = bandpromo_create_integrations_default_llm_model($llmTier);
        $llmMeta = bandpromo_create_integrations_llm_model_meta((string) $current['llm_model']);
    }
    $imageMeta = bandpromo_create_integrations_image_model_meta((string) $current['image_model']);
    if (!$imageMeta || ($imageMeta['price_tier'] ?? '') !== $imageTier) {
        $current['image_model'] = bandpromo_create_integrations_default_image_model($imageTier);
    }
    $videoMeta = bandpromo_create_integrations_video_model_meta((string) $current['video_model']);
    if (!$videoMeta || ($videoMeta['price_tier'] ?? '') !== $videoTier) {
        $current['video_model'] = bandpromo_create_integrations_default_video_model($videoTier);
    }

    $current['price_tier'] = $llmTier;
    $current['openai_model'] = ($llmMeta && ($llmMeta['route'] ?? '') === 'openai')
        ? (string) $llmMeta['provider_model']
        : bandpromo_create_integrations_default_openai_model();
    $current['fal_model'] = (string) $current['image_model'];

    // Keep legacy aliases aligned with provider proofs.
    $current['prompt_tested'] = !empty($current['openai_tested']);
    $current['image_tested'] = !empty($current['fal_tested']);

    // Ready only when every connected provider proof has passed (OpenAI + fal today).
    if (!empty($current['openai_tested']) && !empty($current['fal_tested'])) {
        $current['setup_complete'] = true;
        if (trim((string) $current['setup_completed_at_utc']) === '') {
            $current['setup_completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        }
    } else {
        $current['setup_complete'] = false;
        $current['setup_completed_at_utc'] = '';
    }

    $current['updated_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');

    return bandpromo_json_write_file($path, $current);
}

function bandpromo_create_integrations_mask_key(string $key): string
{
    $key = trim($key);
    $len = strlen($key);
    if ($len === 0) {
        return '';
    }
    if ($len <= 8) {
        return str_repeat('•', $len);
    }

    // Fixed short preview — never mirror full key length with dots.
    return substr($key, 0, 4) . '••••' . substr($key, -4);
}

/**
 * Best-effort fal credit balance. Needs a key with billing:usage:read (BILLING / READONLY / FULL).
 *
 * @return array{ok:bool,available:bool,balance:?float,currency:string,label:string,buy_url:string,error:string}
 */
function bandpromo_create_integrations_fal_credits(string $apiKey): array
{
    $buyUrl = 'https://fal.ai/dashboard/billing';
    $apiKey = trim($apiKey);
    $empty = [
        'ok' => false,
        'available' => false,
        'balance' => null,
        'currency' => 'USD',
        'label' => '',
        'buy_url' => $buyUrl,
        'error' => '',
    ];
    if ($apiKey === '') {
        $empty['error'] = 'No fal.ai key recorded.';

        return $empty;
    }

    $response = bandpromo_create_integrations_http_json(
        'GET',
        'https://api.fal.ai/v1/account/billing?expand=credits',
        [
            'Authorization' => 'Key ' . $apiKey,
            'Accept' => 'application/json',
        ],
        null,
        20
    );
    $decoded = json_decode($response['body'], true);
    if (!$response['ok'] || !is_array($decoded)) {
        $message = '';
        if (is_array($decoded)) {
            $err = $decoded['error'] ?? null;
            if (is_array($err)) {
                $message = trim((string) ($err['message'] ?? ''));
            } elseif (is_string($err)) {
                $message = trim($err);
            }
            if ($message === '') {
                $message = trim((string) ($decoded['detail'] ?? $decoded['message'] ?? ''));
            }
        }
        if ($message === '') {
            $message = $response['error'] !== '' ? $response['error'] : 'Could not read fal balance.';
        }
        $empty['error'] = $message;

        return $empty;
    }

    $credits = is_array($decoded['credits'] ?? null) ? $decoded['credits'] : null;
    if ($credits === null || !isset($credits['current_balance'])) {
        $empty['error'] = 'fal did not return a credit balance for this key (billing permission may be missing).';

        return $empty;
    }

    $balance = (float) $credits['current_balance'];
    $currency = trim((string) ($credits['currency'] ?? 'USD'));
    if ($currency === '') {
        $currency = 'USD';
    }
    $formatted = number_format($balance, 2, '.', '');
    $label = $currency === 'USD'
        ? ('$' . $formatted)
        : ($formatted . ' ' . $currency);

    return [
        'ok' => true,
        'available' => true,
        'balance' => $balance,
        'currency' => $currency,
        'label' => $label,
        'buy_url' => $buyUrl,
        'error' => '',
    ];
}

/**
 * OpenAI has no supported remaining-balance API for ordinary project keys.
 *
 * @return array{ok:bool,available:bool,balance:?float,currency:string,label:string,buy_url:string,error:string}
 */
function bandpromo_create_integrations_openai_credits(): array
{
    return [
        'ok' => false,
        'available' => false,
        'balance' => null,
        'currency' => 'USD',
        'label' => '',
        'buy_url' => 'https://platform.openai.com/settings/organization/billing',
        'error' => 'OpenAI does not expose remaining credit balance to ordinary API keys. Check billing in the OpenAI dashboard.',
    ];
}

/**
 * Load selected keys from gitignored .env without dumping the file.
 *
 * @param list<string> $names
 * @return array<string, string>
 */
function bandpromo_create_integrations_read_dotenv(string $root, array $names): array
{
    $wanted = [];
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name !== '') {
            $wanted[$name] = true;
        }
    }
    if ($wanted === []) {
        return [];
    }

    $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $found = [];
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (stripos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $name = trim(substr($line, 0, $eq));
        if ($name === '' || !isset($wanted[$name])) {
            continue;
        }
        $value = trim(substr($line, $eq + 1));
        if ($value !== '' && (($value[0] === "'" && substr($value, -1) === "'") || ($value[0] === '"' && substr($value, -1) === '"'))) {
            $value = substr($value, 1, -1);
        }
        $found[$name] = $value;
    }

    return $found;
}

function bandpromo_create_integrations_env_value(string $root, string $name): string
{
    $fromProcess = getenv($name);
    if (is_string($fromProcess) && trim($fromProcess) !== '') {
        return trim($fromProcess);
    }

    $dotenv = bandpromo_create_integrations_read_dotenv($root, [$name]);
    if (isset($dotenv[$name]) && trim((string) $dotenv[$name]) !== '') {
        return trim((string) $dotenv[$name]);
    }

    return '';
}

/**
 * @return array{
 *   openai_api_key:string,
 *   fal_api_key:string,
 *   openai_source:string,
 *   fal_source:string
 * }
 */
function bandpromo_create_integrations_resolve_keys(string $root, string $username): array
{
    $stored = bandpromo_create_integrations_load($root, $username);
    $openai = trim((string) $stored['openai_api_key']);
    $openaiSource = $openai !== '' ? 'user' : '';
    if ($openai === '') {
        $openai = bandpromo_create_integrations_env_value($root, 'OPENAI_API_KEY');
        if ($openai !== '') {
            $openaiSource = 'env';
        }
    }

    $fal = trim((string) $stored['fal_api_key']);
    $falSource = $fal !== '' ? 'user' : '';
    if ($fal === '') {
        $fal = bandpromo_create_integrations_env_value($root, 'FAL_KEY');
        if ($fal !== '') {
            $falSource = 'env';
        }
    }

    return [
        'openai_api_key' => $openai,
        'fal_api_key' => $fal,
        'openai_source' => $openaiSource,
        'fal_source' => $falSource,
    ];
}

/**
 * @return array<string, mixed>
 */
function bandpromo_create_integrations_status_payload(string $root, string $username): array
{
    $stored = bandpromo_create_integrations_load($root, $username);
    $resolved = bandpromo_create_integrations_resolve_keys($root, $username);
    $llmTier = bandpromo_create_integrations_normalize_price_tier((string) $stored['llm_price_tier']);
    $imageTier = bandpromo_create_integrations_normalize_price_tier((string) $stored['image_price_tier']);
    $videoTier = bandpromo_create_integrations_normalize_price_tier((string) $stored['video_price_tier']);
    $llmModel = bandpromo_create_integrations_normalize_llm_model((string) $stored['llm_model'], $llmTier);
    $imageModel = bandpromo_create_integrations_normalize_image_model((string) $stored['image_model'], $imageTier);
    $videoModel = bandpromo_create_integrations_normalize_video_model((string) $stored['video_model'], $videoTier);
    $llmMeta = bandpromo_create_integrations_llm_model_meta($llmModel);
    $imageMeta = bandpromo_create_integrations_image_model_meta($imageModel);
    $videoMeta = bandpromo_create_integrations_video_model_meta($videoModel);

    $llmRoute = is_array($llmMeta) ? (string) ($llmMeta['route'] ?? 'fal') : 'fal';
    $openaiConfigured = $resolved['openai_api_key'] !== '';
    $falConfigured = $resolved['fal_api_key'] !== '';
    $promptKeyReady = $llmRoute === 'openai' ? $openaiConfigured : $falConfigured;
    $priceTiers = [
        ['id' => 'cheap', 'label' => 'Cheap'],
        ['id' => 'good', 'label' => 'Good'],
        ['id' => 'premium', 'label' => 'Premium'],
    ];
    // Balance lookup is only useful on the Start ready view; skip the network call during setup.
    $openaiCredits = bandpromo_create_integrations_openai_credits();
    $falCredits = bandpromo_create_integrations_fal_credits('');
    if (!empty($stored['setup_complete']) && $falConfigured) {
        $falCredits = bandpromo_create_integrations_fal_credits($resolved['fal_api_key']);
    }

    return [
        'openai_configured' => $openaiConfigured,
        'fal_configured' => $falConfigured,
        'openai_preview' => $openaiConfigured
            ? bandpromo_create_integrations_mask_key($resolved['openai_api_key'])
            : '',
        'fal_preview' => $falConfigured
            ? bandpromo_create_integrations_mask_key($resolved['fal_api_key'])
            : '',
        'openai_source' => $resolved['openai_source'],
        'fal_source' => $resolved['fal_source'],
        'llm_price_tier' => $llmTier,
        'image_price_tier' => $imageTier,
        'video_price_tier' => $videoTier,
        'llm_price_tier_label' => bandpromo_create_integrations_price_tier_label($llmTier),
        'image_price_tier_label' => bandpromo_create_integrations_price_tier_label($imageTier),
        'video_price_tier_label' => bandpromo_create_integrations_price_tier_label($videoTier),
        'price_tier' => $llmTier,
        'price_tier_label' => bandpromo_create_integrations_price_tier_label($llmTier),
        'price_tiers' => $priceTiers,
        'llm_model' => $llmModel,
        'image_model' => $imageModel,
        'video_model' => $videoModel,
        'llm_meta' => $llmMeta,
        'image_meta' => $imageMeta,
        'video_meta' => $videoMeta,
        'llm_route' => $llmRoute,
        'prompt_key_ready' => $promptKeyReady,
        'image_key_ready' => $falConfigured,
        'video_key_ready' => $falConfigured,
        'openai_model' => bandpromo_create_integrations_normalize_openai_model((string) $stored['openai_model']),
        'fal_model' => $imageModel,
        'openai_models' => bandpromo_create_integrations_openai_models(),
        'fal_models' => bandpromo_create_integrations_image_models(),
        'llm_models' => bandpromo_create_integrations_llm_models(),
        'image_models' => bandpromo_create_integrations_image_models(),
        'video_models' => bandpromo_create_integrations_video_models(),
        'llm_models_for_tier' => bandpromo_create_integrations_models_for_tier(
            bandpromo_create_integrations_llm_models(),
            $llmTier
        ),
        'image_models_for_tier' => bandpromo_create_integrations_models_for_tier(
            bandpromo_create_integrations_image_models(),
            $imageTier
        ),
        'video_models_for_tier' => bandpromo_create_integrations_models_for_tier(
            bandpromo_create_integrations_video_models(),
            $videoTier
        ),
        'setup_complete' => !empty($stored['setup_complete']),
        'openai_tested' => !empty($stored['openai_tested']),
        'fal_tested' => !empty($stored['fal_tested']),
        'prompt_tested' => !empty($stored['openai_tested']),
        'image_tested' => !empty($stored['fal_tested']),
        'setup_completed_at_utc' => (string) $stored['setup_completed_at_utc'],
        'updated_at_utc' => (string) $stored['updated_at_utc'],
        'start_proof_models' => bandpromo_create_integrations_start_proof_models(),
        'last_results' => bandpromo_create_integrations_last_results_payload($root, $username),
        'openai_credits' => $openaiCredits,
        'fal_credits' => $falCredits,
    ];
}

/**
 * @param array<string, string> $headers
 * @return array{ok:bool,status:int,body:string,error:string}
 */
function bandpromo_create_integrations_http_json(string $method, string $url, array $headers, $body = null, int $timeout = 90): array
{
    $method = strtoupper($method);
    $payload = null;
    if ($body !== null) {
        if (is_string($body)) {
            $payload = $body;
        } else {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not encode request body.'];
            }
            $payload = $encoded;
        }
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not initialize cURL.'];
        }

        $opts = bandpromo_ssl_curl_setopt_ca([
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'bandPromo Create integrations',
        ]);
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => ($error !== '' ? $error : 'HTTPS request failed.')];
        }

        return [
            'ok' => $status > 0 && $status < 400,
            'status' => $status,
            'body' => (string) $responseBody,
            'error' => ($status >= 400 ? ('HTTP ' . $status) : ''),
        ];
    }

    if (!ini_get('allow_url_fopen')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Outbound HTTPS needs cURL or allow_url_fopen.'];
    }

    $http = [
        'method' => $method,
        'header' => implode("\r\n", $headerLines),
        'timeout' => $timeout,
        'ignore_errors' => true,
        'follow_location' => 1,
        'user_agent' => 'bandPromo Create integrations',
    ];
    if ($payload !== null) {
        $http['content'] = $payload;
    }

    $context = stream_context_create([
        'http' => $http,
        'ssl' => bandpromo_ssl_stream_context_ca([
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]),
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $line, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }

    if ($responseBody === false) {
        return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'HTTPS request failed.'];
    }

    return [
        'ok' => $status > 0 && $status < 400,
        'status' => $status,
        'body' => (string) $responseBody,
        'error' => ($status >= 400 ? ('HTTP ' . $status) : ''),
    ];
}

/**
 * Turn a brand shell delivery web path into a fal-usable image URL.
 * Prefers a data URI from disk so fal does not need to reach this host.
 */
function bandpromo_create_integrations_logo_image_ref(string $root, string $webPath): string
{
    $webPath = trim($webPath);
    if ($webPath === '') {
        return '';
    }

    if (preg_match('#^data:image/#i', $webPath) === 1) {
        return $webPath;
    }

    $absHttp = '';
    if (preg_match('#^https?://#i', $webPath) === 1) {
        $absHttp = $webPath;
    } elseif (isset($webPath[0]) && $webPath[0] === '/') {
        try {
            require_once __DIR__ . '/config-loader.php';
            $siteUrl = '';
            if (function_exists('get_config_nonempty')) {
                $siteUrl = rtrim((string) get_config_nonempty('install.site.url', get_config('site.url', '')), '/');
            }
            if ($siteUrl !== '' && stripos($siteUrl, 'example.com') === false) {
                $absHttp = $siteUrl . $webPath;
            }
        } catch (Throwable $e) {
            $absHttp = '';
        }
    }

    $diskPath = '';
    if ($webPath[0] === '/') {
        $diskPath = rtrim($root, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
    }

    if ($diskPath !== '' && is_file($diskPath) && is_readable($diskPath)) {
        $size = @filesize($diskPath);
        // Keep under fal image context comfort; logos are small.
        if (is_int($size) && $size > 0 && $size <= 4 * 1024 * 1024) {
            $bytes = @file_get_contents($diskPath);
            if (is_string($bytes) && $bytes !== '') {
                $mime = 'image/png';
                $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $mime = 'image/jpeg';
                } elseif ($ext === 'webp') {
                    $mime = 'image/webp';
                } elseif ($ext === 'gif') {
                    $mime = 'image/gif';
                }

                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }
    }

    return $absHttp;
}

/**
 * @return array{
 *   ok:bool,
 *   used_fallback:bool,
 *   brand_id:string,
 *   brand_title:string,
 *   brief:string,
 *   filled_slots:list<string>,
 *   logo_web_path:string,
 *   logo_image_url:string
 * }
 */
function bandpromo_create_integrations_base_brand_brief(string $root): array
{
    require_once __DIR__ . '/brand-storage.php';

    $usedFallback = false;
    $document = [];
    try {
        $document = bandpromo_brand_load_active_document($root);
    } catch (Throwable $e) {
        $document = [];
    }

    if (!is_array($document) || $document === []) {
        $usedFallback = true;
        $document = function_exists('bandpromo_brand_default_document')
            ? bandpromo_brand_default_document()
            : [
                'id' => 'bandpromo-default',
                'title' => 'bandPromo Default',
                'mood' => 'Clean demo identity',
                'tone_notes' => 'Neutral platform defaults',
                'keywords' => ['demo', 'electronic', 'modern'],
                'tokens' => [
                    'color' => [
                        'primary' => '#00d2ff',
                        'secondary' => '#3a7bd5',
                        'background' => '#121212',
                        'text' => '#ffffff',
                    ],
                ],
                'asset_ids' => [],
            ];
    }

    $brandId = trim((string) ($document['id'] ?? ''));
    $brandTitle = trim((string) ($document['title'] ?? ''));
    if ($brandTitle === '') {
        $brandTitle = $brandId !== '' ? $brandId : 'Untitled brand';
    }

    $colors = [];
    $colorTokens = $document['tokens']['color'] ?? [];
    if (is_array($colorTokens)) {
        foreach (['primary', 'secondary', 'background', 'text', 'surface_mid', 'link'] as $key) {
            if (!empty($colorTokens[$key])) {
                $colors[] = $key . '=' . trim((string) $colorTokens[$key]);
            }
        }
    }

    $filledSlots = [];
    $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
    foreach (['logo', 'poster', 'background_image', 'background_video'] as $slot) {
        if (trim((string) ($assetIds[$slot] ?? '')) !== '') {
            $filledSlots[] = $slot;
        }
    }

    $keywords = [];
    if (is_array($document['keywords'] ?? null)) {
        foreach ($document['keywords'] as $word) {
            $word = trim((string) $word);
            if ($word !== '') {
                $keywords[] = $word;
            }
        }
    }

    $logoWebPath = '';
    if (!$usedFallback) {
        try {
            $logoWebPath = trim(bandpromo_brand_resolve_shell_slot_url($root, $document, 'logo'));
        } catch (Throwable $e) {
            $logoWebPath = '';
        }
    }
    $logoImageUrl = $logoWebPath !== ''
        ? bandpromo_create_integrations_logo_image_ref($root, $logoWebPath)
        : '';

    $lines = [
        'Brand title: ' . $brandTitle,
        'Brand id: ' . ($brandId !== '' ? $brandId : 'unknown'),
        'Mood: ' . trim((string) ($document['mood'] ?? 'n/a')),
        'Tone notes: ' . trim((string) ($document['tone_notes'] ?? 'n/a')),
        'Keywords: ' . ($keywords !== [] ? implode(', ', $keywords) : 'n/a'),
        'Colours: ' . ($colors !== [] ? implode(', ', $colors) : 'n/a'),
        'Filled shell slots: ' . ($filledSlots !== [] ? implode(', ', $filledSlots) : 'none yet'),
        'Brand logo reference: ' . ($logoImageUrl !== '' ? 'available (will be attached for image generation)' : 'none'),
    ];

    return [
        'ok' => true,
        'used_fallback' => $usedFallback,
        'brand_id' => $brandId,
        'brand_title' => $brandTitle,
        'brief' => implode("\n", $lines),
        'filled_slots' => $filledSlots,
        'logo_web_path' => $logoWebPath,
        'logo_image_url' => $logoImageUrl,
    ];
}

/**
 * @return array{
 *   ok:bool,
 *   prompt:string,
 *   error:string,
 *   status:int,
 *   model:string,
 *   cost?:array{prompt_tokens:int,completion_tokens:int,total_tokens:int,estimate_usd:float,estimate_label:string},
 *   needs_setup?:bool,
 *   help?:string
 * }
 */
function bandpromo_create_integrations_openai_artwork_prompt(string $apiKey, string $brandBrief, string $model = ''): array
{
    $apiKey = trim($apiKey);
    $model = bandpromo_create_integrations_normalize_openai_model($model);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'OpenAI API key is missing.',
            'status' => 0,
            'model' => $model,
            'needs_setup' => true,
            'help' => 'Add an OpenAI API key under Create → Connect services (or set OPENAI_API_KEY in the install .env). Create a key at https://platform.openai.com/api-keys',
        ];
    }

    $system = 'You write concise image-generation prompts for music promotional artwork. '
        . 'Reply with one plain prompt only — no markdown, no quotes, no preamble. '
        . 'UK English spelling is fine. Keep it under 1000 characters. '
        . 'Describe a square promotional still that fits the brand brief. '
        . 'When a brand logo reference is available, say the logo should appear prominently and stay recognisable. '
        . 'Do not ask for large blocks of readable text, slogans, paragraphs, or words copied from this brief. '
        . 'If any lettering appears at all, keep it to a short title or artist name at most — otherwise prefer no text in the image. '
        . 'Describe mood, colour, composition, and lighting instead of writing copy to render.';
    $user = "Write an artwork prompt for this base brand:\n\n" . $brandBrief;

    $body = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
    ];
    // gpt-4o* still uses max_tokens + temperature; newer chat models prefer max_completion_tokens.
    // ~1000 characters needs headroom beyond a short reply (~400 completion tokens).
    if (strpos($model, 'gpt-4o') === 0) {
        $body['temperature'] = 0.8;
        $body['max_tokens'] = 400;
    } else {
        $body['max_completion_tokens'] = 400;
    }

    $response = bandpromo_create_integrations_http_json(
        'POST',
        'https://api.openai.com/v1/chat/completions',
        [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        $body,
        60
    );

    $decoded = json_decode($response['body'], true);
    if (!$response['ok']) {
        $message = '';
        if (is_array($decoded)) {
            $message = trim((string) (($decoded['error']['message'] ?? '') ?: ($decoded['error'] ?? '')));
        }
        if ($message === '') {
            $message = $response['error'] !== '' ? $response['error'] : 'OpenAI request failed.';
        }
        $needsSetup = in_array($response['status'], [401, 403], true);

        return [
            'ok' => false,
            'prompt' => '',
            'error' => $message,
            'status' => $response['status'],
            'model' => $model,
            'needs_setup' => $needsSetup,
            'help' => $needsSetup
                ? 'Check the OpenAI key under Create → Connect services. Create or rotate keys at https://platform.openai.com/api-keys'
                : '',
        ];
    }

    $prompt = '';
    $usage = null;
    if (is_array($decoded)) {
        $prompt = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        if (isset($decoded['usage']) && is_array($decoded['usage'])) {
            $usage = $decoded['usage'];
        }
    }
    $prompt = trim($prompt, " \t\n\r\0\x0B\"'");
    if ($prompt === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'OpenAI returned an empty prompt.',
            'status' => $response['status'],
            'model' => $model,
        ];
    }

    return [
        'ok' => true,
        'prompt' => $prompt,
        'error' => '',
        'status' => $response['status'],
        'model' => $model,
        'cost' => bandpromo_create_integrations_openai_cost_from_usage($model, $usage),
    ];
}

/**
 * Native OpenAI Images edit (proves the OpenAI key can create/edit stills with a logo reference).
 *
 * @return array{ok:bool,image_url:string,error:string,status:int,model:string,logo_attached:bool,cost?:array<string,mixed>,needs_setup?:bool,help?:string}
 */
function bandpromo_create_integrations_openai_image(
    string $apiKey,
    string $prompt,
    string $logoImageUrl = '',
    string $model = ''
): array {
    $apiKey = trim($apiKey);
    $model = bandpromo_create_integrations_openai_native_image_model($model);
    $prompt = trim($prompt);
    $logoImageUrl = trim($logoImageUrl);
    $logoUsable = $logoImageUrl !== ''
        && (preg_match('#^https?://#i', $logoImageUrl) === 1 || preg_match('#^data:image/#i', $logoImageUrl) === 1);

    if ($apiKey === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'error' => 'OpenAI API key is missing.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'needs_setup' => true,
            'help' => 'Add an OpenAI API key under Create → Start.',
        ];
    }
    if ($prompt === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'error' => 'Missing image prompt for OpenAI.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
        ];
    }
    if (!$logoUsable) {
        return [
            'ok' => false,
            'image_url' => '',
            'error' => 'OpenAI image proof needs a brand logo reference. Set one under Content → Branding, then try again.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'help' => 'Open Content → Branding and set a logo on the base brand.',
        ];
    }

    $response = bandpromo_create_integrations_http_json(
        'POST',
        'https://api.openai.com/v1/images/edits',
        [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        [
            'model' => $model,
            'prompt' => $prompt,
            'images' => [
                ['image_url' => $logoImageUrl],
            ],
            'size' => '1024x1024',
            'quality' => 'medium',
            'output_format' => 'png',
        ],
        120
    );

    $decoded = json_decode($response['body'], true);
    if (!$response['ok']) {
        $message = '';
        if (is_array($decoded)) {
            $err = $decoded['error'] ?? null;
            if (is_array($err)) {
                $message = trim((string) ($err['message'] ?? ''));
            } elseif (is_string($err)) {
                $message = trim($err);
            }
        }
        if ($message === '') {
            $message = $response['error'] !== '' ? $response['error'] : 'OpenAI image request failed.';
        }
        $needsSetup = in_array($response['status'], [401, 403], true);

        return [
            'ok' => false,
            'image_url' => '',
            'error' => $message,
            'status' => $response['status'],
            'model' => $model,
            'logo_attached' => true,
            'needs_setup' => $needsSetup,
            'help' => $needsSetup
                ? 'Check the OpenAI key under Create → Start. Create or rotate keys at https://platform.openai.com/api-keys'
                : '',
        ];
    }

    $imageUrl = '';
    if (is_array($decoded) && isset($decoded['data'][0]) && is_array($decoded['data'][0])) {
        $row = $decoded['data'][0];
        $imageUrl = trim((string) ($row['url'] ?? ''));
        if ($imageUrl === '' && !empty($row['b64_json'])) {
            $imageUrl = 'data:image/png;base64,' . trim((string) $row['b64_json']);
        }
    }
    if ($imageUrl === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'error' => 'OpenAI returned no image.',
            'status' => $response['status'],
            'model' => $model,
            'logo_attached' => true,
        ];
    }

    return [
        'ok' => true,
        'image_url' => $imageUrl,
        'error' => '',
        'status' => $response['status'],
        'model' => $model,
        'logo_attached' => true,
        'cost' => [
            'estimate_usd' => 0.0,
            'estimate_label' => 'OpenAI Images · ' . $model,
            'supports_logo' => true,
        ],
    ];
}

/**
 * Artwork prompt via fal OpenRouter router (see create-model-catalog.json defaults).
 *
 * @return array{ok:bool,prompt:string,error:string,status:int,model:string,cost?:array<string,mixed>,needs_setup?:bool,help?:string}
 */
function bandpromo_create_integrations_fal_artwork_prompt(string $apiKey, string $brandBrief, string $providerModel): array
{
    $apiKey = trim($apiKey);
    $providerModel = trim($providerModel);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'fal.ai API key is missing.',
            'status' => 0,
            'model' => $providerModel,
            'needs_setup' => true,
            'help' => 'Add a fal.ai API key under Create → Connect (or set FAL_KEY in the install .env).',
        ];
    }

    $system = 'You write concise image-generation prompts for music promotional artwork. '
        . 'Reply with one plain prompt only — no markdown, no quotes, no preamble. '
        . 'UK English spelling is fine. Keep it under 1000 characters. '
        . 'Describe a square promotional still that fits the brand brief. '
        . 'When a brand logo reference is available, say the logo should appear prominently and stay recognisable. '
        . 'Do not ask for large blocks of readable text, slogans, paragraphs, or words copied from this brief. '
        . 'If any lettering appears at all, keep it to a short title or artist name at most — otherwise prefer no text in the image. '
        . 'Describe mood, colour, composition, and lighting instead of writing copy to render.';
    $user = "Write an artwork prompt for this base brand:\n\n" . $brandBrief;

    $falEndpoint = bandpromo_create_integrations_catalog_llm_endpoint();
    // fal openrouter/router defaults reasoning=false. Mandatory-reasoning models
    // (e.g. Claude Sonnet 5.5) reject that with "Reasoning is mandatory…".
    $response = bandpromo_create_integrations_http_json(
        'POST',
        'https://fal.run/' . ltrim($falEndpoint, '/'),
        [
            'Authorization' => 'Key ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        [
            'model' => $providerModel,
            'system_prompt' => $system,
            'prompt' => $user,
            'max_tokens' => 400,
            'reasoning' => true,
        ],
        60
    );

    $decoded = json_decode($response['body'], true);
    if (!$response['ok']) {
        $message = '';
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['error'] ?? $decoded['detail'] ?? $decoded['message'] ?? ''));
        }
        if ($message === '') {
            $message = $response['error'] !== '' ? $response['error'] : 'fal LLM request failed.';
        }
        $needsSetup = in_array($response['status'], [401, 403], true);

        return [
            'ok' => false,
            'prompt' => '',
            'error' => $message,
            'status' => $response['status'],
            'model' => $providerModel,
            'needs_setup' => $needsSetup,
            'help' => $needsSetup
                ? 'Check the fal.ai key under Create → Connect. Create or rotate keys at https://fal.ai/dashboard/keys'
                : '',
        ];
    }

    $prompt = '';
    if (is_array($decoded)) {
        $prompt = trim((string) ($decoded['output'] ?? ''));
    }
    $prompt = trim($prompt, " \t\n\r\0\x0B\"'");
    if ($prompt === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'fal LLM returned an empty prompt.',
            'status' => $response['status'],
            'model' => $providerModel,
        ];
    }

    $meta = null;
    foreach (bandpromo_create_integrations_llm_models() as $row) {
        if (($row['route'] ?? '') === 'fal' && ($row['provider_model'] ?? '') === $providerModel) {
            $meta = $row;
            break;
        }
    }
    $hint = is_array($meta) ? (string) ($meta['price_hint'] ?? '') : 'fal OpenRouter';
    $usage = is_array($decoded) && is_array($decoded['usage'] ?? null) ? $decoded['usage'] : null;
    $promptTokens = is_array($usage) ? (int) ($usage['prompt_tokens'] ?? 0) : 0;
    $completionTokens = is_array($usage) ? (int) ($usage['completion_tokens'] ?? 0) : 0;
    $totalTokens = is_array($usage) ? (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens)) : ($promptTokens + $completionTokens);
    $estimate = 0.0;
    if (is_array($usage) && isset($usage['cost']) && is_numeric($usage['cost'])) {
        $estimate = (float) $usage['cost'];
    } elseif (is_array($meta)) {
        $inRate = (float) ($meta['input_per_mtok'] ?? 0);
        $outRate = (float) ($meta['output_per_mtok'] ?? 0);
        if ($inRate > 0 || $outRate > 0) {
            $estimate = ($promptTokens / 1000000.0) * $inRate
                + ($completionTokens / 1000000.0) * $outRate;
        }
    }

    return [
        'ok' => true,
        'prompt' => $prompt,
        'error' => '',
        'status' => $response['status'],
        'model' => $providerModel,
        'cost' => [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimate_usd' => round($estimate, 8),
            'estimate_label' => $hint,
        ],
    ];
}

/**
 * Route artwork prompt to OpenAI or fal OpenRouter from a unified llm_model id.
 *
 * @return array{ok:bool,prompt:string,error:string,status:int,model:string,route:string,cost?:array<string,mixed>,needs_setup?:bool,help?:string}
 */
function bandpromo_create_integrations_artwork_prompt(
    string $root,
    string $username,
    string $brandBrief,
    string $llmModel = ''
): array {
    $resolved = bandpromo_create_integrations_resolve_keys($root, $username);
    $prefs = bandpromo_create_integrations_load($root, $username);
    $llmModel = bandpromo_create_integrations_normalize_llm_model(
        $llmModel !== '' ? $llmModel : (string) $prefs['llm_model'],
        (string) $prefs['price_tier']
    );
    $meta = bandpromo_create_integrations_llm_model_meta($llmModel);
    $route = is_array($meta) ? (string) ($meta['route'] ?? 'fal') : 'fal';
    $providerModel = is_array($meta) ? (string) ($meta['provider_model'] ?? '') : '';

    if ($route === 'openai') {
        $result = bandpromo_create_integrations_openai_artwork_prompt(
            $resolved['openai_api_key'],
            $brandBrief,
            $providerModel
        );
        $result['route'] = 'openai';
        $result['llm_model'] = $llmModel;

        return $result;
    }

    $result = bandpromo_create_integrations_fal_artwork_prompt(
        $resolved['fal_api_key'],
        $brandBrief,
        $providerModel
    );
    $result['route'] = 'fal';
    $result['llm_model'] = $llmModel;

    return $result;
}

/**
 * Build fal queue payload for a curated Create image edit model.
 * Every curated model requires a usable brand-logo reference URL.
 *
 * @return array{endpoint:string,payload:array<string,mixed>,logo_attached:bool,cost:array{estimate_usd:float,estimate_label:string,supports_logo:bool},error?:string}
 */
function bandpromo_create_integrations_fal_build_request(string $model, string $prompt, string $logoImageUrl = ''): array
{
    $model = bandpromo_create_integrations_normalize_fal_model($model);
    $meta = bandpromo_create_integrations_fal_model_meta($model);
    $family = is_array($meta) ? (string) ($meta['family'] ?? '') : '';
    $logoImageUrl = trim($logoImageUrl);
    $logoUsable = $logoImageUrl !== ''
        && (preg_match('#^https?://#i', $logoImageUrl) === 1 || preg_match('#^data:image/#i', $logoImageUrl) === 1);
    $cost = bandpromo_create_integrations_fal_cost_estimate($model);
    $endpoint = 'https://queue.fal.run/' . $model;

    if (!$logoUsable) {
        return [
            'endpoint' => $endpoint,
            'payload' => [],
            'logo_attached' => false,
            'cost' => $cost,
            'error' => 'These image models need a brand logo reference. Set one under Content → Branding, then try again.',
        ];
    }

    $payload = [
        'prompt' => $prompt,
        'num_images' => 1,
    ];
    $variant = is_array($meta) ? trim((string) ($meta['variant'] ?? '')) : '';

    if ($family === 'qwen_edit') {
        // Classic Qwen edit takes a single image_url (not image_urls).
        $payload['image_url'] = $logoImageUrl;
        $payload['image_size'] = 'square_hd';
        $payload['output_format'] = 'png';
        $payload['enable_safety_checker'] = true;
    } elseif ($family === 'style_ref') {
        // Recraft-style endpoints: pass the logo as the style/reference image when possible.
        $payload['image_url'] = $logoImageUrl;
        $payload['style_image_url'] = $logoImageUrl;
    } else {
        $payload['image_urls'] = [$logoImageUrl];

        if ($family === 'openai_gpt_image') {
            $payload['image_size'] = '1024x1024';
            $payload['quality'] = 'low';
            $payload['input_fidelity'] = 'high';
            $payload['output_format'] = 'png';
        } elseif ($family === 'flux_edit' || $family === 'flux_flash_edit') {
            $payload['image_size'] = 'square_hd';
            $payload['output_format'] = 'png';
            $payload['enable_safety_checker'] = true;
        } elseif ($family === 'grok_imagine') {
            $payload['aspect_ratio'] = '1:1';
            $payload['resolution'] = '1k';
            $payload['output_format'] = 'png';
        } elseif ($family === 'seedream' || $family === 'seedream_lite') {
            $payload['image_size'] = 'square_hd';
            $payload['enable_safety_checker'] = true;
        } elseif ($family === 'krea_edit') {
            $payload['image_size'] = 'square_hd';
            $payload['output_format'] = 'png';
            if ($variant !== '') {
                $payload['model'] = $variant;
            }
        } elseif ($family === 'nano_banana') {
            $payload['aspect_ratio'] = '1:1';
            $payload['resolution'] = '1K';
            $payload['output_format'] = 'png';
            $payload['limit_generations'] = true;
            $payload['system_prompt'] = 'Use the brand logo reference image. Keep the logo recognisable while following the prompt.';
        } else {
            $payload['aspect_ratio'] = '1:1';
            $payload['image_size'] = 'square_hd';
            $payload['output_format'] = 'png';
        }
    }

    return [
        'endpoint' => $endpoint,
        'payload' => $payload,
        'logo_attached' => true,
        'cost' => $cost,
    ];
}

/**
 * Queue submit + poll for a curated fal image model. Does not resubmit on ambiguity.
 *
 * @return array{
 *   ok:bool,
 *   image_url:string,
 *   request_id:string,
 *   error:string,
 *   status:int,
 *   model:string,
 *   logo_attached:bool,
 *   cost?:array{estimate_usd:float,estimate_label:string,supports_logo:bool},
 *   needs_setup?:bool,
 *   needs_credits?:bool,
 *   help?:string
 * }
 */
function bandpromo_create_integrations_fal_image(string $apiKey, string $prompt, string $logoImageUrl = '', string $model = ''): array
{
    $apiKey = trim($apiKey);
    $model = bandpromo_create_integrations_normalize_fal_model($model);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => '',
            'error' => 'fal.ai API key is missing.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'needs_setup' => true,
            'help' => 'Add a fal.ai API key under Create → Connect services (or set FAL_KEY in the install .env). Create a key at https://fal.ai/dashboard/keys',
        ];
    }

    $prompt = trim($prompt);
    if ($prompt === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => '',
            'error' => 'Missing image prompt for fal.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
        ];
    }

    $authHeaders = [
        'Authorization' => 'Key ' . $apiKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];

    $built = bandpromo_create_integrations_fal_build_request($model, $prompt, $logoImageUrl);
    $logoAttached = !empty($built['logo_attached']);
    $cost = is_array($built['cost'] ?? null) ? $built['cost'] : bandpromo_create_integrations_fal_cost_estimate($model);
    if (!empty($built['error'])) {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => '',
            'error' => (string) $built['error'],
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'cost' => $cost,
            'help' => 'Open Content → Branding and set a logo on the base brand, then run Create image from prompt again.',
        ];
    }

    $submit = bandpromo_create_integrations_http_json(
        'POST',
        (string) $built['endpoint'],
        $authHeaders,
        $built['payload'],
        60
    );

    $submitDecoded = json_decode($submit['body'], true);
    if (!$submit['ok']) {
        $err = bandpromo_create_integrations_fal_error_payload($submit, $submitDecoded);
        $err['logo_attached'] = $logoAttached;
        $err['model'] = $model;
        $err['cost'] = $cost;

        return $err;
    }

    $requestId = '';
    $statusUrl = '';
    $responseUrl = '';
    if (is_array($submitDecoded)) {
        $requestId = trim((string) ($submitDecoded['request_id'] ?? ''));
        $statusUrl = trim((string) ($submitDecoded['status_url'] ?? ''));
        $responseUrl = trim((string) ($submitDecoded['response_url'] ?? ''));
    }

    if ($statusUrl === '' || stripos($statusUrl, 'https://') !== 0 || stripos($statusUrl, 'fal.') === false) {
        // Some responses already include the completed result.
        $imageUrl = bandpromo_create_integrations_fal_extract_image_url(is_array($submitDecoded) ? $submitDecoded : []);
        if ($imageUrl !== '') {
            return [
                'ok' => true,
                'image_url' => $imageUrl,
                'request_id' => $requestId,
                'error' => '',
                'status' => $submit['status'],
                'model' => $model,
                'logo_attached' => $logoAttached,
                'cost' => $cost,
            ];
        }

        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => $requestId,
            'error' => 'fal queue response did not include a usable status URL.',
            'status' => $submit['status'],
            'model' => $model,
            'logo_attached' => $logoAttached,
            'cost' => $cost,
        ];
    }

    $deadline = time() + 120;
    $lastStatus = $submit['status'];
    $lastBody = $submit['body'];
    while (time() < $deadline) {
        usleep(1500000);
        $poll = bandpromo_create_integrations_http_json('GET', $statusUrl, [
            'Authorization' => 'Key ' . $apiKey,
            'Accept' => 'application/json',
        ], null, 30);
        $lastStatus = $poll['status'];
        $lastBody = $poll['body'];
        $pollDecoded = json_decode($poll['body'], true);

        if (!$poll['ok'] && $poll['status'] >= 400) {
            $err = bandpromo_create_integrations_fal_error_payload($poll, $pollDecoded, $requestId);
            $err['logo_attached'] = $logoAttached;
            $err['model'] = $model;
            $err['cost'] = $cost;

            return $err;
        }

        $queueStatus = '';
        if (is_array($pollDecoded)) {
            $queueStatus = strtoupper(trim((string) ($pollDecoded['status'] ?? '')));
            if (!empty($pollDecoded['response_url'])) {
                $candidate = trim((string) $pollDecoded['response_url']);
                if (stripos($candidate, 'https://') === 0 && stripos($candidate, 'fal.') !== false) {
                    $responseUrl = $candidate;
                }
            }
        }

        if ($queueStatus === 'COMPLETED' || $queueStatus === 'OK') {
            break;
        }
        if (in_array($queueStatus, ['FAILED', 'ERROR', 'CANCELLED'], true)) {
            $detail = '';
            if (is_array($pollDecoded)) {
                $detail = trim((string) ($pollDecoded['error'] ?? $pollDecoded['detail'] ?? ''));
            }

            return [
                'ok' => false,
                'image_url' => '',
                'request_id' => $requestId,
                'error' => $detail !== '' ? $detail : ('fal job ' . strtolower($queueStatus)),
                'status' => $lastStatus,
                'model' => $model,
                'logo_attached' => $logoAttached,
                'cost' => $cost,
            ];
        }
    }

    if ($responseUrl === '' || stripos($responseUrl, 'https://') !== 0 || stripos($responseUrl, 'fal.') === false) {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => $requestId,
            'error' => 'fal job finished without a usable result URL (not resubmitted).',
            'status' => $lastStatus,
            'model' => $model,
            'logo_attached' => $logoAttached,
            'cost' => $cost,
        ];
    }

    $result = bandpromo_create_integrations_http_json('GET', $responseUrl, [
        'Authorization' => 'Key ' . $apiKey,
        'Accept' => 'application/json',
    ], null, 60);
    $resultDecoded = json_decode($result['body'], true);
    if (!$result['ok']) {
        $err = bandpromo_create_integrations_fal_error_payload($result, $resultDecoded, $requestId);
        $err['logo_attached'] = $logoAttached;
        $err['model'] = $model;
        $err['cost'] = $cost;

        return $err;
    }

    $imageUrl = bandpromo_create_integrations_fal_extract_image_url(is_array($resultDecoded) ? $resultDecoded : []);
    if ($imageUrl === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => $requestId,
            'error' => 'fal returned no image URL.',
            'status' => $result['status'],
            'model' => $model,
            'logo_attached' => $logoAttached,
            'cost' => $cost,
        ];
    }

    return [
        'ok' => true,
        'image_url' => $imageUrl,
        'request_id' => $requestId,
        'error' => '',
        'status' => $result['status'],
        'model' => $model,
        'logo_attached' => $logoAttached,
        'cost' => $cost,
    ];
}

/**
 * Image-to-video via fal (brand logo / still as the reference image).
 *
 * @return array{ok:bool,video_url:string,request_id:string,error:string,status:int,model:string,logo_attached:bool,cost?:array<string,mixed>,needs_setup?:bool,needs_credits?:bool,help?:string}
 */
function bandpromo_create_integrations_fal_video(
    string $apiKey,
    string $prompt,
    string $logoImageUrl = '',
    string $model = ''
): array {
    $apiKey = trim($apiKey);
    $model = bandpromo_create_integrations_normalize_video_model($model);
    $meta = bandpromo_create_integrations_video_model_meta($model);
    $cost = [
        'estimate_usd' => is_array($meta) ? (float) ($meta['estimate_usd'] ?? 0) : 0.0,
        'estimate_label' => is_array($meta) ? (string) ($meta['price_hint'] ?? '') : '',
        'supports_logo' => true,
    ];
    $logoImageUrl = trim($logoImageUrl);
    $logoUsable = $logoImageUrl !== ''
        && (preg_match('#^https?://#i', $logoImageUrl) === 1 || preg_match('#^data:image/#i', $logoImageUrl) === 1);

    if ($apiKey === '') {
        return [
            'ok' => false,
            'video_url' => '',
            'request_id' => '',
            'error' => 'fal.ai API key is missing.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'needs_setup' => true,
            'help' => 'Add a fal.ai API key under Create → Start.',
            'cost' => $cost,
        ];
    }
    if (!$logoUsable) {
        return [
            'ok' => false,
            'video_url' => '',
            'request_id' => '',
            'error' => 'These video models need a brand logo reference. Set one under Content → Branding, then try again.',
            'status' => 0,
            'model' => $model,
            'logo_attached' => false,
            'cost' => $cost,
        ];
    }

    $endpoint = 'https://queue.fal.run/' . $model;
    $firstParam = is_array($meta) ? trim((string) ($meta['first_frame_parameter'] ?? 'image_url')) : 'image_url';
    $lastParam = is_array($meta) ? trim((string) ($meta['last_frame_parameter'] ?? 'end_image_url')) : 'end_image_url';
    if ($firstParam === '') {
        $firstParam = 'image_url';
    }
    if ($lastParam === '') {
        $lastParam = 'end_image_url';
    }
    $payload = [
        'prompt' => $prompt !== ''
            ? $prompt
            : 'Subtle living motion for promotional artwork. Keep the brand logo recognisable. '
                . 'Keep the referenced image centred and fully visible inside a safe middle square — '
                . 'nothing important near the edges, so a later 1:1 crop still shows the whole subject. '
                . 'Create a perfect loop with constant motion from start to end — no ease-in or ease-out. '
                . 'No audio.',
    ];
    // Preferences tests reuse the brand logo for both endpoint frames.
    $payload[$firstParam] = $logoImageUrl;
    $payload[$lastParam] = $logoImageUrl;

    $submit = bandpromo_create_integrations_http_json(
        'POST',
        $endpoint,
        [
            'Authorization' => 'Key ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        $payload,
        60
    );
    $submitDecoded = json_decode($submit['body'], true);
    if (!$submit['ok']) {
        $err = bandpromo_create_integrations_fal_error_payload($submit, $submitDecoded);
        $err['video_url'] = '';
        $err['logo_attached'] = true;
        $err['model'] = $model;
        $err['cost'] = $cost;
        unset($err['image_url']);

        return $err;
    }

    $requestId = is_array($submitDecoded) ? trim((string) ($submitDecoded['request_id'] ?? '')) : '';
    $statusUrl = is_array($submitDecoded) ? trim((string) ($submitDecoded['status_url'] ?? '')) : '';
    $responseUrl = is_array($submitDecoded) ? trim((string) ($submitDecoded['response_url'] ?? '')) : '';

    if ($requestId === '' && is_array($submitDecoded)) {
        $videoUrl = bandpromo_create_integrations_fal_extract_video_url($submitDecoded);
        if ($videoUrl !== '') {
            return [
                'ok' => true,
                'video_url' => $videoUrl,
                'request_id' => '',
                'error' => '',
                'status' => $submit['status'],
                'model' => $model,
                'logo_attached' => true,
                'cost' => $cost,
            ];
        }
    }

    $deadline = time() + 150;
    $lastStatus = $submit['status'];
    while ($statusUrl !== '' && time() < $deadline) {
        usleep(1500000);
        $poll = bandpromo_create_integrations_http_json('GET', $statusUrl, [
            'Authorization' => 'Key ' . $apiKey,
            'Accept' => 'application/json',
        ], null, 60);
        $pollDecoded = json_decode($poll['body'], true);
        $lastStatus = $poll['status'];
        if (!$poll['ok']) {
            $err = bandpromo_create_integrations_fal_error_payload($poll, $pollDecoded, $requestId);
            $err['video_url'] = '';
            $err['logo_attached'] = true;
            $err['model'] = $model;
            $err['cost'] = $cost;
            unset($err['image_url']);

            return $err;
        }
        $status = is_array($pollDecoded) ? strtoupper(trim((string) ($pollDecoded['status'] ?? ''))) : '';
        if ($status === 'COMPLETED' || $status === 'OK') {
            if ($responseUrl === '' && is_array($pollDecoded)) {
                $responseUrl = trim((string) ($pollDecoded['response_url'] ?? ''));
            }
            break;
        }
        if (in_array($status, ['FAILED', 'ERROR', 'CANCELLED'], true)) {
            return [
                'ok' => false,
                'video_url' => '',
                'request_id' => $requestId,
                'error' => 'fal video job failed (' . $status . ').',
                'status' => $lastStatus,
                'model' => $model,
                'logo_attached' => true,
                'cost' => $cost,
            ];
        }
    }

    if ($responseUrl === '') {
        return [
            'ok' => false,
            'video_url' => '',
            'request_id' => $requestId,
            'error' => 'fal video job timed out without a result.',
            'status' => $lastStatus,
            'model' => $model,
            'logo_attached' => true,
            'cost' => $cost,
        ];
    }

    $result = bandpromo_create_integrations_http_json('GET', $responseUrl, [
        'Authorization' => 'Key ' . $apiKey,
        'Accept' => 'application/json',
    ], null, 60);
    $resultDecoded = json_decode($result['body'], true);
    if (!$result['ok']) {
        $err = bandpromo_create_integrations_fal_error_payload($result, $resultDecoded, $requestId);
        $err['video_url'] = '';
        $err['logo_attached'] = true;
        $err['model'] = $model;
        $err['cost'] = $cost;
        unset($err['image_url']);

        return $err;
    }

    $videoUrl = bandpromo_create_integrations_fal_extract_video_url(is_array($resultDecoded) ? $resultDecoded : []);
    if ($videoUrl === '') {
        return [
            'ok' => false,
            'video_url' => '',
            'request_id' => $requestId,
            'error' => 'fal returned no video URL.',
            'status' => $result['status'],
            'model' => $model,
            'logo_attached' => true,
            'cost' => $cost,
        ];
    }

    return [
        'ok' => true,
        'video_url' => $videoUrl,
        'request_id' => $requestId,
        'error' => '',
        'status' => $result['status'],
        'model' => $model,
        'logo_attached' => true,
        'cost' => $cost,
    ];
}

/**
 * @deprecated Prefer bandpromo_create_integrations_fal_image()
 */
function bandpromo_create_integrations_fal_nano_banana2(string $apiKey, string $prompt, string $logoImageUrl = ''): array
{
    return bandpromo_create_integrations_fal_image($apiKey, $prompt, $logoImageUrl, 'fal-ai/nano-banana-2/edit');
}


/**
 * @param array{ok:bool,status:int,body:string,error:string} $response
 * @param mixed $decoded
 * @return array{
 *   ok:bool,
 *   image_url:string,
 *   request_id:string,
 *   error:string,
 *   status:int,
 *   needs_setup?:bool,
 *   needs_credits?:bool,
 *   help?:string
 * }
 */
function bandpromo_create_integrations_fal_error_payload(array $response, $decoded, string $requestId = ''): array
{
    $message = '';
    $detail = '';
    if (is_array($decoded)) {
        if (is_string($decoded['detail'] ?? null)) {
            $detail = trim((string) $decoded['detail']);
        } elseif (is_array($decoded['detail'] ?? null)) {
            $detail = trim((string) json_encode($decoded['detail']));
        }
        $message = trim((string) ($decoded['error'] ?? $decoded['message'] ?? $detail));
    }
    if ($message === '') {
        $message = $response['error'] !== '' ? $response['error'] : 'fal request failed.';
    }

    $needsCredits = false;
    $needsSetup = in_array($response['status'], [401, 403], true);
    $lower = strtolower($message . ' ' . $detail);
    if ($response['status'] === 403 && (strpos($lower, 'exhausted balance') !== false || strpos($lower, 'user is locked') !== false)) {
        $needsCredits = strpos($lower, 'exhausted balance') !== false;
        $needsSetup = false;
    }

    $help = '';
    if ($needsCredits) {
        $help = 'The fal connection works, but this account needs credits. Add billing or credits at https://fal.ai/dashboard/billing';
    } elseif ($needsSetup) {
        $help = 'Check the fal.ai key under Create → Connect services. Create or rotate keys at https://fal.ai/dashboard/keys';
    }

    return [
        'ok' => false,
        'image_url' => '',
        'request_id' => $requestId,
        'error' => $message,
        'status' => $response['status'],
        'logo_attached' => false,
        'needs_setup' => $needsSetup,
        'needs_credits' => $needsCredits,
        'help' => $help,
    ];
}

/**
 * @param array<string, mixed> $decoded
 */
function bandpromo_create_integrations_fal_extract_video_url(array $decoded): string
{
    if (isset($decoded['video']) && is_array($decoded['video'])) {
        $url = trim((string) ($decoded['video']['url'] ?? ''));
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return $url;
        }
    }
    if (isset($decoded['videos']) && is_array($decoded['videos'])) {
        foreach ($decoded['videos'] as $video) {
            if (!is_array($video)) {
                continue;
            }
            $url = trim((string) ($video['url'] ?? ''));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
    }
    $direct = trim((string) ($decoded['video_url'] ?? $decoded['url'] ?? ''));
    if ($direct !== '' && preg_match('#^https?://#i', $direct)) {
        return $direct;
    }

    return '';
}

/**
 * @param array<string, mixed> $decoded
 */
function bandpromo_create_integrations_fal_extract_image_url(array $decoded): string
{
    if (isset($decoded['images']) && is_array($decoded['images'])) {
        foreach ($decoded['images'] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $url = trim((string) ($image['url'] ?? ''));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
    }

    $direct = trim((string) ($decoded['image']['url'] ?? $decoded['url'] ?? ''));
    if ($direct !== '' && preg_match('#^https?://#i', $direct)) {
        return $direct;
    }

    return '';
}
