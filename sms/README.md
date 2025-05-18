# SMS Notification System

This system supports multiple SMS providers to send SMS notifications to customers when their bookings are confirmed.

## Configuration

### 1. PhilSMS Account Setup

1. Create an account at [PhilSMS](https://app.philsms.com/)
2. After signing in, navigate to the API section to obtain your API token
3. Register a sender ID (this is the name that will appear as the sender of SMS messages)

### 2. Second Provider Setup (Optional)

1. Create an account with your second SMS provider
2. Obtain your API token and other required credentials
3. Register a sender ID if required by the provider

### 3. Update Configuration

The SMS configuration is stored in `sms/config.php`. You need to update:

```php
// Default SMS provider
define('DEFAULT_SMS_PROVIDER', 'philsms'); // Options: 'philsms', 'second_provider'

// PhilSMS Configuration
define('PHILSMS_API_TOKEN', 'YOUR_API_TOKEN');
define('PHILSMS_SENDER_ID', 'YOUR_SENDER_ID');

// Second SMS Provider Configuration
define('SECOND_PROVIDER_API_TOKEN', 'YOUR_SECOND_PROVIDER_TOKEN');
define('SECOND_PROVIDER_API_ENDPOINT', 'YOUR_SECOND_PROVIDER_ENDPOINT');
define('SECOND_PROVIDER_SENDER_ID', 'YOUR_SECOND_PROVIDER_SENDER_ID');
```

## Usage

The SMS notification system is automatically triggered when:

1. An admin confirms a booking from the admin/reservations.php page
2. The customer's phone number is available in their profile

### Specifying a Provider

When sending an SMS, you can specify which provider to use:

```php
// Send via the default provider
sendSMS($phoneNumber, $message);

// Send via a specific provider
sendSMS($phoneNumber, $message, 'philsms');
sendSMS($phoneNumber, $message, 'second_provider');
```

When using the send_sms.php endpoint, you can add a 'provider' parameter to your POST request:

```
POST /sms/send_sms.php
{
  "number": "09123456789",
  "message": "Your message here",
  "provider": "second_provider"  // Optional, omit to use default provider
}
```

## Troubleshooting

If SMS messages are not being sent, check:

1. Ensure your SMS provider account has sufficient credits
2. Verify the API token in config.php is correct and not expired
3. Confirm that the sender ID is registered and approved in your provider account
4. Check the PHP error logs for any error messages related to the API calls

## Log Files

SMS operations are logged to your server's PHP error log. You can check these logs to diagnose issues with SMS delivery.

## Additional Information

- The system formats phone numbers to match the API requirements
- Phone numbers are automatically formatted to include the Philippines country code (63)
- For international numbers, ensure they are in the correct format with the country code 