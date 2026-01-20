// Modal Management JavaScript

// Show walk-in guest check-in modal
function showWalkInModal() {
    const modal = document.getElementById('walkin-modal');
    modal.classList.add('active');
    
    // Reset to step 1
    window.currentStep = 1;
    window.isMemberWalkIn = false;
    
    // Update dropdown to show guest rates only
    if (typeof updateRatesDropdown === 'function') {
        updateRatesDropdown(false);
    }
    
    showStep(1);
    
    setTimeout(() => {
        document.getElementById('guest-name').focus();
    }, 100);
}

// Close walk-in modal
function closeWalkInModal() {
    const modal = document.getElementById('walkin-modal');
    modal.classList.remove('active');
    document.getElementById('walkin-form').reset();
    window.currentStep = 1;
    window.isMemberWalkIn = false;
    window.memberWalkInData = null;
    
    // Reset dropdown to guest rates
    if (typeof updateRatesDropdown === 'function') {
        updateRatesDropdown(false);
    }
    
    showStep(1);
    updatePaymentAmount();
    
    // Restore input fields visibility
    document.getElementById('guest-name').style.display = '';
    document.getElementById('guest-name').parentElement.style.display = '';
    document.getElementById('guest-name').readOnly = false;
    document.getElementById('guest-name').style.backgroundColor = '';
    
    document.getElementById('guest-phone').style.display = '';
    document.getElementById('guest-phone').parentElement.style.display = '';
    document.getElementById('guest-phone').readOnly = false;
    document.getElementById('guest-phone').style.backgroundColor = '';
    
    document.getElementById('discount-type').disabled = false;
    document.getElementById('discount-type').style.backgroundColor = '';
    
    // Remove member info display if exists
    const memberInfoDisplay = document.getElementById('member-info-display');
    if (memberInfoDisplay) {
        memberInfoDisplay.remove();
    }
}

// Multi-step modal functions
function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(s => s.style.display = 'none');
    
    // Show current step
    const currentStepEl = document.getElementById(`step-${step}`);
    if (currentStepEl) {
        currentStepEl.style.display = 'block';
    }
    
    // Update progress indicators
    updateProgressIndicators(step);
    
    // Update buttons
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnComplete = document.getElementById('btn-complete');
    const stepTitle = document.getElementById('step-title');
    
    if (step === 1) {
        btnPrev.style.display = 'none';
        btnNext.style.display = 'inline-block';
        btnComplete.style.display = 'none';
        stepTitle.innerHTML = '<i class="fas fa-user"></i> Guest Details';
    } else if (step === 2) {
        btnPrev.style.display = 'inline-block';
        btnNext.style.display = 'inline-block';
        btnComplete.style.display = 'none';
        stepTitle.innerHTML = '<i class="fas fa-credit-card"></i> Select Payment Method';
    } else if (step === 3) {
        btnPrev.style.display = 'inline-block';
        btnNext.style.display = 'none';
        btnComplete.style.display = 'inline-block';
        stepTitle.innerHTML = '<i class="fas fa-check"></i> Review & Confirm';
    }
    
    window.currentStep = step;
}

function updateProgressIndicators(step) {
    // Update circle backgrounds
    for (let i = 1; i <= 3; i++) {
        const circle = document.getElementById(`step-${i}-circle`);
        if (i < step) {
            circle.style.background = '#27ae60';
            circle.style.borderColor = '#27ae60';
            circle.style.color = 'white';
        } else if (i === step) {
            circle.style.background = 'white';
            circle.style.borderColor = 'white';
            circle.style.color = '#667eea';
        } else {
            circle.style.background = 'rgba(255,255,255,0.3)';
            circle.style.borderColor = 'rgba(255,255,255,0.5)';
            circle.style.color = 'white';
        }
    }
}

