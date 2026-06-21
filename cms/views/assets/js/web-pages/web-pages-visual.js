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
        sortables:    [],          // SortableJS instances we created (for teardown)
        view:         "editor",    // "editor" | "preview"
        previewTimer: null         // debounce handle for preview refresh
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

    /* ---------- props panel ---------- */

    // Per-type schema. Each entry is the list of editable props for a
    // block type, with the input kind to render. Keeping this declarative
    // means adding a new prop is one line — no new DOM-building code.
    //
    // Supported kinds:
    //   text        single-line text input
    //   textarea    multi-line text input (small)
    //   code        multi-line monospace input (bigger, for rawHtml)
    //   url         like text but with type="url"
    //   number      numeric input with optional min/max/step
    //   select      <select> from a list of { value, label } options
    var PROPS_SCHEMA = {
        heading: [
            { key: "text",  kind: "text",   label: "Texto" },
            { key: "level", kind: "select", label: "Nivel",
              options: [
                  { value: 1, label: "H1 (más grande)" },
                  { value: 2, label: "H2" },
                  { value: 3, label: "H3" },
                  { value: 4, label: "H4" },
                  { value: 5, label: "H5" },
                  { value: 6, label: "H6 (más chico)" }
              ], coerce: "int" }
        ],
        paragraph: [
            { key: "text", kind: "textarea", label: "Texto", rows: 4 }
        ],
        image: [
            { key: "src",   kind: "url",  label: "URL de la imagen",
              placeholder: "https://… o /uploads/foo.jpg" },
            { key: "alt",   kind: "text", label: "Texto alternativo (alt)",
              placeholder: "Descripción para lectores de pantalla" },
            { key: "width", kind: "text", label: "Ancho",
              placeholder: "100% · 320px · auto" }
        ],
        button: [
            { key: "text",  kind: "text", label: "Texto del botón" },
            { key: "href",  kind: "url",  label: "Enlace (URL)" },
            { key: "style", kind: "select", label: "Estilo",
              options: [
                  { value: "primary",   label: "Primario (azul)" },
                  { value: "secondary", label: "Secundario (gris)" }
              ] }
        ],
        divider: [
            { key: "height", kind: "number", label: "Altura (píxeles)",
              min: 1, max: 400, step: 1, coerce: "int" }
        ],
        rawHtml: [
            { key: "html", kind: "code", label: "HTML / CSS / JS inline",
              rows: 10, placeholder: "<div class=\"mi-bloque\">…</div>" }
        ]
    };

    function renderProps() {
        var $props = document.getElementById("wpb-props");
        if (!$props) { return; }
        $props.innerHTML = "";

        var block = currentBlock();
        if (!block) {
            $props.appendChild(el("div", {
                className: "small text-muted text-center py-4 px-2",
                html: '<i class="bi bi-sliders d-block fs-2 mb-2"></i>' +
                      'Seleccioná un bloque<br>para editar sus propiedades'
            }));
            return;
        }

        $props.appendChild(el("div", {
            className: "small text-muted fw-semibold mb-2 px-1",
            text: describeBlock(block).title
        }));

        var schema = PROPS_SCHEMA[block.type] || [];
        var $form = el("form", {
            className: "wpb-props-form",
            id: "wpb-props-form",
            dataset: { blockId: block.id },
            // `autocomplete="off"` because the inputs are content, not
            // user credentials, and we don't want browser autofill to mess
            // with placeholders.
            autocomplete: "off"
        });

        schema.forEach(function (field) {
            $form.appendChild(renderField(field, block));
        });

        $props.appendChild($form);
    }

    function renderField(field, block) {
        var value = block.props ? block.props[field.key] : "";
        var $wrap = el("div", { className: "mb-3 wpb-field",
            dataset: { propKey: field.key, propCoerce: field.coerce || "" } });
        $wrap.appendChild(el("label", {
            className: "form-label small fw-semibold mb-1",
            text: field.label
        }));

        var $input;
        switch (field.kind) {
            case "select":
                $input = el("select", { className: "form-select form-select-sm wpb-prop-input" });
                (field.options || []).forEach(function (opt) {
                    var $opt = el("option", { value: String(opt.value), text: opt.label });
                    if (String(value) === String(opt.value)) { $opt.selected = true; }
                    $input.appendChild($opt);
                });
                break;
            case "textarea":
            case "code":
                $input = el("textarea", {
                    className: "form-control form-control-sm wpb-prop-input" +
                               (field.kind === "code" ? " font-monospace" : ""),
                    rows: String(field.rows || 3),
                    placeholder: field.placeholder || ""
                });
                $input.value = value == null ? "" : value;
                break;
            case "number":
                $input = el("input", {
                    type: "number",
                    className: "form-control form-control-sm wpb-prop-input"
                });
                if (field.min  != null) { $input.min  = String(field.min); }
                if (field.max  != null) { $input.max  = String(field.max); }
                if (field.step != null) { $input.step = String(field.step); }
                $input.value = value == null ? "" : value;
                break;
            case "url":
                $input = el("input", {
                    type: "url",
                    className: "form-control form-control-sm wpb-prop-input",
                    placeholder: field.placeholder || ""
                });
                $input.value = value == null ? "" : value;
                break;
            default: // text
                $input = el("input", {
                    type: "text",
                    className: "form-control form-control-sm wpb-prop-input",
                    placeholder: field.placeholder || ""
                });
                $input.value = value == null ? "" : value;
        }

        $wrap.appendChild($input);
        return $wrap;
    }

    function currentBlock() {
        if (!state.selectedId) { return null; }
        return state.tree.blocks.find(function (b) {
            return b.id === state.selectedId;
        }) || null;
    }

    // Refresh just the editable card of a single block — used after a
    // props edit so the summary in the canvas updates without touching
    // SortableJS bookkeeping.
    function refreshBlockCard(blockId) {
        var block = state.tree.blocks.find(function (b) { return b.id === blockId; });
        if (!block) { return; }
        var $old = document.querySelector(
            '#wpb-canvas-list .wpb-block[data-block-id="' + cssEscape(blockId) + '"]'
        );
        if (!$old) { return; }
        var $new = renderBlock(block);
        if (state.selectedId === blockId) { $new.classList.add("is-selected"); }
        $old.parentNode.replaceChild($new, $old);
    }

    // Minimal CSS.escape polyfill for the few cases where SortableJS
    // doesn't bring one. Our ids only contain [a-z0-9_], so this is enough.
    function cssEscape(s) {
        return String(s).replace(/([^\w-])/g, "\\$1");
    }

    function wirePropsEvents() {
        var $props = document.getElementById("wpb-props");
        if (!$props || $props.dataset.wpbWired === "1") { return; }
        $props.dataset.wpbWired = "1";

        // We use `input` on the props panel for live update — every
        // keystroke updates state.tree and refreshes the block's card.
        // `change` is also bound to catch select/number values when the
        // user blurs the input.
        function onAnyChange(e) {
            var $input = e.target.closest(".wpb-prop-input");
            if (!$input) { return; }
            var $form = $input.closest("#wpb-props-form");
            if (!$form) { return; }
            var blockId = $form.dataset.blockId;
            var $wrap = $input.closest(".wpb-field");
            if (!$wrap) { return; }
            var key    = $wrap.dataset.propKey;
            var coerce = $wrap.dataset.propCoerce;

            var raw = $input.value;
            var value;
            if (coerce === "int") {
                var n = parseInt(raw, 10);
                value = Number.isFinite(n) ? n : 0;
            } else {
                value = raw;
            }

            var block = state.tree.blocks.find(function (b) { return b.id === blockId; });
            if (!block) { return; }
            block.props = block.props || {};
            block.props[key] = value;

            refreshBlockCard(blockId);
            // Re-render the props header (e.g. "Encabezado H2" reflects
            // the new level immediately). The form itself doesn't need
            // re-rendering because the input already has the new value.
            var $header = $props.querySelector(".fw-semibold");
            if ($header) { $header.textContent = describeBlock(block).title; }
            // If the preview is visible, schedule a refresh so the user
            // sees their edit live.
            schedulePreviewRefresh();
        }
        $props.addEventListener("input",  onAnyChange);
        $props.addEventListener("change", onAnyChange);
    }

    /* ---------- preview iframe ---------- */
    // The preview is built from state.tree → WebPagesCompile.compileTree
    // (the same compiler the save flow will use). We wrap the resulting
    // template in a minimal HTML document that loads the CMS Bootstrap so
    // .row / .col-md-* / .btn classes look like they will on the public
    // site. CodeMirror-style live updates: while in preview view, edits
    // re-render the iframe after a small debounce.

    var PREVIEW_DEBOUNCE_MS = 200;

    function schedulePreviewRefresh() {
        if (state.view !== "preview") { return; }
        if (state.previewTimer) {
            clearTimeout(state.previewTimer);
        }
        state.previewTimer = setTimeout(function () {
            state.previewTimer = null;
            renderPreview();
        }, PREVIEW_DEBOUNCE_MS);
    }

    function compileForPreview() {
        if (!window.WebPagesCompile) {
            return { template: "", customCss: "",
                     warnings: ["WebPagesCompile no está cargado"] };
        }
        try {
            return window.WebPagesCompile.compileTree(state.tree);
        } catch (err) {
            return { template:  '<pre style="color:#b91c1c;padding:1rem;">' +
                                'Error de compilación:\n' +
                                String(err && err.message || err) + '</pre>',
                     customCss: "",
                     warnings:  [String(err && err.message || err)] };
        }
    }

    function buildPreviewDoc(compiled) {
        var assets = window.CMS_ASSETS_PATH || "";
        var bootstrap = assets + "/plugins/bootstrap5/bootstrap.min.css";
        // Inline CSS user wrote in rawHtml + customCss compiled from blocks.
        var css = compiled.customCss || "";

        // Origin so the iframe can resolve the bootstrap path even though
        // it has no base URL of its own (srcdoc documents resolve relative
        // URLs against `about:srcdoc`, which is useless).
        var origin = window.location.origin;
        if (bootstrap && bootstrap.indexOf("//") === -1) {
            bootstrap = origin + bootstrap;
        }

        // Minimal Bootstrap-host doc. `.wpb-preview-wrap` mimics a Bootstrap
        // container so blocks look like they would in a real page body.
        var emptyHint = state.tree.blocks.length ? "" :
            '<div class="text-center text-muted py-5">' +
            '<div style="font-size:2rem;margin-bottom:.5rem">👀</div>' +
            'Tu página aparecerá acá cuando agregues bloques.' +
            '</div>';

        return '<!doctype html>' +
            '<html lang="es"><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<link rel="stylesheet" href="' + escAttr(bootstrap) + '">' +
            '<style>' +
            'body{padding:1.5rem;background:#fff;}' +
            css +
            '</style>' +
            '</head><body>' +
            '<div class="container wpb-preview-wrap">' +
            (compiled.template || emptyHint) +
            '</div>' +
            '</body></html>';
    }

    function escAttr(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;");
    }

    function renderPreview() {
        var $iframe = document.getElementById("wpb-preview-frame");
        if (!$iframe) { return; }
        var compiled = compileForPreview();
        $iframe.srcdoc = buildPreviewDoc(compiled);
    }

    function setView(view) {
        if (view !== "editor" && view !== "preview") { view = "editor"; }
        state.view = view;
        var $canvas  = document.getElementById("wpb-canvas");
        var $iframe  = document.getElementById("wpb-preview-frame");
        // The palette and props panel stay visible in both views — only
        // the center cell swaps between editor and iframe.
        if ($canvas) { $canvas.style.display = (view === "editor")  ? "" : "none"; }
        if ($iframe) { $iframe.style.display = (view === "preview") ? "" : "none"; }
        if (view === "preview") {
            // Render immediately on enter, then debounce on edits.
            renderPreview();
        }
        // Keep the radios in sync if setView was called from code.
        var $radio = document.querySelector('input[name="wpb-view"][value="' + view + '"]');
        if ($radio) { $radio.checked = true; }
    }

    function wireViewToggle() {
        document.querySelectorAll('input[name="wpb-view"]').forEach(function (el) {
            // Guard against rewiring on every fullRender.
            if (el.dataset.wpbWired === "1") { return; }
            el.dataset.wpbWired = "1";
            el.addEventListener("change", function () {
                setView(this.value);
            });
        });
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
        // The new block is auto-selected — jump the props panel to it.
        renderProps();
        updateSaveButtonState();
        schedulePreviewRefresh();
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
        schedulePreviewRefresh();
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
        renderProps();
        updateSaveButtonState();
        schedulePreviewRefresh();
    }

    function selectBlock(id) {
        state.selectedId = id;
        applySelectionClass();
        renderProps();
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
        wirePropsEvents();
        wireViewToggle();
        wireSaveButton();
        wireNameSync();
        ensureSortables();
        applySelectionClass();
        renderProps();
        setView(state.view);
        updateSaveButtonState();
    }

    /* ---------- public API ---------- */

    function init(/* opts */) {
        // Idempotent — the modal lifecycle script calls us on every mount.
        if (state.inited) { return; }
        state.inited = true;

        // When the host (code-mode editor) loads a page, hydrate the
        // visual tree from its config so reopening the modal shows what
        // the user saved. The opposite (Save) is wired in saveBlocks().
        document.addEventListener("wpb:config-loaded", function (evt) {
            var c = evt.detail || {};
            if (c.builderMode === "visual" && Array.isArray(c.blocks && c.blocks.blocks)) {
                // The saved tree has shape { version, table, blocks: [...] };
                // loadTree validates and renders if the modal is open.
                loadTree(c.blocks);
            } else if (c.builderMode === "visual" && c.blocks && typeof c.blocks === "object") {
                // Defensive: also accept the case where blocks itself is the
                // tree (older shapes / hand-edited configs).
                loadTree(c.blocks);
            } else {
                // Page was saved from code mode — clear any in-memory tree
                // so the visual modal starts empty rather than showing
                // stale state from a previous edit.
                loadTree(null);
            }
            // Mirror the loaded file name into the modal's input so the
            // user sees what they're editing without opening the host
            // editor. If the modal hasn't been mounted yet, the seed in
            // wireNameSync() will catch it on first mount.
            var $visual = document.getElementById("wpb-visual-name");
            if ($visual) { $visual.value = c.fileName || ""; }
            updateSaveButtonState();
        });
    }

    function mount() {
        if (state.mounted) { return; }
        state.mounted = true;
        fullRender();
    }

    function unmount() {
        state.mounted = false;
        teardownSortables();
        // Cancel any pending debounced preview refresh.
        if (state.previewTimer) {
            clearTimeout(state.previewTimer);
            state.previewTimer = null;
        }
        // Reset the view so a fresh mount lands on Editor again.
        state.view = "editor";
        // Clear wired flags so the next mount re-wires events on the
        // (possibly new) canvas / props / toggle DOM.
        var $canvas = document.getElementById("wpb-canvas");
        var $props  = document.getElementById("wpb-props");
        if ($canvas) { delete $canvas.dataset.wpbWired; }
        if ($props)  { delete $props.dataset.wpbWired; }
        document.querySelectorAll('input[name="wpb-view"]').forEach(function (el) {
            delete el.dataset.wpbWired;
        });
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
                renderProps();
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

    /* ---------- persistence (save / load) ---------- */

    // Saves the current tree as a real page via
    // cms/ajax/web-pages.ajax.php?action=generate. The visual builder
    // owns the template + customCss + builderMode + blocks; the rest of
    // the page metadata (name, table, heading, SEO, visibility, etc.)
    // comes from the code-mode form fields in the host view — that way
    // we don't duplicate UI for fields that are identical between modes.
    //
    // The result handler updates the page list (so the new page appears
    // immediately) and surfaces success / errors to the user.

    function ajaxUrl() {
        var base = window.CMS_AJAX_PATH || "/ajax";
        return base + "/web-pages.ajax.php";
    }

    function readHostField(id, fallback) {
        var $el = document.getElementById(id);
        if (!$el) { return fallback == null ? "" : fallback; }
        if ($el.type === "checkbox") { return $el.checked ? 1 : 0; }
        return $el.value == null ? (fallback == null ? "" : fallback) : $el.value;
    }

    function readCheckedValue(name) {
        var $el = document.querySelector('input[name="' + name + '"]:checked');
        return $el ? $el.value : "";
    }

    function readCheckedValues(selector) {
        var out = [];
        document.querySelectorAll(selector).forEach(function ($el) { out.push($el.value); });
        return out;
    }

    // Reads the page metadata from the existing code-mode form. The
    // visual builder doesn't have its own copies of these fields because
    // they apply identically to both modes (heading, SEO, visibility, …).
    // The file-name input is the exception: the visual modal carries its
    // own copy (#wpb-visual-name) so the user doesn't need to close it to
    // pick a name. Both inputs are kept in sync by wireNameSync().
    function readHostConfig() {
        var visualName = String(readHostField("wpb-visual-name", "")).trim();
        var hostName   = String(readHostField("wpb-name", "")).trim();
        return {
            name:      visualName || hostName,
            heading:   readHostField("wpb-heading", ""),
            metaTitle: readHostField("wpb-meta-title", ""),
            metaDesc:  readHostField("wpb-meta-desc", ""),
            ogTitle:   readHostField("wpb-og-title", ""),
            ogType:    readHostField("wpb-og-type", "website"),
            ogDesc:    readHostField("wpb-og-desc", ""),
            ogImage:   readHostField("wpb-og-image", ""),
            // Page is private if the host visibility radios say so; the
            // visual builder mirrors that choice without its own UI.
            "private": readCheckedValue("wpb-visibility") === "private" ? 1 : 0,
            isHome:    readHostField("wpb-home", 0),
            "accessRoles[]": readCheckedValues(".wpb-role:checked"),
            "accessUsers[]": readCheckedValues(".wpb-user:checked"),
            // The host's "Tipo de página" radio chooses table vs. static.
            // Static = no table binding (the compiler will still produce
            // valid HTML with literal text), table = bind to whatever the
            // user picked in the host's <select>.
            table: readCheckedValue("wpb-mode") === "static"
                ? ""
                : readHostField("wpb-table", "")
        };
    }

    function updateSaveButtonState() {
        var $save = document.getElementById("wpb-visual-save");
        if (!$save) { return; }
        // Enabled when there's at least one block and a non-empty name.
        // We read both the inline modal input AND the host's #wpb-name
        // (whichever is set) so the button stays in sync regardless of
        // where the user typed.
        var visualName = String(readHostField("wpb-visual-name", "")).trim();
        var hostName   = String(readHostField("wpb-name", "")).trim();
        var name = visualName || hostName;
        $save.disabled = !state.tree.blocks.length || name === "";
    }

    function showSaveFeedback(kind, message) {
        // Toastr is loaded globally by the CMS (`fncToastr`); fall back to
        // a console log if it isn't.
        if (typeof window.fncToastr === "function") {
            window.fncToastr(kind, message);
            return;
        }
        console.log("[wpb-visual]", kind, message);
    }

    function saveBlocks() {
        if (!state.tree.blocks.length) {
            showSaveFeedback("warning", "Agregá al menos un bloque antes de guardar.");
            return;
        }
        var host = readHostConfig();
        if (!host.name) {
            showSaveFeedback("warning", "Escribí un nombre de archivo en el editor (campo \"Nombre del archivo\") antes de guardar.");
            return;
        }

        // Sync the tree's table with what the host picked so the saved
        // config is consistent. Without this a user could pick a table in
        // the host but the saved blocks tree would still hold the previous
        // table identifier.
        state.tree.table = host.table || "";

        var compiled = compileForPreview();
        if (compiled.warnings && compiled.warnings.length) {
            // Non-fatal: log the warnings but proceed. The compiler is
            // strict enough that any real error would have thrown.
            console.warn("[wpb-visual] compile warnings:", compiled.warnings);
        }

        var payload = Object.assign({}, host, {
            action:      "generate",
            template:    compiled.template,
            customCss:   compiled.customCss,
            customJs:    "", // v1: visual builder doesn't carry inline JS
            builderMode: "visual",
            blocks:      JSON.stringify(state.tree)
        });

        var $save = document.getElementById("wpb-visual-save");
        if ($save) { $save.disabled = true; }

        // Use jQuery if available (it's bundled by the CMS); otherwise fall
        // back to fetch with URL-encoded form data.
        function done(res) {
            if (!res || !res.success) {
                showSaveFeedback("error", (res && res.error) || "No se pudo guardar la página.");
                updateSaveButtonState();
                return;
            }
            if (res.written) {
                showSaveFeedback("success", "Página guardada en web/pages/" + host.name + ".php");
            } else {
                showSaveFeedback("warning", res.reason || "La página se generó pero no se pudo escribir en disco.");
            }
            updateSaveButtonState();
            // Refresh the host's pages list so the new file appears (the
            // code-mode editor renders the list via web-pages.js — it
            // re-fetches when we trigger this custom event).
            document.dispatchEvent(new CustomEvent("wpb:pages-changed"));
        }
        function fail() {
            showSaveFeedback("error", "Falló la conexión con el servidor.");
            updateSaveButtonState();
        }

        if (window.jQuery) {
            window.jQuery.ajax({
                url: ajaxUrl(), method: "POST", dataType: "json", data: payload
            }).done(done).fail(fail);
        } else {
            var body = new URLSearchParams();
            Object.keys(payload).forEach(function (k) {
                var v = payload[k];
                if (Array.isArray(v)) {
                    v.forEach(function (item) { body.append(k, item); });
                } else {
                    body.append(k, v == null ? "" : v);
                }
            });
            fetch(ajaxUrl(), {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString()
            }).then(function (r) { return r.json(); }).then(done).catch(fail);
        }
    }

    function wireSaveButton() {
        var $save = document.getElementById("wpb-visual-save");
        if (!$save || $save.dataset.wpbWired === "1") { return; }
        $save.dataset.wpbWired = "1";
        $save.addEventListener("click", saveBlocks);
    }

    // Keeps the modal's inline file-name input (#wpb-visual-name) and the
    // host's hidden one (#wpb-name) in lockstep so the user can type in
    // either place. Also refreshes the save-button state on every
    // keystroke.
    function wireNameSync() {
        var $host   = document.getElementById("wpb-name");
        var $visual = document.getElementById("wpb-visual-name");
        if ($host && $host.dataset.wpbWired !== "1") {
            $host.dataset.wpbWired = "1";
            $host.addEventListener("input", function () {
                if ($visual && $visual.value !== $host.value) {
                    $visual.value = $host.value;
                }
                updateSaveButtonState();
            });
        }
        if ($visual && $visual.dataset.wpbWired !== "1") {
            $visual.dataset.wpbWired = "1";
            $visual.addEventListener("input", function () {
                if ($host && $host.value !== $visual.value) {
                    $host.value = $visual.value;
                    // Some host listeners react to "change" rather than
                    // "input"; fire both so the code editor stays current.
                    $host.dispatchEvent(new Event("input",  { bubbles: true }));
                    $host.dispatchEvent(new Event("change", { bubbles: true }));
                }
                updateSaveButtonState();
            });
        }
        // Seed the visual input with the host's current value (handles
        // the "open modal after editing a page in code mode" case).
        if ($host && $visual && !$visual.value) {
            $visual.value = $host.value || "";
        }
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
