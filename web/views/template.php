<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// Resolve SEO values: page-specific → site-wide defaults → config fallbacks
$_seoMeta       = $seoMeta ?? null;
$_seoSettings   = $seoSettings ?? [];

$_defaultTitle  = $_seoSettings['seo_default_title']       ?? ($pageTitle ?? 'My Website');
$_defaultDesc   = $_seoSettings['seo_default_description'] ?? ($pageDescription ?? '');
$_canonicalBase = rtrim($_seoSettings['seo_canonical_base_url'] ?? '', '/');

$_metaTitle = '';
$_metaDesc  = '';
$_canonical = '';
$_ogTitle   = '';
$_ogDesc    = '';
$_ogImage   = '';
$_ogType    = 'website';
$_ogUrl     = '';

if ($_seoMeta) {
    // Replace %page_title% placeholder in default title template
    $titleTemplate = str_replace('%page_title%', $pageTitle ?? '', $_defaultTitle);

    $_metaTitle = !empty($_seoMeta->meta_title_seo) ? $_seoMeta->meta_title_seo : $titleTemplate;
    $_metaDesc  = !empty($_seoMeta->meta_desc_seo)  ? $_seoMeta->meta_desc_seo  : $_defaultDesc;
    $_ogTitle   = !empty($_seoMeta->og_title_seo)   ? $_seoMeta->og_title_seo   : $_metaTitle;
    $_ogDesc    = !empty($_seoMeta->og_desc_seo)    ? $_seoMeta->og_desc_seo    : $_metaDesc;
    $_ogImage   = $_seoMeta->og_image_seo ?? '';
    $_ogType    = !empty($_seoMeta->og_type_seo)    ? $_seoMeta->og_type_seo    : 'website';

    if ($_canonicalBase && !empty($_seoMeta->slug_seo)) {
        $_canonical = $_canonicalBase . '/' . $_seoMeta->slug_seo;
        $_ogUrl     = $_canonical;
    }
} else {
    $titleTemplate = str_replace('%page_title%', $pageTitle ?? '', $_defaultTitle);
    $_metaTitle = $titleTemplate;
    $_metaDesc  = $_defaultDesc;
}

$_safe = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

// Favicon configured in the CMS → Apariencia (theme_brand_favicon), shared with
// the admin panel. Normal pages already have it in $seoSettings (loaded from
// cms_settings); builder-generated pages don't, so fall back to a single lookup.
$_favicon = $_seoSettings['theme_brand_favicon'] ?? '';
if ($_favicon === '' && class_exists('ApiController')) {
    try {
        $_favResp = ApiController::getByFilter('cms_settings', 'key_setting', 'theme_brand_favicon');
        if (isset($_favResp->status) && $_favResp->status === 200 && !empty($_favResp->results)) {
            $_favicon = $_favResp->results[0]->value_setting ?? '';
        }
    } catch (Throwable $e) { /* no favicon configured */ }
}
?>
    <title><?php echo $_safe($_metaTitle) ?: 'My Website'; ?></title>
    <meta name="description" content="<?php echo $_safe($_metaDesc); ?>">
    <?php if ($_canonical): ?>
    <link rel="canonical" href="<?php echo $_safe($_canonical); ?>">
    <?php endif ?>
    <!-- Open Graph -->
    <meta property="og:title"       content="<?php echo $_safe($_ogTitle ?: $_metaTitle); ?>">
    <meta property="og:description" content="<?php echo $_safe($_ogDesc  ?: $_metaDesc); ?>">
    <meta property="og:type"        content="<?php echo $_safe($_ogType); ?>">
    <?php if ($_ogUrl): ?>
    <meta property="og:url"         content="<?php echo $_safe($_ogUrl); ?>">
    <?php endif ?>
    <?php if ($_ogImage): ?>
    <meta property="og:image"       content="<?php echo $_safe($_ogImage); ?>">
    <?php endif ?>
    
    <?php if ($_favicon): ?>
    <link rel="icon" href="<?php echo $_safe($_favicon); ?>">
    <?php endif ?>

    <!-- Bootstrap CSS -->
    <link href="<?php echo $baseUrl; ?>views/assets/plugins/bootstrap5/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>views/assets/css/style.css">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Shared header (edit via CMS → Páginas Web → Header y Footer). Falls back
         to the default nav below until a custom header is saved. -->
    <?php
    $_headerPartial = __DIR__ . '/../partials/header.php';
    if (file_exists($_headerPartial)) {
        include $_headerPartial;
    } else {
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $baseUrl; ?>">
                <i class="fas fa-home"></i> <?php echo $siteName ?? 'My Website'; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>">Home</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php } ?>

    <!-- Main Content -->
    <main>
        <?php 
        // Include page content
        if (isset($pageContent)) {
            echo $pageContent;
        } elseif (isset($pageFile) && file_exists($pageFile)) {
            include $pageFile;
        }
        ?>
    </main>

    <!-- Shared footer (edit via CMS → Páginas Web → Header y Footer). Falls back
         to the default footer below until a custom footer is saved. -->
    <?php
    $_footerPartial = __DIR__ . '/../partials/footer.php';
    if (file_exists($_footerPartial)) {
        include $_footerPartial;
    } else {
    ?>
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo $siteName ?? 'My Website'; ?></h5>
                    <p class="mb-0">Powered by Dynamic CMS Framework</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> All rights reserved</p>
                </div>
            </div>
        </div>
    </footer>
    <?php } ?>

    <!-- Bootstrap JS -->
    <script src="<?php echo $baseUrl; ?>views/assets/plugins/bootstrap5/bootstrap.bundle.min.js"></script>
    <!-- jQuery (optional, for AJAX) -->
    <script src="<?php echo $baseUrl; ?>views/assets/plugins/jquery/jquery.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo $baseUrl; ?>views/assets/js/main.js"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

