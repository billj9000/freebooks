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
//  Path: /modules/phreedom/classes/tabs.php
//

#[\AllowDynamicProperties]
class tabs {
    public  $help_path   = '';
    public  $module      = '';
    public  $error       = false;
    public  $extra_buttons = '';
    public  $title       = '';
    
    // The class's own settings, never taken from a posted form.
    const NOT_FROM_FORMS = array('id', 'module', 'help_path', 'title', 'error', 'extra_buttons', 'security_id');

    public function __construct (){
    	foreach ($_POST as $key => $value) {
    	  if (in_array($key, self::NOT_FROM_FORMS, true)) continue;
    	  $this->$key = db_prepare_input($value);
    	}
    	// It goes into SQL unquoted: a whole number, 0 for a new tab.
    	$id = isset($_POST['sID']) ? $_POST['sID'] : ($_GET['sID'] ?? '');
    	$this->id = ctype_digit((string)$id) ? (int)$id : 0;
    }

    public function btn_save($id = '') {
	  	global $db, $messageStack;
		if ($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] < 2) {
		  $messageStack->add(ERROR_NO_PERMISSION,'error');
		  return false;
		}
		$sql_data_array = array(
		  'module_id'   => $this->module,
		  'tab_name'    => $this->tab_name,
		  'description' => $this->description,
		  'sort_order'  => $this->sort_order,
		);
	    if (!$this->id == 0) {
		  db_perform(TABLE_EXTRA_TABS, $sql_data_array, 'update', "id = " . $this->id);
	      gen_add_audit_log(sprintf(EXTRA_TABS_LOG, TEXT_UPDATE), $this->tab_name);
		} else {
		  // Test for duplicates.
		  $result = $db->Execute("select id from " . TABLE_EXTRA_TABS . " where module_id='" . db_input($this->module) . "' and tab_name='" . db_input($this->tab_name) . "'");
		  if ($result->RecordCount() > 0) {
		  	$messageStack->add(EXTRA_TABS_DELETE_ERROR,'error');
		  	$this->error = true;
			return false;
		  }
		  db_perform(TABLE_EXTRA_TABS, $sql_data_array);
		  gen_add_audit_log(sprintf(EXTRA_TABS_LOG, TEXT_ADD), $this->tab_name);
		}
		return true;
	}

    public function btn_delete($id = 0) {
	  	global $db, $messageStack;
		if ($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] < 4) {
		  $messageStack->add(ERROR_NO_PERMISSION,'error');
		  return false;
		}
		$id = (int)$id;
		$result = $db->Execute("SELECT field_name FROM ".TABLE_EXTRA_FIELDS." WHERE tab_id='$id'");
		if ($result->RecordCount() > 0) {
		  $messageStack->add(INV_CATEGORY_CANNOT_DELETE . $result->fields['field_name'], 'error');
		  return false;
		}
		$result = $db->Execute("SELECT tab_name FROM ".TABLE_EXTRA_TABS." WHERE id='$id'");
		$db->Execute("DELETE FROM ".TABLE_EXTRA_TABS." WHERE id=$id");
		gen_add_audit_log(sprintf(EXTRA_TABS_LOG, TEXT_DELETE), $result->fields['tab_name']);
		return true;
	}
	
    public function build_main_html() {
	   global $db, $messageStack;
	   $content = array();
	   $content['thead'] = array(
		  'value' => array(TEXT_TITLE, TEXT_DESCRIPTION, TEXT_SORT_ORDER, TEXT_ACTION),
		  'params'=> 'width="100%" cellspacing="0" cellpadding="1"',
	   );
	   $result = $db->Execute("select id, tab_name, description, sort_order from " . TABLE_EXTRA_TABS . " where module_id='" . db_input($this->module) . "'");
	   $rowCnt = 0;
	   while (!$result->EOF) {
		  $actions = '';
		  if ($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 1) $actions .= html_icon('actions/edit-find-replace.png', TEXT_EDIT,   'small', 'onclick="loadPopUp(\'tabs_edit\', ' . $result->fields['id'] . ')"') . chr(10);
		  if ($_SESSION['admin_security'][SECURITY_ID_CONFIGURATION] > 3) $actions .= html_icon('emblems/emblem-unreadable.png', TEXT_DELETE, 'small', 'onclick="if (confirm(\'' . EXTRA_TABS_DELETE_INTRO . '\')) subjectDelete(\'tabs\', ' . $result->fields['id'] . ')"') . chr(10);
		  $content['tbody'][$rowCnt] = array(
		    array('value' => htmlspecialchars($result->fields['tab_name']),
				  'params'=> 'style="cursor:pointer" onclick="loadPopUp(\'tabs_edit\',\''.$result->fields['id'].'\')"'),
			array('value' => htmlspecialchars($result->fields['description']), 
				  'params'=> 'style="cursor:pointer" onclick="loadPopUp(\'tabs_edit\',\''.$result->fields['id'].'\')"'),
			array('value' => $result->fields['sort_order'],
				  'params'=> 'style="cursor:pointer" onclick="loadPopUp(\'tabs_edit\',\''.$result->fields['id'].'\')"'),
		    array('value' => $actions,
                  'params'=> 'align="right"'),
		   );
	       $result->MoveNext();
	       $rowCnt++;
	   }
	   return html_datatable('tab_table', $content);
	}
	
    public function build_form_html($action, $id = '') {
	   global $db;
	   if ($action <> 'new' && $this->error == false) {
	       $result = $db->Execute("select * from " . TABLE_EXTRA_TABS . " where id = " . $this->id);
	       foreach ($result->fields as $key => $value) $this->$key = $value;
       }
	   $output  = '<table style="border-collapse:collapse;margin-left:auto; margin-right:auto;">' . chr(10);
	   $output .= '  <thead class="table-dark">' . "\n";
	   $output .= '  <tr>' . chr(10);
	   $output .= '    <th colspan="2">' . ($action=='new' ? INV_INFO_HEADING_NEW_CATEGORY : INV_INFO_HEADING_EDIT_CATEGORY) . '</th>' . chr(10);
	   $output .= '  </tr>' . chr(10);
	   $output .= '  </thead>' . "\n";
	   $output .= '  <tbody>' . "\n";
	   $output .= '  <tr>' . chr(10);
	   $output .= '    <td colspan="2">' . ($action=='new' ? EXTRA_TABS_INSERT_INTRO : SETUP_CURR_EDIT_INTRO) . '</td>' . chr(10);
	   $output .= '  </tr>' . chr(10);
	   $output .= '  <tr>' . chr(10);
	   $output .= '    <td>' . TEXT_TAB_NAME . '</td>' . chr(10);
	   $output .= '    <td>' . html_input_field('tab_name', $this->tab_name) . '</td>' . chr(10);
	   $output .= '  </tr>' . chr(10);
	   $output .= '  <tr>' . chr(10);
	   $output .= '    <td>' . TEXT_DESCRIPTION . '</td>' . chr(10);
	   $output .= '    <td>' . html_textarea_field('description', 30, 10, $this->description) . '</td>' . chr(10);
	   $output .= '  </tr>' . chr(10);
	   $output .= '  <tr>' . chr(10);
	   $output .= '    <td>' . TEXT_SORT_ORDER . '</td>' . chr(10);
	   $output .= '    <td>' . html_input_field('sort_order', $this->sort_order) . '</td>' . chr(10);
	   $output .= '  </tr>' . chr(10);
	   $output .= '  </tbody>' . "\n";
	   $output .= '</table>' . chr(10);
	   return $output;
	}
	
}

?>