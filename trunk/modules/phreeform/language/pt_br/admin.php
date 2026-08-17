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
 // Arquivo Tradução Idioma  Phreedom 
 // Generated: 2013-10-22 05:28:46
 // Module/Method: phreeform
 // ISO Language: pt_br
 // Version: 3.6
 // Path: /modules/phreeform/language/pt_br/admin.php

define('MODULE_PHREEFORM_TITLE','Módulo Relatórios');
define('MODULE_PHREEFORM_DESCRIPTION','O Módulo Relatórios contém todas as ferramentas e formulários para imprimir relatórios em  formato PDF ou HTML. <b>ATENÇÃO: Este é um módulo central e não deve ser removido!</b>');
define('BOX_PHREEFORM_MODULE_ADM','PhreeForm Administração');
define('PB_CONVERT_REPORTS','Converter Relatórios .txt para PhreeForm');
define('PB_CONVERT_SAVE_ERROR','Aconteceu um erro ao salvar o relatório convertido: %s');
define('PB_CONVERT_SUCCESS','Conversão bem sucedida de %s relatórios e formulários. Se houve algum erra durante a conversão, ele deve ter sido mostrado em uma mensagem anterior.');
define('PF_DEFAULT_COLUMN_WIDTH_TEXT','Especifica a largura padrão para largura de colunas do relatório em mm (padrão: 25)');
define('PF_DEFAULT_MARGIN_TEXT','Especifica a margem de página padrão para uso em relatórios e formulários em mm (padrão: 8)');
define('PF_DEFAULT_TITLE1_TEXT','Especifica o texto de título padrão para imprimir como cabeçalho nos relatórios (padrão: %reportname%)');
define('PF_DEFAULT_TITLE2_TEXT','Especifica o texto de título padrão para imprimir como cabeçalho 2 nos relatórios  (padrão: Relatóreio Gerado em %date%)');
define('PF_DEFAULT_PAPERSIZE_TEXT','Especifica o tamanho de página padrão para uso em relatórios e formulários (padrão: Carta)');
define('PHREEFORM_TOOLS_RESTAMP_TITLE','Re-stamp Paper Size');
define('PHREEFORM_TOOLS_RESTAMP_DESC','Re-target every stored report and form onto the current default paper size (%s). Element positions are not moved, so a form drawn for a wider page will sit closer to the right edge on a narrower one. Any file whose page setting cannot be located is left untouched and counted.');
define('PHREEFORM_TOOLS_RESTAMP_SUBMIT','Re-stamp all reports and forms');
define('PHREEFORM_TOOLS_RESTAMP_CONFIRM','This rewrites the page size of every stored report and form. Continue?');
define('PHREEFORM_RESTAMP_DONE','Re-stamped %d report(s) and form(s) onto %s.');
define('PHREEFORM_RESTAMP_SKIPPED','%d file(s) were left untouched because their page setting could not be located.');
define('PF_DEFAULT_ORIENTATION_TEXT','Especifica a orientação de página padrão para uso em relatórios e formulários (padrão: Retrato)');
define('PF_DEFAULT_TRIM_LENGTH_TEXT','Especifica a abreviaçãode nomes de relatórios e formulários ao listar em formato de diretório (padrão: 25)');
define('PF_DEFAULT_ROWSPACE_TEXT','Especifica a separação entre as linhas de cabeçalho para relatórios (padrão: 2)');
define('PDF_APP_TEXT','Especifica a aplicação geredora de PDF padrão . Atenção: TCPDF é exigido para UTF-8 e geração de código de barras.');
define('PHREEFORM_TOOLS_REBUILD_TITLE','PhreeForm Verificação Estrutura / Reconstruir');
define('PHREEFORM_TOOLS_REBUILD_DESC','Este ferramente verifica e reconstrói a estrutura de relatórios e formulários. Vai recarregar a estrutura da pasta, certificar-se de que não há relatórios órfãos e limpar qualquer entrada na tabela que não tenha um relatório/formulário associado a ela');
define('PHREEFORM_TOOLS_REBUILD_SUBMIT','Iniciar Verificação Estrutura / Reconstruir');
define('PHREEFORM_TOOLS_REBUILD_SUCCESS','Reconstrução de tabela de relatórios bem sucedida. O número de relatórios reconstruido foi %s. %s relatórios órfãos foram colocados na pasta Miscellaneous.');
define('PHREEFORM_TOOLS_REBUILD_SKIPPED','%s file(s) could not be read as either a report or a form and were left where they are. They are not in the rebuilt list.');

?>
