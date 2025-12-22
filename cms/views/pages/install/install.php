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
Icon Selector Modal
===============================================-->
<div class="modal fade" id="iconSelectorModal" tabindex="-1" aria-labelledby="iconSelectorModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="iconSelectorModalLabel">
					<i class="bi bi-palette"></i> Seleccionar Icono
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<input type="text" class="form-control" id="iconSearch" placeholder="Buscar icono...">
				</div>
				<div class="icon-grid" id="iconGrid" style="max-height: 500px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;">
					<!-- Icons will be loaded dynamically -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
			</div>
		</div>
	</div>
</div>

<!--=============================================
Font Selector Modal
===============================================-->
<div class="modal fade" id="fontSelectorModal" tabindex="-1" aria-labelledby="fontSelectorModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="fontSelectorModalLabel">
					<i class="bi bi-type"></i> Seleccionar Tipografía
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<input type="text" class="form-control" id="fontSearch" placeholder="Buscar fuente...">
				</div>
				<div class="font-list" id="fontList" style="max-height: 500px; overflow-y: auto;">
					<!-- Fonts will be loaded dynamically -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
			</div>
		</div>
	</div>
</div>

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
Selector Styles
===============================================-->
<style>
	.icon-item {
		padding: 15px;
		text-align: center;
		border: 2px solid #e0e0e0;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.3s ease;
		background: #fff;
	}
	
	.icon-item:hover {
		border-color: #007bff;
		background: #f0f8ff;
		transform: scale(1.05);
	}
	
	.icon-item.selected {
		border-color: #007bff;
		background: #e7f3ff;
	}
	
	.icon-item i {
		font-size: 2rem;
		display: block;
		margin-bottom: 5px;
	}
	
	.icon-item span {
		font-size: 0.75rem;
		color: #666;
		display: block;
		word-break: break-word;
	}
	
	.icon-preview {
		display: inline-flex;
		align-items: center;
		font-size: 1.5rem;
		color: #007bff;
	}
	
	.font-item {
		padding: 15px;
		border: 2px solid #e0e0e0;
		border-radius: 8px;
		margin-bottom: 10px;
		cursor: pointer;
		transition: all 0.3s ease;
		background: #fff;
	}
	
	.font-item:hover {
		border-color: #007bff;
		background: #f0f8ff;
	}
	
	.font-item.selected {
		border-color: #007bff;
		background: #e7f3ff;
	}
	
	.font-item-name {
		font-size: 1.2rem;
		font-weight: bold;
		margin-bottom: 5px;
	}
	
	.font-item-preview {
		font-size: 1rem;
		color: #666;
	}
</style>

<!--=============================================
Selector Scripts
===============================================-->
<script>
// List of popular Bootstrap Icons
const bootstrapIcons = [
	'bi-house', 'bi-house-door', 'bi-building', 'bi-briefcase', 'bi-briefcase-fill',
	'bi-graph-up', 'bi-graph-down', 'bi-bar-chart', 'bi-pie-chart', 'bi-speedometer',
	'bi-people', 'bi-person', 'bi-person-circle', 'bi-person-square', 'bi-people-fill',
	'bi-envelope', 'bi-envelope-fill', 'bi-envelope-open', 'bi-chat', 'bi-chat-dots',
	'bi-gear', 'bi-gear-fill', 'bi-sliders', 'bi-tools', 'bi-wrench',
	'bi-folder', 'bi-folder-fill', 'bi-file-earmark', 'bi-file-earmark-text', 'bi-file-earmark-code',
	'bi-image', 'bi-image-fill', 'bi-camera', 'bi-camera-fill', 'bi-palette',
	'bi-heart', 'bi-heart-fill', 'bi-star', 'bi-star-fill', 'bi-bookmark',
	'bi-shield', 'bi-shield-fill', 'bi-lock', 'bi-lock-fill', 'bi-key',
	'bi-bell', 'bi-bell-fill', 'bi-megaphone', 'bi-bullhorn', 'bi-volume-up',
	'bi-calendar', 'bi-calendar-event', 'bi-clock', 'bi-clock-history', 'bi-stopwatch',
	'bi-search', 'bi-funnel', 'bi-filter', 'bi-sort-down', 'bi-sort-up',
	'bi-plus', 'bi-plus-circle', 'bi-dash', 'bi-x', 'bi-check',
	'bi-arrow-left', 'bi-arrow-right', 'bi-arrow-up', 'bi-arrow-down', 'bi-arrows-move',
	'bi-grid', 'bi-grid-3x3', 'bi-list', 'bi-list-ul', 'bi-menu-button',
	'bi-download', 'bi-upload', 'bi-share', 'bi-link', 'bi-link-45deg',
	'bi-printer', 'bi-save', 'bi-trash', 'bi-pencil', 'bi-pencil-square',
	'bi-eye', 'bi-eye-slash', 'bi-info-circle', 'bi-question-circle', 'bi-exclamation-circle',
	'bi-check-circle', 'bi-x-circle', 'bi-flag', 'bi-flag-fill', 'bi-bookmark-star',
	'bi-trophy', 'bi-award', 'bi-gift', 'bi-cart', 'bi-bag',
	'bi-credit-card', 'bi-wallet', 'bi-cash', 'bi-currency-dollar', 'bi-currency-euro',
	'bi-globe', 'bi-geo-alt', 'bi-map', 'bi-compass', 'bi-navigation',
	'bi-wifi', 'bi-bluetooth', 'bi-battery', 'bi-lightning', 'bi-lightning-fill',
	'bi-sun', 'bi-moon', 'bi-cloud', 'bi-cloud-rain', 'bi-cloud-sun',
	'bi-music-note', 'bi-play', 'bi-pause', 'bi-stop', 'bi-skip-forward',
	'bi-film', 'bi-camera-video', 'bi-mic', 'bi-mic-mute', 'bi-headphones',
	'bi-laptop', 'bi-phone', 'bi-tablet', 'bi-display', 'bi-tv',
	'bi-database', 'bi-server', 'bi-hdd', 'bi-usb', 'bi-usb-drive',
	'bi-box', 'bi-archive', 'bi-inbox', 'bi-outbox', 'bi-send',
	'bi-recycle', 'bi-trash2', 'bi-trash3', 'bi-x-octagon', 'bi-shield-exclamation',
	'bi-activity', 'bi-pulse', 'bi-heart-pulse', 'bi-thermometer', 'bi-droplet',
	'bi-flower1', 'bi-flower2', 'bi-tree', 'bi-bug', 'bi-bug-fill',
	'bi-robot', 'bi-cpu', 'bi-motherboard', 'bi-memory', 'bi-hdd-stack'
];

