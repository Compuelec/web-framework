<?php
/**
 * Workflow Manager - Main View
 * Visual interface for managing workflow states and transitions
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">
                                <i class="bi bi-diagram-3 text-primary me-2"></i>
                                Workflow Manager
                            </h4>
                            <p class="text-muted mb-0">Administra los estados y transiciones de tus workflows</p>
                        </div>
                        <span class="badge bg-primary">v<?php echo $pluginConfig['plugin']['version'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Module Selector -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="bi bi-collection me-2"></i>Modulos con Workflow</h6>
                </div>
                <div class="card-body p-0">
                    <div id="modules-list" class="list-group list-group-flush">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <p class="small text-muted mt-2 mb-0">Cargando modulos...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informacion</h6>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <p class="mb-2"><strong>Estados:</strong> Define los diferentes estados que puede tener un registro.</p>
                        <p class="mb-2"><strong>Transiciones:</strong> Define como se puede pasar de un estado a otro.</p>
                        <p class="mb-0"><strong>Roles:</strong> Controla quien puede ejecutar cada transicion.</p>
                    </small>
                </div>
            </div>
        </div>

        <!-- Workflow Editor -->
        <div class="col-md-8">
            <div id="workflow-editor" style="display: none;">
                <!-- States Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-circle-fill me-2"></i>Estados</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-state">
                            <i class="bi bi-plus-circle me-1"></i>Agregar Estado
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="states-container">
                            <!-- States will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Transitions Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-arrow-right-circle me-2"></i>Transiciones</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-transition">
                            <i class="bi bi-plus-circle me-1"></i>Agregar Transicion
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="transitions-container">
                            <!-- Transitions will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Settings Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Configuracion</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label small">Estado Inicial</label>
                                <select class="form-select form-select-sm" id="initial-state">
                                    <!-- Options will be loaded -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Registrar Transiciones</label>
                                <select class="form-select form-select-sm" id="log-transitions">
                                    <option value="true">Si - Guardar historial</option>
                                    <option value="false">No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="d-grid">
                    <button type="button" class="btn btn-primary btn-lg" id="btn-save-workflow">
                        <i class="bi bi-check-circle me-2"></i>Guardar Workflow
                    </button>
                </div>
            </div>

            <!-- No Module Selected -->
            <div id="no-module-selected" class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-diagram-3 display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">Selecciona un modulo</h5>
                    <p class="text-muted">Elige un modulo de la lista para configurar su workflow</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- State Template -->
<template id="state-template">
    <div class="state-item card mb-2" data-state-index="">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-auto">
                    <input type="color" class="form-control form-control-color state-color" value="#6c757d" title="Color del estado">
                </div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm state-id" placeholder="ID (ej: draft)" required>
                </div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm state-label" placeholder="Etiqueta (ej: Borrador)" required>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-state">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Transition Template -->
<template id="transition-template">
    <div class="transition-item card mb-2" data-transition-index="">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm transition-id" placeholder="ID" required>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm transition-from" multiple title="Desde">
                        <!-- Options will be loaded -->
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm transition-to" title="Hacia">
                        <!-- Options will be loaded -->
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm transition-label" placeholder="Etiqueta" required>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm transition-roles" multiple title="Roles">
                        <!-- Options will be loaded -->
                    </select>
                </div>
                <div class="col-md-1">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input transition-require-comment" title="Requiere comentario">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-transition">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Load Plugin CSS -->
<?php $projectBasePath = dirname($cmsBasePath); ?>
<link rel="stylesheet" href="<?php echo $projectBasePath ?>/plugins/workflow-manager/assets/css/workflow-manager.css">

<!-- Load Plugin JS -->
<script src="<?php echo $projectBasePath ?>/plugins/workflow-manager/assets/js/workflow-manager.js"></script>
