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
//  Path: /modules/shipping/pages/rm_manifest/template_main.php
//
echo html_form('rm_manifest', FILENAME_DEFAULT, 'module=shipping&page=rm_manifest', 'post') . chr(10);
echo html_hidden_field('action', '') . chr(10);
$toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
echo $toolbar->build_toolbar();
?>
<h1><?php echo RM_MANIFEST_HEADING; ?></h1>
<div style="max-width:680px">

<?php if ($manifest_done): ?>
  <div class="alert alert-success">
    <strong><?php echo RM_MANIFEST_SUCCESS; ?></strong>
    <?php if ($manifest_id): ?>
      &nbsp; <?php echo RM_MANIFEST_ID; ?>: <code><?php echo htmlspecialchars($manifest_id); ?></code>
    <?php endif; ?>
  </div>
<?php endif; ?>

  <!-- Manifest action card -->
  <div class="card mb-3">
    <div class="card-header py-2 fb-widget-header"><?php echo RM_MANIFEST_HEADING; ?></div>
    <div class="card-body">
      <p class="mb-3"><?php echo RM_MANIFEST_DESC; ?></p>
      <?php if (!$manifest_done): ?>
        <button type="button" class="btn btn-primary btn-sm"
                onclick="if(confirm('<?php echo addslashes(RM_MANIFEST_CONFIRM); ?>')) { document.querySelector('[name=action]').value='manifest'; document.getElementById('rm_manifest').submit(); }">
          <?php echo RM_MANIFEST_BTN; ?>
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm"
                onclick="if(window.opener) window.opener.location.reload(); self.close();">
          <?php echo TEXT_CLOSE; ?>
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Today's shipments -->
  <div class="card">
    <div class="card-header py-2 fb-widget-header">
      <?php echo RM_MANIFEST_SHIPMENTS; ?> &mdash; <?php echo date(DATE_FORMAT); ?>
      <span class="badge bg-secondary ms-2"><?php echo count($shipments); ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($shipments)): ?>
        <p class="text-muted p-3 mb-0"><?php echo RM_MANIFEST_NONE_TODAY; ?></p>
      <?php else: ?>
        <table class="table table-sm table-striped mb-0">
          <thead class="table-light">
            <tr>
              <th><?php echo SHIPPING_TEXT_REFERENCE_ID; ?></th>
              <th><?php echo SHIPPING_TEXT_TRACKING_NUM; ?></th>
              <th><?php echo RM_TEXT_RECIPIENT; ?></th>
              <th><?php echo SHIPPING_TEXT_SERVICE; ?></th>
              <th><?php echo SHIPPING_TEXT_SHIPMENT_DATE; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shipments as $s): ?>
            <tr>
              <td><?php echo htmlspecialchars($s['ref_id']); ?></td>
              <td><code><?php echo htmlspecialchars($s['tracking_id']); ?></code></td>
              <td><?php echo htmlspecialchars($s['recipient_name']); ?></td>
              <td><?php echo htmlspecialchars($s['method']); ?></td>
              <td><?php echo gen_locale_date(substr($s['ship_date'], 0, 10)); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</form>
