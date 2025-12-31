/*=============================================
Abrir ventana modal de páginas
=============================================*/

var CMS_AJAX_PATH = window.CMS_AJAX_PATH || "/ajax";

/*=============================================
Load tables for metrics module (available globally)
=============================================*/

function loadTablesForEdit(savedTable, savedColumn) {
	$.ajax({
		url: CMS_AJAX_PATH + "/modules.ajax.php?action=getTables",
		method: 'GET',
		dataType: 'json',
		success: function(response) {
			if (response.status === 200 && response.results.length > 0) {
				var options = '<option value="">Seleccionar tabla...</option>';
				response.results.forEach(function(table) {
					// Use strict comparison and trim to ensure match
					var tableValue = String(table).trim();
					var savedTableValue = String(savedTable).trim();
					var selected = (tableValue === savedTableValue) ? 'selected' : '';
					options += '<option value="' + table + '" ' + selected + '>' + table + '</option>';
				});
				$("#metricTable").html(options);
				
				// If a table was saved, load its columns
				if (savedTable && savedTable !== "") {
					// Set the table value explicitly
					$("#metricTable").val(savedTable);
					// Load columns directly
					loadTableColumnsForEdit(savedTable, savedColumn);
				} else {
					$("#metricColumn").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
				}
			} else {
				$("#metricTable").html('<option value="">No hay tablas disponibles</option>');
			}
		},
			error: function(xhr, status, error) {
				$("#metricTable").html('<option value="">Error al cargar tablas</option>');
			}
	});
}

/*=============================================
Load columns from a table for metrics module (available globally)
=============================================*/

function loadTableColumnsForEdit(tableName, savedColumn) {
	if (!tableName || tableName === "") {
		$("#metricColumn").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
		return;
	}
	
	$("#metricColumn").prop("disabled", true).html('<option value="">Cargando columnas...</option>');
	
	$.ajax({
		url: CMS_AJAX_PATH + "/modules.ajax.php",
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
					// Use strict comparison and trim to ensure match
					var columnValue = String(column).trim();
					var savedColumnValue = String(savedColumn).trim();
					var selected = (columnValue === savedColumnValue) ? 'selected' : '';
					options += '<option value="' + column + '" ' + selected + '>' + column + '</option>';
				});
				$("#metricColumn").html(options).prop("disabled", false);
				
				// Set the column value explicitly if it was saved
				if (savedColumn && savedColumn !== "") {
					$("#metricColumn").val(savedColumn);
				}
				
				// Update content_module after column is loaded using JSON.stringify
				var iconValue = $("#metricIcon").val() || "bi-gear";
				var metricData = {
					type: $("#metricType").val() || "total",
					table: tableName,
					column: $("#metricColumn").val() || savedColumn || "",
					config: $("#metricConfig").val() || "unit",
					icon: iconValue,
					color: $("#metricColor").val() || "108, 95, 252"
				};
				$("#content_module").val(JSON.stringify(metricData));
				
				// Trigger change event to ensure all handlers are called
				$("#metricColumn").trigger('change');
			} else {
				$("#metricColumn").html('<option value="">No hay columnas disponibles</option>').prop("disabled", true);
			}
		},
			error: function(xhr, status, error) {
				$("#metricColumn").html('<option value="">Error al cargar columnas</option>').prop("disabled", true);
			}
	});
}

