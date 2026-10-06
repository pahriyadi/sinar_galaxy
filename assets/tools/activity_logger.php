<?php
/**
 * Activity Logger Class untuk Galaxy Travel
 * Hanya Super Admin yang dapat mengakses
 */

class ActivityLogger {
    private $conn;
    private $user_id;
    private $username;
    private $nama_lengkap;
    private $role;
    private $ip_address;
    private $user_agent;
    private $session_id;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->ip_address = $this->getClientIP();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->session_id = session_id();
        
        // Set user info jika sudah login
        if (isset($_SESSION['user'])) {
            $this->user_id = $_SESSION['user']['id_users'] ?? 0;
            $this->username = $_SESSION['user']['username'] ?? '';
            $this->nama_lengkap = $_SESSION['user']['nama_lengkap'] ?? '';
            $this->role = $_SESSION['user']['role'] ?? '';
        }
    }
    
    /**
     * Log aktivitas user
     */
    public function logActivity($activity_type, $description, $table_name = null, $record_id = null, $old_data = null, $new_data = null) {
        // Cek apakah logging diaktifkan
        if (!$this->isLoggingEnabled()) {
            return false;
        }
        
        try {
            $sql = "INSERT INTO activity_log (user_id, username, nama_lengkap, role, activity_type, table_name, record_id, description, old_data, new_data, ip_address, user_agent, session_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("isssssissssss", 
                $this->user_id,
                $this->username,
                $this->nama_lengkap,
                $this->role,
                $activity_type,
                $table_name,
                $record_id,
                $description,
                $old_data,
                $new_data,
                $this->ip_address,
                $this->user_agent,
                $this->session_id
            );
            
            $result = $stmt->execute();
            $log_id = $this->conn->insert_id;
            
            // Jika ada perubahan data, log audit trail
            if ($old_data && $new_data && $log_id) {
                $this->logAuditTrail($log_id, $old_data, $new_data);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Activity Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log login activity
     */
    public function logLogin($status = 'success', $failure_reason = null) {
        if (!$this->isLoggingEnabled() || !$this->getSetting('log_login_attempts')) {
            return false;
        }
        
        try {
            $sql = "INSERT INTO login_history (user_id, username, nama_lengkap, role, ip_address, user_agent, session_id, status, failure_reason) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("issssssss", 
                $this->user_id,
                $this->username,
                $this->nama_lengkap,
                $this->role,
                $this->ip_address,
                $this->user_agent,
                $this->session_id,
                $status,
                $failure_reason
            );
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Login Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log logout activity
     */
    public function logLogout() {
        try {
            $sql = "UPDATE login_history SET logout_time = NOW() 
                    WHERE session_id = ? AND logout_time IS NULL 
                    ORDER BY login_time DESC LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $this->session_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Logout Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log audit trail untuk perubahan data
     */
    private function logAuditTrail($log_id, $old_data, $new_data) {
        try {
            $old_array = json_decode($old_data, true);
            $new_array = json_decode($new_data, true);
            
            if (!$old_array || !$new_array) {
                return false;
            }
            
            $all_fields = array_unique(array_merge(array_keys($old_array), array_keys($new_array)));
            
            foreach ($all_fields as $field) {
                $old_value = $old_array[$field] ?? null;
                $new_value = $new_array[$field] ?? null;
                
                if ($old_value !== $new_value) {
                    $change_type = 'modified';
                    if ($old_value === null) {
                        $change_type = 'added';
                    } elseif ($new_value === null) {
                        $change_type = 'deleted';
                    }
                    
                    $sql = "INSERT INTO audit_trail (log_id, field_name, old_value, new_value, change_type) 
                            VALUES (?, ?, ?, ?, ?)";
                    
                    $stmt = $this->conn->prepare($sql);
                    $stmt->bind_param("issss", 
                        $log_id,
                        $field,
                        $old_value,
                        $new_value,
                        $change_type
                    );
                    
                    $stmt->execute();
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Audit Trail Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get activity logs dengan filter
     */
    public function getActivityLogs($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT al.*, u.nama_lengkap as user_nama 
                FROM activity_log al 
                LEFT JOIN data_users u ON al.user_id = u.id_users 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['activity_type'])) {
            $sql .= " AND al.activity_type = ?";
            $params[] = $filters['activity_type'];
            $types .= "s";
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND al.table_name = ?";
            $params[] = $filters['table_name'];
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND al.ip_address LIKE ?";
            $params[] = "%" . $filters['ip_address'] . "%";
            $types .= "s";
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get login history
     */
    public function getLoginHistory($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT lh.*, u.nama_lengkap as user_nama 
                FROM login_history lh 
                LEFT JOIN data_users u ON lh.user_id = u.id_users 
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $sql .= " AND lh.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND lh.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(lh.login_time) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(lh.login_time) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        $sql .= " ORDER BY lh.login_time DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get audit trail untuk log tertentu
     */
    public function getAuditTrail($log_id) {
        $sql = "SELECT * FROM audit_trail WHERE log_id = ? ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $log_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get system setting
     */
    private function getSetting($key) {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['setting_value'];
        }
        
        return null;
    }
    
    /**
     * Check if logging is enabled
     */
    private function isLoggingEnabled() {
        return $this->getSetting('activity_log_enabled') == '1';
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean old logs berdasarkan retention policy
     */
    public function cleanOldLogs() {
        $retention_days = $this->getSetting('log_retention_days') ?? 365;
        
        try {
            // Clean activity logs
            $sql = "DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $retention_days);
            $stmt->execute();
            
            // Clean login history
            $sql = "DELETE FROM login_history WHERE login_time < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $retention_days);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Clean Logs Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Jalankan backup otomatis untuk H-1 jika belum ada
     */
    public function runDailyBackupIfNeeded() {
        require_once __DIR__ . '/backup/backup_utils.php';
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if (!isBackupExists($yesterday)) {
            $userId = isset($_SESSION['user']['id_users']) ? (int)$_SESSION['user']['id_users'] : 0;
            backupLogsByDate($yesterday, $userId);
        }
    }
    
    /**
     * Get statistics
     */
    public function getStatistics($date_from = null, $date_to = null) {
        $stats = [];
        
        try {
            // Total activities
            $sql = "SELECT COUNT(*) as total FROM activity_log";
            if ($date_from && $date_to) {
                $sql .= " WHERE DATE(created_at) BETWEEN ? AND ?";
            }
            $stmt = $this->conn->prepare($sql);
            if ($date_from && $date_to) {
                $stmt->bind_param("ss", $date_from, $date_to);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['total_activities'] = $result->fetch_assoc()['total'];
            
            // Activities by type
            $sql = "SELECT activity_type, COUNT(*) as count FROM activity_log";
            if ($date_from && $date_to) {
                $sql .= " WHERE DATE(created_at) BETWEEN ? AND ?";
            }
            $sql .= " GROUP BY activity_type";
            $stmt = $this->conn->prepare($sql);
            if ($date_from && $date_to) {
                $stmt->bind_param("ss", $date_from, $date_to);
            }
            $stmt->execute();
            $stats['activities_by_type'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Top users
            $sql = "SELECT user_id, username, COUNT(*) as count FROM activity_log";
            if ($date_from && $date_to) {
                $sql .= " WHERE DATE(created_at) BETWEEN ? AND ?";
            }
            $sql .= " GROUP BY user_id, username ORDER BY count DESC LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            if ($date_from && $date_to) {
                $stmt->bind_param("ss", $date_from, $date_to);
            }
            $stmt->execute();
            $stats['top_users'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Login statistics
            $sql = "SELECT COUNT(*) as total FROM login_history";
            if ($date_from && $date_to) {
                $sql .= " WHERE DATE(login_time) BETWEEN ? AND ?";
            }
            $stmt = $this->conn->prepare($sql);
            if ($date_from && $date_to) {
                $stmt->bind_param("ss", $date_from, $date_to);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['total_logins'] = $result->fetch_assoc()['total'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Statistics Error: " . $e->getMessage());
            return [];
        }
    }
}

// Helper functions untuk kemudahan penggunaan
function logActivity($activity_type, $description, $table_name = null, $record_id = null, $old_data = null, $new_data = null) {
    global $conn;
    $logger = new ActivityLogger($conn);
    return $logger->logActivity($activity_type, $description, $table_name, $record_id, $old_data, $new_data);
}

function logLogin($status = 'success', $failure_reason = null) {
    global $conn;
    $logger = new ActivityLogger($conn);
    return $logger->logLogin($status, $failure_reason);
}

function logLogout() {
    global $conn;
    $logger = new ActivityLogger($conn);
    return $logger->logLogout();
}
?> 