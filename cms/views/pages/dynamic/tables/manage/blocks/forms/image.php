<?php if ($module->columns[$i]->type_column == "image"):

	// Stored value is a single image URL (URL-encoded), same as before.
	$imageValue = (!empty($data) && isset($data[$module->columns[$i]->title_column]))
		? urldecode($data[$module->columns[$i]->title_column])
		: '';
?>
<div class="singleImageField">

	<input type="hidden"
		name="<?php echo $module->columns[$i]->title_column ?>"
		class="singleImageValue"
		value="<?php echo htmlspecialchars($imageValue, ENT_QUOTES) ?>">

	<div class="singleImageThumb mb-2" style="<?php echo $imageValue ? '' : 'display:none;' ?>">
		<div class="position-relative" style="width:90px;">
			<img src="<?php echo htmlspecialchars($imageValue, ENT_QUOTES) ?>" class="singleImagePreview rounded border w-100" style="height:90px; object-fit:cover;">
			<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1 singleImageRemove" style="line-height:1;" title="Quitar">&times;</button>
		</div>
	</div>

	<label class="btn btn-sm btn-outline-primary mb-0">
		<i class="bi bi-image me-1"></i>Agregar imagen
		<input type="file" accept="image/*" class="singleImageInput d-none">
	</label>
	<span class="spinner-border spinner-border-sm text-primary ms-2 singleImageSpinner" style="display:none;"></span>
</div>

<?php endif ?>
