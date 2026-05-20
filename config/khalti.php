<?php
/**
 * Purpose: Store Khalti API keys, endpoints, and helper config.
 * Website Section: Payment Gateway Configuration.
 * Developer Notes: Keep Khalti secrets and URLs out of public/index.php.
 */

if (!defined('KHALTI_SECRET_KEY')) {
	define('KHALTI_SECRET_KEY', (string) env('KHALTI_SECRET_KEY', '0fae723f07034a31aa04a5ad836777c4'));
}

if (!defined('KHALTI_INITIATE_URL')) {
	define('KHALTI_INITIATE_URL', (string) env('KHALTI_INITIATE_URL', 'https://dev.khalti.com/api/v2/epayment/initiate/'));
}

if (!defined('KHALTI_LOOKUP_URL')) {
	define('KHALTI_LOOKUP_URL', (string) env('KHALTI_LOOKUP_URL', 'https://dev.khalti.com/api/v2/epayment/lookup/'));
}

if (!defined('KHALTI_WEBSITE_URL')) {
	define('KHALTI_WEBSITE_URL', rtrim((string) env('APP_URL', 'http://localhost/rentals-app/public'), '/'));
}
