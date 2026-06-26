/*=============================================
Visual builder — pure block compiler

Takes the visual builder's JSON tree (`{ version, table, blocks }`) and
returns the `template` / `customCss` strings the rest of the generator
already understands. The same strings the user could have written by hand
in the code editor.

Pure function: no DOM, no jQuery, no globals besides the IIFE export.
Reused by:
  - the live preview (debounced compile + setPreview)
  - the save flow (compile once before POSTing to web-pages.ajax.php)
  - future v1.1 / v2 features (visual ↔ code round-trip, server-side
    re-render, etc.)

JSON shape (v1) — see docs/GENERADOR-PAGINAS.md for the legacy template tags.

  {
    "version": 1,
    "table": "productos",      // empty string for static pages
    "blocks": [ Block, ... ]
  }

  Block (discriminated union by `type`):

    heading        props: { level: 1|2|3, text }
    paragraph      props: { text }
    image          props: { src, alt, width }
    button         props: { text, href, style }    // style: primary|secondary
    divider        props: { height }               // px

    columns        props: { count: 2|3|4, gap }    // px
                   children: Block[][]              // one array per column

    field          props: { column, tag }          // tag: span|div|h1..h6|strong|em
    fieldImage     props: { column, width }
    fieldGallery   props: { column, itemWidth }

    list           props: { wrapper, wrapperClass } // wraps the {{#cada}} loop
                   children: Block[]                // sub-template repeated per row

    form           props: { submitText }
                   children: Block[]                // form inputs + decorations

    formInput      props: { column, label }
    formTextarea   props: { column, label }
    formFile       props: { column, label }

    rawHtml        props: { html }                  // escape hatch — NOT escaped

A block MAY carry an `id` string used by the UI for drag identity; the
compiler ignores it (round-trip safe).

Output contract:
  compileTree(tree) → { template: string, customCss: string }
  Errors throw; the caller catches and shows a toast.
=============================================*/

