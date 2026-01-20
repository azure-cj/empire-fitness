document.addEventListener('DOMContentLoaded', function () {
    // TAB SWITCHING
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const tabName = this.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            const target = document.getElementById(tabName + '-tab');
            if (target) target.classList.add('active');
        });
    });

    // SEARCH FUNCTIONALITY
    const searchPending = document.getElementById('searchPending');
    if (searchPending) {
        searchPending.addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.inquiry-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    const searchAssessments = document.getElementById('searchAssessments');
    if (searchAssessments) {
        searchAssessments.addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.assessment-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Close modals on outside click
    window.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
        }
    });

    // Attach form handlers
    const processForm = document.getElementById('processForm');
    if (processForm) {
        processForm.addEventListener('submit', submitProcessForm);
    }

    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', submitEditForm);
    }
});

/* PROCESS MODAL */
function openProcessModal(inquiryId, clientName, clientEmail) {
    document.getElementById('processInquiryId').value = inquiryId;
    document.getElementById('processClientName').textContent = clientName;
    document.getElementById('processClientEmail').textContent = clientEmail;
    document.getElementById('processModal').classList.add('show');
}

function closeProcessModal() {
    document.getElementById('processModal').classList.remove('show');
    const form = document.getElementById('processForm');
    if (form) form.reset();
}

/* PROCESS FORM SUBMISSION (AJAX) */
function submitProcessForm(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch('includes/assessment_handler.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(json => {
            if (json.success) {
                alert(json.message);
                location.reload();
            } else {
                alert(json.message || 'Error processing request');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error processing request');
        });
}

/* REJECT REQUEST */
function rejectRequest(inquiryId) {
    if (!confirm('Are you sure you want to reject this assessment request?')) return;

    fetch('includes/assessment_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reject_request&inquiry_id=${encodeURIComponent(inquiryId)}`
    })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.message);
                location.reload();
            } else {
                alert(json.message || 'Error rejecting request');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error rejecting request');
        });
}

/* VIEW DETAILS - loads HTML into modal */
function viewDetails(assessmentId) {
    fetch('includes/assessment_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_details&assessment_id=${encodeURIComponent(assessmentId)}`
    })
        .then(r => r.text())
        .then(html => {
            const container = document.getElementById('detailsModalContent');
            if (container) container.innerHTML = html;
            const modal = document.getElementById('viewDetailsModal');
            if (modal) modal.classList.add('show');
        })
        .catch(err => {
            console.error(err);
            alert('Error loading assessment details');
        });
}

function closeViewModal() {
    const modal = document.getElementById('viewDetailsModal');
    if (modal) modal.classList.remove('show');
}

/* EDIT ASSESSMENT - load data then show modal */
function editAssessment(assessmentId) {
    fetch('includes/assessment_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_assessment_data&assessment_id=${encodeURIComponent(assessmentId)}`
    })
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert(json.message || 'Error loading assessment data');
                return;
            }
            const a = json.assessment;
            document.getElementById('editAssessmentId').value = a.assessment_id;
            document.getElementById('editCoachId').value = a.coach_id || '';
            document.getElementById('editAssessmentDate').value = a.assessment_date || '';
            document.getElementById('editWeight').value = a.weight || '';
            document.getElementById('editHeight').value = a.height || '';
            document.getElementById('editBodyFat').value = a.body_fat_percentage || '';
            document.getElementById('editMuscleMass').value = a.muscle_mass || '';
            document.getElementById('editBloodPressure').value = a.blood_pressure || '';
            document.getElementById('editHeartRate').value = a.resting_heart_rate || '';
            document.getElementById('editFitnessGoals').value = a.fitness_goals || '';
            document.getElementById('editMedicalConditions').value = a.medical_conditions || '';
            document.getElementById('editNotes').value = a.notes || '';
            document.getElementById('editNextAssessmentDate').value = a.next_assessment_date || '';

            document.getElementById('editModal').classList.add('show');
        })
        .catch(err => {
            console.error(err);
            alert('Error loading assessment data');
        });
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.remove('show');
    const form = document.getElementById('editForm');
    if (form) form.reset();
}

/* EDIT FORM SUBMIT */
function submitEditForm(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch('includes/assessment_handler.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.message);
                location.reload();
            } else {
                alert(json.message || 'Error updating assessment');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating assessment');
        });
}

/* DELETE ASSESSMENT */
function deleteAssessment(assessmentId) {
    if (!confirm('Are you sure you want to delete this assessment? This action cannot be undone.')) return;

    fetch('includes/assessment_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_assessment&assessment_id=${encodeURIComponent(assessmentId)}`
    })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.message);
                location.reload();
            } else {
                alert(json.message || 'Error deleting assessment');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error deleting assessment');
        });
}