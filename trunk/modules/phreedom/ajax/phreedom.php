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
//  Path: /modules/phreedom/ajax/phreedom.php
//
/**************   Check user security   *****************************/
$security_level = validate_ajax_user();
/**************  include page specific files    *********************/
/**************   page specific initialization  *************************/
$xml    = NULL;
switch ($_REQUEST['action']) {
	case 'pull_colors':
		$theme = (string)($_GET['theme'] ?? '');
		// It names a directory read and a file included below: an existing theme, by name.
		if (!preg_match('/^[a-z0-9_]+$/i', $theme) || !is_dir(DIR_FS_ADMIN.'themes/'.$theme)) break;
		$contents = scandir(DIR_FS_ADMIN.'themes/'.$theme.'/css/');
		include(DIR_FS_ADMIN.'themes/'.$theme.'/config.php');
		foreach ($contents as $color) {
			if ($color <> '.' && $color <> '..' && is_dir(DIR_FS_ADMIN.DIR_WS_THEMES.'/css/'.$color)) {
				$xml .= '<color>'.chr(10);
				$xml .= xmlEntry('id',   $color);
				$xml .= xmlEntry('text', $color);
				$xml .= '</color>'.chr(10);
			}
		}
		foreach ($theme_menu_options as $key => $value) {
			$xml .= '<menu>'.chr(10);
			$xml .= xmlEntry('id',   $key);
			$xml .= xmlEntry('text', $value);
			$xml .= '</menu>'.chr(10);
		}
		break;
	case 'chart':
		$modID = (string)($_GET['modID'] ?? '');
		// It names a file required below: an existing module, by name.
		if (!preg_match('/^[a-z0-9_]+$/i', $modID) || !is_dir(DIR_FS_MODULES.$modID)) break;
		$fID   = $_GET['fID'];
		$x     = 0;
		$data  = array();
		while (isset($_GET['d'.$x])) {
			$data[$x] = $_GET['d'.$x];
			$x++;
		}
		if (file_exists(DIR_FS_MODULES.$modID.'/functions/'.$modID.'.php')) {
			gen_pull_language($modID);
			require_once(DIR_FS_MODULES.$modID.'/functions/'.$modID.'.php');
			if ($results = get_chart_data($fID, $data)) {
				$xml .= xmlEntry('modID',  $modID);
				$xml .= xmlEntry('type',   $results['type']);
				$xml .= xmlEntry('title',  $results['title']);
				$xml .= xmlEntry('width',  $results['width']);
				$xml .= xmlEntry('height', $results['height']);
				$xml .= xmlEntry('rowCnt', count(is_array($results['data']) ? $results['data'] : []));
				if (!empty($results['data'])) {
				  foreach ($results['data'] as $value) {
				    $xml .= '<chartData>';
				  	$xml .= xmlEntry('string', $value['label']);
					$xml .= xmlEntry('number', $value['value']);
				    $xml .= '</chartData>';
				  }
				} else {
					$xml .= xmlEntry('error', 'No data returned from function call!');
				}
			} else {
				$xml .= xmlEntry('error', 'No data returned from function call!');
				break;
			}
		} else {
			$xml .= xmlEntry('error', 'Could not find module function file to process!');
			break;
		}
		break;

	default: die;
}
echo createXmlHeader() . $xml . createXmlFooter();
die;
?>