/**
 * Empire Fitness - Real-time Notification Client
 * Include this file in your PHP pages to enable real-time notifications
 * Usage: <script src="<?php echo $baseUrl; ?>/js/realtime-notifications.js"></script>
 */

class NotificationManager {
    constructor(config = {}) {
        this.socket = null;
        this.userId = config.userId || null;
        this.userRole = config.userRole || null;
        this.userName = config.userName || null;
        this.serverUrl = config.serverUrl || 'http://localhost:3001';
        this.notificationContainer = config.notificationContainer || '#notifications';
        this.notifications = [];
        this.maxNotifications = config.maxNotifications || 10;
        this.autoHideTime = config.autoHideTime || 5000; // 5 seconds

        this.init();
    }

    /**
     * Initialize Socket.IO connection
     */
    init() {
        console.log('🔌 Initializing real-time notifications...');
        
        // Check if Socket.IO already loaded
        if (typeof io !== 'undefined') {
            console.log('📦 Socket.IO already loaded');
            this.connect();
            return;
        }
        
        // Load Socket.IO client library from local file
        const script = document.createElement('script');
        script.src = '/empirefitness/js/socket.io.min.js';
        script.onerror = () => {
            console.error('❌ Failed to load Socket.IO from local file');
            console.log('ℹ️  Attempting server endpoint...');
            script.src = 'http://localhost:3001/socket.io/socket.io.min.js';
            script.onload = () => {
                console.log('✅ Socket.IO loaded from server endpoint');
                this.connect();
            };
            script.onerror = () => {
                console.error('❌ Failed server endpoint');
                console.log('ℹ️  Attempting CDN...');
                script.src = 'https://cdn.socket.io/4.5.4/socket.io.min.js';
                script.onload = () => {
                    console.log('✅ Socket.IO loaded from CDN');
                    this.connect();
                };
                script.onerror = () => console.error('❌ All Socket.IO load attempts failed');
                document.head.appendChild(script);
            };
            document.head.appendChild(script);
        };
        script.onload = () => {
            console.log('✅ Socket.IO loaded from local file');
            this.connect();
        };
        document.head.appendChild(script);
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        console.log('🔌 Attempting connection to', this.serverUrl);
        
        if (typeof io === 'undefined') {
            console.error('❌ Socket.IO library not loaded');
            setTimeout(() => this.init(), 1000); // Retry after 1 second
            return;
        }

        try {
            this.socket = io(this.serverUrl, {
                reconnection: true,
                reconnectionDelay: 1000,
                reconnectionDelayMax: 5000,
                reconnectionAttempts: 5
            });

            // Connection events
            this.socket.on('connect', () => {
                console.log('✅ Connected to real-time server');
                console.log('📍 Socket ID:', this.socket.id);
                this.userJoin();
            });

            this.socket.on('connect_error', (error) => {
                console.error('❌ Connection error:', error);
            });

            this.socket.on('disconnect', (reason) => {
                console.log('👋 Disconnected from server. Reason:', reason);
            });

            // Listen for all notification types
            this.setupNotificationListeners();
        } catch (e) {
            console.error('❌ Exception during connection:', e);
        }
    }

    /**
     * Tell server that user has joined
     */
    userJoin() {
        this.socket.emit('user:join', {
            userId: this.userId,
            userRole: this.userRole,
            userName: this.userName
        });
    }

    /**
     * Check connection status
     */
    getStatus() {
        return {
            connected: this.socket && this.socket.connected,
            socketId: this.socket ? this.socket.id : null,
            userId: this.userId,
            userRole: this.userRole,
            serverUrl: this.serverUrl,
            notificationCount: this.notifications.length
        };
    }

    /**
     * Manually emit a test notification to self
     */
    sendTestNotification(title = '✅ Test', message = 'Notification system working!') {
        if (this.socket && this.socket.connected) {
            this.socket.emit('test:notification', {
                title,
                message,
                type: 'test'
            });
        } else {
            console.error('❌ Socket not connected. Cannot send test notification.');
        }
    }

    /**
     * Setup all notification listeners
     */
    setupNotificationListeners() {
        // Coach Assignment Notifications
        this.socket.on('notification:assignment_new', (data) => {
            this.showNotification(data);
            this.triggerCallback('onAssignmentNew', data);
        });

        this.socket.on('notification:assignment_updated', (data) => {
            this.showNotification(data);
            this.triggerCallback('onAssignmentUpdated', data);
        });

        this.socket.on('notification:assignment_removed', (data) => {
            this.showNotification(data);
            this.triggerCallback('onAssignmentRemoved', data);
        });

        // Schedule Notifications
        this.socket.on('notification:schedule_created', (data) => {
            this.showNotification(data);
            this.triggerCallback('onScheduleCreated', data);
        });

        this.socket.on('notification:schedule_updated', (data) => {
            this.showNotification(data);
            this.triggerCallback('onScheduleUpdated', data);
        });

        this.socket.on('notification:schedule_cancelled', (data) => {
            this.showNotification(data);
            this.triggerCallback('onScheduleCancelled', data);
        });

        // Manager Notifications
        this.socket.on('notification:manager_alert', (data) => {
            this.showNotification(data);
            this.triggerCallback('onManagerAlert', data);
        });

        // Activity Notifications
        this.socket.on('notification:member_checkin', (data) => {
            this.showNotification(data);
            this.triggerCallback('onMemberCheckIn', data);
        });

        this.socket.on('notification:coach_status_changed', (data) => {
            this.showNotification(data);
            this.triggerCallback('onCoachStatusChanged', data);
        });

        // Assessment Notifications
        this.socket.on('notification:assessment_created', (data) => {
            this.showNotification(data);
            this.triggerCallback('onAssessmentCreated', data);
        });

        this.socket.on('notification:assessment_completed', (data) => {
            this.showNotification(data);
            this.triggerCallback('onAssessmentCompleted', data);
        });

        // User Status Notifications
        this.socket.on('notification:user_online', (data) => {
            console.log(`👤 ${data.userName} is now online`);
            this.triggerCallback('onUserOnline', data);
        });

        this.socket.on('notification:user_offline', (data) => {
            console.log(`👋 ${data.userName} went offline`);
            this.triggerCallback('onUserOffline', data);
        });

        // Custom notifications
        this.socket.on('notification:custom', (data) => {
            this.showNotification(data);
            this.triggerCallback('onCustomNotification', data);
        });

        this.socket.on('notification:broadcast', (data) => {
            this.showNotification(data);
            this.triggerCallback('onBroadcast', data);
        });
    }

