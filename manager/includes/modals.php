<?php
// includes/modals.php
// This file contains modal HTML fragments used by assessments.php
?>
<!-- VIEW DETAILS MODAL -->
<div id="viewDetailsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2><i class="fas fa-clipboard-check"></i> Assessment Details</h2>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailsModalContent">
            <!-- Content loaded via AJAX (get_details) -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- PROCESS REQUEST MODAL -->
<div id="processModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-check"></i> Process Assessment Request</h2>
            <button class="modal-close" onclick="closeProcessModal()">&times;</button>
        </div>
        <form id="processForm" method="POST" action="includes/assessment_handler.php">
            <input type="hidden" name="action" value="process_request">
            <input type="hidden" name="inquiry_id" id="processInquiryId">

            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Client:</strong> <span id="processClientName"></span><br>
                        <strong>Email:</strong> <span id="processClientEmail"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign Coach *</label>
                    <select name="coach_id" required>
                        <option value="">Select a coach...</option>
                        <?php foreach ($availableCoaches as $coach): ?>
                        <option value="<?php echo $coach['coach_id']; ?>">
                            <?php echo htmlspecialchars($coach['coach_name']); ?>
                            <?php if (!empty($coach['specialization'])): ?>
                                - <?php echo htmlspecialchars($coach['specialization']); ?>
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assessment Date *</label>
                    <input type="date" name="assessment_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" rows="3" placeholder="Any special notes for the coach..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeProcessModal()">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-check"></i> Process & Assign
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT ASSESSMENT MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Assessment</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" method="POST" action="includes/assessment_handler.php">
            <input type="hidden" name="action" value="update_assessment">
            <input type="hidden" name="assessment_id" id="editAssessmentId">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-section">
                        <h3><i class="fas fa-user-tie"></i> Assignment</h3>
                        <div class="form-group">
                            <label>Assigned Coach *</label>
                            <select name="coach_id" id="editCoachId" required>
                                <option value="">Select a coach...</option>
                                <?php foreach ($availableCoaches as $coach): ?>
                                <option value="<?php echo $coach['coach_id']; ?>">
                                    <?php echo htmlspecialchars($coach['coach_name']); ?>
                                    <?php if (!empty($coach['specialization'])): ?>
                                        - <?php echo htmlspecialchars($coach['specialization']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assessment Date *</label>
                            <input type="date" name="assessment_date" id="editAssessmentDate" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-weight"></i> Physical Measurements</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" name="weight" id="editWeight" step="0.1" min="0">
                            </div>
                            <div class="form-group">
                                <label>Height (cm)</label>
                                <input type="number" name="height" id="editHeight" step="0.1" min="0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Body Fat %</label>
                                <input type="number" name="body_fat_percentage" id="editBodyFat" step="0.1" min="0" max="100">
                            </div>
                            <div class="form-group">
                                <label>Muscle Mass (kg)</label>
                                <input type="number" name="muscle_mass" id="editMuscleMass" step="0.1" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Blood Pressure</label>
                                <input type="text" name="blood_pressure" id="editBloodPressure" placeholder="120/80">
                            </div>
                            <div class="form-group">
                                <label>Resting Heart Rate (bpm)</label>
                                <input type="number" name="resting_heart_rate" id="editHeartRate" min="0" max="250">
                            </div>
                        </div>
                    </div>

                    <div class="form-section full-width">
                        <h3><i class="fas fa-bullseye"></i> Goals & Conditions</h3>
                        <div class="form-group">
                            <label>Fitness Goals</label>
                            <textarea name="fitness_goals" id="editFitnessGoals" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Medical Conditions</label>
                            <textarea name="medical_conditions" id="editMedicalConditions" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="form-section full-width">
                        <h3><i class="fas fa-sticky-note"></i> Notes</h3>
                        <div class="form-group">
                            <label>Assessment Notes</label>
                            <textarea name="notes" id="editNotes" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Next Assessment Date</label>
                            <input type="date" name="next_assessment_date" id="editNextAssessmentDate">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>