<?php
/**
 * Entry/Exit Handler - PDO Version with Enhanced Error Logging
 * Location: receptionist/includes/entry_exit_handler.php
 */

// Prevent any output before JSON
ob_start();

session_start();

// Clean any output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Correct path from receptionist/includes/ to config/connection.php
$conn_path = '../../config/connection.php';
if (!file_exists($conn_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection file not found',
        'path' => $conn_path
    ]);
    exit;
}

require_once $conn_path;

// Get PDO connection
try {
    $conn = getDBConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Manila');

class ImprovedEntryExitHandler {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Currently inside (not checked out today)
            $sql = "SELECT COUNT(*) as count FROM attendance_log 
                    WHERE log_date = CURDATE() AND time_out IS NULL";
            $stmt = $this->conn->query($sql);
            $stats['currently_inside'] = $stmt->fetch()['count'];
            
            // Today's total check-ins
            $sql = "SELECT COUNT(*) as count FROM attendance_log 
                    WHERE log_date = CURDATE()";
            $stmt = $this->conn->query($sql);
            $stats['today_checkins'] = $stmt->fetch()['count'];
            
            // Members today (client_id is not null)
            $sql = "SELECT COUNT(*) as count FROM attendance_log 
                    WHERE log_date = CURDATE() AND client_id IS NOT NULL";
            $stmt = $this->conn->query($sql);
            $stats['members_today'] = $stmt->fetch()['count'];
            
            // Walk-ins today (client_id is null and guest_name is not null)
            $sql = "SELECT COUNT(*) as count FROM attendance_log 
                    WHERE log_date = CURDATE() AND client_id IS NULL AND guest_name IS NOT NULL";
            $stmt = $this->conn->query($sql);
            $stats['walkins_today'] = $stmt->fetch()['count'];
            
            return [
                'success' => true,
                'stats' => $stats
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error loading stats: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get currently inside people
     */
    public function getCurrentlyInside() {
        try {
            $sql = "SELECT 
                        al.attendance_id,
                        al.client_id,
                        al.guest_name,
                        CASE 
                            WHEN al. client_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                            ELSE al.guest_name
                        END as name,
                        CASE 
                            WHEN al.client_id IS NOT NULL THEN c.client_type
                            ELSE 'Walk-in'
                        END as client_type,
                        al.time_in,
                        al.check_in_timestamp,
                        c.profile_image,
                        TIMESTAMPDIFF(MINUTE, al.check_in_timestamp, NOW()) as minutes_inside
                    FROM attendance_log al
                    LEFT JOIN clients c ON al.client_id = c. client_id
                    WHERE al.log_date = CURDATE() AND al.time_out IS NULL
                    ORDER BY al.check_in_timestamp DESC";
            
            $stmt = $this->conn->query($sql);
            $people = [];
            
            while ($row = $stmt->fetch()) {
                $hours = floor($row['minutes_inside'] / 60);
                $minutes = $row['minutes_inside'] % 60;
                
                $duration = '';
                if ($hours > 0) {
                    $duration = $hours . 'h ' . $minutes . 'm';
                } else {
                    $duration = $minutes . 'm';
                }
                
                $people[] = [
                    'attendance_id' => $row['attendance_id'],
                    'client_id' => $row['client_id'],
                    'name' => $row['name'],
                    'client_type' => $row['client_type'],
                    'time_in' => date('h:i A', strtotime($row['time_in'])),
                    'duration' => $duration,
                    'profile_image' => $row['profile_image']
                ];
            }
            
            return [
                'success' => true,
                'people' => $people
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error loading currently inside: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get today's entry/exit log
     */
    public function getTodayLog() {
        try {
            $sql = "SELECT 
                        al.attendance_id,
                        al.client_id,
                        al. guest_name,
                        CASE 
                            WHEN al.client_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                            ELSE al.guest_name
                        END as name,
                        CASE 
                            WHEN al. client_id IS NOT NULL THEN c.client_type
                            ELSE 'Walk-in'
                        END as client_type,
                        al.time_in,
                        al.time_out,
                        al. check_in_timestamp,
                        al.check_out_timestamp,
                        c.profile_image
                    FROM attendance_log al
                    LEFT JOIN clients c ON al.client_id = c.client_id
                    WHERE al. log_date = CURDATE()
                    ORDER BY al.check_in_timestamp DESC";
            
            $stmt = $this->conn->query($sql);
            $logs = [];
            
            while ($row = $stmt->fetch()) {
                $duration = '-';
                if ($row['time_out']) {
                    $start = strtotime($row['check_in_timestamp']);
                    $end = strtotime($row['check_out_timestamp']);
                    $diff = $end - $start;
                    
                    $hours = floor($diff / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    
                    if ($hours > 0) {
                        $duration = $hours . 'h ' .  $minutes . 'm';
                    } else {
                        $duration = $minutes . 'm';
                    }
                }
                
                $logs[] = [
                    'attendance_id' => $row['attendance_id'],
                    'client_id' => $row['client_id'],
                    'name' => $row['name'],
                    'guest_name' => $row['guest_name'],
                    'client_type' => $row['client_type'],
                    'time_in' => date('h:i A', strtotime($row['time_in'])),
                    'time_out' => $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : null,
                    'duration' => $duration,
                    'profile_image' => $row['profile_image']
                ];
            }
            
            return [
                'success' => true,
                'logs' => $logs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error loading today log: ' . $e->getMessage()
            ];
        }
    }
    
    public function getLogByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT 
                        al.attendance_id,
                        al.client_id,
                        al.guest_name,
                        CASE 
                            WHEN al.client_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                            ELSE al.guest_name
                        END as name,
                        CASE 
                            WHEN al.client_id IS NOT NULL THEN c.client_type
                            ELSE 'Walk-in'
                        END as client_type,
                        al.time_in,
                        al.time_out,
                        al.check_in_timestamp,
                        al.check_out_timestamp,
                        c.profile_image
                    FROM attendance_log al
                    LEFT JOIN clients c ON al.client_id = c.client_id
                    WHERE al.log_date >= :start_date AND al.log_date <= :end_date
                    ORDER BY al.check_in_timestamp DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $logs = [];
            
            while ($row = $stmt->fetch()) {
                $duration = '-';
                if ($row['time_out']) {
                    $start = strtotime($row['check_in_timestamp']);
                    $end = strtotime($row['check_out_timestamp']);
                    $diff = $end - $start;
                    
                    $hours = floor($diff / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    
                    if ($hours > 0) {
                        $duration = $hours . 'h ' . $minutes . 'm';
                    } else {
                        $duration = $minutes . 'm';
                    }
                }
                
                $logs[] = [
                    'attendance_id' => $row['attendance_id'],
                    'client_id' => $row['client_id'],
                    'name' => $row['name'],
                    'guest_name' => $row['guest_name'],
                    'client_type' => $row['client_type'],
                    'time_in' => date('h:i A', strtotime($row['time_in'])),
                    'time_out' => $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : null,
                    'duration' => $duration,
                    'profile_image' => $row['profile_image']
                ];
            }
            
            return [
                'success' => true,
                'logs' => $logs,
                'date_range' => "$startDate to $endDate"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error loading log by date range: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process member check-in
     */
    public function checkIn($identifier) {
        try {
            // Get member info
            $member = $this->getMemberInfo($identifier);
            
            if (!$member) {
                return [
                    'success' => false,
                    'message' => 'Member not found.  Please verify the ID.',
                    'error_type' => 'not_found'
                ];
            }
            
            // Check if member is active
            if ($member['status'] !== 'Active' || $member['account_status'] !== 'Active') {
                return [
                    'success' => false,
                    'message' => 'Member account is not active.',
                    'error_type' => 'inactive'
                ];
            }
            
            // Check if member is Walk-in type (needs payment first)
            if ($member['client_type'] === 'Walk-in') {
                return [
                    'success' => false,
                    'message' => 'Walk-in guest.  Please process daily payment first.',
                    'error_type' => 'walk_in',
                    'member' => [
                        'client_id' => $member['client_id'],
                        'name' => $member['full_name']
                    ]
                ];
            }
            
            // Check membership validity for Members
            $membership = $this->getActiveMembership($member['client_id']);
            
            if (!$membership) {
                return [
                    'success' => false,
                    'message' => 'No active membership found.  Please renew membership.',
                    'error_type' => 'no_membership'
                ];
            }
            
            // Check if membership is expired
            if (strtotime($membership['end_date']) < strtotime(date('Y-m-d'))) {
                return [
                    'success' => false,
                    'message' => 'Membership expired on ' . date('M d, Y', strtotime($membership['end_date'])),
                    'error_type' => 'expired'
                ];
            }
            
            // Check if already checked in
            if ($this->isAlreadyCheckedIn($member['client_id'])) {
                return [
                    'success' => false,
                    'message' => 'Member is already checked in.',
                    'error_type' => 'already_checked_in'
                ];
            }
            
            // Record entry
            $attendance_id = $this->recordEntry($member['client_id'], 'Member', $membership['plan_name']);
            
            // Create POS transaction for member check-in
            $posTransactionResult = $this->createPOSTransaction(
                $member['client_id'],
                $member['full_name'],
                0, // No charge for member check-in (they're using their membership)
                'Membership',
                'Member Check-in',
                'Member gym entry via ' . $membership['plan_name'] . ' membership',
                $attendance_id
            );
            
            return [
                'success' => true,
                'message' => $member['full_name'] . ' checked in successfully!',
                'attendance_id' => $attendance_id,
                'pos_transaction' => $posTransactionResult
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * Process check-out
     */
    public function checkOut($identifier) {
        try {
            // Try to find by client_id or attendance_id
            $sql = "SELECT al.*, c.first_name, c. last_name, al.guest_name
                    FROM attendance_log al
                    LEFT JOIN clients c ON al.client_id = c.client_id
                    WHERE al.log_date = CURDATE() 
                    AND al.time_out IS NULL
                    AND (al.client_id = :client_id OR al.attendance_id = :attendance_id)
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'client_id' => $identifier,
                'attendance_id' => $identifier
            ]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'No active check-in found for this ID: ' . $identifier,
                    'error_type' => 'not_checked_in'
                ];
            }
            
            $record = $stmt->fetch();
            
            // Record exit
            $this->recordExit($record['attendance_id']);
            
            $name = $record['first_name'] ?  $record['first_name'] .  ' ' . $record['last_name'] : $record['guest_name'];
            
            return [
                'success' => true,
                'message' => $name . ' checked out successfully!'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'System error: ' .  $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * Process walk-in guest check-in
     * Guest records use client_id = NULL to indicate they are NOT in the clients table
     */
    public function walkInCheckIn($guestName, $guestPhone, $discountType) {
        try {
            // Basic validation
            $guestName = trim($guestName);
            if ($guestName === '') {
                return [
                    'success' => false,
                    'message' => 'Guest name is required',
                    'error_type' => 'validation'
                ];
            }

            // Get rate for discount type
            $rate = $this->getRate($discountType);

            // For guests, do NOT provide client_id - it will be NULL
            // Try to insert with receptionist_id first (if column exists)
            // If it fails, fall back to insert without it
            try {
                $sql = "INSERT INTO attendance_log 
                        (guest_name, attendance_type, entry_method, receptionist_id, log_date, time_in, 
                         check_in_timestamp, discount_type, status, temp_payment_status) 
                        VALUES (:name, 'Walk-in', 'Receptionist', :receptionist_id, CURDATE(), CURTIME(), 
                                NOW(), :discount, 'Pending', 'pending')";

                $stmt = $this->conn->prepare($sql);

                $params = [
                    'name' => $guestName,
                    'discount' => $discountType,
                    'receptionist_id' => $_SESSION['employee_id'] ?? null
                ];

                $result = $stmt->execute($params);
            } catch (PDOException $e) {
                // If receptionist_id column doesn't exist, use fallback insert
                if (strpos($e->getMessage(), 'receptionist_id') !== false || strpos($e->getMessage(), '1054') !== false) {
                    error_log("Warning: receptionist_id column not found. Using fallback INSERT. Please run: admin/database_setup.php");
                    
                    $sql = "INSERT INTO attendance_log 
                            (guest_name, attendance_type, entry_method, log_date, time_in, 
                             check_in_timestamp, discount_type, status, temp_payment_status) 
                            VALUES (:name, 'Walk-in', 'Receptionist', CURDATE(), CURTIME(), 
                                    NOW(), :discount, 'Pending', 'pending')";

                    $stmt = $this->conn->prepare($sql);

                    $params = [
                        'name' => $guestName,
                        'discount' => $discountType
                    ];

                    $result = $stmt->execute($params);
                } else {
                    throw $e;
                }
            }

            if (! $result) {
                $err = $stmt->errorInfo();
                return [
                    'success' => false,
                    'message' => 'Insert failed',
                    'sql_state' => $err[0] ?? null,
                    'driver_code' => $err[1] ??  null,
                    'driver_message' => $err[2] ??  null
                ];
            }

            $attendance_id = $this->conn->lastInsertId();
            if (! $attendance_id) {
                return [
                    'success' => false,
                    'message' => 'Insert succeeded but failed to obtain lastInsertId'
                ];
            }

            // Create POS transaction for walk-in check-in
            $posTransactionResult = $this->createPOSTransaction(
                null, // No client_id for walk-ins
                $guestName,
                $rate,
                'Cash', // Default to Cash for walk-in
                'Walk-in Check-in',
                'Walk-in guest entry - ' . $discountType . ' rate',
                $attendance_id
            );

            return [
                'success' => true,
                'message' => 'Walk-in guest ' . $guestName . ' checked in.  Payment required: ₱' . number_format($rate, 2),
                'payment_required' => true,
                'attendance_id' => $attendance_id,
                'amount' => $rate,
                'guest_name' => $guestName,
                'discount_type' => $discountType,
                'pos_transaction' => $posTransactionResult
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create an attendance record for a member (used for member walk-ins after payment)
     */
    public function createMemberAttendance($client_id) {
        try {
            // Basic validation
            if (!$client_id) {
                return [ 'success' => false, 'message' => 'Missing client_id' ];
            }

            // Ensure member exists
            $member = $this->getMemberInfo($client_id);
            if (!$member) {
                return [ 'success' => false, 'message' => 'Member not found' ];
            }

            // Record entry and return attendance id
            $attendance_id = $this->recordEntry($client_id, 'Member', 'Member');

            return [
                'success' => true,
                'message' => 'Member attendance created',
                'attendance_id' => $attendance_id
            ];
        } catch (Exception $e) {
            return [ 'success' => false, 'message' => 'Error creating attendance: ' . $e->getMessage() ];
        }
    }

    /**
     * Process guest payment
     * Inserts into unified_payments with NULL client_id
     */
    public function processGuestPayment($attendance_id, $amount, $paymentMethod, $remarks = '') {
        try {
            // Validate input
            if (!$attendance_id || !$amount || !$paymentMethod) {
                return [
                    'success' => false,
                    'message' => 'Missing required payment information'
                ];
            }

            // Get the attendance record
            $sql = "SELECT * FROM attendance_log WHERE attendance_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['id' => $attendance_id]);

            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Attendance record not found'
                ];
            }

            $record = $stmt->fetch();

            // Insert into unified_payments with NULL client_id for guest
            $paymentSql = "INSERT INTO unified_payments 
                          (client_id, payment_type, payment_date, amount, payment_method, 
                           payment_status, remarks, created_at, updated_at) 
                          VALUES (NULL, 'Daily', CURDATE(), :amount, :method, 
                                  'Paid', :remarks, NOW(), NOW())";

            $paymentStmt = $this->conn->prepare($paymentSql);
            $paymentStmt->execute([
                'amount' => $amount,
                'method' => $paymentMethod,
                'remarks' => $remarks
            ]);

            $payment_id = $this->conn->lastInsertId();

            // Update attendance_log with payment_id
            $updateSql = "UPDATE attendance_log 
                         SET payment_id = :payment_id, temp_payment_status = 'paid'
                         WHERE attendance_id = :attendance_id";
            
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([
                'payment_id' => $payment_id,
                'attendance_id' => $attendance_id
            ]);

            return [
                'success' => true,
                'message' => 'Guest payment processed successfully',
                'payment_id' => $payment_id,
                'amount' => $amount,
                'attendance_id' => $attendance_id
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' .  $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get member information
     */
    private function getMemberInfo($identifier) {
        try {
            $sql = "SELECT c.*, 
                    CONCAT(c.first_name, ' ', c.last_name) as full_name
                    FROM clients c 
                    WHERE c.client_id = :id OR c.username = :username
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'id' => $identifier,
                'username' => $identifier
            ]);
            
            return $stmt->rowCount() > 0 ? $stmt->fetch() : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get active membership
     */
    private function getActiveMembership($client_id) {
        try {
            $sql = "SELECT cm.*, m.plan_name, m.duration_days
                    FROM client_memberships cm
                    JOIN memberships m ON cm.membership_id = m.membership_id
                    WHERE cm. client_id = :id
                    AND cm.status = 'Active'
                    AND cm.end_date >= CURDATE()
                    ORDER BY cm.end_date DESC
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['id' => $client_id]);
            
            return $stmt->rowCount() > 0 ?  $stmt->fetch() : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if already checked in
     */
    private function isAlreadyCheckedIn($client_id) {
        try {
            $sql = "SELECT attendance_id FROM attendance_log 
                    WHERE client_id = :id
                    AND log_date = CURDATE() 
                    AND time_out IS NULL
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['id' => $client_id]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Record entry
     */
    private function recordEntry($client_id, $attendance_type, $membership_name = 'Member') {
        try {
            $sql = "INSERT INTO attendance_log 
                    (client_id, attendance_type, entry_method, log_date, time_in, 
                     check_in_timestamp, discount_type, status) 
                    VALUES (:id, :type, 'Receptionist', CURDATE(), CURTIME(), 
                            NOW(), 'Member', 'Completed')";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'id' => $client_id,
                'type' => $membership_name
            ]);
            
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            throw new Exception("Error recording entry: " . $e->getMessage());
        }
    }
    
    /**
     * Record exit
     */
    private function recordExit($attendance_id) {
        try {
            $sql = "UPDATE attendance_log 
                    SET time_out = CURTIME(), 
                        check_out_timestamp = NOW(),
                        status = 'Completed'
                    WHERE attendance_id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['id' => $attendance_id]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error recording exit: " . $e->getMessage());
        }
    }
    
    /**
     * Get rate by discount type
     */
    public function getRate($type) {
        try {
            $sql = "SELECT * FROM rates 
                    WHERE rate_name LIKE :type AND is_active = 1
                    LIMIT 1";
            
            $searchType = '%' . $type . '%';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['type' => $searchType]);
            
            if ($stmt->rowCount() > 0) {
                $rate = $stmt->fetch();
                return (float)$rate['price'];
            }
            
            // Default rates if not found in database
            $defaults = [
                'Regular' => 100.00,
                'Student' => 80.00
            ];
            
            return $defaults[$type] ?? 100.00;
        } catch (Exception $e) {
            return 100.00; // Return default on error
        }
    }

    /**
     * Create POS transaction for entry/exit
     * This records the payment/fee in the POS system
     */
    public function createPOSTransaction($client_id, $client_name, $amount, $paymentMethod = 'Cash', $transactionType = 'Walk-in Fee', $description = '', $attendance_id = null) {
        try {
            if (!$amount || $amount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid transaction amount'
                ];
            }

            // Get active POS session for the current employee
            $sessionSql = "SELECT session_id FROM pos_sessions 
                          WHERE employee_id = :employee_id AND status = 'Open'
                          ORDER BY start_time DESC
                          LIMIT 1";
            
            $sessionStmt = $this->conn->prepare($sessionSql);
            $sessionStmt->execute(['employee_id' => $_SESSION['employee_id']]);
            
            if ($sessionStmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'No active POS session. Please open a session first.',
                    'requires_session' => true
                ];
            }
            
            $session = $sessionStmt->fetch();
            $session_id = $session['session_id'];
            
            // Generate receipt number
            $receiptSql = "SELECT COUNT(*) as count FROM pos_transactions 
                          WHERE session_id = :session_id";
            $receiptStmt = $this->conn->prepare($receiptSql);
            $receiptStmt->execute(['session_id' => $session_id]);
            $count = $receiptStmt->fetch()['count'] + 1;
            $receipt_number = 'RCP-' . date('YmdHis') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            // Insert transaction into pos_transactions
            $transSql = "INSERT INTO pos_transactions (
                            session_id, employee_id, employee_name, 
                            client_id, client_name, 
                            transaction_type, description, amount, payment_method,
                            transaction_date, transaction_time, receipt_number, 
                            status, created_at, updated_at
                        ) VALUES (
                            :session_id, :employee_id, :employee_name,
                            :client_id, :client_name,
                            :transaction_type, :description, :amount, :payment_method,
                            CURDATE(), CURTIME(), :receipt_number,
                            'Completed', NOW(), NOW()
                        )";
            
            $transStmt = $this->conn->prepare($transSql);
            $transStmt->execute([
                'session_id' => $session_id,
                'employee_id' => $_SESSION['employee_id'],
                'employee_name' => $_SESSION['employee_name'],
                'client_id' => $client_id,
                'client_name' => $client_name,
                'transaction_type' => $transactionType,
                'description' => $description,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'receipt_number' => $receipt_number
            ]);
            
            $transaction_id = $this->conn->lastInsertId();
            
            // Update attendance log with transaction reference if attendance_id provided
            if ($attendance_id) {
                $updateSql = "UPDATE attendance_log 
                             SET payment_id = :transaction_id
                             WHERE attendance_id = :attendance_id";
                
                $updateStmt = $this->conn->prepare($updateSql);
                $updateStmt->execute([
                    'transaction_id' => $transaction_id,
                    'attendance_id' => $attendance_id
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'POS transaction created successfully',
                'transaction_id' => $transaction_id,
                'receipt_number' => $receipt_number,
                'amount' => $amount,
                'payment_method' => $paymentMethod
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating POS transaction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record member check-in with automatic POS transaction
     * For members who pay per visit
     */
    public function memberCheckInWithPayment($identifier, $paymentMethod = 'Cash', $rate = null) {
        try {
            // First do the regular check-in
            $checkInResult = $this->checkIn($identifier);
            
            if (!$checkInResult['success']) {
                return $checkInResult;
            }
            
            $member = $this->getMemberInfo($identifier);
            
            // Get rate if not provided
            if (!$rate) {
                $rate = $this->getRate('Member');
            }
            
            // Create POS transaction
            $posResult = $this->createPOSTransaction(
                $member['client_id'],
                $member['full_name'],
                $rate,
                $paymentMethod,
                'Daily Visit Fee',
                'Member daily gym fee',
                $checkInResult['attendance_id']
            );
            
            return [
                'success' => true,
                'message' => $checkInResult['message'],
                'attendance_id' => $checkInResult['attendance_id'],
                'pos_transaction' => $posResult
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record walk-in check-in with automatic POS transaction
     */
    public function walkInCheckInWithPayment($guestName, $guestPhone, $discountType, $paymentMethod = 'Cash') {
        try {
            // First do the walk-in check-in
            $checkInResult = $this->walkInCheckIn($guestName, $guestPhone, $discountType);
            
            if (!$checkInResult['success']) {
                return $checkInResult;
            }
            
            // Get the rate for this discount type
            $rate = $this->getRate($discountType);
            
            // Create POS transaction for walk-in
            $posResult = $this->createPOSTransaction(
                null, // No client_id for walk-ins
                $guestName,
                $rate,
                $paymentMethod,
                'Walk-in Fee',
                'Walk-in guest - ' . $discountType . ' rate',
                $checkInResult['attendance_id']
            );
            
            if ($posResult['success']) {
                return [
                    'success' => true,
                    'message' => $checkInResult['message'] . ' | POS transaction created',
                    'attendance_id' => $checkInResult['attendance_id'],
                    'pos_transaction' => $posResult,
                    'amount_paid' => $rate
                ];
            } else {
                // Walk-in checked in but POS transaction failed
                return [
                    'success' => true, // Still success for check-in
                    'message' => $checkInResult['message'] . ' | Warning: POS transaction could not be created',
                    'attendance_id' => $checkInResult['attendance_id'],
                    'pos_error' => $posResult['message'],
                    'amount' => $rate
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check and fix table structure
     */
    public function checkTableStructure() {
        try {
            // Get column info
            $sql = "SHOW COLUMNS FROM attendance_log WHERE Field = 'client_id'";
            $stmt = $this->conn->query($sql);
            $column = $stmt->fetch();
            
            // Check for triggers
            $triggerSql = "SHOW TRIGGERS WHERE `Table` = 'attendance_log'";
            $triggerStmt = $this->conn->query($triggerSql);
            $triggers = $triggerStmt->fetchAll();
            
            return [
                'success' => true,
                'column_info' => $column,
                'triggers' => $triggers,
                'note' => 'Guest payments: client_id = NULL in unified_payments'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify QR Code and return client_id
     */
    public function verifyQRCode($qrCodeHash) {
        try {
            // Validate QR code format
            if (empty($qrCodeHash)) {
                return [
                    'success' => false,
                    'message' => 'Invalid QR code format'
                ];
            }
            
            // Look up QR code in database
            $sql = "SELECT 
                        mq.client_id,
                        mq.is_active,
                        mq.valid_until,
                        c.first_name,
                        c.last_name,
                        c.status as client_status,
                        c.account_status
                    FROM member_qr_codes mq
                    INNER JOIN clients c ON mq.client_id = c.client_id
                    WHERE mq.qr_code_hash = :qr_hash
                    AND mq.is_active = 1
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['qr_hash' => $qrCodeHash]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'QR code not found or inactive'
                ];
            }
            
            $qrData = $stmt->fetch();
            
            // Check if QR code has expiration and is expired
            if ($qrData['valid_until'] && strtotime($qrData['valid_until']) < time()) {
                return [
                    'success' => false,
                    'message' => 'QR code has expired'
                ];
            }
            
            // Check if client account is active
            if ($qrData['client_status'] !== 'Active' || $qrData['account_status'] !== 'Active') {
                return [
                    'success' => false,
                    'message' => 'Member account is not active'
                ];
            }
            
            // Check if member has active membership
            $membershipSql = "SELECT cm.*, m.plan_name
                             FROM client_memberships cm
                             INNER JOIN memberships m ON cm.membership_id = m.membership_id
                             WHERE cm.client_id = :client_id
                             AND cm.status = 'Active'
                             AND cm.end_date >= CURDATE()
                             LIMIT 1";
            
            $membershipStmt = $this->conn->prepare($membershipSql);
            $membershipStmt->execute(['client_id' => $qrData['client_id']]);
            
            if ($membershipStmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'No active membership found for this member'
                ];
            }
            
            $membership = $membershipStmt->fetch();
            
            // Update last_used timestamp
            $updateSql = "UPDATE member_qr_codes 
                         SET last_used = NOW() 
                         WHERE qr_code_hash = :qr_hash";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute(['qr_hash' => $qrCodeHash]);
            
            // Return success with client data
            return [
                'success' => true,
                'client_id' => $qrData['client_id'],
                'member_name' => $qrData['first_name'] . ' ' . $qrData['last_name'],
                'membership_plan' => $membership['plan_name'],
                'membership_end_date' => $membership['end_date'],
                'message' => 'QR code verified successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error verifying QR code: ' . $e->getMessage()
            ];
        }
    }
}

// Handle requests
try {
    if (! isset($conn)) {
        throw new Exception('Database connection not available');
    }
    
    $handler = new ImprovedEntryExitHandler($conn);
    
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        case 'get_stats':
            echo json_encode($handler->getStats());
            break;
            
        case 'get_currently_inside':
            echo json_encode($handler->getCurrentlyInside());
            break;
            
        case 'get_today_log':
            echo json_encode($handler->getTodayLog());
            break;
            
        case 'get_log_by_date':
            $startDate = $_GET['start_date'] ?? date('Y-m-d');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            echo json_encode($handler->getLogByDateRange($startDate, $endDate));
            break;
            
        case 'check_in':
            $identifier = $_POST['identifier'] ?? '';
            echo json_encode($handler->checkIn($identifier));
            break;
            
        case 'check_out':
            $identifier = $_POST['identifier'] ?? '';
            echo json_encode($handler->checkOut($identifier));
            break;
            
        case 'walkin_checkin':
            $guestName = $_POST['guest_name'] ?? '';
            $guestPhone = $_POST['guest_phone'] ?? '';
            $discountType = $_POST['discount_type'] ?? 'Regular';
            echo json_encode($handler->walkInCheckIn($guestName, $guestPhone, $discountType));
            break;

        case 'walkin_checkin_with_payment':
            $guestName = $_POST['guest_name'] ?? '';
            $guestPhone = $_POST['guest_phone'] ?? '';
            $discountType = $_POST['discount_type'] ?? 'Regular';
            $paymentMethod = $_POST['payment_method'] ?? 'Cash';
            echo json_encode($handler->walkInCheckInWithPayment($guestName, $guestPhone, $discountType, $paymentMethod));
            break;

        case 'member_checkin_with_payment':
            $identifier = $_POST['identifier'] ?? '';
            $paymentMethod = $_POST['payment_method'] ?? 'Cash';
            $rate = $_POST['rate'] ?? null;
            echo json_encode($handler->memberCheckInWithPayment($identifier, $paymentMethod, $rate));
            break;

        case 'create_member_attendance':
            $client_id = $_POST['client_id'] ?? '';
            echo json_encode($handler->createMemberAttendance($client_id));
            break;

        case 'process_guest_payment':
            $attendance_id = $_POST['attendance_id'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $paymentMethod = $_POST['payment_method'] ?? '';
            $remarks = $_POST['remarks'] ?? '';
            echo json_encode($handler->processGuestPayment($attendance_id, $amount, $paymentMethod, $remarks));
            break;

        case 'create_pos_transaction':
            $client_id = $_POST['client_id'] ?? null;
            $client_name = $_POST['client_name'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $paymentMethod = $_POST['payment_method'] ?? 'Cash';
            $transactionType = $_POST['transaction_type'] ?? 'Walk-in Fee';
            $description = $_POST['description'] ?? '';
            $attendance_id = $_POST['attendance_id'] ?? null;
            echo json_encode($handler->createPOSTransaction($client_id, $client_name, $amount, $paymentMethod, $transactionType, $description, $attendance_id));
            break;
            
        case 'get_rate':
            $type = $_GET['type'] ?? 'Regular';
            $amount = $handler->getRate($type);
            echo json_encode([
                'success' => true,
                'amount' => $amount
            ]);
            break;
            
        case 'check_table':
            echo json_encode($handler->checkTableStructure());
            break;
            
        case 'verify_qr_code':
            $qrCodeHash = $_POST['qr_code_hash'] ?? '';
            echo json_encode($handler->verifyQRCode($qrCodeHash));
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action: ' . $action
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

// PDO connections close automatically when script ends
?>