<?php
session_start();

// Check authentication
if (!isset($_SESSION['employee_id']) || ($_SESSION['employee_role'] !== 'Manager' && $_SESSION['employee_role'] !== 'Admin')) {
    http_response_code(403);
    echo "Unauthorized access";
    exit;
}

require_once '../config/connection.php';
$conn = getDBConnection();

$invoice_id = intval($_GET['invoice_id'] ?? 0);
if (!$invoice_id) {
    http_response_code(400);
    echo "Invoice ID required";
    exit;
}

try {
    // Fetch invoice details
    $invoice_stmt = $conn->prepare("
        SELECT 
            ci.*,
            CONCAT(c.first_name, ' ', c.last_name) as coach_name,
            c.email as coach_email,
            c.phone as coach_phone
        FROM coach_invoices ci
        JOIN coach c ON ci.coach_id = c.coach_id
        WHERE ci.invoice_id = ?
    ");
    $invoice_stmt->execute([$invoice_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        echo "Invoice not found";
        exit;
    }

    // Fetch line items
    $items_stmt = $conn->prepare("
        SELECT 
            cc.*,
            CONCAT(cl.first_name, ' ', cl.last_name) as client_name
        FROM coach_commissions cc
        LEFT JOIN clients cl ON cc.client_id = cl.client_id
        WHERE cc.invoice_id = ?
        ORDER BY cc.transaction_date
    ");
    $items_stmt->execute([$invoice_id]);
    $line_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    http_response_code(500);
    echo "Database error: " . $e->getMessage();
    exit;
}

// Generate HTML for PDF
$html = generateInvoiceHTML($invoice, $line_items);

// Output HTML (for now, can be extended with PDF library)
// For production, use TCPDF or FPDF
header('Content-Type: text/html; charset=utf-8');
echo $html;

function generateInvoiceHTML($invoice, $items) {
    $total_gross = array_sum(array_column($items, 'gross_amount'));
    $total_commission = array_sum(array_column($items, 'commission_amount'));
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {$invoice['invoice_number']}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .invoice-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px;
            background: white;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .header-left h1 {
            font-size: 32px;
            color: #222;
            margin-bottom: 10px;
        }
        
        .invoice-number {
            font-size: 16px;
            color: #666;
            font-weight: 600;
        }
        
        .header-right {
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #d1e7dd;
            color: #0f5132;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .details-section {
            margin-bottom: 40px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .details-section h4 {
            font-size: 12px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .detail-row .label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-row .value {
            color: #222;
        }
        
        .line-items-section {
            margin-bottom: 40px;
        }
        
        .line-items-section h3 {
            font-size: 16px;
            font-weight: 600;
            color: #222;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .total-row .label {
            min-width: 200px;
            text-align: right;
            color: #666;
            padding-right: 20px;
        }
        
        .total-row .value {
            min-width: 120px;
            text-align: right;
            font-weight: 600;
            color: #222;
        }
        
        .grand-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 10px;
            font-size: 16px;
        }
        
        .grand-total .label {
            color: #222;
            font-weight: 700;
        }
        
        .grand-total .value {
            color: #007bff;
            font-size: 18px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .invoice-container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="header-left">
                <h1>INVOICE</h1>
                <p class="invoice-number">{$invoice['invoice_number']}</p>
            </div>
            <div class="header-right">
                <div class="status-badge">{$invoice['status']}</div>
            </div>
        </div>
        
        <div class="details-grid">
            <div class="details-section">
                <h4>Coach Information</h4>
                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span class="value">{$invoice['coach_name']}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span class="value">{$invoice['coach_email']}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone:</span>
                    <span class="value">{$invoice['coach_phone'] ?? 'N/A'}</span>
                </div>
            </div>
            
            <div class="details-section">
                <h4>Invoice Information</h4>
                <div class="detail-row">
                    <span class="label">Issue Date:</span>
                    <span class="value">
HTML;

    $html .= date('M d, Y', strtotime($invoice['issued_date'])) . <<<HTML
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">Due Date:</span>
                    <span class="value">
HTML;

    $html .= date('M d, Y', strtotime($invoice['due_date'])) . <<<HTML
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">Period:</span>
                    <span class="value">
HTML;

    $html .= date('M d, Y', strtotime($invoice['from_date'])) . ' - ' . date('M d, Y', strtotime($invoice['to_date'])) . <<<HTML
                    </span>
                </div>
            </div>
        </div>
        
        <div class="line-items-section">
            <h3>Commission Breakdown</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Client</th>
                        <th>Gross Amount</th>
                        <th>Commission Rate</th>
                        <th>Commission Amount</th>
                    </tr>
                </thead>
                <tbody>
HTML;

    foreach ($items as $item) {
        $html .= <<<HTML
                    <tr>
                        <td>
HTML;
        $html .= date('M d, Y', strtotime($item['transaction_date'])) . <<<HTML
                        </td>
                        <td>
HTML;
        $html .= htmlspecialchars($item['service_name'] ?? 'N/A') . <<<HTML
                        </td>
                        <td>
HTML;
        $html .= htmlspecialchars($item['client_name'] ?? 'N/A') . <<<HTML
                        </td>
                        <td>₱
HTML;
        $html .= number_format($item['gross_amount'], 2) . <<<HTML
                        </td>
                        <td>
HTML;
        $html .= number_format($item['commission_rate'], 2) . <<<HTML
%                        </td>
                        <td>₱
HTML;
        $html .= number_format($item['commission_amount'], 2) . <<<HTML
                        </td>
                    </tr>
HTML;
    }

    $html .= <<<HTML
                </tbody>
            </table>
            
            <div class="total-row">
                <span class="label">Total Gross:</span>
                <span class="value">₱
HTML;

    $html .= number_format($total_gross, 2) . <<<HTML
                </span>
            </div>
            
            <div class="total-row">
                <span class="label">Total Commission:</span>
                <span class="value">₱
HTML;

    $html .= number_format($total_commission, 2) . <<<HTML
                </span>
            </div>
            
            <div class="total-row grand-total">
                <span class="label">Amount Due:</span>
                <span class="value">₱
HTML;

    $html .= number_format($invoice['total_commission_due'], 2) . <<<HTML
                </span>
            </div>
        </div>
        
        <div class="footer">
            <p>Generated on 
HTML;

    $html .= date('M d, Y \a\t h:i A') . <<<HTML
</p>
            <p>This is an automatically generated invoice. Please retain for your records.</p>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}
?>