$(document).on("click",".myModule",function(){

	var idPage = $(this).attr("idPage");
	var item = $(this).attr("item");
	

	$("#myModule").modal("show");

	/*=============================================
	Cuando se abre la ventana modal
	=============================================*/

	$("#myModule").on('shown.bs.modal', function () {

		$("input[name='id_page_module']").remove();
		$("input[name='id_module']").remove();

		/*=============================================
		Capturar el Id de la página
		=============================================*/

		$("#type_module").before(`
			<input type="hidden" value="${btoa(idPage)}" name="id_page_module">
		 `)

		$("#metricsBlock").hide();
		$("#graphicsBlock").hide();
		$("#suffixModule").hide();
		$("#editableModule").hide();
		$("#columnsBlock").hide();

		/*=============================================
		tipo de módulo seleccionado
		=============================================*/

		$(document).on("change","#type_module",function(){

			$("#metricsBlock").hide();
			$("#graphicsBlock").hide();
			$("#suffixModule").hide();
			$("#editableModule").hide();
			$("#columnsBlock").hide();

			/*=============================================
			Aparecer campos de métricas
			=============================================*/

			if($(this).val() == "metrics"){

				$("#metricsBlock").show();

				// Initialize icon with default value if empty
				if(!$("#metricIcon").val()){
					$("#metricIcon").val("bi-gear");
					$("#metricIconPreview").attr("class", "bi bi-gear");
				}

			}

			/*=============================================
			Aparecer campos de gráficos
			=============================================*/

			if($(this).val() == "graphics"){

				$("#graphicsBlock").show();

				// Load tables when graphics block is shown
				if (typeof loadTablesForGraphic === 'function') {
					loadTablesForGraphic();
				}

			}

			/*=============================================
			Aparecer campos de tablas
			=============================================*/

			if($(this).val() == "tables"){

				$("#suffixModule").show();
				$("#editableModule").show();
				$("#columnsBlock").show();
			}


		})
		

		/*=============================================
		Estamos editando módulo
		=============================================*/

		if(item != undefined){

			$("#type_module").before(`
				<input type="hidden" value="${btoa(JSON.parse(item).id_module)}" name="id_module">
			`)

			/*=============================================
			tipo breadcrumbs
			=============================================*/

			if(JSON.parse(item).type_module == "breadcrumbs"){

				$("#type_module").val(JSON.parse(item).type_module);
				$("#type_module").attr("disabled",true);
				$("#title_module").attr("readonly",false);
				$("#title_module").val(JSON.parse(item).title_module);
				$("#width_module").val(JSON.parse(item).width_module);
			}

			/*=============================================
			tipo metrics
			=============================================*/

			if(JSON.parse(item).type_module == "metrics"){

				$("#type_module").val(JSON.parse(item).type_module);
				$("#type_module").attr("disabled",true);
				$("#title_module").attr("readonly",false);
				$("#title_module").val(JSON.parse(item).title_module);
				$("#width_module").val(JSON.parse(item).width_module);
			
				$("#metricsBlock").show();

				// Parse content_module - handle both string and already parsed cases
				var moduleData = JSON.parse(item);
				var contentModuleStr = moduleData.content_module;
				var contentData;
				
				try {
					// Try to parse if it's a string
					if (typeof contentModuleStr === 'string') {
						contentData = JSON.parse(contentModuleStr);
					} else {
						// Already an object
						contentData = contentModuleStr;
					}
				} catch(e) {
					contentData = {};
				}

				var savedTable = contentData.table || "";
				var savedColumn = contentData.column || "";

				$("#metricType").val(contentData.type || "total");
				$("#metricConfig").val(contentData.config || "unit");
				var metricIcon = contentData.icon || "bi-gear";
				
				// Set icon value and update preview
				$("#metricIcon").val(metricIcon);
				// Update preview immediately
				$("#metricIconPreview").attr("class", "bi " + metricIcon);
				// Trigger change event to ensure all listeners are notified
				$("#metricIcon").trigger('change');
				
				$("#metricColor").val(contentData.color || "108, 95, 252");

				// Initialize content_module with current values using JSON.stringify
				var metricData = {
					type: contentData.type || "total",
					table: savedTable,
					column: savedColumn,
					config: contentData.config || "unit",
					icon: metricIcon,
					color: contentData.color || "108, 95, 252"
				};
				$("#content_module").val(JSON.stringify(metricData));

				// Load tables first, then set the saved table and column
				// Use setTimeout to ensure modal is fully rendered
				setTimeout(function() {
					loadTablesForEdit(savedTable, savedColumn);
				}, 300);

			}

			/*=============================================
			tipo gráfico
			=============================================*/

			if(JSON.parse(item).type_module == "graphics"){

				$("#type_module").val(JSON.parse(item).type_module);
				$("#type_module").attr("disabled",true);
				$("#title_module").attr("readonly",false);
				$("#title_module").val(JSON.parse(item).title_module);
				$("#width_module").val(JSON.parse(item).width_module);
			
				$("#graphicsBlock").show();

				// Parse content_module - handle both string and already parsed cases
				var moduleData = JSON.parse(item);
				var contentModuleStr = moduleData.content_module;
				var contentData;
				
				try {
					// Try to parse if it's a string
					if (typeof contentModuleStr === 'string') {
						contentData = JSON.parse(contentModuleStr);
					} else {
						// Already an object
						contentData = contentModuleStr;
					}
				} catch(e) {
					contentData = {};
				}

				var savedTable = contentData.table || "";
				var savedXAxis = contentData.xAxis || "";
				var savedYAxis = contentData.yAxis || "";

				$("#graphicType").val(contentData.type || "line");
				$("#graphicColor").val(contentData.color || "108, 95, 252");

				// Initialize content_module with current values using JSON.stringify
				var graphicData = {
					type: contentData.type || "line",
					table: savedTable,
					xAxis: savedXAxis,
					yAxis: savedYAxis,
					color: contentData.color || "108, 95, 252"
				};
				$("#content_module").val(JSON.stringify(graphicData));

				// Load tables first, then set the saved table and columns
				// Use setTimeout to ensure modal is fully rendered
				setTimeout(function() {
					loadTablesForEditGraphic(savedTable, savedXAxis, savedYAxis);
				}, 300);

			}

			/*=============================================
			tipo tables
			=============================================*/

			if(JSON.parse(item).type_module == "tables"){

				$("#type_module").val(JSON.parse(item).type_module);
				$("#type_module").attr("disabled",true);
				$("#type_module").before(`
					<input type="hidden" name="type_module" value="tables">
				 `);
				$("#title_module").val(JSON.parse(item).title_module);
				$("#title_module").attr("readonly", true);
				$("#suffixModule").show();
				$("#suffix_module").val(JSON.parse(item).suffix_module);
				$("#suffix_module").attr("readonly", true);
				$("#width_module").val(JSON.parse(item).width_module);
				$("#editableModule").show();
				$("#editable_module").val(JSON.parse(item).editable_module);

				$("#columnsBlock").show();

				var indexColumns = JSON.parse($("#indexColumns").val());

				$(".listColumns").html('');

				/*=============================================
				Visualizar las columnas a editar
				=============================================*/

				JSON.parse(item).columns.forEach((e,i)=>{

					/*=============================================
					Marcar tipo de columna seleccionado
					=============================================*/

					var typeColumn = ["text","textarea","int","double","image","video","file","boolean","select","array","object","json","date","time","datetime","timestamp","code","link","color","money","password","email","relations","order","chatgpt"];
					var selectColumn = [];

					typeColumn.forEach((v,f)=>{

						if(e.type_column == v){

							selectColumn[f] = "selected";
						
						}else{

							selectColumn[f] = "";
						}
					})

					// Labels are always visible in the new design

					/*=============================================
					Marcar la selección de visibilidad
					=============================================*/

					var selectOn = "";
					var selectOff = "";	

					if(e.visible_column == 1){
						selectOn = "selected";
					}

					if(e.visible_column == 0){
						selectOff = "selected";
					}

					
					$(".listColumns").append(`

						<div class="col-12 mb-3">
							<div class="card border">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center mb-3">
										<h6 class="card-title mb-0 text-muted">
											<i class="bi bi-columns"></i> Columna ${i + 1}
										</h6>
										<button type="button" class="btn btn-sm btn-outline-danger deleteColumn" index="${i}" idItem="${e.id_column}" title="Eliminar columna">
											<i class="bi bi-trash"></i>
										</button>
									</div>

									<input type="hidden" name="id_column_${i}" value="${e.id_column}">
									<input type="hidden" name="original_title_column_${i}" value="${e.title_column}">

									<div class="row g-3">
										<div class="col-md-4">
											<label for="title_column_${i}" class="form-label small fw-semibold">Título<sup>*</sup></label>
											<input
												type="text"
												class="form-control form-control-sm rounded"
												id="title_column_${i}"
												name="title_column_${i}"
												value="${e.title_column}"
												placeholder="Nombre de la columna"
												required>
											<div class="valid-feedback">Válido</div>
											<div class="invalid-feedback">Campo Inválido</div>
										</div>

										<div class="col-md-3">
											<label for="alias_column_${i}" class="form-label small fw-semibold">Alias<sup>*</sup></label>
											<input
												type="text"
												class="form-control form-control-sm rounded"
												id="alias_column_${i}"
												name="alias_column_${i}"
												value="${e.alias_column}"
												placeholder="Alias de la columna"
												required>
											<div class="valid-feedback">Válido</div>
											<div class="invalid-feedback">Campo Inválido</div>
										</div>

										<div class="col-md-3">
											<label for="type_column_${i}" class="form-label small fw-semibold">Tipo<sup>*</sup></label>
											<select 
												class="form-select form-select-sm rounded" 
												id="type_column_${i}"
												name="type_column_${i}"
												required>
												<option value="text" ${selectColumn[0]}>Texto</option>
												<option value="textarea" ${selectColumn[1]}>Área Texto</option>
												<option value="int" ${selectColumn[2]}>Número Entero</option>
												<option value="double" ${selectColumn[3]}>Número Decimal</option>
												<option value="image" ${selectColumn[4]}>Imagen</option>
												<option value="video" ${selectColumn[5]}>Video</option>
												<option value="file" ${selectColumn[6]}>Archivo</option>
												<option value="boolean" ${selectColumn[7]}>Boleano</option>
												<option value="select" ${selectColumn[8]}>Selección</option>
												<option value="array" ${selectColumn[9]}>Arreglo</option>
												<option value="object" ${selectColumn[10]}>Objeto</option>
												<option value="json" ${selectColumn[11]}>JSON</option>
												<option value="date" ${selectColumn[12]}>Fecha</option>
												<option value="time" ${selectColumn[13]}>Hora</option>
												<option value="datetime" ${selectColumn[14]}>Fecha y Hora</option>
												<option value="timestamp" ${selectColumn[15]}>Fecha Automática</option>
												<option value="code" ${selectColumn[16]}>Código</option>
												<option value="link" ${selectColumn[17]}>Enlace</option>
												<option value="color" ${selectColumn[18]}>Color</option>
												<option value="money" ${selectColumn[19]}>Dinero</option>
												<option value="password" ${selectColumn[20]}>Contraseña</option>
												<option value="email" ${selectColumn[21]}>Email</option>
												<option value="relations" ${selectColumn[22]}>Relaciones</option>
												<option value="order" ${selectColumn[23]}>Ordenar</option>
												<option value="chatgpt" ${selectColumn[24]}>ChatGPT</option>
											</select>
											<div class="valid-feedback">Válido</div>
											<div class="invalid-feedback">Campo Inválido</div>
										</div>

										<div class="col-md-2">
											<label for="visible_column_${i}" class="form-label small fw-semibold">Visibilidad</label>
											<select 
												class="form-select form-select-sm rounded" 
												name="visible_column_${i}" 
												id="visible_column_${i}" 
												required>
												<option value="1" ${selectOn}>ON</option>
												<option value="0" ${selectOff}>OFF</option>							
											</select>
											<div class="valid-feedback">Válido</div>
											<div class="invalid-feedback">Campo Inválido</div>
										</div>
									</div>
								</div>
							</div>
						</div>

					 `)

					indexColumns.push(i);

				})

				$("#indexColumns").val(JSON.stringify(indexColumns));

			}

			/*=============================================
			tipo personalizable
			=============================================*/

			if(JSON.parse(item).type_module == "custom"){

				$("#type_module").val(JSON.parse(item).type_module);
				$("#type_module").attr("disabled",true);
				$("#title_module").val(JSON.parse(item).title_module);
				$("#title_module").attr("readonly", true);
			}


		/*=============================================
		Estamos creando módulo
		=============================================*/
		
		}else{
			$("#type_module").attr("disabled",false);
			$("#title_module").attr("readonly",false);
			$("#title_module").val("");
		}
	
	})

	/*=============================================
	Cuando se cierra la ventana modal
	=============================================*/

	$("#myModule").on('hidden.bs.modal', function (){

		$("#type_module").val("breadcrumbs");
		$("#metricsBlock").hide();
		$("#graphicsBlock").hide();
	})

})

