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
//  Path: /modules/phreeform/pages/admin/template_tab_general.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'general' ? ' show active' : ''); ?>" id="tab_general" role="tabpanel">
  <div class="fb-card mb-3">
    <div class="card-header py-1 fb-widget-header text-center"><?php echo PF_ADMIN_CONFIG_INFO; ?></div>
    <div class="card-body p-2">
      <table class="table table-borderless table-sm mb-0" style="width:auto">
        <tbody>
          <tr>
            <td><?php echo PF_DEFAULT_COLUMN_WIDTH_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_column_width', $_POST['pf_default_column_width'] ? $_POST['pf_default_column_width'] : PF_DEFAULT_COLUMN_WIDTH, 'class="form-control form-control-sm" size="4" maxlength="4"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_MARGIN_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_margin', $_POST['pf_default_margin'] ? $_POST['pf_default_margin'] : PF_DEFAULT_MARGIN, 'class="form-control form-control-sm" size="4" maxlength="4"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_TITLE1_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_title1', $_POST['pf_default_title1'] ? $_POST['pf_default_title1'] : PF_DEFAULT_TITLE1, 'class="form-control form-control-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_TITLE2_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_title2', $_POST['pf_default_title2'] ? $_POST['pf_default_title2'] : PF_DEFAULT_TITLE2, 'class="form-control form-control-sm" size="40"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_PAPERSIZE_TEXT; ?></td>
            <td><?php echo html_pull_down_menu('pf_default_papersize', gen_build_pull_down($PaperSizes), $_POST['pf_default_papersize'] ? $_POST['pf_default_papersize'] : PF_DEFAULT_PAPERSIZE, 'class="form-select form-select-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_ORIENTATION_TEXT; ?></td>
            <td><?php echo html_pull_down_menu('pf_default_orientation', gen_build_pull_down($PaperOrientation), $_POST['pf_default_orientation'] ? $_POST['pf_default_orientation'] : PF_DEFAULT_ORIENTATION, 'class="form-select form-select-sm"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_TRIM_LENGTH_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_trim_length', $_POST['pf_default_trim_length'] ? $_POST['pf_default_trim_length'] : PF_DEFAULT_TRIM_LENGTH, 'class="form-control form-control-sm" size="3"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PF_DEFAULT_ROWSPACE_TEXT; ?></td>
            <td><?php echo html_input_field('pf_default_rowspace', $_POST['pf_default_rowspace'] ? $_POST['pf_default_rowspace'] : PF_DEFAULT_ROWSPACE, 'class="form-control form-control-sm" size="3" maxlength="2"'); ?></td>
          </tr>
          <tr>
            <td><?php echo PDF_APP_TEXT; ?></td>
            <td><?php echo html_pull_down_menu('pdf_app', $pdf_choices, $_POST['pdf_app'] ? $_POST['pdf_app'] : PDF_APP, 'class="form-select form-select-sm"'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
