<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
//  Path: /modules/contacts/pages/main/template_detail.php
//
echo html_form('contacts', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
// include hidden fields
echo html_hidden_field('action',        '') . chr(10);
echo html_hidden_field('f0',         $f0) . chr(10);
echo html_hidden_field('id',  $cInfo->id) . chr(10);
echo html_hidden_field('rowSeq',      '') . chr(10);
echo html_hidden_field('del_crm_note','') . chr(10);
echo html_hidden_field('payment_id',  '') . chr(10);
// customize the toolbar actions
if ($_REQUEST['action'] == 'properties') {
  $toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
  $toolbar->icon_list['save']['show']     = false;
} else {
  $toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
  if ((!$cInfo->id && $security_level < 2) || ($cInfo->id && $security_level < 3)) {
    $toolbar->icon_list['save']['show']   = false;
  } else {
    $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
  }
}
$toolbar->icon_list['open']['show']       = false;
$toolbar->icon_list['delete']['show']     = false;
$toolbar->icon_list['print']['show']      = false;

// pull in extra toolbar overrides and additions
if (!empty($extra_toolbar_buttons)) {
  foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
}

// add the help file index and build the toolbar
if( !$cInfo->help == '' ) $toolbar->add_help($cInfo->help);
echo $toolbar->build_toolbar(); 
$fields->set_fields_to_display($type);
// Build the page

$custom_path = DIR_FS_MODULES . 'contacts/custom/pages/main/extra_tabs.php';
if (file_exists($custom_path)) { include($custom_path); }

function tab_sort($a, $b) {
  if ($a['order'] == $b['order']) return 0;
  return ($a['order'] > $b['order']) ? 1 : -1;
}
usort($cInfo->tab_list, 'tab_sort');

// Convert extra_tab_li (legacy tab format) to Bootstrap nav buttons
$extra_nav_items = '';
if (!empty($fields->extra_tab_li)) {
  preg_match_all("/<li><a href='#(tab_\d+)'>(.*?)<\/a><\/li>/i", $fields->extra_tab_li, $matches, PREG_SET_ORDER);
  foreach ($matches as $m) {
    $extra_nav_items .= '<li class="nav-item">'
      . '<button class="nav-link" data-bs-toggle="tab" data-bs-target="#' . $m[1] . '" type="button">'
      . $m[2] . '</button></li>' . chr(10);
  }
}

// Add tab-pane class to extra_tab_html divs (legacy tab format uses plain <div id='tab_X'>)
$extra_tab_panes = '';
if (!empty($fields->extra_tab_html)) {
  $extra_tab_panes = preg_replace("/<div id='(tab_\d+)'>/", "<div id='$1' class='tab-pane'>", $fields->extra_tab_html);
}

?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div id="detailtabs">
<ul class="nav nav-tabs fb-tabs" role="tablist">
<?php
  $first_tab = true;
  foreach ($cInfo->tab_list as $value) {
    $active = $first_tab ? ' active' : '';
    echo '<li class="nav-item">'
       . '<button class="nav-link' . $active . '" data-bs-toggle="tab" data-bs-target="#tab_' . $value['tag'] . '" type="button">'
       . htmlspecialchars($value['text']) . '</button></li>' . chr(10);
    $first_tab = false;
  }
  echo $extra_nav_items;
?>
</ul>
<div class="tab-content fb-tab-content">
<?php
foreach ($cInfo->tab_list as $value) {
  if (file_exists(DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php')) {
    include(DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php');
  } else {
    include(DIR_FS_WORKING . 'pages/main/' . $value['file'] . '.php');
  }
}
if (isset($extra_contact_tabs) && is_array($extra_contact_tabs)) {
  foreach ($extra_contact_tabs as $tabs) {
    $file_path = DIR_FS_WORKING . 'custom/pages/main/' . $tabs['tab_filename'] . '.php';
    if (file_exists($file_path)) { require($file_path); }
  }
}
echo $extra_tab_panes;
?>
</div>
</div>
</form>