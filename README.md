# 🎉 The Barn & Backyard Event Management System

A comprehensive system for managing event bookings, services, and guest attendance.

![The Barn & Backyard](img/barn-backyard.svg)

## 📋 Table of Contents

- [System Overview](#-system-overview)
- [Key Features](#-key-features)
- [Installation](#-installation)
- [Admin Features](#-admin-features)
- [User Features](#-user-features)
- [Automatic Booking Cancellation](#-automatic-booking-cancellation)
- [Troubleshooting](#-troubleshooting)

## 🌐 System Overview

The Barn & Backyard Event Management System helps venue owners manage event bookings, services, and guest attendance. The system handles everything from booking new events to tracking attendance on the day of the event.

## ✨ Key Features

- **Event Booking Management**: Schedule and manage all event bookings
- **Service Management**: Offer various service packages (e.g., weddings, corporate events)
- **Guest Management**: Track guest lists and attendance
- **Automatic Cancellation**: Auto-cancel unpaid bookings after 1 hour
- **Mobile-Friendly Design**: Works on all devices
- **Dark Mode Support**: Comfortable viewing in any lighting

## 💻 Installation

1. **Requirements**:
   - PHP 7.4+
   - MySQL 5.7+
   - Web server (Apache/Nginx)
   - XAMPP (recommended for local setup)

2. **Setup Steps**:
   ```
   # Clone the repository
   git clone https://github.com/Ak1ero/SAD-Project.git
   
   # Import the database
   mysql -u username -p event < database.sql
   
   # Configure database connection
   # Edit db/config.php with your database credentials
   ```

3. **Time Zone Configuration**:
   - The system uses Philippines time zone (Asia/Manila)
   - This can be modified in db/config.php if needed

## 👨‍💼 Admin Features

### Dashboard
Access the admin dashboard at `/admin/admindash.php` to view:
- Event statistics
- Recent bookings
- System status

### Event Management
- View all upcoming events
- Check event details
- Manage event status

### Guest Management
- View guest lists for each event
- Take attendance on event day
- Generate attendance reports

### Service Management
- Add/edit service packages
- Manage service items (bands, photographers, etc.)
- Set pricing and availability

## 👨‍👩‍👧‍👦 User Features

### Booking Process
1. Users browse available services
2. Select an event date and package
3. Add guest information
4. Complete payment
5. Receive booking confirmation

### Guest Information
Guests receive:
- Unique codes for check-in
- Event details and location
- Automated notifications

## ⏱️ Automatic Booking Cancellation

The system automatically cancels unpaid bookings after 1 hour to prevent reservation blocking.

### How It Works
1. When a booking is confirmed but not paid, a 1-hour timer starts
2. System checks every 5 minutes for unpaid bookings older than 1 hour
3. Any found bookings are automatically cancelled
4. Customers are notified about the cancellation

### Monitoring Auto-Cancel Status
Visit `/check_auto_cancel_status.php` to:
- See when the auto-cancel system last ran
- View upcoming cancellations
- Check for any issues with the system

### Troubleshooting Auto-Cancel
If automatic cancellation isn't working:
1. Verify XAMPP services are running
2. Check Task Scheduler is running the EventBookingAutoCancel task
3. View error logs in auto_cancel_log.txt

## 🔧 Troubleshooting

### Common Issues

**Database Connection Errors**
- Verify database credentials in db/config.php
- Ensure MySQL service is running

**Booking Not Showing**
- Clear browser cache
- Check for payment confirmation

**Time Zone Issues**
- Ensure the server's time zone is set correctly
- Check date_default_timezone_set in config files

### Getting Help
- Visit admin/check_auto_cancel_status.php for system diagnostics
- Contact system administrator for database issues
- Submit issues to the GitHub repository for software bugs

---

© 2024 The Barn & Backyard | All Rights Reserved 