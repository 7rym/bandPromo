<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/create-integrations.php';

bandpromo_enforce_https();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    $payload = [];
}

$csrfToken = (string) ($payload['csrf_token'] ?? '');
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$username = trim((string) ($_SESSION['username'] ?? ''));
if ($username === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isDeveloperUser($username)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Create is developer-only for now.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sessionStep = strtolower(trim((string) ($payload['step'] ?? 'prompt')));
if (!in_array($sessionStep, ['prompt', 'image', 'video', 'proof'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown step. Use prompt, image, video, or proof.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Start connection-proof only. Preferences tests must not flip setup_complete.
$recordSetup = !empty($payload['record_setup']);
$proofProvider = strtolower(trim((string) ($payload['provider'] ?? '')));

session_write_close();

@set_time_limit($sessionStep === 'prompt' ? 60 : ($sessionStep === 'proof' ? 240 : 180));
@ignore_user_abort(true);

$root = dirname(__DIR__);
$resolved = bandpromo_create_integrations_resolve_keys($root, $username);
$prefs = bandpromo_create_integrations_load($root, $username);
$llmTier = bandpromo_create_integrations_normalize_price_tier((string) ($prefs['llm_price_tier'] ?? 'cheap'));
$imageTier = bandpromo_create_integrations_normalize_price_tier((string) ($prefs['image_price_tier'] ?? 'cheap'));
$videoTier = bandpromo_create_integrations_normalize_price_tier((string) ($prefs['video_price_tier'] ?? 'cheap'));

$llmModel = bandpromo_create_integrations_normalize_llm_model(
    (string) ($payload['llm_model'] ?? $prefs['llm_model'] ?? ''),
    $llmTier
);
if (array_key_exists('openai_model', $payload) && !array_key_exists('llm_model', $payload)) {
    $llmModel = bandpromo_create_integrations_normalize_llm_model(
        'openai:' . trim((string) $payload['openai_model']),
        $llmTier
    );
}
$llmMeta = bandpromo_create_integrations_llm_model_meta($llmModel);
$llmRoute = is_array($llmMeta) ? (string) ($llmMeta['route'] ?? 'fal') : 'fal';
$providerModel = is_array($llmMeta) ? (string) ($llmMeta['provider_model'] ?? '') : '';

$imageModel = bandpromo_create_integrations_normalize_image_model(
    (string) ($payload['image_model'] ?? $payload['fal_model'] ?? $prefs['image_model'] ?? ''),
    $imageTier
);
$videoModel = bandpromo_create_integrations_normalize_video_model(
    (string) ($payload['video_model'] ?? $prefs['video_model'] ?? ''),
    $videoTier
);

$brand = bandpromo_create_integrations_base_brand_brief($root);
$logoImageUrl = trim((string) ($brand['logo_image_url'] ?? ''));
$logoAttached = $logoImageUrl !== '';

$result = [
    'ok' => false,
    'step' => $sessionStep,
    'brand' => [
        'id' => $brand['brand_id'],
        'title' => $brand['brand_title'],
        'used_fallback' => !empty($brand['used_fallback']),
        'filled_slots' => $brand['filled_slots'],
        'logo_available' => $logoAttached,
        'logo_web_path' => trim((string) ($brand['logo_web_path'] ?? '')),
    ],
    'llm' => [
        'ok' => false,
        'configured' => $llmRoute === 'openai'
            ? ($resolved['openai_api_key'] !== '')
            : ($resolved['fal_api_key'] !== ''),
        'route' => $llmRoute,
        'llm_model' => $llmModel,
        'model' => $providerModel,
        'prompt' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
        'cost' => null,
    ],
    'openai' => [
        'ok' => false,
        'configured' => $resolved['openai_api_key'] !== '',
        'source' => $resolved['openai_source'],
        'model' => $providerModel,
        'prompt' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
        'cost' => null,
    ],
    'fal' => [
        'ok' => false,
        'configured' => $resolved['fal_api_key'] !== '',
        'source' => $resolved['fal_source'],
        'model' => $sessionStep === 'video' ? $videoModel : $imageModel,
        'image_url' => '',
        'video_url' => '',
        'request_id' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
        'needs_credits' => false,
        'logo_attached' => false,
        'cost' => null,
    ],
    'integrations' => null,
];

$keyHelpOpenAi = 'Add an OpenAI API key under Create → Start (or set OPENAI_API_KEY in the install .env). '
    . 'Create or rotate keys at https://platform.openai.com/api-keys';
$keyHelpFal = 'Add a fal.ai API key under Create → Start (or set FAL_KEY in the install .env). '
    . 'Create or rotate keys at https://fal.ai/dashboard/keys';

if ($sessionStep === 'proof') {
    if (!in_array($proofProvider, ['openai', 'fal'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Proof provider must be openai or fal.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $proofModels = bandpromo_create_integrations_start_proof_models();
    $result['provider'] = $proofProvider;
    $result['proof'] = [
        'provider' => $proofProvider,
        'text' => [
            'ok' => false,
            'prompt' => '',
            'model' => '',
            'error' => '',
            'help' => '',
            'cost' => null,
        ],
        'image' => [
            'ok' => false,
            'image_url' => '',
            'model' => '',
            'error' => '',
            'help' => '',
            'logo_attached' => false,
            'cost' => null,
        ],
    ];

    if ($proofProvider === 'openai') {
        if ($resolved['openai_api_key'] === '') {
            $result['proof']['text']['error'] = 'No OpenAI key recorded.';
            $result['proof']['text']['help'] = $keyHelpOpenAi;
            $result['error'] = $result['proof']['text']['error'];
            $result['help'] = $keyHelpOpenAi;
            http_response_code(400);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $llmMeta = $proofModels['openai_llm'];
        $imageMeta = $proofModels['openai_image'];
        $llmId = (string) ($llmMeta['id'] ?? '');
        $imageNative = (string) ($imageMeta['native_model'] ?? bandpromo_create_integrations_openai_native_image_model((string) ($imageMeta['id'] ?? '')));

        $promptResult = bandpromo_create_integrations_artwork_prompt(
            $root,
            $username,
            (string) $brand['brief'],
            $llmId
        );
        $result['proof']['text']['ok'] = !empty($promptResult['ok']);
        $result['proof']['text']['prompt'] = (string) ($promptResult['prompt'] ?? '');
        $result['proof']['text']['model'] = (string) ($promptResult['model'] ?? ($llmMeta['provider_model'] ?? ''));
        $result['proof']['text']['error'] = (string) ($promptResult['error'] ?? '');
        $result['proof']['text']['help'] = (string) ($promptResult['help'] ?? '');
        $result['proof']['text']['cost'] = is_array($promptResult['cost'] ?? null) ? $promptResult['cost'] : null;
        $result['llm'] = array_merge($result['llm'], [
            'ok' => $result['proof']['text']['ok'],
            'prompt' => $result['proof']['text']['prompt'],
            'model' => $result['proof']['text']['model'],
            'llm_model' => $llmId,
            'route' => 'openai',
            'error' => $result['proof']['text']['error'],
            'help' => $result['proof']['text']['help'],
            'cost' => $result['proof']['text']['cost'],
        ]);

        if (!$result['proof']['text']['ok']) {
            $result['ok'] = false;
            $result['error'] = $result['proof']['text']['error'] !== ''
                ? $result['proof']['text']['error']
                : 'OpenAI text proof failed.';
            $result['help'] = $result['proof']['text']['help'];
            $result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);
            http_response_code(!empty($promptResult['needs_setup']) ? 400 : 502);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $imageResult = bandpromo_create_integrations_openai_image(
            $resolved['openai_api_key'],
            $result['proof']['text']['prompt'],
            $logoImageUrl,
            $imageNative
        );
        $result['proof']['image']['ok'] = !empty($imageResult['ok']);
        $result['proof']['image']['image_url'] = (string) ($imageResult['image_url'] ?? '');
        $result['proof']['image']['model'] = (string) ($imageResult['model'] ?? $imageNative);
        $result['proof']['image']['error'] = (string) ($imageResult['error'] ?? '');
        $result['proof']['image']['help'] = (string) ($imageResult['help'] ?? '');
        $result['proof']['image']['logo_attached'] = !empty($imageResult['logo_attached']);
        $result['proof']['image']['cost'] = is_array($imageResult['cost'] ?? null) ? $imageResult['cost'] : null;
        $result['ok'] = $result['proof']['image']['ok'];

        if ($result['ok'] && $recordSetup) {
            bandpromo_create_integrations_save($root, $username, [
                'openai_tested' => true,
            ]);
        }
        if ($result['ok']) {
            $atUtc = gmdate('Y-m-d\TH:i:s\Z');
            $media = bandpromo_create_integrations_store_result_media(
                $root,
                $username,
                'openai_proof_image',
                (string) ($result['proof']['image']['image_url'] ?? '')
            );
            if (!empty($media['ok'])) {
                $localUrl = bandpromo_create_integrations_result_media_url('openai_proof_image', $atUtc);
                $result['proof']['image']['image_url'] = $localUrl;
            }
            bandpromo_create_integrations_record_last_result($root, $username, 'openai_proof', [
                'ok' => true,
                'at_utc' => $atUtc,
                'brand_title' => (string) ($brand['brand_title'] ?? ''),
                'brand_id' => (string) ($brand['brand_id'] ?? ''),
                'prompt' => (string) ($result['proof']['text']['prompt'] ?? ''),
                'text_model' => (string) ($result['proof']['text']['model'] ?? ''),
                'image_model' => (string) ($result['proof']['image']['model'] ?? ''),
                'logo_attached' => !empty($result['proof']['image']['logo_attached']),
                'cost_label' => bandpromo_create_integrations_join_cost_labels(
                    $result['proof']['text']['cost'] ?? null,
                    $result['proof']['image']['cost'] ?? null
                ),
                'media_kind' => !empty($media['ok']) ? 'openai_proof_image' : '',
                'media_ext' => (string) ($media['media_ext'] ?? ''),
            ]);
        }
    } else {
        if ($resolved['fal_api_key'] === '') {
            $result['proof']['text']['error'] = 'No fal.ai key recorded.';
            $result['proof']['text']['help'] = $keyHelpFal;
            $result['error'] = $result['proof']['text']['error'];
            $result['help'] = $keyHelpFal;
            http_response_code(400);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $llmMeta = $proofModels['fal_llm'];
        $imageMeta = $proofModels['fal_image'];
        $llmId = (string) ($llmMeta['id'] ?? '');
        $imageId = (string) ($imageMeta['id'] ?? '');

        $promptResult = bandpromo_create_integrations_artwork_prompt(
            $root,
            $username,
            (string) $brand['brief'],
            $llmId
        );
        $result['proof']['text']['ok'] = !empty($promptResult['ok']);
        $result['proof']['text']['prompt'] = (string) ($promptResult['prompt'] ?? '');
        $result['proof']['text']['model'] = (string) ($promptResult['model'] ?? ($llmMeta['provider_model'] ?? ''));
        $result['proof']['text']['error'] = (string) ($promptResult['error'] ?? '');
        $result['proof']['text']['help'] = (string) ($promptResult['help'] ?? '');
        $result['proof']['text']['cost'] = is_array($promptResult['cost'] ?? null) ? $promptResult['cost'] : null;
        $result['llm'] = array_merge($result['llm'], [
            'ok' => $result['proof']['text']['ok'],
            'prompt' => $result['proof']['text']['prompt'],
            'model' => $result['proof']['text']['model'],
            'llm_model' => $llmId,
            'route' => 'fal',
            'error' => $result['proof']['text']['error'],
            'help' => $result['proof']['text']['help'],
            'cost' => $result['proof']['text']['cost'],
        ]);

        if (!$result['proof']['text']['ok']) {
            $result['ok'] = false;
            $result['error'] = $result['proof']['text']['error'] !== ''
                ? $result['proof']['text']['error']
                : 'fal text proof failed.';
            $result['help'] = $result['proof']['text']['help'];
            $result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);
            http_response_code(!empty($promptResult['needs_setup']) ? 400 : 502);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $imageResult = bandpromo_create_integrations_fal_image(
            $resolved['fal_api_key'],
            $result['proof']['text']['prompt'],
            $logoImageUrl,
            $imageId
        );
        $result['proof']['image']['ok'] = !empty($imageResult['ok']);
        $result['proof']['image']['image_url'] = (string) ($imageResult['image_url'] ?? '');
        $result['proof']['image']['model'] = (string) ($imageResult['model'] ?? $imageId);
        $result['proof']['image']['error'] = (string) ($imageResult['error'] ?? '');
        $result['proof']['image']['help'] = (string) ($imageResult['help'] ?? '');
        $result['proof']['image']['logo_attached'] = !empty($imageResult['logo_attached']);
        $result['proof']['image']['cost'] = is_array($imageResult['cost'] ?? null) ? $imageResult['cost'] : null;
        $result['fal'] = array_merge($result['fal'], [
            'ok' => $result['proof']['image']['ok'],
            'image_url' => $result['proof']['image']['image_url'],
            'model' => $result['proof']['image']['model'],
            'error' => $result['proof']['image']['error'],
            'help' => $result['proof']['image']['help'],
            'logo_attached' => $result['proof']['image']['logo_attached'],
            'cost' => $result['proof']['image']['cost'],
            'request_id' => (string) ($imageResult['request_id'] ?? ''),
        ]);
        $result['ok'] = $result['proof']['image']['ok'];

        if ($result['ok'] && $recordSetup) {
            bandpromo_create_integrations_save($root, $username, [
                'fal_tested' => true,
            ]);
        }
        if ($result['ok']) {
            $atUtc = gmdate('Y-m-d\TH:i:s\Z');
            $media = bandpromo_create_integrations_store_result_media(
                $root,
                $username,
                'fal_proof_image',
                (string) ($result['proof']['image']['image_url'] ?? '')
            );
            if (!empty($media['ok'])) {
                $localUrl = bandpromo_create_integrations_result_media_url('fal_proof_image', $atUtc);
                $result['proof']['image']['image_url'] = $localUrl;
                $result['fal']['image_url'] = $localUrl;
            }
            bandpromo_create_integrations_record_last_result($root, $username, 'fal_proof', [
                'ok' => true,
                'at_utc' => $atUtc,
                'brand_title' => (string) ($brand['brand_title'] ?? ''),
                'brand_id' => (string) ($brand['brand_id'] ?? ''),
                'prompt' => (string) ($result['proof']['text']['prompt'] ?? ''),
                'text_model' => (string) ($result['proof']['text']['model'] ?? ''),
                'image_model' => (string) ($result['proof']['image']['model'] ?? ''),
                'logo_attached' => !empty($result['proof']['image']['logo_attached']),
                'cost_label' => bandpromo_create_integrations_join_cost_labels(
                    $result['proof']['text']['cost'] ?? null,
                    $result['proof']['image']['cost'] ?? null
                ),
                'media_kind' => !empty($media['ok']) ? 'fal_proof_image' : '',
                'media_ext' => (string) ($media['media_ext'] ?? ''),
            ]);
        }
    }

    $result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);

    bandpromo_admin_audit_log('create_provider_proof', [
        'target_type' => 'admin_integrations',
        'target_id' => bandpromo_create_integrations_sanitize_username($username),
        'status' => $result['ok'] ? 'ok' : 'error',
        'data' => [
            'provider' => $proofProvider,
            'text_ok' => !empty($result['proof']['text']['ok']),
            'image_ok' => !empty($result['proof']['image']['ok']),
            'record_setup' => $recordSetup,
            'brand_id' => $brand['brand_id'],
        ],
    ]);

    if (!$result['ok']) {
        $result['error'] = (string) ($result['proof']['image']['error'] ?? 'Provider proof failed.');
        $result['help'] = (string) ($result['proof']['image']['help'] ?? '');
        http_response_code(502);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($sessionStep === 'prompt') {
    $needsKey = $llmRoute === 'openai'
        ? ($resolved['openai_api_key'] === '')
        : ($resolved['fal_api_key'] === '');
    if ($needsKey) {
        $help = $llmRoute === 'openai' ? $keyHelpOpenAi : $keyHelpFal;
        $error = $llmRoute === 'openai' ? 'No OpenAI key recorded.' : 'No fal.ai key recorded.';
        $result['llm']['needs_setup'] = true;
        $result['llm']['help'] = $help;
        $result['llm']['error'] = $error;
        $result['openai']['needs_setup'] = $llmRoute === 'openai';
        $result['openai']['help'] = $help;
        $result['openai']['error'] = $error;
        $result['error'] = $error;
        $result['help'] = $help;
        http_response_code(400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $promptResult = bandpromo_create_integrations_artwork_prompt(
        $root,
        $username,
        (string) $brand['brief'],
        $llmModel
    );
    $result['llm']['ok'] = !empty($promptResult['ok']);
    $result['llm']['prompt'] = (string) ($promptResult['prompt'] ?? '');
    $result['llm']['error'] = (string) ($promptResult['error'] ?? '');
    $result['llm']['help'] = (string) ($promptResult['help'] ?? '');
    $result['llm']['needs_setup'] = !empty($promptResult['needs_setup']);
    $result['llm']['model'] = (string) ($promptResult['model'] ?? $providerModel);
    $result['llm']['llm_model'] = (string) ($promptResult['llm_model'] ?? $llmModel);
    $result['llm']['route'] = (string) ($promptResult['route'] ?? $llmRoute);
    $result['llm']['cost'] = is_array($promptResult['cost'] ?? null) ? $promptResult['cost'] : null;

    $result['openai']['ok'] = $result['llm']['ok'] && $result['llm']['route'] === 'openai';
    $result['openai']['prompt'] = $result['llm']['prompt'];
    $result['openai']['error'] = $result['llm']['route'] === 'openai' ? $result['llm']['error'] : '';
    $result['openai']['help'] = $result['llm']['route'] === 'openai' ? $result['llm']['help'] : '';
    $result['openai']['needs_setup'] = $result['llm']['route'] === 'openai' && $result['llm']['needs_setup'];
    $result['openai']['model'] = $result['llm']['model'];
    $result['openai']['cost'] = $result['llm']['route'] === 'openai' ? $result['llm']['cost'] : null;

    $result['ok'] = $result['llm']['ok'];

    if ($result['ok'] && $recordSetup) {
        // Do not clear image_tested — re-running the prompt proof must not wipe a completed Start.
        bandpromo_create_integrations_save($root, $username, [
            'prompt_tested' => true,
        ]);
    }
    if ($result['ok'] && !$recordSetup) {
        bandpromo_create_integrations_record_last_result($root, $username, 'prefs_llm', [
            'ok' => true,
            'at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'brand_title' => (string) ($brand['brand_title'] ?? ''),
            'brand_id' => (string) ($brand['brand_id'] ?? ''),
            'prompt' => (string) ($result['llm']['prompt'] ?? ''),
            'text_model' => (string) ($result['llm']['model'] ?? ''),
            'cost_label' => bandpromo_create_integrations_join_cost_labels($result['llm']['cost'] ?? null),
        ]);
    }

    $result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);

    bandpromo_admin_audit_log('create_prompt_tested', [
        'target_type' => 'admin_integrations',
        'target_id' => bandpromo_create_integrations_sanitize_username($username),
        'status' => $result['ok'] ? 'ok' : 'error',
        'data' => [
            'llm_ok' => $result['llm']['ok'],
            'llm_route' => $result['llm']['route'],
            'llm_model' => $result['llm']['llm_model'],
            'provider_model' => $result['llm']['model'],
            'brand_id' => $brand['brand_id'],
            'record_setup' => $recordSetup,
            'needs_setup' => $result['llm']['needs_setup'],
        ],
    ]);

    if (!$result['ok']) {
        http_response_code(!empty($promptResult['needs_setup']) ? 400 : 502);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($sessionStep === 'image') {
    if ($resolved['fal_api_key'] === '') {
        $result['fal']['needs_setup'] = true;
        $result['fal']['help'] = $keyHelpFal;
        $result['fal']['error'] = 'No fal.ai key recorded.';
        $result['error'] = 'No fal.ai key recorded.';
        $result['help'] = $keyHelpFal;
        http_response_code(400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $prompt = trim((string) ($payload['prompt'] ?? ''));
    if ($prompt === '') {
        // Preferences image test can invent a short prompt from the brand when none is posted.
        $prompt = 'Square promotional still for '
            . trim((string) ($brand['brand_title'] ?? 'this brand'))
            . '. Keep the brand logo recognisable. Prefer mood, colour, and composition over readable text.';
    }

    $result['llm']['ok'] = true;
    $result['llm']['prompt'] = $prompt;
    $result['openai']['ok'] = true;
    $result['openai']['prompt'] = $prompt;

    $fal = bandpromo_create_integrations_fal_image(
        $resolved['fal_api_key'],
        $prompt,
        $logoImageUrl,
        $imageModel
    );
    $result['fal']['ok'] = !empty($fal['ok']);
    $result['fal']['image_url'] = (string) ($fal['image_url'] ?? '');
    $result['fal']['request_id'] = (string) ($fal['request_id'] ?? '');
    $result['fal']['error'] = (string) ($fal['error'] ?? '');
    $result['fal']['help'] = (string) ($fal['help'] ?? '');
    $result['fal']['needs_setup'] = !empty($fal['needs_setup']);
    $result['fal']['needs_credits'] = !empty($fal['needs_credits']);
    $result['fal']['logo_attached'] = !empty($fal['logo_attached']);
    $result['fal']['model'] = (string) ($fal['model'] ?? $imageModel);
    $result['fal']['cost'] = is_array($fal['cost'] ?? null) ? $fal['cost'] : null;
    $result['ok'] = $result['fal']['ok'];

    if ($result['ok'] && $recordSetup) {
        bandpromo_create_integrations_save($root, $username, [
            'image_tested' => true,
        ]);
    }
    if ($result['ok'] && !$recordSetup) {
        $atUtc = gmdate('Y-m-d\TH:i:s\Z');
        $media = bandpromo_create_integrations_store_result_media(
            $root,
            $username,
            'prefs_image',
            (string) ($result['fal']['image_url'] ?? '')
        );
        if (!empty($media['ok'])) {
            $localUrl = bandpromo_create_integrations_result_media_url('prefs_image', $atUtc);
            $result['fal']['image_url'] = $localUrl;
        }
        bandpromo_create_integrations_record_last_result($root, $username, 'prefs_image', [
            'ok' => true,
            'at_utc' => $atUtc,
            'brand_title' => (string) ($brand['brand_title'] ?? ''),
            'brand_id' => (string) ($brand['brand_id'] ?? ''),
            'prompt' => $prompt,
            'image_model' => (string) ($result['fal']['model'] ?? ''),
            'logo_attached' => !empty($result['fal']['logo_attached']),
            'cost_label' => bandpromo_create_integrations_join_cost_labels($result['fal']['cost'] ?? null),
            'media_kind' => !empty($media['ok']) ? 'prefs_image' : '',
            'media_ext' => (string) ($media['media_ext'] ?? ''),
        ]);
    }

    $result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);

    bandpromo_admin_audit_log('create_image_tested', [
        'target_type' => 'admin_integrations',
        'target_id' => bandpromo_create_integrations_sanitize_username($username),
        'status' => $result['ok'] ? 'ok' : 'error',
        'data' => [
            'fal_ok' => $result['fal']['ok'],
            'image_model' => $result['fal']['model'],
            'brand_id' => $brand['brand_id'],
            'record_setup' => $recordSetup,
            'fal_needs_credits' => $result['fal']['needs_credits'],
            'logo_attached' => $result['fal']['logo_attached'],
        ],
    ]);

    if (!$result['ok']) {
        http_response_code(!empty($fal['needs_setup']) || !empty($fal['needs_credits']) ? 400 : 502);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// step === video
if ($resolved['fal_api_key'] === '') {
    $result['fal']['needs_setup'] = true;
    $result['fal']['help'] = $keyHelpFal;
    $result['fal']['error'] = 'No fal.ai key recorded.';
    $result['error'] = 'No fal.ai key recorded.';
    $result['help'] = $keyHelpFal;
    http_response_code(400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$prompt = trim((string) ($payload['prompt'] ?? ''));
if ($prompt === '') {
    $prompt = 'Subtle living motion for promotional artwork. Keep the brand logo recognisable. '
        . 'Keep the referenced image centred and fully visible inside a safe middle square — '
        . 'nothing important near the edges, so a later 1:1 crop still shows the whole subject. '
        . 'Create a perfect loop with constant motion from start to end — no ease-in or ease-out. '
        . 'No audio.';
}

$fal = bandpromo_create_integrations_fal_video(
    $resolved['fal_api_key'],
    $prompt,
    $logoImageUrl,
    $videoModel
);
$result['fal']['ok'] = !empty($fal['ok']);
$result['fal']['video_url'] = (string) ($fal['video_url'] ?? '');
$result['fal']['request_id'] = (string) ($fal['request_id'] ?? '');
$result['fal']['error'] = (string) ($fal['error'] ?? '');
$result['fal']['help'] = (string) ($fal['help'] ?? '');
$result['fal']['needs_setup'] = !empty($fal['needs_setup']);
$result['fal']['needs_credits'] = !empty($fal['needs_credits']);
$result['fal']['logo_attached'] = !empty($fal['logo_attached']);
$result['fal']['model'] = (string) ($fal['model'] ?? $videoModel);
$result['fal']['cost'] = is_array($fal['cost'] ?? null) ? $fal['cost'] : null;
$result['ok'] = $result['fal']['ok'];

if ($result['ok']) {
    $atUtc = gmdate('Y-m-d\TH:i:s\Z');
    $media = bandpromo_create_integrations_store_result_media(
        $root,
        $username,
        'prefs_video',
        (string) ($result['fal']['video_url'] ?? '')
    );
    if (!empty($media['ok'])) {
        $localUrl = bandpromo_create_integrations_result_media_url('prefs_video', $atUtc);
        $result['fal']['video_url'] = $localUrl;
    }
    bandpromo_create_integrations_record_last_result($root, $username, 'prefs_video', [
        'ok' => true,
        'at_utc' => $atUtc,
        'brand_title' => (string) ($brand['brand_title'] ?? ''),
        'brand_id' => (string) ($brand['brand_id'] ?? ''),
        'prompt' => $prompt,
        'video_model' => (string) ($result['fal']['model'] ?? ''),
        'logo_attached' => !empty($result['fal']['logo_attached']),
        'cost_label' => bandpromo_create_integrations_join_cost_labels($result['fal']['cost'] ?? null),
        'media_kind' => !empty($media['ok']) ? 'prefs_video' : '',
        'media_ext' => (string) ($media['media_ext'] ?? ''),
    ]);
}

$result['integrations'] = bandpromo_create_integrations_status_payload($root, $username);

bandpromo_admin_audit_log('create_video_tested', [
    'target_type' => 'admin_integrations',
    'target_id' => bandpromo_create_integrations_sanitize_username($username),
    'status' => $result['ok'] ? 'ok' : 'error',
    'data' => [
        'fal_ok' => $result['fal']['ok'],
        'video_model' => $result['fal']['model'],
        'brand_id' => $brand['brand_id'],
        'logo_attached' => $result['fal']['logo_attached'],
    ],
]);

if (!$result['ok']) {
    http_response_code(!empty($fal['needs_setup']) || !empty($fal['needs_credits']) ? 400 : 502);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