/*=============================================
Eliminar un módulo
=============================================*/

$(document).on("click",".deleteModule",function(){

	var idModule = $(this).attr("idModule");
	
	if(atob(idModule) == 1 || atob(idModule) == 2){

		fncToastr("error", "Este módulo no se puede borrar");
		return;
	}

	fncSweetAlert("confirm", "¿Está seguro de borrar este módulo?", "").then(resp=>{

		if(resp){
			
			var data = new FormData();
			data.append("idModuleDelete",idModule);
			data.append("token", localStorage.getItem("tokenAdmin"));

			$.ajax({

				url: CMS_AJAX_PATH + "/modules.ajax.php",
				method:"POST",
				data:data,
				contentType:false,
				cache:false,
				processData:false,
				success: function(response){
					
					if(response == 200){

						fncSweetAlert("success","El módulo ha sido eliminado con éxito",setTimeout(()=>location.reload(),1250));
					
					}else{

						fncToastr("error", "ERROR: El módulo tiene columnas vinculadas");
					}
				}

			})

		}
	})

})

/*=============================================
Cambio en datos de métricas
=============================================*/

$(document).on("change",".changeMetric",function(e){

	// Get the current element that triggered the change
	var $currentElement = $(this);
	var isIconField = $currentElement.attr("id") == "metricIcon";
	
	// Update icon preview when it changes
	if(isIconField){
		// Get value from the custom event if available, otherwise from the element
		var iconValue = (e.originalEvent && e.originalEvent.iconValue) || $currentElement.val() || "bi-gear";
		
		// Clean the value if it has quotes
		if (iconValue && iconValue.indexOf('"') !== -1) {
			var parts = iconValue.split('"');
			iconValue = parts.length > 1 ? parts[1] : parts[0];
		}
		
		// Ensure the value is set correctly
		if (iconValue && iconValue !== $currentElement.val()) {
			$currentElement.val(iconValue);
		}
		
		// Update preview
		$("#metricIconPreview").attr("class", "bi " + iconValue);
	}

	// Get icon value - use the element that triggered if it's the icon field, otherwise get from DOM
	var iconValue;
	if(isIconField){
		// Use the value from the element that triggered the event, or from custom event
		iconValue = (e.originalEvent && e.originalEvent.iconValue) || $currentElement.val() || "bi-gear";
	} else {
		// Get from DOM
		iconValue = $("#metricIcon").val() || "bi-gear";
	}
	
	// Clean the value if it has quotes
	if (iconValue && iconValue.indexOf('"') !== -1) {
		var parts = iconValue.split('"');
		iconValue = parts.length > 1 ? parts[1] : parts[0];
		$("#metricIcon").val(iconValue);
	}
	
	// Double-check the value from the DOM element directly
	var domValue = document.getElementById("metricIcon") ? document.getElementById("metricIcon").value : iconValue;
	if (domValue && domValue !== iconValue && isIconField) {
		iconValue = domValue;
	}
	
	// Use JSON.stringify to properly escape and format the JSON
	var metricData = {
		type: $("#metricType").val() || "total",
		table: $("#metricTable").val() || "",
		column: $("#metricColumn").val() || "",
		config: $("#metricConfig").val() || "unit",
		icon: iconValue,
		color: $("#metricColor").val() || "108, 95, 252"
	};

	var contentModuleValue = JSON.stringify(metricData);
	$("#content_module").val(contentModuleValue);

})

