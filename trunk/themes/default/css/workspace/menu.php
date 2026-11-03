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
//  Path: /themes/default/css/workspace/menu.php
//
// The Workspace main menu: a dark sidebar in which each heading of $mainmenu is a
// group that opens in place, with the user, company and Log Out at the foot. The
// group holding the current page starts open and the page's own entry is marked;
// ws_menu_trail() gives layout.php the same path for its breadcrumb. On a narrow
// window the sidebar is a Bootstrap collapse opened from the top bar.
uasort($mainmenu, 'fbmenu_sort');
$GLOBALS['ws_trail'] = ws_menu_find($mainmenu);
$ws_logout = $mainmenu['logout'] ?? null;
?>
<aside id="ws-side" class="ws-side collapse d-lg-block">
  <a class="ws-brand" href="index.php">FreeBooks</a>
  <nav class="ws-nav">
<?php foreach ($mainmenu as $key => $item) if ($key !== 'logout') ws_menu_group($key, $item); ?>
  </nav>
  <div class="ws-account">
    <span class="ws-account-company"><?php echo COMPANY_NAME; ?></span>
    <span class="ws-account-user"><?php echo htmlspecialchars($_SESSION['display_name'] ?? '', ENT_QUOTES); ?></span>
<?php if ($ws_logout) { ?>
    <a class="ws-account-logout" href="<?php echo $ws_logout['link']; ?>"><?php echo ws_menu_icon('logout') . $ws_logout['text']; ?></a>
<?php } ?>
  </div>
</aside>
<?php

// How closely a menu link matches the page being shown: the number of its query
// parameters, when every one of them equals the request's, or -1. A link with no
// parameters (Home) matches only a request that names no module.
function ws_menu_score($link) {
  $query = parse_url(html_entity_decode((string)$link), PHP_URL_QUERY);
  parse_str((string)$query, $params);
  if (!$params) return isset($_GET['module']) ? -1 : 0;
  foreach ($params as $name => $value) {
    if (!isset($_GET[$name]) || (string)$_GET[$name] !== (string)$value) return -1;
  }
  return count($params);
}

// The menu entries leading to the best match for the current page, outermost
// first, each as array(key, text); empty when nothing matches. An entry with a
// submenu can have a page of its own (Module Administration); its entries are
// tried first, so it is the match only when none of them matches as closely.
function ws_menu_find(array $items, array $trail = array()) {
  $best = array('score' => -1, 'trail' => array());
  foreach ($items as $key => $item) {
    if (!fbmenu_item_permitted($item)) continue;
    $here = array_merge($trail, array(array($key, $item['text'] ?? '')));
    if (isset($item['submenu'])) {
      $found = ws_menu_find($item['submenu'], $here);
      if ($found['score'] > $best['score']) $best = $found;
    }
    if ($trail && !empty($item['link']) && ($score = ws_menu_score($item['link'])) > $best['score']) {
      $best = array('score' => $score, 'trail' => $here);
    }
  }
  return $trail ? $best : $best['trail'];
}

// The trail for the breadcrumb; empty on a page the menu does not list.
function ws_menu_trail() {
  return $GLOBALS['ws_trail'] ?? array();
}

// Whether the entry reached by these keys, outermost first, is on the trail.
function ws_menu_on_trail(array $path) {
  $keys = array_column(ws_menu_trail(), 0);
  return array_slice($keys, 0, count($path)) === $path;
}

// One top-level heading: a plain link when it has no submenu, otherwise a group.
function ws_menu_group($key, array $item) {
  if (!fbmenu_item_permitted($item)) return;
  if (!isset($item['submenu'])) {
    $on = ws_menu_on_trail(array($key)) ? ' ws-on' : '';
    echo '    <a class="ws-top' . $on . '" href="' . $item['link'] . '" ' . ($item['params'] ?? '') . '>'
       . ws_menu_icon($key) . '<span>' . $item['text'] . '</span></a>' . "\n";
    return;
  }
  if (!fbmenu_permitted($item['submenu'])) return;
  uasort($item['submenu'], 'fbmenu_sort');
  $open = ws_menu_on_trail(array($key));
  echo '    <details class="ws-group"' . ($open ? ' open' : '') . '>' . "\n";
  echo '      <summary class="ws-top' . ($open ? ' ws-in' : '') . '">' . ws_menu_icon($key) . '<span>' . $item['text'] . '</span></summary>' . "\n";
  foreach ($item['submenu'] as $ckey => $child) ws_menu_item($child, array($key, $ckey));
  echo '    </details>' . "\n";
}

// An entry under a heading. One with its own submenu becomes a small heading
// over its entries, which are indented a step further; when it also has a page
// of its own (Module Administration), the heading is a link to that page.
function ws_menu_item(array $item, array $path) {
  if (!fbmenu_item_permitted($item)) return;
  $depth  = count($path) - 1;
  $params = $item['params'] ?? '';
  $on     = count($path) == count(ws_menu_trail()) && ws_menu_on_trail($path);
  if (isset($item['submenu'])) {
    if (!fbmenu_permitted($item['submenu'])) return;
    uasort($item['submenu'], 'fbmenu_sort');
    if (!empty($item['link'])) {
      echo '      <a class="ws-sub-head ws-sub-head-link ws-d' . $depth . ($on ? ' ws-on" aria-current="page' : '') . '" href="' . $item['link'] . '" ' . $params . '>'
         . $item['text'] . '</a>' . "\n";
    } else {
      echo '      <div class="ws-sub-head ws-d' . $depth . '">' . $item['text'] . '</div>' . "\n";
    }
    foreach ($item['submenu'] as $ckey => $child) ws_menu_item($child, array_merge($path, array($ckey)));
    return;
  }
  echo '      <a class="ws-sub ws-d' . $depth . ($on ? ' ws-on" aria-current="page' : '') . '" href="' . ($item['link'] ?? '') . '" ' . $params . '>'
     . $item['text'] . '</a>' . "\n";
}

// A line icon for a top-level heading, by its $mainmenu key; a heading added by
// a module this theme does not know gets a plain dot.
function ws_menu_icon($key) {
  $paths = array(
    'home'      => '<path d="M4 11l8-7 8 7v9h-5v-6H9v6H4z"/>',
    'customers' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2 .7 3.2 2.5 3.6 5.2"/>',
    'vendors'   => '<path d="M3 21V9l9-6 9 6v12M9 21v-7h6v7"/>',
    'employees' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M7.5 18c.7-2.2 2.4-3.4 4.5-3.4s3.8 1.2 4.5 3.4"/>',
    'inventory' => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
    'banking'   => '<path d="M3 10l9-6 9 6M5 10v8M10 10v8M14 10v8M19 10v8M3 21h18"/>',
    'gl'        => '<path d="M5 3h11l3 3v15H5zM9 8h6M9 12h6M9 16h4"/>',
    'tools'     => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z"/>',
    'quality'   => '<path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'company'   => '<path d="M4 21V5l8-2v18M12 7h8v14M7 8h2M7 12h2M7 16h2M15 11h2M15 15h2M2 21h20"/>',
    'logout'    => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
  );
  $path = $paths[$key] ?? '<circle cx="12" cy="12" r="3"/>';
  return '<svg class="ws-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
?>
