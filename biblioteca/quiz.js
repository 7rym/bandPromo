let quizState = {
    currentQuiz: null,
    questions: [],
    currentQuestion: 0,
    score: 0,
    answered: false,
    selectedAnswer: null,
    timeRemaining: 60,
    timerInterval: null,
    quizActive: false,
    userAnswers: [] // Phase 4: Track user answers for integrity verification
};

function loadQuiz(quizType = 'chronicles') {
    const quizBox = document.getElementById('quizBox');
    if (!quizBox) {
        return;
    }
    quizBox.innerHTML = '<div class="quiz-loading">Loading quiz...</div>';
    
    // Clear any existing timer
    if (quizState.timerInterval) {
        clearInterval(quizState.timerInterval);
    }
    
    fetch(`../biblioteca/quiz.php?type=${encodeURIComponent(quizType)}`)
        .then(response => {
            if (!response.ok) throw new Error('Failed to load quiz');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                quizBox.innerHTML = `<div class="quiz-error">${data.error}</div>`;
                return;
            }
            
            quizState.currentQuiz = quizType;
            quizState.questions = data.questions;
            // Shuffle questions randomly
            shuffleArray(quizState.questions);
            quizState.currentQuestion = 0;
            quizState.score = 0;
            quizState.answered = false;
            quizState.selectedAnswer = null;
            quizState.timeRemaining = 30;
            quizState.quizActive = true;
            quizState.userAnswers = []; // Reset for Phase 4
            
            // Log quiz started activity
            if (typeof logActivity !== 'undefined') {
                logActivity('quiz_started', {
                    quiz_type: quizType,
                    question_count: data.questions.length
                });
            }
            
            // Start timer
            startQuizTimer();
            
            renderQuestion();
        })
        .catch(error => {
            quizBox.innerHTML = `<div class="quiz-error">Error loading quiz: ${error.message}</div>`;
        });
}

function renderQuizStart() {
    const quizBox = document.getElementById('quizBox');
    
    // Clear any existing timer
    if (quizState.timerInterval) {
        clearInterval(quizState.timerInterval);
    }
    
    // Get quizzes from config (set in HTML), with fallback to default
    const quizzes = window.quizzesConfig || [
        { id: 'twisted', name: 'Twisted' },
        { id: 'chronicles', name: 'Chronicles' }
    ];
    
    // Build quiz buttons dynamically
    const quizButtonsHTML = quizzes.map(quiz => `
        <div class="quiz-button-group">
            <button class="quiz-select-btn" data-quiz="${quiz.id}">
                <span class="quiz-title">${quiz.name}</span>
            </button>
            <div class="quiz-highscores-col" id="${quiz.id}-scores">
                <h4>Top 10</h4>
                <div class="quiz-loading">Loading...</div>
            </div>
        </div>
    `).join('');
    
    quizBox.innerHTML = `
        <div class="quiz-start">
            <h2>Select quiz to start!</h2>
            <div class="quiz-buttons-wrapper">
                ${quizButtonsHTML}
            </div>
        </div>
    `;
    
    // Add event listeners to the buttons
    const quizButtons = quizBox.querySelectorAll('.quiz-select-btn');
    quizButtons.forEach(button => {
        button.addEventListener('click', function() {
            const quizType = this.getAttribute('data-quiz');
            loadQuiz(quizType);
        });
    });
    
    // Load highscores for each quiz
    quizzes.forEach(quiz => {
        loadQuizHighscores(quiz.id);
    });
}

