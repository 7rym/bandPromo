<?php
/**
 * Analytics Engine for bandPromo
 * Reads listener activity from SQLite via activity-store.php
 */

require_once __DIR__ . '/time-helpers.php';
require_once __DIR__ . '/activity-store.php';

class PlaybackAnalytics {
    private $root;
    private $entryCache = [];

    public function __construct($logDir = null) {
        $this->root = dirname(__DIR__);
        if ($logDir !== null) {
            $candidate = realpath($logDir);
            if ($candidate !== false) {
                $this->root = dirname($candidate);
            }
        }
    }

    private function isTrackExitEvent($entry) {
        return $entry['activity'] === 'track_exited'
            || in_array($entry['activity'], ['track_ended', 'track_change_next', 'track_change_prev', 'track_interrupted'], true);
    }

    private function isTrackProgressEvent($entry) {
        if ($this->isTrackExitEvent($entry)) {
            return true;
        }

        return $entry['activity'] === 'session_end'
            && !empty($entry['data']['track_title'])
            && !empty($entry['data']['current_time']);
    }

    private function getMeaningfulListeningSeconds($entry) {
        if (!$this->isTrackProgressEvent($entry)) {
            return 0;
        }

        if ((int)($entry['data']['completion_rate'] ?? 0) < 5) {
            return 0;
        }

        return (int)($entry['data']['current_time'] ?? 0);
    }

    private function getTrackExitReason($entry) {
        if ($entry['activity'] === 'track_exited') {
            return $entry['data']['exit_reason'] ?? null;
        }

        return match ($entry['activity']) {
            'track_ended' => 'ended',
            'track_change_next' => 'next_click',
            'track_change_prev' => 'prev_click',
            'track_interrupted' => 'playlist_select',
            default => null,
        };
    }

    /**
     * @param array<string,int> $counters
     */
    private function incrementCounter(array &$counters, string $key): void {
        $counters[$key] = (int)($counters[$key] ?? 0) + 1;
    }

    /**
     * @param array<string,array{original:int,lq:int}> $byDevice
     */
    private function ensureQualityDeviceBucket(array &$byDevice, string $device): void {
        if (!isset($byDevice[$device])) {
            $byDevice[$device] = ['original' => 0, 'lq' => 0];
        }
    }
    
    /**
     * Get listening time for a user (in seconds)
     */
    public function getUserListeningTime($username, $dateStart = null, $dateEnd = null) {
        $entries = $this->getLogEntries($dateStart, $dateEnd, $username);
        $listeningTime = 0;
        
        foreach ($entries as $entry) {
            $listeningTime += $this->getMeaningfulListeningSeconds($entry);
        }
        
        return $listeningTime;
    }
    
    /**
     * Get all users with their listening times (sorted)
     */
    public function getUsersListeningStats($dateStart = null, $dateEnd = null, $limit = 100) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if ($dateEnd === null) {
            $dateEnd = gmdate('Y-m-d');
        }

        try {
            $users = bandpromo_activity_store_users_listening_stats($this->root, $dateStart, $dateEnd, $limit);
            if ($users !== []) {
                return $users;
            }
        } catch (Throwable $e) {
            error_log('bandPromo analytics user rollup read error: ' . $e->getMessage());
        }

        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $users = [];
        
