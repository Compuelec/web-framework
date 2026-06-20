/*=============================================
Data Export System

Fetches the table data as JSON from export.ajax.php and builds the file in the
browser using locally-vendored libraries (no CDN):
  - CSV   : UTF-8 with BOM (opens with correct accents in Excel)
  - Excel : real .xlsx, styled header + zebra rows (xlsx-js-style)
  - PDF   : styled landscape table (jsPDF + autotable)

Libraries are loaded on demand the first time a format is used, so they don't
weigh on every CMS page load.
=============================================*/

var ExportData = {

    // Brand accent used in the PDF/Excel headers (CMS primary purple).
    accent: [108, 95, 252],
    zebra:  [247, 250, 252],

    _scripts: {}, // url -> Promise (load-once cache)

    init: function () {
        this.addExportButtons();
        this.bindEvents();
    },

    pluginBase: function () {
        return (window.CMS_BASE_PATH || '') + '/views/assets/plugins';
    },

    // Lazy-load a local script once; returns a Promise.
    loadScript: function (url) {
        if (this._scripts[url]) { return this._scripts[url]; }
        this._scripts[url] = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = url;
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('No se pudo cargar ' + url)); };
            document.head.appendChild(s);
        });
        return this._scripts[url];
    },

    addExportButtons: function () {
        // Add export buttons to dynamic tables that don't have them yet
        $('.card-header').each(function () {
            var $header = $(this);
            if ($header.find('.export-buttons').length === 0 &&
                $header.closest('#cardTable').length > 0) {

                var $nav = $header.find('.nav.justify-content-lg-end');
                if ($nav.length > 0 && $nav.find('.export-buttons').length === 0) {
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

    bindEvents: function () {
        var self = this;
        $(document).on('click', '.export-btn', function () {
            var format = $(this).data('format');
            var $table = $(this).closest('#cardTable');
            if ($table.length > 0) {
                self.exportTable($table, format);
            }
        });
    },

    // Fetch the prepared rows/types as JSON for the current table + filters.
    fetchData: function ($table) {
        var module = JSON.parse($('#contentModule').val() || '{}');
        if (!module || !module.title_module) {
            return Promise.reject(new Error('No se pudo obtener la información del módulo'));
        }

        var params = new URLSearchParams({
            module: module.title_module,
            format: 'json',
            token: window.CMS_TOKEN || ''
        });

        var search = $('#searchTable').val();
        var between1 = $('#between1').val();
        var between2 = $('#between2').val();
        if (search) { params.append('search', search); }
        if (between1 && between2) {
            params.append('between1', between1);
            params.append('between2', between2);
        }

        return fetch(CMS_AJAX_PATH + '/export.ajax.php?' + params.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error('El servidor respondió ' + r.status); }
                return r.json();
            })
            .then(function (res) {
                if (!res || !res.success || !Array.isArray(res.rows) || res.rows.length === 0) {
                    throw new Error('No hay datos para exportar');
                }
                return res;
            });
    },

    exportTable: function ($table, format) {
        var self = this;

        fncMatPreloader('on');

        this.fetchData($table)
            .then(function (res) {
                if (format === 'csv')   { return self.buildCSV(res); }
                if (format === 'excel') { return self.buildXLSX(res); }
                if (format === 'pdf')   { return self.buildPDF(res); }
            })
            .then(function () {
                fncMatPreloader('off');
                fncToastr('success', 'Exportación completada');
            })
            .catch(function (err) {
                fncMatPreloader('off');
                fncToastr('error', (err && err.message) || 'No se pudo exportar');
            });
    },

    fileName: function (res, ext) {
        var d = new Date();
        var stamp = d.getFullYear() + '-' +
            ('0' + (d.getMonth() + 1)).slice(-2) + '-' +
            ('0' + d.getDate()).slice(-2);
        return (res.title || 'export') + '_' + stamp + '.' + ext;
    },

    isNumericType: function (type) {
        return type === 'int' || type === 'double' || type === 'money' || type === 'index';
    },

    /* ---------------- CSV ---------------- */
    buildCSV: function (res) {
        var csv = res.rows.map(function (row) {
            return row.map(function (cell) {
                var s = (cell == null) ? '' : String(cell);
                if (/[",\n;]/.test(s)) { s = '"' + s.replace(/"/g, '""') + '"'; }
                return s;
            }).join(',');
        }).join('\r\n');

        // Prepend UTF-8 BOM so Excel reads accents correctly.
        var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        this.download(blob, this.fileName(res, 'csv'));
    },

    /* ---------------- Excel (.xlsx, styled) ---------------- */
    buildXLSX: function (res) {
        var self = this;
        return this.loadScript(this.pluginBase() + '/xlsx/xlsx.bundle.js').then(function () {
            var XLSX = window.XLSX;
            var aoa = res.rows;
            var ws = XLSX.utils.aoa_to_sheet(aoa);
            var range = XLSX.utils.decode_range(ws['!ref']);

            // Column widths from the longest cell in each column.
            ws['!cols'] = aoa[0].map(function (_, c) {
                var max = 10;
                aoa.forEach(function (row) {
                    var v = (row[c] == null) ? '' : String(row[c]);
                    if (v.length > max) { max = v.length; }
                });
                return { wch: Math.min(max + 2, 60) };
            });

            // Freeze the header row.
            ws['!freeze'] = { xSplit: 0, ySplit: 1, topLeftCell: 'A2', activePane: 'bottomLeft' };

            var thin = { style: 'thin', color: { rgb: 'E2E8F0' } };
            var border = { top: thin, bottom: thin, left: thin, right: thin };
            var headerFill = self.accent.map(function (n) { return ('0' + n.toString(16)).slice(-2); }).join('').toUpperCase();
            var zebraFill = self.zebra.map(function (n) { return ('0' + n.toString(16)).slice(-2); }).join('').toUpperCase();

            for (var r = range.s.r; r <= range.e.r; r++) {
                for (var c = range.s.c; c <= range.e.c; c++) {
                    var addr = XLSX.utils.encode_cell({ r: r, c: c });
                    if (!ws[addr]) { continue; }
                    if (r === 0) {
                        ws[addr].s = {
                            font: { bold: true, color: { rgb: 'FFFFFF' }, sz: 11 },
                            fill: { fgColor: { rgb: headerFill } },
                            alignment: { horizontal: 'center', vertical: 'center' },
                            border: border
                        };
                    } else {
                        var numeric = self.isNumericType(res.types[c]);
                        ws[addr].s = {
                            alignment: { horizontal: numeric ? 'right' : 'left', vertical: 'center' },
                            border: border,
                            fill: (r % 2 === 0) ? { fgColor: { rgb: zebraFill } } : { fgColor: { rgb: 'FFFFFF' } }
                        };
                    }
                }
            }

            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Datos');
            XLSX.writeFile(wb, self.fileName(res, 'xlsx'));
        });
    },

    /* ---------------- PDF (styled, landscape) ---------------- */
    buildPDF: function (res) {
        var self = this;
        var base = this.pluginBase() + '/jspdf';
        return this.loadScript(base + '/jspdf.umd.min.js')
            .then(function () { return self.loadScript(base + '/jspdf.plugin.autotable.min.js'); })
            .then(function () {
                var jsPDF = window.jspdf.jsPDF;
                var doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                var pageW = doc.internal.pageSize.getWidth();
                var accent = self.accent;

                // Title band.
                doc.setFillColor(accent[0], accent[1], accent[2]);
                doc.rect(0, 0, pageW, 18, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(15);
                doc.text(String(res.title || 'Exportación').toUpperCase(), 14, 12);

                // Meta line.
                var now = new Date();
                doc.setTextColor(120, 120, 120);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.text('Exportado: ' + now.toLocaleString('es-CL') + '  ·  ' + (res.rows.length - 1) + ' registro(s)', 14, 24);

                // Right-align numeric columns.
                var columnStyles = {};
                (res.types || []).forEach(function (t, i) {
                    if (self.isNumericType(t)) { columnStyles[i] = { halign: 'right' }; }
                });

                doc.autoTable({
                    head: [res.rows[0]],
                    body: res.rows.slice(1),
                    startY: 28,
                    styles: { fontSize: 8, cellPadding: 2.5, overflow: 'linebreak', lineColor: [226, 232, 240], lineWidth: 0.1, textColor: [45, 55, 72] },
                    headStyles: { fillColor: accent, textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    alternateRowStyles: { fillColor: self.zebra },
                    columnStyles: columnStyles,
                    margin: { top: 28, left: 14, right: 14 },
                    tableWidth: 'auto',
                    didDrawPage: function (data) {
                        var page = doc.internal.getNumberOfPages();
                        doc.setFontSize(8);
                        doc.setTextColor(150, 150, 150);
                        doc.text(
                            'Página ' + data.pageNumber + ' de ' + page,
                            pageW / 2,
                            doc.internal.pageSize.getHeight() - 8,
                            { align: 'center' }
                        );
                    }
                });

                doc.save(self.fileName(res, 'pdf'));
            });
    },

    download: function (blob, filename) {
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
$(document).ready(function () {
    ExportData.init();
    setTimeout(function () { ExportData.addExportButtons(); }, 500);
});

// Also initialize after dynamically loading tables
$(document).ajaxComplete(function () {
    setTimeout(function () { ExportData.addExportButtons(); }, 100);
});

// Detect dynamically inserted tables
if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function () {
        ExportData.addExportButtons();
    });
    $(document).ready(function () {
        observer.observe(document.body, { childList: true, subtree: true });
    });
}
