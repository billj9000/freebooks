<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
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
//  Path: /modules/payment/classes/payment.php
//
// Revision history
// 2012-05-11 - Created
gen_pull_language('payment');
class payment {
  public $payment_fields;
  public $refund_error = '';
  public $title;
  public $description;
  public $open_pos_drawer = false;
  public $show_in_pos	  = true;
  /**
   * Whether this method handles an entry settled in a currency other than the
   * company's own (issue #63).
   *
   * False by default, and deliberately the conservative way round, exactly as
   * refund() is: a method that transmits an amount to a gateway and names
   * DEFAULT_CURRENCY beside it will charge the right NUMBER in the wrong
   * currency, which is a real payment for the wrong money and not an error
   * anybody sees. Six of the methods shipped here do that - authorizenet,
   * firstdata, linkpoint_api, nova_xml, paypal and paypal_nvp - and until each
   * is given the entry's code and rate, the page refuses rather than charges.
   *
   * Set true where it is safe: a method that transmits NO amount (cod,
   * moneyorder, directdebit, freecharger) records the entry and nothing else,
   * so the currency is bookkeeping; and one that is genuinely told which
   * currency to take and whose service accepts any of them (paypal). Not
   * square_terminal: Square takes only its location's currency.
   */
  public $multi_currency   = false;
  // The kinds of request for payment this method can make, as type => label;
  // see create_request(). Empty for a method that cannot ask for payment.
  public $request_types    = array();
  public $pos_gl_acct;
  public $sort_order;
  public $key             = array();
  public $field_0;
  public $field_1;
  public $field_2;
  public $field_3;
  public $field_4;
  public $field_5;
  public $field_6;

  public function __construct(){
  	// Every payment method instantiates this base constructor (e.g. during
  	// install, which creates several methods in the same request), so this
  	// must not re-define on the second and later instantiations.
  	if (!defined('FILENAME_POPUP_CVV_HELP')) define('FILENAME_POPUP_CVV_HELP', 'popup_cvv_help'); // TBD
	$this->open_pos_drawer  = defined('MODULE_PAYMENT_'.strtoupper($this->code).'_OPEN_POS_DRAWER')  ? constant('MODULE_PAYMENT_'.strtoupper($this->code).'_OPEN_POS_DRAWER')  : $this->open_pos_drawer;
	$this->sort_order  		= defined('MODULE_PAYMENT_'.strtoupper($this->code).'_SORT_ORDER')  		? constant('MODULE_PAYMENT_'.strtoupper($this->code).'_SORT_ORDER')  	 : $this->sort_order;
	$this->pos_gl_acct 		= defined('MODULE_PAYMENT_'.strtoupper($this->code).'_POS_GL_ACCT') 		? constant('MODULE_PAYMENT_'.strtoupper($this->code).'_POS_GL_ACCT') 	 : $this->pos_gl_acct;
	$this->show_in_pos      = defined('MODULE_PAYMENT_'.strtoupper($this->code).'_SHOW_IN_POS')      ? constant('MODULE_PAYMENT_'.strtoupper($this->code).'_SHOW_IN_POS')      : $this->show_in_pos;
	$this->key[] = array('key' => 'MODULE_PAYMENT_'.strtoupper($this->code).'_OPEN_POS_DRAWER', 'default' => $this->open_pos_drawer, 'text' => OPEN_POS_DRAWER_DESC );
	$this->key[] = array('key' => 'MODULE_PAYMENT_'.strtoupper($this->code).'_SORT_ORDER', 	   'default' => $this->sort_order,      'text' => SORT_ORDER_DESC);
	$this->key[] = array('key' => 'MODULE_PAYMENT_'.strtoupper($this->code).'_POS_GL_ACCT', 	   'default' => $this->pos_gl_acct,     'text' => POS_GL_ACCT_DESC);
	$this->key[] = array('key' => 'MODULE_PAYMENT_'.strtoupper($this->code).'_SHOW_IN_POS', 	   'default' => $this->show_in_pos,     'text' => SHOW_IN_POS_DESC);
	$this->field_0 = isset($_POST[$this->code.'_field_0']) ? $_POST[$this->code.'_field_0'] : '';//$this->cc_card_owner_last
	$this->field_1 = isset($_POST[$this->code.'_field_1']) ? $_POST[$this->code.'_field_1'] : '';//$this->cc_card_number
	$this->field_2 = isset($_POST[$this->code.'_field_2']) ? $_POST[$this->code.'_field_2'] : '';//$this->cc_expiry_month
	$this->field_3 = isset($_POST[$this->code.'_field_3']) ? $_POST[$this->code.'_field_3'] : '';//$this->cc_expiry_year
	$this->field_4 = isset($_POST[$this->code.'_field_4']) ? $_POST[$this->code.'_field_4'] : '';//$this->cc_cvv2
	$this->field_5 = isset($_POST[$this->code.'_field_5']) ? $_POST[$this->code.'_field_5'] : '';//$this->cc_card_owner_first
	$this->field_6 = isset($_POST[$this->code.'_field_6']) ? $_POST[$this->code.'_field_6'] : '';//$this->alternate 2
	$card_number = trim($this->field_1);
	$card_number = substr($card_number, 0, 4) . '********' . substr($card_number, -4);
	$this->payment_fields = implode(':', array($this->field_0, $card_number, $this->field_2, $this->field_3, $this->field_4, $this->field_5, $this->field_6));
  }

