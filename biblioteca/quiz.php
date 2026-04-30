<?php
require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

// Block direct browser access (must be API/JavaScript call)
$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$is_browser_request = (
    strpos($accept, 'text/html') !== false && 
    strpos($accept, 'application/json') === false
);

if ($is_browser_request) {
    http_response_code(403);
    die('Access Forbidden');
}

// Derive allowed quiz types from quizbase-*.json files that exist on disk
$allowed_types = array_map(
    fn($f) => substr(basename($f), strlen('quizbase-'), -strlen('.json')),
    glob(__DIR__ . '/quizbase-*.json') ?: []
);
if (empty($allowed_types)) $allowed_types = ['chronicles'];

$quizType = isset($_GET['type']) ? sanitize_input($_GET['type']) : $allowed_types[0];
if (!in_array($quizType, $allowed_types)) {
    $quizType = $allowed_types[0];
}

// Load quiz data
$quizFile = __DIR__ . '/quizbase-' . $quizType . '.json';

if (!file_exists($quizFile)) {
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

$quizData = json_decode(file_get_contents($quizFile), true);

if (!is_array($quizData)) {
    echo json_encode(['error' => 'Invalid quiz data']);
    exit;
}

// Remove correct answers from quiz data before sending to client
// Server will validate answers when score is submitted
// HOWEVER: Include correct for client-side feedback ONLY
// Validator.php will verify independently from quizbase for security
$safeQuizData = [];
foreach ($quizData as $question) {
    $safeQuestion = [
        'question' => $question['question'] ?? '',
        'options' => $question['options'] ?? [],
        'correct' => $question['correct'] ?? null  // Include for client-side feedback
        // Note: Server validates independently from quizbase, so client could fake this
        // but it only affects what the client sees, not the final score
    ];
    $safeQuizData[] = $safeQuestion;
}

// Return quiz data WITHOUT answers as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'quizType' => $quizType,
    'questions' => $safeQuizData,
    'totalQuestions' => count($safeQuizData)
]);

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
