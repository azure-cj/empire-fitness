// Global variables
let currentWeekStart = new Date();
let allClasses = [];
let allSchedules = [];
let allCoaches = [];
let currentView = 'calendar';
let activeTab = 'classes';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

function initializePage() {
    setMinDate();
    loadCoaches();
    loadClasses();
    switchTab('classes');
}

function setMinDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (input.id !== 'recurrenceEndDate') {
            input.min = today;
        }
    });
}

// ========================================
// TAB MANAGEMENT
// ========================================

function switchTab(tab) {
    activeTab = tab;
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    if (tab === 'classes') {
        document.getElementById('classesTab').classList.add('active');
        loadClasses();
    } else if (tab === 'schedules') {
        document.getElementById('schedulesTab').classList.add('active');
        loadSchedules();
        renderCalendar();
    } else if (tab === 'otc-bookings') {
        document.getElementById('otcBookingsTab').classList.add('active');
        loadOTCBookings();
    }
}

// ========================================
// VIEW MANAGEMENT (Calendar/List)
// ========================================

function switchView(view) {
    currentView = view;
    
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.view-btn[data-view="${view}"]`).classList.add('active');
    
    if (view === 'calendar') {
        document.getElementById('calendarView').style.display = 'block';
        document.getElementById('listView').style.display = 'none';
        renderCalendar();
    } else {
        document.getElementById('calendarView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
        renderSchedulesList();
    }
}

// ========================================
// LOAD DATA FUNCTIONS
// ========================================

function loadCoaches() {
    fetch('includes/schedules_handler.php?action=get_coaches')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allCoaches = data.coaches;
                populateCoachSelects();
            } else {
                showAlert('Error loading coaches: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading coaches', 'error');
        });
}

function populateCoachSelects() {
    const coachSelect = document.getElementById('coachId');
    coachSelect.innerHTML = '<option value="">Select Coach</option>';
    
    allCoaches.forEach(coach => {
        if (coach.status === 'Active') {
            const option = document.createElement('option');
            option.value = coach.employee_id;
            option.textContent = coach.name;
            coachSelect.appendChild(option);
        }
    });
}

function loadClasses() {
    const tbody = document.getElementById('classesTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="no-data"><i class="fas fa-spinner fa-spin"></i><p>Loading classes...</p></td></tr>';
    
    fetch('includes/schedules_handler.php?action=get_classes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allClasses = data.classes;
                renderClasses();
                populateClassSelects();
            } else {
                showAlert('Error loading classes: ' + data.message, 'error');
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Error loading classes</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading classes', 'error');
            tbody.innerHTML = '<tr><td colspan="9" class="no-data">Error loading classes</td></tr>';
        });
}

function renderClasses() {
    const tbody = document.getElementById('classesTableBody');
    
    if (allClasses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="no-data"><i class="fas fa-dumbbell"></i><p>No classes found</p></td></tr>';
        return;
    }
    
    const filteredClasses = filterClassesData();
    
    tbody.innerHTML = filteredClasses.map(cls => {
        let actionButtons = '';
        
        // Show different buttons based on status
        if (cls.status === 'Pending') {
            actionButtons = `
                <button onclick="viewClassDetails(${cls.class_id})" class="btn-icon" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="approveClass(${cls.class_id})" class="btn-icon btn-success" title="Approve Class">
                    <i class="fas fa-check"></i>
                </button>
                <button onclick="openRejectClassModal(${cls.class_id}, '${escapeHtml(cls.class_name)}')" class="btn-icon btn-warning" title="Reject Class">
                    <i class="fas fa-ban"></i>
                </button>
                <button onclick="editClass(${cls.class_id})" class="btn-icon" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
            `;
        } else if (cls.status === 'Rejected') {
            actionButtons = `
                <button onclick="viewClassDetails(${cls.class_id})" class="btn-icon" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="viewRejectionReason(${cls.class_id})" class="btn-icon btn-info" title="View Reason">
                    <i class="fas fa-info-circle"></i>
                </button>
                <button onclick="editClass(${cls.class_id})" class="btn-icon" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="openDeleteClass(${cls.class_id}, '${escapeHtml(cls.class_name)}')" class="btn-icon btn-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        } else {
            actionButtons = `
                <button onclick="viewClassDetails(${cls.class_id})" class="btn-icon" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="editClass(${cls.class_id})" class="btn-icon" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="openDeleteClass(${cls.class_id}, '${escapeHtml(cls.class_name)}')" class="btn-icon btn-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }
        
        return `
        <tr>
            <td>${cls.class_id}</td>
            <td><strong>${escapeHtml(cls. class_name)}</strong></td>
            <td><span class="badge badge-info">${escapeHtml(cls.class_type)}</span></td>
            <td>${cls.coach_name ?  escapeHtml(cls.coach_name) : '<span class="text-muted">Unassigned</span>'}</td>
            <td>${cls.duration} min</td>
            <td>${cls.max_capacity} people</td>
            <td>₱${parseFloat(cls.single_session_price).toFixed(2)}</td>
            <td>${getStatusBadge(cls.status)}</td>
            <td>
                <div class="action-buttons">
                    ${actionButtons}
                </div>
            </td>
        </tr>
    `;
    }). join('');
}

function filterClassesData() {
    const typeFilter = document.getElementById('classTypeFilter').value;
    const statusFilter = document.getElementById('classStatusFilter').value;
    
    return allClasses.filter(cls => {
        const matchesType = typeFilter === 'all' || cls.class_type === typeFilter;
        const matchesStatus = statusFilter === 'all' || cls.status === statusFilter;
        return matchesType && matchesStatus;
    });
}

function filterClasses() {
    renderClasses();
}

function populateClassSelects() {
    const scheduleClassSelect = document.getElementById('scheduleClassId');
    const classFilter = document.getElementById('scheduleClassFilter');
    
    scheduleClassSelect.innerHTML = '<option value="">Select Class</option>';
    classFilter.innerHTML = '<option value="all">All Classes</option>';
    
    allClasses.forEach(cls => {
        if (cls.status === 'Active') {
            const option = document.createElement('option');
            option.value = cls.class_id;
            option.textContent = cls.class_name;
            option.dataset.duration = cls.duration;
            option.dataset.capacity = cls.max_capacity;
            scheduleClassSelect.appendChild(option);
            
            const filterOption = option.cloneNode(true);
            classFilter.appendChild(filterOption);
        }
    });
}

function loadSchedules() {
    const startDate = getWeekStart(currentWeekStart);
    const endDate = getWeekEnd(currentWeekStart);
    
    fetch(`includes/schedules_handler.php?action=get_schedules&start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allSchedules = data.schedules;
                if (currentView === 'calendar') {
                    renderCalendar();
                } else {
                    renderSchedulesList();
                }
            } else {
                showAlert('Error loading schedules: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading schedules', 'error');
        });
}

