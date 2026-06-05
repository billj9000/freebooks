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
//  Path: /modules/phreebooks/pages/budget/template_main.php
//
echo html_form('budget', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
if ($security_level < 2) $toolbar->icon_list['save']['show'] = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_icon('new',  'onclick="if (confirm(\'' . GL_CLEAR_ACTUAL_CONFIRM . '\')) submitToDo(\'clear_fy\')"', $order = 20);
$toolbar->icon_list['new']['text']      = TEXT_BUDGET_CLEAR_HINT;
$toolbar->add_icon('copy', 'onclick="if (confirm(\'' . GL_COPY_ACTUAL_CONFIRM  . '\')) submitToDo(\'copy_fy\')"',  $order = 25);
$toolbar->icon_list['copy']['text']     = GL_BUDGET_COPY_HINT;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help();
echo $toolbar->build_toolbar();
?>
<h1><?php echo GL_BUDGET_HEADING_TITLE; ?></h1>
<p class="text-center"><?php echo GL_BUDGET_INTRO_TEXT; ?></p>

<!-- Account and fiscal year selector -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-body py-2 px-3" style="font-size:11px">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="d-flex align-items-center gap-2">
        <span><?php echo TEXT_GL_ACCOUNT; ?></span>
        <?php echo html_pull_down_menu('gl_acct', gen_coa_pull_down(), $gl_acct, 'style="font-size:11px;width:280px" onchange="submit()"'); ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fetchAcct()" title="<?php echo TEXT_LOAD_ACCT_PRIOR; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/></svg></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyBudget('clear')" title="<?php echo TEXT_CLEAR; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg></button>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span><?php echo GL_FISCAL_YEAR; ?></span>
        <?php echo html_pull_down_menu('fy', get_fiscal_year_pulldown(), $fy, 'style="font-size:11px;width:80px" onchange="submit()"'); ?>
      </div>
    </div>
  </div>
</div>

<!-- Budget table -->
<div class="card fb-card mb-2 mx-auto" style="width:fit-content">
  <div class="card-header py-1 fb-widget-header"><?php echo GL_BUDGET_HEADING_TITLE; ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_PERIOD_DATES; ?></th>
            <th class="text-end"><?php echo TEXT_PRIOR_FY; ?></th>
            <th></th>
            <th class="text-end"><?php echo TEXT_BUDGET; ?></th>
            <th></th>
            <th class="text-end"><?php echo TEXT_NEXT_FY; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 0;
          $odd = true;
          foreach ($fy_array as $value) {
          ?>
          <tr class="<?php echo $odd ? 'odd' : 'even'; ?>">
            <td class="text-center">
              <?php echo $value['period']; ?>
              <?php echo html_hidden_field('id_' . $i, $value['id']); ?>
            </td>
            <td><?php echo html_input_field('prior_'  . $i, $currencies->format($value['prior']),  'readonly class="form-control form-control-sm text-end" style="width:110px"'); ?></td>
            <td></td>
            <td><?php echo html_input_field('budget_' . $i, $currencies->format($value['budget']), 'class="form-control form-control-sm text-end" style="width:110px" onchange="calculateTotal()"'); ?></td>
            <td></td>
            <td><?php echo html_input_field('next_'   . $i, $currencies->format($value['next']),   'readonly class="form-control form-control-sm text-end" style="width:110px"'); ?></td>
          </tr>
          <?php
          $i++;
          $odd = !$odd;
          } ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <th class="text-end"><?php echo TEXT_TOTAL; ?></th>
            <th><?php echo html_input_field('prior', '', 'readonly class="form-control form-control-sm text-end" style="width:110px"'); ?></th>
            <th class="text-center">
              <button type="button" class="btn btn-sm btn-outline-light" onclick="copyBudget('prior')" title="<?php echo GL_TEXT_COPY_PRIOR; ?>">&#8594;</button>
            </th>
            <th>
              <div class="d-flex align-items-center gap-1">
                <?php echo html_input_field('total', '', 'class="form-control form-control-sm text-end" style="width:110px"'); ?>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="copyBudget('spread')" title="<?php echo GL_TEXT_ALLOCATE; ?>">&#247;</button>
              </div>
            </th>
            <th class="text-center">
              <button type="button" class="btn btn-sm btn-outline-light" onclick="copyBudget('next')" title="<?php echo GL_TEXT_COPY_NEXT; ?>">&#8592;</button>
            </th>
            <th><?php echo html_input_field('next', '', 'readonly class="form-control form-control-sm text-end" style="width:110px"'); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
</form>
