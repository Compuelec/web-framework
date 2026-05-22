<?php if ($module->columns[$i]->type_column == "workflow"): ?>

<?php
// Get workflow configuration
require_once __DIR__ . "/../../../../../../../controllers/workflow.controller.php";
$workflow = WorkflowController::getWorkflow($module->id_module);

// If no workflow config exists, create default
if (!$workflow) {
    $default = WorkflowController::getDefaultWorkflow();
    WorkflowController::saveWorkflow($module->id_module, $default['states'], $default['transitions'], $default['settings']);
    $workflow = WorkflowController::getWorkflow($module->id_module);
}

// Get current state value
$currentState = '';
if (!empty($data)) {
    $currentState = urldecode($data[$module->columns[$i]->title_column] ?? '');
}

// If no current state, use initial state from workflow settings
if (empty($currentState) && $workflow && isset($workflow->settings->initial_state)) {
    $currentState = $workflow->settings->initial_state;
}

// Default to 'draft' if still empty
if (empty($currentState)) {
    $currentState = 'draft';
}

// Get state info
$stateInfo = null;
if ($workflow) {
    $stateInfo = WorkflowController::getStateInfo($workflow, $currentState);
}

// Get allowed transitions (only for existing records)
$allowedTransitions = [];
$recordId = '';
if ($workflow && !empty($data) && isset($routesArray[2])) {
    $recordId = base64_decode($routesArray[2]);
    $userRole = $_SESSION["admin"]->rol_admin ?? 'guest';
    $allowedTransitions = WorkflowController::getAllowedTransitions($workflow, $currentState, $userRole);
}

// State colors and labels
$stateColor = $stateInfo->color ?? '#6c757d';
$stateLabel = $stateInfo->label ?? ucfirst($currentState);
?>

<div class="workflow-field" data-module-id="<?php echo $module->id_module ?>">

    <!-- Hidden input for form submission -->
    <input type="hidden"
           name="<?php echo $module->columns[$i]->title_column ?>"
           id="<?php echo $module->columns[$i]->title_column ?>"
           value="<?php echo htmlspecialchars($currentState) ?>">

    <!-- Current State Badge -->
    <div class="mb-3">
        <span class="badge rounded-pill px-3 py-2" style="background-color: <?php echo $stateColor ?>; font-size: 1rem;">
            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
            <?php echo htmlspecialchars($stateLabel) ?>
        </span>
    </div>

    <!-- Transition Buttons (only show when editing existing record) -->
    <?php if (!empty($allowedTransitions) && !empty($data)): ?>
    <div class="workflow-transitions mt-3">
        <small class="text-muted d-block mb-2">
            <i class="bi bi-arrow-right-circle"></i> Acciones disponibles:
        </small>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($allowedTransitions as $transition): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-primary workflow-transition-btn"
                        data-transition-id="<?php echo $transition->id ?>"
                        data-transition-label="<?php echo htmlspecialchars($transition->label) ?>"
                        data-require-comment="<?php echo (isset($transition->require_comment) && $transition->require_comment) ? '1' : '0' ?>"
                        data-table="<?php echo $module->title_module ?>"
                        data-suffix="<?php echo $module->suffix_module ?>"
                        data-record-id="<?php echo $recordId ?>">
                    <i class="bi bi-arrow-right"></i>
                    <?php echo htmlspecialchars($transition->label) ?>
                </button>
            <?php endforeach ?>
        </div>
    </div>
    <?php elseif (empty($data)): ?>
    <div class="alert alert-info py-2 mb-0">
        <small>
            <i class="bi bi-info-circle"></i>
            El registro se creara con estado "<strong><?php echo htmlspecialchars($stateLabel) ?></strong>".
            Las transiciones estaran disponibles despues de guardar.
        </small>
    </div>
    <?php endif ?>

</div>

<?php endif ?>
