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
//  Path: /modules/phreebooks/pages/journal/template_main.php
//
echo html_form('journal', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
echo html_hidden_field('action',          '') . chr(10);
echo html_hidden_field('id',              $cInfo->id) . chr(10);
echo html_hidden_field('recur_id',        $cInfo->recur_id        ? $cInfo->recur_id        : 0) . chr(10);
echo html_hidden_field('recur_frequency', $cInfo->recur_frequency ? $cInfo->recur_frequency : 0) . chr(10);
if (!ENABLE_MULTI_BRANCH) echo html_hidden_field('store_id', '0') . chr(10);
// toolbar
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=phreebooks&amp;page=status&amp;jID=' . JOURNAL_ID . '&amp;list=1', 'SSL') . '\'"';
$toolbar->icon_list['open']['params']   = 'onclick="OpenGLList()"';
$toolbar->icon_list['delete']['params'] = 'onclick="if (confirm(\'' . GL_DELETE_ALERT . '\')) submitToDo(\'delete\')"';
if ($security_level < 4) $toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
if ($security_level < 2) $toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new',  'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"', 2);
$toolbar->add_icon('copy', 'onclick="verifyCopy()"', 9);
if (!$cInfo->id) $toolbar->add_icon('recur', 'onclick="OpenRecurList(this)"', 10);
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('07.06.02');
echo $toolbar->build_toolbar();
?>
<h1><?php echo GL_ENTRY_TITLE; ?></h1>

<!-- Entry details card -->
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo GL_ENTRY_TITLE; ?></div>
  <div class="card-body py-2 px-2">
    <div class="d-flex flex-wrap gap-3 align-items-end">
      <div>
        <label class="d-block mb-0" style="font-size:11px"><?php echo TEXT_REFERENCE; ?></label>
        <?php echo html_input_field('purchase_invoice_id', $cInfo->purchase_invoice_id, 'class="form-control form-control-sm" maxlength="20"'); ?>
      </div>
      <div>
        <label class="d-block mb-0" style="font-size:11px"><?php echo TEXT_POST_DATE; ?></label>
        <?php echo html_calendar_field($cal_gl); ?>
      </div>
      <div class="form-check mb-1">
        <?php echo html_checkbox_field('auto_complete', '1', '1', '', 'class="form-check-input"'); ?>
        <label class="form-check-label" style="font-size:11px"><?php echo TEXT_AUTO_COMPLETE; ?></label>
      </div>
      <?php if (ENABLE_MULTI_BRANCH) { ?>
      <div>
        <label class="d-block mb-0" style="font-size:11px"><?php echo GEN_STORE_ID; ?></label>
        <?php echo html_pull_down_menu('store_id', gen_get_store_ids(),
              $cInfo->store_id ? $cInfo->store_id : $_SESSION['admin_prefs']['def_store_id'],
              'class="form-select form-select-sm"'); ?>
      </div>
      <?php } ?>
    </div>
  </div>
</div>

<!-- Journal lines card -->
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo GL_JOURNAL_LINES; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th style="width:4%"></th>
            <th style="width:18%" class="text-center"><?php echo TEXT_GL_ACCOUNT; ?></th>
            <th class="text-center"><?php echo TEXT_DESCRIPTION; ?></th>
            <th style="width:13%" class="text-end"><?php echo TEXT_DEBIT_AMOUNT; ?></th>
            <th style="width:13%" class="text-end"><?php echo TEXT_CREDIT_AMOUNT; ?></th>
          </tr>
        </thead>
        <tbody id="item_table">
          <?php
          if (!isset($cInfo->id_1)) {
            echo '<script>addGLRow();</script>' . chr(10);
          } else {
            $i = 1;
            while (true) {
              $id     = 'id_'     . $i;
              $acct   = 'acct_'   . $i;
              $desc   = 'desc_'   . $i;
              $debit  = 'debit_'  . $i;
              $credit = 'credit_' . $i;
              if (!isset($cInfo->$id)) break;
              echo '<tr>' . chr(10);
              echo '<td class="text-center">';
              echo html_hidden_field($id, $cInfo->$id);
              echo '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="if(confirm(\'' . GL_DELETE_ALERT . '\')) removeGLRow(' . $i . ');">&times;</button>';
              echo '</td>' . chr(10);
              echo '<td>' . html_pull_down_menu($acct, $gl_array_list, $cInfo->$acct, 'class="form-select form-select-sm"') . '</td>' . chr(10);
              echo '<td>' . html_input_field($desc, $cInfo->$desc, 'class="form-control form-control-sm" maxlength="64"') . '</td>' . chr(10);
              echo '<td>' . html_input_field($debit, $cInfo->$debit, 'class="form-control form-control-sm text-end" maxlength="20" onchange="formatRow(' . $i . ', \'d\')"') . '</td>' . chr(10);
              echo '<td>' . html_input_field($credit, $cInfo->$credit, 'class="form-control form-control-sm text-end" maxlength="20" onchange="formatRow(' . $i . ', \'c\')"') . '</td>' . chr(10);
              echo '</tr>' . chr(10);
              echo '<tr class="table-warning">' . chr(10);
              echo '<td colspan="3">&nbsp;</td>' . chr(10);
              echo '<td colspan="2" id="msg_' . $i . '">&nbsp;</td>' . chr(10);
              echo '</tr>' . chr(10);
              $i++;
            }
          }
          ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo GL_TOTALS; ?></td>
            <td><?php echo html_input_field('debit_total',  '0', 'readonly class="form-control form-control-sm text-end"'); ?></td>
            <td><?php echo html_input_field('credit_total', '0', 'readonly class="form-control form-control-sm text-end"'); ?></td>
          </tr>
          <tr>
            <td colspan="3" class="text-end pe-2" style="font-size:11px;vertical-align:middle"><?php echo GL_OUT_OF_BALANCE; ?></td>
            <td></td>
            <td><?php echo html_input_field('balance_total', '0', 'readonly class="form-control form-control-sm text-end"'); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="px-2 pb-2 pt-1">
      <button type="button" class="btn btn-primary btn-sm" onclick="addGLRow()">+ <?php echo TEXT_ADD; ?></button>
    </div>
  </div>
</div>

<!-- Attachment card -->
<div class="card fb-card mb-2">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_ATTACHMENT; ?></div>
  <div class="card-body py-2 px-2">
    <div><?php echo TEXT_SELECT_FILE_TO_ATTACH; ?> <?php echo html_file_field('file_name'); ?></div>
    <div id="show_attach" style="display:none" class="mt-1">
      <?php echo html_checkbox_field('rm_attach', '1', isset($cInfo->rm_attach) ? (bool)$cInfo->rm_attach : false, '', ''); ?>
      <?php echo TEXT_DELETE_ATTACHMENT; ?>
      <button type="button" class="btn btn-secondary btn-sm ms-2" onclick="downloadAttachment()"><?php echo TEXT_DOWNLOAD_ATTACHMENT; ?></button>
    </div>
  </div>
</div>
</form>
