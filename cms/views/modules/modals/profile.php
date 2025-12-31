<!-- The Modal -->
<div class="modal" id="myProfile">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded">

      <form method="POST" class="needs-validation" novalidate>

        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title text-capitalize">Perfil <?php echo isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) ? $_SESSION["admin"]->rol_admin : '' ?></h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal body -->
        <div class="modal-body px-4">

          <input type="hidden" name="id_admin" value="<?php echo isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) ? base64_encode($_SESSION["admin"]->id_admin) : '' ?>">
         
          <div class="mb-3">
            <h6 class="text-muted mb-3">
              <i class="bi bi-person-circle"></i> Información de Cuenta
            </h6>
          </div>

          <div class="row g-3">
            
            <!--=============================================
            Account Information Section
            ===============================================-->
            
            <div class="col-12">
              <div class="card border-0 bg-light">
                <div class="card-body">
                  <h6 class="card-title text-muted mb-3">
                    <i class="bi bi-shield-lock"></i> Credenciales
                  </h6>
                  
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="email_admin" class="form-label small fw-semibold">Correo<sup>*</sup></label>
                      <input 
                        type="email"
                        class="form-control form-control-sm rounded"
                        id="email_admin"
                        name="email_admin"
                        value="<?php echo isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) ? $_SESSION["admin"]->email_admin : '' ?>"
                        required
                      >
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>

                    <div class="col-md-6">
                      <label for="password_admin" class="form-label small fw-semibold">Contraseña</label>
                      <input 
                        type="password"
                        class="form-control form-control-sm rounded"
                        id="password_admin"
                        name="password_admin"
                        placeholder="Dejar vacío para mantener la actual"
                      >
                      <div class="valid-feedback">Válido.</div>
                      <div class="invalid-feedback">Campo inválido.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (isset($_SESSION["admin"]) && is_object($_SESSION["admin"]) && $_SESSION["admin"]->rol_admin == "superadmin"): ?>

              <!--=============================================
              Dashboard Configuration Section
              ===============================================-->
              
              <div class="col-12">
                <div class="mb-3">
                  <h6 class="text-muted mb-3">
                    <i class="bi bi-sliders"></i> Configuración del Dashboard
                  </h6>
                </div>
              </div>

              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-info-circle"></i> Información General
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label for="title_admin" class="form-label small fw-semibold">Nombre del Dashboard<sup>*</sup></label>
                        <input 
                          type="text" 
                          class="form-control form-control-sm rounded" 
                          id="title_admin"
                          name="title_admin"
                          value="<?php echo $_SESSION["admin"]->title_admin ?>"
                          placeholder="Nombre del dashboard"
                          required
                        >
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--=============================================
              Appearance Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-palette"></i> Apariencia
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="symbol_admin" class="form-label small fw-semibold">Símbolo<sup>*</sup></label>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text bg-white">
                            <i class="bi <?php echo $_SESSION["admin"]->symbol_admin ?>" id="iconPreviewPlaceholder"></i>
                          </span>
                          <input 
                            type="text"
                            class="form-control form-control-sm rounded"
                            id="symbol_admin"
                            name="symbol_admin"
                            value="<?php echo htmlspecialchars($_SESSION["admin"]->symbol_admin) ?>"
                            placeholder="Seleccionar icono"
                            readonly
                            required
                          >
                          <button type="button" class="btn btn-outline-secondary" id="btnSelectIcon" data-bs-toggle="modal" data-bs-target="#iconSelectorModal" title="Seleccionar icono">
                            <i class="bi bi-grid-3x3-gap"></i>
                          </button>
                        </div>
                        <div class="valid-feedback">Válido.</div>
                        <div class="invalid-feedback">Campo inválido.</div>
                      </div>

                      <div class="col-md-6">
                        <label for="color_admin" class="form-label small fw-semibold">Color del Dashboard</label>
                        <div class="d-flex align-items-center gap-2">
                          <input 
                            type="color"
                            class="form-control form-control-color"
                            id="color_admin"
                            name="color_admin"
                            value="<?php echo $_SESSION["admin"]->color_admin ?>"
                            title="Seleccionar color"
                            style="width: 60px; height: 38px;"
                          >
                          <input 
                            type="text"
                            class="form-control form-control-sm rounded"
                            id="color_admin_text"
                            value="<?php echo $_SESSION["admin"]->color_admin ?>"
                            readonly
                            style="max-width: 120px;"
                          >
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--=============================================
              Typography Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-type"></i> Tipografía
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label for="font_admin" class="form-label small fw-semibold">Tipografía del Dashboard</label>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text bg-white">
                            <i class="bi bi-fonts"></i>
                          </span>
                          <textarea 
                            class="form-control form-control-sm rounded"
                            id="font_admin"
                            name="font_admin"
                            placeholder="Seleccionar tipografía"
                            readonly
                            rows="2"
                          ><?php echo htmlspecialchars($_SESSION["admin"]->font_admin) ?></textarea>
                          <button type="button" class="btn btn-outline-secondary" id="btnSelectFont" data-bs-toggle="modal" data-bs-target="#fontSelectorModal" title="Seleccionar tipografía">
                            <i class="bi bi-grid-3x3-gap"></i>
                          </button>
                        </div>
                        <div id="fontPreview" class="mt-2 p-2 border rounded bg-light" <?php if(empty($_SESSION["admin"]->font_admin)): ?> style="display: none;" <?php endif ?>>
                          <small class="text-muted d-block mb-1"><strong>Vista previa:</strong></small>
                          <span id="fontPreviewText" style="font-size: 0.95rem; <?php if(!empty($_SESSION["admin"]->font_admin)): ?><?php echo explode("\n\n", $_SESSION["admin"]->font_admin)[1] ?? $_SESSION["admin"]->font_admin ?><?php endif ?>">Texto de ejemplo con la fuente seleccionada</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--=============================================
              Login Background Section
              ===============================================-->
              
              <div class="col-12">
                <div class="card border-0 bg-light">
                  <div class="card-body">
                    <h6 class="card-title text-muted mb-3">
                      <i class="bi bi-image"></i> Imagen de Fondo
                    </h6>
                    
                    <div class="row g-3">
                      <div class="col-md-12">
                        <label for="back_admin" class="form-label small fw-semibold">Imagen para el Login</label>
                        <input 
                          type="text" 
                          class="form-control form-control-sm rounded" 
                          id="back_admin"
                          name="back_admin"
                          value="<?php echo $_SESSION["admin"]->back_admin ?>"
                          placeholder="URL de la imagen"
                        >
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            <?php endif ?>

          </div>

        </div>

        <!-- Modal footer -->
        <div class="modal-footer d-flex justify-content-between">
          
          <div><button type="button" class="btn btn-dark rounded" data-bs-dismiss="modal">Cerrar</button></div>
          <div><button type="submit" class="btn btn-default backColor rounded">Guardar</button></div>
          
        </div>

      </form>

    </div>
  </div>
