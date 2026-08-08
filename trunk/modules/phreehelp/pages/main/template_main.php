<!DOCTYPE html>
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
//  Path: /modules/phreehelp/pages/main/template_main.php
//
?>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo HEADING_TITLE; ?></title>
  <link rel="shortcut icon" type="image/ico" href="favicon.ico">
  <link rel="stylesheet" href="ext/bootstrap5/bootstrap.min.css">
  <link rel="stylesheet" href="ext/bootstrap5/freebooks.css">
  <script src="ext/bootstrap5/bootstrap.bundle.min.js" defer></script>
  <script>
    var icon_path = '<?php echo DIR_WS_ICONS; ?>';
    function Toggle(item) {
      var obj = document.getElementById(item);
      var visible = obj ? obj.style.display !== 'none' : false;
      var key = document.getElementById('img' + item);
      if (visible) {
        if (obj) obj.style.display = 'none';
        if (key) key.innerHTML = "<img src='" + icon_path + "16x16/places/folder.png' width='16' height='16' border='0'>";
      } else {
        if (obj) obj.style.display = 'block';
        if (key) key.innerHTML = "<img src='" + icon_path + "16x16/actions/document-open.png' width='16' height='16' border='0'>";
      }
    }
    function Expand(tab_type) {
      document.querySelectorAll('div[id^="' + tab_type + '"]').forEach(function(div) {
        div.style.display = 'block';
        var key = document.getElementById('img' + div.id);
        if (key) key.innerHTML = "<img src='" + icon_path + "16x16/actions/document-open.png' width='16' height='16' border='0'>";
      });
    }
    function Collapse(tab_type) {
      document.querySelectorAll('div[id^="' + tab_type + '"]').forEach(function(div) {
        div.style.display = 'none';
        var key = document.getElementById('img' + div.id);
        if (key) key.innerHTML = "<img src='" + icon_path + "16x16/places/folder.png' width='16' height='16' border='0'>";
      });
    }
  </script>
  <style>
    html, body { height: 100%; margin: 0; overflow: hidden; }
    #help-layout { display: flex; flex-direction: column; height: 100vh; font-family: Verdana, Arial, sans-serif; font-size: 11px; }
    #help-body   { display: flex; flex: 1; overflow: hidden; }
    #help-sidebar { width: 300px; flex-shrink: 0; border-right: 1px solid #a6c9e2; background: #fcfdfd; display: flex; flex-direction: column; overflow: hidden; }
    #helpTabContent { flex: 1; overflow-y: auto; padding: 0.5rem; }
    #contentFrame { flex: 1; border: none; height: 100%; background: #ffffff; }

    /* Header bar: dark gloss-wave, matches the app's main navbar (.fb-navbar) */
    #help-header {
      display:             flex;
      align-items:         center;
      gap:                 8px;
      padding:             4px 8px;
      background-color:    #2191c0;
      background-image:    url(/themes/default/css/start/images/ui-bg_gloss-wave_75_2191c0_500x100.png);
      background-repeat:   repeat-x;
      background-position: 50% 50%;
      border-bottom:       1px solid #4297d7;
    }
    #help-header-label {
      color:       #ffffff;
      font-weight: bold;
      font-size:   13px;
    }

    /* Toolbar bar: lighter "glass" gloss-wave, matches .fb-toolbar-section */
    #help-toolbar {
      display:             flex;
      align-items:         center;
      gap:                 4px;
      padding:             0 6px;
      background-color:    #79c9ec;
      background-image:    url(/themes/default/css/start/images/ui-bg_glass_75_79c9ec_1x400.png);
      background-repeat:   repeat-x;
      background-position: 50% 50%;
      border-bottom:       1px solid #448dae;
    }
    #help-toolbar img.imgIcon { cursor: pointer; }

    /* Sidebar tabs: reuse fb-tabs but keep them compact in the narrow rail */
    #helpTabs .nav-link { font-size: 11px; padding: 4px 8px; }

    /* ── fb-help-tree: Contents / Index / Search rendering ───────────────── */
    .fb-help-tree {
      list-style: none;
      margin:     0;
      padding:    0;
    }
    .fb-help-tree .fb-help-tree {
      margin-left:  14px;
      padding-left: 8px;
      border-left:  1px dotted #a6c9e2;
    }
    .fb-help-tree-root { padding: 4px 2px; }
    .fb-help-node { margin: 1px 0; }

    /* Folders and leaf (document) rows both lay out icon + label side by side */
    .fb-help-folder, .fb-help-leaf {
      display:       flex;
      align-items:   flex-start;
      gap:           4px;
      padding:       2px 4px;
      border-radius: 3px;
    }
    .fb-help-folder { cursor: pointer; }
    .fb-help-folder:hover,
    .fb-help-leaf:hover {
      background-color: #e8f4fb;
    }
    .fb-help-tree a.fb-help-label,
    .fb-help-tree span.fb-help-label {
      color:            #222222;
      text-decoration:  none;
      word-break:       break-word;
      flex:             1 1 auto;
    }
    .fb-help-tree a.fb-help-label:hover { color: #026890; text-decoration: underline; }
    .fb-help-toggle { line-height: 0; flex-shrink: 0; }
    .fb-help-folder .fb-help-label { color: #164a63; font-weight: bold; }
    .fb-help-tree a.fb-help-label.fb-help-active {
      background-color: #cfe9f7;
      color:             #026890;
      font-weight:       bold;
      border-radius:     3px;
    }
  </style>
</head>
<body>
<div id="help-layout">

  <!-- Header bar: FreeBooks logo, dark gloss-wave (matches the app's main navbar) -->
  <div id="help-header" class="flex-shrink-0">
    <?php $help_logo = DIR_WS_THEMES . 'images/freebooks_logo_text_default.png'; ?>
    <?php if (is_file($help_logo)): ?>
      <img src="<?php echo $help_logo; ?>" alt="FreeBooks" style="height:20px;width:auto">
    <?php endif; ?>
    <span id="help-header-label"><?php echo HEADING_HELP_LABEL; ?></span>
  </div>

  <!-- Toolbar bar: lighter glass gloss-wave (matches .fb-toolbar-section) -->
  <div id="help-toolbar" class="flex-shrink-0">
    <?php echo html_icon('actions/go-home.png',        TEXT_HOME,    'large', 'style="cursor:pointer;" onclick="document.getElementById(\'contentFrame\').contentWindow.location.href=\'' . DOC_ROOT_URL . '\'"'); ?>
    <?php echo html_icon('actions/go-previous.png',     TEXT_BACK,    'large', 'style="cursor:pointer;" onclick="document.getElementById(\'contentFrame\').contentWindow.history.back()"'); ?>
    <?php echo html_icon('actions/go-next.png',         TEXT_FORWARD, 'large', 'style="cursor:pointer;" onclick="document.getElementById(\'contentFrame\').contentWindow.history.forward()"'); ?>
    <?php echo html_icon('devices/printer.png',         TEXT_PRINT,   'large', 'style="cursor:pointer;" onclick="document.getElementById(\'contentFrame\').contentWindow.print()"'); ?>
    <?php echo html_icon('actions/system-log-out.png',  TEXT_EXIT,    'large', 'style="cursor:pointer;" onclick="window.close()"'); ?>
  </div>

  <!-- Main body -->
  <div id="help-body">

    <!-- Left sidebar -->
    <div id="help-sidebar">
      <ul class="nav nav-tabs fb-tabs flex-shrink-0" id="helpTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?php echo (!$search_text) ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-contents" type="button" role="tab"><?php echo HEADING_CONTENTS; ?></button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-index" type="button" role="tab"><?php echo HEADING_INDEX; ?></button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?php echo $search_text ? 'active' : ''; ?>" id="tab-search-btn" data-bs-toggle="tab" data-bs-target="#tab-search" type="button" role="tab"><?php echo TEXT_SEARCH; ?></button>
        </li>
      </ul>
      <div class="tab-content fb-tab-content" id="helpTabContent">
        <div class="tab-pane fade <?php echo (!$search_text) ? 'show active' : ''; ?>" id="tab-contents" role="tabpanel">
          <?php echo retrieve_toc(); ?>
        </div>
        <div class="tab-pane fade" id="tab-index" role="tabpanel">
          <?php echo retrieve_index(); ?>
        </div>
        <div class="tab-pane fade <?php echo $search_text ? 'show active' : ''; ?>" id="tab-search" role="tabpanel">
          <form method="get" action="index.php" class="mb-2 pt-2 px-2">
            <input type="hidden" name="module" value="phreehelp">
            <input type="hidden" name="page" value="main">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control" name="search_text" value="<?php echo htmlspecialchars($search_text); ?>" placeholder="<?php echo TEXT_KEYWORD; ?>">
              <button class="btn btn-primary btn-sm" type="submit"><?php echo TEXT_SEARCH; ?></button>
            </div>
          </form>
          <?php if ($search_text): ?>
            <div class="px-2"><strong><?php echo TEXT_SEARCH_RESULTS; ?></strong></div>
            <div class="px-2"><?php echo search_results($search_text); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /help-sidebar -->

    <!-- Help content -->
    <iframe id="contentFrame" name="mainFrame" src="<?php echo $start_page; ?>" title="<?php echo HEADING_TITLE; ?>"></iframe>

  </div><!-- /help-body -->
</div><!-- /help-layout -->
<script>
  // Keep the Contents tree in sync with whatever page is loaded in the
  // content frame: open every ancestor folder of the current page, open
  // the page's own subtree if it's a chapter home page, and highlight
  // its link — whether the frame navigated via a sidebar click or a link
  // inside the manual content itself.
  function openFolderDiv(div) {
    div.style.display = 'block';
    var key = document.getElementById('img' + div.id);
    if (key) key.innerHTML = "<img src='" + icon_path + "16x16/actions/document-open.png' width='16' height='16' border='0'>";
  }
  function openAncestorFolders(link) {
    var node = link.closest('.fb-help-node');
    while (node) {
      var wrapperDiv = node.parentElement ? node.parentElement.closest('div[id^="dc_"]') : null;
      if (!wrapperDiv) break;
      openFolderDiv(wrapperDiv);
      node = wrapperDiv.closest('.fb-help-node');
    }
  }
  function openOwnChildren(link) {
    var node = link.closest('.fb-help-node');
    var ownDiv = node ? node.querySelector(':scope > div[id^="dc_"]') : null;
    if (ownDiv) openFolderDiv(ownDiv);
  }
  function syncContentsTreeToFrame() {
    var frame = document.getElementById('contentFrame');
    var currentHref;
    try { currentHref = frame.contentWindow.location.href; } catch (e) { return; }
    var match = null;
    document.querySelectorAll('#tab-contents a.fb-help-label').forEach(function (a) {
      a.classList.remove('fb-help-active');
      if (a.href === currentHref) match = a;
    });
    if (match) {
      match.classList.add('fb-help-active');
      openAncestorFolders(match);
      openOwnChildren(match);
      match.scrollIntoView({ block: 'nearest' });
    }
  }
  document.getElementById('contentFrame').addEventListener('load', syncContentsTreeToFrame);
</script>
</body>
</html>
