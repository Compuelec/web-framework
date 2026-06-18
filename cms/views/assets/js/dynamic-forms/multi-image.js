/*=============================================
Multi-image field
Upload several images to a record, with thumbnails, remove and an
optional per-column limit (data-max). Stores a JSON array of URLs in the
hidden .multiImageValue input.
=============================================*/

(function () {
    "use strict";

    var $ = window.jQuery;
    var DEFAULT_FOLDER = "1"; // root upload folder

    function syncField($field) {
        var urls = $field.find(".multiImageThumb").map(function () {
            return $(this).attr("data-url");
        }).get();
        $field.find(".multiImageValue").val(JSON.stringify(urls));

        var max = parseInt($field.attr("data-max"), 10) || 0;
        $field.find(".multiImageCounter").text(
            urls.length + (max > 0 ? " / " + max : "") + " imagen(es)"
        );
    }

    function addThumb($field, url) {
        var $thumb = $('<div class="multiImageThumb position-relative" style="width:90px;"></div>').attr("data-url", url);
        $thumb.append($('<img class="rounded border w-100" style="height:90px;object-fit:cover;">').attr("src", url));
        $thumb.append('<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1 multiImageRemove" style="line-height:1;" title="Quitar">&times;</button>');
        $field.find(".multiImageThumbs").append($thumb);
    }

    $(document).on("change", ".multiImageInput", function () {
        var input = this;
        var files = input.files;
        if (!files || !files.length) { return; }

        var $field  = $(input).closest(".multiImageField");
        var max     = parseInt($field.attr("data-max"), 10) || 0;
        var current = $field.find(".multiImageThumb").length;

        var list = Array.prototype.slice.call(files);
        if (max > 0 && current + list.length > max) {
            list = list.slice(0, Math.max(0, max - current));
            fncToastr("warning", "Máximo " + max + " imágenes");
        }
        if (!list.length) { input.value = ""; return; }

        var $spinner  = $field.find(".multiImageSpinner").show();
        var remaining = list.length;

        list.forEach(function (file) {
            var data = new FormData();
            data.append("file", file);
            data.append("folder", DEFAULT_FOLDER);
            data.append("token", window.CMS_TOKEN || "");

            $.ajax({
                url: (window.CMS_AJAX_PATH || "/ajax") + "/files.ajax.php",
                method: "POST", data: data, contentType: false, processData: false, dataType: "json"
            }).done(function (res) {
                if (res && res.status === 200 && res.link) {
                    addThumb($field, res.link);
                    syncField($field);
                } else {
                    fncToastr("error", (res && res.error) || "No se pudo subir una imagen");
                }
            }).fail(function () {
                fncToastr("error", "Error al subir una imagen");
            }).always(function () {
                remaining--;
                if (remaining === 0) { $spinner.hide(); input.value = ""; }
            });
        });
    });

    $(document).on("click", ".multiImageRemove", function () {
        var $field = $(this).closest(".multiImageField");
        $(this).closest(".multiImageThumb").remove();
        syncField($field);
    });

    $(function () {
        $(".multiImageField").each(function () { syncField($(this)); });
    });
})();