        foreach ($entries as $entry) {
            $username = $entry['username'];
            if (!isset($users[$username])) {
                $entryUnix = bandpromo_entry_unix_timestamp($entry);
                $users[$username] = [
                    'username' => $username,
                    'listening_time' => 0,
                    'play_count' => 0,
                    'sessions' => 0,
                    'last_activity_unix' => $entryUnix,
                    'first_activity_unix' => $entryUnix,
                    'devices' => []
                ];
            }

            $entryUnix = bandpromo_entry_unix_timestamp($entry);
            if ($entryUnix > 0) {
                if ($entryUnix >= (int) ($users[$username]['last_activity_unix'] ?? 0)) {
                    $users[$username]['last_activity_unix'] = $entryUnix;
                }
                if ($entryUnix <= (int) ($users[$username]['first_activity_unix'] ?? $entryUnix)) {
                    $users[$username]['first_activity_unix'] = $entryUnix;
                }
            }
            
            // Count plays
            if ($entry['activity'] === 'play_start') {
                $users[$username]['sessions']++;
            }
            
            // Count plays
            if ($entry['activity'] === 'track_started') {
                $users[$username]['play_count']++;
            }
            
            // Accumulate actual listening time from end events (>= 5% completion to exclude accidental taps)
            $users[$username]['listening_time'] += $this->getMeaningfulListeningSeconds($entry);
            
            // Track devices
            if (!empty($entry['user_agent'])) {
                $device = $this->getDeviceType($entry['user_agent']);
                /** @var array<string,int> $deviceCounts */
                $deviceCounts = $users[$username]['devices'];
                $this->incrementCounter($deviceCounts, $device);
                $users[$username]['devices'] = $deviceCounts;
            }
        }
        
        // Sort by listening time
        usort($users, function($a, $b) {
            return $b['listening_time'] - $a['listening_time'];
        });

        foreach ($users as &$user) {
            $user['last_activity'] = bandpromo_admin_format_timestamp((int) ($user['last_activity_unix'] ?? 0));
            $user['first_activity'] = bandpromo_admin_format_timestamp((int) ($user['first_activity_unix'] ?? 0));
            unset($user['last_activity_unix'], $user['first_activity_unix']);
        }
        unset($user);
        
