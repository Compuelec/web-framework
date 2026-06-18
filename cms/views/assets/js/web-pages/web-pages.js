/*=============================================
Web Pages builder
Visual generator for public frontend pages.
=============================================*/

(function () {
    "use strict";

    var $table    = $("#wpb-table");
    var $title    = $("#wpb-title");
    var $name     = $("#wpb-name");
    var $detail   = $("#wpb-detail");
    var $generate = $("#wpb-generate");
    var $result   = $("#wpb-result");

    // Only run on the builder page.
    if (!$table.length) {
        return;
    }

    var ajaxPath    = window.CMS_AJAX_PATH || "/ajax";
    var modulesUrl  = ajaxPath + "/modules.ajax.php";
    var generateUrl = ajaxPath + "/web-pages.ajax.php";

    /*=============================================
    Load the table list into the dropdown
    =============================================*/
    function loadTables() {
        $.ajax({
            url: modulesUrl + "?action=getTables",
            method: "GET",
            dataType: "json"
        }).done(function (res) {
            var tables = (res && res.results) || [];
            $table.empty().append('<option value="">— Elige una tabla —</option>');
            tables.forEach(function (t) {
                $table.append($("<option>").val(t).text(t));
            });
        }).fail(function () {
            $table.empty().append('<option value="">No se pudieron cargar las tablas</option>');
        });
    }

    /*=============================================
    Load the columns of the selected table
    =============================================*/
    function loadColumns(table) {
        $title.prop("disabled", true).empty().append("<option>Cargando…</option>");
        $generate.prop("disabled", true);

        $.ajax({
            url: modulesUrl,
            method: "POST",
            dataType: "json",
            data: { action: "getTableColumns", tableName: table }
        }).done(function (res) {
            var cols = (res && res.results) || [];
            $title.empty();
            cols.forEach(function (c) {
                $title.append($("<option>").val(c).text(c));
            });
            // Default to a name_* column if present.
            var preferred = cols.find(function (c) { return /^name_/.test(c) || /^title_/.test(c); });
            if (preferred) { $title.val(preferred); }

            $title.prop("disabled", false);
            $generate.prop("disabled", false);
            $name.attr("placeholder", table);
        }).fail(function () {
            $title.empty().append("<option>No se pudieron cargar las columnas</option>");
        });
    }

    /*=============================================
    Trigger a browser download of generated source
    =============================================*/
    function downloadFile(fileName, contents) {
        var blob = new Blob([contents], { type: "application/x-php" });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement("a");
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /*=============================================
    Generate the page(s)
    =============================================*/
    function generate() {
        var table = $table.val();
        if (!table) { return; }

        $generate.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Generando…');
        $result.html("");

        $.ajax({
            url: generateUrl,
            method: "POST",
            dataType: "json",
            data: {
                action: "generate",
                table:  table,
                title:  $title.val(),
                name:   $name.val(),
                detail: $detail.is(":checked") ? 1 : 0
            }
        }).done(function (res) {
            if (!res || !res.success) {
                $result.html(alertBox("danger", "No se pudo generar", (res && res.error) || "Error desconocido."));
                return;
            }

            if (res.written) {
                var links = res.files.map(function (f) {
                    return '<li><code>web/pages/' + escapeHtml(f) + "</code></li>";
                }).join("");
                $result.html(
                    alertBox("success", "¡Página creada!",
                        "Se generaron estos archivos:<ul class='mt-2 mb-0'>" + links + "</ul>")
                );
            } else {
                // Not writable: offer downloads.
                var buttons = "";
                Object.keys(res.sources).forEach(function (fname) {
                    buttons += '<button class="btn btn-sm btn-outline-primary me-2 mb-2 wpb-dl" data-file="' +
                        escapeHtml(fname) + '"><i class="bi bi-download me-1"></i>' + escapeHtml(fname) + "</button>";
                });
                var $box = $(alertBox("warning", "Generado (descarga manual)",
                    escapeHtml(res.reason) + "<div class='mt-2'>" + buttons + "</div>"));
                $box.on("click", ".wpb-dl", function () {
                    var f = $(this).data("file");
                    downloadFile(f, res.sources[f]);
                });
                $result.html($box);
            }
        }).fail(function () {
            $result.html(alertBox("danger", "Error", "No se pudo contactar al servidor."));
        }).always(function () {
            $generate.prop("disabled", false).html('<i class="bi bi-magic me-1"></i>Generar página');
        });
    }

    function alertBox(type, title, body) {
        return '<div class="alert alert-' + type + '"><h6 class="mb-1">' + escapeHtml(title) + "</h6><div>" + body + "</div></div>";
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    /*=============================================
    Wire up events
    =============================================*/
    $table.on("change", function () {
        var t = $(this).val();
        if (t) {
            loadColumns(t);
        } else {
            $title.prop("disabled", true).empty().append("<option>Selecciona una tabla primero</option>");
            $generate.prop("disabled", true);
        }
    });

    $generate.on("click", generate);

    loadTables();
})();
