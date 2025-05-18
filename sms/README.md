# SMS Notification System

This system uses PhilSMS API to send SMS notifications to customers when their bookings are confirmed.

## Configuration

### 1. PhilSMS Account Setup

1. Create an account at [PhilSMS](https://app.philsms.com/)
2. After signing in, navigate to the API section to obtain your API token
3. Register a sender ID (this is the name that will appear as the sender of SMS messages)

### 2. Update Configuration

The SMS configuration is stored in `sms/config.php`. You need to update:

```php
// API Token - Replace with your actual API token from PhilSMS
define('PHILSMS_API_TOKEN', 'YOUR_API_TOKEN');

// Sender ID - Make sure this is registered in your PhilSMS account
define('PHILSMS_SENDER_ID', 'YOUR_SENDER_ID');
```

## Usage

The SMS notification system is automatically triggered when:

1. An admin confirms a booking from the admin/reservations.php page
2. The customer's phone number is available in their profile

## Troubleshooting

If SMS messages are not being sent, check:

1. Ensure your PhilSMS account has sufficient credits
2. Verify the API token in config.php is correct and not expired
3. Confirm that the sender ID is registered and approved in your PhilSMS account
4. Check the PHP error logs for any error messages related to the API calls

## Log Files

SMS operations are logged to your server's PHP error log. You can check these logs to diagnose issues with SMS delivery.

## Additional Information

- The system formats phone numbers to match the PhilSMS API requirements
- Phone numbers are automatically formatted to include the Philippines country code (63)
- For international numbers, ensure they are in the correct format with the country code 