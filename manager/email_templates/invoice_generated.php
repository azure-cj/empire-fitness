<?php
/**
 * Invoice Generated Email Template
 * Sent when a new invoice is generated for a coach
 */

$coach_name = $coach_name ?? 'Coach';
$invoice_number = $invoice_number ?? 'INV-000000';
$total_due = $total_due ?? 0;
$due_date = $due_date ?? date('M d, Y');
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
        .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .amount { font-size: 24px; font-weight: bold; color: #007bff; }
        .button { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        table { width: 100%; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Generated</h1>
        </div>
        <div class="content">
            <p>Hello <?php echo htmlspecialchars($coach_name); ?>,</p>
            
            <p>A new commission invoice has been generated for you.</p>
            
            <table>
                <tr>
                    <th>Invoice Details</th>
                </tr>
                <tr>
                    <td><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice_number); ?></td>
                </tr>
                <tr>
                    <td><strong>Amount Due:</strong> <span class="amount">₱<?php echo number_format($total_due, 2); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Due Date:</strong> <?php echo htmlspecialchars($due_date); ?></td>
                </tr>
            </table>
            
            <p>Please review your invoice and arrange payment according to your payment method preferences.</p>
            
            <center>
                <a href="<?php echo htmlspecialchars($invoice_url); ?>" class="button">View Invoice</a>
            </center>
            
            <p>If you have any questions about this invoice, please contact the management team.</p>
            
            <p>Best regards,<br>Empire Fitness Management</p>
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
