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

session_write_close();

// Long fal queue poll.
@set_time_limit(180);
@ignore_user_abort(true);

$root = dirname(__DIR__);
$resolved = bandpromo_create_integrations_resolve_keys($root, $username);
$brand = bandpromo_create_integrations_base_brand_brief($root);

$result = [
    'ok' => false,
    'brand' => [
        'id' => $brand['brand_id'],
        'title' => $brand['brand_title'],
        'used_fallback' => !empty($brand['used_fallback']),
        'filled_slots' => $brand['filled_slots'],
    ],
    'openai' => [
        'ok' => false,
        'configured' => $resolved['openai_api_key'] !== '',
        'source' => $resolved['openai_source'],
        'prompt' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
    ],
    'fal' => [
        'ok' => false,
        'configured' => $resolved['fal_api_key'] !== '',
        'source' => $resolved['fal_source'],
        'image_url' => '',
        'request_id' => '',
        'error' => '',
        'help' => '',
        'needs_setup' => false,
        'needs_credits' => false,
    ],
];

if ($resolved['openai_api_key'] === '' && $resolved['fal_api_key'] === '') {
    $help = 'Add your OpenAI and fal.ai API keys under Connect services on this page. '
        . 'OpenAI: https://platform.openai.com/api-keys — fal: https://fal.ai/dashboard/keys. '
        . 'Keys stay with your admin account on this install (and in a full site backup), not in campaign exports.';
    $result['openai']['needs_setup'] = true;
    $result['openai']['help'] = $help;
    $result['openai']['error'] = 'No OpenAI key recorded.';
    $result['fal']['needs_setup'] = true;
    $result['fal']['help'] = $help;
    $result['fal']['error'] = 'No fal.ai key recorded.';
    $result['error'] = 'No API keys recorded yet.';
    $result['help'] = $help;
    http_response_code(400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$openai = bandpromo_create_integrations_openai_artwork_prompt(
    $resolved['openai_api_key'],
    (string) $brand['brief']
);
$result['openai']['ok'] = !empty($openai['ok']);
$result['openai']['prompt'] = (string) ($openai['prompt'] ?? '');
$result['openai']['error'] = (string) ($openai['error'] ?? '');
$result['openai']['help'] = (string) ($openai['help'] ?? '');
$result['openai']['needs_setup'] = !empty($openai['needs_setup']);

if (!$result['openai']['ok']) {
    bandpromo_admin_audit_log('create_connections_tested', [
        'target_type' => 'admin_integrations',
        'target_id' => bandpromo_create_integrations_sanitize_username($username),
        'status' => 'error',
        'data' => [
            'openai_ok' => false,
            'fal_ok' => false,
            'brand_id' => $brand['brand_id'],
            'openai_needs_setup' => $result['openai']['needs_setup'],
        ],
    ]);
    http_response_code(!empty($openai['needs_setup']) ? 400 : 502);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$fal = bandpromo_create_integrations_fal_nano_banana2(
    $resolved['fal_api_key'],
    (string) $result['openai']['prompt']
);
$result['fal']['ok'] = !empty($fal['ok']);
$result['fal']['image_url'] = (string) ($fal['image_url'] ?? '');
$result['fal']['request_id'] = (string) ($fal['request_id'] ?? '');
$result['fal']['error'] = (string) ($fal['error'] ?? '');
$result['fal']['help'] = (string) ($fal['help'] ?? '');
$result['fal']['needs_setup'] = !empty($fal['needs_setup']);
$result['fal']['needs_credits'] = !empty($fal['needs_credits']);

$result['ok'] = $result['openai']['ok'] && $result['fal']['ok'];

bandpromo_admin_audit_log('create_connections_tested', [
    'target_type' => 'admin_integrations',
    'target_id' => bandpromo_create_integrations_sanitize_username($username),
    'status' => $result['ok'] ? 'ok' : 'error',
    'data' => [
        'openai_ok' => $result['openai']['ok'],
        'fal_ok' => $result['fal']['ok'],
        'brand_id' => $brand['brand_id'],
        'fal_request_id' => $result['fal']['request_id'] !== '' ? 'present' : '',
        'fal_needs_credits' => $result['fal']['needs_credits'],
        'fal_needs_setup' => $result['fal']['needs_setup'],
    ],
]);

if (!$result['ok']) {
    http_response_code(!empty($fal['needs_setup']) || !empty($fal['needs_credits']) ? 400 : 502);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
