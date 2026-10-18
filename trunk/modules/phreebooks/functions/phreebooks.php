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
//  Path: /modules/phreebooks/functions/phreebooks.php
//

function fetch_item_description($id) {
  global $db;
  $result = $db->Execute("select description from " . TABLE_JOURNAL_ITEM . " where ref_id = " . $id . " limit 1");
  return $result->fields['description'];
}

function validate_fiscal_year($next_fy, $next_period, $next_start_date, $num_periods = 12) {
  global $db;
  for ($i = 0; $i < $num_periods; $i++) {
	$fy_array = array(
	  'period'      => $next_period,
	  'fiscal_year' => $next_fy,
	  'start_date'  => $next_start_date,
	  'end_date'    => gen_specific_date($next_start_date, $day_offset = -1, $month_offset = 1),
	  'date_added'  => date('Y-m-d'),
	);
	db_perform(TABLE_ACCOUNTING_PERIODS, $fy_array, 'insert');
	$next_period++;
	$next_start_date = gen_specific_date($next_start_date, $day_offset = 0, $month_offset = 1);
  }
  return $next_period--;
}

function modify_account_history_records($id, $add_acct = true) {
  global $db;
  $result = $db->Execute("select max(period) as period from " . TABLE_ACCOUNTING_PERIODS);
  $max_period = $result->fields['period'];
  if (!$max_period) die ('table: '.TABLE_ACCOUNTING_PERIODS.' is not set, run setup.');
  if ($add_acct) {
    $result = $db->Execute("select heading_only from " . TABLE_CHART_OF_ACCOUNTS . " where id = '" . db_input($id) . "'");
	if ($result->fields['heading_only'] <> '1') {
	  for ($i = 0, $j = 1; $i < $max_period; $i++, $j++) {
	    $db->Execute("insert into " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " (account_id, period) values('" . db_input($id) . "', '" . $j . "')");
	  }
	}
  } else {
	$result = $db->Execute("delete from " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " where account_id = '" . db_input($id) . "'");
  }
}

function build_and_check_account_history_records() {
  global $db;
  $result = $db->Execute("select max(period) as period from " . TABLE_ACCOUNTING_PERIODS);
  $max_period = $result->fields['period'];
  if (!$max_period) die ('table: '.TABLE_ACCOUNTING_PERIODS.' is not set, run setup.');
  $result = $db->Execute("select id, heading_only from " . TABLE_CHART_OF_ACCOUNTS . " order by id");
  while (!$result->EOF) {
    if ($result->fields['heading_only'] <> '1') {
	  $account_id = $result->fields['id'];
	  for ($i = 0, $j = 1; $i < $max_period; $i++, $j++) {
	    $record_found = $db->Execute("select id from " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " where account_id = '" . $account_id . "' and period = " . $j);
	    if (!$record_found->RecordCount()) {
		  $db->Execute("insert into " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " (account_id, period) values('" . $account_id . "', '" . $j . "')");
		 }
	  }
	}
	$result->MoveNext();
  }
}

function get_fiscal_year_pulldown() {
    global $db;
    $fy_values = $db->Execute("select distinct fiscal_year from " . TABLE_ACCOUNTING_PERIODS . " order by fiscal_year");
    $fy_array = array();
    while (!$fy_values->EOF) {
      $fy_array[] = array('id' => $fy_values->fields['fiscal_year'], 'text' => $fy_values->fields['fiscal_year']);
      $fy_values->MoveNext();
    }
    return $fy_array;
}

function load_coa_types() {
  global $coa_types_list;
  if (!is_array($coa_types_list)) { 
    require_once(DIR_FS_MODULES . 'phreebooks/defaults.php');
  }
  $coa_types = array();
  foreach ($coa_types_list as $value) {
    $coa_types[$value['id']] = array(
	  'id'    => $value['id'],
	  'text'  => $value['text'],
	  'asset' => $value['asset'],
	);
  }
  return $coa_types;
}

function load_coa_info($types = array()) { // includes inactive accounts
  global $db;
  $coa_data = array();
  $sql = "select * from " . TABLE_CHART_OF_ACCOUNTS;
  if (count(is_array($types > 0) ? $types > 0 : [])) $sql .= " where account_type in (" . implode(", ", $types) . ")";
  $result = $db->Execute($sql);
  while (!$result->EOF) {
    $coa_data[$result->fields['id']] = array(
	  'id'              => $result->fields['id'],
	  'description'     => $result->fields['description'],
	  'heading_only'    => $result->fields['heading_only'],
	  'primary_acct_id' => $result->fields['primary_acct_id'],
	  'account_type'    => $result->fields['account_type'],
	);
	$result->MoveNext();
  }
  return $coa_data;
}

