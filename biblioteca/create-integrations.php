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
 * @return array{openai_api_key:string,fal_api_key:string,updated_at_utc:string}
 */
function bandpromo_create_integrations_defaults(): array
{
    return [
        'openai_api_key' => '',
        'fal_api_key' => '',
        'updated_at_utc' => '',
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
 * @return array{openai_api_key:string,fal_api_key:string,updated_at_utc:string}
 */
function bandpromo_create_integrations_normalize(array $decoded): array
{
    $out = bandpromo_create_integrations_defaults();
    $out['openai_api_key'] = trim((string) ($decoded['openai_api_key'] ?? ''));
    $out['fal_api_key'] = trim((string) ($decoded['fal_api_key'] ?? ''));
    $out['updated_at_utc'] = trim((string) ($decoded['updated_at_utc'] ?? ''));

    return $out;
}

/**
 * @return array{openai_api_key:string,fal_api_key:string,updated_at_utc:string}
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
 * @param array{openai_api_key?:string,fal_api_key?:string} $patch
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
 * @return array{ok:bool,prompt:string,error:string,status:int,needs_setup?:bool,help?:string}
 */
function bandpromo_create_integrations_openai_artwork_prompt(string $apiKey, string $brandBrief): array
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'OpenAI API key is missing.',
            'status' => 0,
            'needs_setup' => true,
            'help' => 'Add an OpenAI API key under Create → Connect services (or set OPENAI_API_KEY in the install .env). Create a key at https://platform.openai.com/api-keys',
        ];
    }

    $system = 'You write concise image-generation prompts for music promotional artwork. '
        . 'Reply with one plain prompt only — no markdown, no quotes, no preamble. '
        . 'UK English spelling is fine. Keep it under 80 words. '
        . 'Describe a square promotional still that fits the brand brief. '
        . 'When a brand logo reference is available, say the logo should appear prominently and stay recognisable.';
    $user = "Write an artwork prompt for this base brand:\n\n" . $brandBrief;

    $response = bandpromo_create_integrations_http_json(
        'POST',
        'https://api.openai.com/v1/chat/completions',
        [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.8,
            'max_tokens' => 180,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ],
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
            'needs_setup' => $needsSetup,
            'help' => $needsSetup
                ? 'Check the OpenAI key under Create → Connect services. Create or rotate keys at https://platform.openai.com/api-keys'
                : '',
        ];
    }

    $prompt = '';
    if (is_array($decoded)) {
        $prompt = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
    }
    $prompt = trim($prompt, " \t\n\r\0\x0B\"'");
    if ($prompt === '') {
        return [
            'ok' => false,
            'prompt' => '',
            'error' => 'OpenAI returned an empty prompt.',
            'status' => $response['status'],
        ];
    }

    return [
        'ok' => true,
        'prompt' => $prompt,
        'error' => '',
        'status' => $response['status'],
    ];
}

/**
 * Queue submit + poll for fal-ai/nano-banana-2. Does not resubmit on ambiguity.
 *
 * @return array{
 *   ok:bool,
 *   image_url:string,
 *   request_id:string,
 *   error:string,
 *   status:int,
 *   logo_attached:bool,
 *   needs_setup?:bool,
 *   needs_credits?:bool,
 *   help?:string
 * }
 */
function bandpromo_create_integrations_fal_nano_banana2(string $apiKey, string $prompt, string $logoImageUrl = ''): array
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => '',
            'error' => 'fal.ai API key is missing.',
            'status' => 0,
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
            'logo_attached' => false,
        ];
    }

    $authHeaders = [
        'Authorization' => 'Key ' . $apiKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];

    $logoImageUrl = trim($logoImageUrl);
    $logoAttached = $logoImageUrl !== ''
        && (preg_match('#^https?://#i', $logoImageUrl) === 1 || preg_match('#^data:image/#i', $logoImageUrl) === 1);

    $payload = [
        'prompt' => $prompt,
        'num_images' => 1,
        'resolution' => '0.5K',
        'aspect_ratio' => '1:1',
        'output_format' => 'png',
        'limit_generations' => true,
    ];
    if ($logoAttached) {
        $payload['context'] = [
            [
                'type' => 'image',
                'image_url' => $logoImageUrl,
            ],
            [
                'type' => 'text',
                'text' => 'This is the brand logo. Keep it recognisable and use it as a primary visual reference in the artwork.',
            ],
        ];
        $payload['system_prompt'] = 'Use the attached brand logo as a key visual reference. Preserve recognisable logo identity while following the prompt.';
    }

    $submit = bandpromo_create_integrations_http_json(
        'POST',
        'https://queue.fal.run/fal-ai/nano-banana-2',
        $authHeaders,
        $payload,
        60
    );

    $submitDecoded = json_decode($submit['body'], true);
    if (!$submit['ok']) {
        $err = bandpromo_create_integrations_fal_error_payload($submit, $submitDecoded);
        $err['logo_attached'] = $logoAttached;

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
                'logo_attached' => $logoAttached,
            ];
        }

        return [
            'ok' => false,
            'image_url' => '',
            'request_id' => $requestId,
            'error' => 'fal queue response did not include a usable status URL.',
            'status' => $submit['status'],
            'logo_attached' => $logoAttached,
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
                'logo_attached' => $logoAttached,
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
            'logo_attached' => $logoAttached,
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
            'logo_attached' => $logoAttached,
        ];
    }

    return [
        'ok' => true,
        'image_url' => $imageUrl,
        'request_id' => $requestId,
        'error' => '',
        'status' => $result['status'],
        'logo_attached' => $logoAttached,
    ];
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
