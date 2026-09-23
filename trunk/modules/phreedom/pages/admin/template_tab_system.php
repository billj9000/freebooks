<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2013 PhreeSoft                       |
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
//  Path: /modules/phreedom/pages/admin/template_tab_system.php
//
// What a support conversation opens by asking for. Everything printed here is
// chosen in pre_process.php: no $_SERVER, no $_ENV, no phpinfo(), nothing
// naming a path or a credential.
?>
<div class="tab-pane fade<?php echo ($active_tab === 'system' ? ' show active' : ''); ?>" id="tab_system" role="tabpanel">

<?php if (!empty($sys_install_present)) { ?>
  <div class="alert alert-warning mt-2 mb-2"><?php echo TEXT_SYS_INSTALL_PRESENT; ?></div>
<?php } ?>

  <div id="sys_info_body">
<?php
  $blocks = array(
    TEXT_SYS_PHP    => $sys_php,
    TEXT_SYS_DB     => $sys_db,
    TEXT_SYS_SCHEMA => $sys_schema,
    TEXT_SYS_ENV    => array_merge($sys_env, $sys_logs),
  );
  foreach ($blocks as $legend => $rows) {
    if (empty($rows)) continue;
?>
    <div class="fb-fieldset mt-2 mb-2">
      <div class="fb-fieldset-legend"><?php echo htmlspecialchars($legend, ENT_QUOTES, 'UTF-8'); ?></div>
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th><?php echo TEXT_SYS_SETTING; ?></th>
            <th><?php echo TEXT_SYS_VALUE; ?></th>
          </tr>
        </thead>
        <tbody>
<?php   foreach ($rows as $label => $value) { ?>
          <tr>
            <td><?php echo htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
<?php   } ?>
        </tbody>
      </table>
    </div>
<?php } ?>

    <div class="fb-fieldset mt-2 mb-2">
      <div class="fb-fieldset-legend"><?php echo TEXT_SYS_EXTENSIONS; ?></div>
      <table class="pkg-table table table-borderless table-hover table-sm mb-0">
        <tbody>
          <tr>
<?php foreach ($sys_ext as $ext => $loaded) { ?>
            <td><?php echo htmlspecialchars($ext, ENT_QUOTES, 'UTF-8'); ?>:
              <?php echo $loaded ? '&#10003;' : '<span class="text-danger fw-bold">' . TEXT_SYS_MISSING . '</span>'; ?>
            </td>
<?php } ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <button type="button" class="btn btn-primary btn-sm mb-2" id="sys_info_copy"
          data-copied="<?php echo htmlspecialchars(TEXT_SYS_COPIED, ENT_QUOTES, 'UTF-8'); ?>"><?php echo TEXT_SYS_COPY; ?></button>
</div>
