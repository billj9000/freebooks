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
//  Path: /themes/default/menu_bs.php
//
usort($mainmenu, 'fbmenu_sort');
?>
<nav class="navbar navbar-expand-lg fb-navbar py-0<?php echo defined('MY_MENU_HOVER') && MY_MENU_HOVER ? ' fb-navbar-hover' : ''; ?>">
  <div class="container-fluid px-2">
    <a class="navbar-brand py-1 px-1" href="index.php">
      <?php
        $_bs_logo = DIR_WS_THEMES . 'images/freebooks_logo_text_default.png';
        if (is_file($_bs_logo)): ?>
        <img src="<?php echo $_bs_logo; ?>" alt="FreeBooks" style="height:20px;width:auto">
      <?php else: ?>
        <span class="fw-bold" style="font-size:13px">FreeBooks</span>
      <?php endif; ?>
    </a>
    <button class="navbar-toggler p-1 border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#fb-nav-main" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="fb-nav-main">
      <ul class="navbar-nav">
        <?php foreach ($mainmenu as $item) fbmenu_render($item, 0); ?>
      </ul>
    </div>
  </div>
</nav>
<div id="content-wrap">
<?php

function fbmenu_sort($a, $b) {
  if (is_integer($a['order']) && is_integer($b['order'])) return $a['order'] - $b['order'];
  return strcmp($a['order'], $b['order']);
}

function fbmenu_permitted(array $items) {
  if ($_SESSION['admin_id'] == 1) return true;
  foreach ($items as $item) {
    if (isset($item['submenu']) && is_array($item['submenu'])) {
      if (fbmenu_permitted($item['submenu'])) return true;
    } else {
      if (isset($item['security_id']) && $item['security_id'] != ''
          && array_key_exists($item['security_id'], $_SESSION['admin_security'])
          && $_SESSION['admin_security'][$item['security_id']] > 0) return true;
    }
  }
  return false;
}

function fbmenu_render(array $item, int $depth) {
  // Security check for this item
  if (($_SESSION['admin_id'] ?? 0) != 1) {
    if (isset($item['security_id']) && $item['security_id'] != '') {
      if (!array_key_exists($item['security_id'], $_SESSION['admin_security'])
          || $_SESSION['admin_security'][$item['security_id']] < 1) return;
    }
  }

  $text  = isset($item['icon']) ? $item['icon'] . ' ' . $item['text'] : $item['text'];
  $href  = $item['link'] ?? '';
  $extra = $item['params'] ?? '';

  if (isset($item['submenu'])) {
    usort($item['submenu'], 'fbmenu_sort');
    if (!fbmenu_permitted($item['submenu'])) return;

    if ($depth === 0) {
      echo '<li class="nav-item dropdown">';
      echo '<a class="nav-link dropdown-toggle py-2 px-2" href="#" data-bs-toggle="dropdown" role="button">' . $text . '</a>';
      echo '<ul class="dropdown-menu">';
      foreach ($item['submenu'] as $child) fbmenu_render($child, 1);
      echo '</ul></li>' . "\n";
    } else {
      echo '<li class="dropdown-submenu">';
      echo '<a class="dropdown-item" href="' . $href . '" ' . $extra . '>' . $text . '</a>';
      echo '<ul class="dropdown-menu">';
      foreach ($item['submenu'] as $child) fbmenu_render($child, $depth + 1);
      echo '</ul></li>' . "\n";
    }
  } else {
    if ($depth === 0) {
      echo '<li class="nav-item"><a class="nav-link py-2 px-2" href="' . $href . '" ' . $extra . '>' . $text . '</a></li>' . "\n";
    } else {
      echo '<li><a class="dropdown-item" href="' . $href . '" ' . $extra . '>' . $text . '</a></li>' . "\n";
    }
  }
}
?>