        return array_slice($users, 0, $limit);
    }
    
    /**
     * Get most played tracks
     */
    public function getTopTracks($dateStart = null, $dateEnd = null, $limit = 50) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if ($dateEnd === null) {
            $dateEnd = gmdate('Y-m-d');
        }

        try {
            $tracks = bandpromo_activity_store_top_tracks($this->root, $dateStart, $dateEnd, $limit);
            if ($tracks !== []) {
                return $tracks;
            }
        } catch (Throwable $e) {
            error_log('bandPromo analytics track rollup read error: ' . $e->getMessage());
        }

        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $tracks = [];
        
        
        foreach ($entries as $entry) {
            if ($this->isTrackProgressEvent($entry) && isset($entry['data']['track_title'])) {
                // Ignore accidental plays under 5% completion
                if ((int)($entry['data']['completion_rate'] ?? 0) < 5) continue;
                
                $key = $this->normalizeTrackKey($entry['data']['track_title'], $entry['data']['track_artist'] ?? '');
                
                if (!isset($tracks[$key])) {
                    $tracks[$key] = [
                        'title' => $entry['data']['track_title'],
                        'artist' => $entry['data']['track_artist'] ?? 'Unknown',
                        'play_count' => 0,
                        'total_time' => 0,
                        'unique_users' => [],
                        'avg_completion' => 0
                    ];
                }
                
                $tracks[$key]['play_count']++;
                $tracks[$key]['total_time'] += (int)($entry['data']['current_time'] ?? 0);
                $tracks[$key]['unique_users'][$entry['username']] = true;
            }
        }
        
        // Add unique user count and sort
        foreach ($tracks as &$track) {
            $track['unique_users'] = count($track['unique_users']);
            $track['avg_time'] = $track['play_count'] > 0 ? round($track['total_time'] / $track['play_count'], 1) : 0;
        }
        
        usort($tracks, function($a, $b) {
            return $b['play_count'] - $a['play_count'];
        });
        
        return array_slice($tracks, 0, $limit);
    }
    
    /**
     * Get most played artists
     */
    public function getTopArtists($dateStart = null, $dateEnd = null, $limit = 50) {
        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $artists = [];
        
        foreach ($entries as $entry) {
            if ($entry['activity'] === 'track_started' && isset($entry['data']['track_artist'])) {
                $artist = $entry['data']['track_artist'];
                
                if (!isset($artists[$artist])) {
                    $artists[$artist] = [
                        'name' => $artist,
                        'play_count' => 0,
                        'total_time' => 0,
                        'unique_users' => [],
                        'tracks' => []
                    ];
                }
                
                $artists[$artist]['play_count']++;
                $artists[$artist]['total_time'] += (int)($entry['data']['duration'] ?? 0);
                $artists[$artist]['unique_users'][$entry['username']] = true;
                
                $track = $entry['data']['track_title'] ?? 'Unknown';
                $artists[$artist]['tracks'][$track] = ($artists[$artist]['tracks'][$track] ?? 0) + 1;
            }
        }
        
        foreach ($artists as &$artist) {
            $artist['unique_users'] = count($artist['unique_users']);
            arsort($artist['tracks']);
            $artist['tracks'] = array_slice($artist['tracks'], 0, 5);
        }
        
        usort($artists, function($a, $b) {
            return $b['play_count'] - $a['play_count'];
        });
        
        return array_slice($artists, 0, $limit);
    }
    
    /**
     * Get overall platform statistics
     */
    public function getPlatformStats($dateStart = null, $dateEnd = null) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if ($dateEnd === null) {
            $dateEnd = gmdate('Y-m-d');
        }

        try {
            $stats = bandpromo_activity_store_platform_stats($this->root, $dateStart, $dateEnd);
            if (($stats['total_sessions'] ?? 0) > 0 || ($stats['total_plays'] ?? 0) > 0 || !empty($stats['activity_types'])) {
                return $stats;
            }
        } catch (Throwable $e) {
            error_log('bandPromo analytics platform rollup read error: ' . $e->getMessage());
        }

        $entries = $this->getLogEntries($dateStart, $dateEnd);
        
        try {
            $hourlyDistribution = bandpromo_activity_store_hourly_distribution($this->root, $dateStart, $dateEnd);
        } catch (Throwable $e) {
            error_log('bandPromo analytics hourly distribution error: ' . $e->getMessage());
            $hourlyDistribution = [];
        }

        $stats = [
            'total_plays' => 0,
            'total_listening_time' => 0,
            'unique_users' => [],
            'total_sessions' => 0,
            'device_breakdown' => [],
            'quality_estimate' => [],
            'hourly_distribution' => $hourlyDistribution,
            'daily_distribution' => [],
            'activity_types' => []
        ];
        
        foreach ($entries as $entry) {
            // Basic counts
            $stats['activity_types'][$entry['activity']] = ($stats['activity_types'][$entry['activity']] ?? 0) + 1;
            $stats['unique_users'][$entry['username']] = true;
            
            // Track plays
            if ($entry['activity'] === 'play_start') {
                $stats['total_sessions']++;
            }
            
            // Listening time
            if ($entry['activity'] === 'track_started' && isset($entry['data']['track_title'])) {
                $stats['total_plays']++;
            }

            $stats['total_listening_time'] += $this->getMeaningfulListeningSeconds($entry);
            
            // Device tracking
            if (!empty($entry['user_agent'])) {
                $device = $this->getDeviceType($entry['user_agent']);
                /** @var array<string,int> $deviceBreakdown */
                $deviceBreakdown = $stats['device_breakdown'];
                $this->incrementCounter($deviceBreakdown, $device);
                $stats['device_breakdown'] = $deviceBreakdown;
            }

            // Quality distribution: use real data.quality when available
            $logged = strtoupper(trim($entry['data']['quality'] ?? ''));
            $quality = ($logged === 'ORIGINAL' || $logged === 'HQ' || $logged === 'LQ' || $logged === 'OPTIMAL') ? (($logged === 'LQ' || $logged === 'OPTIMAL') ? 'LQ' : 'ORIGINAL') : $this->inferQuality($entry['user_agent'] ?? '');
            /** @var array<string,int> $qualityEstimate */
            $qualityEstimate = $stats['quality_estimate'];
            $this->incrementCounter($qualityEstimate, $quality);
            $stats['quality_estimate'] = $qualityEstimate;
            
            // Daily distribution (hourly chart uses rollup/query helper above)
            $entryUnix = bandpromo_entry_unix_timestamp($entry);
            if ($entryUnix > 0) {
                $bucketTz = bandpromo_analytics_bucket_timezone();
                $day = bandpromo_admin_day_from_unix($entryUnix, $bucketTz);
                /** @var array<string,int> $dailyDistribution */
                $dailyDistribution = $stats['daily_distribution'];
                $this->incrementCounter($dailyDistribution, $day);
                $stats['daily_distribution'] = $dailyDistribution;
            }
        }
        
        // Convert unique users to count
        $stats['unique_users'] = count($stats['unique_users']);
        
        return $stats;
    }
    
    /**
     * Get daily/weekly trending data
     */
    public function getTrendingData($dateStart = null, $dateEnd = null) {
        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $trends = [];
        
        foreach ($entries as $entry) {
            $entryUnix = bandpromo_entry_unix_timestamp($entry);
            $bucketTz = bandpromo_analytics_bucket_timezone();
            $date = $entryUnix > 0
                ? bandpromo_admin_day_from_unix($entryUnix, $bucketTz)
                : bandpromo_admin_day_from_unix(time(), $bucketTz);
            
            if (!isset($trends[$date])) {
                $trends[$date] = [
                    'date' => $date,
                    'plays' => 0,
                    'listening_time' => 0,
                    'sessions' => 0,
                    'unique_users' => [],
                    'top_track' => null,
                    'top_artist' => null
                ];
            }
            
            if ($entry['activity'] === 'play_start') {
                $trends[$date]['sessions']++;
            }
            
            if ($entry['activity'] === 'track_started') {
                $trends[$date]['plays']++;
                $trends[$date]['unique_users'][$entry['username']] = true;
            }

            $trends[$date]['listening_time'] += $this->getMeaningfulListeningSeconds($entry);
        }
        
        foreach ($trends as &$day) {
            $day['unique_users'] = count($day['unique_users']);
        }
        
        ksort($trends);
        return $trends;
    }
    
    /**
     * Get user session details
     */
    public function getUserSessions($username, $dateStart = null, $dateEnd = null, $limit = 100) {
        $entries = $this->getLogEntries($dateStart, $dateEnd, $username);
        $sessions = [];
        $currentSession = null;
        
        foreach ($entries as $entry) {
            if ($entry['activity'] === 'play_start') {
                if ($currentSession !== null) {
                    $sessions[] = $currentSession;
                }
                $currentSession = [
                    'start_time' => $entry['timestamp'],
                    'end_time' => $entry['timestamp'],
                    'device' => $this->getDeviceType($entry['user_agent'] ?? ''),
                    'tracks' => [],
                    'total_time' => 0,
                    'play_count' => 0
                ];
            }
            
            if ($currentSession !== null) {
                $currentSession['end_time'] = $entry['timestamp'];
                
                if ($entry['activity'] === 'track_started') {
                    $currentSession['play_count']++;
                    $duration = (int)($entry['data']['duration'] ?? 0);
                    $currentSession['total_time'] += $duration;
                    $currentSession['tracks'][] = [
                        'title' => $entry['data']['track_title'] ?? 'Unknown',
                        'artist' => $entry['data']['track_artist'] ?? 'Unknown',
                        'duration' => $duration
                    ];
                }

                $currentSession['total_time'] += $this->getMeaningfulListeningSeconds($entry);

                if ($entry['activity'] === 'session_end') {
                    $sessions[] = $currentSession;
                    $currentSession = null;
                }
            }
        }
        
        if ($currentSession !== null) {
            $sessions[] = $currentSession;
        }
        
        return array_slice($sessions, 0, $limit);
    }
    
    /**
     * Get quality distribution.
     * Uses data.quality (original/LQ/optimal) logged directly by the player when available;
     * falls back to inferQuality() from user-agent for legacy log entries.
     */
    public function getQualityStats($dateStart = null, $dateEnd = null) {
        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $stats = [
            'original' => 0,
            'lq' => 0,
            'original_listening_time' => 0,
            'lq_listening_time' => 0,
            'real_data_entries' => 0,   // entries with data.quality
            'inferred_entries'  => 0,   // entries where we fell back to user-agent
            'by_device' => []
        ];

        foreach ($entries as $entry) {
            $device = $this->getDeviceType($entry['user_agent'] ?? '');
            /** @var array<string,array{original:int,lq:int}> $byDevice */
            $byDevice = $stats['by_device'];
            $this->ensureQualityDeviceBucket($byDevice, $device);
            $stats['by_device'] = $byDevice;

            // Prefer real quality field; fall back to user-agent inference for legacy entries
            $logged = strtoupper(trim($entry['data']['quality'] ?? ''));
            if ($logged === 'ORIGINAL' || $logged === 'HQ' || $logged === 'LQ' || $logged === 'OPTIMAL') {
                // Normalise: legacy 'HQ' → 'ORIGINAL'; 'LQ'/'OPTIMAL' → 'LQ'
                if ($logged === 'LQ' || $logged === 'OPTIMAL') { $logged = 'LQ'; }
                else { $logged = 'ORIGINAL'; }
                $quality = $logged;
                $stats['real_data_entries']++;
            } else {
                $quality = $this->inferQuality($entry['user_agent'] ?? '');
                $stats['inferred_entries']++;
            }

            $key = strtolower($quality);
            $stats[$key]++;
            /** @var array<string,array{original:int,lq:int}> $byDevice */
            $byDevice = $stats['by_device'];
            $this->ensureQualityDeviceBucket($byDevice, $device);
            $byDevice[$device][$key] = (int)($byDevice[$device][$key] ?? 0) + 1;
            $stats['by_device'] = $byDevice;

            // Accumulate actual listening time from end-events (>= 5% completion)
            $listenKey = $key . '_listening_time';
            $stats[$listenKey] += $this->getMeaningfulListeningSeconds($entry);
        }

        return $stats;
    }
    
    /**
     * Get track completion rate analysis
     */
    public function getCompletionRates($dateStart = null, $dateEnd = null, $limit = 50) {
        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $tracks = [];
        
        foreach ($entries as $entry) {
            // Count track_ended, track_change_next, and track_interrupted as play attempts
            if ($this->isTrackProgressEvent($entry) && isset($entry['data']['track_title'])) {
                $completionRate = (int)($entry['data']['completion_rate'] ?? 0);
                // Ignore accidental taps (< 5% completion)
                if ($completionRate < 5) continue;
                $key = $this->normalizeTrackKey($entry['data']['track_title'], $entry['data']['track_artist'] ?? '');
                
                if (!isset($tracks[$key])) {
                    $tracks[$key] = [
                        'title' => $entry['data']['track_title'],
                        'artist' => $entry['data']['track_artist'] ?? 'Unknown',
                        'track_index' => $entry['data']['track_index'] ?? PHP_INT_MAX,
                        'total_plays' => 0,
                        'completion_rates' => [],
                        'avg_completion' => 0,
                        'skipped_count' => 0
                    ];
                }
                
                // Keep the lowest observed index (most reliable playlist position)
                if (isset($entry['data']['track_index']) && $entry['data']['track_index'] < $tracks[$key]['track_index']) {
                    $tracks[$key]['track_index'] = $entry['data']['track_index'];
                }
                
                $tracks[$key]['total_plays']++;
                $tracks[$key]['completion_rates'][] = $completionRate;
                
                // Track skips (incomplete plays, less than 90%)
                if ($completionRate < 90) {
                    $tracks[$key]['skipped_count']++;
                }
            }
        }
        
        // Calculate averages
        foreach ($tracks as &$track) {
            if (!empty($track['completion_rates'])) {
                $track['avg_completion'] = round(array_sum($track['completion_rates']) / count($track['completion_rates']), 1);
            } else {
                $track['avg_completion'] = 0;
            }
            unset($track['completion_rates']); // Remove detailed array for cleaner output
        }
        
        // Sort by playlist position (track_index)
        usort($tracks, function($a, $b) {
            return $a['track_index'] <=> $b['track_index'];
        });
        
        return array_slice($tracks, 0, $limit);
    }
    
    /**
     * Get skip patterns - when users skip tracks
     */
    public function getSkipPatterns($dateStart = null, $dateEnd = null, $limit = 50) {
        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $skipData = [];
        
        foreach ($entries as $entry) {
            $exitReason = $this->getTrackExitReason($entry);

            // Track forward/manual skips only (prev = deliberate rewind, not a quality signal)
            if (in_array($exitReason, ['next_click', 'playlist_select'], true)) {
                $completionRate = (int)($entry['data']['completion_rate'] ?? 0);
                
                // Only count as "skip" if they got less than 90% through
                if ($completionRate < 90) {
                    $key = $this->normalizeTrackKey($entry['data']['track_title'], $entry['data']['track_artist'] ?? '');
                    
                    if (!isset($skipData[$key])) {
                        $skipData[$key] = [
                            'title' => $entry['data']['track_title'],
                            'artist' => $entry['data']['track_artist'] ?? 'Unknown',
                            'skip_count' => 0,
                            'avg_completion_on_skip' => [],
                            'skip_direction' => []
                        ];
                    }
                    
                    $skipData[$key]['skip_count']++;
                    $skipData[$key]['avg_completion_on_skip'][] = $completionRate;
                    $skipData[$key]['skip_direction'][] = $exitReason === 'playlist_select' ? 'playlist' : 'next';
                }
            }
        }
        
        // Calculate averages
        foreach ($skipData as &$track) {
            if (!empty($track['avg_completion_on_skip'])) {
                $track['avg_completion_on_skip'] = round(array_sum($track['avg_completion_on_skip']) / count($track['avg_completion_on_skip']), 1);
                $nextCount = count(array_filter($track['skip_direction'], fn($d) => $d === 'next'));
                $playlistCount = count(array_filter($track['skip_direction'], fn($d) => $d === 'playlist'));
                $track['skip_behavior'] = $nextCount . ' forward, ' . $playlistCount . ' playlist';
            }
            unset($track['skip_direction']);
        }
        
        // Sort by skip count
        usort($skipData, function($a, $b) {
            return $b['skip_count'] - $a['skip_count'];
        });
        
        return array_slice($skipData, 0, $limit);
    }
    
    /**
     * Get raw log entries with optional filters
     * Returns paginated list of raw log entries for display
     */
    public function getRawLog($dateStart = null, $dateEnd = null, $activityType = null, $username = null, $limit = 200, $offset = 0) {
        $entries = $this->getLogEntries($dateStart, $dateEnd, $username);

        // Filter by activity type
        if ($activityType !== null && $activityType !== '') {
            $entries = array_values(array_filter($entries, function($e) use ($activityType) {
                return $e['activity'] === $activityType;
            }));
        }

        // Reverse chronological order
        $entries = array_reverse($entries);

        $total = count($entries);
        $page = array_slice($entries, $offset, $limit);

        return [
            'entries' => $page,
            'total'   => $total,
        ];
    }

    /**
     * Get all distinct activity types present in the log
     */
    public function getActivityTypes($dateStart = null, $dateEnd = null) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if ($dateEnd === null) {
            $dateEnd = gmdate('Y-m-d');
        }

        try {
            return bandpromo_activity_store_distinct_listener_activities($this->root, $dateStart, $dateEnd);
        } catch (Throwable $e) {
            error_log('bandPromo activity type lookup error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all activity types breakdown
     */
    public function getActivityBreakdown($dateStart = null, $dateEnd = null) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if ($dateEnd === null) {
            $dateEnd = gmdate('Y-m-d');
        }

        try {
            $stats = bandpromo_activity_store_platform_stats($this->root, $dateStart, $dateEnd);
            $activities = $stats['activity_types'] ?? [];
            if ($activities !== []) {
                arsort($activities);
                return [
                    'activities' => $activities,
                    'total_events' => array_sum($activities),
                ];
            }
        } catch (Throwable $e) {
            error_log('bandPromo analytics activity rollup read error: ' . $e->getMessage());
        }

        $entries = $this->getLogEntries($dateStart, $dateEnd);
        $activities = [];
        
        foreach ($entries as $entry) {
            $activity = $entry['activity'];
            if (!isset($activities[$activity])) {
                $activities[$activity] = 0;
            }
            $activities[$activity]++;
        }
        
        arsort($activities);
        
        return [
            'activities' => $activities,
            'total_events' => array_sum($activities)
        ];
    }
    
    private function getLogEntries($dateStart = null, $dateEnd = null, $username = null) {
        if ($dateStart === null) {
            $dateStart = gmdate('Y-m-d');
        }
        if ($dateEnd === null) {
            $dateEnd = $dateStart;
        }

        $cacheKey = $dateStart . '|' . $dateEnd . '|' . ($username ?? '');
        if (isset($this->entryCache[$cacheKey])) {
            return $this->entryCache[$cacheKey];
        }

        try {
            $entries = bandpromo_activity_store_fetch_listener_entries($this->root, $dateStart, $dateEnd, $username);
        } catch (Throwable $e) {
            error_log('bandPromo analytics read error: ' . $e->getMessage());
            $entries = [];
        }

        $this->entryCache[$cacheKey] = $entries;

        return $entries;
    }
    
    /**
     * Helper: Infer device quality from user agent
     */
    private function inferQuality($userAgent) {
        // Mobile devices typically get optimized variant
        if (preg_match('/(Mobile|Android|iPhone|iPad|Windows Phone|BlackBerry)/i', $userAgent)) {
            return 'LQ';
        }
        // Desktop typically gets original quality
        return 'ORIGINAL';
    }
    
    /**
     * Helper: Get device type from user agent
     */
    private function getDeviceType($userAgent) {
        if (preg_match('/iPhone/i', $userAgent)) return 'iPhone';
        if (preg_match('/iPad/i', $userAgent)) return 'iPad';
        if (preg_match('/Android/i', $userAgent)) return 'Android';
        if (preg_match('/Windows Phone/i', $userAgent)) return 'Windows Phone';
        if (preg_match('/BlackBerry/i', $userAgent)) return 'BlackBerry';
        if (preg_match('/Windows|Win64|Win32/i', $userAgent)) return 'Windows';
        if (preg_match('/Macintosh|Mac OS/i', $userAgent)) return 'macOS';
        if (preg_match('/Linux/i', $userAgent)) return 'Linux';
        return 'Unknown';
    }
    
    /**
     * Helper: Normalize track key for deduplication
     */
    private function normalizeTrackKey($title, $artist) {
        return strtolower(trim($title)) . '|' . strtolower(trim($artist ?? ''));
    }
    
    /**
     * Format seconds to human readable time
     */
    public static function formatSeconds($seconds) {
        $seconds = (int)$seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }
    
    /**
     * Format seconds to total hours
     */
    public static function formatHours($seconds) {
        return round((int)$seconds / 3600, 1);
    }

    /**
     * Format seconds for stat cards using a human-useful unit.
     * Returns [value, unit].
     */
    public static function formatTimeStat($seconds) {
        $seconds = (int)$seconds;

        if ($seconds >= 3600) {
            return [self::formatHours($seconds), 'hours'];
        }

        return [self::formatSeconds($seconds), 'm:ss'];
    }
}
