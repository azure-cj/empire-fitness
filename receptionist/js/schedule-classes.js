// Schedule Classes Management
let currentView = 'list';
let currentDate = new Date();
let allClasses = [];
let filteredClasses = [];
let selectedClassType = '';
let selectedStatus = '';
let searchTerm = '';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadClasses();
    loadStats();
    renderCalendar();
    
    // Add search event listener
    const searchInput = document.getElementById('search-classes');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterClassesList);
    }
});

// Load all classes from database
function loadClasses() {
    console.log('📚 Loading classes from API...');
    fetch('includes/schedule_classes_handler.php?action=get_classes')
        .then(response => {
            console.log('📡 Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('✅ API Response:', data);
            if (data.success) {
                allClasses = data.classes || [];
                console.log(`✅ Loaded ${allClasses.length} classes`);
                filteredClasses = [...allClasses];
                renderCalendar();
            } else {
                console.error('❌ API Error:', data.message, data.error);
                showToast(`Failed to load classes: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            console.error('❌ Fetch Error:', error);
            showToast('Error loading classes: ' + error.message, 'error');
        });
}

// Load statistics
function loadStats() {
    console.log('📊 Loading statistics...');
    fetch('includes/schedule_classes_handler.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            console.log('✅ Stats Response:', data);
            if (data.success) {
                document.getElementById('today-classes').textContent = data.stats.today;
                document.getElementById('total-bookings').textContent = data.stats.bookings;
                document.getElementById('week-classes').textContent = data.stats.week;
            } else {
                console.error('❌ Stats Error:', data.message);
            }
        })
        .catch(error => console.error('❌ Stats fetch error:', error));
}

// Change calendar view
function changeView(view) {
    currentView = view;
    
    // Update active button
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.view-btn').classList.add('active');
    
    // Show selected view
    document.querySelectorAll('.calendar-view').forEach(v => {
        v.classList.remove('active');
    });
    document.getElementById(`${view}-view`).classList.add('active');
    
    renderCalendar();
}

// Navigate date (prev/next)
function navigateDate(direction) {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + (direction === 'next' ? 1 : -1));
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 7 : -7));
    } else if (currentView === 'day') {
        currentDate.setDate(currentDate.getDate() + (direction === 'next' ? 1 : -1));
    }
    renderCalendar();
}

// Go to today
function goToToday() {
    currentDate = new Date();
    renderCalendar();
}

// Filter classes by type
function filterClasses() {
    selectedClassType = document.getElementById('class-type-filter').value;
    
    if (selectedClassType) {
        filteredClasses = allClasses.filter(c => c.class_type === selectedClassType);
    } else {
        filteredClasses = [...allClasses];
    }
    
    renderCalendar();
}

// Filter classes for list view
function filterClassesList() {
    const typeFilter = document.getElementById('filter-type')?.value || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';
    const searchInput = document.getElementById('search-classes')?.value.toLowerCase() || '';
    
    filteredClasses = allClasses.filter(cls => {
        const matchType = !typeFilter || cls.class_type === typeFilter;
        const matchStatus = !statusFilter || cls.status === statusFilter;
        const matchSearch = !searchInput || 
            cls.class_name.toLowerCase().includes(searchInput) ||
            (cls.coach_name && cls.coach_name.toLowerCase().includes(searchInput)) ||
            (cls.room_location && cls.room_location.toLowerCase().includes(searchInput)) ||
            cls.class_type.toLowerCase().includes(searchInput);
        
        return matchType && matchStatus && matchSearch;
    });
    
    if (currentView === 'list') {
        renderListView();
    }
}

// Render calendar based on current view
function renderCalendar() {
    updateDateDisplay();
    
    if (currentView === 'month') {
        renderMonthView();
    } else if (currentView === 'week') {
        renderWeekView();
    } else if (currentView === 'day') {
        renderDayView();
    } else if (currentView === 'list') {
        renderListView();
    }
}

// Update date display
function updateDateDisplay() {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
    let displayText = '';
    
    if (currentView === 'month') {
        displayText = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    } else if (currentView === 'week') {
        const weekStart = getWeekStart(currentDate);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);
        displayText = `${monthNames[weekStart.getMonth()]} ${weekStart.getDate()} - ${monthNames[weekEnd.getMonth()]} ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
    } else if (currentView === 'day') {
        displayText = `${monthNames[currentDate.getMonth()]} ${currentDate.getDate()}, ${currentDate.getFullYear()}`;
    } else if (currentView === 'list') {
        displayText = 'All Upcoming Classes';
    }
    
    document.getElementById('current-date').textContent = displayText;
}

// Render Month View
function renderMonthView() {
    const grid = document.getElementById('month-grid');
    grid.innerHTML = '';
    
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let i = 0; i < 42; i++) {
        const cellDate = new Date(startDate);
        cellDate.setDate(cellDate.getDate() + i);
        
        const cell = document.createElement('div');
        cell.className = 'calendar-cell';
        
        if (cellDate.getMonth() !== month) {
            cell.classList.add('other-month');
        }
        
        if (cellDate.getTime() === today.getTime()) {
            cell.classList.add('today');
        }
        
        const dateDiv = document.createElement('div');
        dateDiv.className = 'cell-date';
        if (cellDate.getTime() === today.getTime()) {
            dateDiv.classList.add('today');
        }
        dateDiv.textContent = cellDate.getDate();
        cell.appendChild(dateDiv);
        
        // Add classes for this date
        const dayClasses = getClassesForDate(cellDate);
        dayClasses.slice(0, 3).forEach(cls => {
            const event = document.createElement('div');
            event.className = `class-event ${cls.class_type.toLowerCase().replace(' ', '')}`;
            event.textContent = `${cls.start_time} ${cls.class_name}`;
            event.onclick = () => showClassDetails(cls.schedule_id);
            cell.appendChild(event);
        });
        
        if (dayClasses.length > 3) {
            const more = document.createElement('div');
            more.className = 'more-events';
            more.textContent = `+${dayClasses.length - 3} more`;
            more.onclick = () => showDayClasses(cellDate);
            cell.appendChild(more);
        }
        
        grid.appendChild(cell);
    }
}

// Render Week View
// Render Week View - FIXED VERSION
function renderWeekView() {
    const weekStart = getWeekStart(currentDate);
    const grid = document.getElementById('week-grid');
    grid.innerHTML = '';
    
    // Clear and rebuild header
    const headerContainer = document.querySelector('.week-header');
    headerContainer.innerHTML = '';
    
    const dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Create day headers
    for (let i = 0; i < 7; i++) {
        const day = new Date(weekStart);
        day.setDate(day.getDate() + i);
        
        const col = document.createElement('div');
        col.className = 'week-day-header';
        if (day.getTime() === today.getTime()) {
            col.classList.add('today');
        }
        
        col.innerHTML = `
            <span class="day-name">${dayNames[i]}</span>
            <span class="day-date${day.getTime() === today.getTime() ? ' today' : ''}">${day.getDate()}</span>
        `;
        
        headerContainer.appendChild(col);
    }
    
    // Create day columns for schedule
    for (let i = 0; i < 7; i++) {
        const day = new Date(weekStart);
        day.setDate(day.getDate() + i);
        
        const dayCol = document.createElement('div');
        dayCol.className = 'day-column';
        
        const dayClasses = getClassesForDate(day);
        
        if (dayClasses.length === 0) {
            dayCol.innerHTML = '<div class="no-schedules">No classes scheduled</div>';
        } else {
            dayClasses.forEach(cls => {
                const event = createWeekScheduleEvent(cls);
                dayCol.appendChild(event);
            });
        }
        
        grid.appendChild(dayCol);
    }
}

// Create schedule event element for week view
function createWeekScheduleEvent(cls) {
    const event = document.createElement('div');
    event.className = `schedule-event ${cls.status.toLowerCase()}`;
    
    // Add class type as a class for border color
    const classType = cls.class_type.toLowerCase().replace(' ', '');
    event.classList.add(classType);
    
    event.innerHTML = `
        <div class="event-time">
            <i class="fas fa-clock"></i>
            ${formatTime(cls.start_time)} - ${formatTime(cls.end_time)}
        </div>
        <div class="event-title">${cls.class_name}</div>
        <div class="event-coach">
            <i class="fas fa-user-tie"></i>
            ${cls.coach_name || 'No Coach'}
        </div>
        <div class="event-capacity">
            <i class="fas fa-users"></i>
            ${cls.current_bookings}/${cls.max_capacity}
        </div>
    `;
    
    event.onclick = () => showClassDetails(cls.schedule_id);
    
    return event;
}

// Create schedule event element for week/day view
function createScheduleEvent(cls) {
    const event = document.createElement('div');
    event.className = `schedule-event ${cls.class_type.toLowerCase().replace(' ', '')}`;
    
    const startTime = cls.start_time.split(':');
    const endTime = cls.end_time.split(':');
    const startMinutes = parseInt(startTime[0]) * 60 + parseInt(startTime[1]);
    const endMinutes = parseInt(endTime[0]) * 60 + parseInt(endTime[1]);
    const startOffset = Math.max(0, startMinutes - (6 * 60)); // Offset from 6 AM, min 0
    const duration = Math.max(20, endMinutes - startMinutes); // Minimum height of 20px
    
    event.style.top = `${startOffset}px`;
    event.style.height = `${duration}px`;
    event.style.minHeight = '20px';
    
    event.innerHTML = `
        <strong>${cls.class_name}</strong><br>
        <small>${cls.start_time} - ${cls.end_time}</small><br>
        <small>${cls.coach_name || 'TBA'}</small>
    `;
    
    event.onclick = () => showClassDetails(cls.schedule_id);
    
    return event;
}

// Render Day View
function renderDayView() {
    const container = document.getElementById('day-schedule');
    container.innerHTML = '';
    
    const header = document.createElement('div');
    header.className = 'day-header';
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    header.innerHTML = `<h3>${dayNames[currentDate.getDay()]}, ${currentDate.toLocaleDateString()}</h3>`;
    container.appendChild(header);
    
    const eventsDiv = document.createElement('div');
    eventsDiv.className = 'day-events';
    
    const dayClasses = getClassesForDate(currentDate);
    
    if (dayClasses.length === 0) {
        eventsDiv.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem;">No classes scheduled for this day</p>';
    } else {
        dayClasses.forEach(cls => {
            const event = document.createElement('div');
            event.className = 'day-event';
            event.style.borderLeftColor = getClassColor(cls.class_type);
            
            const duration = calculateDuration(cls.start_time, cls.end_time);
            
            event.innerHTML = `
                <div class="event-time-block">
                    <div class="event-start-time">${formatTime(cls.start_time)}</div>
                    <div class="event-duration">${duration} min</div>
                </div>
                <div class="event-details">
                    <div class="event-title">${cls.class_name}</div>
                    <div class="event-meta">
                        <div class="event-meta-item">
                            <i class="fas fa-user-tie"></i>
                            ${cls.coach_name || 'No Coach'}
                        </div>
                        <div class="event-meta-item">
                            <i class="fas fa-users"></i>
                            ${cls.current_bookings} / ${cls.max_capacity}
                        </div>
                        <div class="event-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            ${cls.room_location || 'TBA'}
                        </div>
                    </div>
                </div>
            `;
            
            event.onclick = () => showClassDetails(cls.schedule_id);
            eventsDiv.appendChild(event);
        });
    }
    
    container.appendChild(eventsDiv);
}

// Render List View
function renderListView() {
    const container = document.getElementById('list-container');
    container.innerHTML = '';
    
    // Group classes by date
    const groupedClasses = {};
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    filteredClasses.forEach(cls => {
        const classDate = new Date(cls.schedule_date);
        if (classDate >= today) {
            const dateKey = classDate.toDateString();
            if (!groupedClasses[dateKey]) {
                groupedClasses[dateKey] = [];
            }
            groupedClasses[dateKey].push(cls);
        }
    });
    
    const sortedDates = Object.keys(groupedClasses).sort((a, b) => new Date(a) - new Date(b));
    
    if (sortedDates.length === 0) {
        container.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem;">No upcoming classes</p>';
        return;
    }
    
    sortedDates.forEach(dateKey => {
        const group = document.createElement('div');
        group.className = 'list-date-group';
        
        const date = new Date(dateKey);
        const header = document.createElement('div');
        header.className = 'list-date-header';
        header.innerHTML = `
            <h3>${date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</h3>
            <span class="list-date-count">${groupedClasses[dateKey].length} classes</span>
        `;
        group.appendChild(header);
        
        const eventsDiv = document.createElement('div');
        eventsDiv.className = 'list-events';
        
        groupedClasses[dateKey].sort((a, b) => a.start_time.localeCompare(b.start_time));
        
        groupedClasses[dateKey].forEach(cls => {
            const event = document.createElement('div');
            event.className = 'list-event';
            
            const capacity = cls.max_capacity > 0 ? (cls.current_bookings / cls.max_capacity) * 100 : 0;
            const capacityClass = capacity >= 100 ? 'full' : capacity >= 75 ? 'warning' : '';
            
            event.innerHTML = `
                <div class="list-event-time">
                    ${formatTime(cls.start_time)} - ${formatTime(cls.end_time)}
                </div>
                <div>
                    <div class="list-event-title">${cls.class_name}</div>
                    <div class="list-event-coach">
                        <i class="fas fa-user-tie"></i> ${cls.coach_name || 'No Coach'}
                    </div>
                </div>
                <div class="list-event-capacity">
                    <span>${cls.current_bookings}/${cls.max_capacity}</span>
                    <div class="capacity-bar">
                        <div class="capacity-fill ${capacityClass}" style="width: ${Math.min(capacity, 100)}%"></div>
                    </div>
                </div>
                <div>
                    <span class="list-event-type" style="background: ${getClassColor(cls.class_type)}">
                        ${cls.class_type}
                    </span>
                </div>
            `;
            
            event.onclick = () => showClassDetails(cls.schedule_id);
            eventsDiv.appendChild(event);
        });
        
        group.appendChild(eventsDiv);
        container.appendChild(group);
    });
}

// Get classes for a specific date
function getClassesForDate(date) {
    // Create a date string in local timezone (YYYY-MM-DD)
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    
    return filteredClasses.filter(cls => cls.schedule_date === dateStr)
        .sort((a, b) => a.start_time.localeCompare(b.start_time));
}

// Get week start (Sunday)
function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day;
    return new Date(d.setDate(diff));
}

// Show class details modal
function showClassDetails(scheduleId) {
    fetch(`includes/schedule_classes_handler.php?action=get_class_details&schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateClassModal(data.class);
                loadParticipants(scheduleId);
                document.getElementById('class-modal').classList.add('active');
            } else {
                showToast('Failed to load class details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading class details', 'error');
        });
}

// Populate class modal
function populateClassModal(cls) {
    document.getElementById('modal-class-name').textContent = cls.class_name;
    document.getElementById('modal-class-type').textContent = cls.class_type;
    document.getElementById('modal-coach-name').textContent = cls.coach_name || 'Not Assigned';
    document.getElementById('modal-date').textContent = formatDate(cls.schedule_date);
    document.getElementById('modal-time').textContent = `${formatTime(cls.start_time)} - ${formatTime(cls.end_time)}`;
    document.getElementById('modal-duration').textContent = `${calculateDuration(cls.start_time, cls.end_time)} minutes`;
    document.getElementById('modal-location').textContent = cls.room_location || 'To Be Announced';
    document.getElementById('modal-bookings').textContent = cls.current_bookings || 0;
    document.getElementById('modal-capacity').textContent = cls.max_capacity;
    document.getElementById('modal-description').textContent = cls.description || 'No description available';
    
    const statusSpan = document.getElementById('modal-status');
    statusSpan.innerHTML = `<span class="status-indicator ${cls.status.toLowerCase()}"></span> ${cls.status}`;
    
    const capacity = cls.max_capacity > 0 ? (cls.current_bookings / cls.max_capacity) * 100 : 0;
    const capacityStatus = document.getElementById('capacity-status');
    if (capacity >= 100) {
        capacityStatus.className = 'capacity-badge full';
        capacityStatus.textContent = 'Full';
    } else if (capacity >= 75) {
        capacityStatus.className = 'capacity-badge filling';
        capacityStatus.textContent = 'Filling Up';
    } else {
        capacityStatus.className = 'capacity-badge available';
        capacityStatus.textContent = 'Available';
    }
}

// Load participants
function loadParticipants(scheduleId) {
    fetch(`includes/schedule_classes_handler.php?action=get_participants&schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayParticipants(data.participants);
            } else {
                document.getElementById('participants-list').innerHTML = '<p class="text-muted">No participants yet</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('participants-list').innerHTML = '<p class="text-muted">Failed to load participants</p>';
        });
}

// Display participants
function displayParticipants(participants) {
    const container = document.getElementById('participants-list');
    document.getElementById('participants-count').textContent = participants.length;
    
    if (participants.length === 0) {
        container.innerHTML = '<p class="text-muted">No participants yet</p>';
        return;
    }
    
    container.innerHTML = '';
    participants.forEach(p => {
        const item = document.createElement('div');
        item.className = 'participant-item';
        
        const initial = p.full_name.charAt(0).toUpperCase();
        
        item.innerHTML = `
            <div class="participant-avatar">${initial}</div>
            <div class="participant-name">${p.full_name}</div>
        `;
        
        container.appendChild(item);
    });
}

// Close class modal
function closeClassModal() {
    document.getElementById('class-modal').classList.remove('active');
}

// Open legend modal
function openLegendModal() {
    document.getElementById('legend-modal').classList.add('active');
}

// Close legend modal
function closeLegendModal() {
    document.getElementById('legend-modal').classList.remove('active');
}

// Show day classes (when clicking "+X more")
function showDayClasses(date) {
    currentDate = new Date(date);
    changeView('day');
}

// Utility functions
function getClassColor(type) {
    const colors = {
        'Boxing': '#ef4444',
        'Kickboxing': '#f59e0b',
        'Muay Thai': '#8b5cf6',
        'Zumba': '#ec4899',
        'HIIT': '#10b981',
        'Other': '#6b7280'
    };
    return colors[type] || '#6b7280';
}

function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function calculateDuration(startTime, endTime) {
    const [startH, startM] = startTime.split(':').map(Number);
    const [endH, endM] = endTime.split(':').map(Number);
    return (endH * 60 + endM) - (startH * 60 + startM);
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('.toast-icon');
    
    toastMessage.textContent = message;
    
    toastIcon.className = 'toast-icon fas';
    if (type === 'success') {
        toastIcon.classList.add('fa-check-circle');
    } else if (type === 'error') {
        toastIcon.classList.add('fa-times-circle');
    }
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
});