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
//  Path: /modules/doc_ctl/pages/main/js_include.php
//
?>
<link rel="stylesheet" href="<?php echo DIR_WS_ADMIN; ?>modules/doc_ctl/includes/bootstrap-icons/bootstrap-icons.min.css"/>
<link rel="stylesheet" href="<?php echo DIR_WS_ADMIN; ?>modules/doc_ctl/includes/wunderbaum/wunderbaum.min.css"/>
<script src="<?php echo DIR_WS_ADMIN; ?>modules/doc_ctl/includes/wunderbaum/wunderbaum.umd.min.js"></script>
<script type="text/javascript">
var docTree = null;
var WB_TREE_URL = "index.php?module=doc_ctl&page=ajax&op=wb_operation&sub=wb_tree";

function wbPost(url, params) {
  var meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) params['csrf_token'] = meta.getAttribute('content');
  return fetch(url, { method: 'POST', body: new URLSearchParams(params) });
}

function init() {
  docTree = new mar10.Wunderbaum({
    element: document.getElementById("demo"),
    debugLevel: 0,
    source: WB_TREE_URL,
    iconMap: "bootstrap",
    types: {
      drive:   { icon: icon_path + "16x16/devices/drive-harddisk.png" },
      folder:  { icon: icon_path + "16x16/places/folder.png" },
      default: { icon: icon_path + "16x16/mimetypes/text-x-generic.png" }
    },
    filter: {
      autoExpand: true,
      mode: "hide"
    },
    edit: {
      trigger: ["F2"],
      apply: function(e) {
        if (!e.newValue) return false;
        return wbPost("index.php?module=doc_ctl&page=ajax&op=wb_operation",
          { sub: "wb_rename", id: e.node.key, title: e.newValue }
        ).then(function(r) { return r.json(); }).then(function(r) {
          if (!r.status) return false;
          fetch_doc(e.node.key);
        });
      }
    },
    dnd: {
      dragStart: function(e) {
        return e.node.type !== "drive";
      },
      dragEnter: function(e) {
        if (e.node.type === "folder" || e.node.type === "drive") return "over";
        return ["before", "after"];
      },
      drop: function(e) {
        e.sourceNode.moveTo(e.node, e.suggestedDropMode);
        var parent   = e.sourceNode.parent;
        var parentKey = (parent && parent.key) ? parent.key : "1";
        var position  = parent && parent.children ? parent.children.indexOf(e.sourceNode) : 0;
        if (position < 0) position = 0;
        wbPost("index.php?module=doc_ctl&page=ajax&op=wb_operation",
          { sub: "wb_move", id: e.sourceNode.key, ref: parentKey, position: position }
        ).catch(function() { docTree.load(WB_TREE_URL); });
      }
    },
    activate: function(e) {
      if (e.node) fetch_doc(e.node.key);
    }
  });

  document.getElementById("add_folder").addEventListener("click", function() { docAddNode("folder"); });
  document.getElementById("add_default").addEventListener("click", function() { docAddNode("default"); });
  document.getElementById("rename").addEventListener("click", function() {
    var node = docTree.getActiveNode();
    if (node && node.type !== "drive") node.startEditTitle();
  });
  document.getElementById("search").addEventListener("click", function() {
    var q = document.querySelector("#mmenu input[name='text']").value;
    if (q) docTree.filterNodes(q, { mode: "hide", autoExpand: true });
  });
  document.getElementById("clear_search").addEventListener("click", function() {
    docTree.clearFilter();
    document.querySelector("#mmenu input[name='text']").value = "";
  });
}

function check_form() {
  return true;
}

function docAddNode(type) {
  var node = docTree ? docTree.getActiveNode() : null;
  if (!node || (node.type !== "folder" && node.type !== "drive")) {
    alert("<?php echo addslashes(TEXT_SELECT_FOLDER); ?>");
    return;
  }
  var prompt_msg = (type === "folder") ? "<?php echo addslashes(TEXT_NEW_FOLDER); ?>" : "<?php echo addslashes(TEXT_NEW_DOCUMENT); ?>";
  var title = prompt(prompt_msg + ":");
  if (!title || !title.trim()) return;
  title = title.trim();
  wbPost("index.php?module=doc_ctl&page=ajax&op=wb_operation",
    { sub: "wb_create", parent_id: node.key, title: title, type: type }
  ).then(function(r) { return r.json(); }).then(function(r) {
    if (r.status && r.id) {
      var newNode = { title: title, key: String(r.id), type: type, folder: (type === "folder") };
      if (type === "folder") newNode.children = [];
      node.addNode(newNode, "appendChild");
      node.setExpanded(true);
    }
  });
}

function fetch_home() {
  fetch_doc(-1);
}

function fetch_doc(id) {
  if (!id) return;
  fetchXml("index.php?module=doc_ctl&page=ajax&op=load_doc_details&id=" + id, fillDocDetails);
}

function fillDocDetails(xml) {
  if (!xml) return;
  document.getElementById("rightColumn").innerHTML = xmlText(xml, "htmlContents");
}

function docAction(action) {
  var idEl = document.getElementById("id");
  var id = idEl ? idEl.value : "";
  if (!id) return;
  fetchXml("index.php?module=doc_ctl&page=ajax&op=doc_operation&action=" + action + "&id=" + id, docActionResp);
}

function docActionResp(xml) {
  if (!xml) return;
  if (xmlText(xml, "msg")) alert(xmlText(xml, "msg"));
  fetch_doc(xmlText(xml, "docID"));
  if (xmlText(xml, "action") === "reload_tree" && docTree) docTree.load(WB_TREE_URL);
}

function deleteBookmark(id) {
  fetchXml("index.php?module=doc_ctl&page=ajax&op=doc_operation&fID=rcvBkMkDel&action=del_bookmark&id=" + id, respDelBookmark);
}

function respDelBookmark(xml) {
  if (!xml) return;
  if (xmlText(xml, "msg")) alert(xmlText(xml, "msg"));
  location.reload(true);
}
</script>
