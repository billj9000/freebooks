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
//  Path: /modules/vin_lookup/pages/main/template_recent_rows.php
//
// Shared row markup for the Recent Lookups table body. Included both by the
// full page (pages/main/template_main.php) and by the ajax lookup fragment
// (ajax/lookup.php), which re-renders it as an out-of-band swap so the list
// stays live after every lookup instead of only updating on full page load.
// Expects $recent (array of cache rows) and $security_level in scope.
//
if (empty($recent)): ?>
<tr><td colspan="7" class="p-3 text-muted"><?php echo VIN_TEXT_NO_LOOKUPS; ?></td></tr>
<?php else: ?>
<?php foreach ($recent as $i => $row): ?>
<tr class="<?php echo $i % 2 === 0 ? 'odd' : 'even'; ?>">
  <td><code><?php echo htmlspecialchars($row['vrm']); ?></code></td>
  <td><?php echo $row['vin'] !== '' ? htmlspecialchars($row['vin']) : '<em>' . htmlspecialchars(VIN_TEXT_VIN_RESTRICTED) . '</em>'; ?></td>
  <td><?php echo htmlspecialchars($row['make']); ?></td>
  <td><?php echo htmlspecialchars($row['model']); ?></td>
  <td><?php echo htmlspecialchars($row['date_added']); ?></td>
  <td><?php echo (int)$row['hit_count']; ?></td>
  <td>
    <button type="button" class="btn btn-outline-secondary btn-sm"
      hx-get="index.php?module=vin_lookup&amp;page=ajax&amp;op=lookup"
      hx-vals='{"vrm": "<?php echo htmlspecialchars($row['vrm'], ENT_QUOTES); ?>"}'
      hx-target="#vin_results"
      hx-swap="innerHTML"
      onclick="document.getElementById('vrm').value = '<?php echo addslashes($row['vrm']); ?>'; window.scrollTo(0,0);">
      <?php echo VIN_BTN_VIEW; ?>
    </button>
    <?php if ($security_level > 3): ?>
    <a href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=vin_lookup&amp;page=main&amp;action=delete&amp;id=' . (int)$row['id'], 'SSL'); ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('<?php echo addslashes(VIN_CONFIRM_DELETE); ?>')">
      <?php echo VIN_BTN_DELETE; ?>
    </a>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
