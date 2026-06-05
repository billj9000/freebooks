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
//  Path: /modules/inventory/pages/main/template_tab_hist_sr.php
?>
<div class="table-responsive">
  <table class="pkg-table table table-borderless table-hover table-sm mb-0">
    <thead class="table-dark">
      <tr>
        <th><?php echo TEXT_DATE; ?></th>
        <th><?php echo TEXT_INVOICE_NUM; ?></th>
        <th><?php echo TEXT_CUSTOMER; ?></th>
        <th><?php echo TEXT_SERIAL_NUMBER; ?></th>
        <th><?php echo TEXT_PAID; ?></th>
        <th class="text-end"><?php echo TEXT_AMOUNT; ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php
    $odd = true;
    while (!$cInfo->orderHistory->EOF) {
      $oID          = $cInfo->orderHistory->fields['id'];
      $post_date    = gen_locale_date($cInfo->orderHistory->fields['post_date']);
      $reference_id = htmlspecialchars($cInfo->orderHistory->fields['purchase_invoice_id']);
      $primary_name = htmlspecialchars($cInfo->orderHistory->fields['bill_primary_name']);
      $closed       = $cInfo->orderHistory->fields['closed'] ? TEXT_YES : '';
      $total_amount = $currencies->format_full($cInfo->orderHistory->fields['total_amount']);
      $link_page    = html_href_link(FILENAME_DEFAULT, "module=phreebooks&amp;page=orders&amp;oID=$oID&amp;action=edit&amp;jID=12", 'SSL');
    ?>
      <tr class="<?php echo $odd ? 'odd' : 'even'; ?>" style="cursor:pointer">
        <td onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo $post_date; ?></td>
        <td onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo $reference_id; ?></td>
        <td onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo $primary_name; ?></td>
        <td onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo htmlspecialchars($cInfo->orderHistory->fields['serialize_number']); ?></td>
        <td onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo $closed; ?></td>
        <td class="text-end" onclick="window.open('<?php echo $link_page; ?>','_blank')"><?php echo $total_amount; ?></td>
        <td class="text-center" onclick="event.stopPropagation()">
          <?php echo html_icon('actions/document-print.png', TEXT_PRINT, 'small', 'onclick="printOrder(' . $oID . ')"') . chr(10); ?>
          <?php echo html_icon('actions/edit-find-replace.png', TEXT_EDIT, 'small', 'onclick="window.open(\'' . $link_page . '\',\'_blank\')"') . chr(10); ?>
        </td>
      </tr>
    <?php
      $cInfo->orderHistory->MoveNext();
      $odd = !$odd;
    }
    ?>
    </tbody>
  </table>
</div>
