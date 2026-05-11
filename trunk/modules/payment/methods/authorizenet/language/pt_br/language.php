<?php
// +-----------------------------------------------------------------+
// Arquivo Tradução Idioma  Phreedom 
// Generated: 2013-10-22 05:28:45
// Module/Method: payment-authorizenet
// ISO Language: pt_br
// Version: 3.6
// +-----------------------------------------------------------------+
// Path: /modules/payment/methods/authorizenet/language/pt_br/language.php

define('MODULE_PAYMENT_AUTHORIZENET_TEXT_TITLE','Authorize.net');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_DESCRIPTION','Aceitar pagamentos cartão de crédito via gateway de pagamentos Authorize.net ');
define('MODULE_PAYMENT_AUTHORIZENET_LOGIN_DESC','ID de acesso a API utilizada pelo serviço Authorize.net');
define('MODULE_PAYMENT_AUTHORIZENET_TXNKEY_DESC','Chave de Transação utilizada para encriptar dados TP<br />(Veja seu Authorizenet Account->Security Settings->API Login ID e Transaction Key para detalhes.)');
define('MODULE_PAYMENT_AUTHORIZENET_MD5HASH_DESC','Chave de encriptação utilizada para validar dados de transação recebidos (MAX 20 CARACTERES, exatamente como digitado nas propriedades de conta Authorize.net ). Ou deixe em branco.');
define('MODULE_PAYMENT_AUTHORIZENET_TESTMODE_DESC','Modo de transação para utilizar no processamento de ordens.');
define('MODULE_PAYMENT_AUTHORIZENET_AUTHORIZATION_TYPE_DESC','Você quer que as transações de cartão de crédito submetidas sejam somente autorizadas ou autorizadas e capturadas?');
define('MODULE_PAYMENT_AUTHORIZENET_USE_CVV_DESC','Você quer perguntar ao cliente sobre o número CVV do cartão');
define('MODULE_PAYMENT_AUTHORIZENET_DEBUGGING_DESC','Você quer habilitar o modo DEBUG?  Um registro completp de transações falhas pode ser enviada via email para oproprietário.');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_AUTHENTICITY_WARNING','ATENÇÃO: Problema de segurança hash. Por favor contate o proprietário da loja imediatamente. Sua ordem não foi completamente autorizada.');
define('MODULE_PAYMENT_AUTHORIZENET_ENTRY_REFUND_BUTTON_TEXT','Aceitar Estorno');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_REFUND_CONFIRM_ERROR','Erro: Você solicitou um Estorno mas não selecionou a caixa de Confirmação.');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_INVALID_REFUND_AMOUNT','Erro: Você solicitou um Estorno mas digitou um valor inválido.');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_CC_NUM_REQUIRED_ERROR','Erro: Você solicitou um Estorno mas não digitou os quatro últimos números do cartão de crédito.');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_REFUND_INITIATED','Estorno Iniciado. Transação ID: %s - Código Autorização: %s');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_TRANS_ID_REQUIRED_ERROR','Erro: Você deve especificar o ID da Transação.');
define('MODULE_PAYMENT_AUTHORIZENET_ENTRY_VOID_BUTTON_TEXT','Aceitar Cancelamento');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_VOID_CONFIRM_ERROR','Erro: Você solicitou um Cancelamento mas não selecionou a caixa de Confirmação.');
define('MODULE_PAYMENT_AUTHORIZENET_TEXT_VOID_INITIATED','Cancelamento Iniciado. Transação ID: %s - Códiog Autorização: %s ');

?>