    function update() {
    foreach ($this->keys() as $key) {
          $field = strtolower($key['key']);
          if (isset($_POST[$field])) write_configure($key['key'], $_POST[$field]);
        }
  }
 
  function configure($key) {
    $val = defined($key) ? constant($key) : '';
    switch ($key) {
        case 'MODULE_PAYMENT_'.strtoupper($this->code).'_OPEN_POS_DRAWER':
                $temp = array(
                                array('id' => '0', 'text' => TEXT_NO),
                                array('id' => '1', 'text' => TEXT_YES),
                );
                return html_pull_down_menu(strtolower($key), $temp, $val);
            case 'MODULE_PAYMENT_'.strtoupper($this->code).'_SHOW_IN_POS':
                $temp = array(
                                array('id' => '0', 'text' => TEXT_NO),
                                array('id' => '1', 'text' => TEXT_YES),
                );
                return html_pull_down_menu(strtolower($key), $temp, $val);
        case 'MODULE_PAYMENT_'.strtoupper($this->code).'_POS_GL_ACCT':
                return html_pull_down_menu(strtolower($key), gen_coa_pull_down(), $val);
        default:
                return html_input_field(strtolower($key), $val);
    }
  }
 
  function selection() {
    return array(
      'id'   => $this->code,
      'page' => $this->title,
    );
  }
 
  function keys() {
        return $this->key;
  }
 
  function javascript_validation() {
    return false;
  }

  function pre_confirmation_check() {
    return false;
  }

  function before_process() {
    return false;
  }

  // Override in payment modules that support refunds.
  // $payment_id: gateway transaction ID from the original receipt.
  // $amount_minor: refund amount in the currency's smallest unit (e.g. cents).
  // Returns false if the refund failed (rolls back the J20); true or a string on success.
  function refund($payment_id, $amount_minor, $currency) {
    return false; // not supported by default
  }

  // Override to create a payment checkout link for the given invoice.
  // $fb_invoice_id: journal_main.id of the invoice being paid.
  // Returns the checkout URL string on success, false if not supported.
  function create_payment_link($fb_invoice_id, $amount, $currency, $description, $return_url, $cancel_url) {
    return false;
  }

  // Override to create an invoice in the payment system.
  // $fb_invoice_id: journal_main.id of the FreeBooks invoice being paid.
  // $send: whether to send the invoice to the customer immediately.
  // Returns invoice ID string on success, false if not supported.
  function create_paypal_invoice($fb_invoice_id, $customer, $line_items, $send = false) {
    return false;
  }

  // Override to return a URL to view a transaction on the gateway's website.
  // Returns URL string, or null if not supported.
  function get_transaction_url($transaction_id) {
    return null;
  }

  // ─── Requests for payment ─────────────────────────────────────────────────
  // A method that can ask a customer to pay an invoice - by a payment link, or
  // an invoice its own service sends - lists the kinds of request it makes as
  // type => label, and implements create_request() and, for a request with a
  // link, email_request(). The Request payment dialog offers every installed
  // method whose list is not empty.

  // Ask for $amount in $currency against the FreeBooks invoice $invoice_id,
  // record the request with record_request(), and return
  //   ['external_id' => the method's reference, 'url' => a link or null,
  //    'sent' => true when the method's service has emailed the customer]
  // or false, with the reason in get_last_error().
  function create_request($type, $invoice_id, $amount, $currency, $email) {
    return false;
  }

