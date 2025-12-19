/*=============================================
Data Export System
=============================================*/

var ExportData = {
    init: function() {
        this.addExportButtons();
        this.bindEvents();
    },
    
    addExportButtons: function() {
        // Add export buttons to dynamic tables that don't have them yet
        $('.card-header').each(function() {
            var $header = $(this);
            if ($header.find('.export-buttons').length === 0 && 
                $header.closest('#cardTable').length > 0) {
                
                // Check if buttons already exist in the nav
                var $nav = $header.find('.nav.justify-content-lg-end');
                if ($nav.length > 0 && $nav.find('.export-buttons').length === 0) {
                    // Insert before date range button
                    var $dateRangeItem = $nav.find('li:has(#daterange-btn)');
                    if ($dateRangeItem.length > 0) {
                        var $exportItem = $('<li class="nav-item p-0 me-2"></li>');
                        $exportItem.html(`
                            <div class="btn-group export-buttons" role="group">
                                <button type="button" class="btn btn-sm btn-outline-success export-btn" data-format="excel" title="Exportar a Excel">
                                    <i class="bi bi-file-earmark-excel"></i> Excel
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary export-btn" data-format="csv" title="Exportar a CSV">
                                    <i class="bi bi-filetype-csv"></i> CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger export-btn" data-format="pdf" title="Exportar a PDF">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                </button>
                            </div>
                        `);
                        $dateRangeItem.before($exportItem);
                    }
                }
            }
        });
    },
    
    bindEvents: function() {
        var self = this;
        
        $(document).on('click', '.export-btn', function() {
            var format = $(this).data('format');
            var $table = $(this).closest('#cardTable');
            
            if ($table.length > 0) {
                self.exportTable($table, format);
            }
        });
    },
    
    exportTable: function($table, format) {
        var self = this;
        var module = JSON.parse($('#contentModule').val() || '{}');
        
        if (!module || !module.title_module) {
            fncToastr('error', 'Could not get module information');
            return;
        }
        
        // Use CMS export endpoint that handles everything
        var params = new URLSearchParams({
            module: module.title_module,
            format: format,
            token: localStorage.getItem('tokenAdmin') || ''
        });
        
        // Apply current filters if they exist
        var search = $('#searchTable').val();
        var between1 = $('#between1').val();
        var between2 = $('#between2').val();
        
        if (search) {
            params.append('search', search);
        }
        
        if (between1 && between2) {
            params.append('between1', between1);
            params.append('between2', between2);
        }
        
        // For PDF, open in new window/tab
        if (format === 'pdf') {
            var exportUrl = CMS_AJAX_PATH + '/export.ajax.php?' + params.toString();
            var newWindow = window.open(exportUrl, '_blank', 'width=800,height=600');
            
            // Show success message
            if (newWindow) {
                fncToastr('success', 'Generando PDF en nueva ventana...');
            } else {
                fncToastr('warning', 'Por favor, permite ventanas emergentes para exportar PDF');
            }
        } else {
            // For CSV and Excel, download directly
            fncMatPreloader('on');
            fncSweetAlert('loading', 'Preparing export...', '');
            
            // Create temporary link to trigger download
            var link = document.createElement('a');
            link.href = CMS_AJAX_PATH + '/export.ajax.php?' + params.toString();
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Close loading after a moment
            setTimeout(function() {
                fncMatPreloader('off');
                fncSweetAlert('close');
                fncToastr('success', 'Exportación completada');
            }, 1000);
        }
    },
    
    arrayToCSV: function(data) {
        return data.map(function(row) {
            return row.map(function(cell) {
                // Escape quotes and wrap in quotes if contains commas or newlines
                var cellStr = String(cell || '');
                if (cellStr.includes(',') || cellStr.includes('\n') || cellStr.includes('"')) {
                    cellStr = '"' + cellStr.replace(/"/g, '""') + '"';
                }
                return cellStr;
            }).join(',');
        }).join('\n');
    },
    
    downloadFile: function(blob, filename, mimeType) {
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    ExportData.init();
    // Try again after a short delay to catch dynamically loaded content
    setTimeout(function() {
        ExportData.addExportButtons();
    }, 500);
});

// Also initialize after dynamically loading tables
$(document).ajaxComplete(function() {
    setTimeout(function() {
        ExportData.addExportButtons();
    }, 100);
});

// Use MutationObserver for better detection of new content
if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function(mutations) {
        ExportData.addExportButtons();
    });
    
    $(document).ready(function() {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
}

