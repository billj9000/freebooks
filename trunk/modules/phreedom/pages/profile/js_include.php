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
//  Path: /modules/phreedom/pages/profile/js_include.php
//
?>
<script>
function init() {}
function check_form() { return true; }

function updateColors() {
  var theme = document.getElementById('theme').value;
  $.ajax({
    type: 'GET',
    url: 'index.php?module=phreedom&page=ajax&op=phreedom&action=pull_colors&theme=' + encodeURIComponent(theme),
    dataType: 'xml',
    success: function(xml) {
      var menuSel   = document.getElementById('menu');
      var colorsSel = document.getElementById('colors');
      while (menuSel.options.length)   menuSel.remove(0);
      while (colorsSel.options.length) colorsSel.remove(0);
      $(xml).find('menu').each(function(i) {
        var opt = new Option($(this).find('text').text(), $(this).find('id').text());
        menuSel.options.add(opt);
      });
      $(xml).find('color').each(function(i) {
        var opt = new Option($(this).find('text').text(), $(this).find('id').text());
        colorsSel.options.add(opt);
      });
    }
  });
}
</script>
