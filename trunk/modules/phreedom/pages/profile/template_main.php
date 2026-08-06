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
//  Path: /modules/phreedom/pages/profile/template_main.php
//
echo html_form('my_profile', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '') . chr(10);

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_help('06.03');
echo $toolbar->build_toolbar();

$cur_theme  = $prefs['theme']  ?: DEFAULT_THEME;
$cur_colors = $prefs['colors'] ?: DEFAULT_COLORS;
$cur_hover  = !empty($prefs['menu_hover']);
?>
<h1><?php echo PAGE_TITLE; ?></h1>

<div class="d-flex justify-content-center">
  <div class="card" style="min-width:400px;max-width:540px;width:100%">
    <div class="card-header py-2 fb-widget-header"><?php echo TEXT_THEMES_COLORS_TITLE; ?></div>
    <div class="card-body">
      <p class="mb-3" style="font-size:11px"><?php echo TEXT_THEMES_COLORS_DESC; ?></p>

      <div class="mb-2 row align-items-center">
        <label class="col-sm-5 col-form-label col-form-label-sm fw-bold"><?php echo TEXT_LOGIN_THEME; ?></label>
        <div class="col-sm-7">
          <?php echo html_pull_down_menu('theme', load_theme_dropdown(), $cur_theme,
            'class="form-select form-select-sm" onchange="updateColors()"'); ?>
        </div>
      </div>

      <div class="mb-2 row align-items-center">
        <label class="col-sm-5 col-form-label col-form-label-sm fw-bold"><?php echo TEXT_LOGIN_COLORS; ?></label>
        <div class="col-sm-7">
          <?php echo html_pull_down_menu('colors', load_colors_dropdown(), $cur_colors,
            'class="form-select form-select-sm"'); ?>
        </div>
      </div>

      <div class="mb-0 row align-items-center">
        <label class="col-sm-5 col-form-label col-form-label-sm fw-bold"><?php echo TEXT_MENU_OPEN_BEHAVIOUR; ?></label>
        <div class="col-sm-7">
          <div class="d-flex gap-3">
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="radio" name="menu_hover" id="menu_click" value=""
                     <?php echo !$cur_hover ? 'checked' : ''; ?>>
              <label class="form-check-label" for="menu_click"><?php echo TEXT_MENU_CLICK; ?></label>
            </div>
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="radio" name="menu_hover" id="menu_hover_opt" value="1"
                     <?php echo $cur_hover ? 'checked' : ''; ?>>
              <label class="form-check-label" for="menu_hover_opt"><?php echo TEXT_MENU_HOVER; ?></label>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php if (wa_enabled()): ?>
<div class="d-flex justify-content-center mt-3">
  <div class="card" style="min-width:400px;max-width:540px;width:100%">
    <div class="card-header py-2 fb-widget-header"><?php echo TEXT_PASSKEYS_TITLE; ?></div>
    <div class="card-body">
      <p class="mb-3" style="font-size:11px"><?php echo TEXT_PASSKEYS_DESC; ?></p>

      <table class="table table-borderless table-hover" style="font-size:11px">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_PASSKEY_LABEL; ?></th>
            <th><?php echo TEXT_PASSKEY_ADDED; ?></th>
            <th><?php echo TEXT_PASSKEY_LAST_USED; ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody id="pk-tbody">
          <?php if (!$pk_credentials): ?>
          <tr id="pk-empty-row"><td colspan="4" class="text-muted"><?php echo TEXT_PASSKEY_NONE; ?></td></tr>
          <?php else: foreach ($pk_credentials as $pk_index => $cred): $pk_row_class = $pk_index % 2 ? 'odd' : 'even'; ?>
          <?php require(DIR_FS_MODULES . 'phreedom/includes/webauthn/row.php'); endforeach; endif; ?>
        </tbody>
      </table>

      <button type="button" id="pk-add-btn" class="btn btn-primary btn-sm"><?php echo TEXT_PASSKEY_ADD; ?></button>
      <div id="pk-msg" class="mt-2" style="font-size:11px"></div>
    </div>
  </div>
</div>
<?php endif; ?>

</form>
