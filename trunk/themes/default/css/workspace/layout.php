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
//  Path: /themes/default/css/workspace/layout.php
//
// The Workspace page: the sidebar menu on the left and, beside it, a top bar with
// the breadcrumb, then the page and the footer. A page drawn without the menu
// ($include_header false) gets the page and footer alone. What any layout must
// keep is listed in themes/default/layout.php.
?>
  <script type="text/javascript" src="modules/phreedom/includes/wz_tooltip/wz_tooltip.js"></script>
  <script type="text/javascript" src="modules/phreedom/includes/wz_tooltip/tip_balloon.js"></script>
  <div id="please_wait"><p><?php echo html_icon('phreebooks/please_wait.gif', TEXT_PLEASE_WAIT, 'large'); ?></p></div>
<?php if ($include_header) { ?>
  <script type="text/javascript">
  // On a wide window the sidebar can be hidden to give the page its width; the
  // choice is this browser's own, kept in localStorage.
  try { if (localStorage.getItem('fb.ws.side') === 'hidden') document.documentElement.classList.add('ws-side-hidden'); } catch (e) {}
  function wsToggleSide(btn) {
    var hidden = document.documentElement.classList.toggle('ws-side-hidden');
    try { localStorage.setItem('fb.ws.side', hidden ? 'hidden' : 'shown'); } catch (e) {}
    wsSideLabel(btn);
  }
  function wsSideLabel(btn) {
    var hidden = document.documentElement.classList.contains('ws-side-hidden');
    btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    btn.setAttribute('aria-label', hidden ? <?php echo json_encode(TEXT_SHOW); ?> : <?php echo json_encode(TEXT_HIDE); ?>);
    btn.title = btn.getAttribute('aria-label');
  }
  </script>
  <div class="ws-shell">
<?php require_once(theme_file('menu.php')); ?>
  <div class="ws-main">
    <header class="ws-topbar">
      <button class="ws-menu-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#ws-side" aria-controls="ws-side" aria-expanded="false" aria-label="<?php echo TEXT_SHOW; ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <button id="ws-side-toggle" class="ws-menu-toggle d-none d-lg-inline-flex" type="button" aria-controls="ws-side" onclick="wsToggleSide(this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
      </button>
      <script type="text/javascript">wsSideLabel(document.getElementById('ws-side-toggle'));</script>
      <nav class="ws-crumbs">
<?php
  $ws_crumbs = array_column(ws_menu_trail(), 1);
  if (!$ws_crumbs) $ws_crumbs = array(PAGE_TITLE);
  $ws_last = count($ws_crumbs) - 1;
  foreach ($ws_crumbs as $i => $crumb) {
    echo '        ' . ($i ? '<span class="ws-crumb-sep">&rsaquo;</span>' : '')
       . '<span class="ws-crumb' . ($i == $ws_last ? ' ws-crumb-here" aria-current="page' : '') . '">' . strip_tags($crumb) . '</span>' . "\n";
  }
?>
      </nav>
      <span class="ws-topbar-company"><?php echo COMPANY_NAME; ?></span>
    </header>
<?php } ?>
  <div id="content-wrap">
  <!-- Template -->
  <?php if (is_file($template_path)) { require($template_path); } else throw new RuntimeException('No template file: ' . $template_path); ?>
  </div>
  <!-- Footer -->
  <?php if ($include_footer) require(theme_file('footer.php')); ?>
<?php if ($include_header) { ?>
  </div>
  </div>
<?php } ?>
