<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors directly; we'll return them as JSON

// Check if user is logged in and has admin role
if (!isset($_SESSION['employee_id']) || $_SESSION['employee_role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin privileges required.'
    ]);
    exit;
}

// Capture any output that might interfere with JSON
ob_start();

require_once '../includes/email_functions.php';

$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'test_email':
        $email = $_POST['email'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        
        if (!$email || !$subject || !$body) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            break;
        }
        
        $result = sendEmail($email, $subject, $body);
        $debug = ob_get_clean();
        
        // Include debug output if available
        if ($debug && defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $result['debug'] = $debug;
        }
        
        echo json_encode($result);
        break;

    case 'welcome_email':
        $email = $_POST['email'] ?? '';
        $name = $_POST['name'] ?? '';
        $code = $_POST['code'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$name || !$code || !$password) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            break;
        }
        
        $result = sendWelcomeEmail($email, $name, $code, $password);
        $debug = ob_get_clean();
        
        if ($debug && defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $result['debug'] = $debug;
        }
        
        echo json_encode($result);
        break;

    case 'employee_email':
        $email = $_POST['email'] ?? '';
        $name = $_POST['name'] ?? '';
        $code = $_POST['code'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        
        if (!$email || !$name || !$code || !$password || !$role) {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            break;
        }
        
        $result = sendEmployeeCredentialsEmail($email, $name, $code, $password, $role);
        $debug = ob_get_clean();
        
        if ($debug && defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $result['debug'] = $debug;
        }
        
        echo json_encode($result);
        break;

    default:
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}
?>
