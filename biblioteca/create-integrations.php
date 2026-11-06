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

/**
 * @return array{openai_api_key:string,fal_api_key:string,openai_model:string,fal_model:string,updated_at_utc:string}
 */
function bandpromo_create_integrations_defaults(): array
{
    return [
        'openai_api_key' => '',
        'fal_api_key' => '',
        'openai_model' => bandpromo_create_integrations_default_openai_model(),
        'fal_model' => bandpromo_create_integrations_default_fal_model(),
        'updated_at_utc' => '',
    ];
}

/**
 * Curated OpenAI chat models for Create prompt tests (cheapest first).
 * Rates are USD per 1M tokens (standard tier) — used for operator cost hints.
 *
 * @return list<array{id:string,label:string,price_hint:string,input_per_mtok:float,output_per_mtok:float}>
 */
function bandpromo_create_integrations_openai_models(): array
{
    return [
        [
            'id' => 'gpt-6-luna',
            'label' => 'GPT-6 Luna',
            'price_hint' => '~$0.10 / $0.50 per 1M in/out',
            'input_per_mtok' => 0.10,
            'output_per_mtok' => 0.50,
        ],
        [
            'id' => 'gpt-6-sol',
            'label' => 'GPT-6 Sol',
            'price_hint' => '~$2.00 / $10.00 per 1M in/out',
            'input_per_mtok' => 2.00,
            'output_per_mtok' => 10.00,
        ],
        [
            'id' => 'gpt-6-astra',
            'label' => 'GPT-6 Astra',
            'price_hint' => '~$10.00 / $50.00 per 1M in/out',
            'input_per_mtok' => 10.00,
            'output_per_mtok' => 50.00,
        ],
    ];
}

/**
 * Curated fal edit models that accept a brand-logo reference (cheapest first).
 * One pick per major vendor, all served through fal.ai.
 *
 * @return list<array{id:string,label:string,vendor:string,price_hint:string,estimate_usd:float,supports_logo:bool,family:string}>
 */
function bandpromo_create_integrations_fal_models(): array
{
    return [
        [
            'id' => 'fal-ai/gpt-image-1.5/edit',
            'label' => 'OpenAI GPT Image 1.5',
            'vendor' => 'OpenAI',
            'price_hint' => '~$0.009 / image (low quality)',
            'estimate_usd' => 0.009,
            'supports_logo' => true,
            'family' => 'openai_gpt_image',
        ],
        [
            'id' => 'meta/muse-image/edit',
            'label' => 'Meta Muse',
            'vendor' => 'Meta',
            'price_hint' => '~$0.01 / image',
            'estimate_usd' => 0.01,
            'supports_logo' => true,
            'family' => 'meta_muse',
        ],
        [
            'id' => 'fal-ai/flux-2/flash/edit',
            'label' => 'FLUX 2 Flash',
            'vendor' => 'Flux',
            'price_hint' => '~$0.01 / edit (1 MP in + out)',
            'estimate_usd' => 0.01,
            'supports_logo' => true,
            'family' => 'flux_flash_edit',
        ],
        [
            'id' => 'xai/grok-imagine-image/edit',
            'label' => 'Grok Imagine',
            'vendor' => 'Grok',
            'price_hint' => '~$0.022 / image',
            'estimate_usd' => 0.022,
            'supports_logo' => true,
            'family' => 'grok_imagine',
        ],
        [
            'id' => 'fal-ai/qwen-image-edit',
            'label' => 'Qwen Image Edit',
            'vendor' => 'Alibaba',
            'price_hint' => '~$0.03 / MP',
            'estimate_usd' => 0.03,
            'supports_logo' => true,
            'family' => 'qwen_edit',
        ],
        [
            'id' => 'fal-ai/bytedance/seedream/v5/lite/edit',
            'label' => 'Seedream 5 Lite',
            'vendor' => 'ByteDance',
            'price_hint' => '~$0.035 / image',
            'estimate_usd' => 0.035,
            'supports_logo' => true,
            'family' => 'seedream_lite',
        ],
        [
            'id' => 'fal-ai/nano-banana-2/edit',
            'label' => 'Nano Banana 2',
            'vendor' => 'Google',
            'price_hint' => '~$0.06 at 0.5K',
            'estimate_usd' => 0.06,
            'supports_logo' => true,
            'family' => 'nano_banana_2_edit',
        ],
    ];
}

function bandpromo_create_integrations_default_openai_model(): string
{
    $models = bandpromo_create_integrations_openai_models();

    return (string) ($models[0]['id'] ?? 'gpt-6-luna');
}

function bandpromo_create_integrations_default_fal_model(): string
{
    $models = bandpromo_create_integrations_fal_models();

    return (string) ($models[0]['id'] ?? 'fal-ai/gpt-image-1.5/edit');
}

function bandpromo_create_integrations_normalize_openai_model(string $model): string
{
    $model = trim($model);
    foreach (bandpromo_create_integrations_openai_models() as $row) {
        if ($row['id'] === $model) {
            return $model;
        }
    }

    return bandpromo_create_integrations_default_openai_model();
}

function bandpromo_create_integrations_normalize_fal_model(string $model): string
{
    $model = trim($model);
    foreach (bandpromo_create_integrations_fal_models() as $row) {
        if ($row['id'] === $model) {
            return $model;
        }
    }

    return bandpromo_create_integrations_default_fal_model();
}

/**
 * @return array<string, mixed>|null
 */
