<?php if ($module->columns[$i]->type_column == "multiimage"):

	// Existing value is a URL-encoded JSON array of image URLs.
	$multiImages = [];
	if (!empty($data) && isset($data[$module->columns[$i]->title_column])) {
		$decodedImages = json_decode(urldecode($data[$module->columns[$i]->title_column]), true);
		if (is_array($decodedImages)) {
			$multiImages = $decodedImages;
		}
	}

	// Optional per-column limit stored in matrix_column (0 / empty = no limit).
	$multiMax = isset($module->columns[$i]->matrix_column) ? (int) $module->columns[$i]->matrix_column : 0;
?>
<div class="multiImageField" data-max="<?php echo $multiMax ?>">

	<input type="hidden"
		name="<?php echo $module->columns[$i]->title_column ?>"
		class="multiImageValue"
		value="<?php echo htmlspecialchars(json_encode(array_values($multiImages)), ENT_QUOTES) ?>">

	<div class="multiImageThumbs d-flex flex-wrap gap-2 mb-2">
		<?php foreach ($multiImages as $imgUrl): ?>
			<div class="multiImageThumb position-relative" data-url="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES) ?>" style="width:90px;">
				<img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES) ?>" class="rounded border w-100" style="height:90px; object-fit:cover;">
				<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1 multiImageRemove" style="line-height:1;" title="Quitar">&times;</button>
			</div>
		<?php endforeach ?>
	</div>

	<label class="btn btn-sm btn-outline-primary mb-0">
		<i class="bi bi-images me-1"></i>Agregar imágenes
		<input type="file" accept="image/*" multiple class="multiImageInput d-none">
	</label>
	<span class="spinner-border spinner-border-sm text-primary ms-2 multiImageSpinner" style="display:none;"></span>
	<small class="text-muted ms-2 multiImageCounter"></small>
</div>

<?php endif ?>
