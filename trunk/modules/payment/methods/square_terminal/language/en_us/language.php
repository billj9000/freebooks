<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/payment/methods/square_terminal/language/en_us/language.php
//

define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_TITLE',       'Square Terminal');
define('MODULE_PAYMENT_SQUARE_TERMINAL_TEXT_DESCRIPTION', 'Accept card payments via a Square card terminal device.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ACCESS_TOKEN_DESC', 'Square API access token (from the Square Developer Dashboard).');
define('MODULE_PAYMENT_SQUARE_TERMINAL_LOCATION_ID_DESC',  'Square Location ID where the terminal device is registered.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_DEVICE_ID_DESC',    'Square Terminal Device ID.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_SANDBOX_DESC',      'Use Square sandbox environment for testing.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_BTN_SEND',          'Send to Terminal');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_IDLE',       'Ready — click Send to Terminal to begin.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_SENDING',    'Sending amount to terminal...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_WAITING',    'Waiting for payment on terminal...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_COMPLETED',  'Payment confirmed!');
define('MODULE_PAYMENT_SQUARE_TERMINAL_STATUS_CANCELED',   'Payment canceled or failed — click Send to Terminal to retry.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_AUTO_SEND_DESC',      'When arriving from the invoice Payment button: Off = do nothing (user clicks Send manually); On = send immediately; Countdown = count down 5 seconds then send, with a Cancel option.');

define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_BTN',            'Pair New Terminal');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_BTN',          'Browse Devices');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_LOADING',      'Loading devices from Square...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_BROWSE_NONE',         'No Terminal API devices found on this Square account.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_CREATING',       'Creating pairing code...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_INSTRUCTIONS',   'On the terminal: <strong>Settings &rarr; Device Management &rarr; Enter pairing code</strong><br>Waiting for terminal to pair...');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_SUCCESS',        'Terminal paired — Device ID filled in above. Click Save to confirm.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_TIMEOUT',        'Pairing timed out (code expired). Please try again.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_PAIR_ERROR_CONFIG',   'Access token and location ID must be configured and saved before pairing.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_COUNTDOWN_SENDING',   'Sending in %ss &mdash; <a href="#" onclick="sqTermCancelCountdown();return false;">Cancel</a>');

define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NOT_CONFIRMED', 'Square Terminal: payment must be confirmed on the terminal before saving.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_VERIFY',        'Square Terminal: could not verify the completed payment with Square. Check the access token and try again.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_TOKEN',      'Square Terminal: access token is not configured.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_DEVICE',     'Square Terminal: device ID is not configured.');
define('MODULE_PAYMENT_SQUARE_TERMINAL_ERROR_NO_LOCATION',   'Square Terminal: location ID is not configured.');

// Fallback for SORT_ORDER_DESC which is only loaded on the payment admin page
if (!defined('SORT_ORDER_DESC')) define('SORT_ORDER_DESC', 'Sort order of display. Lowest is displayed first.');
?>
