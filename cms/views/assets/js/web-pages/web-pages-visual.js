/*=============================================
Visual page builder (DnD)

Mounts the visual builder next to the existing code-mode editor of the
Generador de Páginas Web. The user toggles between modes from the card
header; this file is loaded lazily only when Visual mode is entered the
first time during a session.

This file is intentionally a skeleton in this commit. Subsequent commits
of `feat/cms-page-builder-visual` flesh it out in this order:

  1. mount() / unmount() — show/hide the visual surface, wire SortableJS
  2. renderCanvas(blocks) — recursive DOM render from the JSON tree
  3. palette events — drag a block / a column chip into the canvas
  4. props panel — edit `props` of the selected block
  5. preview — debounced compile via WebPagesCompile + iframe refresh
  6. serialize/deserialize — wire to web-pages.ajax.php save/load

Public API consumed by web-pages.php (the host view) and by the legacy
web-pages.js (which delegates to us when builderMode === "visual"):

  WebPagesVisual.init(opts)        // one-shot wiring at DOM ready
  WebPagesVisual.mount()           // user clicked toggle → Visual
  WebPagesVisual.unmount()         // user clicked toggle → Código
  WebPagesVisual.loadTree(tree)    // hydrate from saved blocks JSON
  WebPagesVisual.getTree()         // serialize current state to JSON
  WebPagesVisual.setColumns(list)  // chips refresh when table changes

The compiler is `window.WebPagesCompile` (web-pages-compile.js).
=============================================*/

(function ($) {
    "use strict";

    var state = {
        mounted:  false,
        tree:     emptyTree(),
        columns:  [],          // current table's column suffixes
        selected: null         // id of currently selected block
    };

    function emptyTree() {
        return { version: 1, table: "", blocks: [] };
    }

    /* ---------- public API (no-ops until implemented) ---------- */

    function init(/* opts */) {
        // Wires the toggle button and the empty placeholders. Subsequent
        // commits attach real handlers.
        state.mounted = false;
    }

    function mount() {
        // Show the visual surface, hide CodeMirror. Real implementation
        // comes in the next commit.
        state.mounted = true;
    }

    function unmount() {
        state.mounted = false;
    }

    function loadTree(tree) {
        if (tree && typeof tree === "object" && tree.version === 1) {
            state.tree = tree;
        } else {
            state.tree = emptyTree();
        }
    }

    function getTree() {
        // Deep clone so the caller can't mutate our state by reference.
        return JSON.parse(JSON.stringify(state.tree));
    }

    function setColumns(list) {
        state.columns = Array.isArray(list) ? list.slice() : [];
    }

    /* ---------- export ---------- */

    window.WebPagesVisual = {
        init:        init,
        mount:       mount,
        unmount:     unmount,
        loadTree:    loadTree,
        getTree:     getTree,
        setColumns:  setColumns
    };
})(jQuery);
