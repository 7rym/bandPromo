<?php
require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

session_start();

// Load CSRF protection
require_once __DIR__ . '/csrf.php';

// Load quiz validator for Phase 4 completion verification
require_once __DIR__ . '/quiz-validator.php';

// Load rate limiting for Phase 5
require_once __DIR__ . '/rate-limit.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['quizType']) || !isset($data['score'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$quizType = sanitize_input($data['quizType']);
$score = intval($data['score']);

// ========== PHASE 3: CSRF Token Validation ==========

// Validate CSRF token
$csrf_token = isset($data['csrf_token']) ? $data['csrf_token'] : '';
if (!validate_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
session_write_close(); // done reading session — release lock before file I/O

// =============================================

// ========== PHASE 4: Completion Verification ==========

// If answers are provided, verify score integrity server-side
if (isset($data['answers']) && is_array($data['answers'])) {
    $answers = $data['answers'];
    
    // Calculate score from user answers
    try {
        $calculation = calculate_quiz_score($quizType, $answers);
        $calculated_score = $calculation['score'];
        
        // Verify submitted score matches calculated score
        if ($score !== $calculated_score) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Score integrity check failed',
                'submitted' => $score,
                'expected' => $calculated_score
            ]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to verify score: ' . $e->getMessage()]);
        exit;
    }
}

// =============================================

// ========== PHASE 5: Rate Limiting ==========

// Check per-user submission rate limit
$user_rate_check = check_submission_rate_limit();
if (!$user_rate_check['allowed']) {
    http_response_code(429); // Too Many Requests
    echo json_encode([
        'error' => $user_rate_check['error'],
        'retry_after' => $user_rate_check['wait_seconds'],
        'reset_at' => $user_rate_check['reset_at']
    ]);
    exit;
}

// Check per-IP request rate limit
$ip_rate_check = check_ip_rate_limit();
if (!$ip_rate_check['allowed']) {
    http_response_code(429); // Too Many Requests
    echo json_encode([
        'error' => $ip_rate_check['error'],
        'retry_after' => $ip_rate_check['wait_seconds'],
        'reset_at' => $ip_rate_check['reset_at']
    ]);
    exit;
}

// =============================================

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';

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

// ========== PHASE 2: Score Validation ==========

// Load quiz to validate score
$quizFile = __DIR__ . '/quizbase-' . $quizType . '.json';
if (!file_exists($quizFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

$quizData = json_decode(file_get_contents($quizFile), true);
if (!is_array($quizData)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid quiz data']);
    exit;
}

// Calculate maximum possible score (1 point per question)
$maxScore = count($quizData);

// Validate score is non-negative
if ($score < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Score cannot be negative']);
    exit;
}

// Validate score doesn't exceed maximum
if ($score > $maxScore) {
    http_response_code(400);
    echo json_encode(['error' => 'Score exceeds maximum (' . $maxScore . ')']);
    exit;
}

// =============================================

// Path to highscores file
$scoresFile = __DIR__ . '/../data/highscores.json';

// Initialize highscores if file doesn't exist
if (!file_exists($scoresFile)) {
    $initialScores = array_fill_keys($allowed_types, []);
    file_put_contents($scoresFile, json_encode($initialScores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Read current highscores
$highscores = json_decode(file_get_contents($scoresFile), true);

if (!is_array($highscores)) {
    $highscores = array_fill_keys($allowed_types, []);
}

// Ensure quiz type exists in highscores
if (!isset($highscores[$quizType])) {
    $highscores[$quizType] = [];
}

// Create new score entry
$newScore = [
    'username' => $username,
    'score' => $score,
    'date' => date('c') // ISO 8601 format
];

// Add the new score
$highscores[$quizType][] = $newScore;

// Sort by score descending
usort($highscores[$quizType], function($a, $b) {
    return $b['score'] - $a['score'];
});

// Keep only top 100 (we'll limit to 10 when displaying)
$highscores[$quizType] = array_slice($highscores[$quizType], 0, 100);

// Save updated highscores
file_put_contents($scoresFile, json_encode($highscores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Return success with top 10 scores
$topScores = array_slice($highscores[$quizType], 0, 10);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Score saved successfully',
    'newScore' => $newScore,
    'topScores' => $topScores
]);

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