function nextStep() {
    if (window.currentStep === 1) {
        // Validate step 1
        const guestName = document.getElementById('guest-name').value.trim();
        const discountType = document.getElementById('discount-type').value;
        const guestPhone = document.getElementById('guest-phone').value.trim();
        
        // Validate phone if provided - must be exactly 11 digits
        if (guestPhone && guestPhone.length !== 11) {
            showToast('Phone number must be exactly 11 digits', 'warning');
            document.getElementById('guest-phone').focus();
            return;
        }
        
        if (!guestName) {
            showToast('Please enter guest name', 'warning');
            document.getElementById('guest-name').focus();
            return;
        }
        
        if (!discountType) {
            showToast('Please select a rate type', 'warning');
            return;
        }
        
        // Store data and proceed to step 2
        updatePaymentAmount();
        
        // Populate step 2 summary
        document.getElementById('summary-guest-name').textContent = guestName;
        document.getElementById('summary-rate-type').textContent = discountType;
        
        showStep(2);
        document.getElementById('guest-payment-method').focus();
        
    } else if (window.currentStep === 2) {
        // Validate step 2
        const paymentMethod = document.getElementById('guest-payment-method').value;
        
        if (!paymentMethod) {
            showToast('Please select a payment method', 'warning');
            return;
        }
        
        // Populate step 3 review
        const guestName = document.getElementById('guest-name').value.trim();
        const guestPhone = document.getElementById('guest-phone').value.trim();
        const discountType = document.getElementById('discount-type').value;
        const amountText = document.getElementById('step-2-amount').textContent;
        
        document.getElementById('review-name').textContent = guestName;
        document.getElementById('review-phone').textContent = guestPhone ? `📞 ${guestPhone}` : 'No phone provided';
        document.getElementById('review-type').textContent = discountType;
        document.getElementById('review-amount').textContent = amountText;
        document.getElementById('review-payment-method').textContent = paymentMethod;
        
        showStep(3);
    }
}

function previousStep() {
    if (window.currentStep > 1) {
        showStep(window.currentStep - 1);
    }
}

// Update payment amount based on discount type
function updatePaymentAmount() {
    const discountSelect = document.getElementById('discount-type');
    const selectedOption = discountSelect.options[discountSelect.selectedIndex];
    
    if (!selectedOption.value) {
        document.getElementById('step-2-amount').textContent = '₱0.00';
        const descEl = document.getElementById('rate-description');
        if (descEl) descEl.style.display = 'none';
        return;
    }
    
    // Get price from data attribute on the option
    const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    const description = selectedOption.getAttribute('data-description') || '';
    
    const amountEl = document.getElementById('step-2-amount');
    amountEl.textContent = `₱${price.toFixed(2)}`;
    amountEl.style.animation = 'none';
    
    // Show description if available
    const descEl = document.getElementById('rate-description');
    if (descEl && description) {
        descEl.textContent = description;
        descEl.style.display = 'block';
    } else if (descEl) {
        descEl.style.display = 'none';
    }
    
    setTimeout(() => {
        amountEl.style.animation = 'pulse 0.5s ease';
    }, 10);
}