(function (root) {
    "use strict";

    /* ---------- helpers ---------- */

    function escHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function escAttr(s) {
        // Same rules as text — quotes already covered by escHtml.
        return escHtml(s);
    }

    // Legacy template tags use bare identifiers — match the existing
    // generator validator: a-z 0-9 _, starting with a letter. Anything
    // else throws; the UI should never produce it.
    var IDENT_RE = /^[a-z][a-z0-9_]*$/;

    function assertIdent(value, where) {
        if (typeof value !== "string" || !IDENT_RE.test(value)) {
            throw new Error("Invalid identifier in " + where + ": " + JSON.stringify(value));
        }
    }

    function asInt(v, fallback) {
        var n = parseInt(v, 10);
        return Number.isFinite(n) ? n : fallback;
    }

    function asStr(v, fallback) {
        return (typeof v === "string" && v.length) ? v : (fallback || "");
    }

    /* ---------- per-block compilers ---------- */
    // Each one returns a fragment of `template`. CSS contributions are
    // pushed into the shared `cssParts` array via `ctx.addCss(str)`.

    function compileHeading(b) {
        var lvl = asInt(b.props && b.props.level, 1);
        if (lvl < 1 || lvl > 6) { lvl = 1; }
        return "<h" + lvl + ">" + escHtml((b.props || {}).text) + "</h" + lvl + ">";
    }

    function compileParagraph(b) {
        return "<p>" + escHtml((b.props || {}).text) + "</p>";
    }

    function compileImage(b) {
        var p = b.props || {};
        var width = asStr(p.width, "100%");
        return '<img src="' + escAttr(p.src) + '" alt="' + escAttr(p.alt) +
            '" style="width:' + escAttr(width) + '">';
    }

    function compileButton(b) {
        var p = b.props || {};
        var style = (p.style === "secondary") ? "btn-secondary" : "btn-primary";
        return '<a class="btn ' + style + '" href="' + escAttr(p.href || "#") + '">' +
            escHtml(p.text) + "</a>";
    }

    function compileDivider(b) {
        var h = asInt((b.props || {}).height, 24);
        return '<div style="height:' + h + 'px"></div>';
    }

    function compileColumns(b, ctx) {
        var p = b.props || {};
        var count = asInt(p.count, 2);
        if (count < 1 || count > 4) { count = 2; }
        var gap = asInt(p.gap, 16);
        var children = Array.isArray(b.children) ? b.children : [];
        // Bootstrap col-md-* values for 1/2/3/4 columns.
        var colClass = { 1: "col-md-12", 2: "col-md-6", 3: "col-md-4", 4: "col-md-3" }[count];
        var cols = [];
        for (var i = 0; i < count; i++) {
            var inner = compileBlocks(children[i] || [], ctx);
            cols.push('<div class="' + colClass + '">' + inner + "</div>");
        }
        return '<div class="row" style="gap:' + gap + 'px">' + cols.join("") + "</div>";
    }

    function compileField(b) {
        var p = b.props || {};
        assertIdent(p.column, "field.column");
        var tag = asStr(p.tag, "span");
        // tag must be a known safe element — block anything weird.
        if (!/^(span|div|h1|h2|h3|h4|h5|h6|strong|em|small)$/.test(tag)) {
            tag = "span";
        }
        return "<" + tag + ">{{" + p.column + "}}</" + tag + ">";
    }

    function compileFieldImage(b) {
        var p = b.props || {};
        assertIdent(p.column, "fieldImage.column");
        var width = asStr(p.width, "100%");
        return '<img src="{{' + p.column + '}}" style="width:' + escAttr(width) + '">';
    }

    function compileFieldGallery(b) {
        var p = b.props || {};
        assertIdent(p.column, "fieldGallery.column");
        var w = asInt(p.itemWidth, 80);
        return "{{#imagenes " + p.column + '}}<img src="{{url}}" style="width:' + w + 'px">{{/imagenes}}';
    }

    function compileList(b, ctx) {
        var p = b.props || {};
        var wrapper = asStr(p.wrapper, "div");
        if (!/^(div|ul|ol|section)$/.test(wrapper)) { wrapper = "div"; }
        var wrapperClass = asStr(p.wrapperClass, "");
        var children = Array.isArray(b.children) ? b.children : [];
        var inner = compileBlocks(children, ctx);
        var openTag = "<" + wrapper + (wrapperClass ? ' class="' + escAttr(wrapperClass) + '"' : "") + ">";
        return openTag + "{{#cada}}" + inner + "{{/cada}}</" + wrapper + ">";
    }

    function compileForm(b, ctx) {
        var p = b.props || {};
        var submitText = asStr(p.submitText, "Enviar");
        var children = Array.isArray(b.children) ? b.children : [];
        var inner = compileBlocks(children, ctx);
        // The submit lives at the bottom of the form, separate from sub-blocks
        // so the user can't accidentally have two of them.
        return "{{#form}}" + inner + "{{submit " + escHtml(submitText) + "}}{{/form}}";
    }

    function compileFormInput(b) {
        var p = b.props || {};
        assertIdent(p.column, "formInput.column");
        var label = asStr(p.label, p.column);
        return '<label>' + escHtml(label) + "</label>{{input " + p.column + "}}";
    }

    function compileFormTextarea(b) {
        var p = b.props || {};
        assertIdent(p.column, "formTextarea.column");
        var label = asStr(p.label, p.column);
        return '<label>' + escHtml(label) + "</label>{{textarea " + p.column + "}}";
    }

    function compileFormFile(b) {
        var p = b.props || {};
        assertIdent(p.column, "formFile.column");
        var label = asStr(p.label, p.column);
        return '<label>' + escHtml(label) + "</label>{{file " + p.column + "}}";
    }

    function compileRawHtml(b) {
        // Escape hatch: user pasted HTML on purpose. No escaping.
        // The visual builder already warns the user this is unfiltered.
        return String((b.props || {}).html || "");
    }

    /* ---------- dispatcher ---------- */

    var COMPILERS = {
        heading:      compileHeading,
        paragraph:    compileParagraph,
        image:        compileImage,
        button:       compileButton,
        divider:      compileDivider,
        columns:      compileColumns,
        field:        compileField,
        fieldImage:   compileFieldImage,
        fieldGallery: compileFieldGallery,
        list:         compileList,
        form:         compileForm,
        formInput:    compileFormInput,
        formTextarea: compileFormTextarea,
        formFile:     compileFormFile,
        rawHtml:      compileRawHtml
    };

    function compileBlock(block, ctx) {
        if (!block || typeof block !== "object") { return ""; }
        var fn = COMPILERS[block.type];
        if (!fn) {
            // Unknown block type: render nothing. Logged once so future
            // versions can detect data created by a newer client.
            if (ctx && ctx.warn) { ctx.warn("Unknown block type: " + block.type); }
            return "";
        }
        return fn(block, ctx);
    }

    function compileBlocks(blocks, ctx) {
        if (!Array.isArray(blocks)) { return ""; }
        var out = [];
        for (var i = 0; i < blocks.length; i++) {
            out.push(compileBlock(blocks[i], ctx));
        }
        return out.join("");
    }

    /* ---------- public API ---------- */

    function compileTree(tree) {
        if (!tree || typeof tree !== "object") {
            throw new Error("compileTree: tree must be an object");
        }
        if (tree.version !== 1) {
            throw new Error("compileTree: unsupported version " + tree.version);
        }
        var warnings = [];
        var cssParts = [];
        var ctx = {
            warn: function (msg) { warnings.push(msg); },
            addCss: function (s) { if (s) { cssParts.push(String(s)); } }
        };
        var template = compileBlocks(tree.blocks, ctx);
        return {
            template:  template,
            customCss: cssParts.join("\n\n"),
            warnings:  warnings
        };
    }

    /* ---------- export ---------- */

    var api = { compileTree: compileTree, compileBlocks: compileBlocks, compileBlock: compileBlock };

    if (typeof module !== "undefined" && module.exports) {
        module.exports = api; // Node — for unit tests later
    }
    root.WebPagesCompile = api; // Browser — used by web-pages-visual.js
})(typeof window !== "undefined" ? window : globalThis);
