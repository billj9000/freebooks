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
// Customer-facing landing page after completing a PayPal payment.
// No session auth — this is shown to the paying customer.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Received</title>
<style>
  body { font-family: sans-serif; display: flex; justify-content: center; align-items: center;
         min-height: 100vh; margin: 0; background: #f4f6f9; }
  .box { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.12);
         padding: 2.5rem 3rem; max-width: 480px; text-align: center; }
  h1 { color: #27ae60; margin-top: 0; }
  p  { color: #555; line-height: 1.6; }
</style>
</head>
<body>
  <div class="box">
    <h1>&#10003; Payment Received</h1>
    <p>Thank you! Your payment has been processed by PayPal.</p>
    <p>You will receive a confirmation email from PayPal shortly.<br>
       Please contact us if you have any questions.</p>
  </div>
</body>
</html>
