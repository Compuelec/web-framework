/**
 * Workflow Field Handler
 * Handles workflow state transitions in dynamic forms
 */

var CMS_AJAX_PATH = window.CMS_AJAX_PATH || "/ajax";

/**
 * Handle workflow transition button click
 */
$(document).on('click', '.workflow-transition-btn', function(e) {
    e.preventDefault();

    var btn = $(this);
    var transitionId = btn.data('transition-id');
    var transitionLabel = btn.data('transition-label');
    var requireComment = btn.data('require-comment') == '1';
    var table = btn.data('table');
    var suffix = btn.data('suffix');
    var recordId = btn.data('record-id');

    // Disable button during processing
    btn.prop('disabled', true);

    if (requireComment) {
        // Show prompt for comment
        showCommentPrompt(transitionLabel, function(comment) {
            if (comment !== null && comment !== false) {
                executeWorkflowTransition(table, suffix, recordId, transitionId, comment, btn);
            } else {
                btn.prop('disabled', false);
            }
        });
    } else {
        // Show confirmation
        showConfirmation(transitionLabel, function(confirmed) {
            if (confirmed) {
                executeWorkflowTransition(table, suffix, recordId, transitionId, null, btn);
            } else {
                btn.prop('disabled', false);
            }
        });
    }
});

/**
 * Show confirmation dialog
 */
function showConfirmation(actionLabel, callback) {
    if (typeof fncSweetAlert === 'function') {
        fncSweetAlert('confirm', '¿Confirmar accion: ' + actionLabel + '?', '').then(function(result) {
            callback(result);
        });
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Confirmar accion',
            text: '¿' + actionLabel + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Si, continuar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            callback(result.isConfirmed);
        });
    } else {
        callback(confirm('¿Confirmar accion: ' + actionLabel + '?'));
    }
}

/**
 * Show comment prompt dialog
 */
function showCommentPrompt(actionLabel, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: actionLabel,
            text: 'Por favor, ingrese un comentario:',
            input: 'textarea',
            inputPlaceholder: 'Escriba su comentario aqui...',
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            inputValidator: function(value) {
                if (!value || value.trim() === '') {
                    return 'El comentario es obligatorio';
                }
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                callback(result.value);
            } else {
                callback(null);
            }
        });
    } else {
        var comment = prompt('Ingrese un comentario para: ' + actionLabel);
        callback(comment);
    }
}

/**
 * Execute workflow transition via AJAX
 */
function executeWorkflowTransition(table, suffix, recordId, transitionId, comment, btn) {
    var token = localStorage.getItem("tokenAdmin") || '';

    // Show loading
    if (typeof fncMatPreloader === 'function') {
        fncMatPreloader("on");
    }
    if (typeof fncSweetAlert === 'function') {
        fncSweetAlert("loading", "Procesando...", "");
    }

    var data = new FormData();
    data.append('action', 'executeTransition');
    data.append('table', table);
    data.append('suffix', suffix);
    data.append('record_id', recordId);
    data.append('transition_id', transitionId);
    data.append('token', token);

    if (comment) {
        data.append('comment', comment);
    }

    $.ajax({
        url: CMS_AJAX_PATH + "/workflow.ajax.php",
        method: "POST",
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        dataType: 'json',
        success: function(response) {
            // Hide loading
            if (typeof fncMatPreloader === 'function') {
                fncMatPreloader("off");
            }
            if (typeof fncSweetAlert === 'function') {
                fncSweetAlert("close", "", "");
            }

            if (response.success) {
                // Show success message
                if (typeof fncSweetAlert === 'function') {
                    fncSweetAlert("success", "Estado actualizado correctamente", "");
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Estado actualizado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    alert('Estado actualizado correctamente');
                }

                // Reload page after delay
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                // Show error message
                var errorMsg = response.error || "No se pudo actualizar el estado";

                if (typeof fncSweetAlert === 'function') {
                    fncSweetAlert("error", "Error", errorMsg);
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg
                    });
                } else {
                    alert('Error: ' + errorMsg);
                }

                // Re-enable button
                if (btn) {
                    btn.prop('disabled', false);
                }
            }
        },
        error: function(xhr, status, error) {
            // Hide loading
            if (typeof fncMatPreloader === 'function') {
                fncMatPreloader("off");
            }
            if (typeof fncSweetAlert === 'function') {
                fncSweetAlert("close", "", "");
            }

            // Show error
            var errorMsg = "Error de conexion: " + error;

            if (typeof fncSweetAlert === 'function') {
                fncSweetAlert("error", "Error de conexion", errorMsg);
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: errorMsg
                });
            } else {
                alert(errorMsg);
            }

            // Re-enable button
            if (btn) {
                btn.prop('disabled', false);
            }
        }
    });
}

/**
 * Initialize workflow fields on page load
 */
$(document).ready(function() {
    // Add any initialization logic here
    $('.workflow-field').each(function() {
        var moduleId = $(this).data('module-id');
        // Could load workflow config here if needed
    });
});
