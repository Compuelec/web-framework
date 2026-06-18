/*=============================================
Web Pages builder (visual, configurable)
=============================================*/

(function () {
    "use strict";

    var $ = window.jQuery;
    var $root = $("#web-pages-builder");
    if (!$root.length) { return; }

    var ajaxPath = window.CMS_AJAX_PATH || "/ajax";
    var url      = ajaxPath + "/web-pages.ajax.php";

    var $table   = $("#wpb-table");
    var $title   = $("#wpb-title");
    var $cols    = $("#wpb-columns");
    var $gen     = $("#wpb-generate");
    var $genLbl  = $("#wpb-generate-label");
    var $result  = $("#wpb-result");
    var $editing = $("#wpb-editing");
    var $pages   = $("#wpb-pages");

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    /* ---------- load tables (custom/user tables only) ---------- */
    function loadTables() {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "tables" } })
            .done(function (res) {
                if (!res || !res.success) {
                    $table.empty().append('<option value="">Error al cargar tablas</option>');
                    return;
                }
                var tables = Array.isArray(res.tables) ? res.tables : [];
                $table.empty();
                if (!tables.length) {
                    $table.append('<option value="">No hay tablas propias todavía</option>');
                    return;
                }
                $table.append('<option value="">— Elige una tabla —</option>');
                tables.forEach(function (t) { $table.append($("<option>").val(t).text(t)); });
            })
            .fail(function () { $table.empty().append('<option value="">Error al cargar tablas</option>'); });
    }

    /* ---------- load columns ---------- */
    function loadColumns(table, selected, titleCol, cb) {
        $title.prop("disabled", true).empty().append("<option>Cargando…</option>");
        $cols.html('<span class="text-muted small">Cargando…</span>');
        $gen.prop("disabled", true);

        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "columns", table: table } })
            .done(function (res) {
                var columns = (res && res.columns) || [];

                // Title dropdown
                $title.empty();
                columns.forEach(function (c) { $title.append($("<option>").val(c).text(c)); });
                var preferredTitle = titleCol || columns.filter(function (c) { return /^name_|^title_/.test(c); })[0];
                if (preferredTitle) { $title.val(preferredTitle); }
                $title.prop("disabled", false);

                // Column checkboxes
                var html = "";
                columns.forEach(function (c) {
                    var checked = selected ? (selected.indexOf(c) !== -1) : !/^id_/.test(c);
                    var id = "wpbc-" + c;
                    html += '<div class="form-check"><input class="form-check-input wpb-col" type="checkbox" value="' +
                        escapeHtml(c) + '" id="' + escapeHtml(id) + '"' + (checked ? " checked" : "") +
                        '><label class="form-check-label small" for="' + escapeHtml(id) + '">' + escapeHtml(c) + "</label></div>";
                });
                $cols.html(html);

                $gen.prop("disabled", false);
                $("#wpb-name").attr("placeholder", table);
                if (cb) { cb(); }
            })
            .fail(function () { $cols.html('<span class="text-danger small">Error al cargar columnas</span>'); });
    }

    /* ---------- existing pages ---------- */
    function loadPages() {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "list" } })
            .done(function (res) {
                if (!res || !res.success) {
                    $pages.html('<div class="list-group-item text-danger small">' + escapeHtml((res && res.error) || "Error al cargar.") + "</div>");
                    return;
                }
                var pages = res.pages || [];
                if (!pages.length) {
                    $pages.html('<div class="list-group-item text-muted small">Aún no hay páginas.</div>');
                    return;
                }
                $pages.empty();
                pages.forEach(function (p) {
                    var $item = $('<div class="list-group-item d-flex justify-content-between align-items-start"></div>');
                    var $info = $('<a href="#" class="flex-grow-1 text-decoration-none text-body">' +
                        '<div class="fw-semibold">' + escapeHtml(p.heading || p.file) + "</div>" +
                        '<div class="small text-muted">' + escapeHtml(p.file) + ".php · " + escapeHtml(p.table) + "</div></a>");
                    $info.on("click", function (e) { e.preventDefault(); loadForEdit(p.file); });
                    var $del = $('<button class="btn btn-sm btn-link text-danger p-0 ms-2" title="Eliminar"><i class="bi bi-trash"></i></button>');
                    $del.on("click", function (e) { e.preventDefault(); deletePage(p.file); });
                    $pages.append($item.append($info).append($del));
                });
            })
            .fail(function () {
                $pages.html('<div class="list-group-item text-danger small">No se pudieron cargar las páginas.</div>');
            });
    }

    /* ---------- collect / apply config ---------- */
    function collectConfig() {
        var columns = $cols.find(".wpb-col:checked").map(function () { return this.value; }).get();
        return {
            action:    "generate",
            table:     $table.val(),
            name:      $("#wpb-name").val(),
            heading:   $("#wpb-heading").val(),
            intro:     $("#wpb-intro").val(),
            title:     $title.val(),
            "columns[]": columns,
            layout:    $("input[name='wpb-layout']:checked").val(),
            perRow:    $("#wpb-perrow").val(),
            accent:    $("#wpb-accent").val(),
            detail:    $("#wpb-detail").is(":checked") ? 1 : 0,
            customCss: $("#wpb-css").val(),
            customHtml: $("#wpb-html").val(),
            customJs:  $("#wpb-js").val()
        };
    }

    function applyConfig(c) {
        $table.val(c.table);
        $("#wpb-heading").val(c.heading || "");
        $("#wpb-intro").val(c.intro || "");
        $("#wpb-name").val(c.fileName || "");
        $("input[name='wpb-layout'][value='" + (c.layout || "cards") + "']").prop("checked", true);
        $("#wpb-perrow").val(String(c.perRow || 3));
        $("#wpb-accent").val(c.accent || "#0d6efd");
        $("#wpb-detail").prop("checked", !!c.withDetail);
        $("#wpb-css").val(c.customCss || "");
        $("#wpb-html").val(c.customHtml || "");
        $("#wpb-js").val(c.customJs || "");
        loadColumns(c.table, c.columns || [], c.titleColumn);
    }

    function loadForEdit(file) {
        $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "load", file: file } })
            .done(function (res) {
                if (!res || !res.success) { return; }
                $editing.val(file);
                $genLbl.text("Guardar cambios");
                applyConfig(res.config);
            });
    }

    function deletePage(file) {
        fncSweetAlert("confirm", '¿Eliminar la página "' + file + '"? Se borrará el archivo .php y su página de detalle si existe.')
            .then(function (confirmed) {
                if (!confirmed) { return; }
                $.ajax({ url: url, method: "POST", dataType: "json", data: { action: "delete", file: file } })
                    .done(function (res) {
                        if (!res || !res.success) {
                            fncSweetAlert("error", (res && res.error) || "No se pudo eliminar.", "");
                            return;
                        }
                        if ($editing.val() === file) { resetForm(); }
                        fncToastr("success", "Página eliminada");
                        loadPages();
                    })
                    .fail(function () { fncSweetAlert("error", "No se pudo contactar al servidor.", ""); });
            });
    }

    function resetForm() {
        $editing.val("");
        $genLbl.text("Crear página");
        $("#wpb-heading,#wpb-intro,#wpb-name,#wpb-css,#wpb-html,#wpb-js").val("");
        $("#wpb-accent").val("#0d6efd");
        $("input[name='wpb-layout'][value='cards']").prop("checked", true);
        $("#wpb-perrow").val("3");
        $("#wpb-detail").prop("checked", false);
        $table.val("");
        $title.prop("disabled", true).empty().append("<option>Elige una tabla</option>");
        $cols.html('<span class="text-muted small">Elige una tabla</span>');
        $gen.prop("disabled", true);
        $result.html("");
    }

    /* ---------- generate ---------- */
    function generate() {
        if (!$table.val()) { return; }
        $gen.prop("disabled", true);
        $result.html("");

        $.ajax({ url: url, method: "POST", dataType: "json", data: collectConfig() })
            .done(function (res) {
                if (!res || !res.success) {
                    fncSweetAlert("error", (res && res.error) || "Error desconocido.", "");
                    return;
                }
                if (res.written) {
                    var links = res.files.map(function (f) { return "<li><code>web/pages/" + escapeHtml(f) + "</code></li>"; }).join("");
                    var viewUrl = (window.CMS_BASE_PATH || "").replace(/\/cms$/, "") + "/" + escapeHtml(res.urlPath);
                    fncToastr("success", "Página creada correctamente");
                    $result.html(resultCard("Archivos creados",
                        "<ul class='mb-2'>" + links + "</ul>" +
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
        return '<div class="card"><div class="card-body"><h6 class="card-title">' + escapeHtml(title) + "</h6><div>" + body + "</div></div></div>";
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
        if (t) { loadColumns(t, null, null); } else { resetForm(); }
    });
    $gen.on("click", generate);
    $("#wpb-new").on("click", resetForm);

    // Defer the initial loads to DOM-ready so the global CSRF ajaxSend hook
    // (registered by auth-interceptor on DOMContentLoaded) is active before the
    // list POST fires — otherwise it would be rejected as an invalid CSRF token.
    $(function () {
        loadTables();
        loadPages();
    });
})();
