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
//  Path: /modules/inventory/pages/main/template_detail.php

echo html_form('inventory', FILENAME_DEFAULT, gen_get_all_get_params(['action', 'cID', 'sku', 'add', 'inventory_type']), 'post', 'enctype="multipart/form-data"');
echo html_hidden_field('action', '') . chr(10);
echo html_hidden_field('rowSeq',    $cInfo->id) . chr(10);
echo html_hidden_field('id',        $cInfo->id) . chr(10);
if (isset($cInfo->ms_attr_0)) echo html_hidden_field('ms_attr_0', $cInfo->ms_attr_0) . chr(10);
if (isset($cInfo->ms_attr_1)) echo html_hidden_field('ms_attr_1', $cInfo->ms_attr_1) . chr(10);

if ($_REQUEST['action'] == 'properties') {
  echo html_hidden_field('search_text', '') . chr(10);
  $toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
  $toolbar->icon_list['open']['show']     = false;
  $toolbar->icon_list['delete']['show']   = false;
  $toolbar->icon_list['save']['show']     = false;
  $toolbar->icon_list['print']['show']    = false;
} else {
  $toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(['action', 'page']), 'SSL') . '\'"';
  $toolbar->icon_list['open']['show']     = false;
  if ($security_level > 3 && $cInfo->inventory_type != 'mi' && $cInfo->inventory_type != 'ia'
      && ($cInfo->last_journal_date != '0000-00-00 00:00:00' || $cInfo->last_journal_date != '')) {
    $toolbar->icon_list['delete']['show']   = true;
    $toolbar->icon_list['delete']['params'] = "onclick='if (confirm(\"" . INV_MSG_DELETE_INV_ITEM . "\")) deleteItem({$cInfo->id},\"{$cInfo->inventory_type}\")'";
  } else {
    $toolbar->icon_list['delete']['show'] = false;
  }
  if ($security_level > 2 || $first_entry) {
    $toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
    $toolbar->add_icon('post_next', 'onclick="submitToDo(\'post_next\')"', 11);
    $toolbar->icon_list['post_next']['icon'] = 'actions/go-next.png';
    $toolbar->icon_list['post_next']['text'] = TEXT_SAVE_AND_COPY_TO_NEW;
  } else {
    $toolbar->icon_list['save']['show'] = false;
  }
  $toolbar->icon_list['print']['show'] = false;
  $toolbar->add_help($cInfo->help_path);
}
if (!empty($extra_toolbar_buttons_detail)) {
  foreach ($extra_toolbar_buttons_detail as $key => $value) $toolbar->icon_list[$key] = $value;
}
echo $toolbar->build_toolbar();
$fields->set_fields_to_display($cInfo->inventory_type);

// merge in any extension-injected tabs
if (isset($extra_inventory_tabs) && is_array($extra_inventory_tabs)) {
  foreach ($extra_inventory_tabs as $tab) {
    $cInfo->tab_list[$tab['id']] = ['file' => $tab['filename'], 'tag' => $tab['id'], 'order' => $tab['order'], 'text' => $tab['title']];
  }
}

// sort tabs by order
$temp = [];
foreach ($cInfo->tab_list as $key => $value) $temp[$key] = $value['order'];
array_multisort($temp, SORT_ASC, $cInfo->tab_list);

// Convert $fields->extra_tab_li (jQuery UI format) to Bootstrap nav buttons
$extra_nav_items = '';
if (!empty($fields->extra_tab_li)) {
  preg_match_all("/<li><a href='#(tab_\d+)'>(.*?)<\/a><\/li>/i", $fields->extra_tab_li, $matches, PREG_SET_ORDER);
  foreach ($matches as $m) {
    $extra_nav_items .= '<li class="nav-item" role="presentation">'
      . '<button class="nav-link" id="' . $m[1] . '-tab" data-bs-toggle="tab" data-bs-target="#' . $m[1] . '" type="button" role="tab" aria-controls="' . $m[1] . '" aria-selected="false">'
      . $m[2] . '</button></li>' . chr(10);
  }
}

// Add Bootstrap tab-pane class to extra_tab_html divs (jQuery UI format uses plain <div id='tab_X'>)
$extra_tab_panes = '';
if (!empty($fields->extra_tab_html)) {
  $extra_tab_panes = preg_replace("/<div id='(tab_\d+)'>/", "<div id='$1' class='tab-pane fade'>", $fields->extra_tab_html);
}
?>
<h1 id="heading_title"><?php echo MENU_HEADING_INVENTORY . ' - ' . TEXT_SKU . '# ' . $cInfo->sku . ' (' . $cInfo->description_short . ')'; ?></h1>

<div class="pkg-wrap">

<!-- Bootstrap nav tabs -->
<ul class="nav nav-tabs fb-tabs" id="invTabs" role="tablist">
<?php
$first_tab = true;
foreach ($cInfo->tab_list as $value):
  $tab_id     = 'tab_' . $value['tag'];
  $active_cls = $first_tab ? ' active' : '';
  $aria_sel   = $first_tab ? 'true' : 'false';
?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?php echo $active_cls; ?>" id="<?php echo $tab_id; ?>-tab"
            data-bs-toggle="tab" data-bs-target="#<?php echo $tab_id; ?>"
            type="button" role="tab"
            aria-controls="<?php echo $tab_id; ?>" aria-selected="<?php echo $aria_sel; ?>">
      <?php echo $value['text']; ?>
    </button>
  </li>
<?php
  $first_tab = false;
endforeach;
echo $extra_nav_items;
?>
</ul>

<div class="tab-content fb-tab-content" id="invTabContent">
<?php
$first_tab = true;
foreach ($cInfo->tab_list as $value):
  $tab_id     = 'tab_' . $value['tag'];
  $pane_class = 'tab-pane fade' . ($first_tab ? ' show active' : '');
  $tab_file   = file_exists(DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php')
                  ? DIR_FS_WORKING . 'custom/pages/main/' . $value['file'] . '.php'
                  : DIR_FS_WORKING . 'pages/main/' . $value['file'] . '.php';
?>
  <div class="<?php echo $pane_class; ?>" id="<?php echo $tab_id; ?>" role="tabpanel" aria-labelledby="<?php echo $tab_id; ?>-tab">
    <?php include($tab_file); ?>
  </div>
<?php
  $first_tab = false;
endforeach;
echo $extra_tab_panes;

if (isset($extra_inventory_tabs) && is_array($extra_inventory_tabs)) {
  foreach ($extra_inventory_tabs as $tabs) {
    $file_path = DIR_FS_WORKING . 'custom/pages/main/' . $tabs['tab_filename'] . '.php';
    if (file_exists($file_path)) require($file_path);
  }
}
?>
</div><!-- /tab-content -->

</div><!-- /pkg-wrap -->
</form>