/*=============================================
Cambio en datos de gráficos
=============================================*/

$(document).on("change",".changeGraphic",function(){

	// Use JSON.stringify to properly escape and format the JSON
	var graphicData = {
		type: $("#graphicType").val() || "line",
		table: $("#graphicTable").val() || "",
		xAxis: $("#graphicX").val() || "",
		yAxis: $("#graphicY").val() || "",
		color: $("#graphicColor").val() || "108, 95, 252"
	};

	$("#content_module").val(JSON.stringify(graphicData));	           

})

/*=============================================
Agregar columnas
=============================================*/

$(document).on("click",".addColumn",function(){

	var indexRandom = Math.ceil(Math.random() * 10000);

	$(".listColumns").append(`

		<div class="col-12 mb-3">
			<div class="card border">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h6 class="card-title mb-0 text-muted">
							<i class="bi bi-columns"></i> Columna ${$(".listColumns .col-12").length + 1}
						</h6>
						<button type="button" class="btn btn-sm btn-outline-danger deleteColumn" index="${indexRandom}" idItem="0" title="Eliminar columna">
							<i class="bi bi-trash"></i>
						</button>
					</div>

					<input type="hidden" name="id_column_${indexRandom}" value="0">

					<div class="row g-3">
						<div class="col-md-4">
							<label for="title_column_${indexRandom}" class="form-label small fw-semibold">Título<sup>*</sup></label>
							<input
								type="text"
								class="form-control form-control-sm rounded"
								id="title_column_${indexRandom}"
								name="title_column_${indexRandom}"
								placeholder="Nombre de la columna"
								required>
							<div class="valid-feedback">Válido</div>
							<div class="invalid-feedback">Campo Inválido</div>
						</div>

						<div class="col-md-3">
							<label for="alias_column_${indexRandom}" class="form-label small fw-semibold">Alias<sup>*</sup></label>
							<input
								type="text"
								class="form-control form-control-sm rounded"
								id="alias_column_${indexRandom}"
								name="alias_column_${indexRandom}"
								placeholder="Alias de la columna"
								required>
							<div class="valid-feedback">Válido</div>
							<div class="invalid-feedback">Campo Inválido</div>
						</div>

						<div class="col-md-3">
							<label for="type_column_${indexRandom}" class="form-label small fw-semibold">Tipo<sup>*</sup></label>
							<select 
								class="form-select form-select-sm rounded" 
								id="type_column_${indexRandom}"
								name="type_column_${indexRandom}"
								required>
								<option value="text">Texto</option>
								<option value="textarea">Área Texto</option>
								<option value="int">Número Entero</option>
								<option value="double">Número Decimal</option>
								<option value="image">Imagen</option>
								<option value="video">Video</option>
								<option value="file">Archivo</option>
								<option value="boolean">Boleano</option>
								<option value="select">Selección</option>
								<option value="array">Arreglo</option>
								<option value="object">Objeto</option>
								<option value="json">JSON</option>
								<option value="date">Fecha</option>
								<option value="time">Hora</option>
								<option value="datetime">Fecha y Hora</option>
								<option value="timestamp">Fecha Automática</option>
								<option value="code">Código</option>
								<option value="link">Enlace</option>
								<option value="color">Color</option>
								<option value="money">Dinero</option>
								<option value="password">Contraseña</option>
								<option value="email">Email</option>
								<option value="relations">Relaciones</option>
								<option value="order">Ordenar</option>
								<option value="chatgpt">ChatGPT</option>
							</select>
							<div class="valid-feedback">Válido</div>
							<div class="invalid-feedback">Campo Inválido</div>
						</div>

						<div class="col-md-2">
							<label for="visible_column_${indexRandom}" class="form-label small fw-semibold">Visibilidad</label>
							<select 
								class="form-select form-select-sm rounded" 
								name="visible_column_${indexRandom}" 
								id="visible_column_${indexRandom}" 
								required>
								<option value="1">ON</option>
								<option value="0">OFF</option>							
							</select>
							<div class="valid-feedback">Válido</div>
							<div class="invalid-feedback">Campo Inválido</div>
						</div>
					</div>
				</div>
			</div>
		</div>

	 `)

	var indexColumns = JSON.parse($("#indexColumns").val());

	indexColumns.push(indexRandom);

	$("#indexColumns").val(JSON.stringify(indexColumns));

})

