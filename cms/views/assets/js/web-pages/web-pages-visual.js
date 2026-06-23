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
        { type: "rawHtml",   label: "HTML libre",     icon: "bi-code" },
        // Container blocks — accept other blocks inside their sub-canvas.
        // Compile to {{#cada}}…{{/cada}} and {{#form}}…{{submit}}{{/form}}
        // respectively (see web-pages-compile.js).
        { type: "list",      label: "Lista de registros", icon: "bi-list-ul" },
        { type: "form",      label: "Formulario",         icon: "bi-ui-checks" }
    ];

    // Form-only blocks. Live in their own palette section because they
    // only make sense inside a `form` container (the {{input campo}} etc.
    // template tags are stripped by the framework when used outside a
    // {{#form}}…{{/form}}). The user can still drop them anywhere; the
    // compiler doesn't validate the parent, but the v1 UX nudges them
    // toward a form by showing the section header.
    var FORM_INPUTS = [
        { type: "formInput",    label: "Campo de texto",  icon: "bi-input-cursor-text" },
        { type: "formTextarea", label: "Área de texto",   icon: "bi-textarea-resize" },
        { type: "formFile",     label: "Subir archivo",   icon: "bi-cloud-upload" }
    ];

    // Default props for a freshly-dragged block. Kept separate so the
    // catalogue (above) stays declarative. `column` (optional) is the
    // table column carried by a column-chip drop — used to seed the
    // field/fieldImage/fieldGallery blocks with the right binding.
    function defaultProps(type, column) {
        switch (type) {
            case "heading":      return { level: 1, text: "Encabezado" };
            case "paragraph":    return { text: "Escribe un párrafo aquí." };
            case "image":        return { src: "", alt: "", width: "100%" };
            case "button":       return { text: "Botón", href: "#", style: "primary" };
            case "divider":      return { height: 24 };
            case "rawHtml":      return { html: "<div>tu HTML</div>" };
            case "field":        return { column: column || "", tag: "span" };
            case "fieldImage":   return { column: column || "", width: "100%" };
            case "fieldGallery": return { column: column || "", itemWidth: 80 };
            // Containers: empty `children` array gets populated when the
            // user drops blocks into the rendered sub-canvas. The render
            // path always normalises a missing children to [].
            case "list":         return { wrapper: "div", wrapperClass: "" };
            case "form":         return { submitText: "Enviar" };
            // Form inputs — seeded with the column the chip was carrying
            // (when dragged from a column chip). The label defaults to
            // the column name so the rendered <label> isn't empty.
            case "formInput":    return { column: column || "", label: column || "" };
            case "formTextarea": return { column: column || "", label: column || "" };
            case "formFile":     return { column: column || "", label: column || "" };
            default:             return {};
        }
    }

    function isContainer(type) {
        return type === "list" || type === "form";
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

    // The palette has two stacked sections:
    //   1. Static block library (#wpb-palette-list) — same identity
    //      across re-renders so SortableJS keeps its hooks.
    //   2. Column chips (#wpb-column-chips-list) — only present when a
    //      table is selected; contents refresh whenever state.columns
    //      changes, the host element itself persists.
    // Both lists belong to the same SortableJS group "wpb-blocks" so
    // drags land on the canvas the same way (commit 7b drops chips as
    // field/fieldImage/fieldGallery blocks, see onPaletteDrop).
    function renderPalette($palette) {
        // First-time render: scaffold the static sections. The dynamic
        // contents (column chips, form-inputs section visibility) are
        // refreshed afterwards by the helpers below.
        if ($palette.dataset.wpbBuilt !== "1") {
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

            // Column-chips section. Title + container both live across
            // renders; only the children of the list refresh below.
            $palette.appendChild(el("div", {
                className: "small text-muted fw-semibold mb-2 mt-3 px-1",
                id: "wpb-column-chips-title"
            }));
            $palette.appendChild(el("div", {
                className: "wpb-palette-list wpb-column-chips-list",
                id: "wpb-column-chips-list"
            }));

            // Form-input chips section. The chips themselves are static
            // (their labels and types don't depend on a table) but a
            // column chip dragged INTO a form also produces an input.
            $palette.appendChild(el("div", {
                className: "small text-muted fw-semibold mb-2 mt-3 px-1",
                id: "wpb-form-inputs-title",
                text: "Inputs de formulario"
            }));
            var $formInputs = el("div", {
                className: "wpb-palette-list wpb-form-inputs-list",
                id: "wpb-form-inputs-list"
            });
            FORM_INPUTS.forEach(function (item) {
                $formInputs.appendChild(el("div", {
                    className: "wpb-palette-item wpb-palette-form-input",
                    dataset: { paletteType: item.type },
                    title: "Arrastra dentro de un Formulario"
                }, [
                    el("i", { className: "bi " + item.icon }),
                    el("span", { text: item.label })
                ]));
            });
            $palette.appendChild($formInputs);

            $palette.dataset.wpbBuilt = "1";
        }
        renderColumnChips();
    }

    // Translates the framework's column type to the right block type the
    // chip should produce when dropped. Falls back to a plain {{field}}
    // tag for anything we don't recognise.
    function blockTypeForColumnType(t) {
        if (t === "image" || t === "img")  { return "fieldImage"; }
        if (t === "multiimage")            { return "fieldGallery"; }
        return "field";
    }

    // Bootstrap icon to display next to each column chip — purely visual.
    function iconForColumnType(t) {
        switch (t) {
            case "image":      return "bi-image";
            case "multiimage": return "bi-images";
            case "file":       return "bi-paperclip";
            case "video":      return "bi-camera-video";
            case "textarea":   return "bi-card-text";
            case "boolean":    return "bi-check2-square";
            case "date":       return "bi-calendar3";
            case "time":       return "bi-clock";
            case "email":      return "bi-envelope";
            case "link":       return "bi-link-45deg";
            case "color":      return "bi-palette";
            case "money":
            case "double":
            case "int":        return "bi-123";
            default:           return "bi-tag";
        }
    }

    function renderColumnChips() {
        var $title = document.getElementById("wpb-column-chips-title");
        var $list  = document.getElementById("wpb-column-chips-list");
        if (!$title || !$list) { return; }

        var cols  = state.columns || [];
        var types = (columnsCache[state.tree.table] && columnsCache[state.tree.table].types) || {};

        if (!state.tree.table || !cols.length) {
            // No table picked (or no columns yet) — collapse the section.
            $title.textContent = "";
            $title.style.display = "none";
            $list.innerHTML = "";
            $list.style.display = "none";
            return;
        }

        $title.textContent = "Campos de " + state.tree.table;
        $title.style.display = "";
        $list.style.display = "";
        $list.innerHTML = "";

        cols.forEach(function (col) {
            var fwType = types[col] || "";
            var blockType = blockTypeForColumnType(fwType);
            $list.appendChild(el("div", {
                className: "wpb-palette-item wpb-palette-column",
                dataset: {
                    paletteType:   blockType,
                    paletteColumn: col
                },
                title: "Arrastra al canvas (campo " + col + ")"
            }, [
                el("i", { className: "bi " + iconForColumnType(fwType) }),
                el("span", { text: col })
            ]));
        });
    }

    /* ---------- canvas render ---------- */

    // Path helpers — see the comment at the top of the file. A path is
    // the "/"-joined list of indexes that lead from the root tree to a
    // container's children array. Root is the empty string.

    function getBlocksAtPath(path) {
        if (!path) { return state.tree.blocks; }
        var parts = path.split("/");
        var node = state.tree.blocks;
        for (var i = 0; i < parts.length; i++) {
            var idx = parseInt(parts[i], 10);
            if (!Array.isArray(node) || !node[idx]) { return null; }
            // Containers normalise their children to an array.
            if (!Array.isArray(node[idx].children)) { node[idx].children = []; }
            node = node[idx].children;
        }
        return node;
    }

    function childPath(parentPath, idx) {
        return parentPath ? (parentPath + "/" + idx) : String(idx);
    }

    // Returns the type of the nearest ancestor container for a given
    // path, or "" if the path is the root canvas. Used to pick the right
    // block type when a column chip is dropped inside a form (an input
    // tag) vs. anywhere else (a plain {{field}} tag).
    function ancestorContainerType(path) {
        if (!path) { return ""; }
        // path = "0/children" or "0/children/1/children" … strip the
        // trailing "/children" and look up that node's type.
        var parts = path.split("/");
        if (parts[parts.length - 1] !== "children") { return ""; }
        parts.pop();
        var node = state.tree.blocks;
        var last = null;
        for (var i = 0; i < parts.length; i++) {
            var idx = parseInt(parts[i], 10);
            if (!Array.isArray(node) || !node[idx]) { return ""; }
            last = node[idx];
            node = Array.isArray(node[idx].children) ? node[idx].children : [];
        }
        return last ? last.type : "";
    }

    // Each rendered card holds:
    //   - its blockId (for selection / deletion)
    //   - its full path to itself, so an edit can locate the exact node
    //     without searching the whole tree (search still works, but we
    //     prefer paths because they survive duplicate ids defensively).
    function renderBlock(block, parentPath, indexInParent) {
        var info = describeBlock(block);
        var path = childPath(parentPath, indexInParent);

        var $children = [
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
        ];

        // Container blocks (list, form) host a sub-canvas that accepts
        // its own SortableJS drops. The sub-canvas carries the path to
        // the children array so onPaletteDrop / onCanvasReorder can
        // mutate the right slice of state.tree.
        if (isContainer(block.type)) {
            // Normalise on render so children is always an array.
            if (!Array.isArray(block.children)) { block.children = []; }
            var subPath = path + "/children";
            var $sub = el("div", {
                className: "wpb-subcanvas",
                dataset: { blockPath: subPath, containerType: block.type }
            });
            if (!block.children.length) {
                $sub.appendChild(el("div", {
                    className: "wpb-subcanvas-empty text-center text-muted small py-3",
                    "data-sortable-skip": "1",
                    style: "pointer-events:none",
                    text: block.type === "list"
                        ? "Arrastra aquí los bloques que se repetirán por cada registro"
                        : "Arrastra aquí los campos del formulario"
                }));
            } else {
                block.children.forEach(function (child, i) {
                    $sub.appendChild(renderBlock(child, subPath, i));
                });
            }
            $children.push($sub);
        }

        return el("div", {
            className: "wpb-block" + (isContainer(block.type) ? " wpb-block-container" : ""),
            dataset: { blockId: block.id, blockPath: path }
        }, $children);
    }

    function describeBlock(block) {
        var p = block.props || {};
        switch (block.type) {
            case "heading":      return { title: "Encabezado H" + p.level, summary: p.text || "(sin texto)" };
            case "paragraph":    return { title: "Párrafo",     summary: (p.text || "").slice(0, 80) || "(vacío)" };
            case "image":        return { title: "Imagen",      summary: p.src || "(sin URL)" };
            case "button":       return { title: "Botón",       summary: (p.text || "") + " → " + (p.href || "#") };
            case "divider":      return { title: "Separador",   summary: p.height + " px" };
            case "rawHtml":      return { title: "HTML libre",  summary: (p.html || "").slice(0, 80) };
            case "field":        return { title: "Campo: " + (p.column || "?"), summary: "<" + (p.tag || "span") + ">{{" + (p.column || "") + "}}</" + (p.tag || "span") + ">" };
            case "fieldImage":   return { title: "Imagen de campo: " + (p.column || "?"), summary: "<img src=\"{{" + (p.column || "") + "}}\"> · " + (p.width || "100%") };
            case "fieldGallery": return { title: "Galería de imágenes: " + (p.column || "?"), summary: "Recorre " + (p.column || "?") + " · " + (p.itemWidth || 80) + "px por imagen" };
            case "list":         return { title: "Lista de registros", summary: "{{#cada}} … {{/cada}} · contiene " + ((block.children || []).length) + " bloque(s)" };
            case "form":         return { title: "Formulario", summary: "{{#form}} … {{submit " + (p.submitText || "Enviar") + "}}{{/form}} · contiene " + ((block.children || []).length) + " bloque(s)" };
            case "formInput":    return { title: "Campo de texto",  summary: (p.label || p.column || "?") + " → {{input " + (p.column || "") + "}}" };
            case "formTextarea": return { title: "Área de texto",   summary: (p.label || p.column || "?") + " → {{textarea " + (p.column || "") + "}}" };
            case "formFile":     return { title: "Subir archivo",   summary: (p.label || p.column || "?") + " → {{file " + (p.column || "") + "}}" };
            default:             return { title: block.type,    summary: "" };
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
        ],
        field: [
            { key: "column", kind: "column", label: "Columna de la tabla" },
            { key: "tag",    kind: "select", label: "Etiqueta HTML",
              options: [
                  { value: "span",   label: "<span> (inline)" },
                  { value: "div",    label: "<div> (bloque)" },
                  { value: "strong", label: "<strong> (negrita)" },
                  { value: "em",     label: "<em> (itálica)" },
                  { value: "small",  label: "<small> (texto chico)" },
                  { value: "h1",     label: "H1" },
                  { value: "h2",     label: "H2" },
                  { value: "h3",     label: "H3" },
                  { value: "h4",     label: "H4" }
              ] }
        ],
        fieldImage: [
            { key: "column", kind: "column", label: "Columna de la imagen" },
            { key: "width",  kind: "text",   label: "Ancho",
              placeholder: "100% · 320px · auto" }
        ],
        fieldGallery: [
            { key: "column",    kind: "column", label: "Columna multi-imagen" },
            { key: "itemWidth", kind: "number", label: "Ancho de cada imagen (px)",
              min: 16, max: 800, step: 1, coerce: "int" }
        ],
        list: [
            { key: "wrapper", kind: "select", label: "Etiqueta envolvente",
              options: [
                  { value: "div",     label: "<div>" },
                  { value: "ul",      label: "<ul> (lista)" },
                  { value: "ol",      label: "<ol> (lista numerada)" },
                  { value: "section", label: "<section>" }
              ] },
            { key: "wrapperClass", kind: "text", label: "Clase CSS del envolvente",
              placeholder: "row, products-grid, …" }
        ],
        form: [
            { key: "submitText", kind: "text", label: "Texto del botón enviar",
              placeholder: "Enviar" }
        ],
        formInput: [
            { key: "column", kind: "column", label: "Columna donde se guarda" },
            { key: "label",  kind: "text",   label: "Etiqueta visible",
              placeholder: "Nombre" }
        ],
        formTextarea: [
            { key: "column", kind: "column", label: "Columna donde se guarda" },
            { key: "label",  kind: "text",   label: "Etiqueta visible",
              placeholder: "Mensaje" }
        ],
        formFile: [
            { key: "column", kind: "column", label: "Columna donde se guarda" },
            { key: "label",  kind: "text",   label: "Etiqueta visible",
              placeholder: "Adjuntar archivo" }
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
                      'Selecciona un bloque<br>para editar sus propiedades'
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
            case "column":
                // Dynamic select: options come from state.columns (the
                // currently picked table). When the user hasn't picked a
                // table, the chip should still be editable so they can
                // type a literal column name — render an input fallback.
                if (state.columns && state.columns.length) {
                    $input = el("select", { className: "form-select form-select-sm wpb-prop-input" });
                    if (!value || state.columns.indexOf(value) === -1) {
                        var $empty = el("option", { value: "", text: "(elegí una columna)" });
                        if (!value) { $empty.selected = true; }
                        $input.appendChild($empty);
                    }
                    state.columns.forEach(function (col) {
                        var $opt = el("option", { value: col, text: col });
                        if (col === value) { $opt.selected = true; }
                        $input.appendChild($opt);
                    });
                } else {
                    $input = el("input", {
                        type: "text",
                        className: "form-control form-control-sm wpb-prop-input",
                        placeholder: "Nombre de columna (sin tabla cargada)"
                    });
                    $input.value = value == null ? "" : value;
                }
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
        // findBlockById walks the whole tree so blocks nested inside
        // list/form containers resolve correctly. A shallow .find on
        // state.tree.blocks would return null for them and the props
        // panel would silently fall back to the empty placeholder.
        var hit = findBlockById(state.selectedId);
        return hit ? hit.block : null;
    }

    // Walks the block tree (including children of containers) and yields
    // each { block, parentArray, index, path } so callers can locate the
    // node without searching by id every time.
    function findBlockById(blockId) {
        function recurse(arr, parentPath) {
            for (var i = 0; i < arr.length; i++) {
                var b = arr[i];
                var path = childPath(parentPath, i);
                if (b.id === blockId) {
                    return { block: b, parentArray: arr, index: i, path: path };
                }
                if (Array.isArray(b.children)) {
                    var hit = recurse(b.children, path + "/children");
                    if (hit) { return hit; }
                }
            }
            return null;
        }
        return recurse(state.tree.blocks, "");
    }

    // Refresh just the editable card of a single block — used after a
    // props edit so the summary in the canvas updates without touching
    // SortableJS bookkeeping. Walks the tree so it works for blocks
    // nested inside containers.
    function refreshBlockCard(blockId) {
        var hit = findBlockById(blockId);
        if (!hit) { return; }
        var $old = document.querySelector(
            '#wpb-canvas-list .wpb-block[data-block-id="' + cssEscape(blockId) + '"]'
        );
        if (!$old) { return; }
        // Compute the parent path + index from the located node.
        // hit.path = "0/children/2"; the index is the last segment, the
        // parent path is everything before "/children/<idx>".
        var parts = hit.path.split("/");
        var indexInParent = parseInt(parts.pop(), 10);
        var parentPath = parts.join("/");
        var $new = renderBlock(hit.block, parentPath, indexInParent);
        if (state.selectedId === blockId) { $new.classList.add("is-selected"); }
        $old.parentNode.replaceChild($new, $old);
        // The replaced card may contain new sub-canvases that need their
        // own SortableJS instances; also drop any instances tied to the
        // sub-canvases we just discarded.
        pruneSortables();
        ensureSortables();
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

            // findBlockById walks containers' children so edits on blocks
            // nested inside a list/form persist (a shallow find on
            // state.tree.blocks would miss them and the keystroke would
            // be silently dropped on the floor).
            var hit = findBlockById(blockId);
            if (!hit) { return; }
            var block = hit.block;
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
                      'Arrastra un bloque desde la paleta para empezar'
            }));
        } else {
            state.tree.blocks.forEach(function (b, i) {
                $canvasList.appendChild(renderBlock(b, "", i));
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
            // Drop the wpb marker BEFORE destroy so a subsequent
            // ensureSortables() (e.g. when the modal is reopened) sees
            // the host element as un-wired and recreates the instance.
            // Otherwise data-sortable-wired persists on the still-attached
            // palette/chips lists, ensureSortables short-circuits, and DnD
            // is dead from the second open onwards.
            if (s && s.el && s.el.dataset) {
                delete s.el.dataset.sortableWired;
            }
            try { s.destroy(); } catch (e) { /* ignore */ }
        });
        state.sortables = [];
    }

    function ensureSortables() {
        if (!window.Sortable) {
            console.warn("[wpb-visual] SortableJS not loaded");
            return;
        }
        // Idempotent: each container/list carries data-sortable-wired
        // once we've attached SortableJS so subsequent calls only wire
        // newly-added sub-canvases.

        function wireSourceList(el) {
            if (!el || el.dataset.sortableWired === "1") { return; }
            el.dataset.sortableWired = "1";
            state.sortables.push(window.Sortable.create(el, {
                group: { name: "wpb-blocks", pull: "clone", put: false },
                sort: false,
                animation: 150
            }));
        }

        function wireDropList(el) {
            if (!el || el.dataset.sortableWired === "1") { return; }
            el.dataset.sortableWired = "1";
            state.sortables.push(window.Sortable.create(el, {
                group: { name: "wpb-blocks", pull: false, put: true },
                handle: ".wpb-block-handle",
                draggable: ".wpb-block",
                filter: "[data-sortable-skip]",
                animation: 150,
                onAdd: onPaletteDrop,
                onUpdate: onCanvasReorder
            }));
        }

        // Source lists (palette + column chips + form-inputs) — clone mode.
        wireSourceList(document.getElementById("wpb-palette-list"));
        wireSourceList(document.getElementById("wpb-column-chips-list"));
        wireSourceList(document.getElementById("wpb-form-inputs-list"));

        // Drop lists: root canvas + every sub-canvas currently rendered.
        // Any new sub-canvas added by renderBlock will be wired on the
        // next ensureSortables() call (which we run after every render
        // that may have created one).
        wireDropList(document.getElementById("wpb-canvas-list"));
        document.querySelectorAll("#wpb-canvas-list .wpb-subcanvas")
            .forEach(wireDropList);
    }

    // Tears down only the SortableJS instances whose host elements are
    // no longer in the DOM. Called after we re-render a parent list (the
    // sub-canvases inside get replaced; their old instances should die).
    function pruneSortables() {
        state.sortables = state.sortables.filter(function (s) {
            var alive = s && s.el && document.body.contains(s.el);
            if (!alive) {
                try { s.destroy(); } catch (e) { /* ignore */ }
            }
            return alive;
        });
    }

    // Reads the path of the SortableJS target list. For the root canvas
    // it's "" (the empty string), for any sub-canvas it's whatever its
    // data-block-path attribute says ("0/children", "0/children/1/children", …).
    function pathOfList(el) {
        if (!el) { return ""; }
        var p = el.dataset && el.dataset.blockPath;
        // Empty-string path is meaningful (= root), so check for undefined.
        return p == null ? "" : p;
    }

    function onPaletteDrop(evt) {
        // Sortable's clone (the dropped DOM node) carries the type via
        // dataset.paletteType and, for column chips, the column name via
        // dataset.paletteColumn. We add the block to state.tree (at the
        // right sub-array per evt.to.dataset.blockPath) and re-render
        // the affected list IN PLACE.
        var $node = evt.item;
        var type   = $node && $node.dataset && $node.dataset.paletteType;
        var column = $node && $node.dataset && $node.dataset.paletteColumn;
        if (!type) { return; }

        var path = pathOfList(evt.to);
        var target = getBlocksAtPath(path);
        if (!target) { return; }

        // Smart conversion: a column chip dropped inside a {{#form}}
        // becomes a form input (the {{input col}} tag) instead of a
        // plain {{col}} read tag. The framework template engine treats
        // them differently and only {{input}}/{{textarea}}/{{file}}
        // produce editable controls.
        if (column && (type === "field" || type === "fieldImage" || type === "fieldGallery")) {
            if (ancestorContainerType(path) === "form") {
                // For image/multiimage columns inside a form we still want
                // a file picker rather than a textarea.
                type = (type === "fieldImage" || type === "fieldGallery")
                    ? "formFile"
                    : "formInput";
            }
        }

        var newBlock = {
            id:    nonce(),
            type:  type,
            props: defaultProps(type, column)
        };
        if (isContainer(type)) { newBlock.children = []; }

        var idx = evt.newIndex == null ? target.length : evt.newIndex;
        target.splice(idx, 0, newBlock);
        state.selectedId = newBlock.id;

        // Re-render the affected list (root or sub-canvas) plus everything
        // below it. We can't avoid re-rendering the parent block-card if
        // the drop landed in a sub-canvas (the card's summary counts
        // children), so we always re-render from the root canvas down —
        // SortableJS bookkeeping for the root is preserved because the
        // root list element itself stays put.
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            renderCanvas($canvasList);
            pruneSortables();
            ensureSortables();
            applySelectionClass();
        }
        renderProps();
        updateSaveButtonState();
        schedulePreviewRefresh();
    }

    function onCanvasReorder(evt) {
        var fromPath = pathOfList(evt.from);
        var toPath   = pathOfList(evt.to);
        var oldIdx = evt.oldIndex, newIdx = evt.newIndex;
        if (oldIdx == null || newIdx == null) { return; }

        var fromArr = getBlocksAtPath(fromPath);
        var toArr   = getBlocksAtPath(toPath);
        if (!fromArr || !toArr) { return; }

        // Same list, no movement: nothing to do.
        if (fromPath === toPath && oldIdx === newIdx) { return; }

        var moved = fromArr.splice(oldIdx, 1)[0];
        if (!moved) { return; }
        toArr.splice(newIdx, 0, moved);

        // Drag-between-lists: SortableJS already moved the DOM but block
        // path attributes on the moved card are now stale. Re-render the
        // whole canvas to refresh paths + container summaries; if the
        // move was within a single list, that's still cheap enough.
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            renderCanvas($canvasList);
            pruneSortables();
            ensureSortables();
            applySelectionClass();
        }
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
        var hit = findBlockById(id);
        if (!hit) { return; }
        hit.parentArray.splice(hit.index, 1);
        if (state.selectedId === id) { state.selectedId = null; }
        var $canvasList = document.getElementById("wpb-canvas-list");
        if ($canvasList) {
            renderCanvas($canvasList);
            pruneSortables();
            ensureSortables();
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
            id: "wpb-canvas-list",
            // Path "" = root. Sub-canvas inside containers carry their
            // own "0/children/1/children" etc.
            dataset: { blockPath: "" }
        });
        $canvas.appendChild($canvasList);
        renderCanvas($canvasList);

        wireCanvasEvents();
        wirePropsEvents();
        wireViewToggle();
        wireSaveButton();
        wireNameSync();
        wireTablePicker();
        ensureSortables();
        applySelectionClass();
        renderProps();
        setView(state.view);
        updateSaveButtonState();

        // Populate the table picker the first time the modal mounts; on
        // subsequent opens the cached list is reused, so this is a no-op.
        populateTableSelect().then(function () {
            // Refresh the column cache for the currently selected table
            // so column chips (commit 7b) have data to render.
            if (state.tree.table) { applyTableChange(state.tree.table); }
        });
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
            // Mirror the loaded file name + table into the modal so the
            // user sees what they're editing without opening the host
            // editor. If the modal hasn't been mounted yet, the seed in
            // wireNameSync() / populateTableSelect() catches it on first
            // mount.
            var $visualName  = document.getElementById("wpb-visual-name");
            var $visualTable = document.getElementById("wpb-visual-table");
            if ($visualName)  { $visualName.value  = c.fileName || ""; }
            if ($visualTable) { $visualTable.value = c.table    || ""; }
            // Also reflect into the in-memory tree so saveBlocks doesn't
            // drop the table binding when the user opens Visual right
            // after loading a page.
            state.tree.table = c.table || "";
            // Refresh the column cache so chips show up if the loaded
            // page already has a table picked.
            if (state.tree.table) { applyTableChange(state.tree.table); }
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
        // The palette uses state.columns to render column chips. Re-render
        // it whenever the column list changes so the chips reflect the
        // currently selected table.
        var $palette = document.getElementById("wpb-palette");
        if ($palette) { renderPalette($palette); }
    }

    /* ---------- data-source picker (table selection) ---------- */

    // Lazy cache so the table list isn't fetched again every time the
    // modal opens. Invalidated on demand if a future commit adds a way
    // to create tables from inside the modal.
    var tablesCache = null;
    var columnsCache = {}; // table → { columns, types }

    function ajaxFormFetch(payload) {
        // POST with URL-encoded form data, same shape the legacy code-mode
        // editor uses. We rely on jQuery so the CSRF interceptor the CMS
        // installs in ajaxSend applies — a hand-rolled fetch would skip
        // it and the server would reject the request with "Invalid CSRF
        // token". jQuery is bundled by the CMS on every authenticated
        // page, so this is always available here.
        if (!window.jQuery) {
            return Promise.reject(new Error(
                "jQuery is required for the visual builder (CSRF interceptor)"
            ));
        }
        return new Promise(function (resolve, reject) {
            window.jQuery.ajax({
                url: ajaxUrl(), method: "POST", dataType: "json", data: payload
            }).done(resolve).fail(reject);
        });
    }

    function loadTables() {
        if (tablesCache) { return Promise.resolve(tablesCache); }
        // Only cache successful responses; a transient network error
        // would otherwise pin the picker to an empty list for the rest
        // of the session and the user would have to reload the CMS.
        return ajaxFormFetch({ action: "tables" }).then(function (res) {
            if (res && res.success && Array.isArray(res.tables)) {
                tablesCache = res.tables;
                return tablesCache;
            }
            throw new Error((res && res.error) || "No se pudieron cargar las tablas");
        });
    }

    function loadColumns(table) {
        if (!table) { return Promise.resolve({ columns: [], types: {} }); }
        if (columnsCache[table]) { return Promise.resolve(columnsCache[table]); }
        // Same logic as loadTables: don't cache failures, throw so the
        // caller can decide whether to surface the error.
        return ajaxFormFetch({ action: "columns", table: table }).then(function (res) {
            if (res && res.success) {
                var out = { columns: res.columns || [], types: res.types || {} };
                columnsCache[table] = out;
                return out;
            }
            throw new Error((res && res.error) || "No se pudieron cargar las columnas");
        });
    }

    function populateTableSelect() {
        var $sel = document.getElementById("wpb-visual-table");
        if (!$sel) { return Promise.resolve(); }
        // Don't refetch if we already populated it during this session.
        if ($sel.dataset.wpbPopulated === "1") { return Promise.resolve(); }
        return loadTables().then(function (tables) {
            // Keep the static-page sentinel as option[value=""].
            $sel.querySelectorAll('option[value]:not([value=""])').forEach(function (o) { o.remove(); });
            tables.forEach(function (t) {
                var $o = document.createElement("option");
                $o.value = t;
                $o.textContent = t;
                $sel.appendChild($o);
            });
            $sel.dataset.wpbPopulated = "1";
            // Sync the value with the current tree (which may carry a
            // table from a freshly-loaded page).
            if (state.tree.table) { $sel.value = state.tree.table; }
        }).catch(function (err) {
            // Leave wpbPopulated unset so the next mount retries.
            console.warn("[wpb-visual] populateTableSelect:", err && err.message || err);
        });
    }

    function applyTableChange(newTable) {
        // Block-types that depend on a table column become invalid when
        // the table changes. We don't delete them — the user might be
        // moving between two tables with overlapping column names — but
        // any chip-driven field/list/form created later will pick up the
        // new column set.
        state.tree.table = newTable || "";
        loadColumns(newTable).then(function (info) {
            setColumns(info.columns || []);
        }).catch(function (err) {
            // Clear chips on transient failure but don't pin the cache —
            // loadColumns now throws on errors instead of caching empties.
            setColumns([]);
            console.warn("[wpb-visual] loadColumns:", err && err.message || err);
        });
    }

    function wireTablePicker() {
        var $sel = document.getElementById("wpb-visual-table");
        if (!$sel || $sel.dataset.wpbWired === "1") { return; }
        $sel.dataset.wpbWired = "1";
        $sel.addEventListener("change", function () {
            applyTableChange(this.value);
            schedulePreviewRefresh();
        });
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
            // The visual modal carries its own table picker that we use
            // as the source of truth — the host's radio + <select> are
            // only consulted as a fallback for the legacy code-mode flow.
            table: readHostField("wpb-visual-table",
                readCheckedValue("wpb-mode") === "static"
                    ? ""
                    : readHostField("wpb-table", ""))
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
            showSaveFeedback("warning", "Agrega al menos un bloque antes de guardar.");
            return;
        }
        var host = readHostConfig();
        if (!host.name) {
            showSaveFeedback("warning", "Escribe un nombre de archivo en el editor (campo \"Nombre del archivo\") antes de guardar.");
            return;
        }

        // Sync the tree's table with what the host picked so the saved
        // config is consistent. Without this a user could pick a table in
        // the host but the saved blocks tree would still hold the previous
        // table identifier.
        state.tree.table = host.table || "";

        // Compile directly (not via compileForPreview which embeds errors
        // as an HTML <pre> in `template` — fine for the iframe preview,
        // disastrous if it got persisted). If compilation throws we abort
        // the save and surface the error so the user can fix the offending
        // block before clobbering the .php file on disk.
        if (!window.WebPagesCompile) {
            showSaveFeedback("error", "El compilador no está cargado.");
            return;
        }
        var compiled;
        try {
            compiled = window.WebPagesCompile.compileTree(state.tree);
        } catch (err) {
            showSaveFeedback("error", "Error al compilar: " +
                (err && err.message ? err.message : String(err)));
            return;
        }
        if (compiled.warnings && compiled.warnings.length) {
            // Non-fatal: log the warnings but proceed.
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

        // jQuery is always bundled by the CMS; we rely on its ajaxSend
        // interceptor to attach the CSRF token. A vanilla fetch fallback
        // would skip that header and the server would reject the save
        // — drop the fallback so the path stays consistent with the rest
        // of the visual builder (ajaxFormFetch) and the legacy editor.
        if (!window.jQuery) {
            fail();
            return;
        }
        window.jQuery.ajax({
            url: ajaxUrl(), method: "POST", dataType: "json", data: payload
        }).done(done).fail(fail);
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
            // Listen to both `input` and `change`: typing fires `input`,
            // but a `value = …` assignment elsewhere followed by a manual
            // `change` event would otherwise skip the visual mirror.
            var syncToVisual = function () {
                if ($visual && $visual.value !== $host.value) {
                    $visual.value = $host.value;
                }
                updateSaveButtonState();
            };
            $host.addEventListener("input",  syncToVisual);
            $host.addEventListener("change", syncToVisual);
        }
        if ($visual && $visual.dataset.wpbWired !== "1") {
            $visual.dataset.wpbWired = "1";
            var syncToHost = function () {
                if ($host && $host.value !== $visual.value) {
                    $host.value = $visual.value;
                    // Some host listeners react to "change" rather than
                    // "input"; fire both so the code editor stays current.
                    $host.dispatchEvent(new Event("input",  { bubbles: true }));
                    $host.dispatchEvent(new Event("change", { bubbles: true }));
                }
                updateSaveButtonState();
            };
            $visual.addEventListener("input",  syncToHost);
            $visual.addEventListener("change", syncToHost);
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
