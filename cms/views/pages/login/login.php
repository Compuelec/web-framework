<?php 

$securityCode = null;

if(isset($_GET["scode"])){

	$url = "admins?linkTo=email_admin&equalTo=".base64_decode($_GET["scode"]);
  $method = "GET";
  $fields = array();

  $scode = CurlController::request($url, $method, $fields);

  if($scode->status == 200){

    $securityCode = $scode->results[0];
    
  }

}

?>


<div class="container-fluid backgroundImage" <?php if (!empty($admin->back_admin)): ?>
	style="background-image: url(<?php echo $admin->back_admin ?>)"
<?php else: ?>
	style="background-color:  #f0f0f5 !important"
<?php endif ?>>
	
	<div class="d-flex flex-wrap justify-content-center align-content-center vh-100">
		
		<div class="card border-0 rounded shadow p-4 w-25" style="min-width:320px !important">
			
			<form method="POST" class="needs-validation" novalidate>
				
				<?php
				// Use the same brand (logo / symbol / name) as the sidebar — from
				// Apariencia (cms_settings), falling back to the admin's values.
				$loginLogo   = $_SESSION['cms_theme']['theme_brand_logo']   ?? '';
				$loginTitle  = $_SESSION['cms_theme']['theme_brand_title']  ?? '';
				$loginSymbol = $_SESSION['cms_theme']['theme_brand_symbol'] ?? '';
				if ($loginTitle === '')  { $loginTitle  = $admin->title_admin ?? ''; }
				if ($loginSymbol === '') { $loginSymbol = $admin->symbol_admin ?? 'bi-grid'; }
				?>
				<?php if ($loginLogo): ?>
					<div class="text-center pt-3">
						<img src="<?php echo htmlspecialchars($loginLogo, ENT_QUOTES) ?>" alt="<?php echo htmlspecialchars($loginTitle, ENT_QUOTES) ?>" style="max-height:64px; max-width:220px; object-fit:contain;">
					</div>
				<?php endif ?>

				<h3 class="<?php echo $loginLogo ? 'pt-2' : 'pt-3' ?> text-center">
					<i class="<?php echo htmlspecialchars($loginSymbol, ENT_QUOTES) ?> textColor"></i> <?php echo htmlspecialchars($loginTitle, ENT_QUOTES) ?>
				</h3>

				<hr>

				<?php if (empty($securityCode)): ?>
					
				<div class="form-group mb-3">
					
					<label for="email_admin_login">Correo</label>

					<input 
					type="email"
					class="form-control rounded"
					id="email_admin_login"
					name="email_admin"
					placeholder="Escribe el correo"
					required
					>

					<div class="valid-feedback">Válido.</div>
    				<div class="invalid-feedback">Campo inválido.</div>

				</div>

				<div class="form-group mb-3">

					<div class="row mb-1">
						<div class="col-5">
							<label for="password_admin">Contraseña</label>
						</div>
						<div class="col-7 text-end">
							<a href="#resetPassword" class="textColor" data-bs-toggle="modal" style="font-size:12px">¿Olvidaste la contraseña?</a>
						</div>
					</div>

					<div class="input-group">
	
						<input 
						type="password"
						class="form-control rounded-start"
						id="password_admin"
						name="password_admin"
						placeholder="Escribe la contraseña"
						required
						>

						<span class="input-group-text rounded-end">
							<i class="viewPass bi bi-eye-slash" state="locked" style="cursor:pointer"></i>
						</span>

					</div>

					<div class="valid-feedback">Válido.</div>
    				<div class="invalid-feedback">Campo inválido.</div>

				</div>


				<div class="form-check">
					<input type="checkbox" class="form-check-input" id="remember" onchange="rememberEmail(event)">
					<label class="form-check-label ms-2" for="remember">Recordar Ingreso</label>
				</div>

				<?php 
				
					require_once "controllers/admins.controller.php";
					$login = new AdminsController();
					$login -> login();

				?>

				<?php else: ?>

					<div class="form-group mb-3">
						
						<label for="scode_admin">Código de Seguridad</label>

						<input 
						type="text"
						class="form-control rounded mt-2"
						id="scode_admin"
						name="scode_admin"
						placeholder="Escribe el código de seguridad"
						required
						>

						<div class="valid-feedback">Válido.</div>
	    			<div class="invalid-feedback">Campo inválido.</div>

					</div>

				<?php 
				
					require_once "controllers/admins.controller.php";
					$login = new AdminsController();
					$login -> securityCode();

				?>

				<?php endif ?>

				<button type="submit" class="btn btn-dark btn-block w-100 rounded mt-3 backColor">Enviar</button>


				

			</form>

		</div>

	</div>


</div>

<!--====================================
Modal to recover password
====================================-->

<!-- The Modal -->
<div class="modal" id="resetPassword">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded">

    <form method="post" class="needs-validation" novalidate>

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Recuperar la contraseña</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal body -->
      <div class="modal-body p-4">

      	 <div class="form-group mb-3">

            <label for="title_page pb-1">Ingresa tu correo electrónico para recibir una nueva</label>

            <input 
            type="email"
            class="form-control rounded mt-1"
            name="resetPassword"
            placeholder="Email"
            onchange="validateJS(event,'email')"
            required
            >

            <div class="valid-feedback">Válido.</div>
            <div class="invalid-feedback">Campo inválido.</div>

          </div>



      </div>

      <!-- Modal footer -->
        <div class="modal-footer d-flex justify-content-between">
          
          <div><button type="button" class="btn btn-dark rounded" data-bs-dismiss="modal">Cerrar</button></div>
          <div><button type="submit" class="btn btn-default backColor rounded">Enviar</button></div>
          
        </div>

        <?php 

        require_once "controllers/admins.controller.php";
        $reset = new AdminsController();
        $reset -> resetPassword();

        ?>

    </form>

    </div>
  </div>
</div><!-- The Modal -->
