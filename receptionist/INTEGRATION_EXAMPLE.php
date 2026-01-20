// INTEGRATION EXAMPLE: How to connect process_payment.php with manage_entry_exit.php
// Add this to your entry-exit.js file or inline script

/**
 * Open payment modal from entry/exit system
 * Called after guest checks out
 */
function openPaymentModalFromCheckout(attendanceId, guestName, dailyRate = null) {
    // Navigate to payment page with parameters
    // Option 1: New tab/window
    window.open(
        'process_payment.php?attendance_id=' + attendanceId + 
        '&guest_name=' + encodeURIComponent(guestName) +
        (dailyRate ? '&amount=' + dailyRate : ''),
        '_blank'
    );
    
    // Option 2: Modal on same page (requires process_payment.php to export functions)
    // This would need JavaScript function exports
}

/**
 * Quick payment trigger from checkout button
 * Call this when user clicks "Process Payment" in currently-inside section
 */
function quickPaymentForCheckout(attendanceId, clientName, clientId = null) {
    // Pre-fill payment form and open modal
    // This requires the modal functions to be available in global scope
    
    document.getElementById('payment-client-type').value = clientId ? 'member' : 'walkin';
    document.getElementById('payment-client-id').value = clientId || '';
    document.getElementById('payment-type').value = 'daily_entrance';
    document.getElementById('payment-amount').value = '50'; // Set your default daily rate
    
    // Store attendance ID for later use
    document.getElementById('attendance_id').value = attendanceId;
    
    // Open modal
    openPaymentModal();
}

/**
 * In manage_entry_exit.php, modify the currently-inside section button to:
 */
// <button class="btn btn-sm btn-success" onclick="quickPaymentForCheckout(
//     <?php echo $record['attendance_id']; ?>,
//     '<?php echo htmlspecialchars($record['name']); ?>',
//     <?php echo $record['client_id'] ? $record['client_id'] : 'null'; ?>
// )">
//     <i class="fas fa-credit-card"></i> Process Payment
// </button>

/**
 * Alternative: Direct link approach (simplest)
 * Just add this link/button in the "Currently Inside" table action column:
 */
// <a href="process_payment.php?attendance_id=<?php echo $record['attendance_id']; ?>&guest_name=<?php echo urlencode($record['name']); ?>&client_id=<?php echo $record['client_id'] ?? 0; ?>" 
//    class="btn btn-sm btn-success" target="_blank">
//     <i class="fas fa-credit-card"></i> Process Payment
// </a>

/**
 * In manage_entry_exit.php HTML table, the action column might look like:
 */
?>
<!-- Example currently-inside section -->
<div class="currently-inside-grid" id="currently-inside-grid">
    <?php foreach ($currently_inside as $person): ?>
        <div class="person-card">
            <div class="person-name"><?php echo htmlspecialchars($person['name']); ?></div>
            <div class="person-time">
                Checked in: <?php echo date('h:i A', strtotime($person['time_in'])); ?>
            </div>
            <div class="person-actions" style="display: flex; gap: 8px; margin-top: 10px;">
                <a href="process_payment.php?attendance_id=<?php echo $person['attendance_id']; ?>&guest_name=<?php echo urlencode($person['name']); ?>&client_id=<?php echo $person['client_id'] ?? 0; ?>" 
                   class="btn btn-sm btn-success" target="_blank">
                    <i class="fas fa-credit-card"></i> Payment
                </a>
                <button class="btn btn-sm btn-danger" onclick="processCheckOut(<?php echo $person['attendance_id']; ?>)">
                    <i class="fas fa-sign-out-alt"></i> Check Out
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

/**
 * USING WINDOW.OPENER (for modal popup approach)
 * 
 * In manage_entry_exit.php, open payment in a window with reference:
 */
function openPaymentWindow(attendanceId, guestName, clientId) {
    const paymentWindow = window.open(
        'process_payment.php?attendance_id=' + attendanceId + 
        '&guest_name=' + encodeURIComponent(guestName) +
        '&client_id=' + (clientId || 0),
        'paymentWindow',
        'width=800,height=600,resizable=yes'
    );
    
    // Wait for window to load and set up communication
    if (window.opener === null) {
        // We're the opened window - this is process_payment.php
        // Do nothing special needed
    } else {
        // We're the opener - set up listener for close
        paymentWindow.addEventListener('beforeunload', function() {
            // Refresh the currently-inside list when payment window closes
            refreshCurrentlyInside();
        });
    }
}