function renderQuestion() {
    const quizBox = document.getElementById('quizBox');
    const question = quizState.questions[quizState.currentQuestion];
    
    let html = `
        <div class="quiz-container">
            <div class="quiz-timer">
                <div class="timer-display">${quizState.timeRemaining}s</div>
            </div>
            
            <h3 class="quiz-question">${escapeHtml(question.question)}</h3>
            
            <div class="quiz-options">
    `;
    
    question.options.forEach((option, index) => {
        const optionNumber = index + 1;
        const isCorrect = optionNumber === question.correct;
        let optionClass = 'quiz-option';
        
        if (quizState.answered) {
            if (quizState.selectedAnswer === index) {
                optionClass += isCorrect ? ' correct' : ' incorrect';
            } else if (isCorrect) {
                optionClass += ' correct';
            }
        }
        
        html += `
            <button class="${optionClass}" data-answer="${index}" ${quizState.answered ? 'disabled' : ''}>
                ${escapeHtml(option)}
            </button>
        `;
    });
    
    html += `
            </div>
    `;
    
    if (quizState.answered) {
        const isCorrect = (quizState.selectedAnswer + 1) === question.correct;
        html += `
            <div class="quiz-feedback ${isCorrect ? 'correct-feedback' : 'incorrect-feedback'}">
                ${isCorrect ? '✓ Correct!' : '✗ Incorrect'}
            </div>
        `;
    }
    
    html += `</div>`;
    quizBox.innerHTML = html;
    
    // Add event listeners to answer buttons
    const answerButtons = quizBox.querySelectorAll('.quiz-option');
    answerButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!quizState.answered) {
                const index = parseInt(this.getAttribute('data-answer'));
                selectAnswer(index);
            }
        });
    });
}

function selectAnswer(index) {
    if (quizState.answered || !quizState.quizActive) return;
    
    const question = quizState.questions[quizState.currentQuestion];
    quizState.selectedAnswer = index;
    quizState.answered = true;
    
    // Phase 4: Store user answer for integrity verification
    // Use question text as identifier (not index) since questions are shuffled
    quizState.userAnswers.push({
        question: question.question, // Use question text as unique identifier
        answer: index + 1, // Convert to 1-based (matching quizbase)
        correct: question.correct // Store for client-side feedback
    });
    
    const isCorrect = (index + 1) === question.correct;
    
    if (isCorrect) {
        quizState.score++;
        // Add 3 seconds for correct answer
        quizState.timeRemaining += 3;
        
        // Show green flash and next question
        flashCorrect();
    } else {
        // Show incorrect feedback for 2 seconds, then next question
        renderQuestion();
        setTimeout(() => {
            if (quizState.quizActive) {
                nextQuestion();
            }
        }, 2000);
    }
}

function flashCorrect() {
    const quizBox = document.getElementById('quizBox');
    if (!quizBox || !quizState.quizActive) return;
    
    // Add green flash class
    quizBox.classList.add('quiz-correct-flash');
    
    // Remove class and show next question after animation
    setTimeout(() => {
        if (quizState.quizActive) {
            quizBox.classList.remove('quiz-correct-flash');
            nextQuestion();
        }
    }, 600);
}

function nextQuestion() {
    if (!quizState.quizActive) return;
    
    quizState.currentQuestion++;
    quizState.answered = false;
    quizState.selectedAnswer = null;
    
    if (quizState.currentQuestion < quizState.questions.length) {
        renderQuestion();
    } else {
        // No more questions in array, but quiz continues with timer
        quizState.currentQuestion = Math.floor(Math.random() * quizState.questions.length);
        renderQuestion();
    }
}

function startQuizTimer() {
    if (quizState.timerInterval) {
        clearInterval(quizState.timerInterval);
    }
    
    quizState.timerInterval = setInterval(() => {
        quizState.timeRemaining--;
        
        // Update timer display
        const timerDisplay = document.querySelector('.timer-display');
        if (timerDisplay) {
            timerDisplay.textContent = quizState.timeRemaining + 's';
            
            // Change color based on time remaining
            if (quizState.timeRemaining <= 10) {
                timerDisplay.classList.add('timer-low');
            } else {
                timerDisplay.classList.remove('timer-low');
            }
        }
        
        // Time's up
        if (quizState.timeRemaining <= 0) {
            clearInterval(quizState.timerInterval);
            renderQuizEnd();
        }
    }, 1000);
}

