# Event Management System - Service Items Module

This module adds functionality to manage items associated with services, such as bands, photographers, and more.

## Setup

1. Execute the SQL script to create the necessary database table:

```sql
mysql -u [username] -p [database_name] < database/service_items.sql
```

2. Make sure the following directory exists and is writable:

```
uploads/service_items/
```

3. Upload the new files to your server:
   - `admin/service_items.php`
   - Update to `admin/theme.php`
   - `database/migrate_service_items.php` (optional, for migration)

## Data Migration

If you are upgrading from a previous version with separate tables for bands and photographers, a migration script is provided:

1. Run the migration script to transfer data to the new unified table:

```
php database/migrate_service_items.php
```

This script will:
- Check if old tables (bands, photographers) exist
- Transfer all data to the new unified service_items table
- Preserve all item information including references to services
- Report on the migration progress

## Usage

1. From the Admin Dashboard, go to Theme Management
2. In the services section, you'll see a "+" button next to each service
3. Click the "+" button to add an item to that service:
   - For services with "band" or "music" in the name, it will be categorized as a band
   - For services with "photo" or "camera" in the name, it will be categorized as a photographer
   - For other services, it will be categorized as a generic service item

## Data Model

All service items are stored in a single table (`service_items`) with the following fields:

- Name
- Phone Number
- Email
- Price Range
- Image
- Service ID (reference to the associated service)
- Service Type (band, photographer, or generic)

This unified approach simplifies the database structure while still allowing for categorization of items by their service type.

## Customizing Item Types

To add more specialized item types:

1. Update the service type detection in the JavaScript code to recognize different service categories
2. Use the existing service_items table with the new service type value 

# Auto-Cancellation Feature

This feature automatically cancels reservations that remain unpaid after a specified period, preventing users from indefinitely holding reservations without payment.

## How It Works

1. When a booking is confirmed, the system updates the `updated_at` timestamp in the database.
2. The auto-cancel script checks for confirmed bookings that are unpaid and were confirmed more than 1 hour ago.
3. These bookings are automatically changed to "cancelled" status, freeing the date for other customers.
4. An optional notification system warns users before their bookings are cancelled.

## Files Added

- `auto_cancel_unpaid_bookings.php` - Main script that performs the cancellations
- `notify_unpaid_bookings.php` - Sends warning emails to users approaching the cancellation deadline
- `admin/run_auto_cancel.php` - Admin interface for manually running the auto-cancel script
- `setup_cron.php` - Instructions for setting up automated execution

## Setup Instructions

1. Upload all the new files to your server
2. Set up a cron job to run the auto-cancel script every 15 minutes:

```
*/15 * * * * php /path/to/your/site/auto_cancel_unpaid_bookings.php > /dev/null 2>&1
```

3. (Optional) Set up a cron job to send notification emails every 5 minutes:

```
*/5 * * * * php /path/to/your/site/notify_unpaid_bookings.php > /dev/null 2>&1
```

4. Test the functionality by confirming a booking and leaving it unpaid for the specified time period.

## Customizing the Time Limit

The default time limit is 1 hour. To change this:

1. Edit `auto_cancel_unpaid_bookings.php` and modify the cutoff time calculation
2. Also update the notification timing in `notify_unpaid_bookings.php` to maintain the appropriate warning period 