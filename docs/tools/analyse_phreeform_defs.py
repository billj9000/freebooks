#!/usr/bin/env python3
"""Inventory the shipped PhreeForm report/form definitions.

This is the analysis behind section 6 ("Catalog inventory") of
docs/forms-design.md. It is a design/planning aid, not part of the running
application -- it reads the shipped XML definitions and reports:

  * the join graph, reduced to connected components (candidate datasets)
  * every distinct table.column reference (the field catalog)
  * computed/expression fields, which can only live in code-defined datasets
  * special_class documents, which become DocumentProviders
  * processing hints, which become the typed-value format vocabulary
  * conversion blockers worth knowing about before writing the converter

Read-only. Stdlib only. Safe to re-run whenever the shipped definitions
change -- the numbers quoted in forms-design.md come straight from here, so
re-run it before revising that section.

Usage:
    docs/tools/analyse_phreeform_defs.py [REPO_ROOT] [--json OUT.json]
"""
import glob
import json
import os
import re
import sys
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict

# Definitions ship per-module; both trees use the same layout.
GLOBS = (
    "trunk/modules/*/language/en_us/reports/*",
    "extensions/Modules/*/language/en_us/reports/*",
)

# A join we can reduce to an edge: "a.col = b.col"
JOIN_RE = re.compile(r"([A-Za-z0-9_]+)\.[A-Za-z0-9_]+\s*=\s*([A-Za-z0-9_]+)\.[A-Za-z0-9_]+")
QUALIFIED = re.compile(r"^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$")   # table.column
UNQUALIFIED = re.compile(r"^[A-Za-z0-9_]+$")                # COMPANY_* const or provider output


def text_of(el):
    return (el.text or "").strip() if el is not None else ""


def parse_definitions(root):
    """Return (docs, parse_errors). Each doc is a plain dict."""
    docs, errors = [], []
    paths = sorted(p for g in GLOBS for p in glob.glob(os.path.join(root, g)))
    for path in paths:
        if os.path.isdir(path):
            continue
        try:
            raw = open(path, encoding="utf-8", errors="surrogateescape").read()
            xml = ET.fromstring(raw)
        except Exception as exc:                        # noqa: BLE001 - report, don't crash
            errors.append((path, str(exc)[:80]))
            continue

        doc = {
            "path": os.path.relpath(path, root),
            "module": path.split("/language/")[0].split("/")[-1],
            "file": os.path.basename(path),
            "title": text_of(xml.find("title")),
            "type": text_of(xml.find("reporttype")),
            "special": text_of(xml.find("special_class")),
            "tables": [], "joins": [], "fields": [], "filters": [],
            "groups": [], "sorts": [], "processing": [], "elements": [],
        }
        for tbl in xml.findall("tables"):
            name, rel = text_of(tbl.find("tablename")), text_of(tbl.find("relationship"))
            if name:
                doc["tables"].append(name)
            if rel:
                doc["joins"].append(rel)
        for fld in xml.findall("fieldlist"):
            name = text_of(fld.find("fieldname"))
            if name:
                doc["fields"].append(name)
            proc = text_of(fld.find("processing"))
            if proc:
                doc["processing"].append(proc)
            etype = text_of(fld.find("type"))
            if etype:
                doc["elements"].append(etype)
            # Form elements nest their real column refs inside <boxfield>.
            for box in fld.findall("boxfield"):
                boxname = text_of(box.find("fieldname"))
                if boxname:
                    doc["fields"].append(boxname)
        for tag, key in (("filterlist", "filters"), ("grouplist", "groups"), ("sortlist", "sorts")):
            for el in xml.findall(tag):
                name = text_of(el.find("fieldname"))
                if name:
                    doc[key].append(name)
        docs.append(doc)
    return docs, errors