    /**
     * Show notification in UI
     */
    showNotification(data) {
        const notification = {
            id: Date.now(),
            ...data
        };

        this.notifications.push(notification);

        // Keep only recent notifications
        if (this.notifications.length > this.maxNotifications) {
            this.notifications.shift();
        }

        // Create notification element
        const element = this.createNotificationElement(notification);
        const container = document.querySelector(this.notificationContainer);
        
        if (container) {
            container.appendChild(element);

            // Auto-hide after delay
            setTimeout(() => {
                this.removeNotification(notification.id);
            }, this.autoHideTime);
        }

        // Play sound if enabled
        if (this.soundEnabled) {
            this.playNotificationSound();
        }

        // Browser notification if enabled
        if (this.browserNotificationEnabled && 'Notification' in window) {
            this.showBrowserNotification(data);
        }
    }

    /**
     * Create notification DOM element
     */
    createNotificationElement(notification) {
        const div = document.createElement('div');
        div.className = 'notification notification-' + notification.type;
        div.id = 'notif-' + notification.id;
        
        const severity = notification.severity || 'info';
        const icon = notification.icon || '🔔';
        
        div.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${icon}</span>
                <div class="notification-body">
                    <div class="notification-title">${notification.title || notification.message}</div>
                    ${notification.message && notification.title ? `<div class="notification-message">${notification.message}</div>` : ''}
                </div>
                <button class="notification-close" onclick="notificationManager.removeNotification(${notification.id})">×</button>
            </div>
        `;

        return div;
    }

    /**
     * Remove notification from UI
     */
    removeNotification(notificationId) {
        const element = document.getElementById('notif-' + notificationId);
        if (element) {
            element.classList.add('fade-out');
            setTimeout(() => {
                element.remove();
            }, 300);
        }
        
        // Remove from array
        this.notifications = this.notifications.filter(n => n.id !== notificationId);
    }

    /**
     * Show browser notification
     */
    showBrowserNotification(data) {
        if (Notification.permission === 'granted') {
            new Notification(data.title || data.message, {
                icon: '/images/favicon.ico',
                badge: '/images/logo.png',
                body: data.message || '',
                tag: 'empire-fitness',
                requireInteraction: false
            });
        }
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.play().catch(err => console.log('Could not play sound:', err));
    }

    /**
     * Emit event to server
     */
    emit(event, data) {
        if (this.socket && this.socket.connected) {
            this.socket.emit(event, data);
            console.log(`📤 Emitting event: ${event}`, data);
        } else {
            console.error('❌ Socket not connected');
        }
    }

    /**
     * Send assignment notification
     */
    sendAssignmentNotification(type, data) {
        this.emit(`assignment:${type}`, data);
    }

    /**
     * Send schedule notification
     */
    sendScheduleNotification(type, data) {
        this.emit(`schedule:${type}`, data);
    }

    /**
     * Send manager alert
     */
    sendManagerAlert(title, message, severity = 'info') {
        this.emit('manager:alert', {
            title,
            message,
            severity,
            userId: this.userId
        });
    }

    /**
     * Send activity notification
     */
    sendActivityNotification(type, data) {
        this.emit(`activity:${type}`, data);
    }

    /**
     * Request live data
     */
    requestLiveData(type) {
        this.socket.emit(`status:request_${type}`, {});
    }

    /**
     * Trigger callback function
     */
    triggerCallback(callbackName, data) {
        if (typeof window[callbackName] === 'function') {
            window[callbackName](data);
        }
    }

    /**
     * Enable browser notifications
     */
    enableBrowserNotifications() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        this.browserNotificationEnabled = true;
    }

    /**
     * Enable notification sound
     */
    enableSound() {
        this.soundEnabled = true;
    }

    /**
     * Disable notification sound
     */
    disableSound() {
        this.soundEnabled = false;
    }

    /**
     * Clear all notifications
     */
    clearAll() {
        this.notifications.forEach(notif => {
            this.removeNotification(notif.id);
        });
    }

    /**
     * Get notification history
     */
    getHistory() {
        return this.notifications;
    }

    /**
     * Disconnect from server
     */
    disconnect() {
        if (this.socket) {
            this.socket.disconnect();
            console.log('🛑 Disconnected from server');
        }
    }
}

// Create global instance
let notificationManager;

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    // Get user info from PHP data attributes or session
    const userId = document.body.dataset.userId || sessionStorage.getItem('user_id') || null;
    const userRole = document.body.dataset.userRole || sessionStorage.getItem('user_role') || null;
    const userName = document.body.dataset.userName || sessionStorage.getItem('user_name') || null;

    if (userId && userRole) {
        const notificationManager = new NotificationManager({
            userId,
            userRole,
            userName,
            serverUrl: 'http://localhost:3001',
            notificationContainer: '#notifications'
        });

        console.log(`✅ Notification manager initialized for ${userName} (${userRole})`);
    } else {
        console.warn('⚠️ User info not available for notifications');
    }
});
