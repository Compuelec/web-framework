<!-- The Modal -->
<div class="modal fade" id="myModule">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded">

      <form method="POST" class="needs-validation" novalidate>

        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title text-capitalize">Módulos</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal body -->
        <div class="modal-body px-4">

          <!--=============================================
          Seleccionar tipo de módulo
          ===============================================-->

          <div class="form-group mb-3">

            <label for="type_module">Tipo<sup>*</sup></label>

            <select
            class="form-select form-select-sm rounded" 
            name="type_module" 
            id="type_module">
              
              <option value="breadcrumbs">Breadcrumb</option>
              <option value="metrics">Métrica</option>
              <option value="graphics">Gráfico</option>
              <option value="tables">Tabla</option>
              <option value="custom">Personalizable</option>

            </select>

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <!--=============================================
          Agregar título al módulo
          ===============================================-->

          <div class="form-group mb-3">

            <label for="title_module">Título<sup>*</sup></label>

            <input 
            type="text"
            class="form-control rounded form-control-sm"
            id="title_module"
            name="title_module"
            required
            >

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>

          <!--=============================================
          Add suffix field for table module
          ===============================================-->

          <div id="suffixModule" style="display:none">
            <div class="mb-3">
              <h6 class="text-muted mb-3">
                <i class="bi bi-table"></i> Configuración de Tabla
              </h6>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-gear"></i> Configuración General
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="suffix_module" class="form-label small fw-semibold">Sufijo<sup>*</sup></label>
                        <input 
                          type="text"
                          class="form-control form-control-sm rounded"
                          id="suffix_module"
                          name="suffix_module"
                          placeholder="Sufijo para identificadores"
                        >
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-6">
                        <label for="editable_module" class="form-label small fw-semibold">Editable</label>
                        <select class="form-select form-select-sm rounded" name="editable_module" id="editable_module">
                          <option value="1">ON</option>
                          <option value="0">OFF</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!--=============================================
          Add width field for module
          ===============================================-->

          <div class="form-group mb-3">
            <label for="width_module" class="form-label small fw-semibold">Ancho</label>
            <select class="form-select form-select-sm rounded" name="width_module" id="width_module" required>
              <option value="25">25%</option>
              <option value="33">33%</option>
              <option value="50">50%</option>
              <option value="75">75%</option>
              <option value="100" selected>100%</option>
            </select>
            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>
          </div>

          <!--=============================================
          Add fields for metrics
          ===============================================-->

          <div id="metricsBlock" style="display:none">
            
            <div class="mb-3">
              <h6 class="text-muted mb-3">
                <i class="bi bi-bar-chart-line"></i> Configuración de Métrica
              </h6>
            </div>

            <div class="row g-3">
              
              <!--=============================================
              Data Source Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-database"></i> Fuente de Datos
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label for="metricType" class="form-label small fw-semibold">Tipo de Métrica</label>
                        <select class="form-select form-select-sm rounded changeMetric" id="metricType">
                          <option value="total">Total</option>
                          <option value="add">Suma</option>
                          <option value="average">Promedio</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="metricTable" class="form-label small fw-semibold">Tabla</label>
                        <select class="form-select form-select-sm rounded changeMetric" id="metricTable">
                          <option value="">Seleccionar tabla...</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="metricColumn" class="form-label small fw-semibold">Columna</label>
                        <select class="form-select form-select-sm rounded changeMetric" id="metricColumn" disabled>
                          <option value="">Seleccione primero una tabla</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--=============================================
              Display Settings Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-palette"></i> Apariencia
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label for="metricConfig" class="form-label small fw-semibold">Configuración</label>
                        <select class="form-select form-select-sm rounded changeMetric" id="metricConfig">
                          <option value="unit">Unidad</option>
                          <option value="price">Precio</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="metricIcon" class="form-label small fw-semibold">Icono</label>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text bg-white">
                            <i class="bi" id="metricIconPreview">bi-gear</i>
                          </span>
                          <input 
                            type="text" 
                            class="form-control form-control-sm rounded changeMetric cleanIcon" 
                            id="metricIcon"
                            value="bi-gear"
                            placeholder="Seleccionar icono"
                            readonly
                          >
                          <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#iconSelectorModalModule" title="Seleccionar icono">
                            <i class="bi bi-grid-3x3-gap"></i>
                          </button>
                        </div>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="metricColor" class="form-label small fw-semibold">Color</label>
                        <select class="form-select form-select-sm rounded changeMetric" id="metricColor">
                          <option class="bg-primary" value="108, 95, 252">Primary</option>
                          <option class="bg-secondary" value="5, 195, 251">Secondary</option>
                          <option class="bg-warning" value="247, 183, 49">Warning</option>
                          <option class="bg-info" value="247, 183, 49">Info</option>
                          <option class="bg-success" value="9, 173, 149">Success</option>
                          <option class="bg-danger" value="232, 38, 70">Danger</option>
                          <option class="bg-light" value="246, 246, 251">Light</option>
                          <option class="bg-dark" value="52, 58, 64">Dark</option>
                          <option class="bg-blue" value="43, 62, 101">Blue</option>
                          <option class="bg-indigo" value="77, 93, 219">Indigo</option>
                          <option class="bg-purple" value="137, 39, 236">Purple</option>
                          <option class="bg-pink" value="236, 130, 239">Pink</option>
                          <option class="bg-red" value="208, 61, 70">Red</option>
                          <option class="bg-maroon" value="128, 0, 0">Maroon</option>
                          <option class="bg-orange" value="252, 115, 3">Orange</option>
                          <option class="bg-yellow" value="255, 193, 2">Yellow</option>
                          <option class="bg-green" value="29, 216, 113">Green</option>
                          <option class="bg-teal" value="28, 175, 159">Teal</option>
                          <option class="bg-cyan" value="0, 209, 209">Cyan</option>
                          <option class="bg-gray" value="134, 153, 163">Gray</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!--=============================================
          Add fields for graphics
          ===============================================-->

          <div id="graphicsBlock" style="display:none">
            
            <div class="mb-3">
              <h6 class="text-muted mb-3">
                <i class="bi bi-graph-up"></i> Configuración de Gráfico
              </h6>
            </div>

            <div class="row g-3">
              
              <!--=============================================
              Chart Configuration Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-sliders"></i> Configuración del Gráfico
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="graphicType" class="form-label small fw-semibold">Tipo de Gráfico</label>
                        <select class="form-select form-select-sm rounded changeGraphic" id="graphicType">
                          <option value="line">Línea</option>
                          <option value="bar">Barra</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-6">
                        <label for="graphicColor" class="form-label small fw-semibold">Color</label>
                        <select class="form-select form-select-sm rounded changeGraphic" id="graphicColor">
                          <option class="bg-primary" value="108, 95, 252">Primary</option>
                          <option class="bg-secondary" value="5, 195, 251">Secondary</option>
                          <option class="bg-warning" value="247, 183, 49">Warning</option>
                          <option class="bg-info" value="247, 183, 49">Info</option>
                          <option class="bg-success" value="9, 173, 149">Success</option>
                          <option class="bg-danger" value="232, 38, 70">Danger</option>
                          <option class="bg-light" value="246, 246, 251">Light</option>
                          <option class="bg-dark" value="52, 58, 64">Dark</option>
                          <option class="bg-blue" value="43, 62, 101">Blue</option>
                          <option class="bg-indigo" value="77, 93, 219">Indigo</option>
                          <option class="bg-purple" value="137, 39, 236">Purple</option>
                          <option class="bg-pink" value="236, 130, 239">Pink</option>
                          <option class="bg-red" value="208, 61, 70">Red</option>
                          <option class="bg-maroon" value="128, 0, 0">Maroon</option>
                          <option class="bg-orange" value="252, 115, 3">Orange</option>
                          <option class="bg-yellow" value="255, 193, 2">Yellow</option>
                          <option class="bg-green" value="29, 216, 113">Green</option>
                          <option class="bg-teal" value="28, 175, 159">Teal</option>
                          <option class="bg-cyan" value="0, 209, 209">Cyan</option>
                          <option class="bg-gray" value="134, 153, 163">Gray</option>
                        </select>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--=============================================
              Data Source Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-database"></i> Fuente de Datos
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label for="graphicTable" class="form-label small fw-semibold">Tabla</label>
                        <input 
                          type="text" 
                          class="form-control form-control-sm rounded changeGraphic" 
                          id="graphicTable"
                          placeholder="Nombre de la tabla"
                        >
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="graphicX" class="form-label small fw-semibold">Eje X</label>
                        <input 
                          type="text" 
                          class="form-control form-control-sm rounded changeGraphic" 
                          id="graphicX"
                          placeholder="Columna para eje X"
                        >
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-4">
                        <label for="graphicY" class="form-label small fw-semibold">Eje Y</label>
                        <input 
                          type="text" 
                          class="form-control form-control-sm rounded changeGraphic" 
                          id="graphicY"
                          placeholder="Columna para eje Y"
                        >
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!--=============================================
          Add fields for tables and columns
          ===============================================-->

          <div id="columnsBlock" style="display:none">
            
            <div class="mb-3">
              <h6 class="text-muted mb-3">
                <i class="bi bi-list-columns"></i> Columnas de la Tabla
              </h6>
            </div>

            <div class="card border-0 bg-light">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="card-title text-muted mb-0">
                    <i class="bi bi-database"></i> Gestión de Columnas
                  </h6>
                  <button type="button" class="btn btn-sm btn-default border rounded addColumn">
                    <i class="bi bi-plus-circle"></i> Agregar Columna
                  </button>
                </div>

                <input type="hidden" id="indexColumns" name="indexColumns" value='[]'>
                <input type="hidden" id="deleteColumns" name="deleteColumns" value='[]'>

                <div class="row g-3 listColumns">
                  <!-- Columns will be added here dynamically -->
                </div>

                <div class="mt-3 text-muted small">
                  <i class="bi bi-info-circle"></i> Las columnas definidas aquí se crearán en la base de datos cuando se guarde el módulo.
                </div>
              </div>
            </div>

          </div>

          <input type="hidden" id="content_module" name="content_module">

        </div>

        <!-- Modal footer -->
        <div class="modal-footer d-flex justify-content-between">
          
          <div><button type="button" class="btn btn-dark rounded" data-bs-dismiss="modal">Cerrar</button></div>
          <div><button type="submit" class="btn btn-default backColor rounded">Guardar</button></div>
          
        </div>

    

    </div>
  </div>