/*=============================================
Eliminar columnas
=============================================*/

$(document).on("click",".deleteColumn",function(){

	var elem = $(this);

	fncSweetAlert("confirm","¿Está seguro de borrar esta columna?","").then(resp=>{

		if(resp){

			$(elem).parent().parent().remove();

			/*=============================================
			 ID de columnas a borrar
			=============================================*/
			
			if($(elem).attr("idItem") > 0){

				var deleteColumns = JSON.parse($("#deleteColumns").val());

				deleteColumns.push(Number($(elem).attr("idItem")));

				$("#deleteColumns").val(JSON.stringify(deleteColumns));
			}

			/*=============================================
			Actualizar el Índice de columnas
			=============================================*/

			var indexColumns = JSON.parse($("#indexColumns").val());

			indexColumns = indexColumns.filter(e => e != $(elem).attr("index"));

			$("#indexColumns").val(JSON.stringify(indexColumns));
			
		}

	})

	// Functions loadTablesForEdit and loadTableColumnsForEdit are now defined globally at the top of this file

})

/*=============================================
Load tables for graphics module (available globally)
=============================================*/

function loadTablesForGraphic() {
	$.ajax({
		url: CMS_AJAX_PATH + "/modules.ajax.php?action=getTables",
		method: 'GET',
		dataType: 'json',
		success: function(response) {
			if (response.status === 200 && response.results.length > 0) {
				var options = '<option value="">Seleccionar tabla...</option>';
				response.results.forEach(function(table) {
					options += '<option value="' + table + '">' + table + '</option>';
				});
				$("#graphicTable").html(options);
			} else {
				$("#graphicTable").html('<option value="">No hay tablas disponibles</option>');
			}
		},
			error: function(xhr, status, error) {
				$("#graphicTable").html('<option value="">Error al cargar tablas</option>');
			}
	});
}