// Process guest payment (final step)
async function processGuestPayment() {
    const guestName = document.getElementById('guest-name').value.trim();
    const guestPhone = document.getElementById('guest-phone').value.trim();
    const discountType = document.getElementById('discount-type').value;
    const paymentMethod = document.getElementById('guest-payment-method').value;
    const confirmCheckbox = document.getElementById('guest-payment-confirm').checked;
    
    // Check if this is a member walk-in
    const isMemberWalkIn = window.isMemberWalkIn || false;
    const memberData = window.memberWalkInData || {};
    
    if (!confirmCheckbox) {
        showToast('Please confirm the information is correct', 'warning');
        return;
    }
    
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="loading"></span> Processing...';
    
    try {
        // Step 1: Check in the guest/member
        const checkInData = new FormData();
        checkInData.append('action', 'walkin_checkin');
        checkInData.append('guest_name', guestName);
        checkInData.append('guest_phone', guestPhone);
        checkInData.append('discount_type', discountType);
        
        // If member walk-in, add client_id and set attendance_type to Member
        if (isMemberWalkIn && memberData.client_id) {
            checkInData.append('client_id', memberData.client_id);
            checkInData.append('attendance_type', 'Member');
        } else {
            checkInData.append('attendance_type', 'Guest');
        }
        
        const checkInResponse = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: checkInData
        });
        
        const checkInResult = await checkInResponse.json();
        
        if (!checkInResult.success) {
            showToast('Failed to check in ' + (isMemberWalkIn ? 'member' : 'guest') + ': ' + checkInResult.message, 'error');
            button.disabled = false;
            button.innerHTML = originalHTML;
            return;
        }
        
        const attendanceId = checkInResult.attendance_id;
        
        // Get the amount from the display
        const amountText = document.getElementById('step-2-amount').textContent;
        const amount = parseFloat(amountText.replace('₱', '')) || 0;
        
        // Step 2: Record the payment
        const paymentData = new FormData();
        paymentData.append('action', 'process_payment');
        paymentData.append('type', isMemberWalkIn ? 'member_walkin' : 'walkin');
        paymentData.append('attendance_id', attendanceId);
        paymentData.append('amount', amount);
        paymentData.append('payment_method', paymentMethod);
        paymentData.append('description', guestName);
        
        if (isMemberWalkIn && memberData.client_id) {
            paymentData.append('client_id', memberData.client_id);
        }
        
        const paymentResponse = await fetch('includes/payment_handler.php', {
            method: 'POST',
            body: paymentData
        });
        
        const paymentResult = await paymentResponse.json();
        
        if (paymentResult.success) {
            const successMsg = isMemberWalkIn 
                ? 'Member checked in and payment recorded successfully!' 
                : 'Guest checked in and payment recorded successfully!';
            showToast(successMsg, 'success');
            closeWalkInModal();
            
            // Refresh data
            loadStats();
            loadCurrentlyInside();
            loadTodayLog();
        } else {
            showToast('Payment recorded warning: ' + paymentResult.message, 'warning');
            closeWalkInModal();
            loadStats();
            loadCurrentlyInside();
            loadTodayLog();
        }
    } catch (error) {
        console.error('Error processing guest payment:', error);
        showToast('System error: ' + error.message, 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHTML;
    }
}

// Close guest payment modal (no longer used, but keeping for compatibility)
function closeGuestPaymentModal() {
    // This is now handled by closeWalkInModal
    closeWalkInModal();
}

// =====================================================
// MEMBER WALK-IN REGISTRATION FUNCTIONS
// =====================================================

// Show member ID lookup modal
function showMemberIDModal() {
    const modal = document.getElementById('member-id-modal');
    if (modal) {
        modal.classList.add('active');
        // Clear previous data
        document.getElementById('member-lookup-id').value = '';
        document.getElementById('member-lookup-error').style.display = 'none';
        document.getElementById('member-lookup-info').style.display = 'none';
        
        setTimeout(() => {
            document.getElementById('member-lookup-id').focus();
        }, 100);
    }
}

// Close member ID modal
function closeMemberIDModal() {
    const modal = document.getElementById('member-id-modal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('member-lookup-id').value = '';
        document.getElementById('member-lookup-error').style.display = 'none';
        document.getElementById('member-lookup-info').style.display = 'none';
    }
}