</div>

<!--=============================================
Icon Selector Modal for Modules - Reusable Component
===============================================-->
<div class="modal fade" id="iconSelectorModalModule" tabindex="-1" aria-labelledby="iconSelectorModalModuleLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="iconSelectorModalModuleLabel">
					<i class="bi bi-palette"></i> Seleccionar Icono
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<input type="text" class="form-control" id="iconSearchModule" placeholder="Buscar icono...">
				</div>
				<div class="icon-grid" id="iconGridModule" style="max-height: 500px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;">
					<!-- Icons will be loaded dynamically -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
			</div>
		</div>
	</div>
</div>

<!--=============================================
Initialize Icon Selector for Modules
===============================================-->
<?php
// Get CMS base path
require_once __DIR__ . '/../../../controllers/template.controller.php';
$cmsBasePath = TemplateController::cmsBasePath();
?>
<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/selectors/selectors.css">
<script src="<?php echo $cmsBasePath ?>/views/assets/js/selectors/icon-selector.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Initialize icon selector for modules
	if (typeof initIconSelector === 'function') {
		initIconSelector({
			inputId: 'metricIcon',
			previewId: 'metricIconPreview',
			modalId: 'iconSelectorModalModule',
			gridId: 'iconGridModule',
			searchId: 'iconSearchModule'
		});
	}
	
	// Update preview when input value changes
	// Use event delegation to ensure it works even when modal is opened dynamically
	$(document).off('input change', '#metricIcon').on('input change', '#metricIcon', function(e) {
		var iconValue = $(this).val() || 'bi-gear';
		
		// Clean the value if it has quotes (from cleanIcon listener)
		if (iconValue.indexOf('"') !== -1) {
			var parts = iconValue.split('"');
			iconValue = parts.length > 1 ? parts[1] : parts[0];
			$(this).val(iconValue);
		}
		
		// Update preview
		$('#metricIconPreview').attr('class', 'bi ' + iconValue);
	});
	
	// Also listen for when modal is shown to update preview
	$('#myModule').on('shown.bs.modal', function() {
		if ($('#metricIcon').length && $('#metricIcon').val()) {
			var iconValue = $('#metricIcon').val();
			// Clean the value if it has quotes
			if (iconValue.indexOf('"') !== -1) {
				var parts = iconValue.split('"');
				iconValue = parts.length > 1 ? parts[1] : parts[0];
				$('#metricIcon').val(iconValue);
			}
			$('#metricIconPreview').attr('class', 'bi ' + iconValue);
		}
	});
	
	// Listen for when icon selector modal closes to ensure value is updated
	$('#iconSelectorModalModule').on('hidden.bs.modal', function() {
		setTimeout(function() {
			var iconValue = $('#metricIcon').val() || 'bi-gear';
			// Clean the value if it has quotes
			if (iconValue.indexOf('"') !== -1) {
				var parts = iconValue.split('"');
				iconValue = parts.length > 1 ? parts[1] : parts[0];
				$('#metricIcon').val(iconValue);
			}
			$('#metricIconPreview').attr('class', 'bi ' + iconValue);
			// Update content_module directly without triggering change to avoid loops
			if (typeof updateMetricContent === 'function') {
				updateMetricContent();
			}
		}, 100);
	});

	// Load tables when metrics block is shown
	$('#myModule').on('shown.bs.modal', function() {
		if ($('#type_module').val() === 'metrics') {
			loadTables();
		}
	});

	// Load tables when type changes to metrics
	$('#type_module').on('change', function() {
		if ($(this).val() === 'metrics') {
			loadTables();
		} else {
			// Reset selects when switching away from metrics
			$('#metricTable').val('').trigger('change');
			$('#metricColumn').val('').prop('disabled', true);
		}
	});

	// Load columns when table is selected
	$('#metricTable').on('change', function() {
		var tableName = $(this).val();
		if (tableName) {
			loadTableColumns(tableName);
			// Update content_module when table changes
			updateMetricContent();
		} else {
			$('#metricColumn').html('<option value="">Seleccione primero una tabla</option>').prop('disabled', true);
			// Update content_module when table is cleared
			updateMetricContent();
		}
	});

	// Update content_module when column changes
	$('#metricColumn').on('change', function() {
		updateMetricContent();
	});

	// Function to update content_module with current metric values
	function updateMetricContent() {
		var iconValue = $('#metricIcon').val() || 'bi-gear';
		// Use JSON.stringify to properly escape and format the JSON
		var metricData = {
			type: $('#metricType').val() || 'total',
			table: $('#metricTable').val() || '',
			column: $('#metricColumn').val() || '',
			config: $('#metricConfig').val() || 'unit',
			icon: iconValue,
			color: $('#metricColor').val() || '108, 95, 252'
		};
		$('#content_module').val(JSON.stringify(metricData));
	}

	// Function to load tables
	function loadTables() {
		$.ajax({
			url: '<?php echo $cmsBasePath ?>/ajax/modules.ajax.php?action=getTables',
			method: 'GET',
			dataType: 'json',
			success: function(response) {
				if (response.status === 200 && response.results.length > 0) {
					var options = '<option value="">Seleccionar tabla...</option>';
					response.results.forEach(function(table) {
						options += '<option value="' + table + '">' + table + '</option>';
					});
					$('#metricTable').html(options);
				} else {
					$('#metricTable').html('<option value="">No hay tablas disponibles</option>');
				}
			},
			error: function() {
				$('#metricTable').html('<option value="">Error al cargar tablas</option>');
			}
		});
	}

	// Function to load columns from a table
	function loadTableColumns(tableName) {
		$('#metricColumn').prop('disabled', true).html('<option value="">Cargando columnas...</option>');
		
		$.ajax({
			url: '<?php echo $cmsBasePath ?>/ajax/modules.ajax.php',
			method: 'POST',
			data: {
				action: 'getTableColumns',
				tableName: tableName
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === 200 && response.results.length > 0) {
					var options = '<option value="">Seleccionar columna...</option>';
					response.results.forEach(function(column) {
						options += '<option value="' + column + '">' + column + '</option>';
					});
					$('#metricColumn').html(options).prop('disabled', false);
				} else {
					$('#metricColumn').html('<option value="">No hay columnas disponibles</option>').prop('disabled', true);
				}
			},
			error: function() {
				$('#metricColumn').html('<option value="">Error al cargar columnas</option>').prop('disabled', true);
			}
		});
	}
});
</script>