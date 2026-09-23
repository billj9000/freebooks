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
//  Path: /modules/contacts/classes/type/b.php
//  branches
require_once(DIR_FS_MODULES . 'contacts/classes/contacts.php');

class b extends contacts{	
	public $security_token = SECURITY_ID_MAINTAIN_BRANCH;
	public $help		       = '07.08.04';
	public $address_types  = array('bm', 'bs', 'bb', 'im');
	public $type            = 'b';
	
	public function __construct(){
		$this->page_title_new = sprintf(BOX_TEXT_NEW_TITLE, TEXT_BRANCH);
		$this->tab_list[] = array('file'=>'template_notes',		'tag'=>'notes',    'order'=>40, 'text'=>TEXT_NOTES);
		$this->tab_list[] = array('file'=>'template_b_general',	'tag'=>'general',  'order'=> 1, 'text'=>TEXT_GENERAL);
		parent::__construct();
	}
	
  	function delete($id = 0) {
	  	global $db; 
	  	if ( $this->id == '' ) $this->id = $id;
	  	$this->id = (int)$this->id;
		// error check
		return parent::do_delete();
  	}
}
?>