<?php
/*==============================
Free HTML / CSS / JS content module
================================*/

// Decode the stored content (JSON: { html, css, js })
$htmlContent = json_decode($module->content_module);

$freeHtml = isset($htmlContent->html) ? $htmlContent->html : '';
$freeCss  = isset($htmlContent->css)  ? $htmlContent->css  : '';
$freeJs   = isset($htmlContent->js)   ? $htmlContent->js   : '';

// Unique wrapper id so authors can scope their own CSS/JS if they wish
$wrapperId = "cms-html-module-".$module->id_module;
?>

<div class="<?php if ($module->width_module == "100"): ?> col-lg-12 <?php endif ?><?php if ($module->width_module == "75"): ?> col-lg-9 <?php endif ?><?php if ($module->width_module == "50"): ?> col-lg-6 <?php endif ?><?php if ($module->width_module == "33"): ?> col-lg-4 <?php endif ?><?php if ($module->width_module == "25"): ?> col-lg-3 <?php endif ?> col-12 mb-3 position-relative">

	<?php if ($_SESSION["admin"]->rol_admin == "superadmin"): ?>

		<div class="position-absolute border rounded" style="top:0px; right:12px; z-index:100">

			<button type="button" class="btn btn-sm text-muted rounded m-0 px-1 py-0 border-0 myModule" item="<?php echo htmlspecialchars(json_encode($module), ENT_QUOTES) ?>" idPage="<?php echo $page->results[0]->id_page ?>">
				<i class="bi bi-pencil-square"></i>
			</button>

			<button type="button" class="btn btn-sm text-muted rounded m-0 px-1 py-0 border-0 deleteModule" idModule=<?php echo base64_encode($module->id_module) ?> >
				<i class="bi bi-trash"></i>
			</button>

		</div>

	<?php endif ?>

	<!--==============================
	Start free content
	================================-->

	<?php if (!empty($freeCss)): ?>
		<style><?php echo $freeCss ?></style>
	<?php endif ?>

	<div id="<?php echo $wrapperId ?>">
		<?php echo $freeHtml ?>
	</div>

	<?php if (!empty($freeJs)): ?>
		<script><?php echo $freeJs ?></script>
	<?php endif ?>

</div>
