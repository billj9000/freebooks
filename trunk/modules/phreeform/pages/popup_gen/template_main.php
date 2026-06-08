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
//  Path: /modules/phreeform/pages/popup_gen/template_main.php
//
echo html_form('popup_gen', FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'gID')));
echo html_hidden_field('action',  '') . chr(10);
echo html_hidden_field('title', $report->title) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['print']['params']  = 'onclick="submitToDo(\'exp_pdf\', true)"';
$toolbar->icon_list['delete']['show']   = false;
if ($report->reporttype == 'rpt') {
  $toolbar->icon_list['save']['params'] = 'onclick="entrySave();"';
  $toolbar->icon_list['export_html']    = array(
    'show'   => true,
    'icon'   => 'mimetypes/text-html.png',
    'params' => 'onclick="submitToDo(\'exp_html\', true)"',
    'text'   => TEXT_GENERATE_HTML,
    'order'  => '9',
  );
  $toolbar->add_icon('export_csv', 'onclick="submitToDo(\'exp_csv\', true)"', $order = 10);
  $toolbar->icon_list['export_xml'] = array(
    'show'   => true,
    'icon'   => 'mimetypes/text-x-script.png',
    'params' => 'onclick="submitToDo(\'exp_xml\', true)"',
    'text'   => 'export xml',
    'order'  => '12',
  );
  $toolbar->add_icon('copy', 'onclick="querySaveAs()"', $order = 13);
} else {
  $toolbar->icon_list['save']['show'] = false;
}
$toolbar->add_help('11.02');
echo $toolbar->build_toolbar();
?>
<h1 class="mb-2 px-2">
<?php if (count(is_array($r_list) ? $r_list : []) > 1) {
  echo PHREEFORM_GROUP . $title;
} else {
  echo ($report->reporttype == 'frm' ? TEXT_FORM : TEXT_REPORT) . ': ' . $report->title;
} ?>
</h1>

<!-- Delivery method -->
<div class="d-flex justify-content-center mb-2">
<div class="d-inline-flex flex-column">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_DELIVERY_METHOD; ?></div>
  <div class="card-body py-2">
    <div class="d-flex gap-4">
      <label class="d-flex align-items-center gap-1">
        <?php echo html_radio_field('delivery_method', 'I', ($delivery_method == 'I') ? true : false, '', 'class="form-check-input" onclick="hideEmail();"', false); ?>
        <?php echo TEXT_BROWSER; ?>
      </label>
      <label class="d-flex align-items-center gap-1">
        <?php echo html_radio_field('delivery_method', 'D', ($delivery_method == 'D') ? true : false, '', 'class="form-check-input" onclick="hideEmail();"', false); ?>
        <?php echo TEXT_DOWNLOAD; ?>
      </label>
      <label class="d-flex align-items-center gap-1">
        <?php echo html_radio_field('delivery_method', 'S', ($delivery_method == 'S') ? true : false, '', 'class="form-check-input" onclick="hideEmail();"', false); ?>
        <?php echo TEXT_EMAIL; ?>
      </label>
    </div>
  </div>
</div>
</div>
</div>

