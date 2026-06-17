<?php
// $seoData (optional): SEO record object from SeoController::getSeo()
// $pageTitle (optional): current page title for slug auto-generation
$seoData      = $seoData ?? null;
$existingSlug = $seoData ? htmlspecialchars($seoData->slug_seo ?? '')          : '';
$existingMT   = $seoData ? htmlspecialchars($seoData->meta_title_seo ?? '')    : '';
$existingMD   = $seoData ? htmlspecialchars($seoData->meta_desc_seo ?? '')     : '';
$existingOGT  = $seoData ? htmlspecialchars($seoData->og_title_seo ?? '')      : '';
$existingOGD  = $seoData ? htmlspecialchars($seoData->og_desc_seo ?? '')       : '';
$existingOGI  = $seoData ? htmlspecialchars($seoData->og_image_seo ?? '')      : '';
$existingOGTy = $seoData ? htmlspecialchars($seoData->og_type_seo ?? 'website'): 'website';

require_once __DIR__ . '/../../controllers/template.controller.php';
$cmsBasePath = TemplateController::cmsBasePath();
?>

<link rel="stylesheet" href="<?php echo $cmsBasePath ?>/views/assets/css/seo/seo.css">

<div class="seo-panel mt-3">

    <!-- Basic SEO -->
    <div class="card border-0 bg-light mb-3">
        <div class="card-body">
            <h6 class="card-title text-muted mb-3">
                <i class="bi bi-search"></i> SEO
            </h6>

            <div class="row g-3">

                <!-- Slug -->
                <div class="col-12">
                    <label for="slug_seo" class="form-label small fw-semibold">
                        Slug (URL)
                    </label>
                    <div class="input-group input-group-sm">
                        <input
                            type="text"
                            class="form-control form-control-sm rounded-start"
                            id="slug_seo"
                            name="slug_seo"
                            placeholder="mi-pagina"
                            value="<?php echo $existingSlug ?>"
                        >
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_gen_slug" title="Auto-generate from title">
                            <i class="bi bi-magic"></i>
                        </button>
                    </div>
                    <div class="seo-slug-preview" id="slug_preview">
                        <?php if ($existingSlug): ?>
                            <?php echo '/' . $existingSlug ?>
                        <?php endif ?>
                    </div>
                </div>

                <!-- Meta Title -->
                <div class="col-12">
                    <label for="meta_title_seo" class="form-label small fw-semibold">
                        Meta Title
                    </label>
                    <input
                        type="text"
                        class="form-control form-control-sm rounded seo-meta-title"
                        id="meta_title_seo"
                        name="meta_title_seo"
                        placeholder="Título para buscadores (máx 60 caracteres)"
                        maxlength="80"
                        value="<?php echo $existingMT ?>"
                    >
                    <div class="seo-bar"><div class="seo-bar-fill" id="mt_bar"></div></div>
                    <div class="seo-char-counter" id="mt_counter">0 / 60</div>
                </div>

                <!-- Meta Description -->
                <div class="col-12">
                    <label for="meta_desc_seo" class="form-label small fw-semibold">
                        Meta Description
                    </label>
                    <textarea
                        class="form-control form-control-sm rounded seo-meta-desc"
                        id="meta_desc_seo"
                        name="meta_desc_seo"
                        rows="2"
                        placeholder="Descripción para buscadores (máx 160 caracteres)"
                        maxlength="200"
                    ><?php echo $existingMD ?></textarea>
                    <div class="seo-bar"><div class="seo-bar-fill" id="md_bar"></div></div>
                    <div class="seo-char-counter" id="md_counter">0 / 160</div>
                </div>

            </div>
        </div>
    </div>

    <!-- Open Graph -->
    <div class="card border-0 bg-light mb-2">
        <div class="card-body">
            <h6 class="card-title text-muted mb-3">
                <i class="bi bi-share"></i> Open Graph (redes sociales)
            </h6>

            <div class="row g-3">

                <div class="col-md-6">
                    <label for="og_title_seo" class="form-label small fw-semibold">OG Title</label>
                    <input
                        type="text"
                        class="form-control form-control-sm rounded"
                        id="og_title_seo"
                        name="og_title_seo"
                        placeholder="Título en redes (deja vacío para usar Meta Title)"
                        value="<?php echo $existingOGT ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label for="og_type_seo" class="form-label small fw-semibold">OG Type</label>
                    <select class="form-select form-select-sm rounded" id="og_type_seo" name="og_type_seo">
                        <option value="website"  <?php echo $existingOGTy === 'website'  ? 'selected' : '' ?>>website</option>
                        <option value="article"  <?php echo $existingOGTy === 'article'  ? 'selected' : '' ?>>article</option>
                        <option value="product"  <?php echo $existingOGTy === 'product'  ? 'selected' : '' ?>>product</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="og_desc_seo" class="form-label small fw-semibold">OG Description</label>
                    <textarea
                        class="form-control form-control-sm rounded"
                        id="og_desc_seo"
                        name="og_desc_seo"
                        rows="2"
                        placeholder="Descripción en redes (deja vacío para usar Meta Description)"
                    ><?php echo $existingOGD ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">OG Image</label>
                    <div class="input-group input-group-sm">
                        <input
                            type="text"
                            class="form-control form-control-sm rounded-start"
                            id="og_image_seo"
                            name="og_image_seo"
                            placeholder="Ruta de imagen (usa el gestor de archivos)"
                            value="<?php echo $existingOGI ?>"
                            readonly
                        >
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_og_image" title="Select from file manager">
                            <i class="bi bi-image"></i>
                        </button>
                    </div>
                    <?php if ($existingOGI): ?>
                        <img src="<?php echo $cmsBasePath . '/' . ltrim($existingOGI, '/') ?>" alt="OG preview" class="mt-2 rounded" style="max-height:80px; max-width:200px; object-fit:cover;">
                    <?php endif ?>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
