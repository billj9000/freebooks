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
// +-----------------------------------------------------------------+
// Arquivo Tradução Idioma  Phreedom 
// Generated: 2013-10-22 05:28:45
// Module/Method: payment-moneyorder
// ISO Language: pt_br
// Version: 3.6
// +-----------------------------------------------------------------+
// Path: /modules/payment/methods/moneyorder/language/pt_br/language.php

define('MODULE_PAYMENT_MONEYORDER_TEXT_TITLE','Ordem PAgamento/Cheque');
define('MODULE_PAYMENT_MONEYORDER_TEXT_DESCRIPTION','Pagamentos via cheque, ordem de pagamento e outras formas de pagamento direto.');
define('MODULE_PAYMENT_MONEYORDER_TEXT_INTRODUCTION', 'Por favor, emita seu cheque ou ordem de pagamento nominal a:<br />' . (defined('MODULE_PAYMENT_MONEYORDER_PAYTO') ? MODULE_PAYMENT_MONEYORDER_PAYTO : '') . '<br />Envie seu pagamento para:<br />' . (defined('COMPANY_NAME') ? nl2br(COMPANY_NAME) : ''));
define('MODULE_PAYMENT_MONEYORDER_TEXT_REF_NUM','Número Referência');
define('MODULE_PAYMENT_MONEYORDER_PAYTO_DESC','Fazer Pagamentos para:');

?>