  // Email the link of the recorded request $request (a payment_requests row)
  // to $email. Returns true when the email was sent.
  function email_request($request, $email) {
    return false;
  }

  // Withdraw the open request $request (a payment_requests row), so that the
  // customer can no longer pay it. Returns true when the method's service has
  // withdrawn it. A method that cannot leaves it open, and a payment made
  // against it anyway is capped at what the invoice still owes.
  function cancel_request($request) {
    return false;
  }

  // What the invoice $invoice_id still owes, in the home currency: what was
  // billed, less every payment against it. It is the ledger's own measure when
  // it decides whether an invoice is paid.
  static function invoice_balance($invoice_id) {
    global $db;
    $billed = $db->Execute("SELECT SUM(credit_amount) - SUM(debit_amount) AS amt FROM " . TABLE_JOURNAL_ITEM
      . " WHERE ref_id = " . (int)$invoice_id . " AND gl_type <> 'ttl'");
    $paid   = $db->Execute("SELECT SUM(credit_amount) - SUM(debit_amount) AS amt FROM " . TABLE_JOURNAL_ITEM
      . " WHERE so_po_item_ref_id = " . (int)$invoice_id . " AND gl_type IN ('pmt','chk')");
    return round((float)$billed->fields['amt'] - (float)$paid->fields['amt'], 8);
  }

  // A request's status as people see it. A request still open after its
  // expires_at has passed can no longer be paid, so it shows as expired.
  static function request_display_status($request) {
    if ($request['status'] === 'requested' && !empty($request['expires_at'])
        && strtotime($request['expires_at']) < time()) return 'expired';
    return $request['status'];
  }

  // Record a request for payment this method has just made: a payment link or
  // an invoice the method sent, asking for $amount in $currency against the
  // FreeBooks invoice $invoice_id. $external_id is the method's own reference
  // for it, by which later notifications find it again. $expires_at is a
  // 'Y-m-d H:i:s' time, or null for a request that does not expire.
  // $external_ref is a second reference, for a method that needs a different
  // handle to withdraw the request than the one its payments carry.
  function record_request($request_type, $external_id, $invoice_id, $amount, $currency, $url = null, $expires_at = null, $external_ref = null) {
    global $db;
    $this->ensure_requests_table();
    // db_perform() writes SQL NULL only for the string 'null'; a PHP null would
    // reach the column as '', which a datetime refuses under strict SQL.
    db_perform(TABLE_PAYMENT_REQUESTS, array(
      'method'        => $this->code,
      'request_type'  => $request_type,
      'external_id'   => $external_id,
      'external_ref'  => $external_ref ?? 'null',
      'ref_id'        => (int)$invoice_id,
      'amount'        => (float)$amount,
      'currency_code' => $currency,
      'url'           => $url ?? 'null',
      'status'        => 'requested',
      'admin_id'      => (int)($_SESSION['admin_id'] ?? 0),
      'created_at'    => date('Y-m-d H:i:s'),
      'expires_at'    => $expires_at ?? 'null',
    ));
  }

  // The request this method recorded under $external_id, as a row array, or
  // null. $request_type narrows it to one kind of request.
  function find_request($external_id, $request_type = null) {
    global $db;
    $this->ensure_requests_table();
    $sql = "SELECT * FROM " . TABLE_PAYMENT_REQUESTS . " WHERE method = '" . db_input($this->code) . "'"
         . " AND external_id = '" . db_input((string)$external_id) . "'";
    if ($request_type !== null) $sql .= " AND request_type = '" . db_input($request_type) . "'";
    $row = $db->Execute($sql . " LIMIT 1");
    return $row->EOF ? null : $row->fields;
  }

  // The table is created from the payment module's own declaration, the one
  // the installer and the migration use, so there is a single definition of it.
  // A table made before external_ref existed gets it here, once.
  function ensure_requests_table() {
    global $db;
    static $checked = false;
    if ($checked) return;
    $checked = true;
    if (db_table_exists(TABLE_PAYMENT_REQUESTS)) {
      if (!db_field_exists(TABLE_PAYMENT_REQUESTS, 'external_ref')) {
        $db->Execute("ALTER TABLE " . TABLE_PAYMENT_REQUESTS . " ADD external_ref varchar(64) NULL DEFAULT NULL AFTER external_id");
      }
      return;
    }
    require_once(DIR_FS_MODULES . 'payment/classes/install.php');
    $install = new payment_admin();
    $db->Execute($install->tables[TABLE_PAYMENT_REQUESTS]);
  }

