<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or  |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// +-----------------------------------------------------------------+
//  Path: /modules/labels/pages/main/template_main.php

$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['save']['show']   = false;
$toolbar->icon_list['print']['show']  = false;
if ($security_level > 1) {
  $toolbar->add_icon('new', 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=labels&page=edit', 'SSL') . '\'"', 10);
}
$toolbar->add_help('07.04.07');
echo $toolbar->build_toolbar();
?>
<h1><?php echo TEXT_LABEL_TEMPLATES; ?></h1>

<div class="pkg-wrap">

<div class="card fb-card">
  <div class="card-header py-1 fb-widget-header"><?php echo TEXT_LABEL_TEMPLATES; ?></div>
  <div class="card-body p-0">
<?php if (empty($templates)): ?>
    <p class="p-3 text-muted"><?php echo TEXT_NO_TEMPLATES_DEFINED; ?></p>
<?php else: ?>
    <table class="table table-sm table-borderless table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th><?php echo TEXT_LABEL_NAME; ?></th>
          <th class="text-center"><?php echo TEXT_WIDTH_MM; ?></th>
          <th class="text-center"><?php echo TEXT_HEIGHT_MM; ?></th>
          <th class="text-center"><?php echo TEXT_PRINTER_TYPE; ?></th>
          <th class="text-center"><?php echo TEXT_LABEL_DATE_MODIFIED; ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php $odd = true; foreach ($templates as $t): $row_class = $odd ? 'odd' : 'even'; $odd = !$odd; ?>
        <tr class="<?php echo $row_class; ?>">
          <td><?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="text-center"><?php echo $t['width_mm']; ?></td>
          <td class="text-center"><?php echo $t['height_mm']; ?></td>
          <td class="text-center"><?php echo ($t['printer_type'] === 'zpl') ? TEXT_PRINTER_TYPE_ZPL : TEXT_PRINTER_TYPE_HTML; ?></td>
          <td class="text-center"><?php echo $t['date_modified']; ?></td>
          <td class="text-end text-nowrap">
            <?php if ($security_level > 1): ?>
            <a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=labels&page=edit&id=' . $t['id'], 'SSL'); ?>" class="btn btn-primary btn-sm"><?php echo TEXT_EDIT; ?></a>
            <?php endif; ?>
            <?php if ($security_level > 3): ?>
            <?php // A posted form, not a link: see the delete case in pre_process.php.
            echo html_form('label_del_' . (int)$t['id'], FILENAME_DEFAULT, 'module=labels&page=main&action=delete', 'post',
              'class="d-inline" onsubmit="return confirm(' . htmlspecialchars(json_encode(TEXT_DELETE_ENTRY)) . ')"'); ?>
              <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
              <button type="submit" class="btn btn-danger btn-sm ms-1"><?php echo TEXT_DELETE; ?></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </div>
</div>

</div><!-- /pkg-wrap -->
