<?php
/**
 * Secure Highscores API
 * 
 * Returns top scores from highscores.json only for authenticated users
 * Blocks direct browser access to raw JSON file
 * 
 * Security checks:
 * - Session authentication required
 * - HTTP header validation
 * - Quiz type validation
 */

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/quiz-input.php';
bandpromo_enforce_https();

session_start();

// Require authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check for direct browser access (reject with 403)
$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$is_browser_request = (
    strpos($accept, 'text/html') !== false && 
    strpos($accept, 'application/json') === false
);

if ($is_browser_request) {
    http_response_code(403);
    die('Access Forbidden');
}

// Get quiz type from query string
$quizType = isset($_GET['type']) ? bandpromo_sanitize_quiz_input($_GET['type']) : 'chronicles';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Validate limits
if ($limit > 100) $limit = 100;
if ($limit < 1) $limit = 10;

// Derive allowed quiz types from quizbase-*.json files that exist on disk
$allowed_types = array_map(
    fn($f) => substr(basename($f), strlen('quizbase-'), -strlen('.json')),
    glob(__DIR__ . '/quizbase-*.json') ?: []
);
if (!in_array($quizType, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quiz type']);
    exit;
}

// Path to highscores file
$scoresFile = __DIR__ . '/../data/highscores.json';

// Initialize highscores if file doesn't exist
if (!file_exists($scoresFile)) {
    $initialScores = [
        'chronicles' => [],
        'twisted' => []
    ];
    file_put_contents($scoresFile, json_encode($initialScores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Read current highscores
$highscores = json_decode(file_get_contents($scoresFile), true);

if (!is_array($highscores)) {
    $highscores = ['chronicles' => [], 'twisted' => []];
}

// Get scores for requested type
$scores = isset($highscores[$quizType]) ? $highscores[$quizType] : [];

// Sort by score descending and get top N
usort($scores, function($a, $b) {
    return $b['score'] - $a['score'];
});

$topScores = array_slice($scores, 0, $limit);

// Return scores as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'quizType' => $quizType,
    'scores' => $topScores,
    'count' => count($topScores)
]);
?>
