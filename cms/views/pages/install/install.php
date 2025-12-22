<div class="container-fluid install-container">
	
	<div class="d-flex flex-wrap justify-content-center align-items-center min-vh-100 py-5">
		
		<div class="install-card">
			
			<form method="POST" class="needs-validation install-form" novalidate>
				
				<div class="text-center mb-4">
					<h4 class="install-title mb-1">Instalación del Dashboard</h4>
					<p class="text-muted small mb-0">Configura tu sistema en pocos pasos</p>
				</div>

				<hr class="install-divider">

				<!-- Section: Domain Detection -->
				<?php
				require_once __DIR__ . '/../../../controllers/path-updater.controller.php';
				$domainInfo = PathUpdaterController::detectDomain();
				?>
				<div class="mb-4">
					<h6 class="section-title mb-3">
						<i class="bi bi-globe me-2"></i>
						Configuración del Servidor
					</h6>
					<div class="alert alert-info">
						<small>
							<strong>Dominio detectado:</strong> <?php echo htmlspecialchars($domainInfo['host']); ?><br>
							<strong>URL Base:</strong> <?php echo htmlspecialchars($domainInfo['base_url']); ?><br>
							<strong>URL API:</strong> <?php echo htmlspecialchars($domainInfo['api_url']); ?>
						</small>
					</div>
				</div>

				<!-- Section: Database Configuration Info -->
				<?php
				// Check if this is a package installation
				require_once __DIR__ . '/../../../controllers/package-install.controller.php';
				require_once __DIR__ . '/../../../controllers/install.controller.php';
				$isPackageInstall = PackageInstallController::hasDatabaseFile();
				
				if ($isPackageInstall): 
					// Get database configuration from config.php (read-only)
					$config = InstallController::getConfig();
					$dbConfig = $config['database'] ?? [];
					$hasDbConfig = !empty($dbConfig['host']) && !empty($dbConfig['name']) && isset($dbConfig['user']) && isset($dbConfig['pass']);
					?>
					<!-- Package Installation: Show database config from config.php (read-only) -->
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-box-seam me-2"></i>
							Instalación desde Paquete
						</h6>
						<div class="alert alert-info">
							<small>
								<i class="bi bi-info-circle me-2"></i>
								<strong>Se detectó un archivo <code>database.sql</code> en la raíz.</strong><br>
								Este es un paquete empaquetado. La base de datos será restaurada automáticamente durante la instalación usando la configuración de <code>config.php</code>.
							</small>
						</div>
						<?php if ($hasDbConfig): ?>
							<div class="alert alert-success">
								<small>
									<i class="bi bi-check-circle me-2"></i>
									<strong>Configuración de base de datos (desde config.php):</strong><br>
									Servidor: <code><?php echo htmlspecialchars($dbConfig['host']); ?></code><br>
									Base de datos: <code><?php echo htmlspecialchars($dbConfig['name']); ?></code><br>
									Usuario: <code><?php echo htmlspecialchars($dbConfig['user']); ?></code><br>
									<em>La configuración se lee desde <code>config.php</code> y no puede ser modificada.</em>
								</small>
							</div>
							<!-- Hidden fields to pass config to backend -->
							<input type="hidden" name="db_host" value="<?php echo htmlspecialchars($dbConfig['host']); ?>">
							<input type="hidden" name="db_name" value="<?php echo htmlspecialchars($dbConfig['name']); ?>">
							<input type="hidden" name="db_user" value="<?php echo htmlspecialchars($dbConfig['user']); ?>">
							<input type="hidden" name="db_pass" value="<?php echo htmlspecialchars($dbConfig['pass'] ?? ''); ?>">
						<?php else: ?>
							<div class="alert alert-danger">
								<small>
									<i class="bi bi-exclamation-triangle me-2"></i>
									<strong>Error: Configuración de base de datos no encontrada.</strong><br>
									Por favor, configure la base de datos en <code>cms/config.php</code> o <code>cms/config.example.php</code> antes de continuar con la instalación desde paquete.
								</small>
							</div>
						<?php endif; ?>
					</div>
				<?php else: ?>
					<!-- Clean Installation: Show config info -->
					<?php
					require_once __DIR__ . '/../../../controllers/install.controller.php';
					$config = InstallController::getConfig();
					$dbConfig = $config['database'] ?? [];
					$hasDbConfig = !empty($dbConfig['host']) && !empty($dbConfig['name']) && isset($dbConfig['user']) && isset($dbConfig['pass']);
					?>
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-database me-2"></i>
							Configuración de Base de Datos
						</h6>
						<?php if ($hasDbConfig): ?>
							<div class="alert alert-success">
								<small>
									<i class="bi bi-check-circle me-2"></i>
									<strong>Configuración de base de datos encontrada:</strong><br>
									Servidor: <code><?php echo htmlspecialchars($dbConfig['host']); ?></code><br>
									Base de datos: <code><?php echo htmlspecialchars($dbConfig['name']); ?></code><br>
									Usuario: <code><?php echo htmlspecialchars($dbConfig['user']); ?></code><br>
									<em>La configuración de base de datos se lee desde <code>config.php</code></em>
								</small>
							</div>
						<?php else: ?>
							<div class="alert alert-warning">
								<small>
									<i class="bi bi-exclamation-triangle me-2"></i>
									<strong>Configuración de base de datos no encontrada.</strong><br>
									Por favor, configure la base de datos en <code>cms/config.php</code> o <code>cms/config.example.php</code> antes de continuar con la instalación.
								</small>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Section: Administrator Information -->
				<?php if (!$isPackageInstall): ?>
					<!-- Only show admin fields for clean installation -->
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-person-circle me-2"></i>
							Información del Administrador
						</h6>
						
						<div class="row g-3">
							<div class="col-md-6">
								<div class="form-group">
									<label for="email_admin" class="form-label small">
										Correo Administrador <span class="text-danger">*</span>
									</label>
									<input 
									type="email"
									class="form-control"
									id="email_admin"
									name="email_admin"
									placeholder="admin@ejemplo.com"
									required
									>
									<div class="valid-feedback small">✓ Válido</div>
									<div class="invalid-feedback small">Correo inválido</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="password_admin" class="form-label small">
										Contraseña <span class="text-danger">*</span>
									</label>
									<input 
									type="password"
									class="form-control"
									id="password_admin"
									name="password_admin"
									placeholder="••••••••"
									required
									>
									<div class="valid-feedback small">✓ Válido</div>
									<div class="invalid-feedback small">Requerido</div>
								</div>
							</div>
						</div>
					</div>
				<?php else: ?>
					<!-- Package installation: Admin info comes from restored database -->
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-person-circle me-2"></i>
							Información del Administrador
						</h6>
						<div class="alert alert-info">
							<small>
								<i class="bi bi-info-circle me-2"></i>
								<strong>La información del administrador se restaurará desde la base de datos.</strong><br>
								Los datos del administrador (correo, contraseña, etc.) están incluidos en el archivo <code>database.sql</code> y se restaurarán automáticamente.
							</small>
						</div>
						<!-- Hidden field to allow form submission -->
						<input type="hidden" name="email_admin" value="package_install">
					</div>
				<?php endif; ?>

				<!-- Section: Dashboard Configuration -->
				<?php if (!$isPackageInstall): ?>
					<!-- Only show dashboard config for clean installation -->
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-sliders me-2"></i>
							Configuración del Dashboard
						</h6>
					
					<div class="form-group mb-3">
						<label for="title_admin" class="form-label small">
							Nombre del Dashboard <span class="text-danger">*</span>
						</label>
						<input 
						type="text"
						class="form-control"
						id="title_admin"
						name="title_admin"
						placeholder="Mi Dashboard"
						required
						>
						<div class="valid-feedback small">✓ Válido</div>
						<div class="invalid-feedback small">Requerido</div>
					</div>

					<div class="row g-3">
						<div class="col-md-6">
							<div class="form-group">
								<label for="symbol_admin" class="form-label small">
									Símbolo <span class="text-danger">*</span>
								</label>
								<div class="input-group">
									<span class="input-group-text">
										<i class="bi" id="iconPreviewPlaceholder">bi-gear</i>
									</span>
									<input 
									type="text"
									class="form-control"
									id="symbol_admin"
									name="symbol_admin"
									placeholder="Seleccionar icono"
									readonly
									required
									>
									<button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectIcon" data-bs-toggle="modal" data-bs-target="#iconSelectorModal">
										<i class="bi bi-grid-3x3-gap"></i>
									</button>
									<div class="valid-feedback small">✓ Seleccionado</div>
									<div class="invalid-feedback small">Requerido</div>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="color_admin" class="form-label small">
									Color del Dashboard
								</label>
								<div class="d-flex align-items-center gap-2">
									<input 
									type="color"
									class="form-control form-control-color"
									id="color_admin"
									name="color_admin"
									value="#007bff"
									title="Escoge Color"
									style="width: 60px; height: 38px;"
									>
									<input 
									type="text"
									class="form-control"
									id="color_admin_text"
									value="#007bff"
									readonly
									style="max-width: 100px;"
									>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group mb-3">
						<label for="font_admin" class="form-label small">
							Tipografía del Dashboard
						</label>
						<div class="input-group">
							<span class="input-group-text">
								<i class="bi bi-fonts"></i>
							</span>
							<textarea 
							class="form-control"
							id="font_admin"
							name="font_admin"
							placeholder="Seleccionar tipografía"
							readonly
							rows="2"
							></textarea>
							<button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectFont" data-bs-toggle="modal" data-bs-target="#fontSelectorModal">
								<i class="bi bi-grid-3x3-gap"></i>
							</button>
						</div>
						<div id="fontPreview" class="mt-2 p-2 border rounded bg-light" style="display: none;">
							<small class="text-muted d-block mb-1"><strong>Vista previa:</strong></small>
							<span id="fontPreviewText" style="font-size: 0.95rem;">Texto de ejemplo con la fuente seleccionada</span>
						</div>
					</div>

					<div class="form-group mb-3">
						<label for="back_admin" class="form-label small">
							Imagen para el Login <small class="text-muted">(opcional)</small>
						</label>
						<input 
						type="text"
						class="form-control"
						id="back_admin"
						name="back_admin"
						placeholder="URL de la imagen"
						>
					</div>
					</div>
				<?php else: ?>
					<!-- Package installation: Dashboard config comes from restored database -->
					<div class="mb-4">
						<h6 class="section-title mb-3">
							<i class="bi bi-sliders me-2"></i>
							Configuración del Dashboard
						</h6>
						<div class="alert alert-info">
							<small>
								<i class="bi bi-info-circle me-2"></i>
								<strong>La configuración del dashboard se restaurará desde la base de datos.</strong><br>
								Los datos del dashboard (nombre, símbolo, color, etc.) están incluidos en el archivo <code>database.sql</code> y se restaurarán automáticamente.
							</small>
						</div>
					</div>
				<?php endif; ?>

				<div class="install-footer">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<small class="text-muted">
							<span class="text-danger">*</span> Campos obligatorios
						</small>
					</div>
					<button type="submit" class="btn btn-primary w-100">
						<i class="bi bi-check-circle me-2"></i>
						Instalar Dashboard
					</button>
				</div>


				<?php 
				
				// Detect if this is a package installation (has database.sql in root)
				require_once __DIR__ . '/../../../controllers/package-install.controller.php';
				$isPackageInstall = PackageInstallController::hasDatabaseFile();
				
				if ($isPackageInstall) {
					// Use package installer (restores database and updates URLs)
					$install = new PackageInstallController();
					$install->install();
				} else {
					// Use normal installer (clean installation)
					require_once "controllers/install.controller.php";
					$install = new InstallController();
					$install->install();
				}
				
				?>

			</form>

		</div>

	</div>

