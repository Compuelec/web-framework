/*=============================================
Visual page builder (DnD)

Mounts the visual builder inside the fullscreen Bootstrap modal of the
Generador de Páginas Web. Wired from the "Editar visual" button in
web-pages.php — mounts when the modal becomes visible and tears down
when it's dismissed so SortableJS doesn't leak handlers.

This commit (3/N) lands:
  - palette of static block types (heading, paragraph, image, button,
    divider, rawHtml) — the building blocks that don't depend on a table
  - SortableJS-powered drag from the palette to the canvas
  - SortableJS-powered reorder within the canvas
  - block render as an editable card with delete button
  - selection state for the future props panel

What's intentionally missing (next commits):
  - table-aware blocks (field, fieldImage, list, form, ...): commit 4
  - props panel + live editing of block props:                commit 5
  - debounced live preview via WebPagesCompile + iframe:      commit 6
  - persistence (load/save through web-pages.ajax.php):       commit 7

The JSON shape and compiler contract live in web-pages-compile.js.

Public API consumed by web-pages.php (the host view):

  WebPagesVisual.init(opts)        // one-shot wiring at DOM ready
  WebPagesVisual.mount()           // modal `shown.bs.modal`
  WebPagesVisual.unmount()         // modal `hidden.bs.modal`
  WebPagesVisual.loadTree(tree)    // hydrate from saved blocks JSON
  WebPagesVisual.getTree()         // serialize current state to JSON
  WebPagesVisual.setColumns(list)  // chips refresh when table changes

External dependencies (loaded by web-pages.php):
  - WebPagesCompile (web-pages-compile.js)
  - Sortable        (plugins/sortablejs/Sortable.min.js)
=============================================*/

