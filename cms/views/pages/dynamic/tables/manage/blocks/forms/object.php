<?php if ($module->columns[$i]->type_column == "object"): ?>


	<div class="itemsObject">

		<?php if (!empty($data) && $data[$module->columns[$i]->title_column] != null): $arrayObj =  new ArrayObject(json_decode(urldecode($data[$module->columns[$i]->title_column])));?>

			<?php if (!empty($arrayObj) && $arrayObj->count() > 0): ?>

				<?php foreach ($arrayObj as $key => $value): ?>

					<div class="row row-cols-1 row-cols-sm-2 itemObject">
						
						<div class="col">
							
							<div class="form-floating mb-3">
								<input 
								type="text"
								class="form-control rounded propertyObject <?php echo $module->columns[$i]->title_column ?>"
								onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
								value="<?php echo $key ?>"
								>

								<label>Propiedad</label>

							</div>

						</div>

						<div class="col">
							
							<div class="form-floating mb-3">
								
								<input 
								type="text"
								class="form-control rounded position-relative valueObject <?php echo $module->columns[$i]->title_column ?>"
								onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
								value="<?php echo htmlspecialchars($value) ?>"
								>

								<label>Valor</label>

								<button type="button" class="btn btn-sm position-absolute" style="top:0; right:0;" onclick="removeObject('<?php echo $module->columns[$i]->title_column ?>','_<?php echo $key ?>',event)">
									<i class="bi bi-x"></i>
								</button>

							</div>
							
						</div>

					</div>	
					
				<?php endforeach ?>

			<?php else: ?>

				<div class="row row-cols-1 row-cols-sm-2 itemObject">
				
					<div class="col">
						
						<div class="form-floating mb-3">
							<input 
							type="text"
							class="form-control rounded propertyObject <?php echo $module->columns[$i]->title_column ?>"
							onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
							>

							<label>Propiedad</label>

						</div>

					</div>

					<div class="col">
						
						<div class="form-floating mb-3">
							
							<input 
							type="text"
							class="form-control rounded position-relative valueObject <?php echo $module->columns[$i]->title_column ?>"
							onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
							>

							<label>Valor</label>

							<button type="button" class="btn btn-sm position-absolute" style="top:0; right:0;" onclick="removeObject('<?php echo $module->columns[$i]->title_column ?>','_0',event)">
								<i class="bi bi-x"></i>
							</button>

						</div>
						
					</div>

				</div>	

			<?php endif ?>


		<?php else: ?>
		
			<div class="row row-cols-1 row-cols-sm-2 itemObject">
				
				<div class="col">
					
					<div class="form-floating mb-3">
						<input 
						type="text"
						class="form-control rounded propertyObject <?php echo $module->columns[$i]->title_column ?>"
						onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
						>

						<label>Propiedad</label>

					</div>

				</div>

				<div class="col">
					
					<div class="form-floating mb-3">
						
						<input 
						type="text"
						class="form-control rounded position-relative valueObject <?php echo $module->columns[$i]->title_column ?>"
						onchange="changeItemObject('<?php echo $module->columns[$i]->title_column ?>')"
						>

						<label>Valor</label>

						<button type="button" class="btn btn-sm position-absolute" style="top:0; right:0;" onclick="removeObject('<?php echo $module->columns[$i]->title_column ?>','_0',event)">
							<i class="bi bi-x"></i>
						</button>

					</div>
					
				</div>

			</div>	

		<?php endif ?>

	</div>

	<?php if ($module->columns[$i]->title_column == "permissions_admin"): ?>
		<!-- Help text for permissions field -->
		<div class="alert alert-info border-0 bg-light mb-3 mt-3" role="alert">
			<div class="d-flex align-items-start">
				<i class="bi bi-info-circle me-2 mt-1"></i>
				<div class="small">
					<strong class="d-block mb-2">Ejemplos de uso:</strong>
					<ul class="mb-0 ps-3">
						<li class="mb-1">
							<strong>Acceso a página específica:</strong><br>
							Propiedad: <code>archivos</code> | Valor: <code>ON</code><br>
							<small class="text-muted">El usuario solo verá la página "archivos" (el valor viene de la URL de la página al crearla).</small>
						</li>
						<li class="mb-0">
							<strong>Acceso completo:</strong><br>
							Propiedad: <code>TODO</code> | Valor: <code>ON</code><br>
							<small class="text-muted">El usuario tendrá acceso a todas las páginas del sistema.</small>
						</li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif ?>

	<button type="button" class="btn btn-sm btn-default backColor rounded addObject"><small>Add Item</small></button>

	<?php if (!empty($data)): ?>

		<input type="hidden" name="<?php echo $module->columns[$i]->title_column ?>" id="<?php echo $module->columns[$i]->title_column ?>" value='<?php echo urldecode($data[$module->columns[$i]->title_column]) ?>'>

	<?php else: ?>

		<input type="hidden" name="<?php echo $module->columns[$i]->title_column ?>" id="<?php echo $module->columns[$i]->title_column ?>" value='{}'>

	<?php endif ?>	
<?php endif ?>