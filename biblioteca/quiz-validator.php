<?php
/**
 * Quiz Answer Validation Helper
 * 
 * Verifies that submitted answers are actually valid quiz responses
 * and calculates the server-side score for integrity checking
 */

/**
 * Calculate correct score from user answers
 * 
 * @param string $quizType Type of quiz (e.g., 'chronicles', 'twisted')
 * @param array $userAnswers User's submitted answers 
 *              Format: [{ question: string, answer: int, correct: int }, ...]
 * @return array ['score' => int, 'correct' => int, 'total' => int]
 */
function calculate_quiz_score($quizType, $userAnswers) {
    // Load quiz base
    $quizFile = __DIR__ . '/quizbase-' . $quizType . '.json';
    
    if (!file_exists($quizFile)) {
        return ['error' => 'Quiz not found'];
    }
    
    $quizData = json_decode(file_get_contents($quizFile), true);
    
    if (!is_array($quizData)) {
        return ['error' => 'Invalid quiz data'];
    }
    
    $correct_answers = 0;
    
    // Build lookup map of question text -> correct answer from quizbase
    $question_map = [];
    foreach ($quizData as $question) {
        $question_text = isset($question['question']) ? $question['question'] : '';
        $correct_answer = isset($question['correct']) ? $question['correct'] : 0;
        $question_map[$question_text] = $correct_answer;
    }
    
    // Validate each user answer
    // Phase 4: Questions are identified by text (not index) to handle shuffling
    foreach ($userAnswers as $user_answer) {
        // Convert to array if it's an object
        $answer_data = (array)$user_answer;
        
        if (!isset($answer_data['question']) || !isset($answer_data['answer'])) {
            continue; // Skip malformed answers
        }
        
        $question_text = $answer_data['question'];
        $user_answer_value = intval($answer_data['answer']);
        
        // Find expected correct answer for this question
        if (!isset($question_map[$question_text])) {
            continue; // Question not found in quizbase
        }
        
        $correct_answer = $question_map[$question_text];
        
        // Compare answers (both 1-based)
        if ($user_answer_value === $correct_answer) {
            $correct_answers++;
        }
    }
    
    return [
        'score' => $correct_answers,
        'correct' => $correct_answers,
        'total' => count($quizData),
        'success' => true
    ];
}

/**
 * Verify that submitted score matches calculated score
 * This prevents tampering with score values
 * 
 * @param string $quizType Quiz type
 * @param array $userAnswers User answers
 * @param int $submittedScore Score submitted by user
 * @return bool True if scores match
 */
function verify_score_integrity($quizType, $userAnswers, $submittedScore) {
    $calculation = calculate_quiz_score($quizType, $userAnswers);
    
    // If error calculating, reject
    if (isset($calculation['error'])) {
        return false;
    }
    
    // Score must match calculation exactly
    return $calculation['score'] === $submittedScore;
}
?>
