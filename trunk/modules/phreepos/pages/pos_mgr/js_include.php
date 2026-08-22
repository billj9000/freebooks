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
//  Path: /modules/phreepos/pages/pos_mgr/js_include.php
//
?>
<script type="text/javascript">
<?php
echo js_calendar_init($cal_date);
?>
<!--
function init() {
  document.getElementById('search_text').focus();
  document.getElementById('search_text').select();
}

function check_form() {
  return true;
}

function printOrder(id) {
  var printWin = window.open("<?php echo gen_print_form_url(POPUP_FORM_TYPE, 'journal_main.id', 'EQUAL'); ?>"+id,"reportFilter","width=700,height=550,resizable=1,scrollbars=1,top=150,left=200");
  printWin.focus();
}

$(document).ready(function(){
	$("#search_date").change(function(){
		submitToDo('search',false);
	});  

});
// -->
</script>