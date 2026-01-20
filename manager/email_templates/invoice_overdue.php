<?php
/**
 * Invoice Overdue Email Template
 * Sent when an invoice is overdue
 */

$coach_name = $coach_name ?? 'Coach';
$invoice_number = $invoice_number ?? 'INV-000000';
$amount_due = $amount_due ?? 0;
$due_date = $due_date ?? date('M d, Y');
$days_overdue = $days_overdue ?? 0;
$invoice_url = $invoice_url ?? '#';

ob_start();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .amount { font-size: 24px; font-weight: bold; color: #dc3545; }
        .alert { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0; color: #842029; }
        .button { display: inline-block; background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        table { width: 100%; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Overdue Notice</h1>
        </div>
        <div class="content">
            <p>Hello <?php echo htmlspecialchars($coach_name); ?>,</p>
            
            <div class="alert">
                <strong>🚨 URGENT: Payment Overdue</strong><br>
                Your invoice is now <?php echo htmlspecialchars($days_overdue); ?> day(s) overdue.
            </div>
            
            <table>
                <tr>
                    <th>Invoice Details</th>
                </tr>
                <tr>
                    <td><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice_number); ?></td>
                </tr>
                <tr>
                    <td><strong>Amount Due:</strong> <span class="amount">₱<?php echo number_format($amount_due, 2); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Original Due Date:</strong> <?php echo htmlspecialchars($due_date); ?></td>
                </tr>
                <tr>
                    <td><strong>Days Overdue:</strong> <?php echo htmlspecialchars($days_overdue); ?></td>
                </tr>
            </table>
            
            <p>We have not yet received payment for the above invoice. <strong>Immediate action is required.</strong></p>
            
            <p>Please arrange payment immediately to avoid any service interruptions or penalties.</p>
            
            <center>
                <a href="<?php echo htmlspecialchars($invoice_url); ?>" class="button">Pay Now</a>
            </center>
            
            <p>If you have already submitted payment, please contact us immediately with your payment confirmation.</p>
            
            <p>For payment assistance or to discuss payment arrangements, please contact the management team.</p>
            
            <p>Thank you,<br>Empire Fitness Management</p>
        </div>
        <div class="footer">
            <p>&copy; 2026 Empire Fitness. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

<?php
$html = ob_get_clean();
return $html;
?>
