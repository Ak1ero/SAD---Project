# Email System for The Barn & Backyard

This directory contains the email sending functionality for The Barn & Backyard event management system.

## Features

- Sending personalized event invitations to guests
- QR code inclusion for quick event check-in
- SMTP email support with fallback to PHP mail()
- Error handling and logging
- Configuration via environment variables

## Configuration

Email settings can be configured in two ways:

1. By setting environment variables on your server
2. By modifying the default values in `config.php`

For security, never commit sensitive information like SMTP passwords to your code repository.

### Required SMTP Settings for Gmail

If you're using Gmail, you'll need to:

1. Enable 2-Step Verification on your Google account
2. Generate an App Password (in Google Account settings)
3. Use this App Password instead of your regular password

### Setting Up

To test emails locally:

1. Set `SMTP_ENABLED` to `true` in `config.php`
2. Update `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, and `SMTP_PASSWORD` with your email provider details
3. Make sure your web server allows outgoing connections to your email provider

## Troubleshooting

If you're having issues sending emails:

1. Check the logs in the `logs` directory
2. Ensure your SMTP credentials are correct
3. Verify your server allows outgoing SMTP connections
4. Try using the PHP mail() function as a fallback by setting `SMTP_ENABLED` to `false`

## Technical Details

The system uses PHPMailer when available, with a fallback to PHP's native mail() function. All emails are HTML formatted for better presentation.

## Directory Structure

- `Mailer.php` - Main class for sending emails via SMTP
- `config.php` - SMTP and email configuration settings
- `send-invitations.php` - Script to handle invitation sending requests
- `templates/` - Email templates directory
  - `invitation.html` - HTML template for invitation emails

## Setup Instructions

1. **Install PHP Mailer Library**

   This system requires PHPMailer. If it's not already installed, you can install it via Composer:

   ```
   composer require phpmailer/phpmailer
   ```

2. **Configure SMTP Settings**

   Edit the `config.php` file to set your SMTP server details:
   
   ```php
   return [
       'smtp_host' => 'your-smtp-server.com',
       'smtp_port' => 587,
       'smtp_username' => 'your-email@example.com',
       'smtp_password' => 'your-password',
       'smtp_encryption' => 'tls',
       'from_email' => 'no-reply@barnbackyard.com',
       'from_name' => 'The Barn & Backyard'
   ];
   ```

3. **Set Up Database**

   Run the `setup.php` script to add necessary columns to the `booking_guests` table:
   
   ```
   http://your-website.com/email/setup.php
   ```

4. **QR Code Generation**

   By default, the system uses the PHP QR Code library. If it's not available, you'll need to:
   
   - Install the library: `composer require chillerlan/php-qrcode`
   - Or modify the `generateQRCode` method in `Mailer.php` to use an alternative library or API

## Usage

The email invitation system is integrated with the booking details modal in the user's booking page. When a user clicks the "Send Invitation to Guests" button, the system:

1. Gets the guest list for the booking
2. Generates a unique QR code for each guest
3. Creates personalized email invitations
4. Sends emails to all guests
5. Displays results to the user

The invitation emails include:
- Event date and time
- Event package and theme details
- Location information
- A personalized QR code for check-in

## Customization

To customize the invitation email template, edit `templates/invitation.html`. The template uses the following placeholders:

- `{GUEST_NAME}` - Guest's name
- `{EVENT_DATE}` - Date of the event
- `{EVENT_TIME}` - Time of the event
- `{EVENT_LOCATION}` - Location of the event
- `{EVENT_PACKAGE}` - Event package name
- `{EVENT_THEME}` - Event theme name
- `{BOOKING_REFERENCE}` - Booking reference number

## Support

For any issues or questions, please contact the development team.

## Attachment Size Limits

Be aware that some email providers have attachment size limits:
- Gmail: 25MB
- Outlook/Hotmail: 20MB
- Yahoo: 25MB

QR codes are small, but if you add other attachments, keep these limits in mind.

## Database Structure

The system uses the existing `guests` table for storing guest information and tracking email status. 