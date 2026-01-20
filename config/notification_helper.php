<?php
/**
 * Empire Fitness - Real-time Notification Helper
 * Sends notifications to Socket.IO server via REST API
 * Usage: include_once '../config/notification_helper.php';
 *        sendNotification('assignment:created', 'Coach assigned to member');
 */

if (!function_exists('sendNotification')) {
    /**
     * Send notification to Socket.IO server
     * 
     * @param string $type - Notification type (e.g., 'assignment:created')
     * @param string $message - Notification message
     * @param array $data - Additional data
     * @param integer $userId - Target user ID (optional)
     * @return boolean - Success status
     */
    function sendNotification($type, $message, $data = [], $userId = null) {
        $serverUrl = 'http://localhost:3001/api/notify/user/';
        $targetUserId = $userId ?? ($_SESSION['employee_id'] ?? 1);

        // Build notification title from type
        $titleMap = [
            'assignment:created' => ' Assignment Created',
            'assignment:updated' => 'Assignment Updated',
            'assignment:removed' => ' Assignment Removed',
            'schedule:created' => ' Schedule Created',
            'schedule:updated' => ' Schedule Updated',
            'schedule:cancelled' => ' Schedule Cancelled',
            'member:checkin' => ' Member Checked In',
            'member:checkout' => ' Member Checked Out',
            'member:approved' => '✅ Member Approved',
            'assessment:created' => ' Assessment Created',
            'payment:received' => ' Payment Received',
            'payment:pending' => ' Payment Pending',
            'payment:failed' => ' Payment Failed',
            'member:registered' => ' New Member',
            'coach:status_changed' => ' Coach Status Changed',
        ];

        $title = $titleMap[$type] ?? '📬 ' . ucfirst(str_replace(':', ' - ', $type));

        $payload = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Merge additional data
        if (!empty($data)) {
            $payload = array_merge($payload, $data);
        }

        try {
            $ch = curl_init($serverUrl . $targetUserId);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (Exception $e) {
            // Silently fail - don't break page functionality if notifications are down
            error_log('Notification send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all managers
     * 
     * @param string $type - Notification type
     * @param string $message - Notification message
     * @param string $severity - Severity level (info, warning, critical)
     * @return boolean - Success status
     */
    function sendManagerNotification($type, $message, $severity = 'info') {
        $serverUrl = 'http://localhost:3001/api/notify/managers';

        $payload = [
            'title' => $type,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        try {
            $ch = curl_init($serverUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (Exception $e) {
            error_log('Manager notification send failed: ' . $e->getMessage());
            return false;
        }
    }
}
?>