function renderQuizEnd() {
    // Mark quiz as inactive
    quizState.quizActive = false;
    
    // Clear timer
    if (quizState.timerInterval) {
        clearInterval(quizState.timerInterval);
    }
    
    const quizBox = document.getElementById('quizBox');
    
    quizBox.innerHTML = `
        <div class="quiz-results">
            <h2>Quiz Complete!</h2>
            <div class="quiz-score">
                <div class="score-display">${quizState.score} points</div>
            </div>
            <div id="highscores-container" class="quiz-loading">Loading highscores...</div>
            <button class="quiz-action-btn" data-action="restart">Play Again</button>
        </div>
    `;
    
    // Add event listener to restart button
    const restartBtn = quizBox.querySelector('[data-action="restart"]');
    if (restartBtn) {
        restartBtn.addEventListener('click', function() {
            renderQuizStart();
        });
    }
    
    // Save score to backend
    saveScore();
}

function saveScore() {
    const scoreData = {
        quizType: quizState.currentQuiz,
        score: quizState.score,
        csrf_token: sessionStorage.getItem('csrf_token'), // Add CSRF token to request
        // Phase 4: Include user answers for integrity verification
        answers: quizState.userAnswers
    };
    
    // Log quiz completed activity
    if (typeof logActivity !== 'undefined') {
        logActivity('quiz_completed', {
            quiz_type: quizState.currentQuiz,
            score: quizState.score,
            total_questions_answered: quizState.userAnswers.length,
            correct_answers: quizState.score
        });
    }
    
    fetch('../biblioteca/save-score.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(scoreData)
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to save score');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayTopScores(data.topScores);
        }
    })
    .catch(error => {
        const container = document.getElementById('highscores-container');
        if (container) {
            container.innerHTML = `<div class="quiz-error">Error saving score</div>`;
        }
    });
}

function loadQuizHighscores(quizType) {
    // Use quizType directly as container ID (e.g., "twisted-scores", "chronicles-scores")
    const containerId = `${quizType}-scores`;
    const container = document.getElementById(containerId);
    
    if (!container) return;
    
    fetch(`../biblioteca/get-highscores.php?type=${encodeURIComponent(quizType)}&limit=10`)
        .then(response => {
            if (!response.ok) throw new Error('Failed to load scores');
            return response.json();
        })
        .then(data => {
            displayTopScoresInContainer(data.scores, container);
        })
        .catch(error => {
            container.innerHTML = '<div class="quiz-error">Failed to load scores</div>';
        });
}

function displayTopScores(scores) {
    const container = document.getElementById('highscores-container');
    if (!container) return;
    
    if (!scores || scores.length === 0) {
        container.innerHTML = '<div class="quiz-no-scores">No scores yet</div>';
        return;
    }
    
    let html = '<div class="quiz-highscores"><h4>Top 10 Scores</h4><ul>';
    
    scores.forEach((score, index) => {
        const medals = ['🥇', '🥈', '🥉'];
        const medal = medals[index] || '•';
        html += `
            <li class="highscore-entry">
                <span class="medal">${medal}</span>
                <span class="name">${escapeHtml(score.username)}</span>
                <span class="score">${score.score} points</span>
            </li>
        `;
    });
    
    html += '</ul></div>';
    container.innerHTML = html;
}

function displayTopScoresInContainer(scores, container) {
    if (!scores || scores.length === 0) {
        container.innerHTML = '<div class="quiz-no-scores">No scores yet</div>';
        return;
    }
    
    let html = '<ul class="highscore-list">';
    
    scores.forEach((score, index) => {
        const medals = ['🥇', '🥈', '🥉'];
        const medal = medals[index] || '•';
        html += `
            <li class="highscore-entry">
                <span class="medal">${medal}</span>
                <span class="name">${escapeHtml(score.username)}</span>
                <span class="score">${score.score}</span>
            </li>
        `;
    });
    
    html += '</ul>';
    container.innerHTML = html;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Fisher-Yates shuffle algorithm for randomizing questions
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}
