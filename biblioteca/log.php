<?php
/**
 * Logging Service for Twisted Chronicles
 * Logs user activity including track plays, song changes, and timestamps
 */

class UserActivityLogger {
    private $logDir;
    private $username;
    
    public function __construct($logDir = null, $username = null) {
        if ($logDir === null) {
            $logDir = dirname(__DIR__) . '/log';
        }
        $this->logDir = $logDir;
        $this->username = $username ?? (isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown');
        
        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log a user activity
     * @param string $activity Activity type (e.g., 'play_start', 'play_change', 'play_pause')
     * @param array $data Additional data to log
     * @return bool Success
     */
    public function log($activity, $data = []) {
        try {
            // Get today's log file
            $logFile = $this->logDir . '/' . date('Y-m-d') . '.log';
            
            // Build log entry
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'timestamp_unix' => time(),
                'username' => htmlspecialchars($this->username),
                'activity' => htmlspecialchars($activity),
                'ip' => $this->getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
                'data' => $data
            ];
            
            // Append to log file as JSON line
            $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
            
            return true;
        } catch (Exception $e) {
            error_log('Logging error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip = 'unknown';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    
    /**
     * Get log entries for a specific date or date range
     */
    public static function getLogEntries($logDir = null, $date = null, $username = null) {
        if ($logDir === null) {
            $logDir = dirname(__DIR__) . '/log';
        }
        
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $logFile = $logDir . '/' . $date . '.log';
        $entries = [];
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && ($username === null || $entry['username'] === $username)) {
                    $entries[] = $entry;
                }
            }
        }
        
        return $entries;
    }
}

// API endpoint for logging activity from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log') {
    session_start();
    
    // Verify user is authenticated
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $logger = new UserActivityLogger();
    
    if (isset($input['activity'])) {
        $success = $logger->log($input['activity'], $input['data'] ?? []);
        echo json_encode(['success' => $success]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing activity parameter']);
    }
    exit;
}
?>
