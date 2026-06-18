<?php
// Prepare condition attributes for conditional fields
$conditionsAttr = '';
$conditionsData = '';
if (!empty($module->columns[$i]->conditions_column)) {
	$conditionsData = htmlspecialchars($module->columns[$i]->conditions_column, ENT_QUOTES);
	$conditionsAttr = 'data-conditions="' . $conditionsData . '"';
}
?>
<div class="card rounded border-0 shadow mb-3 pb-3 conditional-field-container"
	 data-field="<?php echo $module->columns[$i]->title_column ?>"
	 <?php echo $conditionsAttr ?>>

	<div class="card-body">

		<label for="<?php echo $module->columns[$i]->title_column ?>" class="form-label float-start text-capitalize">
			<?php echo $module->columns[$i]->alias_column ?>:
		</label>
		<span class="float-end badge badge-default border small rounded text-muted">
			<?php echo $module->columns[$i]->type_column ?>
		</span>
		<div class="clearfix"></div>

		<?php 
		
		/*=============================================
		Text type form
		=============================================*/
		
		include "forms/text.php"; 

		/*=============================================
		TextArea type form
		=============================================*/
		
		include "forms/textarea.php"; 

		/*=============================================
		Integer number type form
		=============================================*/
		
		include "forms/int.php"; 

		/*=============================================
		Decimal number type form
		=============================================*/
		
		include "forms/double.php"; 

		/*=============================================
		Select type form
		=============================================*/
		
		include "forms/select.php"; 

		/*=============================================
		Boolean type form
		=============================================*/
		
		include "forms/boolean.php"; 

		/*=============================================
		Array type form
		=============================================*/
		
		include "forms/array.php"; 

		/*=============================================
		Object type form
		=============================================*/
		
		include "forms/object.php"; 

		/*=============================================
		JSON type form
		=============================================*/
		
		include "forms/_json.php"; 

		/*=============================================
		File, Image, Video type form
		=============================================*/
		
		include "forms/file.php";

			/*=============================================
			Multi-image type form
			=============================================*/

			include "forms/multiimage.php"; 

		/*=============================================
		Date type form
		=============================================*/
		
		include "forms/date.php"; 

		/*=============================================
		Time type form
		=============================================*/
		
		include "forms/time.php"; 

		/*=============================================
		Date and Time type form
		=============================================*/
		
		include "forms/datetime.php"; 

		/*=============================================
		Automatic Date and Time type form
		=============================================*/

		include "forms/timestamp.php"; 

		/*=============================================
		Code type form
		=============================================*/

		include "forms/code.php"; 

		/*=============================================
		Color type form
		=============================================*/

		include "forms/color.php"; 

		/*=============================================
		Password type form
		=============================================*/

		include "forms/password.php"; 

		/*=============================================
		Email type form
		=============================================*/

		include "forms/email.php"; 

		/*=============================================
		Relations type form
		=============================================*/

		include "forms/relations.php";

		/*=============================================
		ChatGPT type form
		=============================================*/

		include "forms/chatgpt.php";

		/*=============================================
		Workflow type form
		=============================================*/

		include "forms/workflow.php";

		?>

		<div class="valid-feedback">Válido.</div>
		<div class="invalid-feedback">Campo inválido.</div>
	
	</div>

</div>