// List of popular Google Fonts
const googleFonts = [
	{ name: 'Roboto', family: 'Roboto', category: 'Sans Serif' },
	{ name: 'Open Sans', family: 'Open Sans', category: 'Sans Serif' },
	{ name: 'Lato', family: 'Lato', category: 'Sans Serif' },
	{ name: 'Montserrat', family: 'Montserrat', category: 'Sans Serif' },
	{ name: 'Poppins', family: 'Poppins', category: 'Sans Serif' },
	{ name: 'Raleway', family: 'Raleway', category: 'Sans Serif' },
	{ name: 'Ubuntu', family: 'Ubuntu', category: 'Sans Serif' },
	{ name: 'Nunito', family: 'Nunito', category: 'Sans Serif' },
	{ name: 'Source Sans Pro', family: 'Source Sans Pro', category: 'Sans Serif' },
	{ name: 'Inter', family: 'Inter', category: 'Sans Serif' },
	{ name: 'Playfair Display', family: 'Playfair Display', category: 'Serif' },
	{ name: 'Merriweather', family: 'Merriweather', category: 'Serif' },
	{ name: 'Lora', family: 'Lora', category: 'Serif' },
	{ name: 'PT Serif', family: 'PT Serif', category: 'Serif' },
	{ name: 'Crimson Text', family: 'Crimson Text', category: 'Serif' },
	{ name: 'Roboto Slab', family: 'Roboto Slab', category: 'Serif' },
	{ name: 'Dancing Script', family: 'Dancing Script', category: 'Handwriting' },
	{ name: 'Pacifico', family: 'Pacifico', category: 'Handwriting' },
	{ name: 'Caveat', family: 'Caveat', category: 'Handwriting' },
	{ name: 'Kalam', family: 'Kalam', category: 'Handwriting' },
	{ name: 'Permanent Marker', family: 'Permanent Marker', category: 'Handwriting' },
	{ name: 'Oswald', family: 'Oswald', category: 'Display' },
	{ name: 'Bebas Neue', family: 'Bebas Neue', category: 'Display' },
	{ name: 'Righteous', family: 'Righteous', category: 'Display' },
	{ name: 'Bangers', family: 'Bangers', category: 'Display' },
	{ name: 'Anton', family: 'Anton', category: 'Display' }
];

// Load icons in the modal
function loadIcons() {
	const iconGrid = document.getElementById('iconGrid');
	iconGrid.innerHTML = '';
	
	bootstrapIcons.forEach(iconClass => {
		const iconItem = document.createElement('div');
		iconItem.className = 'icon-item';
		iconItem.innerHTML = `
			<i class="bi ${iconClass}"></i>
			<span>${iconClass.replace('bi-', '')}</span>
		`;
		iconItem.addEventListener('click', () => {
			// Remove previous selection
			document.querySelectorAll('.icon-item').forEach(item => item.classList.remove('selected'));
			// Add selection
			iconItem.classList.add('selected');
			// Update input with icon name
			const iconInput = document.getElementById('symbol_admin');
			iconInput.value = iconClass;
			// Update icon preview in input-group-text
			const iconPreviewPlaceholder = document.getElementById('iconPreviewPlaceholder');
			if (iconPreviewPlaceholder) {
				iconPreviewPlaceholder.className = `bi ${iconClass}`;
			}
			// Mark field as valid
			iconInput.classList.remove('is-invalid');
			iconInput.classList.add('is-valid');
			// Close modal after a brief delay
			setTimeout(() => {
				const modalElement = document.getElementById('iconSelectorModal');
				const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
				modal.hide();
			}, 300);
		});
		iconGrid.appendChild(iconItem);
	});
}