<!-- Email details (shown only when Email delivery is selected) -->
<div id="rpt_email" style="display:none">
<div class="d-flex justify-content-center mb-2">
<div class="d-inline-flex flex-column">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_EMAIL; ?></div>
  <div class="card-body py-2">
    <div style="display:grid;grid-template-columns:max-content 1fr max-content 1fr;align-items:center;gap:0.3rem 0.5rem">
      <label class="text-end small"><?php echo TEXT_SENDER_NAME; ?></label>
      <?php echo html_input_field('from_name',  $from_name,  'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_EMAIL; ?></label>
      <?php echo html_input_field('from_email', $from_email, 'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_RECEPIENT_NAME; ?></label>
      <?php echo html_input_field('to_name',  $to_name,  'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_EMAIL; ?></label>
      <?php echo html_input_field('to_email', $to_email, 'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_CC_NAME; ?></label>
      <?php echo html_input_field('cc_name',  $cc_name,  'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_EMAIL; ?></label>
      <?php echo html_input_field('cc_email', $cc_email, 'class="form-control form-control-sm"'); ?>
      <label class="text-end small"><?php echo TEXT_MESSAGE_SUBJECT; ?></label>
      <div style="grid-column:2/-1"><?php echo html_input_field('message_subject', $message_subject, 'class="form-control form-control-sm"'); ?></div>
    </div>
    <div class="mt-2">
      <label class="form-label small mb-1"><?php echo TEXT_MESSAGE_BODY; ?></label>
      <?php echo html_htmlarea_field('message_body', '60', '8', $message_body); ?>
    </div>
  </div>
</div>
</div>
</div>
</div>

<?php if (count(is_array($r_list) ? $r_list : []) > 1) { ?>
<!-- Multiple report selector -->
<div class="d-flex justify-content-center mb-2">
<div class="d-inline-flex flex-column">
<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo GEN_HEADING_PLEASE_SELECT; ?></div>
  <div class="card-body py-2">
    <?php foreach ($r_list as $value) { ?>
    <div class="d-flex align-items-center gap-1">
      <?php echo html_radio_field('rID', $value['id'], false, '', 'class="form-check-input" onchange="fetchEmailMsg()"', false); ?>
      <span><?php echo $value['text']; ?></span>
    </div>
    <?php } ?>
  </div>
</div>
</div>
</div>

<?php } elseif (!$rID) {
  echo '<p class="px-2">' . PHREEFORM_NO_DOCUMENTS . '</p>';
} else {
  echo html_hidden_field('rID', $rID) . chr(10);
?>
<!-- Report criteria / field / page tabs -->
<div id="gentabs" class="mx-2">
  <ul class="nav nav-tabs fb-tabs" role="tablist">
    <?php
      $first_tab = true;
      foreach ($tab_list as $key => $value) {
        $active = $first_tab ? ' active' : '';
        echo '<li class="nav-item">'
           . '<button class="nav-link' . $active . '" data-bs-toggle="tab" data-bs-target="#tab_' . $key . '" type="button">'
           . htmlspecialchars($value) . '</button></li>' . chr(10);
        $first_tab = false;
      }
    ?>
  </ul>
  <div class="tab-content fb-tab-content">

    <!-- Criteria tab -->
    <div id="tab_crit" class="tab-pane">
      <div class="d-flex justify-content-center mt-2">
      <div class="d-inline-flex flex-column">
      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo TEXT_CRITERIA_SETUP; ?></div>
        <div class="card-body p-0">
          <div id="rpt_body">
            <table class="pkg-table table table-borderless table-sm mb-0" style="border-collapse:collapse">
              <tbody>
                <?php if ($report->datelist <> '') {
                  if ($report->datelist == 'z') { ?>
                    <tr>
                      <td><?php echo TEXT_PERIOD; ?></td>
                      <td colspan="3"><?php echo html_pull_down_menu('period', gen_get_period_pull_down(false), CURRENT_ACCOUNTING_PERIOD, 'class="form-select form-select-sm" style="width:auto"'); ?></td>
                    </tr>
                  <?php } else { ?>
                    <tr>
                      <th class="table-secondary">&nbsp;</th>
                      <th class="table-secondary">&nbsp;</th>
                      <th class="table-secondary text-center"><?php echo TEXT_FROM; ?></th>
                      <th class="table-secondary text-center"><?php echo TEXT_TO; ?></th>
                    </tr>
                    <tr>
                      <td><?php echo TEXT_DATE; ?></td>
                      <td><?php echo html_pull_down_menu('datedefault', gen_build_pull_down($ValidDateChoices), $DateArray[0], 'class="form-select form-select-sm" style="width:auto"'); ?></td>
                      <td><?php echo html_calendar_field($cal_from); ?></td>
                      <td><?php echo html_calendar_field($cal_to); ?></td>
                    </tr>
                  <?php }
                }
                if ($report->reporttype == 'rpt' && $report->grouplist <> '') { ?>
                <tr>
                  <td><?php echo TEXT_GROUP; ?></td>
                  <td colspan="3">
                    <?php echo html_pull_down_menu('defgroup', $group_list, $group_default, 'class="form-select form-select-sm" style="width:auto"'); ?>
                    <?php echo ' ' . TEXT_GROUP_PAGE_BREAK . ' ' . html_checkbox_field('grpbreak', '1', $group_break); ?>
                  </td>
                </tr>
                <?php }
                if ($report->reporttype == 'rpt' && $report->sortlist <> '') { ?>
                <tr>
                  <td><?php echo TEXT_SORT; ?></td>
                  <td colspan="3"><?php echo html_pull_down_menu('defsort', $sort_list, $sort_default, 'class="form-select form-select-sm" style="width:auto"'); ?></td>
                </tr>
                <?php }
                if ($report->reporttype == 'rpt') { ?>
                <tr>
                  <td><?php echo TEXT_TRUNC; ?></td>
                  <td colspan="3">
                    <label class="d-inline-flex align-items-center gap-1 me-2">
                      <?php echo html_radio_field('deftrunc', '0', ($report->truncate <> '1') ? true : false); ?>
                      <?php echo TEXT_NO; ?>
                    </label>
                    <label class="d-inline-flex align-items-center gap-1">
                      <?php echo html_radio_field('deftrunc', '1', ($report->truncate == '1') ? true : false); ?>
                      <?php echo TEXT_YES; ?>
                    </label>
                  </td>
                </tr>
                <?php }
                if ($report->filterlist <> '') { ?>
                  <tr>
                    <th class="table-secondary"><?php echo TEXT_FILTER; ?></th>
                    <th class="table-secondary"><?php echo TEXT_TYPE; ?></th>
                    <th class="table-secondary"><?php echo TEXT_FROM; ?></th>
                    <th class="table-secondary"><?php echo TEXT_TO; ?></th>
                  </tr>
                  <?php foreach ($report->filterlist as $key => $LineItem) {
                    $CritBlocks  = explode(':', $CritChoices[$LineItem->type]);
                    $numInputs   = array_shift($CritBlocks);
                    $filter_choices = array();
                    foreach ($CritBlocks as $value) {
                      $filter_choices[] = array('id' => $value, 'text' => constant('TEXT_' . $value));
                    }
                    if ($LineItem->visible) {
                      $field_0 = html_pull_down_menu('defcritsel' . $key, $filter_choices, $LineItem->default, 'class="form-select form-select-sm" style="width:auto"');
                      $field_1 = html_input_field('fromvalue' . $key, $LineItem->min_val, 'class="form-control form-control-sm" maxlength="20"');
                      $field_2 = html_input_field('tovalue'   . $key, $LineItem->max_val, 'class="form-control form-control-sm" maxlength="20"');
                    } else {
                      $field_0 = html_hidden_field('defcritsel' . $key, $LineItem->default ? $LineItem->default : $CritBlocks[0]) . chr(10);
                      $field_1 = html_hidden_field('fromvalue'  . $key, $LineItem->min_val);
                      $field_2 = html_hidden_field('tovalue'    . $key, $LineItem->max_val);
                    }
                    echo '<tr' . ($LineItem->visible ? '' : ' style="display:none"') . '>' . chr(10);
                    echo '<td>' . $LineItem->description . '</td>' . chr(10);
                    echo '<td>' . $field_0 . '</td>' . chr(10);
                    echo '<td>' . ($numInputs >= 1 ? $field_1 : '&nbsp;') . '</td>' . chr(10);
                    echo '<td>' . ($numInputs == 2 ? $field_2 : '&nbsp;') . '</td>' . chr(10);
                    echo '</tr>' . chr(10);
                  }
                } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      </div>
      </div>
    </div><!-- #tab_crit -->

    <?php if ($report->reporttype == 'rpt') { ?>
    <!-- Fields tab -->
    <div id="tab_field" class="tab-pane">
      <div class="d-flex justify-content-center mt-2">
      <div class="d-inline-flex gap-1 align-items-start">
        <div class="card fb-card">
          <div class="card-header py-1 fb-widget-header" id="fieldListHeading"><?php echo TEXT_FIELD_LIST; ?></div>
          <div class="card-body p-0">
            <table id="field_setup" class="pkg-table table table-borderless table-sm mb-0" style="border-collapse:collapse">
              <thead class="table-dark">
                <tr>
                  <th><?php echo PHREEFORM_TBLFNAME; ?></th>
                  <th><?php echo PHREEFORM_DISPNAME; ?></th>
                  <th><?php echo TEXT_COLUMN . '<br>' . TEXT_BREAK; ?></th>
                  <th><?php echo TEXT_COLUMN . '<br>' . TEXT_WIDTH; ?></th>
                  <th><?php echo TEXT_TOTAL  . '<br>' . TEXT_WIDTH; ?></th>
                  <th><?php echo TEXT_SHOW   . '<br>' . TEXT_FIELD; ?></th>
                  <th><?php echo PHREEFORM_TEXTPROC; ?></th>
                  <th><?php echo TEXT_TOTAL  . '<br>' . TEXT_COLUMN; ?></th>
                  <th><?php echo TEXT_ALIGN; ?></th>
                  <th><?php echo TEXT_ACTION; ?></th>
                </tr>
              </thead>
              <tbody>
              <?php for ($i = 0; $i < count(is_array($report->fieldlist) ? $report->fieldlist : []); $i++) { ?>
                <tr>
                  <td><?php echo html_combo_box('fld_fld[]', CreateSpecialDropDown($report), $report->fieldlist[$i]->fieldname, '', '220px', '', 'fld_combo_' . $i); ?></td>
                  <td><?php echo html_input_field('fld_desc[]', $report->fieldlist[$i]->description, 'size="20" maxlength="25"'); ?></td>
                  <td><?php echo html_pull_down_menu('fld_brk[]',  $nyChoice,   $report->fieldlist[$i]->columnbreak, 'onchange="calculateWidth()"'); ?></td>
                  <td><?php echo html_input_field('fld_clmn[]', ($report->fieldlist[$i]->columnwidth ? $report->fieldlist[$i]->columnwidth : PF_DEFAULT_COLUMN_WIDTH), 'size="4" maxlength="3" onchange="calculateWidth()"'); ?></td>
                  <td class="text-center">&nbsp;</td>
                  <td><?php echo html_pull_down_menu('fld_vis[]',  $nyChoice,   $report->fieldlist[$i]->visible, 'onchange="calculateWidth()"'); ?></td>
                  <td><?php echo html_pull_down_menu('fld_proc[]', $pFields,    $report->fieldlist[$i]->processing); ?></td>
                  <td><?php echo html_pull_down_menu('fld_tot[]',  $nyChoice,   $report->fieldlist[$i]->total); ?></td>
                  <td><?php echo html_pull_down_menu('fld_algn[]', $kFontAlign, $report->fieldlist[$i]->align); ?></td>
                  <td class="text-center text-nowrap">
                    <?php
                      echo html_icon('actions/view-fullscreen.png',   TEXT_MOVE,   'small', 'style="cursor:move"', '', '', 'move_fld_' . $i) . chr(10);
                      echo html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . TEXT_DELETE_ENTRY . '\')) rowAction(\'field_setup\', \'delete\'); calculateWidth();"');
                    ?>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="pt-5">
          <?php echo html_icon('actions/list-add.png', TEXT_ADD, 'small', 'onclick="rowAction(\'field_setup\', \'add\')"'); ?>
        </div>
      </div>
      </div>
    </div><!-- #tab_field -->

    <!-- Page setup tab -->
    <div id="tab_page" class="tab-pane">
      <div class="d-flex justify-content-center mt-2">
      <div class="d-inline-flex flex-column">
      <div class="card fb-card">
        <div class="card-header py-1 fb-widget-header"><?php echo PHREEFORM_PGLAYOUT; ?></div>
        <div class="card-body p-0">
          <table class="pkg-table table table-borderless table-sm mb-0" style="border-collapse:collapse">
            <tbody>
              <tr>
                <td colspan="4" class="text-center">
                  <?php echo TEXT_PAPER . ' ' . html_pull_down_menu('papersize', gen_build_pull_down($PaperSizes), $report->page->size, 'onchange="calculateWidth()"'); ?>
                </td>
                <td colspan="4" class="text-center">
                  <?php echo TEXT_ORIEN . ' '; ?>
                  <?php echo html_radio_field('paperorientation', 'P', ($report->page->orientation == 'P') ? true : false, '', 'onchange="calculateWidth()"') . ' ' . TEXT_PORTRAIT; ?>
                  &nbsp;
                  <?php echo html_radio_field('paperorientation', 'L', ($report->page->orientation == 'L') ? true : false, '', 'onchange="calculateWidth()"') . ' ' . TEXT_LANDSCAPE; ?>
                </td>
              </tr>
              <tr><th colspan="8" class="table-secondary"><?php echo PHREEFORM_PGMARGIN; ?></th></tr>
              <tr>
                <td colspan="2" class="text-center"><?php echo TEXT_TOP    . ' ' . html_input_field('margintop',    $report->page->margin->top,    'size="5" maxlength="3"') . ' ' . TEXT_MM; ?></td>
                <td colspan="2" class="text-center"><?php echo TEXT_BOTTOM . ' ' . html_input_field('marginbottom', $report->page->margin->bottom, 'size="5" maxlength="3"') . ' ' . TEXT_MM; ?></td>
                <td colspan="2" class="text-center"><?php echo TEXT_LEFT   . ' ' . html_input_field('marginleft',   $report->page->margin->left,   'size="5" maxlength="3" onchange="calculateWidth()"') . ' ' . TEXT_MM; ?></td>
                <td colspan="2" class="text-center"><?php echo TEXT_RIGHT  . ' ' . html_input_field('marginright',  $report->page->margin->right,  'size="5" maxlength="3" onchange="calculateWidth()"') . ' ' . TEXT_MM; ?></td>
              </tr>
              <tr><th colspan="8" class="table-secondary"><?php echo PHREEFORM_PGHEADER; ?></th></tr>
              <tr>
                <td colspan="3">&nbsp;</td>
                <td class="text-center"><?php echo TEXT_SHOW; ?></td>
                <td class="text-center"><?php echo TEXT_FONT; ?></td>
                <td class="text-center"><?php echo TEXT_SIZE; ?></td>
                <td class="text-center"><?php echo TEXT_COLOR; ?></td>
                <td class="text-center"><?php echo TEXT_ALIGN; ?></td>
              </tr>
              <tr>
                <td colspan="3"><?php echo TEXT_PGCOYNM; ?></td>
                <td class="text-center"><?php echo html_checkbox_field('coynameshow', '1', ($report->page->heading->show == '1') ? true : false); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('coynamefont',  $kFonts,      $report->page->heading->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('coynamesize',  $kFontSizes,  $report->page->heading->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('coynamecolor', $kFontColors, $report->page->heading->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('coynamealign', $kFontAlign,  $report->page->heading->align); ?></td>
              </tr>
              <tr>
                <td colspan="3"><?php echo PHREEFORM_PGTITL1 . ' ' . html_input_field('title1desc', $report->page->title1->text, 'size="30" maxlength="50"'); ?></td>
                <td class="text-center"><?php echo html_checkbox_field('title1show', '1', ($report->page->title1->show == '1') ? true : false); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title1font',  $kFonts,      $report->page->title1->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title1size',  $kFontSizes,  $report->page->title1->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title1color', $kFontColors, $report->page->title1->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title1align', $kFontAlign,  $report->page->title1->align); ?></td>
              </tr>
              <tr>
                <td colspan="3"><?php echo PHREEFORM_PGTITL2 . ' ' . html_input_field('title2desc', $report->page->title2->text, 'size="30" maxlength="50"'); ?></td>
                <td class="text-center"><?php echo html_checkbox_field('title2show', '1', ($report->page->title2->show == '1') ? true : false); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title2font',  $kFonts,      $report->page->title2->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title2size',  $kFontSizes,  $report->page->title2->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title2color', $kFontColors, $report->page->title2->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('title2align', $kFontAlign,  $report->page->title2->align); ?></td>
              </tr>
              <tr>
                <td colspan="4"><?php echo PHREEFORM_PGFILDESC; ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('filterfont',  $kFonts,      $report->page->filter->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('filtersize',  $kFontSizes,  $report->page->filter->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('filtercolor', $kFontColors, $report->page->filter->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('filteralign', $kFontAlign,  $report->page->filter->align); ?></td>
              </tr>
              <tr>
                <td colspan="4"><?php echo PHREEFORM_RPTDATA; ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('datafont',    $kFonts,      $report->page->data->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('datasize',    $kFontSizes,  $report->page->data->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('datacolor',   $kFontColors, $report->page->data->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('dataalign',   $kFontAlign,  $report->page->data->align); ?></td>
              </tr>
              <tr>
                <td colspan="4"><?php echo PHREEFORM_TOTALS; ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('totalsfont',  $kFonts,      $report->page->totals->font); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('totalssize',  $kFontSizes,  $report->page->totals->size); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('totalscolor', $kFontColors, $report->page->totals->color); ?></td>
                <td class="text-center"><?php echo html_pull_down_menu('totalsalign', $kFontAlign,  $report->page->totals->align); ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      </div>
      </div>
    </div><!-- #tab_page -->
    <?php } ?>

  </div><!-- .tab-content -->
</div><!-- #gentabs -->
<?php } ?>
</form>