  // The lines of the FreeBooks invoice $invoice_id, for a request that lists
  // them: ['lines' => [['kind' => sos|frt|tax, 'name', 'qty', 'unit'], ...],
  // 'discount' => the invoice's discount]. Amounts are in the invoice's own
  // currency, rounded to the cent. $rate is its exchange rate and not optional
  // detail: every journal amount is stored in the HOME currency, while the
  // amount asked for is in the invoice's, so without it the lines are pounds
  // under a dollar heading. Products come first, then shipping, then taxes.
  //
  // The unit price is what the line charged, divided by its quantity - not
  // full_price, which is the list price: a price sheet or a price changed on the
  // invoice leaves it behind, and lines priced from it did not add up to the
  // invoice. Where the charge does not divide into a whole-cent unit price (3
  // for 10.00), the line is one unit at its whole amount, its quantity named,
  // so that every line is exactly what the invoice charged.
  function invoice_lines($invoice_id, $rate) {
    global $db;
    $rate  = (float)$rate ?: 1.0;
    $lines = ['sos' => [], 'frt' => [], 'tax' => []];
    $discount = 0.0;
    $rows = $db->Execute("SELECT gl_type, sku, qty, description, debit_amount, credit_amount"
      . " FROM " . TABLE_JOURNAL_ITEM . " WHERE ref_id = " . (int)$invoice_id
      . " AND gl_type IN ('sos', 'frt', 'tax', 'dsc') ORDER BY item_cnt");
    while (!$rows->EOF) {
      $row = $rows->fields;
      $gt  = $row['gl_type'];
      $rows->MoveNext();
      if ($gt === 'dsc') { $discount += (float)$row['debit_amount'] * $rate; continue; }
      $charged = round((float)$row['credit_amount'] * $rate, 2);
      if ($charged == 0) continue;
      if ($gt === 'sos') {
        $name = trim($row['description'] ?? '');
        if (!empty($row['sku'])) $name = $row['sku'] . ($name ? ' ' . $name : '');
        if (!$name) $name = 'Item';
        $qty = (float)$row['qty'] > 0 ? (float)$row['qty'] : 1.0;
      } else {
        $name = $gt === 'frt' ? 'Shipping' : (trim($row['description'] ?? '') ?: 'Tax');
        $qty  = 1.0;
      }
      $unit = round($charged / $qty, 2);
      if (abs($unit * $qty - $charged) >= 0.005) {
        $name .= ' (x' . rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') . ')';
        $qty   = 1.0;
        $unit  = $charged;
      }
      $lines[$gt][] = ['kind' => $gt, 'name' => $name, 'qty' => $qty, 'unit' => $unit];
    }
    return ['lines' => array_merge($lines['sos'], $lines['frt'], $lines['tax']), 'discount' => round($discount, 2)];
  }

  // Email the customer the payment link of $request (a payment_requests row),
  // with a copy of the invoice attached when one can be printed. $text holds
  // the method's wording: subject (sprintf, with the company name), intro,
  // button, expires ('' when the link does not expire), alt_link and contact;
  // $colour is the button's. Returns true when the email was sent.
  function email_payment_link($request, $email, array $text, $colour) {
    global $db;
    $url = (string)($request['url'] ?? '');
    $inv = $db->Execute("SELECT purchase_invoice_id FROM " . TABLE_JOURNAL_MAIN . " WHERE id = " . (int)$request['ref_id']);
    $description = $inv->EOF ? '' : 'Invoice ' . $inv->fields['purchase_invoice_id'];

    $attachments = [];
    $pdf_path    = null;
    $pdf = $this->invoice_pdf((int)$request['ref_id']);
    if ($pdf) {
      $pdf_path = DIR_FS_MY_FILES . $_SESSION['company'] . '/temp/invoice_' . (int)$request['ref_id'] . '_' . time() . '.pdf';
      if (@file_put_contents($pdf_path, $pdf) !== false) $attachments['file'] = $pdf_path;
      else $pdf_path = null;
    }

    $from_name    = defined('STORE_NAME') ? STORE_NAME : 'FreeBooks';
    $from_address = defined('EMAIL_FROM') ? EMAIL_FROM : '';
    $subject      = sprintf($text['subject'], $from_name) . ($description ? ' — ' . $description : '');
    $safe_url     = htmlspecialchars($url, ENT_QUOTES);
    // validate_send_mail() sends this as HTML when EMAIL_USE_HTML is on, and
    // strips the tags for plain-text delivery.
    $body = '<html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;margin:0;padding:20px">'
          . '<p>' . htmlspecialchars($text['intro'])
          .   ($description ? ' <strong>' . htmlspecialchars($description, ENT_QUOTES) . '</strong>' : '') . '</p>'
          . '<p style="text-align:center;margin:28px 0">'
          .   '<a href="' . $safe_url . '" style="background:' . $colour . ';color:#ffffff;padding:14px 32px;'
          .   'border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;display:inline-block">'
          .   htmlspecialchars($text['button']) . '</a>'
          . '</p>'
          . ($text['expires'] !== '' ? '<p style="font-size:12px;color:#888;font-style:italic">' . htmlspecialchars($text['expires']) . '</p>' : '')
          . '<p style="font-size:12px;color:#555">' . htmlspecialchars($text['alt_link']) . '<br>'
          .   '<a href="' . $safe_url . '" style="color:' . $colour . '">' . $safe_url . '</a></p>'
          . '<p>' . htmlspecialchars($text['contact']) . '</p>'
          . '<p>Thank you,<br>' . htmlspecialchars($from_name, ENT_QUOTES) . '</p>'
          . '</body></html>';
    $result = validate_send_mail('', $email, $subject, $body, $from_name, $from_address, [], $attachments);
    if ($pdf_path) @unlink($pdf_path);
    return $result !== false;
  }

  // The printed invoice, from whichever form is filed under 'cust:inv', or null.
  // Best effort: an email goes without it rather than not at all.
  function invoice_pdf($invoice_id) {
    global $db;
    try {
      if (!defined('MODULE_FORMS_STATUS') || !defined('TABLE_PHREEFORM')) return null;
      $r = $db->Execute("SELECT id FROM " . TABLE_PHREEFORM
        . " WHERE doc_group = 'cust:inv' AND (doc_ext = 'frm' OR doc_ext = 'rpt') ORDER BY doc_title LIMIT 1");
      if ($r->EOF) return null;
      require_once(DIR_FS_MODULES . 'forms/functions/forms.php');
      $rendered = forms_render_to_string((int)$r->fields['id'], [$invoice_id]);
      return !empty($rendered['pdf']) ? $rendered['pdf'] : null;
    } catch (\Throwable $e) {
      error_log($this->code . ': the invoice PDF for ' . $invoice_id . ' could not be made: ' . $e->getMessage());
      return null;
    }
  }

  function confirmation() {
    return array('title' => $this->description);
  }
 
  function getsortorder(){
        if(!defined('MODULE_PAYMENT_'.strtoupper($this->code).'_SORT_ORDER')){
                return $this->sort_order;
        } else {
                return constant('MODULE_PAYMENT_'.strtoupper($this->code).'_SORT_ORDER');
        }
  }

  function expirationMonths() {
  	$months = array();
  	for ($i = 1; $i < 13; $i++) {
  		$j = ($i < 10) ? '0' . $i : $i;
  		$months[] = array('id' => sprintf('%02d', $i), 'text' => $j.'-'.strftime('%B',mktime(0,0,0,$i,1,2000)));
  	}
  	return $months;
  }

  function expirationYears() {
  	$years = array();
  	$today = getdate();
  	for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
  		$year = strftime('%Y',mktime(0,0,0,1,1,$i));
  		$years[] = array('id' => $year, 'text' => $year);
  	}
  	return $years;
  }

  function validate($ccNumber) {
  	global $messageStack;
    $cardNumber = strrev($ccNumber);
    $numSum = 0;
    for ($i = 0; $i < strlen($cardNumber); $i++) {
      $currentNum = substr($cardNumber, $i, 1);
      if ($i % 2 == 1) $currentNum *= 2; // Double every second digit
      if ($currentNum > 9) { // Add digits of 2-digit numbers together
        $firstNum = $currentNum % 10;
        $secondNum = ($currentNum - $firstNum) / 10;
        $currentNum = $firstNum + $secondNum;
      }
      $numSum += $currentNum;
    }
    if ($numSum % 10 <> 0) { // If the total has remainder it's bad
    	$messageStack->add(TEXT_CCVAL_ERROR_INVALID_NUMBER,'error');
    	return false;
    }
    return true;
  }
}
?>