<?php 

if (!empty($routesArray[0])){

    $url = "relations?rel=modules,pages&type=module,page&linkTo=url_page&equalTo=".$routesArray[0];

}else{

     $url = "relations?rel=modules,pages&type=module,page&linkTo=order_page&equalTo=1";
}

$method = "GET";
$fields = array();

$modules = CurlController::request($url,$method,$fields);

if($modules->status == 200){

    $modules = $modules->results;

}else{

    $modules = array();

}

?>
    
<div class="container-fluid py-3 p-lg-4">
          
    <div class="row">

        <?php if (!empty($modules)): ?>

            <?php foreach ($modules as $key => $value): $module = $value ?>

                <!--=========================================
                When the module is a breadcrumb
                ===========================================-->

                <?php if ($module->type_module == "breadcrumbs"): ?>

                    <?php include "breadcrumbs/breadcrumbs.php" ?>
                    
                <?php endif ?>

                <!--=========================================
                When the module is a metric
                ===========================================-->

                <?php if ($module->type_module == "metrics"): ?>

                    <?php include "metrics/metrics.php" ?>
                    
                <?php endif ?>

                <!--=========================================
                When the module is a chart
                ===========================================-->

                <?php if ($module->type_module == "graphics"): ?>

                    <?php include "graphics/graphics.php" ?>
                    
                <?php endif ?>

                <!--=========================================
                When the module is a table
                ===========================================-->

                <?php if ($module->type_module == "tables"): ?>

                    <?php include "tables/tables.php" ?>
                    
                <?php endif ?>

                <!--=========================================
                When the module is custom
                ===========================================-->

                <?php if ($module->type_module == "custom"): ?>

                    <?php 
                        $moduleName = str_replace(" ","_",$module->title_module);
                        $modulePath = __DIR__."/custom/".$moduleName."/".$moduleName.".php";
                        $includePath = "custom/".$moduleName."/".$moduleName.".php";
                        
                        // Include file only if it exists
                        if(file_exists($modulePath)){
                            include $includePath;
                        }else{
                            // File doesn't exist (e.g. a legacy "custom" module whose
                            // PHP file was never created). Show the warning and, for
                            // superadmins, a button to delete the orphan module so the
                            // page is no longer blocked by it.
                            echo '<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0">
                                <div>
                                    <strong>Módulo no encontrado:</strong> El archivo del módulo "'.htmlspecialchars($module->title_module).'" no existe.
                                </div>';

                            if(($_SESSION["admin"]->rol_admin ?? "") == "superadmin"){
                                echo '<button type="button" class="btn btn-sm btn-danger deleteModule" idModule="'.base64_encode($module->id_module).'">
                                    <i class="bi bi-trash me-1"></i>Eliminar módulo
                                </button>';
                            }

                            echo '</div>';
                        }
                    ?>
                    
                <?php endif ?>

                <!--=========================================
                When the module is free HTML/CSS/JS content
                ===========================================-->

                <?php if ($module->type_module == "html"): ?>

                    <?php include "html/html.php" ?>

                <?php endif ?>

            <?php endforeach ?>
            
        <?php endif ?>

        <?php if ($_SESSION["admin"]->rol_admin == "superadmin"): ?>

                <div class="text-center <?php if (!empty($routesArray[1]) && $routesArray[1] == "manage"): ?> d-none  <?php endif ?>">
                
                    <button class="btn btn-default bg-white border rounded btn-sm ms-3 menu-text mt-1 py-2 px-3 myModule" idPage="<?php echo $page->results[0]->id_page ?>">Agregar Módulo</button>

                </div>
        
        <?php endif ?>

    </div>

</div>
