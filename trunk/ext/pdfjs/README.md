# pdf.js

Vendored, not built. Two files from the npm package `pdfjs-dist`, plus its licence.

| | |
|---|---|
| Version | 6.2.108 |
| Source | `https://registry.npmjs.org/pdfjs-dist/-/pdfjs-dist-6.2.108.tgz` |
| Licence | Apache-2.0 — see `LICENSE`, and the `@licstart` banner inside each file |
| Upstream | https://github.com/mozilla/pdf.js |

```
package/legacy/build/pdf.min.mjs        -> pdf.min.mjs         (504K)
package/legacy/build/pdf.worker.min.mjs -> pdf.worker.min.mjs  (1.3M)
package/LICENSE                         -> LICENSE
```

The **legacy** build is taken deliberately: the modern one assumes a bundler and
newer syntax, and FreeBooks ships assets as files to be copied to a web root
with no build step (see CLAUDE.md).

## What it is for

The Forms module's form canvas renders a form to a real PDF through
`LayoutPdfRenderer`, rasterizes it here in the browser, and draws the element
outlines on a transparent layer above it — so an author positions boxes against
the true output rather than against a CSS impression of it
(`docs/forms-design.md` §13.2, decision H).

Rasterizing in the browser rather than on the server is not a preference. The
deployment targets have **no Imagick, no Ghostscript and no `pdftoppm`**, so a
server-side rasterizer would be a new system dependency on every installation.
This needs nothing of the server.

## Do not add the FreeBooks header to these files

They are third-party and carry their own licence banner. See the File Header
Policy in CLAUDE.md: asserting FreeBooks copyright over Apache-2.0 code
misstates authorship.

## Upgrading

Download the tarball, extract the same three paths, and check that
`docs/tools/verify_forms_canvas.php` still passes — it pins the geometry the
overlay depends on, which is the thing an upgrade could silently change.

The worker path is set in `modules/forms/pages/canvas/js_include.php`. Both
files must be served from the same directory; the worker is loaded as a module
worker by URL, not bundled.
