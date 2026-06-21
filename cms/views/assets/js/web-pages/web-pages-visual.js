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

    function renderCanvas($canvasList) {
        // Updates the contents of the sortable list IN PLACE — does NOT
        // replace the list element itself, because SortableJS holds a
        // reference to it and would lose its handlers if we swap it out.
        // Same reason for re-using the same #wpb-canvas-list across calls.
        $canvasList.innerHTML = "";
        if (!state.tree.blocks.length) {
            // Empty-state lives INSIDE the sortable list so the drop zone
            // visually covers it. pointer-events:none lets the underlying
            // list catch the drop, and `data-sortable-skip` keeps Sortable
            // from including it in its draggable items (via the `filter`
            // option on the Sortable config below).
            $canvasList.appendChild(el("div", {
                className: "text-center text-muted small py-5 wpb-canvas-empty",
                "data-sortable-skip": "1",
                style: "pointer-events:none",
                html: '<i class="bi bi-arrow-down-square d-block fs-1 mb-2"></i>' +
                      'Arrastrá un bloque desde la paleta para empezar'
            }));
        } else {
            state.tree.blocks.forEach(function (b) {
                $canvasList.appendChild(renderBlock(b));
            });
        }
    }

    /* ---------- sortable wiring ---------- */

    // SortableJS holds DOM references; destroying/recreating instances on
    // every state change is what made the second drop after the first one
    // fail. We create one palette+canvas pair when the modal mounts and
    // keep them alive until unmount.

    function teardownSortables() {
        state.sortables.forEach(function (s) {
            try { s.destroy(); } catch (e) { /* ignore */ }
        });
        state.sortables = [];
    }

    function ensureSortables() {
        if (state.sortables.length) { return; }
        if (!window.Sortable) {
            console.warn("[wpb-visual] SortableJS not loaded");
            return;
        }

        var $palette = document.getElementById("wpb-palette-list");
        if ($palette) {
            state.sortables.push(window.Sortable.create($palette, {
                group: { name: "wpb-blocks", pull: "clone", put: false },
                sort: false,
                animation: 150
            }));
        }

        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            state.sortables.push(window.Sortable.create($canvasList, {
                group: { name: "wpb-blocks", pull: false, put: true },
                handle: ".wpb-block-handle",
                draggable: ".wpb-block",
                filter: "[data-sortable-skip]",
                animation: 150,
                onAdd: onPaletteDrop,
                onUpdate: onCanvasReorder
            }));
        }
    }

    function onPaletteDrop(evt) {
        // Sortable's clone (the dropped DOM node) carries the type via
        // dataset.paletteType. We add the block to state.tree and refresh
        // the canvas list's CONTENT in place — the list element itself
        // (which Sortable is still wired to) is NOT recreated.
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
        state.selectedId = newBlock.id;

        // Refresh the canvas list contents from state.tree. The cloned
        // palette item is removed in the process. Doing this AFTER
        // SortableJS has finished processing the drop avoids the
        // teardown-mid-drag bug that left the second drop unresponsive.
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            renderCanvas($canvasList);
            applySelectionClass();
        }
    }

    function onCanvasReorder(evt) {
        var oldIdx = evt.oldIndex;
        var newIdx = evt.newIndex;
        if (oldIdx == null || newIdx == null || oldIdx === newIdx) { return; }
        var moved = state.tree.blocks.splice(oldIdx, 1)[0];
        state.tree.blocks.splice(newIdx, 0, moved);
        // SortableJS already moved the DOM, and the indexes match
        // state.tree, so no re-render is needed — the next time we render
        // from state.tree (delete/new block/etc) order stays consistent.
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
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            renderCanvas($canvasList);
            applySelectionClass();
        }
    }

    function selectBlock(id) {
        state.selectedId = id;
        applySelectionClass();
        // Props panel update comes in the next commit (props panel).
    }

    function applySelectionClass() {
        var id = state.selectedId;
        document.querySelectorAll("#wpb-canvas-list .wpb-block").forEach(function ($el) {
            $el.classList.toggle("is-selected", id != null && $el.dataset.blockId === id);
        });
    }

    /* ---------- top-level render ---------- */

    // Full render — only called when the host containers are missing
    // (first mount, or after the modal was torn down). Subsequent
    // mutations call renderCanvas($canvasList) directly to avoid
    // recreating the Sortable host nodes.
    function fullRender() {
        var $palette = document.getElementById("wpb-palette");
        var $canvas  = document.getElementById("wpb-canvas");
        if (!$palette || !$canvas) { return; }

        renderPalette($palette);

        // Create the canvas list ONCE; mutations only refresh its contents.
        $canvas.innerHTML = "";
        var $canvasList = el("div", {
            className: "wpb-canvas-list",
            id: "wpb-canvas-list"
        });
        $canvas.appendChild($canvasList);
        renderCanvas($canvasList);

        wireCanvasEvents();
        ensureSortables();
        applySelectionClass();
    }

    /* ---------- public API ---------- */

    function init(/* opts */) {
        // Idempotent — the modal lifecycle script calls us on every mount.
        state.inited = true;
    }

    function mount() {
        if (state.mounted) { return; }
        state.mounted = true;
        fullRender();
    }

    function unmount() {
        state.mounted = false;
        teardownSortables();
        // Clear wired flag so the next mount re-wires events on the
        // (possibly new) canvas DOM.
        var $canvas = document.getElementById("wpb-canvas");
        if ($canvas) { delete $canvas.dataset.wpbWired; }
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
        if (state.mounted) {
            var $canvasList = document.getElementById("wpb-canvas-list");
            if ($canvasList) {
                renderCanvas($canvasList);
                applySelectionClass();
            } else {
                fullRender();
            }
        }
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
