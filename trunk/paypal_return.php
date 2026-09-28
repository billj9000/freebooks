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
//  Path: /paypal_return.php
//
// Where PayPal sends a customer after they approve a payment link. No session
// auth: this is shown to the paying customer.
//
// Approval moves no money, so this page captures the payment (PayPal names the
// order as ?token=). The CHECKOUT.ORDER.APPROVED webhook captures it too, for a
// customer who never comes back here; whichever is second finds it done. The
// page reports the capture's actual result: a customer told their payment was
// received when it was not has no reason to try again.

$outcome = 'processing';
if (@include __DIR__ . '/modules/payment/methods/paypal/standalone.php') {
    $capture = $pp->capture_mapped_order((string)($_GET['token'] ?? ''));
    if ($capture === false) {
        $outcome = 'failed';
        error_log('paypal_return: could not capture order ' . ($_GET['token'] ?? '') . ': ' . $pp->get_last_error());
    } elseif (is_array($capture)) {
        $outcome = ($capture['status'] ?? '') === 'COMPLETED' ? 'received' : 'pending';
    }
} else {
    error_log('paypal_return: ' . ($pp_standalone_error ?? 'FreeBooks did not start'));
}

$pages = [
    'received'   => ['&#10003; Payment Received', '#27ae60',
                     'Thank you! Your payment has been received by PayPal.',
                     'You will receive a confirmation email from PayPal shortly.'],
    'pending'    => ['Payment Under Review', '#e67e22',
                     'Thank you. PayPal has accepted your payment and is reviewing it before it completes.',
                     'PayPal will email you when it does.'],
    'processing' => ['Thank You', '#2c3e50',
                     'Your payment is being processed by PayPal.',
                     'You will receive a confirmation email from PayPal when it is complete.'],
    'failed'     => ['Payment Not Completed', '#c0392b',
                     'We could not confirm your payment with PayPal.',
                     'If PayPal does not email you a confirmation, no payment has been taken.'],
];
list($heading, $colour, $line1, $line2) = $pages[$outcome];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo strip_tags(html_entity_decode($heading)); ?></title>
<style>
  body { font-family: sans-serif; display: flex; justify-content: center; align-items: center;
         min-height: 100vh; margin: 0; background: #f4f6f9; }
  .box { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.12);
         padding: 2.5rem 3rem; max-width: 480px; text-align: center; }
  h1 { margin-top: 0; }
  p  { color: #555; line-height: 1.6; }
</style>
</head>
<body>
  <div class="box">
    <h1 style="color: <?php echo $colour; ?>"><?php echo $heading; ?></h1>
    <p><?php echo $line1; ?></p>
    <p><?php echo $line2; ?><br>
       Please contact us if you have any questions.</p>
  </div>
</body>
</html>