def join_components(docs):
    """Reduce every join to an edge, then return (components, edges, unparsed)."""
    edges, unparsed = set(), []
    for doc in docs:
        for join in doc["joins"]:
            found = JOIN_RE.findall(join)
            if found:
                edges.update(tuple(sorted(pair)) for pair in found)
            else:
                unparsed.append((doc["file"], join))

    adjacency = defaultdict(set)
    for left, right in edges:
        adjacency[left].add(right)
        adjacency[right].add(left)

    tables = {t for doc in docs for t in doc["tables"]}
    seen, components = set(), []
    for table in sorted(tables):
        if table in seen:
            continue
        stack, group = [table], set()
        while stack:
            node = stack.pop()
            if node in group:
                continue
            group.add(node)
            seen.add(node)
            stack.extend(adjacency[node] - group)
        components.append(sorted(group))
    components.sort(key=len, reverse=True)
    return components, edges, unparsed


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    root = os.path.abspath(args[0]) if args else os.path.abspath(
        os.path.join(os.path.dirname(__file__), "..", ".."))
    json_out = None
    if "--json" in sys.argv:
        idx = sys.argv.index("--json")
        json_out = sys.argv[idx + 1] if idx + 1 < len(sys.argv) else "forms_inventory.json"

    docs, errors = parse_definitions(root)
    if not docs:
        sys.exit(f"No definitions found under {root} - is this the repo root?")
    print(f"repo: {root}")
    print(f"parsed {len(docs)} definitions ({len(errors)} parse errors)")
    for path, exc in errors:
        print(f"   !! {os.path.basename(path)}: {exc}")

    components, edges, unparsed = join_components(docs)
    print(f"\n=== JOIN GRAPH: {len(edges)} distinct edges -> {len(components)} components "
          f"(candidate datasets) ===")
    for i, group in enumerate(components, 1):
        users = sum(1 for d in docs if set(d["tables"]) & set(group))
        print(f"  D{i}: {'+'.join(group)}   [{users} docs]")
    if unparsed:
        print(f"\n  !! {len(unparsed)} join(s) not reducible to 'a.x = b.y' "
              f"(need a code-defined dataset):")
        for file, join in unparsed[:10]:
            print(f"     {file}: {join[:90]}")

    def components_of(tables):
        return {i for i, g in enumerate(components, 1) if set(tables) & set(g)}

    spanning = [d for d in docs if len(components_of(d["tables"])) > 1]
    print(f"\n=== CROSS-COMPONENT DOCS (need a dataset spanning >1 component): {len(spanning)} ===")
    for doc in spanning:
        print(f"  {doc['file']:<28} {doc['title'][:38]:<40} {sorted(components_of(doc['tables']))}")

    qualified, unqualified, computed = Counter(), Counter(), []
    for doc in docs:
        for name in doc["fields"] + doc["filters"] + doc["groups"] + doc["sorts"]:
            if QUALIFIED.match(name):
                qualified[name] += 1
            elif UNQUALIFIED.match(name):
                unqualified[name] += 1
            else:
                computed.append((doc["file"], name))

    print("\n=== FIELD CATALOG ===")
    print(f"  distinct table.column refs                 : {len(qualified)}")
    print(f"  distinct unqualified refs (consts/provider): {len(unqualified)}")
    print(f"  computed/expression fields                 : {len(computed)} uses, "
          f"{len(set(n for _, n in computed))} distinct")
    print("\n  columns referenced per table:")
    for table, count in Counter(f.split(".")[0] for f in qualified).most_common():
        print(f"    {table:<32} {count}")
    if computed:
        print("\n  computed fields (code-defined datasets only - never the admin UI):")
        for file, name in list(dict.fromkeys(computed)):
            print(f"     {file}: {name[:88]}")

    print("\n=== SCOPE ===")
    print(f"  by type: {dict(Counter(d['type'] for d in docs))}")
    providers = defaultdict(list)
    for doc in docs:
        if doc["special"]:
            providers[doc["special"]].append(doc["type"])
    print(f"  special_class docs: {sum(len(v) for v in providers.values())} "
          f"across {len(providers)} providers (each becomes a DocumentProvider)")
    for name in sorted(providers):
        kinds = ",".join(sorted(set(providers[name])))
        print(f"     {name:<20} [{kinds}] x{len(providers[name])}")
    query_driven = [d for d in docs if not d["special"]]
    print(f"  query-driven docs (the dataset/catalog work): {len(query_driven)} "
          f"{dict(Counter(d['type'] for d in query_driven))}")

    print("\n=== FORM ELEMENT VOCABULARY (LayoutDocument must express all of these) ===")
    element_docs = defaultdict(set)
    for doc in docs:
        if doc["type"] == "frm":
            for el in doc["elements"]:
                element_docs[el].add(doc["file"])
    counts = Counter(el for d in docs if d["type"] == "frm" for el in d["elements"])
    for el, n in counts.most_common():
        print(f"  {el:<8} {n:>4}   ({len(element_docs[el])} forms)")

    print("\n=== PROCESSING HINTS (become the typed-value format vocabulary) ===")
    print(f"  {dict(Counter(p for d in docs for p in d['processing']))}")

    if json_out:
        with open(json_out, "w") as fh:
            json.dump({"components": components, "docs": docs}, fh, indent=1)
        print(f"\n[inventory written to {json_out}]")


if __name__ == "__main__":
    main()
