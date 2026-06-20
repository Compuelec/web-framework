<?php if ($module->columns[$i]->type_column == "measure"): ?>

	<?php
		// matrix_column is the unit: a literal ("kg") or a sibling column name
		// that holds the per-row unit. When it references a sibling column the unit
		// is edited through that column's own field, so no fixed addon is shown.
		$measureUnit = $module->columns[$i]->matrix_column;
		$siblingUnit = false;
		if (!empty($measureUnit)) {
			foreach ($module->columns as $mc) {
				if ($mc->title_column === $measureUnit) { $siblingUnit = true; break; }
			}
		}
	?>

	<div class="input-group">

		<input
		type="number"
		step="any"
		class="form-control rounded"
		id="<?php echo $module->columns[$i]->title_column ?>"
		name="<?php echo $module->columns[$i]->title_column ?>"
		value="<?php echo htmlspecialchars(urldecode((string)($data[$module->columns[$i]->title_column] ?? '')), ENT_QUOTES) ?>">

		<?php if (!empty($measureUnit) && !$siblingUnit): ?>
			<span class="input-group-text"><?php echo htmlspecialchars($measureUnit) ?></span>
		<?php endif ?>

	</div>

<?php endif ?>
