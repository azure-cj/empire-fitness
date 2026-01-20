/**
 * Empire Fitness - Real-time Notification Server
 * Handles WebSocket connections for coaches, managers, and receptionists
 * Supports: Coach assignments, schedule updates, manager notifications, and activity alerts
 */

const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const mysql = require('mysql2/promise');
const path = require('path');
require('dotenv').config();

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Middleware
app.use(cors());
app.use(express.json());

// MySQL Connection Pool
const pool = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'empirefitness',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Store active user connections
const activeUsers = new Map();
const userRooms = new Map(); // Track which rooms users are in

console.log('🚀 Empire Fitness Real-time Server Starting...');
console.log(`📡 Server running on http://localhost:${process.env.PORT || 3000}`);
console.log(`🔌 WebSocket ready on ws://localhost:${process.env.PORT || 3000}`);

// ===== SOCKET.IO CONNECTION HANDLING =====
io.on('connection', (socket) => {
    console.log(`✅ User connected: ${socket.id}`);

    // User joins with their role and ID
    socket.on('user:join', (data) => {
        const { userId, userRole, userName } = data;
        
        activeUsers.set(socket.id, {
            userId,
            userRole,
            userName,
            socketId: socket.id
        });

        // Join specific role room
        socket.join(`role:${userRole}`);
        
        // Join user-specific room
        socket.join(`user:${userId}`);

        console.log(`👤 ${userName} (${userRole}) joined - Socket: ${socket.id}`);
        
        // Notify managers of new user connection
        io.to('role:Manager').emit('notification:user_online', {
            userId,
            userName,
            userRole,
            timestamp: new Date()
        });
    });

    // ===== COACH ASSIGNMENT NOTIFICATIONS =====
    socket.on('assignment:created', async (data) => {
        const { coachId, memberId, coachName, memberName, createdBy } = data;
        
        console.log(`📋 New Assignment: ${memberName} → ${coachName}`);

        try {
            // Notify the assigned coach
            io.to(`user:${coachId}`).emit('notification:assignment_new', {
                type: 'assignment_created',
                memberId,
                memberName,
                message: `You have been assigned to work with ${memberName}`,
                icon: '👤',
                timestamp: new Date(),
                actionUrl: `/manager/coach_profile.php?coach_id=${coachId}`
            });

            // Notify all managers
            io.to('role:Manager').emit('notification:assignment_created', {
                type: 'assignment_created',
                coachId,
                coachName,
                memberId,
                memberName,
                createdBy,
                message: `${coachName} assigned to ${memberName}`,
                icon: '✅',
                timestamp: new Date()
            });

            // Log to database
            await logNotification(
                coachId,
                'assignment_created',
                `New assignment: ${memberName}`,
                'Coach'
            );

        } catch (error) {
            console.error('❌ Error handling assignment creation:', error);
        }
    });

    socket.on('assignment:updated', (data) => {
        const { coachId, memberId, coachName, memberName, changes } = data;
        
        console.log(`🔄 Assignment Updated: ${memberName} with ${coachName}`);

        io.to(`user:${coachId}`).emit('notification:assignment_updated', {
            type: 'assignment_updated',
            memberId,
            memberName,
            changes,
            message: `Assignment updated for ${memberName}`,
            icon: '🔄',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:assignment_updated', {
            type: 'assignment_updated',
            coachId,
            coachName,
            memberId,
            memberName,
            changes,
            message: `Assignment updated: ${memberName}`,
            icon: '🔄',
            timestamp: new Date()
        });
    });

    socket.on('assignment:removed', (data) => {
        const { coachId, memberId, coachName, memberName, reason } = data;
        
        console.log(`❌ Assignment Removed: ${memberName} from ${coachName}`);

        io.to(`user:${coachId}`).emit('notification:assignment_removed', {
            type: 'assignment_removed',
            memberId,
            memberName,
            reason,
            message: `Assignment removed: ${memberName}`,
            icon: '🚫',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:assignment_removed', {
            type: 'assignment_removed',
            coachId,
            coachName,
            memberId,
            memberName,
            reason,
            message: `${coachName} - assignment removed from ${memberName}`,
            icon: '🚫',
            timestamp: new Date()
        });
    });

    // ===== SCHEDULE NOTIFICATIONS =====
    socket.on('schedule:created', (data) => {
        const { coachId, coachName, className, scheduleDate, startTime, endTime } = data;
        
        console.log(`📅 New Schedule: ${className} by ${coachName} on ${scheduleDate}`);

        io.to(`user:${coachId}`).emit('notification:schedule_created', {
            type: 'schedule_created',
            className,
            scheduleDate,
            startTime,
            endTime,
            message: `New class scheduled: ${className}`,
            icon: '📅',
            timestamp: new Date()
        });

        // Notify all managers
        io.to('role:Manager').emit('notification:schedule_created', {
            type: 'schedule_created',
            coachId,
            coachName,
            className,
            scheduleDate,
            startTime,
            endTime,
            message: `${coachName} scheduled ${className}`,
            icon: '📅',
            timestamp: new Date()
        });
    });

    socket.on('schedule:updated', (data) => {
        const { coachId, coachName, className, scheduleDate, startTime, endTime, changes } = data;
        
        console.log(`🔄 Schedule Updated: ${className}`);

        io.to(`user:${coachId}`).emit('notification:schedule_updated', {
            type: 'schedule_updated',
            className,
            changes,
            message: `Schedule updated: ${className}`,
            icon: '🔄',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:schedule_updated', {
            type: 'schedule_updated',
            coachId,
            coachName,
            className,
            changes,
            message: `${coachName} - ${className} schedule updated`,
            icon: '🔄',
            timestamp: new Date()
        });
    });

    socket.on('schedule:cancelled', (data) => {
        const { coachId, coachName, className, scheduleDate, reason } = data;
        
        console.log(`⏸️ Schedule Cancelled: ${className}`);

        io.to(`user:${coachId}`).emit('notification:schedule_cancelled', {
            type: 'schedule_cancelled',
            className,
            scheduleDate,
            reason,
            message: `Class cancelled: ${className}`,
            icon: '⏸️',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:schedule_cancelled', {
            type: 'schedule_cancelled',
            coachId,
            coachName,
            className,
            reason,
            message: `${coachName} - ${className} cancelled`,
            icon: '⏸️',
            timestamp: new Date()
        });
    });

    // ===== MANAGER NOTIFICATIONS =====
    socket.on('manager:alert', (data) => {
        const { severity, title, message, userId } = data;
        
        console.log(`🔔 Manager Alert (${severity}): ${title}`);

        io.to('role:Manager').emit('notification:manager_alert', {
            type: 'manager_alert',
            severity, // 'critical', 'warning', 'info'
            title,
            message,
            userId,
            timestamp: new Date(),
            icon: severity === 'critical' ? '⚠️' : '🔔'
        });
    });

    // ===== ACTIVITY NOTIFICATIONS =====
    socket.on('activity:member_checkin', (data) => {
        const { memberId, memberName, coachId, coachName, checkInTime } = data;
        
        console.log(`✨ Member Check-in: ${memberName}`);

        io.to(`user:${coachId}`).emit('notification:member_checkin', {
            type: 'member_checkin',
            memberId,
            memberName,
            checkInTime,
            message: `${memberName} checked in`,
            icon: '✨',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:member_checkin', {
            type: 'member_checkin',
            memberId,
            memberName,
            coachId,
            coachName,
            checkInTime,
            message: `${memberName} checked in with ${coachName}`,
            icon: '✨',
            timestamp: new Date()
        });
    });

    socket.on('activity:coach_status', (data) => {
        const { coachId, coachName, status, previousStatus } = data;
        
        console.log(`🔄 Coach Status: ${coachName} → ${status}`);

        io.to('role:Manager').emit('notification:coach_status_changed', {
            type: 'coach_status_changed',
            coachId,
            coachName,
            status,
            previousStatus,
            message: `${coachName} is now ${status}`,
            icon: status === 'Active' ? '🟢' : '🔴',
            timestamp: new Date()
        });
    });

    // ===== ASSESSMENT NOTIFICATIONS =====
    socket.on('assessment:created', (data) => {
        const { coachId, coachName, memberId, memberName, assessmentDate } = data;
        
        console.log(`📊 Assessment Created: ${memberName} by ${coachName}`);

        io.to(`user:${coachId}`).emit('notification:assessment_created', {
            type: 'assessment_created',
            memberId,
            memberName,
            assessmentDate,
            message: `Assessment scheduled for ${memberName}`,
            icon: '📊',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:assessment_created', {
            type: 'assessment_created',
            coachId,
            coachName,
            memberId,
            memberName,
            assessmentDate,
            message: `${coachName} created assessment for ${memberName}`,
            icon: '📊',
            timestamp: new Date()
        });
    });

    socket.on('assessment:completed', (data) => {
        const { coachId, coachName, memberId, memberName, results } = data;
        
        console.log(`✅ Assessment Completed: ${memberName}`);

        io.to(`user:${coachId}`).emit('notification:assessment_completed', {
            type: 'assessment_completed',
            memberId,
            memberName,
            results,
            message: `Assessment completed for ${memberName}`,
            icon: '✅',
            timestamp: new Date()
        });

        io.to('role:Manager').emit('notification:assessment_completed', {
            type: 'assessment_completed',
            coachId,
            coachName,
            memberId,
            memberName,
            message: `Assessment completed: ${memberName}`,
            icon: '✅',
            timestamp: new Date()
        });
    });

    // ===== REAL-TIME STATUS UPDATES =====
    socket.on('status:request_list', async (data) => {
        try {
            const connection = await pool.getConnection();
            const [coaches] = await connection.query(
                'SELECT coach_id, first_name, last_name, status FROM coach WHERE status != "Inactive"'
            );
            connection.release();

            socket.emit('status:coaches_list', coaches);
        } catch (error) {
            console.error('❌ Error fetching coaches:', error);
        }
    });

    socket.on('status:request_assignments', async (data) => {
        const { coachId } = data;
        try {
            const connection = await pool.getConnection();
            const [assignments] = await connection.query(
                'SELECT c.client_id, c.first_name, c.last_name FROM clients c WHERE c.assigned_coach_id = ?',
                [coachId]
            );
            connection.release();

            socket.emit('status:coach_assignments', { coachId, assignments });
        } catch (error) {
            console.error('❌ Error fetching assignments:', error);
        }
    });

    // ===== DISCONNECT HANDLING =====
    socket.on('disconnect', () => {
        const user = activeUsers.get(socket.id);
        if (user) {
            console.log(`👋 User disconnected: ${user.userName} (${socket.id})`);
            
            // Notify managers that user went offline
            io.to('role:Manager').emit('notification:user_offline', {
                userId: user.userId,
                userName: user.userName,
                userRole: user.userRole,
                timestamp: new Date()
            });
            
            activeUsers.delete(socket.id);
        }
    });

    socket.on('error', (error) => {
        console.error(`❌ Socket error for ${socket.id}:`, error);
    });
});

