<div class="container-fluid py-3 p-lg-4">
        
    <div class="row">
      
       <!--==============================
          Breadcrumb
         ================================-->

          <div class="col-12 mb-3 position-relative">

            <div class="d-lg-flex justify-content-lg-between mt-2">

              <div class="text-capitalize h5 ps-2">Personalizable</div>

              <div class="pe-0">
                <ul class="nav justify-content-lg-end">
                  <li class="nav-item">
                    <a class="nav-link py-0 px-0 text-dark" href="<?php echo $cmsBasePath ?>/">Inicio</a>
                  </li>
                  <li class="nav-item ps-3">/</li>
                  <li class="nav-item">
                    <a class="nav-link py-0 disabled text-capitalize" href="#">Personalizable</a>
                  </li> 
                </ul>
              </div>

            </div>

          </div>

          <!--==============================
          Módulos
         ================================-->

         <div class="col-12 col-lg-6 mb-3">

            <div class="card rounded">
              <div class="card-body">
                <div class="jumbotron">
                  <h1 class="display-4">Hello, world!</h1>
                  <p class="lead">This is a simple hero unit, a simple jumbotron-style component for calling extra attention to featured content or information.</p>
                  <hr class="my-4">
                  <p>It uses utility classes for typography and spacing to space content out within the larger container.</p>
                  <a class="btn btn-primary btn-sm rounded" href="#" role="button">Learn more</a>
                </div>
              </div>
            </div>

         </div>

          <div class="col-12 col-lg-6 mb-3">

            <div class="card rounded">
              <div class="card-body">
                <div class="jumbotron">
                  <h1 class="display-4">Hello, world!</h1>
                  <p class="lead">This is a simple hero unit, a simple jumbotron-style component for calling extra attention to featured content or information.</p>
                  <hr class="my-4">
                  <p>It uses utility classes for typography and spacing to space content out within the larger container.</p>
                  <a class="btn btn-primary btn-sm rounded" href="#" role="button">Learn more</a>
                </div>
              </div>
            </div>

          </div>

        <!--==============================
        SEO Defaults
        ================================-->

        <div class="col-12 mt-4">
            <div class="card rounded shadow-sm">
                <div class="card-body">

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-search me-2 text-primary"></i>SEO por defecto
                        </h5>
                        <button class="btn btn-sm btn-primary" id="seo-save-btn">
                            <i class="bi bi-check-lg me-1"></i>Guardar
                        </button>
                    </div>

                    <div class="row g-3">

                        <div class="col-12">
                            <label for="seo_default_title" class="form-label small fw-semibold">
                                Plantilla de título por defecto
                            </label>
                            <input
                                type="text"
                                class="form-control form-control-sm rounded"
                                id="seo_default_title"
                                placeholder="%page_title%"
                            >
                            <div class="form-text">Usa <code>%page_title%</code> como marcador del título de la página.</div>
                        </div>

                        <div class="col-12">
                            <label for="seo_default_description" class="form-label small fw-semibold">
                                Meta description por defecto
                            </label>
                            <textarea
                                class="form-control form-control-sm rounded"
                                id="seo_default_description"
                                rows="2"
                                placeholder="Descripción general del sitio"
                            ></textarea>
                        </div>

                        <div class="col-12">
                            <label for="seo_canonical_base_url" class="form-label small fw-semibold">
                                URL base canónica
                            </label>
                            <input
                                type="url"
                                class="form-control form-control-sm rounded"
                                id="seo_canonical_base_url"
                                placeholder="https://example.com"
                            >
                            <div class="form-text">Se usa como prefijo en el sitemap y en las etiquetas canónicas.</div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>

  </div>

<script>
(function () {
    var ajaxUrl = '<?php echo $cmsBasePath ?>/ajax/theme-settings.ajax.php';

    // Load current values on page load
    fetch(ajaxUrl + '?action=get_seo')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.seo) {
                document.getElementById('seo_default_title').value       = data.seo.seo_default_title       || '';
                document.getElementById('seo_default_description').value = data.seo.seo_default_description || '';
                document.getElementById('seo_canonical_base_url').value  = data.seo.seo_canonical_base_url  || '';
            }
        });

    // Save handler
    document.getElementById('seo-save-btn').addEventListener('click', function () {
        var body = new URLSearchParams({
            action:                   'save_seo',
            seo_default_title:        document.getElementById('seo_default_title').value,
            seo_default_description:  document.getElementById('seo_default_description').value,
            seo_canonical_base_url:   document.getElementById('seo_canonical_base_url').value,
        });

        fetch(ajaxUrl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    if (typeof fncToastr === 'function') fncToastr('success', 'Configuración SEO guardada');
                } else {
                    if (typeof fncToastr === 'function') fncToastr('error', data.error || 'Error al guardar');
                }
            });
    });
})();
</script>