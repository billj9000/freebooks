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
//  Path: /modules/contacts/pages/admin/template_tab_postcode_lookup.php
//
?>
<div id="tab_postcode_lookup">
  <fieldset>
    <table>
      <tr><th colspan="2"><?php echo TEXT_POSTCODE_LOOKUP; ?></th></tr>
      <tr>
        <td><?php echo ACT_POSTCODE_LOOKUP_STATUS_DESC; ?></td>
        <td><?php echo html_pull_down_menu('postcode_lookup_status', $sel_yes_no,
          isset($_POST['postcode_lookup_status']) ? $_POST['postcode_lookup_status']
            : (defined('POSTCODE_LOOKUP_STATUS') ? POSTCODE_LOOKUP_STATUS : '0')); ?></td>
      </tr>
      <tr>
        <td><?php echo ACT_POSTCODE_LOOKUP_PROVIDER_DESC; ?></td>
        <td><?php echo html_pull_down_menu('postcode_lookup_provider', [
              ['id' => 'fetchify',     'text' => 'Fetchify'],
              ['id' => 'vehicle_data', 'text' => 'Vehicle Data'],
            ],
            isset($_POST['postcode_lookup_provider']) ? $_POST['postcode_lookup_provider']
              : (defined('POSTCODE_LOOKUP_PROVIDER') ? POSTCODE_LOOKUP_PROVIDER : '')); ?></td>
      </tr>
      <tr>
        <td><?php echo ACT_POSTCODE_LOOKUP_API_KEY_DESC; ?></td>
        <td><?php echo html_input_field('postcode_lookup_api_key',
          isset($_POST['postcode_lookup_api_key']) ? $_POST['postcode_lookup_api_key']
            : (defined('POSTCODE_LOOKUP_API_KEY') ? POSTCODE_LOOKUP_API_KEY : ''),
          'size="49" maxlength="128"'); ?></td>
      </tr>
      <tr>
        <td><?php echo ACT_POSTCODE_LOOKUP_COUNTRY_DESC; ?></td>
        <td><?php echo html_input_field('postcode_lookup_country',
          isset($_POST['postcode_lookup_country']) ? $_POST['postcode_lookup_country']
            : (defined('POSTCODE_LOOKUP_COUNTRY') ? POSTCODE_LOOKUP_COUNTRY : 'GB'),
          'size="4" maxlength="3"'); ?></td>
      </tr>
    </table>
  </fieldset>
</div>
