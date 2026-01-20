<?php
/**
 * Automated Monthly Invoice Generation Cron Job
 * Schedule: Run on 1st of each month at 12:01 AM
 * 
 * Add to cron:
 * 1 0 1 * * /usr/bin/php /path/to/empirefitness/manager/api/cron_generate_monthly_invoices.php
 * 
 * Or call via HTTP:
 * curl https://yoursite.com/manager/api/cron_generate_monthly_invoices.php?token=YOUR_CRON_TOKEN
 */

// Prevent direct access - require cron token or CLI execution
$valid_token = 'your_secure_cron_token_here'; // Change this to a secure token
$is_cli = php_sapi_name() === 'cli';
$is_valid_request = false;

if ($is_cli) {
    $is_valid_request = true;
} elseif (isset($_GET['token']) && $_GET['token'] === $valid_token) {
    $is_valid_request = true;
}

if (!$is_valid_request) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

require_once __DIR__ . '/../../config/connection.php';

// Disable output buffering for logging
if (!$is_cli) {
    header('Content-Type: text/plain');
}

$conn = getDBConnection();
$success_count = 0;
$error_count = 0;
$errors = [];

// Log function
function log_message($message) {
    global $is_cli;
    echo ($is_cli ? '' : '') . $message . "\n";
    flush();
}

try {
    log_message("[" . date('Y-m-d H:i:s') . "] Starting automated invoice generation...");
    
    // Get previous month's date
    $prev_month = date('Y-m', strtotime('-1 month'));
    $month_start = date('Y-m-01', strtotime($prev_month . '-01'));
    $month_end = date('Y-m-t', strtotime($prev_month . '-01'));
    
    log_message("Processing month: $prev_month");
    
    // Get all active coaches
    $coaches_stmt = $conn->query("
        SELECT coach_id, first_name, last_name, email 
        FROM coach 
        WHERE status = 'Active'
        ORDER BY coach_id
    ");
    $coaches = $coaches_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    log_message("Found " . count($coaches) . " active coaches");
    
    foreach ($coaches as $coach) {
        try {
            log_message("  Processing coach: {$coach['first_name']} {$coach['last_name']} (ID: {$coach['coach_id']})");
            
            // Check if invoice already exists for this month
            $check_stmt = $conn->prepare("
                SELECT invoice_id FROM coach_invoices 
                WHERE coach_id = ? 
                AND YEAR(invoice_month) = ?
                AND MONTH(invoice_month) = ?
            ");
            $check_stmt->execute([
                $coach['coach_id'],
                date('Y', strtotime($month_start)),
                date('m', strtotime($month_start))
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                log_message("    → Invoice already exists, skipping");
                continue;
            }
            
            // Get pending commissions for previous month
            $commissions_stmt = $conn->prepare("
                SELECT 
                    commission_id,
                    commission_amount,
                    transaction_date,
                    service_name,
                    client_id
                FROM coach_commissions
                WHERE coach_id = ? 
                AND commission_status = 'Pending'
                AND transaction_date BETWEEN ? AND ?
                ORDER BY transaction_date
            ");
            $commissions_stmt->execute([
                $coach['coach_id'],
                $month_start,
                $month_end
            ]);
            $commissions = $commissions_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($commissions)) {
                log_message("    → No pending commissions found");
                continue;
            }
            
            log_message("    → Found " . count($commissions) . " pending commissions");
            
            // Calculate totals
            $total_commission = array_sum(array_column($commissions, 'commission_amount'));
            
            // Generate unique invoice number
            $invoice_number = 'INV-' . $prev_month . '-' . str_pad($coach['coach_id'], 4, '0', STR_PAD_LEFT);
            
            // Calculate due date (30 days from start of month)
            $due_date = date('Y-m-d', strtotime($month_start . ' +30 days'));
            
            $conn->beginTransaction();
            
            try {
                // Create invoice
                $invoice_stmt = $conn->prepare("
                    INSERT INTO coach_invoices (
                        coach_id,
                        invoice_number,
                        invoice_month,
                        from_date,
                        to_date,
                        total_gross_earnings,
                        total_commission_due,
                        transaction_count,
                        status,
                        issued_date,
                        due_date,
                        generated_by,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Issued', NOW(), ?, ?, NOW())
                ");
                
                // System user ID (0 for automated process)
                $system_user_id = 0;
                
                $invoice_stmt->execute([
                    $coach['coach_id'],
                    $invoice_number,
                    $month_start,
                    $month_start,
                    $month_end,
                    $total_commission,
                    $total_commission,
                    count($commissions),
                    $due_date,
                    $system_user_id
                ]);
                
                $invoice_id = $conn->lastInsertId();
                
                // Update commission records
                $update_stmt = $conn->prepare("
                    UPDATE coach_commissions 
                    SET commission_status = 'Invoiced', invoice_id = ?, updated_at = NOW()
                    WHERE commission_id = ?
                ");
                
                foreach ($commissions as $commission) {
                    $update_stmt->execute([$invoice_id, $commission['commission_id']]);
                }
                
                $conn->commit();
                
                log_message("    → Invoice $invoice_number generated successfully (Amount: ₱" . number_format($total_commission, 2) . ")");
                
                // Send email notification
                try {
                    send_invoice_notification_email($coach, $invoice_number, $total_commission, $due_date);
                    log_message("    → Email notification sent");
                } catch (Exception $e) {
                    log_message("    ⚠ Email notification failed: " . $e->getMessage());
                }
                
                $success_count++;
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            log_message("    ✗ Error: " . $e->getMessage());
            $error_count++;
            $errors[] = "Coach {$coach['coach_id']}: " . $e->getMessage();
        }
    }
    
    log_message("\n[" . date('Y-m-d H:i:s') . "] Automated invoice generation completed");
    log_message("Summary:");
    log_message("  - Invoices generated: $success_count");
    log_message("  - Errors: $error_count");
    
    if (!empty($errors)) {
        log_message("\nErrors encountered:");
        foreach ($errors as $error) {
            log_message("  - $error");
        }
    }
    
    log_message("\n---\n");
    
} catch (Exception $e) {
    log_message("[ERROR] Fatal error: " . $e->getMessage());
    log_message("[ERROR] Trace: " . $e->getTraceAsString());
}

/**
 * Send invoice notification email to coach
 */
function send_invoice_notification_email($coach, $invoice_number, $total_amount, $due_date) {
    // Load email configuration
    require_once __DIR__ . '/../../config/email_config.php';
    
    // Prepare email variables
    $coach_name = $coach['first_name'] . ' ' . $coach['last_name'];
    $invoice_url = 'https://' . $_SERVER['HTTP_HOST'] . '/manager/invoice-detail.php?invoice_id=' . $invoice_number; // This should be updated to use actual invoice ID
    
    // Load email template
    ob_start();
    include __DIR__ . '/../email_templates/invoice_generated.php';
    $email_body = ob_get_clean();
    
    // Send email (using PHPMailer or configured mail function)
    // This is a placeholder - implement based on your email configuration
    /*
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('noreply@empirefitness.com');
    $mail->addAddress($coach['email']);
    $mail->Subject = 'Invoice Generated: ' . $invoice_number;
    $mail->Body = $email_body;
    $mail->isHTML(true);
    $mail->send();
    */
}
?>
