<?php if ($module->columns[$i]->type_column == "select"): ?>

	<div class="input-group mb-3">
		
		<input 
		type="text"
		class="form-control rounded changeSelectType tags-input"
		idColumn="<?php echo $module->columns[$i]->id_column ?>"
		titleColumn="<?php echo $module->columns[$i]->title_column ?>"
		value="<?php echo $module->columns[$i]->matrix_column ?>"
		preValue="<?php if (!empty($data)): ?><?php echo urldecode($data[$module->columns[$i]->title_column])?><?php endif ?>"
		>
	</div>

	<select 
	class="form-select rounded select2"
	name="<?php echo $module->columns[$i]->title_column ?>" 
	id="<?php echo $module->columns[$i]->title_column ?>">

	<?php 
		// Decode matrix_column and check if it's not empty
		$matrixValue = !empty($module->columns[$i]->matrix_column) ? urldecode($module->columns[$i]->matrix_column) : '';
		$matrixValue = trim($matrixValue);
	?>
	
	<?php if (!empty($matrixValue)): ?>

		<?php foreach (explode(",", $matrixValue) as $index => $item):?>
			<?php 
				$item = trim($item);
				if (empty($item)) continue;
			?>
			<option value="<?php echo htmlspecialchars($item) ?>" <?php if (!empty($data) && isset($data[$module->columns[$i]->title_column]) && urldecode($data[$module->columns[$i]->title_column]) == $item): ?> selected <?php endif ?>><?php echo htmlspecialchars($item) ?></option>
			
		<?php endforeach ?>
		
	<?php endif ?>

	</select>

<?php endif ?>