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
//  Path: /modules/translator/pages/main/template_edit.php
//
echo html_form('translator', FILENAME_DEFAULT, gen_get_all_get_params(array('action'))) . chr(10);
echo html_hidden_field('action', '')   . chr(10);
echo html_hidden_field('mod',  $mod) . chr(10);
echo html_hidden_field('lang', $lang). chr(10);
echo html_hidden_field('ver',  $ver) . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action', 'f0', 'f1', 'f2', 'f3')), 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['save']['params']   = 'onclick="submitToDo(\'save\')"';
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo BOX_TRANSLATOR_MODULE; ?></h1>
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
  <?php echo html_button_field('export', TEXT_CHECK_ALL, 'onclick="checkAllBoxes()"'); ?>
  <span><?php echo TEXT_FILTERS; ?></span>
  <span><?php echo TEXT_MODULE; ?></span><?php echo html_pull_down_menu('f0', $sel_modules, $mod); ?>
  <span><?php echo TEXT_LANGUAGE; ?></span><?php echo html_pull_down_menu('f1', $sel_language, $lang); ?>
  <span><?php echo TEXT_VERSION; ?></span><?php echo html_pull_down_menu('f2', $sel_version, $ver); ?>
  <span><?php echo TEXT_TRANSLATED; ?></span><?php echo html_pull_down_menu('f3', $sel_translated, $f3); ?>
  <?php echo html_button_field('apply', TEXT_APPLY, 'onclick="submitToDo(\'filter\')"'); ?>
</div>
<table class="table table-sm table-hover table-borderless w-100">
 <thead class="table-dark">
  <tr><?php echo $list_header; ?></tr>
 </thead>
 <tbody>
<?php
  $i = 0;
  $odd = true;
  while (!$query_result->EOF) {
    $const = $query_result->fields['defined_constant'];
    $cID   = $query_result->fields['id'] . ':' . $query_result->fields['defined_constant'];
    if (!defined($const)) define($const, $const);
    $box_size = strlen($query_result->fields['translation']);
?>
  <tr class="<?php echo $odd?'odd':'even'; ?>" style="cursor:pointer">
    <td align="center">
      <?php echo html_icon('mimetypes/x-office-address-book.png', $query_result->fields['pathtofile'], 'small', '') . chr(10); ?>
      <?php echo html_checkbox_field('d:'.$cID, '1', $query_result->fields['translated'] ? true : false); ?>
    </td>
<?php if ($box_size < 64) { ?>
    <td><?php echo html_input_field('t:'.$cID, htmlspecialchars($query_result->fields['translation']), 'size="64"'); ?></td>
    <td><?php echo html_input_field('x:'.$i,   htmlspecialchars(constant($const)), 'readonly="readonly" size="64"'); ?></td>
<?php } else { ?>
    <td><?php echo html_textarea_field('t:'.$cID, 46, 3, htmlspecialchars($query_result->fields['translation'])); ?></td>
    <td><?php echo html_textarea_field('x:'.$i,   46, 3, htmlspecialchars(constant($const)), 'readonly="readonly"'); ?></td>
<?php } ?>
    <td><?php echo html_input_field('c:'.$i, $const, 'readonly="readonly" size="48"'); ?></td>
  </tr>
<?php
      $i++;
      $query_result->MoveNext();
      $odd = !$odd;
    }
?>
 </tbody>
</table>
</form>