(function () {
    "use strict";

    /* ---------- block catalogue (static blocks only in commit 3/N) ---------- */
    // Future commits append the table-aware blocks (field, list, form…)
    // so the palette stays a single source of truth.
    var PALETTE = [
        { type: "heading",   label: "Encabezado",     icon: "bi-type-h1" },
        { type: "paragraph", label: "Párrafo",        icon: "bi-text-paragraph" },
        { type: "image",     label: "Imagen",         icon: "bi-image" },
        { type: "button",    label: "Botón",          icon: "bi-square" },
        { type: "divider",   label: "Separador",      icon: "bi-dash-lg" },
        { type: "rawHtml",   label: "HTML libre",     icon: "bi-code" }
    ];

    // Default props for a freshly-dragged block. Kept separate so the
    // catalogue (above) stays declarative.
    function defaultProps(type) {
        switch (type) {
            case "heading":   return { level: 1, text: "Encabezado" };
            case "paragraph": return { text: "Escribe un párrafo aquí." };
            case "image":     return { src: "", alt: "", width: "100%" };
            case "button":    return { text: "Botón", href: "#", style: "primary" };
            case "divider":   return { height: 24 };
            case "rawHtml":   return { html: "<div>tu HTML</div>" };
            default:          return {};
        }
    }

    /* ---------- state ---------- */

    var state = {
        mounted:      false,
        inited:       false,
        tree:         emptyTree(),
        columns:      [],          // current table's column suffixes
        selectedId:   null,        // id of currently selected block
        sortables:    []           // SortableJS instances we created (for teardown)
    };

    function emptyTree() {
        return { version: 1, table: "", blocks: [] };
    }

    function nonce() {
        return "blk_" + Math.random().toString(36).slice(2, 9);
    }

    /* ---------- DOM helpers ---------- */

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (!Object.prototype.hasOwnProperty.call(attrs, k)) { continue; }
                if (k === "className") { node.className = attrs[k]; }
                else if (k === "dataset") {
                    for (var d in attrs[k]) { node.dataset[d] = attrs[k][d]; }
                }
                else if (k === "text") { node.textContent = attrs[k]; }
                else if (k === "html") { node.innerHTML = attrs[k]; }
                else { node.setAttribute(k, attrs[k]); }
            }
        }
        if (children) {
            for (var i = 0; i < children.length; i++) {
                if (children[i] != null) { node.appendChild(children[i]); }
            }
        }
        return node;
    }

    /* ---------- palette render ---------- */

    function renderPalette($palette) {
        $palette.innerHTML = "";
        $palette.appendChild(el("div", {
            className: "small text-muted fw-semibold mb-2 px-1",
            text: "Bloques"
        }));
        var $list = el("div", {
            className: "wpb-palette-list",
            id: "wpb-palette-list"
        });
        PALETTE.forEach(function (item) {
            $list.appendChild(el("div", {
                className: "wpb-palette-item",
                dataset: { paletteType: item.type },
                title: "Arrastra al canvas"
            }, [
                el("i", { className: "bi " + item.icon }),
                el("span", { text: item.label })
            ]));
        });
        $palette.appendChild($list);
    }

    /* ---------- canvas render ---------- */

    function renderBlock(block) {
        var info = describeBlock(block);
        return el("div", {
            className: "wpb-block",
            dataset: { blockId: block.id }
        }, [
            el("div", { className: "d-flex justify-content-between align-items-center" }, [
                el("div", { className: "d-flex align-items-center" }, [
                    el("i", {
                        className: "bi bi-grip-vertical text-muted me-2 wpb-block-handle",
                        title: "Arrastrar para reordenar"
                    }),
                    el("strong", { className: "small", text: info.title })
                ]),
                el("button", {
                    type: "button",
                    className: "btn btn-sm btn-link text-danger p-0 wpb-block-delete",
                    title: "Eliminar bloque",
                    "aria-label": "Eliminar bloque",
                    dataset: { blockId: block.id }
                }, [ el("i", { className: "bi bi-trash" }) ])
            ]),
            el("div", { className: "small text-muted mt-1", text: info.summary })
        ]);
    }

    function describeBlock(block) {
        var p = block.props || {};
        switch (block.type) {
            case "heading":   return { title: "Encabezado H" + p.level, summary: p.text || "(sin texto)" };
            case "paragraph": return { title: "Párrafo",     summary: (p.text || "").slice(0, 80) || "(vacío)" };
            case "image":     return { title: "Imagen",      summary: p.src || "(sin URL)" };
            case "button":    return { title: "Botón",       summary: (p.text || "") + " → " + (p.href || "#") };
            case "divider":   return { title: "Separador",   summary: p.height + " px" };
            case "rawHtml":   return { title: "HTML libre",  summary: (p.html || "").slice(0, 80) };
            default:          return { title: block.type,    summary: "" };
        }
    }

    function renderCanvas($canvas) {
        $canvas.innerHTML = "";
        // The sortable list always exists, even when empty — Sortable needs
        // a real DOM target, and the empty-state copy lives inside it as a
        // child so it shrinks/disappears as soon as blocks are dropped.
        var $list = el("div", {
            className: "wpb-canvas-list",
            id: "wpb-canvas-list"
        });
        if (!state.tree.blocks.length) {
            // Empty-state lives INSIDE the sortable list so the drop zone
            // visually covers it. `data-sortable-ignore` tells our config
            // to skip it when computing the new index of a drop, and
            // pointer-events:none lets the underlying list catch the drop.
            $list.appendChild(el("div", {
                className: "text-center text-muted small py-5 wpb-canvas-empty",
                "data-sortable-ignore": "1",
                style: "pointer-events:none",
                html: '<i class="bi bi-arrow-down-square d-block fs-1 mb-2"></i>' +
                      'Arrastrá un bloque desde la paleta para empezar'
            }));
        } else {
            state.tree.blocks.forEach(function (b) { $list.appendChild(renderBlock(b)); });
        }
        $canvas.appendChild($list);
    }

    /* ---------- sortable wiring ---------- */

    function teardownSortables() {
        state.sortables.forEach(function (s) {
            try { s.destroy(); } catch (e) { /* ignore */ }
        });
        state.sortables = [];
    }

    function wireSortables() {
        teardownSortables();
        if (!window.Sortable) {
            console.warn("[wpb-visual] SortableJS not loaded");
            return;
        }

        // Palette → canvas: clone mode so palette items stay put. Drops on
        // the canvas trigger onAdd, where we replace the cloned DOM with a
        // real block-card backed by state.tree.
        var $palette = document.getElementById("wpb-palette-list");
        if ($palette) {
            state.sortables.push(window.Sortable.create($palette, {
                group: { name: "wpb-blocks", pull: "clone", put: false },
                sort: false,
                animation: 150
            }));
        }

        // Canvas root list — accepts palette drops and intra-canvas reorder.
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            state.sortables.push(window.Sortable.create($canvasList, {
                group: { name: "wpb-blocks", pull: false, put: true },
                handle: ".wpb-block-handle",
                draggable: ".wpb-block",
                animation: 150,
                onAdd: onPaletteDrop,
                onUpdate: onCanvasReorder
            }));
        }
    }

    function onPaletteDrop(evt) {
        // Sortable's clone (the dropped DOM node) carries the type via
        // dataset.paletteType. Replace it with a freshly-built block in
        // state.tree, then re-render so the DOM mirrors the source of truth.
        var $node = evt.item;
        var type = $node && $node.dataset && $node.dataset.paletteType;
        if (!type) { return; }

        var newBlock = {
            id:    nonce(),
            type:  type,
            props: defaultProps(type)
        };
        var idx = evt.newIndex == null ? state.tree.blocks.length : evt.newIndex;
        state.tree.blocks.splice(idx, 0, newBlock);
        // Throw away SortableJS's clone — we re-render from state.tree.
        if ($node.parentNode) { $node.parentNode.removeChild($node); }
        // Select the new block so the future props panel jumps to it.
        state.selectedId = newBlock.id;
        rerender();
    }

    function onCanvasReorder(evt) {
        var oldIdx = evt.oldIndex;
        var newIdx = evt.newIndex;
        if (oldIdx == null || newIdx == null || oldIdx === newIdx) { return; }
        var moved = state.tree.blocks.splice(oldIdx, 1)[0];
        state.tree.blocks.splice(newIdx, 0, moved);
        // SortableJS already moved the DOM. Re-render anyway to stay
        // defensively in sync with state.tree.
        rerender();
    }

    /* ---------- canvas events (delete, select) ---------- */

    function wireCanvasEvents() {
        var $canvas = document.getElementById("wpb-canvas");
        if (!$canvas || $canvas.dataset.wpbWired === "1") { return; }
        $canvas.dataset.wpbWired = "1";

        $canvas.addEventListener("click", function (e) {
            var $del = e.target.closest(".wpb-block-delete");
            if ($del) {
                e.preventDefault();
                e.stopPropagation();
                deleteBlock($del.dataset.blockId);
                return;
            }
            var $blk = e.target.closest(".wpb-block");
            if ($blk) {
                selectBlock($blk.dataset.blockId);
            }
        });
    }

    function deleteBlock(id) {
        var idx = state.tree.blocks.findIndex(function (b) { return b.id === id; });
        if (idx === -1) { return; }
        state.tree.blocks.splice(idx, 1);
        if (state.selectedId === id) { state.selectedId = null; }
        rerender();
    }

    function selectBlock(id) {
        state.selectedId = id;
        document.querySelectorAll("#wpb-canvas .wpb-block").forEach(function ($el) {
            $el.classList.toggle("is-selected", $el.dataset.blockId === id);
        });
        // Props panel update comes in commit 5/N.
    }

    /* ---------- top-level render ---------- */

    function rerender() {
        var $palette = document.getElementById("wpb-palette");
        var $canvas  = document.getElementById("wpb-canvas");
        if (!$palette || !$canvas) { return; }
        renderPalette($palette);
        renderCanvas($canvas);
        wireSortables();
        wireCanvasEvents();
        if (state.selectedId) { selectBlock(state.selectedId); }
    }

    /* ---------- public API ---------- */

    function init(/* opts */) {
        // Idempotent — the modal lifecycle script calls us on every mount.
        state.inited = true;
    }

    function mount() {
        if (state.mounted) { return; }
        state.mounted = true;
        rerender();
    }

    function unmount() {
        state.mounted = false;
        teardownSortables();
        // Keep state.tree intact — re-opening the modal should restore the
        // user's work without a round-trip to the server.
    }

    function loadTree(tree) {
        if (tree && typeof tree === "object" && tree.version === 1) {
            // Defensive: ensure every block has an id (older saved trees
            // might not, and we use ids for DnD identity).
            tree.blocks = (tree.blocks || []).map(function (b) {
                return b.id ? b : Object.assign({ id: nonce() }, b);
            });
            state.tree = tree;
        } else {
            state.tree = emptyTree();
        }
        if (state.mounted) { rerender(); }
    }

    function getTree() {
        return JSON.parse(JSON.stringify(state.tree));
    }

    function setColumns(list) {
        state.columns = Array.isArray(list) ? list.slice() : [];
        // Once the palette gets table-aware chips (commit 4/N) this will
        // trigger a re-render of the palette.
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

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { init(); });
    } else {
        init();
    }
})();