(function () {
    // Character counter helper
    function updateCounter(inputEl, barEl, counterEl, limit) {
        var len   = inputEl.value.length;
        var pct   = Math.min(len / limit * 100, 100);
        var cls   = len > limit ? 'over' : (len > limit * 0.8 ? 'warn' : '');
        barEl.style.width    = pct + '%';
        barEl.className      = 'seo-bar-fill' + (cls ? ' ' + cls : '');
        counterEl.textContent = len + ' / ' + limit;
        counterEl.className   = 'seo-char-counter' + (cls ? ' ' + cls : '');
    }

    var mtInput   = document.getElementById('meta_title_seo');
    var mtBar     = document.getElementById('mt_bar');
    var mtCounter = document.getElementById('mt_counter');
    var mdInput   = document.getElementById('meta_desc_seo');
    var mdBar     = document.getElementById('md_bar');
    var mdCounter = document.getElementById('md_counter');

    if (mtInput) {
        updateCounter(mtInput, mtBar, mtCounter, 60);
        mtInput.addEventListener('input', function () { updateCounter(mtInput, mtBar, mtCounter, 60); });
    }
    if (mdInput) {
        updateCounter(mdInput, mdBar, mdCounter, 160);
        mdInput.addEventListener('input', function () { updateCounter(mdInput, mdBar, mdCounter, 160); });
    }

    // Auto-generate slug from page title
    var btnGen   = document.getElementById('btn_gen_slug');
    var slugInput = document.getElementById('slug_seo');
    var slugPrev  = document.getElementById('slug_preview');

    function slugify(str) {
        var s = str.toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        return s;
    }

    if (btnGen && slugInput) {
        btnGen.addEventListener('click', function () {
            var titleEl = document.getElementById('title_page');
            if (titleEl && titleEl.value.trim()) {
                slugInput.value = slugify(titleEl.value.trim());
                if (slugPrev) slugPrev.textContent = '/' + slugInput.value;
            }
        });

        slugInput.addEventListener('input', function () {
            if (slugPrev) {
                slugPrev.textContent = slugInput.value ? '/' + slugInput.value : '';
            }
        });
    }

    // OG image picker — sets value in the hidden input when a file is selected in the archivos modal
    var btnOgImage = document.getElementById('btn_og_image');
    var ogImageInput = document.getElementById('og_image_seo');

    if (btnOgImage) {
        btnOgImage.addEventListener('click', function () {
            // Signal to the archivos modal which field should receive the selected path
            window._seoOgImageTarget = ogImageInput;
            var modal = document.getElementById('myFiles');
            if (modal) {
                var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
                bsModal.show();
            }
        });
    }
})();
</script>