function bandpromo_create_integrations_openai_model_meta(string $model): ?array
{
    $model = bandpromo_create_integrations_normalize_openai_model($model);
    foreach (bandpromo_create_integrations_openai_models() as $row) {
        if ($row['id'] === $model) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function bandpromo_create_integrations_fal_model_meta(string $model): ?array
{
    $model = bandpromo_create_integrations_normalize_fal_model($model);
    foreach (bandpromo_create_integrations_fal_models() as $row) {
        if ($row['id'] === $model) {
            return $row;
        }
    }

    return null;
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
    if ($meta) {
        $estimate = ($promptTokens / 1000000.0) * (float) $meta['input_per_mtok']
            + ($completionTokens / 1000000.0) * (float) $meta['output_per_mtok'];
    }
    $label = $totalTokens > 0
        ? sprintf('About $%s (%s tokens)', rtrim(rtrim(number_format($estimate, 6, '.', ''), '0'), '.'), number_format($totalTokens))
        : 'Cost unknown (no token usage returned)';

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
 * @return array{openai_api_key:string,fal_api_key:string,openai_model:string,fal_model:string,updated_at_utc:string}
 */
function bandpromo_create_integrations_normalize(array $decoded): array
{
    $out = bandpromo_create_integrations_defaults();
    $out['openai_api_key'] = trim((string) ($decoded['openai_api_key'] ?? ''));
    $out['fal_api_key'] = trim((string) ($decoded['fal_api_key'] ?? ''));
    $out['openai_model'] = bandpromo_create_integrations_normalize_openai_model(
        (string) ($decoded['openai_model'] ?? $out['openai_model'])
    );
    $out['fal_model'] = bandpromo_create_integrations_normalize_fal_model(
        (string) ($decoded['fal_model'] ?? $out['fal_model'])
    );
    $out['updated_at_utc'] = trim((string) ($decoded['updated_at_utc'] ?? ''));

    return $out;
}

/**
 * @return array{openai_api_key:string,fal_api_key:string,openai_model:string,fal_model:string,updated_at_utc:string}
 */
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
 * @param array{openai_api_key?:string,fal_api_key?:string,openai_model?:string,fal_model?:string} $patch
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
    }
    if (array_key_exists('fal_api_key', $patch)) {
        $current['fal_api_key'] = trim((string) $patch['fal_api_key']);
    }
    if (array_key_exists('openai_model', $patch)) {
        $current['openai_model'] = bandpromo_create_integrations_normalize_openai_model((string) $patch['openai_model']);
    }
    if (array_key_exists('fal_model', $patch)) {
        $current['fal_model'] = bandpromo_create_integrations_normalize_fal_model((string) $patch['fal_model']);
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
 * @return array{
 *   openai_configured:bool,
 *   fal_configured:bool,
 *   openai_preview:string,
 *   fal_preview:string,
 *   openai_source:string,
 *   fal_source:string,
 *   openai_model:string,
 *   fal_model:string,
 *   openai_models:list<array<string,mixed>>,
 *   fal_models:list<array<string,mixed>>,
 *   updated_at_utc:string
 * }
 */
function bandpromo_create_integrations_status_payload(string $root, string $username): array
{
    $stored = bandpromo_create_integrations_load($root, $username);
    $resolved = bandpromo_create_integrations_resolve_keys($root, $username);

    return [
        'openai_configured' => $resolved['openai_api_key'] !== '',
        'fal_configured' => $resolved['fal_api_key'] !== '',
        'openai_preview' => $resolved['openai_api_key'] !== ''
            ? bandpromo_create_integrations_mask_key($resolved['openai_api_key'])
            : '',
        'fal_preview' => $resolved['fal_api_key'] !== ''
            ? bandpromo_create_integrations_mask_key($resolved['fal_api_key'])
            : '',
        'openai_source' => $resolved['openai_source'],
        'fal_source' => $resolved['fal_source'],
        'openai_model' => bandpromo_create_integrations_normalize_openai_model((string) $stored['openai_model']),
        'fal_model' => bandpromo_create_integrations_normalize_fal_model((string) $stored['fal_model']),
        'openai_models' => bandpromo_create_integrations_openai_models(),
        'fal_models' => bandpromo_create_integrations_fal_models(),
        'updated_at_utc' => (string) $stored['updated_at_utc'],
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

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'bandPromo Create integrations',
        ];
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
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
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

    if ($family === 'qwen_edit') {
        // Classic Qwen edit takes a single image_url (not image_urls).
        $payload['image_url'] = $logoImageUrl;
        $payload['image_size'] = 'square_hd';
        $payload['output_format'] = 'png';
        $payload['enable_safety_checker'] = true;
        $payload['acceleration'] = 'regular';
    } else {
        $payload['image_urls'] = [$logoImageUrl];

        if ($family === 'openai_gpt_image') {
            $payload['image_size'] = '1024x1024';
            $payload['quality'] = 'low';
            $payload['input_fidelity'] = 'high';
            $payload['output_format'] = 'png';
        } elseif ($family === 'meta_muse') {
            $payload['aspect_ratio'] = '1:1';
            $payload['output_format'] = 'png';
        } elseif ($family === 'flux_flash_edit') {
            $payload['image_size'] = 'square_hd';
            $payload['output_format'] = 'png';
            $payload['enable_safety_checker'] = true;
        } elseif ($family === 'grok_imagine') {
            $payload['aspect_ratio'] = '1:1';
            $payload['resolution'] = '1k';
            $payload['output_format'] = 'png';
        } elseif ($family === 'seedream_lite') {
            $payload['image_size'] = 'square_hd';
            $payload['enable_safety_checker'] = true;
        } else {
            // Google Nano Banana 2 edit — cheapest Google tier for this test.
            $payload['aspect_ratio'] = '1:1';
            $payload['resolution'] = '0.5K';
            $payload['output_format'] = 'png';
            $payload['limit_generations'] = true;
            $payload['system_prompt'] = 'Use the brand logo reference image. Keep the logo recognisable while following the prompt.';
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
