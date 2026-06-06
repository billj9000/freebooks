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
//  Path: /modules/phreedom/pages/encryption/template_main.php
//
echo html_form('encrypt', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (!empty($extra_toolbar_buttons)) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('');
echo $toolbar->build_toolbar();
?>
<h1><?php echo BOX_HEADING_ENCRYPTION; ?></h1>

<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_SET_ENCRYPTION_KEY; ?></div>
  <p><?php echo GEN_ENCRYPTION_GEN_INFO; ?></p>
  <p class="fw-bold mb-1"><?php echo GEN_ENCRYPTION_COMP_TYPE; ?></p>
  <table class="table table-borderless table-sm mb-0" style="width:auto">
    <tbody>
      <tr>
        <td><?php echo GEN_ENCRYPTION_KEY; ?></td>
        <td><?php echo html_password_field('enc_key', ''); ?></td>
      </tr>
      <tr>
        <td><?php echo GEN_ENCRYPTION_KEY_CONFIRM; ?></td>
        <td><?php echo html_password_field('enc_key_confirm', ''); ?></td>
      </tr>
      <tr>
        <td colspan="2" class="text-end">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('save')"><?php echo GEN_ADM_TOOLS_BTN_SAVE; ?></button>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<?php if ($security_level > 2) { ?>
<div class="fb-fieldset mb-3">
  <div class="fb-fieldset-legend"><?php echo GEN_ADM_TOOLS_SET_ENCRYPTION_PW; ?></div>
  <p><?php echo GEN_ADM_TOOLS_SET_ENCRYPTION_PW_DESC; ?></p>
  <table class="table table-borderless table-sm mb-0" style="width:auto">
    <tbody>
      <tr>
        <td><?php echo GEN_ADM_TOOLS_ENCRYPT_OLD_PW; ?></td>
        <td><?php echo html_password_field('old_encrypt_key'); ?></td>
      </tr>
      <tr>
        <td><?php echo GEN_ADM_TOOLS_ENCRYPT_PW; ?></td>
        <td><?php echo html_password_field('new_encrypt_key'); ?></td>
      </tr>
      <tr>
        <td><?php echo GEN_ADM_TOOLS_ENCRYPT_PW_CONFIRM; ?></td>
        <td><?php echo html_password_field('new_encrypt_confirm'); ?></td>
      </tr>
      <tr>
        <td colspan="2" class="text-end">
          <button type="button" class="btn btn-primary btn-sm" onclick="submitToDo('encrypt_key')"><?php echo GEN_ADM_TOOLS_BTN_SAVE; ?></button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
<?php } ?>
</form>
