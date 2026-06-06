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
//  Path: /modules/phreepos/pages/admin/template_tab_stats.php
//
?>
<div class="tab-pane fade" id="tab_stats" role="tabpanel">
<?php
  if (!empty($install->tables)) {
    echo "  <div class=\"fb-fieldset mb-2 mt-2\">\n";
    echo "    <div class=\"fb-fieldset-legend\">" . TEXT_TABLE_STATS . "</div>\n";
    echo "    <table class=\"pkg-table table table-borderless table-hover table-sm\" style=\"width:100%\">\n";
    echo "      <thead class=\"table-dark\">\n";
    echo "        <tr>\n";
    echo "          <th>" . TEXT_TABLE . "</th>\n";
    echo "          <th>" . TEXT_ENGINE . "</th>\n";
    echo "          <th class=\"text-center\">" . TEXT_NUM_ROWS . "</th>\n";
    echo "          <th>" . TEXT_COLLATION . "</th>\n";
    echo "          <th class=\"text-center\">" . TEXT_SIZE . "</th>\n";
    echo "          <th class=\"text-center\">" . TEXT_NEXT_ID . "</th>\n";
    echo "        </tr>\n";
    echo "      </thead>\n";
    echo "      <tbody>\n";
    foreach ($install->tables as $tablename => $tablesql) {
      $result = $db->Execute("SHOW TABLE STATUS LIKE '" . $tablename . "'");
      echo "        <tr>\n";
      echo "          <td>" . $result->fields['Name'] . "</td>\n";
      echo "          <td>" . $result->fields['Engine'] . "</td>\n";
      echo "          <td class=\"text-center\">" . $result->fields['Rows'] . "</td>\n";
      echo "          <td>" . $result->fields['Collation'] . "</td>\n";
      echo "          <td class=\"text-center\">" . ($result->fields['Data_length'] + $result->fields['Index_length']) . "</td>\n";
      echo "          <td class=\"text-center\">" . $result->fields['Auto_increment'] . "</td>\n";
      echo "        </tr>\n";
    }
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
  }
?>
</div>