</div>

<!--=============================================
Icon Selector Modal - Reusable Component
===============================================-->
<?php require_once __DIR__ . '/../../modules/selectors/icon-selector.php'; ?>

<!--=============================================
Font Selector Modal - Reusable Component
===============================================-->
<?php require_once __DIR__ . '/../../modules/selectors/font-selector.php'; ?>

<!--=============================================
Installation Form Styles
===============================================-->
<style>
	/* Main container */
	.install-container {
		background: #f8f9fa;
		min-height: 100vh;
		padding: 2rem 1rem;
	}

	/* Installation card */
	.install-card {
		background: #ffffff;
		border-radius: 12px;
		box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
		border: 1px solid #e9ecef;
		padding: 2rem;
		max-width: 800px;
		width: 100%;
	}

	/* Title */
	.install-title {
		font-weight: 600;
		color: #212529;
		font-size: 1.5rem;
	}

	/* Divider */
	.install-divider {
		border: none;
		height: 1px;
		background: #e9ecef;
		margin: 1.5rem 0;
	}

	/* Sections */
	.section-title {
		color: #495057;
		font-weight: 600;
		font-size: 0.95rem;
		display: flex;
		align-items: center;
		margin-bottom: 1rem;
	}

	.section-title i {
		color: #6c757d;
		font-size: 1rem;
	}

	/* Labels */
	.form-label {
		font-weight: 500;
		color: #495057;
		margin-bottom: 0.4rem;
	}

	.form-label.small {
		font-size: 0.875rem;
	}

	/* Inputs */
	.form-control {
		border: 1px solid #ced4da;
		border-radius: 6px;
		padding: 0.5rem 0.75rem;
		transition: all 0.2s ease;
		font-size: 0.9rem;
	}

	.form-control:focus {
		border-color: #80bdff;
		box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
	}

	.form-control::placeholder {
		color: #adb5bd;
		font-size: 0.875rem;
	}

	/* Input group */
	.input-group-text {
		background: #f8f9fa;
		border: 1px solid #ced4da;
		color: #6c757d;
		font-size: 0.9rem;
	}

	.input-group .form-control:focus {
		border-left: 1px solid #80bdff;
	}

	/* Buttons */
	.btn-primary {
		background: #007bff;
		border: none;
		border-radius: 6px;
		padding: 0.5rem 1rem;
		font-weight: 500;
		font-size: 0.9rem;
		transition: all 0.2s ease;
	}

	.btn-primary:hover {
		background: #0056b3;
		transform: translateY(-1px);
		box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
	}

	.btn-outline-secondary {
		border: 1px solid #ced4da;
		border-radius: 6px;
		padding: 0.375rem 0.5rem;
		font-size: 0.875rem;
	}

	.btn-outline-secondary:hover {
		background: #f8f9fa;
		border-color: #adb5bd;
	}

	/* Color picker */
	.form-control-color {
		width: 50px;
		height: 38px;
		border-radius: 6px;
		border: 1px solid #ced4da;
		cursor: pointer;
		padding: 2px;
	}

	/* Footer */
	.install-footer {
		margin-top: 1.5rem;
		padding-top: 1.5rem;
		border-top: 1px solid #e9ecef;
	}

	/* Validation */
	.valid-feedback {
		display: none;
		color: #28a745;
		font-size: 0.8rem;
		margin-top: 0.25rem;
	}

	.invalid-feedback {
		display: none;
		color: #dc3545;
		font-size: 0.8rem;
		margin-top: 0.25rem;
	}

	.valid-feedback.small,
	.invalid-feedback.small {
		font-size: 0.75rem;
	}

	/* Show validation messages only when field is validated */
	.was-validated .form-control:valid ~ .valid-feedback,
	.was-validated .form-control:invalid ~ .invalid-feedback,
	.was-validated .form-control:valid ~ .input-group ~ .valid-feedback,
	.was-validated .form-control:invalid ~ .input-group ~ .invalid-feedback,
	.form-control.is-valid ~ .valid-feedback,
	.form-control.is-invalid ~ .invalid-feedback {
		display: block;
	}

	/* For input-group validation */
	.was-validated .input-group .form-control:valid ~ .valid-feedback,
	.was-validated .input-group .form-control:invalid ~ .invalid-feedback,
	.input-group .form-control.is-valid ~ .valid-feedback,
	.input-group .form-control.is-invalid ~ .invalid-feedback {
		display: block;
		width: 100%;
		margin-top: 0.25rem;
	}

	/* Font preview */
	#fontPreview {
		background: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 6px;
	}

	/* Responsive */
	@media (max-width: 768px) {
		.install-card {
			padding: 1.5rem;
		}

		.install-title {
			font-size: 1.25rem;
		}
	}