// ========================================
// CALENDAR FUNCTIONS
// ========================================

function renderCalendar() {
    const calendarGrid = document.getElementById('calendarGrid');
    const weekStart = new Date(getWeekStartDate(currentWeekStart));
    
    updateCalendarTitle();
    
    // Generate 7 days starting from Sunday
    const days = [];
    for (let i = 0; i < 7; i++) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + i);
        days.push(date);
    }
    
    // Create calendar HTML with proper structure
    calendarGrid.innerHTML = `
        <div class="calendar-days">
            ${days.map(day => `
                <div class="calendar-day ${isToday(day) ? 'today' : ''}">
                    <div class="day-header">
                        <div class="day-name">${getDayName(day)}</div>
                        <div class="day-date">${day.getDate()}</div>
                    </div>
                    <div class="day-schedules" id="day-${formatDate(day)}">
                        ${renderDaySchedules(day)}
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderDaySchedules(date) {
    const dateStr = formatDate(date);
    const daySchedules = allSchedules.filter(s => s.schedule_date === dateStr);
    
    if (daySchedules.length === 0) {
        return '<div class="no-schedules">No classes scheduled</div>';
    }
    
    // Sort schedules by start time
    daySchedules.sort((a, b) => {
        return a.start_time.localeCompare(b.start_time);
    });
    
    return daySchedules.map(schedule => {
        const bookedPercentage = (schedule.current_bookings / schedule.max_capacity) * 100;
        const statusClass = schedule.status.toLowerCase();
        const coachName = schedule.coach_name ? escapeHtml(schedule.coach_name) : 'Unassigned';
        
        return `
            <div class="schedule-item ${statusClass}" onclick="viewScheduleDetails(${schedule.schedule_id})">
                <div class="schedule-time">
                    <i class="fas fa-clock"></i> ${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}
                </div>
                <div class="schedule-name">${escapeHtml(schedule.class_name)}</div>
                <div class="schedule-coach">
                    <i class="fas fa-user-tie"></i> ${coachName}
                </div>
                <div class="schedule-capacity">
                    <div class="capacity-info">
                        <i class="fas fa-users"></i> ${schedule.current_bookings}/${schedule.max_capacity}
                    </div>
                    <div class="capacity-bar">
                        <div class="capacity-fill" style="width: ${bookedPercentage}%"></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderSchedulesList() {
    const tbody = document.getElementById('schedulesTableBody');
    
    if (allSchedules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="no-data"><i class="fas fa-calendar"></i><p>No schedules found</p></td></tr>';
        return;
    }
    
    const filteredSchedules = filterSchedulesData();
    
    tbody.innerHTML = filteredSchedules.map(schedule => {
        const coachName = schedule.coach_name ? escapeHtml(schedule.coach_name) : 'Unassigned';
        
        return `
        <tr>
            <td>${schedule.schedule_id}</td>
            <td><strong>${escapeHtml(schedule.class_name)}</strong><br>
                <small class="text-muted">${coachName}</small></td>
            <td>${formatDateDisplay(schedule.schedule_date)}</td>
            <td>${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</td>
            <td>${escapeHtml(schedule.room_location)}</td>
            <td>${schedule.max_capacity}</td>
            <td>${schedule.current_bookings}</td>
            <td>${getStatusBadge(schedule.status)}</td>
            <td>
                <div class="action-buttons">
                    <button onclick="viewScheduleDetails(${schedule.schedule_id})" class="btn-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editSchedule(${schedule.schedule_id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="openDeleteSchedule(${schedule.schedule_id}, '${escapeHtml(schedule.class_name)}')" class="btn-icon btn-danger" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
    }).join('');
}

function filterSchedulesData() {
    const classFilter = document.getElementById('scheduleClassFilter').value;
    const statusFilter = document.getElementById('scheduleStatusFilter').value;
    
    return allSchedules.filter(schedule => {
        const matchesClass = classFilter === 'all' || schedule.class_id == classFilter;
        const matchesStatus = statusFilter === 'all' || schedule.status === statusFilter;
        return matchesClass && matchesStatus;
    });
}

function filterSchedules() {
    if (currentView === 'calendar') {
        renderCalendar();
    } else {
        renderSchedulesList();
    }
}

function updateCalendarTitle() {
    const weekStart = new Date(getWeekStartDate(currentWeekStart));
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    
    const title = `${getMonthName(weekStart)} ${weekStart.getDate()} - ${getMonthName(weekEnd)} ${weekEnd.getDate()}, ${weekEnd.getFullYear()}`;
    document.getElementById('calendarTitle').textContent = title;
}

function previousWeek() {
    currentWeekStart.setDate(currentWeekStart.getDate() - 7);
    loadSchedules();
}

function nextWeek() {
    currentWeekStart.setDate(currentWeekStart.getDate() + 7);
    loadSchedules();
}

function goToToday() {
    currentWeekStart = new Date();
    loadSchedules();
}

// ========================================
// CLASS MODAL FUNCTIONS
// ========================================

function openAddClassModal() {
    document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-dumbbell"></i> Create New Class';
    document.getElementById('classForm').reset();
    document.getElementById('classId').value = '';
    document.getElementById('classStatus').value = 'Active';
    document.getElementById('classModal').style.display = 'block';
}

function viewClassDetails(classId) {
    const cls = allClasses.find(c => c.class_id == classId);
    if (!cls) {
        showAlert('Class not found', 'error');
        return;
    }
    
    const html = `
        <div class="class-details-view">
            <div class="detail-section">
                <h4><i class="fas fa-dumbbell"></i> Class Information</h4>
                <div class="detail-row">
                    <label>Class Name:</label>
                    <span>${escapeHtml(cls.class_name)}</span>
                </div>
                <div class="detail-row">
                    <label>Type:</label>
                    <span><span class="badge badge-info">${escapeHtml(cls.class_type)}</span></span>
                </div>
                <div class="detail-row">
                    <label>Coach:</label>
                    <span>${cls.coach_name ? escapeHtml(cls.coach_name) : '<span class="text-muted">Unassigned</span>'}</span>
                </div>
                <div class="detail-row">
                    <label>Difficulty Level:</label>
                    <span>${escapeHtml(cls.difficulty_level || 'All Levels')}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-info-circle"></i> Class Details</h4>
                <div class="detail-row">
                    <label>Duration:</label>
                    <span>${cls.duration} minutes</span>
                </div>
                <div class="detail-row">
                    <label>Max Capacity:</label>
                    <span>${cls.max_capacity} people</span>
                </div>
                <div class="detail-row">
                    <label>Single Session Price:</label>
                    <span class="highlight">₱${parseFloat(cls.single_session_price).toFixed(2)}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4><i class="fas fa-toggle-on"></i> Status</h4>
                <div class="detail-row">
                    <label>Class Status:</label>
                    <span>${getStatusBadge(cls.status)}</span>
                </div>
                ${cls.status === 'Rejected' && cls.rejection_reason ? `
                <div class="detail-row">
                    <label>Rejection Reason:</label>
                    <span class="text-danger">${escapeHtml(cls.rejection_reason)}</span>
                </div>
                ` : ''}
            </div>
            
            ${cls.description ? `
            <div class="detail-section">
                <h4><i class="fas fa-align-left"></i> Description</h4>
                <p style="color: #374151; font-size: 14px; line-height: 1.6; margin: 0;">${escapeHtml(cls.description)}</p>
            </div>
            ` : ''}
            
            ${cls.equipment_required ? `
            <div class="detail-section">
                <h4><i class="fas fa-tools"></i> Equipment Required</h4>
                <p style="color: #374151; font-size: 14px; line-height: 1.6; margin: 0;">${escapeHtml(cls.equipment_required)}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('viewClassContent').innerHTML = html;
    document.getElementById('viewClassModal').dataset.classId = classId;
    document.getElementById('viewClassModal').style.display = 'block';
}

function closeViewClassModal() {
    document.getElementById('viewClassModal').style.display = 'none';
}

function editClassFromView() {
    const classId = document.getElementById('viewClassModal').dataset.classId;
    closeViewClassModal();
    editClass(classId);
}

function editClass(classId) {
    const cls = allClasses.find(c => c.class_id == classId);
    if (!cls) return;
    
    document.getElementById('classModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Class';
    document.getElementById('classId').value = cls.class_id;
    document.getElementById('className').value = cls.class_name;
    document.getElementById('classType').value = cls.class_type;
    document.getElementById('coachId').value = cls.coach_id || '';
    document.getElementById('duration').value = cls.duration;
    document.getElementById('maxCapacity').value = cls.max_capacity;
    document.getElementById('singleSessionPrice').value = cls.single_session_price;
    document.getElementById('description').value = cls.description || '';
    document.getElementById('difficultyLevel').value = cls.difficulty_level || 'All Levels';
    document.getElementById('equipmentRequired').value = cls.equipment_required || '';
    document.getElementById('classStatus').value = cls.status;
    
    document.getElementById('classModal').style.display = 'block';
}

function closeClassModal() {
    document.getElementById('classModal').style.display = 'none';
    document.getElementById('classForm').reset();
}

function saveClass() {
    const form = document.getElementById('classForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'save_class');
    
    fetch('includes/schedules_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeClassModal();
            loadClasses();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error saving class', 'error');
    });
}

function openDeleteClass(classId, className) {
    document.getElementById('deleteType').value = 'class';
    document.getElementById('deleteId').value = classId;
    document.getElementById('deleteMessage').textContent = 'Are you sure you want to delete this class?';
    document.getElementById('deleteInfo').textContent = className;
    document.getElementById('deleteModal').style.display = 'block';
}

// ========================================
// SCHEDULE MODAL FUNCTIONS
// ========================================

function openAddScheduleModal() {
    document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Create Schedule';
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleStatus').value = 'Scheduled';
    document.getElementById('isRecurring').checked = false;
    document.getElementById('recurringOptions').style.display = 'none';
    document.getElementById('scheduleModal').style.display = 'block';
}

function editSchedule(scheduleId) {
    fetch(`includes/schedules_handler.php?action=get_schedule&schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const schedule = data.schedule;
                document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Schedule';
                document.getElementById('scheduleId').value = schedule.schedule_id;
                document.getElementById('scheduleClassId').value = schedule.class_id;
                document.getElementById('scheduleDate').value = schedule.schedule_date;
                document.getElementById('startTime').value = schedule.start_time;
                document.getElementById('endTime').value = schedule.end_time;
                document.getElementById('scheduleMaxCapacity').value = schedule.max_capacity;
                document.getElementById('roomLocation').value = schedule.room_location;
                document.getElementById('scheduleStatus').value = schedule.status;
                document.getElementById('scheduleNotes').value = schedule.notes || '';
                document.getElementById('isRecurring').checked = schedule.is_recurring == 1;
                
                if (schedule.is_recurring == 1) {
                    document.getElementById('recurringOptions').style.display = 'block';
                    document.getElementById('recurrencePattern').value = schedule.recurrence_pattern;
                    document.getElementById('recurrenceEndDate').value = schedule.recurrence_end_date;
                }
                
                document.getElementById('scheduleModal').style.display = 'block';
            } else {
                showAlert('Error loading schedule: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading schedule', 'error');
        });
}

function viewScheduleDetails(scheduleId) {
    fetch(`includes/schedules_handler.php?action=get_schedule&schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const schedule = data.schedule;
                const classInfo = allClasses.find(c => c.class_id == schedule.class_id);
                
                const html = `
                    <div class="schedule-details-view">
                        <div class="detail-section">
                            <h4><i class="fas fa-dumbbell"></i> Class Information</h4>
                            <div class="detail-row">
                                <label>Class Name:</label>
                                <span>${escapeHtml(schedule.class_name)}</span>
                            </div>
                            <div class="detail-row">
                                <label>Coach:</label>
                                <span>${schedule.coach_name ? escapeHtml(schedule.coach_name) : 'Unassigned'}</span>
                            </div>
                            ${classInfo ? `
                            <div class="detail-row">
                                <label>Type:</label>
                                <span>${escapeHtml(classInfo.class_type)}</span>
                            </div>
                            <div class="detail-row">
                                <label>Difficulty:</label>
                                <span>${escapeHtml(classInfo.difficulty_level || 'All Levels')}</span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-calendar"></i> Schedule Information</h4>
                            <div class="detail-row">
                                <label>Date:</label>
                                <span>${formatDateDisplay(schedule.schedule_date)}</span>
                            </div>
                            <div class="detail-row">
                                <label>Time:</label>
                                <span>${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</span>
                            </div>
                            <div class="detail-row">
                                <label>Location:</label>
                                <span>${escapeHtml(schedule.room_location)}</span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-users"></i> Capacity & Bookings</h4>
                            <div class="detail-row">
                                <label>Max Capacity:</label>
                                <span>${schedule.max_capacity}</span>
                            </div>
                            <div class="detail-row">
                                <label>Current Bookings:</label>
                                <span>${schedule.current_bookings} / ${schedule.max_capacity}</span>
                            </div>
                            <div class="detail-row">
                                <label>Available Spots:</label>
                                <span class="highlight">${schedule.max_capacity - schedule.current_bookings}</span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-info-circle"></i> Status & Notes</h4>
                            <div class="detail-row">
                                <label>Status:</label>
                                <span>${getStatusBadge(schedule.status)}</span>
                            </div>
                            ${schedule.notes ? `
                            <div class="detail-row">
                                <label>Notes:</label>
                                <span>${escapeHtml(schedule.notes)}</span>
                            </div>
                            ` : ''}
                            ${schedule.is_recurring ? `
                            <div class="detail-row">
                                <label>Recurring:</label>
                                <span>Yes - ${schedule.recurrence_pattern}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                document.getElementById('viewScheduleContent').innerHTML = html;
                document.getElementById('viewScheduleModal').dataset.scheduleId = scheduleId;
                document.getElementById('viewScheduleModal').style.display = 'block';
            } else {
                showAlert('Error loading schedule: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading schedule', 'error');
        });
}

function closeViewScheduleModal() {
    document.getElementById('viewScheduleModal').style.display = 'none';
}

function editScheduleFromView() {
    const scheduleId = document.getElementById('viewScheduleModal').dataset.scheduleId;
    closeViewScheduleModal();
    editSchedule(scheduleId);
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    document.getElementById('scheduleForm').reset();
}

function updateScheduleClassInfo() {
    const select = document.getElementById('scheduleClassId');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const duration = parseInt(selectedOption.dataset.duration) || 60;
        const capacity = parseInt(selectedOption.dataset.capacity) || 10;
        
        document.getElementById('scheduleMaxCapacity').value = capacity;
        
        const startTime = document.getElementById('startTime').value;
        if (startTime) {
            const [hours, minutes] = startTime.split(':');
            const startDate = new Date();
            startDate.setHours(parseInt(hours), parseInt(minutes), 0);
            startDate.setMinutes(startDate.getMinutes() + duration);
            
            const endHours = String(startDate.getHours()).padStart(2, '0');
            const endMinutes = String(startDate.getMinutes()).padStart(2, '0');
            document.getElementById('endTime').value = `${endHours}:${endMinutes}`;
        }
    }
}

function toggleRecurring() {
    const isChecked = document.getElementById('isRecurring').checked;
    document.getElementById('recurringOptions').style.display = isChecked ? 'block' : 'none';
}

function saveSchedule() {
    const form = document.getElementById('scheduleForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'save_schedule');
    
    fetch('includes/schedules_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeScheduleModal();
            loadSchedules();
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error saving schedule', 'error');
    });
}

function openDeleteSchedule(scheduleId, className) {
    document.getElementById('deleteType').value = 'schedule';
    document.getElementById('deleteId').value = scheduleId;
    document.getElementById('deleteMessage').textContent = 'Are you sure you want to delete this schedule?';
    document.getElementById('deleteInfo').textContent = className;
    document.getElementById('deleteModal').style.display = 'block';
}

// ========================================
// DELETE MODAL FUNCTIONS
// ========================================

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function confirmDelete() {
    const deleteType = document.getElementById('deleteType').value;
    const deleteId = document.getElementById('deleteId').value;
    
    const formData = new FormData();
    formData.append('action', deleteType === 'class' ? 'delete_class' : 'delete_schedule');
    formData.append(deleteType === 'class' ? 'class_id' : 'schedule_id', deleteId);
    
    fetch('includes/schedules_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeDeleteModal();
            if (deleteType === 'class') {
                loadClasses();
            } else {
                loadSchedules();
            }
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error deleting item', 'error');
    });
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
    
    setTimeout(() => {
        alertBox.style.display = 'none';
    }, 5000);
}
function getStatusBadge(status) {
    const badges = {
        'Active': 'badge-success',
        'Inactive': 'badge-secondary',
        'Scheduled': 'badge-primary',
        'Completed': 'badge-success',
        'Cancelled': 'badge-danger',
        'Pending': 'badge-warning',
        'Rejected': 'badge-danger'
    };
    return `<span class="badge ${badges[status] || 'badge-secondary'}">${status}</span>`;
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDateDisplay(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function getWeekStartDate(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day; // Sunday = 0
    d.setDate(diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

function getWeekStart(date) {
    return formatDate(getWeekStartDate(date));
}

function getWeekEnd(date) {
    const d = getWeekStartDate(date);
    d.setDate(d.getDate() + 6);
    return formatDate(d);
}

function getDayName(date) {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
}

function getMonthName(date) {
    return date.toLocaleDateString('en-US', { month: 'short' });
}

function isToday(date) {
    const today = new Date();
    return date.getDate() === today.getDate() &&
           date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
function approveClass(classId) {
    if (!confirm('Are you sure you want to approve this class?  It will become Active and Bookable.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'approve_class');
    formData.append('class_id', classId);
    
    fetch('includes/schedules_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ?  'success' : 'error');
        if (data.success) {
            loadClasses();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error approving class', 'error');
    });
}

function openRejectClassModal(classId, className) {
    document.getElementById('rejectClassId').value = classId;
    document.getElementById('rejectClassName').textContent = className;
    document. getElementById('rejectionReason').value = '';
    document.getElementById('rejectModal').style.display = 'block';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function rejectClass() {
    const classId = document.getElementById('rejectClassId'). value;
    const reason = document.getElementById('rejectionReason').value. trim();
    
    if (! reason) {
        showAlert('Please provide a rejection reason', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reject_class');
    formData.append('class_id', classId);
    formData.append('rejection_reason', reason);
    
    fetch('includes/schedules_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    . then(data => {
        showAlert(data.message, data. success ? 'success' : 'error');
        if (data.success) {
            closeRejectModal();
            loadClasses();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error rejecting class', 'error');
    });
}

function viewRejectionReason(classId) {
    const cls = allClasses.find(c => c.class_id == classId);
    if (!cls || !cls.rejection_reason) {
        showAlert('No rejection reason available', 'info');
        return;
    }
    alert(`Rejection Reason:\n\n${cls.rejection_reason}`);
}

// ========================================
// OTC BOOKINGS MANAGEMENT
// ========================================

let allOTCBookings = [];

function loadOTCBookings() {
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_otc_bookings'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allOTCBookings = data.data;
            renderOTCBookings(allOTCBookings);
        } else {
            showAlert('Error loading OTC bookings: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading OTC bookings', 'error');
    });
}

function renderOTCBookings(bookings) {
    const tbody = document.getElementById('otcBookingsTableBody');
    
    if (!bookings || bookings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No OTC bookings found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = bookings.map(booking => {
        const statusClass = getStatusClass(booking.status);
        const scheduledDate = new Date(booking.scheduled_date).toLocaleDateString('en-US', { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        return `
            <tr>
                <td>#${booking.request_id}</td>
                <td>${booking.client_name}</td>
                <td>${booking.class_name}</td>
                <td>${scheduledDate}</td>
                <td><strong>₱${parseFloat(booking.amount).toFixed(2)}</strong></td>
                <td><span class="status-badge ${statusClass}">${booking.status}</span></td>
                <td>${booking.payment_date ? new Date(booking.payment_date).toLocaleDateString() : '-'}</td>
                <td>
                    <button onclick="viewOTCBooking(${booking.request_id})" class="action-btn view-btn" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filterOTCBookings() {
    const statusFilter = document.getElementById('otcStatusFilter').value;
    
    let filtered = allOTCBookings;
    
    if (statusFilter !== 'all') {
        filtered = filtered.filter(b => b.status === statusFilter);
    }
    
    renderOTCBookings(filtered);
}

function viewOTCBooking(requestId) {
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_otc_booking&request_id=${requestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const booking = data.data;
            displayOTCBookingModal(booking);
        } else {
            showAlert('Error loading booking details', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error loading booking details', 'error');
    });
}

function displayOTCBookingModal(booking) {
    document.getElementById('otc-booking-id').textContent = `#${booking.request_id}`;
    document.getElementById('otc-member-name').textContent = booking.client_name;
    document.getElementById('otc-class-name').textContent = booking.class_name;
    document.getElementById('otc-scheduled-date').textContent = new Date(booking.scheduled_date).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    document.getElementById('otc-scheduled-time').textContent = booking.scheduled_time;
    document.getElementById('otc-amount').textContent = `₱${parseFloat(booking.amount).toFixed(2)}`;
    document.getElementById('otc-request-id').value = booking.request_id;
    
    // Handle payment proof image
    const proofImg = document.getElementById('otc-payment-proof');
    const noProofText = document.getElementById('otc-no-proof');
    
    if (booking.payment_proof && booking.payment_proof !== 'OTC-PENDING' && booking.payment_proof !== '') {
        proofImg.src = '../uploads/' + booking.payment_proof;
        proofImg.classList.remove('hidden');
        noProofText.style.display = 'none';
    } else {
        proofImg.classList.add('hidden');
        noProofText.style.display = 'block';
    }
    
    // Show/hide action buttons based on status
    const approveBtn = document.getElementById('otc-approve-btn');
    const rejectBtn = document.getElementById('otc-reject-btn');
    
    if (booking.status === 'Payment Submitted' || booking.status === 'Pending Payment') {
        approveBtn.style.display = 'inline-block';
        rejectBtn.style.display = 'inline-block';
    } else {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
    }
    
    document.getElementById('otcBookingModal').style.display = 'flex';
}

function closeOTCModal() {
    document.getElementById('otcBookingModal').style.display = 'none';
}

function openOTCRejectModal() {
    document.getElementById('otc-reject-request-id').value = document.getElementById('otc-request-id').value;
    document.getElementById('otcRejectionReason').value = '';
    document.getElementById('otcRejectModal').style.display = 'flex';
}

function closeOTCRejectModal() {
    document.getElementById('otcRejectModal').style.display = 'none';
}

function approveOTCBooking() {
    const requestId = document.getElementById('otc-request-id').value;
    
    if (!confirm('Are you sure you want to approve this OTC payment?')) {
        return;
    }
    
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=approve_otc_booking&request_id=${requestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('OTC booking approved successfully', 'success');
            closeOTCModal();
            loadOTCBookings();
        } else {
            showAlert('Error approving booking: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error approving booking', 'error');
    });
}

function submitOTCReject() {
    const requestId = document.getElementById('otc-reject-request-id').value;
    const reason = document.getElementById('otcRejectionReason').value.trim();
    
    if (!reason) {
        showAlert('Please provide a rejection reason', 'warning');
        return;
    }
    
    fetch('includes/otc_bookings_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=reject_otc_booking&request_id=${requestId}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('OTC booking rejected successfully', 'success');
            closeOTCRejectModal();
            closeOTCModal();
            loadOTCBookings();
        } else {
            showAlert('Error rejecting booking: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error rejecting booking', 'error');
    });
}

function getStatusClass(status) {
    switch(status) {
        case 'Pending Payment':
            return 'status-pending';
        case 'Payment Submitted':
            return 'status-submitted';
        case 'Payment Verified':
            return 'status-verified';
        case 'Rejected':
            return 'status-rejected';
        default:
            return 'status-default';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}