// ===== REST API ENDPOINTS =====

// Get active users count
app.get('/api/active-users', (req, res) => {
    res.json({ count: activeUsers.size, users: Array.from(activeUsers.values()) });
});

// Send notification to specific user
app.post('/api/notify/user/:userId', (req, res) => {
    const { userId } = req.params;
    const { title, message, type } = req.body;

    io.to(`user:${userId}`).emit('notification:custom', {
        type: type || 'info',
        title,
        message,
        timestamp: new Date()
    });

    res.json({ success: true, message: 'Notification sent' });
});

// Broadcast to all managers
app.post('/api/notify/managers', (req, res) => {
    const { title, message, severity } = req.body;

    io.to('role:Manager').emit('notification:broadcast', {
        type: 'broadcast',
        title,
        message,
        severity: severity || 'info',
        timestamp: new Date()
    });

    res.json({ success: true, message: 'Notification broadcast to managers' });
});

// ===== DATABASE LOGGING =====
async function logNotification(userId, type, message, userRole) {
    try {
        const connection = await pool.getConnection();
        await connection.query(
            'INSERT INTO notifications (user_id, type, message, user_role, created_at) VALUES (?, ?, ?, ?, NOW())',
            [userId, type, message, userRole]
        );
        connection.release();
    } catch (error) {
        console.error('❌ Error logging notification:', error);
    }
}

// ===== ERROR HANDLING =====
app.use((err, req, res, next) => {
    console.error('❌ Error:', err);
    res.status(500).json({ error: 'Internal server error' });
});

// ===== START SERVER =====
const PORT = process.env.PORT || 3001;

server.on('error', (err) => {
    console.error('❌ Server error:', err);
    if (err.code === 'EADDRINUSE') {
        console.log('⚠️  Port ' + PORT + ' already in use');
    }
});

const listener = server.listen(PORT, () => {
    console.log(`\n🎉 Empire Fitness Real-time Server is ready!`);
    console.log(`📡 WebSocket Server: ws://localhost:${PORT}`);
    console.log(`🔌 Express Server: http://localhost:${PORT}`);
    console.log(`\n✅ Ready to handle real-time notifications`);
});

// Keep server alive
listener.keepAliveTimeout = 65000;

// Error handlers
process.on('uncaughtException', (err) => {
    console.error('❌ Uncaught Exception:', err);
    console.error(err.stack);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('❌ Unhandled Rejection at:', promise, 'reason:', reason);
});

// Graceful shutdown
process.on('SIGINT', () => {
    console.log('\n🛑 Shutting down server...');
    server.close(() => {
        console.log('Server stopped');
        process.exit(0);
    });
});