/*=============================================
Load tables for graphics module when editing (available globally)
=============================================*/

function loadTablesForEditGraphic(savedTable, savedXAxis, savedYAxis) {
	$.ajax({
		url: CMS_AJAX_PATH + "/modules.ajax.php?action=getTables",
		method: 'GET',
		dataType: 'json',
		success: function(response) {
			if (response.status === 200 && response.results.length > 0) {
				var options = '<option value="">Seleccionar tabla...</option>';
				response.results.forEach(function(table) {
					// Use strict comparison and trim to ensure match
					var tableValue = String(table).trim();
					var savedTableValue = String(savedTable).trim();
					var selected = (tableValue === savedTableValue) ? 'selected' : '';
					options += '<option value="' + table + '" ' + selected + '>' + table + '</option>';
				});
				$("#graphicTable").html(options);
				
				// If a table was saved, load its columns
				if (savedTable && savedTable !== "") {
					// Set the table value explicitly
					$("#graphicTable").val(savedTable);
					// Load columns directly
					loadTableColumnsForEditGraphic(savedTable, savedXAxis, savedYAxis);
				} else {
					$("#graphicX").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
					$("#graphicY").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
				}
			} else {
				$("#graphicTable").html('<option value="">No hay tablas disponibles</option>');
			}
		},
			error: function(xhr, status, error) {
				$("#graphicTable").html('<option value="">Error al cargar tablas</option>');
			}
	});
}