</style>

<!--=============================================
Selector Styles - Reusable Component
===============================================-->
<?php
// Get CMS base path
require_once __DIR__ . '/../../../controllers/template.controller.php';
$cmsBasePath = TemplateController::cmsBasePath();
?>
<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/selectors/selectors.css">

<!--=============================================
Selector Scripts - Reusable Components
===============================================-->
<script src="<?php echo $cmsBasePath ?>/views/assets/js/selectors/icon-selector.js"></script>
<script src="<?php echo $cmsBasePath ?>/views/assets/js/selectors/font-selector.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Initialize icon selector
	initIconSelector({
		inputId: 'symbol_admin',
		previewId: 'iconPreviewPlaceholder'
	});

	// Initialize font selector
	initFontSelector({
		inputId: 'font_admin',
		previewId: 'fontPreview',
		previewTextId: 'fontPreviewText'
	});

	// Sync color picker with text field
	const colorPicker = document.getElementById('color_admin');
	const colorText = document.getElementById('color_admin_text');
	if (colorPicker && colorText) {
		colorPicker.addEventListener('input', function() {
			colorText.value = this.value;
		});
		colorText.addEventListener('input', function() {
			if (/^#[0-9A-F]{6}$/i.test(this.value)) {
				colorPicker.value = this.value;
			}
		});
	}

	// Form validation - only show messages after user interaction
	const form = document.querySelector('.install-form');
	if (form) {
		// Remove validation classes on input to prevent premature validation
		const inputs = form.querySelectorAll('input, textarea');
		inputs.forEach(input => {
			// Remove validation classes when user starts typing
			input.addEventListener('input', function() {
				if (this.classList.contains('is-invalid')) {
					this.classList.remove('is-invalid');
				}
				if (this.classList.contains('is-valid')) {
					this.classList.remove('is-valid');
				}
			});

			// Validate on blur (when user leaves the field)
			input.addEventListener('blur', function() {
				if (this.checkValidity()) {
					this.classList.remove('is-invalid');
					this.classList.add('is-valid');
				} else {
					this.classList.remove('is-valid');
					this.classList.add('is-invalid');
				}
			});
		});

		// Validate form on submit
		form.addEventListener('submit', function(event) {
			if (!form.checkValidity()) {
				event.preventDefault();
				event.stopPropagation();
			}
			form.classList.add('was-validated');
		}, false);
	}
});
</script>