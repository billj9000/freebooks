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
//  Path: /modules/phreedom/pages/import_export/template_beg_bal.php
//
echo html_form('import_export', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreedom&amp;page=import_export', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save_bb\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('03.04.02');
echo $toolbar->build_toolbar();
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="row">
  <div class="col-lg-8">
    <table class="pkg-table table table-hover table-sm mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_GL_ACCOUNT; ?></th>
          <th><?php echo TEXT_DESCRIPTION; ?></th>
          <th><?php echo TEXT_ACCOUNT_TYPE; ?></th>
          <th class="text-nowrap"><?php echo TEXT_DEBIT_AMOUNT; ?></th>
          <th class="text-nowrap"><?php echo TEXT_CREDIT_AMOUNT; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0;
        foreach ($glEntry->beg_bal as $coa_id => $values) { ?>
        <tr>
          <td class="text-center"><?php echo $coa_id; ?></td>
          <td><?php echo htmlspecialchars($values['desc']); ?></td>
          <td><?php echo $values['type_desc']; ?></td>
          <?php if ($coa_types[$values['type']]['asset']) { ?>
          <td class="text-center"><?php echo html_input_field('coa_value['.$i.']', (($values['beg_bal'] <> 0) ? $currencies->precise($values['beg_bal']) : '0'), 'style="text-align:right" size="13" maxlength="12" onchange="updateBalance()"'); ?></td>
          <td class="table-secondary">&nbsp;</td>
          <?php } else { ?>
          <td class="table-secondary">&nbsp;</td>
          <td class="text-center"><?php echo html_input_field('coa_value['.$i.']', (($values['beg_bal'] <> 0) ? $currencies->precise(-$values['beg_bal']) : '0'), 'style="text-align:right" size="13" maxlength="12" onchange="updateBalance()"'); ?></td>
          <?php } ?>
        </tr>
        <?php $i++;
        } ?>
      </tbody>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="3" class="text-end"><?php echo TEXT_TOTAL; ?></td>
          <td class="text-end"><?php echo html_input_field('debit_total',  '0', 'readonly="readonly" style="text-align:right" size="13"'); ?></td>
          <td class="text-end"><?php echo html_input_field('credit_total', '0', 'readonly="readonly" style="text-align:right" size="13"'); ?></td>
        </tr>
        <tr>
          <td colspan="4" class="text-end"><?php echo GL_OUT_OF_BALANCE; ?></td>
          <td class="text-end"><?php echo html_input_field('balance_total', '0', 'readonly="readonly" style="text-align:right" size="13"'); ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="col-lg-4">
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo GL_BB_IMPORT_INVENTORY; ?></div>
      <p class="small mb-1"><?php echo GL_BB_IMPORT_HELP_MSG; ?></p>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php echo html_file_field('file_name_inv'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_inv')"><?php echo GL_BB_IMPORT_INVENTORY; ?></button>
      </div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo GL_BB_IMPORT_PURCH_ORDERS; ?></div>
      <p class="small mb-1"><?php echo GL_BB_IMPORT_HELP_MSG; ?></p>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php echo html_file_field('file_name_po'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_po')"><?php echo GL_BB_IMPORT_PURCH_ORDERS; ?></button>
      </div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo GL_BB_IMPORT_PAYABLES; ?></div>
      <p class="small mb-1"><?php echo GL_BB_IMPORT_HELP_MSG; ?></p>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php echo html_file_field('file_name_ap'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_ap')"><?php echo GL_BB_IMPORT_PAYABLES; ?></button>
      </div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo GL_BB_IMPORT_SALES_ORDERS; ?></div>
      <p class="small mb-1"><?php echo GL_BB_IMPORT_HELP_MSG; ?></p>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php echo html_file_field('file_name_so'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_so')"><?php echo GL_BB_IMPORT_SALES_ORDERS; ?></button>
      </div>
    </div>
    <div class="fb-fieldset mb-2">
      <div class="fb-fieldset-legend"><?php echo GL_BB_IMPORT_RECEIVABLES; ?></div>
      <p class="small mb-1"><?php echo GL_BB_IMPORT_HELP_MSG; ?></p>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php echo html_file_field('file_name_ar'); ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('import_ar')"><?php echo GL_BB_IMPORT_RECEIVABLES; ?></button>
      </div>
    </div>
  </div>
</div>
</form>
