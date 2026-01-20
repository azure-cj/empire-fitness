<?php
/**
 * Payment Received Email Template
 * Sent to confirm payment has been received
 */

$coach_name = $coach_name ?? 'Coach';
$invoice_number = $invoice_number ?? 'INV-000000';
$payment_amount = $payment_amount ?? 0;
$payment_date = $payment_date ?? date('M d, Y');
$payment_method = $payment_method ?? 'Not specified';
$payment_reference = $payment_reference ?? '';
$remaining_balance = $remaining_balance ?? 0;

ob_start();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .amount { font-size: 24px; font-weight: bold; color: #28a745; }
        .success { background: #d1e7dd; border: 1px solid #badbcc; padding: 15px; border-radius: 5px; margin: 20px 0; color: #0f5132; }
        table { width: 100%; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Payment Received</h1>
        </div>
        <div class="content">
            <p>Hello <?php echo htmlspecialchars($coach_name); ?>,</p>
            
            <div class="success">
                <strong>✓ Payment Confirmed</strong><br>
                Your payment has been successfully received and processed.
            </div>
            
            <table>
                <tr>
                    <th>Payment Details</th>
                </tr>
                <tr>
                    <td><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice_number); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Amount:</strong> <span class="amount">₱<?php echo number_format($payment_amount, 2); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Payment Date:</strong> <?php echo htmlspecialchars($payment_date); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment_method); ?></td>
                </tr>
                <?php if (!empty($payment_reference)): ?>
                <tr>
                    <td><strong>Reference Number:</strong> <?php echo htmlspecialchars($payment_reference); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($remaining_balance > 0): ?>
                <tr>
                    <td><strong>Remaining Balance:</strong> ₱<?php echo number_format($remaining_balance, 2); ?></td>
                </tr>
                <?php else: ?>
                <tr style="background: #d1e7dd;">
                    <td><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">✓ FULLY PAID</span></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <p>Thank you for your prompt payment. This helps us continue to provide excellent service.</p>
            
            <p>Please keep this email for your records.</p>
            
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
