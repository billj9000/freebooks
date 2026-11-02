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
//  Path: /themes/default/toolbar.php
//
// The buttons of a page toolbar, as icons. toolbar::render_buttons() includes
// this, once per toolbar, with:
//   $toolbar_id     the toolbar's id; a page may have more than one toolbar
//   $toolbar_size   the icon size the page asked for: small, medium or large
//   $toolbar_icons  the buttons to show, in order, keyed by name; each has
//                   'icon' (a path under the icon set, for html_icon()),
//                   'text' (the label) and 'params' (its onclick attribute)
// A scheme's replacement must keep what page scripts rely on:
//  - a <div id="tb_main_<toolbar id>"> whose direct children are the buttons,
//    because removeElement() takes a button out of that div by id;
//  - id="tb_icon_<name>" and the params on the element that is clicked;
//  - hiding a button by its style.visibility and greying it with the class
//    fb-icon-disabled, which scripts set on that same element;
//  - type="button" on a <button>, because many toolbars sit inside a form that
//    any other button would submit.
echo '<div id="tb_main_' . $toolbar_id . '" class="fb-toolbar-section">' . "\n";
foreach ($toolbar_icons as $name => $icon) {
  echo html_icon($icon['icon'], $icon['text'], $toolbar_size, 'id ="tb_icon_' . $name . '" style="cursor:pointer;" ' . $icon['params']) . "\n";
}
echo '</div>' . "\n";
