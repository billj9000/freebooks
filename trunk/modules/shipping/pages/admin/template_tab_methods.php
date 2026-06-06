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
//  Path: /modules/shipping/pages/admin/template_tab_methods.php
//
?>
<div class="tab-pane fade<?php echo ($active_tab === 'methods' ? ' show active' : ''); ?>" id="tab_methods" role="tabpanel">
<table class="pkg-table table table-borderless table-hover">
  <thead class="table-dark">
    <tr>
      <th colspan="2"><?php echo TEXT_SHIPPING_MODULES_AVAILABLE; ?></th>
      <th class="text-center"><?php echo TEXT_SORT_ORDER; ?></th>
      <th class="text-center"><?php echo TEXT_ACTION; ?></th>
    </tr>
  </thead>
  <tbody>
<?php
  $odd = true;
  if (!empty($methods)) foreach ($methods as $method) {
    $installed = defined('MODULE_SHIPPING_' . strtoupper($method) . '_STATUS');
    if (file_exists(DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.png')) {
      $logo = DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.png';
    } elseif (file_exists(DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.jpg')) {
      $logo = DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.jpg';
    } elseif (file_exists(DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.gif')) {
      $logo = DIR_WS_MODULES . 'shipping/methods/' . $method . '/images/logo.gif';
    } else {
      $logo = DIR_WS_MODULES . 'shipping/images/no_logo.png';
    }
    echo '  <tr class="' . ($odd ? 'odd' : 'even') . '">' . chr(10);
    echo '    <td>' . html_image($logo, constant('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_TITLE'), '', '32', '') . '</td>' . chr(10);
    $desc_class = $installed ? ' class="table-success"' : '';
    echo '    <td' . $desc_class . '>' .
      constant('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_TITLE') . ' - ' .
      constant('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_DESCRIPTION') .
      '</td>' . chr(10);
    if (!$installed) {
      echo '    <td class="text-center">&nbsp;</td>' . chr(10);
      if ($security_level > 1) echo '    <td class="text-center"><button type="button" class="btn btn-primary btn-sm" onclick="submitToDo(\'install_' . $method . '\')">' . TEXT_INSTALL . '</button></td>' . chr(10);
      echo '  </tr>' . chr(10);
    } else {
      echo '    <td class="text-center">' . constant('MODULE_SHIPPING_' . strtoupper($method) . '_SORT_ORDER') . '</td>' . chr(10);
      echo '    <td class="text-center text-nowrap">' . chr(10);
      if ($security_level > 3) echo '      <button type="button" class="btn btn-primary btn-sm" onclick="if (confirm(\'' . TEXT_REMOVE_MESSAGE . '\')) submitToDo(\'remove_' . $method . '\')">' . TEXT_REMOVE . '</button>' . chr(10);
      echo html_icon('categories/preferences-system.png', TEXT_PROPERTIES, 'medium', 'onclick="toggleProperties(\'prop_' . $method . '\')"') . chr(10);
      echo '    </td>' . chr(10);
      echo '  </tr>' . chr(10);
      require_once($method_dir . $method . '/' . $method . '.php');
      $properties = new $method();
      echo '  <tr id="prop_' . $method . '" style="display:none"><td colspan="4"><table class="table table-sm table-borderless mb-0" style="width:100%">' . chr(10);
      if (defined('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_INTRODUCTION')) {
        echo '<tr><td colspan="2">' . constant('MODULE_SHIPPING_' . strtoupper($method) . '_TEXT_INTRODUCTION') . '</td></tr>';
      }
      $keys = $properties->keys();
      $custom_method = (method_exists($properties, 'configure')) ? true : false;
      foreach ($keys as $value) {
        echo '<tr><td colspan="2">' . constant($value['key'] . '_DESC') . '</td><td class="text-nowrap">';
        if ($custom_method) { echo $properties->configure($value['key']); }
        else { echo html_input_field(strtolower($value['key']), constant($value['key']), $value['properties']); }
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