</div>


<!--=============================================
Icon Selector Modal
===============================================-->
<div class="modal fade" id="iconSelectorModal" tabindex="-1" aria-labelledby="iconSelectorModalLabel" aria-hidden="true" style="z-index: 1060;">
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
<div class="modal fade" id="fontSelectorModal" tabindex="-1" aria-labelledby="fontSelectorModalLabel" aria-hidden="true" style="z-index: 1060;">
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

<script>
// List of popular Bootstrap Icons
const bootstrapIconsProfile = [
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
const googleFontsProfile = [
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

function loadIconsProfile() {
	const iconGrid = document.getElementById('iconGrid');
	if(!iconGrid) return;
	iconGrid.innerHTML = '';
	
	bootstrapIconsProfile.forEach(iconClass => {
		const iconItem = document.createElement('div');
		iconItem.className = 'icon-item';
		iconItem.innerHTML = `
			<i class="bi ${iconClass}"></i>
			<span>${iconClass.replace('bi-', '')}</span>
		`;
		iconItem.addEventListener('click', () => {
			document.querySelectorAll('#iconGrid .icon-item').forEach(item => item.classList.remove('selected'));
			iconItem.classList.add('selected');
			const iconInput = document.getElementById('symbol_admin');
			iconInput.value = iconClass;
			const iconPreviewPlaceholder = document.getElementById('iconPreviewPlaceholder');
			if (iconPreviewPlaceholder) {
				iconPreviewPlaceholder.className = `bi ${iconClass}`;
			}
			setTimeout(() => {
				const modalElement = document.getElementById('iconSelectorModal');
				const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
				modal.hide();
			}, 300);
		});
		iconGrid.appendChild(iconItem);
	});
}

function loadFontsProfile() {
	const fontList = document.getElementById('fontList');
	if(!fontList) return;
	fontList.innerHTML = '';
	
	googleFontsProfile.forEach(font => {
		const fontItem = document.createElement('div');
		fontItem.className = 'font-item';
		fontItem.style.fontFamily = `"${font.family}", sans-serif`;
		fontItem.innerHTML = `
			<div class="font-item-name">${font.name}</div>
			<div class="font-item-preview">The quick brown fox jumps over the lazy dog</div>
			<small class="text-muted">${font.category}</small>
		`;
		fontItem.addEventListener('click', () => {
			document.querySelectorAll('#fontList .font-item').forEach(item => item.classList.remove('selected'));
			fontItem.classList.add('selected');
			const fontInput = document.getElementById('font_admin');
			const fontUrl = `@import url('https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap');`;
			const fontCss = `font-family: '${font.family}', sans-serif;`;
			fontInput.value = `${fontUrl}\n\n${fontCss}`;
			
			const preview = document.getElementById('fontPreview');
			const previewText = document.getElementById('fontPreviewText');
			preview.style.display = 'block';
			previewText.style.fontFamily = `"${font.family}", sans-serif`;
			
			const link = document.createElement('link');
			link.href = `https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap`;
			link.rel = 'stylesheet';
			document.head.appendChild(link);
			
			setTimeout(() => {
				const modalElement = document.getElementById('fontSelectorModal');
				const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
				modal.hide();
			}, 300);
		});
		fontList.appendChild(fontItem);
	});
}

document.addEventListener('DOMContentLoaded', function() {
	const iconModal = document.getElementById('iconSelectorModal');
	if (iconModal) {
		iconModal.addEventListener('show.bs.modal', loadIconsProfile);
		const iconSearch = document.getElementById('iconSearch');
		if (iconSearch) {
			iconSearch.addEventListener('input', function(e) {
				const searchTerm = e.target.value.toLowerCase();
				document.querySelectorAll('#iconGrid .icon-item').forEach(item => {
					item.style.display = item.textContent.toLowerCase().includes(searchTerm) ? 'block' : 'none';
				});
			});
		}
	}
	
	const fontModal = document.getElementById('fontSelectorModal');
	if (fontModal) {
		fontModal.addEventListener('show.bs.modal', loadFontsProfile);
		const fontSearch = document.getElementById('fontSearch');
		if (fontSearch) {
			fontSearch.addEventListener('input', function(e) {
				const searchTerm = e.target.value.toLowerCase();
				document.querySelectorAll('#fontList .font-item').forEach(item => {
					item.style.display = item.textContent.toLowerCase().includes(searchTerm) ? 'block' : 'none';
				});
			});
		}
	}
	
	const colorPicker = document.getElementById('color_admin');
	const colorText = document.getElementById('color_admin_text');
	if (colorPicker && colorText) {
		colorPicker.addEventListener('input', function() { colorText.value = this.value; });
	}
});
</script>