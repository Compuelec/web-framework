<?php if ($module->columns[$i]->type_column == "file" ||
$module->columns[$i]->type_column == "video"): ?>
<div class="input-group">

 	<input
	type="text"
	class="form-control rounded-start"
	id="<?php echo $module->columns[$i]->title_column ?>"
	name="<?php echo $module->columns[$i]->title_column ?>"
	value="<?php if (!empty($data)): ?><?php echo htmlspecialchars(urldecode($data[$module->columns[$i]->title_column] ?? ''), ENT_QUOTES, 'UTF-8') ?><?php endif ?>">

	<span class="input-group-text rounded-end myFiles" style="cursor:pointer"><i class="bi bi-cloud-arrow-up-fill"></i></span>

</div>

<?php endif ?>
