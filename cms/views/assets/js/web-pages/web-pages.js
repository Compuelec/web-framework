/*=============================================
Web Pages builder (template + live preview)
=============================================*/

(function () {
    "use strict";

    var $ = window.jQuery;
    var $root = $("#web-pages-builder");
    if (!$root.length) { return; }

    var ajaxPath = window.CMS_AJAX_PATH || "/ajax";
    var url      = ajaxPath + "/web-pages.ajax.php";

    var $table    = $("#wpb-table");
    var $template = $("#wpb-template");
    var $fields   = $("#wpb-fields");
    var $repeat   = $("#wpb-repeat");
    var $formBtn  = $("#wpb-form");
    var currentColumns = [], currentTypes = {}, currentIdColumn = "";
    var $gen      = $("#wpb-generate");
    var $genLbl   = $("#wpb-generate-label");
    var $result   = $("#wpb-result");
    var $editing  = $("#wpb-editing");
    var $pages    = $("#wpb-pages");

    var previewTimer = null;
    var previewXhr   = null;
    var cmTemplate = null, cmCss = null, cmJs = null; // CodeMirror editors (if loaded)
    var partialMode = null; // 'header' | 'footer' while editing a shared partial

    /* ---------- code editors (CodeMirror 5) ---------- */
    // CodeMirror 5 is bundled and loaded by the builder view, captured as
    // WPB_CM so it stays isolated from the legacy CodeMirror 3 the CMS uses
    // elsewhere. If it's unavailable, the plain textareas keep working.
    function initEditors() {
        var CM = window.WPB_CM;
        if (!CM || !CM.hint || !CM.hint.css) { return; } // CM5 unavailable → keep textareas

        function make(id, mode, hint) {
            var el = document.getElementById(id);
            if (!el) { return null; }
            var cm = CM.fromTextArea(el, {
                mode: mode, lineNumbers: true, lineWrapping: true,
                matchBrackets: true, autoCloseBrackets: true,
                autoCloseTags: (mode === "htmlmixed"),
                extraKeys: { "Ctrl-Space": "autocomplete" }
            });
            cm.setSize(null, 260);
            cm.on("inputRead", function (editor, change) {
                if (change.origin !== "+input") { return; }
                var ch = change.text[0];
                if (ch && /[\w<.{#-]/.test(ch)) {
                    editor.showHint({ hint: hint, completeSingle: false });
                }
            });
            return cm;
        }

        cmTemplate = make("wpb-template", "htmlmixed", CM.hint.html);
        cmCss = make("wpb-css", "css", CM.hint.css);
        cmJs = make("wpb-js", "javascript", CM.hint.javascript);

        if (cmTemplate) { cmTemplate.on("change", function () { cmTemplate.save(); schedulePreview(); }); }
        if (cmCss) { cmCss.on("change", function () { cmCss.save(); schedulePreview(); }); }
        if (cmJs) { cmJs.on("change", function () { cmJs.save(); }); }

        // CSS/JS editors live in a collapsed accordion — refresh on open.
        $("#wpb-adv-body").on("shown.bs.collapse", function () {
            if (cmCss) { cmCss.refresh(); }
            if (cmJs) { cmJs.refresh(); }
        });
    }

    function syncEditors() {
        if (cmTemplate) { cmTemplate.save(); }
        if (cmCss) { cmCss.save(); }
        if (cmJs) { cmJs.save(); }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    /* ---------- tables (custom only) ---------- */
    function loadTables() {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "tables" } })
            .done(function (res) {
                if (!res || !res.success) { $table.empty().append('<option value="">Error al cargar tablas</option>'); return; }
                var tables = Array.isArray(res.tables) ? res.tables : [];
                $table.empty();
                if (!tables.length) { $table.append('<option value="">No hay tablas propias todavía</option>'); return; }
                $table.append('<option value="">— Elige una tabla —</option>');
                tables.forEach(function (t) { $table.append($("<option>").val(t).text(t)); });
            })
            .fail(function () { $table.empty().append('<option value="">Error al cargar tablas</option>'); });
    }

    /* ---------- fields (clickable tags) ---------- */
    function loadFields(table, cb) {
        $fields.html('<span class="text-muted small">Cargando…</span>');
        $repeat.prop("disabled", true);
        $gen.prop("disabled", true);

        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "columns", table: table } })
            .done(function (res) {
                var cols = (res && res.columns) || [];
                var types = (res && res.types) || {};
                currentColumns = cols; currentTypes = types;
                currentIdColumn = cols.filter(function (c) { return /^id_/.test(c); })[0] || "";
                if (!cols.length) { $fields.html('<span class="text-danger small">Sin columnas</span>'); return; }
                $fields.empty();
                cols.forEach(function (c) {
                    var type = types[c] || "";
                    var snippet, label;
                    if (type === "image") {
                        snippet = '<img src="{{' + c + '}}" alt="" style="max-width:200px">';
                        label = '<i class="bi bi-image me-1"></i>' + escapeHtml(c);
                    } else if (type === "multiimage") {
                        snippet = "{{#imagenes " + c + '}}<img src="{{url}}" alt="" style="max-width:150px">{{/imagenes}}';
                        label = '<i class="bi bi-images me-1"></i>' + escapeHtml(c);
                    } else {
                        snippet = "{{" + c + "}}";
                        label = "<code>{{" + escapeHtml(c) + "}}</code>";
                    }
                    var $chip = $('<button type="button" class="btn btn-sm btn-light border me-1 mb-1"></button>');
                    $chip.html(label);
                    $chip.on("click", function () { insertAtCursor(snippet); });
                    $fields.append($chip);
                });
                $repeat.prop("disabled", false);
                $formBtn.prop("disabled", false);
                $gen.prop("disabled", false);
                $("#wpb-name").attr("placeholder", table);
                if (cb) { cb(); }
            })
            .fail(function () { $fields.html('<span class="text-danger small">Error al cargar campos</span>'); });
    }

    /* ---------- editor helpers ---------- */
    function insertAtCursor(text) {
        if (cmTemplate) {
            cmTemplate.replaceSelection(text);
            cmTemplate.focus();
            cmTemplate.save();
            schedulePreview();
            return;
        }
        var el = $template[0];
        var start = el.selectionStart || 0, end = el.selectionEnd || 0;
        var val = el.value;
        el.value = val.slice(0, start) + text + val.slice(end);
        el.selectionStart = el.selectionEnd = start + text.length;
        el.focus();
        schedulePreview();
    }

    function insertRepeat() {
        var placeholder = "\n  <!-- HTML por cada registro: usa {{campo}} -->\n";
        if (cmTemplate) {
            var selection = cmTemplate.getSelection() || placeholder;
            cmTemplate.replaceSelection("{{#cada}}" + selection + "{{/cada}}");
            cmTemplate.focus();
            cmTemplate.save();
            schedulePreview();
            return;
        }
        var el = $template[0];
        var start = el.selectionStart || 0, end = el.selectionEnd || 0;
        var val = el.value;
        var sel = val.slice(start, end) || placeholder;
        var block = "{{#cada}}" + sel + "{{/cada}}";
        el.value = val.slice(0, start) + block + val.slice(end);
        el.focus();
        schedulePreview();
    }

    /* ---------- live preview ---------- */
    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 450);
    }

    // Local Bootstrap (same-origin) for the preview iframe — avoids the CDN
    // (third-party tracking-prevention warnings + blocked source map under CSP).
    var bsCssUrl = (window.location.origin || "") + (window.CMS_BASE_PATH || "") + "/views/assets/plugins/bootstrap5/bootstrap.min.css";

    function setPreview(html, count, css) {
        var doc = '<!doctype html><html><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<link href="' + bsCssUrl + '" rel="stylesheet">' +
            '<style>body{padding:1rem}' + (css || "") + '</style></head><body>' + (html || "") + '</body></html>';
        document.getElementById("wpb-preview").srcdoc = doc;
        $("#wpb-preview-info").text(count ? (count + " registro(s)") : "");
    }

    function runPreview() {
        if (partialMode) { return; } // header/footer have no data preview
        var table = $table.val();
        // Static pages (no table bound) render their template as-is — there is
        // no data to interpolate, so we skip the backend roundtrip and just
        // pipe the current HTML/CSS into the iframe.
        if (!table) {
            setPreview($template.val() || '<p style="color:#888;font-family:sans-serif">Escribe tu HTML para ver la vista previa.</p>', 0, $("#wpb-css").val());
            return;
        }
        // Abort any in-flight preview so a slow earlier response can't overwrite
        // a newer one (race condition while typing fast).
        if (previewXhr) { previewXhr.abort(); }
        previewXhr = $.ajax({
            url: url, method: "POST", dataType: "json",
            data: { action: "preview", table: table, template: $template.val(), customCss: $("#wpb-css").val() }
        }).done(function (res) {
            if (!res || !res.success) { setPreview('<p style="color:#c00">' + escapeHtml((res && res.error) || "Error") + "</p>", 0); return; }
            setPreview(res.html, res.count, res.css);
        }).fail(function (xhr, status) {
            if (status === "abort") { return; }
            setPreview('<p style="color:#c00">No se pudo generar la vista previa.</p>', 0);
        });
    }

    /* ---------- existing pages ---------- */
    // Pinned header/footer items (always shown, cannot be deleted).
    function renderPinnedPartials() {
        [["header", "Header", "bi-window-dock"], ["footer", "Footer", "bi-window"]].forEach(function (p) {
            var $item = $('<a href="#" class="list-group-item list-group-item-action px-2 py-2" data-partial="' + p[0] + '"></a>');
            $item.html('<div class="fw-semibold"><i class="bi ' + p[2] + ' me-1"></i>' + p[1] + "</div><div class='text-muted' style='font-size:.72rem'>web/partials/" + p[0] + ".php</div>");
            $item.on("click", function (e) { e.preventDefault(); loadPartial(p[0]); });
            $pages.append($item);
        });
        $pages.append('<div class="list-group-item bg-light text-muted px-2 py-1" style="font-size:.72rem;letter-spacing:.03em">PÁGINAS</div>');
    }

    function loadPages() {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "list" } })
            .done(function (res) {
                $pages.empty();
                renderPinnedPartials();
                if (partialMode) { $('#wpb-pages [data-partial="' + partialMode + '"]').addClass("active"); }
                if (!res || !res.success) { $pages.append('<div class="list-group-item text-danger small">' + escapeHtml((res && res.error) || "Error al cargar.") + "</div>"); return; }
                var pages = res.pages || [];
                if (!pages.length) { $pages.append('<div class="list-group-item text-muted small">Aún no hay páginas.</div>'); return; }
                pages.forEach(function (p) {
                    var $item = $('<div class="list-group-item d-flex justify-content-between align-items-start px-2"></div>');
                    var $info = $('<a href="#" class="flex-grow-1 text-decoration-none text-body small"></a>');
                    // Badge: "Estática" (intencional, marcada con confirmedStatic) tiene
                    // prioridad sobre la pista neutral "Sin tabla" para páginas legacy.
                    // Estilos inline porque el CMS carga Bootstrap 5.0 (las utilidades
                    // -subtle / -emphasis recién existen desde 5.3).
                    var badge = "";
                    if (p.confirmedStatic) {
                        badge = ' <span class="badge ms-1" style="font-size:.62rem;background:#cfe2ff;color:#084298;border:1px solid #b6d4fe" title="Página estática (sin datos vinculados, intencional)"><i class="bi bi-file-earmark-text me-1"></i>Estática</span>';
                    } else if (!p.table) {
                        badge = ' <span class="badge ms-1" style="font-size:.62rem;background:#f8f9fa;color:#6c757d;border:1px solid #dee2e6" title="Página sin tabla vinculada">Sin tabla</span>';
                    }
                    $info.html('<div class="fw-semibold">' + escapeHtml(p.heading || p.file) + badge + "</div><div class='text-muted'>" + escapeHtml(p.file) + ".php</div>");
                    $info.on("click", function (e) { e.preventDefault(); loadForEdit(p.file); });
                    var $del = $('<button class="btn btn-sm btn-link text-danger p-0 ms-1" title="Eliminar"><i class="bi bi-trash"></i></button>');
                    $del.on("click", function (e) { e.preventDefault(); deletePage(p.file); });
                    $pages.append($item.append($info).append($del));
                });
            })
            .fail(function () { $pages.empty(); renderPinnedPartials(); $pages.append('<div class="list-group-item text-danger small">No se pudieron cargar las páginas.</div>'); });
    }

    /* ---------- config in/out ---------- */
    function collectConfig() {
        syncEditors();
        return {
            action:    "generate",
            table:     $table.val(),
            name:      $("#wpb-name").val(),
            heading:   $("#wpb-heading").val(),
            template:  $template.val(),
            customCss: $("#wpb-css").val(),
            customJs:  $("#wpb-js").val(),
            metaTitle: $("#wpb-meta-title").val(),
            metaDesc:  $("#wpb-meta-desc").val(),
            ogTitle:   $("#wpb-og-title").val(),
            ogType:    $("#wpb-og-type").val(),
            ogDesc:    $("#wpb-og-desc").val(),
            ogImage:   $("#wpb-og-image").val(),
            private:   $("input[name='wpb-visibility']:checked").val() === "private" ? 1 : 0,
            isHome:    $("#wpb-home").is(":checked") ? 1 : 0,
            "accessRoles[]": $(".wpb-role:checked").map(function () { return this.value; }).get(),
            "accessUsers[]": $(".wpb-user:checked").map(function () { return this.value; }).get()
        };
    }

    function loadMeta() {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "meta" } })
            .done(function (res) {
                if (!res || !res.success) { return; }
                var roles = res.roles || [], users = res.users || [];
                $("#wpb-roles").html(roles.length ? roles.map(function (r) {
                    return '<label class="d-inline-flex align-items-center gap-2 me-3 mb-1 small" style="cursor:pointer">' +
                        '<input type="checkbox" class="form-check-input m-0 wpb-role" value="' + escapeHtml(r) + '"><span>' + escapeHtml(r) + "</span></label>";
                }).join("") : '<span class="text-muted small">No hay roles</span>');
                $("#wpb-users").html(users.length ? users.map(function (u) {
                    return '<label class="d-flex align-items-center gap-2 mb-1 small" style="cursor:pointer">' +
                        '<input type="checkbox" class="form-check-input m-0 wpb-user" value="' + escapeHtml(u.id) + '"><span>' + escapeHtml(u.email) + "</span></label>";
                }).join("") : '<span class="text-muted small">No hay usuarios</span>');
            });
    }

    // Build a {{#form}} block from the table's columns and insert it.
    function insertFormSnippet() {
        if (!currentColumns.length) { return; }
        var rows = "";
        currentColumns.forEach(function (c) {
            if (c === currentIdColumn) { return; }
            var type = currentTypes[c] || "";
            var tag;
            if (type === "image" || type === "multiimage" || type === "file") {
                tag = "{{file " + c + "}}";
            } else if (type === "textarea") {
                tag = "{{textarea " + c + "}}";
            } else {
                tag = "{{input " + c + "}}";
            }
            rows += '  <label class="form-label">' + c + "</label>\n  " + tag + "\n";
        });
        insertAtCursor("{{#form}}\n" + rows + "  {{submit Guardar}}\n{{/form}}\n");
    }

    function applyConfig(c) {
        $table.val(c.table);
        $("#wpb-heading").val(c.heading || "");
        $("#wpb-name").val(c.fileName || "");
        $template.val(c.template || "");
        $("#wpb-css").val(c.customCss || "");
        $("#wpb-js").val(c.customJs || "");
        $("#wpb-meta-title").val(c.metaTitle || "");
        $("#wpb-meta-desc").val(c.metaDesc || "");
        $("#wpb-og-title").val(c.ogTitle || "");
        $("#wpb-og-type").val(c.ogType || "website");
        $("#wpb-og-desc").val(c.ogDesc || "");
        $("#wpb-og-image").val(c.ogImage || "");
        $("input[name='wpb-visibility'][value='" + (c.private ? "private" : "public") + "']").prop("checked", true);
        $("#wpb-access").toggle(!!c.private);
        var roles = c.accessRoles || [], users = (c.accessUsers || []).map(String);
        $(".wpb-role").each(function () { this.checked = roles.indexOf(this.value) !== -1; });
        $(".wpb-user").each(function () { this.checked = users.indexOf(this.value) !== -1; });
        if (cmTemplate) { cmTemplate.setValue(c.template || ""); }
        if (cmCss) { cmCss.setValue(c.customCss || ""); }
        if (cmJs) { cmJs.setValue(c.customJs || ""); }
        loadFields(c.table, runPreview);
    }

    function loadForEdit(file) {
        exitPartialMode();
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "load", file: file } })
            .done(function (res) {
                if (!res || !res.success) { fncSweetAlert("error", (res && res.error) || "No se pudo cargar.", ""); return; }
                $editing.val(file);
                $genLbl.text("Guardar cambios");
                applyConfig(res.config);
            });
    }

    function deletePage(file) {
        fncSweetAlert("confirm", '¿Eliminar la página "' + file + '"? Esta acción no se puede deshacer.')
            .then(function (confirmed) {
                if (!confirmed) { return; }
                $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "delete", file: file } })
                    .done(function (res) {
                        if (!res || !res.success) { fncSweetAlert("error", (res && res.error) || "No se pudo eliminar.", ""); return; }
                        if ($editing.val() === file) { resetForm(); }
                        fncToastr("success", "Página eliminada");
                        loadPages();
                    })
                    .fail(function () { fncSweetAlert("error", "No se pudo contactar al servidor.", ""); });
            });
    }

    function resetForm() {
        exitPartialMode();
        $editing.val("");
        $genLbl.text("Crear página");
        $("#wpb-heading,#wpb-name,#wpb-template,#wpb-css,#wpb-js,#wpb-meta-title,#wpb-meta-desc,#wpb-og-title,#wpb-og-desc,#wpb-og-image").val("");
        $("#wpb-og-type").val("website");
        $("#wpb-home").prop("checked", false);
        if (cmTemplate) { cmTemplate.setValue(""); }
        if (cmCss) { cmCss.setValue(""); }
        if (cmJs) { cmJs.setValue(""); }
        $table.val("");
        $("#wpb-vis-public").prop("checked", true);
        $("#wpb-access").hide();
        $(".wpb-role, .wpb-user").prop("checked", false);
        $fields.html('<span class="text-muted small">Elige una tabla</span>');
        $repeat.prop("disabled", true);
        $formBtn.prop("disabled", true);
        $gen.prop("disabled", true);
        $result.html("");
        setPreview('<p style="color:#888;font-family:sans-serif">Elige una tabla para ver la vista previa.</p>', 0);
    }

    /* ---------- generate ---------- */
    function generate() {
        if (partialMode) { savePartial(); return; }
        if (!$table.val()) { return; }
        $gen.prop("disabled", true);
        $result.html("");

        $.ajax({ url: url, method: "POST", dataType: "json", data: collectConfig() })
            .done(function (res) {
                if (!res || !res.success) { fncSweetAlert("error", (res && res.error) || "Error desconocido.", ""); return; }
                if (res.written) {
                    var links = res.files.map(function (f) { return "<li><code>web/pages/" + escapeHtml(f) + "</code></li>"; }).join("");
                    var viewUrl = (window.CMS_BASE_PATH || "").replace(/\/cms$/, "") + "/" + escapeHtml(res.urlPath);
                    fncToastr("success", "Página guardada correctamente");
                    var homeNote = res.homeWritten ? '<div class="small text-success mb-2"><i class="bi bi-house-door me-1"></i>Es la página de inicio (raíz del dominio).</div>' : "";
                    $result.html(resultCard("Listo",
                        "<ul class='mb-2 small'>" + links + "</ul>" + homeNote +
                        '<a class="btn btn-sm btn-primary" target="_blank" href="' + viewUrl + '"><i class="bi bi-box-arrow-up-right me-1"></i>Ver página</a>'));
                    loadPages();
                } else {
                    var buttons = "";
                    Object.keys(res.sources).forEach(function (fn) {
                        buttons += '<button class="btn btn-sm btn-outline-primary me-2 mb-2 wpb-dl" data-file="' + escapeHtml(fn) + '"><i class="bi bi-download me-1"></i>' + escapeHtml(fn) + "</button>";
                    });
                    fncToastr("warning", "El servidor no puede escribir en web/pages: descarga manual");
                    var $box = $(resultCard("Descarga manual", "<p class='small text-muted'>" + escapeHtml(res.reason) + "</p>" + buttons));
                    $box.on("click", ".wpb-dl", function () { downloadFile($(this).data("file"), res.sources[$(this).data("file")]); });
                    $result.html($box);
                }
            })
            .fail(function () { fncSweetAlert("error", "No se pudo contactar al servidor.", ""); })
            .always(function () { $gen.prop("disabled", false); });
    }

    function resultCard(title, body) {
        return '<div class="alert alert-success mb-0"><h6 class="mb-1">' + escapeHtml(title) + "</h6><div>" + body + "</div></div>";
    }

    function downloadFile(name, contents) {
        var blob = new Blob([contents], { type: "application/x-php" });
        var a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    /* ---------- events ---------- */
    $table.on("change", function () {
        var t = $(this).val();
        if (t) { loadFields(t, runPreview); } else { resetForm(); }
    });
    $template.on("input", schedulePreview);
    $("#wpb-css").on("input", schedulePreview);
    $repeat.on("click", insertRepeat);
    $formBtn.on("click", insertFormSnippet);
    $("input[name='wpb-visibility']").on("change", function () { $("#wpb-access").toggle($(this).val() === "private"); });
    $gen.on("click", generate);
    $("#wpb-new").on("click", resetForm);

    /* ---------- shared header / footer (pinned items, edited like a page) ---------- */
    // partialMode is 'header' | 'footer' while editing a partial, else null.
    function setEd(cm, id, v) {
        $("#" + id).val(v);
        if (cm) { cm.setValue(v); setTimeout(function () { cm.refresh(); }, 30); }
    }

    function enterPartialMode(which) {
        partialMode = which;
        $editing.val("");
        $(".wpb-page-only").hide();
        $result.html("");
        $gen.prop("disabled", false);
        $genLbl.text(which === "footer" ? "Guardar footer" : "Guardar header");
        $("#wpb-pages .list-group-item, #wpb-pages [data-partial]").removeClass("active");
        $('#wpb-pages [data-partial="' + which + '"]').addClass("active");
        $("#wpb-adv-body").addClass("show"); // CSS/JS box is shared and useful here
        setPreview('<div style="padding:1.5rem;font-family:sans-serif;color:#888">El header/footer se ve en las páginas públicas. Guarda y ábrelas para verlo.</div>', 0);
    }

    function exitPartialMode() {
        if (!partialMode) { return; }
        partialMode = null;
        $(".wpb-page-only").show();
        $("#wpb-access").toggle($("input[name='wpb-visibility']:checked").val() === "private");
        $('#wpb-pages [data-partial]').removeClass("active");
        $genLbl.text("Crear página");
    }

    function loadPartial(which) {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "getPartial", which: which } })
            .done(function (res) {
                if (!res || !res.success) { fncToastr("warning", "No se pudo cargar."); return; }
                enterPartialMode(which);
                setEd(cmTemplate, "wpb-template", res.html || "");
                setEd(cmCss, "wpb-css", res.css || "");
                setEd(cmJs, "wpb-js", res.js || "");
            })
            .fail(function () { fncToastr("warning", "Error al cargar."); });
    }

    function savePartial() {
        syncEditors();
        $gen.prop("disabled", true);
        $.ajax({
            url: url, method: "POST", dataType: "json",
            data: { action: "savePartial", which: partialMode, html: $template.val(), css: $("#wpb-css").val(), js: $("#wpb-js").val() }
        })
        .done(function (res) {
            if (res && res.success) { fncToastr("success", (res.which === "footer" ? "Footer" : "Header") + " guardado"); }
            else { fncSweetAlert("error", (res && res.error) || "No se pudo guardar.", ""); }
        })
        .fail(function () { fncSweetAlert("error", "No se pudo contactar al servidor.", ""); })
        .always(function () { $gen.prop("disabled", false); });
    }

    // Defer initial loads to DOM-ready so the CSRF ajaxSend hook is active.
    $(function () {
        initEditors();
        loadTables();
        loadMeta();
        loadPages();
        setPreview('<p style="color:#888;font-family:sans-serif">Elige una tabla para ver la vista previa.</p>', 0);
    });
})();