// HTML button:
// <button class="btn btn-sm btn-success" 
//         onclick="openPaymentWindow(<?php echo $person['attendance_id']; ?>, '<?php echo htmlspecialchars($person['name']); ?>', <?php echo $person['client_id'] ?? 0; ?>)">
//     <i class="fas fa-credit-card"></i> Payment
// </button>

/**
 * FETCH FROM SAME PAGE (AJAX approach)
 * 
 * Load payment modal content via AJAX into existing modal on the same page:
 */
function loadPaymentModal(attendanceId, guestName, clientId, amount = null) {
    fetch('process_payment_modal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            attendance_id: attendanceId,
            guest_name: guestName,
            client_id: clientId,
            amount: amount
        })
    })
    .then(r => r.text())
    .then(html => {
        document.getElementById('payment-modal-content').innerHTML = html;
        document.getElementById('payment-modal-overlay').classList.add('active');
    });
}

// This would require creating a separate process_payment_modal.php file
// that returns just the modal HTML without the page structure


/**
 * RECOMMENDED APPROACH:
 * 
 * Simple link approach - open process_payment.php in new window/tab:
 * 
 * In manage_entry_exit.php, add action button:
 */
// <a href="process_payment.php?attendance_id=<?php echo $attendance_id; ?>&guest_name=<?php echo urlencode($guest_name); ?>&client_id=<?php echo $client_id; ?>" 
//    class="btn btn-sm btn-success"
//    target="_blank">
//     <i class="fas fa-credit-card"></i> Process Payment
// </a>

// Advantages:
// ✓ Simple to implement
// ✓ No complex AJAX needed
// ✓ Modal is fully self-contained
// ✓ User can keep entry/exit page open
// ✓ Payment page loads independently
// ✓ Easy to test and debug


/**
 * If using the link approach, add this to process_payment.php to pre-fill form:
 */
?>

<?php
// At the top of process_payment.php, after session check, add:

$attendance_id = $_GET['attendance_id'] ?? '';
$guest_name = $_GET['guest_name'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$amount = $_GET['amount'] ?? '';

// In JavaScript section, add:
?>

<script>
// Pre-fill form if coming from entry/exit
document.addEventListener('DOMContentLoaded', function() {
    const attendanceId = '<?php echo htmlspecialchars($attendance_id); ?>';
    const guestName = '<?php echo htmlspecialchars($guest_name); ?>';
    const clientId = '<?php echo htmlspecialchars($client_id); ?>';
    const amount = '<?php echo htmlspecialchars($amount); ?>';
    
    if (attendanceId) {
        // Auto-fill and open modal
        if (clientId) {
            document.getElementById('payment-client-type').value = 'member';
            document.getElementById('payment-client-id').value = clientId;
        } else {
            document.getElementById('payment-client-type').value = 'walkin';
        }
        
        document.getElementById('payment-type').value = 'daily_entrance';
        if (amount) {
            document.getElementById('payment-amount').value = amount;
        }
        
        // Store attendance ID for handler
        document.getElementById('modal-client-id').value = attendanceId;
        document.getElementById('modal-client-name').value = guestName;
        
        // Optional: Auto-open modal after page loads
        // setTimeout(() => openPaymentModal(), 500);
    }
});
</script>

<?php
/**
 * COMPLETE HTML EXAMPLE for currently-inside section:
 */
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> Currently Inside the Gym</h3>
    </div>
    <div class="card-body">
        <div class="currently-inside-grid" id="currently-inside-grid">
            <?php if (!empty($inside)): ?>
                <?php foreach ($inside as $person): ?>
                    <div class="person-card" style="border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px;">
                        <div style="font-weight: 600; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($person['name']); ?>
                        </div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            <i class="fas fa-clock"></i>
                            <?php echo date('h:i A', strtotime($person['time_in'])); ?>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 12px;">
                            <a href="process_payment.php?attendance_id=<?php echo $person['attendance_id']; ?>&guest_name=<?php echo urlencode($person['name']); ?>&client_id=<?php echo $person['client_id'] ?? 0; ?>"
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-credit-card"></i> Payment
                            </a>
                            <button class="btn btn-sm btn-danger" onclick="processCheckOut(<?php echo $person['attendance_id']; ?>)">
                                <i class="fas fa-sign-out-alt"></i> Check Out
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 30px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                    <p>No one is currently inside</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
