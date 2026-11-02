<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
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
//  Path: /themes/default/toolbar_labelled.php
//
// Toolbar buttons that show their icon at 16px and their label, for a colour
// scheme whose toolbar.php includes this file. What it is given, and what page
// scripts need from it, is listed in themes/default/toolbar.php. Save, or New
// where there is no Save, is drawn as the primary button (.fb-tb-primary); Help
// sits at the right-hand end (.fb-tb-end). The scheme's theme.css styles .fb-tb-btn.
$primary = isset($toolbar_icons['save']) ? 'save' : (isset($toolbar_icons['new']) ? 'new' : '');
echo '<div id="tb_main_' . $toolbar_id . '" class="fb-toolbar-section fb-toolbar-labelled">' . "\n";
foreach ($toolbar_icons as $name => $icon) {
  // The 16px copy of an icon where the set has one; otherwise the page's size, drawn at 16px.
  $src = DIR_WS_ICONS . '16x16/' . $icon['icon'];
  if (!is_file(DIR_FS_ADMIN . $src)) $src = DIR_WS_ICONS . ($toolbar_size == 'medium' ? '22x22/' : ($toolbar_size == 'small' ? '16x16/' : '32x32/')) . $icon['icon'];
  // Labels come from the language files and may already carry entities.
  $label = htmlspecialchars(strip_tags($icon['text']), ENT_QUOTES, 'UTF-8', false);
  $class = 'fb-tb-btn' . ($name == $primary ? ' fb-tb-primary' : '') . ($name == 'help' ? ' fb-tb-end' : '');
  echo '<button type="button" id="tb_icon_' . $name . '" class="' . $class . '" title="' . $label . '" ' . $icon['params'] . '>'
     . '<img src="' . $src . '" alt="" width="16" height="16">'
     . '<span>' . $label . '</span></button>' . "\n";
}
echo '</div>' . "\n";
