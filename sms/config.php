<?php
/**
 * SMS API Configuration
 */

// Default SMS provider
define('DEFAULT_SMS_PROVIDER', 'philsms'); // Options: 'philsms', 'second_provider'

// PhilSMS Configuration
define('PHILSMS_API_TOKEN', '1678|HDsKAtLPRp4y0gsbviCjeNefB8pwz5DMQdy4SgRp');
define('PHILSMS_API_ENDPOINT', 'https://app.philsms.com/api/v3/sms/send');
define('PHILSMS_SENDER_ID', 'PhilSMS');

// Second SMS Provider Configuration (replace with your actual second provider details)
define('SECOND_PROVIDER_API_TOKEN', '1678|HDsKAtLPRp4y0gsbviCjeNefB8pwz5DMQdy4SgRp');
define('SECOND_PROVIDER_API_ENDPOINT', 'https://app.philsms.com/api/v3/sms/send');
define('SECOND_PROVIDER_SENDER_ID', 'SecondProvider');
?> 