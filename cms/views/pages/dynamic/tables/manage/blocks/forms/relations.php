<?php if ($module->columns[$i]->type_column == "relations"): ?>

	<?php 

	/*=============================================
	Fetch all the tables
	=============================================*/

	require_once "controllers/install.controller.php";
	$tables = InstallController::getTables();

	?>

	<select 
	class="form-select rounded mb-3 select2 changeRelations"
	idColumn="<?php echo $module->columns[$i]->id_column ?>">

		<?php if ($module->columns[$i]->matrix_column != null): ?>

			<option value="<?php echo $module->columns[$i]->matrix_column ?>"><?php echo $module->columns[$i]->matrix_column ?></option>

		<?php else: ?>

			<option value="">Seleccione Tabla</option>


		<?php endif	?>

			<?php foreach ($tables as $index => $item): ?>

				<option value="<?php echo $item ?>" <?php if (!empty($data) && $module->columns[$i]->matrix_column == $item): ?> selected <?php endif ?> ><?php echo $item ?></option>

			<?php endforeach ?>

			<!-- Core "admins" table (cashiers / users): selectable as a relation
			     target even though it is not a generated "tables" module. -->
			<option value="admins" <?php if (!empty($data) && $module->columns[$i]->matrix_column == "admins"): ?> selected <?php endif ?>>admins (cajeros / usuarios)</option>


	</select>

	<div class="mb-3"></div>

	<select 
	class="form-select rounded select2 selectRelations"
	name="<?php echo $module->columns[$i]->title_column ?>" 
	id="<?php echo $module->columns[$i]->title_column ?>">

	<?php if ($module->columns[$i]->matrix_column != null): ?>

		<?php 

			$url = $module->columns[$i]->matrix_column;
			$method = "GET";
			$fields = array();

			// Bounded select for the core admins table so the option list never
			// carries the password hash; other tables keep the default fetch.
			$relUrl = ($url == "admins") ? "admins?select=id_admin,title_admin,email_admin" : $url;

			$columnsTable = CurlController::request($relUrl,$method,$fields);

			if($columnsTable->status == 200){

				$columnsTable = $columnsTable->results;

			}else{

				$columnsTable = array();
			}

		?>

		<?php if (!empty($columnsTable)): ?>

			<option value="0">0</option>

			<?php foreach ($columnsTable as $index => $item): ?>

				<option value="<?php echo json_decode(json_encode($item),true)[array_keys((array)$item)[0]] ?>" <?php if (!empty($data) && json_decode(json_encode($item),true)[array_keys((array)$item)[0]] == $data[$module->columns[$i]->title_column]): ?> selected <?php endif ?>><?php echo json_decode(json_encode($item),true)[array_keys((array)$item)[0]] ?> - <?php echo urldecode(json_decode(json_encode($item),true)[array_keys((array)$item)[1]]) ?></option>

			<?php endforeach ?>

		<?php endif ?>

	<?php endif ?>
		

	</select>

<?php endif ?>