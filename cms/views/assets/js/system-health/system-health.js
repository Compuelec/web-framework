/*=============================================
System Health
Diagnoses and repairs writable-directory permissions.
=============================================*/

(function () {
    "use strict";

    var $rows    = $("#sh-rows");
    var $summary = $("#sh-summary");
    var $check   = $("#sh-check");
    var $fix     = $("#sh-fix");

    if (!$rows.length) {
        return;
    }

    var url = (window.CMS_AJAX_PATH || "/ajax") + "/system-health.ajax.php";

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function statusBadge(r) {
        if (r.writable) {
            return '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Escribible</span>';
        }
        if (!r.exists) {
            return '<span class="badge bg-secondary">No existe</span>';
        }
        return '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Sin permiso</span>';
    }

    function renderRow(r) {
        var html = "<tr>";
        html += "<td><code>" + escapeHtml(r.path) + "</code>";
        if (r.owner) {
            html += '<div class="small text-muted">dueño: ' + escapeHtml(r.owner) + "</div>";
        }
        html += "</td>";
        html += "<td>" + escapeHtml(r.label) + "</td>";
        html += '<td class="text-center">' + statusBadge(r);
        if (!r.writable && r.command) {
            var cmdId = "cmd-" + Math.random().toString(36).slice(2);
            html += '<div class="mt-2 text-start">' +
                '<div class="small text-muted mb-1">Ejecuta esto (o entrégalo a tu hosting):</div>' +
                '<div class="input-group input-group-sm">' +
                '<input type="text" class="form-control font-monospace" id="' + cmdId + '" readonly value="' + escapeHtml(r.command) + '">' +
                '<button class="btn btn-outline-secondary sh-copy" data-target="' + cmdId + '"><i class="bi bi-clipboard"></i></button>' +
                "</div></div>";
        }
        html += "</td></tr>";
        return html;
    }

    function render(res) {
        if (!res || !res.success) {
            $rows.html('<tr><td colspan="3" class="text-danger text-center py-4">' +
                escapeHtml((res && res.error) || "Error al verificar.") + "</td></tr>");
            return;
        }

        var rowsHtml = res.results.map(renderRow).join("");
        $rows.html(rowsHtml);

        if (res.allOk) {
            $summary.html('<div class="alert alert-success mb-0">' +
                '<i class="bi bi-check-circle me-1"></i>Todo en orden: el sistema puede escribir en todos los directorios necesarios.</div>');
        } else {
            $summary.html('<div class="alert alert-warning mb-0">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>Algunos directorios no son escribibles. ' +
                'El servidor web corre como <code>' + escapeHtml(res.webUser) + "</code>. " +
                'Pulsa "Intentar reparar"; si no basta, usa el comando que aparece en cada fila.</div>');
        }
    }

    function run(action, $btn) {
        var original = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: url,
            method: "POST",
            dataType: "json",
            data: { action: action }
        }).done(render).fail(function () {
            $rows.html('<tr><td colspan="3" class="text-danger text-center py-4">No se pudo contactar al servidor.</td></tr>');
        }).always(function () {
            $btn.prop("disabled", false).html(original);
        });
    }

    $rows.on("click", ".sh-copy", function () {
        var input = document.getElementById($(this).data("target"));
        if (input) {
            input.select();
            try { document.execCommand("copy"); } catch (e) {}
        }
    });

    $check.on("click", function () { run("check", $check); });
    $fix.on("click", function () { run("fix", $fix); });

    // Initial check on load.
    run("check", $check);
})();
