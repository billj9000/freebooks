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
//  Path: /modules/shipping/pages/rm_rates/template_main.php
//
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, 'module=shipping&page=admin', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
$toolbar->add_help('09.01.10');
echo $toolbar->build_toolbar();
$today = date('Y-m-d');
?>
<h1><?php echo RM_RATES_HEADING; ?></h1>
<div style="max-width:900px">

<?php if ($error_count): ?>
  <div class="card mb-3 border-danger">
    <div class="card-header py-2 fb-widget-header"><?php echo sprintf(RM_RATES_CHECK_FAILED, htmlspecialchars($_SESSION['rm_rates_pending_name'] ?? ''), $error_count); ?></div>
    <div class="card-body">
      <ul class="mb-2">
        <?php foreach ($check_errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
      </ul>
      <?php if ($error_count > count($check_errors)): ?>
        <p class="text-muted"><?php echo sprintf(RM_RATES_MORE_ERRORS, $error_count - count($check_errors)); ?></p>
      <?php endif; ?>
      <?php echo html_form('rm_rates_discard', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post'); ?>
        <input type="hidden" name="action" value="discard">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_RATES_DISCARD; ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($pending): ?>
  <div class="card mb-3">
    <div class="card-header py-2 fb-widget-header"><?php echo sprintf(RM_RATES_PENDING, htmlspecialchars($_SESSION['rm_rates_pending_name'] ?? '')); ?></div>
    <div class="card-body">
      <p>
        <?php echo sprintf(RM_RATES_PENDING_DESC, $pending['rows'], gen_locale_date($pending['effective_from'])); ?>
        <?php if ($pending['replaces']) echo ' ' . sprintf(RM_RATES_PENDING_REPLACES, $pending['replaces']); ?>
      </p>
      <table class="table table-sm table-borderless table-hover mb-3">
        <thead class="table-dark">
          <tr>
            <th><?php echo RM_RATES_TEXT_CARRIER; ?></th>
            <th><?php echo RM_RATES_TEXT_SERVICE; ?></th>
            <th><?php echo RM_RATES_TEXT_CHANNEL; ?></th>
            <th class="text-end"><?php echo RM_RATES_TEXT_ROWS; ?></th>
            <th class="text-end"><?php echo RM_RATES_TEXT_CONFIRM; ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending['summary'] as $s): ?>
          <tr>
            <td><?php echo htmlspecialchars($s['carrier']); ?></td>
            <td><?php echo htmlspecialchars($s['service']); ?></td>
            <td><?php echo htmlspecialchars($s['price_channel']); ?></td>
            <td class="text-end"><?php echo $s['rows']; ?></td>
            <td class="text-end"><?php echo $s['confirm'] ?: ''; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php echo html_form('rm_rates_import', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'class="d-inline"'); ?>
        <input type="hidden" name="action" value="import">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_RATES_IMPORT; ?></button>
      </form>
      <?php echo html_form('rm_rates_discard', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'class="d-inline"'); ?>
        <input type="hidden" name="action" value="discard">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_RATES_DISCARD; ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if (!$pending && !$error_count): ?>
  <div class="card mb-3">
    <div class="card-header py-2 fb-widget-header"><?php echo RM_RATES_UPLOAD; ?></div>
    <div class="card-body">
      <p><?php echo RM_RATES_UPLOAD_DESC; ?></p>
      <?php echo html_form('rm_rates_check', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'enctype="multipart/form-data"'); ?>
        <input type="hidden" name="action" value="check">
        <div class="d-flex gap-2 align-items-center">
          <input type="file" name="rate_file" accept=".csv" class="form-control form-control-sm" style="max-width:400px" required>
          <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_RATES_CHECK; ?></button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

  <div class="card">
    <div class="card-header py-2 fb-widget-header"><?php echo RM_RATES_STORED; ?></div>
    <div class="card-body p-0">
      <?php if (empty($versions)): ?>
        <p class="text-muted p-3 mb-0"><?php echo RM_RATES_NONE; ?></p>
      <?php else: ?>
        <table class="table table-sm table-borderless table-hover mb-0">
          <thead class="table-dark">
            <tr>
              <th><?php echo RM_RATES_TEXT_EFFECTIVE; ?></th>
              <th class="text-end"><?php echo RM_RATES_TEXT_ROWS; ?></th>
              <th class="text-end"><?php echo RM_RATES_TEXT_CONFIRM; ?></th>
              <th><?php echo RM_RATES_TEXT_STATUS; ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($versions as $v): ?>
            <?php
              if ($v['effective_from'] === $current)     $status = '<span class="badge bg-success">' . RM_RATES_STATUS_CURRENT . '</span>';
              elseif ($v['effective_from'] > $today)     $status = '<span class="badge bg-info text-dark">' . RM_RATES_STATUS_FUTURE . '</span>';
              else                                        $status = '<span class="badge bg-secondary">' . RM_RATES_STATUS_PAST . '</span>';
            ?>
            <tr>
              <td><?php echo gen_locale_date($v['effective_from']); ?></td>
              <td class="text-end"><?php echo (int)$v['num_rows']; ?></td>
              <td class="text-end"><?php echo (int)$v['confirm'] ?: ''; ?></td>
              <td><?php echo $status; ?></td>
              <td class="text-end">
                <?php if ($security_level > 3): ?>
                <?php echo html_form('rm_rates_delete_' . $v['effective_from'], FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'class="d-inline" onsubmit="return confirm(\'' . addslashes(sprintf(RM_RATES_DELETE_CONFIRM, gen_locale_date($v['effective_from']))) . '\')"'); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="effective_from" value="<?php echo htmlspecialchars($v['effective_from']); ?>">
                  <button type="submit" class="btn btn-primary btn-sm"><?php echo TEXT_DELETE; ?></button>
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

  <div class="card mt-3">
    <div class="card-header py-2 fb-widget-header"><?php echo RM_ZONES_HEADING; ?></div>
    <div class="card-body">
      <p><?php echo sprintf(RM_ZONES_DESC, count($zones), count($zones_check)); ?></p>
      <?php if ($zone_count): ?>
        <div class="alert alert-danger">
          <strong><?php echo sprintf(RM_ZONES_CHECK_FAILED, $zone_count); ?></strong>
          <ul class="mb-0">
            <?php foreach ($zone_errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
          </ul>
          <?php if ($zone_count > count($zone_errors)) echo sprintf(RM_RATES_MORE_ERRORS, $zone_count - count($zone_errors)); ?>
        </div>
      <?php endif; ?>
      <?php if ($zones_check): ?>
        <table class="table table-sm table-borderless table-hover mb-3">
          <thead class="table-dark">
            <tr>
              <th><?php echo RM_ZONES_TEXT_COUNTRY; ?></th>
              <th><?php echo RM_ZONES_TEXT_RM; ?></th>
              <th><?php echo RM_ZONES_TEXT_PF; ?></th>
              <th><?php echo RM_ZONES_TEXT_NOTES; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($zones_check as $z): ?>
            <tr>
              <td><?php echo htmlspecialchars($z['country']) . ' (' . $z['iso2'] . ')'; ?></td>
              <td><?php echo htmlspecialchars($z['rm_zone']); ?></td>
              <td><?php echo htmlspecialchars($z['pf_zone']); ?></td>
              <td><?php echo htmlspecialchars($z['notes']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p><?php echo RM_ZONES_EDIT_DESC; ?></p>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-primary btn-sm" href="<?php echo html_href_link(FILENAME_DEFAULT, 'module=shipping&page=rm_rates&action=zones_export', 'SSL'); ?>"><?php echo RM_ZONES_DOWNLOAD; ?></a>
        <?php if ($security_level > 2): ?>
        <?php echo html_form('rm_zones_import', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'enctype="multipart/form-data" class="d-flex gap-2 align-items-center"'); ?>
          <input type="hidden" name="action" value="zones_import">
          <input type="file" name="zone_file" accept=".csv" class="form-control form-control-sm" style="max-width:300px" required>
          <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_ZONES_IMPORT; ?></button>
        </form>
        <?php echo html_form('rm_zones_reset', FILENAME_DEFAULT, 'module=shipping&page=rm_rates', 'post', 'class="d-inline" onsubmit="return confirm(\'' . addslashes(RM_ZONES_RESET_CONFIRM) . '\')"'); ?>
          <input type="hidden" name="action" value="zones_reset">
          <button type="submit" class="btn btn-primary btn-sm"><?php echo RM_ZONES_RESET; ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
