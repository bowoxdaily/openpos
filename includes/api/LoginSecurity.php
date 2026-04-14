<?php
/**
 * OpenPOS Login Security Handler
 * 
 * Handles:
 * - Brute force protection
 * - Account lockout
 * - Login attempt logging
 * - Session timeout validation
 * - IP-based restrictions
 */

if(!class_exists('OP_LoginSecurity')) {
    class OP_LoginSecurity {
        
        private $max_attempts = 5;
        private $lockout_duration = 900; // 15 minutes in seconds
        private $session_timeout = 28800; // 8 hours in seconds
        private $option_key_prefix = 'openpos_login_';
        
        public function __construct() {
            $this->max_attempts = apply_filters('openpos_max_login_attempts', $this->max_attempts);
            $this->lockout_duration = apply_filters('openpos_lockout_duration', $this->lockout_duration);
            $this->session_timeout = apply_filters('openpos_session_timeout', $this->session_timeout);
        }
        
        /**
         * Check if user is locked out due to failed attempts
         * 
         * @param string $username
         * @param string $ip
         * @return array ['locked' => bool, 'remaining_time' => int, 'attempts_left' => int]
         */
        public function check_lockout($username, $ip = '') {
            if (!$ip) {
                $ip = $this->get_client_ip();
            }
            
            $lockout_key = $this->option_key_prefix . 'lockout_' . md5($username . '_' . $ip);
            $attempts_key = $this->option_key_prefix . 'attempts_' . md5($username . '_' . $ip);
            
            $lockout_time = get_transient($lockout_key);
            $attempts = (int) get_transient($attempts_key);
            
            if ($lockout_time) {
                $remaining_time = $lockout_time - time();
                return [
                    'locked' => true,
                    'remaining_time' => max(0, $remaining_time),
                    'attempts_left' => 0,
                    'message' => sprintf(
                        __('Account temporarily locked. Please try again in %d minutes.', 'openpos'),
                        ceil($remaining_time / 60)
                    )
                ];
            }
            
            return [
                'locked' => false,
                'remaining_time' => 0,
                'attempts_left' => max(0, $this->max_attempts - $attempts)
            ];
        }
        
        /**
         * Record a failed login attempt
         * 
         * @param string $username
         * @param string $ip
         * @return bool
         */
        public function record_failed_attempt($username, $ip = '') {
            if (!$ip) {
                $ip = $this->get_client_ip();
            }
            
            $attempts_key = $this->option_key_prefix . 'attempts_' . md5($username . '_' . $ip);
            $lockout_key = $this->option_key_prefix . 'lockout_' . md5($username . '_' . $ip);
            
            $attempts = (int) get_transient($attempts_key) + 1;
            
            // Set transient for 30 minutes to track attempts
            set_transient($attempts_key, $attempts, 1800);
            
            // Log the failed attempt
            $this->log_login_attempt($username, $ip, false, 'Invalid credentials');
            
            // If max attempts reached, lock the account
            if ($attempts >= $this->max_attempts) {
                set_transient($lockout_key, time() + $this->lockout_duration, $this->lockout_duration);
                $this->log_login_attempt($username, $ip, false, 'Account locked - max attempts exceeded', true);
                return false;
            }
            
            return true;
        }
        
        /**
         * Clear failed login attempts
         * 
         * @param string $username
         * @param string $ip
         * @return void
         */
        public function clear_attempts($username, $ip = '') {
            if (!$ip) {
                $ip = $this->get_client_ip();
            }
            
            $attempts_key = $this->option_key_prefix . 'attempts_' . md5($username . '_' . $ip);
            delete_transient($attempts_key);
        }
        
        /**
         * Record a successful login
         * 
         * @param int $user_id
         * @param string $username
         * @param string $ip
         * @param string $session_id
         * @return void
         */
        public function record_successful_login($user_id, $username, $ip = '', $session_id = '') {
            if (!$ip) {
                $ip = $this->get_client_ip();
            }
            
            // Clear failed attempts
            $this->clear_attempts($username, $ip);
            
            // Log successful login
            $this->log_login_attempt($username, $ip, true, 'Successful login', false, $user_id, $session_id);
            
            // Update user last login
            update_user_meta($user_id, '_openpos_last_login', current_time('mysql', true));
            update_user_meta($user_id, '_openpos_last_login_ip', $ip);
            update_user_meta($user_id, '_openpos_login_count', (int)get_user_meta($user_id, '_openpos_login_count', true) + 1);
        }
        
        /**
         * Validate session hasn't expired
         * 
         * @param array $session_data
         * @param string $session_id
         * @return array ['valid' => bool, 'message' => string, 'expired_at' => int]
         */
        public function validate_session_timeout($session_data, $session_id = '') {
            if (!is_array($session_data) || empty($session_data)) {
                return [
                    'valid' => false,
                    'message' => __('Session data not found', 'openpos'),
                    'expired_at' => 0
                ];
            }
            
            $logged_time = isset($session_data['logged_time']) ? $session_data['logged_time'] : 0;
            
            if (!$logged_time) {
                return [
                    'valid' => false,
                    'message' => __('Invalid session timestamp', 'openpos'),
                    'expired_at' => 0
                ];
            }
            
            // Convert to timestamp
            $logged_timestamp = strtotime($logged_time);
            $current_timestamp = current_time('timestamp', true);
            $elapsed = $current_timestamp - $logged_timestamp;
            
            if ($elapsed > $this->session_timeout) {
                $expired_at = $logged_timestamp + $this->session_timeout;
                $this->log_login_attempt(
                    isset($session_data['username']) ? $session_data['username'] : 'unknown',
                    $this->get_client_ip(),
                    false,
                    'Session timeout'
                );
                
                return [
                    'valid' => false,
                    'message' => __('Your session has expired. Please login again.', 'openpos'),
                    'expired_at' => $expired_at
                ];
            }
            
            return [
                'valid' => true,
                'message' => '',
                'expired_at' => $logged_timestamp + $this->session_timeout,
                'remaining_seconds' => $this->session_timeout - $elapsed
            ];
        }
        
        /**
         * Log login attempt to database
         * 
         * @param string $username
         * @param string $ip
         * @param bool $success
         * @param string $reason
         * @param bool $locked
         * @param int $user_id
         * @param string $session_id
         * @return int|false
         */
        public function log_login_attempt($username, $ip, $success = false, $reason = '', $locked = false, $user_id = 0, $session_id = '') {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'openpos_login_logs';
            
            // Create table if doesn't exist
            $this->create_login_logs_table();
            
            $data = [
                'username' => sanitize_text_field($username),
                'user_id' => (int) $user_id,
                'ip_address' => sanitize_text_field($ip),
                'success' => (bool) $success,
                'reason' => sanitize_text_field($reason),
                'locked' => (bool) $locked,
                'session_id' => sanitize_text_field($session_id),
                'user_agent' => sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)),
                'login_time' => current_time('mysql', true)
            ];
            
            return $wpdb->insert($table_name, $data);
        }
        
        /**
         * Get login history for user
         * 
         * @param int $user_id
         * @param int $limit
         * @return array
         */
        public function get_login_history($user_id = 0, $limit = 20) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'openpos_login_logs';
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE success = 1 " .
                ($user_id ? "AND user_id = %d " : "") .
                "ORDER BY login_time DESC LIMIT %d",
                $user_id ? [$user_id, $limit] : [$limit]
            );
            
            return $wpdb->get_results($query);
        }
        
        /**
         * Check for suspicious login activity
         * 
         * @param int $user_id
         * @param string $current_ip
         * @return array
         */
        public function check_suspicious_activity($user_id, $current_ip = '') {
            if (!$current_ip) {
                $current_ip = $this->get_client_ip();
            }
            
            $last_logins = $this->get_login_history($user_id, 1);
            
            if (empty($last_logins)) {
                return ['suspicious' => false, 'reason' => ''];
            }
            
            $last_login = $last_logins[0];
            $last_ip = $last_login->ip_address;
            $last_time = strtotime($last_login->login_time);
            $current_time = current_time('timestamp', true);
            $time_diff = $current_time - $last_time;
            
            $suspicious_flags = [];
            
            // Check if IP changed from last login
            if ($last_ip !== $current_ip) {
                $suspicious_flags[] = 'ip_changed';
            }
            
            // Check if login happens too fast (impossible travel)
            if ($time_diff < 120 && $last_ip !== $current_ip) { // Less than 2 minutes
                $suspicious_flags[] = 'impossible_travel';
            }
            
            return [
                'suspicious' => !empty($suspicious_flags),
                'flags' => $suspicious_flags,
                'last_ip' => $last_ip,
                'last_login' => $last_login->login_time
            ];
        }
        
        /**
         * Create login logs table if not exists
         * 
         * @return void
         */
        private function create_login_logs_table() {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'openpos_login_logs';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                user_id BIGINT(20),
                ip_address VARCHAR(100) NOT NULL,
                success TINYINT(1) DEFAULT 0,
                reason VARCHAR(255),
                locked TINYINT(1) DEFAULT 0,
                session_id VARCHAR(255),
                user_agent VARCHAR(255),
                login_time DATETIME NOT NULL,
                KEY username (username),
                KEY user_id (user_id),
                KEY ip_address (ip_address),
                KEY login_time (login_time)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        /**
         * Get client IP address
         * 
         * @return string
         */
        private function get_client_ip() {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            }
            
            return sanitize_text_field($ip);
        }
        
        /**
         * Get security settings
         * 
         * @return array
         */
        public function get_settings() {
            return [
                'max_attempts' => $this->max_attempts,
                'lockout_duration' => $this->lockout_duration,
                'session_timeout' => $this->session_timeout,
                'session_timeout_minutes' => ceil($this->session_timeout / 60)
            ];
        }
    }
}