/*=============================================
Load columns from a table for graphics module (available globally)
=============================================*/

function loadTableColumnsForEditGraphic(tableName, savedXAxis, savedYAxis) {
	if (!tableName || tableName === "") {
		$("#graphicX").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
		$("#graphicY").html('<option value="">Seleccione primero una tabla</option>').prop("disabled", true);
		return;
	}
	
	$("#graphicX").prop("disabled", true).html('<option value="">Cargando columnas...</option>');
	$("#graphicY").prop("disabled", true).html('<option value="">Cargando columnas...</option>');
	
	$.ajax({
		url: CMS_AJAX_PATH + "/modules.ajax.php",
		method: 'POST',
		data: {
			action: 'getTableColumns',
			tableName: tableName
		},
		dataType: 'json',
		success: function(response) {
			if (response.status === 200 && response.results.length > 0) {
				// Build options for X axis
				var optionsX = '<option value="">Seleccionar columna...</option>';
				response.results.forEach(function(column) {
					var columnValue = String(column).trim();
					var savedXValue = String(savedXAxis).trim();
					var selected = (columnValue === savedXValue) ? 'selected' : '';
					optionsX += '<option value="' + column + '" ' + selected + '>' + column + '</option>';
				});
				$("#graphicX").html(optionsX).prop("disabled", false);
				
				// Build options for Y axis
				var optionsY = '<option value="">Seleccionar columna...</option>';
				response.results.forEach(function(column) {
					var columnValue = String(column).trim();
					var savedYValue = String(savedYAxis).trim();
					var selected = (columnValue === savedYValue) ? 'selected' : '';
					optionsY += '<option value="' + column + '" ' + selected + '>' + column + '</option>';
				});
				$("#graphicY").html(optionsY).prop("disabled", false);
				
				// Set the values explicitly if they were saved
				if (savedXAxis && savedXAxis !== "") {
					$("#graphicX").val(savedXAxis);
				}
				if (savedYAxis && savedYAxis !== "") {
					$("#graphicY").val(savedYAxis);
				}
				
				// Update content_module after columns are loaded using JSON.stringify
				var graphicData = {
					type: $("#graphicType").val() || "line",
					table: tableName,
					xAxis: $("#graphicX").val() || savedXAxis || "",
					yAxis: $("#graphicY").val() || savedYAxis || "",
					color: $("#graphicColor").val() || "108, 95, 252"
				};
				$("#content_module").val(JSON.stringify(graphicData));
				
				// Trigger change events to ensure all handlers are called
				$("#graphicX").trigger('change');
				$("#graphicY").trigger('change');
			} else {
				$("#graphicX").html('<option value="">No hay columnas disponibles</option>').prop("disabled", true);
				$("#graphicY").html('<option value="">No hay columnas disponibles</option>').prop("disabled", true);
			}
		},
			error: function(xhr, status, error) {
				$("#graphicX").html('<option value="">Error al cargar columnas</option>').prop("disabled", true);
				$("#graphicY").html('<option value="">Error al cargar columnas</option>').prop("disabled", true);
			}
	});
}