/**
 * Where the shipped charts of accounts live, for the locale in play.
 *
 * A locale with its own charts directory gets it; anything else falls back to
 * en_us, which in practice is every locale - only en_us has charts.
 *
 * Every caller reads each file from the directory it was listed out of.
 */
function phreebooks_chart_dir(): string {
  $locale = !empty($_SESSION['language']) ? basename($_SESSION['language'])
          : (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en_us');
  $dir    = DIR_FS_MODULES . 'phreebooks/language/' . $locale . '/charts/';
  return is_dir($dir) ? $dir : DIR_FS_MODULES . 'phreebooks/language/en_us/charts/';
}

/**
 * One chart of accounts file, read.
 *
 * Beyond the account list a chart may say three things about itself, all
 * optional, so a chart without them still parses:
 *
 *  - <currency> the currency the company keeps its books in.
 *  - <demo>     whether the shipped demonstration data may be loaded against
 *               it. The demo records name US account numbers literally, so they
 *               only mean anything on a chart numbered the way those charts
 *               are. A chart that says nothing is taken to allow it.
 *  - <defaults> which of the chart's OWN accounts each AR/AP setting means.
 *
 * That last one matters most: the settings name account numbers, and only the
 * chart knows which of its own accounts each one should be.
 *
 * Returns false rather than a half read chart if the file is not one.
 */
function phreebooks_chart_read($file) {
  $name = basename((string)$file);
  $path = phreebooks_chart_dir() . $name;
  if (substr($name, -4) <> '.xml' || !is_file($path)) return false;
  $xml = xml_to_object(file_get_contents($path));
  if (is_object($xml) && isset($xml->ChartofAccounts)) $xml = $xml->ChartofAccounts; // just the first one
  if (!is_object($xml)) return false;
  $accounts = isset($xml->account) ? $xml->account : array();
  if (is_object($accounts)) $accounts = array($accounts); // a chart of one account
  if (!is_array($accounts)) $accounts = array();
  $defaults = array();
  if (isset($xml->defaults) && is_object($xml->defaults)) {
    foreach (get_object_vars($xml->defaults) as $key => $value) {
      if (is_object($value) || is_array($value)) continue;
      $defaults[strtoupper($key)] = trim((string)$value);
    }
  }
  $description = (isset($xml->description) && is_string($xml->description) && $xml->description <> '')
    ? $xml->description : substr($name, 0, -4);
  return array(
    'file'        => $name,
    'description' => $description,
    'country'     => (isset($xml->country)  && is_string($xml->country))  ? $xml->country  : '',
    'currency'    => (isset($xml->currency) && is_string($xml->currency)) ? $xml->currency : '',
    'demo'        => !isset($xml->demo) || (string)$xml->demo <> '0',
    'defaults'    => $defaults,
    'accounts'    => $accounts,
  );
}

/**
 * The charts a company can be seeded from, file name => the chart's own
 * description. Mirrors forms_paper_sizes(): one list, offered by the installer,
 * the company manager and the settings page alike.
 */
function phreebooks_charts(): array {
  $charts = array();
  $files  = @scandir(phreebooks_chart_dir());
  if (!is_array($files)) return $charts;
  foreach ($files as $file) {
    if (substr($file, -4) <> '.xml') continue;
    $chart = phreebooks_chart_read($file);
    if ($chart) $charts[$chart['file']] = $chart['description'];
  }
  return $charts;
}

/**
 * The chart a form starts on before anybody has chosen.
 *
 * Only the preselection - every form still offers the whole list, and the
 * installer and the company manager both check what came back against it.
 *
 * Asked of the list rather than named as a constant so that removing a chart
 * from the shipped set cannot leave the forms pointing at a file that is not
 * there. If the preferred one is gone, the first chart alphabetically stands in.
 */
function phreebooks_default_chart(): string {
  $charts    = phreebooks_charts();
  $preferred = 'GB_Limited.xml';
  if (isset($charts[$preferred])) return $preferred;
  return count($charts) ? (string)array_key_first($charts) : '';
}

/**
 * Put a chart's accounts into the chart of accounts table.
 *
 * Returns the ids it did NOT write because the table already held them, so the
 * one caller that merges into a live chart (the settings page importer) can say
 * which, and the two that seed an empty one can ignore it.
 */
function phreebooks_chart_insert($accounts): array {
  global $db;
  $skipped = array();
  if (!is_array($accounts)) return $skipped;
  foreach ($accounts as $account) {
    if (!is_object($account)) continue;
    $id = isset($account->id) ? trim((string)$account->id) : '';
    if ($id === '') continue;
    $result = $db->Execute("select id from " . TABLE_CHART_OF_ACCOUNTS . " where id = '" . db_input($id) . "'");
    if ($result->RecordCount() > 0) { $skipped[] = $id; continue; }
    db_perform(TABLE_CHART_OF_ACCOUNTS, array(
      'id'              => $id,
      'description'     => isset($account->description) ? $account->description : '',
      'heading_only'    => isset($account->heading)     ? $account->heading     : '0',
      'primary_acct_id' => isset($account->primary)     ? $account->primary     : '',
      'account_type'    => isset($account->type)        ? $account->type        : '0',
    ), 'insert');
  }
  return $skipped;
}

/**
 * The open documents a receipt or payment may settle, as the page shows them.
 *
 * $display_code/$display_value decide the currency the AMOUNTS come back in,
 * and the two callers want different things (issue #63):
 *
 *  - Building a NEW receipt, nothing is settled yet and there is no currency to
 *    be in, so each document is priced in its own - which is the only figure the
 *    customer would recognise.
 *  - Opening a STORED one, the record already has a currency and the arithmetic
 *    on the page is in it. Pricing a row in some other currency there would put
 *    two units in one column and make the total stop adding up. So the caller
 *    passes the record's own pair and every row is shown in it.
 *
 * Either way the row carries `currencies_code`, the DOCUMENT's own, so the page
 * can say when the two differ - on a record posted by an earlier release, where
 * it is worth saying rather than hiding.
 */
function fill_paid_invoice_array($id, $account_id, $type = 'c', $display_code = null, $display_value = null) {
	// to build this data array, all current open invoices need to be gathered and then the paid part needs
	// to be applied along with discounts taken by row.
	global $db, $currencies;
	$negate = ((JOURNAL_ID == 20 && $type == 'c') || (JOURNAL_ID == 18 && $type == 'v')) ? true : false;
	// first read all currently open invoices and the payments of interest and put into an array
	$paid_indeces   = array();
	$payment_fields = ''; // a new payment has no stored details yet
	if ($id > 0) { 
	  $result = $db->Execute("select distinct so_po_item_ref_id from " . TABLE_JOURNAL_ITEM . " where ref_id = " . $id);
	  while (!$result->EOF) {
	    if ($result->fields['so_po_item_ref_id']) $paid_indeces[] = $result->fields['so_po_item_ref_id'];
	    $result->MoveNext();
	  }
	}
	switch ($type) {
	  case 'c': $search_journal = '(12, 13)'; break;
	  case 'v': $search_journal = '(6, 7)';   break;
	  default: return false;
	}
	$open_invoices = array();
	// currencies_code/currencies_value come with the document because total_amount does NOT:
	// every journal amount is stored in the HOME currency (orders/pre_process.php divides the
	// typed figure by the rate), so a USD invoice carries its GBP carrying value and the pair
	// above is the only thing that says what the customer was actually billed. Without them a
	// foreign invoice would show as its home-currency equivalent, with no way to settle it in
	// the currency it was raised in (issue #63).
	$sql = "select id, journal_id, post_date, terms, purch_order_id, purchase_invoice_id, total_amount, gl_acct_id,
	  currencies_code, currencies_value 
	  from " . TABLE_JOURNAL_MAIN . " 
	  where (journal_id in " . $search_journal . " and closed = '0' and bill_acct_id = " . $account_id . ")";
	if (!empty($paid_indeces)) $sql .= " or (id in (" . implode(',',$paid_indeces) . ") and closed = '0')";
	$sql .= " order by post_date";
	$result = $db->Execute($sql);
	while (!$result->EOF) {
	  if ($result->fields['journal_id'] == 7 || $result->fields['journal_id'] == 13) {
	    $result->fields['total_amount'] = -$result->fields['total_amount'];
	  }
	  $result->fields['total_amount'] -= fetch_partially_paid($result->fields['id']);
	  $result->fields['description']   = $result->fields['purch_order_id'];
	  $result->fields['discount']      = 0;
	  $result->fields['amount_paid']   = 0;
	  $open_invoices[$result->fields['id']] = $result->fields;
	  $result->MoveNext();
	}
	// next read the record of interest and add/adjust open invoice array with amounts
	$sql = "select id, ref_id, so_po_item_ref_id, gl_type, description, debit_amount, credit_amount, gl_account 
	  from " . TABLE_JOURNAL_ITEM . " where ref_id = " . $id;
	$result = $db->Execute($sql);
	while (!$result->EOF) {
	  $amount = ($result->fields['debit_amount']) ? $result->fields['debit_amount'] : $result->fields['credit_amount'];
	  if ($negate) $amount = -$amount;
	  $index = $result->fields['so_po_item_ref_id'];
	  // An invoice this payment closed is not among the open ones read above; its row starts empty here.
	  if (in_array($result->fields['gl_type'], array('dsc', 'chk', 'pmt')) && !isset($open_invoices[$index])) {
	    $open_invoices[$index] = array('total_amount' => 0, 'description' => '', 'discount' => 0, 'amount_paid' => 0,
	      'currencies_code' => '', 'currencies_value' => '');
	  }
	  switch ($result->fields['gl_type']) {
	    case 'dsc': // it's the discount field
		  $open_invoices[$index]['discount']      = $amount;
		  $open_invoices[$index]['amount_paid']  -= $amount;
		  break;
		case 'chk':
	    case 'pmt': // it's the payment field
		  $open_invoices[$index]['total_amount'] += $amount;
		  $open_invoices[$index]['description']   = $result->fields['description'];
		  $open_invoices[$index]['amount_paid']   = $amount;
		  break;
		case 'ttl':
		  $payment_fields = $result->fields['description']; // payment details
		default:
	  }
	  $result->MoveNext();
	}
	ksort($open_invoices);

	$balance   = 0;
	$index     = 0;
	$item_list = array();
	foreach ($open_invoices as $key => $line_item) {
	  // fetch some information about the invoice
	  $sql = "select id, post_date, terms, purchase_invoice_id, purch_order_id, gl_acct_id, waiting, currencies_code, currencies_value
		from " . TABLE_JOURNAL_MAIN . " where id = " . $key;
	  $result = $db->Execute($sql);
	  // A payment can point at an entry that is not there - a history import's broken link. Its row
	  // still lists what was paid, keeping the payment's own link, with the entry's details blank.
	  $inv = $result->EOF ? array('id' => $key, 'post_date' => '', 'terms' => '', 'purchase_invoice_id' => '',
	    'purch_order_id' => '', 'gl_acct_id' => '', 'waiting' => '0', 'currencies_code' => '', 'currencies_value' => '') : $result->fields;
	  $due_dates = calculate_terms_due_dates($inv['post_date'], $inv['terms'], ($type == 'v' ? 'AP' : 'AR'));
	  if ($negate) {
	    $line_item['total_amount'] = -$line_item['total_amount'];
	    $line_item['discount']     = -$line_item['discount'];
	    $line_item['amount_paid']  = -$line_item['amount_paid'];
	  }
	  $balance += $line_item['total_amount'];
	  // A document with no currency code - posted by an earlier release, or by a
	  // path that never set one - means home currency at par, so that is what it
	  // keeps rather than an empty code the formatter cannot look up.
	  $doc_code   = $line_item['currencies_code']  ?: ($inv['currencies_code']  ?: DEFAULT_CURRENCY);
	  $doc_value  = $line_item['currencies_value'] ?: ($inv['currencies_value'] ?: 1);
	  $show_code  = $display_code ?: $doc_code;
	  $show_value = $display_code ? ($display_value ?: 1) : $doc_value;
	  $item_list[] = array(
		'id'                  => $inv['id'],
		'waiting'             => $inv['waiting'],
		'purchase_invoice_id' => $inv['purchase_invoice_id'],
		'purch_order_id'      => $inv['purch_order_id'],
		'percent'             => $due_dates['discount'],
		'post_date'           => $inv['post_date'],
		'early_date'          => $due_dates['early_date'],
		'net_date'            => gen_locale_date($due_dates['net_date']),
		// Not the company's currency. The stored figure is the home-currency one,
		// so format() is asked to APPLY a rate rather than to leave the number
		// alone: 73.23 stored at 1.3656 is the 100.00 the customer sees on their
		// invoice. Which rate is the caller's choice, above.
		'currencies_code'     => $doc_code,      // the document's own, for saying when they differ
		'currencies_value'    => $doc_value,
		'display_code'        => $show_code,     // what the amounts below are in
		'display_value'       => $show_value,
		'total_amount'        => $currencies->format($line_item['total_amount'], true, $show_code, $show_value),
		'gl_acct_id'          => $inv['gl_acct_id'],
		'description'         => $line_item['description'],
		'discount'            => $line_item['discount']    ? $currencies->format($line_item['discount'],    true, $show_code, $show_value) : '',
		'amount_paid'         => $line_item['amount_paid'] ? $currencies->format($line_item['amount_paid'], true, $show_code, $show_value) : '',
	  );
	  $index++;
	}
	switch(PHREEBOOKS_DEFAULT_BILL_SORT) {
	  case 'due_date': // sort the open invoice array to order by preference
		foreach ($item_list as $key => $row) {
			$net_date[$key]   = $row['net_date'];
			$invoice_id[$key] = $row['purchase_invoice_id'];
		}
		array_multisort($net_date, SORT_ASC, $invoice_id, SORT_ASC, $item_list);
	  default: // sort by invoice number
	}
    return array('balance' => $balance, 'payment_fields' => $payment_fields, 'invoices' => $item_list);
}

function fetch_partially_paid($id) {
  global $db;
  $sql = "select sum(i.debit_amount) as debit, sum(i.credit_amount) as credit 
	from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id 
	where i.so_po_item_ref_id = $id and m.journal_id in (18, 20) and i.gl_type in ('chk', 'pmt') 
	group by m.journal_id";
  $result = $db->Execute($sql);
  if($result->RecordCount() == 0) return 0;
  if ($result->fields['debit'] || $result->fields['credit']) {
    return $result->fields['debit'] + $result->fields['credit'];
  } else {
    return 0;
  }
}

function calculate_terms_due_dates($post_date, $terms_encoded, $type = 'AR') {
  $terms = explode(':', (string)$terms_encoded);
  $date_details = gen_get_dates($post_date);
  $result = array();
  switch ($terms[0]) {
	default:
	case '0': // Default terms
		$result['discount'] = constant($type . '_PREPAYMENT_DISCOUNT_PERCENT') / 100;
		$result['net_date'] = gen_specific_date($post_date, constant($type . '_NUM_DAYS_DUE'));
		if ($result['discount'] <> 0) {
		  $result['early_date'] = gen_specific_date($post_date, constant($type . '_PREPAYMENT_DISCOUNT_DAYS'));
		} else {
		  $result['early_date'] = $post_date; // move way out
		}
		break;
	case '1': // Cash on Delivery (COD)
	case '2': // Prepaid
		$result['discount']   = 0;
		$result['early_date'] = $post_date;
		$result['net_date']   = $post_date;
		break;
	case '3': // Special terms
		$result['discount']   = $terms[1] / 100;
		$result['early_date'] = gen_specific_date($post_date, $terms[2]);
		$result['net_date']   = gen_specific_date($post_date, $terms[3]);
		break;
	case '4': // Due on day of next month
		$result['discount']   = $terms[1] / 100;
		$result['early_date'] = gen_specific_date($post_date, $terms[2]);
		$result['net_date']   = date('Y-m-d', mktime(0, 0, 0, $date_details['ThisMonth'] + 1, (int)$terms[3], $date_details['ThisYear']));
		break;
	case '5': // Due at end of month
		$result['discount']   = $terms[1] / 100;
		$result['early_date'] = gen_specific_date($post_date, $terms[2]);
		$result['net_date']   = date('Y-m-d', mktime(0, 0, 0, $date_details['ThisMonth'], $date_details['TotalDays'], $date_details['ThisYear']));
		break;
  }
  return $result;
}

function load_cash_acct_balance($post_date, $gl_acct_id, $period) {
  global $db, $messageStack;
  $acct_balance = 0;
  if (!$gl_acct_id) return $acct_balance;
  $sql = "select beginning_balance from " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " 
	where account_id = '" . db_input($gl_acct_id) . "' and period = " . (int)$period;
  $result = $db->Execute($sql);
  $acct_balance = $result->fields['beginning_balance'];

  // load the payments and deposits for the current period
  $bank_list = array();
  $sql = "select i.debit_amount, i.credit_amount
	from " . TABLE_JOURNAL_MAIN . " m inner join " . TABLE_JOURNAL_ITEM . " i on m.id = i.ref_id
	where m.period = " . (int)$period . " and i.gl_account = '" . db_input($gl_acct_id) . "' and m.post_date <= '" . db_input($post_date) . "' 
	order by m.post_date, m.journal_id";
  $result = $db->Execute($sql);
  while (!$result->EOF) {
    $acct_balance += $result->fields['debit_amount'] - $result->fields['credit_amount'];
    $result->MoveNext();
  }
  return $acct_balance;
}

  function gen_build_tax_auth_array() {
    global $db;
    $tax_auth_values = $db->Execute("select tax_auth_id, description_short, account_id , tax_rate
      from " . TABLE_TAX_AUTH . " order by description_short");
    if ($tax_auth_values->RecordCount() < 1) {
      return false;
    } else {
		while (!$tax_auth_values->EOF) {
		  $tax_auth_array[$tax_auth_values->fields['tax_auth_id']] = array(
			'description_short' => $tax_auth_values->fields['description_short'],
			'account_id'        => $tax_auth_values->fields['account_id'],
			'tax_rate'          => $tax_auth_values->fields['tax_rate'],
		  );
		  $tax_auth_values->MoveNext();
		}
    	return $tax_auth_array;
    }
  }

  function gen_calculate_tax_rate($tax_authorities_chosen, $tax_auth_array) {
	$chosen_auth_array = explode(':', $tax_authorities_chosen);
	$total_tax_rate = 0;
	while ($chosen_auth = array_shift($chosen_auth_array)) {
	  $total_tax_rate += $tax_auth_array[$chosen_auth]['tax_rate'];
	}
	return $total_tax_rate;
  }

  function ord_calculate_tax_drop_down($type = 'c') {
    global $db;
	$tax_auth_array = gen_build_tax_auth_array();
    $sql = "select tax_rate_id, description_short, rate_accounts from " . TABLE_TAX_RATES;
	switch ($type) {
	  default:
	  case 'c':
	  case 'v': $sql .= " where type = '" . $type . "'"; break;
	  case 'b': // both
	}
	$tax_rates = $db->Execute($sql);
    $tax_rate_drop_down = array();
    $tax_rate_drop_down[] = array('id' => '0', 'rate' => '0', 'text' => TEXT_NONE, 'auths' => '');
	while (!$tax_rates->EOF) {
	  $tax_rate_drop_down[] = array(
	    'id'    => $tax_rates->fields['tax_rate_id'],
		'rate'  => gen_calculate_tax_rate($tax_rates->fields['rate_accounts'], $tax_auth_array), 
		'text'  => $tax_rates->fields['description_short'],
		'auths' => $tax_rates->fields['rate_accounts'],
	  );
	  $tax_rates->MoveNext();
	}
	return $tax_rate_drop_down;
  }

  /**
   * The javascript tax_rates array for a page that totals tax in the browser, one entry per
   * row of $tax_rates (from ord_calculate_tax_drop_down), in the same order as its drop down.
   * Each entry carries its authorities' own rates as well as the combined one, so that the page
   * totals tax as the ledger posts it - per authority, rounded once per document - rather than
   * rounding the combined tax (issue #118). See documentTax() in includes/common.js.
   */
  function ord_js_tax_rates($tax_rates) {
	$tax_auth_array = gen_build_tax_auth_array();
	$js = 'var tax_rates = new Array(' . count($tax_rates) . ');' . chr(10);
	foreach (array_values($tax_rates) as $i => $rate) {
	  $auths = array();
	  foreach (explode(':', (string)$rate['auths']) as $auth) {
		if ($auth === '') continue;
		$auths[] = array('id' => $auth, 'rate' => isset($tax_auth_array[$auth]) ? (float)$tax_auth_array[$auth]['tax_rate'] : 0);
	  }
	  $js .= 'tax_rates[' . $i . '] = new salesTaxes(' . json_encode((string)$rate['id']) . ', ' . json_encode((string)$rate['text'])
		  . ', ' . json_encode((string)$rate['rate']) . ', ' . json_encode($auths) . ');' . chr(10);
	}
	return $js;
  }

  function ord_get_so_po_num($id = '') {
	global $db;
	$result = $db->Execute("select purchase_invoice_id from " . TABLE_JOURNAL_MAIN . " where id = " . $id);
	return ($result->RecordCount()) ? $result->fields['purchase_invoice_id'] : '';
  }

  function ord_get_projects() {
    global $db;
    $result_array = array();
    $result_array[] = array('id' => '', 'text' => TEXT_NONE);
	// fetch cost structure
	$costs = array();
	$result = $db->Execute("select cost_id, description_short from " . TABLE_PROJECTS_COSTS . " where inactive = '0'");
	while(!$result->EOF) {
	  $costs[$result->fields['cost_id']] = $result->fields['description_short'];
	  $result->MoveNext();
	}
	// fetch phase structure
	$phases = array();
	$result = $db->Execute("select phase_id, description_short, cost_breakdown from " . TABLE_PROJECTS_PHASES . " where inactive = '0'");
	while(!$result->EOF) {
	  $phases[$result->fields['phase_id']] = array(
	  	'text'   => $result->fields['description_short'],
		'detail' => $result->fields['cost_breakdown'],
	  );
	  $result->MoveNext();
	}
	// fetch projects
	$result = $db->Execute("select id, short_name, account_number from " . TABLE_CONTACTS . " where type = 'j' and inactive <> '1'");
	while(!$result->EOF) {
	  $base_id   = $result->fields['id'];
	  $base_text = $result->fields['short_name'];
	  if ($result->fields['account_number'] == '1' && !empty($phases)) { // use phases
		foreach ($phases as $phase_id => $phase) {
		  $phase_base = $base_id   . ':' . $phase_id;
		  $phase_text = $base_text . ' -> ' . $phase['text'];
		  if ($phase['detail'] == '1' && !empty($costs)) {
		    foreach ($costs as $cost_id => $cost) {
              $result_array[] = array('id' => $phase_base . ':' . $cost_id, 'text' => $phase_text . ' -> ' . $cost);
			}
		  } else {
            $result_array[] = array('id' => $phase_base, 'text' => $phase_text);
		  }
		}
	  } else {
        $result_array[] = array('id' => $base_id, 'text' => $base_text);
	  }
	  $result->MoveNext();
	}
    return $result_array;
  }

  function gen_auto_update_period($show_message = true) {
	global $db, $messageStack;
	$period = gen_calculate_period(date('Y-m-d'), true);
	if (defined('CURRENT_ACCOUNTING_PERIOD') && $period == CURRENT_ACCOUNTING_PERIOD) return; // we're in the current period
	if (!$period) { // we're outside of the defined fiscal years
	  if ($show_message) $messageStack->add(ERROR_MSG_POST_DATE_NOT_IN_FISCAL_YEAR,'error');
	} else { // update CURRENT_ACCOUNTING_PERIOD constant with this new period
	  $result = $db->Execute("select start_date, end_date from " . TABLE_ACCOUNTING_PERIODS . " where period = " . $period);
	  write_configure('CURRENT_ACCOUNTING_PERIOD',       $period);
	  write_configure('CURRENT_ACCOUNTING_PERIOD_START', $result->fields['start_date']);
	  write_configure('CURRENT_ACCOUNTING_PERIOD_END',   $result->fields['end_date']);
	  gen_add_audit_log(GEN_LOG_PERIOD_CHANGE);
	  if ($show_message) {
	    $messageStack->add(sprintf(ERROR_MSG_ACCT_PERIOD_CHANGE, $period),'success');
	  }
	}
  }

  function build_search_sql($fields, $id, $id_from = '', $id_to = '') {
    $crit = array();
    foreach ($fields as $field) {	
	  $output = '';
	  switch ($id) {
	    default:
		case 'all':  break;
		case 'eq':   if ($id_from) $output .= $field . " = '" . db_input($id_from) . "'";      break;
		case 'neq':  if ($id_from) $output .= $field . " <> '" . db_input($id_from) . "'";     break;
		case 'like': if ($id_from) $output .= $field . " like '%" . db_input($id_from) . "%'"; break;
		case 'rng':
		  if ($id_from)          $output .= $field . " >= '" . db_input($id_from) . "'";
		  if ($output && $id_to) $output .= " and ";
		  if ($id_to)            $output .= $field . " <= '" . db_input($id_to) . "'";
	  }
	  if ($output) $crit[] = $output;
    }
    return ($crit) ? ('(' . implode(' or ', $crit) . ')') : '';
  }

  function repost_journals($journals, $start_date, $end_date) {
	global $db, $messageStack;
	if (!empty($journals)) {
	  $sql = "SELECT id FROM ".TABLE_JOURNAL_MAIN." WHERE journal_id IN (".implode(',', $journals).") 
		AND post_date>= '$start_date' AND post_date<'".gen_specific_date($end_date, 1)."' ORDER BY post_date, id";
	  $result = $db->Execute($sql);
	  $cnt = 0;
	  // Each re-post skips carrying balances forward; it is done once, from the
	  // earliest period any of them touched, after the last (issue #108).
	  $carry = new journal();
	  $first_period = null;
	  $db->transStart();
	  while (!$result->EOF) {
	    $gl_entry = new journal($result->fields['id']);
	    $gl_entry->remove_cogs_rows(); // they will be regenerated during the re-post
	    if (!$gl_entry->Post('edit', true)) {
		  $db->transRollback();
		  $messageStack->add('<br /><br />Failed Re-posting the journals, try a smaller range. The record that failed was # '.$gl_entry->id,'error');
		  return false;
	    }
		$carry->affected_accounts = gen_array_key_merge($carry->affected_accounts, $gl_entry->affected_accounts);
		$first_period = min($first_period ?? $gl_entry->first_period, $gl_entry->first_period);
		$cnt++;
	    $result->MoveNext();
	  }
	  if ($first_period !== null && !$carry->update_chart_history_periods($first_period)) return false;
      $db->transCommit();
	  return $cnt;
	}
  }

  function calculate_aging($id, $type = 'c', $special_terms = '0') {
  	global $db;
  	$output = array();
  	if (!$id) return $output;
  	$today         = date('Y-m-d');
  	$terms         = explode(':', $special_terms);
  	$credit_limit  = $terms[4] ? $terms[4] : constant(($type=='v'?'AP':'AR').'_CREDIT_LIMIT_AMOUNT');
	$due_days      = $terms[3] ? $terms[3] : constant(($type=='v'?'AP':'AR').'_NUM_DAYS_DUE');
	$due_date      = gen_specific_date($today, -$due_days);
	$late_30 = gen_specific_date($today, ($type == 'v') ? -AP_AGING_DATE_1 : -AR_AGING_PERIOD_1);
	$late_60 = gen_specific_date($today, ($type == 'v') ? -AP_AGING_DATE_2 : -AR_AGING_PERIOD_2);
	$late_90 = gen_specific_date($today, ($type == 'v') ? -AP_AGING_DATE_3 : -AR_AGING_PERIOD_3);
	$output = array(
	  'balance_0'  => '0',
	  'balance_30' => '0',
	  'balance_60' => '0',
	  'balance_90' => '0',
	);
	$inv_jid = ($type == 'v') ? '6, 7' : '12, 13';
	$pmt_jid = ($type == 'v') ? '20' : '18';
	$total_outstanding = 0;
	$past_due          = 0;
	$sql = "select id from " . TABLE_JOURNAL_MAIN . " 
		where bill_acct_id = " . $id . " and journal_id in (" . $inv_jid . ") and closed = '0'";
	$open_inv = $db->Execute($sql);
	while(!$open_inv->EOF) {
	  $result = $db->Execute("select debit_amount, credit_amount from " . TABLE_JOURNAL_ITEM . " where gl_type = 'ttl' and ref_id = " . $open_inv->fields['id']);
	  $result2 = $db->Execute("select journal_id, post_date from " . TABLE_JOURNAL_MAIN . " where id = " . $open_inv->fields['id']);
	  $total_billed = $result->fields['debit_amount'] - $result->fields['credit_amount'];
	  $post_date = $result2->fields['post_date'];
	  $result = $db->Execute("select sum(debit_amount) as debits, sum(credit_amount) as credits 
	    from " . TABLE_JOURNAL_ITEM . " where so_po_item_ref_id = '" . $open_inv->fields['id'] . "' and gl_type in ('pmt', 'chk')");
	  $total_paid = $result->fields['credits'] - $result->fields['debits'];
	  $balance = $total_billed - $total_paid;
	  if ($type == 'v') $balance = -$balance;
	  // start the placement in aging array
	  if ($post_date < $due_date) $past_due += $balance;
	  if ($post_date < $late_90) {
		$output['balance_90'] += $balance;
	    $total_outstanding += $balance;
	  } elseif ($post_date < $late_60) {
		$output['balance_60'] += $balance;
	    $total_outstanding += $balance;
	  } elseif ($post_date < $late_30) {
		$output['balance_30'] += $balance;
	    $total_outstanding += $balance;
	  } elseif ($post_date <= $today) {
		$output['balance_0']  += $balance;
	    $total_outstanding += $balance;
	  } // else it's in the future
	  $open_inv->MoveNext();
	}
	$output['total']        = $total_outstanding;
	$output['past_due']     = $past_due;
	$output['credit_limit'] = $credit_limit;
	$output['terms_lang']   = gen_terms_to_language($special_terms, false, ($type=='v'?'AP':'AR'));
	return $output;
  }

?>