<?php
/**
 * Logging Service for bandPromo
 * Stores listener activity in SQLite (UTC).
 */

require_once __DIR__ . '/time-helpers.php';
require_once __DIR__ . '/activity-store.php';
require_once __DIR__ . '/auth.php';

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

    /**
     * @param array<int,array{activity:string,data?:array}> $events
     * @return array{accepted:int,rejected:int}
     */
    public function logBatch(array $events): array {
        $accepted = 0;
        $rejected = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                $rejected++;
                continue;
            }
            $activity = trim((string) ($event['activity'] ?? ''));
            if ($activity === '') {
                $rejected++;
                continue;
            }
            $data = is_array($event['data'] ?? null) ? $event['data'] : [];
            if ($this->log($activity, $data)) {
                $accepted++;
            } else {
                $rejected++;
            }
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
        ];
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

function bandpromo_log_rate_limit_allows(int $maxPerMinute = 180): bool
{
    $now = time();
    if (!isset($_SESSION['log_rate']) || !is_array($_SESSION['log_rate'])) {
        $_SESSION['log_rate'] = ['window' => $now, 'count' => 0];
    }

    if (($now - (int) ($_SESSION['log_rate']['window'] ?? 0)) >= 60) {
        $_SESSION['log_rate'] = ['window' => $now, 'count' => 0];
    }

    $_SESSION['log_rate']['count'] = (int) ($_SESSION['log_rate']['count'] ?? 0) + 1;

    return $_SESSION['log_rate']['count'] <= $maxPerMinute;
}

// API endpoint for logging activity from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log') {
    bandpromo_ensure_session_started();

    if (!bandpromo_is_authenticated_session()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if (!bandpromo_log_rate_limit_allows()) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many log events. Try again shortly.']);
        exit;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $logger = new UserActivityLogger();

    if (isset($input['events']) && is_array($input['events'])) {
        $events = array_values(array_filter($input['events'], 'is_array'));
        if (count($events) > 50) {
            http_response_code(400);
            echo json_encode(['error' => 'Batch too large (max 50 events)']);
            exit;
        }
        $result = $logger->logBatch($events);
        echo json_encode([
            'success' => $result['accepted'] > 0,
            'accepted' => $result['accepted'],
            'rejected' => $result['rejected'],
        ]);
        exit;
    }

    if (isset($input['activity'])) {
        $success = $logger->log($input['activity'], $input['data'] ?? []);
        echo json_encode(['success' => $success]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Missing activity parameter']);
    exit;
}
?>
