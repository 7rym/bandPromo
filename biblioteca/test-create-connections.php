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
$openaiModel = bandpromo_create_integrations_normalize_openai_model(
    (string) ($payload['openai_model'] ?? $prefs['openai_model'] ?? '')
);
$falModel = bandpromo_create_integrations_normalize_fal_model(
    (string) ($payload['fal_model'] ?? $prefs['fal_model'] ?? '')
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
    'openai' => [
        'ok' => false,
        'configured' => $resolved['openai_api_key'] !== '',
        'source' => $resolved['openai_source'],
        'model' => $openaiModel,
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
        'model' => $falModel,
        'image_url' => '',
        'request_id' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
        'needs_credits' => false,
        'logo_attached' => false,
        'cost' => null,
    ],
];

$keyHelp = 'Add your OpenAI and fal.ai API keys under Connect on this page. '
    . 'OpenAI: https://platform.openai.com/api-keys — fal: https://fal.ai/dashboard/keys. '
    . 'Keys stay with your developer account on this install (and in a full site backup), not in campaign exports.';

if ($sessionStep === 'prompt') {
    if ($resolved['openai_api_key'] === '') {
        $result['openai']['needs_setup'] = true;
        $result['openai']['help'] = $keyHelp;
        $result['openai']['error'] = 'No OpenAI key recorded.';
        $result['error'] = 'No OpenAI key recorded.';
        $result['help'] = $keyHelp;
        http_response_code(400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $openai = bandpromo_create_integrations_openai_artwork_prompt(
        $resolved['openai_api_key'],
        (string) $brand['brief'],
        $openaiModel
    );
    $result['openai']['ok'] = !empty($openai['ok']);
    $result['openai']['prompt'] = (string) ($openai['prompt'] ?? '');
    $result['openai']['error'] = (string) ($openai['error'] ?? '');
    $result['openai']['help'] = (string) ($openai['help'] ?? '');
    $result['openai']['needs_setup'] = !empty($openai['needs_setup']);
    $result['openai']['model'] = (string) ($openai['model'] ?? $openaiModel);
    $result['openai']['cost'] = is_array($openai['cost'] ?? null) ? $openai['cost'] : null;
    $result['ok'] = $result['openai']['ok'];

    bandpromo_admin_audit_log('create_prompt_tested', [
        'target_type' => 'admin_integrations',
        'target_id' => bandpromo_create_integrations_sanitize_username($username),
        'status' => $result['ok'] ? 'ok' : 'error',
        'data' => [
            'openai_ok' => $result['openai']['ok'],
            'openai_model' => $result['openai']['model'],
            'brand_id' => $brand['brand_id'],
            'openai_needs_setup' => $result['openai']['needs_setup'],
        ],
    ]);

    if (!$result['ok']) {
        http_response_code(!empty($openai['needs_setup']) ? 400 : 502);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// step === image
if ($resolved['fal_api_key'] === '') {
    $result['fal']['needs_setup'] = true;
    $result['fal']['help'] = $keyHelp;
    $result['fal']['error'] = 'No fal.ai key recorded.';
    $result['error'] = 'No fal.ai key recorded.';
    $result['help'] = $keyHelp;
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

$result['openai']['ok'] = true;
$result['openai']['prompt'] = $prompt;

$fal = bandpromo_create_integrations_fal_image(
    $resolved['fal_api_key'],
    $prompt,
    $logoImageUrl,
    $falModel
);
$result['fal']['ok'] = !empty($fal['ok']);
$result['fal']['image_url'] = (string) ($fal['image_url'] ?? '');
$result['fal']['request_id'] = (string) ($fal['request_id'] ?? '');
$result['fal']['error'] = (string) ($fal['error'] ?? '');
$result['fal']['help'] = (string) ($fal['help'] ?? '');
$result['fal']['needs_setup'] = !empty($fal['needs_setup']);
$result['fal']['needs_credits'] = !empty($fal['needs_credits']);
$result['fal']['logo_attached'] = !empty($fal['logo_attached']);
$result['fal']['model'] = (string) ($fal['model'] ?? $falModel);
$result['fal']['cost'] = is_array($fal['cost'] ?? null) ? $fal['cost'] : null;
$result['ok'] = $result['fal']['ok'];

bandpromo_admin_audit_log('create_image_tested', [
    'target_type' => 'admin_integrations',
    'target_id' => bandpromo_create_integrations_sanitize_username($username),
    'status' => $result['ok'] ? 'ok' : 'error',
    'data' => [
        'fal_ok' => $result['fal']['ok'],
        'fal_model' => $result['fal']['model'],
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