// Load fonts in the modal
function loadFonts() {
	const fontList = document.getElementById('fontList');
	fontList.innerHTML = '';
	
	// Load Google Fonts
	googleFonts.forEach(font => {
		const fontItem = document.createElement('div');
		fontItem.className = 'font-item';
		fontItem.style.fontFamily = `"${font.family}", sans-serif`;
		fontItem.innerHTML = `
			<div class="font-item-name">${font.name}</div>
			<div class="font-item-preview">The quick brown fox jumps over the lazy dog</div>
			<small class="text-muted">${font.category}</small>
		`;
		fontItem.addEventListener('click', () => {
			// Remove previous selection
			document.querySelectorAll('.font-item').forEach(item => item.classList.remove('selected'));
			// Add selection
			fontItem.classList.add('selected');
			// Update textarea with Google Fonts code
			const fontInput = document.getElementById('font_admin');
			const fontUrl = `@import url('https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap');`;
			const fontCss = `font-family: '${font.family}', sans-serif;`;
			fontInput.value = `${fontUrl}\n\n${fontCss}`;
			
			// Mark field as valid
			fontInput.classList.remove('is-invalid');
			fontInput.classList.add('is-valid');
			
			// Update preview
			const preview = document.getElementById('fontPreview');
			const previewText = document.getElementById('fontPreviewText');
			preview.style.display = 'block';
			previewText.style.fontFamily = `"${font.family}", sans-serif`;
			
			// Load font dynamically
			const link = document.createElement('link');
			link.href = `https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap`;
			link.rel = 'stylesheet';
			document.head.appendChild(link);
			
			// Close modal after a brief delay
			setTimeout(() => {
				const modalElement = document.getElementById('fontSelectorModal');
				const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
				modal.hide();
			}, 300);
		});
		fontList.appendChild(fontItem);
	});
}

// Icon search
document.addEventListener('DOMContentLoaded', function() {
	// Load icons when modal opens
	const iconModal = document.getElementById('iconSelectorModal');
	if (iconModal) {
		iconModal.addEventListener('show.bs.modal', function() {
			loadIcons();
			// Clear search
			const iconSearch = document.getElementById('iconSearch');
			if (iconSearch) iconSearch.value = '';
		});
		
		// Icon search
		const iconSearch = document.getElementById('iconSearch');
		if (iconSearch) {
			iconSearch.addEventListener('input', function(e) {
				const searchTerm = e.target.value.toLowerCase();
				const iconItems = document.querySelectorAll('.icon-item');
				iconItems.forEach(item => {
					const iconText = item.textContent.toLowerCase();
					if (iconText.includes(searchTerm)) {
						item.style.display = 'block';
					} else {
						item.style.display = 'none';
					}
				});
			});
		}
	}
	
	// Load fonts when modal opens
	const fontModal = document.getElementById('fontSelectorModal');
	if (fontModal) {
		fontModal.addEventListener('show.bs.modal', function() {
			loadFonts();
			// Clear search
			const fontSearch = document.getElementById('fontSearch');
			if (fontSearch) fontSearch.value = '';
		});
		
		// Font search
		const fontSearch = document.getElementById('fontSearch');
		if (fontSearch) {
			fontSearch.addEventListener('input', function(e) {
				const searchTerm = e.target.value.toLowerCase();
				const fontItems = document.querySelectorAll('.font-item');
				fontItems.forEach(item => {
					const fontText = item.textContent.toLowerCase();
					if (fontText.includes(searchTerm)) {
						item.style.display = 'block';
					} else {
						item.style.display = 'none';
					}
				});
			});
		}
	}
	
	// Allow clicking input to open icon modal
	const symbolInput = document.getElementById('symbol_admin');
	if (symbolInput) {
		symbolInput.addEventListener('click', function() {
			const modal = new bootstrap.Modal(document.getElementById('iconSelectorModal'));
			modal.show();
		});
	}
	
	// Allow clicking textarea to open font modal
	const fontInput = document.getElementById('font_admin');
	if (fontInput) {
		fontInput.addEventListener('click', function() {
			const modal = new bootstrap.Modal(document.getElementById('fontSelectorModal'));
			modal.show();
		});
	}
	
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