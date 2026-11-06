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
if (!in_array($sessionStep, ['prompt', 'image'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown step. Use prompt or image.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

session_write_close();

@set_time_limit($sessionStep === 'image' ? 180 : 60);
@ignore_user_abort(true);

$root = dirname(__DIR__);
$resolved = bandpromo_create_integrations_resolve_keys($root, $username);
$prefs = bandpromo_create_integrations_load($root, $username);
$tier = bandpromo_create_integrations_normalize_price_tier((string) ($prefs['price_tier'] ?? 'cheap'));

$llmModel = bandpromo_create_integrations_normalize_llm_model(
    (string) ($payload['llm_model'] ?? $prefs['llm_model'] ?? ''),
    $tier
);
if (array_key_exists('openai_model', $payload) && !array_key_exists('llm_model', $payload)) {
    $llmModel = bandpromo_create_integrations_normalize_llm_model(
        'openai:' . trim((string) $payload['openai_model']),
        $tier
    );
}
$llmMeta = bandpromo_create_integrations_llm_model_meta($llmModel);
$llmRoute = is_array($llmMeta) ? (string) ($llmMeta['route'] ?? 'fal') : 'fal';
$providerModel = is_array($llmMeta) ? (string) ($llmMeta['provider_model'] ?? '') : '';

$imageModel = bandpromo_create_integrations_normalize_image_model(
    (string) ($payload['image_model'] ?? $payload['fal_model'] ?? $prefs['image_model'] ?? ''),
    $tier
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
        'model' => $imageModel,
        'image_url' => '',
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

    if ($result['ok']) {
        // Fresh prompt invalidates the prior image proof.
        bandpromo_create_integrations_save($root, $username, [
            'llm_model' => $llmModel,
            'prompt_tested' => true,
            'image_tested' => false,
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
            'needs_setup' => $result['llm']['needs_setup'],
        ],
    ]);

    if (!$result['ok']) {
        http_response_code(!empty($promptResult['needs_setup']) ? 400 : 502);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// step === image
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
    $result['fal']['error'] = 'Create an image prompt first.';
    $result['error'] = 'Create an image prompt first.';
    http_response_code(400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

if ($result['ok']) {
    bandpromo_create_integrations_save($root, $username, [
        'image_model' => $imageModel,
        'image_tested' => true,
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
        'fal_request_id' => $result['fal']['request_id'] !== '' ? 'present' : '',
        'fal_needs_credits' => $result['fal']['needs_credits'],
        'fal_needs_setup' => $result['fal']['needs_setup'],
        'logo_attached' => $result['fal']['logo_attached'],
    ],
]);

if (!$result['ok']) {
    http_response_code(!empty($fal['needs_setup']) || !empty($fal['needs_credits']) ? 400 : 502);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
