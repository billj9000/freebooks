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
<html lang="en"<?php echo html_color_mode(); ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo HEADING_TITLE; ?></title>
  <link rel="shortcut icon" type="image/ico" href="favicon.ico">
  <link rel="stylesheet" href="ext/bootstrap5/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo html_versioned('ext/bootstrap5/freebooks.css'); ?>">
  <?php echo html_theme_stylesheet(); ?>
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
    #help-layout { display: flex; flex-direction: column; height: 100vh; font-family: var(--fb-font); font-size: var(--fb-font-size); color: var(--fb-ink); background: var(--fb-surface); }
    #help-body   { display: flex; flex: 1; overflow: hidden; }
    #help-sidebar { width: 300px; flex-shrink: 0; border-right: 1px solid var(--fb-line); background: var(--fb-content-bg); display: flex; flex-direction: column; overflow: hidden; }
    #helpTabContent { flex: 1; overflow-y: auto; padding: 0.5rem; }
    #contentFrame { flex: 1; border: none; height: 100%; background: var(--fb-surface); }

    /* Header bar: coloured like the main menu bar (.fb-navbar) */
    #help-header {
      display:             flex;
      align-items:         center;
      gap:                 8px;
      padding:             4px 8px;
      background-color:    var(--fb-nav-bg);
      background-image:    var(--fb-nav-image);
      background-repeat:   repeat-x;
      background-position: 50% 50%;
      border-bottom:       1px solid var(--fb-nav-border);
    }
    #help-header-label {
      color:       var(--fb-nav-ink);
      font-weight: bold;
      font-size:   13px;
    }

    /* The toolbar is drawn by the colour scheme's toolbar.php, as on any page. */
    #help-toolbar > .fb-toolbar-section { margin: 0; padding: 0 6px; border-radius: 0; border-width: 0 0 1px; }
    #help-toolbar > .fb-toolbar-labelled { padding: 6px 8px; border-bottom: 1px solid var(--fb-line); }

    /* Sidebar tabs: reuse fb-tabs but keep them compact in the narrow rail */
    #helpTabs .nav-link { font-size: var(--fb-font-size); padding: 4px 8px; }

    /* ── fb-help-tree: Contents / Index / Search rendering ───────────────── */
    .fb-help-tree {
      list-style: none;
      margin:     0;
      padding:    0;
    }
    .fb-help-tree .fb-help-tree {
      margin-left:  14px;
      padding-left: 8px;
      border-left:  1px dotted var(--fb-line);
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
      background-color: var(--fb-row-hover-soft-bg);
    }
    .fb-help-tree a.fb-help-label,
    .fb-help-tree span.fb-help-label {
      color:            var(--fb-ink);
      text-decoration:  none;
      word-break:       break-word;
      flex:             1 1 auto;
    }
    .fb-help-tree a.fb-help-label:hover { color: var(--fb-row-hover-ink); text-decoration: underline; }
    .fb-help-toggle { line-height: 0; flex-shrink: 0; }
    .fb-help-folder .fb-help-label { color: var(--fb-band-ink); font-weight: bold; }
    .fb-help-tree a.fb-help-label.fb-help-active {
      background-color: var(--fb-row-hover-bg);
      color:             var(--fb-row-hover-ink);
      font-weight:       bold;
      border-radius:     3px;
    }
  </style>
</head>
<body>
<div id="help-layout">

  <!-- Header bar: FreeBooks logo, coloured like the main menu bar -->
  <div id="help-header" class="flex-shrink-0">
    <?php $help_logo = DIR_WS_THEMES . 'images/freebooks_logo_text_default.png'; ?>
    <?php if (is_file($help_logo)): ?>
      <img src="<?php echo $help_logo; ?>" alt="FreeBooks" style="height:20px;width:auto">
    <?php endif; ?>
    <span id="help-header-label"><?php echo HEADING_HELP_LABEL; ?></span>
  </div>

  <div id="help-toolbar" class="flex-shrink-0">
<?php
  $help_frame   = "document.getElementById('contentFrame').contentWindow";
  $help_toolbar = new toolbar('help');
  $help_toolbar->icon_list = array(
    'home'    => array('show' => true, 'icon' => 'actions/go-home.png',       'text' => TEXT_HOME,    'params' => 'onclick="' . $help_frame . ".location.href='" . DOC_ROOT_URL . "'" . '"'),
    'back'    => array('show' => true, 'icon' => 'actions/go-previous.png',   'text' => TEXT_BACK,    'params' => 'onclick="' . $help_frame . '.history.back()"'),
    'forward' => array('show' => true, 'icon' => 'actions/go-next.png',       'text' => TEXT_FORWARD, 'params' => 'onclick="' . $help_frame . '.history.forward()"'),
    'print'   => array('show' => true, 'icon' => 'devices/printer.png',       'text' => TEXT_PRINT,   'params' => 'onclick="' . $help_frame . '.print()"'),
    'exit'    => array('show' => true, 'icon' => 'actions/system-log-out.png', 'text' => TEXT_EXIT,   'params' => 'onclick="window.close()"'),
  );
  echo $help_toolbar->render_buttons();
?>
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
    <iframe id="contentFrame" name="mainFrame" src="<?php echo htmlspecialchars($start_page, ENT_QUOTES); ?>" title="<?php echo HEADING_TITLE; ?>"></iframe>

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

  // Under a dark colour scheme a manual topic, which is a page of its own with the
  // manual's light stylesheet, is given the scheme's colour tokens and
  // manual_dark.css, which reads them. A light scheme leaves the topic as written.
  var fbHelpTopicTokens = ['--fb-font', '--fb-ink', '--fb-ink-heading', '--fb-surface', '--fb-readonly-bg',
    '--fb-line', '--fb-primary', '--fb-head-bg', '--fb-head-ink', '--fb-row-even-bg', '--fb-row-hover-soft-bg'];
  function fbHelpTopicMode() {
    if (document.documentElement.getAttribute('data-bs-theme') !== 'dark') return;
    var doc;
    try { doc = document.getElementById('contentFrame').contentDocument; } catch (e) { return; }
    if (!doc || !doc.head || doc.getElementById('fb-manual-dark')) return;
    var root = getComputedStyle(document.documentElement);
    fbHelpTopicTokens.forEach(function (name) {
      doc.documentElement.style.setProperty(name, root.getPropertyValue(name));
    });
    var link = doc.createElement('link');
    link.id   = 'fb-manual-dark';
    link.rel  = 'stylesheet';
    link.href = new URL(<?php echo json_encode(html_versioned('modules/phreehelp/style_sheet/manual_dark.css')); ?>, document.baseURI).href;
    doc.head.appendChild(link);
  }
  document.getElementById('contentFrame').addEventListener('load', fbHelpTopicMode);
</script>
</body>
</html>