// Validate member ID and fetch member data
function validateMemberID() {
    const memberID = document.getElementById('member-lookup-id').value.trim();
    const errorDiv = document.getElementById('member-lookup-error');
    const infoDiv = document.getElementById('member-lookup-info');
    
    // Reset messages
    errorDiv.style.display = 'none';
    infoDiv.style.display = 'none';
    
    if (!memberID) {
        errorDiv.style.display = 'block';
        document.getElementById('error-message').textContent = 'Please enter a member ID';
        return;
    }
    
    // Fetch member data from server
    fetch(`includes/members_list_handler.php?action=get_member_details&member_id=${encodeURIComponent(memberID)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;
                
                // Show member info
                document.getElementById('member-found-name').textContent = member.full_name;
                document.getElementById('member-found-status').textContent = member.status || 'Active';
                infoDiv.style.display = 'block';
                
                // Store member data globally for later use
                window.selectedMember = {
                    client_id: member.client_id,
                    full_name: member.full_name,
                    email: member.email,
                    phone: member.phone,
                    status: member.status,
                    membership_plan: member.membership_plan,
                    is_member: true
                };
                
                // Close member ID modal
                setTimeout(() => {
                    closeMemberIDModal();
                    // Open member walk-in modal with member data
                    openMemberWalkInModal(window.selectedMember);
                }, 800);
                
            } else {
                errorDiv.style.display = 'block';
                document.getElementById('error-message').textContent = data.message || 'Member not found';
            }
        })
        .catch(error => {
            console.error('Error validating member:', error);
            errorDiv.style.display = 'block';
            document.getElementById('error-message').textContent = 'Error validating member: ' + error.message;
        });
}

// Open walk-in modal for member (pre-filled with member data and member discount)

// Allow Enter key for member ID lookup
document.addEventListener('DOMContentLoaded', function() {
    const memberIdInput = document.getElementById('member-lookup-id');
    if (memberIdInput) {
        memberIdInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                validateMemberID();
            }
        });
    }
});

// ============================================================================
// ERROR/SUCCESS MODALS
// ============================================================================

function showErrorModal(message) {
    document.getElementById('error-modal-message').textContent = message;
    document.getElementById('error-modal').classList.add('active');
}

function closeErrorModal() {
    document.getElementById('error-modal').classList.remove('active');
}

function showSuccessModal(message) {
    document.getElementById('success-modal-message').textContent = message;
    document.getElementById('success-modal').classList.add('active');
}

function closeSuccessModal() {
    document.getElementById('success-modal').classList.remove('active');
}

// ============================================================================
// MEMBER WALK-IN MODAL FUNCTIONS
// ============================================================================

// Open member walk-in modal with member data
function openMemberWalkInModal(memberData) {
    const modal = document.getElementById('member-walkin-modal');
    if (!modal) return;
    
    modal.classList.add('active');
    
    // Reset to step 1
    window.memberCurrentStep = 1;
    window.memberWalkInData = memberData;
    
    // Display member info
    const infoContainer = document.getElementById('member-info-display-container');
    infoContainer.innerHTML = `
        <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <i class="fas fa-check-circle" style="font-size: 20px; color: #22c55e;"></i>
                <strong style="color: #065f46; font-size: 16px;">Member Information</strong>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="border-bottom: 1px solid #dcfce7; padding-bottom: 10px;">
                    <small style="color: #6b7280; font-weight: 600; display: block; margin-bottom: 4px;">MEMBER ID</small>
                    <strong style="color: #065f46; font-size: 15px;">${memberData.client_id}</strong>
                </div>
                <div style="border-bottom: 1px solid #dcfce7; padding-bottom: 10px;">
                    <small style="color: #6b7280; font-weight: 600; display: block; margin-bottom: 4px;">STATUS</small>
                    <strong style="color: #065f46; font-size: 15px;">${memberData.status || 'Active'}</strong>
                </div>
            </div>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #dcfce7;">
                <small style="color: #6b7280; font-weight: 600; display: block; margin-bottom: 4px;">FULL NAME</small>
                <strong style="color: #065f46; font-size: 15px;">${memberData.full_name}</strong>
            </div>
            ${memberData.phone ? `
            <div style="margin-top: 12px;">
                <small style="color: #6b7280; font-weight: 600; display: block; margin-bottom: 4px;">PHONE</small>
                <strong style="color: #065f46; font-size: 15px;">${memberData.phone}</strong>
            </div>
            ` : ''}
            ${memberData.email ? `
            <div style="margin-top: 12px;">
                <small style="color: #6b7280; font-weight: 600; display: block; margin-bottom: 4px;">EMAIL</small>
                <strong style="color: #065f46; font-size: 15px;">${memberData.email}</strong>
            </div>
            ` : ''}
        </div>
    `;
    
    // Populate member rates dropdown
    const memberDiscountSelect = document.getElementById('member-discount-type');
    if (memberDiscountSelect) {
        const ratesToUse = allRates.member || [];

        // Clear existing options (keep the placeholder)
        memberDiscountSelect.innerHTML = '<option value="">Select rate type</option>';

        // Add options from the member rate array
        ratesToUse.forEach(rate => {
            const option = document.createElement('option');
            // Use rate_id as the option value so selection is unique and reliable
            option.value = rate.rate_id;
            option.setAttribute('data-rate-id', rate.rate_id);
            option.setAttribute('data-price', rate.price);
            option.setAttribute('data-rate-name', rate.rate_name);
            option.setAttribute('data-description', rate.description || '');
            option.setAttribute('data-is-discounted', rate.is_discounted || 0);

            let optionText = `${rate.rate_name} (₱${parseFloat(rate.price).toFixed(2)})`;
            if (rate.is_discounted) {
                optionText += ' - Discounted';
            }
            option.textContent = optionText;
            memberDiscountSelect.appendChild(option);
        });
    }
    
    memberShowStep(1);
    
    // Update modal title
    const stepTitle = document.getElementById('member-step-title');
    if (stepTitle) {
        stepTitle.innerHTML = '<i class="fas fa-user-check"></i> Member Walk-in Registration';
    }
}

// Close member walk-in modal
function closeMemberWalkInModal() {
    const modal = document.getElementById('member-walkin-modal');
    modal.classList.remove('active');
    document.getElementById('member-walkin-form').reset();
    window.memberCurrentStep = 1;
    window.memberWalkInData = null;
    memberShowStep(1);
    
    // Clear info container
    document.getElementById('member-info-display-container').innerHTML = '';
}

// Show specific step in member modal
function memberShowStep(step) {
    window.memberCurrentStep = step;
    
    // Hide all steps
    for (let i = 1; i <= 3; i++) {
        const stepDiv = document.getElementById(`member-step-${i}`);
        if (stepDiv) stepDiv.style.display = 'none';
    }
    
    // Show current step
    const currentStepDiv = document.getElementById(`member-step-${step}`);
    if (currentStepDiv) currentStepDiv.style.display = 'block';
    
    // Update progress circles
    for (let i = 1; i <= 3; i++) {
        const circle = document.getElementById(`member-step-${i}-circle`);
        if (circle) {
            circle.classList.remove('active', 'completed');
            if (i < step) {
                circle.classList.add('completed');
            } else if (i === step) {
                circle.classList.add('active');
            }
        }
    }
    
    // Show/hide buttons
    document.getElementById('member-btn-prev').style.display = step > 1 ? 'inline-block' : 'none';
    document.getElementById('member-btn-next').style.display = step < 3 ? 'inline-block' : 'none';
    document.getElementById('member-btn-complete').style.display = step === 3 ? 'inline-block' : 'none';
    
    // Update summary on step 2
    if (step === 2) {
        const discountSelect = document.getElementById('member-discount-type');
        const selectedOption = discountSelect.options[discountSelect.selectedIndex];
        
        document.getElementById('member-summary-name').textContent = window.memberWalkInData.full_name;
        document.getElementById('member-summary-rate-type').textContent = selectedOption ? selectedOption.text : '-';
    }
    
    // Update review on step 3
    if (step === 3) {
        const discountSelect = document.getElementById('member-discount-type');
        const paymentMethodSelect = document.getElementById('member-payment-method');
        const selectedRate = discountSelect.options[discountSelect.selectedIndex];
        const selectedMethod = paymentMethodSelect.options[paymentMethodSelect.selectedIndex];
        
        document.getElementById('member-review-name').textContent = window.memberWalkInData.full_name;
        document.getElementById('member-review-type').textContent = selectedRate ? selectedRate.text : '-';
        document.getElementById('member-review-amount').textContent = selectedRate ? selectedRate.getAttribute('data-price') : '₱0.00';
        document.getElementById('member-review-payment-method').textContent = selectedMethod ? selectedMethod.text : '-';
    }
}

// Move to next step in member modal
function memberNextStep() {
    const discountSelect = document.getElementById('member-discount-type');
    const paymentMethodSelect = document.getElementById('member-payment-method');
    
    // Validate required fields before moving forward
    if (window.memberCurrentStep === 1) {
        if (!discountSelect.value) {
            showErrorModal('Please select a rate type');
            return;
        }
        // Update amount display when moving to step 2
        updateMemberPaymentAmount();
    } else if (window.memberCurrentStep === 2) {
        if (!paymentMethodSelect.value) {
            showErrorModal('Please select a payment method');
            return;
        }
    }
    
    if (window.memberCurrentStep < 3) {
        memberShowStep(window.memberCurrentStep + 1);
    }
}

// Move to previous step in member modal
function memberPreviousStep() {
    if (window.memberCurrentStep > 1) {
        memberShowStep(window.memberCurrentStep - 1);
    }
}

// Update member payment amount display
function updateMemberPaymentAmount() {
    const discountSelect = document.getElementById('member-discount-type');
    const selectedOption = discountSelect.options[discountSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const price = selectedOption.getAttribute('data-price');
        const rateName = selectedOption.getAttribute('data-rate-name');
        const description = selectedOption.getAttribute('data-description');
        
        // Update step 2 amount display
        document.getElementById('member-step-2-amount').textContent = `₱${parseFloat(price).toFixed(2)}`;
        
        // Update review step amount
        document.getElementById('member-review-amount').textContent = `₱${parseFloat(price).toFixed(2)}`;
        document.getElementById('member-review-type').textContent = rateName;
    }
}

// Process member payment and complete check-in
async function processMemberPayment() {
    const discountSelect = document.getElementById('member-discount-type');
    const paymentMethodSelect = document.getElementById('member-payment-method');
    const confirmCheckbox = document.getElementById('member-payment-confirm');

    if (!confirmCheckbox.checked) {
        showErrorModal('Please confirm the payment information');
        return;
    }

    if (!discountSelect.value || !paymentMethodSelect.value) {
        showErrorModal('Please complete all required fields');
        return;
    }

    const selectedOption = discountSelect.options[discountSelect.selectedIndex];
    const rateId = selectedOption ? selectedOption.getAttribute('data-rate-id') : '';
    const rateName = selectedOption ? selectedOption.getAttribute('data-rate-name') : '';
    const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) || 0 : 0;

    const button = event && event.target ? event.target : document.getElementById('member-btn-complete');
    const originalHTML = button ? button.innerHTML : '';
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="loading"></span> Processing...';
    }

    try {
        // Step 1: Create member attendance (for member walk-in, use client_id only)
        const attendanceData = new FormData();
        attendanceData.append('action', 'create_member_attendance');
        attendanceData.append('client_id', window.memberWalkInData.client_id);

        const attendanceResponse = await fetch('includes/entry_exit_handler.php', {
            method: 'POST',
            body: attendanceData
        });

        const attendanceResult = await attendanceResponse.json();

        if (!attendanceResult.success) {
            showErrorModal('Failed to check in member: ' + (attendanceResult.message || 'Unknown'));
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
            return;
        }

        const attendanceId = attendanceResult.attendance_id;

        // Step 2: Process payment via payment_handler
        const paymentData = new FormData();
        paymentData.append('action', 'process_payment');
        // Use 'walkin' to call the existing walkin payment flow which updates attendance_log
        paymentData.append('type', 'walkin');
        paymentData.append('attendance_id', attendanceId);
        paymentData.append('amount', price);
        paymentData.append('payment_method', paymentMethodSelect.value);
        paymentData.append('description', window.memberWalkInData.full_name || 'Member Walk-in');
        paymentData.append('client_id', window.memberWalkInData.client_id);

        const paymentResponse = await fetch('includes/payment_handler.php', {
            method: 'POST',
            body: paymentData
        });

        const paymentResult = await paymentResponse.json();

        if (paymentResult.success) {
            showSuccessModal('Member checked in and payment recorded successfully');
            // Close modal and refresh lists
            setTimeout(() => {
                closeMemberWalkInModal();
                loadStats();
                loadCurrentlyInside();
                loadTodayLog();
            }, 800);
        } else {
            showErrorModal('Payment processing failed: ' + (paymentResult.message || 'Unknown'));
        }
    } catch (error) {
        console.error('Error processing member payment:', error);
        showErrorModal('System error: ' + (error.message || error));
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHTML;
        }
    }
}
