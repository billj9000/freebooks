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
//  Path: /modules/payment/pages/admin/template_tab_methods.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'methods' ? ' show active' : ''); ?>" id="tab_methods" role="tabpanel">
<table class="pkg-table table table-borderless table-hover">
  <thead class="table-dark">
    <tr>
      <th colspan="2"><?php echo TEXT_PAYMENT_MODULES_AVAILABLE; ?></th>
      <th class="text-center"><?php echo TEXT_SORT_ORDER; ?></th>
      <th class="text-center"><?php echo TEXT_ACTION; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
$odd = true;
if (!empty($methods)) foreach ($methods as $method) {
  require_once($method_dir . $method . '/' . $method . '.php');
  $properties = new $method;
  $installed = defined('MODULE_PAYMENT_' . strtoupper($properties->code) . '_STATUS');
  if (file_exists(DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.png')) {
    $logo = DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.png';
  } elseif (file_exists(DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.jpg')) {
    $logo = DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.jpg';
  } elseif (file_exists(DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.gif')) {
    $logo = DIR_WS_MODULES . 'payment/methods/' . $properties->code . '/images/logo.gif';
  } else {
    $logo = DIR_WS_MODULES . 'payment/images/no_logo.png';
  }
  $desc_class = $installed ? ' class="table-success"' : '';
  echo '  <tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
  echo '    <td>' . html_image($logo, $properties->title, '', '32', '') . '</td>' . chr(10);
  echo '    <td' . $desc_class . '>' . $properties->title . ' - ' . $properties->description . '</td>' . chr(10);
  if (!$installed) {
    echo '    <td class="text-center">&nbsp;</td>' . chr(10);
    if ($security_level > 1) echo '    <td class="text-center"><button type="button" class="btn btn-primary btn-sm" onclick="submitToDo(\'install_' . $properties->code . '\')">' . TEXT_INSTALL . '</button></td>' . chr(10);
    echo '  </tr>' . chr(10);
  } else {
    echo '    <td class="text-center">' . $properties->getsortorder() . '</td>' . chr(10);
    echo '    <td class="text-center text-nowrap">' . chr(10);
    if ($security_level > 3) echo '      <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm(\'' . TEXT_REMOVE_MESSAGE . '\')) submitToDo(\'remove_' . $properties->code . '\')">' . TEXT_REMOVE . '</button>' . chr(10);
    echo html_icon('categories/preferences-system.png', TEXT_PROPERTIES, 'medium', 'onclick="toggleProperties(\'prop_' . $properties->code . '\')"') . chr(10);
    echo '    </td>' . chr(10);
    echo '  </tr>' . chr(10);
    echo '  <tr id="prop_' . $properties->code . '" style="display:none"><td colspan="4"><table class="table table-sm table-borderless mb-0" style="width:100%">' . chr(10);
    if (defined('MODULE_PAYMENT_' . strtoupper($properties->code) . '_TEXT_INTRODUCTION')) {
      echo '<tr><td colspan="2">' . constant('MODULE_PAYMENT_' . strtoupper($properties->code) . '_TEXT_INTRODUCTION') . '</td></tr>';
    }
    foreach ($properties->keys() as $value) {
      echo '<tr><td colspan="2">' . $value['text'] . '</td><td>';
      echo $properties->configure($value['key']);
      echo '</td></tr>';
    }
    echo '<tr><td colspan="3"><hr /></td></tr>';
    echo '  </table></td></tr>' . chr(10);
  }
  $odd = !$odd;
}
?>
  </tbody>
</table>
</div>
