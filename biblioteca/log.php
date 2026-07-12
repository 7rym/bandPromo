<?php
/**
 * Logging Service for bandPromo
 * Stores listener activity in SQLite (UTC).
 */

require_once __DIR__ . '/time-helpers.php';
require_once __DIR__ . '/activity-store.php';

class UserActivityLogger {
    private $root;
    private $username;

    public function __construct($logDir = null, $username = null) {
        $this->root = dirname(__DIR__);
        if ($logDir !== null) {
            $candidate = realpath($logDir);
            if ($candidate !== false) {
                $this->root = dirname($candidate);
            }
        }
        $this->username = $username ?? (isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown');
    }

    /**
     * Log a user activity
     *
     * @param string $activity Activity type (e.g., 'play_start', 'track_exited')
     * @param array $data Additional data to log
     */
    public function log($activity, $data = []) {
        try {
            return bandpromo_activity_store_append_listener($this->root, [
                'timestamp' => bandpromo_utc_now_iso(),
                'timestamp_unix' => bandpromo_utc_now_unix(),
                'username' => htmlspecialchars($this->username),
                'activity' => htmlspecialchars($activity),
                'ip' => $this->getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            error_log('Logging error: ' . $e->getMessage());
            return false;
        }
    }

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
}

// API endpoint for logging activity from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log') {
    session_start();

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
